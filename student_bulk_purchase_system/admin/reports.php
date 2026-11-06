<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

$admin_user_id = $_SESSION["user_id"];


/* =========================
   GET ADMIN DETAILS
========================= */

$admin_stmt = $conn->prepare("
    SELECT first_name, last_name
    FROM admins
    WHERE user_id = ?
    LIMIT 1
");

$admin_stmt->bind_param("i", $admin_user_id);
$admin_stmt->execute();

$admin_result = $admin_stmt->get_result();
$admin = $admin_result->fetch_assoc();

$admin_name = $admin
    ? $admin["first_name"] . " " . $admin["last_name"]
    : "Administrator";


/* =========================
   GENERAL STATISTICS
========================= */

function getCount($conn, $sql)
{
    $result = $conn->query($sql);

    if ($result) {
        $row = $result->fetch_row();
        return $row[0] ?? 0;
    }

    return 0;
}


$total_students = getCount(
    $conn,
    "SELECT COUNT(*) FROM students"
);

$total_products = getCount(
    $conn,
    "SELECT COUNT(*) FROM products"
);

$total_bulk_purchases = getCount(
    $conn,
    "SELECT COUNT(*) FROM bulk_purchases"
);

$total_participants = getCount(
    $conn,
    "SELECT COUNT(*) FROM bulk_purchase_participants"
);

$total_payments = getCount(
    $conn,
    "SELECT COUNT(*) FROM payments"
);

$paid_payments = getCount(
    $conn,
    "SELECT COUNT(*) FROM payments WHERE payment_status = 'paid'"
);

$pending_payments = getCount(
    $conn,
    "SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'"
);

$total_amount_paid = 0;

$amount_result = $conn->query("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM payments
    WHERE payment_status = 'paid'
");

if ($amount_result) {

    $amount_row = $amount_result->fetch_row();

    $total_amount_paid = $amount_row[0] ?? 0;
}


/* =========================
   BULK PURCHASE STATUS
========================= */

$status_query = $conn->query("
    SELECT
        status,
        COUNT(*) AS total
    FROM bulk_purchases
    GROUP BY status
");

$bulk_status = [];

if ($status_query) {

    while ($row = $status_query->fetch_assoc()) {

        $bulk_status[$row["status"]] = $row["total"];
    }
}


/* =========================
   PAYMENT STATUS
========================= */

$payment_status_query = $conn->query("
    SELECT
        payment_status,
        COUNT(*) AS total
    FROM payments
    GROUP BY payment_status
");

$payment_status = [];

if ($payment_status_query) {

    while ($row = $payment_status_query->fetch_assoc()) {

        $payment_status[$row["payment_status"]] =
            $row["total"];
    }
}


/* =========================
   MOST PARTICIPATED PRODUCTS
========================= */

$product_query = $conn->query("
    SELECT
        p.name AS product_name,
        p.category,
        COUNT(bpp.participant_id) AS participants,
        COALESCE(SUM(bpp.quantity), 0) AS quantity_requested
    FROM products p

    LEFT JOIN bulk_purchases bp
        ON p.product_id = bp.product_id

    LEFT JOIN bulk_purchase_participants bpp
        ON bp.bulk_purchase_id = bpp.bulk_purchase_id
        AND bpp.status != 'cancelled'

    GROUP BY
        p.product_id,
        p.name,
        p.category

    ORDER BY quantity_requested DESC

    LIMIT 10
");

$products_report = [];

if ($product_query) {

    while ($row = $product_query->fetch_assoc()) {

        $products_report[] = $row;
    }
}


/* =========================
   RECENT PAYMENTS
========================= */

$recent_payments_query = $conn->query("
    SELECT
        pay.amount_paid,
        pay.payment_method,
        pay.payment_status,
        pay.created_at,

        s.first_name,
        s.last_name,

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

    LIMIT 10
");

$recent_payments = [];

if ($recent_payments_query) {

    while ($row = $recent_payments_query->fetch_assoc()) {

        $recent_payments[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reports | Admin</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        body {
            margin: 0;
            background: #f5f7fa;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding-top: 20px;
            overflow-y: auto;
        }

        .brand {
            padding: 15px 25px 25px;
            color: #198754;
            font-size: 20px;
            font-weight: bold;
        }

        .nav-link {
            color: #374151;
            padding: 12px 25px;
            margin: 3px 10px;
            border-radius: 8px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #e8f5ee;
            color: #198754;
        }

        .nav-link i {
            width: 25px;
        }

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        .topbar {
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .content {
            padding: 30px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            height: 100%;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: #e8f5ee;
            color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-number {
            font-size: 25px;
            font-weight: bold;
            margin-top: 10px;
        }

        .report-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .report-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table th {
            background: #f8fafc;
            white-space: nowrap;
            font-size: 13px;
        }

        .table td {
            vertical-align: middle;
            font-size: 13px;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .status-row:last-child {
            border-bottom: none;
        }

    </style>

</head>

<body>


<!-- SIDEBAR -->

<div class="sidebar">

    <div class="brand">

        <i class="fa-solid fa-cart-shopping"></i>

        Bulk Purchase

    </div>


    <a href="dashboard.php" class="nav-link">

        <i class="fa-solid fa-gauge"></i>

        Dashboard

    </a>


    <a href="students.php" class="nav-link">

        <i class="fa-solid fa-user-graduate"></i>

        Students

    </a>


    <a href="products.php" class="nav-link">

        <i class="fa-solid fa-box"></i>

        Products

    </a>


    <a href="suppliers.php" class="nav-link">

        <i class="fa-solid fa-truck-field"></i>

        Suppliers

    </a>


    <a href="bulk_purchases.php" class="nav-link">

        <i class="fa-solid fa-layer-group"></i>

        Bulk Purchases

    </a>


    <a href="participants.php" class="nav-link">

        <i class="fa-solid fa-users"></i>

        Participants

    </a>


    <a href="payments.php" class="nav-link">

        <i class="fa-solid fa-money-bill-wave"></i>

        Payments

    </a>


    <a href="orders.php" class="nav-link">

        <i class="fa-solid fa-box-open"></i>

        Orders

    </a>


    <a href="inventory.php" class="nav-link">

        <i class="fa-solid fa-warehouse"></i>

        Inventory

    </a>


    <a href="notifications.php" class="nav-link">

        <i class="fa-solid fa-bell"></i>

        Notifications

    </a>


    <a href="reports.php" class="nav-link active">

        <i class="fa-solid fa-chart-column"></i>

        Reports

    </a>


    <hr>


    <a href="../auth/logout.php" class="nav-link text-danger">

        <i class="fa-solid fa-right-from-bracket"></i>

        Logout

    </a>

</div>


<!-- MAIN -->

<div class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <strong>
            Reports
        </strong>

        <div>

            <i class="fa-solid fa-user-shield text-success"></i>

            <?php echo htmlspecialchars($admin_name); ?>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">


        <div class="mb-4">

            <h3 class="fw-bold">
                System Reports
            </h3>

            <p class="text-muted mb-0">
                Overview of student bulk purchasing activities.
            </p>

        </div>


        <!-- SUMMARY CARDS -->

        <div class="row g-4 mb-4">


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fa-solid fa-user-graduate"></i>

                    </div>

                    <div class="text-muted small">
                        Total Students
                    </div>

                    <div class="stat-number">

                        <?php echo $total_students; ?>

                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fa-solid fa-box"></i>

                    </div>

                    <div class="text-muted small">
                        Total Products
                    </div>

                    <div class="stat-number">

                        <?php echo $total_products; ?>

                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fa-solid fa-layer-group"></i>

                    </div>

                    <div class="text-muted small">
                        Bulk Purchases
                    </div>

                    <div class="stat-number">

                        <?php echo $total_bulk_purchases; ?>

                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fa-solid fa-users"></i>

                    </div>

                    <div class="text-muted small">
                        Participants
                    </div>

                    <div class="stat-number">

                        <?php echo $total_participants; ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- PAYMENT SUMMARY -->

        <div class="row g-4 mb-4">


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fa-solid fa-money-bill-wave"></i>

                    </div>

                    <div class="text-muted small">
                        Total Payments
                    </div>

                    <div class="stat-number">

                        <?php echo $total_payments; ?>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fa-solid fa-circle-check"></i>

                    </div>

                    <div class="text-muted small">
                        Paid Transactions
                    </div>

                    <div class="stat-number text-success">

                        <?php echo $paid_payments; ?>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fa-solid fa-naira-sign"></i>

                    </div>

                    <div class="text-muted small">
                        Verified Amount
                    </div>

                    <div class="stat-number">

                        ₦<?php echo number_format(
                            $total_amount_paid,
                            2
                        ); ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- REPORT TABLES -->

        <div class="row g-4">


            <!-- BULK PURCHASE STATUS -->

            <div class="col-lg-5">

                <div class="report-card">

                    <div class="report-header">

                        <h5 class="fw-bold mb-1">

                            <i class="fa-solid fa-layer-group text-success"></i>

                            Bulk Purchase Status

                        </h5>

                        <p class="text-muted mb-0">

                            Current status of bulk purchases.

                        </p>

                    </div>


                    <div class="p-3">

                        <?php

                        $statuses = [
                            "open" => "Open",
                            "target_reached" => "Target Reached",
                            "closed" => "Closed",
                            "payment" => "Payment",
                            "confirmed" => "Confirmed",
                            "processing" => "Processing",
                            "shipped" => "Shipped",
                            "delivered" => "Delivered",
                            "cancelled" => "Cancelled"
                        ];

                        foreach ($statuses as $key => $label):

                            $count =
                                $bulk_status[$key] ?? 0;

                        ?>

                            <div class="status-row">

                                <span>

                                    <?php
                                    echo $label;
                                    ?>

                                </span>

                                <span class="badge bg-success">

                                    <?php
                                    echo $count;
                                    ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>


            <!-- PAYMENT STATUS -->

            <div class="col-lg-3">

                <div class="report-card">

                    <div class="report-header">

                        <h5 class="fw-bold mb-1">

                            <i class="fa-solid fa-money-check-dollar text-success"></i>

                            Payment Status

                        </h5>

                        <p class="text-muted mb-0">

                            Payment overview.

                        </p>

                    </div>


                    <div class="p-3">


                        <div class="status-row">

                            <span>
                                Paid
                            </span>

                            <span class="badge bg-success">

                                <?php
                                echo $payment_status["paid"] ?? 0;
                                ?>

                            </span>

                        </div>


                        <div class="status-row">

                            <span>
                                Pending
                            </span>

                            <span class="badge bg-warning text-dark">

                                <?php
                                echo $payment_status["pending"] ?? 0;
                                ?>

                            </span>

                        </div>


                        <div class="status-row">

                            <span>
                                Failed
                            </span>

                            <span class="badge bg-danger">

                                <?php
                                echo $payment_status["failed"] ?? 0;
                                ?>

                            </span>

                        </div>


                        <div class="status-row">

                            <span>
                                Refunded
                            </span>

                            <span class="badge bg-secondary">

                                <?php
                                echo $payment_status["refunded"] ?? 0;
                                ?>

                            </span>

                        </div>

                    </div>

                </div>

            </div>


            <!-- PRODUCT PARTICIPATION -->

            <div class="col-lg-4">

                <div class="report-card">

                    <div class="report-header">

                        <h5 class="fw-bold mb-1">

                            <i class="fa-solid fa-chart-simple text-success"></i>

                            Product Participation

                        </h5>

                        <p class="text-muted mb-0">

                            Products with the highest quantities requested.

                        </p>

                    </div>


                    <div class="table-responsive">

                        <table class="table table-sm mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        Participants
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php if (
                                count($products_report) > 0
                            ): ?>

                                <?php foreach (
                                    $products_report
                                    as $product
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $product["product_name"]
                                                );
                                                ?>

                                            </strong>

                                            <br>

                                            <small class="text-muted">

                                                <?php
                                                echo htmlspecialchars(
                                                    $product["category"]
                                                );
                                                ?>

                                            </small>

                                        </td>


                                        <td>

                                            <?php
                                            echo $product["participants"];
                                            ?>

                                        </td>


                                        <td>

                                            <?php
                                            echo $product["quantity_requested"];
                                            ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="3"
                                        class="text-center py-4"
                                    >

                                        No data available.

                                    </td>

                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>


        </div>


        <!-- RECENT PAYMENTS -->

        <div class="report-card mt-4">

            <div class="report-header">

                <h5 class="fw-bold mb-1">

                    <i class="fa-solid fa-clock-rotate-left text-success"></i>

                    Recent Payments

                </h5>

                <p class="text-muted mb-0">

                    The latest payment transactions recorded in the system.

                </p>

            </div>


            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Product
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Method
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Date
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (
                        count($recent_payments) > 0
                    ): ?>

                        <?php foreach (
                            $recent_payments
                            as $payment
                        ): ?>

                            <tr>

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $payment["first_name"] .
                                        " " .
                                        $payment["last_name"]
                                    );

                                    ?>

                                </td>


                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $payment["product_name"]
                                    );

                                    ?>

                                </td>


                                <td>

                                    <strong>

                                        ₦<?php

                                        echo number_format(
                                            $payment["amount_paid"],
                                            2
                                        );

                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        ucfirst(
                                            str_replace(
                                                "_",
                                                " ",
                                                $payment["payment_method"]
                                            )
                                        )
                                    );

                                    ?>

                                </td>


                                <td>

                                    <?php if (
                                        $payment["payment_status"]
                                        ===
                                        "paid"
                                    ): ?>

                                        <span class="badge bg-success">

                                            Paid

                                        </span>

                                    <?php elseif (
                                        $payment["payment_status"]
                                        ===
                                        "pending"
                                    ): ?>

                                        <span class="badge bg-warning text-dark">

                                            Pending

                                        </span>

                                    <?php elseif (
                                        $payment["payment_status"]
                                        ===
                                        "failed"
                                    ): ?>

                                        <span class="badge bg-danger">

                                            Failed

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            <?php

                                            echo htmlspecialchars(
                                                ucfirst(
                                                    $payment["payment_status"]
                                                )
                                            );

                                            ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php

                                    echo date(
                                        "d M Y, h:i A",
                                        strtotime(
                                            $payment["created_at"]
                                        )
                                    );

                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center py-5"
                            >

                                <i
                                    class="fa-solid fa-file-invoice-dollar fa-2x text-muted mb-3"
                                ></i>

                                <p class="mb-0 text-muted">

                                    No payment records found.

                                </p>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>