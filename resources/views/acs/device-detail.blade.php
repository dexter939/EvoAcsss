@extends('layouts.app')

@section('breadcrumb')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-white" href="{{ route('acs.dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-white" href="{{ route('acs.devices') }}">Dispositivi</a></li>
        <li class="breadcrumb-item text-sm text-white active" aria-current="page">{{ $device->serial_number }}</li>
    </ol>
</nav>
@endsection

@section('page-title')
<i class="fas fa-router me-2"></i>{{ $device->manufacturer }} {{ $device->model_name }}
<small class="text-sm opacity-8 ms-2">({{ $device->serial_number }})</small>
@endsection

@section('content')

<!-- Device Status Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient-{{ $device->status == 'online' ? 'success' : ($device->status == 'error' ? 'danger' : 'secondary') }}">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="d-flex flex-column h-100">
                            <p class="mb-1 pt-2 text-white opacity-8 text-xs font-weight-bold">Status</p>
                            <h5 class="font-weight-bolder text-white">
                                <i class="fas fa-circle me-2" style="font-size: 0.6rem;"></i>{{ ucfirst($device->status) }}
                            </h5>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="d-flex flex-column h-100">
                            <p class="mb-1 pt-2 text-white opacity-8 text-xs font-weight-bold">Ultimo Contatto</p>
                            <h6 class="font-weight-bolder text-white text-sm">
                                {{ $device->last_inform ? $device->last_inform->format('d/m H:i') : 'Mai' }}
                            </h6>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="d-flex flex-column h-100">
                            <p class="mb-1 pt-2 text-white opacity-8 text-xs font-weight-bold">Protocollo</p>
                            <h6 class="font-weight-bolder text-white text-sm">
                                {{ strtoupper($device->protocol_type ?? 'TR-069') }}
                                @if($device->mtp_type)
                                <span class="text-xs opacity-8">({{ strtoupper($device->mtp_type) }})</span>
                                @endif
                            </h6>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="d-flex flex-column h-100">
                            <p class="mb-1 pt-2 text-white opacity-8 text-xs font-weight-bold">IP Address</p>
                            <h6 class="font-weight-bolder text-white text-sm">{{ $device->ip_address ?? 'N/A' }}</h6>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="d-flex flex-column h-100">
                            <p class="mb-1 pt-2 text-white opacity-8 text-xs font-weight-bold">Firmware</p>
                            <h6 class="font-weight-bolder text-white text-sm">{{ $device->firmware_version ?? 'N/A' }}</h6>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="d-flex flex-column h-100">
                            <p class="mb-1 pt-2 text-white opacity-8 text-xs font-weight-bold">Hardware</p>
                            <h6 class="font-weight-bolder text-white text-sm">{{ $device->hardware_version ?? 'N/A' }}</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-3">
                <div class="row g-2">
                    <div class="col-lg-2 col-md-4 col-6">
                        <button class="btn btn-gradient-primary btn-sm w-100" onclick="provisionDevice({{ $device->id }}, '{{ $device->serial_number }}')">
                            <i class="fas fa-cog me-2"></i>Provisioning
                        </button>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <button class="btn btn-gradient-warning btn-sm w-100" onclick="rebootDevice({{ $device->id }}, '{{ $device->serial_number }}')">
                            <i class="fas fa-sync me-2"></i>Reboot
                        </button>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <button class="btn btn-gradient-info btn-sm w-100" onclick="openParametersModal()">
                            <i class="fas fa-list me-2"></i>Get Parameters
                        </button>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <button class="btn btn-gradient-success btn-sm w-100" onclick="triggerNetworkScan({{ $device->id }})">
                            <i class="fas fa-network-wired me-2"></i>Scan Network
                        </button>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <button class="btn btn-gradient-dark btn-sm w-100" onclick="aiAnalyzeDeviceHistory({{ $device->id }})">
                            <i class="fas fa-magic me-2"></i>AI Analysis
                        </button>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <a href="{{ route('acs.devices') }}" class="btn btn-secondary btn-sm w-100">
                            <i class="fas fa-arrow-left me-2"></i>Indietro
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Tabs -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <ul class="nav nav-tabs" id="deviceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            <i class="fas fa-chart-line me-2"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="parameters-tab" data-bs-toggle="tab" data-bs-target="#parameters" type="button" role="tab">
                            <i class="fas fa-database me-2"></i>Parametri ({{ $device->parameters->count() }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="network-tab" data-bs-toggle="tab" data-bs-target="#network" type="button" role="tab">
                            <i class="fas fa-wifi me-2"></i>WiFi & Network
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="diagnostics-tab" data-bs-toggle="tab" data-bs-target="#diagnostics" type="button" role="tab">
                            <i class="fas fa-stethoscope me-2"></i>Diagnostics
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab">
                            <i class="fas fa-tasks me-2"></i>Tasks & Commands
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab">
                            <i class="fas fa-history me-2"></i>Eventi & Allarmi
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="deviceTabsContent">
                    
                    <!-- TAB 1: OVERVIEW -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <!-- Device Info Card -->
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header pb-0">
                                        <h6><i class="fas fa-info-circle me-2 text-primary"></i>Informazioni Dispositivo</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="text-xs text-secondary border-0" style="width: 45%;">Serial Number</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->serial_number }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Manufacturer</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->manufacturer }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Model</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->model_name }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">OUI</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->oui ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Product Class</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->product_class ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Firmware Version</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->firmware_version ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Hardware Version</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->hardware_version ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Data Model</td>
                                                <td class="text-xs font-weight-bold border-0">
                                                    @if($device->dataModel)
                                                        <span class="badge bg-gradient-success">{{ $device->dataModel->vendor }} {{ $device->dataModel->model_name }}</span>
                                                        <button class="btn btn-xs btn-link p-0 ms-1" onclick="openDataModelModal()" title="Cambia Data Model">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    @else
                                                        <span class="text-muted">Non assegnato</span>
                                                        <button class="btn btn-xs btn-primary ms-2" onclick="openDataModelModal()">
                                                            <i class="fas fa-plus me-1"></i>Assegna
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Profilo Config.</td>
                                                <td class="text-xs font-weight-bold border-0">
                                                    @if($device->configurationProfile)
                                                        <span class="badge bg-gradient-info">{{ $device->configurationProfile->name }}</span>
                                                    @else
                                                        <span class="text-muted">Nessuno</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @if($device->service)
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Servizio</td>
                                                <td class="text-xs font-weight-bold border-0">
                                                    <a href="{{ route('acs.services.detail', $device->service->id) }}" class="text-primary">
                                                        {{ $device->service->name }}
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Cliente</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->service->customer->name ?? 'N/A' }}</td>
                                            </tr>
                                            @endif
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Connection Info Card -->
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header pb-0">
                                        <h6><i class="fas fa-plug me-2 text-success"></i>Connessione & Management</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="text-xs text-secondary border-0" style="width: 45%;">Status</td>
                                                <td class="border-0">
                                                    <span class="badge bg-gradient-{{ $device->status == 'online' ? 'success' : ($device->status == 'error' ? 'danger' : 'secondary') }}">
                                                        {{ ucfirst($device->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Protocol</td>
                                                <td class="text-xs font-weight-bold border-0">
                                                    {{ strtoupper($device->protocol_type ?? 'TR-069') }}
                                                    @if($device->mtp_type)
                                                    <span class="text-xxs text-muted">({{ strtoupper($device->mtp_type) }})</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">IP Address</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->ip_address ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Connection Request URL</td>
                                                <td class="text-xxs font-weight-bold border-0" style="word-break: break-all;">
                                                    {{ $device->connection_request_url ?? 'N/A' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Ultimo Inform</td>
                                                <td class="text-xs font-weight-bold border-0">
                                                    {{ $device->last_inform ? $device->last_inform->format('d/m/Y H:i:s') : 'Mai' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Periodic Inform</td>
                                                <td class="text-xs font-weight-bold border-0">
                                                    @php
                                                        $periodicInform = $device->parameters->where('parameter_path', 'LIKE', '%PeriodicInformInterval%')->first();
                                                    @endphp
                                                    {{ $periodicInform ? $periodicInform->parameter_value . ' sec' : 'N/A' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Registrato il</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->created_at->format('d/m/Y H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Ultimo Aggiornamento</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $device->updated_at->format('d/m/Y H:i') }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistics Card -->
                            <div class="col-lg-4 col-md-12 mb-4">
                                <div class="card h-100">
                                    <div class="card-header pb-0">
                                        <h6><i class="fas fa-chart-bar me-2 text-warning"></i>Statistiche</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <div class="text-center">
                                                    <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                                        <i class="fas fa-database text-lg opacity-10" aria-hidden="true"></i>
                                                    </div>
                                                    <h6 class="text-sm mt-2 mb-0">Parametri</h6>
                                                    <h5 class="font-weight-bolder mb-0">{{ $device->parameters->count() }}</h5>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="text-center">
                                                    <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                                                        <i class="fas fa-tasks text-lg opacity-10" aria-hidden="true"></i>
                                                    </div>
                                                    <h6 class="text-sm mt-2 mb-0">Tasks</h6>
                                                    <h5 class="font-weight-bolder mb-0">{{ $recentTasks->count() }}</h5>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="text-center">
                                                    <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                                                        <i class="fas fa-clock text-lg opacity-10" aria-hidden="true"></i>
                                                    </div>
                                                    <h6 class="text-sm mt-2 mb-0">Pending Cmds</h6>
                                                    <h5 class="font-weight-bolder mb-0">{{ $pendingCommands->where('status', 'pending')->count() }}</h5>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="text-center">
                                                    <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                                        <i class="fas fa-network-wired text-lg opacity-10" aria-hidden="true"></i>
                                                    </div>
                                                    <h6 class="text-sm mt-2 mb-0">LAN Clients</h6>
                                                    <h5 class="font-weight-bolder mb-0" id="lan-clients-count">-</h5>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="horizontal dark my-3">
                                        <div class="row">
                                            <div class="col-12">
                                                <p class="text-xs mb-1"><i class="fas fa-circle text-success me-2"></i>Uptime dispositivo</p>
                                                <h6 class="font-weight-bolder mb-0" id="device-uptime">
                                                    @php
                                                        $uptime = $device->parameters->where('parameter_path', 'LIKE', '%UpTime%')->first();
                                                        if ($uptime && is_numeric($uptime->parameter_value)) {
                                                            $seconds = (int)$uptime->parameter_value;
                                                            $days = floor($seconds / 86400);
                                                            $hours = floor(($seconds % 86400) / 3600);
                                                            echo "{$days}d {$hours}h";
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    @endphp
                                                </h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Performance Chart (Placeholder) -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header pb-0">
                                        <h6><i class="fas fa-chart-area me-2"></i>Performance Trends (Last 24h)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div style="position: relative; height: 300px;">
                                            <canvas id="performanceChart"></canvas>
                                        </div>
                                        <p class="text-xs text-muted text-center mt-3">
                                            <i class="fas fa-info-circle me-1"></i>Grafico delle performance del dispositivo nelle ultime 24 ore
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TAB 2: PARAMETERS -->
                    <div class="tab-pane fade" id="parameters" role="tabpanel">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="parameterSearch" placeholder="Cerca parametri...">
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-sm btn-primary" onclick="openParametersModal()">
                                    <i class="fas fa-download me-2"></i>Get Parameters from Device
                                </button>
                                <button class="btn btn-sm btn-success" onclick="openSetParametersModal()">
                                    <i class="fas fa-edit me-2"></i>Set Parameters
                                </button>
                            </div>
                        </div>
                        
                        @if($device->parameters->count() > 0)
                        <div class="accordion" id="parametersAccordion">
                            @php
                                // Raggruppa parametri per categoria
                                $groups = [
                                    'DeviceInfo' => ['icon' => 'info-circle', 'color' => 'primary', 'params' => []],
                                    'ManagementServer' => ['icon' => 'server', 'color' => 'success', 'params' => []],
                                    'WANDevice' => ['icon' => 'globe', 'color' => 'info', 'params' => []],
                                    'LANDevice' => ['icon' => 'network-wired', 'color' => 'warning', 'params' => []],
                                    'WLANConfiguration' => ['icon' => 'wifi', 'color' => 'danger', 'params' => []],
                                    'Other' => ['icon' => 'cog', 'color' => 'secondary', 'params' => []],
                                ];
                                
                                foreach ($device->parameters as $param) {
                                    $grouped = false;
                                    foreach ($groups as $key => &$group) {
                                        if (stripos($param->parameter_path, $key) !== false) {
                                            $group['params'][] = $param;
                                            $grouped = true;
                                            break;
                                        }
                                    }
                                    if (!$grouped) {
                                        $groups['Other']['params'][] = $param;
                                    }
                                }
                            @endphp
                            
                            @foreach($groups as $groupName => $groupData)
                                @if(count($groupData['params']) > 0)
                                <div class="accordion-item mb-3">
                                    <h2 class="accordion-header" id="heading{{ $groupName }}">
                                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $groupName }}">
                                            <i class="fas fa-{{ $groupData['icon'] }} me-2 text-{{ $groupData['color'] }}"></i>
                                            <strong>{{ $groupName }}</strong>
                                            <span class="badge bg-gradient-{{ $groupData['color'] }} ms-2">{{ count($groupData['params']) }}</span>
                                        </button>
                                    </h2>
                                    <div id="collapse{{ $groupName }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#parametersAccordion">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover align-items-center mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 50%;">Parameter Path</th>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Value</th>
                                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Type</th>
                                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Updated</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($groupData['params'] as $param)
                                                        <tr class="parameter-row">
                                                            <td class="text-xs">
                                                                <code class="text-xs">{{ $param->parameter_path }}</code>
                                                            </td>
                                                            <td class="text-xs font-weight-bold">
                                                                @if(strlen($param->parameter_value) > 50)
                                                                    <span title="{{ $param->parameter_value }}">
                                                                        {{ substr($param->parameter_value, 0, 50) }}...
                                                                    </span>
                                                                @else
                                                                    {{ $param->parameter_value }}
                                                                @endif
                                                            </td>
                                                            <td class="text-xs text-center">
                                                                <span class="badge badge-sm bg-secondary">{{ $param->parameter_type ?? 'string' }}</span>
                                                            </td>
                                                            <td class="text-xxs text-center text-muted">
                                                                {{ $param->last_updated ? $param->last_updated->format('d/m H:i') : '-' }}
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                        @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Nessun parametro disponibile. Usa il pulsante "Get Parameters from Device" per recuperarli dal dispositivo.
                        </div>
                        @endif
                    </div>
                    
                    <!-- TAB 3: NETWORK & WIFI -->
                    <div class="tab-pane fade" id="network" role="tabpanel">
                        <div class="row">
                            <!-- WAN Configuration -->
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header pb-0">
                                        <h6><i class="fas fa-globe me-2 text-info"></i>WAN Connection</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $wanIP = $device->parameters->where('parameter_path', 'LIKE', '%ExternalIPAddress%')->first();
                                            $wanMask = $device->parameters->where('parameter_path', 'LIKE', '%SubnetMask%')->first();
                                            $wanGateway = $device->parameters->where('parameter_path', 'LIKE', '%DefaultGateway%')->first();
                                            $wanDNS = $device->parameters->where('parameter_path', 'LIKE', '%DNSServers%')->first();
                                        @endphp
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="text-xs text-secondary border-0" style="width: 40%;">External IP</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wanIP->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Subnet Mask</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wanMask->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Default Gateway</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wanGateway->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">DNS Servers</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wanDNS->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- LAN Configuration -->
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header pb-0">
                                        <h6><i class="fas fa-network-wired me-2 text-warning"></i>LAN Configuration</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $lanIP = $device->parameters->where('parameter_path', 'LIKE', '%LANHostConfigManagement%IPInterfaceIPAddress%')->first();
                                            $lanMask = $device->parameters->where('parameter_path', 'LIKE', '%LANHostConfigManagement%IPInterfaceSubnetMask%')->first();
                                            $dhcpEnable = $device->parameters->where('parameter_path', 'LIKE', '%DHCPServerEnable%')->first();
                                            $dhcpMin = $device->parameters->where('parameter_path', 'LIKE', '%MinAddress%')->first();
                                            $dhcpMax = $device->parameters->where('parameter_path', 'LIKE', '%MaxAddress%')->first();
                                        @endphp
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="text-xs text-secondary border-0" style="width: 40%;">LAN IP</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $lanIP->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Subnet Mask</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $lanMask->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">DHCP Server</td>
                                                <td class="text-xs font-weight-bold border-0">
                                                    @if($dhcpEnable)
                                                        <span class="badge bg-gradient-{{ $dhcpEnable->parameter_value == 'true' ? 'success' : 'secondary' }}">
                                                            {{ $dhcpEnable->parameter_value == 'true' ? 'Enabled' : 'Disabled' }}
                                                        </span>
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">DHCP Range</td>
                                                <td class="text-xs font-weight-bold border-0">
                                                    {{ $dhcpMin->parameter_value ?? '?' }} - {{ $dhcpMax->parameter_value ?? '?' }}
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- WiFi 2.4GHz -->
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header pb-0">
                                        <h6><i class="fas fa-wifi me-2 text-primary"></i>WiFi 2.4 GHz</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $wifi24_enable = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.1.Enable%')->first();
                                            $wifi24_ssid = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.1.SSID%')->first();
                                            $wifi24_channel = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.1.Channel%')->first();
                                            $wifi24_security = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.1.BeaconType%')->first();
                                            $wifi24_standard = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.1.Standard%')->first();
                                        @endphp
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="text-xs text-secondary border-0" style="width: 40%;">Status</td>
                                                <td class="border-0">
                                                    @if($wifi24_enable)
                                                        <span class="badge bg-gradient-{{ $wifi24_enable->parameter_value == 'true' ? 'success' : 'secondary' }}">
                                                            {{ $wifi24_enable->parameter_value == 'true' ? 'Enabled' : 'Disabled' }}
                                                        </span>
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">SSID</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wifi24_ssid->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Channel</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wifi24_channel->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Security</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wifi24_security->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Standard</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wifi24_standard->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- WiFi 5GHz -->
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header pb-0">
                                        <h6><i class="fas fa-wifi me-2 text-danger"></i>WiFi 5 GHz</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $wifi5_enable = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.2.Enable%')->first();
                                            $wifi5_ssid = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.2.SSID%')->first();
                                            $wifi5_channel = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.2.Channel%')->first();
                                            $wifi5_security = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.2.BeaconType%')->first();
                                            $wifi5_standard = $device->parameters->where('parameter_path', 'LIKE', '%WLANConfiguration.2.Standard%')->first();
                                        @endphp
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="text-xs text-secondary border-0" style="width: 40%;">Status</td>
                                                <td class="border-0">
                                                    @if($wifi5_enable)
                                                        <span class="badge bg-gradient-{{ $wifi5_enable->parameter_value == 'true' ? 'success' : 'secondary' }}">
                                                            {{ $wifi5_enable->parameter_value == 'true' ? 'Enabled' : 'Disabled' }}
                                                        </span>
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">SSID</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wifi5_ssid->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Channel</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wifi5_channel->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Security</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wifi5_security->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-xs text-secondary border-0">Standard</td>
                                                <td class="text-xs font-weight-bold border-0">{{ $wifi5_standard->parameter_value ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Network Topology Map -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                        <h6><i class="fas fa-sitemap me-2"></i>Network Topology Map</h6>
                                        <button class="btn btn-sm btn-primary" onclick="triggerNetworkScan({{ $device->id }})">
                                            <i class="fas fa-sync me-2"></i>Scan Network
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div id="network-stats" class="row mb-3">
                                            <div class="col-3 text-center">
                                                <div class="text-sm text-muted">Total</div>
                                                <div class="h5 mb-0" id="stats-total">0</div>
                                            </div>
                                            <div class="col-3 text-center">
                                                <div class="text-sm text-muted">LAN</div>
                                                <div class="h5 mb-0" id="stats-lan">0</div>
                                            </div>
                                            <div class="col-3 text-center">
                                                <div class="text-sm text-muted">WiFi 2.4GHz</div>
                                                <div class="h5 mb-0" id="stats-wifi24">0</div>
                                            </div>
                                            <div class="col-3 text-center">
                                                <div class="text-sm text-muted">WiFi 5GHz</div>
                                                <div class="h5 mb-0" id="stats-wifi5">0</div>
                                            </div>
                                        </div>
                                        
                                        <div id="network-topology-container" style="position: relative; height: 400px; border: 1px solid #e9ecef; border-radius: 0.5rem; background: #f8f9fa;">
                                            <div class="d-flex justify-content-center align-items-center h-100">
                                                <div class="text-center text-muted">
                                                    <i class="fas fa-network-wired fa-3x mb-3"></i>
                                                    <p>Click "Scan Network" per visualizzare i client connessi</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div id="network-clients-list" class="mt-3" style="display: none;">
                                            <h6 class="text-sm font-weight-bold mb-2">Connected Clients</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm align-items-center mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Device</th>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">IP Address</th>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Connection</th>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Signal</th>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Last Seen</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="clients-table-body">
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TAB 4: DIAGNOSTICS -->
                    <div class="tab-pane fade" id="diagnostics" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <button class="btn btn-outline-primary w-100" onclick="openDiagnosticModal('ping', {{ $device->id }})">
                                    <i class="fas fa-network-wired fa-2x mb-2"></i>
                                    <p class="mb-0">Ping Test</p>
                                </button>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <button class="btn btn-outline-primary w-100" onclick="openDiagnosticModal('traceroute', {{ $device->id }})">
                                    <i class="fas fa-route fa-2x mb-2"></i>
                                    <p class="mb-0">Traceroute</p>
                                </button>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <button class="btn btn-outline-primary w-100" onclick="openDiagnosticModal('download', {{ $device->id }})">
                                    <i class="fas fa-download fa-2x mb-2"></i>
                                    <p class="mb-0">Download Test</p>
                                </button>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <button class="btn btn-outline-primary w-100" onclick="openDiagnosticModal('upload', {{ $device->id }})">
                                    <i class="fas fa-upload fa-2x mb-2"></i>
                                    <p class="mb-0">Upload Test</p>
                                </button>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6><i class="fas fa-history me-2"></i>Recent Diagnostic Tests</h6>
                            </div>
                            <div class="card-body" id="diagnostic-history">
                                <p class="text-sm text-muted text-center py-4">
                                    <i class="fas fa-info-circle me-2"></i>Nessun test diagnostico disponibile
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TAB 5: TASKS & COMMANDS -->
                    <div class="tab-pane fade" id="tasks" role="tabpanel">
                        <!-- Recent Tasks -->
                        <div class="card mb-4">
                            <div class="card-header pb-0">
                                <h6><i class="fas fa-tasks me-2"></i>Provisioning Tasks Recenti</h6>
                            </div>
                            <div class="card-body px-0 pt-0 pb-2">
                                <div class="table-responsive p-0">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tipo Task</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Stato</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Data</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Result</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($recentTasks as $task)
                                            <tr>
                                                <td class="text-xs px-3">
                                                    @php
                                                        $icons = [
                                                            'provision' => 'cog',
                                                            'reboot' => 'sync',
                                                            'get_parameters' => 'list',
                                                            'set_parameters' => 'edit',
                                                            'firmware_upgrade' => 'download',
                                                        ];
                                                        $icon = $icons[$task->task_type] ?? 'tasks';
                                                    @endphp
                                                    <i class="fas fa-{{ $icon }} me-2"></i>{{ ucfirst(str_replace('_', ' ', $task->task_type)) }}
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-sm bg-gradient-{{ $task->status == 'completed' ? 'success' : ($task->status == 'failed' ? 'danger' : ($task->status == 'processing' ? 'info' : 'warning')) }}">
                                                        {{ ucfirst($task->status) }}
                                                    </span>
                                                </td>
                                                <td class="text-xs text-center">{{ $task->created_at->format('d/m/Y H:i') }}</td>
                                                <td class="text-xxs text-center text-muted">
                                                    @if($task->result_message)
                                                        <span title="{{ $task->result_message }}">
                                                            {{ strlen($task->result_message) > 30 ? substr($task->result_message, 0, 30) . '...' : $task->result_message }}
                                                        </span>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-sm text-muted py-4">
                                                    <i class="fas fa-inbox me-2"></i>Nessun task disponibile
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pending Commands (NAT Traversal) -->
                        <div class="card">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <h6><i class="fas fa-clock me-2"></i>Pending Commands (NAT Traversal)</h6>
                                <span class="badge bg-gradient-warning text-xs">{{ $pendingCommands->where('status', 'pending')->count() }} Pending</span>
                            </div>
                            <div class="card-body px-0 pt-0 pb-2">
                                <div class="table-responsive p-0">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Command Type</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Priority</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Created</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($pendingCommands as $command)
                                            <tr>
                                                <td class="text-xs px-3">
                                                    @php
                                                        $icons = [
                                                            'provision' => 'cog',
                                                            'reboot' => 'sync',
                                                            'get_parameters' => 'list',
                                                            'set_parameters' => 'edit',
                                                            'diagnostic' => 'stethoscope',
                                                            'firmware_update' => 'download',
                                                            'factory_reset' => 'trash',
                                                            'network_scan' => 'network-wired'
                                                        ];
                                                        $icon = $icons[$command->command_type] ?? 'terminal';
                                                    @endphp
                                                    <i class="fas fa-{{ $icon }} me-2"></i>{{ ucfirst(str_replace('_', ' ', $command->command_type)) }}
                                                </td>
                                                <td class="text-center">
                                                    @php
                                                        $badges = [
                                                            'pending' => 'warning',
                                                            'processing' => 'info',
                                                            'completed' => 'success',
                                                            'failed' => 'danger',
                                                            'cancelled' => 'secondary'
                                                        ];
                                                        $badgeColor = $badges[$command->status] ?? 'secondary';
                                                    @endphp
                                                    <span class="badge badge-sm bg-gradient-{{ $badgeColor }}">
                                                        {{ ucfirst($command->status) }}
                                                    </span>
                                                </td>
                                                <td class="text-xs text-center">
                                                    <span class="badge badge-sm {{ $command->priority <= 2 ? 'bg-danger' : ($command->priority <= 5 ? 'bg-warning' : 'bg-secondary') }}">
                                                        {{ $command->priority }}
                                                    </span>
                                                </td>
                                                <td class="text-xs text-center">{{ $command->created_at->format('d/m/Y H:i') }}</td>
                                                <td class="text-center">
                                                    @if($command->status === 'failed' && $command->canRetry())
                                                    <button class="btn btn-xs btn-success" onclick="retryPendingCommand({{ $command->id }})" title="Retry">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                    @endif
                                                    @if(in_array($command->status, ['pending', 'failed']))
                                                    <button class="btn btn-xs btn-danger" onclick="cancelPendingCommand({{ $command->id }})" title="Cancel">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-sm text-muted py-4">
                                                    <i class="fas fa-check-circle me-2"></i>Nessun comando in coda (dispositivo raggiungibile via Connection Request)
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if($pendingCommands->count() > 0)
                                <div class="px-3 py-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-2"></i>I comandi vengono accodati quando Connection Request fallisce (dispositivo dietro NAT). Verranno eseguiti automaticamente al prossimo Periodic Inform.
                                    </small>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <!-- TAB 6: EVENTS & ALARMS -->
                    <div class="tab-pane fade" id="events" role="tabpanel">
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6><i class="fas fa-bell me-2"></i>Timeline Eventi & Allarmi</h6>
                            </div>
                            <div class="card-body">
                                <div id="events-timeline">
                                    <p class="text-sm text-muted text-center py-4">
                                        <i class="fas fa-info-circle me-2"></i>Caricamento timeline eventi...
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Provisioning -->
<div class="modal fade" id="provisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Provisioning Dispositivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="provisionForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-router me-2"></i>Dispositivo: <strong id="provision_device_sn"></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profilo Configurazione *</label>
                        <select class="form-select" name="profile_id" required>
                            <option value="">Seleziona profilo...</option>
                            @foreach($activeProfiles as $profile)
                            <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">Avvia Provisioning</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reboot -->
<div class="modal fade" id="rebootModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reboot Dispositivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rebootForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Sei sicuro di voler riavviare il dispositivo <strong id="reboot_device_sn"></strong>?</p>
                    <p class="text-warning text-sm"><i class="fas fa-exclamation-triangle me-2"></i>Il dispositivo si riavvierà immediatamente.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-warning">Riavvia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Get Parameters -->
<div class="modal fade" id="getParametersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Get Parameters from Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-sm">Inserisci i path dei parametri da recuperare (uno per riga):</p>
                <textarea class="form-control" id="getParamsInput" rows="8" placeholder="InternetGatewayDevice.DeviceInfo.&#10;InternetGatewayDevice.ManagementServer.&#10;InternetGatewayDevice.LANDevice.1."></textarea>
                <small class="text-muted">Suggerimento: Usa il punto finale (es. DeviceInfo.) per ottenere tutti i sotto-parametri</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="submitGetParameters()">Richiedi Parametri</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Set Parameters -->
<div class="modal fade" id="setParametersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Parameters on Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-sm">Inserisci i parametri da modificare (formato: PATH=VALUE, uno per riga):</p>
                <textarea class="form-control" id="setParamsInput" rows="8" placeholder="InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID=MyNewSSID&#10;InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel=6"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-success" onclick="submitSetParameters()">Imposta Parametri</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Diagnostic Test -->
<div class="modal fade" id="diagnosticModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="diagnosticModalTitle">Diagnostic Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="diagnosticForm">
                @csrf
                <div class="modal-body">
                    <div id="diagnosticFormFields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Avvia Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal AI Historical Analysis -->
<div class="modal fade" id="aiHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title text-white"><i class="fas fa-chart-line me-2"></i>AI Historical Diagnostic Analysis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="aiHistoryContent"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Assign Data Model -->
<div class="modal fade" id="dataModelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assegna Data Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i><strong>Data Model Corrente:</strong> 
                    @if($device->dataModel)
                        {{ $device->dataModel->vendor }} {{ $device->dataModel->model_name }} ({{ $device->dataModel->spec_name ?? 'N/A' }})
                    @else
                        Nessuno
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label">Seleziona Data Model *</label>
                    <select class="form-select" id="dataModelSelect">
                        <option value="">-- Seleziona --</option>
                        @foreach($dataModels as $dm)
                        <option value="{{ $dm->id }}" {{ $device->data_model_id == $dm->id ? 'selected' : '' }}>
                            {{ $dm->vendor }} - {{ $dm->model_name }} ({{ $dm->protocol_version }})
                        </option>
                        @endforeach
                    </select>
                    <small class="text-muted">
                        Il Data Model definisce la struttura dei parametri TR-069/TR-181 supportati dal dispositivo
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-success" onclick="submitDataModel()">Assegna Data Model</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
const deviceId = {{ $device->id }};

// Search parameters
document.getElementById('parameterSearch')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    document.querySelectorAll('.parameter-row').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Performance Chart
const perfCtx = document.getElementById('performanceChart');
if (perfCtx) {
    new Chart(perfCtx, {
        type: 'line',
        data: {
            labels: Array.from({length: 24}, (_, i) => `${i}:00`),
            datasets: [{
                label: 'Uptime %',
                data: Array.from({length: 24}, () => 95 + Math.random() * 5),
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top' }
            },
            scales: {
                y: { beginAtZero: false, min: 90, max: 100 }
            }
        }
    });
}

function provisionDevice(id, sn) {
    document.getElementById('provisionForm').action = '/acs/devices/' + id + '/provision';
    document.getElementById('provision_device_sn').textContent = sn;
    new bootstrap.Modal(document.getElementById('provisionModal')).show();
}

function rebootDevice(id, sn) {
    document.getElementById('rebootForm').action = '/acs/devices/' + id + '/reboot';
    document.getElementById('reboot_device_sn').textContent = sn;
    new bootstrap.Modal(document.getElementById('rebootModal')).show();
}

function openParametersModal() {
    new bootstrap.Modal(document.getElementById('getParametersModal')).show();
}

function openSetParametersModal() {
    new bootstrap.Modal(document.getElementById('setParametersModal')).show();
}

async function submitGetParameters() {
    const params = document.getElementById('getParamsInput').value
        .split('\n')
        .map(p => p.trim())
        .filter(p => p.length > 0);
    
    if (params.length === 0) {
        alert('Inserisci almeno un parametro');
        return;
    }
    
    try {
        const response = await fetch(`/acs/devices/${deviceId}/get-parameters`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ parameters: params })
        });
        
        const result = await response.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('getParametersModal')).hide();
            alert('GetParameterValues richiesta inviata con successo!');
            location.reload();
        } else {
            alert('Errore: ' + (result.message || 'Operazione fallita'));
        }
    } catch (error) {
        alert('Errore di rete: ' + error.message);
    }
}

async function submitSetParameters() {
    const lines = document.getElementById('setParamsInput').value
        .split('\n')
        .map(l => l.trim())
        .filter(l => l.length > 0);
    
    const params = {};
    for (const line of lines) {
        const [path, value] = line.split('=');
        if (path && value) {
            params[path.trim()] = value.trim();
        }
    }
    
    if (Object.keys(params).length === 0) {
        alert('Inserisci almeno un parametro nel formato PATH=VALUE');
        return;
    }
    
    try {
        const response = await fetch(`/acs/devices/${deviceId}/set-parameters`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ parameters: params })
        });
        
        const result = await response.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('setParametersModal')).hide();
            alert('SetParameterValues richiesta inviata con successo!');
            location.reload();
        } else {
            alert('Errore: ' + (result.message || 'Operazione fallita'));
        }
    } catch (error) {
        alert('Errore di rete: ' + error.message);
    }
}

function openDiagnosticModal(type, deviceId) {
    const modal = document.getElementById('diagnosticModal');
    const title = document.getElementById('diagnosticModalTitle');
    const fields = document.getElementById('diagnosticFormFields');
    
    const configs = {
        ping: {
            title: 'Ping Test',
            fields: `
                <div class="mb-3">
                    <label class="form-label">Host *</label>
                    <input type="text" class="form-control" name="host" value="8.8.8.8" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Number of Repetitions</label>
                    <input type="number" class="form-control" name="repetitions" value="4">
                </div>
            `
        },
        traceroute: {
            title: 'Traceroute Test',
            fields: `
                <div class="mb-3">
                    <label class="form-label">Host *</label>
                    <input type="text" class="form-control" name="host" value="8.8.8.8" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Max Hop Count</label>
                    <input type="number" class="form-control" name="max_hops" value="30">
                </div>
            `
        },
        download: {
            title: 'Download Speed Test',
            fields: `
                <div class="mb-3">
                    <label class="form-label">Test URL *</label>
                    <input type="text" class="form-control" name="url" value="http://speedtest.example.com/download" required>
                </div>
            `
        },
        upload: {
            title: 'Upload Speed Test',
            fields: `
                <div class="mb-3">
                    <label class="form-label">Test URL *</label>
                    <input type="text" class="form-control" name="url" value="http://speedtest.example.com/upload" required>
                </div>
            `
        }
    };
    
    const config = configs[type];
    title.textContent = config.title;
    fields.innerHTML = config.fields + `<input type="hidden" name="test_type" value="${type}">`;
    
    new bootstrap.Modal(modal).show();
}

async function aiAnalyzeDeviceHistory(deviceId) {
    const modal = new bootstrap.Modal(document.getElementById('aiHistoryModal'));
    const content = document.getElementById('aiHistoryContent');
    
    content.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-primary"></i><p class="mt-3">AI sta analizzando lo storico diagnostico...</p></div>';
    modal.show();
    
    try {
        const response = await fetch(`/acs/devices/${deviceId}/ai-analyze-diagnostics`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            content.innerHTML = `
                <div class="alert alert-info">
                    <strong>Analisi completata:</strong> ${result.tests_analyzed} tests analizzati
                </div>
                <div class="card">
                    <div class="card-body">
                        <pre class="mb-0">${result.analysis || 'Nessuna analisi disponibile'}</pre>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `<div class="alert alert-danger">${result.message || 'Errore durante analisi'}</div>`;
        }
    } catch (error) {
        content.innerHTML = `<div class="alert alert-danger">Errore di rete: ${error.message}</div>`;
    }
}

async function triggerNetworkScan(deviceId) {
    try {
        const response = await fetch(`/acs/devices/${deviceId}/network-scan`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Network scan avviato!');
        }
    } catch (error) {
        console.error('Network scan error:', error);
    }
}

async function retryPendingCommand(commandId) {
    if (!confirm('Riprovare questo comando?')) return;
    
    try {
        const response = await fetch(`/acs/pending-commands/${commandId}/retry`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Errore: ' + (result.message || 'Operazione fallita'));
        }
    } catch (error) {
        alert('Errore di rete: ' + error.message);
    }
}

async function cancelPendingCommand(commandId) {
    if (!confirm('Annullare questo comando?')) return;
    
    try {
        const response = await fetch(`/acs/pending-commands/${commandId}/cancel`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Errore: ' + (result.message || 'Operazione fallita'));
        }
    } catch (error) {
        alert('Errore di rete: ' + error.message);
    }
}

function openDataModelModal() {
    new bootstrap.Modal(document.getElementById('dataModelModal')).show();
}

async function submitDataModel() {
    const dataModelId = document.getElementById('dataModelSelect').value;
    
    if (!dataModelId) {
        alert('Seleziona un Data Model');
        return;
    }
    
    try {
        const response = await fetch(`/acs/devices/${deviceId}/assign-data-model`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ data_model_id: dataModelId })
        });
        
        const result = await response.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('dataModelModal')).hide();
            alert('Data Model assegnato con successo!');
            location.reload();
        } else {
            alert('Errore: ' + (result.message || 'Operazione fallita'));
        }
    } catch (error) {
        alert('Errore di rete: ' + error.message);
    }
}

// Load events timeline on tab switch
document.getElementById('events-tab')?.addEventListener('shown.bs.tab', async function() {
    const timeline = document.getElementById('events-timeline');
    timeline.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Caricamento...</div>';
    
    try {
        const response = await fetch(`/acs/devices/${deviceId}/history`);
        const data = await response.json();
        
        if (data.events && data.events.length > 0) {
            let html = '<div class="timeline timeline-one-side">';
            data.events.forEach(event => {
                html += `
                    <div class="timeline-block mb-3">
                        <span class="timeline-step badge-${event.status === 'success' ? 'success' : event.status === 'failed' ? 'danger' : 'info'}">
                            <i class="fas fa-${event.type === 'provision' ? 'cog' : 'bell'}"></i>
                        </span>
                        <div class="timeline-content">
                            <h6 class="text-dark text-sm font-weight-bold mb-0">${event.title || event.type}</h6>
                            <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">${event.created_at}</p>
                            <p class="text-sm mt-3 mb-2">${event.description || ''}</p>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            timeline.innerHTML = html;
        } else {
            timeline.innerHTML = '<p class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>Nessun evento disponibile</p>';
        }
    } catch (error) {
        timeline.innerHTML = '<p class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>Errore durante caricamento eventi</p>';
    }
});
</script>
@endpush
