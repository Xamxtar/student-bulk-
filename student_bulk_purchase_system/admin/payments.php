<?php

session_start();

require_once "../config/database.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if user is an admin
if ($_SESSION["user_type"] !== "admin") {
    header("Location: ../student/dashboard.php");
    exit();
}


// Get admin information
$user_id = $_SESSION["user_id"];

$admin_query = $conn->prepare("
    SELECT admin_id, first_name, last_name
    FROM admins
    WHERE user_id = ?
");

$admin_query->bind_param("i", $user_id);
$admin_query->execute();

$admin_result = $admin_query->get_result();
$admin = $admin_result->fetch_assoc();

if (!$admin) {
    die("Admin account not found.");
}

$admin_id = $admin["admin_id"];


// Handle payment verification
if (isset($_GET["verify"])) {

    $payment_id = intval($_GET["verify"]);

    try {

        $conn->begin_transaction();

        // Get payment and participant information
        $payment_query = $conn->prepare("
            SELECT 
                p.payment_id,
                p.participant_id,
                p.payment_status,
                bp.student_id,
                bp.bulk_purchase_id
            FROM payments p
            INNER JOIN bulk_purchase_participants bp
                ON p.participant_id = bp.participant_id
            WHERE p.payment_id = ?
            FOR UPDATE
        ");

        $payment_query->bind_param("i", $payment_id);
        $payment_query->execute();

        $payment_result = $payment_query->get_result();

        if ($payment_result->num_rows === 0) {
            throw new Exception("Payment not found.");
        }

        $payment = $payment_result->fetch_assoc();

        // Make sure payment is still pending
        if ($payment["payment_status"] !== "pending") {
            throw new Exception("This payment has already been processed.");
        }

        $participant_id = $payment["participant_id"];
        $student_id = $payment["student_id"];
        $bulk_purchase_id = $payment["bulk_purchase_id"];

        // Update payment
        $update_payment = $conn->prepare("
            UPDATE payments
            SET
                payment_status = 'paid',
                paid_at = NOW(),
                verified_by = ?,
                verified_at = NOW()
            WHERE payment_id = ?
        ");

        $update_payment->bind_param(
            "ii",
            $admin_id,
            $payment_id
        );

        $update_payment->execute();


        // Update participant status
        $update_participant = $conn->prepare("
            UPDATE bulk_purchase_participants
            SET status = 'paid'
            WHERE participant_id = ?
        ");

        $update_participant->bind_param(
            "i",
            $participant_id
        );

        $update_participant->execute();


        // Check whether all active participants have paid
        $payment_check = $conn->prepare("
            SELECT
                COUNT(*) AS total_participants,
                SUM(
                    CASE
                        WHEN status = 'paid' THEN 1
                        ELSE 0
                    END
                ) AS paid_participants
            FROM bulk_purchase_participants
            WHERE bulk_purchase_id = ?
            AND status != 'cancelled'
        ");

        $payment_check->bind_param(
            "i",
            $bulk_purchase_id
        );

        $payment_check->execute();

        $payment_status_result = $payment_check->get_result();
        $payment_counts = $payment_status_result->fetch_assoc();

        $total_participants = (int) $payment_counts["total_participants"];
        $paid_participants = (int) $payment_counts["paid_participants"];


        // If everyone has paid, mark the bulk purchase as confirmed
        if (
            $total_participants > 0 &&
            $total_participants === $paid_participants
        ) {

            $update_bulk = $conn->prepare("
                UPDATE bulk_purchases
                SET status = 'confirmed'
                WHERE bulk_purchase_id = ?
                AND status IN ('target_reached', 'payment')
            ");

            $update_bulk->bind_param(
                "i",
                $bulk_purchase_id
            );

            $update_bulk->execute();

        } else {

            // Otherwise keep it in payment stage
            $update_bulk = $conn->prepare("
                UPDATE bulk_purchases
                SET status = 'payment'
                WHERE bulk_purchase_id = ?
                AND status = 'target_reached'
            ");

            $update_bulk->bind_param(
                "i",
                $bulk_purchase_id
            );

            $update_bulk->execute();
        }


        // Create notification for student
        $notification_title = "Payment Verified";
        $notification_message =
            "Your payment has been verified successfully.";

        $notification_type = "payment";

        $notification = $conn->prepare("
            INSERT INTO notifications
            (
                sender_id,
                recipient_id,
                title,
                message,
                type
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $notification->bind_param(
            "iisss",
            $admin_id,
            $student_id,
            $notification_title,
            $notification_message,
            $notification_type
        );

        $notification->execute();


        $conn->commit();

        header("Location: payments.php?success=verified");
        exit();

    } catch (Exception $e) {

        $conn->rollback();

        header(
            "Location: payments.php?error=" .
            urlencode($e->getMessage())
        );

        exit();
    }
}


// Handle payment rejection
if (isset($_GET["reject"])) {

    $payment_id = intval($_GET["reject"]);

    try {

        $conn->begin_transaction();

        // Get payment and student information
        $payment_query = $conn->prepare("
            SELECT
                p.payment_id,
                p.participant_id,
                p.payment_status,
                bp.student_id
            FROM payments p
            INNER JOIN bulk_purchase_participants bp
                ON p.participant_id = bp.participant_id
            WHERE p.payment_id = ?
            FOR UPDATE
        ");

        $payment_query->bind_param(
            "i",
            $payment_id
        );

        $payment_query->execute();

        $payment_result = $payment_query->get_result();

        if ($payment_result->num_rows === 0) {
            throw new Exception("Payment not found.");
        }

        $payment = $payment_result->fetch_assoc();

        if ($payment["payment_status"] !== "pending") {
            throw new Exception("This payment has already been processed.");
        }

        $student_id = $payment["student_id"];

        // Mark payment as failed
        $update_payment = $conn->prepare("
            UPDATE payments
            SET
                payment_status = 'failed',
                verified_by = ?,
                verified_at = NOW(),
                notes = 'Payment rejected by administrator'
            WHERE payment_id = ?
        ");

        $update_payment->bind_param(
            "ii",
            $admin_id,
            $payment_id
        );

        $update_payment->execute();


        // Notify student
        $notification_title = "Payment Rejected";
        $notification_message =
            "Your payment could not be verified. Please contact the administrator.";

        $notification_type = "payment";

        $notification = $conn->prepare("
            INSERT INTO notifications
            (
                sender_id,
                recipient_id,
                title,
                message,
                type
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $notification->bind_param(
            "iisss",
            $admin_id,
            $student_id,
            $notification_title,
            $notification_message,
            $notification_type
        );

        $notification->execute();


        $conn->commit();

        header("Location: payments.php?success=rejected");
        exit();

    } catch (Exception $e) {

        $conn->rollback();

        header(
            "Location: payments.php?error=" .
            urlencode($e->getMessage())
        );

        exit();
    }
}


// Filter
$filter = $_GET["status"] ?? "all";


// Get payments
if ($filter === "pending") {

    $query = "
        SELECT
            p.payment_id,
            p.amount_paid,
            p.payment_method,
            p.payment_status,
            p.payment_reference,
            p.paid_at,
            p.created_at,

            s.first_name,
            s.last_name,
            s.matric_number,

            pr.name AS product_name,

            bp.quantity

        FROM payments p

        INNER JOIN bulk_purchase_participants bp
            ON p.participant_id = bp.participant_id

        INNER JOIN students s
            ON bp.student_id = s.student_id

        INNER JOIN bulk_purchases b
            ON bp.bulk_purchase_id = b.bulk_purchase_id

        INNER JOIN products pr
            ON b.product_id = pr.product_id

        WHERE p.payment_status = 'pending'

        ORDER BY p.created_at DESC
    ";

} else {

    $query = "
        SELECT
            p.payment_id,
            p.amount_paid,
            p.payment_method,
            p.payment_status,
            p.payment_reference,
            p.paid_at,
            p.created_at,

            s.first_name,
            s.last_name,
            s.matric_number,

            pr.name AS product_name,

            bp.quantity

        FROM payments p

        INNER JOIN bulk_purchase_participants bp
            ON p.participant_id = bp.participant_id

        INNER JOIN students s
            ON bp.student_id = s.student_id

        INNER JOIN bulk_purchases b
            ON bp.bulk_purchase_id = b.bulk_purchase_id

        INNER JOIN products pr
            ON b.product_id = pr.product_id

        ORDER BY p.created_at DESC
    ";
}


$result = $conn->query($query);


// Count pending payments
$pending_query = "
    SELECT COUNT(*) AS total
    FROM payments
    WHERE payment_status = 'pending'
";

$pending_result = $conn->query($pending_query);
$pending_count = $pending_result->fetch_assoc()["total"];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Payments</title>

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
            background: #f4f7f9;
            color: #333;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: #0b5d3b;
            position: fixed;
            left: 0;
            top: 0;
            padding: 25px 15px;
            color: white;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 13px 15px;
            margin-bottom: 5px;
            border-radius: 6px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #0877b9;
        }

        .sidebar i {
            width: 25px;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .topbar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar h1 {
            color: #0b5d3b;
        }

        .pending-box {
            background: #fff;
            border-left: 5px solid #0877b9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .pending-box strong {
            color: #0877b9;
            font-size: 24px;
        }

        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success {
            background: #dff5e8;
            color: #176b3a;
        }

        .error {
            background: #fde2e2;
            color: #a33a3a;
        }

        .filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .filters a {
            display: inline-block;
            text-decoration: none;
            padding: 10px 16px;
            margin-right: 5px;
            border-radius: 5px;
            background: #e9eef2;
            color: #333;
        }

        .filters a.active {
            background: #0877b9;
            color: white;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: #0b5d3b;
            color: white;
            padding: 13px;
            text-align: left;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f8fafb;
        }

        .status {
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending {
            background: #fff2cc;
            color: #856404;
        }

        .paid {
            background: #dff5e8;
            color: #176b3a;
        }

        .failed {
            background: #fde2e2;
            color: #a33a3a;
        }

        .action {
            text-decoration: none;
            padding: 7px 10px;
            border-radius: 5px;
            color: white;
            display: inline-block;
            margin: 2px;
            font-size: 12px;
        }

        .verify {
            background: #0b5d3b;
        }

        .reject {
            background: #c0392b;
        }

        .action:hover {
            opacity: 0.85;
        }

        @media (max-width: 800px) {

            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
                padding: 15px;
            }

        }

    </style>

</head>

<body>


<!-- SIDEBAR -->

<div class="sidebar">

    <h2>
        <i class="fa-solid fa-user-shield"></i>
        Admin Panel
    </h2>

    <a href="dashboard.php">
        <i class="fa-solid fa-house"></i>
        Dashboard
    </a>

    <a href="students.php">
        <i class="fa-solid fa-users"></i>
        Students
    </a>

    <a href="products.php">
        <i class="fa-solid fa-box"></i>
        Products
    </a>

    <a href="suppliers.php">
        <i class="fa-solid fa-truck-field"></i>
        Suppliers
    </a>

    <a href="bulk_purchases.php">
        <i class="fa-solid fa-cart-shopping"></i>
        Bulk Purchases
    </a>

    <a href="participants.php">
        <i class="fa-solid fa-user-group"></i>
        Participants
    </a>

    <a href="payments.php" class="active">
        <i class="fa-solid fa-credit-card"></i>
        Payments
    </a>

    <a href="orders.php">
        <i class="fa-solid fa-boxes-stacked"></i>
        Orders
    </a>

    <a href="inventory.php">
        <i class="fa-solid fa-warehouse"></i>
        Inventory
    </a>

    <a href="notifications.php">
        <i class="fa-solid fa-bell"></i>
        Notifications
    </a>

    <a href="reports.php">
        <i class="fa-solid fa-chart-column"></i>
        Reports
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        Logout
    </a>

</div>


<!-- MAIN -->

<div class="main">

    <div class="topbar">

        <div>
            <h1>Payment Management</h1>

            <p>
                Welcome,
                <?php echo htmlspecialchars(
                    $admin["first_name"] . " " . $admin["last_name"]
                ); ?>
            </p>
        </div>

        <i
            class="fa-solid fa-credit-card"
            style="font-size: 35px; color: #0877b9;"
        ></i>

    </div>


    <!-- MESSAGES -->

    <?php if (isset($_GET["success"])): ?>

        <div class="message success">

            <i class="fa-solid fa-circle-check"></i>

            <?php
            if ($_GET["success"] === "verified") {
                echo "Payment verified successfully.";
            } elseif ($_GET["success"] === "rejected") {
                echo "Payment rejected successfully.";
            }
            ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="message error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php
            echo htmlspecialchars($_GET["error"]);
            ?>

        </div>

    <?php endif; ?>


    <!-- PENDING COUNT -->

    <div class="pending-box">

        <p>Pending Payments</p>

        <strong>
            <?php echo $pending_count; ?>
        </strong>

    </div>


    <!-- FILTERS -->

    <div class="filters">

        <a
            href="payments.php"
            class="<?php echo $filter === 'all' ? 'active' : ''; ?>"
        >
            All Payments
        </a>

        <a
            href="payments.php?status=pending"
            class="<?php echo $filter === 'pending' ? 'active' : ''; ?>"
        >
            Pending
        </a>

    </div>


    <!-- PAYMENT TABLE -->

    <div class="table-container">

        <h2 style="margin-bottom: 20px;">
            <i class="fa-solid fa-list"></i>
            Student Payments
        </h2>

        <table>

            <thead>

                <tr>

                    <th>Student</th>
                    <th>Matric Number</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["first_name"] .
                                    " " .
                                    $row["last_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["matric_number"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["product_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["quantity"]
                                );
                                ?>
                            </td>

                            <td>
                                ₦<?php
                                echo number_format(
                                    $row["amount_paid"],
                                    2
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $row["payment_method"]
                                        )
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["payment_reference"]
                                );
                                ?>
                            </td>

                            <td>

                                <span
                                    class="status
                                    <?php echo htmlspecialchars(
                                        $row["payment_status"]
                                    ); ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        $row["payment_status"]
                                    );
                                    ?>

                                </span>

                            </td>

                            <td>
                                <?php
                                echo date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $row["created_at"]
                                    )
                                );
                                ?>
                            </td>

                            <td>

                                <?php if (
                                    $row["payment_status"] === "pending"
                                ): ?>

                                    <a
                                        href="payments.php?verify=<?php echo $row["payment_id"]; ?>"
                                        class="action verify"
                                        onclick="return confirm('Are you sure you want to verify this payment?');"
                                    >
                                        <i class="fa-solid fa-check"></i>
                                        Verify
                                    </a>

                                    <a
                                        href="payments.php?reject=<?php echo $row["payment_id"]; ?>"
                                        class="action reject"
                                        onclick="return confirm('Are you sure you want to reject this payment?');"
                                    >
                                        <i class="fa-solid fa-xmark"></i>
                                        Reject
                                    </a>

                                <?php else: ?>

                                    <span style="color: #777;">
                                        <i class="fa-solid fa-check-double"></i>
                                        Processed
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="10"
                            style="text-align: center; padding: 40px;"
                        >

                            <i
                                class="fa-solid fa-credit-card"
                                style="font-size: 40px; color: #0877b9;"
                            ></i>

                            <p style="margin-top: 15px;">
                                No payment records found.
                            </p>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


</body>
</html>