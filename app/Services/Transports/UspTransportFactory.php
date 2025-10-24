<?php

namespace App\Services\Transports;

use App\Models\CpeDevice;
use App\Services\UspMessageService;
use Illuminate\Support\Facades\Log;

/**
 * USP Transport Factory
 * 
 * Factory pattern for creating appropriate USP Transport instances
 * based on device MTP (Message Transfer Protocol) type
 * 
 * Automatically selects between HTTP, MQTT, WebSocket, or XMPP transports
 */
class UspTransportFactory
{
    protected UspMessageService $uspService;
    
    public function __construct(UspMessageService $uspService)
    {
        $this->uspService = $uspService;
    }
    
    /**
     * Create transport instance for device based on mtp_type
     * 
     * @param CpeDevice $device Device with mtp_type field
     * @return UspHttpTransport|UspMqttTransport|UspWebSocketTransport
     * @throws \Exception If transport type is unsupported
     */
    public function createForDevice(CpeDevice $device)
    {
        $mtpType = strtolower($device->mtp_type ?? '');
        
        Log::debug('USP Transport Factory: Creating transport', [
            'device_id' => $device->id,
            'mtp_type' => $mtpType
        ]);
        
        return match($mtpType) {
            'http' => $this->createHttpTransport(),
            'mqtt' => $this->createMqttTransport(),
            'websocket' => $this->createWebSocketTransport(),
            'xmpp' => throw new \Exception('XMPP transport not yet implemented in factory'),
            default => throw new \Exception("Unsupported MTP type: {$mtpType}")
        };
    }
    
    /**
     * Create HTTP transport instance
     * 
     * @return UspHttpTransport
     */
    public function createHttpTransport(): UspHttpTransport
    {
        return new UspHttpTransport($this->uspService);
    }
    
    /**
     * Create MQTT transport instance
     * 
     * @return UspMqttTransport
     */
    public function createMqttTransport(): UspMqttTransport
    {
        return new UspMqttTransport($this->uspService);
    }
    
    /**
     * Create WebSocket transport instance
     * 
     * @return UspWebSocketTransport
     */
    public function createWebSocketTransport(): UspWebSocketTransport
    {
        return new UspWebSocketTransport($this->uspService);
    }
    
    /**
     * Check if device has valid transport configuration
     * 
     * @param CpeDevice $device Device to check
     * @return array Validation result with status and message
     */
    public function validateDeviceTransport(CpeDevice $device): array
    {
        $mtpType = strtolower($device->mtp_type ?? '');
        
        // Check if device is TR-369
        if ($device->protocol_type !== 'tr369') {
            return [
                'valid' => false,
                'message' => 'Device must support TR-369 USP protocol',
                'code' => 'invalid_protocol'
            ];
        }
        
        // Check if device is online
        if ($device->status !== 'online') {
            return [
                'valid' => false,
                'message' => 'Device must be online',
                'code' => 'device_offline'
            ];
        }
        
        // Validate based on transport type
        switch ($mtpType) {
            case 'http':
                if (empty($device->connection_request_url)) {
                    return [
                        'valid' => false,
                        'message' => 'HTTP connection URL not configured',
                        'code' => 'missing_http_url'
                    ];
                }
                break;
                
            case 'mqtt':
                if (empty($device->mqtt_client_id)) {
                    return [
                        'valid' => false,
                        'message' => 'MQTT client ID not configured',
                        'code' => 'missing_mqtt_client_id'
                    ];
                }
                break;
                
            case 'websocket':
                if (empty($device->websocket_client_id)) {
                    return [
                        'valid' => false,
                        'message' => 'WebSocket client ID not configured',
                        'code' => 'missing_websocket_client_id'
                    ];
                }
                break;
                
            case 'xmpp':
                return [
                    'valid' => false,
                    'message' => 'XMPP transport not yet fully implemented',
                    'code' => 'xmpp_not_implemented'
                ];
                
            default:
                return [
                    'valid' => false,
                    'message' => "Unsupported MTP type: {$mtpType}",
                    'code' => 'unsupported_mtp_type'
                ];
        }
        
        return [
            'valid' => true,
            'message' => 'Transport configuration valid',
            'transport' => $mtpType
        ];
    }
    
    /**
     * Send USP message using appropriate transport for device
     * 
     * Convenience method that creates transport and sends message
     * 
     * @param CpeDevice $device Target device
     * @param \Usp\Msg $message USP message to send
     * @param string $msgId Message ID
     * @return array Response data
     * @throws \Exception If validation or send fails
     */
    public function sendMessage(CpeDevice $device, $message, string $msgId): array
    {
        // Validate device transport configuration
        $validation = $this->validateDeviceTransport($device);
        
        if (!$validation['valid']) {
            throw new \Exception($validation['message'], $validation['code'] === 'device_offline' ? 503 : 422);
        }
        
        // Create appropriate transport
        $transport = $this->createForDevice($device);
        
        // Send message
        return $transport->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Get supported transport types
     * 
     * @return array List of supported MTP types
     */
    public static function getSupportedTransports(): array
    {
        return [
            'http' => [
                'name' => 'HTTP',
                'description' => 'HTTP-based MTP with POST requests',
                'supported' => true
            ],
            'mqtt' => [
                'name' => 'MQTT',
                'description' => 'MQTT broker-based publish/subscribe',
                'supported' => true
            ],
            'websocket' => [
                'name' => 'WebSocket',
                'description' => 'Real-time bidirectional WebSocket',
                'supported' => true
            ],
            'xmpp' => [
                'name' => 'XMPP',
                'description' => 'XMPP messaging protocol',
                'supported' => false
            ]
        ];
    }
}
