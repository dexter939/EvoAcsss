<nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" navbar-scroll="true">
    <div class="container-fluid py-1 px-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                <li class="breadcrumb-item text-sm">
                    <a class="opacity-5 text-dark" href="{{ route('acs.dashboard') }}">
                        <i class="fas fa-home"></i>
                    </a>
                </li>
                <li class="breadcrumb-item text-sm text-dark active" aria-current="page">
                    @yield('breadcrumb', 'Dashboard')
                </li>
            </ol>
            <h6 class="font-weight-bolder mb-0">@yield('page-title', 'Dashboard')</h6>
        </nav>
        
        <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
            <div class="ms-md-auto pe-md-3 d-flex align-items-center">
            </div>
            
            <ul class="navbar-nav justify-content-end">
                <li class="nav-item d-flex align-items-center me-3">
                    <div class="d-flex align-items-center">
                        <span class="badge badge-sm bg-gradient-success">
                            <i class="fas fa-circle text-white me-1" style="font-size: 0.5rem;"></i>
                            <span id="online-devices-count">-</span> ONLINE
                        </span>
                    </div>
                </li>
                
                <li class="nav-item dropdown pe-2 d-flex align-items-center">
                    <a href="javascript:;" class="nav-link text-body p-0" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa fa-bell cursor-pointer"></i>
                        <span class="badge bg-danger badge-sm badge-circle" id="notifications-count" style="display: none;">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end px-2 py-3 me-sm-n4" aria-labelledby="dropdownMenuButton" id="notifications-list">
                        <li class="text-center text-xs text-secondary py-2" id="no-notifications">
                            <i class="fas fa-check-circle me-1"></i> Nessuna notifica
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item d-xl-none ps-3 d-flex align-items-center">
                    <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav">
                        <div class="sidenav-toggler-inner">
                            <i class="sidenav-toggler-line"></i>
                            <i class="sidenav-toggler-line"></i>
                            <i class="sidenav-toggler-line"></i>
                        </div>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
(function() {
    function updateNavbarStats() {
        fetch('{{ route("acs.dashboard.stats") }}', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function(data) {
            var onlineEl = document.getElementById('online-devices-count');
            if (onlineEl && data.devices) {
                onlineEl.textContent = data.devices.online || 0;
            }
            
            var alarmsCount = 0;
            if (data.alarms) {
                alarmsCount = (data.alarms.critical || 0) + (data.alarms.major || 0);
            }
            
            var countBadge = document.getElementById('notifications-count');
            var notifList = document.getElementById('notifications-list');
            var noNotif = document.getElementById('no-notifications');
            
            if (countBadge) {
                if (alarmsCount > 0) {
                    countBadge.textContent = alarmsCount > 99 ? '99+' : alarmsCount;
                    countBadge.style.display = '';
                } else {
                    countBadge.style.display = 'none';
                }
            }
            
            if (notifList && data.alarms) {
                var html = '';
                if (data.alarms.critical > 0) {
                    html += '<li class="mb-2"><a class="dropdown-item border-radius-md" href="{{ route("acs.alarms") }}?severity=critical">' +
                        '<div class="d-flex py-1"><div class="my-auto me-3"><span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i></span></div>' +
                        '<div class="d-flex flex-column justify-content-center"><h6 class="text-sm font-weight-normal mb-1"><span class="font-weight-bold text-danger">' + 
                        data.alarms.critical + '</span> allarmi critici</h6></div></div></a></li>';
                }
                if (data.alarms.major > 0) {
                    html += '<li class="mb-2"><a class="dropdown-item border-radius-md" href="{{ route("acs.alarms") }}?severity=major">' +
                        '<div class="d-flex py-1"><div class="my-auto me-3"><span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i></span></div>' +
                        '<div class="d-flex flex-column justify-content-center"><h6 class="text-sm font-weight-normal mb-1"><span class="font-weight-bold text-warning">' + 
                        data.alarms.major + '</span> allarmi major</h6></div></div></a></li>';
                }
                if (html) {
                    notifList.innerHTML = html;
                } else if (noNotif) {
                    notifList.innerHTML = '<li class="text-center text-xs text-secondary py-2"><i class="fas fa-check-circle me-1"></i> Nessuna notifica</li>';
                }
            }
        })
        .catch(function(err) {
            console.log('Stats update error:', err.message);
        });
    }
    
    updateNavbarStats();
    setInterval(updateNavbarStats, 30000);
})();
</script>
