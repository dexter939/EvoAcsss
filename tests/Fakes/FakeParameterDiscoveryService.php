<?php

namespace Tests\Fakes;

use App\Models\CpeDevice;
use App\Models\ProvisioningTask;
use Illuminate\Support\Facades\Log;

/**
 * Fake Parameter Discovery Service for testing
 * 
 * Simulates TR-069 parameter discovery without real CWMP calls
 */
class FakeParameterDiscoveryService
{
    protected array $discoveryRequests = [];
    
    public function __construct()
    {
        // No dependencies needed for fake
    }
    
    public function discoverParameters(
        CpeDevice $device, 
        ?string $parameterPath = null, 
        bool $nextLevelOnly = true
    ): ProvisioningTask {
        $this->discoveryRequests[] = [
            'device_id' => $device->id,
            'parameter_path' => $parameterPath,
            'next_level_only' => $nextLevelOnly,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: Discovering device parameters', [
            'device_id' => $device->id,
            'parameter_path' => $parameterPath,
            'next_level_only' => $nextLevelOnly,
            'fake' => true
        ]);
        
        // Create a provisioning task for the discovery operation
        return ProvisioningTask::create([
            'cpe_device_id' => $device->id,
            'task_type' => 'get_parameters',
            'status' => 'pending',
            'task_data' => [
                'path' => $parameterPath ?? 'Device.',
                'next_level_only' => $nextLevelOnly
            ],
            'retry_count' => 0,
            'max_retries' => 3
        ]);
    }
    
    // Test helpers
    public function getDiscoveryRequests(): array
    {
        return $this->discoveryRequests;
    }
    
    public function clearHistory(): void
    {
        $this->discoveryRequests = [];
    }
}
