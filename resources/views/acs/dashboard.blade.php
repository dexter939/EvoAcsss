@extends('layouts.app')

@section('breadcrumb', 'Dashboard')
@section('page-title', 'Dashboard ACS')

@push('styles')
<link href="/assets/css/dashboard-enhancements.css" rel="stylesheet" />
<style>
.mini-chart-container {
    height: 50px;
    width: 100%;
}
.trend-indicator {
    font-size: 0.875rem;
    font-weight: 600;
}
.trend-up { color: #82d616; }
.trend-down { color: #ea0606; }
.activity-timeline {
    position: relative;
    padding-left: 2rem;
}
.activity-timeline::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #cb0c9f, #17c1e8);
}
.activity-item {
    position: relative;
    padding-bottom: 1.5rem;
}
.activity-dot {
    position: absolute;
    left: -1.65rem;
    top: 0.25rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px currentColor;
}
</style>
@endpush

@section('content')
<!-- Main Statistics Cards with Trend Indicators -->
<div class="row">
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card shadow-xl hover-lift">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Dispositivi Totali</p>
                            <h5 class="font-weight-bolder mb-0">
                                <span class="stat-devices-total">{{ $stats['devices']['total'] ?? 0 }}</span>
                            </h5>
                            <p class="mb-0">
                                <span class="text-success text-sm font-weight-bolder trend-indicator">
                                    <i class="fas fa-arrow-up"></i> {{ $stats['devices']['online'] ?? 0 }} online
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                            <i class="ni ni-world text-lg opacity-10" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="mini-chart-container">
                            <canvas id="miniDevicesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card shadow-xl hover-lift">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Task in Coda</p>
                            <h5 class="font-weight-bolder mb-0">
                                <span class="stat-tasks-pending">{{ $stats['tasks']['pending'] ?? 0 }}</span>
                            </h5>
                            <p class="mb-0">
                                <span class="text-warning text-sm font-weight-bolder">
                                    <i class="fas fa-clock"></i> {{ $stats['tasks']['processing'] ?? 0 }} in elaborazione
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                            <i class="ni ni-paper-diploma text-lg opacity-10" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="mini-chart-container">
                            <canvas id="miniTasksChart"></canvas>
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
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Firmware Deploy</p>
                            <h5 class="font-weight-bolder mb-0">
                                <span class="stat-firmware-total">{{ $stats['firmware']['total_deployments'] ?? 0 }}</span>
                            </h5>
                            <p class="mb-0">
                                <span class="text-success text-sm font-weight-bolder">
                                    <i class="fas fa-check"></i> {{ $stats['firmware']['completed'] ?? 0 }} completati
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                            <i class="ni ni-atom text-lg opacity-10" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="mini-chart-container">
                            <canvas id="miniFirmwareChart"></canvas>
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
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Test Diagnostici</p>
                            <h5 class="font-weight-bolder mb-0">
                                <span class="stat-diagnostics-total">{{ $stats['diagnostics']['total'] ?? 0 }}</span>
                            </h5>
                            <p class="mb-0">
                                <span class="text-info text-sm font-weight-bolder">
                                    @if(($stats['diagnostics']['total'] ?? 0) > 0)
                                        {{ round(($stats['diagnostics']['completed'] / $stats['diagnostics']['total']) * 100) }}% successo
                                    @else
                                        0% successo
                                    @endif
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                            <i class="ni ni-chart-bar-32 text-lg opacity-10" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="mini-chart-container">
                            <canvas id="miniDiagnosticsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row mt-4">
    <!-- Recent Devices Table (Soft UI PRO Style) -->
    <div class="col-lg-8 mb-lg-0 mb-4">
        <div class="card">
            <div class="card-header pb-0">
                <div class="row">
                    <div class="col-lg-6 col-7">
                        <h6>Dispositivi CPE Recenti</h6>
                        <p class="text-sm mb-0">
                            <i class="fa fa-check text-info" aria-hidden="true"></i>
                            <span class="font-weight-bold ms-1">{{ count($stats['recent_devices'] ?? []) }} dispositivi</span> attivi nelle ultime ore
                        </p>
                    </div>
                    <div class="col-lg-6 col-5 my-auto text-end">
                        <div class="dropdown float-lg-end pe-4">
                            <a class="cursor-pointer" id="dropdownTable" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-ellipsis-v text-secondary"></i>
                            </a>
                            <ul class="dropdown-menu px-2 py-3 ms-sm-n4 ms-n5" aria-labelledby="dropdownTable">
                                <li><a class="dropdown-item border-radius-md" href="{{ route('acs.devices') }}">Vedi tutti</a></li>
                                <li><a class="dropdown-item border-radius-md" href="#" data-bs-toggle="modal" data-bs-target="#addDeviceModal">Aggiungi nuovo</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body px-0 pb-2">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Dispositivo</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Protocollo</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Stato</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Ultimo Contatto</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stats['recent_devices'] ?? [] as $device)
                            <tr>
                                <td>
                                    <div class="d-flex px-2 py-1">
                                        <div>
                                            <i class="fas fa-{{ $device->protocol_type == 'tr369' ? 'satellite-dish' : 'router' }} text-{{ $device->protocol_type == 'tr369' ? 'success' : 'primary' }} me-2"></i>
                                        </div>
                                        <div class="d-flex flex-column justify-content-center">
                                            <h6 class="mb-0 text-sm">{{ $device->serial_number }}</h6>
                                            <p class="text-xs text-secondary mb-0">{{ $device->manufacturer }} {{ $device->model_name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-sm bg-gradient-{{ $device->protocol_type == 'tr369' ? 'success' : 'primary' }}">
                                        {{ $device->protocol_type == 'tr369' ? 'TR-369' : 'TR-069' }}
                                    </span>
                                    @if($device->mtp_type)
                                    <span class="badge badge-sm bg-gradient-{{ $device->mtp_type == 'mqtt' ? 'warning' : 'info' }} ms-1">
                                        {{ strtoupper($device->mtp_type) }}
                                    </span>
                                    @endif
                                </td>
                                <td class="align-middle text-center text-sm">
                                    <span class="badge badge-sm bg-gradient-{{ $device->status == 'online' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($device->status) }}
                                    </span>
                                </td>
                                <td class="align-middle text-center">
                                    <span class="text-secondary text-xs font-weight-bold">{{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Mai' }}</span>
                                </td>
                                <td class="align-middle">
                                    <a href="/acs/devices/{{ $device->id }}" class="text-secondary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="Visualizza dettagli">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-sm text-secondary py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-5"></i>
                                    Nessun dispositivo registrato
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activity Timeline (Soft UI PRO Style) -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header pb-0 p-3">
                <div class="row">
                    <div class="col-md-8 d-flex align-items-center">
                        <h6 class="mb-0">Activity Log</h6>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-history text-secondary"></i>
                    </div>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="activity-timeline">
                    @forelse($stats['recent_tasks'] ?? [] as $index => $task)
                    <div class="activity-item">
                        <div class="activity-dot bg-{{ $task->status == 'completed' ? 'success' : ($task->status == 'failed' ? 'danger' : 'warning') }}"></div>
                        <div class="ms-3">
                            <p class="text-sm font-weight-bold mb-0">{{ ucfirst(str_replace('_', ' ', $task->task_type)) }}</p>
                            <p class="text-xs text-secondary mb-0">{{ $task->cpeDevice->serial_number ?? 'N/A' }}</p>
                            <p class="text-xs text-secondary mb-0">
                                <i class="fas fa-clock me-1"></i>{{ $task->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-sm text-secondary py-4">
                        <i class="fas fa-clock fa-2x mb-2 d-block opacity-5"></i>
                        Nessuna attività recente
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards Row -->
<div class="row mt-4">
    <div class="col-lg-7 mb-lg-0 mb-4">
        <div class="card">
            <div class="card-header pb-0 p-3">
                <div class="d-flex justify-content-between">
                    <h6 class="mb-2">Panoramica Protocolli TR</h6>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-md-6 mb-md-0 mb-4">
                        <div class="card card-body border card-plain border-radius-lg d-flex align-items-center flex-row">
                            <i class="ni ni-mobile-button text-lg opacity-10 text-primary" style="font-size: 2rem;"></i>
                            <h6 class="mb-0 ms-3">TR-069 (CWMP)</h6>
                            <h5 class="font-weight-bolder ms-auto">{{ $stats['devices']['tr069'] ?? 0 }}</h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-body border card-plain border-radius-lg d-flex align-items-center flex-row">
                            <i class="ni ni-satisfied text-lg opacity-10 text-success" style="font-size: 2rem;"></i>
                            <h6 class="mb-0 ms-3">TR-369 (USP)</h6>
                            <h5 class="font-weight-bolder ms-auto">{{ $stats['devices']['tr369'] ?? 0 }}</h5>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6 mb-md-0 mb-4">
                        <div class="card card-body border card-plain border-radius-lg d-flex align-items-center flex-row">
                            <i class="ni ni-send text-lg opacity-10 text-warning" style="font-size: 2rem;"></i>
                            <h6 class="mb-0 ms-3">MQTT Transport</h6>
                            <h5 class="font-weight-bolder ms-auto">{{ $stats['devices']['tr369_mqtt'] ?? 0 }}</h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-body border card-plain border-radius-lg d-flex align-items-center flex-row">
                            <i class="ni ni-world-2 text-lg opacity-10 text-info" style="font-size: 2rem;"></i>
                            <h6 class="mb-0 ms-3">HTTP Transport</h6>
                            <h5 class="font-weight-bolder ms-auto">{{ $stats['devices']['tr369_http'] ?? 0 }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header pb-0 p-3">
                <h6 class="mb-0">Stato Dispositivi</h6>
            </div>
            <div class="card-body p-3">
                <canvas id="deviceStatusChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row (Soft UI PRO Style) -->
<div class="row mt-4">
    <div class="col-lg-6 mb-lg-0 mb-4">
        <div class="card z-index-2">
            <div class="card-header pb-0">
                <h6>Task Provisioning</h6>
                <p class="text-sm">
                    <i class="fa fa-arrow-up text-success"></i>
                    <span class="font-weight-bold">{{ $stats['tasks']['completed'] ?? 0 }} completati</span> questo mese
                </p>
            </div>
            <div class="card-body p-3">
                <div class="chart">
                    <canvas id="taskChart" class="chart-canvas" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card z-index-2">
            <div class="card-header pb-0">
                <h6>Firmware Deployments</h6>
                <p class="text-sm">
                    <i class="fa fa-check text-success"></i>
                    <span class="font-weight-bold">{{ round((($stats['firmware']['completed'] ?? 0) / max($stats['firmware']['total_deployments'] ?? 1, 1)) * 100) }}%</span> tasso di successo
                </p>
            </div>
            <div class="card-body p-3">
                <div class="chart">
                    <canvas id="firmwareChart" class="chart-canvas" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Diagnostics Overview -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <div class="row">
                    <div class="col-lg-6">
                        <h6>Test Diagnostici TR-143</h6>
                        <p class="text-sm mb-0">
                            Panoramica completa dei test eseguiti per tipo
                        </p>
                    </div>
                    <div class="col-lg-6 text-end">
                        <a href="{{ route('acs.alarms') }}" class="btn btn-sm btn-outline-primary mb-0">
                            <i class="fas fa-bell me-1"></i> Vedi Allarmi
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="border-dashed text-center border-radius-md py-3">
                            <h2 class="text-gradient text-primary">
                                <i class="fas fa-network-wired"></i>
                                <span class="text-lg ms-2">{{ $stats['diagnostics']['by_type']['ping'] ?? 0 }}</span>
                            </h2>
                            <h6 class="mb-0 font-weight-bolder">Ping Tests</h6>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="border-dashed text-center border-radius-md py-3">
                            <h2 class="text-gradient text-info">
                                <i class="fas fa-route"></i>
                                <span class="text-lg ms-2">{{ $stats['diagnostics']['by_type']['traceroute'] ?? 0 }}</span>
                            </h2>
                            <h6 class="mb-0 font-weight-bolder">Traceroute</h6>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="border-dashed text-center border-radius-md py-3">
                            <h2 class="text-gradient text-success">
                                <i class="fas fa-download"></i>
                                <span class="text-lg ms-2">{{ $stats['diagnostics']['by_type']['download'] ?? 0 }}</span>
                            </h2>
                            <h6 class="mb-0 font-weight-bolder">Download</h6>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="border-dashed text-center border-radius-md py-3">
                            <h2 class="text-gradient text-warning">
                                <i class="fas fa-upload"></i>
                                <span class="text-lg ms-2">{{ $stats['diagnostics']['by_type']['upload'] ?? 0 }}</span>
                            </h2>
                            <h6 class="mb-0 font-weight-bolder">Upload</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Mini Sparkline Charts Configuration
const miniChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false }
    },
    scales: {
        x: { display: false },
        y: { display: false }
    },
    elements: {
        point: { radius: 0 },
        line: { tension: 0.4, borderWidth: 2 }
    }
};

// Mini Devices Chart (Primary Gradient)
const miniDevicesCtx = document.getElementById('miniDevicesChart').getContext('2d');
const miniDevicesGradient = miniDevicesCtx.createLinearGradient(0, 0, 0, 50);
miniDevicesGradient.addColorStop(0, 'rgba(203, 12, 159, 0.3)');
miniDevicesGradient.addColorStop(1, 'rgba(203, 12, 159, 0)');

new Chart(miniDevicesCtx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            data: [0, 0, 0, 0, 0, {{ $stats['devices']['online'] ?? 0 }}, {{ $stats['devices']['total'] ?? 0 }}],
            borderColor: '#cb0c9f',
            backgroundColor: miniDevicesGradient,
            fill: true
        }]
    },
    options: miniChartOptions
});

// Mini Tasks Chart (Warning Gradient)
const miniTasksCtx = document.getElementById('miniTasksChart').getContext('2d');
const miniTasksGradient = miniTasksCtx.createLinearGradient(0, 0, 0, 50);
miniTasksGradient.addColorStop(0, 'rgba(251, 207, 51, 0.3)');
miniTasksGradient.addColorStop(1, 'rgba(251, 207, 51, 0)');

new Chart(miniTasksCtx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            data: [
                {{ $stats['tasks']['failed'] ?? 0 }}, 
                {{ $stats['tasks']['processing'] ?? 0 }}, 
                {{ $stats['tasks']['completed'] ?? 0 }}, 
                {{ $stats['tasks']['pending'] ?? 0 }},
                {{ $stats['tasks']['pending'] ?? 0 }},
                {{ $stats['tasks']['pending'] ?? 0 }},
                {{ $stats['tasks']['pending'] ?? 0 }}
            ],
            borderColor: '#fbcf33',
            backgroundColor: miniTasksGradient,
            fill: true
        }]
    },
    options: miniChartOptions
});

