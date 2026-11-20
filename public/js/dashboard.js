// API Configuration
const API_BASE_URL = `${(window.KABUTECH_BASE_URL || '').replace(/\/$/, '')}/api`;
let mistingSystemStatus = false;
let updateInterval;
let historyInterval;
let boxesInterval;
let mistingInterval;
let growInterval;
let historyLimit = 15;
let historyIntervalMode = 'raw';
let csrfToken = '';
let mistingMode = 'auto';
let mistingProfile = 'fruiting';
let fanSystemStatus = false;
let fanMode = 'auto';
let thChart = null;
let dashboardThChart = null;
let tempSparklineChart = null;
let humiditySparklineChart = null;
let dashboardChartMode = 'both';
let dashboardChartLimit = 15;
let cachedSensorHistory = [];
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

    // Sidebar toggle (drawer on mobile, collapse on desktop)
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.getElementById('sidebar');
    const pageWrapper = document.getElementById('pageWrapper');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    window.toggleMobileSidebar = function(forceOpen) {
        if (!sidebar) return;
        const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !sidebar.classList.contains('mobile-open');
        sidebar.classList.toggle('mobile-open', shouldOpen);
        if (sidebarBackdrop) sidebarBackdrop.classList.toggle('show', shouldOpen);
    };

    window.mobileNavTo = function(section, sidebarId, btnEl) {
        showSection(section);
        const sideLink = document.getElementById(sidebarId);
        if (sideLink) setSidebarActive(sideLink);
        document.querySelectorAll('.mobile-nav-item').forEach(b => b.classList.remove('active'));
        if (btnEl) btnEl.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', () => window.toggleMobileSidebar(false));
    }

    if (sidebar) {
        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    window.toggleMobileSidebar(false);
                }
            });
        });
    }

    if (sidebarToggleBtn && sidebar && pageWrapper) {
        sidebarToggleBtn.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                window.toggleMobileSidebar();
                return;
            }
            sidebar.classList.toggle('collapsed');
            pageWrapper.classList.toggle('sidebar-collapsed');
            setTimeout(() => {
                if (dashboardThChart) dashboardThChart.resize();
                if (tempSparklineChart) tempSparklineChart.resize();
                if (humiditySparklineChart) humiditySparklineChart.resize();
                if (thChart) thChart.resize();
            }, 300);
        });
    }

    // Wire up history log-count & average interval selectors
    const limitSelect = document.getElementById('history-limit-select');
    const historyIntervalSelect = document.getElementById('history-interval-select');
    const dashIntervalSelect = document.getElementById('dash-interval-select');

    const INTERVAL_LABELS = {
        raw: 'Live Readings (Every 5s)',
        '5m': '5-Minute Averages',
        '30m': '30-Minute Averages',
        '1h': '1-Hour Averages',
        '1d': 'Daily Averages',
        '1w': 'Weekly Averages',
        '1mo': 'Monthly Averages'
    };

    function applyHistoryInterval(val) {
        historyIntervalMode = val || 'raw';
        if (historyIntervalSelect && historyIntervalSelect.value !== historyIntervalMode) {
            historyIntervalSelect.value = historyIntervalMode;
        }
        if (dashIntervalSelect && dashIntervalSelect.value !== historyIntervalMode) {
            dashIntervalSelect.value = historyIntervalMode;
        }
        const label = INTERVAL_LABELS[historyIntervalMode] || 'Live Readings';
        const subtitle = document.getElementById('history-subtitle');
        if (subtitle) subtitle.textContent = `${label} • Showing up to ${historyLimit} rows`;
        const dashBadge = document.getElementById('dash-interval-badge');
        if (dashBadge) dashBadge.textContent = label;
        fetchHistory();
    }

    if (limitSelect) {
        limitSelect.addEventListener('change', function() {
            historyLimit = parseInt(this.value, 10) || 15;
            applyHistoryInterval(historyIntervalMode);
        });
    }
    if (historyIntervalSelect) {
        historyIntervalSelect.addEventListener('change', function() {
            applyHistoryInterval(this.value);
        });
    }
    if (dashIntervalSelect) {
        dashIntervalSelect.addEventListener('change', function() {
            applyHistoryInterval(this.value);
        });
    }

    // Fetch CSRF token in background (don't block initial sensor reads)
    fetchCsrfToken();

    // Initial burst — load farm readings immediately
    fetchSensorData();
    fetchBoxesData();
    fetchHistory();
    fetchGrowOverview();
    fetchNotifications();
    fetchMistingStatus();
    fetchBoxSettings();
    fetchBatches();
    setupGrowControls();
    setupNotificationDropdown();
    startLiveClock();
    setTimeout(initCameraStream, 800);

    // Polling intervals — staggered to avoid simultaneous requests
    updateInterval  = setInterval(fetchSensorData,   4000);
    boxesInterval   = setInterval(fetchBoxesData,    4000);
    historyInterval = setInterval(fetchHistory,      8000);
    mistingInterval = setInterval(fetchMistingStatus, 12000);
    growInterval    = setInterval(fetchGrowOverview,  15000);
    setInterval(fetchNotifications, 30000);

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
        setTimeout(() => {
            if (thChart) thChart.resize();
        }, 60);
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
        initCameraStream();
        loadCameraSnapshots();
    } else {
        if (overview) overview.classList.remove('d-none');
        setTimeout(() => {
            if (dashboardThChart) dashboardThChart.resize();
            if (tempSparklineChart) tempSparklineChart.resize();
            if (humiditySparklineChart) humiditySparklineChart.resize();
        }, 60);
    }
}

// Activate a sidebar link
function setSidebarActive(el) {
    document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
    if (el) el.classList.add('active');

    // Keep mobile bottom nav highlight in sync with sidebar clicks
    const map = {
        'sidebar-dashboard': 'overview',
        'sidebar-history': 'history',
        'sidebar-incubator': 'incubator',
        'sidebar-diagnostics': 'diagnostics',
    };
    const sec = el ? map[el.id] : null;
    document.querySelectorAll('.mobile-nav-item[data-section]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.section === sec);
    });
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
        const isCachedOffline = response.headers.get('X-SW-Offline') === '1' || !navigator.onLine;
        const data = await response.json();

        if (response.ok) {
            latestSensorData = data;  // cache for incubator box cards
            updateConnectionStatus(!isCachedOffline);
            setOfflineBannerVisible(isCachedOffline);
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
            if (mistingMode === 'manual' && data.desired_on !== undefined) {
                mistingSystemStatus = Boolean(data.desired_on);
            }
            updateMistingDisplay(mistingSystemStatus);
            updateMistingModeButtons();
            updateMistingModeText();

            if (data.fan_desired_mode) {
                fanMode = data.fan_desired_mode;
            }
            if (fanMode === 'manual' && data.fan_desired_on !== undefined) {
                fanSystemStatus = Boolean(data.fan_desired_on);
            }
            updateFanDisplay(fanSystemStatus);
            updateFanModeButtons();
            updateFanModeText();
        }
    } catch (error) {
        // ignore
    }
}

function updateMistingModeText() {
    const el = document.getElementById('misting-auto-status');
    if (!el) return;

    if (mistingMode === 'manual') {
        el.textContent = 'Manual mode — use Turn ON/OFF above';
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
        const fetchLimit = Math.max(historyLimit, dashboardChartLimit);
        const response = await fetch(`${API_BASE_URL}/sensor-data/history?limit=${fetchLimit}&interval=${encodeURIComponent(historyIntervalMode)}`, { signal: historyAbort.signal });
        const data = await response.json();

        if (response.ok) {
            cachedSensorHistory = data;
            updateHistoryTable(data.slice(0, historyLimit));
            renderTrendChart(data.slice(0, historyLimit));
            renderDashboardTrendChart(data);
            renderStatSparklines(data);
        } else {
            console.error('Failed to fetch history data', data);
        }
    } catch (error) {
        if (error.name !== 'AbortError') console.error('Error fetching history data:', error);
    }
}

// Sparklines for Temperature and Humidity Cards on Dashboard
function renderStatSparklines(history) {
    if (!Array.isArray(history) || history.length === 0) return;

    // Use latest 15 readings for the mini card trends (oldest -> newest)
    const recentWindow = history.slice(0, 15).reverse();
    const labels = recentWindow.map(r => {
        try {
            const d = r.recorded_at ? new Date(r.recorded_at) : null;
            return d ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
        } catch {
            return '';
        }
    });

    const temps = recentWindow.map(r => (r.temperature !== null && !isNaN(r.temperature) ? parseFloat(r.temperature) : null));
    const hums  = recentWindow.map(r => (r.humidity !== null && !isNaN(r.humidity) ? parseFloat(r.humidity) : null));

    // Valid values for min/max
    const validTemps = temps.filter(v => v !== null);
    const validHums  = hums.filter(v => v !== null);

    const minTemp = validTemps.length ? Math.min(...validTemps).toFixed(1) : '--';
    const maxTemp = validTemps.length ? Math.max(...validTemps).toFixed(1) : '--';
    const minHum  = validHums.length ? Math.min(...validHums).toFixed(1) : '--';
    const maxHum  = validHums.length ? Math.max(...validHums).toFixed(1) : '--';

    const tempMinEl = document.getElementById('temp-min-stat');
    const tempMaxEl = document.getElementById('temp-max-stat');
    const humMinEl  = document.getElementById('humidity-min-stat');
    const humMaxEl  = document.getElementById('humidity-max-stat');

    if (tempMinEl) tempMinEl.textContent = minTemp;
    if (tempMaxEl) tempMaxEl.textContent = maxTemp;
    if (humMinEl)  humMinEl.textContent = minHum;
    if (humMaxEl)  humMaxEl.textContent = maxHum;

    // Temperature Trend Indicator
    if (validTemps.length >= 2) {
        const firstT = validTemps[0];
        const lastT  = validTemps[validTemps.length - 1];
        const diffT  = lastT - firstT;
        const trendEl = document.getElementById('temp-trend-stat');
        const iconEl  = document.getElementById('temp-trend-icon');
        if (trendEl && iconEl) {
            if (diffT >= 0.2) {
                trendEl.textContent = `+${diffT.toFixed(1)}°C`;
                iconEl.className = 'fas fa-arrow-trend-up text-danger me-1';
            } else if (diffT <= -0.2) {
                trendEl.textContent = `${diffT.toFixed(1)}°C`;
                iconEl.className = 'fas fa-arrow-trend-down text-info me-1';
            } else {
                trendEl.textContent = 'Steady';
                iconEl.className = 'fas fa-minus text-muted me-1';
            }
        }
    }

    // Humidity Trend Indicator
    if (validHums.length >= 2) {
        const firstH = validHums[0];
        const lastH  = validHums[validHums.length - 1];
        const diffH  = lastH - firstH;
        const trendEl = document.getElementById('humidity-trend-stat');
        const iconEl  = document.getElementById('humidity-trend-icon');
        if (trendEl && iconEl) {
            if (diffH >= 0.5) {
                trendEl.textContent = `+${diffH.toFixed(1)}%`;
                iconEl.className = 'fas fa-arrow-trend-up text-info me-1';
            } else if (diffH <= -0.5) {
                trendEl.textContent = `${diffH.toFixed(1)}%`;
                iconEl.className = 'fas fa-arrow-trend-down text-warning me-1';
            } else {
                trendEl.textContent = 'Steady';
                iconEl.className = 'fas fa-minus text-muted me-1';
            }
        }
    }

    if (typeof Chart === 'undefined') return;

    // 1. Temperature Sparkline Chart
    const tempCanvas = document.getElementById('temp-sparkline');
    if (tempCanvas) {
        const ctx = tempCanvas.getContext('2d');
        let tempGrad = 'rgba(239, 68, 68, 0.15)';
        try {
            tempGrad = ctx.createLinearGradient(0, 0, 0, 52);
            tempGrad.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
            tempGrad.addColorStop(1, 'rgba(239, 68, 68, 0.00)');
        } catch (e) {}

        const tempConfig = {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: temps,
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    backgroundColor: tempGrad,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: '#ef4444',
                    pointHoverBackgroundColor: '#ffffff',
                    pointHoverBorderColor: '#ef4444',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        displayColors: false,
                        padding: 6,
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        borderColor: 'rgba(239, 68, 68, 0.4)',
                        borderWidth: 1,
                        callbacks: {
                            title: () => '',
                            label: (ctx) => `${ctx.parsed.y !== null ? ctx.parsed.y.toFixed(1) : '--'} °C`
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false, grace: '12%' }
                }
            }
        };

        if (tempSparklineChart) {
            tempSparklineChart.data = tempConfig.data;
            tempSparklineChart.options = tempConfig.options;
            tempSparklineChart.update();
        } else {
            tempSparklineChart = new Chart(tempCanvas, tempConfig);
        }
    }

    // 2. Humidity Sparkline Chart
    const humCanvas = document.getElementById('humidity-sparkline');
    if (humCanvas) {
        const ctx = humCanvas.getContext('2d');
        let humGrad = 'rgba(6, 182, 212, 0.15)';
        try {
            humGrad = ctx.createLinearGradient(0, 0, 0, 52);
            humGrad.addColorStop(0, 'rgba(6, 182, 212, 0.35)');
            humGrad.addColorStop(1, 'rgba(6, 182, 212, 0.00)');
        } catch (e) {}

        const humConfig = {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: hums,
                    borderColor: '#06b6d4',
                    borderWidth: 2,
                    backgroundColor: humGrad,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: '#06b6d4',
                    pointHoverBackgroundColor: '#ffffff',
                    pointHoverBorderColor: '#06b6d4',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        displayColors: false,
                        padding: 6,
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        borderColor: 'rgba(6, 182, 212, 0.4)',
                        borderWidth: 1,
                        callbacks: {
                            title: () => '',
                            label: (ctx) => `${ctx.parsed.y !== null ? ctx.parsed.y.toFixed(1) : '--'} %`
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false, grace: '12%' }
                }
            }
        };

        if (humiditySparklineChart) {
            humiditySparklineChart.data = humConfig.data;
            humiditySparklineChart.options = humConfig.options;
            humiditySparklineChart.update();
        } else {
            humiditySparklineChart = new Chart(humCanvas, humConfig);
        }
    }
}

