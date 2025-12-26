<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-3" id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
        <a class="align-items-center d-flex m-0 navbar-brand text-wrap" href="{{ route('acs.dashboard') }}">
            <div class="icon icon-shape icon-sm shadow border-radius-md bg-gradient-primary text-center me-2 d-flex align-items-center justify-content-center">
                <i class="fas fa-server text-white opacity-80"></i>
            </div>
            <span class="ms-2 font-weight-bold text-sm">ACS Management</span>
        </a>
    </div>
    <hr class="horizontal dark mt-0 mb-2">
    
    <div class="collapse navbar-collapse w-auto h-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/dashboard') ? 'active' : '' }}" href="{{ route('acs.dashboard') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-home text-primary text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Dashboard</span>
                </a>
            </li>
            
            <!-- Gestione Dispositivi -->
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Gestione CPE</h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/devices*') ? 'active' : '' }}" href="{{ route('acs.devices') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-wifi text-warning text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Dispositivi CPE</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/provisioning') && !Request::is('acs/advanced-provisioning*') ? 'active' : '' }}" href="{{ route('acs.provisioning') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-cogs text-info text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Provisioning</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/advanced-provisioning*') ? 'active' : '' }}" href="{{ route('acs.advanced-provisioning') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-layer-group text-warning text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Advanced Provisioning</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/firmware*') ? 'active' : '' }}" href="{{ route('acs.firmware') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-microchip text-success text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Firmware</span>
                </a>
            </li>
            
            <!-- Multi-tenant Customers -->
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Clienti & Servizi</h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/customers*') ? 'active' : '' }}" href="{{ route('acs.customers') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-users text-primary text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Clienti</span>
                </a>
            </li>
            
            <!-- Sistema -->
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Sistema</h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/tasks*') ? 'active' : '' }}" href="{{ route('acs.tasks') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-tasks text-danger text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Task Queue</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/performance-monitoring*') ? 'active' : '' }}" href="{{ route('acs.performance-monitoring') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-tachometer-alt text-info text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Performance Monitoring</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/advanced-monitoring*') ? 'active' : '' }}" href="{{ route('acs.advanced-monitoring') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-chart-line text-success text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Advanced Monitoring</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/security*') ? 'active' : '' }}" href="{{ route('acs.security') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-shield-alt text-danger text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Security Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/data-models*') ? 'active' : '' }}" href="{{ route('acs.data-models') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-database text-primary text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Data Models</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/profiles*') ? 'active' : '' }}" href="{{ route('acs.profiles') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-file-code text-dark text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Profili Configurazione</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/ai-assistant*') ? 'active' : '' }}" href="{{ route('acs.ai-assistant') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-robot text-primary text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">AI Assistant</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/manufacturers*') ? 'active' : '' }}" href="{{ route('acs.manufacturers') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-industry text-info text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Produttori & Modelli</span>
                </a>
            </li>
            
            <!-- Servizi Avanzati -->
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Servizi Avanzati</h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/diagnostics*') ? 'active' : '' }}" href="{{ route('acs.diagnostics') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-stethoscope text-primary text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Diagnostics</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/network-topology*') ? 'active' : '' }}" href="{{ route('acs.network-topology') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-project-diagram text-success text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Network Topology</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/voip*') ? 'active' : '' }}" href="{{ route('acs.voip') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-phone text-success text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">VoIP Services</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/storage*') ? 'active' : '' }}" href="{{ route('acs.storage') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-hdd text-warning text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Storage/NAS</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/iot*') ? 'active' : '' }}" href="{{ route('acs.iot') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-lightbulb text-info text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">IoT Devices</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/lan-devices*') ? 'active' : '' }}" href="{{ route('acs.lan-devices') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-network-wired text-secondary text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">LAN Devices</span>
                </a>
            </li>
            
            <!-- Servizi Telecomunicazioni -->
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Telecom Services</h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/femtocell*') ? 'active' : '' }}" href="{{ route('acs.femtocell') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-broadcast-tower text-danger text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Femtocell RF</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/stb*') ? 'active' : '' }}" href="{{ route('acs.stb') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-tv text-purple text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">STB/IPTV</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/parameters*') ? 'active' : '' }}" href="{{ route('acs.parameters') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-list-ul text-dark text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Parameters</span>
                </a>
            </li>
            
            <!-- TR-369 USP -->
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">TR-369 USP</h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/dashboard" target="_blank">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-terminal text-secondary text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">API JSON</span>
                </a>
            </li>
            
            <!-- Impostazioni -->
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Impostazioni</h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/updates*') ? 'active' : '' }}" href="{{ route('acs.updates.index') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-sync-alt text-info text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">System Updates</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/users*') || Request::is('acs/roles*') ? 'active' : '' }}" href="{{ route('acs.users') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-users-cog text-warning text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Users & Roles</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link {{ Request::is('acs/settings*') ? 'active' : '' }}" href="{{ route('acs.settings') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fas fa-cog text-primary text-sm opacity-60"></i>
                    </div>
                    <span class="nav-link-text ms-1">Impostazioni</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidenav-footer mx-3 mt-auto mb-2">
        <div class="card card-background shadow-none card-background-mask-secondary" id="sidenavCard">
            <div class="full-background" style="background-image: url('/assets/img/curved-images/white-curved.jpeg')"></div>
            <div class="card-body text-start p-3 w-100">
                <div class="docs-info">
                    <h6 class="text-white up mb-0">ACS Server</h6>
                    <p class="text-xs font-weight-bold">TR-069 & TR-369</p>
                </div>
            </div>
        </div>
    </div>
</aside>
