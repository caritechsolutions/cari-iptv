<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Register - CARI-IPTV Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #334155;
            --danger: #ef4444;
            --success: #22c55e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .register-container {
            width: 100%;
            max-width: 520px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .register-logo {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0 auto 0.75rem;
        }

        .register-brand {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .register-brand span {
            color: var(--primary-light);
        }

        .register-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .register-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            animation: fadeIn 0.3s ease;
        }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            word-break: break-word;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .alert svg {
            flex-shrink: 0;
            margin-top: 1px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 0.375rem;
            color: var(--text-secondary);
        }

        .form-label .required {
            color: var(--danger);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 0.875rem;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        select.form-input {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.875rem center;
            padding-right: 2.5rem;
        }

        .form-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .btn-register {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }

        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .register-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .register-footer a {
            color: var(--primary-light);
            text-decoration: none;
        }

        .register-footer a:hover {
            text-decoration: underline;
        }

        .version {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.25rem 0;
            color: var(--text-muted);
            font-size: 0.8125rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .register-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="register-logo">C</div>
            <h1 class="register-brand">CARI<span>-IPTV</span></h1>
            <p class="register-subtitle">Create your account</p>
        </div>

        <div class="register-card">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/register">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            class="form-input"
                            placeholder="John"
                            value="<?= htmlspecialchars($old['first_name'] ?? '') ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            class="form-input"
                            placeholder="Doe"
                            value="<?= htmlspecialchars($old['last_name'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="john@example.com"
                        value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        class="form-input"
                        placeholder="+1 (555) 000-0000"
                        value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                        autocomplete="tel"
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <input
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            class="form-input"
                            value="<?= htmlspecialchars($old['date_of_birth'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="country">Country</label>
                        <select id="country" name="country" class="form-input">
                            <option value="">Select country</option>
                            <?php
                            $countries = [
                                'AG' => 'Antigua and Barbuda', 'BS' => 'Bahamas', 'BB' => 'Barbados',
                                'BZ' => 'Belize', 'BM' => 'Bermuda', 'VG' => 'British Virgin Islands',
                                'KY' => 'Cayman Islands', 'CU' => 'Cuba', 'CW' => 'Curacao',
                                'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'GD' => 'Grenada',
                                'GP' => 'Guadeloupe', 'GY' => 'Guyana', 'HT' => 'Haiti',
                                'JM' => 'Jamaica', 'MQ' => 'Martinique', 'MS' => 'Montserrat',
                                'PR' => 'Puerto Rico', 'KN' => 'Saint Kitts and Nevis',
                                'LC' => 'Saint Lucia', 'VC' => 'Saint Vincent and the Grenadines',
                                'SX' => 'Sint Maarten', 'SR' => 'Suriname',
                                'TT' => 'Trinidad and Tobago', 'TC' => 'Turks and Caicos Islands',
                                'VI' => 'U.S. Virgin Islands', 'AW' => 'Aruba',
                                'AI' => 'Anguilla', 'BQ' => 'Bonaire',
                                '' => '---',
                                'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
                                'MX' => 'Mexico', 'BR' => 'Brazil', 'CO' => 'Colombia',
                                'VE' => 'Venezuela', 'PA' => 'Panama', 'CR' => 'Costa Rica',
                                'GT' => 'Guatemala', 'HN' => 'Honduras', 'NI' => 'Nicaragua',
                                'SV' => 'El Salvador', 'EC' => 'Ecuador', 'PE' => 'Peru',
                                'CL' => 'Chile', 'AR' => 'Argentina', 'UY' => 'Uruguay',
                                'PY' => 'Paraguay', 'BO' => 'Bolivia',
                            ];
                            $selectedCountry = $old['country'] ?? '';
                            foreach ($countries as $code => $name):
                                if ($code === '' && $name === '---'):
                            ?>
                                <option disabled>---</option>
                            <?php else: ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= $selectedCountry === $code ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </select>
                    </div>
                </div>

                <div class="divider">Account Security</div>

                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Minimum 8 characters"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <div class="form-hint">Must be at least 8 characters long</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirm Password <span class="required">*</span></label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        class="form-input"
                        placeholder="Repeat your password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn-register">
                    Create Account
                </button>
            </form>

            <div class="register-footer">
                Already have an account? <a href="/admin/login">Sign in</a>
            </div>
        </div>

        <div class="version">
            CARI-IPTV v1.0.0
        </div>
    </div>
</body>
</html>
