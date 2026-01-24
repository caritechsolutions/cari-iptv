<?php
/**
 * CARI-IPTV Email Service
 * Handles sending emails via SMTP
 */

namespace CariIPTV\Services;

class EmailService
{
    private SettingsService $settings;
    private array $smtpConfig;
    private ?string $lastError = null;

    public function __construct()
    {
        $this->settings = new SettingsService();
        $this->loadSmtpConfig();
    }

    /**
     * Load SMTP configuration from settings
     */
    private function loadSmtpConfig(): void
    {
        $this->smtpConfig = $this->settings->getGroup('smtp');
    }

    /**
     * Check if SMTP is enabled and configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->smtpConfig['enabled']) &&
               !empty($this->smtpConfig['host']) &&
               !empty($this->smtpConfig['from_email']);
    }

    /**
     * Get the last error message
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Send an email
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'SMTP is not configured. Please configure SMTP settings.';
            return false;
        }

        $host = $this->smtpConfig['host'];
        $port = (int) ($this->smtpConfig['port'] ?? 587);
        $encryption = $this->smtpConfig['encryption'] ?? 'tls';
        $username = $this->smtpConfig['username'] ?? '';
        $password = $this->smtpConfig['password'] ?? '';
        $fromEmail = $this->smtpConfig['from_email'];
        $fromName = $this->smtpConfig['from_name'] ?? 'CARI-IPTV';

        try {
            // Create socket connection
            $socket = $this->connect($host, $port, $encryption);

            if (!$socket) {
                return false;
            }

            // SMTP handshake
            if (!$this->smtpHandshake($socket, $host, $username, $password, $encryption)) {
                fclose($socket);
                return false;
            }

            // Send email
            if (!$this->sendEmail($socket, $fromEmail, $fromName, $to, $subject, $body, $isHtml)) {
                fclose($socket);
                return false;
            }

            // Close connection
            $this->smtpCommand($socket, "QUIT");
            fclose($socket);

            return true;

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Connect to SMTP server
     */
    private function connect(string $host, int $port, string $encryption): mixed
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $protocol = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @stream_socket_client(
            "{$protocol}{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $this->lastError = "Failed to connect to SMTP server: {$errstr} ({$errno})";
            return false;
        }

        // Read greeting
        $response = $this->getResponse($socket);
        if (!str_starts_with($response, '220')) {
            $this->lastError = "SMTP server rejected connection: {$response}";
            return false;
        }

