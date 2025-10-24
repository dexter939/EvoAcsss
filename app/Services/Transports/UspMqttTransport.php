<?php

namespace App\Services\Transports;

use App\Models\CpeDevice;
use App\Services\UspMessageService;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Usp\Msg;

/**
 * USP MQTT Transport (MTP) - BBF TR-369 Compliant
 * 
 * Implements MQTT-based Message Transfer Protocol for USP communication
 * Manages MQTT publish/subscribe with Protocol Buffers encoding
 * 
 * Specification: BBF TR-369 Section 4.4 - MQTT Binding
 * Topic Structure: usp/controller/{endpoint_id} and usp/agent/{endpoint_id}
 */
class UspMqttTransport
{
    protected UspMessageService $uspService;
    protected int $defaultQos = 1; // QoS 1 for at-least-once delivery
    
    public function __construct(UspMessageService $uspService)
    {
        $this->uspService = $uspService;
    }
    
    /**
     * Send USP message via MQTT publish
     * 
     * @param CpeDevice $device Target device with mqtt_client_id
     * @param Msg $message USP Protocol Buffers message
     * @param string $msgId Message ID for tracking
     * @param int $qos MQTT QoS level (0, 1, or 2)
     * @param bool $retain Retain message flag
     * @return array Response data with status
     * @throws \Exception If MQTT publish fails
     */
    public function sendMessage(CpeDevice $device, Msg $message, string $msgId, int $qos = 1, bool $retain = false): array
    {
        // Validate MQTT configuration
        if (empty($device->mqtt_client_id)) {
            throw new \Exception('MQTT client ID not configured for device');
        }
        
        if ($device->mtp_type !== 'mqtt') {
            throw new \Exception('Device MTP type is not MQTT');
        }
        
        // Wrap message in USP Record
        $record = $this->uspService->wrapInRecord(
            $message,
            config('usp.controller_endpoint_id', 'proto::acs-controller-001'),
            $device->usp_endpoint_id ?? 'proto::mqtt-device-' . $device->id
        );
        
        // Serialize Record to binary Protocol Buffers format
        $binaryPayload = $record->serializeToString();
        
        // Build MQTT topic for device subscription
        $topic = $this->buildAgentTopic($device->mqtt_client_id);
        
        Log::info('USP MQTT: Sending message', [
            'device_id' => $device->id,
            'msg_id' => $msgId,
            'topic' => $topic,
            'qos' => $qos,
            'payload_size' => strlen($binaryPayload)
        ]);
        
        try {
            // Publish to MQTT broker
            $mqtt = MQTT::connection();
            $mqtt->publish($topic, $binaryPayload, $qos, $retain);
            
            Log::info('USP MQTT: Message published successfully', [
                'device_id' => $device->id,
                'msg_id' => $msgId,
                'topic' => $topic
            ]);
            
            return [
                'msg_id' => $msgId,
                'status' => 'sent',
                'transport' => 'mqtt',
                'topic' => $topic,
                'qos' => $qos
            ];
            
        } catch (\Exception $e) {
            Log::error('USP MQTT: Publish failed', [
                'device_id' => $device->id,
                'msg_id' => $msgId,
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception("MQTT publish failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Send USP Get request
     * 
     * @param CpeDevice $device Target device
     * @param array $paramPaths Array of parameter paths to retrieve
     * @param string|null $msgId Optional message ID
     * @param int $qos MQTT QoS level
     * @return array Response data
     */
    public function sendGetRequest(CpeDevice $device, array $paramPaths, ?string $msgId = null, int $qos = 1): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createGetMessage($paramPaths, $msgId);
        
        return $this->sendMessage($device, $message, $msgId, $qos);
    }
    
    /**
     * Send USP Set request
     * 
     * @param CpeDevice $device Target device
     * @param array $updateObjects Object paths with parameters to set
     * @param bool $allowPartial Allow partial updates
     * @param string|null $msgId Optional message ID
     * @param int $qos MQTT QoS level
     * @return array Response data
     */
    public function sendSetRequest(CpeDevice $device, array $updateObjects, bool $allowPartial = false, ?string $msgId = null, int $qos = 1): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createSetMessage($updateObjects, $allowPartial, $msgId);
        
        return $this->sendMessage($device, $message, $msgId, $qos);
    }
    
    /**
     * Send USP Add request
     * 
     * @param CpeDevice $device Target device
     * @param string $objectPath Object path to create
     * @param array $paramSettings Parameters for new object
     * @param string|null $msgId Optional message ID
     * @param int $qos MQTT QoS level
     * @return array Response data
     */
    public function sendAddRequest(CpeDevice $device, string $objectPath, array $paramSettings, ?string $msgId = null, int $qos = 1): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createAddMessage($objectPath, $paramSettings, $msgId);
        
        return $this->sendMessage($device, $message, $msgId, $qos);
    }
    
    /**
     * Send USP Delete request
     * 
     * @param CpeDevice $device Target device
     * @param array $objectPaths Object paths to delete
     * @param bool $allowPartial Allow partial deletion
     * @param string|null $msgId Optional message ID
     * @param int $qos MQTT QoS level
     * @return array Response data
     */
    public function sendDeleteRequest(CpeDevice $device, array $objectPaths, bool $allowPartial = false, ?string $msgId = null, int $qos = 1): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createDeleteMessage($objectPaths, $allowPartial, $msgId);
        
        return $this->sendMessage($device, $message, $msgId, $qos);
    }
    
    /**
     * Send USP Operate request
     * 
     * @param CpeDevice $device Target device
     * @param string $command Command path (e.g., 'Device.Reboot()')
     * @param array $commandArgs Command arguments
     * @param string|null $msgId Optional message ID
     * @param int $qos MQTT QoS level
     * @return array Response data
     */
    public function sendOperateRequest(CpeDevice $device, string $command, array $commandArgs = [], ?string $msgId = null, int $qos = 1): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createOperateMessage($command, $commandArgs, $msgId);
        
        return $this->sendMessage($device, $message, $msgId, $qos);
    }
    
