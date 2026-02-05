<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title>Create Account - <?= htmlspecialchars($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fonts/lucide/lucide.css">
    <link rel="stylesheet" href="/assets/css/player.css">
</head>
<body>
    <div class="login-page">
        <div class="login-bg"></div>
        <div class="login-container register-container">
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
                    <h1 class="login-title">Create your account</h1>
                    <p class="login-subtitle">Start watching your favourite content</p>
                </div>

                <div id="registerError" class="login-error"></div>

                <form id="registerForm" class="login-form" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name <span class="form-required">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="form-input"
                                   placeholder="John" autocomplete="given-name" required autofocus>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name <span class="form-required">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="form-input"
                                   placeholder="Doe" autocomplete="family-name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address <span class="form-required">*</span></label>
                        <input type="email" id="email" name="email" class="form-input"
                               placeholder="john@example.com" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-input"
                               placeholder="+1 (555) 000-0000" autocomplete="tel">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="birthday">Date of Birth</label>
                            <input type="date" id="birthday" name="birthday" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="country">Country</label>
                            <select id="country" name="country" class="form-input form-select">
                                <option value="">Select country</option>
                                <option value="AG">Antigua and Barbuda</option>
                                <option value="AI">Anguilla</option>
                                <option value="AW">Aruba</option>
                                <option value="BS">Bahamas</option>
                                <option value="BB">Barbados</option>
                                <option value="BZ">Belize</option>
                                <option value="BM">Bermuda</option>
                                <option value="BQ">Bonaire</option>
                                <option value="VG">British Virgin Islands</option>
                                <option value="KY">Cayman Islands</option>
                                <option value="CU">Cuba</option>
                                <option value="CW">Curacao</option>
                                <option value="DM">Dominica</option>
                                <option value="DO">Dominican Republic</option>
                                <option value="GD">Grenada</option>
                                <option value="GP">Guadeloupe</option>
                                <option value="GY">Guyana</option>
                                <option value="HT">Haiti</option>
                                <option value="JM">Jamaica</option>
                                <option value="MQ">Martinique</option>
                                <option value="MS">Montserrat</option>
                                <option value="PR">Puerto Rico</option>
                                <option value="KN">Saint Kitts and Nevis</option>
                                <option value="LC">Saint Lucia</option>
                                <option value="VC">Saint Vincent and the Grenadines</option>
                                <option value="SX">Sint Maarten</option>
                                <option value="SR">Suriname</option>
                                <option value="TT">Trinidad and Tobago</option>
                                <option value="TC">Turks and Caicos Islands</option>
                                <option value="VI">U.S. Virgin Islands</option>
                                <option disabled>---</option>
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                                <option value="GB">United Kingdom</option>
                                <option value="MX">Mexico</option>
                                <option value="BR">Brazil</option>
                                <option value="CO">Colombia</option>
                                <option value="VE">Venezuela</option>
                                <option value="PA">Panama</option>
                                <option value="CR">Costa Rica</option>
                                <option value="GT">Guatemala</option>
                                <option value="HN">Honduras</option>
                                <option value="NI">Nicaragua</option>
                                <option value="SV">El Salvador</option>
                                <option value="EC">Ecuador</option>
                                <option value="PE">Peru</option>
                                <option value="CL">Chile</option>
                                <option value="AR">Argentina</option>
                                <option value="UY">Uruguay</option>
                                <option value="PY">Paraguay</option>
                                <option value="BO">Bolivia</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-divider">Account Security</div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password <span class="form-required">*</span></label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-input"
                                   placeholder="Minimum 8 characters" autocomplete="new-password" required minlength="8">
                            <button type="button" class="input-toggle" id="togglePassword" tabindex="-1">
                                <i class="lucide-eye"></i>
                            </button>
                        </div>
                        <div class="form-hint">Must be at least 8 characters long</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Confirm Password <span class="form-required">*</span></label>
                        <div class="input-group">
                            <input type="password" id="password_confirm" name="password_confirm" class="form-input"
                                   placeholder="Repeat your password" autocomplete="new-password" required minlength="8">
                            <button type="button" class="input-toggle" id="togglePasswordConfirm" tabindex="-1">
                                <i class="lucide-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="login-btn" id="registerBtn">
                        Create Account
                    </button>
                </form>

                <div class="login-footer">
                    Already have an account? <a href="/login" class="form-link">Sign in</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const API_BASE = '/api/v1';
        const form = document.getElementById('registerForm');
        const btn = document.getElementById('registerBtn');
        const errorEl = document.getElementById('registerError');

        // Toggle password visibility
        function setupToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            if (toggle && input) {
                toggle.addEventListener('click', function() {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    this.querySelector('i').className = isPassword ? 'lucide-eye-off' : 'lucide-eye';
                });
            }
        }
        setupToggle('togglePassword', 'password');
        setupToggle('togglePasswordConfirm', 'password_confirm');

        // Handle form submit
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const birthday = document.getElementById('birthday').value;
            const country = document.getElementById('country').value;
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            // Client-side validation
            if (!firstName || !lastName) {
                showError('Please enter your first and last name');
                return;
            }
            if (!email) {
                showError('Please enter your email address');
                return;
            }
            if (password.length < 8) {
                showError('Password must be at least 8 characters');
                return;
            }
            if (password !== passwordConfirm) {
                showError('Passwords do not match');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="login-spinner"></span> Creating account...';
            hideError();

            try {
                const res = await fetch(API_BASE + '/auth/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        first_name: firstName,
                        last_name: lastName,
                        email: email,
                        phone: phone,
                        birthday: birthday,
                        country: country,
                        password: password,
                        password_confirm: passwordConfirm,
                        device_type: 'web',
                    }),
                });

                const data = await res.json();

                if (!res.ok || data.error) {
                    showError(data.error?.message || 'Registration failed. Please try again.');
                    btn.disabled = false;
                    btn.textContent = 'Create Account';
                    return;
                }

                // Store tokens (auto-login)
                const tokenData = data.data;
                localStorage.setItem('access_token', tokenData.access_token);
                localStorage.setItem('refresh_token', tokenData.refresh_token);
                localStorage.setItem('token_expires', Date.now() + (tokenData.expires_in * 1000));
                localStorage.setItem('user', JSON.stringify(tokenData.user));

                // Redirect to app
                window.location.href = '/';
            } catch (err) {
                showError('Connection error. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Create Account';
            }
        });

        function showError(msg) {
            errorEl.textContent = msg;
            errorEl.classList.add('visible');
            errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function hideError() {
            errorEl.classList.remove('visible');
        }

        // If already authenticated, redirect to app
        if (localStorage.getItem('access_token')) {
            window.location.href = '/';
        }
    })();
    </script>
</body>
</html>
