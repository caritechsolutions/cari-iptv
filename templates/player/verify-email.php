<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title>Verify Email - <?= htmlspecialchars($siteName) ?></title>
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
            <div class="login-card" style="text-align: center;">
                <div class="login-header">
                    <div class="login-logo">
                        <?php if (!empty($siteLogo)): ?>
                            <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="login-logo-img">
                        <?php else: ?>
                            <div class="login-logo-icon"><?= strtoupper(substr($siteName, 0, 1)) ?></div>
                        <?php endif; ?>
                        <span class="login-logo-text"><?= htmlspecialchars($siteName) ?></span>
                    </div>
                </div>

                <div id="verifyLoading">
                    <div style="margin: 2rem 0;">
                        <div class="login-spinner" style="width: 32px; height: 32px; border-width: 3px; border-color: rgba(99,102,241,0.3); border-top-color: var(--p-primary);"></div>
                    </div>
                    <p style="color: var(--p-text-secondary); font-size: 0.9375rem;">Verifying your email address...</p>
                </div>

                <div id="verifySuccess" style="display: none;">
                    <div class="verify-icon verify-icon-success">
                        <i class="lucide-check"></i>
                    </div>
                    <h2 class="login-title">Email Verified</h2>
                    <p style="color: var(--p-text-secondary); font-size: 0.9375rem; margin-bottom: 2rem;">Your email has been verified. You can now sign in to your account.</p>
                    <a href="/login" class="login-btn" style="display: inline-block; text-decoration: none; text-align: center;">Sign In</a>
                </div>

                <div id="verifyError" style="display: none;">
                    <div class="verify-icon verify-icon-error">
                        <i class="lucide-x"></i>
                    </div>
                    <h2 class="login-title">Verification Failed</h2>
                    <p id="verifyErrorMsg" style="color: var(--p-text-secondary); font-size: 0.9375rem; margin-bottom: 2rem;">Invalid or expired verification link.</p>
                    <a href="/login" class="login-btn" style="display: inline-block; text-decoration: none; text-align: center;">Go to Sign In</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const token = <?= json_encode($token) ?>;

        async function verify() {
            try {
                const res = await fetch('/api/v1/auth/verify-email/' + encodeURIComponent(token));
                const data = await res.json();

                document.getElementById('verifyLoading').style.display = 'none';

                if (res.ok && data.data) {
                    document.getElementById('verifySuccess').style.display = 'block';
                } else {
                    document.getElementById('verifyErrorMsg').textContent =
                        data.error?.message || 'Invalid or expired verification link.';
                    document.getElementById('verifyError').style.display = 'block';
                }
            } catch (err) {
                document.getElementById('verifyLoading').style.display = 'none';
                document.getElementById('verifyError').style.display = 'block';
            }
        }

        verify();
    })();
    </script>
</body>
</html>
