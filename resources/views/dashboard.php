<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" id="csrf-token-meta" content="<?php echo e(csrf_token()); ?>">
    <title>Fiona's Mushroom Farm</title>
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
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo e($base); ?>/images/icon-192.png">
    <link rel="apple-touch-icon" href="<?php echo e($base); ?>/images/icon-192.png">
    <link rel="manifest" href="<?php echo e($base); ?>/manifest.json">
    <meta name="theme-color" content="#10b981">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="KabuTech">
    <link rel="preload" as="image" href="<?php echo e($logoUrl); ?>">
    <script>
    // Apply saved theme before first paint to avoid a flash of the wrong theme
    (function(){
        try {
            var t = localStorage.getItem('kabutech-theme');
            if (t === 'light' || t === 'dark') {
                document.documentElement.setAttribute('data-theme', t);
            }
        } catch (e) {}
    })();
    </script>
</head>
<body>

<div id="intro-overlay" class="intro-overlay" aria-live="polite">
    <div class="intro-content">
        <img src="<?php echo e($logoUrl); ?>" alt="" class="intro-logo" width="64" height="64" fetchpriority="high" decoding="async">
        <p class="intro-subtitle">KabuTech: An IoT-Based Mushroom Farm Monitoring System for Fiona's Mushroom Farm.</p>
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
            <img src="<?php echo e($logoUrl); ?>" alt="Fiona's Mushroom Farm Logo" class="navbar-logo" width="40" height="40" decoding="async">
            <span class="header-brand-name">Fiona's Mushroom Farm</span>
        </div>

        <!-- Right side controls -->
        <div class="header-actions">
            <!-- Install PWA App button (shown automatically when installable) -->
            <button class="header-icon-btn d-none" type="button" id="btn-install-pwa" aria-label="Install app" title="Install KabuTech App">
                <i class="fas fa-download text-success"></i>
            </button>
            <!-- Light / Dark mode toggle -->
            <button class="header-icon-btn theme-toggle-btn" type="button" id="themeToggleBtn" aria-label="Switch to light mode" title="Switch to light mode">
                <i class="fas fa-sun" id="themeToggleIcon"></i>
            </button>
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
                <div class="dropdown-menu dropdown-menu-end shadow p-0" style="min-width: 300px;" aria-labelledby="notifDropdown">
                    <div class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 border-bottom" style="border-color: var(--color-border) !important;">
                        <span class="small fw-semibold"><i class="fas fa-mobile-screen-button me-1 text-success"></i>Push Alerts</span>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size: 0.72rem;" id="btn-toggle-push">Enable</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 d-none" style="font-size: 0.72rem;" id="btn-test-push" title="Send a test push notification">Test</button>
                        </div>
                    </div>
                    <ul class="list-unstyled mb-0" style="max-height: 280px; overflow-y: auto;" id="notif-dropdown-menu">
                        <li class="px-3 py-2 small text-muted">Loading…</li>
                    </ul>
                </div>
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

<!-- ──Page Wrapper (sidebar + content) ── -->
<div class="page-wrapper" id="pageWrapper">

<!-- ── Offline Network Banner ── -->
<div id="offline-banner" class="offline-banner d-none" role="status" aria-live="polite">
    <i class="fas fa-wifi-slash me-2"></i>
    <span><strong>Offline Mode:</strong> Wi-Fi / network is disconnected. Showing last cached farm readings.</span>
</div>

<!-- ── Mobile Sidebar Backdrop ── -->
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

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
                    <span class="sidebar-icon"><i class="fas fa-video"></i></span>
                    <span class="sidebar-label">Live Camera</span>
                </a>
            </li>
        </ul>

    </nav>
</aside>

