<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ApiResponse;
use App\Models\CpeDevice;
use App\Models\UspPendingRequest;
use App\Models\UspSubscription;
use App\Services\UspMessageService;
use App\Services\UspMqttService;
use App\Services\UspWebSocketService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * UspController - API Controller for TR-369 USP operations
 * 
 * Provides RESTful API endpoints for managing TR-369 USP devices
 * Supports Get, Set, Operate, Add, and Delete operations via MQTT/HTTP transport
 */
class UspController extends Controller
{
    use ApiResponse;
    protected $uspService;
    protected $mqttService;
    protected $webSocketService;
    
    public function __construct(
        UspMessageService $uspService, 
        UspMqttService $mqttService,
        UspWebSocketService $webSocketService
    ) {
        $this->uspService = $uspService;
        $this->mqttService = $mqttService;
        $this->webSocketService = $webSocketService;
    }
    
    /**
     * Get parameters from USP device
     * 
     * Sends a USP Get message to retrieve parameter values
     * 
     * @param Request $request {paths: array of parameter paths}
     * @param CpeDevice $device Target USP device
     * @return \Illuminate\Http\JsonResponse
     */
    public function getParameters(Request $request, CpeDevice $device)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        // Validate device is online
        if ($device->status !== 'online') {
            return $this->failureResponse('Device must be online', 422);
        }
        
        // Validate WebSocket client ID if using WebSocket transport
        if ($device->mtp_type === 'websocket' && empty($device->websocket_client_id)) {
            return $this->failureResponse('WebSocket client ID not configured', 422);
        }
        
        // Validate HTTP connection URL if using HTTP transport
        if ($device->mtp_type === 'http' && empty($device->connection_request_url)) {
            return $this->failureResponse('HTTP connection URL not configured', 422);
        }
        
        // Validate MQTT client ID if using MQTT transport
        if ($device->mtp_type === 'mqtt' && empty($device->mqtt_client_id)) {
            return $this->failureResponse('MQTT client ID not configured', 422);
        }
        
        // Validate request
        $validated = $request->validate([
            'param_paths' => 'required|array|min:1',
            'param_paths.*' => 'required|string'
        ]);
        
