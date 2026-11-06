<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>

    <style>
        :root {
            --green: #2e7d32;
            --green-dark: #1b5e20;
            --blue: #1976d2;
            --blue-dark: #0d47a1;
            --blue-soft: rgba(25, 118, 210, 0.16);
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 24px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--white);
            color: var(--blue-dark);
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }

        .container {
            width: min(100%, 520px);
            padding: 34px;
            border: 1px solid var(--blue-soft);
            border-radius: 14px;
            background: var(--white);
            box-shadow: 0 12px 30px rgba(25, 118, 210, 0.12);
        }

        h2 {
            margin: 0 0 8px;
            color: var(--green-dark);
            font-size: 1.75rem;
            text-align: center;
        }

        .form-intro {
            margin: 0 0 26px;
            color: var(--blue);
            font-size: 0.95rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: var(--blue-dark);
            font-size: 0.94rem;
            font-weight: 700;
        }

        input,
        select {
            width: 100%;
            min-height: 46px;
            padding: 10px 12px;
            border: 1px solid var(--blue-soft);
            border-radius: 8px;
            outline: none;
            background: var(--white);
            color: var(--blue-dark);
            font: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input::placeholder {
            color: var(--blue);
            opacity: 0.72;
        }

        input:focus,
        select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-soft);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 6px;
            width: 38px;
            height: 38px;
            padding: 0;
            border: 0;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transform: translateY(-50%);
            background: transparent;
            color: var(--blue);
            cursor: pointer;
        }

        .password-toggle:hover,
        .password-toggle:focus-visible {
            background: var(--blue-soft);
            color: var(--blue-dark);
            outline: none;
        }

        .password-toggle svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .password-toggle .eye-off {
            display: none;
        }

        .password-toggle.is-visible .eye {
            display: none;
        }

        .password-toggle.is-visible .eye-off {
            display: block;
        }

        .submit-button {
            width: 100%;
            min-height: 48px;
            margin-top: 8px;
            border: 0;
            border-radius: 8px;
            background: var(--green);
            color: var(--white);
            font: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .submit-button:hover,
        .submit-button:focus-visible {
            background: var(--green-dark);
            outline: none;
        }

        .submit-button:active {
            transform: translateY(1px);
        }

        .login-link {
            margin: 22px 0 0;
            color: var(--blue);
            font-size: 0.94rem;
            text-align: center;
        }

        .login-link a {
            color: var(--blue);
            font-weight: 700;
            text-decoration: none;
        }

        .login-link a:hover,
        .login-link a:focus-visible {
            color: var(--blue-dark);
            text-decoration: underline;
            outline: none;
        }

        @media (max-width: 560px) {
            body {
                padding: 16px 12px;
                align-items: flex-start;
            }

            .container {
                padding: 26px 20px;
            }

            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <main class="container">
        <h2>Student Registration</h2>
        <p class="form-intro">Create your account to start using the bulk purchasing system.</p>

        <form action="register_process.php" method="POST">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>

            <div class="form-group">
                <label for="matric_number">Matric Number</label>
                <input type="text" id="matric_number" name="matric_number" required>
            </div>

            <div class="form-group">
                <label for="department">Department</label>
                <input type="text" id="department" name="department" required>
            </div>

            <div class="form-group">
                <label for="level">Level</label>
                <select id="level" name="level" required>
                    <option value="">Select Level</option>
                    <option value="100">100 Level</option>
                    <option value="200">200 Level</option>
                    <option value="300">300 Level</option>
                    <option value="400">400 Level</option>
                    <option value="500">500 Level</option>
                    <option value="600">600 Level</option>
                </select>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="text" id="phone_number" name="phone_number" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" title="Show password" data-show-label="Show password" data-hide-label="Hide password">
                        <svg class="eye" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                            <circle cx="12" cy="12" r="2.5"></circle>
                        </svg>
                        <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18"></path>
                            <path d="M10.6 5.1A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a17.4 17.4 0 0 1-2.1 2.8M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.6-.8"></path>
                            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" aria-label="Show confirm password" aria-pressed="false" title="Show confirm password" data-show-label="Show confirm password" data-hide-label="Hide confirm password">
                        <svg class="eye" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                            <circle cx="12" cy="12" r="2.5"></circle>
                        </svg>
                        <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18"></path>
                            <path d="M10.6 5.1A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a17.4 17.4 0 0 1-2.1 2.8M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.6-.8"></path>
                            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-button">Register</button>
        </form>

        <p class="login-link">
            Already have an account?
            <a href="login.php">Login</a>
        </p>
    </main>

    <script>
        document.querySelectorAll('.password-toggle').forEach(function (toggleButton) {
            var passwordInput = toggleButton.closest('.password-wrapper').querySelector('input');

            toggleButton.addEventListener('click', function () {
                var isPasswordVisible = passwordInput.type === 'text';
                var willBeVisible = !isPasswordVisible;

                passwordInput.type = willBeVisible ? 'text' : 'password';
                toggleButton.classList.toggle('is-visible', willBeVisible);
                toggleButton.setAttribute('aria-pressed', String(willBeVisible));
                toggleButton.setAttribute(
                    'aria-label',
                    willBeVisible ? toggleButton.dataset.hideLabel : toggleButton.dataset.showLabel
                );
                toggleButton.title = willBeVisible ? toggleButton.dataset.hideLabel : toggleButton.dataset.showLabel;
            });
        });
    </script>
</body>
</html>