<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ApiResponse;
use App\Models\RouterManufacturer;
use App\Models\RouterProduct;
use App\Models\FirmwareCompatibility;
use App\Models\VendorQuirk;
use App\Models\ConfigurationTemplateLibrary;
use App\Services\Vendor\VendorDetectionService;
use App\Services\Vendor\CompatibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * VendorLibraryController - Controller API per Multi-Vendor Device Library
 * VendorLibraryController - API Controller for Multi-Vendor Device Library
 * 
 * Gestisce manufacturer, products, firmware compatibility, quirks, templates
 * Handles manufacturer, products, firmware compatibility, quirks, templates
 */
class VendorLibraryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private VendorDetectionService $vendorDetection,
        private CompatibilityService $compatibility
    ) {}

    /**
     * GET /api/v1/vendors/manufacturers
     * Lista manufacturers con filtri opzionali
     * List manufacturers with optional filters
     */
    public function getManufacturers(Request $request): JsonResponse
    {
        try {
            $query = RouterManufacturer::withCount(['products', 'quirks', 'templates']);

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('tr069_support')) {
                $query->where('tr069_support', $request->boolean('tr069_support'));
            }

            if ($request->has('tr369_support')) {
                $query->where('tr369_support', $request->boolean('tr369_support'));
            }

            $manufacturers = $query->orderBy('name')->get();

            return $this->successDataResponse([
                'manufacturers' => $manufacturers,
                'count' => $manufacturers->count()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve manufacturers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/manufacturers/{id}
     * Dettaglio manufacturer singolo
     * Single manufacturer detail
     */
    public function getManufacturer(int $id): JsonResponse
    {
        try {
            $manufacturer = RouterManufacturer::with(['products', 'quirks', 'templates'])
                ->withCount(['products', 'quirks', 'templates'])
                ->findOrFail($id);

            return $this->successDataResponse($manufacturer);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Manufacturer not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve manufacturer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/products
     * Lista products con filtri e ricerca
     * List products with filters and search
     */
    public function getProducts(Request $request): JsonResponse
    {
        try {
            $query = RouterProduct::with('manufacturer');

            if ($request->has('manufacturer_id')) {
                $query->where('manufacturer_id', $request->manufacturer_id);
            }

            if ($request->has('wifi_standard')) {
                $query->where('wifi_standard', $request->wifi_standard);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('model_name', 'ILIKE', "%{$search}%")
                      ->orWhereHas('manufacturer', function ($mq) use ($search) {
                          $mq->where('name', 'ILIKE', "%{$search}%");
                      });
                });
            }

            $products = $query->orderBy('release_year', 'desc')->get();

            return $this->successDataResponse([
                'products' => $products,
                'count' => $products->count()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/products/{id}
     * Dettaglio prodotto singolo
     * Single product detail
     */
    public function getProduct(int $id): JsonResponse
    {
        try {
            $product = RouterProduct::with(['manufacturer', 'compatibilities.firmwareVersion', 'quirks', 'templates'])
                ->findOrFail($id);

            return $this->successDataResponse($product);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Product not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/products/{id}/compatibility-matrix
     * Matrice compatibilità firmware per prodotto
     * Firmware compatibility matrix for product
     */
    public function getProductCompatibilityMatrix(int $id): JsonResponse
    {
        try {
            $matrix = $this->compatibility->getProductCompatibilityMatrix($id);

            return $this->successDataResponse([
                'product_id' => $id,
                'compatibility_matrix' => $matrix
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve compatibility matrix: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/quirks
     * Lista vendor quirks attivi con filtri
     * List active vendor quirks with filters
     */
    public function getQuirks(Request $request): JsonResponse
    {
        try {
            $query = VendorQuirk::with(['manufacturer', 'product'])
                ->where('is_active', true);

            if ($request->has('manufacturer_id')) {
                $query->where('manufacturer_id', $request->manufacturer_id);
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('severity')) {
                $query->where('severity', $request->severity);
            }

            if ($request->has('quirk_type')) {
                $query->where('quirk_type', $request->quirk_type);
            }

            $quirks = $query->orderBy('severity', 'desc')->get();

            return $this->successDataResponse([
                'quirks' => $quirks,
                'count' => $quirks->count()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve quirks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/products/{id}/quirks
     * Quirks applicabili a prodotto specifico (opzionalmente per firmware version)
     * Applicable quirks for specific product (optionally for firmware version)
     */
    public function getProductQuirks(int $productId, Request $request): JsonResponse
    {
        try {
            $firmwareVersion = $request->input('firmware_version');
            
            $quirks = $this->compatibility->getApplicableQuirks($productId, $firmwareVersion);

            return $this->successDataResponse([
                'product_id' => $productId,
                'firmware_version' => $firmwareVersion,
                'quirks' => $quirks,
                'count' => count($quirks)
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve product quirks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/templates
     * Lista configuration templates con filtri
     * List configuration templates with filters
     */
    public function getTemplates(Request $request): JsonResponse
    {
        try {
            $query = ConfigurationTemplateLibrary::with(['manufacturer', 'product']);

            if ($request->has('manufacturer_id')) {
                $query->where('manufacturer_id', $request->manufacturer_id);
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('category')) {
                $query->where('template_category', $request->category);
            }

            if ($request->has('protocol')) {
                $query->where('protocol', $request->protocol);
            }

            if ($request->has('official_only')) {
                $query->where('is_official', true);
            }

            $templates = $query->orderBy('usage_count', 'desc')->get();

            return $this->successDataResponse([
                'templates' => $templates,
                'count' => $templates->count()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve templates: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/templates/{id}
     * Dettaglio singolo template (incrementa usage counter)
     * Single template detail (increments usage counter)
     */
    public function getTemplate(int $id): JsonResponse
    {
        try {
            $template = ConfigurationTemplateLibrary::with(['manufacturer', 'product'])
                ->findOrFail($id);

            $template->incrementUsageCount();

            return $this->successDataResponse($template);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Template not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/vendors/detect
     * Rileva vendor da device_info (TR-069) o MAC address
     * Detect vendor from device_info (TR-069) or MAC address
     */
    public function detectVendor(Request $request): JsonResponse
    {
        // Validazione input - PRIMA di business logic
        // Input validation - BEFORE business logic
        $validated = $request->validate([
            'device_info' => 'required|array',
            'mac_address' => 'nullable|string'
        ]);

        try {
            $detection = $this->vendorDetection->detectFromTR069DeviceInfo($validated['device_info']);

            if (!$detection && isset($validated['mac_address'])) {
                $manufacturer = $this->vendorDetection->detectFromMacAddress($validated['mac_address']);
                if ($manufacturer) {
                    $detection = [
                        'manufacturer' => $manufacturer,
                        'product' => null,
                        'confidence' => 60,
                        'method' => 'mac_address'
                    ];
                }
            }

            if ($detection) {
                return $this->successResponse('Vendor detected successfully', $detection);
            } else {
                return $this->failureResponse('Vendor detection failed - no matching vendor found', 404);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Vendor detection error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/vendors/compatibility/check
     * Verifica compatibilità firmware-prodotto
     * Check firmware-product compatibility
     */
    public function checkCompatibility(Request $request): JsonResponse
    {
        // Validazione input - PRIMA di business logic
        // Input validation - BEFORE business logic
        $validated = $request->validate([
            'firmware_version_id' => 'required|integer|exists:firmware_versions,id',
            'product_id' => 'required|integer|exists:router_products,id'
        ]);

        try {
            $result = $this->compatibility->checkCompatibility(
                $validated['firmware_version_id'],
                $validated['product_id']
            );

            return $this->successDataResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse('Compatibility check failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/vendors/stats
     * Statistiche complete vendor library
     * Complete vendor library statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = $this->vendorDetection->getVendorStatistics();

            $additionalStats = [
                'total_quirks' => VendorQuirk::where('is_active', true)->count(),
                'total_templates' => ConfigurationTemplateLibrary::count(),
                'official_templates' => ConfigurationTemplateLibrary::where('is_official', true)->count(),
                'total_compatibility_entries' => FirmwareCompatibility::count(),
                'tested_compatibilities' => FirmwareCompatibility::where('tested', true)->count(),
            ];

            return $this->successDataResponse(array_merge($stats, $additionalStats));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    // ==================== BULK OPERATIONS ====================

    /**
     * POST /api/v1/vendors/bulk/detect
     * Rileva vendor/product per multipli device
     * Detect vendor/product for multiple devices
     * 
     * @param Request $request {device_ids: array}
     * @return JsonResponse
     */
    public function bulkDetectVendor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'required|integer|exists:cpe_devices,id'
        ]);

        try {
            $devices = \App\Models\CpeDevice::whereIn('id', $validated['device_ids'])
                ->get(['id', 'serial_number', 'manufacturer', 'model_name', 'oui', 'product_class']);

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($devices as $device) {
                try {
                    $detection = $this->vendorDetection->detectVendor(
                        $device->manufacturer,
                        $device->model_name,
                        $device->oui,
                        $device->product_class
                    );

                    $results[] = [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'status' => 'success',
                        'detection' => $detection
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $results[] = [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                    $failureCount++;
                }
            }

            return $this->successDataResponse([
                'processed' => count($devices),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Bulk vendor detection failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/vendors/bulk/apply-template
     * Applica configuration template a multipli device
     * Apply configuration template to multiple devices
     * 
     * @param Request $request {device_ids: array, template_id: int, dry_run: bool}
     * @return JsonResponse
     */
    public function bulkApplyTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'required|integer|exists:cpe_devices,id',
            'template_id' => 'required|integer|exists:configuration_templates_library,id',
            'dry_run' => 'sometimes|boolean'
        ]);

        try {
            $template = ConfigurationTemplateLibrary::with(['manufacturer', 'product'])->findOrFail($validated['template_id']);
            $devices = \App\Models\CpeDevice::whereIn('id', $validated['device_ids'])
                ->get(['id', 'serial_number', 'manufacturer', 'model_name', 'protocol_type']);

            $dryRun = $validated['dry_run'] ?? false;
            $results = [];
            $compatibleCount = 0;
            $incompatibleCount = 0;
            $appliedCount = 0;

            foreach ($devices as $device) {
                $isCompatible = true;
                $errors = [];

                if ($template->manufacturer && $device->manufacturer !== $template->manufacturer->name) {
                    $isCompatible = false;
                    $errors[] = "Manufacturer mismatch: template for {$template->manufacturer->name}, device is {$device->manufacturer}";
                }

                if ($template->protocol && $device->protocol_type !== strtolower(str_replace('TR-', 'tr', $template->protocol))) {
                    $isCompatible = false;
                    $errors[] = "Protocol mismatch: template for {$template->protocol}, device uses {$device->protocol_type}";
                }

                if ($isCompatible) {
                    $compatibleCount++;
                    
                    if (!$dryRun) {
                        try {
                            \App\Models\ProvisioningTask::create([
                                'device_id' => $device->id,
                                'task_type' => 'configuration',
                                'task_status' => 'pending',
                                'template_id' => $template->id,
                                'config_data' => $template->template_content,
                                'scheduled_at' => now(),
                                'priority' => 'normal'
                            ]);
                            $appliedCount++;
                            
                            $results[] = [
                                'device_id' => $device->id,
                                'serial_number' => $device->serial_number,
                                'status' => 'scheduled',
                                'message' => 'Provisioning task created successfully'
                            ];
                        } catch (\Exception $e) {
                            $results[] = [
                                'device_id' => $device->id,
                                'serial_number' => $device->serial_number,
                                'status' => 'failed',
                                'error' => 'Failed to create provisioning task: ' . $e->getMessage()
                            ];
                        }
                    } else {
                        $results[] = [
                            'device_id' => $device->id,
                            'serial_number' => $device->serial_number,
                            'status' => 'compatible',
                            'message' => 'Device is compatible (dry-run mode)'
                        ];
                    }
                } else {
                    $incompatibleCount++;
                    $results[] = [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'status' => 'incompatible',
                        'errors' => $errors
                    ];
                }
            }

            if (!$dryRun && $appliedCount > 0) {
                $template->increment('usage_count', $appliedCount);
            }

            return $this->successDataResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->template_name,
                    'category' => $template->template_category
                ],
                'dry_run' => $dryRun,
                'processed' => count($devices),
                'compatible_count' => $compatibleCount,
                'incompatible_count' => $incompatibleCount,
                'applied_count' => $appliedCount,
                'results' => $results
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Template not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Bulk template application failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/vendors/bulk/firmware-check
     * Verifica compatibilità firmware per multipli device
     * Check firmware compatibility for multiple devices
     * 
     * @param Request $request {device_ids: array, target_firmware_version: string}
     * @return JsonResponse
     */
    public function bulkFirmwareCompatibilityCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'required|integer|exists:cpe_devices,id',
            'target_firmware_version' => 'sometimes|string'
        ]);

        try {
            $devices = \App\Models\CpeDevice::whereIn('id', $validated['device_ids'])
                ->get(['id', 'serial_number', 'manufacturer', 'model_name', 'software_version']);

            $results = [];
            $compatibleCount = 0;
            $incompatibleCount = 0;
            $unknownCount = 0;

            foreach ($devices as $device) {
                try {
                    $product = RouterProduct::with('manufacturer')
                        ->whereHas('manufacturer', function ($q) use ($device) {
                            $q->where('name', 'ILIKE', $device->manufacturer);
                        })
                        ->where('model_name', 'ILIKE', $device->model_name)
                        ->first();

                    if (!$product) {
                        $unknownCount++;
                        $results[] = [
                            'device_id' => $device->id,
                            'serial_number' => $device->serial_number,
                            'status' => 'unknown',
                            'message' => 'Product not found in vendor library',
                            'current_firmware' => $device->software_version
                        ];
                        continue;
                    }

                    $targetVersion = $validated['target_firmware_version'] ?? null;
                    $compatibilityMatrix = $this->compatibility->getProductCompatibilityMatrix($product->id);

                    $currentCompatibility = null;
                    $targetCompatibility = null;

                    foreach ($compatibilityMatrix as $compat) {
                        if ($compat['firmware_version'] === $device->software_version) {
                            $currentCompatibility = $compat;
                        }
                        if ($targetVersion && $compat['firmware_version'] === $targetVersion) {
                            $targetCompatibility = $compat;
                        }
                    }

                    $canUpgrade = false;
                    $upgradeRecommendation = null;

                    if ($targetVersion && $targetCompatibility) {
                        if (in_array($targetCompatibility['compatibility_status'], ['compatible', 'compatible_with_issues'])) {
                            $canUpgrade = true;
                            $compatibleCount++;
                            $upgradeRecommendation = 'upgrade_recommended';
                        } else {
                            $incompatibleCount++;
                            $upgradeRecommendation = 'upgrade_not_recommended';
                        }
                    } else {
                        $unknownCount++;
                        $upgradeRecommendation = 'compatibility_unknown';
                    }

                    $results[] = [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'product' => [
                            'id' => $product->id,
                            'manufacturer' => $product->manufacturer->name,
                            'model' => $product->model_name
                        ],
                        'current_firmware' => $device->software_version,
                        'current_status' => $currentCompatibility['compatibility_status'] ?? 'unknown',
                        'target_firmware' => $targetVersion,
                        'target_status' => $targetCompatibility['compatibility_status'] ?? 'unknown',
                        'can_upgrade' => $canUpgrade,
                        'recommendation' => $upgradeRecommendation,
                        'notes' => $targetCompatibility['notes'] ?? null
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $this->successDataResponse([
                'target_firmware_version' => $validated['target_firmware_version'] ?? 'current',
                'processed' => count($devices),
                'compatible_count' => $compatibleCount,
                'incompatible_count' => $incompatibleCount,
                'unknown_count' => $unknownCount,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Bulk firmware compatibility check failed: ' . $e->getMessage(), 500);
        }
    }
}
