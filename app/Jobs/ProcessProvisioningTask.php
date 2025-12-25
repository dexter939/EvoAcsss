<?php

namespace App\Jobs;

use App\Models\ProvisioningTask;
use App\Models\CpeDevice;
use App\Services\TR069Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Traits\TenantAwareJob;

/**
 * ProcessProvisioningTask - Job per elaborazione asincrona task provisioning
 * ProcessProvisioningTask - Job for asynchronous provisioning task processing
 * 
 * Questo job elabora task di provisioning TR-069:
 * - GetParameterValues: Lettura parametri dal dispositivo
 * - SetParameterValues: Scrittura parametri sul dispositivo
 * - Reboot: Riavvio remoto dispositivo
 * - Download: Download firmware via TR-069
 * 
 * This job processes TR-069 provisioning tasks:
 * - GetParameterValues: Read parameters from device
 * - SetParameterValues: Write parameters to device
 * - Reboot: Remote device reboot
 * - Download: Firmware download via TR-069
 * 
 * Caratteristiche:
 * - 3 tentativi automatici con delay di 60 secondi / 3 automatic retries with 60s delay
 * - Timeout 120 secondi per richiesta / 120s timeout per request
 * - Invio SOAP request a ConnectionRequestURL del dispositivo / SOAP request sent to device ConnectionRequestURL
 * - Aggiornamento automatico stato FirmwareDeployment per download / Automatic FirmwareDeployment status update for downloads
 */
class ProcessProvisioningTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels, TenantAwareJob;

    /**
     * Numero massimo tentativi / Maximum number of attempts
     */
    public $tries = 3;
    
    /**
     * Timeout job in secondi / Job timeout in seconds
     */
    public $timeout = 120;

    /**
     * Costruttore job
     * Job constructor
     * 
     * @param ProvisioningTask $task Task da elaborare / Task to process
     */
    public function __construct(
        public ProvisioningTask $task
    ) {}

    /**
     * Esegue il job di provisioning
     * Execute provisioning job
     * 
     * Flusso:
     * 1. Verifica task in stato "pending"
     * 2. Aggiorna stato a "processing"
     * 3. Genera richiesta SOAP TR-069 appropriata
     * 4. Invia richiesta a ConnectionRequestURL dispositivo
     * 5. Salva risultato e aggiorna stato task
     * 6. Se download firmware, aggiorna anche stato FirmwareDeployment
     * 
     * Flow:
     * 1. Check task is in "pending" status
     * 2. Update status to "processing"
     * 3. Generate appropriate TR-069 SOAP request
     * 4. Send request to device ConnectionRequestURL
     * 5. Save result and update task status
     * 6. If firmware download, also update FirmwareDeployment status
     */
    public function handle(): void
    {
        // Solo task pending possono essere elaborate
        // Only pending tasks can be processed
        if ($this->task->status !== 'pending') {
            return;
        }

        // I task diagnostici e network_scan vengono processati durante Periodic Inform, non via Connection Request
        // Diagnostic and network_scan tasks are processed during Periodic Inform, not via Connection Request
        if (in_array($this->task->task_type, ['diagnostic', 'network_scan'])) {
            \Log::info('Task will be sent via Periodic Inform', [
                'task_type' => $this->task->task_type,
                'task_id' => $this->task->id,
                'device_id' => $this->task->cpe_device_id
            ]);
            return;
        }

        // Aggiorna stato a processing
        // Update status to processing
        $this->task->update([
            'status' => 'processing',
            'started_at' => now()
        ]);

        try {
            $device = $this->task->cpeDevice;
            
            // Verifica dispositivo e ConnectionRequestURL disponibili
            // Verify device and ConnectionRequestURL available
            if (!$device || !$device->connection_request_url) {
                throw new \Exception('Device not found or connection request URL missing');
            }

            $tr069Service = new TR069Service();
            $soapRequest = null;

            // Genera richiesta SOAP appropriata per tipo task
            // Generate appropriate SOAP request for task type
            switch ($this->task->task_type) {
                case 'get_parameters':
                    $parameters = $this->task->task_data['parameters'] ?? [];
                    $soapRequest = $tr069Service->generateGetParameterValuesRequest($parameters);
                    break;

                case 'set_parameters':
                    $parameters = $this->task->task_data['parameters'] ?? [];
                    $soapRequest = $tr069Service->generateSetParameterValuesRequest($parameters);
                    break;

                case 'reboot':
                    $soapRequest = $tr069Service->generateRebootRequest();
                    break;

                case 'download':
                    $url = $this->task->task_data['url'] ?? '';
                    $fileType = $this->task->task_data['file_type'] ?? '1 Firmware Upgrade Image';
                    $fileSize = $this->task->task_data['file_size'] ?? 0;
                    $soapRequest = $tr069Service->generateDownloadRequest($url, $fileType, $fileSize);
                    break;

                case 'diagnostic':
                    $diagnosticType = $this->task->task_data['diagnostic_type'] ?? '';
                    
                    switch ($diagnosticType) {
                        case 'IPPing':
                            $host = $this->task->task_data['host'] ?? '';
                            $count = $this->task->task_data['count'] ?? 4;
                            $timeout = $this->task->task_data['timeout'] ?? 1000;
                            $packetSize = $this->task->task_data['packet_size'] ?? 64;
                            $soapRequest = $tr069Service->generateIPPingDiagnosticsRequest($host, $count, $timeout, $packetSize);
                            break;
                        
                        case 'TraceRoute':
                            $host = $this->task->task_data['host'] ?? '';
                            $numberOfTries = $this->task->task_data['number_of_tries'] ?? 3;
                            $timeout = $this->task->task_data['timeout'] ?? 5000;
                            $dataBlockSize = $this->task->task_data['data_block_size'] ?? 38;
                            $maxHops = $this->task->task_data['max_hops'] ?? 30;
                            $soapRequest = $tr069Service->generateTraceRouteDiagnosticsRequest($host, $numberOfTries, $timeout, $dataBlockSize, $maxHops);
                            break;
                        
                        case 'DownloadDiagnostics':
                            $url = $this->task->task_data['download_url'] ?? '';
                            $testFileSize = $this->task->task_data['test_file_size'] ?? 0;
                            $soapRequest = $tr069Service->generateDownloadDiagnosticsRequest($url, $testFileSize);
                            break;
                        
                        case 'UploadDiagnostics':
                            $url = $this->task->task_data['upload_url'] ?? '';
                            $testFileSize = $this->task->task_data['test_file_size'] ?? 1048576;
                            $soapRequest = $tr069Service->generateUploadDiagnosticsRequest($url, $testFileSize);
                            break;
                    }
                    break;
            }

            // Invia richiesta SOAP al dispositivo se generata
            // Send SOAP request to device if generated
            if ($soapRequest) {
                $response = Http::withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => ''
                ])
                ->withBasicAuth(
                    $device->connection_request_username ?? '',
                    $device->connection_request_password ?? ''
                )
                ->timeout(30)
                ->send('POST', $device->connection_request_url, [
                    'body' => $soapRequest
                ]);

                // Aggiorna task come completata
                // Update task as completed
                $this->task->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'result_data' => [
                        'response_code' => $response->status(),
                        'response_body' => $response->body()
                    ]
                ]);

                // Se task di download firmware, aggiorna deployment
                // If firmware download task, update deployment
                if ($this->task->task_type === 'download' && isset($this->task->task_data['deployment_id'])) {
                    $deployment = \App\Models\FirmwareDeployment::find($this->task->task_data['deployment_id']);
                    if ($deployment) {
                        $deployment->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'download_progress' => 100
                        ]);
                    }
                }

                // Se task diagnostico, aggiorna DiagnosticTest come in_progress
                // If diagnostic task, update DiagnosticTest as in_progress
                if ($this->task->task_type === 'diagnostic' && isset($this->task->task_data['diagnostic_id'])) {
                    $diagnostic = \App\Models\DiagnosticTest::find($this->task->task_data['diagnostic_id']);
                    if ($diagnostic) {
                        $diagnostic->update([
                            'status' => 'in_progress',
                            'started_at' => now()
                        ]);
                    }
                }

                Log::info('Provisioning task completed', [
                    'task_id' => $this->task->id,
                    'device_id' => $device->id,
                    'type' => $this->task->task_type
                ]);
            }

        } catch (\Exception $e) {
            // Gestione errori con retry logic
            // Error handling with retry logic
            $this->task->increment('retry_count');
            
            // Se raggiunto max retry, segna come failed
            // If max retry reached, mark as failed
            if ($this->task->retry_count >= $this->task->max_retries) {
                $this->task->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now()
                ]);
                
                // Aggiorna anche deployment firmware se fallito
                // Also update firmware deployment if failed
                if ($this->task->task_type === 'download' && isset($this->task->task_data['deployment_id'])) {
                    $deployment = \App\Models\FirmwareDeployment::find($this->task->task_data['deployment_id']);
                    if ($deployment) {
                        $deployment->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'completed_at' => now()
                        ]);
                    }
                }
                
                // Aggiorna anche diagnostic test se fallito
                // Also update diagnostic test if failed
                if ($this->task->task_type === 'diagnostic' && isset($this->task->task_data['diagnostic_id'])) {
                    $diagnostic = \App\Models\DiagnosticTest::find($this->task->task_data['diagnostic_id']);
                    if ($diagnostic) {
                        $diagnostic->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'completed_at' => now()
                        ]);
                    }
                }
            } else {
                // Altrimenti riprova dopo 60 secondi
                // Otherwise retry after 60 seconds
                $this->task->update([
                    'status' => 'pending',
                    'error_message' => $e->getMessage()
                ]);
                
                $this->release(60);
            }

            Log::error('Provisioning task failed', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage(),
                'retry_count' => $this->task->retry_count
            ]);
        }
    }
}
