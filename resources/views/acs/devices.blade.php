@extends('layouts.app')

@section('breadcrumb', 'Dispositivi CPE')
@section('page-title', 'Gestione Dispositivi')

@section('content')
<!-- Header Section -->
<div class="row mb-4">
    <div class="col-lg-8 col-md-6">
        <h6 class="text-white">Dispositivi CPE Registrati</h6>
        <p class="text-sm mb-0 text-white opacity-8">
            {{ $devices->total() }} {{ $devices->total() === 1 ? 'dispositivo' : 'dispositivi' }} 
            @if(request()->hasAny(['protocol', 'mtp_type', 'status']))
                (filtrati)
            @endif
        </p>
    </div>
    <div class="col-lg-4 col-md-6 text-md-end">
        <button class="btn bg-gradient-primary mt-4 mb-0" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
            <i class="fas fa-plus me-2"></i>Info Auto-Registration
        </button>
    </div>
</div>

<!-- Filters Toolbar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-body border card-plain border-radius-lg d-flex align-items-center flex-row">
            <form method="GET" action="{{ route('acs.devices') }}" class="row w-100 align-items-center">
                <div class="col-md-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text text-body"><i class="fas fa-search" aria-hidden="true"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Cerca..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="protocol" class="form-select form-select-sm">
                        <option value="">Tutti i protocolli</option>
                        <option value="tr069" {{ request('protocol') == 'tr069' ? 'selected' : '' }}>TR-069 (CWMP)</option>
                        <option value="tr369" {{ request('protocol') == 'tr369' ? 'selected' : '' }}>TR-369 (USP)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="mtp_type" class="form-select form-select-sm">
                        <option value="">Tutti MTP</option>
                        <option value="mqtt" {{ request('mtp_type') == 'mqtt' ? 'selected' : '' }}>MQTT</option>
                        <option value="http" {{ request('mtp_type') == 'http' ? 'selected' : '' }}>HTTP</option>
                        <option value="stomp" {{ request('mtp_type') == 'stomp' ? 'selected' : '' }}>STOMP</option>
                        <option value="websocket" {{ request('mtp_type') == 'websocket' ? 'selected' : '' }}>WebSocket</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Tutti gli stati</option>
                        <option value="online" {{ request('status') == 'online' ? 'selected' : '' }}>Online</option>
                        <option value="offline" {{ request('status') == 'offline' ? 'selected' : '' }}>Offline</option>
                        <option value="provisioning" {{ request('status') == 'provisioning' ? 'selected' : '' }}>Provisioning</option>
                        <option value="error" {{ request('status') == 'error' ? 'selected' : '' }}>Error</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm bg-gradient-dark w-100 mb-0">Filtra</button>
                </div>
                <div class="col-md-2">
                    @if(request()->hasAny(['protocol', 'mtp_type', 'status', 'search']))
                    <a href="{{ route('acs.devices') }}" class="btn btn-sm btn-outline-secondary w-100 mb-0">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Devices Grid (Profile-Teams Pattern) -->
