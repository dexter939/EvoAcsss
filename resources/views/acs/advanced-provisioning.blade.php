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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Task Totali</p>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Completati</p>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">In Attesa</p>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Falliti</p>
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
                <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto" id="provisioningTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active text-nowrap" id="bulk-tab" data-bs-toggle="pill" data-bs-target="#bulk" type="button" role="tab">
                            <i class="fas fa-layer-group me-2"></i>Provisioning Massivo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap" id="scheduled-tab" data-bs-toggle="pill" data-bs-target="#scheduled" type="button" role="tab">
                            <i class="fas fa-calendar-alt me-2"></i>Programmati
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap" id="templates-tab" data-bs-toggle="pill" data-bs-target="#templates" type="button" role="tab">
                            <i class="fas fa-copy me-2"></i>Template
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap" id="conditions-tab" data-bs-toggle="pill" data-bs-target="#conditions" type="button" role="tab">
                            <i class="fas fa-code-branch me-2"></i>Regole Condizionali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button" role="tab">
                            <i class="fas fa-history me-2"></i>Cronologia
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap" id="analytics-tab" data-bs-toggle="pill" data-bs-target="#analytics" type="button" role="tab">
                            <i class="fas fa-chart-bar me-2"></i>Statistiche
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
                                    Selezione Dispositivi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label text-sm">Filtra Dispositivi</label>
                                    <select class="form-select form-select-sm" id="deviceFilterType">
                                        <option value="all">Tutti i Dispositivi</option>
                                        <option value="status">Per Stato</option>
                                        <option value="manufacturer">Per Produttore</option>
                                        <option value="model">Per Modello</option>
                                        <option value="firmware">Per Versione Firmware</option>
                                        <option value="service">Per Servizio</option>
                                        <option value="custom">Query Personalizzata</option>
                                    </select>
                                </div>
                                
                                <div id="filterOptions" class="mb-3" style="display: none;"></div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-sm">Dispositivi Disponibili</label>
                                    <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;" id="deviceList">
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-spinner fa-spin"></i> Caricamento dispositivi...
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info text-xs mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span id="selectedCount">0</span> dispositivi selezionati
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header pb-0">
                                <h6>
                                    <i class="fas fa-cog me-2 text-success"></i>
                                    Configurazione da Applicare
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-sm">Sorgente Configurazione</label>
                                        <select class="form-select form-select-sm" id="configSource">
                                            <option value="profile">Usa Profilo Esistente</option>
                                            <option value="template">Usa Template</option>
                                            <option value="custom">Parametri Personalizzati</option>
                                            <option value="ai">Configurazione Generata da AI</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-sm">Modalita Esecuzione</label>
                                        <select class="form-select form-select-sm" id="executionMode">
                                            <option value="immediate">Immediata</option>
                                            <option value="scheduled">Programmata</option>
                                            <option value="staged">Rollout Graduale</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div id="configSourceOptions" class="mb-3"></div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-sm">Controlli Pre-Esecuzione</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="checkDeviceOnline" checked>
                                        <label class="form-check-label text-xs">Verifica dispositivo online</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="checkDataModel" checked>
                                        <label class="form-check-label text-xs">Valida compatibilita data model</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="checkBackup">
                                        <label class="form-check-label text-xs">Backup configurazione attuale</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enableRollback">
                                        <label class="form-check-label text-xs">Abilita rollback automatico in caso di errore</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-sm">Strategia Rollout (per modalita Graduale)</label>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label text-xs">Dimensione Batch (%)</label>
                                            <input type="number" class="form-control form-control-sm" id="batchSize" value="10" min="1" max="100">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-xs">Attesa tra Batch (min)</label>
                                            <input type="number" class="form-control form-control-sm" id="batchDelay" value="5" min="1" max="1440">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-xs">Soglia Successo (%)</label>
                                            <input type="number" class="form-control form-control-sm" id="successThreshold" value="95" min="50" max="100">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="validateConfiguration()">
                                        <i class="fas fa-check-circle me-1"></i>Valida
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="executeBulkProvisioning()">
                                        <i class="fas fa-play me-1"></i>Esegui Provisioning
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
                                Task di Provisioning Programmati
                            </h6>
                            <button class="btn btn-sm btn-primary" onclick="showScheduleModal()">
                                <i class="fas fa-plus me-1"></i>Programma Nuovo Task
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
                                Libreria Template Configurazione
                            </h6>
                            <button class="btn btn-sm btn-primary" onclick="showCreateTemplateModal()">
                                <i class="fas fa-plus me-1"></i>Crea Template
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-sm">Categoria</label>
                                <select class="form-select form-select-sm" id="templateCategory" onchange="filterTemplates()">
                                    <option value="all">Tutte le Categorie</option>
                                    <option value="wifi">Configurazione WiFi</option>
                                    <option value="voip">VoIP/SIP</option>
                                    <option value="security">Sicurezza & Firewall</option>
                                    <option value="qos">QoS & Gestione Traffico</option>
                                    <option value="wan">Configurazione WAN</option>
                                    <option value="lan">LAN & DHCP</option>
                                    <option value="parental">Controllo Parentale</option>
                                    <option value="diagnostics">Diagnostica</option>
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
                                Regole di Provisioning Condizionale
                            </h6>
                            <button class="btn btn-sm btn-primary" onclick="showCreateRuleModal()">
                                <i class="fas fa-plus me-1"></i>Crea Regola
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-sm text-muted">Definisci regole per applicare automaticamente configurazioni in base alle caratteristiche del dispositivo</p>
                        <div id="conditionalRulesList"></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card">
                    <div class="card-header pb-0">
                        <h6>
                            <i class="fas fa-undo me-2 text-danger"></i>
                            Cronologia Configurazioni & Rollback
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-sm">Seleziona Dispositivo</label>
                            <select class="form-select form-select-sm" id="historyDeviceSelect" onchange="loadConfigHistory()">
                                <option value="">Seleziona un dispositivo...</option>
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
                                <h6>Tasso di Successo Provisioning (Ultimi 30 Giorni)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="successRateChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6>Template Piu Utilizzati</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="topTemplatesChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-12 mb-4">
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6>Timeline Task di Provisioning</h6>
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
            
            $('#deviceList').html(html || '<p class="text-muted text-center">Nessun dispositivo disponibile</p>');
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
                    html = '<input type="text" class="form-control form-control-sm" placeholder="Inserisci nome produttore" onchange="applyFilter()">';
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
                        <label class="form-label text-sm">Seleziona Profilo</label>
                        <select class="form-select form-select-sm" id="selectedProfile">
                            <option value="">Caricamento profili...</option>
                        </select>
                    `;
                    loadProfiles();
                    break;
                case 'template':
                    html = `
                        <label class="form-label text-sm">Seleziona Template</label>
                        <select class="form-select form-select-sm" id="selectedTemplate">
                            <option value="">Caricamento template...</option>
                        </select>
                    `;
                    break;
                case 'custom':
                    html = `
                        <label class="form-label text-sm">Parametri Personalizzati (JSON)</label>
                        <textarea class="form-control form-control-sm" id="customParameters" rows="5" placeholder='{"parameter.path": "value"}'></textarea>
                    `;
                    break;
                case 'ai':
                    html = `
                        <label class="form-label text-sm">Richiesta Configurazione AI</label>
                        <textarea class="form-control form-control-sm" id="aiRequest" rows="3" placeholder="Descrivi la configurazione desiderata (es. 'Configura WiFi 6 con sicurezza WPA3')"></textarea>
                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="generateAIConfig()">
                            <i class="fas fa-robot me-1"></i>Genera con AI
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
                    $('#selectedProfile').html('<option value="">Seleziona un profilo...</option>' + options);
                }
            });
        }

        function loadTemplates() {
            const templates = [
                { id: 1, name: 'WiFi 6 Standard', category: 'wifi', description: 'Configurazione WiFi 6 standard' },
                { id: 2, name: 'VoIP Base', category: 'voip', description: 'Configurazione VoIP base' },
                { id: 3, name: 'Sicurezza Avanzata', category: 'security', description: 'Impostazioni sicurezza avanzate' },
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
                                    <i class="fas fa-download me-1"></i>Usa
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
                title: 'Validazione Configurazione',
                html: 'Esecuzione controlli pre-avvio...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Validazione Superata',
                    html: '<ul class="text-start text-sm"><li>Tutti i dispositivi sono online</li><li>Compatibilita data model verificata</li><li>Nessun conflitto rilevato</li></ul>'
                });
            }, 2000);
        }

        function executeBulkProvisioning() {
            if (selectedDevices.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Nessun Dispositivo Selezionato',
                    text: 'Seleziona almeno un dispositivo'
                });
                return;
            }
            
            Swal.fire({
                title: 'Eseguire Provisioning Massivo?',
                html: `Applicare la configurazione a <strong>${selectedDevices.length}</strong> dispositivo/i?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Esegui',
                cancelButtonText: 'Annulla'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Provisioning Avviato',
                        text: `${selectedDevices.length} task accodati per l'esecuzione`
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
                        labels: ['Sett 1', 'Sett 2', 'Sett 3', 'Sett 4'],
                        datasets: [{
                            label: 'Tasso Successo %',
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
