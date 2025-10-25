<?php

namespace App\Services;

use App\Models\CpeDevice;
use App\Models\DeviceCapability;
use App\Models\ProvisioningTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service per TR-111 Parameter Discovery
 * Service for TR-111 Parameter Discovery
 * 
 * Gestisce il discovery dinamico dei parametri da dispositivi CPE
 * Handles dynamic parameter discovery from CPE devices
 */
class ParameterDiscoveryService
{
    /**
     * Avvia discovery parametri per dispositivo
     * Start parameter discovery for device
     * 
     * @param CpeDevice $device Dispositivo target
     * @param string|null $parameterPath Path base per discovery (null = root)
     * @param bool $nextLevel Solo next level
     * @return ProvisioningTask Task creato
     */
    public function discoverParameters(CpeDevice $device, ?string $parameterPath = null, bool $nextLevel = true): ProvisioningTask
    {
        $task = ProvisioningTask::create([
            'cpe_device_id' => $device->id,
            'task_type' => 'get_parameter_names',
            'status' => 'pending',
            'task_data' => [
                'parameter_path' => $parameterPath ?? '',
                'next_level_only' => $nextLevel
            ]
        ]);

        \App\Jobs\ProcessProvisioningTask::dispatch($task);

        return $task;
    }

    /**
     * Processa risposta GetParameterNames e salva capabilities
     * Process GetParameterNames response and save capabilities
     * 
     * @param CpeDevice $device Dispositivo
     * @param array $parameterList Lista parametri dalla risposta
     * @return int Numero parametri salvati
     */
    public function processGetParameterNamesResponse(CpeDevice $device, array $parameterList): int
    {
        $savedCount = 0;
        $now = Carbon::now();

        foreach ($parameterList as $paramInfo) {
            $parameterPath = $paramInfo['Name'] ?? $paramInfo['name'] ?? null;
            $isWritable = $paramInfo['Writable'] ?? $paramInfo['writable'] ?? false;

            if (!$parameterPath) {
                continue;
            }

            // Estrai parameter name dall'ultimo segmento del path
            $parameterName = $this->extractParameterName($parameterPath);
            
            // Determina tipo dato e vendor-specific flag
            $dataType = $this->guessDataType($parameterPath, $paramInfo);
            $isVendorSpecific = $this->isVendorSpecific($parameterPath);

            // Upsert capability (insert or update)
            DeviceCapability::updateOrCreate(
                [
                    'cpe_device_id' => $device->id,
                    'parameter_path' => $parameterPath
                ],
                [
                    'parameter_name' => $parameterName,
                    'is_writable' => $isWritable,
                    'data_type' => $dataType,
                    'is_vendor_specific' => $isVendorSpecific,
                    'discovered_at' => $now,
                    'last_verified_at' => $now,
                    'metadata' => [
                        'discovery_method' => 'GetParameterNames',
                        'discovery_timestamp' => $now->toIso8601String()
                    ]
                ]
            );

            $savedCount++;
        }

        Log::info("TR-111: Discovered {$savedCount} parameters for device {$device->serial_number}");

        return $savedCount;
    }

    /**
     * Estrae nome parametro dal path completo
     * Extract parameter name from full path
     */
    private function extractParameterName(string $parameterPath): ?string
    {
        $parts = explode('.', trim($parameterPath, '.'));
        return count($parts) > 0 ? end($parts) : null;
    }

    /**
     * Indovina tipo dato basato sul path e metadata
     * Guess data type based on path and metadata
     */
    private function guessDataType(string $parameterPath, array $paramInfo): ?string
    {
        // Se il tipo è specificato nella risposta (estensioni vendor)
        if (isset($paramInfo['type']) || isset($paramInfo['Type'])) {
            return $paramInfo['type'] ?? $paramInfo['Type'];
        }

        // Pattern-based guessing
        $lowerPath = strtolower($parameterPath);

        if (str_contains($lowerPath, 'enable') || str_contains($lowerPath, 'status')) {
            return 'boolean';
        }

        if (str_contains($lowerPath, 'count') || str_contains($lowerPath, 'number') || 
            str_contains($lowerPath, 'port') || str_contains($lowerPath, 'size')) {
            return 'int';
        }

        if (str_contains($lowerPath, 'address') || str_contains($lowerPath, 'name') || 
            str_contains($lowerPath, 'description')) {
            return 'string';
        }

        return 'string'; // Default fallback
    }