// Real-Time Temperature & Humidity Environmental Trends Chart (Dashboard Overview)
function renderDashboardTrendChart(history) {
    const canvas = document.getElementById('dashboard-th-chart');
    if (!canvas || typeof Chart === 'undefined') return;
    if (!Array.isArray(history) || history.length === 0) return;

    // Slice based on user's dashboardChartLimit and order chronologically (oldest -> newest)
    const rows = history.slice(0, dashboardChartLimit).reverse();
    const labels = rows.map(r => {
        if (r.period_label) return r.period_label;
        try {
            const d = r.recorded_at ? new Date(r.recorded_at) : null;
            return d ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        } catch {
            return '';
        }
    });

    const temps = rows.map(r => (r.temperature !== null && !isNaN(r.temperature) ? parseFloat(r.temperature) : null));
    const hums  = rows.map(r => (r.humidity !== null && !isNaN(r.humidity) ? parseFloat(r.humidity) : null));

    // Update summary pills
    const validTemps = temps.filter(v => v !== null);
    const validHums  = hums.filter(v => v !== null);
    const minT = validTemps.length ? Math.min(...validTemps).toFixed(1) : '--';
    const maxT = validTemps.length ? Math.max(...validTemps).toFixed(1) : '--';
    const minH = validHums.length ? Math.min(...validHums).toFixed(1) : '--';
    const maxH = validHums.length ? Math.max(...validHums).toFixed(1) : '--';

    const tEl = document.getElementById('dash-chart-temp-minmax');
    if (tEl) tEl.textContent = `${minT} / ${maxT} °C`;
    const hEl = document.getElementById('dash-chart-hum-minmax');
    if (hEl) hEl.textContent = `${minH} / ${maxH} %`;

    const ctx = canvas.getContext('2d');
    let tempGrad = 'rgba(239, 68, 68, 0.12)';
    let humGrad  = 'rgba(14, 165, 233, 0.12)';
    try {
        tempGrad = ctx.createLinearGradient(0, 0, 0, 270);
        tempGrad.addColorStop(0, 'rgba(239, 68, 68, 0.25)');
        tempGrad.addColorStop(1, 'rgba(239, 68, 68, 0.00)');

        humGrad = ctx.createLinearGradient(0, 0, 0, 270);
        humGrad.addColorStop(0, 'rgba(14, 165, 233, 0.25)');
        humGrad.addColorStop(1, 'rgba(14, 165, 233, 0.00)');
    } catch (e) {}

    const datasets = [];
    if (dashboardChartMode === 'both' || dashboardChartMode === 'temp') {
        datasets.push({
            label: 'Temperature (°C)',
            data: temps,
            borderColor: '#ef4444',
            borderWidth: 2.2,
            backgroundColor: tempGrad,
            fill: true,
            tension: 0.35,
            pointRadius: rows.length > 30 ? 2 : 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#ef4444',
            pointHoverBackgroundColor: '#ffffff',
            pointHoverBorderColor: '#ef4444',
            pointHoverBorderWidth: 2,
            yAxisID: 'yTemp',
        });
    }
    if (dashboardChartMode === 'both' || dashboardChartMode === 'hum') {
        datasets.push({
            label: 'Humidity (%)',
            data: hums,
            borderColor: '#0ea5e9',
            borderWidth: 2.2,
            backgroundColor: humGrad,
            fill: true,
            tension: 0.35,
            pointRadius: rows.length > 30 ? 2 : 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#0ea5e9',
            pointHoverBackgroundColor: '#ffffff',
            pointHoverBorderColor: '#0ea5e9',
            pointHoverBorderWidth: 2,
            yAxisID: 'yHum',
        });
    }

    const gridColor = getChartGridColor(0.06);
    const tickColor = '#94a3b8';

    const config = {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        color: tickColor,
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                        font: { size: 11, weight: '500' },
                        padding: 12
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.94)',
                    titleColor: '#e2e8f0',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(255, 255, 255, 0.12)',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    usePointStyle: true,
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            const val = context.parsed.y !== null ? context.parsed.y.toFixed(1) : '--';
                            const unit = context.dataset.yAxisID === 'yTemp' ? '°C' : '%';
                            return ` ${context.dataset.label}: ${val} ${unit}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: gridColor },
                    ticks: { color: tickColor, font: { size: 11 }, maxRotation: 0, autoSkip: true }
                },
                yTemp: {
                    display: dashboardChartMode !== 'hum',
                    position: 'left',
                    grid: { color: gridColor },
                    ticks: {
                        color: '#ef4444',
                        font: { size: 11 },
                        callback: (val) => `${val}°C`
                    },
                    suggestedMin: 18,
                    suggestedMax: 35
                },
                yHum: {
                    display: dashboardChartMode !== 'temp',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: {
                        color: '#0ea5e9',
                        font: { size: 11 },
                        callback: (val) => `${val}%`
                    },
                    min: 0,
                    max: 100
                }
            }
        }
    };

    if (dashboardThChart) {
        dashboardThChart.data = config.data;
        dashboardThChart.options = config.options;
        dashboardThChart.update();
    } else {
        dashboardThChart = new Chart(canvas, config);
    }
}

function setDashboardChartMode(mode) {
    dashboardChartMode = mode;
    ['both', 'temp', 'hum'].forEach(m => {
        const btn = document.getElementById(`btn-chart-${m}`);
        if (btn) {
            if (m === mode) btn.classList.add('active');
            else btn.classList.remove('active');
        }
    });
    if (cachedSensorHistory && cachedSensorHistory.length > 0) {
        renderDashboardTrendChart(cachedSensorHistory);
    }
}

function setDashboardChartLimit(limit) {
    dashboardChartLimit = limit;
    [15, 30, 50].forEach(l => {
        const btn = document.getElementById(`btn-limit-${l}`);
        if (btn) {
            if (l === limit) btn.classList.add('active');
            else btn.classList.remove('active');
        }
    });
    if (cachedSensorHistory && cachedSensorHistory.length >= limit) {
        renderDashboardTrendChart(cachedSensorHistory);
    } else {
        fetchHistory();
    }
}

window.setDashboardChartMode = setDashboardChartMode;
window.setDashboardChartLimit = setDashboardChartLimit;

// Trend Chart for Sensor Data History tab
function renderTrendChart(history) {
    const canvas = document.getElementById('th-chart');
    if (!canvas || typeof Chart === 'undefined') return;
    if (!Array.isArray(history) || history.length === 0) return;

    // Oldest -> newest for chart
    const rows = [...history].reverse();
    const labels = rows.map(r => {
        if (r.period_label) return r.period_label;
        try {
            const d = r.recorded_at ? new Date(r.recorded_at) : null;
            return d ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        } catch {
            return '';
        }
    });

    const temps = rows.map(r => (r.temperature ?? null));
    const hums = rows.map(r => (r.humidity ?? null));

    const grid = getChartGridColor(0.08);
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

let dashboardViewMode = 'both';

function setDashboardViewMode(mode) {
    dashboardViewMode = mode;
    ['both', 'graph', 'logs'].forEach(m => {
        const btn = document.getElementById(`btn-view-${m}`);
        if (btn) {
            if (m === mode) btn.classList.add('active');
            else btn.classList.remove('active');
        }
    });

    const chartWrap = document.getElementById('dash-chart-container');
    const logsWrap = document.getElementById('dash-logs-container');

    if (mode === 'both') {
        if (chartWrap) chartWrap.style.display = 'block';
        if (logsWrap) logsWrap.style.display = 'block';
        setTimeout(() => { if (dashboardThChart) dashboardThChart.resize(); }, 50);
    } else if (mode === 'graph') {
        if (chartWrap) chartWrap.style.display = 'block';
        if (logsWrap) logsWrap.style.display = 'none';
        setTimeout(() => { if (dashboardThChart) dashboardThChart.resize(); }, 50);
    } else if (mode === 'logs') {
        if (chartWrap) chartWrap.style.display = 'none';
        if (logsWrap) logsWrap.style.display = 'block';
    }
}
window.setDashboardViewMode = setDashboardViewMode;

// Update history table (simplified 4-column Sensor Data table + Period Average Summary)
function updateHistoryTable(history) {
    const tbody = document.getElementById('history-body');
    const dashTbody = document.getElementById('dashboard-history-body');
    if (!tbody && !dashTbody) return;

    const avgTempEl = document.getElementById('history-avg-temp');
    const avgHumEl  = document.getElementById('history-avg-hum');
    const minMaxTempEl = document.getElementById('history-minmax-temp');
    const minMaxHumEl  = document.getElementById('history-minmax-hum');

    if (!Array.isArray(history) || history.length === 0) {
        const emptyMsg = `<tr><td colspan="4" class="text-center text-muted py-3">No sensor readings available for this period.</td></tr>`;
        if (tbody) tbody.innerHTML = emptyMsg;
        if (dashTbody) dashTbody.innerHTML = emptyMsg;
        if (avgTempEl) avgTempEl.textContent = '-- °C';
        if (avgHumEl) avgHumEl.textContent = '-- %';
        if (minMaxTempEl) minMaxTempEl.textContent = '-- / -- °C';
        if (minMaxHumEl) minMaxHumEl.textContent = '-- / -- %';
        return;
    }

    // Determine current species targets
    const selectedSpecies = document.getElementById('mushroom-type-select')?.value || 'oyster_white';
    const isBrown = selectedSpecies === 'oyster_brown';
    const optTempMin = isBrown ? 22.0 : 18.0;
    const optTempMax = isBrown ? 30.0 : 26.0;
    const optHumMin  = isBrown ? 85.0 : 80.0;
    const optHumMax  = 95.0;

    const validTemps = [];
    const validHums  = [];
    let rows = '';
    let dashRows = '';

    history.forEach((item) => {
        const tempNum = item.temperature !== null && item.temperature !== undefined && !isNaN(item.temperature)
            ? parseFloat(item.temperature) : null;
        const humNum = item.humidity !== null && item.humidity !== undefined && !isNaN(item.humidity)
            ? parseFloat(item.humidity) : null;

        if (tempNum !== null) validTemps.push(tempNum);
        if (humNum !== null) validHums.push(humNum);

        const temp = tempNum !== null ? tempNum.toFixed(1) : '--';
        const hum  = humNum !== null ? humNum.toFixed(1) : '--';
        const isAvg = item.interval && item.interval !== 'raw';
        const sampleBadge = isAvg && item.sample_count
            ? ` <span class="badge bg-secondary bg-opacity-25 text-muted ms-1" style="font-size:0.68rem;">${item.sample_count} readings avg</span>`
            : '';

        const dateOpts = { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        const ts = item.period_label
            ? escapeHtml(item.period_label)
            : (item.recorded_at ? new Date(item.recorded_at).toLocaleString([], dateOpts) : '');

        // Simplified climate status badge (warm white text for high contrast)
        let targetStatus = '<span class="badge bg-secondary" style="font-size:0.72rem; color:#faf5eb !important;">--</span>';
        if (tempNum !== null && humNum !== null) {
            if (tempNum >= optTempMin && tempNum <= optTempMax && humNum >= optHumMin && humNum <= optHumMax) {
                targetStatus = '<span class="badge bg-success bg-opacity-25 border border-success border-opacity-25" style="font-size:0.72rem; color:#faf5eb !important;"><i class="fas fa-check-circle me-1"></i>Optimal</span>';
            } else if (tempNum > optTempMax) {
                targetStatus = `<span class="badge bg-danger bg-opacity-25 border border-danger border-opacity-25" style="font-size:0.72rem; color:#faf5eb !important;">Warm (${temp}°C)</span>`;
            } else if (tempNum < optTempMin) {
                targetStatus = `<span class="badge bg-info bg-opacity-25 border border-info border-opacity-25" style="font-size:0.72rem; color:#faf5eb !important;">Cool (${temp}°C)</span>`;
            } else if (humNum < optHumMin) {
                targetStatus = `<span class="badge bg-warning bg-opacity-25 border border-warning border-opacity-25" style="font-size:0.72rem; color:#faf5eb !important;">Low Hum (${hum}%)</span>`;
            } else {
                targetStatus = '<span class="badge bg-success bg-opacity-15 border border-success border-opacity-25" style="font-size:0.72rem; color:#faf5eb !important;">Normal</span>';
            }
        }

        const rowHtml = `
            <tr>
                <td><span class="small font-monospace" style="color:#cbd5e1;">${ts}</span>${sampleBadge}</td>
                <td><strong class="fw-bold" style="color:#f87171; font-size: 0.92rem;">${temp} °C</strong></td>
                <td><strong class="fw-bold" style="color:#38bdf8; font-size: 0.92rem;">${hum} %</strong></td>
                <td>${targetStatus}</td>
            </tr>
        `;
        rows += rowHtml;
        dashRows += rowHtml;
    });

    if (tbody) tbody.innerHTML = rows;
    if (dashTbody) dashTbody.innerHTML = dashRows;

    if (validTemps.length > 0) {
        const avgT = validTemps.reduce((a, b) => a + b, 0) / validTemps.length;
        const minT = Math.min(...validTemps);
        const maxT = Math.max(...validTemps);
        if (avgTempEl) avgTempEl.textContent = `${avgT.toFixed(1)} °C`;
        if (minMaxTempEl) minMaxTempEl.textContent = `${minT.toFixed(1)} / ${maxT.toFixed(1)} °C`;
    }
    if (validHums.length > 0) {
        const avgH = validHums.reduce((a, b) => a + b, 0) / validHums.length;
        const minH = Math.min(...validHums);
        const maxH = Math.max(...validHums);
        if (avgHumEl) avgHumEl.textContent = `${avgH.toFixed(1)} %`;
        if (minMaxHumEl) minMaxHumEl.textContent = `${minH.toFixed(1)} / ${maxH.toFixed(1)} %`;
    }
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

    // Update Misting System (in manual mode, keep desired manual state instead of overwriting with stale sensor telemetry)
    if (mistingMode === 'auto') {
        mistingSystemStatus = Boolean(data.misting_system);
    }
    updateMistingDisplay(mistingSystemStatus);
    if (data.recorded_at) {
        document.getElementById('misting-update').textContent = formatUpdateTime(data.recorded_at);
    }

    // Update Exhaust / FAE Fan (in manual mode, keep desired manual state)
    if (fanMode === 'auto') {
        fanSystemStatus = Boolean(data.fan_system);
    }
    updateFanDisplay(fanSystemStatus);
    const fanUpdateEl = document.getElementById('fan-update');
    if (fanUpdateEl && data.recorded_at) {
        fanUpdateEl.textContent = formatUpdateTime(data.recorded_at);
    }
}

// Update misting system display
function updateMistingDisplay(status) {
    const statusElement = document.getElementById('misting-status');
    const badgeElement = document.getElementById('misting-badge');
    const buttonElement = document.getElementById('misting-btn');

    if (mistingMode === 'auto') {
        if (status) {
            if (statusElement) statusElement.textContent = 'ON';
            if (badgeElement) {
                badgeElement.textContent = 'Auto Spraying';
                badgeElement.className = 'status-badge status-on pulse';
            }
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-spray-can me-2"></i>Auto Spraying';
                buttonElement.className = 'control-btn auto-spraying pulse';
                buttonElement.title = 'Misting pump is currently active under Auto mode';
            }
        } else {
            if (statusElement) statusElement.textContent = 'AUTO';
            if (badgeElement) {
                badgeElement.textContent = 'Auto Ready';
                badgeElement.className = 'status-badge status-auto';
            }
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-robot me-2"></i>Auto Regulating';
                buttonElement.className = 'control-btn auto-standby';
                buttonElement.title = 'ESP32 monitors humidity. Click Manual to toggle pump directly.';
            }
        }
    } else {
        // Manual mode
        if (status) {
            if (statusElement) statusElement.textContent = 'ON';
            if (badgeElement) {
                badgeElement.textContent = 'Manual Active';
                badgeElement.className = 'status-badge status-on';
            }
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-power-off me-2"></i>Turn OFF';
                buttonElement.className = 'control-btn off';
                buttonElement.title = 'Click to deactivate misting pump';
            }
        } else {
            if (statusElement) statusElement.textContent = 'OFF';
            if (badgeElement) {
                badgeElement.textContent = 'Manual Inactive';
                badgeElement.className = 'status-badge status-off';
            }
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-power-off me-2"></i>Turn ON';
                buttonElement.className = 'control-btn';
                buttonElement.title = 'Click to activate misting pump';
            }
        }
    }

    updateMistingModeButtons();
    updateMistingModeText();
}

// Update misting mode buttons (Auto vs Manual active state)
function updateMistingModeButtons() {
    const autoBtn = document.getElementById('misting-mode-auto-btn');
    const manualBtn = document.getElementById('misting-mode-manual-btn');

    if (autoBtn && manualBtn) {
        if (mistingMode === 'auto') {
            autoBtn.classList.add('active');
            manualBtn.classList.remove('active');
            autoBtn.setAttribute('aria-pressed', 'true');
            manualBtn.setAttribute('aria-pressed', 'false');
        } else {
            manualBtn.classList.add('active');
            autoBtn.classList.remove('active');
            manualBtn.setAttribute('aria-pressed', 'true');
            autoBtn.setAttribute('aria-pressed', 'false');
        }
    }

    // Keep settings modal select in sync if open/exists
    const configSelect = document.getElementById('config-misting-mode');
    if (configSelect && configSelect.value !== mistingMode) {
        configSelect.value = mistingMode;
    }
}

// Switch misting mode between 'auto' and 'manual'
async function setMistingMode(mode) {
    if (mode !== 'auto' && mode !== 'manual') return;
    if (mode === mistingMode) return;

    const autoBtn = document.getElementById('misting-mode-auto-btn');
    const manualBtn = document.getElementById('misting-mode-manual-btn');
    const prevMode = mistingMode;

    // Optimistic UI state
    mistingMode = mode;
    updateMistingModeButtons();
    updateMistingDisplay(mode === 'auto' ? false : mistingSystemStatus);

    if (autoBtn) autoBtn.disabled = true;
    if (manualBtn) manualBtn.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}/misting/control`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                mode: mode,
                status: mode === 'auto' ? false : mistingSystemStatus
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            mistingMode = result.desired_mode || mode;
            if (result.desired_on !== undefined) {
                mistingSystemStatus = Boolean(result.desired_on);
            }
            updateMistingDisplay(mistingSystemStatus);
            updateConnectionStatus(true);
            showNotification(result.message || `Misting mode switched to ${mode.toUpperCase()}`, 'success');
        } else {
            throw new Error(result.message || 'Failed to switch misting mode');
        }
    } catch (error) {
        console.error('Error changing misting mode:', error);
        mistingMode = prevMode;
        updateMistingDisplay(mistingSystemStatus);
        updateConnectionStatus(false);
        showNotification('Failed to change misting mode. Please try again.', 'error');
    } finally {
        if (autoBtn) autoBtn.disabled = false;
        if (manualBtn) manualBtn.disabled = false;
    }
}