<!-- ── Main Content Area ── -->
<main class="main-content" id="mainContent">
<div class="container dashboard-container">
    <div class="dashboard-header text-center">
        <h1 class="dashboard-title">Welcome to KabuTech</h1>
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
            <span class="readings-divider">|</span>
            <span class="readings-item"><i class="fas fa-seedling me-2 mush-text"></i><strong>Mushroom Growth:</strong> <span id="header-growth-pct" class="mush-text fw-bold">--%</span></span>
            <span class="readings-divider">|</span>
            <span class="readings-item"><i class="fas fa-chart-line me-2 mush-text-caramel"></i><strong>Forecast Reliability:</strong> <span id="header-ml-conf" class="mush-text-cream fw-bold">--%</span></span>
        </div>
    </div>

    <!-- Overview (Dashboard page only) -->
    <div id="overview-section">
        <div class="row g-4 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card" id="temperature-card">
                <div class="stat-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="text-center">
                    <div class="stat-label">Temperature</div>
                    <div class="stat-value" id="temperature-value">--<span class="stat-unit">°C</span></div>
                    <div class="last-update" id="temp-update"></div>

                    <!-- Mini Trend Graph (Sparkline) -->
                    <div class="stat-sparkline-wrap">
                        <canvas id="temp-sparkline" height="52"></canvas>
                    </div>
                    <div class="stat-mini-metrics d-flex justify-content-between align-items-center">
                        <span class="stat-metric-pill" title="Lowest recorded temperature in window"><i class="fas fa-arrow-down text-info me-1"></i>Min: <strong id="temp-min-stat">--</strong>°C</span>
                        <span class="stat-metric-pill" id="temp-trend-pill" title="Temperature trend"><i class="fas fa-minus text-muted me-1" id="temp-trend-icon"></i><span id="temp-trend-stat">Steady</span></span>
                        <span class="stat-metric-pill" title="Highest recorded temperature in window"><i class="fas fa-arrow-up text-danger me-1"></i>Max: <strong id="temp-max-stat">--</strong>°C</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card" id="humidity-card">
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="text-center">
                    <div class="stat-label">Humidity</div>
                    <div class="stat-value" id="humidity-value">--<span class="stat-unit">%</span></div>
                    <div class="last-update" id="humidity-update"></div>

                    <!-- Mini Trend Graph (Sparkline) -->
                    <div class="stat-sparkline-wrap">
                        <canvas id="humidity-sparkline" height="52"></canvas>
                    </div>
                    <div class="stat-mini-metrics d-flex justify-content-between align-items-center">
                        <span class="stat-metric-pill" title="Lowest recorded humidity in window"><i class="fas fa-arrow-down text-warning me-1"></i>Min: <strong id="humidity-min-stat">--</strong>%</span>
                        <span class="stat-metric-pill" id="humidity-trend-pill" title="Humidity trend"><i class="fas fa-minus text-muted me-1" id="humidity-trend-icon"></i><span id="humidity-trend-stat">Steady</span></span>
                        <span class="stat-metric-pill" title="Highest recorded humidity in window"><i class="fas fa-arrow-up text-primary me-1"></i>Max: <strong id="humidity-max-stat">--</strong>%</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card" id="misting-card">
                <div class="stat-icon"><i class="fas fa-spray-can"></i></div>
                <div class="text-center">
                    <div class="stat-label">Misting System</div>
                    <div class="stat-value" id="misting-status" style="font-size: 1.8rem;">AUTO</div>
                    <span class="status-badge status-auto" id="misting-badge">Auto Ready</span>

                    <!-- Misting Mode Buttons: Auto & Manual -->
                    <div class="misting-mode-selector-wrapper mt-3 mb-2">
                        <div class="misting-mode-selector" role="group" aria-label="Misting Control Mode">
                            <button type="button" class="misting-mode-btn active" id="misting-mode-auto-btn" onclick="setMistingMode('auto')" title="Switch to Automatic (sensor-driven) misting">
                                <i class="fas fa-robot me-1"></i> Auto
                            </button>
                            <button type="button" class="misting-mode-btn" id="misting-mode-manual-btn" onclick="setMistingMode('manual')" title="Switch to Manual relay control">
                                <i class="fas fa-hand-pointer me-1"></i> Manual
                            </button>
                        </div>
                    </div>

                    <div class="d-flex flex-column align-items-center mt-1">
                        <button class="control-btn auto-standby" id="misting-btn" onclick="toggleMisting()" title="ESP32 maintains targets in Auto mode. Click to toggle in Manual mode.">
                            <i class="fas fa-robot me-2"></i>Auto Regulating
                        </button>
                        <div class="misting-mode-info">
                            <span class="d-block small text-muted mb-1">ESP32 control mode</span>
                            <span class="misting-auto-status" id="misting-auto-status">Loading…</span>
                        </div>
                    </div>
                    <div class="last-update" id="misting-update"></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card" id="fan-card">
                <div class="stat-icon"><i class="fas fa-fan"></i></div>
                <div class="text-center">
                    <div class="stat-label">Exhaust / FAE Fan</div>
                    <div class="stat-value" id="fan-status" style="font-size: 1.8rem;">AUTO</div>
                    <span class="status-badge status-auto" id="fan-badge">Auto Ready</span>

                    <!-- Fan Mode Buttons: Auto & Manual -->
                    <div class="misting-mode-selector-wrapper mt-3 mb-2">
                        <div class="misting-mode-selector" role="group" aria-label="Fan Control Mode">
                            <button type="button" class="misting-mode-btn active" id="fan-mode-auto-btn" onclick="setFanMode('auto')" title="Switch to Automatic (FAE + cooling) fan control">
                                <i class="fas fa-robot me-1"></i> Auto
                            </button>
                            <button type="button" class="misting-mode-btn" id="fan-mode-manual-btn" onclick="setFanMode('manual')" title="Switch to Manual fan relay control">
                                <i class="fas fa-hand-pointer me-1"></i> Manual
                            </button>
                        </div>
                    </div>

                    <div class="d-flex flex-column align-items-center mt-1">
                        <button class="control-btn auto-standby" id="fan-btn" onclick="toggleFan()" title="ESP32 manages FAE & cooling in Auto mode. Click to toggle in Manual mode.">
                            <i class="fas fa-robot me-2"></i>Auto Regulating
                        </button>
                        <div class="misting-mode-info">
                            <span class="d-block small text-muted mb-1">ESP32 fan control</span>
                            <span class="misting-auto-status" id="fan-auto-status">Loading…</span>
                        </div>
                    </div>
                    <div class="last-update" id="fan-update"></div>
                </div>
            </div>
        </div>
        </div>

        <!-- Real-Time Temperature & Humidity Environmental Trends Graph -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card glass-card environmental-trends-card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold">
                                <i class="fas fa-chart-line me-2 mush-text"></i>Real-Time Temperature &amp; Humidity Trends
                            </span>
                            <span class="badge mush-badge is-good" id="dash-chart-sync-badge">
                                <span class="live-indicator" style="width:6px;height:6px;margin-right:4px;"></span>Live Sync
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <!-- Display View: Split (Both) / Graph Only / Logs Only -->
                            <div class="btn-group btn-group-sm" role="group" aria-label="Display mode">
                                <button type="button" class="btn btn-outline-secondary active" id="btn-view-both" onclick="setDashboardViewMode('both')" title="Show graph and text logs together">
                                    <i class="fas fa-table-columns me-1"></i>Split
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-view-graph" onclick="setDashboardViewMode('graph')" title="Show graph only">
                                    <i class="fas fa-chart-line me-1"></i>Graph
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-view-logs" onclick="setDashboardViewMode('logs')" title="Show text logs table only">
                                    <i class="fas fa-list-ul me-1"></i>Text Logs
                                </button>
                            </div>
                            <!-- View Filter -->
                            <div class="btn-group btn-group-sm" role="group" aria-label="Metric toggle">
                                <button type="button" class="btn btn-outline-secondary active" id="btn-chart-both" onclick="setDashboardChartMode('both')">
                                    <i class="fas fa-layer-group me-1"></i>Both
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-chart-temp" onclick="setDashboardChartMode('temp')">
                                    <i class="fas fa-thermometer-half me-1 text-danger"></i>Temp
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-chart-hum" onclick="setDashboardChartMode('hum')">
                                    <i class="fas fa-tint me-1 text-info"></i>Humidity
                                </button>
                            </div>
                            <!-- Point Limit -->
                            <div class="btn-group btn-group-sm" role="group" aria-label="Point limit">
                                <button type="button" class="btn btn-outline-secondary active" id="btn-limit-15" onclick="setDashboardChartLimit(15)">15 pts</button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-limit-30" onclick="setDashboardChartLimit(30)">30 pts</button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-limit-50" onclick="setDashboardChartLimit(50)">50 pts</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Metric Summary Highlights -->
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-lg-3">
                                <div class="p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-10 d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10" style="width:36px; height:36px; min-width:36px;">
                                        <i class="fas fa-thermometer-half text-danger"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="small text-muted text-truncate">Temp Window (Min / Max)</div>
                                        <div class="fw-bold mush-text-cream" id="dash-chart-temp-minmax">-- / -- °C</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-10 d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-10" style="width:36px; height:36px; min-width:36px;">
                                        <i class="fas fa-tint text-info"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="small text-muted text-truncate">Humidity Window (Min / Max)</div>
                                        <div class="fw-bold mush-text-cream" id="dash-chart-hum-minmax">-- / -- %</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-10 d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10" style="width:36px; height:36px; min-width:36px;">
                                        <i class="fas fa-crosshairs text-warning"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="small text-muted text-truncate">Ideal Fruiting Temp</div>
                                        <div class="fw-bold text-warning" id="dash-chart-target-temp">22 – 28 °C</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-10 d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10" style="width:36px; height:36px; min-width:36px;">
                                        <i class="fas fa-droplet text-success"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="small text-muted text-truncate">Ideal Fruiting Humidity</div>
                                        <div class="fw-bold text-success" id="dash-chart-target-hum">80 – 95 %</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Environmental Trend Chart -->
                        <div class="dashboard-chart-wrap" id="dash-chart-container">
                            <canvas id="dashboard-th-chart"></canvas>
                        </div>

                        <!-- Live Temperature & Humidity Telemetry Logs (Simplified) -->
                        <div class="dashboard-logs-wrap mt-3" id="dash-logs-container">
                            <div class="d-flex justify-content-between align-items-center mb-2 px-1 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold text-light" style="font-size: 0.88rem;">
                                        <i class="fas fa-table-list me-1 text-success"></i>Sensor Readings Log
                                    </span>
                                    <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-25" id="dash-interval-badge" style="font-size: 0.7rem;">Live Readings</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <label for="dash-interval-select" class="small text-muted mb-0">Average:</label>
                                    <select id="dash-interval-select" class="form-select form-select-sm py-0" style="width: auto; font-size: 0.76rem;">
                                        <option value="raw" selected>Live (Every 5s)</option>
                                        <option value="5m">5 Mins Average</option>
                                        <option value="30m">30 Mins Average</option>
                                        <option value="1h">1 Hour Average</option>
                                        <option value="1d">1 Day Average</option>
                                        <option value="1w">1 Week Average</option>
                                        <option value="1mo">1 Month Average</option>
                                    </select>
                                    <a href="#" onclick="showSection('history'); setSidebarActive(document.getElementById('sidebar-history')); return false;" class="small text-decoration-none mush-link" style="font-size: 0.74rem;">
                                        Full Sensor Data <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="table-responsive rounded border border-secondary border-opacity-20" style="max-height: 290px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <thead class="sticky-top" style="background: rgba(18, 20, 28, 0.96); backdrop-filter: blur(8px);">
                                        <tr>
                                            <th scope="col">Time / Period</th>
                                            <th scope="col"><i class="fas fa-thermometer-half me-1 text-danger"></i>Temperature (°C)</th>
                                            <th scope="col"><i class="fas fa-tint me-1 text-info"></i>Humidity (%)</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashboard-history-body">
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Loading sensor readings...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Grow: species, targets, harvest prediction -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card glass-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold"><i class="fas fa-seedling me-2 mush-text"></i>Crop Growth & Harvest Forecast</span>
                        <span class="badge mush-badge is-good" id="ml-badge-status" style="font-size:0.75rem;"><i class="fas fa-seedling me-1"></i> Smart Monitor Active</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:0.75rem;" data-bs-toggle="collapse" data-bs-target="#overviewManualSensorCollapse" aria-expanded="false" aria-controls="overviewManualSensorCollapse">
                            <i class="fas fa-temperature-half me-1"></i> Manual Temp/Humidity
                        </button>
                        <button type="button" class="btn btn-sm btn-success py-0 px-2" style="font-size:0.75rem;" data-bs-toggle="collapse" data-bs-target="#overviewBatchCollapse" aria-expanded="false" aria-controls="overviewBatchCollapse">
                            <i class="fas fa-plus me-1"></i> Register Batch
                        </button>
                        <button type="button" class="btn btn-sm py-0 px-2 mush-link" style="font-size:0.75rem; border: 1px solid var(--mush-border); background: rgba(16, 185, 129, 0.1);" data-bs-toggle="modal" data-bs-target="#mlModelModal"><i class="fas fa-chart-line me-1"></i> Technical Metrics</button>
                        <small class="text-muted">Auto climate targets</small>
                    </div>
                </div>
                <div class="collapse" id="overviewManualSensorCollapse">
                    <div class="card-body border-bottom border-secondary" style="background: rgba(16, 185, 129, 0.05);">
                        <form id="overviewManualSensorForm" class="row g-2 align-items-end">
                            <div class="col-md-4 col-sm-6">
                                <label for="manual-temp-overview" class="form-label small mb-1"><i class="fas fa-thermometer-half text-danger me-1"></i>Temperature (°C)</label>
                                <input type="number" id="manual-temp-overview" class="form-control form-control-sm" step="0.1" min="-10" max="60" placeholder="e.g. 24.5" required>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <label for="manual-hum-overview" class="form-label small mb-1"><i class="fas fa-tint text-info me-1"></i>Humidity (%)</label>
                                <input type="number" id="manual-hum-overview" class="form-control form-control-sm" step="0.1" min="0" max="100" placeholder="e.g. 88.0" required>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="manual-box-overview" class="form-label small mb-1">Incubator Box</label>
                                <select id="manual-box-overview" class="form-select form-select-sm">
                                    <option value="box_a">Incubator Box A</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-check me-1"></i> Save Reading</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="collapse" id="overviewBatchCollapse">
                    <div class="card-body border-bottom border-secondary" style="background: rgba(16, 185, 129, 0.05);">
                        <form id="overviewBatchForm" class="row g-2 align-items-end">
                            <div class="col-md-3 col-sm-6">
                                <label for="overview-batch-name" class="form-label small mb-1">Batch ID/Name</label>
                                <input type="text" id="overview-batch-name" class="form-control form-control-sm" placeholder="e.g. Oyster Batch B" required>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="overview-batch-type" class="form-label small mb-1">Mushroom Type</label>
                                <select id="overview-batch-type" class="form-select form-select-sm">
                                    <option value="oyster_white">White Oyster Mushroom</option>
                                    <option value="oyster_brown">Brown Oyster Mushroom</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="overview-batch-incubator" class="form-label small mb-1">Incubator Box</label>
                                <select id="overview-batch-incubator" class="form-select form-select-sm">
                                    <option value="box_a">Incubator Box A</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="overview-batch-bags" class="form-label small mb-1">Bags Count (Max 5)</label>
                                <input type="number" id="overview-batch-bags" class="form-control form-control-sm" min="1" max="5" value="5" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-check me-1"></i> Register</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="mushroom-type-select" class="form-label small mb-1">Mushroom type</label>
                            <select class="form-select form-select-sm" id="mushroom-type-select">
                                <option value="oyster_white">White Oyster Mushroom</option>
                                <option value="oyster_brown">Brown Oyster Mushroom</option>
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

                    <!-- Mushroom Growth Progress & Harvest Forecast Banner -->
                    <div class="ml-analytics-banner mush-banner p-3 rounded-3 mb-3">
                        <div class="row g-3 align-items-center">
                            <!-- Left: Mushroom Growth Progress % -->
                            <div class="col-lg-6 border-end-lg border-secondary border-opacity-25 pe-lg-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <div style="min-width: 0;">
                                        <span class="mush-section-title">
                                            <i class="fas fa-seedling me-1 mush-text"></i>Growth Progress
                                        </span>
                                        <div class="d-flex flex-wrap align-items-baseline gap-2 mt-1">
                                            <span class="mush-big-number mb-0" id="ml-main-growth-pct">0%</span>
                                            <span class="badge mush-badge is-idle" id="ml-main-growth-phase">Awaiting Phase</span>
                                        </div>
                                    </div>
                                    <div class="text-sm-end">
                                        <span class="small text-muted d-block" id="ml-growth-days-detail">—</span>
                                        <span class="badge mush-badge is-idle" id="ml-growth-maturity-badge" style="font-size: 0.68rem;">Standby</span>
                                    </div>
                                </div>
                                <!-- Growth Progress Bar -->
                                <div class="progress mush-progress">
                                    <div id="ml-main-growth-bar" class="mush-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex flex-wrap justify-content-between gap-1 text-muted mt-2" style="font-size: 0.75rem;">
                                    <span id="ml-growth-phase-timer" class="mush-text-cream"><i class="fas fa-clock me-1 mush-text"></i>Start incubation or fruiting to track</span>
                                    <span id="ml-growth-velocity-tag" class="mush-helper"><i class="fas fa-gauge-high me-1 mush-text"></i>Growth Pace: Normal (1.0x)</span>
                                </div>
                            </div>

                            <!-- Right: Harvest Forecast & Reliability -->
                            <div class="col-lg-6 ps-lg-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <div style="min-width: 0;">
                                        <span class="mush-section-title">
                                            <i class="fas fa-calendar-check me-1 mush-text"></i>Harvest Forecast & Reliability
                                        </span>
                                        <div class="d-flex flex-wrap align-items-baseline gap-2 mt-1">
                                            <span class="mush-big-number mb-0" id="ml-main-conf-pct">86%</span>
                                            <span class="badge mush-badge is-good" id="ml-main-conf-label">High Confidence</span>
                                        </div>
                                    </div>
                                    <div class="text-sm-end">
                                        <span class="small text-muted d-block">Data Accuracy</span>
                                        <span class="fw-bold mush-text-cream" id="ml-main-r2-pct">86% Fit</span>
                                    </div>
                                </div>
                                <!-- Reliability Bar -->
                                <div class="progress mush-progress">
                                    <div id="ml-main-conf-bar" class="mush-bar is-reliability" role="progressbar" style="width: 86%;" aria-valuenow="86" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex flex-wrap justify-content-between gap-1 text-muted mt-2" style="font-size: 0.75rem;">
                                    <span id="ml-pred-harvest-date" class="mush-text-cream"><i class="fas fa-calendar-check me-1 mush-text"></i>Expected Harvest: Ready when fruiting starts</span>
                                    <span id="ml-model-algo-tag" role="button" data-bs-toggle="modal" data-bs-target="#mlModelModal" class="mush-link" title="View technical statistics for evaluation/panelists"><i class="fas fa-chart-line me-1"></i>View Technical Model</span>
                                </div>
                            </div>
                        </div>

                        <!-- Environmental Efficiency Metrics Bar -->
                        <div class="row g-2 pt-2 mt-2 border-top border-secondary border-opacity-25 align-items-center">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-10">
                                    <span class="small mush-text-cream">
                                        <i class="fas fa-temperature-three-quarters me-1 text-danger"></i>Temp Efficiency
                                    </span>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress mush-progress" style="width: 55px; height: 6px;">
                                            <div id="ml-temp-efficiency-bar" class="mush-bar" role="progressbar" style="width: 0%;"></div>
                                        </div>
                                        <span id="ml-temp-efficiency" class="fw-bold small mush-text-cream">--%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-10">
                                    <span class="small mush-text-cream">
                                        <i class="fas fa-droplet me-1 text-primary"></i>Humidity Efficiency
                                    </span>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress mush-progress" style="width: 55px; height: 6px;">
                                            <div id="ml-hum-efficiency-bar" class="mush-bar" role="progressbar" style="width: 0%;"></div>
                                        </div>
                                        <span id="ml-hum-efficiency" class="fw-bold small mush-text-cream">--%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-10">
                                    <span class="small mush-text-cream">
                                        <i class="fas fa-leaf me-1 text-success"></i>Climate Efficiency
                                    </span>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress mush-progress" style="width: 55px; height: 6px;">
                                            <div id="ml-env-efficiency-bar" class="mush-bar is-good" role="progressbar" style="width: 0%;"></div>
                                        </div>
                                        <span id="ml-env-efficiency" class="fw-bold small text-success">--%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 small">
                        <!-- Card 1: Fruiting Climate -->
                        <div class="col-md-6">
                            <div class="mush-qa">
                                <div class="mush-qa-q"><i class="fas fa-cloud-sun me-1 mush-text"></i> Ideal Climate for Fruiting</div>
                                <p class="mush-qa-a" id="grow-targets-text">—</p>
                            </div>
                        </div>

                        <!-- Card 2: Incubation Timeline -->
                        <div class="col-md-6">
                            <div class="mush-qa">
                                <div class="mush-qa-q"><i class="fas fa-hourglass-start me-1 mush-text"></i> Incubation Timeline</div>
                                <p class="mush-qa-a" id="grow-incubation-text">Click “Start incubation” when your bags are prepared.</p>
                            </div>
                        </div>

                        <!-- Card 3: Incubation Climate -->
                        <div class="col-md-6">
                            <div class="mush-qa">
                                <div class="mush-qa-q"><i class="fas fa-shield-virus me-1 mush-text"></i> Ideal Climate for Incubation (Spawn Run)</div>
                                <p class="mush-qa-a" id="grow-incubation-targets-text">—</p>
                            </div>
                        </div>

                        <!-- Card 4: Harvest Timeline -->
                        <div class="col-md-6">
                            <div class="mush-qa">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="mush-qa-q mb-0"><i class="fas fa-basket-shopping me-1 mush-text"></i> Harvest Timeline</div>
                                    <span class="badge mush-badge is-fruiting px-2 py-0 d-none" id="ml-days-left-badge" style="font-size:0.7rem;">—</span>
                                </div>
                                <p class="mush-qa-a mb-2" id="grow-fruiting-text">Keep conditions on target for an estimate.</p>
                                <p class="mush-qa-a" id="grow-prediction-text">Click “Start fruiting” once pinheads form on your bags.</p>

                                <!-- Growth Progress Indicator -->
                                <div id="ml-prediction-progress-container" class="mt-2 p-2 rounded mush-banner d-none">
                                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:0.75rem;">
                                        <span class="mush-text-cream"><i class="fas fa-seedling me-1 mush-text"></i>Growth Completion</span>
                                        <span class="fw-semibold mush-text-cream" id="ml-growth-pct">0%</span>
                                    </div>
                                    <div class="progress mush-progress" style="height: 6px;">
                                        <div id="ml-growth-progress-bar" class="mush-bar" role="progressbar" style="width: 0%;"></div>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted mt-1" style="font-size:0.68rem;">
                                        <span id="ml-growth-velocity" class="mush-text-cream"><i class="fas fa-gauge-high me-1 mush-text"></i>Growth Pace: 1.0x</span>
                                        <span id="ml-confidence-badge" class="mush-text-cream"><i class="fas fa-shield-halved me-1 mush-text"></i>Reliability: 85%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                        <span class="fw-semibold">Sensor Data</span>
                        <small class="text-muted d-block" id="history-subtitle">Simplified temperature &amp; humidity readings</small>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-success py-1 px-2" data-bs-toggle="collapse" data-bs-target="#historyManualSensorCollapse" aria-expanded="false" aria-controls="historyManualSensorCollapse">
                            <i class="fas fa-plus me-1"></i> Log Manual Reading
                        </button>
                        <label for="history-interval-select" class="form-label small text-muted mb-0 text-nowrap">Average</label>
                        <select id="history-interval-select" class="form-select form-select-sm" style="width: auto;">
                            <option value="raw" selected>Live (Every 5s)</option>
                            <option value="5m">5 Mins Avg</option>
                            <option value="30m">30 Mins Avg</option>
                            <option value="1h">1 Hour Avg</option>
                            <option value="1d">1 Day Avg</option>
                            <option value="1w">1 Week Avg</option>
                            <option value="1mo">1 Month Avg</option>
                        </select>
                        <label for="history-limit-select" class="form-label small text-muted mb-0 text-nowrap">Rows</label>
                        <select id="history-limit-select" class="form-select form-select-sm form-select-sm-inline">
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
                <div class="collapse border-bottom border-secondary" id="historyManualSensorCollapse">
                    <div class="card-body" style="background: rgba(16, 185, 129, 0.05);">
                        <form id="historyManualSensorForm" class="row g-2 align-items-end">
                            <div class="col-md-4 col-sm-6">
                                <label for="manual-temp-history" class="form-label small mb-1"><i class="fas fa-thermometer-half text-danger me-1"></i>Temperature (°C)</label>
                                <input type="number" id="manual-temp-history" class="form-control form-control-sm" step="0.1" min="-10" max="60" placeholder="e.g. 24.5" required>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <label for="manual-hum-history" class="form-label small mb-1"><i class="fas fa-tint text-info me-1"></i>Humidity (%)</label>
                                <input type="number" id="manual-hum-history" class="form-control form-control-sm" step="0.1" min="0" max="100" placeholder="e.g. 88.0" required>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="manual-box-history" class="form-label small mb-1">Incubator Box</label>
                                <select id="manual-box-history" class="form-select form-select-sm">
                                    <option value="box_a">Incubator Box A</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-check me-1"></i> Save Reading</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="p-3">
                        <!-- Simplified Period Average Summary Cards -->
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-3">
                                <div class="p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-15 text-center">
                                    <div class="small text-muted">Avg Temperature</div>
                                    <div class="fw-bold text-danger fs-5" id="history-avg-temp">-- °C</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-15 text-center">
                                    <div class="small text-muted">Avg Humidity</div>
                                    <div class="fw-bold text-info fs-5" id="history-avg-hum">-- %</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-15 text-center">
                                    <div class="small text-muted">Temp Range (Min / Max)</div>
                                    <div class="fw-semibold" id="history-minmax-temp">-- / -- °C</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-15 text-center">
                                    <div class="small text-muted">Humidity Range (Min / Max)</div>
                                    <div class="fw-semibold" id="history-minmax-hum">-- / -- %</div>
                                </div>
                            </div>
                        </div>

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
                            <thead class="sticky-top" style="background: rgba(18, 20, 28, 0.96);">
                                <tr>
                                    <th scope="col">Time / Period</th>
                                    <th scope="col">Temperature (°C)</th>
                                    <th scope="col">Humidity (%)</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="history-body">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Waiting for sensor data...</td>
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
                                    <option value="oyster_white">White Oyster Mushroom</option>
                                    <option value="oyster_brown">Brown Oyster Mushroom</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Assign Incubator</label>
                                <select id="batch-incubator" class="form-select form-select-sm">
                                    <option value="incubator_a">Incubator Room A</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Bags Count (Max 5)</label>
                                <input type="number" id="batch-bags" class="form-control form-control-sm" min="1" max="5" value="5" required>
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
                                    <th>Grow Climate &amp; ML Target</th>
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

    <!-- Live Camera Monitoring & Snapshots Gallery Section -->
    <div class="row g-4 mt-4 d-none" id="camera-gallery-section">
        <div class="col-12">
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold"><i class="fas fa-video me-2 text-success"></i>Live Camera Monitoring</span>
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
                             data-src="<?php echo e($cameraSrc); ?>"
                             alt="ESP32-CAM Live Stream"
                             class="camera-stream-img"
                             style="display:none;"
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
    </div><!-- /#camera-gallery-section -->

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
              <select id="harvest-mushroom-type" class="form-select form-select-sm" required onchange="updateHarvestModalBestClimate()">
                <option value="oyster_white">White Oyster Mushroom</option>
                <option value="oyster_brown">Brown Oyster Mushroom</option>
              </select>
            </div>

            <!-- Best Climate for ML Model Training Guidance Card -->
            <div class="col-12">
              <div class="p-2 rounded mush-banner border border-secondary border-opacity-25" id="harvest-best-climate-card">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="small fw-semibold mush-text-cream">
                    <i class="fas fa-bullseye text-warning me-1"></i> Best Climate for ML Model Training
                  </span>
                  <span class="badge mush-badge is-good" id="harvest-best-species-badge" style="font-size:0.7rem;">White Oyster Baseline</span>
                </div>
                <div class="d-flex flex-wrap gap-3 small text-muted" style="font-size:0.75rem;">
                  <span><i class="fas fa-thermometer-half text-danger me-1"></i>Best Temp: <strong class="mush-text-cream" id="harvest-modal-best-temp">20 – 24 °C</strong></span>
                  <span><i class="fas fa-droplet text-info me-1"></i>Best Humidity: <strong class="mush-text-cream" id="harvest-modal-best-hum">85 – 95 % RH</strong></span>
                  <span><i class="fas fa-dna text-success me-1"></i>Base Temp ($T_{base}$): <strong class="mush-text-cream">10.0 °C</strong></span>
                </div>
                <small class="text-muted d-block mt-1" style="font-size:0.7rem;">
                  <i class="fas fa-info-circle me-1"></i> Logging batch temperature and humidity directly trains the ML thermal growth model against environmental stress deviation.
                </small>
              </div>
            </div>

            <div class="col-md-4">
              <label for="harvest-flush-number" class="form-label small">Flush Number</label>
              <input type="number" id="harvest-flush-number" class="form-control form-control-sm" min="1" max="10" value="1" required>
            </div>
            <div class="col-md-4">
              <label for="harvest-weight-grams" class="form-label small">Yield Weight (Grams)</label>
              <input type="number" id="harvest-weight-grams" class="form-control form-control-sm" step="1" min="1" placeholder="e.g. 1850" required>
            </div>
            <div class="col-md-4">
              <label for="harvest-substrate-weight" class="form-label small">Substrate Weight (g)</label>
              <input type="number" id="harvest-substrate-weight" class="form-control form-control-sm" step="1" min="0" placeholder="e.g. 4000 (4–5 bags)">
            </div>

            <!-- Observed Temperature & Humidity for ML Model -->
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label for="harvest-temperature" class="form-label small mb-0">Batch Avg Temp (°C)</label>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size:0.7rem;" onclick="useCurrentTelemetryForHarvest('temp')">
                  <i class="fas fa-wand-magic-sparkles me-1 text-warning"></i>Use Live
                </button>
              </div>
              <input type="number" id="harvest-temperature" class="form-control form-control-sm" step="0.1" min="0" max="50" placeholder="e.g. 23.5">
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label for="harvest-humidity" class="form-label small mb-0">Batch Avg Humidity (%)</label>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size:0.7rem;" onclick="useCurrentTelemetryForHarvest('hum')">
                  <i class="fas fa-wand-magic-sparkles me-1 text-info"></i>Use Live
                </button>
              </div>
              <input type="number" id="harvest-humidity" class="form-control form-control-sm" step="0.1" min="0" max="100" placeholder="e.g. 88.0">
            </div>

            <div class="col-12">
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
                <option value="oyster_white">White Oyster Mushroom</option>
                <option value="oyster_brown">Brown Oyster Mushroom</option>
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
                <option value="oyster_white" selected>White Oyster Mushroom</option>
                <option value="oyster_brown">Brown Oyster Mushroom</option>
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

