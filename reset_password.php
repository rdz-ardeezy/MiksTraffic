<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$message = '';
$messageType = '';
$validToken = false;
$username = '';

$db = new MiksDB();

// Validasi Token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $resetData = $db->verifyPasswordResetToken($token);
    
    if ($resetData) {
        $validToken = true;
        $username = $resetData['username'];
    } else {
        $message = "Tautan reset password tidak valid atau sudah kedaluwarsa.";
        $messageType = "error";
    }
} else {
    header("Location: login.php");
    exit();
}

// Proses form ubah password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "Semua kolom harus diisi.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Konfirmasi password tidak cocok.";
        $messageType = "error";
    } else {
        $user = $db->getUserByUsername($username);
        if ($user) {
            $db->updateAdminCredentials($user['id'], $username, $newPassword);
            $db->deletePasswordResetToken($token);
            $message = "Password berhasil diubah! Mengarahkan ke halaman login...";
            $messageType = "success";
            $validToken = false; // Sembunyikan form
            header("Refresh: 3; URL=login.php");
        } else {
            $message = "Pengguna tidak ditemukan.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Password Baru - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary-color);
            border: none;
            border-radius: 0.5rem;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            text-align: center;
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
    </style>
</head>
<body class="login-container">
    <div class="glass-card login-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem;">Buat Password Baru</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.5rem;">Silakan masukkan password baru untuk <strong><?php echo htmlspecialchars($username); ?></strong></p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($validToken): ?>
        <form method="POST">
            <div class="form-group">
                <label for="password">Password Baru</label>
                <input type="password" id="password" name="password" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password Baru</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary" style="margin-bottom: 1rem;">Simpan Password</button>
        </form>
        <?php endif; ?>
        
        <?php if (!$validToken && $messageType === 'error'): ?>
            <div style="text-align: center;">
                <a href="forgot_password.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.875rem;">Minta Link Baru</a>
                <br><br>
            </div>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="login.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.875rem;">Kembali ke Halaman Login</a>
        </div>
    </div>
</body>
</html>
