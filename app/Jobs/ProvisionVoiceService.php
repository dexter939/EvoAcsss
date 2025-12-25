<?php

namespace App\Jobs;

use App\Models\VoiceService;
use App\Models\ProvisioningTask;
use App\Services\VoipProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Traits\TenantAwareJob;

class ProvisionVoiceService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels, TenantAwareJob;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public VoiceService $voiceService
    ) {}

    public function handle(): void
    {
        try {
            $device = $this->voiceService->cpeDevice;
            
            if (!$device) {
                throw new \Exception('Device not found for VoiceService ID: ' . $this->voiceService->id);
            }

            if ($device->protocol_type !== 'tr069') {
                throw new \Exception('Device protocol is not TR-069. VoIP provisioning only works with TR-069 devices.');
            }

            $provisioningService = new VoipProvisioningService();
            
            $parameters = $provisioningService->provisionCompleteVoiceService(
                $this->voiceService
            );

            $provisioningTask = ProvisioningTask::create([
                'cpe_device_id' => $device->id,
                'task_type' => 'set_parameters',
                'status' => 'pending',
                'task_data' => [
                    'parameters' => $parameters,
                    'source' => 'voip_provisioning',
                    'voice_service_id' => $this->voiceService->id
                ]
            ]);

            ProcessProvisioningTask::dispatch($provisioningTask);

            Log::info('VoIP provisioning task created', [
                'voice_service_id' => $this->voiceService->id,
                'device_id' => $device->id,
                'task_id' => $provisioningTask->id,
                'parameters_count' => count($parameters)
            ]);

        } catch (\Exception $e) {
            Log::error('VoIP provisioning failed', [
                'voice_service_id' => $this->voiceService->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
