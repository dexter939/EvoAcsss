@extends('layouts.app')

@section('breadcrumb')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="{{ route('acs.vendors.index') }}">Vendor Library</a></li>
        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="{{ route('acs.vendors.manufacturers') }}">Manufacturers</a></li>
        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">{{ $manufacturer->name }}</li>
    </ol>
</nav>
@endsection

@section('page-title', $manufacturer->name)

@section('content')
<!-- Overview Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Products</p>
                            <h5 class="font-weight-bolder mb-0">{{ $manufacturer->products_count }}</h5>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                            <i class="fas fa-router text-lg opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Quirks</p>
                            <h5 class="font-weight-bolder mb-0">{{ $manufacturer->quirks_count }}</h5>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                            <i class="fas fa-exclamation-triangle text-lg opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Templates</p>
                            <h5 class="font-weight-bolder mb-0">{{ $manufacturer->templates_count }}</h5>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                            <i class="fas fa-file-code text-lg opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Status</p>
                            <h5 class="font-weight-bolder mb-0">
                                @if($manufacturer->is_certified)
                                    <span class="badge badge-sm bg-gradient-success">Certified</span>
                                @else
                                    <span class="badge badge-sm bg-gradient-secondary">Standard</span>
                                @endif
                            </h5>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                            <i class="fas fa-certificate text-lg opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manufacturer Info -->
<div class="row mt-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header pb-0">
                <h6>Manufacturer Information</h6>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item border-0 ps-0 pt-0 text-sm">
                        <strong class="text-dark">Category:</strong> &nbsp; {{ ucfirst($manufacturer->category) }}
                    </li>
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong class="text-dark">Country:</strong> &nbsp; {{ $manufacturer->country }}
                    </li>
                    @if($manufacturer->website)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong class="text-dark">Website:</strong> &nbsp; 
                        <a href="{{ $manufacturer->website }}" target="_blank">{{ $manufacturer->website }}</a>
                    </li>
                    @endif
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong class="text-dark">Protocol Support:</strong>
                        <div class="mt-2">
                            @if($manufacturer->tr069_support)
                                <span class="badge badge-sm bg-gradient-success me-1">TR-069</span>
                            @endif
                            @if($manufacturer->tr369_support)
                                <span class="badge badge-sm bg-gradient-info me-1">TR-369</span>
                            @endif
                            @if($manufacturer->tr104_support)
                                <span class="badge badge-sm bg-gradient-primary me-1">TR-104</span>
                            @endif
                        </div>
                    </li>
                    @if($manufacturer->notes)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong class="text-dark">Notes:</strong>
                        <p class="text-secondary mb-0 mt-2">{{ $manufacturer->notes }}</p>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Products List -->
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Products ({{ $manufacturer->products_count }})</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Model</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">WiFi</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Year</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($manufacturer->products as $product)
                            <tr>
                                <td>
                                    <div class="d-flex px-2 py-1">
                                        <div class="d-flex flex-column justify-content-center">
                                            <h6 class="mb-0 text-sm">{{ $product->model_name }}</h6>
                                            @if($product->hardware_version)
                                                <p class="text-xs text-secondary mb-0">HW: {{ $product->hardware_version }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">{{ $product->wifi_standard ?? 'N/A' }}</p>
                                </td>
                                <td class="align-middle text-center text-sm">
                                    <span class="text-secondary text-xs font-weight-bold">{{ $product->release_year }}</span>
                                </td>
                                <td class="align-middle text-center">
                                    <span class="badge badge-sm bg-gradient-{{ $product->is_active ? 'success' : 'secondary' }}">
                                        {{ $product->is_active ? 'Active' : 'Discontinued' }}
                                    </span>
                                </td>
                                <td class="align-middle">
                                    <a href="{{ route('acs.vendors.products.detail', $product->id) }}" 
                                       class="btn btn-link text-secondary mb-0">
                                        <i class="fas fa-eye text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-sm text-secondary">
                                    No products found for this manufacturer
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Known Quirks -->
        @if($manufacturer->quirks->count() > 0)
        <div class="card">
            <div class="card-header pb-0">
                <h6>Known Quirks & Issues ({{ $manufacturer->quirks->count() }})</h6>
            </div>
            <div class="card-body">
                @foreach($manufacturer->quirks as $quirk)
                <div class="alert alert-{{ 
                    $quirk->severity === 'critical' ? 'danger' : 
                    ($quirk->severity === 'high' ? 'warning' : 'info') 
                }} mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="alert-heading mb-1">
                                <span class="badge bg-dark me-2">{{ strtoupper($quirk->quirk_type) }}</span>
                                {{ $quirk->title }}
                            </h6>
                            <p class="mb-2">{{ $quirk->description }}</p>
                            @if($quirk->workaround)
                            <p class="mb-0">
                                <strong>Workaround:</strong> {{ $quirk->workaround }}
                            </p>
                            @endif
                        </div>
                        <span class="badge bg-{{ 
                            $quirk->severity === 'critical' ? 'danger' : 
                            ($quirk->severity === 'high' ? 'warning' : 'info') 
                        }}">{{ strtoupper($quirk->severity) }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
