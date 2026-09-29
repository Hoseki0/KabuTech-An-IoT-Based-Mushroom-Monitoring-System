// API Configuration
const API_BASE_URL = '/api';
let mistingSystemStatus = false;
let updateInterval;
let historyInterval;
let boxesInterval;
let mistingInterval;
let growInterval;
let historyLimit = 15;
let csrfToken = '';
let mistingMode = 'auto';
let mistingProfile = 'fruiting';
let thChart = null;
let lastNotifIds = new Set();
let notifBootstrapped = false;

// AbortController guards — cancel in-flight requests on next tick
let sensorAbort = null;
let boxesAbort  = null;
let historyAbort = null;

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Intro is dismissed by inline script in the view
    showSection('overview');

    // Sidebar toggle
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.getElementById('sidebar');
    const pageWrapper = document.getElementById('pageWrapper');
    if (sidebarToggleBtn && sidebar && pageWrapper) {
        sidebarToggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            pageWrapper.classList.toggle('sidebar-collapsed');
        });
    }

    // Wire up history log-count selector
    const limitSelect = document.getElementById('history-limit-select');
    if (limitSelect) {
        limitSelect.addEventListener('change', function() {
            historyLimit = parseInt(this.value, 10) || 15;
            const subtitle = document.getElementById('history-subtitle');
            if (subtitle) subtitle.textContent = `Latest ${historyLimit} readings (auto-refresh)`;
            fetchHistory();
        });
    }

    fetchCsrfToken().then(() => {
        // Initial burst
        fetchSensorData();
        fetchBoxesData();
        fetchHistory();
        fetchGrowOverview();
        fetchNotifications();
        fetchMistingStatus();
        setupGrowControls();
        setupNotificationDropdown();
        startLiveClock();
        initCameraStream();

        // Polling intervals — staggered to avoid simultaneous requests
        updateInterval  = setInterval(fetchSensorData,   4000);
        boxesInterval   = setInterval(fetchBoxesData,    4000);
        historyInterval = setInterval(fetchHistory,      8000);
        mistingInterval = setInterval(fetchMistingStatus, 12000);
        growInterval    = setInterval(fetchGrowOverview,  15000);
        setInterval(fetchNotifications, 30000);
    });

    // Page Visibility API — pause expensive polls when tab is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(updateInterval);
            clearInterval(boxesInterval);
            clearInterval(historyInterval);
            clearInterval(mistingInterval);
            clearInterval(growInterval);
        } else {
            // Immediately refresh on tab focus
            fetchSensorData();
            fetchBoxesData();
            updateInterval  = setInterval(fetchSensorData,   4000);
            boxesInterval   = setInterval(fetchBoxesData,    4000);
            historyInterval = setInterval(fetchHistory,      8000);
            mistingInterval = setInterval(fetchMistingStatus, 12000);
            growInterval    = setInterval(fetchGrowOverview,  15000);
        }
    });
});

function startLiveClock() {
    const clockEl = document.getElementById('live-clock');
    const dateEl = document.getElementById('live-date');
    if (!clockEl || !dateEl) return;

    const updateTime = () => {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString('en-US', { hour12: true });
        dateEl.textContent = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };
    updateTime();
    setInterval(updateTime, 1000);
}

// Switch between menu sections (Dashboard / Sensor Data / Incubator / Exports / Diagnostics / Camera)
function showSection(section) {
    const overview = document.getElementById('overview-section');
    const history = document.getElementById('history-section');
    const incubator = document.getElementById('incubator-section');
    const exportsSec = document.getElementById('exports-section');
    const diagSec = document.getElementById('diagnostics-section');
    const cameraSec = document.getElementById('camera-gallery-section');

    // Hide all sections first
    [overview, history, incubator, exportsSec, diagSec, cameraSec].forEach(el => {
        if (el) el.classList.add('d-none');
    });

    if (section === 'history') {
        if (history) history.classList.remove('d-none');
    } else if (section === 'incubator') {
        if (incubator) incubator.classList.remove('d-none');
        renderIncubatorTab();
    } else if (section === 'exports') {
        if (exportsSec) exportsSec.classList.remove('d-none');
        loadCorrelationAnalytics();
    } else if (section === 'diagnostics') {
        if (diagSec) diagSec.classList.remove('d-none');
        loadHardwareDiagnostics();
    } else if (section === 'camera-gallery') {
        if (cameraSec) cameraSec.classList.remove('d-none');
        loadCameraSnapshots();
    } else {
        if (overview) overview.classList.remove('d-none');
    }
}

// Activate a sidebar link
function setSidebarActive(el) {
    document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
    if (el) el.classList.add('active');
}

// Fetch CSRF token
async function fetchCsrfToken() {
    try {
        const response = await fetch(`${API_BASE_URL}/csrf-token`);
        const data = await response.json();
        csrfToken = data.token;
        document.getElementById('csrf-token-meta').setAttribute('content', csrfToken);
    } catch (error) {
        console.error('Error fetching CSRF token:', error);
    }
}

// Latest sensor data cache (Box A — used by overview dashboard cards)
let latestSensorData = null;

// Per-box sensor data cache — populated by fetchBoxesData()
// Shape: { box_a: { temperature, humidity, connected, ... }, box_b: {...}, box_c: {...} }
let latestBoxData = {};

/** Fetch per-box latest readings from the aggregate endpoint. */
async function fetchBoxesData() {
    if (boxesAbort) boxesAbort.abort();
    boxesAbort = new AbortController();
    try {
        const r = await fetch(`${API_BASE_URL}/sensor-data/boxes/latest`, { signal: boxesAbort.signal });
        if (!r.ok) return;
        const data = await r.json();
        latestBoxData = data;

        // Refresh box cards if the incubator tab is visible
        if (document.getElementById('incubator-section') &&
            !document.getElementById('incubator-section').classList.contains('d-none')) {
            renderIncubatorBoxCards();
        }
    } catch (e) {
        if (e.name !== 'AbortError') console.error('fetchBoxesData', e);
    }
}

// Fetch latest sensor data
async function fetchSensorData() {
    if (sensorAbort) sensorAbort.abort();
    sensorAbort = new AbortController();
    try {
        // Add updating class to show data is being refreshed
        document.getElementById('temperature-value').classList.add('updating');
        document.getElementById('humidity-value').classList.add('updating');
        
        const response = await fetch(`${API_BASE_URL}/sensor-data/latest`, { signal: sensorAbort.signal });
        const data = await response.json();

        if (response.ok) {
            latestSensorData = data;  // cache for incubator box cards
            updateConnectionStatus(true);
            updateDashboard(data);

            // Remove updating class after update
            setTimeout(() => {
                document.getElementById('temperature-value').classList.remove('updating');
                document.getElementById('humidity-value').classList.remove('updating');
            }, 500);
        } else {
            throw new Error('Failed to fetch data');
        }
    } catch (error) {
        if (error.name === 'AbortError') return;
        console.error('Error fetching sensor data:', error);
        updateConnectionStatus(false);
        // Remove updating class on error
        document.getElementById('temperature-value').classList.remove('updating');
        document.getElementById('humidity-value').classList.remove('updating');
    }
}

async function fetchMistingStatus() {
    try {
        const response = await fetch(`${API_BASE_URL}/misting/status`);
        const data = await response.json();
        if (response.ok) {
            mistingMode = data.desired_mode || 'auto';
            mistingProfile = data.desired_profile || 'fruiting';
            updateMistingModeText();
        }
    } catch (error) {
        // ignore
    }
}

function updateMistingModeText() {
    const el = document.getElementById('misting-auto-status');
    if (!el) return;

    if (mistingMode === 'manual') {
        el.textContent = 'Manual — use Turn ON/OFF above';
        return;
    }

    const profileLabel = mistingProfile === 'incubation' ? 'incubation' : 'fruiting';
    el.textContent = `Auto (${profileLabel}) — ESP32 maintains targets`;
}

// Fetch sensor data history
async function fetchHistory() {
    if (historyAbort) historyAbort.abort();
    historyAbort = new AbortController();
    try {
        const response = await fetch(`${API_BASE_URL}/sensor-data/history?limit=${historyLimit}`, { signal: historyAbort.signal });
        const data = await response.json();

        if (response.ok) {
            updateHistoryTable(data);
            renderTrendChart(data);
        } else {
            console.error('Failed to fetch history data', data);
        }
    } catch (error) {
        if (error.name !== 'AbortError') console.error('Error fetching history data:', error);
    }
}

function renderTrendChart(history) {
    const canvas = document.getElementById('th-chart');
    if (!canvas || typeof Chart === 'undefined') return;
    if (!Array.isArray(history) || history.length === 0) return;

    // Oldest -> newest for chart
    const rows = [...history].reverse();
    const labels = rows.map(r => {
        try {
            const d = r.recorded_at ? new Date(r.recorded_at) : null;
            return d ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        } catch {
            return '';
        }
    });

    const temps = rows.map(r => (r.temperature ?? null));
    const hums = rows.map(r => (r.humidity ?? null));

    const css = getComputedStyle(document.documentElement);
    const accent = (css.getPropertyValue('--color-primary') || '#16a34a').trim();

    const grid = 'rgba(255, 255, 255, 0.08)';
    const tick = '#94a3b8';

    const config = {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Temp (°C)',
                    data: temps,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.08)',
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    yAxisID: 'yTemp',
                },
                {
                    label: 'Humidity (%)',
                    data: hums,
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.08)',
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    yAxisID: 'yHum',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: {
                    labels: { color: tick, boxWidth: 10, boxHeight: 10, usePointStyle: true },
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                },
            },
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    grid: { color: grid },
                    ticks: { color: tick, maxRotation: 0, autoSkip: true },
                },
                yTemp: {
                    position: 'left',
                    grid: { color: grid },
                    ticks: { color: tick },
                },
                yHum: {
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: tick },
                    min: 0,
                    max: 100,
                },
            },
        },
    };

    if (thChart) {
        thChart.data = config.data;
        thChart.options = config.options;
        thChart.update();
    } else {
        thChart = new Chart(canvas, config);
    }
}