<!-- ── Machine Learning Prediction Model Metrics Modal ── -->
<div class="modal fade" id="mlModelModal" tabindex="-1" aria-labelledby="mlModelModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content glass-card border border-secondary" style="background: var(--color-surface-solid); color: var(--color-text);">
      <div class="modal-header border-bottom border-secondary">
        <div>
          <h5 class="modal-title fw-bold" id="mlModelModalLabel">
            <i class="fas fa-brain me-2 text-success"></i>Machine Learning Growth & Harvest Model
          </h5>
          <small class="text-muted">Thermal-Time Developmental Degree-Day Regression & Bayesian Ridge regularizer</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <!-- Model Architecture & Formula Overview -->
        <div class="p-3 rounded border border-secondary border-opacity-25 bg-dark bg-opacity-25 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold small text-info"><i class="fas fa-square-root-variable me-1"></i>Regression Formula</span>
            <span class="badge bg-success-subtle text-success border border-success" id="modal-ml-status-badge">Trained Model Active</span>
          </div>
          <div class="font-monospace small text-light p-2 rounded bg-black bg-opacity-50 border border-secondary border-opacity-25 text-center">
            &Ycirc;<sub>days</sub> = &beta;<sub>0</sub> + &beta;<sub>temp</sub> &times; (T<sub>avg</sub> - T<sub>opt</sub>) + &beta;<sub>hum</sub> &times; max(0, H<sub>min</sub> - H<sub>avg</sub>) + &beta;<sub>flush</sub> &times; (Flush - 1)
          </div>
          <p class="small text-muted mt-2 mb-0" style="font-size:0.75rem;">
            Physiological base temperature threshold: <strong>10.0 °C</strong>. When temperatures deviate from optimal, cell division rate shifts non-linearly. L2 Regularization parameter (&lambda; = 0.5) applies Empirical Bayesian shrinkage against historical harvest batches.
          </p>
        </div>

        <!-- Real-Time Telemetry Impact Breakdown -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="card h-100 border border-secondary border-opacity-25 bg-dark bg-opacity-10 p-3">
              <span class="fw-semibold small text-primary mb-2"><i class="fas fa-tower-broadcast me-1"></i>Active Telemetry Ingestion</span>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Observed Avg Temperature</span>
                <span class="fw-semibold" id="modal-telemetry-temp">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Temperature Shift Effect</span>
                <span class="fw-semibold" id="modal-telemetry-temp-adj">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Observed Avg Humidity</span>
                <span class="fw-semibold" id="modal-telemetry-hum">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Humidity Lag Penalty</span>
                <span class="fw-semibold" id="modal-telemetry-hum-adj">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Temperature Efficiency</span>
                <span class="fw-semibold text-danger" id="modal-temp-efficiency">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Humidity Efficiency</span>
                <span class="fw-semibold text-primary" id="modal-hum-efficiency">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1">
                <span class="text-muted">Climate Efficiency Index</span>
                <span class="fw-semibold text-success" id="modal-env-efficiency">—</span>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card h-100 border border-secondary border-opacity-25 bg-dark bg-opacity-10 p-3">
              <span class="fw-semibold small text-success mb-2"><i class="fas fa-gauge-high me-1"></i>Predictive Performance</span>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Coefficient of Fit (R&sup2;)</span>
                <span class="fw-semibold text-success" id="modal-model-r2">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Mean Absolute Error (MAE)</span>
                <span class="fw-semibold" id="modal-model-mae">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1 border-bottom border-secondary border-opacity-10">
                <span class="text-muted">Training Harvest Batches</span>
                <span class="fw-semibold" id="modal-model-samples">—</span>
              </div>
              <div class="d-flex justify-content-between small py-1">
                <span class="text-muted">Growth Rate Velocity</span>
                <span class="fw-semibold text-info" id="modal-model-velocity">—</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Trained Model Coefficients Table -->
        <span class="fw-semibold small text-light d-block mb-2"><i class="fas fa-table-columns me-1"></i>Trained Species Parameters</span>
        <div class="table-responsive">
          <table class="table table-sm table-bordered text-center align-middle" style="font-size:0.75rem; border-color: rgba(255,255,255,0.1);">
            <thead class="table-dark">
              <tr>
                <th>Species Profile</th>
                <th>Base Days (&beta;<sub>0</sub>)</th>
                <th>Temp Shift (&beta;<sub>temp</sub>)</th>
                <th>Hum Lag (&beta;<sub>hum</sub>)</th>
                <th>Flush Lag (&beta;<sub>flush</sub>)</th>
                <th>Fit (R&sup2;)</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="modal-species-params-table">
              <tr>
                <td colspan="7" class="text-muted py-2"><i class="fas fa-spinner fa-spin me-1"></i> Loading trained model parameters...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer border-top border-secondary">
        <button type="button" class="btn btn-sm btn-outline-info" id="btn-refresh-ml-metrics"><i class="fas fa-arrows-rotate me-1"></i> Recalculate Model</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