<div class="row">
    @forelse($devices as $device)
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card card-blog card-plain">
            <div class="card-header p-0 mt-n4 mx-3">
                <a class="d-block shadow-xl border-radius-xl">
                    <div class="position-relative">
                        <!-- Device Icon/Image with Gradient Background -->
                        <div class="p-5 text-center bg-gradient-{{ $device->protocol_type === 'tr369' ? 'success' : 'primary' }} border-radius-lg">
                            <i class="fas fa-{{ $device->protocol_type === 'tr369' ? 'satellite-dish' : 'router' }} fa-4x text-white opacity-10"></i>
                        </div>
                        <!-- Status Badge Overlay -->
                        <div class="position-absolute top-0 end-0 m-3">
                            <span class="badge badge-sm bg-gradient-{{ $device->status == 'online' ? 'success' : ($device->status == 'offline' ? 'secondary' : ($device->status == 'provisioning' ? 'warning' : 'danger')) }}">
                                {{ ucfirst($device->status) }}
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="card-body px-3 pt-3">
                <!-- Device Title -->
                <h5 class="mb-0">{{ $device->serial_number }}</h5>
                <p class="text-sm text-secondary mb-2">
                    {{ $device->manufacturer }} - {{ $device->model_name }}
                </p>

                <!-- Protocol Badges -->
                <div class="mb-3">
                    @if($device->protocol_type === 'tr369')
                        <span class="badge badge-sm bg-gradient-success me-1">TR-369 USP</span>
                        @if($device->mtp_type)
                            <span class="badge badge-sm bg-gradient-{{ $device->mtp_type === 'mqtt' ? 'warning' : ($device->mtp_type === 'stomp' ? 'info' : 'dark') }}">
                                {{ strtoupper($device->mtp_type) }}
                            </span>
                        @endif
                    @else
                        <span class="badge badge-sm bg-gradient-primary">TR-069 CWMP</span>
                    @endif
                </div>

                <!-- Device Info -->
                <p class="mb-1 text-xs">
                    <i class="fas fa-network-wired text-primary me-1"></i>
                    <strong>IP:</strong> {{ $device->ip_address ?? 'N/A' }}
                </p>
                <p class="mb-1 text-xs">
                    <i class="far fa-clock text-info me-1"></i>
                    <strong>Ultimo contatto:</strong> 
                    {{ ($device->last_contact ?? $device->last_inform) ? ($device->last_contact ?? $device->last_inform)->diffForHumans() : 'Mai' }}
                </p>
                
                @if($device->service)
                <p class="mb-1 text-xs">
                    <i class="fas fa-building text-success me-1"></i>
                    <strong>Servizio:</strong> {{ $device->service->name }}
                </p>
                @endif

                @if($device->dataModel)
                <p class="mb-3 text-xs">
                    <i class="fas fa-database text-warning me-1"></i>
                    <strong>Data Model:</strong> 
                    <span class="badge badge-sm bg-gradient-{{ 
                        $device->dataModel->protocol_version == 'TR-181' || $device->dataModel->protocol_version == 'TR-181 Issue 2' ? 'info' : 
                        ($device->dataModel->protocol_version == 'TR-098' ? 'primary' : 'secondary')
                    }}">{{ $device->dataModel->protocol_version }}</span>
                </p>
                @else
                <p class="mb-3 text-xs text-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>Data Model non assegnato
                </p>
                @endif

                <!-- Actions -->
                <div class="d-flex align-items-center justify-content-between">
                    <a href="{{ route('acs.devices.show', $device->id) }}" class="btn btn-sm btn-outline-primary px-3 mb-0">
                        <i class="fas fa-eye me-1"></i>Dettagli
                    </a>
                    
                    <div class="btn-group" role="group">
                        <button class="btn btn-link text-success px-2 mb-0" onclick="provisionDevice({{ $device->id }}, '{{ $device->serial_number }}')" title="Provisioning">
                            <i class="fas fa-cog"></i>
                        </button>
                        <button class="btn btn-link text-primary px-2 mb-0" onclick="connectionRequest({{ $device->id }}, '{{ $device->serial_number }}', {{ $device->connection_request_url ? 'true' : 'false' }})" title="Connection Request">
                            <i class="fas fa-bell"></i>
                        </button>
                        <button class="btn btn-link text-warning px-2 mb-0" onclick="rebootDevice({{ $device->id }}, '{{ $device->serial_number }}')" title="Reboot">
                            <i class="fas fa-sync"></i>
                        </button>
                        <button class="btn btn-link text-info px-2 mb-0" onclick="diagnosticDevice({{ $device->id }}, '{{ $device->serial_number }}')" title="Diagnostica">
                            <i class="fas fa-stethoscope"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card card-body border card-plain border-radius-lg d-flex align-items-center flex-row">
            <div class="text-center py-5 w-100">
                <i class="fas fa-router fa-3x text-secondary opacity-6 mb-3"></i>
                <h6 class="text-secondary mb-2">Nessun dispositivo registrato</h6>
                <p class="text-sm text-secondary mb-0">I dispositivi si registreranno automaticamente al primo Inform TR-069 o USP Record TR-369.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>

