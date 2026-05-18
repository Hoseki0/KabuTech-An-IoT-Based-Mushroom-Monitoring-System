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
    $bgUrl = $base . '/images/mushroom-bg.jpg';
    $logoUrl = $base . '/images/mushroom-logo.jpg';
    ?>
    <link href="<?php echo e($base); ?>/vendor/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e($base); ?>/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e($base); ?>/css/dashboard.css">
    <link rel="preload" as="image" href="<?php echo e($bgUrl); ?>">
    <link rel="preload" as="image" href="<?php echo e($logoUrl); ?>">
    <style>:root { --bg-image: url('<?php echo e($bgUrl); ?>'); }</style>
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

<nav class="navbar navbar-dark navbar-expand-lg">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1 d-flex align-items-center">
            <img src="<?php echo e($logoUrl); ?>" alt="KABUTECH: Mushroom Monitoring System" class="navbar-logo me-2" width="42" height="42" decoding="async">
            Mushroom Monitoring System
        </span>
        <!-- WiFi + Notification always visible beside the hamburger on mobile -->
        <div class="d-flex align-items-center gap-2 ms-auto me-2 d-lg-none">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light position-relative" type="button" id="notifDropdownMobile" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Alerts">
                    <i class="fas fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notif-badge-mobile">0</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 280px; max-height: 320px; overflow-y: auto;" aria-labelledby="notifDropdownMobile" id="notif-dropdown-menu-mobile">
                    <li class="px-3 py-2 small text-muted">Loading…</li>
                </ul>
            </div>
            <div class="connection-status disconnected" id="connection-status-mobile" aria-label="Connection status" title="Connecting...">
                <i class="fas fa-wifi"></i>
            </div>
        </div>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu" aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarMenu">
            <div class="d-flex align-items-center ms-lg-auto">
                <!-- WiFi + Notification only on desktop (lg+) -->
                <div class="dropdown me-2 my-2 my-lg-0 d-none d-lg-block">
                    <button class="btn btn-sm btn-outline-light position-relative" type="button" id="notifDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Alerts">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notif-badge">0</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 280px; max-height: 320px; overflow-y: auto;" aria-labelledby="notifDropdown" id="notif-dropdown-menu">
                        <li class="px-3 py-2 small text-muted">Loading…</li>
                    </ul>
                </div>
                <div class="connection-status disconnected me-2 my-2 my-lg-0 d-none d-lg-flex" id="connection-status" aria-label="Connection status" title="Connecting...">
                    <i id="connection-icon" class="fas fa-wifi"></i>
                </div>
                <ul class="navbar-nav mb-2 mb-lg-0 align-items-lg-center">
                    <?php if (!auth()->check() || !auth()->user()->isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="#" id="nav-dashboard" onclick="showSection('overview'); return false;">
                            <i class="fas fa-gauge-high me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="nav-history" onclick="showSection('history'); return false;">
                            <i class="fas fa-table me-1"></i> Sensor Data
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (auth()->check()): ?>
                    <li class="nav-item">
                        <span class="nav-link disabled small text-white-50 py-lg-2"><?php echo e(auth()->user()->name); ?></span>
                    </li>
                    <?php if (auth()->user()->isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e($base); ?>/admin"><i class="fas fa-user-shield me-1"></i> Admin</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <form action="<?php echo e($base); ?>/logout" method="post" class="d-inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-sm btn-outline-light ms-lg-2">Logout</button>
                        </form>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container dashboard-container">
    <div class="dashboard-header text-center">
        <h1 class="dashboard-title">Welcome to Kabutech</h1>
        <p class="dashboard-subtitle">
            <span class="live-indicator"></span>
            <span id="live-status">Live</span> — Real-time sensor data
        </p>
        <?php if (isset($installation) && $installation): ?>
        <div class="alert alert-dark border border-success border-opacity-50 text-start mx-auto mt-3 mb-0" style="max-width: 720px; background: rgba(0,0,0,0.35);">
            <div class="small text-white-50 mb-1"><i class="fas fa-warehouse me-1 text-success"></i> Active mushroom farm</div>
            <div class="fw-semibold text-white"><?php echo e($installation->name); ?></div>
            <?php if (! empty($installation->owner_name)): ?>
                <div class="small text-white-50 mt-1">Owner: <?php echo e($installation->owner_name); ?></div>
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
                    <div class="d-flex justify-content-center gap-2 flex-wrap mt-2">
                        <button class="control-btn off" id="misting-btn" onclick="toggleMisting()"><i class="fas fa-power-off me-2"></i>Turn ON</button>
                        <button class="control-btn off" id="misting-auto-btn" onclick="setAutoMisting('fruiting')"><i class="fas fa-rotate me-2"></i>AUTO (fruiting)</button>
                        <button class="control-btn off" id="misting-auto-inc-btn" onclick="setAutoMisting('incubation')"><i class="fas fa-rotate me-2"></i>AUTO (incubation)</button>
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
                            <select class="form-select form-select-sm" id="mushroom-type-select"
                                    style="background-color:#1e1e2e; color:#fff; border-color:rgba(255,255,255,0.2);
                                           background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3E%3C/svg%3E\"); background-repeat:no-repeat; background-position:right .75rem center; background-size:16px 12px; padding-right:2.5rem;">
                                <option value="oyster_mushroom">Oyster Mushroom</option>
                                <option value="straw_mushroom">Straw Mushroom</option>
                                <option value="milky_mushroom">Milky Mushroom</option>
                                <option value="wood_ear">Wood Ear</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Stage clocks</label>
                            <div class="d-grid gap-2" style="grid-template-columns: 1fr 1fr;">
                                <button type="button" class="btn btn-sm btn-outline-light" id="btn-incubation-start">Start incubation</button>
                                <button type="button" class="btn btn-sm btn-outline-light" id="btn-incubation-clear">Clear incubation</button>
                                <button type="button" class="btn btn-sm btn-success" id="btn-fruiting-start">Start fruiting</button>
                                <button type="button" class="btn btn-sm btn-outline-light" id="btn-fruiting-clear">Clear fruiting</button>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="fas fa-video me-2"></i>Live Camera</span>
                    <small class="text-muted">ESP32-CAM</small>
                </div>
                <div class="card-body text-center camera-stream-wrap">
                    <!-- Camera: use proxy so it works on ngrok; JS will retry if stream drops -->
                    <?php
                    $cameraStreamUrl = config('iot.camera_stream_url', '');
                    // Same origin as the page ($base), not url() / APP_URL — otherwise opening the dashboard
                    // on localhost while APP_URL is ngrok makes the camera load from the wrong host.
                    $cameraSrc = !empty($cameraStreamUrl) ? $base . '/api/camera-stream' : '';
                    ?>
                    <?php if (!empty($cameraSrc)): ?>
                    <img id="camera-stream"
                         src="<?php echo e($cameraSrc); ?>"
                         alt="ESP32-CAM Stream"
                         class="camera-stream-img"
                         onerror="window.cameraStreamError && window.cameraStreamError(this);">
                    <div id="camera-offline" class="camera-offline-msg" style="display:none;">
                        <i class="fas fa-video-slash text-muted fa-3x mb-2"></i>
                        <p class="text-muted mb-0">Camera disconnected. <button type="button" class="btn btn-sm btn-outline-light mt-2" onclick="window.cameraStreamRetry && window.cameraStreamRetry();">Retry</button></p>
                    </div>
                    <?php else: ?>
                    <div class="camera-offline-msg">
                        <i class="fas fa-camera text-muted fa-3x mb-2"></i>
                        <p class="text-muted mb-1">Connect your ESP32-CAM</p>
                        <p class="text-muted small mb-0">Add to your <code>.env</code>: <code>ESP32_CAM_STREAM_URL=http://YOUR_ESP32_CAM_IP:81/stream</code></p>
                        <p class="text-muted small mt-2">Use the IP shown in Serial Monitor after the camera connects to Wi‑Fi.</p>
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
                        <select id="history-limit-select" class="form-select form-select-sm"
                                style="width:auto;background-color:#1e1e2e;color:#fff;border-color:rgba(255,255,255,0.2);">
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
</div>

<?php if (auth()->check() && !auth()->user()->isAdmin()): ?>
<!-- Feedback Floating Button -->
<button type="button" id="feedback-fab" data-bs-toggle="modal" data-bs-target="#feedbackModal"
        title="Share your feedback"
        style="position:fixed;bottom:2rem;right:2rem;z-index:1040;
               width:60px;height:60px;border-radius:50%;border:none;
               background:linear-gradient(135deg, rgba(34, 197, 94, 0.95), rgba(22, 163, 74, 0.95));
               color:#fff;font-size:1.5rem;cursor:pointer;
               box-shadow:0 8px 32px rgba(34,197,94,0.4);
               display:flex;align-items:center;justify-content:center;
               transition:transform .3s cubic-bezier(0.4,0,0.2,1),box-shadow .3s cubic-bezier(0.4,0,0.2,1);"
        onmouseenter="this.style.transform='scale(1.1)';this.style.boxShadow='0 12px 40px rgba(34,197,94,0.55)';"
        onmouseleave="this.style.transform='scale(1)';this.style.boxShadow='0 8px 32px rgba(34,197,94,0.4)';">
    <i class="fas fa-comment-dots"></i>
</button>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:rgba(20,40,12,0.85);border:1px solid rgba(255,255,255,0.18);border-radius:24px;backdrop-filter:blur(24px);box-shadow:0 12px 40px rgba(0,0,0,0.3);">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-white fw-bold" id="feedbackModalLabel">
          <i class="fas fa-comment-dots me-2 text-success"></i>Share Your Feedback
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="feedbackForm">
        <input type="hidden" name="_token" id="feedback-csrf" value="<?php echo e(csrf_token()); ?>">
        <div class="modal-body pt-3">
          <p class="text-muted small mb-4">Help us improve the Kabutech monitoring system. Your feedback is reviewed by the admin team.</p>

          <!-- Inline feedback message (shown after submit) -->
          <div id="feedback-inline-msg" class="alert d-none mb-3" role="alert"></div>

          <!-- Star rating picker -->
          <div class="mb-4">
            <label class="form-label text-white fw-semibold mb-2">How would you rate the system?</label>
            <div class="d-flex gap-1" id="star-picker" role="group" aria-label="Star rating">
              <?php for($s=1;$s<=5;$s++): ?>
              <label class="star-label" style="cursor:pointer;font-size:2rem;color:rgba(255,255,255,0.2);transition:color .15s;" title="<?php echo $s; ?> star<?php echo $s>1?'s':''; ?>">
                <input type="radio" name="rating" value="<?php echo $s; ?>" required style="position:absolute;opacity:0;width:0;height:0;">
                <i class="fas fa-star"></i>
              </label>
              <?php endfor; ?>
            </div>
            <div id="star-label-text" class="small mt-1" style="color:rgba(255,255,255,0.5);min-height:1.2em;"></div>
          </div>

          <!-- Comment -->
          <div class="mb-3">
            <label for="feedback-content" class="form-label text-white fw-semibold">Your comments</label>
            <textarea id="feedback-content" name="content" rows="4"
                      class="form-control bg-dark text-white border-secondary"
                      style="border-radius: 12px; background-color: rgba(0,0,0,0.2) !important; resize: none;"
                      placeholder="Tell us what you think, what can be improved…"
                      maxlength="2000" required></textarea>
            <div class="form-text text-muted text-end" id="char-count">0 / 2000</div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:12px;">Cancel</button>
          <button type="submit" id="feedback-submit-btn" class="btn btn-success px-4" style="border-radius:12px;box-shadow:0 4px 16px rgba(34,197,94,0.3);">
            <i class="fas fa-paper-plane me-2"></i>Submit Feedback
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Feedback success toast -->
<?php if (session('feedback_sent')): ?>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1060;">
  <div id="feedbackToast" class="toast show align-items-center text-white border-0"
       style="background:rgba(34,197,94,0.9);backdrop-filter:blur(10px);" role="alert">
    <div class="d-flex">
      <div class="toast-body">
        <i class="fas fa-check-circle me-2"></i><?php echo e(session('feedback_sent')); ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script src="<?php echo e($base); ?>/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo e($base); ?>/vendor/chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function(){
    var streamEl = document.getElementById('camera-stream');
    var offlineEl = document.getElementById('camera-offline');
    var streamSrc = streamEl ? streamEl.getAttribute('src') : '';
    function showOffline(){ if(streamEl) streamEl.style.display='none'; if(offlineEl) offlineEl.style.display='block'; }
    function showStream(){ if(streamEl) streamEl.style.display='block'; if(offlineEl) offlineEl.style.display='none'; }
    function retry(){
        if(!streamEl || !streamSrc) return;
        showStream();
        streamEl.src = streamSrc + (streamSrc.indexOf('?')>=0 ? '&' : '?') + '_t=' + Date.now();
    }
    window.cameraStreamError = function(){ showOffline(); };
    window.cameraStreamRetry = retry;
    if(streamEl && streamSrc){
        streamEl.onerror = function(){ window.cameraStreamError && window.cameraStreamError(); };
        setInterval(function(){ if(streamEl.style.display!=='none' && streamEl.complete && streamEl.naturalWidth===0) retry(); }, 15000);
    }
})();
</script>
<script src="<?php echo e($base); ?>/js/dashboard.js"></script>
<script>
// Star rating picker + AJAX feedback submit (no page refresh)
(function(){
    var labels = ['','Poor','Fair','Good','Great','Excellent'];
    var colors = ['','#ef4444','#f97316','#eab308','#22c55e','#3b82f6'];
    var stars  = document.querySelectorAll('#star-picker .star-label');
    var labelEl= document.getElementById('star-label-text');
    function highlight(n){
        stars.forEach(function(el,i){
            el.style.color = i < n ? '#eab308' : 'rgba(255,255,255,0.2)';
        });
        if(labelEl){ labelEl.textContent = n ? labels[n] : ''; labelEl.style.color = n ? colors[n] : 'rgba(255,255,255,0.5)'; }
    }
    stars.forEach(function(el,i){
        var inp = el.querySelector('input');
        el.addEventListener('mouseenter', function(){ highlight(i+1); });
        el.addEventListener('mouseleave', function(){
            var checked = document.querySelector('#star-picker input:checked');
            highlight(checked ? parseInt(checked.value) : 0);
        });
        if(inp) inp.addEventListener('change', function(){ highlight(i+1); });
    });

    // Character counter
    var ta = document.getElementById('feedback-content');
    var cc = document.getElementById('char-count');
    if(ta && cc){
        ta.addEventListener('input', function(){ cc.textContent = ta.value.length + ' / 2000'; });
    }

    // --- AJAX feedback submit ---
    var form    = document.getElementById('feedbackForm');
    var msgEl   = document.getElementById('feedback-inline-msg');
    var submitBtn = document.getElementById('feedback-submit-btn');
    var modalEl = document.getElementById('feedbackModal');

    function showMsg(text, isError){
        if(!msgEl) return;
        msgEl.textContent = text;
        msgEl.className = 'alert mb-3 ' + (isError ? 'alert-danger' : 'alert-success');
    }
    function hideMsg(){ if(msgEl) msgEl.className = 'alert d-none mb-3'; }
    function resetForm(){
        if(form) form.reset();
        highlight(0);
        if(cc) cc.textContent = '0 / 2000';
    }

    if(form){
        form.addEventListener('submit', function(e){
            e.preventDefault();
            hideMsg();
            var token = document.getElementById('feedback-csrf');
            var data  = new FormData(form);
            if(submitBtn){ submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending…'; }
            fetch('<?php echo e(url('/feedback')); ?>', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token ? token.value : '', 'Accept': 'application/json' },
                body: data
            })
            .then(function(r){ return r.json().catch(function(){ return {success: r.ok}; }); })
            .then(function(json){
                if(submitBtn){ submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Feedback'; }
                if(json && (json.success || json.message === 'ok' || json.status === 'ok')){
                    showMsg('\u2713 Thank you! Your feedback was submitted.', false);
                    resetForm();
                    // Auto-close modal after 2 s
                    setTimeout(function(){
                        var bsModal = bootstrap.Modal.getInstance(modalEl);
                        if(bsModal) bsModal.hide();
                        hideMsg();
                    }, 2000);
                } else {
                    showMsg(json && json.message ? json.message : 'Something went wrong. Please try again.', true);
                }
            })
            .catch(function(){
                if(submitBtn){ submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Feedback'; }
                showMsg('Network error. Please check your connection and try again.', true);
            });
        });

        // Clear message when modal is closed
        if(modalEl) modalEl.addEventListener('hidden.bs.modal', function(){ hideMsg(); });
    }
})();
</script>
</body>
</html>
