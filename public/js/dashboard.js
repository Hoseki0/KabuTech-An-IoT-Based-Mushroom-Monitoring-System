// API Configuration
const API_BASE_URL = '/api';
let mistingSystemStatus = false;
let updateInterval;
let historyInterval;
let historyLimit = 15;
let csrfToken = '';
let mistingMode = 'auto'; // 'auto' | 'manual'
let thChart = null;
let lastNotifIds = new Set();
let notifBootstrapped = false;

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Intro is dismissed by inline script in the view
    showSection('overview');

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
        fetchMistingStatus();
        fetchSensorData();
        fetchHistory();
        fetchGrowOverview();
        fetchNotifications();
        setupGrowControls();
        setupNotificationDropdown();
        updateInterval = setInterval(fetchSensorData, 5000);
        historyInterval = setInterval(fetchHistory, 5000);
        setInterval(fetchGrowOverview, 10000);
        setInterval(fetchNotifications, 15000);
    });
});

// Switch between menu sections (Dashboard / Sensor Data)
function showSection(section) {
    const overview = document.getElementById('overview-section');
    const history = document.getElementById('history-section');
    const navDashboard = document.getElementById('nav-dashboard');
    const navHistory = document.getElementById('nav-history');

    if (!overview || !history || !navDashboard || !navHistory) return;

    if (section === 'history') {
        overview.classList.add('d-none');
        history.classList.remove('d-none');
        navDashboard.classList.remove('active');
        navHistory.classList.add('active');
    } else {
        overview.classList.remove('d-none');
        history.classList.add('d-none');
        navDashboard.classList.add('active');
        navHistory.classList.remove('active');
    }
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

// Fetch latest sensor data
async function fetchSensorData() {
    try {
        // Add updating class to show data is being refreshed
        document.getElementById('temperature-value').classList.add('updating');
        document.getElementById('humidity-value').classList.add('updating');
        
        const response = await fetch(`${API_BASE_URL}/sensor-data/latest`);
        const data = await response.json();

        if (response.ok) {
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
        }
    } catch (error) {
        // ignore
    }
}

// Fetch sensor data history
async function fetchHistory() {
    try {
        const response = await fetch(`${API_BASE_URL}/sensor-data/history?limit=${historyLimit}`);
        const data = await response.json();

        if (response.ok) {
            updateHistoryTable(data);
            renderTrendChart(data);
        } else {
            console.error('Failed to fetch history data', data);
        }
    } catch (error) {
        console.error('Error fetching history data:', error);
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
    const accent = (css.getPropertyValue('--accent') || '#86efac').trim();

    const grid = 'rgba(255,255,255,0.10)';
    const tick = 'rgba(255,255,255,0.65)';

    const config = {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Temp (°C)',
                    data: temps,
                    borderColor: accent,
                    backgroundColor: 'rgba(134, 239, 172, 0.12)',
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    yAxisID: 'yTemp',
                },
                {
                    label: 'Humidity (%)',
                    data: hums,
                    borderColor: 'rgba(255,255,255,0.75)',
                    backgroundColor: 'rgba(255,255,255,0.08)',
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
    const autoButton = document.getElementById('misting-auto-btn');

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

    if (autoButton) {
        const isAuto = mistingMode === 'auto';
        autoButton.className = isAuto ? 'control-btn' : 'control-btn off';
    }

    const incAutoButton = document.getElementById('misting-auto-inc-btn');
    if (incAutoButton) {
        const isAuto = mistingMode === 'auto';
        incAutoButton.className = isAuto ? 'control-btn' : 'control-btn off';
    }
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

// Set misting to AUTO mode (ESP32 controls based on thresholds)
async function setAutoMisting(profile = 'fruiting') {
    const button = document.getElementById('misting-auto-btn');
    if (button) {
        button.disabled = true;
        button.classList.add('loading');
    }

    try {
        const response = await fetch(`${API_BASE_URL}/misting/control`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ status: false, mode: 'auto', profile })
        });

        const result = await response.json();
        if (response.ok && result.success) {
            mistingMode = 'auto';
            updateMistingDisplay(mistingSystemStatus);
            showNotification(`Misting set to AUTO (${profile})`, 'success');
        } else {
            throw new Error(result.message || 'Failed to set AUTO');
        }
    } catch (error) {
        console.error('Error setting auto misting:', error);
        showNotification('Failed to set AUTO mode. Please try again.', 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.classList.remove('loading');
        }
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

/** Clears server-side read state and refreshes the list; hides the count immediately. */
async function markAllNotificationsRead() {
    hideNotificationBadge();
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

/** Opening the bell hides the count immediately; list syncs when the menu closes (avoids replacing DOM while open). */
function setupNotificationDropdown() {
    ['notifDropdown', 'notifDropdownMobile'].forEach(id => {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.addEventListener('shown.bs.dropdown', () => {
            hideNotificationBadge();
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

        const unread = data.unread_count ?? 0;
        [badge, badgeMobile].forEach(b => {
            if (b) {
                b.textContent = unread > 99 ? '99+' : String(unread);
                b.classList.toggle('d-none', unread === 0);
            }
        });

        const items = data.notifications || [];
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

