<?php

namespace App\Services\Transports;

use App\Models\CpeDevice;
use App\Services\UspMessageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Usp\Msg;

/**
 * USP WebSocket Transport (MTP) - BBF TR-369 Compliant
 * 
 * Implements WebSocket-based Message Transfer Protocol for USP communication
 * Manages real-time bidirectional messaging with Protocol Buffers encoding
 * Uses Redis queue for message delivery to WebSocket server daemon
 * 
 * Specification: BBF TR-369 Section 4.5 - WebSocket Binding
 */
class UspWebSocketTransport
{
    protected UspMessageService $uspService;
    
    public function __construct(UspMessageService $uspService)
    {
        $this->uspService = $uspService;
    }
    
    /**
     * Send USP message via WebSocket
     * 
     * Queues message in Redis for WebSocket server daemon to deliver
     * 
     * @param CpeDevice $device Target device with websocket_client_id
     * @param Msg $message USP Protocol Buffers message
     * @param string $msgId Message ID for tracking
     * @return array Response data with status
     * @throws \Exception If WebSocket send fails
     */
    public function sendMessage(CpeDevice $device, Msg $message, string $msgId): array
    {
        // Validate WebSocket configuration
        if (empty($device->websocket_client_id)) {
            throw new \Exception('WebSocket client ID not configured for device');
        }
        
        if ($device->mtp_type !== 'websocket') {
            throw new \Exception('Device MTP type is not WebSocket');
        }
        
        // Check if device is connected to WebSocket server
        $clientId = Redis::hget('usp:websocket:connections', $device->id);
        
        if (!$clientId) {
            Log::warning('USP WebSocket: Device not connected', [
                'device_id' => $device->id,
                'websocket_client_id' => $device->websocket_client_id
            ]);
            
            // Update device connection status
            $device->update([
                'websocket_connected_at' => null,
                'last_websocket_ping' => null
            ]);
            
            throw new \Exception('Device not connected to WebSocket server');
        }
        
        // Wrap message in USP Record
        $record = $this->uspService->wrapInRecord(
            $message,
            config('usp.controller_endpoint_id', 'proto::acs-controller-001'),
            $device->usp_endpoint_id ?? 'proto::ws-device-' . $device->id
        );
        
        // Serialize Record to binary Protocol Buffers format
        $binaryPayload = $record->serializeToString();
        
        Log::info('USP WebSocket: Sending message', [
            'device_id' => $device->id,
            'msg_id' => $msgId,
            'client_id' => $clientId,
            'payload_size' => strlen($binaryPayload)
        ]);
        
        try {
            // Queue message for WebSocket server to send
            // Format: usp:websocket:outbound:{client_id}
            $queueKey = "usp:websocket:outbound:{$clientId}";
            Redis::lpush($queueKey, $binaryPayload);
            
            Log::info('USP WebSocket: Message queued successfully', [
                'device_id' => $device->id,
                'msg_id' => $msgId,
                'queue_key' => $queueKey
            ]);
            
            return [
                'msg_id' => $msgId,
                'status' => 'queued',
                'transport' => 'websocket',
                'client_id' => $clientId,
                'queue_key' => $queueKey
            ];
            
        } catch (\Exception $e) {
            Log::error('USP WebSocket: Failed to queue message', [
                'device_id' => $device->id,
                'msg_id' => $msgId,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception("WebSocket queue failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Send USP Get request
     * 
     * @param CpeDevice $device Target device
     * @param array $paramPaths Array of parameter paths to retrieve
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendGetRequest(CpeDevice $device, array $paramPaths, ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createGetMessage($paramPaths, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Send USP Set request
     * 
     * @param CpeDevice $device Target device
     * @param array $updateObjects Object paths with parameters to set
     * @param bool $allowPartial Allow partial updates
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendSetRequest(CpeDevice $device, array $updateObjects, bool $allowPartial = false, ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createSetMessage($updateObjects, $allowPartial, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Send USP Add request
     * 
     * @param CpeDevice $device Target device
     * @param string $objectPath Object path to create
     * @param array $paramSettings Parameters for new object
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendAddRequest(CpeDevice $device, string $objectPath, array $paramSettings, ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createAddMessage($objectPath, $paramSettings, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Send USP Delete request
     * 
     * @param CpeDevice $device Target device
     * @param array $objectPaths Object paths to delete
     * @param bool $allowPartial Allow partial deletion
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendDeleteRequest(CpeDevice $device, array $objectPaths, bool $allowPartial = false, ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createDeleteMessage($objectPaths, $allowPartial, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Send USP Operate request
     * 
     * @param CpeDevice $device Target device
     * @param string $command Command path (e.g., 'Device.Reboot()')
     * @param array $commandArgs Command arguments
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendOperateRequest(CpeDevice $device, string $command, array $commandArgs = [], ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createOperateMessage($command, $commandArgs, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Check if device is connected to WebSocket server
     * 
     * @param CpeDevice $device Target device
     * @return bool True if connected, false otherwise
     */
    public function isDeviceConnected(CpeDevice $device): bool
    {
        if (empty($device->websocket_client_id)) {
            return false;
        }
        
        $clientId = Redis::hget('usp:websocket:connections', $device->id);
        return !empty($clientId);
    }
    
    /**
     * Get connection info for device
     * 
     * @param CpeDevice $device Target device
     * @return array|null Connection info or null if not connected
     */
    public function getConnectionInfo(CpeDevice $device): ?array
    {
        if (!$this->isDeviceConnected($device)) {
            return null;
        }
        
        $clientId = Redis::hget('usp:websocket:connections', $device->id);
        $pingTime = $device->last_websocket_ping;
        $connectedAt = $device->websocket_connected_at;
        
        return [
            'client_id' => $clientId,
            'connected' => true,
            'connected_at' => $connectedAt,
            'last_ping' => $pingTime,
            'queue_key' => "usp:websocket:outbound:{$clientId}"
        ];
    }
    
    /**
     * Send heartbeat/ping to device
     * 
     * @param CpeDevice $device Target device
     * @return array Response data
     */
    public function sendPing(CpeDevice $device): array
    {
        $msgId = $this->generateMessageId();
        
        // Create a simple Get request for Device.DeviceInfo.SoftwareVersion as ping
        $message = $this->uspService->createGetMessage(['Device.DeviceInfo.SoftwareVersion'], $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Generate unique message ID
     * 
     * @return string UUID message ID
     */
    protected function generateMessageId(): string
    {
        return 'msg-ws-' . uniqid() . '-' . bin2hex(random_bytes(4));
    }
    
    /**
     * Parse USP response from WebSocket payload
     * 
     * @param string $binaryPayload Binary Protocol Buffers data
     * @return \Usp_record\Record|null Parsed USP Record or null on error
     */
    public function parseResponse(string $binaryPayload): ?\Usp_record\Record
    {
        try {
            return $this->uspService->deserializeRecord($binaryPayload);
        } catch (\Exception $e) {
            Log::error('USP WebSocket: Failed to parse response', [
                'error' => $e->getMessage(),
                'payload_size' => strlen($binaryPayload)
            ]);
            return null;
        }
    }
}
