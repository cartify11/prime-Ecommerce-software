<?php
/**
 * Prime E Commerce Hub - Authentication Login Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/helpers/Auth.php';

// If already logged in, redirect to main application
if (Auth::check()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Check if system is installed
try {
    $pdo = Database::getConnection();
    $check = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'system_installed'");
    if (!$check || $check->fetchColumn() !== '1') {
        header('Location: ' . BASE_URL . '/install.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: ' . BASE_URL . '/install.php');
    exit;
}

$csrfToken = Security::getCsrfToken();
$expired = isset($_GET['expired']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Prime E Commerce Hub</title>
    <link rel="icon" type="image/png" href="assets/images/icon.png">
    <style>
        :root {
            --bg-primary: #0a0d14;
            --bg-secondary: #0f131c;
            --bg-card: rgba(18, 22, 32, 0.95);
            --border-color: rgba(226, 177, 60, 0.2);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-gold: #e2b13c;
            --accent-gold-glow: rgba(226, 177, 60, 0.3);
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body {
            background-color: var(--bg-primary);
            background-image: radial-gradient(circle at 50% 20%, rgba(226, 177, 60, 0.05), transparent 60%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.8), 0 0 25px rgba(226, 177, 60, 0.05);
            backdrop-filter: blur(12px);
        }
        .brand-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .brand-logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
            margin-bottom: 12px;
            filter: drop-shadow(0 0 10px var(--accent-gold-glow));
        }
        .brand-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--accent-gold);
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease;
        }
        input:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 12px rgba(226, 177, 60, 0.25);
            background: rgba(255, 255, 255, 0.05);
        }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: var(--accent-gold);
            color: #0a0d14;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-login:hover:not(:disabled) {
            opacity: 0.92;
            transform: translateY(-1px);
            box-shadow: 0 5px 20px rgba(226, 177, 60, 0.35);
        }
        .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .alert-box {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: none;
        }
        .alert-box.show { display: block; }
        .alert-box.error {
            background: rgba(244, 63, 94, 0.12);
            border: 1px solid var(--accent-rose);
            color: #fecdd3;
        }
        .alert-box.info {
            background: rgba(226, 177, 60, 0.12);
            border: 1px solid var(--accent-gold);
            color: var(--accent-gold);
        }
        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 0.75rem;
            color: var(--text-secondary);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand-header">
            <img src="assets/images/icon.png" alt="Prime Hub Logo" class="brand-logo" onerror="this.style.display='none'">
            <h1 class="brand-title">Prime Hub</h1>
            <p class="brand-sub">Multi-User Management Portal</p>
        </div>

        <div id="alert-box" class="alert-box <?php echo $expired ? 'info show' : ''; ?>">
            <?php echo $expired ? 'Your previous session has expired. Please login again.' : ''; ?>
        </div>

        <form id="login-form">
            <input type="hidden" name="csrf_token" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" autocomplete="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn-login" id="submit-btn">
                <span>Sign In to System</span>
            </button>
        </form>

        <div class="footer-note">
            Prime E Commerce Hub &bull; Enterprise Business Management
        </div>
    </div>

    <script>
        const form = document.getElementById('login-form');
        const alertBox = document.getElementById('alert-box');
        const submitBtn = document.getElementById('submit-btn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            alertBox.className = 'alert-box';
            alertBox.textContent = '';
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Authenticating...';

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const csrfToken = document.getElementById('csrf-token').value;

            try {
                const res = await fetch('api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ username, password, csrf_token: csrfToken })
                });

                const data = await res.json();

                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    alertBox.className = 'alert-box error show';
                    alertBox.textContent = data.message || 'Login failed. Please verify credentials.';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Sign In to System';
                }
            } catch (err) {
                alertBox.className = 'alert-box error show';
                alertBox.textContent = 'Network or server error. Please try again.';
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Sign In to System';
            }
        });
    </script>
</body>
</html>
