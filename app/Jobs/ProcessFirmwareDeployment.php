<?php

namespace App\Jobs;

use App\Models\FirmwareDeployment;
use App\Models\ProvisioningTask;
use App\Services\TR069Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Traits\TenantAwareJob;

/**
 * ProcessFirmwareDeployment - Job per elaborazione deployment firmware
 * ProcessFirmwareDeployment - Job for firmware deployment processing
 * 
 * Questo job gestisce il deployment di firmware su dispositivi CPE:
 * 1. Cambia stato deployment a "downloading"
 * 2. Crea ProvisioningTask con tipo "download" contenente URL firmware
 * 3. Dispatcha ProcessProvisioningTask per invio Download SOAP al dispositivo
 * 4. Aggiorna stato deployment a "installing"
 * 
 * This job manages firmware deployment to CPE devices:
 * 1. Changes deployment status to "downloading"
 * 2. Creates ProvisioningTask with type "download" containing firmware URL
 * 3. Dispatches ProcessProvisioningTask to send Download SOAP to device
 * 4. Updates deployment status to "installing"
 * 
 * Caratteristiche:
 * - 3 tentativi automatici con delay di 120 secondi / 3 automatic retries with 120s delay
 * - Timeout 300 secondi / 300s timeout
 * - ProcessProvisioningTask aggiorna stato finale (completed/failed) / ProcessProvisioningTask updates final status
 */
class ProcessFirmwareDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels, TenantAwareJob;

    /**
     * Numero massimo tentativi / Maximum number of attempts
     */
    public $tries = 3;
    
    /**
     * Timeout job in secondi / Job timeout in seconds
     */
    public $timeout = 300;

    /**
     * Costruttore job
     * Job constructor
     * 
     * @param FirmwareDeployment $deployment Deployment da elaborare / Deployment to process
     */
    public function __construct(
        public FirmwareDeployment $deployment
    ) {}

    /**
     * Esegue il job di deployment firmware
     * Execute firmware deployment job
     * 
     * Flusso:
     * 1. Verifica deployment in stato "scheduled"
     * 2. Cambia stato a "downloading"
     * 3. Genera URL download firmware pubblico
     * 4. Crea ProvisioningTask tipo "download" con URL, file_size, deployment_id
     * 5. Dispatcha ProcessProvisioningTask per invio Download SOAP
     * 6. Cambia stato deployment a "installing"
     * 7. ProcessProvisioningTask aggiornerà stato finale a completed/failed
     * 
     * Flow:
     * 1. Check deployment is in "scheduled" status
     * 2. Change status to "downloading"
     * 3. Generate public firmware download URL
     * 4. Create ProvisioningTask type "download" with URL, file_size, deployment_id
     * 5. Dispatch ProcessProvisioningTask to send Download SOAP
     * 6. Change deployment status to "installing"
     * 7. ProcessProvisioningTask will update final status to completed/failed
     */
    public function handle(): void
    {
        // Solo deployment scheduled possono essere elaborati
        // Only scheduled deployments can be processed
        if ($this->deployment->status !== 'scheduled') {
            return;
        }

        try {
            // Aggiorna stato a downloading
            // Update status to downloading
            $this->deployment->update([
                'status' => 'downloading',
                'started_at' => now()
            ]);

            $firmware = $this->deployment->firmwareVersion;
            $device = $this->deployment->cpeDevice;

            // Verifica firmware e dispositivo esistono
            // Verify firmware and device exist
            if (!$firmware || !$device) {
                throw new \Exception('Firmware or device not found');
            }

            // Genera URL pubblico per download firmware
            // Generate public URL for firmware download
            $downloadUrl = url('/firmware/' . $firmware->file_path);
            
            // Crea task di provisioning tipo "download" per TR-069
            // Create provisioning task type "download" for TR-069
            $task = ProvisioningTask::create([
                'cpe_device_id' => $device->id,
                'task_type' => 'download',
                'status' => 'pending',
                'task_data' => [
                    'url' => $downloadUrl,
                    'file_type' => '1 Firmware Upgrade Image',
                    'file_size' => $firmware->file_size,
                    'firmware_id' => $firmware->id,
                    'deployment_id' => $this->deployment->id  // Per aggiornamento stato finale
                ]
            ]);

            // Dispatcha job per invio Download SOAP al dispositivo
            // Dispatch job to send Download SOAP to device
            ProcessProvisioningTask::dispatch($task);

            // Aggiorna stato deployment a installing
            // Update deployment status to installing
            // Nota: ProcessProvisioningTask aggiornerà a completed/failed quando finisce
            // Note: ProcessProvisioningTask will update to completed/failed when done
            $this->deployment->update([
                'status' => 'installing',
            ]);

            Log::info('Firmware deployment initiated', [
                'deployment_id' => $this->deployment->id,
                'firmware_id' => $firmware->id,
                'device_id' => $device->id,
                'task_id' => $task->id
            ]);

        } catch (\Exception $e) {
            // Gestione errori con retry logic
            // Error handling with retry logic
            $this->deployment->increment('retry_count');
            
            // Se raggiunto max retry, segna come failed
            // If max retry reached, mark as failed
            if ($this->deployment->retry_count >= 3) {
                $this->deployment->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now()
                ]);
            } else {
                // Altrimenti riprova dopo 120 secondi
                // Otherwise retry after 120 seconds
                $this->deployment->update([
                    'status' => 'scheduled',
                    'error_message' => $e->getMessage()
                ]);
                
                $this->release(120);
            }

            Log::error('Firmware deployment failed', [
                'deployment_id' => $this->deployment->id,
                'error' => $e->getMessage(),
                'retry_count' => $this->deployment->retry_count
            ]);
        }
    }
}
