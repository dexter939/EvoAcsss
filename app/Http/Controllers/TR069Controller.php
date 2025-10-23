<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CpeDevice;
use App\Models\ProvisioningTask;
use App\Services\TR069SessionManager;
use App\Services\TR069Service;
use App\Services\DataModelMatcher;
use App\Services\Vendor\VendorDetectionService;
use Carbon\Carbon;

/**
 * TR069Controller - Controller per gestione protocollo TR-069 (CWMP)
 * TR069Controller - Controller for TR-069 (CWMP) protocol management
 * 
 * Gestisce le richieste SOAP dai dispositivi CPE secondo lo standard TR-069
 * Handles SOAP requests from CPE devices according to TR-069 standard
 */
class TR069Controller extends Controller
{
    /**
     * Gestisce le richieste SOAP dai dispositivi CPE (Inform + Responses)
     * Handles SOAP requests from CPE devices (Inform + Responses)
     * 
     * Handler generico che gestisce:
     * - Inform: Registrazione dispositivo e inizio sessione
     * - GetParameterValuesResponse: Risposta a GetParameterValues
     * - SetParameterValuesResponse: Risposta a SetParameterValues
     * - RebootResponse: Conferma reboot
     * - TransferComplete: Conferma download firmware completato
     * 
     * Generic handler that processes:
     * - Inform: Device registration and session start
     * - GetParameterValuesResponse: Response to GetParameterValues
     * - SetParameterValuesResponse: Response to SetParameterValues
     * - RebootResponse: Reboot confirmation
     * - TransferComplete: Firmware download completion
     * 
     * @param Request $request Richiesta HTTP con body SOAP XML / HTTP request with SOAP XML body
     * @return \Illuminate\Http\Response Risposta SOAP / SOAP response
     */
    public function handleInform(Request $request)
    {
        // Ottiene il corpo grezzo della richiesta SOAP
        // Gets raw SOAP request body
        $rawBody = $request->getContent();
        
        \Log::info('TR-069 SOAP request received');
        
        // Parsa il messaggio XML SOAP usando DOMDocument (carrier-grade namespace support)
        // Parse SOAP XML message using DOMDocument (carrier-grade namespace support)
        $dom = new \DOMDocument();
        if (!$dom->loadXML($rawBody)) {
            return response('Invalid XML', 400);
        }
        
        // Crea XPath con namespace SOAP e CWMP registrati
        // Create XPath with SOAP and CWMP namespaces registered
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('cwmp', 'urn:dslforum-org:cwmp-1-0');
        
        // Determina tipo di messaggio (Inform o Response)
        // Determine message type (Inform or Response)
        $messageType = $this->detectMessageType($xpath);
        
        \Log::info('TR-069 message type detected', ['type' => $messageType]);
        
        // Se è una risposta, gestisce separatamente
        // If it's a response, handle separately
        if ($messageType !== 'Inform') {
            return $this->handleResponse($request, $xpath);
        }
        
        // È un Inform, processa normalmente
        // It's an Inform, process normally
        $deviceIdNodes = $xpath->query('//cwmp:DeviceId | //DeviceId');
        $deviceId = $deviceIdNodes->length > 0 ? $deviceIdNodes->item(0) : null;
        
        \Log::info('TR-069 DeviceId parsed', ['found' => $deviceId !== null, 'node_count' => $deviceIdNodes->length]);
        
        // ParameterValueStruct può essere con o senza namespace
        $paramList = $xpath->query('//cwmp:ParameterValueStruct | //ParameterValueStruct');
        
        // Variabili per ConnectionRequest (comunicazione bidirezionale ACS->CPE)
        // Variables for ConnectionRequest (bidirectional communication ACS->CPE)
        $connectionRequestUrl = null;
        $connectionRequestUsername = null;
        $connectionRequestPassword = null;
        
        // Cerca ConnectionRequestURL nei parametri dell'Inform
        // Search for ConnectionRequestURL in Inform parameters
        for ($i = 0; $i < $paramList->length; $i++) {
            $param = $paramList->item($i);
            $nameNodes = $param->getElementsByTagName('Name');
            $valueNodes = $param->getElementsByTagName('Value');
            
            $name = $nameNodes->length > 0 ? $nameNodes->item(0)->textContent : '';
            $value = $valueNodes->length > 0 ? $valueNodes->item(0)->textContent : '';
            
            if (str_contains($name, 'ConnectionRequestURL')) {
                $connectionRequestUrl = $value;
            } elseif (str_contains($name, 'ConnectionRequestUsername')) {
                $connectionRequestUsername = $value;
            } elseif (str_contains($name, 'ConnectionRequestPassword')) {
                $connectionRequestPassword = $value;
            }
        }
        
        // Registra o aggiorna il dispositivo CPE nel database
        // Register or update CPE device in database
        $device = null;
        if ($deviceId) {
            // Estrae identificatori dispositivo usando DOMDocument
            // Extract device identifiers using DOMDocument
            $serialNodes = $deviceId->getElementsByTagName('SerialNumber');
            $ouiNodes = $deviceId->getElementsByTagName('OUI');
            $productClassNodes = $deviceId->getElementsByTagName('ProductClass');
            $manufacturerNodes = $deviceId->getElementsByTagName('Manufacturer');
            
            $serialNumber = $serialNodes->length > 0 ? $serialNodes->item(0)->textContent : '';
            $oui = $ouiNodes->length > 0 ? $ouiNodes->item(0)->textContent : '';
            $productClass = $productClassNodes->length > 0 ? $productClassNodes->item(0)->textContent : '';
            $manufacturer = $manufacturerNodes->length > 0 ? $manufacturerNodes->item(0)->textContent : '';
            
            \Log::info('TR-069 Device Info Parsed', [
                'serial' => $serialNumber,
                'oui' => $oui,
                'product_class' => $productClass,
                'manufacturer' => $manufacturer
            ]);
            
            // Estrae SoftwareVersion e HardwareVersion se presenti
            $softwareVersionNodes = $deviceId->getElementsByTagName('SoftwareVersion');
            $hardwareVersionNodes = $deviceId->getElementsByTagName('HardwareVersion');
            $softwareVersion = $softwareVersionNodes->length > 0 ? $softwareVersionNodes->item(0)->textContent : null;
            $hardwareVersion = $hardwareVersionNodes->length > 0 ? $hardwareVersionNodes->item(0)->textContent : null;
            
            // Prepara dati per update/create
            // Prepare data for update/create
            $deviceData = [
                'oui' => $oui,
                'product_class' => $productClass,
                'manufacturer' => $manufacturer,
                'model_name' => $productClass,
                'protocol_type' => 'tr069',
                'ip_address' => $request->ip(),
                'last_inform' => Carbon::now(),
                'last_contact' => Carbon::now(),
                'status' => 'online'
            ];
            
            if ($softwareVersion) {
                $deviceData['software_version'] = $softwareVersion;
            }
            if ($hardwareVersion) {
                $deviceData['hardware_version'] = $hardwareVersion;
            }
            
            // Aggiunge ConnectionRequest info se disponibili
            // Add ConnectionRequest info if available
            if ($connectionRequestUrl) {
                $deviceData['connection_request_url'] = $connectionRequestUrl;
            }
            if ($connectionRequestUsername) {
                $deviceData['connection_request_username'] = $connectionRequestUsername;
            }
            if ($connectionRequestPassword) {
                $deviceData['connection_request_password'] = $connectionRequestPassword;
            }
            
            // Crea o aggiorna dispositivo (upsert per serial_number)
            // Create or update device (upsert by serial_number)
            $device = CpeDevice::updateOrCreate(
                ['serial_number' => $serialNumber],
                $deviceData
            );
            
            \Log::info('Device registered/updated', ['serial' => $serialNumber, 'id' => $device->id]);
            
            // VENDOR AUTO-DETECTION: Auto-detect manufacturer and product from DeviceInfo
            $vendorDetection = new VendorDetectionService();
            $deviceInfo = [
                'Manufacturer' => $manufacturer,
                'ModelName' => $productClass,
                'ManufacturerOUI' => $oui,
                'SoftwareVersion' => $softwareVersion,
                'HardwareVersion' => $hardwareVersion
            ];
            $vendorDetection->updateCpeDeviceVendor($device, $deviceInfo);
            
            // AUTO-MAPPING DATA MODEL: Se il dispositivo non ha un data model assegnato,
            // trova automaticamente il data model più appropriato
            // AUTO-MAPPING DATA MODEL: If device has no data model assigned,
            // automatically find the most appropriate data model
            if (!$device->data_model_id) {
                $dataModelMatcher = new DataModelMatcher();
                $assignedDataModelId = $dataModelMatcher->autoMapDevice(
                    $device->id,
                    $manufacturer,
                    $productClass,
                    $productClass,
                    $softwareVersion,
                    $oui
                );
                
                if ($assignedDataModelId) {
                    $device->refresh();
                    \Log::info('Data model auto-mapped to device', [
                        'device_id' => $device->id,
                        'data_model_id' => $assignedDataModelId,
                        'manufacturer' => $manufacturer,
                        'model' => $productClass
                    ]);
                } else {
                    \Log::warning('Data model auto-mapping failed', [
                        'device_id' => $device->id,
                        'manufacturer' => $manufacturer,
                        'model' => $productClass
                    ]);
                }
            }
            
            // Salva parametri dell'Inform in device_parameters
            // Save Inform parameters to device_parameters
            if ($paramList->length > 0) {
                for ($i = 0; $i < $paramList->length; $i++) {
                    $param = $paramList->item($i);
                    $nameNodes = $param->getElementsByTagName('Name');
                    $valueNodes = $param->getElementsByTagName('Value');
                    
                    if ($nameNodes->length > 0 && $valueNodes->length > 0) {
                        $paramName = $nameNodes->item(0)->textContent;
                        $paramValue = $valueNodes->item(0)->textContent;
                        
                        // Salva o aggiorna parametro
                        \DB::table('device_parameters')->updateOrInsert(
                            [
                                'cpe_device_id' => $device->id,
                                'parameter_path' => $paramName
                            ],
                            [
                                'parameter_value' => $paramValue,
                                'last_updated' => Carbon::now()
                            ]
                        );
                    }
                }
                
                \Log::info('Parameters saved', ['device_id' => $device->id, 'count' => $paramList->length]);
            }
        }
        
        // SESSION MANAGEMENT: Gestisce sessione TR-069
        // SESSION MANAGEMENT: Handle TR-069 session
        $sessionManager = new TR069SessionManager();
        $cookieValue = $request->cookie('TR069SessionID');
        $session = null;
        
        if ($device) {
            // Ottiene o crea sessione per il dispositivo
            // Get or create session for device
            $session = $sessionManager->getOrCreateSession($device, $cookieValue, $request->ip());
            
            // Cerca task di provisioning in coda per questo dispositivo
            // Search for pending provisioning tasks for this device
            $pendingTasks = ProvisioningTask::where('cpe_device_id', $device->id)
                ->where('status', 'pending')
                ->orderBy('scheduled_at', 'asc')
                ->get();
            
            // Accoda comandi SOAP per ogni task pending nella sessione
            // Queue SOAP commands for each pending task in session
            foreach ($pendingTasks as $task) {
                $this->queueTaskCommands($session, $task);
                $task->update(['status' => 'processing']);
            }
            
            // NAT TRAVERSAL: Recovery - resetta comandi stuck in "processing" (sessione interrotta)
            // NAT TRAVERSAL: Recovery - reset commands stuck in "processing" (session interrupted)
            $stuckCommands = \App\Models\PendingCommand::where('cpe_device_id', $device->id)
                ->where('status', 'processing')
                ->where(function($query) {
                    // Fallback a updated_at/executed_at se processing_started_at è NULL (legacy)
                    $query->where('processing_started_at', '<', now()->subMinutes(2))
                          ->orWhere(function($q) {
                              $q->whereNull('processing_started_at')
                                ->where('updated_at', '<', now()->subMinutes(2));
                          });
                })
                ->get();
            
            foreach ($stuckCommands as $stuck) {
                if ($stuck->canRetry()) {
                    $stuck->update([
                        'status' => 'pending',
                        'retry_count' => $stuck->retry_count + 1,
                        'error_message' => 'Session timeout - recovered by watchdog'
                    ]);
                    \Log::warning('TR-069 PendingCommand recovered from stuck processing', [
                        'command_id' => $stuck->id,
                        'retry_count' => $stuck->retry_count
                    ]);
                } else {
                    $stuck->markAsFailed('Max retries reached after session timeouts');
                    \Log::error('TR-069 PendingCommand failed after max retries', [
                        'command_id' => $stuck->id
                    ]);
                }
            }
            
            // NAT TRAVERSAL: Controlla pending commands accodati (quando Connection Request fallisce)
            // NAT TRAVERSAL: Check queued pending commands (when Connection Request fails)
            $pendingCommands = \App\Models\PendingCommand::where('cpe_device_id', $device->id)
                ->where('status', 'pending')
                ->orderBy('priority', 'asc')
                ->orderBy('created_at', 'asc')
                ->limit(5) // Max 5 comandi per sessione per evitare timeout
                ->get();
            
            // Accoda pending commands nella sessione TR-069
            // Queue pending commands in TR-069 session
            foreach ($pendingCommands as $command) {
                $this->queuePendingCommand($session, $command);
                $command->markAsProcessing();
                
                \Log::info('Pending command queued in TR-069 session', [
                    'device' => $device->serial_number,
                    'command_type' => $command->command_type,
                    'command_id' => $command->id,
                    'priority' => $command->priority
                ]);
            }
        }
        
        // Genera risposta SOAP
        // Generate SOAP response
        $response = $this->generateSessionResponse($session);
        
        // Restituisce risposta con cookie di sessione
        // Return response with session cookie
        $httpResponse = response($response, 200)
            ->header('Content-Type', 'text/xml; charset=utf-8')
            ->header('SOAPAction', '');
        
        if ($session) {
            $httpResponse->cookie('TR069SessionID', $session->cookie, 5); // 5 minuti
        }
        
        return $httpResponse;
    }
    
