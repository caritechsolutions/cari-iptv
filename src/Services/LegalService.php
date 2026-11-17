<?php
/**
 * CARI-IPTV Legal pages (Privacy Policy, Terms of Service)
 *
 * The text lives in the settings group "legal" (Admin → Settings → Legal)
 * and is served on the web player at /privacy and /terms, which is where the
 * apps, Google's consent screen and the store listings link to. Each
 * operator maintains their own text; a sensible default is generated from
 * the site name and admin email until they do.
 *
 * Authoring format (plain text, no HTML needed):
 *   # Heading          → section heading
 *   blank line         → new paragraph
 *   - item             → bullet list
 *   [[site]] [[company]] [[email]] [[country]] [[date]] → substituted
 * Raw HTML is accepted too (a line starting with "<" is passed through).
 */

namespace CariIPTV\Services;

class LegalService
{
    private SettingsService $settings;

    public function __construct(?SettingsService $settings = null)
    {
        $this->settings = $settings ?? new SettingsService();
    }

    /** Operator values used for substitution. */
    public function variables(): array
    {
        $legal = $this->settings->getGroup('legal');
        $site = (string) $this->settings->get('site_name', 'CARI-IPTV', 'general');
        return [
            'site' => $site,
            'company' => trim((string) ($legal['company_name'] ?? '')) ?: $site,
            'email' => trim((string) ($legal['contact_email'] ?? '')) ?: (string) $this->settings->get('admin_email', '', 'general'),
            'country' => trim((string) ($legal['country'] ?? '')) ?: 'our country of operation',
            'date' => trim((string) ($legal['updated_at'] ?? '')) ?: date('j F Y'),
        ];
    }

    /** Raw stored text (empty string when the operator has not written one). */
    public function raw(string $which): string
    {
        $legal = $this->settings->getGroup('legal');
        return (string) ($legal[$which === 'terms' ? 'terms_text' : 'privacy_text'] ?? '');
    }

    /** Text to render: the operator's, or the default. */
    public function text(string $which): string
    {
        $raw = $this->raw($which);
        return trim($raw) !== '' ? $raw : self::defaultText($which);
    }

    /** Rendered HTML for the page. */
    public function html(string $which): string
    {
        return $this->render($this->text($which), $this->variables());
    }

    public function render(string $text, array $vars): string
    {
        $text = str_replace("\r", '', $text);
        foreach ($vars as $k => $v) {
            $text = str_replace('[[' . $k . ']]', htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), $text);
        }

        $out = '';
        $para = [];
        $list = [];
        $flushPara = function () use (&$out, &$para) {
            if ($para) {
                $out .= '<p>' . implode('<br>', $para) . "</p>\n";
                $para = [];
            }
        };
        $flushList = function () use (&$out, &$list) {
            if ($list) {
                $out .= "<ul>\n" . implode('', array_map(fn($i) => "<li>{$i}</li>\n", $list)) . "</ul>\n";
                $list = [];
            }
        };

        foreach (explode("\n", $text) as $line) {
            $t = trim($line);
            if ($t === '') {
                $flushPara();
                $flushList();
                continue;
            }
            if ($t[0] === '<') {              // raw HTML line
                $flushPara();
                $flushList();
                $out .= $t . "\n";
                continue;
            }
            if (preg_match('/^(#{1,3})\s+(.*)$/', $t, $m)) {
                $flushPara();
                $flushList();
                $level = strlen($m[1]) + 1; // # → h2, ## → h3
                $id = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $m[2]), '-'));
                $out .= "<h{$level} id=\"" . htmlspecialchars($id) . '">' . $this->inline($m[2]) . "</h{$level}>\n";
                continue;
            }
            if (preg_match('/^[-*]\s+(.*)$/', $t, $m)) {
                $flushPara();
                $list[] = $this->inline($m[1]);
                continue;
            }
            $flushList();
            $para[] = $this->inline($t);
        }
        $flushPara();
        $flushList();
        return $out;
    }

    /** Escape, then allow **bold** and bare links. */
    private function inline(string $s): string
    {
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
        $s = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $s);
        $s = preg_replace('~(?<![\w/])(/[a-z][a-z0-9/-]*)(?=[\s.,;)]|$)~', '<a href="$1">$1</a>', $s);
        return $s;
    }

    // ------------------------------------------------------------------
    // Defaults
    // ------------------------------------------------------------------

    public static function defaultText(string $which): string
    {
        return $which === 'terms' ? self::DEFAULT_TERMS : self::DEFAULT_PRIVACY;
    }

    public const DEFAULT_PRIVACY = <<<'TXT'
