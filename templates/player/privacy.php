<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title><?= ($which ?? 'privacy') === 'terms' ? 'Terms of Service' : 'Privacy Policy' ?> - <?= htmlspecialchars($siteName) ?></title>
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

            <?php $which = $which ?? 'privacy'; $other = $which === 'privacy' ? 'terms' : 'privacy'; ?>
            <h1><?= $which === 'terms' ? 'Terms of Service' : 'Privacy Policy' ?></h1>
            <p class="updated">Last updated: <?= htmlspecialchars($legalVars['date']) ?> &middot;
                <a href="/<?= $other ?>"><?= $other === 'terms' ? 'Terms of Service' : 'Privacy Policy' ?></a></p>

            <?= $legalHtml ?>

            <?php if ($which === 'privacy'): ?>
                <h2 id="terms">Terms of Service</h2>
                <p>Our terms are published at <a href="/terms">/terms</a>.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
