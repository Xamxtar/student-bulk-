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


// Get all bulk purchases joined by this student
$sql = "
    SELECT
        bp.bulk_purchase_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.start_date,
        bp.closing_date,
        bp.status AS bulk_status,

        p.product_id,
        p.name AS product_name,
        p.category,
        p.description,
        p.image_url,

        bpp.participant_id,
        bpp.quantity,
        bpp.unit_price,
        bpp.subtotal,
        bpp.status AS participation_status,

        pay.payment_id,
        pay.amount_paid,
        pay.payment_method,
        pay.payment_status,
        pay.payment_reference,
        pay.paid_at

    FROM bulk_purchase_participants bpp

    INNER JOIN bulk_purchases bp
        ON bpp.bulk_purchase_id = bp.bulk_purchase_id

    INNER JOIN products p
        ON bp.product_id = p.product_id

    LEFT JOIN payments pay
        ON bpp.participant_id = pay.participant_id

    WHERE bpp.student_id = ?

    ORDER BY bpp.joined_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Participations</title>

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
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid #0b7a3b;
        }

        .topbar h1 {
            font-size: 24px;
            color: #1f2937;
        }

        .topbar p {
            margin-top: 5px;
            color: #777;
        }

        .student-name {
            color: #0b7a3b;
            font-weight: bold;
        }

        /* Summary cards */

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #ffffff;
            padding: 22px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .summary-icon {
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

        .summary-card h3 {
            font-size: 22px;
            color: #1f2937;
        }

        .summary-card p {
            color: #777;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Participation cards */

        .participations {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
        }

        .participation-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .product-section {
            display: flex;
            gap: 18px;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .product-image {
            width: 90px;
            height: 90px;
            border-radius: 10px;
            background: #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image i {
            font-size: 30px;
            color: #6b7280;
        }

        .product-info h2 {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 7px;
        }

        .category {
            display: inline-block;
            background: #eaf2ff;
            color: #1559a6;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 12px;
        }

        /* Details */

        .details {
            padding: 20px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #777;
        }

        .detail-value {
            font-weight: bold;
            color: #333;
        }

        .amount {
            color: #0b7a3b;
            font-size: 17px;
        }

        /* Status */

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-joined {
            background: #eaf2ff;
            color: #1559a6;
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
        .status-cancelled {
            background: #fdecec;
            color: #b42318;
        }

        .status-processing {
            background: #eaf2ff;
            color: #1559a6;
        }

        .status-shipped {
            background: #f0ebff;
            color: #633bb3;
        }

        .status-delivered {
            background: #eaf7ef;
            color: #0b7a3b;
        }

        /* Progress */

        .progress-section {
            margin-top: 15px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 7px;
            color: #666;
        }

        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #0b7a3b;
            border-radius: 10px;
        }

        /* Buttons */

        .actions {
            padding: 0 20px 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            padding: 11px 15px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: bold;
            transition: 0.3s;
        }

        .btn-primary {
            background: #0b7a3b;
            color: white;
        }

        .btn-primary:hover {
            background: #096331;
        }

        .btn-payment {
            background: #1559a6;
            color: white;
        }

        .btn-payment:hover {
            background: #104982;
        }

        /* Empty */

        .empty {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
        }

        .empty i {
            font-size: 45px;
            color: #0b7a3b;
            margin-bottom: 15px;
        }

        .empty h2 {
            margin-bottom: 8px;
            color: #333;
        }

        .empty p {
            color: #777;
            margin-bottom: 20px;
        }

        /* Responsive */

        @media (max-width: 1000px) {

            .participations {
                grid-template-columns: 1fr;
            }

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

            .topbar {
                display: block;
            }

            .topbar p {
                margin-top: 8px;
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

    <a href="my_participations.php" class="active">
        <i class="fa-solid fa-cart-shopping"></i>
        <span>My Participations</span>
    </a>

    <a href="payment_history.php">
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

        <div>
            <h1>My Participations</h1>

            <p>
                View the bulk purchases you have joined,
                your quantities, payments and order status.
            </p>
        </div>

        <div>
            <span class="student-name">
                <?php echo htmlspecialchars(
                    $student["first_name"] . " " . $student["last_name"]
                ); ?>
            </span>
        </div>

    </div>


    <?php

    /*
     * Calculate summary information.
     */

    $total_participations = 0;
    $total_quantity = 0;
    $total_amount = 0;
    $total_paid = 0;

    // Store records so we can use them twice
    $participations = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $participations[] = $row;

        $total_participations++;

        $total_quantity += (int) $row["quantity"];

        $total_amount += (float) $row["subtotal"];

        if ($row["payment_status"] === "paid") {
            $total_paid += (float) $row["amount_paid"];
        }
    }

    ?>


    <!-- Summary -->

    <div class="summary">

        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-users"></i>
            </div>

            <div>
                <h3><?php echo $total_participations; ?></h3>
                <p>Bulk Purchases Joined</p>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-box"></i>
            </div>

            <div>
                <h3><?php echo $total_quantity; ?></h3>
                <p>Total Items</p>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-naira-sign"></i>
            </div>

            <div>
                <h3>
                    ₦<?php echo number_format($total_amount, 2); ?>
                </h3>
                <p>Total Amount</p>
            </div>

        </div>

    </div>


    <!-- Participations -->

    <?php if (count($participations) > 0): ?>

        <div class="participations">

            <?php foreach ($participations as $row): ?>

                <?php

                $target = (int) $row["target_quantity"];

                $current = (int) $row["current_quantity"];

                if ($target > 0) {
                    $progress = ($current / $target) * 100;
                } else {
                    $progress = 0;
                }

                $progress = min(100, $progress);


                // Payment status
                $payment_status = $row["payment_status"];

                if (!$payment_status) {
                    $payment_status = "pending";
                }


                // Bulk purchase status
                $bulk_status = $row["bulk_status"];

                ?>

                <div class="participation-card">


                    <!-- Product -->

                    <div class="product-section">

                        <div class="product-image">

                            <?php if (!empty($row["image_url"])): ?>

                                <img
                                    src="/student_bulk_purchase_system/<?php echo htmlspecialchars($row["image_url"]); ?>"
                                    alt="Product"
                                >

                            <?php else: ?>

                                <i class="fa-solid fa-box"></i>

                            <?php endif; ?>

                        </div>


                        <div class="product-info">

                            <h2>
                                <?php echo htmlspecialchars(
                                    $row["product_name"]
                                ); ?>
                            </h2>

                            <span class="category">
                                <?php echo htmlspecialchars(
                                    $row["category"]
                                ); ?>
                            </span>

                        </div>

                    </div>


                    <!-- Details -->

                    <div class="details">


                        <div class="detail-row">

                            <span class="detail-label">
                                Quantity Joined
                            </span>

                            <span class="detail-value">
                                <?php echo (int) $row["quantity"]; ?>
                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                Unit Price
                            </span>

                            <span class="detail-value">
                                ₦<?php echo number_format(
                                    $row["unit_price"],
                                    2
                                ); ?>
                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                Amount Owed
                            </span>

                            <span class="detail-value amount">
                                ₦<?php echo number_format(
                                    $row["subtotal"],
                                    2
                                ); ?>
                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                Payment Status
                            </span>

                            <span>

                                <?php if ($payment_status === "paid"): ?>

                                    <span class="status status-paid">
                                        Paid
                                    </span>

                                <?php elseif ($payment_status === "failed"): ?>

                                    <span class="status status-failed">
                                        Failed
                                    </span>

                                <?php elseif ($payment_status === "refunded"): ?>

                                    <span class="status status-failed">
                                        Refunded
                                    </span>

                                <?php else: ?>

                                    <span class="status status-pending">
                                        Pending
                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                Participation
                            </span>

                            <span>

                                <?php if (
                                    $row["participation_status"] === "cancelled"
                                ): ?>

                                    <span class="status status-cancelled">
                                        Cancelled
                                    </span>

                                <?php else: ?>

                                    <span class="status status-joined">
                                        Joined
                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                Bulk Purchase Status
                            </span>

                            <span class="detail-value">

                                <?php
                                echo htmlspecialchars(
                                    ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $bulk_status
                                        )
                                    )
                                );
                                ?>

                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                Closing Date
                            </span>

                            <span class="detail-value">

                                <?php
                                echo date(
                                    "M d, Y",
                                    strtotime($row["closing_date"])
                                );
                                ?>

                            </span>

                        </div>


                        <!-- Progress -->

                        <div class="progress-section">

                            <div class="progress-header">

                                <span>
                                    Group Progress
                                </span>

                                <span>
                                    <?php echo $current; ?>
                                    /
                                    <?php echo $target; ?>
                                    items
                                </span>

                            </div>

                            <div class="progress-bar">

                                <div
                                    class="progress-fill"
                                    style="width: <?php echo $progress; ?>%;"
                                ></div>

                            </div>

                        </div>

                    </div>


                    <!-- Actions -->

                    <div class="actions">

                        <a
                            href="bulk_purchase_details.php?id=<?php echo (int) $row["bulk_purchase_id"]; ?>"
                            class="btn btn-primary"
                        >
                            <i class="fa-solid fa-eye"></i>
                            View Details
                        </a>


                        <?php if (
                            $payment_status !== "paid"
                            && $row["participation_status"] !== "cancelled"
                        ): ?>

                            <a
                                href="payment.php?participant_id=<?php echo (int) $row["participant_id"]; ?>"
                                class="btn btn-payment"
                            >
                                <i class="fa-solid fa-credit-card"></i>
                                Make Payment
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <!-- No Participations -->

        <div class="empty">

            <i class="fa-solid fa-cart-shopping"></i>

            <h2>No Bulk Purchases Yet</h2>

            <p>
                You have not joined any bulk purchases.
                Browse available purchases and join one to get started.
            </p>

            <a
                href="bulk_purchases.php"
                class="btn btn-primary"
            >
                <i class="fa-solid fa-users"></i>
                Browse Bulk Purchases
            </a>

        </div>

    <?php endif; ?>


</div>

</body>

</html>

<?php

mysqli_stmt_close($stmt);
mysqli_close($conn);

?>