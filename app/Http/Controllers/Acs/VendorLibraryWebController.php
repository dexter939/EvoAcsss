<?php

namespace App\Http\Controllers\Acs;

use App\Http\Controllers\Controller;
use App\Models\RouterManufacturer;
use App\Models\RouterProduct;
use App\Models\VendorQuirk;
use App\Models\ConfigurationTemplateLibrary;
use App\Models\FirmwareCompatibility;
use App\Services\Vendor\VendorDetectionService;
use App\Services\Vendor\CompatibilityService;
use Illuminate\Http\Request;

class VendorLibraryWebController extends Controller
{
    public function __construct(
        private VendorDetectionService $vendorDetection,
        private CompatibilityService $compatibility
    ) {}

    /**
     * Vendor Library Dashboard - Overview
     */
    public function index()
    {
        $stats = [
            'manufacturers_count' => RouterManufacturer::count(),
            'products_count' => RouterProduct::count(),
            'quirks_count' => VendorQuirk::where('is_active', true)->count(),
            'templates_count' => ConfigurationTemplateLibrary::count(),
            'tr069_vendors' => RouterManufacturer::where('tr069_support', true)->count(),
            'tr369_vendors' => RouterManufacturer::where('tr369_support', true)->count(),
        ];

        $recentProducts = RouterProduct::with('manufacturer')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $topManufacturers = RouterManufacturer::withCount('products')
            ->orderBy('products_count', 'desc')
            ->limit(6)
            ->get();

        return view('acs.vendor-library.index', compact('stats', 'recentProducts', 'topManufacturers'));
    }

    /**
     * Manufacturers List
     */
    public function manufacturers(Request $request)
    {
        $query = RouterManufacturer::withCount(['products', 'quirks', 'templates']);

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('tr069_support')) {
            $query->where('tr069_support', true);
        }

        if ($request->has('tr369_support')) {
            $query->where('tr369_support', true);
        }

        $manufacturers = $query->orderBy('name')->paginate(12);

        $categories = RouterManufacturer::distinct()->pluck('category');

        return view('acs.vendor-library.manufacturers', compact('manufacturers', 'categories'));
    }

    /**
     * Manufacturer Detail
     */
    public function manufacturerDetail(int $id)
    {
        $manufacturer = RouterManufacturer::with(['products', 'quirks' => function ($q) {
            $q->where('is_active', true);
        }, 'templates'])
        ->withCount(['products', 'quirks', 'templates'])
        ->findOrFail($id);

        return view('acs.vendor-library.manufacturer-detail', compact('manufacturer'));
    }

    /**
     * Products List
     */
    public function products(Request $request)
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

        $products = $query->orderBy('release_year', 'desc')->paginate(15);

        $manufacturers = RouterManufacturer::orderBy('name')->get();
        $wifiStandards = RouterProduct::distinct()->pluck('wifi_standard')->filter();

        return view('acs.vendor-library.products', compact('products', 'manufacturers', 'wifiStandards'));
    }

    /**
     * Product Detail with Compatibility Matrix
     */
    public function productDetail(int $id)
    {
        $product = RouterProduct::with(['manufacturer', 'compatibilities.firmwareVersion', 'quirks' => function ($q) {
            $q->where('is_active', true);
        }, 'templates'])
        ->findOrFail($id);

        $compatibilityMatrix = $this->compatibility->getProductCompatibilityMatrix($id);

        return view('acs.vendor-library.product-detail', compact('product', 'compatibilityMatrix'));
    }

    /**
     * Vendor Quirks Browser
     */
    public function quirks(Request $request)
    {
        $query = VendorQuirk::with(['manufacturer', 'product'])
            ->where('is_active', true);

        if ($request->has('manufacturer_id')) {
            $query->where('manufacturer_id', $request->manufacturer_id);
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('quirk_type')) {
            $query->where('quirk_type', $request->quirk_type);
        }

        $quirks = $query->orderBy('severity', 'desc')->paginate(20);

        $manufacturers = RouterManufacturer::orderBy('name')->get();
        $quirkTypes = VendorQuirk::distinct()->pluck('quirk_type')->filter();

        return view('acs.vendor-library.quirks', compact('quirks', 'manufacturers', 'quirkTypes'));
    }

    /**
     * Configuration Templates Library
     */
    public function templates(Request $request)
    {
        $query = ConfigurationTemplateLibrary::with(['manufacturer', 'product']);

        if ($request->has('manufacturer_id')) {
            $query->where('manufacturer_id', $request->manufacturer_id);
        }

        if ($request->has('category')) {
            $query->where('template_category', $request->category);
        }

        if ($request->has('protocol')) {
            $query->where('protocol', $request->protocol);
        }

        $templates = $query->orderBy('usage_count', 'desc')->paginate(15);

        $manufacturers = RouterManufacturer::orderBy('name')->get();
        $categories = ConfigurationTemplateLibrary::distinct()->pluck('template_category')->filter();
        $protocols = ConfigurationTemplateLibrary::distinct()->pluck('protocol')->filter();

        return view('acs.vendor-library.templates', compact('templates', 'manufacturers', 'categories', 'protocols'));
    }
}
