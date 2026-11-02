<?php
/**
 * Google / Apple sign-in buttons for the web player's login and register
 * pages. Included after the form; expects $socialAuth from
 * SocialAuthService::publicConfig(). Renders nothing when neither provider
 * is enabled in Admin → Settings → Sign-in.
 */
$socialAuth = $socialAuth ?? ['google' => ['enabled' => false], 'apple' => ['enabled' => false]];
$googleOn = !empty($socialAuth['google']['enabled']);
$appleOn = !empty($socialAuth['apple']['enabled']);
if (!$googleOn && !$appleOn) return;
?>
<div class="social-signin">
    <div class="social-divider"><span>or continue with</span></div>
    <div id="socialError" class="login-error" style="display:none;"></div>
    <?php if ($googleOn): ?>
        <div id="googleSignIn" class="social-btn-wrap"></div>
    <?php endif; ?>
    <?php if ($appleOn): ?>
        <button type="button" class="social-btn social-btn-apple" id="appleSignIn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.37 12.74c-.02-2.4 1.96-3.55 2.05-3.6-1.12-1.63-2.86-1.86-3.47-1.88-1.48-.15-2.89.87-3.64.87-.75 0-1.9-.85-3.13-.83-1.61.02-3.1.94-3.93 2.38-1.68 2.9-.43 7.2 1.2 9.55.8 1.15 1.75 2.45 3 2.4 1.2-.05 1.66-.78 3.11-.78 1.45 0 1.86.78 3.13.75 1.3-.02 2.12-1.17 2.91-2.33.92-1.34 1.3-2.64 1.32-2.7-.03-.01-2.53-.97-2.55-3.83zM14.0 5.7c.66-.8 1.1-1.9.98-3-.95.04-2.1.63-2.78 1.43-.61.7-1.15 1.83-1 2.9 1.06.08 2.14-.54 2.8-1.33z"/></svg>
            Sign in with Apple
        </button>
    <?php endif; ?>
</div>
<style>
.social-signin { margin-top: 1.25rem; }
.social-divider { display: flex; align-items: center; gap: .75rem; color: rgba(255,255,255,.45); font-size: .8rem; margin-bottom: 1rem; }
.social-divider::before, .social-divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,.12); }
.social-btn-wrap { display: flex; justify-content: center; margin-bottom: .75rem; min-height: 40px; }
.social-btn { width: 100%; display: flex; align-items: center; justify-content: center; gap: .5rem; padding: .7rem 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,.15); background: #fff; color: #000; font: inherit; font-weight: 600; cursor: pointer; }
.social-btn:hover { background: #eee; }
.social-btn[disabled] { opacity: .6; cursor: default; }
</style>
<?php if ($googleOn): ?><script src="https://accounts.google.com/gsi/client" async defer></script><?php endif; ?>
<?php if ($appleOn): ?><script src="https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js" async defer></script><?php endif; ?>
<script>
(function () {
    const API_BASE = '/api/v1';
    const cfg = <?= json_encode($socialAuth, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const errEl = document.getElementById('socialError');

    function fail(msg) { errEl.textContent = msg; errEl.classList.add('visible'); errEl.style.display = 'block'; }

    // Exchange the provider token for our session, exactly as /auth/login does.
    async function finish(provider, idToken, extra) {
        errEl.style.display = 'none';
        try {
            const res = await fetch(API_BASE + '/auth/social', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ provider: provider, id_token: idToken, device_type: 'web' }, extra || {})),
            });
            const data = await res.json();
            if (!res.ok || data.error) { fail(data.error?.message || 'Sign-in failed'); return; }
            const t = data.data;
            localStorage.setItem('access_token', t.access_token);
            localStorage.setItem('refresh_token', t.refresh_token);
            localStorage.setItem('token_expires', Date.now() + (t.expires_in * 1000));
            localStorage.setItem('user', JSON.stringify(t.user));
            localStorage.setItem('remember', '1');
            window.location.href = '/';
        } catch (e) {
            fail('Connection error. Please try again.');
        }
    }

    // ---- Google (Identity Services): renders its own button, hands us an ID token
    if (cfg.google && cfg.google.enabled) {
        const mount = document.getElementById('googleSignIn');
        const init = () => {
            if (!window.google || !google.accounts) { setTimeout(init, 150); return; }
            google.accounts.id.initialize({
                client_id: cfg.google.client_id,
                callback: (resp) => finish('google', resp.credential),
                ux_mode: 'popup',
                auto_select: false,
                itp_support: true,
            });
            google.accounts.id.renderButton(mount, {
                theme: 'outline', size: 'large', shape: 'rectangular', text: 'continue_with', width: Math.min(mount.parentElement.clientWidth, 400),
            });
        };
        init();
    }

    // ---- Apple (AppleID JS): popup flow, returns identity token + one-time code (+ name on first sign-in)
    if (cfg.apple && cfg.apple.enabled) {
        const btn = document.getElementById('appleSignIn');
        btn.addEventListener('click', async () => {
            if (!window.AppleID) { fail('Apple sign-in is still loading, try again'); return; }
            btn.disabled = true;
            try {
                AppleID.auth.init({
                    clientId: cfg.apple.services_id,
                    scope: 'name email',
                    redirectURI: window.location.origin + '/login',
                    usePopup: true,
                });
                const r = await AppleID.auth.signIn();
                const a = r.authorization || {};
                const u = r.user || {};
                await finish('apple', a.id_token, {
                    authorization_code: a.code,
                    first_name: u.name ? u.name.firstName : undefined,
                    last_name: u.name ? u.name.lastName : undefined,
                });
            } catch (e) {
                if (!e || e.error !== 'popup_closed_by_user') fail('Apple sign-in was cancelled or failed');
            } finally {
                btn.disabled = false;
            }
        });
    }
})();
</script>
