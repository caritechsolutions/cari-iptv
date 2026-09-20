<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title>Privacy Policy - <?= htmlspecialchars($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/player.css">
    <style>
        .legal-page { max-width: 760px; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; color: var(--p-text, #e2e8f0); line-height: 1.7; font-size: 0.95rem; }
        .legal-page h1 { font-size: 1.75rem; margin-bottom: 0.25rem; }
        .legal-page h2 { font-size: 1.15rem; margin: 2rem 0 0.5rem; color: var(--p-text, #fff); }
        .legal-page p, .legal-page li { color: var(--p-text-secondary, #94a3b8); }
        .legal-page ul { padding-left: 1.25rem; }
        .legal-page .placeholder { border-left: 3px solid #f59e0b; background: rgba(245,158,11,0.08); padding: 0.75rem 1rem; margin: 1rem 0; border-radius: 6px; color: #fbbf24; font-size: 0.85rem; }
        .legal-page a { color: var(--p-primary, #6366f1); }
        .legal-page .updated { font-size: 0.8rem; color: var(--p-text-secondary, #94a3b8); }
    </style>
</head>
<body>
    <div class="login-page" style="align-items: flex-start;">
        <div class="login-bg"></div>
        <div class="legal-page" style="position: relative; z-index: 1;">
            <div class="login-logo" style="margin-bottom: 1.5rem;">
                <?php if (!empty($siteLogo)): ?>
                    <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="login-logo-img">
                <?php else: ?>
                    <div class="login-logo-icon"><?= strtoupper(substr($siteName, 0, 1)) ?></div>
                <?php endif; ?>
                <span class="login-logo-text"><?= htmlspecialchars($siteName) ?></span>
            </div>

            <h1>Privacy Policy</h1>
            <p class="updated">Last updated: [DATE]</p>

            <div class="placeholder">
                PLACEHOLDER — replace every bracketed item and review all sections with your legal adviser before publishing.
                This page is served at <code>/privacy</code> and is the URL to enter in Google Play Console and App Store Connect.
                Edit <code>templates/player/privacy.php</code>.
            </div>

            <h2>1. Who we are</h2>
            <p>[COMPANY LEGAL NAME] ("we", "us") operates the <?= htmlspecialchars($siteName) ?> service and mobile apps. Contact: [CONTACT EMAIL], [POSTAL ADDRESS].</p>

            <h2>2. Data we collect</h2>
            <ul>
                <li><strong>Account data:</strong> name, email address, username, password (stored hashed), optional phone number, country and date of birth.</li>
                <li><strong>Usage data:</strong> what you watch and for how long, playback position, items you add to My List, ratings, searches and pages viewed. Used to provide continue-watching and recommendations.</li>
                <li><strong>Playback quality data:</strong> buffering, start-up time and error events, used to monitor service quality.</li>
                <li><strong>Device and connection data:</strong> device name and type, app version, IP address and approximate country derived from it.</li>
                <li><strong>Advertising data:</strong> ad impressions, clicks and completions, linked to your account where you are signed in.</li>
            </ul>

            <h2>3. How we use it</h2>
            <p>To deliver the service, personalise content, enforce your subscription and device limits, show and measure advertising, keep the service secure, and comply with law. [ADD ANY OTHER PURPOSES]</p>

            <h2>4. Sharing</h2>
            <p>[LIST PROCESSORS: hosting provider, email delivery provider, video delivery/CDN, analytics, advertisers.] We do not sell personal data. [CONFIRM]</p>

            <h2>5. Retention</h2>
            <p>Account and usage data are kept while your account is active. When you delete your account (see below), personal data is removed immediately; anonymised subscription and billing records are retained for [N] years for accounting and legal obligations.</p>

            <h2 id="deletion">6. Deleting your account and data</h2>
            <p>You can delete your account at any time:</p>
            <ul>
                <li>In the app: Settings → Delete account (you will be asked for your password).</li>
                <li>On the web, without the app: <a href="/delete-account">/delete-account</a>.</li>
            </ul>
            <p>Deletion removes your profile details, watch history, My List, ratings, recommendations and signed-in devices. Subscription and billing records are kept in anonymised form (not linked to your name or email).</p>

            <h2>7. Your rights</h2>
            <p>Depending on where you live you may have rights to access, correct, export or erase your data, or object to processing. Contact [CONTACT EMAIL]. [ADD SUPERVISORY AUTHORITY / JURISDICTION DETAILS]</p>

            <h2>8. Children</h2>
            <p>The service is not directed at children under [AGE]. Parental controls are available in the app. [CONFIRM AGE AND POLICY]</p>

            <h2>9. Security</h2>
            <p>Passwords are stored hashed. Data is transmitted over HTTPS. [DESCRIBE OTHER MEASURES]</p>

            <h2>10. Changes</h2>
            <p>We will post changes on this page and update the date above.</p>

            <h2 id="terms">Terms of Service</h2>
            <div class="placeholder">PLACEHOLDER — insert your terms of service here or link to a separate page. The mobile app links to <code>/privacy#terms</code>.</div>
            <p>[TERMS OF SERVICE TEXT]</p>
        </div>
    </div>
</body>
</html>