        try {
            $msgId = Str::uuid()->toString();
            
            // Send via appropriate MTP
            if ($device->mtp_type === 'mqtt') {
                $this->mqttService->sendGetRequest($device, $validated['param_paths']);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'status' => 'sent',
                        'transport' => 'mqtt'
                    ]
                ]);
            } elseif ($device->mtp_type === 'websocket') {
                $this->webSocketService->sendGetRequest($device, $validated['param_paths'], $msgId);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'status' => 'sent',
                        'transport' => 'websocket'
                    ]
                ]);
            } else {
                // For HTTP MTP, send USP request to device
                $getMessage = $this->uspService->createGetMessage($validated['param_paths'], $msgId);
                return $this->sendHttpRequest($device, $getMessage, $msgId);
            }
        } catch (\Throwable $e) {
            \Log::error('USP Get request failed: ' . $e->getMessage());
            return $this->failureResponse('Failed to send USP Get request', 500);
        }
    }
    
    /**
     * Set parameters on USP device
     * 
     * Sends a USP Set message to modify parameter values
     * 
     * @param Request $request {parameters: object with param_path => value}
     * @param CpeDevice $device Target USP device
     * @return \Illuminate\Http\JsonResponse
     */
    public function setParameters(Request $request, CpeDevice $device)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        // Validate device is online
        if ($device->status !== 'online') {
            return $this->failureResponse('Device must be online', 422);
        }
        
        // Validate request
        $validated = $request->validate([
            'param_paths' => 'required|array|min:1',
            'allow_partial' => 'boolean'
        ]);
        
        try {
            $msgId = Str::uuid()->toString();
            $allowPartial = $validated['allow_partial'] ?? true;
            
            // Send via appropriate MTP
            if ($device->mtp_type === 'mqtt') {
                $this->mqttService->sendSetRequest($device, $validated['param_paths']);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'status' => 'sent',
                        'transport' => 'mqtt'
                    ]
                ]);
            } elseif ($device->mtp_type === 'websocket') {
                $this->webSocketService->sendSetRequest($device, $validated['param_paths'], $msgId, $allowPartial);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'status' => 'sent',
                        'transport' => 'websocket'
                    ]
                ]);
            } else {
                // For HTTP MTP, send USP request to device
                // Check if payload is already in nested format (values are arrays)
                $firstValue = reset($validated['param_paths']);
                $isNestedFormat = is_array($firstValue);
                
                if ($isNestedFormat) {
                    // Already in updateObjects format, use directly
                    $updateObjects = $validated['param_paths'];
                } else {
                    // Convert from flat to nested format
                    $updateObjects = $this->convertToUpdateObjects($validated['param_paths']);
                }
                
                $setMessage = $this->uspService->createSetMessage($updateObjects, $allowPartial, $msgId);
                return $this->sendHttpRequest($device, $setMessage, $msgId);
            }
        } catch (\Throwable $e) {
            \Log::error('USP Set request failed: ' . $e->getMessage());
            return $this->failureResponse('Failed to send USP Set request', 500);
        }
    }
    
    /**
     * Execute operation on USP device
     * 
     * Sends a USP Operate message to execute a command
     * 
     * @param Request $request {command: string, params: object}
     * @param CpeDevice $device Target USP device
     * @return \Illuminate\Http\JsonResponse
     */
    public function operate(Request $request, CpeDevice $device)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        // Validate device is online
        if ($device->status !== 'online') {
            return $this->failureResponse('Device must be online', 422);
        }
        
        // Validate request
        $validated = $request->validate([
            'command' => 'required|string',
            'command_args' => 'array'
        ]);
        
        try {
            $msgId = Str::uuid()->toString();
            $params = $validated['command_args'] ?? [];
            
            // Send via appropriate MTP
            if ($device->mtp_type === 'mqtt') {
                $this->mqttService->sendOperateRequest($device, $validated['command'], $params);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'command' => $validated['command'],
                        'status' => 'sent',
                        'transport' => 'mqtt'
                    ]
                ]);
            } elseif ($device->mtp_type === 'websocket') {
                $this->webSocketService->sendOperateRequest($device, $validated['command'], $params, $msgId);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'command' => $validated['command'],
                        'status' => 'sent',
                        'transport' => 'websocket'
                    ]
                ]);
            } else {
                // For HTTP MTP, send USP request to device
                $operateMessage = $this->uspService->createOperateMessage($validated['command'], $params, $msgId);
                return $this->sendHttpRequest($device, $operateMessage, $msgId);
            }
        } catch (\Throwable $e) {
            \Log::error('USP Operate request failed: ' . $e->getMessage());
            return $this->failureResponse('Failed to send USP Operate request', 500);
        }
    }
    
    /**
     * Add object instance on USP device
     * 
     * Sends a USP Add message to create a new object instance
     * 
     * @param Request $request {object_path: string, params: object}
     * @param CpeDevice $device Target USP device
     * @return \Illuminate\Http\JsonResponse
     */
    public function addObject(Request $request, CpeDevice $device)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        // Validate device is online
        if ($device->status !== 'online') {
            return $this->failureResponse('Device must be online', 422);
        }
        
        // Validate request
        $validated = $request->validate([
            'object_path' => 'required|string',
            'parameters' => 'array'
        ]);
        
        try {
            $msgId = Str::uuid()->toString();
            
            // Build Add message
            $addMessage = $this->uspService->createAddMessage(
                $validated['object_path'],
                $validated['parameters'] ?? [],
                false,
                $msgId
            );
            
            // Wrap in Record
            $record = $this->uspService->wrapInRecord(
                $addMessage,
                config('usp.controller_endpoint_id'),
                $device->usp_endpoint_id
            );
            
            // Send via appropriate MTP
            if ($device->mtp_type === 'mqtt') {
                $topic = "usp/agent/{$device->usp_endpoint_id}/request";
                $this->mqttService->publish($topic, $record);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'object_path' => $validated['object_path'],
                        'transport' => 'mqtt'
                    ]
                ]);
            } elseif ($device->mtp_type === 'websocket') {
                $this->webSocketService->sendAddRequest($device, $validated['object_path'], $validated['parameters'] ?? [], $msgId, false);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'object_path' => $validated['object_path'],
                        'transport' => 'websocket'
                    ]
                ]);
            } else {
                // For HTTP MTP, send USP request to device
                return $this->sendHttpRequest($device, $addMessage, $msgId);
            }
        } catch (\Throwable $e) {
            \Log::error('USP Add request failed: ' . $e->getMessage());
            return $this->failureResponse('Failed to send USP Add request', 500);
        }
    }
    
    /**
     * Delete object instance on USP device
     * 
     * Sends a USP Delete message to remove an object instance
     * 
     * @param Request $request {object_paths: array of paths to delete}
     * @param CpeDevice $device Target USP device
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteObject(Request $request, CpeDevice $device)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        // Validate device is online
        if ($device->status !== 'online') {
            return $this->failureResponse('Device must be online', 422);
        }
        
        // Validate request
        $validated = $request->validate([
            'object_paths' => 'required|array|min:1',
            'object_paths.*' => 'required|string'
        ]);
        
        try {
            $msgId = Str::uuid()->toString();
            
            // Build Delete message
            $deleteMessage = $this->uspService->createDeleteMessage(
                $validated['object_paths'],
                false,
                $msgId
            );
            
            // Wrap in Record
            $record = $this->uspService->wrapInRecord(
                $deleteMessage,
                config('usp.controller_endpoint_id'),
                $device->usp_endpoint_id
            );
            
            // Send via appropriate MTP
            if ($device->mtp_type === 'mqtt') {
                $topic = "usp/agent/{$device->usp_endpoint_id}/request";
                $this->mqttService->publish($topic, $record);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'deleted_objects' => $validated['object_paths'],
                        'transport' => 'mqtt'
                    ]
                ]);
            } elseif ($device->mtp_type === 'websocket') {
                $this->webSocketService->sendDeleteRequest($device, $validated['object_paths'], $msgId, false);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'deleted_objects' => $validated['object_paths'],
                        'transport' => 'websocket'
                    ]
                ]);
            } else {
                // For HTTP MTP, send USP request to device
                return $this->sendHttpRequest($device, $deleteMessage, $msgId);
            }
        } catch (\Throwable $e) {
            \Log::error('USP Delete request failed: ' . $e->getMessage());
            return $this->failureResponse('Failed to send USP Delete request', 500);
        }
    }
    
    /**
     * Reboot USP device
     * 
     * Sends a USP Operate message with Device.Reboot() command
     * 
     * @param CpeDevice $device Target USP device
     * @return \Illuminate\Http\JsonResponse
     */
    public function reboot(CpeDevice $device)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        // Validate device is online
        if ($device->status !== 'online') {
            return $this->failureResponse('Device must be online', 422);
        }
        
        try {
            $msgId = Str::uuid()->toString();
            
            // Send Operate with Device.Reboot() command
            if ($device->mtp_type === 'mqtt') {
                $this->mqttService->sendOperateRequest($device, 'Device.Reboot()', []);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'status' => 'sent'
                    ]
                ]);
            } elseif ($device->mtp_type === 'websocket') {
                $this->webSocketService->sendOperateRequest($device, 'Device.Reboot()', [], $msgId);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'msg_id' => $msgId,
                        'status' => 'sent',
                        'transport' => 'websocket'
                    ]
                ]);
            } else {
                // For HTTP MTP, send USP request to device
                $operateMessage = $this->uspService->createOperateMessage('Device.Reboot()', [], $msgId);
                return $this->sendHttpRequest($device, $operateMessage, $msgId);
            }
        } catch (\Throwable $e) {
            \Log::error('USP Reboot failed: ' . $e->getMessage());
            return $this->failureResponse('Failed to send USP Reboot command', 500);
        }
    }
    
    /**
     * Store USP request for HTTP MTP devices
     * 
     * Saves the USP message in database for later retrieval by polling devices
     * 
     * @param CpeDevice $device Target device
     * @param string $msgId Message ID
     * @param string $messageType Type of message (get, set, operate, add, delete)
     * @param \Usp\Msg $message Protobuf USP message
     * @return UspPendingRequest
     */
    protected function storePendingRequest(CpeDevice $device, string $msgId, string $messageType, $message)
    {
        // Wrap message in Record
        $record = $this->uspService->wrapInRecord(
            $message,
            config('usp.controller_endpoint_id'),
            $device->usp_endpoint_id
        );
        
        // Serialize Record to binary for storage
        $binaryPayload = $record->serializeToString();
        
        // Store in database with 1 hour expiration
        return UspPendingRequest::create([
            'cpe_device_id' => $device->id,
            'msg_id' => $msgId,
            'message_type' => $messageType,
            'request_payload' => $binaryPayload,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addHour()
        ]);
    }
    
    /**
     * Convert API parameter format to USP updateObjects format
     * 
     * Converts flat parameters like:
     *   ['Device.WiFi.Radio.1.Channel' => '11']
     * To grouped updateObjects like:
     *   ['Device.WiFi.Radio.1.' => ['Channel' => '11']]
     * 
     * @param array $parameters Flat parameter array
     * @return array Grouped updateObjects array
     */
    protected function convertToUpdateObjects(array $parameters): array
    {
        $updateObjects = [];
        
        foreach ($parameters as $fullPath => $value) {
            // Split path into object path and parameter name
            // e.g., "Device.WiFi.Radio.1.Channel" -> "Device.WiFi.Radio.1." + "Channel"
            $lastDotPos = strrpos($fullPath, '.');
            
            if ($lastDotPos !== false) {
                $objectPath = substr($fullPath, 0, $lastDotPos + 1);
                $paramName = substr($fullPath, $lastDotPos + 1);
                
                if (!isset($updateObjects[$objectPath])) {
                    $updateObjects[$objectPath] = [];
                }
                
                $updateObjects[$objectPath][$paramName] = $value;
            } else {
                // If no dot found, treat entire path as parameter under root
                $updateObjects['Device.'][$fullPath] = $value;
            }
        }
        
        return $updateObjects;
    }
    
    /**
     * Create event subscription on USP device
     * 
     * Sends a USP Subscribe message (ADD to Device.LocalAgent.Subscription.{i}.)
     * 
     * @param Request $request {event_path, reference_list (optional), notification_retry}
     * @param CpeDevice $device Target USP device
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSubscription(Request $request, CpeDevice $device)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        // Validate device is online
        if ($device->status !== 'online') {
            return $this->failureResponse('Device must be online', 422);
        }
        
        // Validate request
        $validated = $request->validate([
            'subscription_id' => 'required|string',
            'notification_type' => ['required', 'string', \Illuminate\Validation\Rule::in([
                'ValueChange',
                'Event',
                'ObjectCreation',
                'ObjectDeletion',
                'OperationComplete'
            ])],
            'reference_list' => 'required|array',
            'reference_list.*' => 'string',
            'enabled' => 'boolean',
            'persistent' => 'boolean'
        ]);
        
        try {
            $msgId = Str::uuid()->toString();
            $subscriptionId = $validated['subscription_id'];
            
            // Create subscription record
            $subscription = UspSubscription::create([
                'cpe_device_id' => $device->id,
                'subscription_id' => $subscriptionId,
                'notification_type' => $validated['notification_type'],
                'reference_list' => $validated['reference_list'],
                'enabled' => $validated['enabled'] ?? true,
                'persistent' => $validated['persistent'] ?? true
            ]);
            
            // Send via appropriate MTP (simplified for now - actual USP message sending can be added)
            return response()->json([
                'success' => true,
                'data' => [
                    'subscription_id' => $subscriptionId,
                    'msg_id' => $msgId,
                    'status' => 'created'
                ]
            ], 201);
        } catch (\Exception $e) {
            \Log::error('USP create subscription failed: ' . $e->getMessage());
            return $this->failureResponse('Failed to create subscription', 500);
        }
    }
    
    /**
     * List all subscriptions for a device
     * 
     * @param CpeDevice $device Target USP device
     * @return \Illuminate\Http\JsonResponse
     */
    public function listSubscriptions(CpeDevice $device)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        $subscriptions = UspSubscription::where('cpe_device_id', $device->id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'data' => $subscriptions
        ]);
    }
    
    /**
     * Delete subscription from USP device
     * 
     * Sends a USP Delete message to remove subscription
     * 
     * @param CpeDevice $device Target USP device
     * @param UspSubscription $subscription Target subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSubscription(CpeDevice $device, UspSubscription $subscription)
    {
        // Validate device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return $this->failureResponse('Device must support TR-369 USP protocol', 422);
        }
        
        // Validate subscription belongs to device
        if ($subscription->cpe_device_id !== $device->id) {
            return $this->failureResponse('Subscription does not belong to this device', 403);
        }
        
        try {
            $msgId = Str::uuid()->toString();
            
            // Soft delete subscription
            $subscription->delete();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'msg_id' => $msgId,
                    'subscription_id' => $subscription->subscription_id
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('USP delete subscription failed: ' . $e->getMessage());
            return $this->failureResponse('Failed to delete subscription', 500);
        }
    }
    
    private function sendHttpRequest(CpeDevice $device, $message, string $msgId)
    {
        // Wrap message in USP Record and serialize to protobuf
        $record = $this->uspService->wrapInRecord($message, $device->usp_endpoint_id, $msgId);
        $serializedRecord = $record->serializeToString();
        
        // Use withBody() + send('POST') to transmit raw binary data
        // Note: Using ->post() after ->withBody() can override the binary payload
        $httpResponse = Http::withHeaders([
            'Content-Type' => 'application/vnd.bbf.usp.msg',
            'Accept' => 'application/vnd.bbf.usp.msg'
        ])
        ->withBody($serializedRecord, 'application/vnd.bbf.usp.msg')
        ->timeout(30)
        ->send('POST', $device->connection_request_url);
        
        if (!$httpResponse->successful()) {
            \Log::error('USP HTTP request failed', [
                'device_id' => $device->id,
                'status' => $httpResponse->status(),
                'url' => $device->connection_request_url
            ]);
            return $this->failureResponse('Failed to send HTTP request to device', 500);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'msg_id' => $msgId,
                'status' => 'sent',
                'transport' => 'http'
            ]
        ]);
    }
}