<!-- Pagination -->
@if($devices->hasPages())
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-center mt-4">
            {{ $devices->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endif

<!-- Modal: Auto-Registration Info -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Auto-Registration Info</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>I dispositivi si registrano automaticamente</strong> quando inviano il primo Inform TR-069 o USP Record TR-369.
                </div>
                
                <h6 class="mb-3">Configurazione CPE TR-069 (CWMP)</h6>
                <div class="bg-gray-100 p-3 border-radius-lg mb-4">
                    <p class="mb-1"><strong>ACS URL:</strong> <code>{{ url('/tr069') }}</code></p>
                    <p class="mb-1"><strong>Username:</strong> <code>acs_admin</code></p>
                    <p class="mb-0"><strong>Password:</strong> <code>configurato nel CPE</code></p>
                </div>

                <h6 class="mb-3">Configurazione CPE TR-369 (USP)</h6>
                <div class="bg-gray-100 p-3 border-radius-lg mb-4">
                    <p class="mb-2"><strong>Controller Endpoint:</strong></p>
                    <ul class="mb-0">
                        <li><strong>MQTT:</strong> <code>mqtt://{{ request()->getHost() }}:1883</code></li>
                        <li><strong>HTTP:</strong> <code>{{ url('/tr369/usp') }}</code></li>
                        <li><strong>WebSocket:</strong> <code>ws://{{ request()->getHost() }}:8080</code></li>
                        <li><strong>STOMP:</strong> <code>stomp://{{ request()->getHost() }}:61613</code></li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Assicurati che il CPE sia configurato con l'URL ACS corretto prima di accenderlo.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Device Details (Comprehensive) -->
<div class="modal fade" id="deviceDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dettagli Dispositivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="deviceDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Caricamento...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Provisioning Dispositivo -->
<div class="modal fade" id="provisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Provisioning Dispositivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                            @foreach(App\Models\ConfigurationProfile::where('is_active', true)->get() as $profile)
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

<!-- Modal Reboot Dispositivo -->
<div class="modal fade" id="rebootModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reboot Dispositivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<!-- Modal Connection Request -->
<div class="modal fade" id="connectionRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Connection Request TR-069</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-router me-2"></i>Dispositivo: <strong id="connreq_device_sn"></strong>
                </div>
                <p>Invia richiesta HTTP al dispositivo per iniziare una nuova sessione TR-069.</p>
                <p class="text-sm text-secondary">Il dispositivo riceverà la richiesta e risponderà con un nuovo Inform all'ACS.</p>
                <div id="connreq_result" class="mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                <button type="button" class="btn btn-primary" id="sendConnectionRequestBtn" onclick="sendConnectionRequest()">
                    <i class="fas fa-bell me-2"></i>Invia Connection Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Diagnostica TR-143 -->
<div class="modal fade" id="diagnosticModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Diagnostici TR-143</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-router me-2"></i>Dispositivo: <strong id="diag_device_sn"></strong>
                </div>
                
                <ul class="nav nav-pills mb-3" id="diagnosticTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ping-tab" data-bs-toggle="pill" data-bs-target="#ping" type="button">Ping</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="traceroute-tab" data-bs-toggle="pill" data-bs-target="#traceroute" type="button">Traceroute</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="download-tab" data-bs-toggle="pill" data-bs-target="#download" type="button">Download Test</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="upload-tab" data-bs-toggle="pill" data-bs-target="#upload" type="button">Upload Test</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="diagnosticTabContent">
                    <!-- Ping Tab -->
                    <div class="tab-pane fade show active" id="ping" role="tabpanel">
                        <form id="pingForm">
                            <div class="mb-3">
                                <label class="form-label">Host/IP *</label>
                                <input type="text" class="form-control" name="host" value="8.8.8.8" required>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Pacchetti</label>
                                    <input type="number" class="form-control" name="packets" value="4" min="1" max="100">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Timeout (ms)</label>
                                    <input type="number" class="form-control" name="timeout" value="1000" min="100" max="10000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Packet Size (bytes)</label>
                                    <input type="number" class="form-control" name="size" value="64" min="32" max="1500">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Avvia Ping Test</button>
                        </form>
                    </div>
                    
                    <!-- Traceroute Tab -->
                    <div class="tab-pane fade" id="traceroute" role="tabpanel">
                        <form id="tracerouteForm">
                            <div class="mb-3">
                                <label class="form-label">Host/IP *</label>
                                <input type="text" class="form-control" name="host" value="google.com" required>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tentativi</label>
                                    <input type="number" class="form-control" name="tries" value="3" min="1" max="10">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Timeout (ms)</label>
                                    <input type="number" class="form-control" name="timeout" value="5000" min="100" max="30000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Max Hops</label>
                                    <input type="number" class="form-control" name="max_hops" value="30" min="1" max="64">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Avvia Traceroute</button>
                        </form>
                    </div>
                    
                    <!-- Download Test Tab -->
                    <div class="tab-pane fade" id="download" role="tabpanel">
                        <form id="downloadForm">
                            <div class="mb-3">
                                <label class="form-label">URL File Download *</label>
                                <input type="url" class="form-control" name="url" value="http://speedtest.ftp.otenet.gr/files/test1Mb.db" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">File Size (bytes, opzionale)</label>
                                <input type="number" class="form-control" name="file_size" value="1048576">
                            </div>
                            <button type="submit" class="btn btn-primary">Avvia Download Test</button>
                        </form>
                    </div>
                    
                    <!-- Upload Test Tab -->
                    <div class="tab-pane fade" id="upload" role="tabpanel">
                        <form id="uploadForm">
                            <div class="mb-3">
                                <label class="form-label">URL Server Upload *</label>
                                <input type="url" class="form-control" name="url" value="http://httpbin.org/post" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">File Size (bytes)</label>
                                <input type="number" class="form-control" name="file_size" value="1048576" min="0" max="104857600">
                            </div>
                            <button type="submit" class="btn btn-primary">Avvia Upload Test</button>
                        </form>
                    </div>
                </div>
                
                <div id="diagnostic_result" class="mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Assegna a Servizio -->
<div class="modal fade" id="assignServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assegna Dispositivo a Servizio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignServiceForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-router me-2"></i>Dispositivo: <strong id="assign_device_sn"></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cliente</label>
                        <select class="form-select" name="customer_id" id="customer_select" onchange="loadCustomerServices(this.value)">
                            <option value="">Seleziona cliente...</option>
                            @foreach(App\Models\Customer::all() as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Servizio *</label>
                        <select class="form-select" name="service_id" id="service_select" required>
                            <option value="">Seleziona prima il cliente...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">Assegna</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentDeviceId = null;
let currentConnectionRequestUrl = null;

// View Device Details (Load via AJAX)
function viewDeviceDetails(deviceId) {
    const modal = new bootstrap.Modal(document.getElementById('deviceDetailsModal'));
    modal.show();
    
    fetch(`/acs/devices/${deviceId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            renderDeviceDetails(data);
        })
        .catch(error => {
            document.getElementById('deviceDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Errore nel caricamento dei dettagli del dispositivo: ${error.message}
                </div>
            `;
        });
}

function renderDeviceDetails(device) {
    const statusColors = {
        'online': 'success',
        'offline': 'secondary',
        'provisioning': 'warning',
        'error': 'danger'
    };
    
    const html = `
        <div class="row">
            <!-- Device Overview -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="icon icon-shape bg-gradient-${device.protocol_type === 'tr369' ? 'success' : 'primary'} shadow text-center border-radius-xl mb-3">
                            <i class="fas fa-${device.protocol_type === 'tr369' ? 'satellite-dish' : 'router'} fa-3x text-white opacity-10"></i>
                        </div>
                        <h5 class="mb-0">${device.serial_number}</h5>
                        <p class="text-sm text-secondary mb-2">${device.manufacturer} - ${device.model_name}</p>
                        <span class="badge bg-gradient-${statusColors[device.status]}">${device.status.toUpperCase()}</span>
                    </div>
                </div>
            </div>
            
            <!-- Device Information Tabs -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header p-3">
                        <ul class="nav nav-tabs" id="deviceTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">Info</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="params-tab" data-bs-toggle="tab" data-bs-target="#params" type="button">Parameters</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">History</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="deviceTabContent">
                            <!-- Info Tab -->
                            <div class="tab-pane fade show active" id="info" role="tabpanel">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr><td><strong>Serial Number:</strong></td><td>${device.serial_number}</td></tr>
                                        <tr><td><strong>Manufacturer:</strong></td><td>${device.manufacturer}</td></tr>
                                        <tr><td><strong>Model:</strong></td><td>${device.model_name}</td></tr>
                                        <tr><td><strong>OUI:</strong></td><td>${device.oui || 'N/A'}</td></tr>
                                        <tr><td><strong>Product Class:</strong></td><td>${device.product_class || 'N/A'}</td></tr>
                                        <tr><td><strong>IP Address:</strong></td><td>${device.ip_address || 'N/A'}</td></tr>
                                        <tr><td><strong>Protocol:</strong></td><td>${device.protocol_type === 'tr369' ? 'TR-369 USP' : 'TR-069 CWMP'}</td></tr>
                                        ${device.mtp_type ? `<tr><td><strong>MTP Type:</strong></td><td>${device.mtp_type.toUpperCase()}</td></tr>` : ''}
                                        <tr><td><strong>Firmware Version:</strong></td><td>${device.firmware_version || 'N/A'}</td></tr>
                                        <tr><td><strong>Hardware Version:</strong></td><td>${device.hardware_version || 'N/A'}</td></tr>
                                        <tr><td><strong>Last Contact:</strong></td><td>${device.last_contact_formatted || 'Mai'}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Parameters Tab -->
                            <div class="tab-pane fade" id="params" role="tabpanel">
                                <p class="text-sm text-secondary">Caricamento parametri in corso...</p>
                            </div>
                            
                            <!-- History Tab -->
                            <div class="tab-pane fade" id="history" role="tabpanel">
                                <p class="text-sm text-secondary">Cronologia eventi in corso...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('deviceDetailsContent').innerHTML = html;
    
    // Add event listener for Parameters tab
    document.getElementById('params-tab').addEventListener('click', function() {
        loadDeviceParameters(device.id);
    });
    
    // Add event listener for History tab
    document.getElementById('history-tab').addEventListener('click', function() {
        loadDeviceHistory(device.id);
    });
}

// HTML Escaping to prevent XSS from device-supplied data
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Load Device Parameters (Tab)
let parametersCache = {}; // Cache keyed by device ID
function loadDeviceParameters(deviceId) {
    if (parametersCache[deviceId]) {
        renderDeviceParameters(parametersCache[deviceId]);
        return; // Already loaded for this device
    }
    
    const container = document.getElementById('params');
    container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Caricamento parametri...</div>';
    
    fetch(`/acs/devices/${deviceId}/parameters`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        parametersCache[deviceId] = data;
        renderDeviceParameters(data);
    })
    .catch(error => {
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Errore nel caricamento parametri: ${error.message}
            </div>
        `;
    });
}

