<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - CARI-IPTV Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #334155;
            --danger: #ef4444;
            --success: #22c55e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.75rem;
            margin: 0 auto 1rem;
        }

        .login-brand {
            font-size: 1.75rem;
            font-weight: 700;
        }

        .login-brand span {
            color: var(--primary-light);
        }

        .login-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .login-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 2rem;
        }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .alert svg {
            flex-shrink: 0;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.9375rem;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .form-check label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .login-footer a {
            color: var(--primary-light);
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .version {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            animation: fadeIn 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">C</div>
            <h1 class="login-brand">CARI<span>-IPTV</span></h1>
            <p class="login-subtitle">Admin Control Panel</p>
        </div>

        <div class="login-card">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/login">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-group">
                    <label class="form-label" for="username">Username or Email</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-input"
                        placeholder="Enter your username"
                        required
                        autocomplete="username"
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn-login">
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <a href="/admin/forgot-password">Forgot your password?</a>
            </div>
        </div>

        <div class="version">
            CARI-IPTV v1.0.0
        </div>
    </div>
</body>
</html>
