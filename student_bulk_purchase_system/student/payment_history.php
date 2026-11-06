<?php

session_start();

require_once "../config/database.php";

// Check if student is logged in
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

// Get student information
$student_sql = "
    SELECT student_id, first_name, last_name
    FROM students
    WHERE user_id = ?
    LIMIT 1
";

$student_stmt = mysqli_prepare($conn, $student_sql);
mysqli_stmt_bind_param($student_stmt, "i", $user_id);
mysqli_stmt_execute($student_stmt);

$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result);

mysqli_stmt_close($student_stmt);

if (!$student) {
    die("Student account not found.");
}

$student_id = (int) $student["student_id"];


// Get payment history
$sql = "
    SELECT
        pay.payment_id,
        pay.amount_paid,
        pay.payment_method,
        pay.payment_status,
        pay.payment_reference,
        pay.paid_at,
        pay.verified_at,

        bpp.participant_id,
        bpp.quantity,
        bpp.subtotal,

        bp.bulk_purchase_id,
        bp.status AS bulk_status,

        p.name AS product_name,
        p.category

    FROM payments pay

    INNER JOIN bulk_purchase_participants bpp
        ON pay.participant_id = bpp.participant_id

    INNER JOIN bulk_purchases bp
        ON bpp.bulk_purchase_id = bp.bulk_purchase_id

    INNER JOIN products p
        ON bp.product_id = p.product_id

    WHERE bpp.student_id = ?

    ORDER BY pay.payment_id DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


// Summary variables
$total_payments = 0;
$total_paid = 0;
$total_pending = 0;
$total_failed = 0;

$payments = [];

