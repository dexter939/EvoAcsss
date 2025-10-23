<?php

namespace App\Services\Vendor;

use App\Models\RouterManufacturer;
use App\Models\RouterProduct;
use App\Models\CpeDevice;
use Illuminate\Support\Facades\Log;

class VendorDetectionService
{
    /**
     * Auto-detect vendor from TR-069 DeviceInfo
     */
    public function detectFromTR069DeviceInfo(array $deviceInfo): ?array
    {
        $result = [
            'manufacturer' => null,
            'product' => null,
            'confidence' => 0,
            'method' => null
        ];

        if (!empty($deviceInfo['Manufacturer'])) {
            $manufacturer = $this->matchManufacturerByName($deviceInfo['Manufacturer']);
            if ($manufacturer) {
                $result['manufacturer'] = $manufacturer;
                $result['confidence'] = 80;
                $result['method'] = 'manufacturer_name';

                if (!empty($deviceInfo['ModelName'])) {
                    $product = $this->matchProductByModel($manufacturer->id, $deviceInfo['ModelName']);
                    if ($product) {
                        $result['product'] = $product;
                        $result['confidence'] = 95;
                        $result['method'] = 'manufacturer_model';
                    }
                }

                return $result;
            }
        }

        if (!empty($deviceInfo['ManufacturerOUI'])) {
            $manufacturer = $this->matchManufacturerByOUI($deviceInfo['ManufacturerOUI']);
            if ($manufacturer) {
                $result['manufacturer'] = $manufacturer;
                $result['confidence'] = 70;
                $result['method'] = 'oui_prefix';
                return $result;
            }
        }

        return $result['manufacturer'] ? $result : null;
    }

    /**
     * Detect vendor from MAC address
     */
    public function detectFromMacAddress(string $macAddress): ?RouterManufacturer
    {
        $oui = strtoupper(substr(str_replace([':', '-', '.'], '', $macAddress), 0, 6));
        
        if (strlen($oui) < 6) {
            return null;
        }

        $ouiFormatted = substr($oui, 0, 2) . ':' . substr($oui, 2, 2) . ':' . substr($oui, 4, 2);

        return $this->matchManufacturerByOUI($ouiFormatted);
    }

    /**
     * Match manufacturer by name (fuzzy matching)
     */
    private function matchManufacturerByName(string $name): ?RouterManufacturer
    {
        $name = trim($name);

        $exact = RouterManufacturer::where('name', 'ILIKE', $name)->first();
        if ($exact) {
            return $exact;
        }

        $partial = RouterManufacturer::where('name', 'ILIKE', "%{$name}%")->first();
        if ($partial) {
            return $partial;
        }

        $manufacturers = RouterManufacturer::all();
        foreach ($manufacturers as $manufacturer) {
            if (stripos($name, $manufacturer->name) !== false || 
                stripos($manufacturer->name, $name) !== false) {
                return $manufacturer;
            }
        }

        return null;
    }

    /**
     * Match manufacturer by OUI prefix
     */
    private function matchManufacturerByOUI(string $oui): ?RouterManufacturer
    {
        $oui = strtoupper(str_replace([':', '-', '.'], ':', $oui));

        return RouterManufacturer::whereNotNull('oui_prefix')
            ->get()
            ->first(function ($manufacturer) use ($oui) {
                $prefixes = array_map('trim', explode(',', $manufacturer->oui_prefix));
                foreach ($prefixes as $prefix) {
                    if (stripos($oui, strtoupper($prefix)) === 0) {
                        return true;
                    }
                }
                return false;
            });
    }

    /**
     * Match product by model name
     */
    private function matchProductByModel(int $manufacturerId, string $modelName): ?RouterProduct
    {
        $modelName = trim($modelName);

        $exact = RouterProduct::where('manufacturer_id', $manufacturerId)
            ->where('model_name', 'ILIKE', $modelName)
            ->first();
        if ($exact) {
            return $exact;
        }

        $partial = RouterProduct::where('manufacturer_id', $manufacturerId)
            ->where('model_name', 'ILIKE', "%{$modelName}%")
            ->first();
        if ($partial) {
            return $partial;
        }

        $products = RouterProduct::where('manufacturer_id', $manufacturerId)->get();
        foreach ($products as $product) {
            if (stripos($modelName, $product->model_name) !== false || 
                stripos($product->model_name, $modelName) !== false) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Update CPE device with detected vendor information
     */
    public function updateCpeDeviceVendor(CpeDevice $device, array $deviceInfo): bool
    {
        $detection = $this->detectFromTR069DeviceInfo($deviceInfo);
        
        if (!$detection) {
            Log::info("Vendor detection failed for device {$device->serial_number}");
            return false;
        }

        $updates = [];
        
        if ($detection['manufacturer']) {
            $updates['manufacturer'] = $detection['manufacturer']->name;
        }

        if ($detection['product']) {
            $updates['model'] = $detection['product']->model_name;
        }

        if (!empty($updates)) {
            $device->update($updates);
            Log::info("Updated device {$device->serial_number} vendor info", [
                'manufacturer' => $updates['manufacturer'] ?? null,
                'model' => $updates['model'] ?? null,
                'confidence' => $detection['confidence'],
                'method' => $detection['method']
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get vendor statistics
     */
    public function getVendorStatistics(): array
    {
        $manufacturers = RouterManufacturer::withCount(['products', 'quirks', 'templates'])->get();
        
        return [
            'total_manufacturers' => $manufacturers->count(),
            'total_products' => RouterProduct::count(),
            'tr069_supported' => $manufacturers->where('tr069_support', true)->count(),
            'tr369_supported' => $manufacturers->where('tr369_support', true)->count(),
            'manufacturers' => $manufacturers->map(function ($manufacturer) {
                return [
                    'id' => $manufacturer->id,
                    'name' => $manufacturer->name,
                    'category' => $manufacturer->category,
                    'country' => $manufacturer->country,
                    'products_count' => $manufacturer->products_count,
                    'quirks_count' => $manufacturer->quirks_count,
                    'templates_count' => $manufacturer->templates_count,
                    'tr069_support' => $manufacturer->tr069_support,
                    'tr369_support' => $manufacturer->tr369_support,
                ];
            })
        ];
    }
}
