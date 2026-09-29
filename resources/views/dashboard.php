<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" id="csrf-token-meta">
    <title>Mushroom Monitoring System</title>
    <?php
    // Include path after host (e.g. /thesis-ui/public) so links work behind XAMPP subfolders and artisan serve.
    $base = rtrim(request()->getSchemeAndHttpHost() . request()->getBaseUrl(), '/');
    $logoUrl = $base . '/images/mushroom-logo.jpg';
    
    // Get active user from either guard
    $currentUser = auth()->user() ?? auth('admin')->user();
    $isUserAdmin = $currentUser ? $currentUser->isAdmin() : false;
    ?>
    <link href="<?php echo e($base); ?>/vendor/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e($base); ?>/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e($base); ?>/css/dashboard.css">
    <link rel="preload" as="image" href="<?php echo e($logoUrl); ?>">
</head>
<body>

<div id="intro-overlay" class="intro-overlay" aria-live="polite">
    <div class="intro-content">
        <img src="<?php echo e($logoUrl); ?>" alt="" class="intro-logo" width="64" height="64" fetchpriority="high" decoding="async">
        <p class="intro-subtitle">Welcome to KABUTECH: An IoT- Based Monitoring System</p>
    </div>
</div>
<script>
(function(){
    var intro = document.getElementById('intro-overlay');
    if (intro) {
        setTimeout(function(){
            intro.classList.add('intro-done');
            setTimeout(function(){ intro.remove(); }, 650);
        }, 2600);
    }
})();
</script>

<!-- ── Fixed Top Header ── -->
<header class="site-header" role="banner">
    <div class="header-inner">
        <!-- Brand -->
        <div class="header-brand">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle sidebar" title="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <img src="<?php echo e($logoUrl); ?>" alt="KABUTECH Logo" class="navbar-logo" width="40" height="40" decoding="async">
            <span class="header-brand-name">Mushroom Monitoring</span>
        </div>

        <!-- Right side controls -->
        <div class="header-actions">
            <!-- Connection status -->
            <div class="connection-status disconnected" id="connection-status" aria-label="Connection status" title="Connecting...">
                <i id="connection-icon" class="fas fa-wifi"></i>
            </div>
            <!-- Notifications -->
            <div class="dropdown">
                <button class="header-icon-btn position-relative" type="button" id="notifDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Alerts">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge d-none" id="notif-badge">0</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 280px; max-height: 320px; overflow-y: auto;" aria-labelledby="notifDropdown" id="notif-dropdown-menu">
                    <li class="px-3 py-2 small text-muted">Loading…</li>
                </ul>
            </div>
            <!-- Profile Dropdown -->
            <?php if ($currentUser): ?>
            <div class="dropdown">
                <button class="profile-btn" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Profile">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="profile-name d-none d-md-inline"><?php echo e($currentUser->name); ?></span>
                    <i class="fas fa-chevron-down profile-caret d-none d-md-inline"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end profile-dropdown-menu" aria-labelledby="profileDropdown">
                    <li class="profile-dropdown-header">
                        <div class="profile-dd-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="profile-dd-name"><?php echo e($currentUser->name); ?></div>
                            <div class="profile-dd-role"><?php echo $isUserAdmin ? 'Administrator' : 'User'; ?></div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if ($isUserAdmin): ?>
                    <li>
                        <a class="dropdown-item" href="<?php echo e($base); ?>/admin">
                            <i class="fas fa-user-shield me-2"></i>Admin Panel
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li>
                        <form action="<?php echo e($base); ?>/logout" method="post" class="d-block">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Log Out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ── Page Wrapper (sidebar + content) ── -->
<div class="page-wrapper" id="pageWrapper">

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar" aria-label="Navigation sidebar">
    <nav class="sidebar-nav">
        <div class="sidebar-section-title">Navigation</div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" class="sidebar-link active" id="sidebar-dashboard" onclick="showSection('overview'); setSidebarActive(this); return false;">
                    <span class="sidebar-icon"><i class="fas fa-gauge-high"></i></span>
                    <span class="sidebar-label">Dashboard</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-section-title">Data</div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" class="sidebar-link" id="sidebar-history" onclick="showSection('history'); setSidebarActive(this); return false;">
                    <span class="sidebar-icon"><i class="fas fa-table"></i></span>
                    <span class="sidebar-label">Sensor Data</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-link" id="sidebar-exports" onclick="showSection('exports'); setSidebarActive(this); return false;">
                    <span class="sidebar-icon"><i class="fas fa-file-csv"></i></span>
                    <span class="sidebar-label">Export & Analytics</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-section-title">Growing & Hardware</div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" class="sidebar-link" id="sidebar-incubator" onclick="showSection('incubator'); setSidebarActive(this); return false;">
                    <span class="sidebar-icon"><i class="fas fa-box-open"></i></span>
                    <span class="sidebar-label">Incubator &amp; Growth</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-link" id="sidebar-diagnostics" onclick="showSection('diagnostics'); setSidebarActive(this); return false;">
                    <span class="sidebar-icon"><i class="fas fa-microchip"></i></span>
                    <span class="sidebar-label">Hardware Health</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-link" id="sidebar-camera-gallery" onclick="showSection('camera-gallery'); setSidebarActive(this); return false;">
                    <span class="sidebar-icon"><i class="fas fa-images"></i></span>
                    <span class="sidebar-label">Camera Snapshots</span>
                </a>
            </li>
        </ul>

    </nav>
