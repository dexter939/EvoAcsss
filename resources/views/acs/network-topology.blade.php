@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-lg">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-project-diagram text-primary me-2"></i>
                                    Network Topology Map
                                </h5>
                                <p class="text-sm text-muted mb-0">Real-time visualization of connected devices</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-outline-primary" onclick="triggerNetworkScan()">
                                    <i class="fas fa-sync-alt me-1"></i> Scan Network
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="refreshTopology()">
                                    <i class="fas fa-redo me-1"></i> Refresh
                                </button>
                                <div class="form-check form-switch ms-2">
                                    <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                                    <label class="form-check-label text-sm" for="autoRefresh">Auto-refresh</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-sm">Select CPE Device</label>
                                <select class="form-select form-select-sm" id="deviceSelect" onchange="loadTopology()">
                                    <option value="">Loading devices...</option>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label text-sm">Filter by Connection Type</label>
                                <div class="btn-group btn-group-sm" role="group" id="connectionFilters">
                                    <input type="checkbox" class="btn-check" id="filterAll" checked autocomplete="off" onchange="applyFilters()">
                                    <label class="btn btn-outline-secondary" for="filterAll">
                                        <i class="fas fa-globe me-1"></i> All (<span id="countAll">0</span>)
                                    </label>
                                    
                                    <input type="checkbox" class="btn-check" id="filterLan" checked autocomplete="off" onchange="applyFilters()">
                                    <label class="btn btn-outline-info" for="filterLan">
                                        <i class="fas fa-ethernet me-1"></i> LAN (<span id="countLan">0</span>)
                                    </label>
                                    
                                    <input type="checkbox" class="btn-check" id="filterWifi24" checked autocomplete="off" onchange="applyFilters()">
                                    <label class="btn btn-outline-success" for="filterWifi24">
                                        <i class="fas fa-wifi me-1"></i> 2.4GHz (<span id="countWifi24">0</span>)
                                    </label>
                                    
                                    <input type="checkbox" class="btn-check" id="filterWifi5" checked autocomplete="off" onchange="applyFilters()">
                                    <label class="btn btn-outline-warning" for="filterWifi5">
                                        <i class="fas fa-wifi me-1"></i> 5GHz (<span id="countWifi5">0</span>)
                                    </label>
                                    
                                    <input type="checkbox" class="btn-check" id="filterWifi6" checked autocomplete="off" onchange="applyFilters()">
                                    <label class="btn btn-outline-danger" for="filterWifi6">
                                        <i class="fas fa-wifi me-1"></i> 6GHz (<span id="countWifi6">0</span>)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card bg-light border-0">
                                    <div class="card-body p-2">
                                        <div class="row text-center">
                                            <div class="col">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="icon icon-shape icon-xs bg-gradient-primary shadow text-center border-radius-sm me-2">
                                                        <i class="fas fa-network-wired text-white opacity-10"></i>
                                                    </div>
                                                    <span class="text-xs">Gateway</span>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="icon icon-shape icon-xs bg-gradient-info shadow text-center border-radius-sm me-2">
                                                        <i class="fas fa-ethernet text-white opacity-10"></i>
                                                    </div>
                                                    <span class="text-xs">LAN</span>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="icon icon-shape icon-xs bg-gradient-success shadow text-center border-radius-sm me-2">
                                                        <i class="fas fa-wifi text-white opacity-10"></i>
                                                    </div>
                                                    <span class="text-xs">WiFi 2.4GHz</span>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="icon icon-shape icon-xs bg-gradient-warning shadow text-center border-radius-sm me-2">
                                                        <i class="fas fa-wifi text-white opacity-10"></i>
                                                    </div>
                                                    <span class="text-xs">WiFi 5GHz</span>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="icon icon-shape icon-xs bg-gradient-danger shadow text-center border-radius-sm me-2">
                                                        <i class="fas fa-wifi text-white opacity-10"></i>
                                                    </div>
                                                    <span class="text-xs">WiFi 6GHz</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="networkTopology" style="height: 600px; border: 1px solid #dee2e6; border-radius: 0.5rem; background: #f8f9fa;"></div>
                        
                        <div class="mt-3" id="selectedNodeInfo" style="display: none;">
                            <div class="card">
                                <div class="card-header pb-0">
                                    <h6 class="mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <span id="selectedNodeTitle">Device Information</span>
                                    </h6>
                                </div>
                                <div class="card-body pt-2">
                                    <div class="row text-sm" id="selectedNodeDetails"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.6/dist/dist/vis-network.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.6/dist/vis-network.min.js"></script>

    <script>
        let network = null;
        let currentDeviceId = null;
        let autoRefreshInterval = null;
        let allClients = [];
        
        const connectionColors = {
            'lan': '#17a2b8',
            'wifi_2.4ghz': '#28a745',
            'wifi_5ghz': '#ffc107',
            'wifi_6ghz': '#dc3545'
        };

        $(document).ready(function() {
            loadDevices();
            
            $('#autoRefresh').on('change', function() {
                if ($(this).is(':checked')) {
                    startAutoRefresh();
                } else {
                    stopAutoRefresh();
                }
            });
        });

        function loadDevices() {
            $.ajax({
                url: '{{ route("acs.devices") }}',
                method: 'GET',
                success: function(response) {
                    const select = $('#deviceSelect');
                    select.empty();
                    
                    if (response && Array.isArray(response)) {
                        if (response.length === 0) {
                            select.append('<option value="">No devices available</option>');
                        } else {
                            select.append('<option value="">Select a device...</option>');
                            response.forEach(device => {
                                const status = device.status === 'online' ? '🟢' : '🔴';
                                select.append(`<option value="${device.id}">${status} ${device.manufacturer} ${device.model_name} - ${device.serial_number}</option>`);
                            });
                            
                            if (response.length > 0) {
                                select.val(response[0].id);
                                loadTopology();
                            }
                        }
                    }
                },
                error: function() {
                    $('#deviceSelect').html('<option value="">Error loading devices</option>');
                }
            });
        }

        function loadTopology() {
            const deviceId = $('#deviceSelect').val();
            
            if (!deviceId) {
                return;
            }
            
            currentDeviceId = deviceId;
            
            $.ajax({
                url: `/acs/devices/${deviceId}/network-map`,
                method: 'GET',
                success: function(response) {
                    allClients = response.clients || [];
                    updateStats(response.stats);
                    renderTopology(response.device, allClients);
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load network topology'
                    });
                }
            });
        }

        function updateStats(stats) {
            $('#countAll').text(stats.total || 0);
            $('#countLan').text(stats.lan || 0);
            $('#countWifi24').text(stats.wifi_2_4ghz || 0);
            $('#countWifi5').text(stats.wifi_5ghz || 0);
            $('#countWifi6').text(stats.wifi_6ghz || 0);
        }

        function renderTopology(device, clients) {
            const container = document.getElementById('networkTopology');
            
            const nodes = [];
            const edges = [];
            
            nodes.push({
                id: 'gateway',
                label: `${device.manufacturer}\n${device.model_name}`,
                title: `Gateway\nSerial: ${device.serial_number}\nIP: ${device.ip_address || 'N/A'}`,
                shape: 'box',
                color: {
                    background: '#667eea',
                    border: '#5568d3',
                    highlight: {
                        background: '#764ba2',
                        border: '#667eea'
                    }
                },
                font: { color: '#ffffff', size: 14, face: 'Arial' },
                size: 30,
                mass: 3,
                deviceType: 'gateway',
                deviceData: device
            });
            
            clients.forEach((client, index) => {
                const color = connectionColors[client.connection_type] || '#6c757d';
                const signalStrength = client.signal_strength ? ` (${client.signal_strength} dBm)` : '';
                
                nodes.push({
                    id: client.id,
                    label: client.hostname || 'Unknown',
                    title: `${client.hostname || 'Unknown'}\nIP: ${client.ip_address}\nMAC: ${client.mac_address}\nType: ${client.connection_type}${signalStrength}\nLast seen: ${client.last_seen}`,
                    shape: 'dot',
                    color: {
                        background: color,
                        border: color,
                        highlight: {
                            background: color,
                            border: '#000000'
                        }
                    },
                    size: client.connection_type === 'lan' ? 20 : (client.signal_strength ? Math.max(15, 30 + client.signal_strength) : 15),
                    font: { size: 11 },
                    connectionType: client.connection_type,
                    clientData: client
                });
                
                edges.push({
                    from: 'gateway',
                    to: client.id,
                    color: { color: color, opacity: 0.6 },
                    width: client.connection_type === 'lan' ? 3 : 1,
                    smooth: {
                        type: 'curvedCW',
                        roundness: 0.2
                    }
                });
            });
            
            const data = { nodes: nodes, edges: edges };
            
            const options = {
                layout: {
                    improvedLayout: true,
                    hierarchical: false
                },
                physics: {
                    enabled: true,
                    solver: 'forceAtlas2Based',
                    forceAtlas2Based: {
                        gravitationalConstant: -50,
                        centralGravity: 0.01,
                        springLength: 150,
                        springConstant: 0.08
                    },
                    stabilization: {
                        iterations: 150
                    }
                },
                interaction: {
                    hover: true,
                    tooltipDelay: 200,
                    zoomView: true,
                    dragView: true
                },
                nodes: {
                    borderWidth: 2,
                    borderWidthSelected: 3
                },
                edges: {
                    smooth: true,
                    arrows: {
                        to: { enabled: false }
                    }
                }
            };
            
            if (network) {
                network.destroy();
            }
            
            network = new vis.Network(container, data, options);
            
            network.on('click', function(params) {
                if (params.nodes.length > 0) {
                    const nodeId = params.nodes[0];
                    showNodeDetails(nodeId, nodes.find(n => n.id === nodeId));
                } else {
                    hideNodeDetails();
                }
            });
            
            network.once('stabilizationIterationsDone', function() {
                network.setOptions({ physics: false });
            });
        }

        function showNodeDetails(nodeId, node) {
            $('#selectedNodeInfo').slideDown();
            
            if (node.deviceType === 'gateway') {
                $('#selectedNodeTitle').text('Gateway: ' + node.deviceData.manufacturer + ' ' + node.deviceData.model_name);
                $('#selectedNodeDetails').html(`
                    <div class="col-md-3 mb-2">
                        <strong>Serial Number:</strong><br>${node.deviceData.serial_number}
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong>IP Address:</strong><br>${node.deviceData.ip_address || 'N/A'}
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong>Status:</strong><br>
                        <span class="badge badge-sm bg-${node.deviceData.status === 'online' ? 'success' : 'secondary'}">${node.deviceData.status}</span>
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong>Protocol:</strong><br>${node.deviceData.protocol_type.toUpperCase()}
                    </div>
                `);
            } else {
                const client = node.clientData;
                $('#selectedNodeTitle').text('Client: ' + (client.hostname || 'Unknown Device'));
                
                let signalHtml = '';
                if (client.signal_strength) {
                    const quality = client.signal_quality;
                    const qualityColor = {
                        'excellent': 'success',
                        'good': 'info',
                        'fair': 'warning',
                        'poor': 'danger'
                    }[quality] || 'secondary';
                    
                    signalHtml = `
                        <div class="col-md-3 mb-2">
                            <strong>Signal Strength:</strong><br>
                            ${client.signal_strength} dBm 
                            <span class="badge badge-sm bg-${qualityColor}">${quality}</span>
                        </div>
                    `;
                }
                
                $('#selectedNodeDetails').html(`
                    <div class="col-md-3 mb-2">
                        <strong>IP Address:</strong><br>${client.ip_address}
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong>MAC Address:</strong><br><code class="text-xs">${client.mac_address}</code>
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong>Connection:</strong><br>
                        <span class="badge badge-sm" style="background-color: ${connectionColors[client.connection_type]}">${client.connection_type.replace('_', ' ')}</span>
                    </div>
                    <div class="col-md-3 mb-2">
                        <strong>Interface:</strong><br>${client.interface_name || 'N/A'}
                    </div>
                    ${signalHtml}
                    <div class="col-md-3 mb-2">
                        <strong>Last Seen:</strong><br>${client.last_seen}
                    </div>
                `);
            }
        }

        function hideNodeDetails() {
            $('#selectedNodeInfo').slideUp();
        }

        function applyFilters() {
            if (!network) return;
            
            const filterAll = $('#filterAll').is(':checked');
            const filterLan = $('#filterLan').is(':checked');
            const filterWifi24 = $('#filterWifi24').is(':checked');
            const filterWifi5 = $('#filterWifi5').is(':checked');
            const filterWifi6 = $('#filterWifi6').is(':checked');
            
            if (filterAll) {
                $('#filterLan, #filterWifi24, #filterWifi5, #filterWifi6').prop('checked', true);
            }
            
            const filteredClients = allClients.filter(client => {
                if (filterAll) return true;
                if (filterLan && client.connection_type === 'lan') return true;
                if (filterWifi24 && client.connection_type === 'wifi_2.4ghz') return true;
                if (filterWifi5 && client.connection_type === 'wifi_5ghz') return true;
                if (filterWifi6 && client.connection_type === 'wifi_6ghz') return true;
                return false;
            });
            
            const deviceSelect = $('#deviceSelect option:selected').text();
            const device = {
                manufacturer: deviceSelect.split(' ')[1] || 'Gateway',
                model_name: deviceSelect.split(' ')[2] || '',
                serial_number: deviceSelect.split(' - ')[1] || '',
                status: 'online',
                protocol_type: 'tr069'
            };
            
            renderTopology(device, filteredClients);
        }

        function refreshTopology() {
            if (currentDeviceId) {
                loadTopology();
            }
        }

        function triggerNetworkScan() {
            if (!currentDeviceId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Device Selected',
                    text: 'Please select a device first'
                });
                return;
            }
            
            Swal.fire({
                title: 'Triggering Network Scan',
                html: 'Sending command to device...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: `/acs/devices/${currentDeviceId}/trigger-network-scan`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: { data_model: 'tr181' },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Scan Triggered',
                        text: response.message || 'Network scan initiated successfully'
                    });
                    
                    setTimeout(function() {
                        refreshTopology();
                    }, 60000);
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Scan Failed',
                        text: xhr.responseJSON?.message || 'Failed to trigger network scan'
                    });
                }
            });
        }

        function startAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            
            autoRefreshInterval = setInterval(function() {
                if (currentDeviceId) {
                    loadTopology();
                }
            }, 30000);
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
    </script>
@endsection
