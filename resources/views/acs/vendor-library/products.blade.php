@extends('layouts.app')

@section('breadcrumb', 'Vendor Library')
@section('page-title', 'Products & Models Database')

@section('content')
<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h6>Search & Filters</h6>
                    <a href="{{ route('acs.vendors.products') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('acs.vendors.products') }}">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Search Model</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Model name or manufacturer..." 
                                       value="{{ request('search') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Manufacturer</label>
                                <select name="manufacturer_id" class="form-control">
                                    <option value="">All Manufacturers</option>
                                    @foreach($manufacturers as $mfr)
                                        <option value="{{ $mfr->id }}" {{ request('manufacturer_id') == $mfr->id ? 'selected' : '' }}>
                                            {{ $mfr->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>WiFi Standard</label>
                                <select name="wifi_standard" class="form-control">
                                    <option value="">All Standards</option>
                                    @foreach($wifiStandards as $std)
                                        <option value="{{ $std }}" {{ request('wifi_standard') == $std ? 'selected' : '' }}>
                                            {{ $std }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Products Grid -->
<div class="row">
    @forelse($products as $product)
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">{{ $product->model_name }}</h6>
                        <p class="text-xs text-secondary mb-0">
                            <i class="fas fa-industry me-1"></i>{{ $product->manufacturer->name }}
                        </p>
                    </div>
                    <span class="badge badge-sm bg-gradient-{{ $product->is_active ? 'success' : 'secondary' }}">
                        {{ $product->is_active ? 'Active' : 'Discontinued' }}
                    </span>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row mb-2">
                    <div class="col-6">
                        <p class="text-xs text-secondary mb-0">WiFi Standard</p>
                        <p class="text-sm font-weight-bold mb-0">{{ $product->wifi_standard ?? 'N/A' }}</p>
                    </div>
                    <div class="col-6">
                        <p class="text-xs text-secondary mb-0">Release Year</p>
                        <p class="text-sm font-weight-bold mb-0">{{ $product->release_year }}</p>
                    </div>
                </div>

                @if($product->hardware_version)
                <div class="mb-2">
                    <p class="text-xs text-secondary mb-0">Hardware Version</p>
                    <p class="text-sm font-weight-bold mb-0">{{ $product->hardware_version }}</p>
                </div>
                @endif

                @if($product->firmware_version)
                <div class="mb-2">
                    <p class="text-xs text-secondary mb-0">Firmware Version</p>
                    <p class="text-sm font-weight-bold mb-0">{{ $product->firmware_version }}</p>
                </div>
                @endif

                <div class="mb-2">
                    <p class="text-xs text-secondary mb-1">Supported Protocols</p>
                    <div>
                        @if($product->supports_tr069)
                            <span class="badge badge-sm bg-gradient-success me-1">TR-069</span>
                        @endif
                        @if($product->supports_tr369)
                            <span class="badge badge-sm bg-gradient-info me-1">TR-369</span>
                        @endif
                    </div>
                </div>

                @if($product->max_wan_speed_mbps)
                <div class="mb-2">
                    <small class="text-muted">
                        <i class="fas fa-tachometer-alt"></i> Max WAN: {{ $product->max_wan_speed_mbps }} Mbps
                    </small>
                </div>
                @endif

                <div class="mt-3 pt-2 border-top">
                    <a href="{{ route('acs.vendors.products.detail', $product->id) }}" 
                       class="btn btn-sm btn-outline-primary w-100">
                        <i class="fas fa-info-circle"></i> View Full Specs
                    </a>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No products found matching your search criteria.
        </div>
    </div>
    @endforelse
</div>

<!-- Pagination -->
<div class="row mt-4">
    <div class="col-12">
        {{ $products->appends(request()->query())->links() }}
    </div>
</div>
@endsection