// Update history table
function updateHistoryTable(history) {
    const tbody = document.getElementById('history-body');
    if (!tbody) return;

    if (!Array.isArray(history) || history.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">No history data available yet.</td></tr>`;
        return;
    }

    let rows = '';
    history.forEach((item, index) => {
        const temp = item.temperature !== null && item.temperature !== undefined
            ? parseFloat(item.temperature).toFixed(1) : '--';
        const hum = item.humidity !== null && item.humidity !== undefined
            ? parseFloat(item.humidity).toFixed(1) : '--';
        const rssi = item.wifi_rssi !== null && item.wifi_rssi !== undefined
            ? `${item.wifi_rssi} dBm` : '--';
        const misting = item.misting_system
            ? '<span class="badge bg-success">ON</span>'
            : '<span class="badge bg-secondary">OFF</span>';
        const mode = item.misting_source ? item.misting_source.toUpperCase() : '--';
        const reason = item.misting_reason ? ` <span class="text-muted">(${item.misting_reason})</span>` : '';
        const ts = item.recorded_at ? new Date(item.recorded_at).toLocaleString() : '';

        rows += `
            <tr>
                <td>${index + 1}</td>
                <td>${ts}</td>
                <td>${temp}</td>
                <td>${hum}</td>
                <td>${rssi}</td>
                <td>${misting}</td>
                <td>${mode}${reason}</td>
            </tr>
        `;
    });

    tbody.innerHTML = rows;
}

// Update dashboard with sensor data
function updateDashboard(data) {
    // Update Temperature with smooth transition
    const tempValue = document.getElementById('temperature-value');
    if (data.temperature !== null && data.temperature !== undefined && !isNaN(data.temperature)) {
        const newTemp = parseFloat(data.temperature).toFixed(1);
        // Only update if value changed (reduces flicker)
        if (tempValue.textContent.trim() !== newTemp) {
            tempValue.innerHTML = `${newTemp}<span class="stat-unit">°C</span>`;
            tempValue.classList.add('updating');
            setTimeout(() => tempValue.classList.remove('updating'), 500);
        }
        if (data.recorded_at) {
            document.getElementById('temp-update').textContent = formatUpdateTime(data.recorded_at);
        }
        const headerTemp = document.getElementById('header-temp');
        if (headerTemp) headerTemp.textContent = newTemp;
    } else {
        tempValue.innerHTML = '--<span class="stat-unit">°C</span>';
        const headerTemp = document.getElementById('header-temp');
        if (headerTemp) headerTemp.textContent = '--';
    }

    // Update Humidity with smooth transition
    const humidityValue = document.getElementById('humidity-value');
    if (data.humidity !== null && data.humidity !== undefined && !isNaN(data.humidity)) {
        const newHumidity = parseFloat(data.humidity).toFixed(1);
        // Only update if value changed (reduces flicker)
        if (humidityValue.textContent.trim() !== newHumidity) {
            humidityValue.innerHTML = `${newHumidity}<span class="stat-unit">%</span>`;
            humidityValue.classList.add('updating');
            setTimeout(() => humidityValue.classList.remove('updating'), 500);
        }
        if (data.recorded_at) {
            document.getElementById('humidity-update').textContent = formatUpdateTime(data.recorded_at);
        }
        const headerHumidity = document.getElementById('header-humidity');
        if (headerHumidity) headerHumidity.textContent = newHumidity;
    } else {
        humidityValue.innerHTML = '--<span class="stat-unit">%</span>';
        const headerHumidity = document.getElementById('header-humidity');
        if (headerHumidity) headerHumidity.textContent = '--';
    }

    // Update Misting System
    mistingSystemStatus = data.misting_system || false;
    updateMistingDisplay(mistingSystemStatus);
    if (data.recorded_at) {
        document.getElementById('misting-update').textContent = formatUpdateTime(data.recorded_at);
    }
}

// Update misting system display
function updateMistingDisplay(status) {
    const statusElement = document.getElementById('misting-status');
    const badgeElement = document.getElementById('misting-badge');
    const buttonElement = document.getElementById('misting-btn');

    if (status) {
        statusElement.textContent = 'ON';
        badgeElement.textContent = 'Active';
        badgeElement.className = 'status-badge status-on';
        buttonElement.innerHTML = '<i class="fas fa-power-off me-2"></i>Turn OFF';
        buttonElement.className = 'control-btn';
    } else {
        statusElement.textContent = 'OFF';
        badgeElement.textContent = 'Inactive';
        badgeElement.className = 'status-badge status-off';
        buttonElement.innerHTML = '<i class="fas fa-power-off me-2"></i>Turn ON';
        buttonElement.className = 'control-btn off';
    }

    updateMistingModeText();
}

// Toggle misting system
async function toggleMisting() {
    const newStatus = !mistingSystemStatus;
    const button = document.getElementById('misting-btn');
    
    // Disable button during request
    button.disabled = true;
    button.classList.add('loading');

    try {
        const response = await fetch(`${API_BASE_URL}/misting/control`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ status: newStatus, mode: 'manual' })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            mistingMode = 'manual';
            mistingSystemStatus = newStatus;
            updateMistingDisplay(newStatus);
            updateConnectionStatus(true);
            
            // Show success feedback
            showNotification(`Misting system ${newStatus ? 'activated' : 'deactivated'} successfully`, 'success');
        } else {
            throw new Error(result.message || 'Failed to control misting system');
        }
    } catch (error) {
        console.error('Error controlling misting system:', error);
        updateConnectionStatus(false);
        showNotification('Failed to control misting system. Please try again.', 'error');
    } finally {
        button.disabled = false;
        button.classList.remove('loading');
    }
}

// Update connection status indicator
function updateConnectionStatus(connected) {
    const liveStatus = document.getElementById('live-status');

    // Update both desktop and mobile wifi indicators
    [{ elId: 'connection-status', iconId: 'connection-icon' }, { elId: 'connection-status-mobile', iconId: null }].forEach(({ elId, iconId }) => {
        const el = document.getElementById(elId);
        if (!el) return;
        if (connected) {
            el.className = el.className.replace('connection-status', '').trim();
            el.className = 'connection-status connected ' + (elId === 'connection-status' ? 'd-none d-lg-flex' : '');
            el.title = 'Connected';
        } else {
            el.className = 'connection-status disconnected pulse ' + (elId === 'connection-status' ? 'd-none d-lg-flex' : '');
            el.title = 'Disconnected';
        }
        if (iconId) {
            const icon = document.getElementById(iconId);
            if (icon) icon.className = connected ? 'fas fa-wifi' : 'fas fa-wifi-slash';
        } else {
            const icon = el.querySelector('i');
            if (icon) icon.className = connected ? 'fas fa-wifi' : 'fas fa-wifi-slash';
        }
    });

    if (liveStatus) {
        liveStatus.textContent = connected ? 'Live' : 'Offline';
        liveStatus.style.color = connected ? '#22c55e' : '#dc2626';
    }
}

// Format update time
function formatUpdateTime(timestamp) {
    if (!timestamp) return '';
    
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // seconds ago

    if (diff < 60) {
        return `Updated ${diff}s ago`;
    } else if (diff < 3600) {
        return `Updated ${Math.floor(diff / 60)}m ago`;
    } else {
        return `Updated ${date.toLocaleTimeString()}`;
    }
}

