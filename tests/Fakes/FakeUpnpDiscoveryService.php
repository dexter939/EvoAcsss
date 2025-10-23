<?php

namespace Tests\Fakes;

use App\Models\CpeDevice;
use App\Models\LanDevice;
use Illuminate\Support\Facades\Log;

/**
 * Fake UPnP Discovery Service for testing
 * 
 * Simulates UPnP/SSDP discovery without real network calls
 */
class FakeUpnpDiscoveryService
{
    protected array $processedAnnouncements = [];
    
    public function __construct()
    {
        // No dependencies needed for fake
    }
    
    public function processSsdpAnnouncement(CpeDevice $device, array $ssdpData): LanDevice
    {
        $this->processedAnnouncements[] = [
            'device_id' => $device->id,
            'ssdp_data' => $ssdpData,
            'timestamp' => now()
        ];
        
        Log::info('FAKE: Processing SSDP announcement', [
            'device_id' => $device->id,
            'usn' => $ssdpData['usn'] ?? null,
            'fake' => true
        ]);
        
        // Create or update LanDevice
        return LanDevice::updateOrCreate(
            [
                'cpe_device_id' => $device->id,
                'usn' => $ssdpData['usn']
            ],
            [
                'location' => $ssdpData['location'] ?? null,
                'device_type' => $ssdpData['device_type'] ?? 'urn:schemas-upnp-org:device:Basic:1',
                'friendly_name' => $ssdpData['friendly_name'] ?? 'Unknown Device',
                'manufacturer' => $ssdpData['manufacturer'] ?? null,
                'model_name' => $ssdpData['model_name'] ?? null,
                'services' => $ssdpData['services'] ?? null,
                'status' => 'active',
                'last_seen' => now()
            ]
        );
    }
    
    // Test helpers
    public function getProcessedAnnouncements(): array
    {
        return $this->processedAnnouncements;
    }
    
    public function clearHistory(): void
    {
        $this->processedAnnouncements = [];
    }
}
