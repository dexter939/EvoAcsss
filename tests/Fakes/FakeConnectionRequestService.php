<?php

namespace Tests\Fakes;

use App\Models\CpeDevice;
use Illuminate\Support\Facades\Log;

/**
 * Fake Connection Request Service for testing
 * 
 * Simulates TR-069 connection requests without real HTTP calls
 */
class FakeConnectionRequestService
{
    protected array $connectionRequests = [];
    protected bool $supportedByDefault = true;
    protected bool $successByDefault = true;
    
    public function __construct()
    {
        // No dependencies needed for fake
    }
    
    public function isConnectionRequestSupported(?CpeDevice $device = null): bool
    {
        Log::info('FAKE: Checking if connection request is supported', [
            'device_id' => $device?->id,
            'fake' => true
        ]);
        
        return $this->supportedByDefault;
    }
    
    public function testConnectionRequest(?CpeDevice $device = null): array
    {
        $this->connectionRequests[] = [
            'device_id' => $device?->id,
            'timestamp' => now(),
            'success' => $this->successByDefault
        ];
        
        Log::info('FAKE: Testing connection request', [
            'device_id' => $device?->id,
            'success' => $this->successByDefault,
            'fake' => true
        ]);
        
        return [
            'success' => $this->successByDefault,
            'message' => $this->successByDefault 
                ? 'Connection request sent successfully' 
                : 'Connection request failed',
            'method' => 'GET',
            'status_code' => $this->successByDefault ? 200 : 500,
            'response_time' => 50
        ];
    }
    
    public function sendConnectionRequest(CpeDevice $device): array
    {
        $this->connectionRequests[] = [
            'device_id' => $device->id,
            'timestamp' => now(),
            'success' => $this->successByDefault
        ];
        
        Log::info('FAKE: Sending connection request to device', [
            'device_id' => $device->id,
            'fake' => true
        ]);
        
        return [
            'success' => $this->successByDefault,
            'message' => 'Connection request sent',
            'status_code' => 200
        ];
    }
    
    // Test configuration helpers
    public function setSupported(bool $supported): void
    {
        $this->supportedByDefault = $supported;
    }
    
    public function setSuccess(bool $success): void
    {
        $this->successByDefault = $success;
    }
    
    // Test helpers
    public function getConnectionRequests(): array
    {
        return $this->connectionRequests;
    }
    
    public function clearHistory(): void
    {
        $this->connectionRequests = [];
    }
}