// Mini Firmware Chart (Success Gradient)
const miniFirmwareCtx = document.getElementById('miniFirmwareChart').getContext('2d');
const miniFirmwareGradient = miniFirmwareCtx.createLinearGradient(0, 0, 0, 50);
miniFirmwareGradient.addColorStop(0, 'rgba(130, 214, 22, 0.3)');
miniFirmwareGradient.addColorStop(1, 'rgba(130, 214, 22, 0)');

new Chart(miniFirmwareCtx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            data: [
                {{ $stats['firmware']['scheduled'] ?? 0 }},
                {{ $stats['firmware']['downloading'] ?? 0 }},
                {{ $stats['firmware']['installing'] ?? 0 }},
                {{ $stats['firmware']['completed'] ?? 0 }},
                {{ $stats['firmware']['completed'] ?? 0 }},
                {{ $stats['firmware']['completed'] ?? 0 }},
                {{ $stats['firmware']['completed'] ?? 0 }}
            ],
            borderColor: '#82d616',
            backgroundColor: miniFirmwareGradient,
            fill: true
        }]
    },
    options: miniChartOptions
});

// Mini Diagnostics Chart (Info Gradient)
const miniDiagnosticsCtx = document.getElementById('miniDiagnosticsChart').getContext('2d');
const miniDiagnosticsGradient = miniDiagnosticsCtx.createLinearGradient(0, 0, 0, 50);
miniDiagnosticsGradient.addColorStop(0, 'rgba(23, 193, 232, 0.3)');
miniDiagnosticsGradient.addColorStop(1, 'rgba(23, 193, 232, 0)');

