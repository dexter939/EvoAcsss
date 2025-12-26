@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <a href="{{ route('acs.updates.index') }}" class="btn btn-sm btn-outline-secondary mb-0">
                    <i class="fas fa-arrow-left me-1"></i> Torna alla Lista
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header pb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-sync-alt text-info me-2"></i>
                                    {{ $update->version }}
                                </h5>
                                <p class="text-sm text-muted mb-0">
                                    Release Tag: <a href="{{ $update->github_release_url }}" target="_blank" class="text-primary">
                                        {{ $update->github_release_tag }}
                                    </a>
                                </p>
                            </div>
                            <div>
                                @if($update->approval_status === 'pending')
                                    <span class="badge bg-gradient-warning">
                                        <i class="fas fa-clock me-1"></i>In Attesa di Approvazione
                                    </span>
                                @elseif($update->approval_status === 'approved')
                                    <span class="badge bg-gradient-success">
                                        <i class="fas fa-check-circle me-1"></i>Approvato
                                    </span>
                                @elseif($update->approval_status === 'rejected')
                                    <span class="badge bg-gradient-danger">
                                        <i class="fas fa-times-circle me-1"></i>Rigettato
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="text-sm mb-1"><strong>Ambiente:</strong> <span class="badge bg-gradient-secondary">{{ strtoupper($update->environment) }}</span></p>
                                <p class="text-sm mb-1"><strong>Data Creazione:</strong> {{ $update->created_at->format('d/m/Y H:i:s') }}</p>
                                @if($update->is_current)
                                    <p class="text-sm mb-1"><strong>Status:</strong> <span class="badge bg-gradient-info">Versione Corrente</span></p>
                                @endif
                            </div>
                            <div class="col-md-6">
                                @if($update->approvedByUser)
                                    <p class="text-sm mb-1"><strong>Approvato Da:</strong> {{ $update->approvedByUser->name }}</p>
                                    <p class="text-sm mb-1"><strong>Data Approvazione:</strong> {{ $update->approved_at?->format('d/m/Y H:i:s') ?? '-' }}</p>
                                @endif
                                @if($update->scheduled_at)
                                    <p class="text-sm mb-1"><strong>Deployment Pianificato:</strong> {{ $update->scheduled_at->format('d/m/Y H:i:s') }}</p>
                                @endif
                            </div>
                        </div>

                        @if($update->changelog)
                            <hr class="horizontal dark">
                            <h6 class="mb-3"><i class="fas fa-list-ul me-2"></i>Changelog</h6>
                            <div class="changelog-content bg-gray-100 p-3 rounded">
                                {!! nl2br(e($update->changelog)) !!}
                            </div>
                        @endif

                        @if($validationResults)
                            <hr class="horizontal dark mt-4">
                            <h6 class="mb-3"><i class="fas fa-check-double me-2"></i>Validation Checks</h6>
                            <div class="list-group">
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-box me-2 {{ $validationResults['package_exists'] ? 'text-success' : 'text-danger' }}"></i>
                                            <strong>Package Exists</strong>
                                        </div>
                                        <span class="badge {{ $validationResults['package_exists'] ? 'bg-success' : 'bg-danger' }}">
                                            {{ $validationResults['package_exists'] ? 'OK' : 'FAIL' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-shield-alt me-2 {{ $validationResults['checksum_valid'] ? 'text-success' : 'text-danger' }}"></i>
                                            <strong>Checksum Validation</strong>
                                        </div>
                                        <span class="badge {{ $validationResults['checksum_valid'] ? 'bg-success' : 'bg-danger' }}">
                                            {{ $validationResults['checksum_valid'] ? 'OK' : 'FAIL' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-file-archive me-2 {{ $validationResults['extracted_files_exist'] ? 'text-success' : 'text-danger' }}"></i>
                                            <strong>Extracted Files</strong>
                                        </div>
                                        <span class="badge {{ $validationResults['extracted_files_exist'] ? 'bg-success' : 'bg-danger' }}">
                                            {{ $validationResults['extracted_files_exist'] ? 'OK' : 'FAIL' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-hdd me-2 {{ $validationResults['disk_space_sufficient'] ? 'text-success' : 'text-danger' }}"></i>
                                            <strong>Disk Space</strong>
                                        </div>
                                        <span class="badge {{ $validationResults['disk_space_sufficient'] ? 'bg-success' : 'bg-danger' }}">
                                            {{ $validationResults['disk_space_sufficient'] ? 'OK' : 'FAIL' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="alert {{ $validationResults['valid'] ? 'alert-success' : 'alert-danger' }} mt-3 mb-0">
                                <i class="fas {{ $validationResults['valid'] ? 'fa-check-circle' : 'fa-exclamation-triangle' }} me-2"></i>
                                <strong>{{ $validationResults['valid'] ? 'Validazione Completata' : 'Validazione Fallita' }}</strong>
                                <p class="mb-0 text-sm">{{ $validationResults['message'] }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header pb-3">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Azioni Disponibili</h6>
                    </div>
                    <div class="card-body">
                        @if($update->approval_status === 'pending')
                            <button class="btn btn-success w-100 mb-2 btn-approve-detail" data-update-id="{{ $update->id }}">
                                <i class="fas fa-check me-2"></i>Approva Aggiornamento
                            </button>
                            <button class="btn btn-danger w-100 mb-2 btn-reject-detail" data-update-id="{{ $update->id }}">
                                <i class="fas fa-times me-2"></i>Rigetta Aggiornamento
                            </button>
                        @elseif($update->approval_status === 'approved')
                            <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                <i class="fas fa-calendar-alt me-2"></i>Pianifica Deployment
                            </button>
                            <button class="btn btn-info w-100 mb-2 btn-apply-detail" data-update-id="{{ $update->id }}">
                                <i class="fas fa-rocket me-2"></i>Applica Ora
                            </button>
                            <button class="btn btn-warning w-100 mb-2 btn-validate" data-update-id="{{ $update->id }}">
                                <i class="fas fa-check-double me-2"></i>Valida Package
                            </button>
                        @endif
                        <a href="{{ $update->github_release_url }}" target="_blank" class="btn btn-outline-dark w-100">
                            <i class="fab fa-github me-2"></i>Visualizza su GitHub
                        </a>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header pb-3">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Timeline Deployment</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline timeline-one-side">
                            @foreach($deploymentHistory as $history)
                                <div class="timeline-block mb-3">
                                    <span class="timeline-step {{ $history->is_current ? 'bg-success' : 'bg-secondary' }}">
                                        <i class="fas fa-{{ $history->is_current ? 'check' : 'circle' }} text-white"></i>
                                    </span>
                                    <div class="timeline-content">
                                        <h6 class="text-dark text-sm font-weight-bold mb-0">
                                            {{ $history->deployment_status }}
                                        </h6>
                                        <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">
                                            {{ $history->created_at->format('d/m/Y H:i') }}
                                        </p>
                                        @if($history->is_current)
                                            <span class="badge badge-sm bg-gradient-info mt-1">Attivo</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i>Pianifica Deployment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="scheduleForm">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="scheduled_at">Data e Ora Deployment</label>
                                <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" required>
                                <small class="form-text text-muted">Il deployment sarà eseguito automaticamente alla data e ora specificate.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                            <button type="submit" class="btn btn-primary">Conferma Pianificazione</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="progressModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-spinner fa-spin me-2"></i>Deployment in Corso</h5>
                    </div>
                    <div class="modal-body">
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 id="deployProgress" 
                                 style="width: 0%">
                                0%
                            </div>
                        </div>
                        <p class="text-sm text-muted mb-0" id="progressMessage">Inizializzazione...</p>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Attenzione:</strong> Non chiudere questa finestra durante il deployment.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.footers.footer')
@endsection

@push('js')
    <script>
        window.updateId = {{ $update->id }};
    </script>
    <script src="{{ asset('assets/js/updates.js') }}"></script>
@endpush
