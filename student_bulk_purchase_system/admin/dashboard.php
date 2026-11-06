<?php

session_start();

require_once "../config/database.php";

// Check admin login
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Get admin information
|--------------------------------------------------------------------------
*/

$admin_sql = "
    SELECT admin_id, first_name, last_name, role
    FROM admins
    WHERE user_id = ?
    LIMIT 1
";

$admin_stmt = mysqli_prepare($conn, $admin_sql);
mysqli_stmt_bind_param($admin_stmt, "i", $user_id);
mysqli_stmt_execute($admin_stmt);

$admin_result = mysqli_stmt_get_result($admin_stmt);
$admin = mysqli_fetch_assoc($admin_result);

mysqli_stmt_close($admin_stmt);

if (!$admin) {
    die("Administrator account not found.");
}

$admin_id = (int) $admin["admin_id"];


/*
|--------------------------------------------------------------------------
| Dashboard statistics
|--------------------------------------------------------------------------
*/

// Total students
$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM students"
);

$total_students = mysqli_fetch_assoc($result)["total"];


// Total products
$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM products"
);

$total_products = mysqli_fetch_assoc($result)["total"];


// Active bulk purchases
$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM bulk_purchases
    WHERE status IN ('open', 'target_reached')
    "
);

$active_bulk_purchases = mysqli_fetch_assoc($result)["total"];


// Pending payments
$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM payments
    WHERE payment_status = 'pending'
    "
);

$pending_payments = mysqli_fetch_assoc($result)["total"];


// Total participants
$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM bulk_purchase_participants
    WHERE status != 'cancelled'
    "
);

$total_participants = mysqli_fetch_assoc($result)["total"];


// Total verified payment amount
$result = mysqli_query(
    $conn,
    "
    SELECT COALESCE(SUM(amount_paid), 0) AS total
    FROM payments
    WHERE payment_status = 'paid'
    "
);

$total_verified_payments =
    mysqli_fetch_assoc($result)["total"];


/*
|--------------------------------------------------------------------------
| Recent bulk purchases
|--------------------------------------------------------------------------
*/

$bulk_sql = "
    SELECT
        bp.bulk_purchase_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.closing_date,
        bp.status,
        p.name AS product_name,
        p.category
    FROM bulk_purchases bp
    INNER JOIN products p
        ON bp.product_id = p.product_id
    ORDER BY bp.created_at DESC
    LIMIT 5
";

$bulk_result = mysqli_query($conn, $bulk_sql);


/*
|--------------------------------------------------------------------------
| Recent payments
|--------------------------------------------------------------------------
*/

$payment_sql = "
    SELECT
        pay.payment_id,
        pay.amount_paid,
        pay.payment_method,
        pay.payment_status,
        pay.payment_reference,
        pay.created_at,

        s.first_name,
        s.last_name,
        s.matric_number,

        p.name AS product_name

    FROM payments pay

    INNER JOIN bulk_purchase_participants bpp
        ON pay.participant_id = bpp.participant_id

    INNER JOIN students s
        ON bpp.student_id = s.student_id

    INNER JOIN bulk_purchases bp
        ON bpp.bulk_purchase_id = bp.bulk_purchase_id

    INNER JOIN products p
        ON bp.product_id = p.product_id

    ORDER BY pay.created_at DESC
    LIMIT 5
";

