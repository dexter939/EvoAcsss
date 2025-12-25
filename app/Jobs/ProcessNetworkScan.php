<?php

namespace App\Jobs;

use App\Models\CpeDevice;
use App\Models\ProvisioningTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Traits\TenantAwareJob;

class ProcessNetworkScan implements ShouldQueue
{
    use Queueable, TenantAwareJob;

    public $timeout = 120;
    public $tries = 3;

    protected $deviceId;
    protected $dataModel;

    /**
     * Create a new job instance.
     */
    public function __construct($deviceId, $dataModel = 'tr098')
    {
        $this->deviceId = $deviceId;
        $this->dataModel = $dataModel;
    }

    /**
     * Execute the job - Crea provisioning task per network scan
     */
    public function handle(): void
    {
        $device = CpeDevice::find($this->deviceId);
        
        if (!$device) {
            Log::error('Network scan failed: Device not found', ['device_id' => $this->deviceId]);
            return;
        }

        // Crea provisioning task per network scan
        $task = ProvisioningTask::create([
            'cpe_device_id' => $device->id,
            'task_type' => 'network_scan',
            'status' => 'pending',
            'task_data' => json_encode([
                'data_model' => $this->dataModel,
                'scan_type' => 'full',
                'timestamp' => now()->toIso8601String(),
            ]),
        ]);

        Log::info('Network scan task created', [
            'device_id' => $device->id,
            'task_id' => $task->id,
            'data_model' => $this->dataModel,
        ]);

        // Delega a ProcessProvisioningTask per esecuzione
        ProcessProvisioningTask::dispatch($task);
    }
}