new Chart(miniDiagnosticsCtx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            data: [
                {{ $stats['diagnostics']['by_type']['ping'] ?? 0 }},
                {{ $stats['diagnostics']['by_type']['traceroute'] ?? 0 }},
                {{ $stats['diagnostics']['by_type']['download'] ?? 0 }},
                {{ $stats['diagnostics']['by_type']['upload'] ?? 0 }},
                0, 0, 0
            ],
            borderColor: '#17c1e8',
            backgroundColor: miniDiagnosticsGradient,
            fill: true
        }]
    },
    options: miniChartOptions
});

// Soft UI Dashboard PRO Chart Configuration
const gradientChartOptionsConfiguration = {
    maintainAspectRatio: false,
    responsive: true,
    plugins: {
        legend: {
            display: false,
        }
    },
    interaction: {
        intersect: false,
        mode: 'index',
    },
    scales: {
        y: {
            grid: {
                drawBorder: false,
                display: true,
                drawOnChartArea: true,
                drawTicks: false,
                borderDash: [5, 5]
            },
            ticks: {
                display: true,
                padding: 10,
                color: '#b2b9bf',
                font: {
                    size: 11,
                    family: "Open Sans",
                    style: 'normal',
                    lineHeight: 2
                },
            }
        },
        x: {
            grid: {
                drawBorder: false,
                display: false,
                drawOnChartArea: false,
                drawTicks: false,
                borderDash: [5, 5]
            },
            ticks: {
                display: true,
                color: '#b2b9bf',
                padding: 20,
                font: {
                    size: 11,
                    family: "Open Sans",
                    style: 'normal',
                    lineHeight: 2
                },
            }
        },
    },
};

