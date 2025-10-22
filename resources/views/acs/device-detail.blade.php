@extends('layouts.app')

@section('breadcrumb', 'Dettaglio Dispositivo')
@section('page-title', $device->serial_number)

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Informazioni Dispositivo</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th class="text-sm">Serial Number:</th>
                        <td class="text-sm">{{ $device->serial_number }}</td>
                    </tr>
                    <tr>
                        <th class="text-sm">Manufacturer:</th>
                        <td class="text-sm">{{ $device->manufacturer }}</td>
                    </tr>
                    <tr>
                        <th class="text-sm">Model:</th>
                        <td class="text-sm">{{ $device->model_name }}</td>
                    </tr>
                    <tr>
                        <th class="text-sm">IP Address:</th>
                        <td class="text-sm">{{ $device->ip_address ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th class="text-sm">Stato:</th>
                        <td><span class="badge bg-gradient-{{ $device->status == 'online' ? 'success' : 'secondary' }}">{{ ucfirst($device->status) }}</span></td>
                    </tr>
                    <tr>
                        <th class="text-sm">Ultimo Inform:</th>
                        <td class="text-sm">{{ $device->last_inform ? $device->last_inform->format('d/m/Y H:i:s') : 'Mai' }}</td>
                    </tr>
                    <tr>
                        <th class="text-sm">Profilo Attivo:</th>
                        <td class="text-sm">{{ $device->configurationProfile->name ?? 'Nessuno' }}</td>
                    </tr>
                </table>
                
                <div class="mt-3">
                    <button class="btn btn-gradient-primary btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#editConfigModal">
                        <i class="fas fa-edit me-2"></i>Modifica Configurazione
                    </button>
                    <button class="btn btn-success btn-sm w-100 mb-2" onclick="provisionDevice({{ $device->id }}, '{{ $device->serial_number }}')">
                        <i class="fas fa-cog me-2"></i>Provisioning
                    </button>
                    <button class="btn btn-warning btn-sm w-100 mb-2" onclick="rebootDevice({{ $device->id }}, '{{ $device->serial_number }}')">
                        <i class="fas fa-sync me-2"></i>Reboot
                    </button>
                    <button class="btn btn-primary btn-sm w-100 mb-2" onclick="aiAnalyzeDeviceHistory({{ $device->id }})">
                        <i class="fas fa-magic me-2"></i>AI Diagnostic Analysis
                    </button>
                    @if($device->protocol_type === 'tr369')
                    <a href="{{ route('acs.devices.subscriptions', $device->id) }}" class="btn btn-info btn-sm w-100 mb-2">
                        <i class="fas fa-bell me-2"></i>Sottoscrizioni Eventi
                    </a>
                    @endif
                    <a href="{{ route('acs.devices') }}" class="btn btn-secondary btn-sm w-100">
                        <i class="fas fa-arrow-left me-2"></i>Torna alla Lista
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Parametri TR-181</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Parametro</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Valore</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($device->parameters as $param)
                            <tr>
                                <td class="text-xs px-3">{{ $param->parameter_path }}</td>
                                <td class="text-xs">{{ $param->parameter_value }}</td>
                                <td class="text-xs text-center">{{ $param->parameter_type }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-sm text-muted py-4">
                                    Nessun parametro disponibile
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Remote Diagnostics (TR-143)</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <button class="btn btn-outline-primary btn-sm w-100" onclick="openDiagnosticModal('ping', {{ $device->id }})">
                            <i class="fas fa-network-wired me-2"></i>Ping Test
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary btn-sm w-100" onclick="openDiagnosticModal('traceroute', {{ $device->id }})">
                            <i class="fas fa-route me-2"></i>Traceroute
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary btn-sm w-100" onclick="openDiagnosticModal('download', {{ $device->id }})">
                            <i class="fas fa-download me-2"></i>Download Test
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary btn-sm w-100" onclick="openDiagnosticModal('upload', {{ $device->id }})">
                            <i class="fas fa-upload me-2"></i>Upload Test
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                <h6>Network Topology Map</h6>
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
                            <p>Click "Scan Network" to visualize connected clients</p>
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
        
        <div class="card mb-4">
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
                                    <i class="fas fa-check-circle me-2"></i>No pending commands (all devices reachable via Connection Request)
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($pendingCommands->count() > 0)
                <div class="px-3 py-2">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-2"></i>Commands are queued when Connection Request fails (device behind NAT). They will execute automatically during the next Periodic Inform.
                    </small>
                </div>
                @endif
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Task Recenti</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tipo Task</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Stato</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTasks as $task)
                            <tr>
                                <td class="text-xs px-3">{{ ucfirst(str_replace('_', ' ', $task->task_type)) }}</td>
                                <td class="text-center">
                                    <span class="badge badge-sm bg-gradient-{{ $task->status == 'completed' ? 'success' : ($task->status == 'failed' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($task->status) }}
                                    </span>
                                </td>
                                <td class="text-xs text-center">{{ $task->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-sm text-muted py-4">
                                    Nessun task
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

<!-- Modal Provisioning -->
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

<!-- Modal Diagnostic Test -->
<div class="modal fade" id="diagnosticModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="diagnosticModalTitle">Diagnostic Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
@endsection

<!-- Modal AI Historical Analysis -->
<div class="modal fade" id="aiHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title text-white"><i class="fas fa-chart-line me-2"></i>AI Historical Diagnostic Analysis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="aiHistoryContent"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
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
            let html = '';
            
            // Header with test count and confidence
            html += `<div class="alert alert-info mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-database me-2"></i>Tests Analyzed: <strong>${result.tests_analyzed}</strong></span>
                    <span><i class="fas fa-percentage me-2"></i>Confidence: <strong>${result.confidence}%</strong></span>
                    <span class="badge bg-gradient-${result.trend === 'improving' ? 'success' : result.trend === 'degrading' ? 'danger' : 'secondary'}">
                        Trend: ${result.trend.toUpperCase()}
                    </span>
                </div>
            </div>`;
            
            // Root Cause
            if (result.root_cause) {
                html += `<div class="card mb-3">
                    <div class="card-header bg-gradient-dark">
                        <h6 class="text-white mb-0"><i class="fas fa-search me-2"></i>Root Cause Analysis</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">${result.root_cause}</p>
                    </div>
                </div>`;
            }
            
            // Patterns Detected
            if (result.patterns && result.patterns.length > 0) {
                html += `<div class="card mb-3">
                    <div class="card-header bg-gradient-warning">
                        <h6 class="text-white mb-0"><i class="fas fa-chart-area me-2"></i>Patterns Detected (${result.patterns.length})</h6>
                    </div>
                    <div class="card-body">`;
                
                result.patterns.forEach((pattern, index) => {
                    const typeClass = {
                        'degradation': 'danger',
                        'intermittent': 'warning',
                        'recurring': 'info'
                    }[pattern.type] || 'secondary';
                    
                    html += `<div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">${index + 1}. ${pattern.description}</h6>
                            <span class="badge bg-gradient-${typeClass}">${pattern.type}</span>
                        </div>
                        <p class="text-sm mb-1"><strong>Affected Tests:</strong> ${pattern.affected_tests.join(', ')}</p>
                        <p class="text-sm mb-0"><strong>Frequency:</strong> ${pattern.frequency}</p>
                    </div>`;
                });
                
                html += `</div></div>`;
            }
            
            // Recommendations
            if (result.recommendations && result.recommendations.length > 0) {
                html += `<div class="card mb-3">
                    <div class="card-header bg-gradient-success">
                        <h6 class="text-white mb-0"><i class="fas fa-lightbulb me-2"></i>Recommendations (${result.recommendations.length})</h6>
                    </div>
                    <div class="card-body">`;
                
                result.recommendations.forEach((rec, index) => {
                    const priorityClass = {
                        'high': 'danger',
                        'medium': 'warning',
                        'low': 'info'
                    }[rec.priority] || 'secondary';
                    
                    html += `<div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">${index + 1}. ${rec.action}</h6>
                            <span class="badge bg-gradient-${priorityClass}">${rec.priority} priority</span>
                        </div>
                        <p class="text-sm mb-0"><strong>Rationale:</strong> ${rec.rationale}</p>
                    </div>`;
                });
                
                html += `</div></div>`;
            }
            
            if (!result.patterns || result.patterns.length === 0) {
                html += `<div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>Nessun pattern critico rilevato. Il dispositivo sembra operare normalmente.
                </div>`;
            }
            
            content.innerHTML = html;
        } else {
            content.innerHTML = `<div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>${result.error}
            </div>`;
        }
    } catch (error) {
        content.innerHTML = `<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>Errore di connessione: ${error.message}
        </div>`;
    }
}

function openDiagnosticModal(type, deviceId) {
    const titles = {
        ping: 'Ping Test (IPPing)',
        traceroute: 'Traceroute Test',
        download: 'Download Speed Test',
        upload: 'Upload Speed Test'
    };
    
    const forms = {
        ping: `
            <div class="mb-3">
                <label class="form-label">Host / IP Address *</label>
                <input type="text" class="form-control" name="host" placeholder="8.8.8.8 or google.com" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Numero Pacchetti</label>
                    <input type="number" class="form-control" name="packets" value="4" min="1" max="100">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Timeout (ms)</label>
                    <input type="number" class="form-control" name="timeout" value="1000" min="100" max="10000">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Dimensione Pacchetto (bytes)</label>
                <input type="number" class="form-control" name="size" value="64" min="32" max="1500">
            </div>
        `,
        traceroute: `
            <div class="mb-3">
                <label class="form-label">Host / IP Address *</label>
                <input type="text" class="form-control" name="host" placeholder="8.8.8.8 or google.com" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Numero Tentativi</label>
                    <input type="number" class="form-control" name="tries" value="3" min="1" max="10">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Timeout (ms)</label>
                    <input type="number" class="form-control" name="timeout" value="5000" min="100" max="30000">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Max Hop Count</label>
                <input type="number" class="form-control" name="max_hops" value="30" min="1" max="64">
            </div>
        `,
        download: `
            <div class="mb-3">
                <label class="form-label">Download URL *</label>
                <input type="url" class="form-control" name="url" placeholder="http://example.com/test.bin" required>
            </div>
            <div class="mb-3">
                <label class="form-label">File Size (bytes, 0=auto)</label>
                <input type="number" class="form-control" name="file_size" value="0" min="0">
            </div>
        `,
        upload: `
            <div class="mb-3">
                <label class="form-label">Upload URL *</label>
                <input type="url" class="form-control" name="url" placeholder="http://example.com/upload" required>
            </div>
            <div class="mb-3">
                <label class="form-label">File Size (bytes)</label>
                <input type="number" class="form-control" name="file_size" value="1048576" min="0" max="104857600">
                <small class="text-muted">Max 100MB</small>
            </div>
        `
    };
    
    document.getElementById('diagnosticModalTitle').textContent = titles[type] || 'Diagnostic Test';
    document.getElementById('diagnosticFormFields').innerHTML = forms[type] || '';
    
    const form = document.getElementById('diagnosticForm');
    form.onsubmit = async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch(`/acs/devices/${deviceId}/diagnostics/${type}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('diagnosticModal')).hide();
                alert(`✅ ${titles[type]} avviato con successo! ID: ${result.diagnostic.id}`);
                location.reload();
            } else {
                alert(`❌ Errore: ${result.message}`);
            }
        } catch (error) {
            alert(`❌ Errore connessione: ${error.message}`);
        }
    };
    
    new bootstrap.Modal(document.getElementById('diagnosticModal')).show();
}

// Network Topology Map Functions
async function triggerNetworkScan(deviceId) {
    try {
        const response = await fetch(`/acs/devices/${deviceId}/trigger-network-scan`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ data_model: 'tr098' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ ' + result.message);
            // Wait 3 seconds then load network map
            setTimeout(() => loadNetworkMap(deviceId), 3000);
        } else {
            alert('❌ Errore: ' + result.message);
        }
    } catch (error) {
        alert('❌ Errore connessione: ' + error.message);
    }
}

async function loadNetworkMap(deviceId) {
    try {
        const response = await fetch(`/acs/devices/${deviceId}/network-map`);
        const result = await response.json();
        
        if (result.success && result.clients.length > 0) {
            updateNetworkStats(result.stats);
            renderNetworkTopology(result.device, result.clients);
            renderClientsList(result.clients);
        } else {
            document.getElementById('network-topology-container').innerHTML = 
                '<div class="d-flex justify-content-center align-items-center h-100"><div class="text-center text-muted"><i class="fas fa-exclamation-circle fa-3x mb-3"></i><p>No clients found. Try scanning again.</p></div></div>';
        }
    } catch (error) {
        console.error('Error loading network map:', error);
    }
}

function updateNetworkStats(stats) {
    document.getElementById('stats-total').textContent = stats.total;
    document.getElementById('stats-lan').textContent = stats.lan;
    document.getElementById('stats-wifi24').textContent = stats.wifi_2_4ghz;
    document.getElementById('stats-wifi5').textContent = stats.wifi_5ghz;
}

function renderNetworkTopology(device, clients) {
    const container = document.getElementById('network-topology-container');
    container.innerHTML = '';
    
    // Create SVG canvas
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', '100%');
    svg.style.position = 'absolute';
    svg.style.top = '0';
    svg.style.left = '0';
    
    // Draw router (center)
    const routerX = container.offsetWidth / 2;
    const routerY = 80;
    
    const routerCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    routerCircle.setAttribute('cx', routerX);
    routerCircle.setAttribute('cy', routerY);
    routerCircle.setAttribute('r', '30');
    routerCircle.setAttribute('fill', '#5e72e4');
    svg.appendChild(routerCircle);
    
    // Router icon (text)
    const routerText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
    routerText.setAttribute('x', routerX);
    routerText.setAttribute('y', routerY + 5);
    routerText.setAttribute('text-anchor', 'middle');
    routerText.setAttribute('fill', 'white');
    routerText.setAttribute('font-size', '14');
    routerText.textContent = '🛜';
    svg.appendChild(routerText);
    
    // Draw clients in circle around router
    const radius = 150;
    const angleStep = (2 * Math.PI) / clients.length;
    
    clients.forEach((client, index) => {
        const angle = angleStep * index - Math.PI / 2;
        const x = routerX + radius * Math.cos(angle);
        const y = routerY + radius * Math.sin(angle);
        
        // Draw connection line
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', routerX);
        line.setAttribute('y1', routerY);
        line.setAttribute('x2', x);
        line.setAttribute('y2', y);
        line.setAttribute('stroke', client.connection_type === 'lan' ? '#2dce89' : '#11cdef');
        line.setAttribute('stroke-width', '2');
        line.setAttribute('stroke-dasharray', client.connection_type === 'lan' ? '0' : '5,5');
        svg.appendChild(line);
        
        // Draw client circle
        const clientCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        clientCircle.setAttribute('cx', x);
        clientCircle.setAttribute('cy', y);
        clientCircle.setAttribute('r', '20');
        clientCircle.setAttribute('fill', client.connection_type === 'lan' ? '#2dce89' : '#11cdef');
        clientCircle.setAttribute('data-bs-toggle', 'tooltip');
        clientCircle.setAttribute('title', `${client.hostname}\\n${client.ip_address}\\nMAC: ${client.mac_address}${client.signal_strength ? '\\nSignal: ' + client.signal_strength + ' dBm' : ''}`);
        svg.appendChild(clientCircle);
        
        // Client icon
        const clientIcon = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        clientIcon.setAttribute('x', x);
        clientIcon.setAttribute('y', y + 5);
        clientIcon.setAttribute('text-anchor', 'middle');
        clientIcon.setAttribute('fill', 'white');
        clientIcon.setAttribute('font-size', '12');
        clientIcon.textContent = client.connection_type === 'lan' ? '💻' : '📱';
        svg.appendChild(clientIcon);
    });
    
    container.appendChild(svg);
}

function renderClientsList(clients) {
    const tbody = document.getElementById('clients-table-body');
    tbody.innerHTML = '';
    
    clients.forEach(client => {
        const signalBadge = client.signal_strength ? 
            `<span class="badge badge-sm bg-gradient-${client.signal_quality === 'excellent' ? 'success' : client.signal_quality === 'good' ? 'info' : client.signal_quality === 'fair' ? 'warning' : 'danger'}">
                ${client.signal_strength} dBm
            </span>` : 
            '<span class="text-muted">-</span>';
            
        const connectionBadge = `<span class="badge badge-sm bg-gradient-${client.connection_type === 'lan' ? 'success' : 'info'}">
            <i class="fas ${client.connection_icon} me-1"></i>${client.connection_type.replace('_', ' ')}
        </span>`;
        
        const row = `
            <tr>
                <td class="text-xs px-3">
                    <strong>${client.hostname}</strong><br>
                    <small class="text-muted">${client.mac_address}</small>
                </td>
                <td class="text-xs">${client.ip_address}</td>
                <td class="text-xs">${connectionBadge}</td>
                <td class="text-xs">${signalBadge}</td>
                <td class="text-xs">${client.last_seen}</td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
    
    document.getElementById('network-clients-list').style.display = 'block';
}

// Auto-load network map on page load if data exists
document.addEventListener('DOMContentLoaded', () => {
    const deviceId = {{ $device->id }};
    loadNetworkMap(deviceId);
});

// NAT Traversal: Retry pending command
async function retryPendingCommand(commandId) {
    if (!confirm('Retry this failed command?')) return;
    
    try {
        const response = await fetch(`/acs/pending-commands/${commandId}/retry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Command queued for retry. It will execute on next Periodic Inform.');
            location.reload();
        } else {
            alert('❌ Error: ' + result.message);
        }
    } catch (error) {
        alert('❌ Connection error: ' + error.message);
    }
}

// NAT Traversal: Cancel pending command
async function cancelPendingCommand(commandId) {
    if (!confirm('Cancel this pending command?')) return;
    
    try {
        const response = await fetch(`/acs/pending-commands/${commandId}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Command cancelled successfully.');
            location.reload();
        } else {
            alert('❌ Error: ' + result.message);
        }
    } catch (error) {
        alert('❌ Connection error: ' + error.message);
    }
}

// ====================
// CPE Configuration Editor
// ====================
function openConfigEditor() {
    document.getElementById('editConfigModal').style.display = 'block';
}

async function saveWiFiConfig() {
    const ssid = document.getElementById('wifi-ssid').value;
    const password = document.getElementById('wifi-password').value;
    const channel = document.getElementById('wifi-channel').value;
    const enabled = document.getElementById('wifi-enabled').checked;
    
    if (!ssid) {
        alert('⚠️ SSID is required');
        return;
    }
    
    const params = {
        'Device.WiFi.SSID.1.SSID': ssid,
        'Device.WiFi.SSID.1.Enable': enabled ? 'true' : 'false'
    };
    
    if (password) {
        params['Device.WiFi.AccessPoint.1.Security.KeyPassphrase'] = password;
    }
    
    if (channel) {
        params['Device.WiFi.Radio.1.Channel'] = channel;
    }
    
    await saveParameters(params, 'WiFi');
}

async function saveLANConfig() {
    const ipAddress = document.getElementById('lan-ip').value;
    const subnetMask = document.getElementById('lan-subnet').value;
    const dhcpEnabled = document.getElementById('lan-dhcp-enabled').checked;
    const dhcpStart = document.getElementById('lan-dhcp-start').value;
    const dhcpEnd = document.getElementById('lan-dhcp-end').value;
    
    if (!ipAddress) {
        alert('⚠️ IP Address is required');
        return;
    }
    
    const params = {
        'Device.IP.Interface.1.IPv4Address.1.IPAddress': ipAddress
    };
    
    if (subnetMask) {
        params['Device.IP.Interface.1.IPv4Address.1.SubnetMask'] = subnetMask;
    }
    
    if (dhcpEnabled !== undefined) {
        params['Device.DHCPv4.Server.Enable'] = dhcpEnabled ? 'true' : 'false';
    }
    
    if (dhcpStart) {
        params['Device.DHCPv4.Server.Pool.1.MinAddress'] = dhcpStart;
    }
    
    if (dhcpEnd) {
        params['Device.DHCPv4.Server.Pool.1.MaxAddress'] = dhcpEnd;
    }
    
    await saveParameters(params, 'LAN');
}

async function saveAdvancedParams() {
    const customParams = document.getElementById('advanced-params').value;
    
    if (!customParams.trim()) {
        alert('⚠️ No parameters to save');
        return;
    }
    
    try {
        const params = JSON.parse(customParams);
        await saveParameters(params, 'Advanced');
    } catch (e) {
        alert('⚠️ Invalid JSON format:\n' + e.message);
    }
}

async function savePortForwardingConfig() {
    const name = document.getElementById('pf-name').value;
    const protocol = document.getElementById('pf-protocol').value;
    const externalPort = document.getElementById('pf-external-port').value;
    const internalPort = document.getElementById('pf-internal-port').value;
    const internalIp = document.getElementById('pf-internal-ip').value;
    const enabled = document.getElementById('pf-enabled').checked;
    
    if (!name || !externalPort || !internalPort || !internalIp) {
        alert('⚠️ Tutti i campi sono obbligatori');
        return;
    }
    
    const params = {
        'Device.NAT.PortMapping.1.Enable': enabled ? 'true' : 'false',
        'Device.NAT.PortMapping.1.Description': name,
        'Device.NAT.PortMapping.1.Protocol': protocol,
        'Device.NAT.PortMapping.1.ExternalPort': externalPort,
        'Device.NAT.PortMapping.1.InternalPort': internalPort,
        'Device.NAT.PortMapping.1.InternalClient': internalIp
    };
    
    await saveParameters(params, 'Port Forwarding');
}

async function saveQoSConfig() {
    const enabled = document.getElementById('qos-enabled').checked;
    const upload = document.getElementById('qos-upload').value;
    const download = document.getElementById('qos-download').value;
    const voip = document.getElementById('qos-voip').value;
    const video = document.getElementById('qos-video').value;
    const gaming = document.getElementById('qos-gaming').value;
    const downloadApps = document.getElementById('qos-download-apps').value;
    
    const params = {
        'Device.QoS.Enable': enabled ? 'true' : 'false'
    };
    
    if (upload) {
        params['Device.QoS.MaxBandwidth.Upstream'] = upload;
    }
    
    if (download) {
        params['Device.QoS.MaxBandwidth.Downstream'] = download;
    }
    
    // Map priorities to DSCP values
    const priorityMap = { high: '46', medium: '26', low: '10' };
    
    params['Device.QoS.Classification.1.DSCPMark'] = priorityMap[voip];
    params['Device.QoS.Classification.2.DSCPMark'] = priorityMap[video];
    params['Device.QoS.Classification.3.DSCPMark'] = priorityMap[gaming];
    params['Device.QoS.Classification.4.DSCPMark'] = priorityMap[downloadApps];
    
    await saveParameters(params, 'QoS');
}

async function saveParentalControlConfig() {
    const enabled = document.getElementById('parental-enabled').checked;
    const blockAdult = document.getElementById('parental-adult').checked;
    const blockGambling = document.getElementById('parental-gambling').checked;
    const blockViolence = document.getElementById('parental-violence').checked;
    const blockSocial = document.getElementById('parental-social').checked;
    const startTime = document.getElementById('parental-start-time').value;
    const endTime = document.getElementById('parental-end-time').value;
    const devices = document.getElementById('parental-devices').value;
    const blockedSites = document.getElementById('parental-blocked-sites').value;
    
    const params = {
        'Device.X_ParentalControl.Enable': enabled ? 'true' : 'false',
        'Device.X_ParentalControl.Filter.Adult': blockAdult ? 'true' : 'false',
        'Device.X_ParentalControl.Filter.Gambling': blockGambling ? 'true' : 'false',
        'Device.X_ParentalControl.Filter.Violence': blockViolence ? 'true' : 'false',
        'Device.X_ParentalControl.Filter.SocialMedia': blockSocial ? 'true' : 'false',
        'Device.X_ParentalControl.Schedule.StartTime': startTime,
        'Device.X_ParentalControl.Schedule.EndTime': endTime
    };
    
    if (devices.trim()) {
        params['Device.X_ParentalControl.MACList'] = devices.trim().replace(/\n/g, ',');
    }
    
    if (blockedSites.trim()) {
        params['Device.X_ParentalControl.BlockedSites'] = blockedSites.trim().replace(/\n/g, ',');
    }
    
    await saveParameters(params, 'Parental Control');
}

function addPortForwardingRule() {
    alert('📋 Funzionalità in sviluppo: Visualizzazione regole esistenti.\n\nPer ora puoi aggiungere una nuova regola usando il form sotto.');
}

async function saveParameters(params, configType) {
    const deviceId = {{ $device->id }};
    const protocol = '{{ $device->protocol_type }}';
    
    const submitBtn = event.target;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    
    try {
        let url, payload;
        
        if (protocol === 'tr369') {
            url = `/api/v1/usp/devices/${deviceId}/set-params`;
            payload = { param_paths: params };
        } else {
            url = `/api/v1/devices/${deviceId}/parameters/set`;
            payload = { parameters: params };
        }
        
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            alert(`✅ ${configType} configuration saved successfully!\n\nDevice will apply changes shortly.`);
            
            // Close modal and reload page after 2 seconds
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('editConfigModal')).hide();
                location.reload();
            }, 2000);
        } else {
            alert(`❌ Error saving ${configType} configuration:\n${result.message || 'Unknown error'}`);
        }
    } catch (error) {
        alert(`❌ Connection error:\n${error.message}`);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Changes';
    }
}
</script>

