<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::check();
$db = new MiksDB();

if (isset($_GET['delete'])) {
    $db->deleteRouter($_GET['delete']);
    header('Location: index.php');
    exit();
}

$routers = $db->getRouters();
?>
<?php
require_once __DIR__ . '/../layout/header.php';
?>

<style>
    .admin-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 3rem;
    }
    .admin-actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 1rem;
        overflow: hidden;
    }
    .admin-table th {
        text-align: left;
        padding: 1.25rem 1rem;
        color: var(--text-muted);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--glass-border);
    }
    .admin-table td {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid var(--glass-border);
        color: var(--text-main);
    }
    .admin-table tr:hover {
        background: rgba(255, 255, 255, 0.02);
    }
    .action-btn {
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-size: 0.815rem;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .btn-edit { background: rgba(99, 102, 241, 0.1); color: var(--primary-color); border: 1px solid rgba(99, 102, 241, 0.2); }
    .btn-delete { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    .btn-add {
        background: var(--primary-color);
        color: white;
        padding: 0.6rem 1.25rem;
        border-radius: 0.6rem;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: 0.3s;
        border: none;
    }
    .btn-add:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
</style>

<div class="admin-container" style="padding-top: 3rem; padding-bottom: 5rem;">
    <div class="admin-header">
        <div>
            <p style="color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.5rem;">Network Infrastructure</p>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.25rem; font-weight: 700;">Manage Routers</h1>
        </div>
        <div class="admin-actions">
            <a href="manage_router.php" class="btn-add"><i class="fas fa-plus"></i> Add Router</a>
            <a href="manage_olt.php" class="btn-add" style="background: #10b981;"><i class="fas fa-server"></i> Manage OLT</a>
            <a href="settings.php" class="btn-add" style="background: #6366f1;"><i class="fas fa-cog"></i> Settings</a>
            <a href="../index.php" class="tab-btn" style="text-decoration: none;"><i class="fas fa-external-link-alt"></i> View Dashboard</a>
        </div>
    </div>

    <div class="glass-card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Host</th>
                        <th>User</th>
                        <th>Interfaces</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($routers)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 4rem;">No routers added yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($routers as $router): ?>
                        <tr>
                            <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($router['name']); ?></td>
                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($router['host']); ?></td>
                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($router['username']); ?></td>
                            <td><span class="badge" style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);"><?php echo htmlspecialchars($router['monitored_interfaces']); ?></span></td>
                            <td style="text-align: right;">
                                <a href="manage_router.php?id=<?php echo $router['id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                <a href="?delete=<?php echo $router['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
