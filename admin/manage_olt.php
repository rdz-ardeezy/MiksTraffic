<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::check();

$db = new MiksDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $data = [
            'id' => $_POST['id'] ?? '',
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'type' => $_POST['type'],
            'host' => $_POST['host'],
            'user' => $_POST['user'],
            'password' => $_POST['password'],
            'port' => $_POST['port'] ?? 80
        ];
        
        if ($db->saveOlt($data)) {
            $message = '<div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fas fa-check-circle"></i> OLT saved successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid rgba(239, 68, 68, 0.2);"><i class="fas fa-exclamation-circle"></i> Failed to save OLT.</div>';
        }
    } elseif ($action === 'delete') {
        if ($db->deleteOlt($_POST['id'])) {
            $message = '<div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fas fa-trash"></i> OLT deleted successfully!</div>';
        }
    }
}

$olts = $db->getOlts();
require_once __DIR__ . '/../layout/header.php';
?>

<div class="admin-container" style="padding-top: 3rem; padding-bottom: 5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
        <div>
            <p style="color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.5rem;">Inventory</p>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.25rem; font-weight: 700;">Manage OLTs</h1>
        </div>
        <div style="display: flex; gap: 1rem;">
            <a href="index.php" class="tab-btn" style="text-decoration: none; border: 1px solid var(--glass-border);"><i class="fas fa-arrow-left"></i> Back</a>
            <button onclick="openOltModal()" class="tab-btn active" style="width: auto;"><i class="fas fa-plus"></i> Add OLT</button>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="dashboard-grid">
        <?php if (empty($olts)): ?>
            <div class="glass-card" style="grid-column: 1 / -1; padding: 4rem; text-align: center;">
                <p style="color: var(--text-muted);">No OLT devices found. Add your first OLT to start monitoring.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($olts as $olt): ?>
        <div class="glass-card mini-card-v2" style="cursor: default;">
            <div class="card-v2-header">
                <div>
                    <h2 class="card-title-v2"><?php echo htmlspecialchars($olt['name']); ?></h2>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;"><i class="fas fa-link"></i> <?php echo htmlspecialchars($olt['host']); ?></p>
                </div>
                <div class="status-badges">
                    <div class="badge sfp-badge-v2" style="background: rgba(99, 102, 241, 0.1); color: #818cf8;"><?php echo $olt['type']; ?></div>
                </div>
            </div>
            
            <div style="margin-top: 1rem;">
                <p style="font-size: 0.875rem; color: var(--text-muted); min-height: 2.5rem;"><?php echo htmlspecialchars($olt['description'] ?: 'No description provided.'); ?></p>
            </div>
            
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--glass-border); display: flex; gap: 0.75rem;">
                <button onclick='editOlt(<?php echo json_encode($olt); ?>)' class="btn btn-secondary btn-sm" style="flex: 1; padding: 0.6rem; border-radius: 0.5rem; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid var(--glass-border); cursor: pointer;"><i class="fas fa-edit"></i> Edit</button>
                <form method="POST" style="flex: 1;" onsubmit="return confirm('Delete this OLT?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $olt['id']; ?>">
                    <button type="submit" class="btn-danger btn-sm" style="width: 100%; padding: 0.6rem; border-radius: 0.5rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); cursor: pointer;"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- OLT Modal -->
<div id="oltModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <div>
                <h2 id="modalTitle" style="font-family: 'Outfit', sans-serif;">Add OLT</h2>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Configure access credentials</p>
            </div>
            <button class="close-btn" onclick="closeOltModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="olt_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Friendly Name</label>
                        <input type="text" name="name" id="olt_name" class="form-control" required placeholder="e.g. OLT Pusat">
                    </div>
                    <div class="form-group">
                        <label>Type / Brand</label>
                        <select name="type" id="olt_type" class="form-control" required>
                            <option value="HIOSO_HA7302CST">HIOSO HA7302CST</option>
                            <option value="VSOL">VSOL / Generic EPON</option>
                            <option value="Mock_Driver">Mock OLT (Testing)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>IP Address / Host</label>
                    <input type="text" name="host" id="olt_host" class="form-control" required placeholder="192.168.1.1">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="user" id="olt_user" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" id="olt_password" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="olt_desc" class="form-control" rows="2" placeholder="Location or notes..."></textarea>
                </div>

                <div class="form-group">
                    <label>Port HTTP/Web</label>
                    <input type="number" name="port" id="olt_port" class="form-control" value="80">
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="tab-btn active" style="flex: 1; justify-content: center; border: none;"><i class="fas fa-save"></i> Save Device</button>
                    <button type="button" class="tab-btn" onclick="closeOltModal()" style="flex: 1; justify-content: center; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openOltModal() {
    document.getElementById('modalTitle').textContent = 'Add OLT';
    document.getElementById('olt_id').value = '';
    document.getElementById('olt_name').value = '';
    document.getElementById('olt_host').value = '';
    document.getElementById('olt_user').value = '';
    document.getElementById('olt_password').value = '';
    document.getElementById('olt_desc').value = '';
    document.getElementById('olt_type').value = 'HIOSO_HA7302CST';
    document.getElementById('olt_port').value = '80';
    document.getElementById('oltModal').style.display = 'block';
}

function closeOltModal() {
    document.getElementById('oltModal').style.display = 'none';
}

function editOlt(olt) {
    document.getElementById('modalTitle').textContent = 'Edit OLT';
    document.getElementById('olt_id').value = olt.id;
    document.getElementById('olt_name').value = olt.name;
    document.getElementById('olt_host').value = olt.host;
    document.getElementById('olt_user').value = olt.user;
    document.getElementById('olt_password').value = olt.password;
    document.getElementById('olt_desc').value = olt.description;
    document.getElementById('olt_type').value = olt.type;
    document.getElementById('olt_port').value = olt.port;
    document.getElementById('oltModal').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