function renderDeviceParameters(data) {
    const container = document.getElementById('params');
    
    if (!data.parameters || data.parameters.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nessun parametro disponibile per questo dispositivo.
                <br><small class="text-muted">Eseguire una Connection Request per sincronizzare i parametri TR-181.</small>
            </div>
        `;
        return;
    }
    
    let html = `
        <!-- Search Box -->
        <div class="mb-3">
            <input type="text" class="form-control form-control-sm" id="param-search" placeholder="Cerca parametro... (es. WiFi, IP, SSID)">
        </div>
        
        <!-- View Toggle -->
        <div class="btn-group btn-group-sm mb-3" role="group">
            <button type="button" class="btn btn-primary active" id="tree-view-btn">
                <i class="fas fa-sitemap me-1"></i>Tree
            </button>
            <button type="button" class="btn btn-outline-primary" id="table-view-btn">
                <i class="fas fa-table me-1"></i>Table
            </button>
        </div>
        <span class="text-sm text-secondary ms-2">
            <i class="fas fa-database me-1"></i><strong>${data.parameters_count}</strong> parametri
        </span>
        
        <!-- Tree View (hierarchical) -->
        <div id="tree-view" style="max-height: 400px; overflow-y: auto;">
            <div class="param-tree">
                ${renderParametersTree(data.hierarchy)}
            </div>
        </div>
        
        <!-- Table View (flat, hidden by default) -->
        <div id="table-view" style="max-height: 400px; overflow-y: auto; display: none;">
            <table class="table table-sm table-hover" id="params-table">
                <thead class="thead-light sticky-top">
                    <tr>
                        <th width="40%">Percorso</th>
                        <th width="35%">Valore</th>
                        <th width="15%">Tipo</th>
                        <th width="10%">RW</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.parameters.forEach(param => {
        const writable = param.writable ? '<span class="badge badge-sm bg-success">W</span>' : '<span class="badge badge-sm bg-secondary">R</span>';
        const typeColors = {
            'string': 'primary',
            'int': 'info',
            'boolean': 'warning',
            'dateTime': 'secondary',
            'base64': 'dark'
        };
        
        html += `
            <tr class="param-row" data-path="${escapeHtml(param.path.toLowerCase())}">
                <td class="text-xs"><code>${escapeHtml(param.path)}</code></td>
                <td class="text-xs">${param.value ? escapeHtml(param.value) : '<span class="text-muted">null</span>'}</td>
                <td><span class="badge badge-sm bg-${typeColors[param.type] || 'secondary'}">${escapeHtml(param.type)}</span></td>
                <td>${writable}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = html;
    
    // View toggle functionality
    document.getElementById('tree-view-btn').addEventListener('click', function() {
        document.getElementById('tree-view').style.display = '';
        document.getElementById('table-view').style.display = 'none';
        this.classList.add('active');
        document.getElementById('table-view-btn').classList.remove('active');
        document.getElementById('table-view-btn').classList.add('btn-outline-primary');
        this.classList.remove('btn-outline-primary');
    });
    
    document.getElementById('table-view-btn').addEventListener('click', function() {
        document.getElementById('tree-view').style.display = 'none';
        document.getElementById('table-view').style.display = '';
        this.classList.add('active');
        document.getElementById('tree-view-btn').classList.remove('active');
        document.getElementById('tree-view-btn').classList.add('btn-outline-primary');
        this.classList.remove('btn-outline-primary');
    });
    
    // Search functionality
    document.getElementById('param-search').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        // Search in table view
        document.querySelectorAll('.param-row').forEach(row => {
            const path = row.getAttribute('data-path');
            row.style.display = path.includes(searchTerm) ? '' : 'none';
        });
        // Search in tree view
        document.querySelectorAll('.tree-node').forEach(node => {
            const text = node.textContent.toLowerCase();
            const shouldShow = searchTerm === '' || text.includes(searchTerm);
            node.style.display = shouldShow ? '' : 'none';
        });
    });
    
    // Tree expand/collapse
    document.querySelectorAll('.tree-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.tree-node');
            const children = parent.querySelector('.tree-children');
            if (children) {
                const isExpanded = children.style.display !== 'none';
                children.style.display = isExpanded ? 'none' : '';
                this.querySelector('i').className = isExpanded ? 'fas fa-chevron-right' : 'fas fa-chevron-down';
            }
        });
    });
}

function renderParametersTree(hierarchy, level = 0) {
    if (!hierarchy || Object.keys(hierarchy).length === 0) return '';
    
    let html = '';
    const indent = level * 20;
    
    for (const [key, value] of Object.entries(hierarchy)) {
        if (key === '_parameters') {
            // Render leaf parameters
            value.forEach(param => {
                const typeColors = {
                    'string': 'primary',
                    'int': 'info',
                    'boolean': 'warning',
                    'dateTime': 'secondary',
                    'base64': 'dark'
                };
                const writable = param.writable ? '<span class="badge badge-xs bg-success">W</span>' : '<span class="badge badge-xs bg-secondary">R</span>';
                
                html += `
                    <div class="tree-node tree-leaf" style="margin-left: ${indent}px;">
                        <span class="tree-icon"><i class="fas fa-file text-info"></i></span>
                        <code class="text-xs">${escapeHtml(param.name)}</code>
                        <span class="text-xs text-secondary ms-2">${param.value ? escapeHtml(param.value) : '<em>null</em>'}</span>
                        <span class="badge badge-xs bg-${typeColors[param.type] || 'secondary'} ms-1">${escapeHtml(param.type)}</span>
                        ${writable}
                    </div>
                `;
            });
        } else {
            // Render folder/object node
            const hasChildren = Object.keys(value).length > 0;
            html += `
                <div class="tree-node tree-folder" style="margin-left: ${indent}px;">
                    ${hasChildren ? '<a href="#" class="tree-toggle"><i class="fas fa-chevron-down"></i></a>' : '<span class="tree-spacer"></span>'}
                    <span class="tree-icon"><i class="fas fa-folder text-warning"></i></span>
                    <strong class="text-sm">${escapeHtml(key)}</strong>
                    <div class="tree-children">
                        ${renderParametersTree(value, level + 1)}
                    </div>
                </div>
            `;
        }
    }
    
    return html;
}

// Load Device History (Tab)
let historyCache = {}; // Cache keyed by device ID
function loadDeviceHistory(deviceId) {
    if (historyCache[deviceId]) {
        renderDeviceHistory(historyCache[deviceId]);
        return; // Already loaded for this device
    }
    
    const container = document.getElementById('history');
    container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Caricamento cronologia...</div>';
    
    fetch(`/acs/devices/${deviceId}/history`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        historyCache[deviceId] = data;
        renderDeviceHistory(data);
    })
    .catch(error => {
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Errore nel caricamento cronologia: ${error.message}
            </div>
        `;
    });
}

function renderDeviceHistory(data) {
    const container = document.getElementById('history');
    
    if (!data.events || data.events.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nessun evento registrato per questo dispositivo.
            </div>
        `;
        return;
    }
    
    const eventIcons = {
        'provisioning': 'cogs',
        'reboot': 'power-off',
        'firmware_update': 'download',
        'diagnostic': 'stethoscope',
        'connection_request': 'plug',
        'parameter_change': 'edit'
    };
    
    const eventColors = {
        'pending': 'warning',
        'processing': 'info',
        'completed': 'success',
        'failed': 'danger'
    };
    
    let html = `
        <p class="text-sm text-secondary mb-3">
            <i class="fas fa-history me-2"></i><strong>${data.events_count}</strong> eventi recenti
        </p>
        <div style="max-height: 400px; overflow-y: auto;">
            <div class="timeline timeline-one-side" data-timeline-axis-style="dotted">
    `;
    
    data.events.forEach(event => {
        const icon = eventIcons[event.type] || 'info-circle';
        const color = eventColors[event.status] || 'secondary';
        
        html += `
            <div class="timeline-block mb-3">
                <span class="timeline-step badge-${color}">
                    <i class="fas fa-${icon}"></i>
                </span>
                <div class="timeline-content">
                    <h6 class="text-dark text-sm font-weight-bold mb-0">${escapeHtml(event.title)}</h6>
                    <p class="text-secondary text-xs mt-1 mb-0">
                        <i class="fas fa-clock me-1"></i>${escapeHtml(event.created_at)}
                        ${event.triggered_by ? `&nbsp;•&nbsp;<i class="fas fa-user me-1"></i>${escapeHtml(event.triggered_by)}` : ''}
                    </p>
                    ${event.description ? `<p class="text-sm mt-2 mb-0">${escapeHtml(event.description)}</p>` : ''}
                    <span class="badge badge-sm bg-${color} mt-2">${escapeHtml(event.status)}</span>
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// Provisioning
function provisionDevice(deviceId, serialNumber) {
    currentDeviceId = deviceId;
    document.getElementById('provision_device_sn').textContent = serialNumber;
    document.getElementById('provisionForm').action = `/acs/devices/${deviceId}/provision`;
    const modal = new bootstrap.Modal(document.getElementById('provisionModal'));
    modal.show();
}

document.getElementById('provisionForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('Provisioning avviato con successo!');
            bootstrap.Modal.getInstance(document.getElementById('provisionModal')).hide();
            location.reload();
        } else {
            alert('Errore: ' + (data.message || 'Provisioning fallito'));
        }
    })
    .catch(error => {
        alert('Errore di rete: ' + error.message);
    });
});