// Show notification (simple alert for now, can be enhanced with toast library)
function showNotification(message, type) {
    // Create a simple notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 120px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        z-index: 1001;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    `;
    
    if (type === 'success') {
        notification.style.background = 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)';
    } else {
        notification.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

function csrfHeadersJson() {
    const token = csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function formatGrowPrediction(pred) {
    if (!pred) {
        return 'Set “Start fruiting” when pins appear for an estimate.';
    }
    const earliest = pred.earliest_harvest_at ? new Date(pred.earliest_harvest_at).toLocaleDateString() : '';
    const latest = pred.latest_harvest_at ? new Date(pred.latest_harvest_at).toLocaleDateString() : '';
    const de = pred.days_until_earliest;
    const dl = pred.days_until_latest;
    if (dl !== null && dl < 0) {
        return `The typical window for your species has passed (${earliest}–${latest}). Check caps for readiness.`;
    }
    if (de !== null && de > 0) {
        return `Earliest around ${earliest} (~${de} day${de === 1 ? '' : 's'}). Latest by ${latest}. ${pred.note || ''}`;
    }
    if (de !== null && de <= 0 && dl !== null && dl >= 0) {
        return `You may be in the harvest window now through ${latest}. ${pred.note || ''}`;
    }
    return `${earliest} – ${latest}. ${pred.note || ''}`;
}

function formatFruitingStartPrediction(pred) {
    if (!pred) {
        return 'Keep conditions on target for an estimate.';
    }
    const earliest = pred.earliest_pins_at ? new Date(pred.earliest_pins_at).toLocaleDateString() : '';
    const latest = pred.latest_pins_at ? new Date(pred.latest_pins_at).toLocaleDateString() : '';
    const de = pred.days_until_earliest;
    const dl = pred.days_until_latest;
    return `Pins may appear around ${earliest}–${latest} (~${de}–${dl} days) if conditions stay within target.`;
}

function formatIncubationPrediction(pred) {
    if (!pred) {
        return 'Set “Start incubation” to get an estimate.';
    }
    const earliest = pred.earliest_fruiting_switch_at ? new Date(pred.earliest_fruiting_switch_at).toLocaleDateString() : '';
    const latest = pred.latest_fruiting_switch_at ? new Date(pred.latest_fruiting_switch_at).toLocaleDateString() : '';
    const de = pred.days_until_earliest;
    const dl = pred.days_until_latest;
    if (dl !== null && dl < 0) {
        return `Incubation window has likely passed (${earliest}–${latest}). Confirm full colonization, then switch to fruiting.`;
    }
    return `Switch to fruiting around ${earliest}–${latest} (~${de}–${dl} days). ${pred.note || ''}`;
}

function envStatusBadgeLabel(env) {
    if (!env) return '—';
    if (env.status === 'optimal') return 'On target';
    if (env.status === 'attention') return 'Adjust conditions';
    if (env.messages && env.messages.length) return 'Review tips';
    return 'Waiting for sensor…';
}

function envStatusBadgeClass(env) {
    if (!env) return 'bg-secondary';
    if (env.status === 'optimal') return 'bg-success';
    if (env.status === 'attention') return 'bg-warning text-dark';
    return 'bg-secondary';
}

async function putGrowSettings(body) {
    const r = await fetch(`${API_BASE_URL}/grow-settings`, {
        method: 'PUT',
        headers: csrfHeadersJson(),
        body: JSON.stringify(body),
    });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) {
        let msg = j.message || 'Save failed';
        if (j.errors && typeof j.errors === 'object') {
            const flat = Object.values(j.errors).flat();
            if (flat.length) msg = flat.join(' ');
        }
        throw new Error(msg);
    }
    return j;
}

async function fetchGrowOverview() {
    try {
        const r = await fetch(`${API_BASE_URL}/grow-settings`, { headers: { Accept: 'application/json' } });
        const g = await r.json();
        if (!r.ok) return;

        const select = document.getElementById('mushroom-type-select');
        if (select && g.mushroom_type) {
            select.value = g.mushroom_type;
        }

        const badge = document.getElementById('env-status-badge');
        if (badge) {
            badge.textContent = envStatusBadgeLabel(g.environment);
            badge.className = `badge rounded-pill ${envStatusBadgeClass(g.environment)}`;
        }

        const targetsEl = document.getElementById('grow-targets-text');
        if (targetsEl && g.targets && g.mushroom_label) {
            const t = g.targets;
            targetsEl.textContent =
                `${g.mushroom_label}: ${t.temp_min}–${t.temp_max} °C, ${t.hum_min}–${t.hum_max} % RH (fruiting).`;
        }

        const incTargetsEl = document.getElementById('grow-incubation-targets-text');
        if (incTargetsEl && g.incubation_targets && g.mushroom_label) {
            const t = g.incubation_targets;
            if (t.temp_min !== null && t.temp_max !== null && t.hum_min !== null && t.hum_max !== null) {
                incTargetsEl.textContent =
                    `${g.mushroom_label}: ${t.temp_min}–${t.temp_max} °C, ${t.hum_min}–${t.hum_max} % RH (incubation).`;
            } else {
                incTargetsEl.textContent = '—';
            }
        }

        const predEl = document.getElementById('grow-prediction-text');
        if (predEl) {
            predEl.textContent = formatGrowPrediction(g.prediction);
        }

        const incEl = document.getElementById('grow-incubation-text');
        if (incEl) {
            incEl.textContent = g.incubation_started_at
                ? formatIncubationPrediction(g.incubation_prediction)
                : 'Set “Start incubation” to get an estimate.';
        }

        const fruitEl = document.getElementById('grow-fruiting-text');
        if (fruitEl) {
            // When fruiting has started (user pressed Start fruiting), the estimate is no longer needed.
            fruitEl.textContent = g.fruiting_started_at
                ? `Fruiting started: ${new Date(g.fruiting_started_at).toLocaleString()}`
                : formatFruitingStartPrediction(g.fruiting_start_prediction);
        }

        const tempCard = document.getElementById('temperature-card');
        const humCard = document.getElementById('humidity-card');
        const lt = g.latest?.temperature;
        const lh = g.latest?.humidity;
        const tr = g.targets;
        if (tempCard && tr && lt !== null && lt !== undefined && !Number.isNaN(Number(lt))) {
            const v = Number(lt);
            tempCard.classList.toggle('border', true);
            tempCard.classList.toggle('border-warning', v < tr.temp_min || v > tr.temp_max);
            tempCard.classList.toggle('border-success', v >= tr.temp_min && v <= tr.temp_max);
        } else if (tempCard) {
            tempCard.classList.remove('border', 'border-warning', 'border-success');
        }
        if (humCard && tr && lh !== null && lh !== undefined && !Number.isNaN(Number(lh))) {
            const v = Number(lh);
            humCard.classList.toggle('border', true);
            humCard.classList.toggle('border-warning', v < tr.hum_min || v > tr.hum_max);
            humCard.classList.toggle('border-success', v >= tr.hum_min && v <= tr.hum_max);
        } else if (humCard) {
            humCard.classList.remove('border', 'border-warning', 'border-success');
        }
    } catch (e) {
        console.error('fetchGrowOverview', e);
    }
}

function setupGrowControls() {
    const select = document.getElementById('mushroom-type-select');
    if (select) {
        select.addEventListener('change', async () => {
            try {
                await putGrowSettings({ mushroom_type: select.value });
                showNotification('Mushroom type saved. AUTO misting targets updated on the device.', 'success');
                await fetchGrowOverview();
            } catch (e) {
                showNotification(e.message || 'Could not save mushroom type', 'error');
            }
        });
    }

    const incStartBtn = document.getElementById('btn-incubation-start');
    if (incStartBtn) {
        incStartBtn.addEventListener('click', async () => {
            try {
                const iso = new Date().toISOString();
                await putGrowSettings({ incubation_started_at: iso });
                showNotification('Incubation clock started.', 'success');
                await fetchGrowOverview();
            } catch (e) {
                showNotification(e.message || 'Could not start incubation', 'error');
            }
        });
    }

    const incClearBtn = document.getElementById('btn-incubation-clear');
    if (incClearBtn) {
        incClearBtn.addEventListener('click', async () => {
            try {
                await putGrowSettings({ clear_incubation: true });
                showNotification('Incubation clock cleared.', 'success');
                await fetchGrowOverview();
            } catch (e) {
                showNotification(e.message || 'Could not clear incubation', 'error');
            }
        });
    }

    const startBtn = document.getElementById('btn-fruiting-start');
    if (startBtn) {
        startBtn.addEventListener('click', async () => {
            try {
                const iso = new Date().toISOString();
                await putGrowSettings({ fruiting_started_at: iso });
                showNotification('Fruiting clock started. Harvest window estimated.', 'success');
                await fetchGrowOverview();
            } catch (e) {
                showNotification(e.message || 'Could not start fruiting', 'error');
            }
        });
    }

    const clearBtn = document.getElementById('btn-fruiting-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', async () => {
            try {
                await putGrowSettings({ clear_fruiting: true });
                showNotification('Fruiting clock cleared.', 'success');
                await fetchGrowOverview();
            } catch (e) {
                showNotification(e.message || 'Could not clear', 'error');
            }
        });
    }
}

function hideNotificationBadge() {
    ['notif-badge', 'notif-badge-mobile'].forEach(id => {
        const badge = document.getElementById(id);
        if (badge) {
            badge.textContent = '0';
            badge.classList.add('d-none');
        }
    });
}

/** Clears server-side and local read state; hides the count immediately. */
async function markAllNotificationsRead() {
    hideNotificationBadge();
    
    // Clear local notifications unread state
    const localNotifs = JSON.parse(localStorage.getItem('local_notifications') || '[]');
    localNotifs.forEach(n => n.read = true);
    localStorage.setItem('local_notifications', JSON.stringify(localNotifs));

    try {
        const r = await fetch(`${API_BASE_URL}/notifications/read-all`, {
            method: 'POST',
            headers: csrfHeadersJson(),
        });
        if (!r.ok) {
            throw new Error('read-all failed');
        }
        await fetchNotifications();
    } catch (e) {
        console.error(e);
        await fetchNotifications();
    }
}

/** Opening the bell hides the count immediately; list syncs when the menu closes. */
function setupNotificationDropdown() {
    ['notifDropdown', 'notifDropdownMobile'].forEach(id => {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.addEventListener('shown.bs.dropdown', () => {
            hideNotificationBadge();
            
            // Mark local notifications read
            const localNotifs = JSON.parse(localStorage.getItem('local_notifications') || '[]');
            localNotifs.forEach(n => n.read = true);
            localStorage.setItem('local_notifications', JSON.stringify(localNotifs));

            fetch(`${API_BASE_URL}/notifications/read-all`, {
                method: 'POST',
                headers: csrfHeadersJson(),
            })
                .then((r) => { if (!r.ok) throw new Error('read-all failed'); })
                .catch((e) => { console.error(e); fetchNotifications(); });
        });
        btn.addEventListener('hidden.bs.dropdown', () => {
            fetchNotifications();
        });
    });
}