// Toggle misting system pump relay
async function toggleMisting() {
    const button = document.getElementById('misting-btn');
    // If user clicked toggle while in Auto mode, automatically switch to Manual mode & turn pump ON
    const targetMode = 'manual';
    const newStatus = (mistingMode === 'auto') ? true : !mistingSystemStatus;
    
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
            body: JSON.stringify({ status: newStatus, mode: targetMode })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            mistingMode = targetMode;
            mistingSystemStatus = newStatus;
            updateMistingDisplay(newStatus);
            updateConnectionStatus(true);
            
            showNotification(
                newStatus ? 'Misting pump activated (Manual mode)' : 'Misting pump deactivated',
                'success'
            );
        } else {
            throw new Error(result.message || 'Failed to control misting system');
        }
    } catch (error) {
        console.error('Error controlling misting system:', error);
        updateConnectionStatus(false);
        showNotification('Failed to control misting system. Please try again.', 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.classList.remove('loading');
        }
    }
}

// Expose misting actions to window scope for onclick handlers
window.setMistingMode = setMistingMode;
window.toggleMisting = toggleMisting;
window.updateMistingDisplay = updateMistingDisplay;
window.updateMistingModeButtons = updateMistingModeButtons;

// ── Exhaust / FAE Fan Controls ──
function updateFanModeText() {
    const el = document.getElementById('fan-auto-status');
    if (!el) return;

    if (fanMode === 'manual') {
        el.textContent = 'Manual mode — use Turn ON/OFF above';
        return;
    }
    el.textContent = 'Auto (FAE + cooling) — ESP32 regulates air';
}

