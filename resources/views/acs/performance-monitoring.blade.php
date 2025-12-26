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
                                    <i class="fas fa-tachometer-alt text-primary me-2"></i>
                                    System Performance Dashboard
                                </h5>
                                <p class="text-sm text-muted mb-0">Real-time performance metrics and scalability monitoring</p>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                                <label class="form-check-label text-sm">Auto-refresh (30s)</label>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">DB Queries/sec</p>
                                    <h5 class="font-weight-bolder" id="statQueriesPerSec">0</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-success font-weight-bold" id="queryTrend">+0%</span> from avg
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                    <i class="fas fa-database text-lg opacity-10" aria-hidden="true"></i>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Cache Hit Rate</p>
                                    <h5 class="font-weight-bolder" id="statCacheHitRate">0%</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-info font-weight-bold" id="cacheKeys">0 keys</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                    <i class="fas fa-memory text-lg opacity-10" aria-hidden="true"></i>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Queue Jobs/min</p>
                                    <h5 class="font-weight-bolder" id="statJobsPerMin">0</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-warning font-weight-bold" id="pendingJobs">0 pending</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                    <i class="fas fa-tasks text-lg opacity-10" aria-hidden="true"></i>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Avg Response</p>
                                    <h5 class="font-weight-bolder" id="statAvgResponse">0ms</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-danger font-weight-bold" id="slowQueries">0 slow</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle">
                                    <i class="fas fa-bolt text-lg opacity-10" aria-hidden="true"></i>
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
                        <h6>Database Performance (Last Hour)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="dbPerformanceChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header pb-0">
                        <h6>Cache Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-sm">Memory Used</span>
                                <strong class="text-sm" id="cacheMemoryUsed">0 MB</strong>
                            </div>
                            <div class="progress mt-1">
                                <div class="progress-bar bg-success" role="progressbar" id="cacheMemoryBar" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-sm">Connected Clients</span>
                                <strong class="text-sm" id="cacheClients">0</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-sm">Total Keys</span>
                                <strong class="text-sm" id="cacheTotalKeys">0</strong>
                            </div>
                        </div>
                        <hr class="horizontal dark">
                        <h6 class="text-sm mb-3">Cache Operations</h6>
                        <canvas id="cacheOpsChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header pb-0">
                        <h6>Queue Processing Rate</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="queueProcessingChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header pb-0">
                        <h6>Top Slow Queries</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Query</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Time</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Count</th>
                                    </tr>
                                </thead>
                                <tbody id="slowQueriesList">
                                    <tr>
                                        <td colspan="3" class="text-center text-sm text-muted">Loading...</td>
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
                        <h6>Database Indexes Analysis</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Table</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Index Name</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Size</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Scans</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="indexesList">
                                    <tr>
                                        <td colspan="5" class="text-center text-sm text-muted">Loading indexes...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        let autoRefreshInterval = null;
        let charts = {};

        $(document).ready(function() {
            initializeCharts();
            loadPerformanceMetrics();
            
            $('#autoRefresh').on('change', function() {
                if ($(this).is(':checked')) {
                    startAutoRefresh();
                } else {
                    stopAutoRefresh();
                }
            });
            
            startAutoRefresh();
        });

        function initializeCharts() {
            const ctx1 = document.getElementById('dbPerformanceChart');
            charts.dbPerformance = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Query Time (ms)',
                        data: [],
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
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

            const ctx2 = document.getElementById('cacheOpsChart');
            charts.cacheOps = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Hits', 'Misses'],
                    datasets: [{
                        data: [0, 0],
                        backgroundColor: ['rgb(34, 197, 94)', 'rgb(239, 68, 68)']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            const ctx3 = document.getElementById('queueProcessingChart');
            charts.queueProcessing = new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Processed',
                        data: [],
                        backgroundColor: 'rgb(34, 197, 94)'
                    }, {
                        label: 'Failed',
                        data: [],
                        backgroundColor: 'rgb(239, 68, 68)'
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
        }

        function loadPerformanceMetrics() {
            $.ajax({
                url: '{{ route("acs.performance.metrics") }}',
                method: 'GET',
                success: function(data) {
                    updateMetrics(data);
                }
            });
        }

        function updateMetrics(data) {
            $('#statQueriesPerSec').text(data.db?.queries_per_sec || 0);
            $('#statCacheHitRate').text((data.cache?.hit_rate || 0) + '%');
            $('#statJobsPerMin').text(data.queue?.jobs_per_min || 0);
            $('#statAvgResponse').text((data.db?.avg_response || 0) + 'ms');
            
            $('#cacheKeys').text((data.cache?.total_keys || 0) + ' keys');
            $('#pendingJobs').text((data.queue?.pending || 0) + ' pending');
            $('#slowQueries').text((data.db?.slow_count || 0) + ' slow');
            
            $('#cacheMemoryUsed').text(data.cache?.memory_used || '0 MB');
            $('#cacheClients').text(data.cache?.connected_clients || 0);
            $('#cacheTotalKeys').text(data.cache?.total_keys || 0);
            
            if (charts.cacheOps && data.cache) {
                charts.cacheOps.data.datasets[0].data = [
                    data.cache.hits || 0,
                    data.cache.misses || 0
                ];
                charts.cacheOps.update();
            }
            
            if (data.db?.slow_queries) {
                renderSlowQueries(data.db.slow_queries);
            }
            
            if (data.indexes) {
                renderIndexes(data.indexes);
            }
        }

        function renderSlowQueries(queries) {
            if (!queries || queries.length === 0) {
                $('#slowQueriesList').html('<tr><td colspan="3" class="text-center text-sm text-muted">No slow queries detected</td></tr>');
                return;
            }
            
            const html = queries.map(q => `
                <tr>
                    <td class="text-xs">${truncateQuery(q.query)}</td>
                    <td class="text-xs text-center">
                        <span class="badge badge-sm bg-${q.time > 1000 ? 'danger' : 'warning'}">${q.time}ms</span>
                    </td>
                    <td class="text-xs text-center">${q.count}</td>
                </tr>
            `).join('');
            
            $('#slowQueriesList').html(html);
        }

        function renderIndexes(indexes) {
            if (!indexes || indexes.length === 0) {
                $('#indexesList').html('<tr><td colspan="5" class="text-center text-sm text-muted">No index data available</td></tr>');
                return;
            }
            
            const html = indexes.map(idx => `
                <tr>
                    <td class="text-xs">${idx.table}</td>
                    <td class="text-xs"><code>${idx.name}</code></td>
                    <td class="text-xs text-center">${idx.size}</td>
                    <td class="text-xs text-center">${idx.scans || 0}</td>
                    <td class="text-center">
                        <span class="badge badge-sm bg-${idx.usage > 80 ? 'success' : (idx.usage > 50 ? 'warning' : 'danger')}">
                            ${idx.usage || 0}%
                        </span>
                    </td>
                </tr>
            `).join('');
            
            $('#indexesList').html(html);
        }

        function truncateQuery(query) {
            return query.length > 60 ? query.substring(0, 60) + '...' : query;
        }

        function startAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            
            autoRefreshInterval = setInterval(function() {
                loadPerformanceMetrics();
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
