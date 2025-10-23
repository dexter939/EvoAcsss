@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                            <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="{{ route('acs.customers') }}">Clienti</a></li>
                            <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="{{ route('acs.customers.detail', $service->customer->id) }}">{{ $service->customer->name }}</a></li>
                            <li class="breadcrumb-item text-sm text-dark active" aria-current="page">{{ $service->name }}</li>
                        </ol>
                    </nav>
                    <h6 class="mt-3">Dettaglio Servizio</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-xs font-weight-bolder opacity-7">Informazioni Servizio</h6>
                            <ul class="list-group">
                                <li class="list-group-item border-0 ps-0 pt-0 text-sm"><strong>Nome:</strong> {{ $service->name }}</li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong>Cliente:</strong> <a href="{{ route('acs.customers.detail', $service->customer->id) }}">{{ $service->customer->name }}</a></li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong>Tipo Servizio:</strong> <span class="badge badge-sm bg-gradient-primary">{{ $service->service_type }}</span></li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong>Numero Contratto:</strong> {{ $service->contract_number ?? '-' }}</li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong>SLA Tier:</strong> {{ $service->sla_tier ?? '-' }}</li>
                                <li class="list-group-item border-0 ps-0 text-sm">
                                    <strong>Stato:</strong>
                                    @if($service->status == 'active')
                                        <span class="badge badge-sm bg-gradient-success">Attivo</span>
                                    @elseif($service->status == 'provisioned')
                                        <span class="badge badge-sm bg-gradient-info">Provisioned</span>
                                    @elseif($service->status == 'suspended')
                                        <span class="badge badge-sm bg-gradient-warning">Sospeso</span>
                                    @else
                                        <span class="badge badge-sm bg-gradient-danger">Terminato</span>
                                    @endif
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-xs font-weight-bolder opacity-7">Date e Statistiche</h6>
                            <ul class="list-group">
                                <li class="list-group-item border-0 ps-0 pt-0 text-sm"><strong>Data Attivazione:</strong> {{ $service->activation_at ? $service->activation_at->format('d/m/Y H:i') : '-' }}</li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong>Data Terminazione:</strong> {{ $service->termination_at ? $service->termination_at->format('d/m/Y H:i') : '-' }}</li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong>Dispositivi Associati:</strong> {{ $service->cpeDevices->count() }}</li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong>Creato il:</strong> {{ $service->created_at->format('d/m/Y H:i') }}</li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong>Ultimo Aggiornamento:</strong> {{ $service->updated_at->format('d/m/Y H:i') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                    <h6>Dispositivi del Servizio</h6>
                    <button class="btn btn-sm btn-success" onclick="openAssignDevicesModal({{ $service->id }}, '{{ $service->name }}')">
                        <i class="fas fa-plus me-1"></i> Assegna Dispositivi
                    </button>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Dispositivo</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Modello</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Protocollo</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">IP Address</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Stato</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Ultimo Inform</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($service->cpeDevices as $device)
                                <tr>
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm">{{ $device->serial_number }}</h6>
                                                <p class="text-xs text-secondary mb-0">{{ $device->manufacturer }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $device->model_name ?? '-' }}</p>
                                        <p class="text-xs text-secondary mb-0">{{ $device->software_version ?? '-' }}</p>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm bg-gradient-{{ $device->protocol_type == 'tr069' ? 'primary' : 'info' }}">
                                            {{ strtoupper($device->protocol_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $device->ip_address ?? '-' }}</p>
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        @if($device->status == 'online')
                                            <span class="badge badge-sm bg-gradient-success">Online</span>
                                        @elseif($device->status == 'offline')
                                            <span class="badge badge-sm bg-gradient-secondary">Offline</span>
                                        @elseif($device->status == 'provisioning')
                                            <span class="badge badge-sm bg-gradient-warning">Provisioning</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-danger">Error</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center">
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Mai' }}
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <a href="{{ route('acs.devices.show', $device->id) }}" 
                                           class="btn btn-link text-secondary mb-0" data-toggle="tooltip" data-original-title="Vedi dettagli">
                                            <i class="fa fa-eye text-xs"></i> Dettagli
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <p class="text-sm text-secondary mb-0">Nessun dispositivo associato a questo servizio</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Assegna Dispositivi al Servizio -->
<div class="modal fade" id="assignDevicesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assegna Dispositivi al Servizio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignDevicesForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-link me-2"></i>Servizio: <strong id="modal_service_name"></strong>
                    </div>
                    
                    <p class="text-sm text-muted mb-3">Seleziona i dispositivi non assegnati da associare a questo servizio:</p>
                    
                    <div id="devices_loading" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-sm text-muted mt-2">Caricamento dispositivi...</p>
                    </div>
                    
                    <div id="devices_list">
                        <!-- Populated by JavaScript -->
                    </div>
                    
                    <div id="no_devices_message" class="text-center py-4" style="display: none;">
                        <i class="fas fa-info-circle text-muted fa-2x mb-2"></i>
                        <p class="text-sm text-muted">Nessun dispositivo disponibile per l'assegnazione</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success" id="assign_devices_btn">
                        <i class="fas fa-check me-1"></i> Assegna Selezionati
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentServiceId = null;

function openAssignDevicesModal(serviceId, serviceName) {
    currentServiceId = serviceId;
    document.getElementById('modal_service_name').textContent = serviceName;
    document.getElementById('assignDevicesForm').action = `/acs/services/${serviceId}/assign-devices`;
    
    // Reset UI
    document.getElementById('devices_list').innerHTML = '';
    document.getElementById('devices_loading').style.display = 'block';
    document.getElementById('no_devices_message').style.display = 'none';
    document.getElementById('assign_devices_btn').disabled = true;
    
    // Open modal
    const modal = new bootstrap.Modal(document.getElementById('assignDevicesModal'));
    modal.show();
    
    // Load unassigned devices
    fetch('/acs/devices/unassigned-list')
        .then(response => response.json())
        .then(data => {
            document.getElementById('devices_loading').style.display = 'none';
            
            if (data.devices && data.devices.length > 0) {
                renderDevicesList(data.devices);
                document.getElementById('assign_devices_btn').disabled = false;
            } else {
                document.getElementById('no_devices_message').style.display = 'block';
                document.getElementById('assign_devices_btn').disabled = true;
            }
        })
        .catch(error => {
            document.getElementById('devices_loading').style.display = 'none';
            document.getElementById('devices_list').innerHTML = 
                '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Errore caricamento dispositivi</div>';
            console.error('Error loading unassigned devices:', error);
        });
}

function renderDevicesList(devices) {
    const listContainer = document.getElementById('devices_list');
    let html = '<div class="list-group">';
    
    devices.forEach(device => {
        html += `
            <label class="list-group-item d-flex align-items-center">
                <input class="form-check-input me-3" type="checkbox" name="device_ids[]" value="${device.id}">
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-0 text-sm">${device.serial_number}</h6>
                            <p class="text-xs text-secondary mb-0">
                                ${device.manufacturer || 'N/A'} - ${device.model_name || 'N/A'}
                            </p>
                        </div>
                        <div class="text-end">
                            <span class="badge badge-sm bg-gradient-${device.protocol_type === 'tr069' ? 'primary' : 'info'}">
                                ${device.protocol_type.toUpperCase()}
                            </span>
                            <span class="badge badge-sm bg-gradient-${device.status === 'online' ? 'success' : 'secondary'}">
                                ${device.status}
                            </span>
                        </div>
                    </div>
                </div>
            </label>
        `;
    });
    
    html += '</div>';
    html += `<div class="mt-3 text-center">
        <button type="button" class="btn btn-link btn-sm" onclick="selectAllDevices(true)">Seleziona Tutti</button>
        <button type="button" class="btn btn-link btn-sm" onclick="selectAllDevices(false)">Deseleziona Tutti</button>
    </div>`;
    
    listContainer.innerHTML = html;
}

function selectAllDevices(select) {
    const checkboxes = document.querySelectorAll('input[name="device_ids[]"]');
    checkboxes.forEach(cb => cb.checked = select);
}

// Handle form submission
document.getElementById('assignDevicesForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const deviceIds = formData.getAll('device_ids[]');
    
    if (deviceIds.length === 0) {
        alert('Seleziona almeno un dispositivo da assegnare');
        return;
    }
    
    const submitBtn = document.getElementById('assign_devices_btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Assegnazione...';
    
    fetch(this.action, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
        },
        body: JSON.stringify({ device_ids: deviceIds })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => Promise.reject(err));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('assignDevicesModal')).hide();
            window.location.reload();
        } else {
            alert('Errore: ' + (data.message || 'Impossibile assegnare dispositivi'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> Assegna Selezionati';
        }
    })
    .catch(error => {
        let errorMsg = 'Errore: ' + (error.message || 'Impossibile assegnare dispositivi');
        
        if (error.already_assigned && error.already_assigned.length > 0) {
            errorMsg = error.message + '\n\nDispositivi già assegnati:\n' + error.already_assigned.join(', ');
        }
        
        alert(errorMsg);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> Assegna Selezionati';
    });
});
</script>
@endpush
