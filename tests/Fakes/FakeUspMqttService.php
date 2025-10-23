<?php

namespace Tests\Fakes;

use App\Models\CpeDevice;
use Illuminate\Support\Facades\Log;

/**
 * Fake MQTT Service for testing
 * 
 * Simulates MQTT behavior without actual broker connections
 * Does NOT extend the real service to avoid connection attempts
 */
class FakeUspMqttService
{
    protected array $publishedMessages = [];
    protected array $sentRequests = [];
    
    public function __construct()
    {
        // No dependencies needed for fake
    }
    
    public function publish(string $topic, $record): bool
    {
        $this->publishedMessages[] = [
            'topic' => $topic,
            'record' => $record,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Record published via MQTT', [
            'topic' => $topic,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function publishToDevice(CpeDevice $device, string $uspRecordBinary): bool
    {
        $this->publishedMessages[] = [
            'device_id' => $device->id,
            'mqtt_client_id' => $device->mqtt_client_id,
            'binary' => $uspRecordBinary,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Record published to device via MQTT', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendGetRequest(CpeDevice $device, array $paths): bool
    {
        $this->sentRequests[] = [
            'type' => 'Get',
            'device_id' => $device->id,
            'paths' => $paths,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Get request sent via MQTT', [
            'device_id' => $device->id,
            'paths' => $paths,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendSetRequest(CpeDevice $device, array $parameters): bool
    {
        $this->sentRequests[] = [
            'type' => 'Set',
            'device_id' => $device->id,
            'parameters' => $parameters,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Set request sent via MQTT', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendOperateRequest(CpeDevice $device, string $command, array $args = []): bool
    {
        $this->sentRequests[] = [
            'type' => 'Operate',
            'device_id' => $device->id,
            'command' => $command,
            'args' => $args,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Operate request sent via MQTT', [
            'device_id' => $device->id,
            'command' => $command,
            'fake' => true
        ]);
        
        return true;
    }
    
    public function sendSubscriptionRequest(CpeDevice $device, string $subscriptionPath, array $subscriptionParams): bool
    {
        $this->sentRequests[] = [
            'type' => 'Subscribe',
            'device_id' => $device->id,
            'subscription_path' => $subscriptionPath,
            'params' => $subscriptionParams,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: USP Subscription request sent via MQTT', [
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
        
        Log::info('FAKE: USP Delete request sent via MQTT', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return true;
    }
    
    // Test helpers
    public function getPublishedMessages(): array
    {
        return $this->publishedMessages;
    }
    
    public function getSentRequests(): array
    {
        return $this->sentRequests;
    }
    
    public function clearHistory(): void
    {
        $this->publishedMessages = [];
        $this->sentRequests = [];
    }
    
    // Topic building methods used by tests - delegate to real service for correctness
    public function buildTopicForDevice($deviceOrEndpointId): string
    {
        $endpointId = $deviceOrEndpointId instanceof \App\Models\CpeDevice 
            ? $deviceOrEndpointId->usp_endpoint_id 
            : $deviceOrEndpointId;
            
        return "usp/request/{$endpointId}";
    }
    
    public function buildResponseTopic($deviceOrEndpointId): string
    {
        $endpointId = $deviceOrEndpointId instanceof \App\Models\CpeDevice 
            ? $deviceOrEndpointId->usp_endpoint_id 
            : $deviceOrEndpointId;
            
        return "usp/response/{$endpointId}";
    }
    
    public function extractEndpointIdFromTopic(string $topic): string
    {
        // Extract endpoint ID from topics like "usp/request/proto::device-001"
        $parts = explode('/', $topic);
        return $parts[2] ?? '';
    }
}
