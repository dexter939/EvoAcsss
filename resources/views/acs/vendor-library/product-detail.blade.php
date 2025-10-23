@extends('layouts.app')

@section('breadcrumb')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="{{ route('acs.vendors.index') }}">Vendor Library</a></li>
        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="{{ route('acs.vendors.products') }}">Products</a></li>
        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">{{ $product->model_name }}</li>
    </ol>
</nav>
@endsection

@section('page-title', $product->model_name)

@section('content')
<!-- Product Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">{{ $product->model_name }}</h4>
                        <p class="text-muted mb-0">
                            <i class="fas fa-industry me-1"></i>
                            <a href="{{ route('acs.vendors.manufacturers.detail', $product->manufacturer->id) }}">
                                {{ $product->manufacturer->name }}
                            </a>
                        </p>
                    </div>
                    <div class="text-end">
                        <span class="badge badge-lg bg-gradient-{{ $product->is_active ? 'success' : 'secondary' }} mb-2">
                            {{ $product->is_active ? 'Active Product' : 'Discontinued' }}
                        </span>
                        <p class="text-sm mb-0">Released: {{ $product->release_year }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Specifications -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Technical Specifications</h6>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item border-0 ps-0 pt-0 text-sm">
                        <strong>Model Name:</strong><br>
                        {{ $product->model_name }}
                    </li>
                    @if($product->hardware_version)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong>Hardware Version:</strong><br>
                        {{ $product->hardware_version }}
                    </li>
                    @endif
                    @if($product->firmware_version)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong>Firmware Version:</strong><br>
                        {{ $product->firmware_version }}
                    </li>
                    @endif
                    @if($product->wifi_standard)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong>WiFi Standard:</strong><br>
                        {{ $product->wifi_standard }}
                    </li>
                    @endif
                    @if($product->max_wan_speed_mbps)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong>Max WAN Speed:</strong><br>
                        {{ $product->max_wan_speed_mbps }} Mbps
                    </li>
                    @endif
                    @if($product->cpu_model)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong>CPU Model:</strong><br>
                        {{ $product->cpu_model }}
                    </li>
                    @endif
                    @if($product->ram_mb)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong>RAM:</strong><br>
                        {{ $product->ram_mb }} MB
                    </li>
                    @endif
                    @if($product->flash_mb)
                    <li class="list-group-item border-0 ps-0 text-sm">
                        <strong>Flash Storage:</strong><br>
                        {{ $product->flash_mb }} MB
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header pb-0">
                <h6>Protocol Support</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    @if($product->supports_tr069)
                        <span class="badge badge-lg bg-gradient-success mb-2">
                            <i class="fas fa-check"></i> TR-069 CWMP
                        </span>
                    @endif
                    @if($product->supports_tr369)
                        <span class="badge badge-lg bg-gradient-info mb-2">
                            <i class="fas fa-check"></i> TR-369 USP
                        </span>
                    @endif
                </div>
                @if($product->notes)
                <div class="alert alert-secondary">
                    <small>{{ $product->notes }}</small>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Compatibility & Templates -->
    <div class="col-lg-8">
        <!-- Firmware Compatibility Matrix -->
        <div class="card mb-4">
            <div class="card-header pb-0">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Firmware Compatibility Matrix</h6>
                    <span class="badge bg-gradient-info">{{ count($compatibilityMatrix) }} Versions</span>
                </div>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                @if(count($compatibilityMatrix) > 0)
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Firmware</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Notes</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tested</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($compatibilityMatrix as $compat)
                            <tr>
                                <td>
                                    <div class="d-flex px-2 py-1">
                                        <div class="d-flex flex-column justify-content-center">
                                            <h6 class="mb-0 text-sm">{{ $compat['firmware_version'] }}</h6>
                                            @if($compat['release_date'])
                                                <p class="text-xs text-secondary mb-0">{{ \Carbon\Carbon::parse($compat['release_date'])->format('Y-m-d') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match($compat['compatibility_status']) {
                                            'compatible' => 'success',
                                            'compatible_with_issues' => 'warning',
                                            'incompatible' => 'danger',
                                            'beta' => 'info',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="badge badge-sm bg-gradient-{{ $badgeClass }}">
                                        {{ str_replace('_', ' ', ucfirst($compat['compatibility_status'])) }}
                                    </span>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0">{{ $compat['notes'] ?? 'N/A' }}</p>
                                </td>
                                <td class="align-middle text-center">
                                    @if($compat['tested_date'])
                                        <span class="text-secondary text-xs">
                                            {{ \Carbon\Carbon::parse($compat['tested_date'])->format('Y-m-d') }}
                                        </span>
                                    @else
                                        <span class="text-secondary text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="p-3">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> No firmware compatibility data available yet.
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Configuration Templates -->
        @if($product->templates->count() > 0)
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Available Configuration Templates ({{ $product->templates->count() }})</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($product->templates as $template)
                    <div class="col-md-6 mb-3">
                        <div class="card border">
                            <div class="card-body p-3">
                                <h6 class="mb-1">{{ $template->template_name }}</h6>
                                <p class="text-xs text-secondary mb-2">
                                    <span class="badge badge-sm bg-gradient-primary">{{ $template->template_category }}</span>
                                    <span class="badge badge-sm bg-gradient-info">{{ $template->protocol }}</span>
                                </p>
                                @if($template->description)
                                <p class="text-xs mb-2">{{ $template->description }}</p>
                                @endif
                                <small class="text-muted">
                                    <i class="fas fa-download"></i> Used {{ $template->usage_count }} times
                                </small>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Known Quirks -->
        @if($product->quirks->count() > 0)
        <div class="card">
            <div class="card-header pb-0">
                <h6>Known Issues & Quirks ({{ $product->quirks->count() }})</h6>
            </div>
            <div class="card-body">
                @foreach($product->quirks as $quirk)
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
                            <p class="mb-2 text-sm">{{ $quirk->description }}</p>
                            @if($quirk->workaround)
                            <div class="bg-white bg-opacity-50 p-2 rounded">
                                <strong class="text-xs">Workaround:</strong>
                                <p class="mb-0 text-xs">{{ $quirk->workaround }}</p>
                            </div>
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