// Reboot
function rebootDevice(deviceId, serialNumber) {
    currentDeviceId = deviceId;
    document.getElementById('reboot_device_sn').textContent = serialNumber;
    document.getElementById('rebootForm').action = `/acs/devices/${deviceId}/reboot`;
    const modal = new bootstrap.Modal(document.getElementById('rebootModal'));
    modal.show();
}

document.getElementById('rebootForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('Comando di reboot inviato!');
            bootstrap.Modal.getInstance(document.getElementById('rebootModal')).hide();
        } else {
            alert('Errore: ' + (data.message || 'Reboot fallito'));
        }
    })
    .catch(error => {
        alert('Errore di rete: ' + error.message);
    });
});

// Connection Request
function connectionRequest(deviceId, serialNumber, hasUrl) {
    currentDeviceId = deviceId;
    currentConnectionRequestUrl = hasUrl;
    document.getElementById('connreq_device_sn').textContent = serialNumber;
    document.getElementById('connreq_result').style.display = 'none';
    
    if(!hasUrl) {
        document.getElementById('connreq_result').innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Questo dispositivo non ha un Connection Request URL configurato. Potrebbe trovarsi dietro NAT/firewall.
            </div>
        `;
        document.getElementById('connreq_result').style.display = 'block';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('connectionRequestModal'));
    modal.show();
}

function sendConnectionRequest() {
    document.getElementById('sendConnectionRequestBtn').disabled = true;
    document.getElementById('connreq_result').innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Invio in corso...';
    document.getElementById('connreq_result').style.display = 'block';
    
    fetch(`/acs/devices/${currentDeviceId}/connection-request`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('sendConnectionRequestBtn').disabled = false;
        if(data.success) {
            document.getElementById('connreq_result').innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>${data.message}
                </div>
            `;
        } else {
            document.getElementById('connreq_result').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>${data.message || 'Connection Request fallito'}
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('sendConnectionRequestBtn').disabled = false;
        document.getElementById('connreq_result').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-times-circle me-2"></i>Errore di rete: ${error.message}
            </div>
        `;
    });
}

// Diagnostic
function diagnosticDevice(deviceId, serialNumber) {
    currentDeviceId = deviceId;
    document.getElementById('diag_device_sn').textContent = serialNumber;
    document.getElementById('diagnostic_result').style.display = 'none';
    const modal = new bootstrap.Modal(document.getElementById('diagnosticModal'));
    modal.show();
}

// Diagnostic Forms Handlers
['ping', 'traceroute', 'download', 'upload'].forEach(testType => {
    document.getElementById(`${testType}Form`)?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const params = Object.fromEntries(formData.entries());
        
        document.getElementById('diagnostic_result').innerHTML = '<div class="spinner-border" role="status"></div> Test in corso...';
        document.getElementById('diagnostic_result').style.display = 'block';
        
        fetch(`/acs/devices/${currentDeviceId}/diagnostics/${testType}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(params)
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                document.getElementById('diagnostic_result').innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>${data.message}
                        <pre class="mt-2 bg-gray-100 p-2 border-radius-md">${JSON.stringify(data.result, null, 2)}</pre>
                    </div>
                `;
            } else {
                document.getElementById('diagnostic_result').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>${data.message || 'Test fallito'}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('diagnostic_result').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>Errore: ${error.message}
                </div>
            `;
        });
    });
});