    /**
     * Helper: Aggiorna status di ProvisioningTask o PendingCommand (NAT Traversal)
     * Helper: Update status of ProvisioningTask or PendingCommand (NAT Traversal)
     * 
     * @param string $taskId Task ID (può avere prefisso "pend_" per PendingCommand)
     * @param string $status Status: completed, failed
     * @param array|null $result Result data
     * @return void
     */
    private function updateTaskOrCommand(string $taskId, string $status, ?array $result = null): void
    {
        // Controlla se è un PendingCommand (prefisso "pend_")
        if (str_starts_with($taskId, 'pend_')) {
            $commandId = (int) substr($taskId, 5);
            $command = \App\Models\PendingCommand::find($commandId);
            
            if ($command) {
                if ($status === 'completed') {
                    $command->markAsCompleted($result);
                } else {
                    $command->markAsFailed($result['error'] ?? 'Unknown error');
                }
                \Log::info("TR-069 PendingCommand $status (NAT Traversal)", ['command_id' => $commandId, 'type' => $command->command_type]);
            }
        } else {
            // ProvisioningTask normale
            $task = ProvisioningTask::find($taskId);
            
            if ($task) {
                $task->update([
                    'status' => $status,
                    'result' => $result
                ]);
                \Log::info("TR-069 Task $status", ['task_id' => $taskId]);
            }
        }
    }
    
