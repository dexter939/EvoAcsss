<?php

namespace App\Services\Vendor;

use App\Models\FirmwareVersion;
use App\Models\RouterProduct;
use App\Models\FirmwareCompatibility;
use App\Models\VendorQuirk;
use Illuminate\Support\Facades\Log;

class CompatibilityService
{
    /**
     * Check firmware compatibility with product
     */
    public function checkCompatibility(int $firmwareVersionId, int $productId): array
    {
        $firmware = FirmwareVersion::find($firmwareVersionId);
        $product = RouterProduct::with('manufacturer')->find($productId);

        if (!$firmware || !$product) {
            return [
                'compatible' => false,
                'status' => 'unknown',
                'reason' => 'Firmware or product not found'
            ];
        }

        $compatibility = FirmwareCompatibility::where('firmware_version_id', $firmwareVersionId)
            ->where('product_id', $productId)
            ->first();

        if ($compatibility) {
            return [
                'compatible' => in_array($compatibility->compatibility_status, ['compatible', 'compatible_with_issues', 'beta']),
                'status' => $compatibility->compatibility_status,
                'tested' => $compatibility->tested,
                'known_issues' => $compatibility->known_issues,
                'installation_notes' => $compatibility->installation_notes,
                'performance_rating' => $compatibility->performance_rating,
                'stability_rating' => $compatibility->stability_rating
            ];
        }

        $basicCheck = $this->performBasicCompatibilityCheck($firmware, $product);
        
        return $basicCheck;
    }

    /**
     * Perform basic compatibility check when no explicit data exists
     */
    private function performBasicCompatibilityCheck(FirmwareVersion $firmware, RouterProduct $product): array
    {
        $compatible = false;
        $reason = 'No compatibility data available';

        if (strcasecmp($firmware->manufacturer, $product->manufacturer->name) === 0 &&
            strcasecmp($firmware->model, $product->model_name) === 0) {
            $compatible = true;
            $reason = 'Manufacturer and model match';
        } elseif (strcasecmp($firmware->manufacturer, $product->manufacturer->name) === 0) {
            $compatible = false;
            $reason = 'Manufacturer matches but model differs - possible incompatibility';
        } else {
            $compatible = false;
            $reason = 'Different manufacturer - incompatible';
        }

        return [
            'compatible' => $compatible,
            'status' => $compatible ? 'untested' : 'incompatible',
            'tested' => false,
            'reason' => $reason
        ];
    }

    /**
     * Get applicable quirks for product/firmware combination
     */
    public function getApplicableQuirks(int $productId, ?string $firmwareVersion = null): array
    {
        $product = RouterProduct::with('manufacturer')->find($productId);
        
        if (!$product) {
            return [];
        }

        $quirks = VendorQuirk::where('is_active', true)
            ->where(function ($query) use ($product) {
                $query->where('manufacturer_id', $product->manufacturer_id)
                      ->whereNull('product_id');
            })
            ->orWhere(function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->get();

        if ($firmwareVersion) {
            $quirks = $quirks->filter(function ($quirk) use ($productId, $firmwareVersion) {
                return $quirk->affectsProduct($productId, $firmwareVersion);
            });
        }

        return $quirks->map(function ($quirk) {
            return [
                'id' => $quirk->id,
                'quirk_type' => $quirk->quirk_type,
                'quirk_name' => $quirk->quirk_name,
                'description' => $quirk->description,
                'severity' => $quirk->severity,
                'affects_protocol' => $quirk->affects_protocol,
                'workaround_config' => $quirk->workaround_config,
                'workaround_notes' => $quirk->workaround_notes,
                'auto_apply' => $quirk->auto_apply
            ];
        })->toArray();
    }

    /**
     * Create or update compatibility entry
     */
    public function recordCompatibility(array $data): FirmwareCompatibility
    {
        return FirmwareCompatibility::updateOrCreate(
            [
                'firmware_version_id' => $data['firmware_version_id'],
                'product_id' => $data['product_id']
            ],
            $data
        );
    }

    /**
     * Get compatibility matrix for a product
     */
    public function getProductCompatibilityMatrix(int $productId): array
    {
        $product = RouterProduct::with('manufacturer')->find($productId);
        
        if (!$product) {
            return [];
        }

        $firmwares = FirmwareVersion::where('manufacturer', 'ILIKE', $product->manufacturer->name)
            ->where('is_active', true)
            ->orderBy('release_date', 'desc')
            ->get();

        $matrix = [];
        
        foreach ($firmwares as $firmware) {
            $compat = FirmwareCompatibility::where('firmware_version_id', $firmware->id)
                ->where('product_id', $productId)
                ->first();

            $matrix[] = [
                'firmware_id' => $firmware->id,
                'firmware_version' => $firmware->version,
                'release_date' => $firmware->release_date,
                'is_stable' => $firmware->is_stable,
                'compatibility_status' => $compat?->compatibility_status ?? 'untested',
                'tested' => $compat?->tested ?? false,
                'performance_rating' => $compat?->performance_rating,
                'stability_rating' => $compat?->stability_rating,
                'known_issues_count' => $compat?->known_issues ? count($compat->known_issues) : 0
            ];
        }

        return $matrix;
    }

    /**
     * Get firmware compatibility statistics
     */
    public function getCompatibilityStatistics(int $firmwareVersionId): array
    {
        $compatibilities = FirmwareCompatibility::where('firmware_version_id', $firmwareVersionId)->get();

        return [
            'total_products' => $compatibilities->count(),
            'compatible' => $compatibilities->where('compatibility_status', 'compatible')->count(),
            'compatible_with_issues' => $compatibilities->where('compatibility_status', 'compatible_with_issues')->count(),
            'incompatible' => $compatibilities->where('compatibility_status', 'incompatible')->count(),
            'untested' => $compatibilities->where('compatibility_status', 'untested')->count(),
            'tested_count' => $compatibilities->where('tested', true)->count(),
            'average_performance' => $compatibilities->whereNotNull('performance_rating')->avg('performance_rating'),
            'average_stability' => $compatibilities->whereNotNull('stability_rating')->avg('stability_rating')
        ];
    }

    /**
     * Apply vendor quirks to configuration
     */
    public function applyQuirksToConfiguration(int $productId, array $config, ?string $firmwareVersion = null): array
    {
        $quirks = $this->getApplicableQuirks($productId, $firmwareVersion);
        $autoApplyQuirks = array_filter($quirks, fn($q) => $q['auto_apply']);

        foreach ($autoApplyQuirks as $quirk) {
            if ($quirk['workaround_config']) {
                Log::info("Applying quirk: {$quirk['quirk_name']}", [
                    'product_id' => $productId,
                    'firmware_version' => $firmwareVersion
                ]);
                
                $config = array_merge($config, $quirk['workaround_config']);
            }
        }

        return $config;
    }
}