function updateFanDisplay(status) {
    const statusElement = document.getElementById('fan-status');
    const badgeElement = document.getElementById('fan-badge');
    const buttonElement = document.getElementById('fan-btn');

    if (fanMode === 'auto') {
        if (status) {
            if (statusElement) statusElement.textContent = 'ON';
            if (badgeElement) {
                badgeElement.textContent = 'Auto Running';
                badgeElement.className = 'status-badge status-on pulse';
            }
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-fan fa-spin me-2"></i>Auto Running';
                buttonElement.className = 'control-btn auto-spraying pulse';
                buttonElement.title = 'Fan is currently running under Auto mode';
            }
        } else {
            if (statusElement) statusElement.textContent = 'AUTO';
            if (badgeElement) {
                badgeElement.textContent = 'Auto Ready';
                badgeElement.className = 'status-badge status-auto';
            }
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-robot me-2"></i>Auto Regulating';
                buttonElement.className = 'control-btn auto-standby';
                buttonElement.title = 'ESP32 manages FAE & cooling. Click Manual to toggle fan directly.';
            }
        }
    } else {
        if (status) {
            if (statusElement) statusElement.textContent = 'ON';
            if (badgeElement) {
                badgeElement.textContent = 'Manual Active';
                badgeElement.className = 'status-badge status-on';
            }
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-power-off me-2"></i>Turn OFF';
                buttonElement.className = 'control-btn off';
                buttonElement.title = 'Click to turn fan OFF';
            }
        } else {
            if (statusElement) statusElement.textContent = 'OFF';
            if (badgeElement) {
                badgeElement.textContent = 'Manual Inactive';
                badgeElement.className = 'status-badge status-off';
            }
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-power-off me-2"></i>Turn ON';
                buttonElement.className = 'control-btn';
                buttonElement.title = 'Click to turn fan ON';
            }
        }
    }

    updateFanModeButtons();
    updateFanModeText();
}

function updateFanModeButtons() {
    const autoBtn = document.getElementById('fan-mode-auto-btn');
    const manualBtn = document.getElementById('fan-mode-manual-btn');

    if (autoBtn && manualBtn) {
        if (fanMode === 'auto') {
            autoBtn.classList.add('active');
            manualBtn.classList.remove('active');
            autoBtn.setAttribute('aria-pressed', 'true');
            manualBtn.setAttribute('aria-pressed', 'false');
        } else {
            manualBtn.classList.add('active');
            autoBtn.classList.remove('active');
            manualBtn.setAttribute('aria-pressed', 'true');
            autoBtn.setAttribute('aria-pressed', 'false');
        }
    }
}

