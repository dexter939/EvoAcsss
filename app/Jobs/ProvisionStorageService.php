<?php

namespace App\Jobs;

use App\Models\StorageService;
use App\Models\ProvisioningTask;
use App\Services\StorageProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Traits\TenantAwareJob;

class ProvisionStorageService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels, TenantAwareJob;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public StorageService $storageService
    ) {}

    public function handle(): void
    {
        try {
            $device = $this->storageService->cpeDevice;
            
            if (!$device) {
                throw new \Exception('Device not found for StorageService ID: ' . $this->storageService->id);
            }

            if ($device->protocol_type !== 'tr069') {
                throw new \Exception('Device protocol is not TR-069. Storage provisioning only works with TR-069 devices.');
            }

            $provisioningService = new StorageProvisioningService();
            
            $parameters = $provisioningService->provisionCompleteStorageService(
                $this->storageService
            );

            $provisioningTask = ProvisioningTask::create([
                'cpe_device_id' => $device->id,
                'task_type' => 'set_parameters',
                'status' => 'pending',
                'task_data' => [
                    'parameters' => $parameters,
                    'source' => 'storage_provisioning',
                    'storage_service_id' => $this->storageService->id
                ]
            ]);

            ProcessProvisioningTask::dispatch($provisioningTask);

            Log::info('Storage provisioning task created', [
                'storage_service_id' => $this->storageService->id,
                'device_id' => $device->id,
                'task_id' => $provisioningTask->id,
                'parameters_count' => count($parameters)
            ]);

        } catch (\Exception $e) {
            Log::error('Storage provisioning failed', [
                'storage_service_id' => $this->storageService->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