        return $socket;
    }

    /**
     * Perform SMTP handshake
     */
    private function smtpHandshake($socket, string $host, string $username, string $password, string $encryption): bool
    {
        // EHLO
        $response = $this->smtpCommand($socket, "EHLO " . gethostname());
        if (!str_starts_with($response, '250')) {
            $this->lastError = "EHLO failed: {$response}";
            return false;
        }

        // STARTTLS if needed
        if ($encryption === 'tls') {
            $response = $this->smtpCommand($socket, "STARTTLS");
            if (!str_starts_with($response, '220')) {
                $this->lastError = "STARTTLS failed: {$response}";
                return false;
            }

            // Enable crypto
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = "Failed to enable TLS encryption";
                return false;
            }

            // EHLO again after STARTTLS
            $response = $this->smtpCommand($socket, "EHLO " . gethostname());
            if (!str_starts_with($response, '250')) {
                $this->lastError = "EHLO after STARTTLS failed: {$response}";
                return false;
            }
        }

        // AUTH LOGIN if credentials provided
        if (!empty($username) && !empty($password)) {
            $response = $this->smtpCommand($socket, "AUTH LOGIN");
            if (!str_starts_with($response, '334')) {
                $this->lastError = "AUTH LOGIN failed: {$response}";
                return false;
            }

            $response = $this->smtpCommand($socket, base64_encode($username));
            if (!str_starts_with($response, '334')) {
                $this->lastError = "Username rejected: {$response}";
                return false;
            }

            $response = $this->smtpCommand($socket, base64_encode($password));
            if (!str_starts_with($response, '235')) {
                $this->lastError = "Authentication failed: {$response}";
                return false;
            }
        }

        return true;
    }

    /**
     * Send the actual email
     */
    private function sendEmail($socket, string $fromEmail, string $fromName, string $to, string $subject, string $body, bool $isHtml): bool
    {
        // MAIL FROM
        $response = $this->smtpCommand($socket, "MAIL FROM:<{$fromEmail}>");
        if (!str_starts_with($response, '250')) {
            $this->lastError = "MAIL FROM failed: {$response}";
            return false;
        }

        // RCPT TO
        $response = $this->smtpCommand($socket, "RCPT TO:<{$to}>");
        if (!str_starts_with($response, '250')) {
            $this->lastError = "RCPT TO failed: {$response}";
            return false;
        }

        // DATA
        $response = $this->smtpCommand($socket, "DATA");
        if (!str_starts_with($response, '354')) {
            $this->lastError = "DATA command failed: {$response}";
            return false;
        }

        // Build email headers and body
        $contentType = $isHtml ? 'text/html' : 'text/plain';

        // Normalize line endings in body to CRLF (required by SMTP RFC 822)
        $body = str_replace(["\r\n", "\r", "\n"], "\r\n", $body);

        $message = "From: {$fromName} <{$fromEmail}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: {$contentType}; charset=UTF-8\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        $message .= "Message-ID: <" . uniqid() . "@" . gethostname() . ">\r\n";
        $message .= "\r\n";
        $message .= $body;
        $message .= "\r\n.\r\n";

        fwrite($socket, $message);
        $response = $this->getResponse($socket);

        if (!str_starts_with($response, '250')) {
            $this->lastError = "Message send failed: {$response}";
            return false;
        }

        return true;
    }

    /**
     * Send SMTP command and get response
     */
    private function smtpCommand($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->getResponse($socket);
    }

    /**
     * Get response from SMTP server
     */
    private function getResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // If 4th character is space, this is the last line
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    /**
     * Send a test email
     */
    public function sendTest(string $to): bool
    {
        $siteName = $this->settings->get('site_name', 'CARI-IPTV', 'general');

        $subject = "{$siteName} - Test Email";
        $body = $this->getEmailTemplate('test', [
            'site_name' => $siteName,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        return $this->send($to, $subject, $body);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset(string $to, string $name, string $resetUrl): bool
    {
        $siteName = $this->settings->get('site_name', 'CARI-IPTV', 'general');

        $subject = "{$siteName} - Password Reset Request";
        $body = $this->getEmailTemplate('password-reset', [
            'site_name' => $siteName,
            'name' => $name,
            'reset_url' => $resetUrl,
            'expiry' => '1 hour',
        ]);

        return $this->send($to, $subject, $body);
    }

    /**
     * Get email template
     */
    private function getEmailTemplate(string $template, array $data = []): string
    {
        $templates = [
            'test' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6366f1; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
        .footer { background: #1e293b; color: #94a3b8; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{site_name}}</h1>
        </div>
        <div class="content">
            <h2>Test Email Successful!</h2>
            <p>This is a test email from your CARI-IPTV system.</p>
            <p>If you received this email, your SMTP settings are configured correctly.</p>
            <p><strong>Sent at:</strong> {{timestamp}}</p>
        </div>
        <div class="footer">
            <p>This email was sent from {{site_name}}</p>
        </div>
    </div>
</body>
</html>',
            'password-reset' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6366f1; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
        .button { display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { background: #1e293b; color: #94a3b8; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 10px; border-radius: 4px; margin-top: 20px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{site_name}}</h1>
        </div>
        <div class="content">
            <h2>Password Reset Request</h2>
            <p>Hello {{name}},</p>
            <p>We received a request to reset your password. Click the button below to create a new password:</p>
            <p style="text-align: center;">
                <a href="{{reset_url}}" class="button">Reset Password</a>
            </p>
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; font-size: 13px; color: #64748b;">{{reset_url}}</p>
            <div class="warning">
                <strong>Note:</strong> This link will expire in {{expiry}}. If you did not request a password reset, you can safely ignore this email.
            </div>
        </div>
        <div class="footer">
            <p>This email was sent from {{site_name}} Admin Panel</p>
        </div>
    </div>
</body>
</html>',
        ];

        $html = $templates[$template] ?? '';

        // Replace placeholders
        foreach ($data as $key => $value) {
            $html = str_replace('{{' . $key . '}}', htmlspecialchars($value), $html);
        }

        return $html;
    }
}