async function setFanMode(mode) {
    if (mode !== 'auto' && mode !== 'manual') return;
    if (mode === fanMode) return;

    const autoBtn = document.getElementById('fan-mode-auto-btn');
    const manualBtn = document.getElementById('fan-mode-manual-btn');
    const prevMode = fanMode;

    fanMode = mode;
    updateFanModeButtons();
    updateFanDisplay(mode === 'auto' ? false : fanSystemStatus);

    if (autoBtn) autoBtn.disabled = true;
    if (manualBtn) manualBtn.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}/fan/control`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                mode: mode,
                status: mode === 'auto' ? false : fanSystemStatus
            })
        });
        const result = await response.json();

        if (response.ok && result.success) {
            fanMode = result.desired_mode || mode;
            if (result.desired_on !== undefined) {
                fanSystemStatus = Boolean(result.desired_on);
            }
            updateFanDisplay(fanSystemStatus);
            showNotification(result.message || `Fan mode switched to ${mode.toUpperCase()}`, 'success');
        } else {
            throw new Error(result.message || 'Failed to switch fan mode');
        }
    } catch (error) {
        console.error('Error changing fan mode:', error);
        fanMode = prevMode;
        updateFanDisplay(fanSystemStatus);
        showNotification('Failed to change fan mode. Please try again.', 'error');
    } finally {
        if (autoBtn) autoBtn.disabled = false;
        if (manualBtn) manualBtn.disabled = false;
    }
}

async function toggleFan() {
    const button = document.getElementById('fan-btn');
    const targetMode = 'manual';
    const newStatus = (fanMode === 'auto') ? true : !fanSystemStatus;

    if (button) {
        button.disabled = true;
        button.classList.add('loading');
    }

    try {
        const response = await fetch(`${API_BASE_URL}/fan/control`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ status: newStatus, mode: targetMode })
        });
        const result = await response.json();

        if (response.ok && result.success) {
            fanMode = targetMode;
            fanSystemStatus = newStatus;
            updateFanDisplay(newStatus);
            showNotification(
                newStatus ? 'Fan turned ON (Manual mode)' : 'Fan turned OFF',
                'success'
            );
        } else {
            throw new Error(result.message || 'Failed to control fan');
        }
    } catch (error) {
        console.error('Error toggling fan:', error);
        showNotification('Failed to control fan. Please try again.', 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.classList.remove('loading');
        }
    }
}

window.setFanMode = setFanMode;
window.toggleFan = toggleFan;
window.updateFanDisplay = updateFanDisplay;
window.updateFanModeButtons = updateFanModeButtons;

// Update connection status indicator
function updateConnectionStatus(connected) {
    const liveStatus = document.getElementById('live-status');

    // Update both desktop and mobile wifi indicators
    [{ elId: 'connection-status', iconId: 'connection-icon' }, { elId: 'connection-status-mobile', iconId: null }].forEach(({ elId, iconId }) => {
        const el = document.getElementById(elId);
        if (!el) return;
        if (connected) {
            el.className = 'connection-status connected';
            el.title = 'Connected';
        } else {
            el.className = 'connection-status disconnected pulse';
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

let activeGrowPrediction = null;

function formatGrowPrediction(pred) {
    const daysLeftBadge = document.getElementById('ml-days-left-badge');
    const progressContainer = document.getElementById('ml-prediction-progress-container');

    if (!pred) {
        if (daysLeftBadge) daysLeftBadge.classList.add('d-none');
        if (progressContainer) progressContainer.classList.add('d-none');
        return 'Set “Start fruiting” when pins appear for an estimate.';
    }

    activeGrowPrediction = pred;

    const earliest = pred.earliest_harvest_at ? new Date(pred.earliest_harvest_at).toLocaleDateString([], { month: 'short', day: 'numeric' }) : '';
    const latest = pred.latest_harvest_at ? new Date(pred.latest_harvest_at).toLocaleDateString([], { month: 'short', day: 'numeric' }) : '';
    const predicted = pred.predicted_harvest_at ? new Date(pred.predicted_harvest_at).toLocaleDateString([], { month: 'short', day: 'numeric' }) : '';
    const de = pred.days_until_earliest;
    const dl = pred.days_until_latest;
    const rem = pred.days_remaining !== undefined ? pred.days_remaining : de;

    // Update days left pill badge
    if (daysLeftBadge) {
        if (rem !== null && rem !== undefined) {
            daysLeftBadge.textContent = rem > 0 ? `~${rem}d remaining` : 'Ready to harvest';
            daysLeftBadge.className = rem > 0
                ? 'badge mush-badge is-fruiting px-2 py-0'
                : 'badge mush-badge is-ready px-2 py-0';
            daysLeftBadge.classList.remove('d-none');
        } else {
            daysLeftBadge.classList.add('d-none');
        }
    }

    // Update ML progress container
    if (progressContainer) {
        progressContainer.classList.remove('d-none');
        const pct = Math.min(100, Math.max(0, pred.growth_progress_percent ?? 0));
        const pctEl = document.getElementById('ml-growth-pct');
        const barEl = document.getElementById('ml-growth-progress-bar');
        const velEl = document.getElementById('ml-growth-velocity');
        const confEl = document.getElementById('ml-confidence-badge');

        if (pctEl) pctEl.textContent = `${pct}%`;
        if (barEl) {
            barEl.style.width = `${pct}%`;
            barEl.className = pct >= 90
                ? 'mush-bar is-ready'
                : 'mush-bar';
        }
        const velText = (pred.daily_growth_velocity && pred.daily_growth_velocity > 1.05) ? 'Fast' : ((pred.daily_growth_velocity && pred.daily_growth_velocity < 0.95) ? 'Slow' : 'Normal');
        if (velEl) velEl.innerHTML = `<i class="fas fa-gauge-high me-1 mush-text"></i>Pace: ${velText} (${pred.daily_growth_velocity ?? 1.0}x)`;
        if (confEl) confEl.innerHTML = `<i class="fas fa-shield-halved me-1 mush-text"></i>Reliability: ${pred.confidence_score ?? 85}%`;
    }

    if (dl !== null && dl < 0) {
        return `The expected harvest window for your species has passed (${earliest}–${latest}). Check caps for readiness.`;
    }

    if (predicted) {
        return `Predicted: ${predicted} (~${rem} day${rem === 1 ? '' : 's'} remaining). Window: ${earliest}–${latest}. ${pred.note || ''}`;
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

        const dashTargetTempEl = document.getElementById('dash-chart-target-temp');
        const dashTargetHumEl = document.getElementById('dash-chart-target-hum');
        if (dashTargetTempEl && g.targets && g.targets.temp_min !== null && g.targets.temp_max !== null) {
            dashTargetTempEl.textContent = `${g.targets.temp_min} – ${g.targets.temp_max} °C`;
        }
        if (dashTargetHumEl && g.targets && g.targets.hum_min !== null && g.targets.hum_max !== null) {
            dashTargetHumEl.textContent = `${g.targets.hum_min} – ${g.targets.hum_max} %`;
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

        // Update live ML percentage readouts for growth and prediction system
        updateMlAnalyticsDisplay(g);
    } catch (e) {
        console.error('fetchGrowOverview', e);
    }
}

// Update comprehensive ML % display for growth progress and model prediction
function updateMlAnalyticsDisplay(growData) {
    if (!growData) return;

    const summary = growData.ml_summary || {};
    const pred = growData.prediction;
    const incPred = growData.incubation_prediction;
    const fruitStartPred = growData.fruiting_start_prediction;

    const growthPct = summary.growth_progress_percent !== undefined
        ? summary.growth_progress_percent
        : (pred?.growth_progress_percent ?? (incPred?.growth_progress_percent ?? 0));

    const confPct = summary.prediction_confidence_percent !== undefined
        ? summary.prediction_confidence_percent
        : (pred?.confidence_score ?? 86);

    const r2Pct = summary.model_fit_r2_percent !== undefined
        ? summary.model_fit_r2_percent
        : (pred?.model_details?.r_squared ? Math.round(pred.model_details.r_squared * 100) : 86);

    const velocity = summary.daily_growth_velocity !== undefined
        ? summary.daily_growth_velocity
        : (pred?.daily_growth_velocity ?? 1.0);

    const activePhase = summary.active_phase || (growData.fruiting_started_at ? 'fruiting' : (growData.incubation_started_at ? 'incubation' : 'none'));

    // 1. Header Bar Live Indicators
    const headerGrowth = document.getElementById('header-growth-pct');
    const headerConf = document.getElementById('header-ml-conf');
    if (headerGrowth) headerGrowth.textContent = `${growthPct}%`;
    if (headerConf) headerConf.textContent = `${confPct}%`;

    // 2. Mushroom Growth Progress %
    const mainGrowthPctEl = document.getElementById('ml-main-growth-pct');
    const mainGrowthBarEl = document.getElementById('ml-main-growth-bar');
    const mainPhaseBadge = document.getElementById('ml-main-growth-phase');
    const growthDaysDetail = document.getElementById('ml-growth-days-detail');
    const growthMaturityBadge = document.getElementById('ml-growth-maturity-badge');
    const growthTimerEl = document.getElementById('ml-growth-phase-timer');
    const growthVelocityTag = document.getElementById('ml-growth-velocity-tag');

    if (mainGrowthPctEl) mainGrowthPctEl.textContent = `${growthPct}%`;
    if (mainGrowthBarEl) {
        mainGrowthBarEl.style.width = `${growthPct}%`;
        mainGrowthBarEl.setAttribute('aria-valuenow', growthPct);
        if (growthPct >= 90) {
            mainGrowthBarEl.className = 'mush-bar is-ready';
        } else if (activePhase === 'incubation') {
            mainGrowthBarEl.className = 'mush-bar is-incubation';
        } else {
            mainGrowthBarEl.className = 'mush-bar';
        }
    }

    const paceWord = velocity > 1.05 ? 'Fast' : (velocity < 0.95 ? 'Slow' : 'Normal');
    if (growthVelocityTag) {
        growthVelocityTag.innerHTML = `<i class="fas fa-gauge-high me-1 mush-text"></i>Growth Pace: ${paceWord} (${velocity}x)`;
    }

    if (activePhase === 'fruiting') {
        if (mainPhaseBadge) {
            mainPhaseBadge.textContent = 'Fruiting Phase';
            mainPhaseBadge.className = 'badge mush-badge is-fruiting';
        }
        if (pred) {
            const rem = pred.days_remaining ?? pred.days_until_earliest ?? 0;
            const total = pred.predicted_total_days ?? 6;
            const elapsed = pred.days_elapsed ?? 0;
            if (growthDaysDetail) growthDaysDetail.textContent = `Day ${elapsed} of ~${total} days`;
            if (growthMaturityBadge) {
                if (growthPct >= 95) {
                    growthMaturityBadge.textContent = 'Ready to Harvest!';
                    growthMaturityBadge.className = 'badge mush-badge is-ready';
                } else if (growthPct >= 60) {
                    growthMaturityBadge.textContent = 'Caps Expanding';
                    growthMaturityBadge.className = 'badge mush-badge is-fruiting';
                } else if (growthPct >= 25) {
                    growthMaturityBadge.textContent = 'Pinheads Formed';
                    growthMaturityBadge.className = 'badge mush-badge is-fruiting';
                } else {
                    growthMaturityBadge.textContent = 'Starting Fruiting';
                    growthMaturityBadge.className = 'badge mush-badge is-idle';
                }
            }
            if (growthTimerEl) {
                growthTimerEl.innerHTML = rem > 0
                    ? `<i class="fas fa-hourglass-half me-1 mush-text"></i>About ~${rem} day${rem === 1 ? '' : 's'} until harvest`
                    : `<i class="fas fa-check-circle me-1 mush-text"></i>Ready for harvest! Pick before caps flatten.`;
            }
        }
    } else if (activePhase === 'incubation') {
        if (mainPhaseBadge) {
            mainPhaseBadge.textContent = 'Incubation (Spawn Run)';
            mainPhaseBadge.className = 'badge mush-badge is-incubation';
        }
        if (incPred) {
            const elapsed = incPred.days_elapsed ?? 0;
            const nominal = incPred.nominal_total_days ?? 17;
            const rem = incPred.days_remaining ?? incPred.days_until_earliest ?? 0;
            if (growthDaysDetail) growthDaysDetail.textContent = `Day ${elapsed} of ~${nominal} days`;
            if (growthMaturityBadge) {
                if (growthPct >= 95) {
                    growthMaturityBadge.textContent = 'Fully Colonized';
                    growthMaturityBadge.className = 'badge mush-badge is-good';
                } else if (growthPct >= 50) {
                    growthMaturityBadge.textContent = 'White Mycelium Spreading';
                    growthMaturityBadge.className = 'badge mush-badge is-incubation';
                } else {
                    growthMaturityBadge.textContent = 'Early Colonization';
                    growthMaturityBadge.className = 'badge mush-badge is-idle';
                }
            }
            if (growthTimerEl) {
                growthTimerEl.innerHTML = `<i class="fas fa-clock me-1 mush-text"></i>~${rem} days until bags are ready for fruiting`;
            }
        }
    } else {
        if (mainPhaseBadge) {
            mainPhaseBadge.textContent = 'Standby';
            mainPhaseBadge.className = 'badge mush-badge is-idle';
        }
        if (growthDaysDetail) growthDaysDetail.textContent = 'No active timer';
        if (growthMaturityBadge) {
            growthMaturityBadge.textContent = 'Standby';
            growthMaturityBadge.className = 'badge mush-badge is-idle';
        }
        if (growthTimerEl) {
            growthTimerEl.innerHTML = `<i class="fas fa-info-circle me-1 mush-text"></i>Click “Start incubation” or “Start fruiting” above`;
        }
    }

    // 3. Prediction Accuracy & Reliability
    const mainConfPctEl = document.getElementById('ml-main-conf-pct');
    const mainConfBarEl = document.getElementById('ml-main-conf-bar');
    const mainConfLabel = document.getElementById('ml-main-conf-label');
    const mainR2PctEl = document.getElementById('ml-main-r2-pct');
    const predHarvestDateEl = document.getElementById('ml-pred-harvest-date');

    if (mainConfPctEl) mainConfPctEl.textContent = `${confPct}%`;
    if (mainConfBarEl) {
        mainConfBarEl.style.width = `${confPct}%`;
        mainConfBarEl.setAttribute('aria-valuenow', confPct);
    }
    if (mainConfLabel) {
        if (confPct >= 90) {
            mainConfLabel.textContent = 'High Confidence';
            mainConfLabel.className = 'badge mush-badge is-good';
        } else if (confPct >= 75) {
            mainConfLabel.textContent = 'Good Reliability';
            mainConfLabel.className = 'badge mush-badge is-fair';
        } else {
            mainConfLabel.textContent = 'Gathering More Data';
            mainConfLabel.className = 'badge mush-badge is-learning';
        }
    }
    if (mainR2PctEl) mainR2PctEl.textContent = `${r2Pct}% Fit`;

    // 4. Environmental Efficiency Scores (Temperature & Humidity)
    const tempEff = summary.temp_efficiency_percent ?? (pred?.temp_efficiency_percent ?? (pred?.environmental_telemetry?.temp_efficiency_percent ?? null));
    const humEff  = summary.hum_efficiency_percent ?? (pred?.hum_efficiency_percent ?? (pred?.environmental_telemetry?.hum_efficiency_percent ?? null));
    const envEff  = summary.climate_efficiency_percent ?? (pred?.climate_efficiency_percent ?? (pred?.environmental_telemetry?.climate_efficiency_percent ?? null));

    const tempEffEl = document.getElementById('ml-temp-efficiency');
    const tempEffBarEl = document.getElementById('ml-temp-efficiency-bar');
    if (tempEffEl) {
        tempEffEl.textContent = tempEff != null ? `${Math.round(tempEff)}%` : '--%';
    }
    if (tempEffBarEl && tempEff != null) {
        tempEffBarEl.style.width = `${Math.min(100, Math.max(0, tempEff))}%`;
        tempEffBarEl.className = tempEff >= 85 ? 'mush-bar is-good' : (tempEff >= 70 ? 'mush-bar is-fair' : 'mush-bar is-learning');
    }

    const humEffEl = document.getElementById('ml-hum-efficiency');
    const humEffBarEl = document.getElementById('ml-hum-efficiency-bar');
    if (humEffEl) {
        humEffEl.textContent = humEff != null ? `${Math.round(humEff)}%` : '--%';
    }
    if (humEffBarEl && humEff != null) {
        humEffBarEl.style.width = `${Math.min(100, Math.max(0, humEff))}%`;
        humEffBarEl.className = humEff >= 85 ? 'mush-bar is-good' : (humEff >= 70 ? 'mush-bar is-fair' : 'mush-bar is-learning');
    }

    const envEffEl = document.getElementById('ml-env-efficiency');
    const envEffBarEl = document.getElementById('ml-env-efficiency-bar');
    if (envEffEl) {
        envEffEl.textContent = envEff != null ? `${Math.round(envEff)}%` : '--%';
    }
    if (envEffBarEl && envEff != null) {
        envEffBarEl.style.width = `${Math.min(100, Math.max(0, envEff))}%`;
        envEffBarEl.className = envEff >= 85 ? 'mush-bar is-good' : (envEff >= 70 ? 'mush-bar is-fair' : 'mush-bar is-learning');
    }

    if (predHarvestDateEl) {
        if (pred && pred.predicted_harvest_at) {
            const harvestDateStr = new Date(pred.predicted_harvest_at).toLocaleDateString([], {
                month: 'short',
                day: 'numeric'
            });
            predHarvestDateEl.innerHTML = `<i class="fas fa-calendar-check me-1 mush-text"></i>Expected Harvest: <strong class="mush-text-cream">${harvestDateStr}</strong>`;
        } else if (incPred && incPred.earliest_fruiting_switch_at) {
            const switchDateStr = new Date(incPred.earliest_fruiting_switch_at).toLocaleDateString([], {
                month: 'short',
                day: 'numeric'
            });
            predHarvestDateEl.innerHTML = `<i class="fas fa-arrow-right-arrow-left me-1 mush-text"></i>Ready for Fruiting: <strong class="mush-text-cream">${switchDateStr}</strong>`;
        } else if (fruitStartPred && fruitStartPred.earliest_pins_at) {
            const pinsDateStr = new Date(fruitStartPred.earliest_pins_at).toLocaleDateString([], {
                month: 'short',
                day: 'numeric'
            });
            predHarvestDateEl.innerHTML = `<i class="fas fa-bullseye me-1 mush-text"></i>Expected Pinheads: <strong class="mush-text-cream">${pinsDateStr}</strong>`;
        } else {
            predHarvestDateEl.innerHTML = `<i class="fas fa-seedling me-1 mush-text"></i>Growth Monitor Ready`;
        }
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

// Selectable mushroom types (Oyster Mushroom only: White / Brown).
// 'oyster_mushroom' is kept as a legacy alias so older records still display.
const MUSHROOM_LABELS = {
    oyster_white: 'White Oyster Mushroom',
    oyster_brown: 'Brown Oyster Mushroom',
    oyster_mushroom: 'White Oyster Mushroom'
};

// ── Active Incubator Batches Log (synced with /api/batches in MySQL) ──
let cachedBatches = [];
let batchesLoaded = false;

function mapApiBatch(b) {
    return {
        id: String(b.id),
        name: b.batch_code,
        type: b.mushroom_type || 'oyster_white',
        incubator: b.box_id || 'box_a',
        bags: Number(b.bag_count) || 0,
        start_date: b.inoculation_date || '',
        stage: b.stage || 'incubation',
    };
}

function getBatches() {
    if (batchesLoaded) return cachedBatches;
    try {
        const saved = localStorage.getItem('incubator_batches');
        return saved ? JSON.parse(saved) : [];
    } catch (e) {
        return [];
    }
}

function saveBatches(batches) {
    cachedBatches = batches;
    batchesLoaded = true;
    try { localStorage.setItem('incubator_batches', JSON.stringify(batches)); } catch (e) {}
}

async function fetchBatches() {
    try {
        const res = await fetch(`${API_BASE_URL}/batches`, {
            headers: { 'Accept': 'application/json' },
        });
        const result = await res.json();
        if (res.ok && result.success && Array.isArray(result.data)) {
            const mapped = result.data.map(mapApiBatch);
            saveBatches(mapped);
            return mapped;
        }
    } catch (e) {
        console.error('Error fetching batches:', e);
    }
    return getBatches();
}

// ── Incubator Room Monitor: selected box state ──
let selectedIncubatorId = 'box_a';

// Base metadata for each incubator box (temp/humidity now come from real API via latestBoxData)
let allRegisteredBoxes = {
    box_a: { box_id: 'box_a', name: 'Incubator Box A', mushroom_type: 'oyster_white', stage: 'fruiting' },
    box_b: { box_id: 'box_b', name: 'Incubator Box B', mushroom_type: 'oyster_white', stage: 'fruiting' },
    box_c: { box_id: 'box_c', name: 'Incubator Box C', mushroom_type: 'oyster_white', stage: 'fruiting' }
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
    let html = '';
    Object.values(allRegisteredBoxes).forEach(box => {
        html += `<option value="${box.box_id}">${escapeHtml(box.name)}</option>`;
    });

    ['batch-incubator', 'overview-batch-incubator', 'manual-box-overview', 'manual-box-history'].forEach(id => {
        const selectEl = document.getElementById(id);
        if (selectEl && html) {
            const prev = selectEl.value;
            selectEl.innerHTML = html;
            if (prev && Array.from(selectEl.options).some(o => o.value === prev)) {
                selectEl.value = prev;
            }
        }
    });
}

/**
 * Renders incubator box cards into #incubator-boxes-grid dynamically.
 */
function renderIncubatorBoxCards() {
    const grid = document.getElementById('incubator-boxes-grid');
    if (!grid) return;

    const batches = getBatches();
    const boxKeys = Object.keys(allRegisteredBoxes);

    const SPECIES_NAMES = MUSHROOM_LABELS;

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
            if (activeBatch.type === 'oyster_brown') cycleLength = 18;
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
        const speciesLabel = SPECIES_NAMES[box.mushroom_type] || 'Oyster Mushroom (White)';
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
        if (activeBatch.type === 'oyster_brown') cycleLength = 18;

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
    await Promise.all([fetchBoxSettings(), fetchBatches()]);
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
        oyster_white: { tMin: 15.0, tMax: 24.0, hMin: 80.0, hMax: 95.0 },
        oyster_brown: { tMin: 22.0, tMax: 30.0, hMin: 85.0, hMax: 95.0 }
    };
    const p = targets[mushroomKey] || targets.oyster_white;
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
    autoFillBoxTargets('oyster_white');
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
    if (typeSelect) typeSelect.value = (box.mushroom_type === 'oyster_brown') ? 'oyster_brown' : 'oyster_white';
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

    const typeLabels = MUSHROOM_LABELS;

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

async function deleteBatch(id) {
    if (!confirm('Remove this incubation batch?')) return;
    try {
        const res = await fetch(`${API_BASE_URL}/batches/${encodeURIComponent(id)}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });
        if (res.ok) {
            await fetchBatches();
            renderIncubatorTab();
            showNotification('Incubation batch removed successfully', 'success');
        } else {
            const err = await res.json().catch(() => ({}));
            showNotification(err.message || 'Could not remove batch', 'danger');
        }
    } catch (e) {
        console.error('Error deleting batch:', e);
        showNotification('Network error removing batch.', 'danger');
    }
}