async function fetchNotifications() {
    const menu = document.getElementById('notif-dropdown-menu');
    const menuMobile = document.getElementById('notif-dropdown-menu-mobile');
    const badge = document.getElementById('notif-badge');
    const badgeMobile = document.getElementById('notif-badge-mobile');
    if (!menu && !menuMobile) return;

    try {
        const r = await fetch(`${API_BASE_URL}/notifications`, { headers: { Accept: 'application/json' } });
        const data = await r.json();
        if (!r.ok) return;

        // Fetch local simulated notifications
        const localNotifs = JSON.parse(localStorage.getItem('local_notifications') || '[]');

        const items = [...localNotifs, ...(data.notifications || [])];
        // Sort newest first
        items.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

        const unread = items.filter(n => !n.read).length;
        [badge, badgeMobile].forEach(b => {
            if (b) {
                b.textContent = unread > 99 ? '99+' : String(unread);
                b.classList.toggle('d-none', unread === 0);
            }
        });

        const newIds = items.filter((n) => !n.read).map((n) => n.id);
        const hasNew = newIds.some((id) => !lastNotifIds.has(id));
        if (notifBootstrapped && hasNew) {
            showNotification('You have new grow alerts.', 'success');
        }
        notifBootstrapped = true;
        lastNotifIds = new Set(newIds);

        const setMenuHtml = (html) => {
            if (menu) menu.innerHTML = html;
            if (menuMobile) menuMobile.innerHTML = html;
        };

        if (items.length === 0) {
            setMenuHtml('<li class="px-3 py-2 small text-muted">No alerts yet.</li>');
        } else {
            const rows = items
                .map((n) => {
                    const when = n.created_at ? new Date(n.created_at).toLocaleString() : '';
                    const unreadCls = n.read ? '' : 'fw-semibold';
                    return `<li><button type="button" class="dropdown-item text-start small ${unreadCls}" data-notif-id="${n.id}">
                        <div>${escapeHtml(n.title || 'Alert')}</div>
                        <div class="text-muted" style="font-size:0.75rem;">${escapeHtml(when)}</div>
                        <div class="mt-1">${escapeHtml(n.body || '')}</div>
                    </button></li>`;
                })
                .join('');
            const fullHtml = `${rows}<li><hr class="dropdown-divider"></li>
                <li><button type="button" class="dropdown-item small text-center notif-mark-all-btn">Mark all read</button></li>`;
            setMenuHtml(fullHtml);

            document.querySelectorAll('[data-notif-id]').forEach((btn) => {
                btn.addEventListener('click', () => { markAllNotificationsRead(); });
            });
            document.querySelectorAll('.notif-mark-all-btn').forEach((btn) => {
                btn.addEventListener('click', () => { markAllNotificationsRead(); });
            });
        }
    } catch (e) {
        console.error('fetchNotifications', e);
    }
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ── Active Incubator Batches Log (Recommendation 5, 8 & 10) ──
const defaultBatches = [
    { id: 'b1', name: 'Oyster Run #4', type: 'oyster_mushroom', incubator: 'incubator_a', bags: 24, start_date: '2026-06-25', stage: 'Colonizing' },
    { id: 'b2', name: 'Milky Batch A', type: 'milky_mushroom', incubator: 'incubator_b', bags: 15, start_date: '2026-06-28', stage: 'Spawn Run' },
];

function getBatches() {
    if (!localStorage.getItem('incubator_batches')) {
        localStorage.setItem('incubator_batches', JSON.stringify(defaultBatches));
    }
    return JSON.parse(localStorage.getItem('incubator_batches'));
}

function saveBatches(batches) {
    localStorage.setItem('incubator_batches', JSON.stringify(batches));
}

// ── Incubator Room Monitor: selected box state ──
let selectedIncubatorId = 'box_a';

// Base metadata for each incubator box (temp/humidity now come from real API via latestBoxData)
let allRegisteredBoxes = {
    box_a: { box_id: 'box_a', name: 'Incubator Box A', mushroom_type: 'oyster_mushroom', stage: 'fruiting' },
    box_b: { box_id: 'box_b', name: 'Incubator Box B', mushroom_type: 'oyster_mushroom', stage: 'fruiting' },
    box_c: { box_id: 'box_c', name: 'Incubator Box C', mushroom_type: 'oyster_mushroom', stage: 'fruiting' }
};

async function fetchBoxSettings() {
    try {
        const res = await fetch(`${API_BASE_URL}/box-settings`);
        const result = await res.json();
        if (result.success && result.data) {
            allRegisteredBoxes = result.data;
            updateIncubatorDropdowns();
        }
    } catch (e) {
        console.error('Error fetching box settings:', e);
    }
}

function updateIncubatorDropdowns() {
    const selectEl = document.getElementById('batch-incubator');
    if (!selectEl) return;

    let html = '';
    Object.values(allRegisteredBoxes).forEach(box => {
        html += `<option value="${box.box_id}">${escapeHtml(box.name)}</option>`;
    });
    selectEl.innerHTML = html;
}

/**
 * Renders incubator box cards into #incubator-boxes-grid dynamically.
 */
function renderIncubatorBoxCards() {
    const grid = document.getElementById('incubator-boxes-grid');
    if (!grid) return;

    const batches = getBatches();
    const boxKeys = Object.keys(allRegisteredBoxes);

    const SPECIES_NAMES = {
        oyster_mushroom: 'Oyster Mushroom',
        straw_mushroom: 'Straw Mushroom',
        milky_mushroom: 'Milky Mushroom',
        wood_ear: 'Wood Ear',
        oyster_white: 'Oyster Mushroom'
    };

    const cards = boxKeys.map((boxId, idx) => {
        const box = allRegisteredBoxes[boxId];
        const boxData = latestBoxData[boxId] || latestBoxData['box_a'] || null;

        const temp = boxData && boxData.temperature != null ? parseFloat(boxData.temperature) : null;
        const hum = boxData && boxData.humidity != null ? parseFloat(boxData.humidity) : null;
        const connected = boxData ? (boxData.connected !== false) : false;

        const activeBatch = batches.find(b => b.incubator === boxId) || null;
        let batchName = activeBatch ? activeBatch.name : 'No active batch';
        let statusText = 'Idle';
        if (activeBatch) {
            const start = new Date(activeBatch.start_date);
            const today = new Date();
            const daysElapsed = Math.floor(Math.abs(today - start) / (1000 * 60 * 60 * 24));
            let cycleLength = 14;
            if (activeBatch.type === 'milky_mushroom') cycleLength = 18;
            if (activeBatch.type === 'straw_mushroom') cycleLength = 12;
            if (activeBatch.type === 'wood_ear') cycleLength = 16;
            statusText = daysElapsed >= cycleLength
                ? 'Ready to Harvest'
                : `Active — Day ${daysElapsed} / ${cycleLength}`;
        }

        const badgeClass = activeBatch === null ? 'idle'
            : (statusText.includes('Harvest') ? 'harvest' : 'active');

        const tempDisplay = temp != null ? temp.toFixed(1) + ' °C' : '--';
        const humDisplay = hum != null ? hum.toFixed(0) + ' % RH' : '--';

        const sourceTag = connected
            ? `<span class="inc-box-live-tag"><span class="inc-box-live-dot"></span>Live</span>`
            : `<span class="inc-box-live-tag" style="color:#ffc107;border-color:rgba(255,193,7,0.3);background:rgba(255,193,7,0.08);">
                 <i class="fas fa-circle-exclamation" style="font-size:0.55rem;"></i>&nbsp;No Signal
               </span>`;

        const selectedClass = (boxId === selectedIncubatorId) ? 'selected' : '';
        const staggerDelay = idx * 0.08;
        const speciesLabel = SPECIES_NAMES[box.mushroom_type] || 'Oyster Mushroom';
        const isDefault = ['box_a', 'box_b', 'box_c'].includes(boxId);

        const deleteBtn = !isDefault ? `
            <button class="btn btn-sm btn-outline-danger border-0 p-1 ms-1" title="Delete custom box" onclick="event.stopPropagation(); deleteIncubatorBox('${boxId}');">
                <i class="fas fa-trash-can"></i>
            </button>
        ` : '';

        return `
        <div class="incubator-box-card ${selectedClass}"
             id="incubator-box-card-${boxId}"
             onclick="selectIncubatorBox('${boxId}')"
             style="animation-delay:${staggerDelay}s">

            <div class="inc-box-header d-flex justify-content-between align-items-center mb-1">
                <span class="inc-box-title"><i class="fas fa-box me-1"></i>${escapeHtml(box.name)}</span>
                <div class="d-flex align-items-center gap-1">
                    ${sourceTag}
                    <button class="btn btn-sm btn-outline-secondary border-0 p-1" title="Configure Box Settings" onclick="event.stopPropagation(); openBoxSettingsModal('${boxId}');">
                        <i class="fas fa-gear text-muted"></i>
                    </button>
                    ${deleteBtn}
                </div>
            </div>

            <div class="mb-2">
                <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-30" style="font-size:0.68rem;">
                    <i class="fas fa-seedling me-1"></i>${escapeHtml(speciesLabel)}
                </span>
            </div>

            <div class="inc-box-sensors">
                <div class="inc-box-sensor-row">
                    <span class="inc-box-sensor-label"><i class="fas fa-thermometer-half text-danger"></i> Temperature</span>
                    <span class="inc-box-sensor-val" id="box-temp-${boxId}">${tempDisplay}</span>
                </div>
                <div class="inc-box-sensor-row">
                    <span class="inc-box-sensor-label"><i class="fas fa-tint text-info"></i> Humidity</span>
                    <span class="inc-box-sensor-val" id="box-hum-${boxId}">${humDisplay}</span>
                </div>
            </div>

            <div class="inc-box-status-row">
                <span class="small text-muted" style="font-size:0.7rem;">${escapeHtml(batchName)}</span>
                <span class="inc-box-status-badge ${badgeClass}">${statusText}</span>
            </div>
        </div>`;
    });

    grid.innerHTML = cards.join('');
}

/**
 * Called when user clicks an incubator box card.
 */
function selectIncubatorBox(roomId) {
    selectedIncubatorId = roomId;
    renderIncubatorBoxCards();   // redraws cards (updates .selected class)
    updateGrowthTimeline(roomId);
}

/**
 * Updates the growth timeline card for the given room.
 */
function updateGrowthTimeline(roomId) {
    const boxSetting = allRegisteredBoxes[roomId];
    const boxName = boxSetting ? boxSetting.name : `Incubator ${roomId.toUpperCase()}`;
    const batches = getBatches();
    const activeBatch = batches.find(b => b.incubator === roomId) || null;

    // Update selected box label strip
    const labelEl = document.getElementById('selected-box-label');
    if (labelEl) labelEl.textContent = boxName;

    let percentage      = 0;
    let startDateString = null;
    let statusText      = 'Idle';
    let predictionText  = `No active batch in ${boxName}. Register one in the Bags Register below.`;

    if (activeBatch) {
        startDateString = activeBatch.start_date;
        const start       = new Date(startDateString);
        const today       = new Date();
        const daysElapsed = Math.floor(Math.abs(today - start) / (1000 * 60 * 60 * 24));
        let cycleLength   = 14;
        if (activeBatch.type === 'milky_mushroom') cycleLength = 18;
        if (activeBatch.type === 'straw_mushroom') cycleLength = 12;
        if (activeBatch.type === 'wood_ear')        cycleLength = 16;

        percentage  = Math.min(100, Math.floor((daysElapsed / cycleLength) * 100));
        statusText  = daysElapsed >= cycleLength ? 'Ready to Harvest'
                    : `Active — Day ${daysElapsed} / ${cycleLength}`;

        const daysLeft    = Math.max(0, cycleLength - daysElapsed);
        const harvestDate = new Date(start);
        harvestDate.setDate(harvestDate.getDate() + cycleLength + 10);
        predictionText = daysElapsed >= cycleLength
            ? `${activeBatch.name} colonization is complete. Inspect bags for pinning and switch to fruiting conditions.`
            : `${activeBatch.name} is ${percentage}% through its ${cycleLength}-day cycle (~${daysLeft} day${daysLeft === 1 ? '' : 's'} remaining). Estimated harvest window around ${harvestDate.toLocaleDateString()}.`;
    }

    // Progress bar
    const pctLabel   = document.getElementById('growth-percentage-label');
    const progressBar = document.getElementById('growth-progress-bar');
    if (pctLabel)    pctLabel.textContent = `${percentage}%`;
    if (progressBar) {
        progressBar.style.width            = `${percentage}%`;
        progressBar.setAttribute('aria-valuenow', percentage);
        progressBar.className = percentage >= 90
            ? 'progress-bar progress-bar-striped progress-bar-animated bg-primary'
            : 'progress-bar progress-bar-striped progress-bar-animated bg-success';
    }

    // Timeline dates
    const startDate  = startDateString ? new Date(startDateString) : null;
    const addDays    = (d, days) => {
        const result = new Date(d);
        result.setDate(result.getDate() + days);
        return result.toLocaleDateString([], { month: 'short', day: '2-digit' });
    };

    const spawnEl    = document.getElementById('time-spawn-date');
    const pinningEl  = document.getElementById('time-pinning-date');
    const fruitingEl = document.getElementById('time-fruiting-date');
    const harvestEl  = document.getElementById('time-harvest-date');

    if (spawnEl)    spawnEl.textContent    = startDate ? startDate.toLocaleDateString([], { month: 'short', day: '2-digit' }) : '—';
    if (pinningEl)  pinningEl.textContent  = startDate ? addDays(startDate, 10) : '—';
    if (fruitingEl) fruitingEl.textContent = startDate ? addDays(startDate, 14) : '—';
    if (harvestEl)  harvestEl.textContent  = startDate ? addDays(startDate, 24) : '—';

    // Timeline dots
    const stepSpawn    = document.getElementById('step-dot-spawn');
    const stepPinning  = document.getElementById('step-dot-pinning');
    const stepFruiting = document.getElementById('step-dot-fruiting');
    const stepHarvest  = document.getElementById('step-dot-harvest');

    if (stepSpawn)    stepSpawn.className    = startDate ? 'step-dot bg-success'   : 'step-dot bg-secondary';
    if (stepPinning)  stepPinning.className  = startDate && percentage >= 40 ? 'step-dot bg-success'   : 'step-dot bg-secondary';
    if (stepFruiting) stepFruiting.className = startDate && percentage >= 70 ? 'step-dot bg-primary'   : 'step-dot bg-secondary';
    if (stepHarvest)  stepHarvest.className  = startDate && percentage >= 95 ? 'step-dot bg-success'   : 'step-dot bg-secondary';

    // Prediction text
    const predEl = document.getElementById('inc-prediction-summary');
    if (predEl) predEl.textContent = predictionText;

    // Also refresh the batch table
    renderBatchesTable();
}

async function renderIncubatorTab() {
    await fetchBoxSettings();
    renderIncubatorBoxCards();
    updateGrowthTimeline(selectedIncubatorId);
    fetchHarvestLogs();
}

async function deleteIncubatorBox(boxId) {
    if (!confirm(`Are you sure you want to remove incubator box "${boxId}"?`)) return;
    try {
        const res = await fetch(`${API_BASE_URL}/box-settings/${boxId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const result = await res.json();
        if (res.ok && result.success) {
            showNotification('Incubator box removed', 'success');
            await fetchBoxSettings();
            if (selectedIncubatorId === boxId) selectedIncubatorId = 'box_a';
            renderIncubatorTab();
        } else {
            showNotification(result.message || 'Could not delete box', 'error');
        }
    } catch (e) {
        console.error('Delete box error:', e);
    }
}

function autoFillBoxTargets(mushroomKey) {
    const targets = {
        oyster_mushroom: { tMin: 15.0, tMax: 24.0, hMin: 80.0, hMax: 95.0 },
        straw_mushroom: { tMin: 28.0, tMax: 35.0, hMin: 85.0, hMax: 95.0 },
        milky_mushroom: { tMin: 22.0, tMax: 30.0, hMin: 80.0, hMax: 92.0 },
        wood_ear: { tMin: 20.0, tMax: 28.0, hMin: 85.0, hMax: 95.0 }
    };
    const p = targets[mushroomKey] || targets.oyster_mushroom;
    const tMin = document.getElementById('new-box-temp-min');
    const tMax = document.getElementById('new-box-temp-max');
    const hMin = document.getElementById('new-box-hum-min');
    const hMax = document.getElementById('new-box-hum-max');
    if (tMin) tMin.value = p.tMin;
    if (tMax) tMax.value = p.tMax;
    if (hMin) hMin.value = p.hMin;
    if (hMax) hMax.value = p.hMax;
}

function resetCreateBoxModal() {
    const createBoxForm = document.getElementById('createBoxForm');
    if (createBoxForm) createBoxForm.reset();
    const idInput = document.getElementById('new-box-id');
    if (idInput) idInput.readOnly = false;
    const modalTitle = document.getElementById('createBoxModalLabel');
    if (modalTitle) modalTitle.innerHTML = `<i class="fas fa-box-open me-2 text-success"></i>Add New Incubator Box`;
    autoFillBoxTargets('oyster_mushroom');
}

function openBoxSettingsModal(boxId) {
    const box = allRegisteredBoxes[boxId];
    if (!box) return;

    const idInput = document.getElementById('new-box-id');
    const nameInput = document.getElementById('new-box-name');
    const typeSelect = document.getElementById('new-box-mushroom-type');
    const stageSelect = document.getElementById('new-box-stage');
    const tMinInput = document.getElementById('new-box-temp-min');
    const tMaxInput = document.getElementById('new-box-temp-max');
    const hMinInput = document.getElementById('new-box-hum-min');
    const hMaxInput = document.getElementById('new-box-hum-max');

    if (idInput) { idInput.value = box.box_id; idInput.readOnly = true; }
    if (nameInput) nameInput.value = box.name || '';
    if (typeSelect) typeSelect.value = box.mushroom_type || 'oyster_mushroom';
    if (stageSelect) stageSelect.value = box.stage || 'fruiting';
    if (tMinInput) tMinInput.value = box.temp_min ?? 15.0;
    if (tMaxInput) tMaxInput.value = box.temp_max ?? 24.0;
    if (hMinInput) hMinInput.value = box.hum_min ?? 80.0;
    if (hMaxInput) hMaxInput.value = box.hum_max ?? 95.0;

    const modalTitle = document.getElementById('createBoxModalLabel');
    if (modalTitle) modalTitle.innerHTML = `<i class="fas fa-gear me-2 text-success"></i>Configure ${escapeHtml(box.name)}`;

    const modalEl = document.getElementById('createBoxModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        let bsModal = bootstrap.Modal.getInstance(modalEl);
        if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const createBoxForm = document.getElementById('createBoxForm');
    if (createBoxForm) {
        createBoxForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const boxId = document.getElementById('new-box-id').value.trim().toLowerCase().replace(/\s+/g, '_');
            const name = document.getElementById('new-box-name').value;
            const mushroomType = document.getElementById('new-box-mushroom-type').value;
            const stage = document.getElementById('new-box-stage').value;
            const tempMin = parseFloat(document.getElementById('new-box-temp-min').value) || null;
            const tempMax = parseFloat(document.getElementById('new-box-temp-max').value) || null;
            const humMin = parseFloat(document.getElementById('new-box-hum-min').value) || null;
            const humMax = parseFloat(document.getElementById('new-box-hum-max').value) || null;

            try {
                const response = await fetch(`${API_BASE_URL}/box-settings`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        box_id: boxId,
                        name: name,
                        mushroom_type: mushroomType,
                        stage: stage,
                        temp_min: tempMin,
                        temp_max: tempMax,
                        hum_min: humMin,
                        hum_max: humMax,
                        misting_mode: 'auto'
                    })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    showNotification(`New incubator box "${name}" created!`, 'success');
                    createBoxForm.reset();
                    
                    const modalEl = document.getElementById('createBoxModal');
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        const bsModal = bootstrap.Modal.getInstance(modalEl);
                        if (bsModal) bsModal.hide();
                    }

                    await fetchBoxSettings();
                    selectedIncubatorId = boxId;
                    renderIncubatorTab();
                } else {
                    showNotification(data.message || 'Failed to create box', 'error');
                }
            } catch (err) {
                console.error('Error creating box:', err);
                showNotification('Network error creating box.', 'error');
            }
        });
    }
});

function renderBatchesTable() {
    const tbody = document.getElementById('inc-batches-tbody');
    if (!tbody) return;

    const batches = getBatches();
    if (batches.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">No batches registered.</td></tr>`;
        return;
    }

    const typeLabels = {
        oyster_mushroom: 'Oyster Mushroom',
        straw_mushroom: 'Straw Mushroom',
        milky_mushroom: 'Milky Mushroom',
        wood_ear: 'Wood Ear'
    };

    const incubatorLabels = {
        incubator_a: 'Incubator Room A',
        incubator_b: 'Incubator Room B',
        incubator_c: 'Incubator Room C'
    };

    tbody.innerHTML = batches.map(b => `
        <tr>
            <td><strong>${escapeHtml(b.name)}</strong></td>
            <td>${escapeHtml(typeLabels[b.type] || b.type)}</td>
            <td><span class="badge bg-secondary">${escapeHtml(incubatorLabels[b.incubator] || b.incubator)}</span></td>
            <td>${b.bags} bags</td>
            <td>${b.start_date}</td>
            <td><span class="badge bg-success">${escapeHtml(b.stage)}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-success py-0 px-2 me-1" title="Log Harvest for this batch" onclick="openHarvestModalForBatch('${b.id}')">
                    <i class="fas fa-basket-shopping me-1"></i> Harvest
                </button>
                <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete batch" onclick="deleteBatch('${b.id}')">
                    <i class="fas fa-trash-can"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function deleteBatch(id) {
    const batches = getBatches().filter(b => b.id !== id);
    saveBatches(batches);
    renderIncubatorTab();
    showNotification('Incubation batch removed successfully', 'success');
}

// Wire up incubator view listeners
document.addEventListener('DOMContentLoaded', () => {
    const newBatchForm = document.getElementById('newBatchForm');
    if (newBatchForm) {
        newBatchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const name = document.getElementById('batch-name').value;
            const type = document.getElementById('batch-type').value;
            const incubator = document.getElementById('batch-incubator').value;
            const bags = parseInt(document.getElementById('batch-bags').value, 10);
            const start_date = new Date().toISOString().split('T')[0];

            const newBatch = {
                id: 'b_' + Date.now(),
                name,
                type,
                incubator,
                bags,
                start_date,
                stage: 'Incubating'
            };

            const batches = getBatches();
            batches.push(newBatch);
            saveBatches(batches);

            // Auto-select the box this batch was assigned to
            selectedIncubatorId = incubator;

            renderIncubatorTab();
            newBatchForm.reset();
            
            // Collapse form
            const collapseEl = document.getElementById('addBatchCollapse');
            if (collapseEl && typeof bootstrap !== 'undefined') {
                const bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
                if (bsCollapse) bsCollapse.hide();
            }

            showNotification('Successfully registered new incubation batch!', 'success');
        });
    }
});

// ── Target parameter custom setting tweaks (Recommendation 1 & 9) ──
const defaultSpeciesCatalog = {
    oyster_mushroom: { temp_min: 15.0, temp_max: 24.0, hum_min: 80.0, hum_max: 95.0 },
    straw_mushroom: { temp_min: 28.0, temp_max: 35.0, hum_min: 85.0, hum_max: 95.0 },
    milky_mushroom: { temp_min: 22.0, temp_max: 30.0, hum_min: 80.0, hum_max: 92.0 },
    wood_ear: { temp_min: 20.0, temp_max: 28.0, hum_min: 85.0, hum_max: 95.0 }
};

function getActiveMushroomType() {
    const select = document.getElementById('mushroom-type-select');
    return select ? select.value : 'oyster_mushroom';
}

function getSpeciesCatalog() {
    if (!localStorage.getItem('custom_catalog')) {
        localStorage.setItem('custom_catalog', JSON.stringify(defaultSpeciesCatalog));
    }
    return JSON.parse(localStorage.getItem('custom_catalog'));
}

function saveSpeciesCatalog(catalog) {
    localStorage.setItem('custom_catalog', JSON.stringify(catalog));
}

function renderSettingsTab() {
    const type = getActiveMushroomType();
    const catalog = getSpeciesCatalog();
    const targets = catalog[type] || defaultSpeciesCatalog[type];

    const tempMin = document.getElementById('tweak-temp-min');
    const tempMax = document.getElementById('tweak-temp-max');
    const humMin = document.getElementById('tweak-hum-min');
    const humMax = document.getElementById('tweak-hum-max');

    if (tempMin) tempMin.value = targets.temp_min;
    if (tempMax) tempMax.value = targets.temp_max;
    if (humMin) humMin.value = targets.hum_min;
    if (humMax) humMax.value = targets.hum_max;

    // Load inventory logs too
    renderInventoryTable();
}

function resetSpeciesTargets() {
    const type = getActiveMushroomType();
    const catalog = getSpeciesCatalog();
    catalog[type] = { ...defaultSpeciesCatalog[type] };
    saveSpeciesCatalog(catalog);
    renderSettingsTab();
    fetchGrowOverview();
    showNotification('Targets reset to default parameters', 'success');
}

// Intercept Grow Settings Fetch/Save to merge local custom parameters
document.addEventListener('DOMContentLoaded', () => {
    const tweakForm = document.getElementById('tweakTargetsForm');
    if (tweakForm) {
        tweakForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const type = getActiveMushroomType();
            const catalog = getSpeciesCatalog();

            catalog[type] = {
                temp_min: parseFloat(document.getElementById('tweak-temp-min').value),
                temp_max: parseFloat(document.getElementById('tweak-temp-max').value),
                hum_min: parseFloat(document.getElementById('tweak-hum-min').value),
                hum_max: parseFloat(document.getElementById('tweak-hum-max').value)
            };

            saveSpeciesCatalog(catalog);
            showNotification('Custom targets saved. Auto misting system rules updated!', 'success');
            fetchGrowOverview();
        });
    }
});

// ── Dynamic Alerts Rule Check & Notification Simulator (Recommendation 2) ──
function triggerAlertSimulation() {
    const localNotifs = JSON.parse(localStorage.getItem('local_notifications') || '[]');
    
    // Check which alert triggers are checked
    const alertTypes = [];
    if (document.getElementById('alert-temp-high')?.checked) alertTypes.push({ title: 'Critical High Temperature', body: 'Grow room temperature reached 29.4°C exceeding safe thresholds.' });
    if (document.getElementById('alert-temp-low')?.checked) alertTypes.push({ title: 'Low Temperature Warning', body: 'Incubator Room B fell below 17.5°C requiring climate adjustment.' });
    if (document.getElementById('alert-hum-low')?.checked) alertTypes.push({ title: 'Low Humidity Alert', body: 'Relative humidity dropped to 72% RH — Misting system auto-started.' });

    if (alertTypes.length === 0) {
        showNotification('Please enable at least one alert switch to run test.', 'error');
        return;
    }

    // Pick one alert randomly
    const alertMeta = alertTypes[Math.floor(Math.random() * alertTypes.length)];

    const newAlert = {
        id: 'local_' + Date.now(),
        title: alertMeta.title,
        body: alertMeta.body,
        read: false,
        created_at: new Date().toISOString()
    };

    localNotifs.push(newAlert);
    localStorage.setItem('local_notifications', JSON.stringify(localNotifs));
    
    // Refresh Bell and notifications
    fetchNotifications();

    showNotification(`Test Notification Triggered: "${alertMeta.title}"`, 'success');
}

// ── Inventory Logs System (Recommendation 9) ──
const defaultInventory = [
    { id: 'i1', name: 'Premium Oyster Grain Spawn', category: 'Spawn', qty: 10, unit: 'bags', date: '2026-07-01' },
    { id: 'i2', name: 'Dry Rice Straw Substrate Bags', category: 'Substrate', qty: 250, unit: 'bags', date: '2026-06-29' },
    { id: 'i3', name: 'Precision Misting Spray Nozzles', category: 'Hardware', qty: 8, unit: 'units', date: '2026-06-20' },
    { id: 'i4', name: 'Oyster Mushroom Yield Batch #3', category: 'Harvest', qty: 14.8, unit: 'kg', date: '2026-07-02' }
];

function getInventory() {
    if (!localStorage.getItem('inventory_items')) {
        localStorage.setItem('inventory_items', JSON.stringify(defaultInventory));
    }
    return JSON.parse(localStorage.getItem('inventory_items'));
}

function saveInventory(items) {
    localStorage.setItem('inventory_items', JSON.stringify(items));
}

function renderInventoryTable() {
    const tbody = document.getElementById('inv-tbody');
    if (!tbody) return;

    const items = getInventory();
    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">Inventory logs empty.</td></tr>`;
        return;
    }

    tbody.innerHTML = items.map(i => `
        <tr>
            <td><strong>${escapeHtml(i.name)}</strong></td>
            <td><span class="badge rounded-pill bg-primary bg-opacity-10 text-success">${escapeHtml(i.category)}</span></td>
            <td>${i.qty} ${escapeHtml(i.unit)}</td>
            <td>${i.date}</td>
            <td>
                <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteInventoryItem('${i.id}')"><i class="fas fa-trash-can"></i></button>
            </td>
        </tr>
    `).join('');
}

function deleteInventoryItem(id) {
    const items = getInventory().filter(i => i.id !== id);
    saveInventory(items);
    renderInventoryTable();
    showNotification('Inventory item removed successfully', 'success');
}

// Add inventory logger listener
document.addEventListener('DOMContentLoaded', () => {
    const invForm = document.getElementById('newInventoryForm');
    if (invForm) {
        invForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const name = document.getElementById('inv-item').value;
            const category = document.getElementById('inv-category').value;
            const qty = parseFloat(document.getElementById('inv-quantity').value);
            const unit = document.getElementById('inv-unit').value;
            const date = new Date().toISOString().split('T')[0];

            const newItem = {
                id: 'i_' + Date.now(),
                name,
                category,
                qty,
                unit,
                date
            };

            const items = getInventory();
            items.push(newItem);
            saveInventory(items);

            renderInventoryTable();
            invForm.reset();

            // Collapse form container
            const collapseEl = document.getElementById('addInventoryCollapse');
            if (collapseEl && typeof bootstrap !== 'undefined') {
                const bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
                if (bsCollapse) bsCollapse.hide();
            }

            showNotification('Item added to Inventory log.', 'success');
        });
    }
});