</aside>

<!-- ── Main Content Area ── -->
<main class="main-content" id="mainContent">
<div class="container dashboard-container">
    <div class="dashboard-header text-center">
        <h1 class="dashboard-title">Welcome to Kabutech</h1>
        <p class="dashboard-subtitle">
            <span class="live-indicator"></span>
            <span id="live-status">Live</span> — Real-time sensor data
        </p>
        <?php if (isset($installation) && $installation): ?>
        <div class="farm-info-card mt-3 mb-0">
            <div class="farm-label"><i class="fas fa-warehouse me-1"></i> Active mushroom farm</div>
            <div class="farm-name"><?php echo e($installation->name); ?></div>
            <?php if (! empty($installation->owner_name)): ?>
                <div class="farm-owner">Owner: <?php echo e($installation->owner_name); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="current-readings-bar mt-3" id="current-readings-bar">
            <span class="readings-item"><i class="fas fa-thermometer-half me-2"></i><strong>Temperature:</strong> <span id="header-temp">--</span> °C</span>
            <span class="readings-divider">|</span>
            <span class="readings-item"><i class="fas fa-tint me-2"></i><strong>Humidity:</strong> <span id="header-humidity">--</span> %</span>
        </div>
    </div>

    <!-- Overview (Dashboard page only) -->
    <div id="overview-section">
        <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card" id="temperature-card">
                <div class="stat-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="text-center">
                    <div class="stat-label">Temperature</div>
                    <div class="stat-value" id="temperature-value">--<span class="stat-unit">°C</span></div>
                    <div class="last-update" id="temp-update"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" id="humidity-card">
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="text-center">
                    <div class="stat-label">Humidity</div>
                    <div class="stat-value" id="humidity-value">--<span class="stat-unit">%</span></div>
                    <div class="last-update" id="humidity-update"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" id="misting-card">
                <div class="stat-icon"><i class="fas fa-spray-can"></i></div>
                <div class="text-center">
                    <div class="stat-label">Misting System</div>
                    <div class="stat-value" id="misting-status" style="font-size: 1.8rem;">OFF</div>
                    <span class="status-badge status-off" id="misting-badge">Inactive</span>
                    <div class="d-flex flex-column align-items-center mt-2">
                        <button class="control-btn off" id="misting-btn" onclick="toggleMisting()"><i class="fas fa-power-off me-2"></i>Turn ON</button>
                        <div class="misting-mode-info">
                            <span class="d-block small text-muted mb-1">ESP32 control mode</span>
                            <span class="misting-auto-status" id="misting-auto-status">Loading…</span>
                        </div>
                    </div>
                    <div class="last-update" id="misting-update"></div>
                </div>
            </div>
        </div>
        </div>

    <!-- Grow: species, targets, harvest prediction -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card glass-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold"><i class="fas fa-seedling me-2"></i>Grow & harvest prediction</span>
                    <small class="text-muted">AUTO misting uses these targets on the ESP32</small>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="mushroom-type-select" class="form-label small mb-1">Mushroom type</label>
                            <select class="form-select form-select-sm" id="mushroom-type-select">
                                <option value="oyster_mushroom">Oyster Mushroom</option>
                                <option value="straw_mushroom">Straw Mushroom</option>
                                <option value="milky_mushroom">Milky Mushroom</option>
                                <option value="wood_ear">Wood Ear</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Stage clocks</label>
                            <div class="d-grid gap-2" style="grid-template-columns: 1fr 1fr;">
                                <button type="button" class="btn btn-sm btn-stage-outline" id="btn-incubation-start">Start incubation</button>
                                <button type="button" class="btn btn-sm btn-stage-outline" id="btn-incubation-clear">Clear incubation</button>
                                <button type="button" class="btn btn-sm btn-success" id="btn-fruiting-start">Start fruiting</button>
                                <button type="button" class="btn btn-sm btn-stage-outline" id="btn-fruiting-clear">Clear fruiting</button>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="badge rounded-pill" id="env-status-badge">—</span>
                        </div>
                    </div>
                    <hr class="border-secondary opacity-25 my-3">
                    <div class="row g-3 small">
                        <div class="col-md-6">
                            <div class="fw-semibold mb-1">Ideal conditions (fruiting)</div>
                            <p class="mb-0 text-muted" id="grow-targets-text">—</p>
                            <div class="fw-semibold mb-1 mt-3">Ideal conditions (incubation)</div>
                            <p class="mb-0 text-muted" id="grow-incubation-targets-text">—</p>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-semibold mb-1">Predicted incubation finish (switch to fruiting)</div>
                            <p class="mb-0 text-muted" id="grow-incubation-text">Set “Start incubation” to get an estimate.</p>
                            <div class="fw-semibold mb-1">Predicted fruiting (pinning) start</div>
                            <p class="mb-0 text-muted" id="grow-fruiting-text">Keep conditions on target for an estimate.</p>
                            <div class="fw-semibold mb-1 mt-3">Predicted harvest window</div>
                            <p class="mb-0 text-muted" id="grow-prediction-text">Set “Start fruiting” when pins appear for an estimate.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

    <!-- Camera -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold"><i class="fas fa-video me-2 text-success"></i>Live Camera Feed</span>
                        <span id="camera-status-pill" class="camera-overlay-badge" style="font-size:0.7rem; padding: 0.15rem 0.5rem;">
                            <span id="camera-pulse-indicator" class="camera-pulse-dot connecting"></span>
                            <span id="camera-status-text">INITIALIZING</span>
                        </span>
                    </div>
                    <div class="camera-toolbar">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Stream mode">
                            <button type="button" class="btn btn-outline-secondary active" id="btn-mode-mjpeg" onclick="setCameraMode('mjpeg')" title="Smooth MJPEG video stream">
                                <i class="fas fa-play-circle me-1"></i>Live Stream
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-mode-snapshot" onclick="setCameraMode('snapshot')" title="1s Snapshot refresh mode (ultra-reliable, low-bandwidth)">
                                <i class="fas fa-sync me-1"></i>Fast Refresh
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-warning" id="btn-toggle-flash" onclick="toggleCameraFlash()" title="Toggle ESP32-CAM Flash LED">
                            <i class="fas fa-lightbulb"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="triggerCameraSnapshot(this)" title="Capture and archive snapshot">
                            <i class="fas fa-camera me-1"></i>Capture
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleCameraFullscreen()" title="Fullscreen view">
                            <i class="fas fa-expand"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="retryCameraStream()" title="Reload feed">
                            <i class="fas fa-redo-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body text-center camera-stream-wrap">
                    <?php
                    $cameraStreamUrl = config('iot.camera_stream_url', '');
                    $cameraSrc = !empty($cameraStreamUrl) ? $base . '/api/camera-stream' : '';
                    ?>
                    <?php if (!empty($cameraSrc)): ?>
                    <div class="camera-player-box" id="camera-player-container">
                        <div class="camera-overlay-top">
                            <span class="camera-overlay-badge">
                                <i class="fas fa-microchip text-primary"></i> ESP32-CAM (OV2640/OV3660)
                            </span>
                            <span class="camera-overlay-badge" id="camera-fps-badge">
                                <i class="fas fa-broadcast-tower text-success"></i> <span id="camera-stream-info">Connecting…</span>
                            </span>
                        </div>
                        <img id="camera-stream"
                             src="<?php echo e($cameraSrc); ?>"
                             alt="ESP32-CAM Live Stream"
                             class="camera-stream-img"
                             onload="onCameraStreamLoad(this)"
                             onerror="window.cameraStreamError && window.cameraStreamError(this);">
                    </div>
                    <div id="camera-offline" class="camera-offline-msg mt-3" style="display:none;">
                        <i class="fas fa-video-slash text-danger fa-3x mb-3"></i>
                        <h6 class="fw-bold mb-1 text-white">Camera Stream Unreachable</h6>
                        <p class="text-muted small mb-3">
                            Unable to connect to ESP32-CAM stream proxy.
                        </p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-primary" onclick="setCameraMode('snapshot')">
                                <i class="fas fa-sync me-1"></i> Try Fast Refresh Mode
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="retryCameraStream()">
                                <i class="fas fa-redo-alt me-1"></i> Retry Connection
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="camera-offline-msg">
                        <i class="fas fa-camera text-muted fa-3x mb-3"></i>
                        <h6 class="fw-bold mb-1 text-white">Connect your ESP32-CAM</h6>
                        <p class="text-muted small mb-2">Add to your <code>.env</code> file:</p>
                        <p class="mb-2"><code>ESP32_CAM_STREAM_URL=http://YOUR_ESP32_CAM_IP:81/stream</code></p>
                        <p class="text-muted small mb-0">Use the IP address shown in the Arduino Serial Monitor after connecting the ESP32-CAM to Wi‑Fi.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /#overview-section -->

    <!-- History -->
    <div class="row mt-4 d-none" id="history-section">
        <div class="col-12">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="fw-semibold">Sensor Data History</span>
                        <small class="text-muted d-block" id="history-subtitle">Latest 15 readings (auto-refresh)</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="history-limit-select" class="form-label small text-muted mb-0 text-nowrap">Show</label>
                        <select id="history-limit-select" class="form-select form-select-sm form-select-sm-inline">
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="form-label small text-muted mb-0">logs</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="p-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="glass-card p-3 sensor-chart-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-semibold"><i class="fas fa-thermometer-half me-1 text-danger"></i> Temperature</span>
                                        <span class="fw-semibold"><i class="fas fa-tint me-1 text-info"></i> Humidity</span>
                                    </div>
                                    <div class="sensor-chart-wrap">
                                        <canvas id="th-chart" height="140"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Timestamp</th>
                                    <th scope="col">Temperature (°C)</th>
                                    <th scope="col">Humidity (%)</th>
                                    <th scope="col">WiFi (RSSI)</th>
                                    <th scope="col">Misting</th>
                                    <th scope="col">Mode</th>
                                </tr>
                            </thead>
                            <tbody id="history-body">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">Waiting for sensor data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Incubator & Grow Section -->
    <div class="row mt-4 d-none" id="incubator-section">
        <!-- Multi-Box Incubator Monitor Grid -->
        <div class="col-12 mb-4">
            <div class="card glass-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="fw-semibold"><i class="fas fa-boxes-packing me-2 text-success"></i>Incubator &amp; Growth Chambers</span>
                        <small class="text-muted d-block">Select a box to monitor climate and view growth prediction</small>
                    </div>
                    <button class="btn btn-sm btn-success px-3" data-bs-toggle="modal" data-bs-target="#createBoxModal" onclick="resetCreateBoxModal()">
                        <i class="fas fa-plus me-1"></i> Add Incubator Box
                    </button>
                </div>
                <div class="card-body">
                    <!-- Incubator box cards rendered dynamically by JS -->
                    <div class="incubator-boxes-grid" id="incubator-boxes-grid">
                        <!-- dynamically filled by renderIncubatorBoxCards() -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Growth Progress & Harvest Timeline (wired to selected box) -->
        <div class="col-12 mb-4">
            <div class="card glass-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="fas fa-chart-line me-2"></i>Growth Progress &amp; Harvest Timeline</span>
                    <div class="d-flex align-items-center gap-3">
                        <div class="timeline-clock text-muted small fw-semibold">
                            <i class="far fa-clock me-1"></i><span id="live-clock">--:--:--</span>
                            <span class="ms-2" id="live-date">-- --- ----</span>
                        </div>
                        <div class="selected-incubator-strip" id="selected-box-strip">
                            <i class="fas fa-box-open"></i>
                            <span id="selected-box-label">Select a box above</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold">Estimated Colonization / Growth Completion</span>
                            <span class="small fw-semibold text-success" id="growth-percentage-label">0%</span>
                        </div>
                        <div class="progress" style="height: 18px; background: rgba(255,255,255,0.06); border-radius: 9px; overflow: hidden; border: 1px solid var(--color-border);">
                            <div id="growth-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="growth-timeline-wrap mb-4">
                        <div class="d-flex justify-content-between position-relative py-3">
                            <div class="timeline-line" style="position: absolute; top: 50%; left: 10%; right: 10%; height: 2px; background: var(--color-border); z-index: 1;"></div>

                            <div class="timeline-step text-center position-relative" style="z-index: 2; width: 25%;">
                                <div class="step-dot bg-secondary" id="step-dot-spawn" style="width:16px; height:16px; border-radius:50%; margin:0 auto 8px; border:3px solid var(--color-bg);"></div>
                                <div class="small fw-semibold">Spawn Run</div>
                                <div class="text-muted" style="font-size: 0.65rem;" id="time-spawn-date">—</div>
                            </div>
                            <div class="timeline-step text-center position-relative" style="z-index: 2; width: 25%;">
                                <div class="step-dot bg-secondary" id="step-dot-pinning" style="width:16px; height:16px; border-radius:50%; margin:0 auto 8px; border:3px solid var(--color-bg);"></div>
                                <div class="small fw-semibold">Pinning</div>
                                <div class="text-muted" style="font-size: 0.65rem;" id="time-pinning-date">—</div>
                            </div>
                            <div class="timeline-step text-center position-relative" style="z-index: 2; width: 25%;">
                                <div class="step-dot bg-secondary" id="step-dot-fruiting" style="width:16px; height:16px; border-radius:50%; margin:0 auto 8px; border:3px solid var(--color-bg);"></div>
                                <div class="small fw-semibold">Fruiting</div>
                                <div class="text-muted" style="font-size: 0.65rem;" id="time-fruiting-date">—</div>
                            </div>
                            <div class="timeline-step text-center position-relative" style="z-index: 2; width: 25%;">
                                <div class="step-dot bg-secondary" id="step-dot-harvest" style="width:16px; height:16px; border-radius:50%; margin:0 auto 8px; border:3px solid var(--color-bg);"></div>
                                <div class="small fw-semibold">Harvest</div>
                                <div class="text-muted" style="font-size: 0.65rem;" id="time-harvest-date">—</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-opacity-10 bg-info rounded border border-secondary">
                        <div class="d-flex align-items-center gap-2 text-info mb-1">
                            <i class="fas fa-info-circle"></i>
                            <span class="fw-semibold small">Harvest Prediction Analysis</span>
                        </div>
                        <p class="small text-muted mb-0" id="inc-prediction-summary">Select an incubator box above to view its growth status and harvest prediction.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incubator Integration: Batch registration of spawn bags (Rec 5) -->
        <div class="col-12 mb-4">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="fas fa-folder-plus me-2"></i>Active Incubator Batches (Bags Register)</span>
                    <button type="button" class="btn btn-sm btn-success px-3" data-bs-toggle="collapse" data-bs-target="#addBatchCollapse"><i class="fas fa-plus me-1"></i> Register New Batch</button>
                </div>
                <div class="collapse" id="addBatchCollapse">
                    <div class="card-body border-bottom border-secondary">
                        <form id="newBatchForm" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small">Batch ID/Name</label>
                                <input type="text" id="batch-name" class="form-control form-control-sm" placeholder="e.g. Oyster Batch B" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Mushroom Type</label>
                                <select id="batch-type" class="form-select form-select-sm">
                                    <option value="oyster_mushroom">Oyster Mushroom</option>
                                    <option value="straw_mushroom">Straw Mushroom</option>
                                    <option value="milky_mushroom">Milky Mushroom</option>
                                    <option value="wood_ear">Wood Ear</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Assign Incubator</label>
                                <select id="batch-incubator" class="form-select form-select-sm">
                                    <option value="incubator_a">Incubator Room A</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Bags Count</label>
                                <input type="number" id="batch-bags" class="form-control form-control-sm" min="1" value="20" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-sm btn-success w-100">Register</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Batch Name</th>
                                    <th>Mushroom Type</th>
                                    <th>Assigned Incubator</th>
                                    <th>Bags Count</th>
                                    <th>Date Started</th>
                                    <th>Growth Stage</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="inc-batches-tbody">
                                <!-- Dynamic -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Harvest & Yield Tracking Section ── -->
        <div class="col-12 mb-4">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="fas fa-wheat-awn me-2 text-success"></i>Harvest &amp; Yield Tracking Log</span>
                    <button type="button" class="btn btn-sm btn-success px-3" data-bs-toggle="modal" data-bs-target="#logHarvestModal">
                        <i class="fas fa-plus me-1"></i> Log Harvest
                    </button>
                </div>
                <div class="card-body border-bottom border-secondary">
                    <div class="row g-3 text-center">
                        <div class="col-md-4">
                            <div class="p-2 rounded" style="background: rgba(16, 185, 129, 0.08); border: 1px solid var(--color-border);">
                                <div class="small text-muted mb-1"><i class="fas fa-scale-balanced me-1 text-success"></i>Total Yield Harvested</div>
                                <div class="h4 mb-0 fw-bold text-success" id="harvest-summary-total-weight">0.00 kg</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2 rounded" style="background: rgba(16, 185, 129, 0.08); border: 1px solid var(--color-border);">
                                <div class="small text-muted mb-1"><i class="fas fa-rotate me-1 text-info"></i>Total Flushes</div>
                                <div class="h4 mb-0 fw-bold text-info" id="harvest-summary-total-flushes">0</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2 rounded" style="background: rgba(16, 185, 129, 0.08); border: 1px solid var(--color-border);">
                                <div class="small text-muted mb-1"><i class="fas fa-chart-line me-1 text-warning"></i>Avg Biological Efficiency (BE %)</div>
                                <div class="h4 mb-0 fw-bold text-warning" id="harvest-summary-avg-be">0.0%</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Harvest Date</th>
                                    <th>Batch Name</th>
                                    <th>Mushroom Species</th>
                                    <th>Flush #</th>
                                    <th>Fresh Weight</th>
                                    <th>BE %</th>
                                    <th>Quality</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="harvest-logs-tbody">
                                <tr><td colspan="8" class="text-center text-muted py-3">Loading harvest records…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /#incubator-section -->

    <!-- Export & Analytics Section -->
    <div class="row mt-4 d-none" id="exports-section">
        <div class="col-12 mb-4">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="fas fa-file-export me-2 text-primary"></i>Data Export &amp; Reporting Center</span>
                    <span class="badge bg-primary text-wrap">Thesis Analysis Ready</span>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 border border-secondary rounded glass-card h-100">
                                <h6 class="fw-bold mb-2"><i class="fas fa-database me-2 text-info"></i>Sensor Telemetry Export (CSV)</h6>
                                <p class="small text-muted mb-3">Download raw temperature, humidity, misting pump activations, and RSSI signal logs.</p>
                                <form id="exportSensorDataForm" method="GET" action="<?php echo e($base); ?>/api/export/sensor-data" target="_blank">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small">Start Date</label>
                                            <input type="date" name="start_date" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">End Date</label>
                                            <input type="date" name="end_date" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small">Filter Box</label>
                                            <select name="box_id" class="form-select form-select-sm">
                                                <option value="all">All Incubator Boxes</option>
                                                <option value="box_a">Box A</option>
                                                <option value="box_b">Box B</option>
                                                <option value="box_c">Box C</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-download me-1"></i> Download Sensor CSV</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border border-secondary rounded glass-card h-100">
                                <h6 class="fw-bold mb-2"><i class="fas fa-wheat-awn me-2 text-success"></i>Harvest Yield Logs Export (CSV)</h6>
                                <p class="small text-muted mb-3">Download recorded mushroom harvest weights, flush counts, substrate weights, and Biological Efficiency (BE%).</p>
                                <form id="exportHarvestLogsForm" method="GET" action="<?php echo e($base); ?>/api/export/harvest-logs" target="_blank">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small">Start Date</label>
                                            <input type="date" name="start_date" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">End Date</label>
                                            <input type="date" name="end_date" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-download me-1"></i> Download Harvest CSV</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 mb-4">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="fas fa-chart-line me-2 text-warning"></i>Yield vs. Environmental Correlation Analysis</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="loadCorrelationAnalytics()"><i class="fas fa-sync me-1"></i> Refresh</button>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Correlates mushroom harvest weight (g) against average ambient temperature and humidity during the 7 days prior to harvest.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Harvest Batch</th>
                                    <th>Harvest Date</th>
                                    <th>Flush #</th>
                                    <th>Harvest Weight</th>
                                    <th>7-Day Avg Temp</th>
                                    <th>7-Day Avg Humidity</th>
                                    <th>Yield Rating</th>
                                </tr>
                            </thead>
                            <tbody id="correlation-table-body">
                                <tr><td colspan="7" class="text-center text-muted py-3">Loading correlation data…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hardware Health Diagnostics Section -->
    <div class="row mt-4 d-none" id="diagnostics-section">
        <div class="col-12 mb-4">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="fas fa-microchip me-2 text-info"></i>Hardware Diagnostic &amp; Health Metrics</span>
                    <button class="btn btn-sm btn-outline-info" onclick="loadHardwareDiagnostics()"><i class="fas fa-sync me-1"></i> Refresh Metrics</button>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="p-3 text-center rounded border border-secondary" style="background: rgba(56, 189, 248, 0.08);">
                                <div class="small text-muted mb-1"><i class="fas fa-wifi me-1 text-info"></i>Wi-Fi Signal Strength</div>
                                <div class="h3 mb-1 fw-bold text-info" id="diag-wifi-rssi">-- dBm</div>
                                <span class="badge bg-info" id="diag-wifi-status">Checking…</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 text-center rounded border border-secondary" style="background: rgba(16, 185, 129, 0.08);">
                                <div class="small text-muted mb-1"><i class="fas fa-faucet-drip me-1 text-success"></i>Pump Daily Runtime</div>
                                <div class="h3 mb-1 fw-bold text-success" id="diag-pump-runtime">-- min</div>
                                <span class="small text-muted" id="diag-pump-duty">Duty cycle: --%</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 text-center rounded border border-secondary" style="background: rgba(245, 158, 11, 0.08);">
                                <div class="small text-muted mb-1"><i class="fas fa-spray-can me-1 text-warning"></i>24h Misting Activations</div>
                                <div class="h3 mb-1 fw-bold text-warning" id="diag-misting-count">-- bursts</div>
                                <span class="small text-muted">Relay trigger count</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 text-center rounded border border-secondary" style="background: rgba(99, 102, 241, 0.08);">
                                <div class="small text-muted mb-1"><i class="fas fa-satellite-dish me-1 text-indigo"></i>24h Telemetry Packets</div>
                                <div class="h3 mb-1 fw-bold" style="color:#818cf8;" id="diag-packet-count">--</div>
                                <span class="small text-muted" id="diag-device-connection">Connected</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Camera Snapshots Gallery Section -->
    <div class="row mt-4 d-none" id="camera-gallery-section">
        <div class="col-12 mb-4">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="fas fa-camera me-2 text-danger"></i>Camera Snapshot Archiving &amp; Gallery</span>
                    <button class="btn btn-sm btn-danger px-3" onclick="triggerCameraSnapshot()"><i class="fas fa-camera me-1"></i> Capture Snapshot Now</button>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3" id="camera-snapshots-grid">
                        <div class="col-12 text-center text-muted py-4">Loading camera snapshots gallery…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</main>