async function submitNewBatchForm({ nameInputId, typeInputId, incubatorInputId, bagsInputId, formEl, collapseId }) {
    const name = document.getElementById(nameInputId)?.value.trim();
    const type = document.getElementById(typeInputId)?.value;
    const incubator = document.getElementById(incubatorInputId)?.value;
    const bags = parseInt(document.getElementById(bagsInputId)?.value, 10);
    const start_date = new Date().toISOString().split('T')[0];

    if (!name || !bags) return;
    if (bags < 1 || bags > 5) {
        showNotification('Each incubator box can only hold 1 to 5 mushroom bags.', 'warning');
        return;
    }

    try {
        const res = await fetch(`${API_BASE_URL}/batches`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                batch_code: name,
                box_id: incubator,
                mushroom_type: type,
                stage: 'incubation',
                bag_count: bags,
                inoculation_date: start_date,
            }),
        });
        const result = await res.json();
        if (!res.ok || !result.success) {
            showNotification(result.message || 'Could not save batch', 'danger');
            return;
        }

        await fetchBatches();
        selectedIncubatorId = incubator;
        renderIncubatorTab();
        if (formEl) formEl.reset();

        const collapseEl = document.getElementById(collapseId);
        if (collapseEl && typeof bootstrap !== 'undefined') {
            const bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
            if (bsCollapse) bsCollapse.hide();
        }

        showNotification('Successfully registered new incubation batch!', 'success');
    } catch (err) {
        console.error('Error creating batch:', err);
        showNotification('Network error saving batch.', 'danger');
    }
}

// Wire up incubator & overview batch registration listeners
document.addEventListener('DOMContentLoaded', () => {
    const newBatchForm = document.getElementById('newBatchForm');
    if (newBatchForm) {
        newBatchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            submitNewBatchForm({
                nameInputId: 'batch-name',
                typeInputId: 'batch-type',
                incubatorInputId: 'batch-incubator',
                bagsInputId: 'batch-bags',
                formEl: newBatchForm,
                collapseId: 'addBatchCollapse',
            });
        });
    }

    const overviewBatchForm = document.getElementById('overviewBatchForm');
    if (overviewBatchForm) {
        overviewBatchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            submitNewBatchForm({
                nameInputId: 'overview-batch-name',
                typeInputId: 'overview-batch-type',
                incubatorInputId: 'overview-batch-incubator',
                bagsInputId: 'overview-batch-bags',
                formEl: overviewBatchForm,
                collapseId: 'overviewBatchCollapse',
            });
        });
    }

    const overviewManualSensorForm = document.getElementById('overviewManualSensorForm');
    if (overviewManualSensorForm) {
        overviewManualSensorForm.addEventListener('submit', (e) => {
            e.preventDefault();
            submitManualSensorForm({
                tempInputId: 'manual-temp-overview',
                humInputId: 'manual-hum-overview',
                boxInputId: 'manual-box-overview',
                formEl: overviewManualSensorForm,
                collapseId: 'overviewManualSensorCollapse',
            });
        });
    }

    const historyManualSensorForm = document.getElementById('historyManualSensorForm');
    if (historyManualSensorForm) {
        historyManualSensorForm.addEventListener('submit', (e) => {
            e.preventDefault();
            submitManualSensorForm({
                tempInputId: 'manual-temp-history',
                humInputId: 'manual-hum-history',
                boxInputId: 'manual-box-history',
                formEl: historyManualSensorForm,
                collapseId: 'historyManualSensorCollapse',
            });
        });
    }
});

async function submitManualSensorForm({ tempInputId, humInputId, boxInputId, formEl, collapseId }) {
    const tempVal = parseFloat(document.getElementById(tempInputId)?.value);
    const humVal  = parseFloat(document.getElementById(humInputId)?.value);
    const boxId   = document.getElementById(boxInputId)?.value || 'box_a';

    if (isNaN(tempVal) || isNaN(humVal)) {
        showNotification('Please enter valid temperature and humidity values.', 'warning');
        return;
    }

    try {
        const res = await fetch(`${API_BASE_URL}/sensor-data`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                box_id: boxId,
                temperature: tempVal,
                humidity: humVal,
                misting_system: Boolean(mistingSystemStatus),
                misting_source: 'manual',
                misting_reason: 'manual_entry',
                fan_system: Boolean(fanSystemStatus),
            }),
        });
        const result = await res.json();
        if (!res.ok || !result.success) {
            showNotification(result.message || 'Could not save manual sensor reading.', 'danger');
            return;
        }

        await Promise.all([
            fetchSensorData(),
            fetchBoxesData(),
            fetchHistory(),
            fetchGrowOverview(),
        ]);

        if (formEl) formEl.reset();
        const collapseEl = document.getElementById(collapseId);
        if (collapseEl && typeof bootstrap !== 'undefined') {
            const bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
            if (bsCollapse) bsCollapse.hide();
        }

        showNotification(`Manual reading saved (${tempVal.toFixed(1)} °C, ${humVal.toFixed(1)}% RH)!`, 'success');
    } catch (err) {
        console.error('Error saving manual sensor reading:', err);
        showNotification('Network error saving manual reading.', 'danger');
    }
}

// ── Target parameter custom setting tweaks (Recommendation 1 & 9) ──
const defaultSpeciesCatalog = {
    oyster_white: { temp_min: 15.0, temp_max: 24.0, hum_min: 80.0, hum_max: 95.0 },
    oyster_brown: { temp_min: 22.0, temp_max: 30.0, hum_min: 85.0, hum_max: 95.0 }
};

