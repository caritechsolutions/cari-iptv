<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title>Delete Account - <?= htmlspecialchars($siteName) ?></title>
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
                    <h1 class="login-title">Delete your account</h1>
                    <p class="login-subtitle">Sign in to confirm you own this account</p>
                </div>

                <div id="deleteError" class="login-error"></div>

                <div id="deleteInfo" style="color: var(--p-text-secondary); font-size: 0.875rem; margin-bottom: 1.25rem; line-height: 1.6;">
                    <p><strong style="color: var(--p-text);">This cannot be undone.</strong> Deleting your account will permanently remove:</p>
                    <ul style="margin: 0.5rem 0 0.5rem 1.25rem;">
                        <li>your name, email address, phone number and profile details</li>
                        <li>your watch history, continue-watching positions and My List</li>
                        <li>your ratings and personalised recommendations</li>
                        <li>all signed-in devices</li>
                    </ul>
                    <p>Subscription and billing records are kept in anonymised form for accounting purposes. See the <a href="/privacy" class="form-link">Privacy Policy</a> for details.</p>
                </div>

                <div id="deleteSuccess" style="display: none; text-align: center;">
                    <div class="verify-icon verify-icon-success"><i class="lucide-check"></i></div>
                    <h2 class="login-title">Account deleted</h2>
                    <p style="color: var(--p-text-secondary); font-size: 0.9375rem; margin-bottom: 2rem;">
                        Your account and personal data have been removed. You can close this page.
                    </p>
                </div>

                <form id="deleteForm" class="login-form" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="identity">Username or Email</label>
                        <input type="text" id="identity" name="identity" class="form-input"
                               placeholder="Enter your username or email" autocomplete="username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input"
                               placeholder="Enter your password" autocomplete="current-password" required>
                    </div>
                    <div class="form-options">
                        <label class="form-checkbox">
                            <input type="checkbox" id="confirmDelete">
                            I understand my account and data will be permanently deleted
                        </label>
                    </div>
                    <button type="submit" class="login-btn" id="deleteBtn" style="background: var(--p-danger, #ef4444);">Delete My Account</button>
                </form>

                <div class="login-footer">
                    Changed your mind? <a href="/login" class="form-link">Back to Sign In</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const API_BASE = '/api/v1';
        const form = document.getElementById('deleteForm');
        const btn = document.getElementById('deleteBtn');
        const errorEl = document.getElementById('deleteError');

        function showError(msg) { errorEl.textContent = msg; errorEl.classList.add('visible'); errorEl.style.display = 'block'; }
        function hideError() { errorEl.classList.remove('visible'); errorEl.style.display = 'none'; }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError();
            const identity = document.getElementById('identity').value.trim();
            const password = document.getElementById('password').value;
            if (!identity || !password) { showError('Please enter your username and password'); return; }
            if (!document.getElementById('confirmDelete').checked) { showError('Please tick the confirmation box to continue'); return; }

            btn.disabled = true;
            btn.innerHTML = '<span class="login-spinner"></span> Deleting...';
            try {
                // 1. Prove ownership by signing in (issues a short-lived token)
                const loginRes = await fetch(API_BASE + '/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ identity: identity, password: password, device_type: 'web', device_name: 'Account deletion page' }),
                });
                const loginData = await loginRes.json();
                if (!loginRes.ok || loginData.error) {
                    showError((loginData.error && loginData.error.message) || 'Invalid credentials');
                    return;
                }
                const accessToken = loginData.data.access_token;

                // 2. Delete with password re-entry (server verifies the password again)
                const delRes = await fetch(API_BASE + '/auth/delete-account', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + accessToken },
                    body: JSON.stringify({ password: password }),
                });
                const delData = await delRes.json();
                if (!delRes.ok || delData.error) {
                    showError((delData.error && delData.error.message) || 'Account deletion failed. Please try again.');
                    return;
                }

                form.style.display = 'none';
                document.getElementById('deleteInfo').style.display = 'none';
                document.querySelector('.login-header').style.display = 'none';
                document.querySelector('.login-footer').style.display = 'none';
                document.getElementById('deleteSuccess').style.display = 'block';
            } catch (err) {
                showError('Network error. Please try again.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Delete My Account';
            }
        });
    })();
    </script>
</body>
</html>
