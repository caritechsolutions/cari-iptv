<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title>Forgot Password - <?= htmlspecialchars($siteName) ?></title>
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
                    <h1 class="login-title">Forgot your password?</h1>
                    <p class="login-subtitle">Enter your email and we will send you a reset link</p>
                </div>

                <div id="forgotError" class="login-error"></div>

                <div id="forgotSent" style="display: none; text-align: center;">
                    <div class="verify-icon verify-icon-success"><i class="lucide-mail"></i></div>
                    <h2 class="login-title">Check your email</h2>
                    <p id="forgotSentMsg" style="color: var(--p-text-secondary); font-size: 0.9375rem; margin-bottom: 0.75rem;"></p>
                    <p style="color: var(--p-text-secondary); font-size: 0.875rem; margin-bottom: 2rem;">The link expires after 1 hour.</p>
                    <a href="/login" class="login-btn" style="display: inline-block; text-decoration: none; text-align: center;">Back to Sign In</a>
                </div>

                <form id="forgotForm" class="login-form" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-input"
                               placeholder="you@example.com" autocomplete="email" required autofocus>
                    </div>
                    <button type="submit" class="login-btn" id="forgotBtn">Send Reset Link</button>
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
        const form = document.getElementById('forgotForm');
        const btn = document.getElementById('forgotBtn');
        const errorEl = document.getElementById('forgotError');

        function showError(msg) { errorEl.textContent = msg; errorEl.classList.add('visible'); errorEl.style.display = 'block'; }
        function hideError() { errorEl.classList.remove('visible'); errorEl.style.display = 'none'; }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError();
            const email = document.getElementById('email').value.trim();
            if (!email || email.indexOf('@') < 0) { showError('Please enter a valid email address'); return; }
            btn.disabled = true;
            btn.innerHTML = '<span class="login-spinner"></span> Sending...';
            try {
                const res = await fetch(API_BASE + '/auth/forgot-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email }),
                });
                const data = await res.json();
                if (!res.ok || data.error) {
                    showError((data.error && data.error.message) || 'Request failed. Please try again.');
                    return;
                }
                form.style.display = 'none';
                document.querySelector('.login-header').style.display = 'none';
                document.querySelector('.login-footer').style.display = 'none';
                document.getElementById('forgotSentMsg').textContent = (data.data && data.data.message) || 'If an account exists with that email, password reset instructions have been sent.';
                document.getElementById('forgotSent').style.display = 'block';
            } catch (err) {
                showError('Network error. Please try again.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Send Reset Link';
            }
        });
    })();
    </script>
</body>
</html>
