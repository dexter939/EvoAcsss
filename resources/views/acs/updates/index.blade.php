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
                                    <i class="fas fa-sync-alt text-info me-2"></i>
                                    System Auto-Update
                                </h5>
                                <p class="text-sm text-muted mb-0">Gestione aggiornamenti software con approval workflow carrier-grade</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-primary" id="btnCheckUpdates">
                                    <i class="fas fa-sync me-1"></i> Controlla Aggiornamenti
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-2 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Totale</p>
                                    <h5 class="font-weight-bolder">{{ $stats['total_updates'] }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-secondary font-weight-bold">Updates</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-secondary shadow-secondary text-center rounded-circle">
                                    <i class="fas fa-sync-alt text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">In Attesa</p>
                                    <h5 class="font-weight-bolder">{{ $stats['pending_approval'] }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-warning font-weight-bold">Pending</span>
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
            <div class="col-lg-2 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Approvati</p>
                                    <h5 class="font-weight-bolder">{{ $stats['approved'] }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-success font-weight-bold">Approved</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                    <i class="fas fa-check-circle text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Rigettati</p>
                                    <h5 class="font-weight-bolder">{{ $stats['rejected'] }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-danger font-weight-bold">Rejected</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                    <i class="fas fa-times-circle text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-6 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Deployed</p>
                                    <h5 class="font-weight-bolder">{{ $stats['deployed'] }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-info font-weight-bold">Active</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle">
                                    <i class="fas fa-rocket text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-6">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Pianificati</p>
                                    <h5 class="font-weight-bolder">{{ $stats['scheduled'] }}</h5>
                                    <p class="mb-0 text-xs">
                                        <span class="text-primary font-weight-bold">Scheduled</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                    <i class="fas fa-calendar-alt text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Aggiornamenti Disponibili</h6>
                            <div class="btn-group" role="group">
                                <a href="{{ route('acs.updates.index', ['status' => 'all']) }}" 
                                   class="btn btn-sm {{ $status === 'all' ? 'btn-dark' : 'btn-outline-dark' }}">
                                    Tutti
                                </a>
                                <a href="{{ route('acs.updates.index', ['status' => 'pending']) }}" 
                                   class="btn btn-sm {{ $status === 'pending' ? 'btn-warning' : 'btn-outline-warning' }}">
                                    In Attesa
                                </a>
                                <a href="{{ route('acs.updates.index', ['status' => 'approved']) }}" 
                                   class="btn btn-sm {{ $status === 'approved' ? 'btn-success' : 'btn-outline-success' }}">
                                    Approvati
                                </a>
                                <a href="{{ route('acs.updates.index', ['status' => 'rejected']) }}" 
                                   class="btn btn-sm {{ $status === 'rejected' ? 'btn-danger' : 'btn-outline-danger' }}">
                                    Rigettati
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        @if($updates->isEmpty())
                            <div class="text-center py-5">
                                <i class="fab fa-github fa-3x text-secondary mb-3"></i>
                                <p class="text-muted">Nessun aggiornamento disponibile.</p>
                                <button class="btn btn-sm btn-primary" id="btnCheckUpdatesEmpty">
                                    <i class="fas fa-sync me-1"></i> Controlla Ora
                                </button>
                            </div>
                        @else
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Versione</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Ambiente</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Approvato Da</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Data Creazione</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($updates as $update)
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div>
                                                            <div class="icon icon-shape icon-sm bg-gradient-dark shadow text-center me-2 d-flex align-items-center justify-content-center">
                                                                <i class="fab fa-github text-white text-sm opacity-10"></i>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">{{ $update->version }}</h6>
                                                            @if($update->is_current)
                                                                <span class="badge badge-sm bg-gradient-info">Corrente</span>
                                                            @endif
                                                            <p class="text-xs text-secondary mb-0">
                                                                <a href="{{ $update->github_release_url }}" target="_blank" class="text-xs">
                                                                    <i class="fab fa-github me-1"></i>{{ $update->github_release_tag }}
                                                                </a>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($update->approval_status === 'pending')
                                                        <span class="badge badge-sm bg-gradient-warning">
                                                            <i class="fas fa-clock me-1"></i>In Attesa
                                                        </span>
                                                    @elseif($update->approval_status === 'approved')
                                                        <span class="badge badge-sm bg-gradient-success">
                                                            <i class="fas fa-check-circle me-1"></i>Approvato
                                                        </span>
                                                    @elseif($update->approval_status === 'rejected')
                                                        <span class="badge badge-sm bg-gradient-danger">
                                                            <i class="fas fa-times-circle me-1"></i>Rigettato
                                                        </span>
                                                    @endif
                                                    @if($update->scheduled_at && $update->scheduled_at->isFuture())
                                                        <br><span class="badge badge-sm bg-gradient-primary mt-1">
                                                            <i class="fas fa-calendar-alt me-1"></i>{{ $update->scheduled_at->format('d/m/Y H:i') }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="badge badge-sm bg-gradient-secondary">
                                                        {{ strtoupper($update->environment) }}
                                                    </span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    @if($update->approvedByUser)
                                                        <span class="text-secondary text-xs font-weight-bold">
                                                            {{ $update->approvedByUser->name }}
                                                        </span>
                                                    @else
                                                        <span class="text-xs text-secondary">-</span>
                                                    @endif
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="text-secondary text-xs font-weight-bold">
                                                        {{ $update->created_at->format('d/m/Y H:i') }}
                                                    </span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <a href="{{ route('acs.updates.show', $update->id) }}" 
                                                       class="btn btn-sm btn-outline-primary mb-0"
                                                       data-bs-toggle="tooltip" 
                                                       title="Visualizza Dettagli">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    @if($update->approval_status === 'pending')
                                                        <button class="btn btn-sm btn-success mb-0 btn-approve" 
                                                                data-update-id="{{ $update->id }}"
                                                                data-bs-toggle="tooltip" 
                                                                title="Approva">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger mb-0 btn-reject" 
                                                                data-update-id="{{ $update->id }}"
                                                                data-bs-toggle="tooltip" 
                                                                title="Rigetta">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    @elseif($update->approval_status === 'approved')
                                                        <button class="btn btn-sm btn-info mb-0 btn-apply" 
                                                                data-update-id="{{ $update->id }}"
                                                                data-bs-toggle="tooltip" 
                                                                title="Applica Aggiornamento">
                                                            <i class="fas fa-rocket"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="px-3 pb-3">
                                {{ $updates->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.footers.footer')
@endsection

@push('js')
    <script src="{{ asset('assets/js/updates.js') }}"></script>
@endpush
