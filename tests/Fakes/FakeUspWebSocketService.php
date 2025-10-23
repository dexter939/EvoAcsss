<?php

namespace Tests\Fakes;

use App\Models\CpeDevice;
use Illuminate\Support\Facades\Log;

/**
 * Fake WebSocket Service for testing
 * 
 * Simulates WebSocket behavior without actual connections
 * Does NOT extend the real service to avoid connection attempts
 */
class FakeUspWebSocketService
{
    protected array $sentMessages = [];
    protected array $sentRequests = [];
    
    public function __construct()
    {
        // No dependencies needed for fake
    }
    
    public function sendToDevice(CpeDevice $device, string $message): bool
    {
        $this->sentMessages[] = [
            'device_id' => $device->id,
            'websocket_client_id' => $device->websocket_client_id,
            'message' => $message,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: Message sent via WebSocket', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendGetRequest(CpeDevice $device, array $paths, string $msgId): bool
    {
        $this->sentRequests[] = [
            'type' => 'Get',
            'device_id' => $device->id,
            'paths' => $paths,
            'msg_id' => $msgId,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Get request sent via WebSocket', [
            'device_id' => $device->id,
            'paths' => $paths,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendSetRequest(CpeDevice $device, array $parameters, string $msgId, bool $allowPartial = true): bool
    {
        $this->sentRequests[] = [
            'type' => 'Set',
            'device_id' => $device->id,
            'parameters' => $parameters,
            'msg_id' => $msgId,
            'allow_partial' => $allowPartial,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Set request sent via WebSocket', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendOperateRequest(CpeDevice $device, string $command, array $args, string $msgId): bool
    {
        $this->sentRequests[] = [
            'type' => 'Operate',
            'device_id' => $device->id,
            'command' => $command,
            'args' => $args,
            'msg_id' => $msgId,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Operate request sent via WebSocket', [
            'device_id' => $device->id,
            'command' => $command,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendAddRequest(CpeDevice $device, string $objectPath, array $parameters, string $msgId): bool
    {
        $this->sentRequests[] = [
            'type' => 'Add',
            'device_id' => $device->id,
            'object_path' => $objectPath,
            'parameters' => $parameters,
            'msg_id' => $msgId,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Add request sent via WebSocket', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendDeleteRequest(CpeDevice $device, array $objectPaths, string $msgId): bool
    {
        $this->sentRequests[] = [
            'type' => 'Delete',
            'device_id' => $device->id,
            'object_paths' => $objectPaths,
            'msg_id' => $msgId,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Delete request sent via WebSocket', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendSubscriptionRequest(CpeDevice $device, array $subscriptionData, string $msgId): bool
    {
        $this->sentRequests[] = [
            'type' => 'Subscribe',
            'device_id' => $device->id,
            'subscription_data' => $subscriptionData,
            'msg_id' => $msgId,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Subscription request sent via WebSocket', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return true;
    }
    
    // Test helpers
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }
    
    public function getSentRequests(): array
    {
        return $this->sentRequests;
    }
    
    public function clearHistory(): void
    {
        $this->sentMessages = [];
        $this->sentRequests = [];
    }
    
    // Connection tracking methods used by tests
    public function isDeviceConnected(CpeDevice $device): bool
    {
        Log::info('FAKE: Checking if device is connected', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return false; // In test environment, devices are not actually connected
    }
    
    public function getConnectedDevices(): array
    {
        Log::info('FAKE: Getting connected devices list', [
            'fake' => true
        ]);
        
        return []; // In test environment, no real connections
    }
}
