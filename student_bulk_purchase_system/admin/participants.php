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
   CANCEL PARTICIPATION
========================= */

if (isset($_GET["cancel"])) {

    $participant_id = intval($_GET["cancel"]);

    $conn->begin_transaction();

    try {

        $stmt = $conn->prepare("
            SELECT
                participant_id,
                bulk_purchase_id,
                quantity,
                status
            FROM bulk_purchase_participants
            WHERE participant_id = ?
            FOR UPDATE
        ");

        $stmt->bind_param("i", $participant_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            throw new Exception("Participation record not found.");
        }

        $participant = $result->fetch_assoc();

        if ($participant["status"] === "cancelled") {
            throw new Exception("This participation is already cancelled.");
        }

        $bulk_purchase_id = $participant["bulk_purchase_id"];
        $quantity = $participant["quantity"];

        /* Lock bulk purchase */

        $bulk_stmt = $conn->prepare("
            SELECT
                bulk_purchase_id,
                current_quantity,
                status
            FROM bulk_purchases
            WHERE bulk_purchase_id = ?
            FOR UPDATE
        ");

        $bulk_stmt->bind_param("i", $bulk_purchase_id);
        $bulk_stmt->execute();

        $bulk_result = $bulk_stmt->get_result();

        if ($bulk_result->num_rows !== 1) {
            throw new Exception("Bulk purchase not found.");
        }

        $bulk = $bulk_result->fetch_assoc();

        /* Cancel participation */

        $update_stmt = $conn->prepare("
            UPDATE bulk_purchase_participants
            SET
                status = 'cancelled',
                cancelled_at = NOW()
            WHERE participant_id = ?
        ");

        $update_stmt->bind_param("i", $participant_id);
        $update_stmt->execute();

        /* Reduce cached quantity */

        $new_quantity = max(
            0,
            $bulk["current_quantity"] - $quantity
        );

        $new_status = $bulk["status"];

        if (
            $bulk["status"] === "target_reached" &&
            $new_quantity < 1
        ) {
            $new_status = "open";
        }

        if (
            $bulk["status"] === "target_reached"
        ) {

            $target_stmt = $conn->prepare("
                SELECT target_quantity
                FROM bulk_purchases
                WHERE bulk_purchase_id = ?
            ");

            $target_stmt->bind_param("i", $bulk_purchase_id);
            $target_stmt->execute();

            $target_result = $target_stmt->get_result();
            $target_data = $target_result->fetch_assoc();

            if ($new_quantity < $target_data["target_quantity"]) {
                $new_status = "open";
            }
        }

        $bulk_update = $conn->prepare("
            UPDATE bulk_purchases
            SET
                current_quantity = ?,
                status = ?
            WHERE bulk_purchase_id = ?
        ");

        $bulk_update->bind_param(
            "isi",
            $new_quantity,
            $new_status,
            $bulk_purchase_id
        );

        $bulk_update->execute();

        $conn->commit();

        header("Location: participants.php?success=cancelled");
        exit();

    } catch (Exception $e) {

        $conn->rollback();

        header(
            "Location: participants.php?error=" .
            urlencode($e->getMessage())
        );

        exit();
    }
}


/* =========================
   GET PARTICIPANTS
========================= */

$sql = "
    SELECT
        bpp.participant_id,
        bpp.quantity,
        bpp.unit_price,
        bpp.subtotal,
        bpp.joined_at,
        bpp.status AS participant_status,

        s.first_name,
        s.last_name,
        s.matric_number,
        s.department,
        s.level,

        bp.bulk_purchase_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.status AS bulk_status,
        bp.closing_date,

        p.name AS product_name,
        p.category,

        pay.payment_id,
        pay.amount_paid,
        pay.payment_method,
        pay.payment_reference,
        pay.payment_status,
        pay.paid_at

    FROM bulk_purchase_participants bpp

    INNER JOIN students s
        ON bpp.student_id = s.student_id

    INNER JOIN bulk_purchases bp
        ON bpp.bulk_purchase_id = bp.bulk_purchase_id

    INNER JOIN products p
        ON bp.product_id = p.product_id

    LEFT JOIN payments pay
        ON bpp.participant_id = pay.participant_id

    ORDER BY bpp.joined_at DESC
";

$result = $conn->query($sql);


/* =========================
   SUMMARY STATISTICS
========================= */

$total_participants = 0;
$active_participants = 0;
$paid_participants = 0;
$total_quantity = 0;

$participants = [];

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $participants[] = $row;

        $total_participants++;

        if ($row["participant_status"] !== "cancelled") {
            $active_participants++;
            $total_quantity += $row["quantity"];
        }

        if (
            isset($row["payment_status"]) &&
            $row["payment_status"] === "paid"
        ) {
            $paid_participants++;
        }
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

    <title>Participants | Admin</title>

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
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
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

        .table-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .table-card-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            white-space: nowrap;
            background: #f8fafc;
            font-size: 13px;
        }

        .table td {
            vertical-align: middle;
            white-space: nowrap;
            font-size: 13px;
        }

        .student-name {
            font-weight: 600;
        }

        .small-text {
            color: #6b7280;
            font-size: 12px;
        }

        .badge {
            font-weight: 500;
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

    <a href="participants.php" class="nav-link active">
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

    <a href="reports.php" class="nav-link">
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

        <div>
            <strong>Participants</strong>
        </div>

        <div>
            <i class="fa-solid fa-user-shield text-success"></i>
            <?php echo htmlspecialchars($admin_name); ?>
        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">

        <div class="mb-4">

            <h3 class="fw-bold">
                Bulk Purchase Participants
            </h3>

            <p class="text-muted mb-0">
                View students participating in bulk purchases and monitor their quantities and payment status.
            </p>

        </div>


        <?php if (isset($_GET["success"])): ?>

            <div class="alert alert-success">

                <i class="fa-solid fa-circle-check"></i>

                Participation cancelled successfully.

            </div>

        <?php endif; ?>


        <?php if (isset($_GET["error"])): ?>

            <div class="alert alert-danger">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?php echo htmlspecialchars($_GET["error"]); ?>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <div class="row g-4 mb-4">

            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <div class="small-text">
                        Total Participants
                    </div>

                    <div class="stat-number">
                        <?php echo $total_participants; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>

                    <div class="small-text">
                        Active Participants
                    </div>

                    <div class="stat-number">
                        <?php echo $active_participants; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-money-check-dollar"></i>
                    </div>

                    <div class="small-text">
                        Paid Participants
                    </div>

                    <div class="stat-number">
                        <?php echo $paid_participants; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-cubes"></i>
                    </div>

                    <div class="small-text">
                        Total Quantity Joined
                    </div>

                    <div class="stat-number">
                        <?php echo $total_quantity; ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- PARTICIPANTS TABLE -->

        <div class="table-card">

            <div class="table-card-header">

                <h5 class="mb-1">
                    Participant Records
                </h5>

                <p class="text-muted mb-0">
                    Students who have joined the available bulk purchases.
                </p>

            </div>


            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>

                        <tr>

                            <th>Student</th>

                            <th>Matric Number</th>

                            <th>Product</th>

                            <th>Bulk Purchase</th>

                            <th>Quantity</th>

                            <th>Unit Price</th>

                            <th>Subtotal</th>

                            <th>Participation</th>

                            <th>Payment</th>

                            <th>Joined</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($participants) > 0): ?>

                        <?php foreach ($participants as $row): ?>

                            <tr>

                                <td>

                                    <div class="student-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $row["first_name"] .
                                            " " .
                                            $row["last_name"]
                                        );
                                        ?>

                                    </div>

                                    <div class="small-text">

                                        <?php
                                        echo htmlspecialchars(
                                            $row["department"]
                                        );
                                        ?>

                                        -

                                        <?php
                                        echo htmlspecialchars(
                                            $row["level"]
                                        );
                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["matric_number"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $row["product_name"]
                                        );
                                        ?>

                                    </strong>

                                    <div class="small-text">

                                        <?php
                                        echo htmlspecialchars(
                                            $row["category"]
                                        );
                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <span class="badge bg-primary">

                                        #<?php
                                        echo $row["bulk_purchase_id"];
                                        ?>

                                    </span>

                                    <div class="small-text">

                                        Target:
                                        <?php
                                        echo $row["target_quantity"];
                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <strong>

                                        <?php
                                        echo $row["quantity"];
                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    ₦<?php
                                    echo number_format(
                                        $row["unit_price"],
                                        2
                                    );
                                    ?>

                                </td>


                                <td>

                                    <strong>

                                        ₦<?php
                                        echo number_format(
                                            $row["subtotal"],
                                            2
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    <?php if ($row["participant_status"] === "joined"): ?>

                                        <span class="badge bg-success">
                                            Joined
                                        </span>

                                    <?php elseif ($row["participant_status"] === "paid"): ?>

                                        <span class="badge bg-primary">
                                            Paid
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Cancelled
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (!$row["payment_id"]): ?>

                                        <span class="badge bg-warning text-dark">
                                            No Payment
                                        </span>

                                    <?php elseif ($row["payment_status"] === "paid"): ?>

                                        <span class="badge bg-success">
                                            Paid
                                        </span>

                                    <?php elseif ($row["payment_status"] === "pending"): ?>

                                        <span class="badge bg-warning text-dark">
                                            Pending
                                        </span>

                                    <?php elseif ($row["payment_status"] === "failed"): ?>

                                        <span class="badge bg-danger">
                                            Failed
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            <?php
                                            echo htmlspecialchars(
                                                $row["payment_status"]
                                            );
                                            ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime($row["joined_at"])
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php if ($row["participant_status"] !== "cancelled"): ?>

                                        <a
                                            href="participants.php?cancel=<?php echo $row["participant_id"]; ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Are you sure you want to cancel this participation?');"
                                        >

                                            <i class="fa-solid fa-xmark"></i>

                                            Cancel

                                        </a>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            No action
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="11"
                                class="text-center py-5"
                            >

                                <i
                                    class="fa-solid fa-users-slash fa-2x text-muted mb-3"
                                ></i>

                                <p class="mb-0">
                                    No participants found.
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