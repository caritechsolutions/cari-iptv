<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title>Sign In - <?= htmlspecialchars($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fonts/lucide/lucide.css">
    <link rel="stylesheet" href="/assets/css/player.css">
</head>
<body>
    <div class="login-page">
        <div class="login-bg"></div>
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="login-logo">
                        <?php if (!empty($siteLogo)): ?>
                            <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="login-logo-img">
                        <?php else: ?>
                            <div class="login-logo-icon"><?= strtoupper(substr($siteName, 0, 1)) ?></div>
                        <?php endif; ?>
                        <span class="login-logo-text"><?= htmlspecialchars($siteName) ?></span>
                    </div>
                    <h1 class="login-title">Welcome back</h1>
                    <p class="login-subtitle">Sign in to continue watching</p>
                </div>

                <div id="loginError" class="login-error"></div>

                <form id="loginForm" class="login-form" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="identity">Username or Email</label>
                        <input type="text" id="identity" name="identity" class="form-input"
                               placeholder="Enter your username or email" autocomplete="username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-input"
                                   placeholder="Enter your password" autocomplete="current-password" required>
                            <button type="button" class="input-toggle" id="togglePassword" tabindex="-1">
                                <i class="lucide-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="form-checkbox">
                            <input type="checkbox" id="remember" name="remember">
                            Remember me
                        </label>
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        Sign In
                    </button>
                </form>

                <div class="login-footer">
                    Don't have an account? <a href="/register" class="form-link">Register</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const API_BASE = '/api/v1';
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('loginBtn');
        const errorEl = document.getElementById('loginError');
        const togglePw = document.getElementById('togglePassword');
        const pwInput = document.getElementById('password');

        // Toggle password visibility
        togglePw.addEventListener('click', function() {
            const isPassword = pwInput.type === 'password';
            pwInput.type = isPassword ? 'text' : 'password';
            this.querySelector('i').className = isPassword ? 'lucide-eye-off' : 'lucide-eye';
        });

        // Handle form submit
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const identity = document.getElementById('identity').value.trim();
            const password = pwInput.value;

            if (!identity || !password) {
                showError('Please enter your username and password');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="login-spinner"></span> Signing in...';
            hideError();

            try {
                const res = await fetch(API_BASE + '/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        identity: identity,
                        password: password,
                        device_type: 'web',
                    }),
                });

                const data = await res.json();

                if (!res.ok || data.error) {
                    showError(data.error?.message || 'Invalid username or password');
                    btn.disabled = false;
                    btn.textContent = 'Sign In';
                    return;
                }

                // Store tokens
                const tokenData = data.data;
                localStorage.setItem('access_token', tokenData.access_token);
                localStorage.setItem('refresh_token', tokenData.refresh_token);
                localStorage.setItem('token_expires', Date.now() + (tokenData.expires_in * 1000));
                localStorage.setItem('user', JSON.stringify(tokenData.user));

                if (document.getElementById('remember').checked) {
                    localStorage.setItem('remember', '1');
                }

                // Redirect to app
                window.location.href = '/';
            } catch (err) {
                showError('Connection error. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Sign In';
            }
        });

        function showError(msg) {
            errorEl.textContent = msg;
            errorEl.classList.add('visible');
        }

        function hideError() {
            errorEl.classList.remove('visible');
        }

        // If already authenticated, redirect to app
        if (localStorage.getItem('access_token')) {
            window.location.href = '/';
        }
    })();
    </script>
</body>
</html>
