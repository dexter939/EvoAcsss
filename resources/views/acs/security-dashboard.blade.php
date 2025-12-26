@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-shield-alt text-danger me-2"></i>
                                    Security Hardening Dashboard
                                </h5>
                                <p class="text-sm text-muted mb-0">Real-time threat monitoring, security events, and IP blacklist management</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#blockIpModal">
                                    <i class="fas fa-ban me-1"></i> Block IP
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-3 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Security Events (24h)</p>
                                    <h5 class="font-weight-bolder" id="statTotalEvents">{{ $stats['total_events_24h'] ?? 0 }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-danger font-weight-bold" id="statCriticalEvents">{{ $stats['critical_events_24h'] ?? 0 }} critical</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                    <i class="fas fa-exclamation-triangle text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Blocked IPs</p>
                                    <h5 class="font-weight-bolder" id="statBlockedIps">{{ $stats['active_blacklisted_ips'] ?? 0 }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-warning font-weight-bold">Active blocks</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                    <i class="fas fa-ban text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Rate Limit Violations</p>
                                    <h5 class="font-weight-bolder" id="statRateLimits">{{ $stats['rate_limit_violations_24h'] ?? 0 }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-info font-weight-bold">Last 24h</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle">
                                    <i class="fas fa-tachometer-alt text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Security Health</p>
                                    <h5 class="font-weight-bolder" id="statHealthScore">{{ $health['health_score'] ?? 0 }}%</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-success font-weight-bold text-uppercase" id="statHealthStatus">{{ $health['status'] ?? 'good' }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                    <i class="fas fa-heartbeat text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-8 mb-lg-0 mb-4">
                <div class="card h-100">
                    <div class="card-header pb-0">
                        <h6>Security Events Trend (Last 7 Days)</h6>
                    </div>
                    <div class="card-body p-3">
                        <canvas id="securityTrendChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header pb-0">
                        <h6>Event Distribution by Type</h6>
                    </div>
                    <div class="card-body p-3">
                        <canvas id="eventTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6 mb-lg-0 mb-4">
                <div class="card h-100">
                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                        <h6>Top Threats & Blocked IPs</h6>
                        <span class="badge badge-sm bg-gradient-danger" id="threatCount">0</span>
                    </div>
                    <div class="card-body px-0 pb-2">
                        <div class="table-responsive">
                            <table class="table align-items-center mb-0" id="threatsTable">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">IP Address</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Violations</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Reason</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Last Activity</th>
                                        <th class="text-secondary opacity-7"></th>
                                    </tr>
                                </thead>
                                <tbody id="threatsTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center text-sm text-muted py-4">
                                            <i class="fas fa-spinner fa-spin me-2"></i> Loading threats...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                        <h6>Recent Security Events</h6>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshSecurityData()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body px-0 pb-2">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table align-items-center mb-0">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Event</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Severity</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">IP</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Time</th>
                                    </tr>
                                </thead>
                                <tbody id="eventsTableBody">
                                    <tr>
                                        <td colspan="4" class="text-center text-sm text-muted py-4">
                                            <i class="fas fa-spinner fa-spin me-2"></i> Loading events...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($health['issues']) && count($health['issues']) > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span class="alert-text"><strong>Security Issues Detected:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach($health['issues'] as $issue)
                            <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2 mb-0"><strong>Recommendation:</strong> {{ $health['recommendation'] }}</p>
                    </span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="modal fade" id="blockIpModal" tabindex="-1" role="dialog" aria-labelledby="blockIpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="blockIpModalLabel">Block IP Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="blockIpForm">
                        <div class="mb-3">
                            <label for="ipAddress" class="form-label">IP Address</label>
                            <input type="text" class="form-control" id="ipAddress" name="ip_address" placeholder="192.168.1.100" required>
                        </div>
                        <div class="mb-3">
                            <label for="blockReason" class="form-label">Reason</label>
                            <input type="text" class="form-control" id="blockReason" name="reason" placeholder="Suspicious activity detected" required>
                        </div>
                        <div class="mb-3">
                            <label for="blockDuration" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="blockDuration" name="duration_minutes" placeholder="Leave empty for permanent block">
                            <small class="form-text text-muted">Leave empty for permanent block</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="blockIpAddress()">Block IP</button>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.footers.footer')
@endsection

@push('js')
<script>
let securityTrendChart, eventTypeChart;

document.addEventListener('DOMContentLoaded', function() {
    loadSecurityData();
    setInterval(refreshSecurityData, 30000);
});

function loadSecurityData() {
    fetch('{{ route('acs.security.data') }}')
        .then(response => response.json())
        .then(data => {
            updateStatistics(data.stats);
            updateThreatsList(data.top_threats);
            updateEventsList(data.recent_events);
            renderSecurityTrendChart(data.trends);
            renderEventTypeChart(data.event_distribution);
        })
        .catch(error => console.error('Error loading security data:', error));
}

function refreshSecurityData() {
    loadSecurityData();
}

function updateStatistics(stats) {
    document.getElementById('statTotalEvents').textContent = stats.total_events_24h || 0;
    document.getElementById('statCriticalEvents').textContent = (stats.critical_events_24h || 0) + ' critical';
    document.getElementById('statBlockedIps').textContent = stats.active_blacklisted_ips || 0;
    document.getElementById('statRateLimits').textContent = stats.rate_limit_violations_24h || 0;
}

function updateThreatsList(threats) {
    const tbody = document.getElementById('threatsTableBody');
    document.getElementById('threatCount').textContent = threats.length;
    
    if (threats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-sm text-muted py-4">No active threats</td></tr>';
        return;
    }
    
    tbody.innerHTML = threats.map(threat => `
        <tr>
            <td>
                <p class="text-xs font-weight-bold mb-0">${threat.ip_address}</p>
            </td>
            <td>
                <span class="badge badge-sm bg-gradient-danger">${threat.violation_count}</span>
            </td>
            <td>
                <p class="text-xs text-secondary mb-0">${threat.reason}</p>
            </td>
            <td>
                <p class="text-xs text-secondary mb-0">${new Date(threat.last_violation_at).toLocaleString()}</p>
            </td>
            <td class="align-middle text-center">
                <button class="btn btn-sm btn-success" onclick="unblockIp('${threat.ip_address}')">
                    <i class="fas fa-check"></i> Unblock
                </button>
            </td>
        </tr>
    `).join('');
}

function updateEventsList(events) {
    const tbody = document.getElementById('eventsTableBody');
    
    if (events.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-sm text-muted py-4">No recent events</td></tr>';
        return;
    }
    
    const severityColors = {
        'critical': 'danger',
        'warning': 'warning',
        'info': 'info'
    };
    
    tbody.innerHTML = events.map(event => `
        <tr>
            <td>
                <p class="text-xs mb-0">${event.event_type.replace(/_/g, ' ').toUpperCase()}</p>
                <p class="text-xxs text-secondary mb-0">${event.description || ''}</p>
            </td>
            <td>
                <span class="badge badge-sm bg-gradient-${severityColors[event.severity] || 'secondary'}">${event.severity}</span>
            </td>
            <td>
                <p class="text-xs mb-0">${event.ip_address || 'N/A'}</p>
            </td>
            <td>
                <p class="text-xs text-secondary mb-0">${new Date(event.created_at).toLocaleTimeString()}</p>
            </td>
        </tr>
    `).join('');
}

function renderSecurityTrendChart(trends) {
    const ctx = document.getElementById('securityTrendChart').getContext('2d');
    
    if (securityTrendChart) {
        securityTrendChart.destroy();
    }
    
    securityTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: trends.labels || [],
            datasets: [
                {
                    label: 'Total Events',
                    data: trends.total || [],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                },
                {
                    label: 'Critical Events',
                    data: trends.critical || [],
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                },
                {
                    label: 'Blocked Attempts',
                    data: trends.blocked || [],
                    borderColor: 'rgb(255, 159, 64)',
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
}

function renderEventTypeChart(distribution) {
    const ctx = document.getElementById('eventTypeChart').getContext('2d');
    
    if (eventTypeChart) {
        eventTypeChart.destroy();
    }
    
    const types = Object.keys(distribution);
    const counts = Object.values(distribution);
    
    eventTypeChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: types.map(t => t.replace(/_/g, ' ').toUpperCase()),
            datasets: [{
                data: counts,
                backgroundColor: [
                    'rgb(255, 99, 132)',
                    'rgb(255, 159, 64)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)',
                    'rgb(54, 162, 235)',
                    'rgb(153, 102, 255)',
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
}

function blockIpAddress() {
    const form = document.getElementById('blockIpForm');
    const formData = new FormData(form);
    
    fetch('{{ route('acs.security.block-ip') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(Object.fromEntries(formData))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('blockIpModal')).hide();
            form.reset();
            loadSecurityData();
            alert('IP address blocked successfully');
        }
    })
    .catch(error => console.error('Error blocking IP:', error));
}

function unblockIp(ipAddress) {
    if (!confirm(`Unblock IP address ${ipAddress}?`)) {
        return;
    }
    
    fetch('{{ route('acs.security.unblock-ip') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ip_address: ipAddress })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadSecurityData();
            alert('IP address unblocked successfully');
        }
    })
    .catch(error => console.error('Error unblocking IP:', error));
}
</script>
@endpush