    /**
     * Subscribe to controller topic for receiving USP messages from devices
     * 
     * @param callable $messageHandler Callback function(binaryPayload, deviceEndpointId)
     * @param int $qos MQTT QoS level
     * @return void
     */
    public function subscribe(callable $messageHandler, int $qos = 1): void
    {
        try {
            $mqtt = MQTT::connection();
            
            // Subscribe to controller topic where devices publish
            $controllerTopic = $this->buildControllerTopic();
            
            Log::info('USP MQTT: Starting subscription', [
                'topic' => $controllerTopic,
                'qos' => $qos
            ]);
            
            $mqtt->subscribe($controllerTopic, function ($topic, $binaryPayload) use ($messageHandler) {
                Log::info('USP MQTT: Message received', [
                    'topic' => $topic,
                    'size' => strlen($binaryPayload)
                ]);
                
                try {
                    // Extract device endpoint ID from topic
                    $endpointId = $this->extractEndpointIdFromTopic($topic);
                    
                    // Call the message handler
                    $messageHandler($binaryPayload, $endpointId);
                    
                } catch (\Exception $e) {
                    Log::error('USP MQTT: Error processing message', [
                        'topic' => $topic,
                        'error' => $e->getMessage()
                    ]);
                }
            }, $qos);
            
            // Keep connection alive
            $mqtt->loop(true);
            
        } catch (\Exception $e) {
            Log::error('USP MQTT: Subscription error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Build MQTT topic for agent (device) subscription
     * Format: usp/agent/{endpoint_id}/request
     * 
     * @param string $endpointId Device endpoint ID
     * @return string MQTT topic
     */
    protected function buildAgentTopic(string $endpointId): string
    {
        return "usp/agent/{$endpointId}/request";
    }
    
    /**
     * Build MQTT topic for controller subscription
     * Format: usp/controller/+/response (wildcard for all devices)
     * 
     * @return string MQTT topic with wildcard
     */
    protected function buildControllerTopic(): string
    {
        return "usp/controller/+/response";
    }
    
    /**
     * Extract endpoint ID from MQTT topic
     * 
     * @param string $topic MQTT topic path
     * @return string|null Endpoint ID or null if not found
     */
    protected function extractEndpointIdFromTopic(string $topic): ?string
    {
        // Parse topic: usp/controller/{endpoint_id}/response
        $parts = explode('/', $topic);
        return $parts[2] ?? null;
    }
    
    /**
     * Generate unique message ID
     * 
     * @return string UUID message ID
     */
    protected function generateMessageId(): string
    {
        return 'msg-mqtt-' . uniqid() . '-' . bin2hex(random_bytes(4));
    }
    
    /**
     * Parse USP response from MQTT payload
     * 
     * @param string $binaryPayload Binary Protocol Buffers data
     * @return \Usp_record\Record|null Parsed USP Record or null on error
     */
    public function parseResponse(string $binaryPayload): ?\Usp_record\Record
    {
        try {
            return $this->uspService->deserializeRecord($binaryPayload);
        } catch (\Exception $e) {
            Log::error('USP MQTT: Failed to parse response', [
                'error' => $e->getMessage(),
                'payload_size' => strlen($binaryPayload)
            ]);
            return null;
        }
    }
}