    /**
     * Genera la risposta SOAP InformResponse
     * Generates SOAP InformResponse
     * 
     * @return string Messaggio SOAP XML / SOAP XML message
     */
    private function generateInformResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:cwmp="urn:dslforum-org:cwmp-1-0">
    <soap:Header>
        <cwmp:ID soap:mustUnderstand="1">1</cwmp:ID>
    </soap:Header>
    <soap:Body>
        <cwmp:InformResponse>
            <MaxEnvelopes>1</MaxEnvelopes>
        </cwmp:InformResponse>
    </soap:Body>
</soap:Envelope>';
    }
    
    /**
     * Accoda comandi SOAP per una task di provisioning nella sessione
     * Queue SOAP commands for provisioning task in session
     * 
     * @param \App\Models\Tr069Session $session Sessione TR-069
     * @param \App\Models\ProvisioningTask $task Task di provisioning
     * @return void
     */
    private function queueTaskCommands($session, $task): void
    {
        $sessionManager = new TR069SessionManager();
        
        switch ($task->task_type) {
            case 'set_parameters':
                $params = $task->task_data['parameters'] ?? [];
                $sessionManager->queueCommand($session, 'SetParameterValues', $params, $task->id);
                break;
                
            case 'get_parameters':
                $params = $task->task_data['parameters'] ?? [];
                $sessionManager->queueCommand($session, 'GetParameterValues', ['parameters' => $params], $task->id);
                break;
                
            case 'reboot':
                $sessionManager->queueCommand($session, 'Reboot', [], $task->id);
                break;
                
            case 'download':
                $params = $task->task_data ?? [];
                // Usa task_id come CommandKey per correlazione deterministica TransferComplete
                // Use task_id as CommandKey for deterministic TransferComplete correlation
                $params['command_key'] = 'task_' . $task->id;
                $sessionManager->queueCommand($session, 'Download', $params, $task->id);
                break;
            
            case 'diagnostic':
                $diagnosticType = $task->task_data['diagnostic_type'] ?? '';
                $params = $task->task_data ?? [];
                $sessionManager->queueCommand($session, 'Diagnostic_' . $diagnosticType, $params, $task->id);
                break;

            case 'network_scan':
                $dataModel = $task->task_data['data_model'] ?? 'tr098';
                $sessionManager->queueCommand($session, 'NetworkScan', ['data_model' => $dataModel], $task->id);
                break;
        }
    }
    
    /**
     * Accoda comandi SOAP per un pending command nella sessione (NAT Traversal)
     * Queue SOAP commands for pending command in session (NAT Traversal)
     * 
     * @param \App\Models\Tr069Session $session Sessione TR-069
     * @param \App\Models\PendingCommand $command Pending command accodato
     * @return void
     */
    private function queuePendingCommand($session, $command): void
    {
        $sessionManager = new TR069SessionManager();
        $params = $command->parameters ?? [];
        
        // Usa prefisso "pend_" per distinguere PendingCommand da ProvisioningTask in handleResponse()
        // Use prefix "pend_" to distinguish PendingCommand from ProvisioningTask in handleResponse()
        $taskId = 'pend_' . $command->id;
        
        switch ($command->command_type) {
            case 'provision':
                // Provisioning: carica configuration profile e imposta parametri
                if (isset($params['profile_id'])) {
                    $profile = \App\Models\ConfigurationProfile::find($params['profile_id']);
                    if ($profile && $profile->configuration_data) {
                        $sessionManager->queueCommand($session, 'SetParameterValues', $profile->configuration_data, $taskId);
                    }
                }
                break;
                
            case 'reboot':
                $sessionManager->queueCommand($session, 'Reboot', [], $taskId);
                break;
                
            case 'get_parameters':
                $parameterNames = $params['parameters'] ?? [];
                $sessionManager->queueCommand($session, 'GetParameterValues', ['parameters' => $parameterNames], $taskId);
                break;
                
            case 'set_parameters':
                $sessionManager->queueCommand($session, 'SetParameterValues', $params, $taskId);
                break;
                
            case 'diagnostic':
                $diagnosticType = $params['diagnostic_type'] ?? '';
                $sessionManager->queueCommand($session, 'Diagnostic_' . $diagnosticType, $params, $taskId);
                break;
                
            case 'firmware_update':
                $params['command_key'] = 'pending_cmd_' . $command->id;
                $sessionManager->queueCommand($session, 'Download', $params, $taskId);
                break;
                
            case 'factory_reset':
                $sessionManager->queueCommand($session, 'FactoryReset', [], $taskId);
                break;
                
            case 'network_scan':
                $dataModel = $params['data_model'] ?? 'tr098';
                $sessionManager->queueCommand($session, 'NetworkScan', ['data_model' => $dataModel], $taskId);
                break;
        }
    }
    
    /**
     * Genera risposta SOAP basata sulla sessione (comando o InformResponse)
     * Generate SOAP response based on session (command or InformResponse)
     * 
     * @param \App\Models\Tr069Session|null $session Sessione TR-069 (opzionale)
     * @return string Messaggio SOAP XML
     */
    private function generateSessionResponse($session): string
    {
        if (!$session) {
            return $this->generateInformResponse();
        }
        
        $sessionManager = new TR069SessionManager();
        
        // Se ci sono comandi pendenti, invia il prossimo comando
        // If there are pending commands, send next command
        if ($sessionManager->hasPendingCommands($session)) {
            $command = $sessionManager->getNextCommand($session);
            
            if ($command) {
                \Log::info('TR-069 sending command to device', [
                    'session_id' => $session->session_id,
                    'command_type' => $command['type'],
                    'task_id' => $command['task_id'] ?? null
                ]);
                
                // Update last_command_sent for response correlation
                $session->update([
                    'last_command_sent' => [
                        'type' => $command['type'],
                        'task_id' => $command['task_id'],
                        'params' => $command['params'],
                        'sent_at' => now()->toIso8601String()
                    ]
                ]);
                
                $tr069Service = new TR069Service();
                $messageId = $session->getNextMessageId();
                
                switch ($command['type']) {
                    case 'GetParameterValues':
                        return $tr069Service->generateGetParameterValuesRequest(
                            $command['params']['parameters'] ?? []
                        );
                        
                    case 'SetParameterValues':
                        return $tr069Service->generateSetParameterValuesRequest(
                            $command['params']
                        );
                        
                    case 'Reboot':
                        return $tr069Service->generateRebootRequest();
                        
                    case 'Download':
                        return $tr069Service->generateDownloadRequest(
                            $command['params']['url'] ?? '',
                            $command['params']['file_type'] ?? '1 Firmware Upgrade Image',
                            $command['params']['file_size'] ?? 0,
                            $messageId,
                            $command['params']['command_key'] ?? ''
                        );
                    
                    case 'Diagnostic_IPPing':
                        return $tr069Service->generateIPPingDiagnosticsRequest(
                            $command['params']['host'] ?? '',
                            $command['params']['count'] ?? 4,
                            $command['params']['timeout'] ?? 1000,
                            $command['params']['packet_size'] ?? 64
                        );
                    
                    case 'Diagnostic_TraceRoute':
                        return $tr069Service->generateTraceRouteDiagnosticsRequest(
                            $command['params']['host'] ?? '',
                            $command['params']['number_of_tries'] ?? 3,
                            $command['params']['timeout'] ?? 5000,
                            $command['params']['data_block_size'] ?? 38,
                            $command['params']['max_hops'] ?? 30
                        );
                    
                    case 'Diagnostic_DownloadDiagnostics':
                        return $tr069Service->generateDownloadDiagnosticsRequest(
                            $command['params']['download_url'] ?? '',
                            $command['params']['test_file_size'] ?? 0
                        );
                    
                    case 'Diagnostic_UploadDiagnostics':
                        return $tr069Service->generateUploadDiagnosticsRequest(
                            $command['params']['upload_url'] ?? '',
                            $command['params']['test_file_size'] ?? 1048576
                        );

                    case 'NetworkScan':
                        return $tr069Service->generateNetworkClientsRequest(
                            $command['params']['data_model'] ?? 'tr098'
                        );
                        
                    default:
                        \Log::warning('TR-069 unknown command type', ['type' => $command['type']]);
                        return $this->generateInformResponse();
                }
            }
        }
        
        // Nessun comando pendente, restituisce InformResponse
        // No pending commands, return InformResponse
        return $this->generateInformResponse();
    }
    
    /**
     * Gestisce richieste vuote dal dispositivo (empty POST)
     * Handles empty requests from device (empty POST)
     * 
     * Richiesta vuota indica la fine della sessione TR-069.
     * Empty request indicates end of TR-069 session.
     * 
     * @param Request $request Richiesta HTTP / HTTP request
     * @return \Illuminate\Http\Response Envelope SOAP vuoto / Empty SOAP envelope
     */
    public function handleEmpty(Request $request)
    {
        \Log::info('TR-069 Empty request received');
        
        // Chiude la sessione se esiste
        // Close session if exists
        $cookieValue = $request->cookie('TR069SessionID');
        if ($cookieValue) {
            $sessionManager = new TR069SessionManager();
            $session = $sessionManager->getSessionByCookie($cookieValue);
            
            if ($session) {
                $sessionManager->closeSession($session);
            }
        }
        
        return response('<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body></soap:Body></soap:Envelope>', 200)
            ->header('Content-Type', 'text/xml; charset=utf-8');
    }
    
    /**
     * Gestisce risposte SOAP dai dispositivi CPE
     * Handles SOAP responses from CPE devices
     * 
     * Gestisce risposte a comandi TR-069:
     * - GetParameterValuesResponse
     * - SetParameterValuesResponse  
     * - RebootResponse
     * - TransferComplete
     * 
     * Handles TR-069 command responses:
     * - GetParameterValuesResponse
     * - SetParameterValuesResponse
     * - RebootResponse
     * - TransferComplete
     * 
     * @param Request $request Richiesta HTTP
     * @param \DOMXPath $xpath XPath object with registered namespaces
     * @return \Illuminate\Http\Response Risposta SOAP
     */
    private function handleResponse(Request $request, $xpath)
    {
        \Log::info('TR-069 Response processing');
        
        // Recupera sessione tramite cookie (supporta sia Laravel cookies che HTTP Cookie header per test)
        $cookieValue = $request->cookie('TR069SessionID');
        
        // Fallback: Parse Cookie header direttamente (per test environment)
        if (!$cookieValue && $request->header('Cookie')) {
            $cookieHeader = $request->header('Cookie');
            if (preg_match('/TR069SessionID=([^;]+)/', $cookieHeader, $matches)) {
                $cookieValue = $matches[1];
            }
        }
        
        \Log::info('TR-069 Response cookie received', ['cookie' => $cookieValue, 'cookie_header' => $request->header('Cookie')]);
        
        $sessionManager = new TR069SessionManager();
        $session = $cookieValue ? $sessionManager->getSessionByCookie($cookieValue) : null;
        
        if ($session) {
            \Log::info('TR-069 Response session found via cookie', ['session_id' => $session->session_id]);
        } else {
            \Log::warning('TR-069 Response session not found via cookie', ['cookie' => $cookieValue]);
        }
        
        // Se non c'è sessione tramite cookie (es. TransferComplete in nuova connessione),
        // cerca dispositivo tramite DeviceId per correlazione alternativa
        // If no session via cookie (e.g., TransferComplete in new connection),
        // find device via DeviceId for alternative correlation
        $device = null;
        if (!$session) {
            $deviceIdNodes = $xpath->query('//cwmp:DeviceId');
            $deviceId = $deviceIdNodes->length > 0 ? $deviceIdNodes->item(0) : null;
            
            if ($deviceId) {
                $serialNodes = $deviceId->getElementsByTagName('SerialNumber');
                $serialNumber = $serialNodes->length > 0 ? $serialNodes->item(0)->textContent : '';
                $device = CpeDevice::where('serial_number', $serialNumber)->first();
                
                if ($device) {
                    \Log::info('TR-069 Response correlated via DeviceId', ['serial_number' => $serialNumber]);
                    
                    // Try to find existing active session for this device (preserves last_command_sent)
                    // Cerca sessione attiva esistente per questo device (preserva last_command_sent)
                    $existingSession = \App\Models\Tr069Session::where('cpe_device_id', $device->id)
                        ->where('status', 'active')
                        ->orderBy('last_activity', 'desc')
                        ->first();
                    
                    if ($existingSession) {
                        $session = $existingSession;
                        \Log::info('TR-069 Response using existing device session', ['session_id' => $session->session_id]);
                    } else {
                        // Crea nuova sessione temporanea solo se non esiste una sessione attiva
                        // Create temporary session only if no active session found
                        $session = $sessionManager->createSession($device, $request->ip());
                        \Log::info('TR-069 Response created temporary session', ['session_id' => $session->session_id]);
                    }
                }
            }
        }
        
        if (!$session) {
            \Log::warning('TR-069 Response without valid session or device correlation');
            return response('No active session', 400);
        }
        
        // Determina tipo di risposta
        $responseType = $this->detectResponseType($xpath);
        
        \Log::info('TR-069 response type detected', ['type' => $responseType]);
        
        // Gestisce risposta in base al tipo usando DOMXPath
        switch ($responseType) {
            case 'GetParameterValuesResponse':
                $this->handleGetParameterValuesResponse($xpath, $session);
                break;
                
            case 'SetParameterValuesResponse':
                $this->handleSetParameterValuesResponse($xpath, $session);
                break;
                
            case 'RebootResponse':
                $this->handleRebootResponse($xpath, $session);
                break;
                
            case 'TransferComplete':
                $this->handleTransferCompleteResponse($xpath, $session);
                // Return TransferCompleteResponse SOAP
                return response($this->generateTransferCompleteResponse(), 200)
                    ->header('Content-Type', 'text/xml; charset=utf-8')
                    ->header('SOAPAction', '');
                break;
                
            default:
                \Log::warning('TR-069 unknown response type');
                break;
        }
        
        // Genera risposta successiva
        $response = $this->generateSessionResponse($session);
        
        return response($response, 200)
            ->header('Content-Type', 'text/xml; charset=utf-8')
            ->header('SOAPAction', '')
            ->cookie('TR069SessionID', $session->cookie, 5);
    }
    
    /**
     * Rileva tipo di messaggio SOAP (Inform o Response)
     * Detect SOAP message type (Inform or Response)
     * 
     * @param \DOMXPath $xpath XPath object with registered namespaces
     * @return string Tipo messaggio
     */
    private function detectMessageType($xpath): string
    {
        // Prima verifica se è un Inform
        if ($xpath->query('//cwmp:Inform')->length > 0) {
            return 'Inform';
        }
        
        // Altrimenti verifica i vari tipi di risposta
        return $this->detectResponseType($xpath);
    }
    
    /**
     * Rileva tipo di risposta SOAP
     * Detect SOAP response type
     * 
     * @param \DOMXPath $xpath XPath object with registered namespaces
     * @return string Tipo risposta
     */
    private function detectResponseType($xpath): string
    {
        if ($xpath->query('//cwmp:GetParameterValuesResponse')->length > 0) {
            return 'GetParameterValuesResponse';
        } elseif ($xpath->query('//cwmp:SetParameterValuesResponse')->length > 0) {
            return 'SetParameterValuesResponse';
        } elseif ($xpath->query('//cwmp:RebootResponse')->length > 0) {
            return 'RebootResponse';
        } elseif ($xpath->query('//cwmp:TransferComplete')->length > 0) {
            return 'TransferComplete';
        } elseif ($xpath->query('//cwmp:DownloadResponse')->length > 0) {
            return 'DownloadResponse';
        }
        
        return 'Unknown';
    }
    
    /**
     * Gestisce GetParameterValuesResponse
     * Handle GetParameterValuesResponse
     * 
     * @param \DOMXPath $xpath XPath object with registered namespaces
     * @param \App\Models\Tr069Session $session Sessione corrente
     * @return void
     */
    private function handleGetParameterValuesResponse($xpath, $session): void
    {
        // Estrae parametri dalla risposta usando DOMXPath (supporta sia namespaced che non-namespaced)
        $paramList = $xpath->query('//cwmp:ParameterValueStruct | //ParameterValueStruct');
        $parameters = [];
        
        foreach ($paramList as $param) {
            $nameNode = $param->getElementsByTagName('Name')->item(0);
            $valueNode = $param->getElementsByTagName('Value')->item(0);
            
            $name = $nameNode ? $nameNode->textContent : '';
            $value = $valueNode ? $valueNode->textContent : '';
            
            if ($name) {
                $parameters[$name] = $value;
            }
        }
        
        \Log::info('TR-069 GetParameterValues response parsed', ['parameters' => $parameters]);
        
        // Salva parametri nel database
        if (!empty($parameters) && $session->cpe_device_id) {
            foreach ($parameters as $path => $value) {
                \DB::table('device_parameters')->updateOrInsert(
                    [
                        'cpe_device_id' => $session->cpe_device_id,
                        'parameter_path' => $path
                    ],
                    [
                        'parameter_value' => $value,
                        'last_updated' => now()
                    ]
                );
            }
        }
        
        // Aggiorna task/command associato se presente
        // NAT TRAVERSAL: Supporta sia ProvisioningTask che PendingCommand
        if ($session->last_command_sent && isset($session->last_command_sent['task_id'])) {
            $taskId = $session->last_command_sent['task_id'];
            
            // Controlla se è un PendingCommand (prefisso "pend_")
            if (str_starts_with($taskId, 'pend_')) {
                $commandId = (int) substr($taskId, 5); // Rimuovi prefisso "pend_"
                $command = \App\Models\PendingCommand::find($commandId);
                
                if ($command) {
                    // Se network_scan, parsa e salva client connessi
                    if ($command->command_type === 'network_scan') {
                        $this->parseAndSaveNetworkClients($command->cpe_device_id, $parameters);
                    }
                    
                    $command->markAsCompleted([
                        'success' => true,
                        'parameters' => $parameters,
                        'completed_at' => now()->toIso8601String()
                    ]);
                    
                    \Log::info('TR-069 PendingCommand completed (NAT Traversal)', ['command_id' => $command->id, 'type' => $command->command_type]);
                }
            } else {
                // ProvisioningTask normale
                $task = ProvisioningTask::find($taskId);
                
                if ($task) {
                    // Se network_scan, parsa e salva client connessi
                    if ($task->task_type === 'network_scan') {
                        $this->parseAndSaveNetworkClients($task->cpe_device_id, $parameters);
                    }
                    
                    $task->update([
                        'status' => 'completed',
                        'result_data' => [
                            'success' => true,
                            'parameters' => $parameters,
                            'completed_at' => now()->toIso8601String()
                        ]
                    ]);
                    
                    \Log::info('TR-069 Task completed', ['task_id' => $task->id, 'type' => $task->task_type]);
                }
            }
        }
    }
    
    /**
     * Parsa parametri TR-069 e salva network clients connessi
     * Parse TR-069 parameters and save connected network clients
     * 
     * @param int $deviceId Device ID
     * @param array $parameters Parametri estratti da GetParameterValuesResponse
     * @return void
     */
    private function parseAndSaveNetworkClients($deviceId, $parameters): void
    {
        if (!$deviceId) {
            \Log::warning('parseAndSaveNetworkClients called with null device_id', [
                'parameters_count' => count($parameters)
            ]);
            return;
        }
        
        $clients = [];
        $currentMacs = [];
        
        // Parse LAN Hosts (TR-098: InternetGatewayDevice.LANDevice.*.Hosts.Host.* o TR-181: Device.Hosts.Host.*)
        foreach ($parameters as $path => $value) {
            // Match Host entries
            if (preg_match('/(?:InternetGatewayDevice\.LANDevice\.\d+\.Hosts\.Host\.|Device\.Hosts\.Host\.)(\d+)\.(.+)/', $path, $matches)) {
                $hostIndex = $matches[1];
                $param = $matches[2];
                
                if (!isset($clients['host_' . $hostIndex])) {
                    $clients['host_' . $hostIndex] = ['type' => 'lan'];
                }
                
                switch ($param) {
                    case 'MACAddress':
                    case 'PhysAddress':
                        $clients['host_' . $hostIndex]['mac'] = strtoupper($value);
                        break;
                    case 'IPAddress':
                        $clients['host_' . $hostIndex]['ip'] = $value;
                        break;
                    case 'HostName':
                        $clients['host_' . $hostIndex]['hostname'] = $value;
                        break;
                    case 'InterfaceType':
                        $clients['host_' . $hostIndex]['interface'] = $value;
                        break;
                    case 'Active':
                        $clients['host_' . $hostIndex]['active'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                }
            }
            
            // Match WiFi Associated Devices (TR-098 o TR-181)
            if (preg_match('/(?:InternetGatewayDevice\.LANDevice\.\d+\.WLANConfiguration\.|Device\.WiFi\.AccessPoint\.)(\d+)\.AssociatedDevice\.(\d+)\.(.+)/', $path, $matches)) {
                $ssidIndex = $matches[1];
                $deviceIndex = $matches[2];
                $param = $matches[3];
                
                $key = 'wifi_' . $ssidIndex . '_' . $deviceIndex;
                
                if (!isset($clients[$key])) {
                    $clients[$key] = ['type' => 'wifi_2.4ghz']; // Default, può essere sovrascritto
                }
                
                switch ($param) {
                    case 'AssociatedDeviceMACAddress':
                    case 'MACAddress':
                    case 'MAC':
                        $clients[$key]['mac'] = strtoupper($value);
                        break;
                    case 'AssociatedDeviceIPAddress':
                    case 'IPAddress':
                    case 'IP':
                        $clients[$key]['ip'] = $value;
                        break;
                    case 'SignalStrength':
                        $clients[$key]['signal_strength'] = (int)$value;
                        break;
                    case 'OperatingFrequencyBand':
                        // Determina banda WiFi (2.4GHz, 5GHz, 6GHz)
                        if (stripos($value, '5') !== false) {
                            $clients[$key]['type'] = 'wifi_5ghz';
                        } elseif (stripos($value, '6') !== false) {
                            $clients[$key]['type'] = 'wifi_6ghz';
                        }
                        break;
                }
            }
        }
        
        // Salva/Aggiorna clients nel database
        foreach ($clients as $client) {
            if (empty($client['mac'])) {
                continue; // Skip se non ha MAC address
            }
            
            $currentMacs[] = $client['mac'];
            
            \App\Models\NetworkClient::updateOrCreate(
                [
                    'device_id' => $deviceId,
                    'mac_address' => $client['mac']
                ],
                [
                    'ip_address' => $client['ip'] ?? null,
                    'hostname' => $client['hostname'] ?? null,
                    'connection_type' => $client['type'] ?? 'lan',
                    'interface_name' => $client['interface'] ?? null,
                    'signal_strength' => $client['signal_strength'] ?? null,
                    'active' => $client['active'] ?? true,
                    'last_seen' => now()
                ]
            );
        }
        
        // Marca come inactive i client che non sono più presenti
        if (!empty($currentMacs)) {
            \App\Models\NetworkClient::where('device_id', $deviceId)
                ->whereNotIn('mac_address', $currentMacs)
                ->update(['active' => false]);
        }
        
        \Log::info('Network clients updated', [
            'device_id' => $deviceId,
            'clients_count' => count($clients),
            'active_macs' => $currentMacs
        ]);
    }
    
    /**
     * Gestisce SetParameterValuesResponse
     * Handle SetParameterValuesResponse
     * 
     * @param \DOMXPath $xpath XPath object with registered namespaces
     * @param \App\Models\Tr069Session $session Sessione corrente
     * @return void
     */
    private function handleSetParameterValuesResponse($xpath, $session): void
    {
        // Estrae status dalla risposta usando DOMXPath (supporta sia namespaced che non-namespaced)
        $statusNode = $xpath->query('//cwmp:Status | //Status')->item(0);
        $statusCode = $statusNode ? (int)$statusNode->textContent : 0;
        
        $success = $statusCode === 0; // 0 = successo in TR-069
        
        \Log::info('TR-069 SetParameterValues response', ['status' => $statusCode, 'success' => $success]);
        
        // Find task via last_command_sent or fallback to latest processing task
        $task = null;
        
        if ($session->last_command_sent && isset($session->last_command_sent['task_id'])) {
            $task = ProvisioningTask::find($session->last_command_sent['task_id']);
        }
        
        // Fallback: find latest processing set_parameters task for this device
        if (!$task) {
            $task = ProvisioningTask::where('cpe_device_id', $session->cpe_device_id)
                ->where('task_type', 'set_parameters')
                ->where('status', 'processing')
                ->orderBy('updated_at', 'desc')
                ->first();
                
            if ($task) {
                \Log::info('TR-069 Task found via fallback (processing set_parameters)', ['task_id' => $task->id]);
            }
        }
        
        if ($task) {
            $task->update([
                'status' => $success ? 'completed' : 'failed',
                'result_data' => [
                    'success' => $success,
                    'status_code' => $statusCode,
                    'completed_at' => now()->toIso8601String()
                ]
            ]);
            
            \Log::info('TR-069 Task updated', ['task_id' => $task->id, 'status' => $success ? 'completed' : 'failed']);
        }
    }
    
    /**
     * Gestisce RebootResponse
     * Handle RebootResponse
     * 
     * @param \DOMXPath $xpath XPath object with registered namespaces
     * @param \App\Models\Tr069Session $session Sessione corrente
     * @return void
     */
    private function handleRebootResponse($xpath, $session): void
    {
        \Log::info('TR-069 Reboot response received');
        
        // Aggiorna task associata
        if ($session->last_command_sent && isset($session->last_command_sent['task_id'])) {
            $task = ProvisioningTask::find($session->last_command_sent['task_id']);
            
            if ($task) {
                $task->update([
                    'status' => 'completed',
                    'result_data' => [
                        'success' => true,
                        'message' => 'Device reboot initiated',
                        'completed_at' => now()->toIso8601String()
                    ]
                ]);
                
                \Log::info('TR-069 Reboot task completed', ['task_id' => $task->id]);
            }
        }
    }
    
    /**
     * Gestisce TransferComplete (callback firmware download)
     * Handle TransferComplete (firmware download callback)
     * 
     * Questo è il callback critico per firmware deployment.
     * Il dispositivo lo invia dopo aver completato il download del firmware.
     * 
     * This is the critical callback for firmware deployment.
     * Device sends it after completing firmware download.
     * 
     * @param \DOMXPath $xpath XPath object with registered namespaces
     * @param \App\Models\Tr069Session $session Sessione corrente
     * @return void
     */
    private function handleTransferCompleteResponse($xpath, $session): void
    {
        // Estrae informazioni da TransferComplete usando DOMXPath (supporta sia namespaced che non-namespaced)
        $commandKeyNode = $xpath->query('//cwmp:CommandKey | //CommandKey')->item(0);
        $faultStructNode = $xpath->query('//cwmp:FaultStruct | //FaultStruct')->item(0);
        $startTimeNode = $xpath->query('//cwmp:StartTime | //StartTime')->item(0);
        $completeTimeNode = $xpath->query('//cwmp:CompleteTime | //CompleteTime')->item(0);
        
        // Parse FaultCode to determine success (FaultCode=0 means success)
        $faultCode = null;
        $faultString = null;
        
        if ($faultStructNode) {
            $faultCodeNode = $faultStructNode->getElementsByTagName('FaultCode')->item(0);
            $faultStringNode = $faultStructNode->getElementsByTagName('FaultString')->item(0);
            $faultCode = $faultCodeNode ? $faultCodeNode->textContent : '';
            $faultString = $faultStringNode ? $faultStringNode->textContent : '';
        }
        
        // Success if no FaultStruct OR FaultCode is 0
        $success = !$faultStructNode || ($faultCode !== null && (string)$faultCode === '0');
        
        $commandKeyStr = $commandKeyNode ? $commandKeyNode->textContent : '';
        $startTimeStr = $startTimeNode ? $startTimeNode->textContent : '';
        $completeTimeStr = $completeTimeNode ? $completeTimeNode->textContent : '';
        
        \Log::info('TR-069 TransferComplete received', [
            'success' => $success,
            'command_key' => $commandKeyStr,
            'fault_code' => $faultCode,
            'fault_string' => $faultString
        ]);
        
        // Trova task da aggiornare tramite CommandKey (metodo deterministico)
        // Find task to update via CommandKey (deterministic method)
        $task = null;
        
        // Metodo 1: Via CommandKey (formato: task_<id>)
        // Method 1: Via CommandKey (format: task_<id>)
        if (str_starts_with($commandKeyStr, 'task_')) {
            $taskId = (int)str_replace('task_', '', $commandKeyStr);
            $task = ProvisioningTask::find($taskId);
            
            if ($task) {
                \Log::info('TR-069 TransferComplete task found via CommandKey', [
                    'task_id' => $task->id,
                    'command_key' => $commandKeyStr
                ]);
            }
        }
        
        // Metodo 2: Tramite sessione last_command_sent (fallback)
        // Method 2: Via session last_command_sent (fallback)
        if (!$task && $session->last_command_sent && isset($session->last_command_sent['task_id'])) {
            $task = ProvisioningTask::find($session->last_command_sent['task_id']);
            
            if ($task) {
                \Log::info('TR-069 TransferComplete task found via session', ['task_id' => $task->id]);
            }
        }
        
        // Metodo 3: Cerca task download per dispositivo SOLO se CommandKey è generico (ultimo resort)
        // Method 3: Find download task by device ONLY if CommandKey is generic (last resort)
        if (!$task && $session->cpe_device_id && !str_starts_with($commandKeyStr, 'task_')) {
            $task = ProvisioningTask::where('cpe_device_id', $session->cpe_device_id)
                ->where('task_type', 'download')
                ->whereIn('status', ['processing', 'pending'])
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($task) {
                \Log::warning('TR-069 TransferComplete task found via device fallback (non-deterministic)', [
                    'task_id' => $task->id,
                    'command_key' => $commandKeyStr
                ]);
            }
        }
        
        if ($task) {
            $task->update([
                'status' => $success ? 'completed' : 'failed',
                'result_data' => [
                    'success' => $success,
                    'command_key' => $commandKeyStr,
                    'start_time' => $startTimeStr,
                    'complete_time' => $completeTimeStr,
                    'fault_code' => $faultCode,
                    'fault_string' => $faultString,
                    'completed_at' => now()->toIso8601String()
                ]
            ]);
            
            // Aggiorna anche FirmwareDeployment se task è di tipo download
            if ($task->task_type === 'download' && isset($task->task_params['deployment_id'])) {
                $deployment = \App\Models\FirmwareDeployment::find($task->task_params['deployment_id']);
                
                if ($deployment) {
                    $deployment->update([
                        'status' => $success ? 'completed' : 'failed',
                        'deployed_at' => now()
                    ]);
                    
                    \Log::info('TR-069 Firmware deployment updated', [
                        'deployment_id' => $deployment->id,
                        'status' => $success ? 'completed' : 'failed'
                    ]);
                }
            }
            
            \Log::info('TR-069 Transfer task completed', [
                'task_id' => $task->id,
                'success' => $success
            ]);
        } else {
            \Log::warning('TR-069 TransferComplete without matching task', [
                'device_id' => $session->cpe_device_id,
                'command_key' => $commandKeyStr
            ]);
        }
    }
    
    /**
     * Genera TransferCompleteResponse SOAP
     * Generate TransferCompleteResponse SOAP
     *
     * @return string SOAP XML
     */
    private function generateTransferCompleteResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:cwmp="urn:dslforum-org:cwmp-1-0">
    <soap:Header>
        <cwmp:ID soap:mustUnderstand="1">' . uniqid() . '</cwmp:ID>
    </soap:Header>
    <soap:Body>
        <cwmp:TransferCompleteResponse>
        </cwmp:TransferCompleteResponse>
    </soap:Body>
</soap:Envelope>';
    }
}