$payment_result = mysqli_query($conn, $payment_sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard</title>

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
            background: #f5f7fa;
            color: #333;
        }

        /* Sidebar */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 25px 15px;
            overflow-y: auto;
        }

        .logo {
            text-align: center;
            color: #0b7a3b;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 13px;
            text-decoration: none;
            color: #444;
            padding: 13px 15px;
            margin-bottom: 7px;
            border-radius: 8px;
            transition: 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #eaf7ef;
            color: #0b7a3b;
        }

        .sidebar a i {
            width: 20px;
            text-align: center;
        }

        /* Main */

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .topbar {
            background: #ffffff;
            padding: 22px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #0b7a3b;
        }

        .topbar h1 {
            color: #1f2937;
            font-size: 25px;
        }

        .topbar p {
            color: #777;
            margin-top: 6px;
        }

        .admin-name {
            color: #0b7a3b;
            font-weight: bold;
        }

        /* Statistics */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: #eaf7ef;
            color: #0b7a3b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-card h2 {
            color: #1f2937;
            font-size: 22px;
        }

        .stat-card p {
            color: #777;
            font-size: 13px;
            margin-top: 4px;
        }

        /* Sections */

        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 18px;
            color: #1f2937;
        }

        .view-all {
            color: #1559a6;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        /* Tables */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th {
            background: #f8fafc;
            text-align: left;
            font-size: 12px;
            color: #666;
            padding: 13px;
        }

        td {
            padding: 14px 13px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .product-name {
            font-weight: bold;
            color: #1f2937;
        }

        .small-text {
            display: block;
            color: #777;
            font-size: 11px;
            margin-top: 4px;
        }

        /* Status */

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-open {
            background: #eaf7ef;
            color: #0b7a3b;
        }

        .status-target {
            background: #eaf2ff;
            color: #1559a6;
        }

        .status-pending {
            background: #fff7e6;
            color: #b26a00;
        }

        .status-paid {
            background: #eaf7ef;
            color: #0b7a3b;
        }

        .status-failed {
            background: #fdecec;
            color: #b42318;
        }

        /* Progress */

        .progress {
            width: 100%;
            height: 7px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 7px;
        }

        .progress-fill {
            height: 100%;
            background: #0b7a3b;
        }

        .amount {
            color: #0b7a3b;
            font-weight: bold;
        }

        /* Responsive */

        @media (max-width: 1100px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .section-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                width: 70px;
                padding: 20px 8px;
            }

            .logo {
                font-size: 0;
            }

            .logo::after {
                content: "SB";
                font-size: 18px;
            }

            .sidebar a span {
                display: none;
            }

            .sidebar a {
                justify-content: center;
            }

            .main {
                margin-left: 70px;
                padding: 15px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- Sidebar -->

<div class="sidebar">

    <div class="logo">
        Student Bulk Purchase
    </div>

    <a href="dashboard.php" class="active">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="students.php">
        <i class="fa-solid fa-user-graduate"></i>
        <span>Students</span>
    </a>

    <a href="products.php">
        <i class="fa-solid fa-box"></i>
        <span>Products</span>
    </a>

    <a href="suppliers.php">
        <i class="fa-solid fa-truck-field"></i>
        <span>Suppliers</span>
    </a>

    <a href="bulk_purchases.php">
        <i class="fa-solid fa-users"></i>
        <span>Bulk Purchases</span>
    </a>

    <a href="participants.php">
        <i class="fa-solid fa-user-group"></i>
        <span>Participants</span>
    </a>

    <a href="payments.php">
        <i class="fa-solid fa-credit-card"></i>
        <span>Payments</span>
    </a>

    <a href="orders.php">
        <i class="fa-solid fa-cart-shopping"></i>
        <span>Orders</span>
    </a>

    <a href="inventory.php">
        <i class="fa-solid fa-warehouse"></i>
        <span>Inventory</span>
    </a>

    <a href="notifications.php">
        <i class="fa-solid fa-bell"></i>
        <span>Notifications</span>
    </a>

    <a href="reports.php">
        <i class="fa-solid fa-chart-column"></i>
        <span>Reports</span>
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>

</div>


<!-- Main -->

<div class="main">


    <!-- Topbar -->

    <div class="topbar">

        <h1>
            Admin Dashboard
        </h1>

        <p>
            Welcome,
            <span class="admin-name">
                <?php echo htmlspecialchars(
                    $admin["first_name"] . " " . $admin["last_name"]
                ); ?>
            </span>
        </p>

    </div>


    <!-- Statistics -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-icon">
                <i class="fa-solid fa-user-graduate"></i>
            </div>

            <div>
                <h2>
                    <?php echo $total_students; ?>
                </h2>

                <p>
                    Students
                </p>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                <i class="fa-solid fa-box"></i>
            </div>

            <div>
                <h2>
                    <?php echo $total_products; ?>
                </h2>

                <p>
                    Products
                </p>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>

            <div>
                <h2>
                    <?php echo $active_bulk_purchases; ?>
                </h2>

                <p>
                    Active Bulk Purchases
                </p>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                <i class="fa-solid fa-clock"></i>
            </div>

            <div>
                <h2>
                    <?php echo $pending_payments; ?>
                </h2>

                <p>
                    Pending Payments
                </p>
            </div>

        </div>

    </div>


    <!-- Additional Statistics -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-icon">
                <i class="fa-solid fa-user-group"></i>
            </div>

            <div>
                <h2>
                    <?php echo $total_participants; ?>
                </h2>

                <p>
                    Participants
                </p>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>

            <div>
                <h2>
                    ₦<?php echo number_format(
                        $total_verified_payments,
                        0
                    ); ?>
                </h2>

                <p>
                    Verified Payments
                </p>
            </div>

        </div>

    </div>


    <!-- Tables -->

    <div class="section-grid">


        <!-- Recent Bulk Purchases -->

        <div class="card">

            <div class="card-header">

                <h2>
                    <i class="fa-solid fa-users"></i>
                    Recent Bulk Purchases
                </h2>

                <a
                    href="bulk_purchases.php"
                    class="view-all"
                >
                    View All
                </a>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Progress
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (mysqli_num_rows($bulk_result) > 0): ?>

                            <?php while ($bulk = mysqli_fetch_assoc($bulk_result)): ?>

                                <?php

                                $target =
                                    (int) $bulk["target_quantity"];

                                $current =
                                    (int) $bulk["current_quantity"];

                                $progress =
                                    $target > 0
                                    ? ($current / $target) * 100
                                    : 0;

                                $progress =
                                    min(100, $progress);

                                ?>

                                <tr>

                                    <td>

                                        <span class="product-name">

                                            <?php echo htmlspecialchars(
                                                $bulk["product_name"]
                                            ); ?>

                                        </span>

                                        <span class="small-text">

                                            <?php echo htmlspecialchars(
                                                $bulk["category"]
                                            ); ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php echo $current; ?>
                                        /
                                        <?php echo $target; ?>

                                        <div class="progress">

                                            <div
                                                class="progress-fill"
                                                style="width: <?php echo $progress; ?>%;"
                                            ></div>

                                        </div>

                                    </td>


                                    <td>

                                        <?php if ($bulk["status"] === "open"): ?>

                                            <span class="status status-open">
                                                Open
                                            </span>

                                        <?php elseif (
                                            $bulk["status"] === "target_reached"
                                        ): ?>

                                            <span class="status status-target">
                                                Target Reached
                                            </span>

                                        <?php else: ?>

                                            <span class="status">
                                                <?php echo htmlspecialchars(
                                                    ucwords(
                                                        str_replace(
                                                            "_",
                                                            " ",
                                                            $bulk["status"]
                                                        )
                                                    )
                                                ); ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="3">
                                    No bulk purchases found.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- Recent Payments -->

        <div class="card">

            <div class="card-header">

                <h2>
                    <i class="fa-solid fa-credit-card"></i>
                    Recent Payments
                </h2>

                <a
                    href="payments.php"
                    class="view-all"
                >
                    View All
                </a>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (mysqli_num_rows($payment_result) > 0): ?>

                            <?php while (
                                $payment =
                                mysqli_fetch_assoc($payment_result)
                            ): ?>

                                <tr>

                                    <td>

                                        <span class="product-name">

                                            <?php echo htmlspecialchars(
                                                $payment["first_name"] .
                                                " " .
                                                $payment["last_name"]
                                            ); ?>

                                        </span>

                                        <span class="small-text">

                                            <?php echo htmlspecialchars(
                                                $payment["product_name"]
                                            ); ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="amount">

                                            ₦<?php echo number_format(
                                                $payment["amount_paid"],
                                                2
                                            ); ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php if (
                                            $payment["payment_status"]
                                            === "pending"
                                        ): ?>

                                            <span
                                                class="status status-pending"
                                            >
                                                Pending
                                            </span>

                                        <?php elseif (
                                            $payment["payment_status"]
                                            === "paid"
                                        ): ?>

                                            <span
                                                class="status status-paid"
                                            >
                                                Paid
                                            </span>

                                        <?php elseif (
                                            $payment["payment_status"]
                                            === "failed"
                                        ): ?>

                                            <span
                                                class="status status-failed"
                                            >
                                                Failed
                                            </span>

                                        <?php else: ?>

                                            <span class="status">
                                                <?php echo htmlspecialchars(
                                                    ucfirst(
                                                        $payment[
                                                            "payment_status"
                                                        ]
                                                    )
                                                ); ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="3">
                                    No payments found.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>

<?php

mysqli_close($conn);

?>