    /**
     * Determina se parametro è vendor-specific
     * Determine if parameter is vendor-specific
     */
    private function isVendorSpecific(string $parameterPath): bool
    {
        // TR-181 Device.X_ indica vendor-specific
        if (str_contains($parameterPath, '.X_')) {
            return true;
        }

        // Altri pattern vendor comuni
        $vendorPatterns = [
            'InternetGatewayDevice.X_',
            'Device.X_',
            '.Vendor.',
            '.Custom.',
            '.Proprietary.'
        ];

        foreach ($vendorPatterns as $pattern) {
            if (str_contains($parameterPath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Costruisce albero parametri per device
     * Build parameter tree for device
     * 
     * @param CpeDevice $device Dispositivo
     * @param string|null $rootPath Path radice (null = tutto)
     * @return array Albero parametri
     */
    public function buildParameterTree(CpeDevice $device, ?string $rootPath = null): array
    {
        $query = $device->deviceCapabilities();

        if ($rootPath) {
            $query->where('parameter_path', 'LIKE', $rootPath . '%');
        }

        $capabilities = $query->orderBy('parameter_path')->get();

        return $this->buildTreeFromCapabilities($capabilities);
    }

    /**
     * Costruisce struttura ad albero da lista capabilities
     * Build tree structure from capabilities list
     */
    public function buildTreeFromCapabilities($capabilities): array
    {
        $tree = [];

        foreach ($capabilities as $capability) {
            // Preserve trailing dot for TR-069 object paths
            $hasTrailingDot = str_ends_with($capability->parameter_path, '.');
            $parts = explode('.', trim($capability->parameter_path, '.'));
            $current = &$tree;

            foreach ($parts as $index => $part) {
                if (!isset($current[$part])) {
                    $pathSoFar = implode('.', array_slice($parts, 0, $index + 1));
                    
                    // Restore trailing dot for objects if this is the last part
                    if ($index === count($parts) - 1 && $hasTrailingDot) {
                        $pathSoFar .= '.';
                    }
                    
                    $current[$part] = [
                        'name' => $part,
                        'full_path' => $pathSoFar,
                        'children' => [],
                        'capability' => null
                    ];
                }

                // Se è l'ultimo elemento, aggiungi i dati della capability
                if ($index === count($parts) - 1) {
                    $current[$part]['capability'] = [
                        'is_writable' => $capability->is_writable,
                        'data_type' => $capability->data_type,
                        'is_vendor_specific' => $capability->is_vendor_specific,
                        'discovered_at' => $capability->discovered_at?->toIso8601String()
                    ];
                }

                $current = &$current[$part]['children'];
            }
        }

        return $tree;
    }

    /**
     * Ottiene statistiche discovery per device
     * Get discovery statistics for device
     */
    public function getDiscoveryStats(CpeDevice $device): array
    {
        return [
            'total_parameters' => $device->deviceCapabilities()->count(),
            'writable_parameters' => $device->deviceCapabilities()->where('is_writable', true)->count(),
            'vendor_specific' => $device->deviceCapabilities()->where('is_vendor_specific', true)->count(),
            'standard_parameters' => $device->deviceCapabilities()->where('is_vendor_specific', false)->count(),
            'last_discovery' => $device->deviceCapabilities()->max('discovered_at'),
            'needs_verification' => $device->deviceCapabilities()
                ->where(function($query) {
                    $query->whereNull('last_verified_at')
                        ->orWhere('last_verified_at', '<', now()->subDays(30));
                })
                ->count()
        ];
    }

    /**
     * Lista comuni oggetti multi-instance TR-181
     * Common TR-181 multi-instance objects list
     */
    protected const MULTI_INSTANCE_OBJECTS = [
        'Device.WiFi.SSID.',
        'Device.WiFi.AccessPoint.',
        'Device.Ethernet.Interface.',
        'Device.Ethernet.Link.',
        'Device.IP.Interface.',
        'Device.DHCPv4.Server.Pool.',
        'Device.Hosts.Host.',
        'Device.NAT.PortMapping.',
        'Device.Routing.Router.',
        'Device.Firewall.Chain.',
    ];

    /**
     * Scopre automaticamente istanze di oggetti multi-instance
     * Automatically discover multi-instance object instances
     * 
     * @param CpeDevice $device Dispositivo target
     * @param array|null $objectPaths Paths specifici (null = tutti comuni)
     * @return array Risultati discovery
     */
    public function discoverMultiInstanceObjects(CpeDevice $device, ?array $objectPaths = null): array
    {
        $objectsToDiscover = $objectPaths ?? self::MULTI_INSTANCE_OBJECTS;
        $results = [
            'device_id' => $device->id,
            'objects_discovered' => 0,
            'instances_found' => 0,
            'objects' => [],
        ];

        foreach ($objectsToDiscover as $objectPath) {
            $objectPath = rtrim($objectPath, '.') . '.';
            
            // Query capabilities per trovare istanze esistenti
            $capabilities = $device->deviceCapabilities()
                ->where('parameter_path', 'LIKE', $objectPath . '%')
                ->get();

            if ($capabilities->isEmpty()) {
                continue;
            }

            // Estrai numeri istanza dai paths
            $instances = [];
            foreach ($capabilities as $cap) {
                $relativePath = substr($cap->parameter_path, strlen($objectPath));
                if (preg_match('/^(\d+)\./', $relativePath, $matches)) {
                    $instanceNum = $matches[1];
                    if (!in_array($instanceNum, $instances)) {
                        $instances[] = $instanceNum;
                    }
                }
            }

            if (!empty($instances)) {
                $results['objects'][$objectPath] = [
                    'instance_count' => count($instances),
                    'instances' => $instances,
                    'parameter_count' => $capabilities->count(),
                ];
                $results['objects_discovered']++;
                $results['instances_found'] += count($instances);
            }
        }

        Log::info('Multi-instance objects discovered', [
            'device_id' => $device->id,
            'objects_discovered' => $results['objects_discovered'],
            'total_instances' => $results['instances_found'],
        ]);

        return $results;
    }

    /**
     * Trigger auto-discovery di un oggetto multi-instance specifico
     * Trigger auto-discovery of a specific multi-instance object
     * 
     * @param CpeDevice $device
     * @param string $objectPath Es. Device.WiFi.SSID.
     * @return ProvisioningTask
     */
    public function discoverMultiInstanceObject(CpeDevice $device, string $objectPath): ProvisioningTask
    {
        $objectPath = rtrim($objectPath, '.') . '.';
        
        // Trigger GetParameterNames per questo oggetto
        return $this->discoverParameters($device, $objectPath, false);
    }

    /**
     * Sincronizza instance data da capabilities a device_parameters
     * Sync instance data from capabilities to device_parameters
     * 
     * @param CpeDevice $device
     * @param array|null $objectPaths Paths specifici (null = tutti)
     * @return array Risultati sync
     */
    public function syncMultiInstanceParameters(CpeDevice $device, ?array $objectPaths = null): array
    {
        $objectsToSync = $objectPaths ?? self::MULTI_INSTANCE_OBJECTS;
        $results = [
            'synced_count' => 0,
            'skipped_count' => 0,
            'objects' => [],
        ];

        DB::beginTransaction();
        
        try {
            foreach ($objectsToSync as $objectPath) {
                $objectPath = rtrim($objectPath, '.') . '.';
                
                // Get capabilities per questo oggetto
                $capabilities = $device->deviceCapabilities()
                    ->where('parameter_path', 'LIKE', $objectPath . '%')
                    ->get();

                $objectSyncCount = 0;
                
                foreach ($capabilities as $cap) {
                    // Sync to device_parameters se non già presente
                    $existing = $device->deviceParameters()
                        ->where('parameter_path', $cap->parameter_path)
                        ->first();

                    if (!$existing) {
                        $device->deviceParameters()->create([
                            'parameter_path' => $cap->parameter_path,
                            'parameter_value' => null, // Will be populated by next GetParameterValues
                            'parameter_type' => $cap->data_type,
                            'is_writable' => $cap->is_writable,
                            'last_updated' => now(),
                        ]);
                        $objectSyncCount++;
                        $results['synced_count']++;
                    } else {
                        $results['skipped_count']++;
                    }
                }

                if ($objectSyncCount > 0) {
                    $results['objects'][$objectPath] = [
                        'synced' => $objectSyncCount,
                    ];
                }
            }

            DB::commit();

            Log::info('Multi-instance parameters synced', [
                'device_id' => $device->id,
                'synced' => $results['synced_count'],
                'skipped' => $results['skipped_count'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to sync multi-instance parameters', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $results;
    }

    /**
     * Verifica se un path è un oggetto multi-instance
     * Check if a path is a multi-instance object
     * 
     * @param string $path
     * @return bool
     */
    public function isMultiInstanceObject(string $path): bool
    {
        $path = rtrim($path, '.') . '.';
        
        foreach (self::MULTI_INSTANCE_OBJECTS as $multiInstancePath) {
            if (str_starts_with($path, $multiInstancePath)) {
                return true;
            }
        }
        
        // Controlla anche pattern con numeri (es. Device.WiFi.SSID.1.)
        if (preg_match('/\.\d+\./', $path)) {
            return true;
        }
        
        return false;
    }

    /**
     * Ottiene lista multi-instance objects disponibili
     * Get list of available multi-instance objects
     * 
     * @return array
     */
    public function getMultiInstanceObjectPaths(): array
    {
        return self::MULTI_INSTANCE_OBJECTS;
    }
}
