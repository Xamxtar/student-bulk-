<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Login</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            width: 400px;
            max-width: 90%;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #0066cc;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #333;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #0066cc;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .password-box {
            position: relative;
        }

        .password-box input {
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #0066cc;
            cursor: pointer;
            font-size: 14px;
            padding: 0;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background: #008000;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .login-btn:hover {
            background: #006600;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #555;
        }

        .register-link a {
            color: #0066cc;
            text-decoration: none;
            font-weight: bold;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="login-container">

    <h2>Student Login</h2>

    <p class="subtitle">
        Student Bulk Purchasing Management System
    </p>

    <form action="authenticate.php" method="POST">

        <div class="form-group">
            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                required
            >
        </div>

        <div class="form-group">

            <label for="password">Password</label>

            <div class="password-box">

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword()"
                    id="toggleButton"
                >
                    Show
                </button>

            </div>

        </div>

        <button type="submit" class="login-btn">
            Login
        </button>

    </form>

    <div class="register-link">
        Don't have an account?
        <a href="register.php">Register</a>
    </div>

</div>

<script>

function togglePassword() {

    const password = document.getElementById("password");
    const toggleButton = document.getElementById("toggleButton");

    if (password.type === "password") {

        password.type = "text";
        toggleButton.textContent = "Hide";

    } else {

        password.type = "password";
        toggleButton.textContent = "Show";

    }

}

</script>

</body>
</html>