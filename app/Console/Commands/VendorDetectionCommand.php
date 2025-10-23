<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CpeDevice;
use App\Services\Vendor\VendorDetectionService;

/**
 * VendorDetectionCommand - Comando per eseguire vendor detection su device CPE
 * VendorDetectionCommand - Command to run vendor detection on CPE devices
 * 
 * Esegue o ri-esegue il vendor detection per identificare manufacturer e product
 * Runs or re-runs vendor detection to identify manufacturer and product
 */
class VendorDetectionCommand extends Command
{
    /**
     * Signature del comando con opzioni
     * Command signature with options
     */
    protected $signature = 'vendor:detect 
                            {--all : Process all devices}
                            {--unmatched : Process only devices without vendor match}
                            {--device= : Process specific device by ID or serial number}
                            {--force : Force re-detection even for already matched devices}';

    /**
     * Descrizione comando
     * Command description
     */
    protected $description = 'Auto-detect vendor and product information for CPE devices';

    private VendorDetectionService $vendorDetection;

    public function __construct(VendorDetectionService $vendorDetection)
    {
        parent::__construct();
        $this->vendorDetection = $vendorDetection;
    }

    /**
     * Esegui comando
     * Execute command
     */
    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║         Vendor Auto-Detection for CPE Devices              ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Determina quali device processare
        // Determine which devices to process
        $devices = $this->selectDevices();

        if ($devices->isEmpty()) {
            $this->warn('No devices found matching the specified criteria.');
            return self::FAILURE;
        }

        $this->info("Found {$devices->count()} device(s) to process.");
        $this->newLine();

        // Processa device con progress bar
        // Process devices with progress bar
        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;

        $progressBar = $this->output->createProgressBar($devices->count());
        $progressBar->setFormat('very_verbose');

        foreach ($devices as $device) {
            $result = $this->processDevice($device);
            
            switch ($result['status']) {
                case 'success':
                    $successCount++;
                    break;
                case 'failed':
                    $failureCount++;
                    break;
                case 'skipped':
                    $skippedCount++;
                    break;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostra riepilogo
        // Show summary
        $this->displaySummary($devices->count(), $successCount, $failureCount, $skippedCount);

        return $failureCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Seleziona device da processare in base alle opzioni
     * Select devices to process based on options
     */
    private function selectDevices()
    {
        $query = CpeDevice::query();

        if ($this->option('device')) {
            // Cerca per ID o serial number
            // Search by ID or serial number
            $identifier = $this->option('device');
            
            if (is_numeric($identifier)) {
                $query->where('id', $identifier);
            } else {
                $query->where('serial_number', $identifier);
            }
        } elseif ($this->option('unmatched')) {
            // Solo device senza vendor match
            // Only devices without vendor match
            $query->where(function ($q) {
                $q->whereNull('manufacturer')
                  ->orWhereNull('model_name');
            });
        } elseif ($this->option('all')) {
            // Tutti i device (nessun filtro)
            // All devices (no filter)
        } else {
            // Default: solo unmatched
            // Default: only unmatched
            $query->where(function ($q) {
                $q->whereNull('manufacturer')
                  ->orWhereNull('model_name');
            });
        }

        return $query->get(['id', 'serial_number', 'manufacturer', 'model_name', 'oui', 'product_class', 'software_version', 'hardware_version']);
    }

    /**
     * Processa singolo device
     * Process single device
     */
    private function processDevice(CpeDevice $device): array
    {
        // Skip se già matchato e non force
        // Skip if already matched and not force
        if (!$this->option('force') && $device->manufacturer && $device->model_name) {
            return [
                'status' => 'skipped',
                'message' => 'Already matched (use --force to re-detect)'
            ];
        }

        try {
            $deviceInfo = [
                'Manufacturer' => $device->manufacturer,
                'ModelName' => $device->model_name,
                'ManufacturerOUI' => $device->oui,
                'ProductClass' => $device->product_class,
                'SoftwareVersion' => $device->software_version,
                'HardwareVersion' => $device->hardware_version
            ];

            // Esegui vendor detection
            // Run vendor detection
            $this->vendorDetection->updateCpeDeviceVendor($device, $deviceInfo);

            // Ricarica device per verificare risultato
            // Reload device to verify result
            $device->refresh();

            if ($device->manufacturer && $device->model_name) {
                return [
                    'status' => 'success',
                    'message' => "Detected: {$device->manufacturer} - {$device->model_name}"
                ];
            } else {
                return [
                    'status' => 'failed',
                    'message' => 'Vendor detection failed - no match found'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mostra riepilogo finale
     * Display final summary
     */
    private function displaySummary(int $total, int $success, int $failed, int $skipped): void
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                   Detection Summary                        ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Processed', $total, '100%'],
                ['✓ Successfully Detected', $success, $this->percentage($success, $total)],
                ['✗ Failed', $failed, $this->percentage($failed, $total)],
                ['↷ Skipped', $skipped, $this->percentage($skipped, $total)],
            ]
        );

        if ($success > 0) {
            $this->info("✓ Successfully detected vendor information for {$success} device(s)");
        }
        if ($failed > 0) {
            $this->warn("✗ Failed to detect vendor information for {$failed} device(s)");
        }
        if ($skipped > 0) {
            $this->comment("↷ Skipped {$skipped} device(s) (already matched)");
        }
    }

    /**
     * Calcola percentuale
     * Calculate percentage
     */
    private function percentage(int $value, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }
        
        return round(($value / $total) * 100, 1) . '%';
    }
}
