@extends('layouts.app')

@section('breadcrumb', 'Vendor Library')
@section('page-title', 'Vendor Quirks Database')

@section('content')
<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h6>Filters</h6>
                    <a href="{{ route('acs.vendors.quirks') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('acs.vendors.quirks') }}">
                    <div class="row">
                        <div class="col-md-4">
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
                                <label>Severity</label>
                                <select name="severity" class="form-control">
                                    <option value="">All Severities</option>
                                    <option value="critical" {{ request('severity') === 'critical' ? 'selected' : '' }}>Critical</option>
                                    <option value="high" {{ request('severity') === 'high' ? 'selected' : '' }}>High</option>
                                    <option value="medium" {{ request('severity') === 'medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="low" {{ request('severity') === 'low' ? 'selected' : '' }}>Low</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Type</label>
                                <select name="quirk_type" class="form-control">
                                    <option value="">All Types</option>
                                    @foreach($quirkTypes as $type)
                                        <option value="{{ $type }}" {{ request('quirk_type') == $type ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="icon icon-shape bg-gradient-danger shadow text-center border-radius-md me-3">
                        <i class="fas fa-exclamation-circle text-lg opacity-10"></i>
                    </div>
                    <div>
                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Critical</p>
                        <h5 class="font-weight-bolder mb-0">{{ $quirks->where('severity', 'critical')->count() }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md me-3">
                        <i class="fas fa-exclamation-triangle text-lg opacity-10"></i>
                    </div>
                    <div>
                        <p class="text-sm mb-0 text-capitalize font-weight-bold">High</p>
                        <h5 class="font-weight-bolder mb-0">{{ $quirks->where('severity', 'high')->count() }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md me-3">
                        <i class="fas fa-info-circle text-lg opacity-10"></i>
                    </div>
                    <div>
                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Medium</p>
                        <h5 class="font-weight-bolder mb-0">{{ $quirks->where('severity', 'medium')->count() }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md me-3">
                        <i class="fas fa-check-circle text-lg opacity-10"></i>
                    </div>
                    <div>
                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Low</p>
                        <h5 class="font-weight-bolder mb-0">{{ $quirks->where('severity', 'low')->count() }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quirks List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Known Vendor Quirks ({{ $quirks->total() }})</h6>
            </div>
            <div class="card-body">
                @forelse($quirks as $quirk)
                <div class="alert alert-{{ 
                    $quirk->severity === 'critical' ? 'danger' : 
                    ($quirk->severity === 'high' ? 'warning' : 
                    ($quirk->severity === 'medium' ? 'info' : 'secondary')) 
                }} mb-3">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex align-items-start mb-2">
                                <span class="badge bg-{{ 
                                    $quirk->severity === 'critical' ? 'danger' : 
                                    ($quirk->severity === 'high' ? 'warning' : 
                                    ($quirk->severity === 'medium' ? 'info' : 'secondary')) 
                                }} me-2">{{ strtoupper($quirk->severity) }}</span>
                                <span class="badge bg-dark me-2">{{ strtoupper($quirk->quirk_type) }}</span>
                            </div>
                            
                            <h6 class="alert-heading mb-2">{{ $quirk->title }}</h6>
                            
                            <p class="mb-2">
                                <strong>Manufacturer:</strong> 
                                <a href="{{ route('acs.vendors.manufacturers.detail', $quirk->manufacturer->id) }}" class="text-dark">
                                    {{ $quirk->manufacturer->name }}
                                </a>
                                @if($quirk->product)
                                    / 
                                    <a href="{{ route('acs.vendors.products.detail', $quirk->product->id) }}" class="text-dark">
                                        {{ $quirk->product->model_name }}
                                    </a>
                                @endif
                            </p>
                            
                            <p class="mb-2">{{ $quirk->description }}</p>
                            
                            @if($quirk->affected_parameters)
                            <div class="mb-2">
                                <strong class="text-sm">Affected Parameters:</strong>
                                <p class="mb-0 font-monospace text-xs">{{ $quirk->affected_parameters }}</p>
                            </div>
                            @endif
                        </div>
                        
                        <div class="col-md-4">
                            @if($quirk->workaround)
                            <div class="bg-white bg-opacity-75 p-3 rounded">
                                <strong class="text-sm"><i class="fas fa-tools"></i> Workaround:</strong>
                                <p class="mb-0 text-sm mt-2">{{ $quirk->workaround }}</p>
                            </div>
                            @else
                            <div class="bg-white bg-opacity-50 p-3 rounded text-center">
                                <i class="fas fa-exclamation-circle text-muted"></i>
                                <p class="mb-0 text-sm text-muted">No workaround available</p>
                            </div>
                            @endif
                            
                            @if($quirk->bbf_issue_reference)
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-link"></i> BBF Issue: {{ $quirk->bbf_issue_reference }}
                                </small>
                            </div>
                            @endif
                            
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Discovered: {{ $quirk->discovered_date ? \Carbon\Carbon::parse($quirk->discovered_date)->format('Y-m-d') : 'N/A' }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No quirks found matching your filters.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Pagination -->
<div class="row mt-4">
    <div class="col-12">
        {{ $quirks->appends(request()->query())->links() }}
    </div>
</div>
@endsection
