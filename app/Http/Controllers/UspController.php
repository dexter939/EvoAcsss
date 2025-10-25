<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Services\UspMessageService;
use App\Services\Transports\UspMqttTransport;
use App\Models\CpeDevice;
use App\Models\DeviceParameter;
use App\Models\UspPendingRequest;
use App\Models\UspSubscription;
use Illuminate\Support\Facades\Log;

/**
 * USP Controller - TR-369 Protocol Handler
 * 
 * Gestisce i messaggi USP in arrivo da dispositivi TR-369
 * Handles incoming USP messages from TR-369 devices
 */
class UspController extends Controller
{
    protected UspMessageService $uspService;
    protected UspMqttTransport $mqttTransport;

    public function __construct(UspMessageService $uspService, UspMqttTransport $mqttTransport)
    {
        $this->uspService = $uspService;
        $this->mqttTransport = $mqttTransport;
    }

    /**
     * Endpoint principale USP - Riceve USP Records o gestisce polling HTTP
     * Main USP endpoint - Receives USP Records or handles HTTP polling
     * 
     * @param Request $request
     * @return Response|JsonResponse
     */
    public function handleUspMessage(Request $request): Response|JsonResponse
    {
        try {
            // Handle HTTP polling (GET request)
            if ($request->isMethod('GET')) {
                return $this->handleHttpPolling($request);
            }
            
            // Handle USP message (POST request)
            // Get binary payload from request body
            $binaryPayload = $request->getContent();

            if (empty($binaryPayload)) {
                return $this->errorResponse('Empty payload received', 400);
            }

            Log::info('USP Record received', [
                'size' => strlen($binaryPayload),
                'content_type' => $request->header('Content-Type')
            ]);

            // Deserialize USP Record
            $record = $this->uspService->deserializeRecord($binaryPayload);

            // Extract message from record
            $msg = $this->uspService->extractMessageFromRecord($record);

            if (!$msg) {
                return $this->errorResponse('Failed to extract USP message from Record', 400);
            }

            // Get message metadata
            $fromId = $record->getFromId();
            $toId = $record->getToId();
            $msgType = $this->uspService->getMessageType($msg);
            $msgId = $msg->getHeader()->getMsgId();

            Log::info('USP Message extracted', [
                'from' => $fromId,
                'to' => $toId,
                'type' => $msgType,
                'msg_id' => $msgId
            ]);

            // Find or create device
            $device = $this->findOrCreateDevice($fromId, $request);

            // Process message based on type
            $responseMsg = match($msgType) {
                'GET' => $this->handleGet($msg, $device),
                'SET' => $this->handleSet($msg, $device),
                'ADD' => $this->handleAdd($msg, $device),
                'DELETE' => $this->handleDelete($msg, $device),
                'OPERATE' => $this->handleOperate($msg, $device),
                'NOTIFY' => $this->handleNotify($msg, $device),
                'GET_RESP', 'SET_RESP', 'ADD_RESP', 'DELETE_RESP', 'OPERATE_RESP' => 
                    $this->handleResponse($msg, $device),
                default => $this->createErrorMessage($msgId, 9000, "Unsupported message type: {$msgType}")
            };

            // If no response message (e.g., NOTIFY with send_resp=false), return 204 No Content
            if ($responseMsg === null) {
                Log::info('No USP response required', [
                    'from' => $fromId,
                    'type' => $msgType
                ]);
                
                return response('', 204);
            }

            // Wrap response in Record
            $responseRecord = $this->uspService->wrapInRecord(
                $responseMsg,
                $fromId,  // Send back to sender
                $toId,    // From our controller endpoint
                $record->getVersion()
            );

            // Serialize and return
            $responseBinary = $this->uspService->serializeRecord($responseRecord);

            Log::info('USP Response sent', [
                'to' => $fromId,
                'type' => $this->uspService->getMessageType($responseMsg),
                'size' => strlen($responseBinary)
            ]);

            return response($responseBinary, 200)
                ->header('Content-Type', 'application/octet-stream');

        } catch (\Exception $e) {
            Log::error('USP processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Trova o crea un dispositivo USP
     * Find or create USP device
     * 
     * @param string $endpointId USP endpoint ID
     * @param Request $request HTTP request
     * @param string|null $mtpType Optional MTP type (http, mqtt, websocket)
     * @return CpeDevice
     */
    protected function findOrCreateDevice(string $endpointId, Request $request, ?string $mtpType = null): CpeDevice
    {
        $device = CpeDevice::where('usp_endpoint_id', $endpointId)->first();

        if (!$device) {
            // Auto-register new USP device
            // USP devices don't have OUI like TR-069, use default value
            $deviceData = [
                'serial_number' => 'USP-' . substr(md5($endpointId), 0, 10),
                'oui' => '000000', // Default OUI for USP devices
                'product_class' => 'USP Device',
                'protocol_type' => 'tr369',
                'usp_endpoint_id' => $endpointId,
                'ip_address' => $request->ip(),
                'status' => 'online',
                'last_inform' => now(),
                'last_contact' => now(),
            ];
            
            // Set MTP-specific fields
            if ($mtpType === 'mqtt') {
                $deviceData['mtp_type'] = 'mqtt';
                $deviceData['mqtt_client_id'] = 'usp-agent-' . substr(md5($endpointId), 0, 12);
            } elseif ($mtpType === 'websocket') {
                $deviceData['mtp_type'] = 'websocket';
            } else {
                $deviceData['mtp_type'] = 'http'; // Default
            }
            
            $device = CpeDevice::create($deviceData);

            Log::info('New USP device auto-registered', [
                'endpoint_id' => $endpointId,
                'device_id' => $device->id,
                'mtp_type' => $deviceData['mtp_type']
            ]);
        } else {
            // Update last contact and MTP type if specified
            $updateData = [
                'last_contact' => now(),
                'status' => 'online',
                'ip_address' => $request->ip()
            ];
            
            // Update MTP type if specified and different
            if ($mtpType && $device->mtp_type !== $mtpType) {
                $updateData['mtp_type'] = $mtpType;
                
                if ($mtpType === 'mqtt' && empty($device->mqtt_client_id)) {
                    $updateData['mqtt_client_id'] = 'usp-agent-' . substr(md5($endpointId), 0, 12);
                }
            }
            
            $device->update($updateData);
        }

        return $device;
    }

    /**
     * Gestisce richiesta GET
     * Handle GET request
     */
    protected function handleGet($msg, CpeDevice $device)
    {
        $msgId = $msg->getHeader()->getMsgId();
        $get = $msg->getBody()->getRequest()->getGet();
        $paramPaths = $get->getParamPaths();

        Log::info('Processing GET request', [
            'device_id' => $device->id,
            'paths' => iterator_to_array($paramPaths)
        ]);

        // Query device parameters
        $results = [];
        foreach ($paramPaths as $path) {
            // Query from device_parameters table
            $params = DeviceParameter::where('cpe_device_id', $device->id)
                ->where('parameter_path', 'LIKE', $path . '%')
                ->get();

            foreach ($params as $param) {
                $results[$param->parameter_path] = $param->parameter_value;
            }

            // If no results, return mock data for demo
            if (empty($results)) {
                $results[$path . 'Manufacturer'] = $device->manufacturer ?? 'Unknown';
                $results[$path . 'ModelName'] = $device->model_name ?? 'TR-369 Device';
                $results[$path . 'SoftwareVersion'] = $device->software_version ?? '1.0';
            }
        }

        return $this->uspService->createGetResponseMessage($msgId, $results);
    }

    /**
     * Gestisce richiesta SET
     * Handle SET request
     */
    protected function handleSet($msg, CpeDevice $device)
    {
        $msgId = $msg->getHeader()->getMsgId();
        $set = $msg->getBody()->getRequest()->getSet();
        $updateObjs = $set->getUpdateObjs();

        Log::info('Processing SET request', [
            'device_id' => $device->id,
            'obj_count' => count($updateObjs)
        ]);

        $updatedParams = [];
        
        // Process each update object
        foreach ($updateObjs as $updateObj) {
            $objPath = $updateObj->getObjPath();
            $paramSettings = $updateObj->getParamSettings();

            foreach ($paramSettings as $setting) {
                $paramName = $setting->getParam();
                $paramValue = $setting->getValue();

                // Update or create parameter
                DeviceParameter::updateOrCreate(
                    [
                        'cpe_device_id' => $device->id,
                        'parameter_path' => $objPath . $paramName
                    ],
                    [
                        'parameter_value' => $paramValue,
                        'parameter_type' => 'string',
                        'is_writable' => true,
                        'last_update' => now()
                    ]
                );

                Log::info('Parameter updated', [
                    'device_id' => $device->id,
                    'path' => $objPath . $paramName,
                    'value' => $paramValue
                ]);
                
                $updatedParams[$objPath . $paramName] = $paramValue;
            }
        }

        // Return proper SET_RESP message
        return $this->uspService->createSetResponseMessage($msgId, $updatedParams ?? []);
    }

    /**
     * Gestisce richiesta ADD
     * Handle ADD request
     */
    protected function handleAdd($msg, CpeDevice $device)
    {
        $msgId = $msg->getHeader()->getMsgId();
        
        Log::info('Processing ADD request', [
            'device_id' => $device->id
        ]);

        // Return proper ADD_RESP message
        return $this->uspService->createAddResponseMessage($msgId, []);
    }

    /**
     * Gestisce richiesta DELETE
     * Handle DELETE request
     */
    protected function handleDelete($msg, CpeDevice $device)
    {
        $msgId = $msg->getHeader()->getMsgId();
        
        Log::info('Processing DELETE request', [
            'device_id' => $device->id
        ]);

        // Return proper DELETE_RESP message
        return $this->uspService->createDeleteResponseMessage($msgId, []);
    }

    /**
     * Gestisce richiesta OPERATE
     * Handle OPERATE request
     */
    protected function handleOperate($msg, CpeDevice $device)
    {
        $msgId = $msg->getHeader()->getMsgId();
        $operate = $msg->getBody()->getRequest()->getOperate();
        $command = $operate->getCommand();

        Log::info('Processing OPERATE request', [
            'device_id' => $device->id,
            'command' => $command
        ]);

        // Return proper OPERATE_RESP message
        return $this->uspService->createOperateResponseMessage($msgId, $command, []);
    }

    /**
     * Gestisce notifica NOTIFY
     * Handle NOTIFY notification
     */
    protected function handleNotify($msg, CpeDevice $device)
    {
        $msgId = $msg->getHeader()->getMsgId();
        $notify = $msg->getBody()->getRequest()->getNotify();
        
        $subscriptionId = $notify->getSubscriptionId();
        $sendResp = $notify->getSendResp();
        
        // Find subscription in database
        $subscription = UspSubscription::where('cpe_device_id', $device->id)
            ->where('subscription_id', $subscriptionId)
            ->active()
            ->first();
        
        // Extract notification type and data
        $notifType = null;
        $notifData = [];
        
        if ($notify->hasEvent()) {
            $notifType = 'Event';
            $event = $notify->getEvent();
            
            $params = [];
            if ($event->getParams()) {
                foreach ($event->getParams() as $key => $value) {
                    $params[$key] = $value;
                }
            }
            
            $notifData = [
                'obj_path' => $event->getObjPath(),
                'event_name' => $event->getEventName(),
                'params' => $params
            ];
        } elseif ($notify->hasValueChange()) {
            $notifType = 'ValueChange';
            $valueChange = $notify->getValueChange();
            $notifData = [
                'param_path' => $valueChange->getParamPath(),
                'param_value' => $valueChange->getParamValue()
            ];
        } elseif ($notify->hasObjCreation()) {
            $notifType = 'ObjectCreation';
            $objCreation = $notify->getObjCreation();
            $notifData = [
                'obj_path' => $objCreation->getObjPath()
            ];
        } elseif ($notify->hasObjDeletion()) {
            $notifType = 'ObjectDeletion';
            $objDeletion = $notify->getObjDeletion();
            $notifData = [
                'obj_path' => $objDeletion->getObjPath()
            ];
        } elseif ($notify->hasOperComplete()) {
            $notifType = 'OperationComplete';
            $operComplete = $notify->getOperComplete();
            $notifData = [
                'command' => $operComplete->getCommand(),
                'obj_path' => $operComplete->getObjPath()
            ];
        } elseif ($notify->hasOnBoardReq()) {
            $notifType = 'OnBoardRequest';
            $onBoardReq = $notify->getOnBoardReq();
            $notifData = [
                'oui' => $onBoardReq->getOui(),
                'product_class' => $onBoardReq->getProductClass(),
                'serial_number' => $onBoardReq->getSerialNumber()
            ];
        }
        
        Log::info('Processing NOTIFY', [
            'device_id' => $device->id,
            'subscription_id' => $subscriptionId,
            'notif_type' => $notifType,
            'send_resp' => $sendResp,
            'subscription_found' => $subscription ? 'yes' : 'no'
        ]);
        
        // Update subscription if found
        if ($subscription) {
            $subscription->recordNotification();
            
            Log::info('Subscription notification recorded', [
                'subscription_id' => $subscription->id,
                'notification_count' => $subscription->notification_count,
                'event_path' => $subscription->event_path,
                'notif_type' => $notifType,
                'notif_data' => $notifData
            ]);
        }
        
        // Return NOTIFY_RESP only if send_resp is true
        if ($sendResp) {
            return $this->uspService->createNotifyResponseMessage($msgId, $subscriptionId);
        }
        
        // No response required - return null
        return null;
    }

    /**
     * Gestisce messaggi di risposta
     * Handle response messages
     */
    protected function handleResponse($msg, CpeDevice $device)
    {
        $msgId = $msg->getHeader()->getMsgId();
        $msgType = $this->uspService->getMessageType($msg);

        Log::info('Received response message', [
            'device_id' => $device->id,
            'type' => $msgType,
            'msg_id' => $msgId
        ]);

        // Response messages don't need a reply
        // Just log and return simple acknowledgment
        return $this->uspService->createGetResponseMessage($msgId, ['Ack' => 'Received']);
    }

    /**
     * Crea messaggio di errore USP
     * Create USP error message
     */
    protected function createErrorMessage(string $msgId, int $errorCode, string $errorMsg)
    {
        return $this->uspService->createErrorMessage($msgId, $errorCode, $errorMsg);
    }

    /**
     * Gestisce polling HTTP per dispositivi HTTP MTP
     * Handles HTTP polling for HTTP MTP devices
     * 
     * @param Request $request
     * @return Response
     */
    protected function handleHttpPolling(Request $request): Response
    {
        // Get device endpoint ID from header or query parameter
        $endpointId = $request->header('USP-Endpoint-ID') ?? $request->query('endpoint_id');
        
        if (!$endpointId) {
            return response('', 204); // No content if no endpoint ID
        }
        
        // Find device
        $device = CpeDevice::where('usp_endpoint_id', $endpointId)->first();
        
        if (!$device) {
            return response('', 404); // Device not found
        }
        
        // Get oldest pending request for this device
        $pendingRequest = UspPendingRequest::where('cpe_device_id', $device->id)
            ->pending()
            ->oldest()
            ->first();
        
        if (!$pendingRequest) {
            return response('', 204); // No pending requests
        }
        
        // Mark as delivered
        $pendingRequest->markAsDelivered();
        
        Log::info('HTTP polling delivered pending request', [
            'device_id' => $device->id,
            'msg_id' => $pendingRequest->msg_id,
            'message_type' => $pendingRequest->message_type
        ]);
        
        // Return binary payload
        return response($pendingRequest->request_payload, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('USP-Message-ID', $pendingRequest->msg_id);
    }
    
    /**
     * Handle MQTT Publish Bridge
     * 
     * Endpoint for MQTT clients and load testing to publish USP messages
     * Simulates MQTT broker receive and processes the message
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleMqttPublish(Request $request): JsonResponse
    {
        try {
            // Get binary payload or JSON data
            $binaryPayload = $request->getContent();
            
            if (empty($binaryPayload)) {
                return $this->errorResponse('Empty payload received', 400);
            }
            
            Log::info('USP MQTT bridge: Message received', [
                'size' => strlen($binaryPayload),
                'content_type' => $request->header('Content-Type')
            ]);
            
            // Deserialize USP Record
            $record = $this->uspService->deserializeRecord($binaryPayload);
            
            // Extract message from record
            $msg = $this->uspService->extractMessageFromRecord($record);
            
            if (!$msg) {
                return $this->errorResponse('Failed to extract USP message from Record', 400);
            }
            
            // Get message metadata
            $fromId = $record->getFromId();
            $toId = $record->getToId();
            $msgType = $this->uspService->getMessageType($msg);
            $msgId = $msg->getHeader()->getMsgId();
            
            Log::info('USP MQTT bridge: Message extracted', [
                'from' => $fromId,
                'to' => $toId,
                'type' => $msgType,
                'msg_id' => $msgId
            ]);
            
            // Find or create device (MQTT MTP type)
            $device = $this->findOrCreateDevice($fromId, $request, 'mqtt');
            
            // Process message (same logic as HTTP endpoint)
            $responseMsg = match($msgType) {
                'GET' => $this->handleGet($msg, $device),
                'SET' => $this->handleSet($msg, $device),
                'ADD' => $this->handleAdd($msg, $device),
                'DELETE' => $this->handleDelete($msg, $device),
                'OPERATE' => $this->handleOperate($msg, $device),
                'NOTIFY' => $this->handleNotify($msg, $device),
                'GET_RESP', 'SET_RESP', 'ADD_RESP', 'DELETE_RESP', 'OPERATE_RESP' => 
                    $this->handleResponse($msg, $device),
                default => $this->createErrorMessage($msgId, 9000, "Unsupported message type: {$msgType}")
            };
            
            // If response message exists, publish it back via MQTT
            $responsePublished = false;
            $publishError = null;
            
            if ($responseMsg) {
                try {
                    // Publish response via MQTT transport
                    $responseType = $this->uspService->getMessageType($responseMsg);
                    $responseMsgId = $responseMsg->getHeader()->getMsgId();
                    
                    $this->mqttTransport->sendMessage($device, $responseMsg, $responseMsgId);
                    
                    $responsePublished = true;
                    
                    Log::info('USP MQTT bridge: Response published', [
                        'device_id' => $device->id,
                        'msg_id' => $responseMsgId,
                        'type' => $responseType
                    ]);
                    
                } catch (\Exception $e) {
                    $publishError = $e->getMessage();
                    
                    Log::error('USP MQTT bridge: Failed to publish response', [
                        'device_id' => $device->id,
                        'error' => $publishError
                    ]);
                }
            }
            
            // Return JSON response (for load testing confirmation)
            $responseData = [
                'status' => 'success',
                'message' => 'USP message processed via MQTT bridge',
                'msg_id' => $msgId,
                'msg_type' => $msgType,
                'device_id' => $device->id,
                'response_published' => $responsePublished,
                'response_type' => $responseMsg ? $this->uspService->getMessageType($responseMsg) : null
            ];
            
            if ($publishError) {
                $responseData['publish_error'] = $publishError;
            }
            
            return response()->json($responseData, 200);
            
        } catch (\Exception $e) {
            Log::error('USP MQTT bridge: Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Risposta HTTP di errore
     * HTTP error response
     */
    protected function errorResponse(string $message, int $code): JsonResponse
    {
        return response()->json([
            'error' => $message
        ], $code);
    }
}
