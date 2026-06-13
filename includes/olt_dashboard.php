<?php
/**
 * OLT Dashboard Component
 */
$db = new MiksDB();
$olts = $db->getOlts();
?>

<div class="olt-dashboard" style="margin-top: 2rem;">
    <div class="dashboard-header" style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1.5rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem;">OLT Status Monitor</h1>
            <button onclick="refreshAllOlts()" class="btn btn-secondary btn-sm" id="btn-refresh-olts">
                <i class="fas fa-sync-alt"></i> Refresh All
            </button>
        </div>
        
        <div class="search-box" style="flex: 1; min-width: 250px; max-width: 500px; position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" id="globalOltSearch" placeholder="Search Customer or MAC..." class="form-control" style="padding-left: 2.5rem;" onkeyup="globalOltSearch()">
        </div>
    </div>

    <div id="search-results-olts" style="display: none; margin-bottom: 2rem;">
        <!-- Search results will appear here -->
    </div>

    <div class="dashboard-grid">
        <?php if (empty($olts)): ?>
            <div class="glass-card" style="grid-column: 1 / -1; padding: 4rem; text-align: center;">
                <p style="color: var(--text-muted);">No OLT devices configured. <a href="admin/manage_olt.php" style="color: var(--primary-color);">Add one now</a>.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($olts as $olt): ?>
        <div class="glass-card olt-card" id="olt-card-<?php echo $olt['id']; ?>" 
             data-olt-id="<?php echo $olt['id']; ?>"
             onclick="showOltDetails(<?php echo $olt['id']; ?>, '<?php echo htmlspecialchars($olt['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($olt['host'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(str_replace(["\r", "\n"], ' ', $olt['description']), ENT_QUOTES); ?>')"
             style="cursor: pointer; transition: transform 0.3s ease;">
            
            <div class="card-v2-header">
                <div>
                    <h2 class="card-title-v2"><?php echo htmlspecialchars($olt['name']); ?></h2>
                    <p class="line-clamp-2" style="color: var(--text-muted); margin-top: 0.5rem;"><?php echo htmlspecialchars($olt['description'] ?: 'No description'); ?></p>
                </div>
                <div class="status-badges">
                    <div class="badge rate-badge" id="olt-status-<?php echo $olt['id']; ?>">Checking...</div>
                </div>
            </div>

            <div id="olt-stats-<?php echo $olt['id']; ?>" style="margin-top: 1.5rem; margin-bottom: 0.5rem;">
                <div style="display: flex; justify-content: center; padding: 2rem;">
                    <i class="fas fa-circle-notch fa-spin fa-2x" style="color: var(--primary-color);"></i>
                </div>
            </div>
            
            <!-- Button removed as requested, card is now clickable -->
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ONU Detail Modal -->
<div id="oltDetailModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 95%; width: 1200px; height: 85vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <div style="flex: 1;">
                <h2 id="oltDetailTitle" style="font-size: 1.25rem;">OLT Details</h2>
                <div id="oltDetailSub" style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;"></div>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a id="btn-open-olt" href="#" target="_blank" class="btn btn-secondary btn-sm" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;"><i class="fas fa-external-link-alt"></i> <span class="hide-mobile">Open Web</span></a>
                <button class="close-btn" onclick="closeOltDetails()" style="width: 32px; height: 32px; font-size: 0.8rem;"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="modal-body" style="flex: 1; overflow: hidden; display: flex; flex-direction: column; padding: 1.5rem;">
            
            <div class="modal-controls-wrapper" style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div class="tabs-container" id="pon-tabs" style="flex: none; overflow-x: auto;">
                        <button class="tab-btn active" onclick="switchPonTab('all')">All PON</button>
                        <button class="tab-btn" onclick="switchPonTab('0/1/1')">PON 1</button>
                        <button class="tab-btn" onclick="switchPonTab('0/1/2')">PON 2</button>
                    </div>
                    <div class="badge-group" style="display: flex; gap: 0.5rem; font-size: 0.75rem;">
                        <div class="badge" style="background: rgba(255,255,255,0.05); padding: 0.2rem 0.5rem;">Total: <span id="count-total">0</span></div>
                        <div class="badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 0.2rem 0.5rem;">Online: <span id="count-online">0</span></div>
                    </div>
                </div>

                <div class="search-box" style="width: 100%; position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="onuSearch" placeholder="Search MAC, ID, or Customer..." class="form-control" style="padding-left: 2.5rem;" onkeyup="filterOnus()">
                </div>
            </div>

            <div class="table-responsive" style="flex: 1; background: rgba(0,0,0,0.2); border-radius: 0.75rem; border: 1px solid var(--glass-border);">
                <table class="onu-table" style="width: 100%; border-collapse: collapse; min-width: 700px;">
                    <thead style="position: sticky; top: 0; background: #1e293b; z-index: 10;">
                        <tr>
                            <th class="onu-info-col" style="text-align: left; padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Customer Info</th>
                            <th class="onu-desktop-only" style="text-align: left; padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">ID</th>
                            <th class="onu-desktop-only" style="text-align: left; padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Customer Name</th>
                            <th class="onu-desktop-only" style="text-align: left; padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">MAC Address</th>
                            <th style="text-align: left; padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Status</th>
                            <th style="text-align: left; padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">RX Power</th>
                            <th class="onu-desktop-only" style="text-align: left; padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Distance</th>
                        </tr>
                    </thead>
                    <tbody id="onuListBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.onu-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--glass-border);
    font-size: 0.875rem;
}
.onu-table tr:hover {
    background: rgba(255,255,255,0.02);
}
.sig-low { color: #ef4444; font-weight: bold; }
.sig-good { color: #10b981; font-weight: bold; }
</style>
