/**
 * MiksTraffic App Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    const charts = {};

    function formatBytes(bits, decimals = 2) {
        if (!bits || isNaN(bits) || bits < 1) return '0 bps';
        const k = 1000;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        const i = Math.floor(Math.log(bits) / Math.log(k));
        return parseFloat((bits / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function initChart(canvasId, interfaceName) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const gradientRx = ctx.createLinearGradient(0, 0, 0, 300);
        gradientRx.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
        gradientRx.addColorStop(1, 'rgba(16, 185, 129, 0)');

        const gradientTx = ctx.createLinearGradient(0, 0, 0, 300);
        gradientTx.addColorStop(0, 'rgba(245, 158, 11, 0.4)');
        gradientTx.addColorStop(1, 'rgba(245, 158, 11, 0)');

        charts[interfaceName] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Download (RX)',
                        borderColor: '#10b981',
                        backgroundColor: gradientRx,
                        fill: true,
                        data: [],
                        tension: 0.4, spanGaps: true,
                        borderWidth: 2,
                        pointRadius: 0,
                    },
                    {
                        label: 'Upload (TX)',
                        borderColor: '#f59e0b',
                        backgroundColor: gradientTx,
                        fill: true,
                        data: [],
                        tension: 0.4, spanGaps: true,
                        borderWidth: 2,
                        pointRadius: 0,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                scales: {
                    x: {
                        display: false,
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                        },
                        ticks: {
                            color: '#94a3b8',
                            callback: function(value) {
                                return formatBytes(value, 1);
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += formatBytes(context.parsed.y);
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    let modalChart = null;
    let historyCharts = {
        hourly: null,
        daily: null,
        monthly: null
    };
    let activeModal = { routerId: null, iface: null, label: null, livePeriod: 'live', lastModalRefresh: 0 };

    window.openModal = function(routerId, iface, label) {
        activeModal = { routerId, iface, label, livePeriod: 'live', lastModalRefresh: Date.now() };
        if (document.getElementById('live-period-select')) {
            document.getElementById('live-period-select').value = 'live';
        }
        document.getElementById('modal-title').textContent = label;
        document.getElementById('modal-subtitle').textContent = `Router ID: ${routerId} | Interface: ${iface}`;
        document.getElementById('traffic-modal').style.display = 'block';
        
        initModalChart();
        initHistoryCharts();
        loadModalHistory('live');
        loadModalHistory('hourly');
        loadModalHistory('daily');
        loadModalHistory('monthly');
    };

    window.closeModal = function() {
        document.getElementById('traffic-modal').style.display = 'none';
        activeModal = { routerId: null, iface: null, label: null, livePeriod: 'live', lastModalRefresh: 0 };
        if (modalChart) {
            modalChart.destroy();
            modalChart = null;
        }
        for (let key in historyCharts) {
            if (historyCharts[key]) {
                historyCharts[key].destroy();
                historyCharts[key] = null;
            }
        }
    };

    window.changeLivePeriod = function(period) {
        activeModal.livePeriod = period;
        activeModal.lastModalRefresh = Date.now();
        loadModalHistory(period);
        
        const indicator = document.getElementById('live-time-indicator');
        // All live periods are considered real-time by the user
        indicator.style.color = 'var(--rx-color)';
        indicator.innerHTML = '<i class="fas fa-circle status-dot"></i> Real-time';
    };

    let cpuChart = null;
    let ramChart = null;
    let activeResRouterId = null;
    let resPeriod = 'live';
    let lastResRefresh = 0;

    window.openResourceModal = function(routerId, routerName) {
        activeResRouterId = routerId;
        resPeriod = 'live';
        lastResRefresh = Date.now();
        const uptime = document.getElementById(`uptime-${routerId}`).textContent;
        document.getElementById('res-modal-title').textContent = routerName;
        document.getElementById('res-modal-subtitle').textContent = `System Uptime: ${uptime}`;
        document.getElementById('resource-modal').style.display = 'block';
        
        if (document.getElementById('res-period-select')) {
            document.getElementById('res-period-select').value = 'live';
        }
        
        initResourceCharts();
        loadResourceHistory('live');
    };

    window.closeResourceModal = function() {
        document.getElementById('resource-modal').style.display = 'none';
        activeResRouterId = null;
        resPeriod = 'live';
        if (cpuChart) { cpuChart.destroy(); cpuChart = null; }
        if (ramChart) { ramChart.destroy(); ramChart = null; }
    };

    window.changeResourcePeriod = function(period) {
        resPeriod = period;
        lastResRefresh = Date.now();
        loadResourceHistory(period);
        
        const indicator = document.getElementById('res-time-indicator');
        if (period.includes('live')) {
            indicator.style.color = 'var(--rx-color)';
            indicator.innerHTML = '<i class="fas fa-circle status-dot"></i> Real-time';
        } else {
            indicator.style.color = 'var(--text-muted)';
            indicator.innerHTML = '<i class="fas fa-history"></i> Historical';
        }
    };

    function initResourceCharts() {
        const cpuCtx = document.getElementById('cpu-history-chart').getContext('2d');
        const ramCtx = document.getElementById('ram-history-chart').getContext('2d');

        if (cpuChart) cpuChart.destroy();
        if (ramChart) ramChart.destroy();

        cpuChart = new Chart(cpuCtx, {
            type: 'line',
            data: { labels: [], datasets: [{ 
                label: 'CPU Load (%)', 
                borderColor: '#6366f1', 
                backgroundColor: 'rgba(99, 102, 241, 0.1)', 
                fill: true, tension: 0.4, spanGaps: true, data: [] 
            }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#94a3b8', font: {size: 10} }, grid: { display: false } },
                    y: { beginAtZero: true, max: 100, ticks: { color: '#94a3b8', font: {size: 10} }, grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });

        ramChart = new Chart(ramCtx, {
            type: 'line',
            data: { labels: [], datasets: [{ 
                label: 'Free RAM (MB)', 
                borderColor: '#10b981', 
                backgroundColor: 'rgba(16, 185, 129, 0.1)', 
                fill: true, tension: 0.4, spanGaps: true, data: [] 
            }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#94a3b8', font: {size: 10} }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { color: '#94a3b8', font: {size: 10}, callback: val => val + ' MB' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });
    }

    async function loadResourceHistory(period = 'live') {
        if (!activeResRouterId) return;
        try {
            const res = await (await fetch(`api/get_resources.php?router_id=${activeResRouterId}&period=${period}`)).json();
            if (res.status === 'success') {
                const labels = res.data.map(d => {
                    const dt = new Date(d.timestamp);
                    if (period === 'live') {
                        return dt.toLocaleTimeString('en-GB', {minute: '2-digit', second: '2-digit'});
                    } else if (period === 'daily') {
                        return dt.toLocaleDateString('en-GB', {day: '2-digit', month: '2-digit'});
                    } else if (period === 'monthly') {
                        return dt.toLocaleDateString('en-GB', {month: 'short', year: '2-digit'});
                    } else {
                        return dt.toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
                    }
                });
                const cpuData = res.data.map(d => d.cpu_load);
                const ramData = res.data.map(d => (d.free_memory / 1024 / 1024).toFixed(1));

                cpuChart.data.labels = labels;
                cpuChart.data.datasets[0].data = cpuData;
                cpuChart.update();

                ramChart.data.labels = labels;
                ramChart.data.datasets[0].data = ramData;
                ramChart.update();
            }
        } catch (e) { console.error('Resource fetch error', e); }
    }

    function initModalChart() {
        const ctx = document.getElementById('modal-chart').getContext('2d');
        if (modalChart) modalChart.destroy();
        
        modalChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Download (RX)',
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4, spanGaps: true,
                        data: []
                    },
                    {
                        label: 'Upload (TX)',
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.4, spanGaps: true,
                        data: []
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#f8fafc' } } },
                animation: {
                    duration: 0
                },
                scales: {
                    x: { 
                        ticks: { 
                            color: '#94a3b8', 
                            autoSkip: true,
                            maxTicksLimit: 12, // Show approx every 5th label if 60 points
                            font: { size: 10 }
                        }, 
                        grid: { color: 'rgba(255,255,255,0.05)' } 
                    },
                    y: { 
                        beginAtZero: true,
                        suggestedMax: 100000000,
                        ticks: { color: '#94a3b8', callback: val => formatBytes(val) }, 
                        grid: { color: 'rgba(255,255,255,0.05)' } 
                    }
                }
            }
        });
    }

    function initHistoryCharts() {
        const configs = [
            { id: 'chart-hourly', key: 'hourly' },
            { id: 'chart-daily', key: 'daily' },
            { id: 'chart-monthly', key: 'monthly' }
        ];

        configs.forEach(conf => {
            const ctx = document.getElementById(conf.id).getContext('2d');
            if (historyCharts[conf.key]) historyCharts[conf.key].destroy();
            
            historyCharts[conf.key] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        { label: 'RX', borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, tension: 0.4, spanGaps: true, data: [] },
                        { label: 'TX', borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.1)', fill: true, tension: 0.4, spanGaps: true, data: [] }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#94a3b8', maxRotation: 45, minRotation: 45, font: {size: 10} }, grid: { display: false } },
                        y: { 
                            beginAtZero: true,
                            suggestedMax: 100000000,
                            ticks: { color: '#94a3b8', font: {size: 10}, callback: val => formatBytes(val) }, 
                            grid: { color: 'rgba(255,255,255,0.05)' } 
                        }
                    }
                }
            });
        });
    }

    async function loadModalHistory(period) {
        if (!activeModal.routerId) return;
        try {
            const response = await fetch(`api/get_traffic.php?router_id=${activeModal.routerId}&interface=${activeModal.iface}&period=${period}`);
            const result = await response.json();

            if (result.status === 'success' && result.data.length > 0) {
                const logs = result.data;
                const labels = logs.map(log => {
                    const d = new Date(log.timestamp);
                    if (period.includes('live')) {
                        if (period === 'live') {
                            return d.toLocaleTimeString('en-GB', {minute: '2-digit', second: '2-digit'});
                        } else {
                            return d.toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
                        }
                    } else if (period === 'monthly') {
                        return log.time_label; // YYYY-MM
                    } else if (period === 'daily') {
                        // log.time_label is YYYY-MM-DD
                        if (log.time_label && log.time_label.includes('-')) {
                            const parts = log.time_label.split('-');
                            if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
                        }
                        return log.time_label;
                    } else if (period === 'hourly') {
                        const d = new Date(log.timestamp);
                        return d.toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit'}); // HH:mm
                    }
                    return log.time_label;
                });
                
                const rxData = logs.map(log => log.rx_rate);
                const txData = logs.map(log => log.tx_rate);

                let targetChart = period.includes('live') ? modalChart : historyCharts[period];
                
                if (targetChart) {
                    targetChart.data.labels = labels;
                    targetChart.data.datasets[0].data = rxData;
                    targetChart.data.datasets[1].data = txData;
                    targetChart.update();
                }
            } else if (period !== 'live') {
                // Handle empty historical data gracefully
                let targetChart = historyCharts[period];
                if (targetChart) {
                    targetChart.data.labels = ['Belum ada data'];
                    targetChart.data.datasets[0].data = [0];
                    targetChart.data.datasets[1].data = [0];
                    targetChart.update();
                }
            }
        } catch (error) {
            console.error('Error loading modal history for ' + period + ':', error);
        }
    }

    const lastTrafficTime = {};

    async function updateLiveTraffic(routerId) {
        try {
            const response = await fetch(`api/get_live_traffic.php?router_id=${routerId}`);
            const result = await response.json();

            if (result.status === 'success') {
                if (result.resources) {
                    const cpuEl = document.getElementById(`cpu-${routerId}`);
                    if (cpuEl) cpuEl.textContent = result.resources.cpu;
                    
                    const ramEl = document.getElementById(`ram-${routerId}`);
                    if (ramEl) {
                        const freeRamMB = (result.resources.ram_free / 1024 / 1024).toFixed(0);
                        const totalRamMB = (result.resources.ram_total / 1024 / 1024).toFixed(0);
                        ramEl.textContent = `${freeRamMB}MB / ${totalRamMB}MB`;
                    }
                    
                    const uptimeEl = document.getElementById(`uptime-${routerId}`);
                    if (uptimeEl) uptimeEl.textContent = result.resources.uptime;

                    // Update Resource Modal Charts if open and in LIVE mode
                    if (activeResRouterId == routerId && resPeriod === 'live') {
                        const timeLabel = new Date().toLocaleTimeString('en-GB', {minute: '2-digit', second: '2-digit'});
                        
                        if (cpuChart) {
                            cpuChart.data.labels.push(timeLabel);
                            cpuChart.data.datasets[0].data.push(result.resources.cpu);
                            if (cpuChart.data.labels.length > 60) {
                                cpuChart.data.labels.shift();
                                cpuChart.data.datasets[0].data.shift();
                            }
                            cpuChart.update();
                        }
                        
                        if (ramChart) {
                            ramChart.data.labels.push(timeLabel);
                            ramChart.data.datasets[0].data.push((result.resources.ram_free / 1024 / 1024).toFixed(1));
                            if (ramChart.data.labels.length > 60) {
                                ramChart.data.labels.shift();
                                ramChart.data.datasets[0].data.shift();
                            }
                            ramChart.update();
                        }
                    }

                    // Update Resource Modal Charts if open and periodic refresh needed
                    if (activeResRouterId == routerId) {
                        let resInterval = 1000;
                        if (resPeriod === 'live_5m') resInterval = 5000;
                        else if (resPeriod === 'live_15m') resInterval = 15000;
                        else if (resPeriod === 'live_30m') resInterval = 30000;
                        else if (resPeriod === 'live_1h') resInterval = 60000;
                        else if (resPeriod === 'hourly') resInterval = 300000; // 5m for hourly
                        else if (resPeriod === 'daily') resInterval = 3600000; // 1h for daily
                        else if (resPeriod === 'monthly') resInterval = 86400000; // 24h for monthly

                        if (Date.now() - lastResRefresh >= resInterval) {
                            lastResRefresh = Date.now();
                            loadResourceHistory(resPeriod);
                        }
                    }
                }

                for (const iface in result.traffic) {
                    const data = result.traffic[iface];
                    const cardKey = `${routerId}-${iface}`;
                    
                    // Zero Traffic Detection
                    const cardElem = document.querySelector(`.mini-card-v2[data-router-id="${routerId}"][data-interface="${iface}"]`);
                    if (data.rx_rate > 0 || data.tx_rate > 0) {
                        lastTrafficTime[cardKey] = Date.now();
                        if (cardElem) cardElem.classList.remove('no-traffic-alert');
                    } else {
                        if (!lastTrafficTime[cardKey]) lastTrafficTime[cardKey] = Date.now();
                        if (Date.now() - lastTrafficTime[cardKey] > 5000) {
                            if (cardElem) cardElem.classList.add('no-traffic-alert');
                        }
                    }

                    // Update Minimalist Card Stats
                    const rxElem = document.getElementById(`rx-val-${routerId}-${iface}`);
                    if (rxElem) rxElem.textContent = formatBytes(data.rx_rate);
                    
                    const txElem = document.getElementById(`tx-val-${routerId}-${iface}`);
                    if (txElem) txElem.textContent = formatBytes(data.tx_rate);

                    const rateElem = document.getElementById(`rate-${routerId}-${iface}`);
                    if (rateElem) rateElem.textContent = data.link_rate || 'unknown';

                    const sfpElem = document.getElementById(`sfp-${routerId}-${iface}`);
                    if (sfpElem) {
                        if (data.sfp_power !== null) {
                            sfpElem.classList.remove('non-sfp');
                            sfpElem.innerHTML = `<i class="fas fa-bolt"></i> ${data.sfp_power.toFixed(2)} dBm`;
                        } else {
                            sfpElem.classList.add('non-sfp');
                            sfpElem.textContent = 'NON-SFP';
                        }
                    }

                    // Update Modal Chart if open
                    if (activeModal.routerId == routerId && activeModal.iface == iface) {
                        let interval = 1000; // 1s default
                        if (activeModal.livePeriod === 'live_5m') interval = 5000;
                        else if (activeModal.livePeriod === 'live_15m') interval = 15000;
                        else if (activeModal.livePeriod === 'live_30m') interval = 30000;
                        else if (activeModal.livePeriod === 'live_1h') interval = 60000;

                        if (Date.now() - activeModal.lastModalRefresh >= interval) {
                            activeModal.lastModalRefresh = Date.now();
                            loadModalHistory(activeModal.livePeriod);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching live data:', error);
        }
    }

    // Tab Switching
    window.switchTab = (tabName) => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        document.querySelector(`.tab-btn[onclick*="${tabName}"]`).classList.add('active');
        document.getElementById(`tab-${tabName}`).classList.add('active');
        
        if (tabName === 'olt') {
            document.querySelectorAll('.olt-card').forEach(card => {
                const oltId = card.dataset.oltId;
                updateOltStats(oltId);
            });
        }
    };

    // OLT Monitoring Functions
    window.updateOltStats = async (id, force = false) => {
        const statsEl = document.getElementById(`olt-stats-${id}`);
        const statusEl = document.getElementById(`olt-status-${id}`);
        
        try {
            const res = await (await fetch(`api/olts.php?action=get_stats&id=${id}&force_refresh=${force}`)).json();
            if (res.status === 'success') {
                const isOnline = res.is_online !== undefined ? res.is_online : true;
                if (isOnline) {
                    statusEl.textContent = 'Connected';
                    statusEl.style.background = 'rgba(16, 185, 129, 0.1)';
                    statusEl.style.color = '#10b981';
                } else {
                    statusEl.textContent = 'Offline (Cached)';
                    statusEl.style.background = 'rgba(239, 68, 68, 0.1)';
                    statusEl.style.color = '#ef4444';
                }
                
                renderOltStatsOnCard(id, res.data);
            } else {
                statusEl.textContent = 'Offline';
                statusEl.style.background = 'rgba(239, 68, 68, 0.1)';
                statusEl.style.color = '#ef4444';
                statsEl.innerHTML = `<p style="text-align: center; color: #ef4444; padding: 1rem;"><i class="fas fa-exclamation-triangle"></i> ${res.message || 'Connection Failed'}</p>`;
            }
        } catch (e) { console.error('OLT Poll Error', e); }
    }

    function renderOltStatsOnCard(id, d) {
        const statsEl = document.getElementById(`olt-stats-${id}`);
        if (!statsEl) return;
        statsEl.innerHTML = `
            <div class="olt-stats-wrapper">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.4rem;">
                    <div class="stat-box-v2" style="text-align: center; border: 1px solid rgba(59, 130, 246, 0.2); padding: 0.5rem;">
                        <div class="stat-label-v2 olt-mini-label" style="justify-content: center;">TOTAL</div>
                        <div class="stat-value-v2 olt-mini-value" style="color: #3b82f6;">${d.total_onus}</div>
                    </div>
                    <div class="stat-box-v2" style="text-align: center; border: 1px solid rgba(16, 185, 129, 0.2); padding: 0.5rem;">
                        <div class="stat-label-v2 olt-mini-label" style="justify-content: center;">ONLINE</div>
                        <div class="stat-value-v2 olt-mini-value" style="color: #10b981;">${d.online_onus}</div>
                    </div>
                    <div class="stat-box-v2" style="text-align: center; border: 1px solid rgba(245, 158, 11, 0.2); padding: 0.5rem;">
                        <div class="stat-label-v2 olt-mini-label" style="justify-content: center;">LOW</div>
                        <div class="stat-value-v2 olt-mini-value" style="color: #f59e0b;">${d.low_onus || 0}</div>
                    </div>
                    <div class="stat-box-v2" style="text-align: center; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.5rem;">
                        <div class="stat-label-v2 olt-mini-label" style="justify-content: center;">OFFLINE</div>
                        <div class="stat-value-v2 olt-mini-value" style="color: #ef4444;">${d.offline_onus}</div>
                    </div>
                </div>
            </div>
        `;
    }

    window.refreshAllOlts = () => {
        const btn = document.getElementById('btn-refresh-olts');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Refreshing...';
        
        const promises = [];
        document.querySelectorAll('.olt-card').forEach(card => {
            promises.push(updateOltStats(card.dataset.oltId, true));
        });
        
        Promise.all(promises).finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh All';
        });
    };

    let allOnusCache = null;
    window.globalOltSearch = async () => {
        const term = document.getElementById('globalOltSearch').value.toLowerCase();
        const resultsEl = document.getElementById('search-results-olts');
        
        if (!term || term.length < 3) {
            resultsEl.style.display = 'none';
            return;
        }

        if (!allOnusCache) {
            resultsEl.innerHTML = '<div class="glass-card" style="padding: 1rem; text-align: center;"><i class="fas fa-circle-notch fa-spin"></i> Indexing all OLTs for first search...</div>';
            resultsEl.style.display = 'block';
            try {
                const res = await (await fetch(`api/olts.php?action=get_all_onus`)).json();
                if (res.status === 'success') allOnusCache = res.data;
            } catch (e) { console.error('Global Search Error', e); }
        }

        if (!allOnusCache) return;

        const results = allOnusCache.filter(onu => {
            const customerName = findCustomerName(onu.mac);
            return (onu.mac.toLowerCase().includes(term) || (customerName && customerName.toLowerCase().includes(term)));
        });

        if (results.length === 0) {
            resultsEl.innerHTML = '<div class="glass-card" style="padding: 1rem; text-align: center;">No matching customers found.</div>';
        } else {
            let html = '<div class="glass-card" style="padding: 1rem;"><h3 style="margin-bottom: 1rem; font-size: 0.875rem; color: var(--text-muted);">Search Results:</h3>';
            results.forEach(onu => {
                const customerName = findCustomerName(onu.mac);
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: rgba(255,255,255,0.03); border-radius: 0.5rem; margin-bottom: 0.5rem; border: 1px solid var(--glass-border);">
                        <div>
                            <div style="font-weight: 700;">${customerName || '-'}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">${onu.mac}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 600; color: var(--primary-color); cursor: pointer;" onclick="showOltDetails(${onu.olt_id}, '${onu.olt_name}', '${onu.olt_host}', '${onu.olt_desc}')">
                                <i class="fas fa-server"></i> ${onu.olt_name} <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
                            </div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">ONU ID: ${onu.onu_id}</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            resultsEl.innerHTML = html;
        }
        resultsEl.style.display = 'block';
    };

    let currentOnus = [];
    let pppoeUsers = [];
    let activePonTab = 'all';

    window.showOltDetails = async (id, name, host, desc) => {
        const modal = document.getElementById('oltDetailModal');
        const body = document.getElementById('onuListBody');
        const title = document.getElementById('oltDetailTitle');
        const sub = document.getElementById('oltDetailSub');
        const btnWeb = document.getElementById('btn-open-olt');
        
        title.textContent = name;
        sub.innerHTML = `<i class="fas fa-link"></i> ${host} &nbsp; | &nbsp; <i class="fas fa-info-circle"></i> ${desc || 'No description'}`;
        btnWeb.href = host.startsWith('http') ? host : `http://${host}`;
        
        modal.style.display = 'block';
        body.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 3rem;"><i class="fas fa-circle-notch fa-spin fa-2x"></i><br><br>Fetching ONU list...</td></tr>';
        
        // Fetch PPPoE users
        try {
            const pppRes = await (await fetch(`api/mikrotik_pppoe.php`)).json();
            if (pppRes.status === 'success') {
                pppoeUsers = pppRes.data;
            }
        } catch (e) { console.error('Failed to fetch PPPoE users'); }

        try {
            const res = await (await fetch(`api/olts.php?action=get_all_details&id=${id}`)).json();
            if (res.status === 'success') {
                currentOnus = res.onus;
                renderOnus();
                // Update the dashboard card stats immediately!
                if (res.stats) {
                    renderOltStatsOnCard(id, res.stats);
                    const statusEl = document.getElementById(`olt-status-${id}`);
                    if (statusEl) {
                        statusEl.textContent = 'Online';
                        statusEl.style.background = 'rgba(16, 185, 129, 0.1)';
                        statusEl.style.color = '#10b981';
                    }
                }
            } else {
                body.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #ef4444;">${res.message}</td></tr>`;
            }
        } catch (e) { body.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #ef4444;">Network Error</td></tr>`; }
    };

    window.switchPonTab = (pon) => {
        activePonTab = pon;
        document.querySelectorAll('#pon-tabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('onclick').includes(`'${pon}'`));
        });
        renderOnus();
    };

    window.closeOltDetails = () => {
        document.getElementById('oltDetailModal').style.display = 'none';
        activePonTab = 'all';
        document.querySelectorAll('#pon-tabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('onclick').includes("'all'"));
        });
    };

    function findCustomerName(mac) {
        if (!mac) return null;
        const cleanMac = mac.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
        // Look for MAC in pppoeUsers (caller-id)
        const user = pppoeUsers.find(u => {
            const uMac = u.mac.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
            return uMac === cleanMac || uMac.includes(cleanMac) || cleanMac.includes(uMac);
        });
        return user ? user.name : null;
    }

    function renderOnus() {
        const term = document.getElementById('onuSearch').value.toLowerCase();
        const body = document.getElementById('onuListBody');
        body.innerHTML = '';
        
        let online = 0, filteredCount = 0;
        
        currentOnus.forEach(onu => {
            // PON Filter (Match 0/1/1 or 0/1/2 within the ID or PON field)
            const onuFullId = (onu.pon + '/' + onu.onu_id); // e.g. 0/1/1
            if (activePonTab !== 'all' && !onuFullId.includes(activePonTab) && !onu.pon.includes(activePonTab)) return;

            const customerName = findCustomerName(onu.mac);
            const match = (onu.mac + ' ' + onu.onu_id + ' ' + onu.status + ' ' + (customerName || '')).toLowerCase();
            
            if (term && !match.includes(term)) return;
            
            filteredCount++;
            const statusLower = (onu.status || '').toLowerCase();
            const isOnline = ['up', 'online', 'active', 'o5'].includes(statusLower);
            if (isOnline) online++;
            
            // RX Power Color Logic
            const rxVal = parseFloat(onu.rx || 0);
            let rxColor = '#94a3b8'; // Default
            if (rxVal !== 0 && !isNaN(rxVal)) {
                if (rxVal >= -22.00) {
                    rxColor = '#10b981'; // Green
                } else if (rxVal >= -24.00) {
                    rxColor = '#f59e0b'; // Yellow (Warning)
                } else {
                    rxColor = '#ef4444'; // Red (Critical)
                }
            }
            
            const statusColor = isOnline ? '#10b981' : '#ef4444';
            
            body.innerHTML += `
                <tr>
                    <td class="onu-info-col">
                        <div style="font-weight: 700; color: #fff; font-size: 0.95rem;">${customerName || '-'}</div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem;">ID: ${onu.onu_id} | <span style="font-family: monospace;">${onu.mac}</span></div>
                    </td>
                    <td class="onu-desktop-only" style="color: var(--text-muted); font-size: 0.75rem;">${onu.onu_id}</td>
                    <td class="onu-desktop-only" style="font-weight: 700; color: #fff;">${customerName || '-'}</td>
                    <td class="onu-desktop-only" style="font-family: monospace; font-size: 0.875rem; color: var(--text-muted);">${onu.mac}</td>
                    <td><span style="font-weight: 800; color: ${statusColor}; text-transform: uppercase; font-size: 0.75rem;">${onu.status}</span></td>
                    <td style="color: ${rxColor}; font-weight: 700; font-size: 0.875rem;">${onu.rx || '--'} <span style="font-size: 0.65rem;">dBm</span></td>
                    <td class="onu-desktop-only">${onu.distance || '0'} m</td>
                </tr>
            `;
        });
        
        document.getElementById('count-total').textContent = filteredCount;
        document.getElementById('count-online').textContent = online;
    }

    window.filterOnus = () => renderOnus();

    window.rebootOnu = async (oltId, onuId, name) => {
        if (!confirm(`Reboot ONU ${onuId}?`)) return;
        const fd = new FormData();
        fd.append('onu_id', onuId);
        fd.append('onu_name', name);
        try {
            const res = await (await fetch(`api/olts.php?action=reboot_onu&id=${oltId}`, { method: 'POST', body: fd })).json();
            alert(res.message);
        } catch (e) { alert('Error sending reboot command'); }
    };

    // Initialize
    const routers = new Set();
    document.querySelectorAll('.mini-card-v2').forEach(card => {
        const routerId = card.dataset.routerId;
        if (routerId) routers.add(routerId);
    });

    // Start live polling for each router
    routers.forEach(routerId => {
        setInterval(() => {
            // Only poll if the tab is active
            if (document.getElementById('tab-mikrotik').classList.contains('active')) {
                updateLiveTraffic(routerId);
            }
        }, 1000);
    });

    // Start OLT Polling
    setInterval(() => {
        if (document.getElementById('tab-olt').classList.contains('active')) {
            document.querySelectorAll('.olt-card').forEach(card => {
                updateOltStats(card.dataset.oltId);
            });
        }
    }, 10000); // OLT is slower, 10s is enough
});
