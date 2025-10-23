@extends('layouts.app')

@section('breadcrumb', 'Vendor Library')
@section('page-title', 'Manufacturers Database')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h6>Filters</h6>
                    <a href="{{ route('acs.vendors.manufacturers') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('acs.vendors.manufacturers') }}">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" class="form-control">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>
                                            {{ ucfirst($cat) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Protocol Support</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tr069_support" value="1" 
                                        {{ request('tr069_support') ? 'checked' : '' }}>
                                    <label class="form-check-label">TR-069</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tr369_support" value="1" 
                                        {{ request('tr369_support') ? 'checked' : '' }}>
                                    <label class="form-check-label">TR-369 USP</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    @forelse($manufacturers as $manufacturer)
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">{{ $manufacturer->name }}</h6>
                    <span class="badge badge-sm bg-gradient-{{ $manufacturer->is_certified ? 'success' : 'secondary' }}">
                        {{ $manufacturer->is_certified ? 'Certified' : 'Standard' }}
                    </span>
                </div>
                <p class="text-xs text-secondary mb-0">{{ $manufacturer->country }}</p>
            </div>
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-6">
                        <p class="text-xs text-secondary mb-1">Category</p>
                        <p class="text-sm font-weight-bold mb-2">{{ ucfirst($manufacturer->category) }}</p>
                    </div>
                    <div class="col-6 text-end">
                        <p class="text-xs text-secondary mb-1">Products</p>
                        <p class="text-sm font-weight-bold mb-2">{{ $manufacturer->products_count }}</p>
                    </div>
                </div>
                
                <div class="mb-2">
                    <p class="text-xs text-secondary mb-1">Protocol Support</p>
                    <div>
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
                </div>

                @if($manufacturer->quirks_count > 0)
                <div class="alert alert-warning py-2 mb-2">
                    <small><i class="fas fa-exclamation-triangle"></i> {{ $manufacturer->quirks_count }} known quirks</small>
                </div>
                @endif

                @if($manufacturer->templates_count > 0)
                <div class="mb-2">
                    <small class="text-success">
                        <i class="fas fa-file-code"></i> {{ $manufacturer->templates_count }} templates available
                    </small>
                </div>
                @endif

                <div class="mt-3">
                    <a href="{{ route('acs.vendors.manufacturers.detail', $manufacturer->id) }}" 
                       class="btn btn-sm btn-outline-primary w-100">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No manufacturers found matching your filters.
        </div>
    </div>
    @endforelse
</div>

<div class="row mt-4">
    <div class="col-12">
        {{ $manufacturers->links() }}
    </div>
</div>
@endsection