// Device Status Chart (Doughnut with gradient)
var ctx1 = document.getElementById("deviceStatusChart").getContext("2d");

var gradientStroke1 = ctx1.createLinearGradient(0, 230, 0, 50);
gradientStroke1.addColorStop(1, 'rgba(203, 12, 159, 0.2)');
gradientStroke1.addColorStop(0.2, 'rgba(72, 72, 176, 0.0)');
gradientStroke1.addColorStop(0, 'rgba(203, 12, 159, 0)');

new Chart(ctx1, {
    type: "doughnut",
    data: {
        labels: ['Online', 'Offline', 'Provisioning', 'Error'],
        datasets: [{
            label: "Dispositivi",
            weight: 9,
            cutout: 60,
            tension: 0.9,
            pointRadius: 2,
            borderWidth: 2,
            backgroundColor: ['#82d616', '#8392ab', '#fbcf33', '#ea0606'],
            data: [
                {{ $stats['devices']['online'] ?? 0 }},
                {{ $stats['devices']['offline'] ?? 0 }},
                {{ $stats['devices']['provisioning'] ?? 0 }},
                {{ $stats['devices']['error'] ?? 0 }}
            ],
            fill: false
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
            }
        },
        interaction: {
            intersect: false,
            mode: 'index',
        },
    },
});