# 1. Who we are
[[company]] ("we", "us") operates the [[site]] service, its website and its mobile apps. Questions about this policy: [[email]].

# 2. What we collect
- **Account details:** your name, email address, username and, if you give them, phone number, country and date of birth. If you sign in with Google or Apple we receive your name and email address from them; we never see your Google or Apple password.
- **Viewing activity:** what you watch and for how long, where you paused, titles you add to My List, ratings and searches. This is what makes "Continue Watching" and recommendations work.
- **Playback quality:** buffering, start-up time and error events, so we can keep the service working well.
- **Device and connection:** device type and name, app version, IP address and the approximate country it indicates.
- **Advertising:** which adverts were shown, clicked or completed, linked to your account while you are signed in.

# 3. How we use it
To provide and personalise the service, apply your subscription and device limits, show and measure advertising, keep the service secure, support you when you contact us, and meet our legal obligations. We do not sell your personal data.

# 4. Who we share it with
Service providers who host the platform, deliver video, send our emails and provide analytics, under contracts that limit what they may do with the data. Advertisers receive aggregate figures only, never your identity. We disclose data where the law requires it.

# 5. How long we keep it
Account and viewing data are kept while your account is active. When you delete your account the personal data is removed straight away; subscription and payment records are kept in anonymised form for as long as accounting law requires.

# 6. Deleting your account
You can delete your account at any time:
- In the app: Account → Delete account.
- On the web, without the app: /delete-account
Deletion removes your profile, viewing history, My List, ratings, recommendations and signed-in devices. If you signed in with Apple, we also revoke that sign-in with Apple.

# 7. Your rights
Depending on where you live, you may have the right to access, correct, export or erase your data, or to object to how it is used. Write to [[email]] and we will respond within the time the law allows. If you are in [[country]], you may also complain to the data protection authority there.

# 8. Children
The service is not directed at children under 13. Adult-rated content is hidden unless an account holder enables it with their parental PIN.

# 9. Security
Passwords are stored hashed, all traffic uses HTTPS, and access to personal data is limited to staff who need it.

# 10. Changes
We will post any changes on this page and update the date at the top. Continued use after a change means you accept the updated policy.
TXT;

    public const DEFAULT_TERMS = <<<'TXT'
# 1. The service
[[site]] is provided by [[company]]. By creating an account or using the apps you agree to these terms. If you do not agree, please do not use the service.

# 2. Your account
- You must give accurate details and keep your password private. You are responsible for activity on your account.
- One account is for one household. The number of devices that can stream at the same time depends on your package.
- You must be at least 18 to hold an account, or have a parent or guardian's permission.

# 3. Subscriptions and free content
- Some content is free to all signed-in viewers; the rest requires an active package.
- Paid packages renew for the period shown at purchase until cancelled. Prices include the taxes stated.
- Where a free trial is offered it converts to a paid package at the end of the trial unless cancelled before then.
- Refunds are given where the law requires or as described at the point of purchase.

# 4. Using the service
- The service is for personal, non-commercial viewing. You may not record, redistribute, publicly show or resell any content or stream.
- You may not attempt to bypass geographic, device or package restrictions, reverse-engineer the apps, or interfere with the service.
- We may suspend or close accounts that break these terms or the law.

# 5. Content and availability
Channels, programmes and titles change over time and may differ by country. We aim for continuous service but cannot promise it will be uninterrupted or error-free.

# 6. Advertising
Some content, particularly on free packages, is supported by advertising. Advertisers are responsible for their own adverts.

# 7. Privacy
How we handle your data is described in our Privacy Policy at /privacy.

# 8. Liability
To the extent the law allows, [[company]] is not liable for indirect losses or for loss caused by events outside its reasonable control. Nothing in these terms limits liability that cannot be limited by law.

# 9. Changes to these terms
We may update these terms; the current version is always at /terms. Material changes will be announced in the app or by email.

# 10. Contact and governing law
These terms are governed by the laws of [[country]]. Questions: [[email]].
TXT;
}
