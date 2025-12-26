@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-lg mb-4">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-robot text-primary me-2"></i>
                                    AI Configuration Assistant
                                </h5>
                                <p class="text-sm text-muted mb-0">Intelligent configuration generation, validation, and optimization powered by OpenAI</p>
                            </div>
                            <div class="badge badge-lg bg-gradient-primary">
                                <i class="fas fa-brain me-1"></i> GPT-4o-mini
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon icon-shape icon-lg bg-gradient-primary shadow text-center border-radius-xl me-3">
                                <i class="fas fa-magic opacity-10" style="top: 12px; position: relative;"></i>
                            </div>
                            <h6 class="mb-0">Template Generation</h6>
                        </div>
                        <p class="text-sm text-muted mb-3">
                            Generate production-ready TR-069/TR-369 configuration templates based on device type, manufacturer, and required services.
                        </p>
                        <button type="button" class="btn btn-sm btn-primary w-100 mb-0" data-bs-toggle="modal" data-bs-target="#generateTemplateModal">
                            <i class="fas fa-wand-magic me-1"></i> Generate Template
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon icon-shape icon-lg bg-gradient-success shadow text-center border-radius-xl me-3">
                                <i class="fas fa-check-circle opacity-10" style="top: 12px; position: relative;"></i>
                            </div>
                            <h6 class="mb-0">Configuration Validation</h6>
                        </div>
                        <p class="text-sm text-muted mb-3">
                            Validate existing configuration profiles for TR-181 compliance, security issues, and performance problems.
                        </p>
                        <button type="button" class="btn btn-sm btn-success w-100 mb-0" data-bs-toggle="modal" data-bs-target="#validateConfigModal">
                            <i class="fas fa-shield-alt me-1"></i> Validate Configuration
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon icon-shape icon-lg bg-gradient-warning shadow text-center border-radius-xl me-3">
                                <i class="fas fa-chart-line opacity-10" style="top: 12px; position: relative;"></i>
                            </div>
                            <h6 class="mb-0">Configuration Optimization</h6>
                        </div>
                        <p class="text-sm text-muted mb-3">
                            Get AI-powered suggestions to optimize your configurations for performance, security, or stability.
                        </p>
                        <button type="button" class="btn btn-sm btn-warning w-100 mb-0" data-bs-toggle="modal" data-bs-target="#optimizeConfigModal">
                            <i class="fas fa-rocket me-1"></i> Optimize Configuration
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon icon-shape icon-lg bg-gradient-info shadow text-center border-radius-xl me-3">
                                <i class="fas fa-stethoscope opacity-10" style="top: 12px; position: relative;"></i>
                            </div>
                            <h6 class="mb-0">Diagnostic Analysis</h6>
                        </div>
                        <p class="text-sm text-muted mb-3">
                            Analyze TR-143 diagnostic test results with AI-powered root cause analysis and troubleshooting recommendations.
                        </p>
                        <button type="button" class="btn btn-sm btn-info w-100 mb-0" data-bs-toggle="modal" data-bs-target="#analyzeDiagnosticModal">
                            <i class="fas fa-microscope me-1"></i> Analyze Diagnostic
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon icon-shape icon-lg bg-gradient-danger shadow text-center border-radius-xl me-3">
                                <i class="fas fa-history opacity-10" style="top: 12px; position: relative;"></i>
                            </div>
                            <h6 class="mb-0">Historical Pattern Detection</h6>
                        </div>
                        <p class="text-sm text-muted mb-3">
                            Analyze device diagnostic history to identify recurring issues, degradation patterns, and root causes.
                        </p>
                        <button type="button" class="btn btn-sm btn-danger w-100 mb-0" data-bs-toggle="modal" data-bs-target="#analyzeHistoryModal">
                            <i class="fas fa-chart-area me-1"></i> Analyze History
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" id="resultsContainer" style="display: none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-brain me-2 text-primary"></i>
                                <span id="resultsTitle">AI Analysis Results</span>
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeResults()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body" id="resultsContent">
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('acs.modals.ai-generate-template')
    @include('acs.modals.ai-validate-config')
    @include('acs.modals.ai-optimize-config')
    @include('acs.modals.ai-analyze-diagnostic')
    @include('acs.modals.ai-analyze-history')

    <script>
        function closeResults() {
            $('#resultsContainer').slideUp();
        }

        function showResults(title, content) {
            $('#resultsTitle').text(title);
            $('#resultsContent').html(content);
            $('#resultsContainer').slideDown();
            $('html, body').animate({
                scrollTop: $('#resultsContainer').offset().top - 100
            }, 500);
        }

        function showLoading(message = 'Processing with AI...') {
            Swal.fire({
                title: '<i class="fas fa-robot text-primary me-2"></i> AI Assistant',
                html: `<div class="text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mb-0">${message}</p>
                </div>`,
                showConfirmButton: false,
                allowOutsideClick: false
            });
        }
    </script>
@endsection