// Task Chart (Bar with gradient)
var ctx2 = document.getElementById("taskChart").getContext("2d");

var gradientStroke2 = ctx2.createLinearGradient(0, 230, 0, 50);
gradientStroke2.addColorStop(1, 'rgba(251, 207, 51, 0.5)');
gradientStroke2.addColorStop(0.2, 'rgba(251, 207, 51, 0.1)');
gradientStroke2.addColorStop(0, 'rgba(251, 207, 51, 0)');

new Chart(ctx2, {
    type: "bar",
    data: {
        labels: ["Pending", "Processing", "Completed", "Failed"],
        datasets: [{
            label: "Tasks",
            tension: 0.4,
            borderWidth: 0,
            borderRadius: 4,
            borderSkipped: false,
            backgroundColor: gradientStroke2,
            data: [
                {{ $stats['tasks']['pending'] ?? 0 }},
                {{ $stats['tasks']['processing'] ?? 0 }},
                {{ $stats['tasks']['completed'] ?? 0 }},
                {{ $stats['tasks']['failed'] ?? 0 }}
            ],
            maxBarThickness: 50
        }],
    },
    options: {
        ...gradientChartOptionsConfiguration,
        scales: {
            ...gradientChartOptionsConfiguration.scales,
            y: {
                ...gradientChartOptionsConfiguration.scales.y,
                ticks: {
                    ...gradientChartOptionsConfiguration.scales.y.ticks,
                    precision: 0
                }
            }
        }
    },
});

// Firmware Chart (Line with gradient)
var ctx3 = document.getElementById("firmwareChart").getContext("2d");

var gradientStroke3 = ctx3.createLinearGradient(0, 230, 0, 50);
gradientStroke3.addColorStop(1, 'rgba(23, 193, 232, 0.5)');
gradientStroke3.addColorStop(0.2, 'rgba(72, 72, 176, 0.1)');
gradientStroke3.addColorStop(0, 'rgba(203, 12, 159, 0)');

new Chart(ctx3, {
    type: "line",
    data: {
        labels: ["Scheduled", "Downloading", "Installing", "Completed", "Failed"],
        datasets: [{
            label: "Firmware",
            tension: 0.4,
            borderWidth: 0,
            pointRadius: 0,
            borderColor: "#17c1e8",
            borderWidth: 3,
            backgroundColor: gradientStroke3,
            fill: true,
            data: [
                {{ $stats['firmware']['scheduled'] ?? 0 }},
                {{ $stats['firmware']['downloading'] ?? 0 }},
                {{ $stats['firmware']['installing'] ?? 0 }},
                {{ $stats['firmware']['completed'] ?? 0 }},
                {{ $stats['firmware']['failed'] ?? 0 }}
            ],
            maxBarThickness: 6
        }],
    },
    options: gradientChartOptionsConfiguration,
});
</script>
@endpush
@endsection
