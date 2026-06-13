<?php
/**
 * MiksTraffic Dashboard
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
$db = new MiksDB();
$routers = $db->getRouters();

require_once __DIR__ . '/layout/header.php';
?>

<div class="dashboard-header" style="margin-top: 2rem; display: flex; justify-content: center;">
    <div class="tabs-container">
        <button class="tab-btn active" onclick="switchTab('mikrotik')"><i class="fas fa-network-wired"></i> MikroTik</button>
        <button class="tab-btn" onclick="switchTab('olt')"><i class="fas fa-server"></i> OLT Monitor</button>
        <div style="width: 1px; background: var(--glass-border); margin: 0.5rem 0.75rem;"></div>
        <a href="login.php" class="tab-btn" style="text-decoration: none;"><i class="fas fa-lock"></i> Admin Panel</a>
    </div>
</div>

<div id="tab-mikrotik" class="tab-content active">

<?php if (empty($routers)): ?>
    <div class="glass-card" style="margin-top: 2rem; text-align: center; padding: 4rem;">
        <i class="fas fa-router" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem; display: block;"></i>
        <h2 style="font-family: 'Outfit', sans-serif;">No Routers Configured</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Please log in to the admin panel to add your first MikroTik router.</p>
        <a href="login.php" class="brand" style="display: inline-flex; -webkit-text-fill-color: initial; background: var(--primary-color); color: white; padding: 0.75rem 2rem; border-radius: 0.5rem; text-decoration: none;">Go to Admin</a>
    </div>
<?php endif; ?>

<?php foreach ($routers as $router): ?>
    <div class="router-header" style="display: flex; justify-content: space-between; align-items: center; margin-top: 3rem; border-left: 4px solid var(--primary-color); padding-left: 1rem;">
        <h2 style="margin: 0; font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($router['name']); ?></h2>
        <div class="router-info-bar" style="cursor: pointer; display: flex; gap: 1rem; font-size: 0.875rem; color: var(--text-muted); background: rgba(255,255,255,0.03); padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid var(--glass-border);"
             onclick="openResourceModal('<?php echo $router['id']; ?>', '<?php echo htmlspecialchars($router['name']); ?>')">
            <div title="CPU Load"><i class="fas fa-microchip"></i> <span id="cpu-<?php echo $router['id']; ?>">--</span>%</div>
            <div title="Free Memory"><i class="fas fa-memory"></i> <span id="ram-<?php echo $router['id']; ?>">--</span></div>
            <div title="Uptime"><i class="fas fa-clock"></i> <span id="uptime-<?php echo $router['id']; ?>">--</span></div>
        </div>
    </div>
    
    <div class="dashboard-grid" style="margin-top: 1rem;">
        <?php 
        $interfacesData = json_decode($router['monitored_interfaces'], true);
        if (is_array($interfacesData)):
            foreach ($interfacesData as $ifaceObj): 
                $interface = $ifaceObj['name'];
                $label = $ifaceObj['label'] ?? $interface;
        ?>
        <div class="glass-card mini-card-v2" 
             data-router-id="<?php echo $router['id']; ?>" 
             data-interface="<?php echo $interface; ?>" 
             data-label="<?php echo htmlspecialchars($label); ?>"
             onclick="openModal('<?php echo $router['id']; ?>', '<?php echo $interface; ?>', '<?php echo htmlspecialchars($label); ?>')">
            
            <div class="card-v2-header">
                <h2 class="card-title-v2"><?php echo htmlspecialchars($label); ?></h2>
                <div class="status-badges">
                    <div class="badge rate-badge" id="rate-<?php echo $router['id']; ?>-<?php echo $interface; ?>">...</div>
                    <div class="badge sfp-badge-v2 non-sfp" id="sfp-<?php echo $router['id']; ?>-<?php echo $interface; ?>">
                        NON-SFP
                    </div>
                </div>
            </div>

            <div class="stats-container-v2">
                <div class="stat-box-v2 upload">
                    <div class="stat-label-v2"><i class="fas fa-arrow-up"></i> Upload</div>
                    <div class="stat-value-v2" id="tx-val-<?php echo $router['id']; ?>-<?php echo $interface; ?>">0 bps</div>
                </div>
                <div class="stat-box-v2 download">
                    <div class="stat-label-v2"><i class="fas fa-arrow-down"></i> Download</div>
                    <div class="stat-value-v2" id="rx-val-<?php echo $router['id']; ?>-<?php echo $interface; ?>">0 bps</div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
<?php endforeach; ?>
</div><!-- End Tab MikroTik -->

<div id="tab-olt" class="tab-content">
    <?php include __DIR__ . '/includes/olt_dashboard.php'; ?>
</div>

<!-- Full Screen Modal -->
<div id="traffic-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2 id="modal-title" style="font-family: 'Outfit', sans-serif; font-size: 1.5rem;">Interface Detail</h2>
                <p id="modal-subtitle" style="color: var(--text-muted); font-size: 0.875rem;">Live traffic monitoring</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="modal-body">
            <!-- Main Live Chart -->
            <div class="chart-container-large" style="margin-bottom: 2rem;">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--text-main); display: flex; justify-content: space-between; align-items: center;">
                    <span>Live Traffic</span>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <select id="live-period-select" onchange="changeLivePeriod(this.value)" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; background: rgba(0,0,0,0.2); color: #fff; border: 1px solid var(--glass-border); border-radius: 0.5rem; outline: none; cursor: pointer;">
                            <option value="live">1 Menit (Live)</option>
                            <option value="live_5m">5 Menit</option>
                            <option value="live_15m">15 Menit</option>
                            <option value="live_30m">30 Menit</option>
                            <option value="live_1h">1 Jam</option>
                        </select>
                        <span id="live-time-indicator" style="font-size: 0.8rem; color: var(--rx-color); font-weight: normal;"><i class="fas fa-circle status-dot" style="display: inline-block; margin-right: 5px;"></i> Real-time</span>
                    </div>
                </h3>
                <canvas id="modal-chart"></canvas>
            </div>
            
            <!-- Historical Charts Grid -->
            <div class="history-charts-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                <!-- Hourly Chart -->
                <div class="glass-card" style="padding: 1rem;">
                    <h4 style="font-family: 'Outfit', sans-serif; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-muted);">Traffic Tiap Jam (24 Jam Terakhir)</h4>
                    <div style="position: relative; height: 200px; width: 100%;">
                        <canvas id="chart-hourly"></canvas>
                    </div>
                </div>
                
                <!-- Daily Chart -->
                <div class="glass-card" style="padding: 1rem;">
                    <h4 style="font-family: 'Outfit', sans-serif; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-muted);">Traffic Tiap Hari (30 Hari Terakhir)</h4>
                    <div style="position: relative; height: 200px; width: 100%;">
                        <canvas id="chart-daily"></canvas>
                    </div>
                </div>
                
                <!-- Monthly Chart -->
                <div class="glass-card" style="padding: 1rem;">
                    <h4 style="font-family: 'Outfit', sans-serif; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-muted);">Traffic Tiap Bulan (24 Bulan Terakhir)</h4>
                    <div style="position: relative; height: 200px; width: 100%;">
                        <canvas id="chart-monthly"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resource History Modal -->
<div id="resource-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px; height: auto; max-height: 90vh;">
        <div class="modal-header">
            <div>
                <h2 id="res-modal-title" style="font-family: 'Outfit', sans-serif; font-size: 1.5rem;">System Resources</h2>
                <p id="res-modal-subtitle" style="color: var(--text-muted); font-size: 0.875rem;">CPU & RAM History</p>
            </div>
            <button class="close-btn" onclick="closeResourceModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 1.5rem; padding-bottom: 3rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 1rem;">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; color: var(--text-main);"><i class="fas fa-chart-line"></i> Performance Metrics</h3>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <select id="res-period-select" onchange="changeResourcePeriod(this.value)" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; background: rgba(0,0,0,0.2); color: #fff; border: 1px solid var(--glass-border); border-radius: 0.5rem; outline: none; cursor: pointer;">
                        <option value="live">1 Menit (Live)</option>
                        <option value="live_5m">5 Menit</option>
                        <option value="live_15m">15 Menit</option>
                        <option value="live_30m">30 Menit</option>
                        <option value="live_1h">1 Jam</option>
                        <option value="hourly">Tiap Jam (24 Jam)</option>
                        <option value="daily">Tiap Hari (30 Hari)</option>
                        <option value="monthly">Tiap Bulan (24 Bulan)</option>
                    </select>
                    <span id="res-time-indicator" style="font-size: 0.8rem; color: var(--rx-color); font-weight: normal;"><i class="fas fa-circle status-dot" style="display: inline-block; margin-right: 5px;"></i> Real-time</span>
                </div>
            </div>

            <div class="glass-card" style="padding: 1.5rem; background: linear-gradient(145deg, rgba(99, 102, 241, 0.05), rgba(30, 41, 59, 0.5));">
                <h3 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-microchip" style="color: #6366f1;"></i> CPU Load History (%)
                </h3>
                <div style="height: 220px;"><canvas id="cpu-history-chart"></canvas></div>
            </div>
            
            <div class="glass-card" style="padding: 1.5rem; background: linear-gradient(145deg, rgba(16, 185, 129, 0.05), rgba(30, 41, 59, 0.5));">
                <h3 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-memory" style="color: #10b981;"></i> Free Memory History (MB)
                </h3>
                <div style="height: 220px;"><canvas id="ram-history-chart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