// Assign Service
function assignService(deviceId, serialNumber) {
    currentDeviceId = deviceId;
    document.getElementById('assign_device_sn').textContent = serialNumber;
    document.getElementById('assignServiceForm').action = `/acs/devices/${deviceId}/assign-service`;
    const modal = new bootstrap.Modal(document.getElementById('assignServiceModal'));
    modal.show();
}

function loadCustomerServices(customerId) {
    const serviceSelect = document.getElementById('service_select');
    serviceSelect.innerHTML = '<option value="">Caricamento...</option>';
    
    if(!customerId) {
        serviceSelect.innerHTML = '<option value="">Seleziona prima il cliente...</option>';
        return;
    }
    
    fetch(`/acs/customers/${customerId}/services`)
        .then(response => response.json())
        .then(services => {
            serviceSelect.innerHTML = '<option value="">Seleziona servizio...</option>';
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.id;
                option.textContent = service.name;
                serviceSelect.appendChild(option);
            });
        })
        .catch(error => {
            serviceSelect.innerHTML = '<option value="">Errore nel caricamento</option>';
        });
}

document.getElementById('assignServiceForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('Dispositivo assegnato con successo!');
            bootstrap.Modal.getInstance(document.getElementById('assignServiceModal')).hide();
            location.reload();
        } else {
            alert('Errore: ' + (data.message || 'Assegnazione fallita'));
        }
    })
    .catch(error => {
        alert('Errore di rete: ' + error.message);
    });
});
</script>
@endpush