function getActiveMushroomType() {
    const select = document.getElementById('mushroom-type-select');
    return select && defaultSpeciesCatalog[select.value] ? select.value : 'oyster_white';
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

    const speciesNames = MUSHROOM_LABELS;

    tbody.innerHTML = logs.map(l => {
        const dateStr = l.harvested_date_formatted || (l.harvested_at ? l.harvested_at.split('T')[0] : 'Today');
        const beBadge = l.biological_efficiency != null 
            ? `<span class="badge bg-warning text-dark">${l.biological_efficiency}%</span>` 
            : `<span class="text-muted small">N/A</span>`;

        let climateCell = '';
        if (l.temperature != null && l.humidity != null) {
            let statusBadge = '';
            if (l.climate_status === 'optimal') {
                statusBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" title="Recorded batch climate is within optimal ML training band"><i class="fas fa-check-circle me-1"></i>Optimal</span>`;
            } else if (l.climate_status === 'suboptimal') {
                statusBadge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" title="${escapeHtml(l.climate_note || 'Sub-optimal')}"><i class="fas fa-triangle-exclamation me-1"></i>Sub-optimal</span>`;
            }

            const tempTargetStr = l.best_temp_label || '20–24°C';
            const humTargetStr = l.best_hum_label || '80–95%';

            climateCell = `
                <div class="d-flex flex-column gap-1">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="fw-bold" style="color: #f1f5f9;"><span style="color: #f87171;">${Number(l.temperature).toFixed(1)}°C</span> &bull; <span style="color: #38bdf8;">${Number(l.humidity).toFixed(1)}% RH</span></span>
                        ${statusBadge}
                    </div>
                    <div style="font-size: 0.72rem; color: #94a3b8;">
                        <i class="fas fa-bullseye me-1 text-primary"></i>Target: <strong class="text-warning">${escapeHtml(tempTargetStr)}</strong>, <strong class="text-info">${escapeHtml(humTargetStr)}</strong>
                    </div>
                </div>
            `;
        } else {
            const tempTargetStr = l.best_temp_label || '20–24°C';
            climateCell = `
                <div class="d-flex flex-column">
                    <span class="small" style="color: #94a3b8;"><i class="fas fa-circle-question me-1"></i>Unrecorded</span>
                    <span style="font-size: 0.72rem; color: #94a3b8;"><i class="fas fa-bullseye me-1 text-primary"></i>Target: ${escapeHtml(tempTargetStr)}</span>
                </div>
            `;
        }

        return `
            <tr>
                <td><small class="font-monospace" style="color:#cbd5e1;">${escapeHtml(dateStr)}</small></td>
                <td><strong style="color:#ffffff;">${escapeHtml(l.batch_name)}</strong></td>
                <td><span style="color:#e2e8f0;">${escapeHtml(speciesNames[l.mushroom_type] || l.mushroom_type)}</span></td>
                <td><span class="badge bg-info text-dark">Flush #${l.flush_number}</span></td>
                <td><strong class="text-success">${l.weight_grams} g</strong> <small style="color:#94a3b8;">(${l.weight_kg} kg)</small></td>
                <td>${beBadge}</td>
                <td>${climateCell}</td>
                <td>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete harvest record" onclick="deleteHarvestLog(${l.id})">
                        <i class="fas fa-trash-can"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function updateHarvestModalBestClimate() {
    const typeSelect = document.getElementById('harvest-mushroom-type');
    const badge = document.getElementById('harvest-modal-species-badge');
    const tempText = document.getElementById('harvest-modal-best-temp');
    const humText = document.getElementById('harvest-modal-best-hum');
    if (!typeSelect) return;

    const type = typeSelect.value || 'oyster_white';
    if (type === 'oyster_brown') {
        if (badge) {
            badge.textContent = 'Brown Oyster Species Profile';
            badge.className = 'badge bg-secondary';
        }
        if (tempText) tempText.textContent = '24.0°C – 28.0°C (Fruiting Range 22–30°C)';
        if (humText) humText.textContent = '85.0% – 95.0% RH (Base 85%)';
    } else {
        if (badge) {
            badge.textContent = 'White Oyster Species Profile';
            badge.className = 'badge bg-success';
        }
        if (tempText) tempText.textContent = '20.0°C – 24.0°C (Fruiting Range 15–24°C)';
        if (humText) humText.textContent = '80.0% – 95.0% RH (Base 80%)';
    }
}
window.updateHarvestModalBestClimate = updateHarvestModalBestClimate;

function useCurrentTelemetryForHarvest(metric) {
    if (metric === 'temperature') {
        const input = document.getElementById('harvest-temperature');
        let tempVal = null;
        if (typeof latestSensorData !== 'undefined' && latestSensorData && latestSensorData.temperature != null) {
            tempVal = parseFloat(latestSensorData.temperature).toFixed(1);
        } else {
            const headerTemp = document.getElementById('header-temp');
            if (headerTemp) {
                const match = headerTemp.textContent.match(/[\d.]+/);
                if (match) tempVal = parseFloat(match[0]).toFixed(1);
            }
        }
        if (tempVal !== null && input) {
            input.value = tempVal;
            showNotification(`Set harvest batch temperature to live reading: ${tempVal}°C`, 'info');
        } else {
            showNotification('Live temperature is not currently available.', 'warning');
        }
    } else if (metric === 'humidity') {
        const input = document.getElementById('harvest-humidity');
        let humVal = null;
        if (typeof latestSensorData !== 'undefined' && latestSensorData && latestSensorData.humidity != null) {
            humVal = parseFloat(latestSensorData.humidity).toFixed(1);
        } else {
            const headerHum = document.getElementById('header-humidity');
            if (headerHum) {
                const match = headerHum.textContent.match(/[\d.]+/);
                if (match) humVal = parseFloat(match[0]).toFixed(1);
            }
        }
        if (humVal !== null && input) {
            input.value = humVal;
            showNotification(`Set harvest batch humidity to live reading: ${humVal}%`, 'info');
        } else {
            showNotification('Live humidity is not currently available.', 'warning');
        }
    }
}
window.useCurrentTelemetryForHarvest = useCurrentTelemetryForHarvest;

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
    const tempInput = document.getElementById('harvest-temperature');
    const humInput = document.getElementById('harvest-humidity');

    if (nameInput) nameInput.value = batch.name;
    if (typeInput) {
        typeInput.value = batch.type;
        updateHarvestModalBestClimate();
    }
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

    // Pre-fill live telemetry if inputs are blank
    if (typeof latestSensorData !== 'undefined' && latestSensorData) {
        if (tempInput && !tempInput.value && latestSensorData.temperature != null) {
            tempInput.value = parseFloat(latestSensorData.temperature).toFixed(1);
        }
        if (humInput && !humInput.value && latestSensorData.humidity != null) {
            humInput.value = parseFloat(latestSensorData.humidity).toFixed(1);
        }
    }

    const modalEl = document.getElementById('logHarvestModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

// Log Harvest Form submit listener & modal hooks
document.addEventListener('DOMContentLoaded', () => {
    const harvestModal = document.getElementById('logHarvestModal');
    if (harvestModal) {
        harvestModal.addEventListener('show.bs.modal', () => {
            updateHarvestModalBestClimate();
            const tempInput = document.getElementById('harvest-temperature');
            const humInput = document.getElementById('harvest-humidity');
            if (typeof latestSensorData !== 'undefined' && latestSensorData) {
                if (tempInput && !tempInput.value && latestSensorData.temperature != null) {
                    tempInput.value = parseFloat(latestSensorData.temperature).toFixed(1);
                }
                if (humInput && !humInput.value && latestSensorData.humidity != null) {
                    humInput.value = parseFloat(latestSensorData.humidity).toFixed(1);
                }
            }
        });
    }

    const logHarvestForm = document.getElementById('logHarvestForm');
    if (logHarvestForm) {
        logHarvestForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('harvest-submit-btn');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…'; }

            const tempInput = document.getElementById('harvest-temperature');
            const humInput = document.getElementById('harvest-humidity');

            const payload = {
                batch_name: document.getElementById('harvest-batch-name').value,
                mushroom_type: document.getElementById('harvest-mushroom-type').value,
                flush_number: parseInt(document.getElementById('harvest-flush-number').value, 10) || 1,
                weight_grams: parseFloat(document.getElementById('harvest-weight-grams').value) || 0,
                substrate_weight_grams: parseFloat(document.getElementById('harvest-substrate-weight').value) || null,
                temperature: tempInput && tempInput.value !== '' ? parseFloat(tempInput.value) : null,
                humidity: humInput && humInput.value !== '' ? parseFloat(humInput.value) : null,
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
                    showNotification('Harvest yield entry logged successfully with batch climate data!', 'success');
                    logHarvestForm.reset();
                    
                    const modalEl = document.getElementById('logHarvestModal');
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        const bsModal = bootstrap.Modal.getInstance(modalEl);
                        if (bsModal) bsModal.hide();
                    }
                    
                    fetchHarvestLogs();
                    if (typeof fetchGrowOverview === 'function') fetchGrowOverview();
                    if (typeof loadCorrelationAnalytics === 'function') loadCorrelationAnalytics();
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
                    <td class="fw-semibold text-light">${item.batch_name}</td>
                    <td><span class="small font-monospace" style="color:#cbd5e1;">${item.harvested_at}</span></td>
                    <td><span class="badge bg-secondary">Flush ${item.flush_number}</span></td>
                    <td class="fw-bold text-success">${item.weight_grams} g</td>
                    <td><strong class="fw-bold" style="color: #f87171;"><i class="fas fa-thermometer-half me-1 text-danger opacity-75"></i>${item.avg_temp_7d} °C</strong></td>
                    <td><strong class="fw-bold" style="color: #38bdf8;"><i class="fas fa-tint me-1 text-info opacity-75"></i>${item.avg_hum_7d} %</strong></td>
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
let cameraHealthTimer = null;

function initCameraStream() {
    const streamEl = document.getElementById('camera-stream');
    if (!streamEl) return;

    // Check camera status first so an offline ESP32-CAM doesn't block single-worker PHP
    checkCameraStatus().then((isOnline) => {
        if (isOnline && streamEl.dataset.src && !streamEl.src) {
            streamEl.style.display = 'block';
            streamEl.src = `${streamEl.dataset.src}?_t=${Date.now()}`;
        }
    });

    // Auto health-check interval for stalled MJPEG stream (register once)
    if (!cameraHealthTimer) {
        cameraHealthTimer = setInterval(function() {
            if (cameraMode === 'mjpeg' && streamEl.style.display !== 'none' && streamEl.src) {
                if (streamEl.complete && streamEl.naturalWidth === 0) {
                    retryCameraStream();
                }
            }
        }, 15000);
    }
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
            return true;
        } else {
            const streamEl = document.getElementById('camera-stream');
            const offlineEl = document.getElementById('camera-offline');
            if (streamEl) streamEl.style.display = 'none';
            if (offlineEl) offlineEl.style.display = 'block';
            updateCameraBadge('offline', 'OFFLINE');
            return false;
        }
    } catch (e) {
        return false;
    }
}

let cameraFlashAutoOffTimer = null;

async function toggleCameraFlash() {
    const btn = document.getElementById('btn-toggle-flash');
    cameraFlashActive = !cameraFlashActive;
    // Cap PWM duty at 128 (50%) instead of 255 to prevent the onboard SMD LED from overheating
    const intensity = cameraFlashActive ? 128 : 0;

    if (cameraFlashAutoOffTimer) {
        clearTimeout(cameraFlashAutoOffTimer);
        cameraFlashAutoOffTimer = null;
    }

    if (btn) {
        btn.classList.toggle('btn-warning', cameraFlashActive);
        btn.classList.toggle('btn-outline-warning', !cameraFlashActive);
    }

    try {
        showNotification(cameraFlashActive ? 'Turning Camera Flash ON (auto-off in 30s)…' : 'Turning Camera Flash OFF…', 'info');
        const res = await fetch(`${API_BASE_URL}/camera/control?var=led_intensity&val=${intensity}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (data.success) {
            showNotification(cameraFlashActive ? 'Camera Flash ON (50% safe brightness, 30s auto-off)' : 'Camera Flash turned OFF', 'success');
            if (cameraFlashActive) {
                cameraFlashAutoOffTimer = setTimeout(() => {
                    if (cameraFlashActive) {
                        toggleCameraFlash();
                    }
                }, 30000);
            }
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

// ── Machine Learning Harvest Prediction Model Metrics ──
async function loadMlModelMetrics(forceRefresh = false) {
    try {
        const url = forceRefresh
            ? `${API_BASE_URL}/prediction/model-metrics?refresh=1`
            : `${API_BASE_URL}/prediction/model-metrics`;
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.success) return;

        const tableBody = document.getElementById('modal-species-params-table');
        if (tableBody && data.models) {
            let html = '';
            const labels = {
                oyster_white: 'White Oyster Mushroom',
                oyster_brown: 'Brown Oyster Mushroom'
            };
            for (const [key, m] of Object.entries(data.models)) {
                html += `<tr>
                    <td class="text-start fw-semibold">${labels[key] || key}</td>
                    <td>${m.intercept_days} d</td>
                    <td class="${m.coef_temp_deviation < 0 ? 'text-success' : 'text-danger'}">${m.coef_temp_deviation > 0 ? '+' : ''}${m.coef_temp_deviation} d/°C</td>
                    <td class="text-warning">+${m.coef_hum_deficit} d/%</td>
                    <td class="text-info">+${m.coef_flush_number} d</td>
                    <td class="fw-bold text-success">${m.r_squared} (${Math.round(m.r_squared * 100)}%)</td>
                    <td><span class="badge ${m.is_calibrated ? 'bg-success' : 'bg-info'}">${m.is_calibrated ? 'Empirical Fit' : 'Calibrated Priors'}</span></td>
                </tr>`;
            }
            tableBody.innerHTML = html;
        }

        // Populate active telemetry in modal if prediction is present
        if (activeGrowPrediction && activeGrowPrediction.environmental_telemetry) {
            const env = activeGrowPrediction.environmental_telemetry;
            const mod = activeGrowPrediction.model_details || {};
            const tEl = document.getElementById('modal-telemetry-temp');
            const taEl = document.getElementById('modal-telemetry-temp-adj');
            const hEl = document.getElementById('modal-telemetry-hum');
            const haEl = document.getElementById('modal-telemetry-hum-adj');
            const teEl = document.getElementById('modal-temp-efficiency');
            const heEl = document.getElementById('modal-hum-efficiency');
            const eeEl = document.getElementById('modal-env-efficiency');
            const r2El = document.getElementById('modal-model-r2');
            const maeEl = document.getElementById('modal-model-mae');
            const samEl = document.getElementById('modal-model-samples');
            const velEl = document.getElementById('modal-model-velocity');

            if (tEl) tEl.textContent = `${env.avg_temperature} °C (ideal: ${env.optimal_temperature} °C)`;
            if (taEl) {
                const adj = env.temp_adjustment_days;
                taEl.textContent = `${adj > 0 ? '+' : ''}${adj} days (${adj < 0 ? 'faster' : 'lag'})`;
                taEl.className = adj < 0 ? 'fw-semibold text-success' : 'fw-semibold text-warning';
            }
            if (hEl) hEl.textContent = `${env.avg_humidity} % RH`;
            if (haEl) {
                const hadj = env.hum_adjustment_days;
                hadj.textContent = `${hadj > 0 ? '+' : ''}${hadj} days lag`;
                haEl.className = hadj > 0 ? 'fw-semibold text-warning' : 'fw-semibold text-success';
            }
            if (teEl) teEl.textContent = env.temp_efficiency_percent != null ? `${env.temp_efficiency_percent}%` : '100%';
            if (heEl) heEl.textContent = env.hum_efficiency_percent != null ? `${env.hum_efficiency_percent}%` : '100%';
            if (eeEl) eeEl.textContent = env.climate_efficiency_percent != null ? `${env.climate_efficiency_percent}%` : '100%';

            const r2Val = mod.r_squared ?? 0.86;
            if (r2El) r2El.textContent = `${r2Val} (${Math.round(r2Val * 100)}% Fit)`;
            if (maeEl) maeEl.textContent = `${mod.mae_days ?? '0.45'} days`;
            if (samEl) samEl.textContent = `${mod.training_samples ?? 0} batches`;
            if (velEl) velEl.textContent = `${activeGrowPrediction.daily_growth_velocity ?? 1.0}x nominal`;
        }
    } catch (err) {
        console.warn('Failed to load ML model metrics:', err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const mlModalEl = document.getElementById('mlModelModal');
    if (mlModalEl) {
        mlModalEl.addEventListener('show.bs.modal', () => loadMlModelMetrics(false));
    }
    const refreshMlBtn = document.getElementById('btn-refresh-ml-metrics');
    if (refreshMlBtn) {
        refreshMlBtn.addEventListener('click', async () => {
            refreshMlBtn.disabled = true;
            refreshMlBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Recalculating…';
            await loadMlModelMetrics(true);
            await fetchGrowOverview();
            refreshMlBtn.disabled = false;
            refreshMlBtn.innerHTML = '<i class="fas fa-arrows-rotate me-1"></i> Recalculate Model';
            showNotification('ML prediction model recalculated with latest telemetry!', 'success');
        });
    }
});





// ══════════════════════════════════════════════════════════════
// ── Light / Dark Theme Toggle ──
// ══════════════════════════════════════════════════════════════
const THEME_STORAGE_KEY = 'kabutech-theme';

function getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
}

// Chart grid lines must contrast with the active background
function getChartGridColor(alpha) {
    return getCurrentTheme() === 'light'
        ? `rgba(15, 23, 42, ${alpha + 0.02})`
        : `rgba(255, 255, 255, ${alpha})`;
}

function refreshChartsForTheme() {
    if (typeof Chart === 'undefined' || !Chart.instances) return;
    Object.values(Chart.instances).forEach(chart => {
        const scales = chart.options && chart.options.scales;
        if (!scales) return;
        Object.values(scales).forEach(scale => {
            if (scale && scale.grid && scale.grid.drawOnChartArea !== false && scale.grid.color !== undefined) {
                scale.grid.color = getChartGridColor(0.07);
            }
        });
        chart.update('none');
    });
}

function updateThemeToggleButton(theme) {
    const btn = document.getElementById('themeToggleBtn');
    const icon = document.getElementById('themeToggleIcon');
    if (!btn || !icon) return;
    // Show the icon of the mode you will switch TO
    const label = theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode';
    icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    btn.title = label;
    btn.setAttribute('aria-label', label);
}

function applyTheme(theme, animate) {
    const root = document.documentElement;
    if (animate) {
        root.classList.add('theme-transition');
        setTimeout(() => root.classList.remove('theme-transition'), 350);
    }
    root.setAttribute('data-theme', theme);
    try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch (e) { /* storage unavailable */ }
    updateThemeToggleButton(theme);
    refreshChartsForTheme();
}

function toggleTheme() {
    applyTheme(getCurrentTheme() === 'light' ? 'dark' : 'light', true);
}

window.toggleTheme = toggleTheme;

document.addEventListener('DOMContentLoaded', () => {
    updateThemeToggleButton(getCurrentTheme());
    const btn = document.getElementById('themeToggleBtn');
    if (btn) btn.addEventListener('click', toggleTheme);
});

// ══════════════════════════════════════════════════════════════
// ── Service Worker & Offline Mode Handling ──
// ══════════════════════════════════════════════════════════════
function setOfflineBannerVisible(isOffline) {
    const banner = document.getElementById('offline-banner');
    if (!banner) return;
    if (isOffline) {
        banner.classList.remove('d-none');
    } else {
        banner.classList.add('d-none');
    }
}

function handleNetworkChange() {
    const online = navigator.onLine;
    setOfflineBannerVisible(!online);
    updateConnectionStatus(online);

    if (online) {
        if (typeof showNotification === 'function') {
            showNotification('Wi-Fi connection restored — syncing live farm data.', 'success');
        }
        fetchSensorData();
        fetchBoxesData();
        fetchHistory();
        fetchMistingStatus();
        fetchGrowOverview();
    } else {
        if (typeof showNotification === 'function') {
            showNotification('Wi-Fi disconnected — switched to offline cached mode.', 'warning');
        }
        // Trigger a fetch so the Service Worker serves the cached snapshot immediately
        fetchSensorData();
        fetchHistory();
    }
}

window.addEventListener('online', handleNetworkChange);
window.addEventListener('offline', handleNetworkChange);

document.addEventListener('DOMContentLoaded', () => {
    if (!navigator.onLine) {
        setOfflineBannerVisible(true);
        updateConnectionStatus(false);
    }

    // Clear user-specific SW caches on logout
    const logoutForms = document.querySelectorAll('form[action$="/logout"]');
    logoutForms.forEach((form) => {
        form.addEventListener('submit', () => {
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_USER_CACHE' });
            }
        });
    });

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        const base = (window.KABUTECH_BASE_URL || '').replace(/\/$/, '');
        const swUrl = `${base}/sw.js`;
        navigator.serviceWorker.register(swUrl).then((reg) => {
            const sendInit = (sw) => {
                if (!sw) return;
                sw.postMessage({ type: 'CACHE_CURRENT_PAGE', url: window.location.href });
            };
            if (navigator.serviceWorker.controller) {
                sendInit(navigator.serviceWorker.controller);
            }
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                sendInit(navigator.serviceWorker.controller);
            });
            if (reg.active) sendInit(reg.active);
        }).catch((err) => {
            console.warn('Service Worker registration failed:', err);
        });
    }
});