// ── Reports Exporter: CSV history downloading (Recommendation 4) ──
async function downloadSensorCsv() {
    try {
        const response = await fetch(`${API_BASE_URL}/sensor-data/history?limit=100`);
        const history = await response.json();
        
        if (!response.ok || !Array.isArray(history) || history.length === 0) {
            showNotification('No sensor logs available to export.', 'error');
            return;
        }

        const dateRange = document.getElementById('export-range')?.value || 'all';
        let filteredLogs = [...history];

        if (dateRange === 'today') {
            const todayStr = new Date().toISOString().split('T')[0];
            filteredLogs = history.filter(item => item.recorded_at.startsWith(todayStr));
        } else if (dateRange === 'week') {
            const weekAgo = new Date();
            weekAgo.setDate(weekAgo.getDate() - 7);
            filteredLogs = history.filter(item => new Date(item.recorded_at) >= weekAgo);
        }

        if (filteredLogs.length === 0) {
            showNotification('No records found matching date filter.', 'error');
            return;
        }

        // Header
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Index,Timestamp,Temperature (C),Humidity (%),WiFi RSSI (dBm),Misting System State,Misting Mode,Misting Reason\r\n";

        // Rows
        filteredLogs.forEach((item, index) => {
            const row = [
                index + 1,
                item.recorded_at,
                item.temperature !== null ? item.temperature : '--',
                item.humidity !== null ? item.humidity : '--',
                item.wifi_rssi !== null ? item.wifi_rssi : '--',
                item.misting_system ? 'ON' : 'OFF',
                item.misting_source || '--',
                item.misting_reason || '--'
            ].join(",");
            csvContent += row + "\r\n";
        });

        // Download link trigger
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `kabutech_sensor_report_${dateRange}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showNotification('Sensor logs downloaded as CSV successfully!', 'success');
    } catch (e) {
        console.error(e);
        showNotification('Failed to generate report export.', 'error');
    }
}

// ── Harvest & Yield Tracking JS Functions ──
let cachedHarvestLogs = [];

async function fetchHarvestLogs() {
    try {
        const response = await fetch(`${API_BASE_URL}/harvest-logs`);
        if (!response.ok) return;
        const data = await response.json();
        if (data.success) {
            cachedHarvestLogs = data.logs || [];
            renderHarvestSummary(data.summary);
            renderHarvestTable(cachedHarvestLogs);
        }
    } catch (e) {
        console.error('Error fetching harvest logs:', e);
    }
}

function renderHarvestSummary(summary) {
    if (!summary) return;
    const totalWeightEl = document.getElementById('harvest-summary-total-weight');
    const totalFlushesEl = document.getElementById('harvest-summary-total-flushes');
    const avgBeEl = document.getElementById('harvest-summary-avg-be');

    if (totalWeightEl) totalWeightEl.textContent = `${(summary.total_weight_kg || 0).toFixed(2)} kg`;
    if (totalFlushesEl) totalFlushesEl.textContent = summary.total_flushes || 0;
    if (avgBeEl) avgBeEl.textContent = `${(summary.avg_be_percentage || 0).toFixed(1)}%`;
}

function renderHarvestTable(logs) {
    const tbody = document.getElementById('harvest-logs-tbody');
    if (!tbody) return;

    if (!logs || logs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-3">No harvest logs recorded yet. Click "Log Harvest" above to record your first yield!</td></tr>`;
        return;
    }

    const speciesNames = {
        oyster_mushroom: 'Oyster Mushroom',
        straw_mushroom: 'Straw Mushroom',
        milky_mushroom: 'Milky Mushroom',
        wood_ear: 'Wood Ear'
    };

    tbody.innerHTML = logs.map(l => {
        const dateStr = l.harvested_date_formatted || (l.harvested_at ? l.harvested_at.split('T')[0] : 'Today');
        const beBadge = l.biological_efficiency != null 
            ? `<span class="badge bg-warning text-dark">${l.biological_efficiency}%</span>` 
            : `<span class="text-muted small">N/A</span>`;

        return `
            <tr>
                <td><small class="text-muted">${escapeHtml(dateStr)}</small></td>
                <td><strong>${escapeHtml(l.batch_name)}</strong></td>
                <td>${escapeHtml(speciesNames[l.mushroom_type] || l.mushroom_type)}</td>
                <td><span class="badge bg-info text-dark">Flush #${l.flush_number}</span></td>
                <td><strong class="text-success">${l.weight_grams} g</strong> <small class="text-muted">(${l.weight_kg} kg)</small></td>
                <td>${beBadge}</td>
                <td><span class="badge bg-secondary">${escapeHtml(l.quality || 'Grade A')}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete harvest record" onclick="deleteHarvestLog(${l.id})">
                        <i class="fas fa-trash-can"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function deleteHarvestLog(id) {
    if (!confirm('Are you sure you want to delete this harvest record?')) return;
    try {
        const response = await fetch(`${API_BASE_URL}/harvest-logs/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        if (response.ok) {
            showNotification('Harvest record removed successfully', 'success');
            fetchHarvestLogs();
        }
    } catch (e) {
        console.error('Delete harvest error:', e);
    }
}

function openHarvestModalForBatch(batchId) {
    const batches = getBatches();
    const batch = batches.find(b => b.id === batchId);
    if (!batch) return;

    const nameInput = document.getElementById('harvest-batch-name');
    const typeInput = document.getElementById('harvest-mushroom-type');
    const dateInput = document.getElementById('harvest-date');

    if (nameInput) nameInput.value = batch.name;
    if (typeInput) typeInput.value = batch.type;
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

    const modalEl = document.getElementById('logHarvestModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

// Log Harvest Form submit listener
document.addEventListener('DOMContentLoaded', () => {
    const logHarvestForm = document.getElementById('logHarvestForm');
    if (logHarvestForm) {
        logHarvestForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('harvest-submit-btn');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…'; }

            const payload = {
                batch_name: document.getElementById('harvest-batch-name').value,
                mushroom_type: document.getElementById('harvest-mushroom-type').value,
                flush_number: parseInt(document.getElementById('harvest-flush-number').value, 10) || 1,
                weight_grams: parseFloat(document.getElementById('harvest-weight-grams').value) || 0,
                substrate_weight_grams: parseFloat(document.getElementById('harvest-substrate-weight').value) || null,
                quality: document.getElementById('harvest-quality').value,
                harvested_at: document.getElementById('harvest-date').value || null,
                notes: document.getElementById('harvest-notes').value || ''
            };

            try {
                const response = await fetch(`${API_BASE_URL}/harvest-logs`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> Save Harvest Entry'; }

                if (response.ok && data.success) {
                    showNotification('Harvest yield entry logged successfully!', 'success');
                    logHarvestForm.reset();
                    
                    const modalEl = document.getElementById('logHarvestModal');
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        const bsModal = bootstrap.Modal.getInstance(modalEl);
                        if (bsModal) bsModal.hide();
                    }
                    
                    fetchHarvestLogs();
                } else {
                    showNotification(data.message || 'Failed to record harvest entry.', 'error');
                }
            } catch (err) {
                console.error('Error logging harvest:', err);
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> Save Harvest Entry'; }
                showNotification('Network error recording harvest entry.', 'error');
            }
        });
    }
});

// ── 1. Yield vs Environmental Correlation Analytics ──
async function loadCorrelationAnalytics() {
    const tbody = document.getElementById('correlation-table-body');
    if (!tbody) return;

    try {
        tbody.innerHTML = `
            <tr><td colspan="7" class="py-2"><div class="skeleton-loader skeleton-row"></div></td></tr>
            <tr><td colspan="7" class="py-2"><div class="skeleton-loader skeleton-row"></div></td></tr>
            <tr><td colspan="7" class="py-2"><div class="skeleton-loader skeleton-row"></div></td></tr>
        `;
        const res = await fetch(`${API_BASE_URL}/analytics/correlation`);
        const result = await res.json();

        if (!result.success || !result.data || result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2 text-info"></i>No harvest logs recorded yet. Log a mushroom harvest entry to generate environmental correlation statistics.</td></tr>';
            return;
        }

        let html = '';
        result.data.forEach(item => {
            let ratingBadge = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Optimal Conditions</span>';
            if (item.avg_temp_7d > 28 || item.avg_hum_7d < 80) {
                ratingBadge = '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>Sub-Optimal Environment</span>';
            }

            html += `
                <tr>
                    <td class="fw-semibold">${item.batch_name}</td>
                    <td>${item.harvested_at}</td>
                    <td><span class="badge bg-secondary">Flush ${item.flush_number}</span></td>
                    <td class="fw-bold text-success">${item.weight_grams} g</td>
                    <td>${item.avg_temp_7d} °C</td>
                    <td>${item.avg_hum_7d} %</td>
                    <td>${ratingBadge}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    } catch (e) {
        console.error('Error loading correlation analytics:', e);
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">Failed to load correlation analytics.</td></tr>';
    }
}

// ── 2. Hardware Diagnostics & Health Metrics ──
async function loadHardwareDiagnostics() {
    const rssiEl = document.getElementById('diag-wifi-rssi');
    const runtimeEl = document.getElementById('diag-pump-runtime');
    const mistingCountEl = document.getElementById('diag-misting-count');
    const packetCountEl = document.getElementById('diag-packet-count');

    if (rssiEl) rssiEl.innerHTML = '<div class="skeleton-loader skeleton-text-sm mx-auto"></div>';
    if (runtimeEl) runtimeEl.innerHTML = '<div class="skeleton-loader skeleton-text-sm mx-auto"></div>';
    if (mistingCountEl) mistingCountEl.innerHTML = '<div class="skeleton-loader skeleton-text-sm mx-auto"></div>';
    if (packetCountEl) packetCountEl.innerHTML = '<div class="skeleton-loader skeleton-text-sm mx-auto"></div>';

    try {
        const res = await fetch(`${API_BASE_URL}/diagnostics/hardware`);
        const result = await res.json();
        if (result.success && result.data) {
            const data = result.data;
            const rssiStatusEl = document.getElementById('diag-wifi-status');
            const dutyEl = document.getElementById('diag-pump-duty');
            const deviceConnEl = document.getElementById('diag-device-connection');

            if (rssiEl) rssiEl.textContent = `${data.avg_wifi_rssi_dbm} dBm`;
            if (rssiStatusEl) {
                rssiStatusEl.textContent = data.wifi_signal_quality;
                rssiStatusEl.className = data.avg_wifi_rssi_dbm >= -70 ? 'badge bg-success' : 'badge bg-warning';
            }
            if (runtimeEl) runtimeEl.textContent = `${data.pump_runtime_minutes_24h} min`;
            if (dutyEl) dutyEl.textContent = `Duty cycle: ${data.estimated_duty_cycle_pct}%`;
            if (mistingCountEl) mistingCountEl.textContent = `${data.misting_activations_24h} bursts`;
            if (packetCountEl) packetCountEl.textContent = data.telemetry_packets_24h;
            if (deviceConnEl) {
                deviceConnEl.innerHTML = data.device_connected 
                    ? '<span class="pulse-ring-dot me-1"></span> Device Online' 
                    : '<span class="badge bg-danger me-1">Offline</span> Device Standby';
                deviceConnEl.className = data.device_connected ? 'small text-success fw-bold d-flex align-items-center justify-content-center gap-1' : 'small text-danger fw-bold';
            }
        }
    } catch (e) {
        console.error('Error loading hardware diagnostics:', e);
    }
}

// ── 3. Camera Snapshots Gallery & Capture ──
let currentActiveSnapshotId = null;

async function loadCameraSnapshots() {
    const grid = document.getElementById('camera-snapshots-grid');
    if (!grid) return;

    try {
        grid.innerHTML = `
            <div class="col-md-4 col-sm-6 mb-3"><div class="skeleton-loader skeleton-card"></div></div>
            <div class="col-md-4 col-sm-6 mb-3"><div class="skeleton-loader skeleton-card"></div></div>
            <div class="col-md-4 col-sm-6 mb-3"><div class="skeleton-loader skeleton-card"></div></div>
        `;
        const res = await fetch(`${API_BASE_URL}/camera/snapshots`);
        const result = await res.json();

        if (!result.success || !result.data || result.data.length === 0) {
            grid.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="fas fa-camera text-secondary fa-3x mb-3 d-block"></i><p class="mb-0">No archived camera snapshots found.</p><small class="text-muted">Click "Capture Snapshot Now" above to save a snapshot from your camera feed.</small></div>';
            return;
        }

        let html = '';
        result.data.forEach(item => {
            html += `
                <div class="col-md-4 col-sm-6">
                    <div class="card glass-card h-100 overflow-hidden border border-secondary shadow-sm">
                        <img src="${item.url}" class="card-img-top" alt="Snapshot" style="height: 180px; object-fit: cover; cursor: pointer;" onclick="openSnapshotModal(${item.id}, '${item.url}', '${item.box_id}', '${item.formatted_time}', '${item.notes || ''}')">
                        <div class="card-body p-2 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold text-light d-block">${item.formatted_time}</small>
                                <span class="badge bg-secondary" style="font-size: 0.65rem;">${item.box_id.toUpperCase()}</span>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSnapshot(${item.id})"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            `;
        });
        grid.innerHTML = html;
    } catch (e) {
        console.error('Error loading camera snapshots:', e);
        if (grid) grid.innerHTML = '<div class="col-12 text-center text-danger py-4">Error loading camera snapshots gallery.</div>';
    }
}

async function triggerCameraSnapshot(btnEl) {
    const triggerBtn = btnEl || event?.currentTarget || document.querySelector('#camera-gallery-section button.btn-danger');
    if (triggerBtn) {
        triggerBtn.classList.add('btn-loading');
        triggerBtn.disabled = true;
    }

    try {
        const streamImg = document.getElementById('camera-stream');
        let imageBase64 = null;

        // Extract current frame from live stream image if available
        if (streamImg && streamImg.complete && streamImg.naturalWidth > 0) {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = streamImg.naturalWidth || 640;
                canvas.height = streamImg.naturalHeight || 480;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(streamImg, 0, 0);
                imageBase64 = canvas.toDataURL('image/jpeg', 0.85);
            } catch (canvasErr) {
                console.warn('CORS or canvas snapshot error, falling back to server snapshot handler:', canvasErr);
            }
        }

        showNotification('Capturing camera snapshot…', 'info');

        const response = await fetch(`${API_BASE_URL}/camera/snapshots`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                box_id: 'box_a',
                notes: 'Captured via dashboard live view',
                image_base64: imageBase64
            })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            showNotification('Camera snapshot captured and archived!', 'success');
            loadCameraSnapshots();
        } else {
            showNotification(data.message || 'Failed to capture snapshot', 'error');
        }
    } catch (e) {
        console.error('Snapshot capture error:', e);
        showNotification('Network error capturing camera snapshot.', 'error');
    } finally {
        if (triggerBtn) {
            triggerBtn.classList.remove('btn-loading');
            triggerBtn.disabled = false;
        }
    }
}

