```php
<?php

session_start();

require_once "../config/database.php";

// Make sure the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// Make sure the logged-in user is a student
if ($_SESSION["user_type"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Get student information
$query = mysqli_prepare(
    $conn,
    "SELECT first_name, last_name, matric_number, department, level
     FROM students
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($query, "i", $user_id);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$student = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard</title>

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f7f9;
            color: #333;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e5e5;
            padding: 20px 15px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            margin-bottom: 30px;
            color: #0066cc;
        }

        .logo i {
            font-size: 24px;
            color: #008000;
        }

        .logo h2 {
            font-size: 18px;
        }

        .menu-title {
            font-size: 12px;
            color: #888;
            padding: 0 10px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 12px;
            margin-bottom: 5px;
            text-decoration: none;
            color: #555;
            border-radius: 7px;
            transition: 0.2s;
        }

        .sidebar a i {
            width: 20px;
            text-align: center;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #eaf4ff;
            color: #0066cc;
        }

        .sidebar .logout {
            position: absolute;
            bottom: 20px;
            left: 15px;
            right: 15px;
            color: #d9534f;
        }

        .sidebar .logout:hover {
            background: #fff1f1;
            color: #c9302c;
        }

        /* MAIN CONTENT */

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* TOP BAR */

        .topbar {
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .topbar h3 {
            color: #333;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-icon {
            width: 40px;
            height: 40px;
            background: #eaf4ff;
            color: #0066cc;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* CONTENT */

        .content {
            padding: 30px;
        }

        .welcome {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #008000;
        }

        .welcome h1 {
            color: #0066cc;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .welcome p {
            color: #666;
        }

        /* CARDS */

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: #ffffff;
            padding: 22px;
            border-radius: 10px;
            border: 1px solid #e8e8e8;
        }

        .card-icon {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .blue {
            background: #eaf4ff;
            color: #0066cc;
        }

        .green {
            background: #eaf8ea;
            color: #008000;
        }

        .card h3 {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .card p {
            color: #777;
            font-size: 14px;
            line-height: 1.5;
        }

        /* INFORMATION */

        .information {
            background: #ffffff;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid #e8e8e8;
        }

        .information-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .information-header h2 {
            color: #0066cc;
            font-size: 19px;
        }

        .information-header i {
            color: #008000;
        }

        .info-row {
            display: flex;
            padding: 13px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 180px;
            color: #777;
            font-weight: bold;
        }

        .info-value {
            color: #333;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                width: 70px;
                padding: 15px 10px;
            }

            .logo h2,
            .menu-title,
            .sidebar a span {
                display: none;
            }

            .logo {
                justify-content: center;
            }

            .sidebar a {
                justify-content: center;
            }

            .sidebar .logout {
                left: 10px;
                right: 10px;
            }

            .main {
                margin-left: 70px;
            }

            .topbar {
                padding: 0 15px;
            }

            .content {
                padding: 20px 15px;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .info-row {
                flex-direction: column;
                gap: 5px;
            }

            .info-label {
                width: auto;
            }

        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">

            <i class="fa-solid fa-cart-shopping"></i>

            <h2>Student Purchase</h2>

        </div>

        <div class="menu-title">
            Main Menu
        </div>

        <a href="dashboard.php" class="active">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="bulk_purchases.php">
            <i class="fa-solid fa-cart-shopping"></i>
            <span>Bulk Purchases</span>
        </a>

        <a href="my_participations.php">
            <i class="fa-solid fa-users"></i>
            <span>My Purchases</span>
        </a>

        <a href="payment_history.php">
            <i class="fa-solid fa-credit-card"></i>
            <span>Payments</span>
        </a>

        <a href="notifications.php">
            <i class="fa-solid fa-bell"></i>
            <span>Notifications</span>
        </a>

        <div class="menu-title" style="margin-top: 25px;">
            Account
        </div>

        <a href="profile.php">
            <i class="fa-solid fa-user"></i>
            <span>My Profile</span>
        </a>

        <a href="../auth/logout.php" class="logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>

    </aside>


    <!-- MAIN -->

    <main class="main">

        <!-- TOP BAR -->

        <header class="topbar">

            <h3>Dashboard</h3>

            <div class="user-info">

                <span>
                    <?php
                    echo htmlspecialchars(
                        $student["first_name"] . " " . $student["last_name"]
                    );
                    ?>
                </span>

                <div class="user-icon">
                    <i class="fa-solid fa-user"></i>
                </div>

            </div>

        </header>


        <!-- CONTENT -->

        <section class="content">

            <!-- WELCOME -->

            <div class="welcome">

                <h1>
                    Welcome,
                    <?php echo htmlspecialchars($student["first_name"]); ?>
                </h1>

                <p>
                    Manage your bulk purchases, payments and orders
                    from your student dashboard.
                </p>

            </div>


            <!-- DASHBOARD CARDS -->

            <div class="cards">

                <div class="card">

                    <div class="card-icon blue">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>

                    <h3>Bulk Purchases</h3>

                    <p>
                        Browse and join available group purchases.
                    </p>

                </div>


                <div class="card">

                    <div class="card-icon green">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <h3>My Purchases</h3>

                    <p>
                        View the group purchases you have joined.
                    </p>

                </div>


                <div class="card">

                    <div class="card-icon blue">
                        <i class="fa-solid fa-credit-card"></i>
                    </div>

                    <h3>Payments</h3>

                    <p>
                        Manage and view your payment information.
                    </p>

                </div>


                <div class="card">

                    <div class="card-icon green">
                        <i class="fa-solid fa-bell"></i>
                    </div>

                    <h3>Notifications</h3>

                    <p>
                        View updates about your purchases and payments.
                    </p>

                </div>

            </div>


            <!-- STUDENT INFORMATION -->

            <div class="information">

                <div class="information-header">

                    <h2>
                        <i class="fa-solid fa-user"></i>
                        Student Information
                    </h2>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Full Name
                    </div>

                    <div class="info-value">
                        <?php
                        echo htmlspecialchars(
                            $student["first_name"] . " " .
                            $student["last_name"]
                        );
                        ?>
                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Matric Number
                    </div>

                    <div class="info-value">
                        <?php
                        echo htmlspecialchars(
                            $student["matric_number"]
                        );
                        ?>
                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Department
                    </div>

                    <div class="info-value">
                        <?php
                        echo htmlspecialchars(
                            $student["department"]
                        );
                        ?>
                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Level
                    </div>

                    <div class="info-value">
                        <?php
                        echo htmlspecialchars(
                            $student["level"]
                        );
                        ?>
                    </div>

                </div>

            </div>

        </section>

    </main>

</body>

</html>
```