while ($row = mysqli_fetch_assoc($result)) {

    $payments[] = $row;

    $total_payments++;

    if ($row["payment_status"] === "paid") {
        $total_paid += (float) $row["amount_paid"];
    }

    if ($row["payment_status"] === "pending") {
        $total_pending++;
    }

    if ($row["payment_status"] === "failed") {
        $total_failed++;
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

    <title>Payment History</title>

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
        }

        .logo {
            text-align: center;
            color: #0b7a3b;
            font-size: 21px;
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
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #0b7a3b;
        }

        .topbar h1 {
            font-size: 24px;
            color: #1f2937;
        }

        .topbar p {
            margin-top: 6px;
            color: #777;
        }

        .student-name {
            color: #0b7a3b;
            font-weight: bold;
        }

        /* Summary */

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 9px;
            background: #eaf7ef;
            color: #0b7a3b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .summary-card h3 {
            font-size: 21px;
            color: #1f2937;
        }

        .summary-card p {
            color: #777;
            font-size: 13px;
            margin-top: 4px;
        }

        /* Payment table */

        .table-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .table-header h2 {
            font-size: 18px;
            color: #1f2937;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th {
            background: #f8fafc;
            color: #555;
            font-size: 13px;
            text-align: left;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .product-name {
            font-weight: bold;
            color: #1f2937;
        }

        .category {
            display: block;
            color: #777;
            font-size: 12px;
            margin-top: 4px;
        }

        .amount {
            color: #0b7a3b;
            font-weight: bold;
        }

        .reference {
            font-family: monospace;
            color: #1559a6;
        }

        /* Status */

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-paid {
            background: #eaf7ef;
            color: #0b7a3b;
        }

        .status-pending {
            background: #fff7e6;
            color: #b26a00;
        }

        .status-failed,
        .status-refunded {
            background: #fdecec;
            color: #b42318;
        }

        /* Payment method */

        .method {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #444;
        }

        /* Empty */

        .empty {
            text-align: center;
            padding: 60px 20px;
        }

        .empty i {
            font-size: 45px;
            color: #0b7a3b;
            margin-bottom: 15px;
        }

        .empty h2 {
            color: #333;
            margin-bottom: 8px;
        }

        .empty p {
            color: #777;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 7px;
            background: #0b7a3b;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .btn:hover {
            background: #096331;
        }

        /* Responsive */

        @media (max-width: 900px) {

            .summary {
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

        }

    </style>

</head>

<body>


<!-- Sidebar -->

<div class="sidebar">

    <div class="logo">
        Student Bulk Purchase
    </div>

    <a href="dashboard.php">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="bulk_purchases.php">
        <i class="fa-solid fa-users"></i>
        <span>Bulk Purchases</span>
    </a>

    <a href="my_participations.php">
        <i class="fa-solid fa-cart-shopping"></i>
        <span>My Participations</span>
    </a>

    <a href="payment_history.php" class="active">
        <i class="fa-solid fa-credit-card"></i>
        <span>Payment History</span>
    </a>

    <a href="notifications.php">
        <i class="fa-solid fa-bell"></i>
        <span>Notifications</span>
    </a>

    <a href="profile.php">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>

</div>


<!-- Main -->

<div class="main">

    <div class="topbar">

        <h1>
            <i class="fa-solid fa-credit-card"></i>
            Payment History
        </h1>

        <p>
            View your payment transactions and their verification status.
        </p>

        <p>
            Student:
            <span class="student-name">
                <?php echo htmlspecialchars(
                    $student["first_name"] . " " . $student["last_name"]
                ); ?>
            </span>
        </p>

    </div>


    <!-- Summary -->

    <div class="summary">

        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-receipt"></i>
            </div>

            <div>
                <h3>
                    <?php echo $total_payments; ?>
                </h3>

                <p>
                    Total Payments
                </p>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div>
                <h3>
                    ₦<?php echo number_format(
                        $total_paid,
                        2
                    ); ?>
                </h3>

                <p>
                    Verified Payments
                </p>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-clock"></i>
            </div>

            <div>
                <h3>
                    <?php echo $total_pending; ?>
                </h3>

                <p>
                    Pending Payments
                </p>
            </div>

        </div>

    </div>


    <!-- Payment History -->

    <div class="table-card">

        <div class="table-header">

            <h2>
                <i class="fa-solid fa-list"></i>
                Payment Transactions
            </h2>

        </div>


        <?php if (count($payments) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Payment Method
                            </th>

                            <th>
                                Reference
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

                        <?php foreach ($payments as $payment): ?>

                            <tr>

                                <td>

                                    <div class="product-name">

                                        <?php echo htmlspecialchars(
                                            $payment["product_name"]
                                        ); ?>

                                    </div>

                                    <span class="category">

                                        <?php echo htmlspecialchars(
                                            $payment["category"]
                                        ); ?>

                                    </span>

                                </td>


                                <td>

                                    <?php echo (int) $payment["quantity"]; ?>

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

                                    <span class="method">

                                        <?php

                                        if (
                                            $payment["payment_method"]
                                            === "bank_transfer"
                                        ) {

                                            echo '<i class="fa-solid fa-building-columns"></i> Bank Transfer';

                                        } elseif (
                                            $payment["payment_method"]
                                            === "mobile_money"
                                        ) {

                                            echo '<i class="fa-solid fa-mobile-screen-button"></i> Mobile Money';

                                        } elseif (
                                            $payment["payment_method"]
                                            === "card"
                                        ) {

                                            echo '<i class="fa-solid fa-credit-card"></i> Card';

                                        } elseif (
                                            $payment["payment_method"]
                                            === "cash"
                                        ) {

                                            echo '<i class="fa-solid fa-money-bill"></i> Cash';

                                        } else {

                                            echo htmlspecialchars(
                                                $payment["payment_method"]
                                            );

                                        }

                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="reference">

                                        <?php echo htmlspecialchars(
                                            $payment["payment_reference"]
                                        ); ?>

                                    </span>

                                </td>


                                <td>

                                    <?php

                                    $status =
                                        $payment["payment_status"];

                                    if ($status === "paid") {

                                        echo '
                                            <span class="status status-paid">
                                                Paid
                                            </span>
                                        ';

                                    } elseif ($status === "pending") {

                                        echo '
                                            <span class="status status-pending">
                                                Pending
                                            </span>
                                        ';

                                    } elseif ($status === "failed") {

                                        echo '
                                            <span class="status status-failed">
                                                Failed
                                            </span>
                                        ';

                                    } elseif ($status === "refunded") {

                                        echo '
                                            <span class="status status-refunded">
                                                Refunded
                                            </span>
                                        ';

                                    }

                                    ?>

                                </td>


                                <td>

                                    <?php

                                    if (!empty($payment["paid_at"])) {

                                        echo date(
                                            "M d, Y",
                                            strtotime(
                                                $payment["paid_at"]
                                            )
                                        );

                                    } else {

                                        echo date(
                                            "M d, Y"
                                        );

                                    }

                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">

                <i class="fa-solid fa-receipt"></i>

                <h2>
                    No Payment History
                </h2>

                <p>
                    You have not submitted any payments yet.
                </p>

                <a
                    href="my_participations.php"
                    class="btn"
                >
                    <i class="fa-solid fa-cart-shopping"></i>
                    View My Participations
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>

<?php

mysqli_stmt_close($stmt);
mysqli_close($conn);

?>