function openSnapshotModal(id, url, boxId, timeStr, notes) {
    currentActiveSnapshotId = id;
    const img = document.getElementById('snapshot-modal-img');
    const timeEl = document.getElementById('snapshot-modal-time');
    const boxEl = document.getElementById('snapshot-modal-box');
    const notesEl = document.getElementById('snapshot-modal-notes');
    const deleteBtn = document.getElementById('btn-delete-snapshot');

    if (img) img.src = url;
    if (timeEl) timeEl.innerHTML = `<i class="far fa-clock me-1"></i>${timeStr}`;
    if (boxEl) boxEl.innerHTML = `<i class="fas fa-box me-1"></i>${boxId.toUpperCase()}`;
    if (notesEl) notesEl.textContent = notes || 'No additional notes.';

    if (deleteBtn) {
        deleteBtn.onclick = function() {
            if (currentActiveSnapshotId) {
                deleteSnapshot(currentActiveSnapshotId);
                const modalEl = document.getElementById('viewSnapshotModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    const bsModal = bootstrap.Modal.getInstance(modalEl);
                    if (bsModal) bsModal.hide();
                }
            }
        };
    }

    const modalEl = document.getElementById('viewSnapshotModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

async function deleteSnapshot(id) {
    if (!confirm('Are you sure you want to delete this camera snapshot?')) return;
    try {
        const response = await fetch(`${API_BASE_URL}/camera/snapshots/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        if (response.ok) {
            showNotification('Snapshot deleted', 'success');
            loadCameraSnapshots();
        }
    } catch (e) {
        console.error('Delete snapshot error:', e);
    }
}

// ── Camera Stream & Controls Manager ──
let cameraMode = 'mjpeg'; // 'mjpeg' | 'snapshot'
let cameraSnapshotTimer = null;
let cameraFlashActive = false;
let cameraRetryCount = 0;
let cameraIsLoading = false;

function initCameraStream() {
    const streamEl = document.getElementById('camera-stream');
    if (!streamEl) return;

    // Check camera status in background
    checkCameraStatus();

    // Auto health-check interval for stalled MJPEG stream
    setInterval(function() {
        if (cameraMode === 'mjpeg' && streamEl.style.display !== 'none') {
            if (streamEl.complete && streamEl.naturalWidth === 0) {
                retryCameraStream();
            }
        }
    }, 15000);
}

function updateCameraBadge(status, text) {
    const dot = document.getElementById('camera-pulse-indicator');
    const label = document.getElementById('camera-status-text');
    const info = document.getElementById('camera-stream-info');

    if (!dot || !label) return;

    dot.className = 'camera-pulse-dot ' + (status === 'live' ? '' : (status === 'connecting' ? 'connecting' : 'offline'));
    label.textContent = text || status.toUpperCase();

    if (info) {
        if (status === 'live') {
            info.textContent = cameraMode === 'mjpeg' ? 'Live Stream 30FPS' : 'Fast Refresh Active';
        } else if (status === 'connecting') {
            info.textContent = 'Connecting…';
        } else {
            info.textContent = 'Offline';
        }
    }
}

function onCameraStreamLoad(img) {
    cameraRetryCount = 0;
    const offlineEl = document.getElementById('camera-offline');
    if (offlineEl) offlineEl.style.display = 'none';
    if (img) img.style.display = 'block';
    updateCameraBadge('live', cameraMode === 'mjpeg' ? 'LIVE' : 'FAST REFRESH');
}

window.cameraStreamError = function(img) {
    console.warn('Camera stream error');
    cameraRetryCount++;
    const offlineEl = document.getElementById('camera-offline');

    if (cameraRetryCount >= 2 && cameraMode === 'mjpeg') {
        console.log('MJPEG stream failing, switching to fast snapshot mode');
        setCameraMode('snapshot');
        return;
    }

    if (img) img.style.display = 'none';
    if (offlineEl) offlineEl.style.display = 'block';
    updateCameraBadge('offline', 'OFFLINE');
};

function retryCameraStream() {
    const streamEl = document.getElementById('camera-stream');
    const offlineEl = document.getElementById('camera-offline');
    if (!streamEl) return;

    updateCameraBadge('connecting', 'RECONNECTING');
    if (offlineEl) offlineEl.style.display = 'none';
    streamEl.style.display = 'block';

    if (cameraMode === 'mjpeg') {
        const baseSrc = `${API_BASE_URL}/camera-stream`;
        streamEl.src = `${baseSrc}?_t=${Date.now()}`;
    } else {
        fetchNextSnapshotFrame();
    }
}

function setCameraMode(mode) {
    cameraMode = mode;
    const btnMjpeg = document.getElementById('btn-mode-mjpeg');
    const btnSnapshot = document.getElementById('btn-mode-snapshot');
    const streamEl = document.getElementById('camera-stream');

    if (btnMjpeg && btnSnapshot) {
        btnMjpeg.classList.toggle('active', mode === 'mjpeg');
        btnSnapshot.classList.toggle('active', mode === 'snapshot');
    }

    if (cameraSnapshotTimer) {
        clearInterval(cameraSnapshotTimer);
        cameraSnapshotTimer = null;
    }

    if (mode === 'mjpeg') {
        if (streamEl) {
            updateCameraBadge('connecting', 'STARTING STREAM');
            streamEl.src = `${API_BASE_URL}/camera-stream?_t=${Date.now()}`;
        }
    } else {
        updateCameraBadge('connecting', 'REFRESH MODE');
        fetchNextSnapshotFrame();
        cameraSnapshotTimer = setInterval(fetchNextSnapshotFrame, 1200);
    }
}

function fetchNextSnapshotFrame() {
    const streamEl = document.getElementById('camera-stream');
    if (!streamEl || cameraIsLoading) return;

    cameraIsLoading = true;
    const imgPreloader = new Image();
    const frameUrl = `${API_BASE_URL}/camera/live-frame?_t=${Date.now()}`;

    imgPreloader.onload = function() {
        streamEl.src = frameUrl;
        onCameraStreamLoad(streamEl);
        cameraIsLoading = false;
    };
    imgPreloader.onerror = function() {
        cameraIsLoading = false;
        if (cameraMode === 'snapshot') {
            updateCameraBadge('offline', 'OFFLINE');
        }
    };
    imgPreloader.src = frameUrl;
}

async function checkCameraStatus() {
    try {
        const res = await fetch(`${API_BASE_URL}/camera/status`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.online) {
            updateCameraBadge('live', 'ONLINE');
        }
    } catch (e) {
        // quiet
    }
}

async function toggleCameraFlash() {
    const btn = document.getElementById('btn-toggle-flash');
    cameraFlashActive = !cameraFlashActive;
    const intensity = cameraFlashActive ? 255 : 0;

    if (btn) {
        btn.classList.toggle('btn-warning', cameraFlashActive);
        btn.classList.toggle('btn-outline-warning', !cameraFlashActive);
    }

    try {
        showNotification(cameraFlashActive ? 'Turning Camera Flash ON…' : 'Turning Camera Flash OFF…', 'info');
        const res = await fetch(`${API_BASE_URL}/camera/control?var=led_intensity&val=${intensity}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (data.success) {
            showNotification(cameraFlashActive ? 'Camera Flash turned ON' : 'Camera Flash turned OFF', 'success');
        } else {
            showNotification('Could not control flash LED', 'warning');
        }
    } catch (e) {
        console.error('Camera flash toggle error:', e);
    }
}

function toggleCameraFullscreen() {
    const player = document.getElementById('camera-player-container');
    if (!player) return;

    if (!document.fullscreenElement) {
        if (player.requestFullscreen) {
            player.requestFullscreen();
        } else if (player.webkitRequestFullscreen) {
            player.webkitRequestFullscreen();
        } else if (player.msRequestFullscreen) {
            player.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

window.retryCameraStream = retryCameraStream;
window.setCameraMode = setCameraMode;
window.toggleCameraFlash = toggleCameraFlash;
window.toggleCameraFullscreen = toggleCameraFullscreen;
window.onCameraStreamLoad = onCameraStreamLoad;