<!-- Modal for CPE Configuration -->
<div class="modal fade" id="editConfigModal" tabindex="-1" aria-labelledby="editConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title text-white" id="editConfigModalLabel">
                    <i class="fas fa-sliders-h me-2"></i>Modifica Configurazione CPE
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Nav tabs -->
                <ul class="nav nav-pills nav-fill mb-4" id="configTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="wifi-tab" data-bs-toggle="tab" data-bs-target="#wifi-config" type="button" role="tab">
                            <i class="fas fa-wifi me-2"></i>WiFi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="lan-tab" data-bs-toggle="tab" data-bs-target="#lan-config" type="button" role="tab">
                            <i class="fas fa-network-wired me-2"></i>LAN
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="portfw-tab" data-bs-toggle="tab" data-bs-target="#portfw-config" type="button" role="tab">
                            <i class="fas fa-exchange-alt me-2"></i>Port Forwarding
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="qos-tab" data-bs-toggle="tab" data-bs-target="#qos-config" type="button" role="tab">
                            <i class="fas fa-tachometer-alt me-2"></i>QoS
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="parental-tab" data-bs-toggle="tab" data-bs-target="#parental-config" type="button" role="tab">
                            <i class="fas fa-shield-alt me-2"></i>Parental
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced-config" type="button" role="tab">
                            <i class="fas fa-cogs me-2"></i>Advanced
                        </button>
                    </li>
                </ul>

                <!-- Tab content -->
                <div class="tab-content" id="configTabContent">
                    <!-- WiFi Tab -->
                    <div class="tab-pane fade show active" id="wifi-config" role="tabpanel">
                        <form id="wifi-form">
                            <div class="mb-3">
                                <label for="wifi-ssid" class="form-label">SSID (Nome Rete)</label>
                                <input type="text" class="form-control" id="wifi-ssid" placeholder="es. MyWiFi_5G">
                            </div>
                            <div class="mb-3">
                                <label for="wifi-password" class="form-label">Password WiFi</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="wifi-password" placeholder="Minimo 8 caratteri">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('wifi-password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Lascia vuoto per non modificare</small>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="wifi-channel" class="form-label">Canale WiFi</label>
                                    <select class="form-select" id="wifi-channel">
                                        <option value="">Auto</option>
                                        <option value="1">1 (2.4GHz)</option>
                                        <option value="6">6 (2.4GHz)</option>
                                        <option value="11">11 (2.4GHz)</option>
                                        <option value="36">36 (5GHz)</option>
                                        <option value="40">40 (5GHz)</option>
                                        <option value="44">44 (5GHz)</option>
                                        <option value="48">48 (5GHz)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Stato WiFi</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="wifi-enabled" checked>
                                        <label class="form-check-label" for="wifi-enabled">WiFi Abilitato</label>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="saveWiFiConfig()">
                                <i class="fas fa-save me-2"></i>Salva Configurazione WiFi
                            </button>
                        </form>
                    </div>

                    <!-- LAN Tab -->
                    <div class="tab-pane fade" id="lan-config" role="tabpanel">
                        <form id="lan-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="lan-ip" class="form-label">IP Address LAN</label>
                                    <input type="text" class="form-control" id="lan-ip" placeholder="es. 192.168.1.1">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lan-subnet" class="form-label">Subnet Mask</label>
                                    <input type="text" class="form-control" id="lan-subnet" placeholder="es. 255.255.255.0">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">DHCP Server</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="lan-dhcp-enabled" checked>
                                    <label class="form-check-label" for="lan-dhcp-enabled">DHCP Abilitato</label>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="lan-dhcp-start" class="form-label">DHCP Start IP</label>
                                    <input type="text" class="form-control" id="lan-dhcp-start" placeholder="es. 192.168.1.100">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lan-dhcp-end" class="form-label">DHCP End IP</label>
                                    <input type="text" class="form-control" id="lan-dhcp-end" placeholder="es. 192.168.1.200">
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="saveLANConfig()">
                                <i class="fas fa-save me-2"></i>Salva Configurazione LAN
                            </button>
                        </form>
                    </div>

                    <!-- Port Forwarding Tab -->
                    <div class="tab-pane fade" id="portfw-config" role="tabpanel">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Attenzione:</strong> Port Forwarding può esporre servizi alla rete pubblica. Configura con attenzione.
                        </div>
                        <form id="portfw-form">
                            <div id="portfw-rules-container">
                                <!-- Port Forwarding Rules will be added here -->
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm mb-3" onclick="addPortForwardingRule()">
                                <i class="fas fa-plus me-2"></i>Aggiungi Regola
                            </button>
                            
                            <hr>
                            
                            <h6 class="mb-3">Nuova Regola Port Forwarding</h6>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="pf-name" class="form-label">Nome</label>
                                    <input type="text" class="form-control" id="pf-name" placeholder="es. Web Server">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="pf-protocol" class="form-label">Protocollo</label>
                                    <select class="form-select" id="pf-protocol">
                                        <option value="TCP">TCP</option>
                                        <option value="UDP">UDP</option>
                                        <option value="Both">TCP+UDP</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="pf-external-port" class="form-label">Porta Esterna</label>
                                    <input type="number" class="form-control" id="pf-external-port" placeholder="8080">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="pf-internal-port" class="form-label">Porta Interna</label>
                                    <input type="number" class="form-control" id="pf-internal-port" placeholder="80">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pf-internal-ip" class="form-label">IP Interno</label>
                                    <input type="text" class="form-control" id="pf-internal-ip" placeholder="192.168.1.100">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Stato</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="pf-enabled" checked>
                                        <label class="form-check-label" for="pf-enabled">Regola Attiva</label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="savePortForwardingConfig()">
                                <i class="fas fa-save me-2"></i>Salva Port Forwarding
                            </button>
                        </form>
                    </div>

                    <!-- QoS Tab -->
                    <div class="tab-pane fade" id="qos-config" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>QoS (Quality of Service):</strong> Gestisci la priorità del traffico per ottimizzare le prestazioni.
                        </div>
                        <form id="qos-form">
                            <div class="mb-3">
                                <label class="form-label">Abilita QoS</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="qos-enabled">
                                    <label class="form-check-label" for="qos-enabled">QoS Attivo</label>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="qos-upload" class="form-label">Upload Speed (Mbps)</label>
                                    <input type="number" class="form-control" id="qos-upload" placeholder="100">
                                    <small class="text-muted">Velocità massima upload della tua connessione</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="qos-download" class="form-label">Download Speed (Mbps)</label>
                                    <input type="number" class="form-control" id="qos-download" placeholder="1000">
                                    <small class="text-muted">Velocità massima download della tua connessione</small>
                                </div>
                            </div>
                            
                            <h6 class="mb-3">Priorità Applicazioni</h6>
                            <div class="mb-3">
                                <label for="qos-voip" class="form-label">VoIP / Chiamate</label>
                                <select class="form-select" id="qos-voip">
                                    <option value="high">Alta Priorità</option>
                                    <option value="medium">Media Priorità</option>
                                    <option value="low">Bassa Priorità</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="qos-video" class="form-label">Streaming Video</label>
                                <select class="form-select" id="qos-video">
                                    <option value="high">Alta Priorità</option>
                                    <option value="medium" selected>Media Priorità</option>
                                    <option value="low">Bassa Priorità</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="qos-gaming" class="form-label">Gaming Online</label>
                                <select class="form-select" id="qos-gaming">
                                    <option value="high">Alta Priorità</option>
                                    <option value="medium" selected>Media Priorità</option>
                                    <option value="low">Bassa Priorità</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="qos-download-apps" class="form-label">Download / P2P</label>
                                <select class="form-select" id="qos-download-apps">
                                    <option value="high">Alta Priorità</option>
                                    <option value="medium">Media Priorità</option>
                                    <option value="low" selected>Bassa Priorità</option>
                                </select>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="saveQoSConfig()">
                                <i class="fas fa-save me-2"></i>Salva Configurazione QoS
                            </button>
                        </form>
                    </div>

                    <!-- Parental Control Tab -->
                    <div class="tab-pane fade" id="parental-config" role="tabpanel">
                        <div class="alert alert-success">
                            <i class="fas fa-shield-alt me-2"></i>
                            <strong>Parental Control:</strong> Proteggi i tuoi bambini online con filtri e restrizioni orarie.
                        </div>
                        <form id="parental-form">
                            <div class="mb-3">
                                <label class="form-label">Abilita Parental Control</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="parental-enabled">
                                    <label class="form-check-label" for="parental-enabled">Filtri Attivi</label>
                                </div>
                            </div>
                            
                            <h6 class="mb-3">Filtri Contenuti</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="parental-adult" checked>
                                <label class="form-check-label" for="parental-adult">
                                    Blocca contenuti per adulti
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="parental-gambling" checked>
                                <label class="form-check-label" for="parental-gambling">
                                    Blocca siti di gioco d'azzardo
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="parental-violence">
                                <label class="form-check-label" for="parental-violence">
                                    Blocca contenuti violenti
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="parental-social">
                                <label class="form-check-label" for="parental-social">
                                    Blocca social media
                                </label>
                            </div>
                            
                            <h6 class="mb-3">Restrizioni Orarie</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="parental-start-time" class="form-label">Ora Inizio Blocco</label>
                                    <input type="time" class="form-control" id="parental-start-time" value="22:00">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parental-end-time" class="form-label">Ora Fine Blocco</label>
                                    <input type="time" class="form-control" id="parental-end-time" value="07:00">
                                </div>
                            </div>
                            
                            <h6 class="mb-3">Dispositivi Controllati (MAC Address)</h6>
                            <div class="mb-3">
                                <textarea class="form-control font-monospace" id="parental-devices" rows="3" placeholder="AA:BB:CC:DD:EE:FF&#10;11:22:33:44:55:66"></textarea>
                                <small class="text-muted">Inserisci un MAC address per riga</small>
                            </div>
                            
                            <h6 class="mb-3">Siti Bloccati (Lista Custom)</h6>
                            <div class="mb-3">
                                <textarea class="form-control" id="parental-blocked-sites" rows="3" placeholder="facebook.com&#10;instagram.com&#10;tiktok.com"></textarea>
                                <small class="text-muted">Inserisci un dominio per riga</small>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="saveParentalControlConfig()">
                                <i class="fas fa-save me-2"></i>Salva Parental Control
                            </button>
                        </form>
                    </div>

                    <!-- Advanced Tab -->
                    <div class="tab-pane fade" id="advanced-config" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Modalità Avanzata:</strong> Inserisci parametri TR-069/TR-369 in formato JSON
                        </div>
                        <form id="advanced-form">
                            <div class="mb-3">
                                <label for="advanced-params" class="form-label">Parametri JSON</label>
                                <textarea class="form-control font-monospace" id="advanced-params" rows="10" placeholder='{
  "Device.ManagementServer.PeriodicInformInterval": "300",
  "Device.Time.NTPServer1": "pool.ntp.org",
  "Device.DNS.Client.Server.1.DNSServer": "8.8.8.8"
}'></textarea>
                                <small class="text-muted">Formato: { "parameter_path": "value", ... }</small>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="saveAdvancedParams()">
                                <i class="fas fa-save me-2"></i>Salva Parametri Avanzati
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Chiudi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const btn = event.target.closest('button');
    const icon = btn.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
@endpush
