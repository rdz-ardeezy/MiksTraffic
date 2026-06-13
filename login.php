<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new MiksDB();
    if (Auth::login($_POST['username'], $_POST['password'], $db)) {
        header('Location: admin/index.php');
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
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
        .error-msg {
            color: #ef4444;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body class="login-container">
    <div class="glass-card login-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem;"><?php echo APP_NAME; ?> Admin</h1>
            <p style="color: var(--text-muted);">Secure Access</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary">Login</button>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="forgot_password.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.875rem; transition: color 0.3s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-muted)'">Lupa Password?</a>
            </div>
        </form>
    </div>
</body>
</html>
