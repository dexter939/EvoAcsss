@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-lg-3 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Tasks</p>
                                    <h5 class="font-weight-bolder" id="statTotalTasks">0</h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                    <i class="ni ni-settings text-lg opacity-10" aria-hidden="true"></i>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Completed</p>
                                    <h5 class="font-weight-bolder text-success" id="statCompleted">0</h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                    <i class="ni ni-check-bold text-lg opacity-10" aria-hidden="true"></i>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Pending</p>
                                    <h5 class="font-weight-bolder text-warning" id="statPending">0</h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                    <i class="ni ni-time-alarm text-lg opacity-10" aria-hidden="true"></i>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Failed</p>
                                    <h5 class="font-weight-bolder text-danger" id="statFailed">0</h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                    <i class="ni ni-fat-remove text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <ul class="nav nav-pills mb-3" id="provisioningTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="bulk-tab" data-bs-toggle="pill" data-bs-target="#bulk" type="button" role="tab">
                            <i class="fas fa-layer-group me-2"></i>Bulk Provisioning
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="scheduled-tab" data-bs-toggle="pill" data-bs-target="#scheduled" type="button" role="tab">
                            <i class="fas fa-calendar-alt me-2"></i>Scheduled
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="templates-tab" data-bs-toggle="pill" data-bs-target="#templates" type="button" role="tab">
                            <i class="fas fa-copy me-2"></i>Templates
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="conditions-tab" data-bs-toggle="pill" data-bs-target="#conditions" type="button" role="tab">
                            <i class="fas fa-code-branch me-2"></i>Conditional Rules
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button" role="tab">
                            <i class="fas fa-history me-2"></i>History & Rollback
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="analytics-tab" data-bs-toggle="pill" data-bs-target="#analytics" type="button" role="tab">
                            <i class="fas fa-chart-bar me-2"></i>Analytics
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="tab-content" id="provisioningTabsContent">
            <div class="tab-pane fade show active" id="bulk" role="tabpanel">
                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header pb-0">
                                <h6>
                                    <i class="fas fa-filter me-2 text-primary"></i>
                                    Device Selection
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label text-sm">Filter Devices</label>
                                    <select class="form-select form-select-sm" id="deviceFilterType">
                                        <option value="all">All Devices</option>
                                        <option value="status">By Status</option>
                                        <option value="manufacturer">By Manufacturer</option>
                                        <option value="model">By Model</option>
                                        <option value="firmware">By Firmware Version</option>
                                        <option value="service">By Service</option>
                                        <option value="custom">Custom Query</option>
                                    </select>
                                </div>
                                
                                <div id="filterOptions" class="mb-3" style="display: none;"></div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-sm">Available Devices</label>
                                    <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;" id="deviceList">
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-spinner fa-spin"></i> Loading devices...
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info text-xs mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span id="selectedCount">0</span> devices selected
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header pb-0">
                                <h6>
                                    <i class="fas fa-cog me-2 text-success"></i>
                                    Configuration to Apply
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-sm">Configuration Source</label>
                                        <select class="form-select form-select-sm" id="configSource">
                                            <option value="profile">Use Existing Profile</option>
                                            <option value="template">Use Template</option>
                                            <option value="custom">Custom Parameters</option>
                                            <option value="ai">AI-Generated Configuration</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-sm">Execution Mode</label>
                                        <select class="form-select form-select-sm" id="executionMode">
                                            <option value="immediate">Immediate</option>
                                            <option value="scheduled">Scheduled</option>
                                            <option value="staged">Staged Rollout</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div id="configSourceOptions" class="mb-3"></div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-sm">Pre-flight Checks</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="checkDeviceOnline" checked>
                                        <label class="form-check-label text-xs">Verify device is online</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="checkDataModel" checked>
                                        <label class="form-check-label text-xs">Validate data model compatibility</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="checkBackup">
                                        <label class="form-check-label text-xs">Backup current configuration</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enableRollback">
                                        <label class="form-check-label text-xs">Enable automatic rollback on failure</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-sm">Rollout Strategy (for Staged mode)</label>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label text-xs">Batch Size (%)</label>
                                            <input type="number" class="form-control form-control-sm" id="batchSize" value="10" min="1" max="100">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-xs">Wait Between Batches (min)</label>
                                            <input type="number" class="form-control form-control-sm" id="batchDelay" value="5" min="1" max="1440">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-xs">Success Threshold (%)</label>
                                            <input type="number" class="form-control form-control-sm" id="successThreshold" value="95" min="50" max="100">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="validateConfiguration()">
                                        <i class="fas fa-check-circle me-1"></i>Validate
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="executeBulkProvisioning()">
                                        <i class="fas fa-play me-1"></i>Execute Provisioning
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="scheduled" role="tabpanel">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6>
                                <i class="fas fa-calendar-check me-2 text-info"></i>
                                Scheduled Provisioning Tasks
                            </h6>
                            <button class="btn btn-sm btn-primary" onclick="showScheduleModal()">
                                <i class="fas fa-plus me-1"></i>Schedule New Task
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="scheduledTasksList"></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="templates" role="tabpanel">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6>
                                <i class="fas fa-box me-2 text-warning"></i>
                                Configuration Templates Library
                            </h6>
                            <button class="btn btn-sm btn-primary" onclick="showCreateTemplateModal()">
                                <i class="fas fa-plus me-1"></i>Create Template
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-sm">Category</label>
                                <select class="form-select form-select-sm" id="templateCategory" onchange="filterTemplates()">
                                    <option value="all">All Categories</option>
                                    <option value="wifi">WiFi Configuration</option>
                                    <option value="voip">VoIP/SIP</option>
                                    <option value="security">Security & Firewall</option>
                                    <option value="qos">QoS & Traffic Management</option>
                                    <option value="wan">WAN Configuration</option>
                                    <option value="lan">LAN & DHCP</option>
                                    <option value="parental">Parental Control</option>
                                    <option value="diagnostics">Diagnostics</option>
                                </select>
                            </div>
                        </div>
                        <div id="templatesList" class="row"></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="conditions" role="tabpanel">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6>
                                <i class="fas fa-sitemap me-2 text-success"></i>
                                Conditional Provisioning Rules
                            </h6>
                            <button class="btn btn-sm btn-primary" onclick="showCreateRuleModal()">
                                <i class="fas fa-plus me-1"></i>Create Rule
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-sm text-muted">Define rules to automatically apply configurations based on device characteristics</p>
                        <div id="conditionalRulesList"></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card">
                    <div class="card-header pb-0">
                        <h6>
                            <i class="fas fa-undo me-2 text-danger"></i>
                            Configuration History & Rollback
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-sm">Select Device</label>
                            <select class="form-select form-select-sm" id="historyDeviceSelect" onchange="loadConfigHistory()">
                                <option value="">Select a device...</option>
                            </select>
                        </div>
                        <div id="configHistoryTimeline"></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="analytics" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6>Provisioning Success Rate (Last 30 Days)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="successRateChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6>Top Configuration Templates</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="topTemplatesChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-12 mb-4">
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6>Provisioning Tasks Timeline</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="tasksTimelineChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        let selectedDevices = [];
        
        $(document).ready(function() {
            loadStatistics();
            loadDeviceList();
            loadTemplates();
            initializeCharts();
            
            $('#deviceFilterType').on('change', function() {
                handleFilterTypeChange($(this).val());
            });
            
            $('#configSource').on('change', function() {
                handleConfigSourceChange($(this).val());
            });
        });

        function loadStatistics() {
            $.ajax({
                url: '{{ route("acs.provisioning.statistics") }}',
                method: 'GET',
                success: function(data) {
                    $('#statTotalTasks').text(data.total || 0);
                    $('#statCompleted').text(data.completed || 0);
                    $('#statPending').text(data.pending || 0);
                    $('#statFailed').text(data.failed || 0);
                }
            });
        }

        function loadDeviceList() {
            $.ajax({
                url: '{{ route("acs.devices") }}',
                method: 'GET',
                success: function(devices) {
                    renderDeviceList(devices);
                }
            });
        }

        function renderDeviceList(devices) {
            const html = devices.map(device => `
                <div class="form-check mb-2">
                    <input class="form-check-input device-checkbox" type="checkbox" value="${device.id}" id="device_${device.id}" onchange="updateSelectedCount()">
                    <label class="form-check-label text-xs" for="device_${device.id}">
                        <strong>${device.manufacturer} ${device.model_name}</strong><br>
                        <small class="text-muted">${device.serial_number} - ${device.status}</small>
                    </label>
                </div>
            `).join('');
            
            $('#deviceList').html(html || '<p class="text-muted text-center">No devices available</p>');
        }

        function updateSelectedCount() {
            selectedDevices = $('.device-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            $('#selectedCount').text(selectedDevices.length);
        }

        function handleFilterTypeChange(filterType) {
            const optionsDiv = $('#filterOptions');
            
            if (filterType === 'all') {
                optionsDiv.hide();
                return;
            }
            
            optionsDiv.show();
            
            let html = '';
            switch(filterType) {
                case 'status':
                    html = `<select class="form-select form-select-sm" onchange="applyFilter()">
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                    </select>`;
                    break;
                case 'manufacturer':
                    html = '<input type="text" class="form-control form-control-sm" placeholder="Enter manufacturer name" onchange="applyFilter()">';
                    break;
            }
            
            optionsDiv.html(html);
        }

        function handleConfigSourceChange(source) {
            const optionsDiv = $('#configSourceOptions');
            let html = '';
            
            switch(source) {
                case 'profile':
                    html = `
                        <label class="form-label text-sm">Select Profile</label>
                        <select class="form-select form-select-sm" id="selectedProfile">
                            <option value="">Loading profiles...</option>
                        </select>
                    `;
                    loadProfiles();
                    break;
                case 'template':
                    html = `
                        <label class="form-label text-sm">Select Template</label>
                        <select class="form-select form-select-sm" id="selectedTemplate">
                            <option value="">Loading templates...</option>
                        </select>
                    `;
                    break;
                case 'custom':
                    html = `
                        <label class="form-label text-sm">Custom Parameters (JSON)</label>
                        <textarea class="form-control form-control-sm" id="customParameters" rows="5" placeholder='{"parameter.path": "value"}'></textarea>
                    `;
                    break;
                case 'ai':
                    html = `
                        <label class="form-label text-sm">AI Configuration Request</label>
                        <textarea class="form-control form-control-sm" id="aiRequest" rows="3" placeholder="Describe the configuration you want (e.g., 'Configure WiFi 6 with WPA3 security')"></textarea>
                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="generateAIConfig()">
                            <i class="fas fa-robot me-1"></i>Generate with AI
                        </button>
                    `;
                    break;
            }
            
            optionsDiv.html(html);
        }

        function loadProfiles() {
            $.ajax({
                url: '{{ route("acs.profiles") }}',
                method: 'GET',
                success: function(profiles) {
                    const options = profiles.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
                    $('#selectedProfile').html('<option value="">Select a profile...</option>' + options);
                }
            });
        }

        function loadTemplates() {
            const templates = [
                { id: 1, name: 'WiFi 6 Standard', category: 'wifi', description: 'Standard WiFi 6 configuration' },
                { id: 2, name: 'VoIP Basic Setup', category: 'voip', description: 'Basic VoIP configuration' },
                { id: 3, name: 'Security Hardening', category: 'security', description: 'Enhanced security settings' },
            ];
            
            renderTemplates(templates);
        }

        function renderTemplates(templates) {
            const html = templates.map(t => `
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body p-3">
                            <h6 class="mb-1">${t.name}</h6>
                            <p class="text-xs text-muted mb-2">${t.description}</p>
                            <span class="badge badge-sm bg-gradient-primary">${t.category}</span>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="useTemplate(${t.id})">
                                    <i class="fas fa-download me-1"></i>Use
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
            
            $('#templatesList').html(html);
        }

        function validateConfiguration() {
            Swal.fire({
                title: 'Validating Configuration',
                html: 'Running pre-flight checks...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Validation Passed',
                    html: '<ul class="text-start text-sm"><li>All devices are online</li><li>Data model compatibility verified</li><li>No conflicts detected</li></ul>'
                });
            }, 2000);
        }

        function executeBulkProvisioning() {
            if (selectedDevices.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Devices Selected',
                    text: 'Please select at least one device'
                });
                return;
            }
            
            Swal.fire({
                title: 'Execute Bulk Provisioning?',
                html: `Apply configuration to <strong>${selectedDevices.length}</strong> device(s)?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Execute',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Provisioning Started',
                        text: `${selectedDevices.length} tasks queued for execution`
                    });
                }
            });
        }

        function initializeCharts() {
            const ctx1 = document.getElementById('successRateChart');
            if (ctx1) {
                new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                        datasets: [{
                            label: 'Success Rate %',
                            data: [92, 95, 97, 96],
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        }
    </script>
@endsection
