<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RouterManufacturer;
use App\Models\RouterProduct;
use App\Models\FirmwareCompatibility;
use App\Models\VendorQuirk;
use App\Models\ConfigurationTemplateLibrary;
use App\Services\Vendor\VendorDetectionService;
use App\Services\Vendor\CompatibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VendorLibraryController extends Controller
{
    public function __construct(
        private VendorDetectionService $vendorDetection,
        private CompatibilityService $compatibility
    ) {}

    /**
     * GET /api/v1/vendors/manufacturers
     */
    public function getManufacturers(Request $request): JsonResponse
    {
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

        return response()->json([
            'success' => true,
            'data' => $manufacturers,
            'count' => $manufacturers->count()
        ]);
    }

    /**
     * GET /api/v1/vendors/manufacturers/{id}
     */
    public function getManufacturer(int $id): JsonResponse
    {
        $manufacturer = RouterManufacturer::with(['products', 'quirks', 'templates'])
            ->withCount(['products', 'quirks', 'templates'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $manufacturer
        ]);
    }

    /**
     * GET /api/v1/vendors/products
     */
    public function getProducts(Request $request): JsonResponse
    {
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

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => $products->count()
        ]);
    }

    /**
     * GET /api/v1/vendors/products/{id}
     */
    public function getProduct(int $id): JsonResponse
    {
        $product = RouterProduct::with(['manufacturer', 'compatibilities.firmwareVersion', 'quirks', 'templates'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * GET /api/v1/vendors/products/{id}/compatibility-matrix
     */
    public function getProductCompatibilityMatrix(int $id): JsonResponse
    {
        $matrix = $this->compatibility->getProductCompatibilityMatrix($id);

        return response()->json([
            'success' => true,
            'product_id' => $id,
            'data' => $matrix
        ]);
    }

    /**
     * GET /api/v1/vendors/quirks
     */
    public function getQuirks(Request $request): JsonResponse
    {
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

        return response()->json([
            'success' => true,
            'data' => $quirks,
            'count' => $quirks->count()
        ]);
    }

    /**
     * GET /api/v1/vendors/products/{id}/quirks
     */
    public function getProductQuirks(int $productId, Request $request): JsonResponse
    {
        $firmwareVersion = $request->input('firmware_version');
        
        $quirks = $this->compatibility->getApplicableQuirks($productId, $firmwareVersion);

        return response()->json([
            'success' => true,
            'product_id' => $productId,
            'firmware_version' => $firmwareVersion,
            'data' => $quirks,
            'count' => count($quirks)
        ]);
    }

    /**
     * GET /api/v1/vendors/templates
     */
    public function getTemplates(Request $request): JsonResponse
    {
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

        return response()->json([
            'success' => true,
            'data' => $templates,
            'count' => $templates->count()
        ]);
    }

    /**
     * GET /api/v1/vendors/templates/{id}
     */
    public function getTemplate(int $id): JsonResponse
    {
        $template = ConfigurationTemplateLibrary::with(['manufacturer', 'product'])
            ->findOrFail($id);

        $template->incrementUsageCount();

        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    /**
     * POST /api/v1/vendors/detect
     */
    public function detectVendor(Request $request): JsonResponse
    {
        $request->validate([
            'device_info' => 'required|array',
            'mac_address' => 'nullable|string'
        ]);

        $detection = $this->vendorDetection->detectFromTR069DeviceInfo($request->device_info);

        if (!$detection && $request->has('mac_address')) {
            $manufacturer = $this->vendorDetection->detectFromMacAddress($request->mac_address);
            if ($manufacturer) {
                $detection = [
                    'manufacturer' => $manufacturer,
                    'product' => null,
                    'confidence' => 60,
                    'method' => 'mac_address'
                ];
            }
        }

        return response()->json([
            'success' => $detection !== null,
            'data' => $detection,
            'message' => $detection ? 'Vendor detected successfully' : 'Vendor detection failed'
        ]);
    }

    /**
     * POST /api/v1/vendors/compatibility/check
     */
    public function checkCompatibility(Request $request): JsonResponse
    {
        $request->validate([
            'firmware_version_id' => 'required|integer|exists:firmware_versions,id',
            'product_id' => 'required|integer|exists:router_products,id'
        ]);

        $result = $this->compatibility->checkCompatibility(
            $request->firmware_version_id,
            $request->product_id
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * GET /api/v1/vendors/stats
     */
    public function getStatistics(): JsonResponse
    {
        $stats = $this->vendorDetection->getVendorStatistics();

        $additionalStats = [
            'total_quirks' => VendorQuirk::where('is_active', true)->count(),
            'total_templates' => ConfigurationTemplateLibrary::count(),
            'official_templates' => ConfigurationTemplateLibrary::where('is_official', true)->count(),
            'total_compatibility_entries' => FirmwareCompatibility::count(),
            'tested_compatibilities' => FirmwareCompatibility::where('tested', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge($stats, $additionalStats)
        ]);
    }
}