<!-- ── Log Harvest Modal ── -->
<div class="modal fade" id="logHarvestModal" tabindex="-1" aria-labelledby="logHarvestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border border-secondary" style="background: var(--color-surface-solid); color: var(--color-text);">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title fw-bold" id="logHarvestModalLabel">
          <i class="fas fa-basket-shopping me-2 text-success"></i>Log Mushroom Harvest
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="logHarvestForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="harvest-batch-name" class="form-label small">Batch ID/Name</label>
              <input type="text" id="harvest-batch-name" class="form-control form-control-sm" placeholder="e.g. Oyster Batch A" required>
            </div>
            <div class="col-md-6">
              <label for="harvest-mushroom-type" class="form-label small">Mushroom Species</label>
              <select id="harvest-mushroom-type" class="form-select form-select-sm" required>
                <option value="oyster_mushroom">Oyster Mushroom</option>
                <option value="straw_mushroom">Straw Mushroom</option>
                <option value="milky_mushroom">Milky Mushroom</option>
                <option value="wood_ear">Wood Ear</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="harvest-flush-number" class="form-label small">Flush Number</label>
              <input type="number" id="harvest-flush-number" class="form-control form-control-sm" min="1" max="10" value="1" required>
            </div>
            <div class="col-md-4">
              <label for="harvest-weight-grams" class="form-label small">Yield Weight (Grams)</label>
              <input type="number" id="harvest-weight-grams" class="form-control form-control-sm" step="1" min="1" placeholder="e.g. 850" required>
            </div>
            <div class="col-md-4">
              <label for="harvest-substrate-weight" class="form-label small">Substrate Weight (g)</label>
              <input type="number" id="harvest-substrate-weight" class="form-control form-control-sm" step="1" min="0" placeholder="e.g. 1000">
            </div>
            <div class="col-md-6">
              <label for="harvest-quality" class="form-label small">Quality Grade</label>
              <select id="harvest-quality" class="form-select form-select-sm">
                <option value="Grade A">Grade A (Premium)</option>
                <option value="Grade B">Grade B (Standard)</option>
                <option value="Grade C">Grade C (Processing)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="harvest-date" class="form-label small">Harvest Date</label>
              <input type="date" id="harvest-date" class="form-control form-control-sm">
            </div>
            <div class="col-12">
              <label for="harvest-notes" class="form-label small">Harvest Notes</label>
              <textarea id="harvest-notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Excellent cap development, firm texture"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top border-secondary">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="harvest-submit-btn" class="btn btn-sm btn-success px-4">
            <i class="fas fa-check me-1"></i> Save Harvest Entry
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Configure Box Settings Modal ── -->
<div class="modal fade" id="configureBoxModal" tabindex="-1" aria-labelledby="configureBoxModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border border-secondary" style="background: var(--color-surface-solid); color: var(--color-text);">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title fw-bold" id="configureBoxModalLabel">
          <i class="fas fa-sliders me-2 text-primary"></i>Configure Incubator Box Settings
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="configureBoxForm">
        <div class="modal-body">
          <input type="hidden" id="config-box-id" value="box_a">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="config-box-name" class="form-label small">Custom Box Name</label>
              <input type="text" id="config-box-name" class="form-control form-control-sm" placeholder="e.g. Fruiting Chamber 1" required>
            </div>
            <div class="col-md-6">
              <label for="config-mushroom-type" class="form-label small">Mushroom Species</label>
              <select id="config-mushroom-type" class="form-select form-select-sm">
                <option value="oyster_white">Oyster Mushroom (White)</option>
                <option value="straw_mushroom">Straw Mushroom</option>
                <option value="milky_mushroom">Milky Mushroom</option>
                <option value="wood_ear">Wood Ear</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="config-stage" class="form-label small">Current Growth Stage</label>
              <select id="config-stage" class="form-select form-select-sm">
                <option value="incubation">Incubation Phase</option>
                <option value="fruiting">Fruiting Phase</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="config-misting-mode" class="form-label small">Misting Mode</label>
              <select id="config-misting-mode" class="form-select form-select-sm">
                <option value="auto">Automatic (Sensor-driven)</option>
                <option value="manual">Manual Relay Toggle</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="config-temp-min" class="form-label small">Min Temperature (°C)</label>
              <input type="number" id="config-temp-min" class="form-control form-control-sm" step="0.5" placeholder="e.g. 20.0">
            </div>
            <div class="col-md-6">
              <label for="config-temp-max" class="form-label small">Max Temperature (°C)</label>
              <input type="number" id="config-temp-max" class="form-control form-control-sm" step="0.5" placeholder="e.g. 28.0">
            </div>
            <div class="col-md-6">
              <label for="config-hum-min" class="form-label small">Min Humidity (%)</label>
              <input type="number" id="config-hum-min" class="form-control form-control-sm" step="1.0" placeholder="e.g. 80.0">
            </div>
            <div class="col-md-6">
              <label for="config-hum-max" class="form-label small">Max Humidity (%)</label>
              <input type="number" id="config-hum-max" class="form-control form-control-sm" step="1.0" placeholder="e.g. 95.0">
            </div>
          </div>
        </div>
        <div class="modal-footer border-top border-secondary">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary px-4">
            <i class="fas fa-save me-1"></i> Save Configuration
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── View Camera Snapshot Modal ── -->
<div class="modal fade" id="viewSnapshotModal" tabindex="-1" aria-labelledby="viewSnapshotModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content glass-card border border-secondary" style="background: var(--color-surface-solid); color: var(--color-text);">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title fw-bold" id="viewSnapshotModalLabel">
          <i class="fas fa-image me-2 text-danger"></i>Camera Snapshot Preview
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-3">
        <img id="snapshot-modal-img" src="" alt="Snapshot Full View" class="img-fluid rounded border border-secondary mb-3" style="max-height: 480px; width: auto;">
        <div class="d-flex justify-content-between align-items-center text-muted small px-2">
          <span id="snapshot-modal-time"><i class="far fa-clock me-1"></i>--</span>
          <span id="snapshot-modal-box"><i class="fas fa-box me-1"></i>Box A</span>
        </div>
        <p class="small text-muted mt-2 text-start mb-0" id="snapshot-modal-notes">--</p>
      </div>
      <div class="modal-footer border-top border-secondary">
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-delete-snapshot"><i class="fas fa-trash me-1"></i> Delete Snapshot</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Add New Incubator Box Modal ── -->
<div class="modal fade" id="createBoxModal" tabindex="-1" aria-labelledby="createBoxModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border border-secondary" style="background: var(--color-surface-solid); color: var(--color-text);">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title fw-bold" id="createBoxModalLabel">
          <i class="fas fa-box-open me-2 text-success"></i>Add New Incubator Box
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="createBoxForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="new-box-id" class="form-label small">Box Unique Identifier</label>
              <input type="text" id="new-box-id" class="form-control form-control-sm" placeholder="e.g. box_d" required>
              <small class="text-muted" style="font-size: 0.65rem;">Unique key for ESP32 telemetry (e.g. box_d)</small>
            </div>
            <div class="col-md-6">
              <label for="new-box-name" class="form-label small">Box Display Name</label>
              <input type="text" id="new-box-name" class="form-control form-control-sm" placeholder="e.g. Incubator Room D" required>
            </div>
            <div class="col-md-6">
              <label for="new-box-mushroom-type" class="form-label small">Selected Mushroom Species</label>
              <select id="new-box-mushroom-type" class="form-select form-select-sm" onchange="autoFillBoxTargets(this.value)" required>
                <option value="oyster_mushroom" selected>Oyster Mushroom (Pleurotus ostreatus)</option>
                <option value="straw_mushroom">Straw Mushroom (Volvariella volvacea)</option>
                <option value="milky_mushroom">Milky Mushroom (Calocybe indica)</option>
                <option value="wood_ear">Wood Ear (Auricularia auricula)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="new-box-stage" class="form-label small">Active Growth Phase</label>
              <select id="new-box-stage" class="form-select form-select-sm">
                <option value="incubation">Incubation Phase</option>
                <option value="fruiting" selected>Fruiting Phase</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="new-box-temp-min" class="form-label small">Min Temperature (°C)</label>
              <input type="number" id="new-box-temp-min" class="form-control form-control-sm" step="0.5" value="15.0">
            </div>
            <div class="col-md-6">
              <label for="new-box-temp-max" class="form-label small">Max Temperature (°C)</label>
              <input type="number" id="new-box-temp-max" class="form-control form-control-sm" step="0.5" value="24.0">
            </div>
            <div class="col-md-6">
              <label for="new-box-hum-min" class="form-label small">Min Humidity (%)</label>
              <input type="number" id="new-box-hum-min" class="form-control form-control-sm" step="1.0" value="80.0">
            </div>
            <div class="col-md-6">
              <label for="new-box-hum-max" class="form-label small">Max Humidity (%)</label>
              <input type="number" id="new-box-hum-max" class="form-control form-control-sm" step="1.0" value="95.0">
            </div>
          </div>
        </div>
        <div class="modal-footer border-top border-secondary">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success px-4">
            <i class="fas fa-plus me-1"></i> Add Incubator Box
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

</div><!-- /.page-wrapper -->

<!-- ── Fixed Footer ── -->
<footer class="site-footer" role="contentinfo">
    <div class="footer-inner">
        <div class="footer-left">
            <img src="<?php echo e($logoUrl); ?>" alt="KABUTECH Logo" width="22" height="22" class="footer-logo" decoding="async">
            <span class="footer-brand">KABUTECH</span>
        </div>
        <div class="footer-center">
            <span class="footer-copy">&copy; <?php echo date('Y'); ?> KabuTech IoT Monitoring System. All rights reserved.</span>
        </div>
        <div class="footer-right">
            <span class="footer-status">
                <span class="live-indicator" style="width:6px;height:6px;margin-right:4px;"></span>Live Monitoring
            </span>
        </div>
    </div>
</footer>


<script src="<?php echo e($base); ?>/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo e($base); ?>/vendor/chart.js/4.4.1/chart.umd.min.js"></script>
<script src="<?php echo e($base); ?>/js/dashboard.js"></script>
</body>
</html>
