@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="fas fa-cog me-2 text-primary"></i>Impostazioni ACS</h5>
                            <p class="text-sm text-muted mb-0">Configura i parametri del server TR-069</p>
                        </div>
                        <form action="{{ route('acs.settings.clear-cache') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-sync-alt me-1"></i>Svuota Cache
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form action="{{ route('acs.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <h6><i class="fas fa-server me-2 text-info"></i>Configurazione Server ACS</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">URL ACS</label>
                            <input type="url" class="form-control" name="acs_url" 
                                   value="{{ old('acs_url', $settings['acs_url']) }}"
                                   placeholder="https://acss.evo-net.it/tr069">
                            <small class="text-muted">URL completo da configurare sui dispositivi CPE</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username ACS</label>
                                <input type="text" class="form-control" name="acs_username" 
                                       value="{{ old('acs_username', $settings['acs_username']) }}"
                                       placeholder="acs_admin">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password ACS</label>
                                <input type="password" class="form-control" name="acs_password" 
                                       value="{{ old('acs_password', $settings['acs_password']) }}"
                                       placeholder="********">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Metodo Autenticazione TR-069</label>
                            <select class="form-select" name="tr069_auth_method">
                                <option value="none" {{ $settings['tr069_auth_method'] == 'none' ? 'selected' : '' }}>Nessuna</option>
                                <option value="basic" {{ $settings['tr069_auth_method'] == 'basic' ? 'selected' : '' }}>Basic</option>
                                <option value="digest" {{ $settings['tr069_auth_method'] == 'digest' ? 'selected' : '' }}>Digest</option>
                            </select>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="tr069_ssl_enabled" 
                                   id="tr069_ssl_enabled" value="1"
                                   {{ $settings['tr069_ssl_enabled'] ? 'checked' : '' }}>
                            <label class="form-check-label" for="tr069_ssl_enabled">
                                Abilita SSL/TLS per TR-069
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <h6><i class="fas fa-plug me-2 text-success"></i>Connection Request</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username CPE</label>
                                <input type="text" class="form-control" name="connection_request_username" 
                                       value="{{ old('connection_request_username', $settings['connection_request_username']) }}"
                                       placeholder="cpe">
                                <small class="text-muted">Username per Connection Request ai CPE</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password CPE</label>
                                <input type="password" class="form-control" name="connection_request_password" 
                                       value="{{ old('connection_request_password', $settings['connection_request_password']) }}"
                                       placeholder="********">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Inform Interval (secondi)</label>
                            <input type="number" class="form-control" name="inform_interval" 
                                   value="{{ old('inform_interval', $settings['inform_interval']) }}"
                                   min="60" max="86400" placeholder="3600">
                            <small class="text-muted">Intervallo tra gli Inform dei CPE (60-86400 secondi)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <h6><i class="fas fa-sliders-h me-2 text-warning"></i>Impostazioni Sistema</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Timeout Sessione (secondi)</label>
                                <input type="number" class="form-control" name="session_timeout" 
                                       value="{{ old('session_timeout', $settings['session_timeout']) }}"
                                       min="300" max="86400">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Max Dispositivi</label>
                                <input type="number" class="form-control" name="max_devices" 
                                       value="{{ old('max_devices', $settings['max_devices']) }}"
                                       min="100" max="1000000">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Livello Log</label>
                            <select class="form-select" name="log_level">
                                <option value="debug" {{ $settings['log_level'] == 'debug' ? 'selected' : '' }}>Debug</option>
                                <option value="info" {{ $settings['log_level'] == 'info' ? 'selected' : '' }}>Info</option>
                                <option value="warning" {{ $settings['log_level'] == 'warning' ? 'selected' : '' }}>Warning</option>
                                <option value="error" {{ $settings['log_level'] == 'error' ? 'selected' : '' }}>Error</option>
                            </select>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_debug" 
                                   id="enable_debug" value="1"
                                   {{ $settings['enable_debug'] ? 'checked' : '' }}>
                            <label class="form-check-label" for="enable_debug">
                                Abilita Modalita Debug
                            </label>
                            <small class="d-block text-muted">Mostra errori dettagliati (solo sviluppo)</small>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <h6><i class="fas fa-info-circle me-2 text-primary"></i>Informazioni Server</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tbody>
                                <tr>
                                    <td class="text-muted">PHP Version</td>
                                    <td class="text-end"><code>{{ $serverInfo['php_version'] }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Laravel Version</td>
                                    <td class="text-end"><code>{{ $serverInfo['laravel_version'] }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Ora Server</td>
                                    <td class="text-end"><code>{{ $serverInfo['server_time'] }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Timezone</td>
                                    <td class="text-end"><code>{{ $serverInfo['timezone'] }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">APP URL</td>
                                    <td class="text-end"><code>{{ $serverInfo['app_url'] }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Database</td>
                                    <td class="text-end"><code>{{ $serverInfo['db_connection'] }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Cache Driver</td>
                                    <td class="text-end"><code>{{ $serverInfo['cache_driver'] }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Queue Driver</td>
                                    <td class="text-end"><code>{{ $serverInfo['queue_driver'] }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Session Driver</td>
                                    <td class="text-end"><code>{{ $serverInfo['session_driver'] }}</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card bg-gradient-dark mb-4">
                    <div class="card-body">
                        <h6 class="text-white"><i class="fas fa-terminal me-2"></i>URL da Configurare sui CPE</h6>
                        <div class="bg-dark rounded p-3 mt-2">
                            <code class="text-success" style="font-size: 1rem;">{{ $settings['acs_url'] ?: config('app.url') . '/tr069' }}</code>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            Usa questo URL nella configurazione CWMP dei dispositivi
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body d-flex justify-content-end gap-2">
                        <a href="{{ route('acs.dashboard') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Annulla
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Salva Impostazioni
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