// ══════════════════════════════════════════════════════════════
// ── Web Push Notifications ──
// ══════════════════════════════════════════════════════════════
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function updatePushButtons(subscribed, unsupportedReason = '') {
    const toggleBtn = document.getElementById('btn-toggle-push');
    const testBtn = document.getElementById('btn-test-push');
    if (!toggleBtn) return;

    if (unsupportedReason) {
        toggleBtn.textContent = 'Unavailable';
        toggleBtn.disabled = true;
        toggleBtn.title = unsupportedReason;
        if (testBtn) testBtn.classList.add('d-none');
        return;
    }

    toggleBtn.disabled = false;
    if (subscribed) {
        toggleBtn.textContent = 'Enabled';
        toggleBtn.className = 'btn btn-sm btn-success py-0 px-2';
        toggleBtn.title = 'Click to disable push notifications on this device';
        if (testBtn) testBtn.classList.remove('d-none');
    } else {
        toggleBtn.textContent = 'Enable';
        toggleBtn.className = 'btn btn-sm btn-outline-success py-0 px-2';
        toggleBtn.title = 'Enable push notifications on this device';
        if (testBtn) testBtn.classList.add('d-none');
    }
}

async function initWebPushControls() {
    const toggleBtn = document.getElementById('btn-toggle-push');
    const testBtn = document.getElementById('btn-test-push');
    if (!toggleBtn) return;

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        updatePushButtons(false, 'Web Push is not supported in this browser (requires HTTPS or Add to Home Screen on iOS).');
        return;
    }

    try {
        const reg = await navigator.serviceWorker.ready;
        const existing = await reg.pushManager.getSubscription();
        updatePushButtons(Boolean(existing));
    } catch (e) {
        updatePushButtons(false);
    }

    toggleBtn.addEventListener('click', async () => {
        toggleBtn.disabled = true;
        try {
            const reg = await navigator.serviceWorker.ready;
            const existing = await reg.pushManager.getSubscription();

            if (existing) {
                await fetch(`${API_BASE_URL}/push/unsubscribe`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ endpoint: existing.endpoint }),
                });
                await existing.unsubscribe();
                updatePushButtons(false);
                showNotification('Push notifications disabled for this device.', 'info');
                return;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                updatePushButtons(false);
                showNotification('Notification permission was denied by the browser.', 'warning');
                return;
            }

            const keyRes = await fetch(`${API_BASE_URL}/push/vapid-key`, {
                headers: { 'Accept': 'application/json' },
            });
            const { publicKey } = await keyRes.json();
            if (!publicKey) {
                throw new Error('VAPID public key is not configured on the server.');
            }

            const subscription = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey),
            });

            const subJson = subscription.toJSON();
            const saveRes = await fetch(`${API_BASE_URL}/push/subscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    endpoint: subJson.endpoint,
                    keys: subJson.keys,
                    contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0],
                }),
            });

            if (!saveRes.ok) {
                throw new Error('Failed to save push subscription on server.');
            }

            updatePushButtons(true);
            showNotification('Push notifications enabled for this device!', 'success');
        } catch (err) {
            console.error('Web Push toggle error:', err);
            updatePushButtons(false);
            showNotification(err.message || 'Could not enable push notifications.', 'danger');
        } finally {
            toggleBtn.disabled = false;
        }
    });

    if (testBtn) {
        testBtn.addEventListener('click', async () => {
            testBtn.disabled = true;
            try {
                const res = await fetch(`${API_BASE_URL}/push/test`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (data.success) {
                    showNotification(`Test notification sent (${data.sent} device${data.sent === 1 ? '' : 's'}).`, 'success');
                } else {
                    showNotification(data.reason || 'No push notifications could be delivered.', 'warning');
                }
            } catch (e) {
                showNotification('Failed to send test push notification.', 'danger');
            } finally {
                testBtn.disabled = false;
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', initWebPushControls);

// ══════════════════════════════════════════════════════════════
// ── PWA Install Prompt ──
// ══════════════════════════════════════════════════════════════
let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    const installBtn = document.getElementById('btn-install-pwa');
    if (installBtn) {
        installBtn.classList.remove('d-none');
    }
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    const installBtn = document.getElementById('btn-install-pwa');
    if (installBtn) {
        installBtn.classList.add('d-none');
    }
    if (typeof showNotification === 'function') {
        showNotification('KabuTech installed as an app!', 'success');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const installBtn = document.getElementById('btn-install-pwa');
    if (!installBtn) return;

    installBtn.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        const { outcome } = await deferredInstallPrompt.userChoice;
        if (outcome === 'accepted') {
            installBtn.classList.add('d-none');
        }
        deferredInstallPrompt = null;
    });
});
