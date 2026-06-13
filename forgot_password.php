<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    if (!empty($username)) {
        $db = new MiksDB();
        $user = $db->getUserByUsername($username);
        
        // We always show the same message for security reasons (don't reveal if user exists)
        $message = "Jika username tersebut ada di database, tautan reset password telah dikirimkan ke email rdznetwork.cs@gmail.com.";
        $messageType = "success";
        
        if ($user) {
            $token = $db->createPasswordResetToken($username);
            if ($token) {
                // Determine the base URL for the reset link
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $resetLink = $protocol . '://' . $host . $uri . '/reset_password.php?token=' . $token;
                
                $to = 'rdznetwork.cs@gmail.com';
                $subject = 'Reset Password Admin - ' . APP_NAME;
                $emailBody = "Halo Admin,\n\n";
                $emailBody .= "Seseorang telah meminta reset password untuk akun admin ('$username') di aplikasi " . APP_NAME . ".\n\n";
                $emailBody .= "Silakan klik tautan berikut untuk membuat password baru:\n";
                $emailBody .= $resetLink . "\n\n";
                $emailBody .= "Tautan ini akan kedaluwarsa dalam 1 jam.\n";
                $emailBody .= "Jika Anda tidak meminta reset password ini, abaikan saja email ini.\n\n";
                $emailBody .= "Terima kasih,\n" . APP_NAME . " System";
                
                $headers = "From: no-reply@mrtg-system.local\r\n";
                $headers .= "Reply-To: no-reply@mrtg-system.local\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                // Attempt to send email
                @mail($to, $subject, $emailBody, $headers);
            }
        }
    } else {
        $message = "Username tidak boleh kosong.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?php echo APP_NAME; ?></title>
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
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem;">Reset Password</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.5rem;">Kami akan mengirimkan instruksi ke email rdznetwork.cs@gmail.com</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Admin Username Anda</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus>
            </div>
            <button type="submit" class="btn-primary" style="margin-bottom: 1rem;">Kirim Link Reset</button>
            <div style="text-align: center;">
                <a href="login.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.875rem;">Kembali ke Halaman Login</a>
            </div>
        </form>
    </div>
</body>
</html>
