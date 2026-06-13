<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::check();
$db = new MiksDB();

$message = '';
$messageType = '';

$adminUser = $db->getUserById($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_app_name') {
        $newAppName = trim($_POST['app_name']);
        if (!empty($newAppName)) {
            $configFile = __DIR__ . '/../includes/config.php';
            $configContent = file_get_contents($configFile);
            // Replace the APP_NAME definition using regex
            $newConfigContent = preg_replace("/define\('APP_NAME',\s*'(.*?)'\);/", "define('APP_NAME', '" . addslashes($newAppName) . "');", $configContent);
            
            if (file_put_contents($configFile, $newConfigContent)) {
                $message = "Application name updated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to write to config.php. Please check file permissions.";
                $messageType = "error";
            }
        } else {
            $message = "Application name cannot be empty.";
            $messageType = "error";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_timezone') {
        $newTimezone = trim($_POST['timezone']);
        if (!empty($newTimezone)) {
            $configFile = __DIR__ . '/../includes/config.php';
            $configContent = file_get_contents($configFile);
            // Replace the APP_TIMEZONE definition
            $newConfigContent = preg_replace("/define\('APP_TIMEZONE',\s*'(.*?)'\);/", "define('APP_TIMEZONE', '" . addslashes($newTimezone) . "');", $configContent);
            
            if (file_put_contents($configFile, $newConfigContent)) {
                $message = "Timezone updated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to update config.php.";
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_credentials') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if (!empty($username)) {
            if (!empty($password)) {
                // Update username and password
                $db->updateAdminCredentials($_SESSION['user_id'], $username, $password);
                $message = "Credentials updated! Please login again with your new password.";
                $messageType = "success";
                // Force logout after password change
                session_destroy();
                header("Refresh: 2; URL=../login.php");
            } else {
                // Update username only
                $db->updateAdminCredentials($_SESSION['user_id'], $username);
                $message = "Username updated successfully.";
                $messageType = "success";
                $adminUser = $db->getUserById($_SESSION['user_id']); // refresh data
            }
        } else {
            $message = "Username cannot be empty.";
            $messageType = "error";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_whatsapp') {
        $wa_url = trim($_POST['wa_url']);
        $wa_number = trim($_POST['wa_number']);
        $wa_threshold = trim($_POST['wa_threshold']);
        $wa_interval = trim($_POST['wa_interval']);
        
        $configFile = __DIR__ . '/../includes/config.php';
        $configContent = file_get_contents($configFile);
        
        $configContent = preg_replace("/define\('WA_API_URL',\s*'(.*?)'\);/", "define('WA_API_URL', '" . addslashes($wa_url) . "');", $configContent);
        $configContent = preg_replace("/define\('WA_TARGET_NUMBER',\s*'(.*?)'\);/", "define('WA_TARGET_NUMBER', '" . addslashes($wa_number) . "');", $configContent);
        $configContent = preg_replace("/define\('WA_TRAFFIC_THRESHOLD',\s*'(.*?)'\);/", "define('WA_TRAFFIC_THRESHOLD', '" . addslashes($wa_threshold) . "');", $configContent);
        $configContent = preg_replace("/define\('WA_CHECK_INTERVAL',\s*'(.*?)'\);/", "define('WA_CHECK_INTERVAL', '" . addslashes($wa_interval) . "');", $configContent);
        
        if (file_put_contents($configFile, $configContent)) {
            $message = "WhatsApp settings updated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update config.php.";
            $messageType = "error";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_admin') {
        $new_user = trim($_POST['new_username']);
        $new_pass = trim($_POST['new_password']);
        if (!empty($new_user) && !empty($new_pass)) {
            try {
                $db->addAdmin($new_user, $new_pass);
                $message = "New admin added successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Failed to add admin. Username might already exist.";
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
        $del_id = (int)$_POST['delete_id'];
        if ($del_id !== $_SESSION['user_id']) {
            if ($db->deleteAdmin($del_id)) {
                $message = "Admin deleted successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to delete admin. Cannot delete the last admin.";
                $messageType = "error";
            }
        } else {
            $message = "You cannot delete your own active session.";
            $messageType = "error";
        }
    }
}

$allAdmins = $db->getAllAdmins();
?>
<?php require_once __DIR__ . '/../layout/header.php'; ?>

<style>
    .settings-container {
        max-width: 800px;
        margin: 0 auto;
        padding-top: 3rem;
        padding-bottom: 5rem;
    }
    .admin-header {
        margin-bottom: 2rem;
    }
    .section-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .settings-card {
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid var(--glass-border);
        border-radius: 1rem;
        padding: 2rem;
        margin-bottom: 2rem;
        backdrop-filter: blur(10px);
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .form-control {
        width: 100%;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        font-family: 'Inter', sans-serif;
        transition: 0.3s;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }
    .btn-save {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
    .alert {
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 2rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    .btn-back {
        color: var(--text-muted);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        transition: 0.3s;
    }
    .btn-back:hover {
        color: white;
    }
</style>

<div class="settings-container">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    
    <div class="admin-header">
        <p style="color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.5rem;">System Configuration</p>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.25rem; font-weight: 700;">Settings</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- General Settings -->
    <div class="settings-card">
        <h2 class="section-title"><i class="fas fa-desktop"></i> General Settings</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_app_name">
            <div class="form-group">
                <label>Application Name</label>
                <input type="text" name="app_name" class="form-control" value="<?php echo htmlspecialchars(APP_NAME); ?>" required>
                <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">This name will appear on the top navigation bar and browser title.</small>
            </div>
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Application Name</button>
        </form>
    </div>

    <!-- Timezone Settings -->
    <div class="settings-card">
        <h2 class="section-title"><i class="fas fa-clock"></i> Timezone Settings</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_timezone">
            <div class="form-group">
                <label>System Timezone</label>
                <select name="timezone" class="form-control" style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white;">
                    <?php
                    $timezones = [
                        'Asia/Jakarta' => '(UTC+07:00) Asia/Jakarta (WIB)',
                        'Asia/Makassar' => '(UTC+08:00) Asia/Makassar (WITA)',
                        'Asia/Jayapura' => '(UTC+09:00) Asia/Jayapura (WIT)',
                        'UTC' => 'UTC'
                    ];
                    foreach ($timezones as $tz => $label) {
                        $selected = (APP_TIMEZONE === $tz) ? 'selected' : '';
                        echo "<option value=\"$tz\" $selected>$label</option>";
                    }
                    ?>
                </select>
                <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">This will affect how charts and logs display time.</small>
            </div>
            <button type="submit" class="btn-save" style="background: #f59e0b;"><i class="fas fa-globe"></i> Update Timezone</button>
        </form>
    </div>

    <!-- Security Settings (Update Current) -->
    <div class="settings-card">
        <h2 class="section-title"><i class="fas fa-shield-alt"></i> Update My Credentials</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_credentials">
            <div class="form-group">
                <label>Admin Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($adminUser['username']); ?>" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">If you change your password, you will be automatically logged out.</small>
            </div>
            <button type="submit" class="btn-save" style="background: #10b981;"><i class="fas fa-lock"></i> Update My Credentials</button>
        </form>
    </div>

    <!-- Admin Management -->
    <div class="settings-card">
        <h2 class="section-title"><i class="fas fa-users"></i> Admin Management</h2>
        
        <!-- List Admins -->
        <div style="background: rgba(0,0,0,0.2); border-radius: 0.5rem; padding: 1rem; margin-bottom: 2rem;">
            <table style="width: 100%; border-collapse: collapse; color: white;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <th style="text-align: left; padding: 0.5rem;">Username</th>
                        <th style="text-align: left; padding: 0.5rem;">Created At</th>
                        <th style="text-align: right; padding: 0.5rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allAdmins as $adm): ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <td style="padding: 0.5rem;"><?php echo htmlspecialchars($adm['username']); ?> <?php if ($adm['id'] == $_SESSION['user_id']) echo '<span style="font-size: 0.7rem; background: var(--primary-color); padding: 2px 6px; border-radius: 10px;">You</span>'; ?></td>
                        <td style="padding: 0.5rem; color: var(--text-muted);"><?php echo $adm['created_at']; ?></td>
                        <td style="padding: 0.5rem; text-align: right;">
                            <?php if ($adm['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                <input type="hidden" name="action" value="delete_admin">
                                <input type="hidden" name="delete_id" value="<?php echo $adm['id']; ?>">
                                <button type="submit" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; padding: 0.25rem 0.5rem; border-radius: 0.25rem; cursor: pointer;"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1rem;">Add New Admin</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_admin">
            <div style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label>Username</label>
                    <input type="text" name="new_username" class="form-control" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn-save" style="background: var(--primary-color);"><i class="fas fa-user-plus"></i> Add Admin</button>
        </form>
    </div>

    <!-- WhatsApp Settings -->
    <div class="settings-card">
        <h2 class="section-title"><i class="fab fa-whatsapp"></i> WhatsApp Notifications</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_whatsapp">
            <div class="form-group">
                <label>Baileys API URL</label>
                <input type="url" name="wa_url" class="form-control" value="<?php echo htmlspecialchars(defined('WA_API_URL') ? WA_API_URL : ''); ?>" placeholder="http://ip-api-baileys:3000/chat/sendmessage" required>
                <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">Full URL of your Baileys API endpoint.</small>
            </div>
            <div class="form-group">
                <label>Target Phone Number</label>
                <input type="text" name="wa_number" class="form-control" value="<?php echo htmlspecialchars(defined('WA_TARGET_NUMBER') ? WA_TARGET_NUMBER : ''); ?>" placeholder="62812345678" required>
                <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">Include country code, e.g. 628...</small>
            </div>
            <div style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label>Traffic Threshold (Mbps)</label>
                    <input type="number" step="0.1" name="wa_threshold" class="form-control" value="<?php echo htmlspecialchars(defined('WA_TRAFFIC_THRESHOLD') ? WA_TRAFFIC_THRESHOLD : '10'); ?>" required>
                    <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">Trigger alert if traffic drops below this.</small>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Check Interval (Minutes)</label>
                    <input type="number" min="1" name="wa_interval" class="form-control" value="<?php echo htmlspecialchars(defined('WA_CHECK_INTERVAL') ? WA_CHECK_INTERVAL : '5'); ?>" required>
                    <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">How often to check traffic.</small>
                </div>
            </div>
            <button type="submit" class="btn-save" style="background: #25D366;"><i class="fas fa-paper-plane"></i> Save WhatsApp Settings</button>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