</div><!-- /.page-wrapper -->

<!-- ── Fixed Footer ── -->
<footer class="site-footer" role="contentinfo">
    <div class="footer-inner">
        <div class="footer-left">
            <img src="<?php echo e($logoUrl); ?>" alt="Fiona's Mushroom Farm Logo" width="22" height="22" class="footer-logo" decoding="async">
            <span class="footer-brand">Fiona's Mushroom Farm</span>
        </div>
        <div class="footer-center">
            <span class="footer-copy">&copy; <?php echo date('Y'); ?> Fiona's Mushroom Farm. All rights reserved.</span>
        </div>
        <div class="footer-right">
            <span class="footer-status">
                <span class="live-indicator" style="width:6px;height:6px;margin-right:4px;"></span>Live Monitoring
            </span>
        </div>
    </div>
</footer>


<!-- ── Mobile Bottom Navigation Bar ── -->
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile navigation">
    <button type="button" class="mobile-nav-item active" data-section="overview" onclick="mobileNavTo('overview', 'sidebar-dashboard', this)">
        <i class="fas fa-gauge-high"></i>
        <span>Home</span>
    </button>
    <button type="button" class="mobile-nav-item" data-section="history" onclick="mobileNavTo('history', 'sidebar-history', this)">
        <i class="fas fa-table"></i>
        <span>Data</span>
    </button>
    <button type="button" class="mobile-nav-item" data-section="incubator" onclick="mobileNavTo('incubator', 'sidebar-incubator', this)">
        <i class="fas fa-box-open"></i>
        <span>Incubator</span>
    </button>
    <button type="button" class="mobile-nav-item" data-section="diagnostics" onclick="mobileNavTo('diagnostics', 'sidebar-diagnostics', this)">
        <i class="fas fa-microchip"></i>
        <span>Health</span>
    </button>
    <button type="button" class="mobile-nav-item" onclick="toggleMobileSidebar(true)">
        <i class="fas fa-bars"></i>
        <span>More</span>
    </button>
</nav>

<script src="<?php echo e($base); ?>/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo e($base); ?>/vendor/chart.js/4.4.1/chart.umd.min.js"></script>
<script>window.KABUTECH_BASE_URL = <?php echo json_encode($base); ?>;</script>
<script src="<?php echo e($base); ?>/js/dashboard.js"></script>
</body>
</html>
