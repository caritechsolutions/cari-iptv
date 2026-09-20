<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title>Reset Password - <?= htmlspecialchars($siteName) ?></title>
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
                    <h1 class="login-title">Choose a new password</h1>
                    <p class="login-subtitle">Enter and confirm your new password below</p>
                </div>

                <div id="resetError" class="login-error"></div>

                <div id="resetInvalid" style="display: none; text-align: center;">
                    <div class="verify-icon verify-icon-error"><i class="lucide-x"></i></div>
                    <p style="color: var(--p-text-secondary); font-size: 0.9375rem; margin-bottom: 2rem;">
                        This reset link is invalid or has expired. Please request a new one from the app.
                    </p>
                    <a href="/login" class="login-btn" style="display: inline-block; text-decoration: none; text-align: center;">Go to Sign In</a>
                </div>

                <div id="resetSuccess" style="display: none; text-align: center;">
                    <div class="verify-icon verify-icon-success"><i class="lucide-check"></i></div>
                    <h2 class="login-title">Password updated</h2>
                    <p style="color: var(--p-text-secondary); font-size: 0.9375rem; margin-bottom: 2rem;">
                        Your password has been reset. All devices have been signed out. You can now sign in with your new password.
                    </p>
                    <a href="/login" class="login-btn" style="display: inline-block; text-decoration: none; text-align: center;">Sign In</a>
                </div>

                <form id="resetForm" class="login-form" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="password">New password</label>
                        <input type="password" id="password" name="password" class="form-input"
                               placeholder="At least 8 characters" autocomplete="new-password" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Confirm new password</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-input"
                               placeholder="Repeat your new password" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="login-btn" id="resetBtn">Reset Password</button>
                </form>

                <div class="login-footer">
                    <a href="/login" class="form-link">Back to Sign In</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const API_BASE = '/api/v1';
        const token = <?= json_encode($token) ?>;
        const form = document.getElementById('resetForm');
        const btn = document.getElementById('resetBtn');
        const errorEl = document.getElementById('resetError');

        function showError(msg) { errorEl.textContent = msg; errorEl.classList.add('visible'); errorEl.style.display = 'block'; }
        function hideError() { errorEl.classList.remove('visible'); errorEl.style.display = 'none'; }
        function showInvalid() {
            form.style.display = 'none';
            document.getElementById('resetInvalid').style.display = 'block';
        }

        // Validate the token before showing the form
        fetch(API_BASE + '/auth/reset-password/' + encodeURIComponent(token))
            .then(r => r.json())
            .then(d => { if (!d.data || !d.data.valid) showInvalid(); })
            .catch(() => { /* leave the form; submit will report the error */ });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError();
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            if (password.length < 8) { showError('Password must be at least 8 characters'); return; }
            if (password !== confirm) { showError('Passwords do not match'); return; }

            btn.disabled = true;
            btn.innerHTML = '<span class="login-spinner"></span> Saving...';
            try {
                const res = await fetch(API_BASE + '/auth/reset-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: token, password: password, password_confirm: confirm }),
                });
                const data = await res.json();
                if (!res.ok || data.error) {
                    if (data.error && data.error.code === 'INVALID_TOKEN') { showInvalid(); return; }
                    showError((data.error && data.error.message) || 'Password reset failed. Please try again.');
                    return;
                }
                form.style.display = 'none';
                document.querySelector('.login-header').style.display = 'none';
                document.getElementById('resetSuccess').style.display = 'block';
            } catch (err) {
                showError('Network error. Please try again.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Reset Password';
            }
        });
    })();
    </script>
</body>
</html>
