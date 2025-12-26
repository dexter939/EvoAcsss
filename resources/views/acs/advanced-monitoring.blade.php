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
                                    <i class="fas fa-chart-line text-success me-2"></i>
                                    Advanced Monitoring & Alerting System
                                </h5>
                                <p class="text-sm text-muted mb-0">Real-time system monitoring, alert rules, and notifications</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createRuleModal">
                                    <i class="fas fa-plus me-1"></i> New Alert Rule
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Alerts (24h)</p>
                                    <h5 class="font-weight-bolder" id="statTotalAlerts">0</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-danger font-weight-bold" id="statCriticalAlerts">0 critical</span>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Active Rules</p>
                                    <h5 class="font-weight-bolder" id="statActiveRules">0</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-success font-weight-bold">Monitoring</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                    <i class="fas fa-shield-alt text-lg opacity-10" aria-hidden="true"></i>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Pending Alerts</p>
                                    <h5 class="font-weight-bolder" id="statPendingAlerts">0</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-warning font-weight-bold">In queue</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                    <i class="fas fa-clock text-lg opacity-10" aria-hidden="true"></i>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Failed Alerts</p>
                                    <h5 class="font-weight-bolder" id="statFailedAlerts">0</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-danger font-weight-bold">Requires attention</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle">
                                    <i class="fas fa-times-circle text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="card h-100">
                    <div class="card-header pb-0">
                        <h6>System Metrics Trends (Last 24 Hours)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="metricsChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header pb-0">
                        <h6>Alert Distribution by Severity</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="severityChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <h6>Active Alert Rules</h6>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        <div class="table-responsive p-0">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Rule Name</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Metric</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Condition</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Severity</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Channels</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Triggers</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Status</th>
                                        <th class="text-secondary opacity-7"></th>
                                    </tr>
                                </thead>
                                <tbody id="alertRulesList">
                                    <tr>
                                        <td colspan="8" class="text-center text-sm text-muted">Loading alert rules...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <h6>Recent Alert Notifications</h6>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        <div class="table-responsive p-0">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Time</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Title</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Message</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Severity</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Channel</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="alertNotificationsList">
                                    <tr>
                                        <td colspan="6" class="text-center text-sm text-muted">Loading notifications...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createRuleModal" tabindex="-1" aria-labelledby="createRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createRuleModalLabel">Create New Alert Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createRuleForm">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Rule Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Metric</label>
                                <select class="form-control" name="metric" required>
                                    <option value="cpu_usage">CPU Usage</option>
                                    <option value="memory_usage">Memory Usage</option>
                                    <option value="disk_usage">Disk Usage</option>
                                    <option value="avg_query_time">Avg Query Time</option>
                                    <option value="devices_offline">Devices Offline</option>
                                    <option value="failed_jobs">Failed Jobs</option>
                                    <option value="cache_hit_rate">Cache Hit Rate</option>
                                    <option value="active_alarms">Active Alarms</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Condition</label>
                                <select class="form-control" name="condition" required>
                                    <option value=">">Greater than (>)</option>
                                    <option value="<">Less than (<)</option>
                                    <option value=">=">Greater or equal (>=)</option>
                                    <option value="<=">Less or equal (<=)</option>
                                    <option value="=">Equal (=)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Threshold Value</label>
                                <input type="number" class="form-control" name="threshold_value" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Severity</label>
                                <select class="form-control" name="severity" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" name="duration_minutes" value="5" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Notification Channels</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="channelEmail">
                                        <label class="form-check-label" for="channelEmail">Email</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="webhook" id="channelWebhook">
                                        <label class="form-check-label" for="channelWebhook">Webhook</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="slack" id="channelSlack">
                                        <label class="form-check-label" for="channelSlack">Slack</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Recipients (comma-separated emails/URLs)</label>
                                <textarea class="form-control" name="recipients" rows="2" placeholder="admin@example.com, https://hooks.slack.com/services/..."></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAlertRule()">Create Rule</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        let metricsChart, severityChart;

        $(document).ready(function() {
            initializeCharts();
            loadMonitoringData();
            
            setInterval(loadMonitoringData, 30000);
        });

        function initializeCharts() {
            const ctx1 = document.getElementById('metricsChart');
            metricsChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'CPU Usage',
                        data: [],
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Memory Usage',
                        data: [],
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            const ctx2 = document.getElementById('severityChart');
            severityChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Critical', 'High', 'Medium', 'Low'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: [
                            'rgb(239, 68, 68)',
                            'rgb(251, 146, 60)',
                            'rgb(251, 191, 36)',
                            'rgb(34, 197, 94)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function loadMonitoringData() {
            $.ajax({
                url: '{{ route("acs.advanced-monitoring.data") }}',
                method: 'GET',
                success: function(data) {
                    updateStatistics(data.statistics);
                    updateAlertRules(data.rules);
                    updateNotifications(data.notifications);
                }
            });
        }

        function updateStatistics(stats) {
            $('#statTotalAlerts').text(stats.last_24h_alerts || 0);
            $('#statCriticalAlerts').text((stats.critical_alerts || 0) + ' critical');
            $('#statActiveRules').text(stats.active_rules || 0);
            $('#statPendingAlerts').text(stats.pending_alerts || 0);
            $('#statFailedAlerts').text(stats.failed_alerts || 0);
        }

        function updateAlertRules(rules) {
            if (!rules || rules.length === 0) {
                $('#alertRulesList').html('<tr><td colspan="8" class="text-center text-sm text-muted">No alert rules configured</td></tr>');
                return;
            }
            
            const html = rules.map(rule => `
                <tr>
                    <td><span class="text-xs font-weight-bold">${rule.name}</span></td>
                    <td><span class="text-xs">${rule.metric}</span></td>
                    <td class="text-center"><span class="text-xs">${rule.condition} ${rule.threshold_value}</span></td>
                    <td class="text-center">
                        <span class="badge badge-sm bg-gradient-${getSeverityColor(rule.severity)}">${rule.severity}</span>
                    </td>
                    <td class="text-center"><span class="text-xs">${rule.notification_channels.join(', ')}</span></td>
                    <td class="text-center"><span class="text-xs">${rule.trigger_count}</span></td>
                    <td class="text-center">
                        <span class="badge badge-sm bg-gradient-${rule.is_active ? 'success' : 'secondary'}">${rule.is_active ? 'Active' : 'Inactive'}</span>
                    </td>
                    <td>
                        <button class="btn btn-link text-danger p-0" onclick="deleteRule(${rule.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
            
            $('#alertRulesList').html(html);
        }

        function updateNotifications(notifications) {
            if (!notifications || notifications.length === 0) {
                $('#alertNotificationsList').html('<tr><td colspan="6" class="text-center text-sm text-muted">No recent notifications</td></tr>');
                return;
            }
            
            const html = notifications.map(notif => `
                <tr>
                    <td><span class="text-xs">${formatDate(notif.created_at)}</span></td>
                    <td><span class="text-xs font-weight-bold">${notif.title}</span></td>
                    <td><span class="text-xs">${truncate(notif.message, 60)}</span></td>
                    <td class="text-center">
                        <span class="badge badge-sm bg-gradient-${getSeverityColor(notif.severity)}">${notif.severity}</span>
                    </td>
                    <td class="text-center"><span class="text-xs">${notif.notification_channel}</span></td>
                    <td class="text-center">
                        <span class="badge badge-sm bg-gradient-${getStatusColor(notif.status)}">${notif.status}</span>
                    </td>
                </tr>
            `).join('');
            
            $('#alertNotificationsList').html(html);
        }

        function getSeverityColor(severity) {
            const colors = {
                'critical': 'danger',
                'high': 'warning',
                'medium': 'info',
                'low': 'success'
            };
            return colors[severity] || 'secondary';
        }

        function getStatusColor(status) {
            const colors = {
                'sent': 'success',
                'pending': 'warning',
                'failed': 'danger'
            };
            return colors[status] || 'secondary';
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString();
        }

        function truncate(str, length) {
            return str.length > length ? str.substring(0, length) + '...' : str;
        }

        function saveAlertRule() {
            const formData = new FormData($('#createRuleForm')[0]);
            
            $.ajax({
                url: '{{ route("acs.advanced-monitoring.create-rule") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#createRuleModal').modal('hide');
                    $('#createRuleForm')[0].reset();
                    loadMonitoringData();
                    toastr.success('Alert rule created successfully');
                },
                error: function(xhr) {
                    toastr.error('Failed to create alert rule');
                }
            });
        }

        function deleteRule(ruleId) {
            if (confirm('Are you sure you want to delete this alert rule?')) {
                $.ajax({
                    url: `/acs/advanced-monitoring/rules/${ruleId}`,
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function() {
                        loadMonitoringData();
                        toastr.success('Alert rule deleted successfully');
                    },
                    error: function() {
                        toastr.error('Failed to delete alert rule');
                    }
                });
            }
        }
    </script>
@endsection
