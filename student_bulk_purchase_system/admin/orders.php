<?php

session_start();

require_once "../config/database.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check admin
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


// --------------------------------------------------
// UPDATE ORDER STATUS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $bulk_purchase_id = intval($_POST["bulk_purchase_id"] ?? 0);
    $new_status = $_POST["new_status"] ?? "";

    $allowed_statuses = [
        "processing",
        "shipped",
        "delivered"
    ];

    if (
        $bulk_purchase_id <= 0 ||
        !in_array($new_status, $allowed_statuses)
    ) {
        header("Location: orders.php?error=invalid");
        exit();
    }

    try {

        $conn->begin_transaction();

        // Lock the bulk purchase record
        $check = $conn->prepare("
            SELECT
                bulk_purchase_id,
                product_id,
                status
            FROM bulk_purchases
            WHERE bulk_purchase_id = ?
            FOR UPDATE
        ");

        $check->bind_param("i", $bulk_purchase_id);
        $check->execute();

        $check_result = $check->get_result();

        if ($check_result->num_rows === 0) {
            throw new Exception("Bulk purchase not found.");
        }

        $order = $check_result->fetch_assoc();

        $current_status = $order["status"];
        $product_id = $order["product_id"];


        // Make sure status follows the correct order
        $valid_transition = false;

        if (
            $current_status === "confirmed" &&
            $new_status === "processing"
        ) {
            $valid_transition = true;
        }

        if (
            $current_status === "processing" &&
            $new_status === "shipped"
        ) {
            $valid_transition = true;
        }

        if (
            $current_status === "shipped" &&
            $new_status === "delivered"
        ) {
            $valid_transition = true;
        }


        if (!$valid_transition) {
            throw new Exception(
                "Invalid order status transition."
            );
        }


        // Update order status
        $update = $conn->prepare("
            UPDATE bulk_purchases
            SET status = ?
            WHERE bulk_purchase_id = ?
        ");

        $update->bind_param(
            "si",
            $new_status,
            $bulk_purchase_id
        );

        $update->execute();


        // Get all students participating in this order
        $students_query = $conn->prepare("
            SELECT student_id
            FROM bulk_purchase_participants
            WHERE bulk_purchase_id = ?
            AND status != 'cancelled'
        ");

        $students_query->bind_param(
            "i",
            $bulk_purchase_id
        );

        $students_query->execute();

        $students_result = $students_query->get_result();


        // Notification details
        if ($new_status === "processing") {

            $title = "Bulk Purchase Processing";
            $message =
                "Your bulk purchase has been confirmed and is now being processed.";

        } elseif ($new_status === "shipped") {

            $title = "Bulk Purchase Shipped";
            $message =
                "Your bulk purchase has been shipped.";

        } else {

            $title = "Bulk Purchase Delivered";
            $message =
                "Your bulk purchase has been marked as delivered.";

        }

        $notification_type = "order_update";


        // Notify participating students
        while ($student = $students_result->fetch_assoc()) {

            $student_id = $student["student_id"];

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
                $title,
                $message,
                $notification_type
            );

            $notification->execute();
        }


        $conn->commit();

        header("Location: orders.php?success=updated");
        exit();

    } catch (Exception $e) {

        $conn->rollback();

        header(
            "Location: orders.php?error=" .
            urlencode($e->getMessage())
        );

        exit();
    }
}


// --------------------------------------------------
// GET ORDERS
// --------------------------------------------------

$query = "
    SELECT
        bp.bulk_purchase_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.start_date,
        bp.closing_date,
        bp.status,

        p.name AS product_name,
        p.category,

        COUNT(
            CASE
                WHEN bpp.status != 'cancelled'
                THEN bpp.participant_id
            END
        ) AS participant_count,

        COALESCE(
            SUM(
                CASE
                    WHEN bpp.status != 'cancelled'
                    THEN bpp.quantity
                    ELSE 0
                END
            ),
            0
        ) AS total_quantity

    FROM bulk_purchases bp

    INNER JOIN products p
        ON bp.product_id = p.product_id

    LEFT JOIN bulk_purchase_participants bpp
        ON bp.bulk_purchase_id = bpp.bulk_purchase_id

    WHERE bp.status IN (
        'confirmed',
        'processing',
        'shipped',
        'delivered'
    )

    GROUP BY
        bp.bulk_purchase_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.start_date,
        bp.closing_date,
        bp.status,
        p.name,
        p.category

    ORDER BY bp.updated_at DESC
";

$result = $conn->query($query);


// --------------------------------------------------
// STATUS COUNTS
// --------------------------------------------------

$count_query = "
    SELECT
        status,
        COUNT(*) AS total
    FROM bulk_purchases
    WHERE status IN (
        'confirmed',
        'processing',
        'shipped',
        'delivered'
    )
    GROUP BY status
";

$count_result = $conn->query($count_query);

$counts = [
    "confirmed" => 0,
    "processing" => 0,
    "shipped" => 0,
    "delivered" => 0
];

while ($count = $count_result->fetch_assoc()) {
    $counts[$count["status"]] = $count["total"];
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

    <title>Order Management</title>

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
            position: fixed;
            left: 0;
            top: 0;
            background: #0b5d3b;
            padding: 25px 15px;
            color: white;
            overflow-y: auto;
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
            margin-bottom: 5px;
        }

        .admin-icon {
            font-size: 35px;
            color: #0877b9;
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

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid #0877b9;
        }

        .card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .card strong {
            font-size: 28px;
            color: #0b5d3b;
        }

        .card i {
            float: right;
            font-size: 25px;
            color: #0877b9;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
        }

        .table-title {
            margin-bottom: 20px;
            color: #0b5d3b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
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
            display: inline-block;
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .confirmed {
            background: #d9edf7;
            color: #0877b9;
        }

        .processing {
            background: #fff2cc;
            color: #856404;
        }

        .shipped {
            background: #e2e3ff;
            color: #4045a5;
        }

        .delivered {
            background: #dff5e8;
            color: #176b3a;
        }

        .action-form {
            display: inline-block;
        }

        .action-button {
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            font-size: 12px;
        }

        .process-button {
            background: #0877b9;
        }

        .ship-button {
            background: #5555a8;
        }

        .deliver-button {
            background: #0b5d3b;
        }

        .action-button:hover {
            opacity: 0.85;
        }

        .empty {
            text-align: center;
            padding: 50px;
            color: #777;
        }

        .empty i {
            font-size: 45px;
            color: #0877b9;
            margin-bottom: 15px;
        }

        @media (max-width: 1000px) {

            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
                padding: 15px;
            }

            .cards {
                grid-template-columns: 1fr;
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

    <a href="payments.php">
        <i class="fa-solid fa-credit-card"></i>
        Payments
    </a>

    <a href="orders.php" class="active">
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


    <!-- TOP BAR -->

    <div class="topbar">

        <div>

            <h1>
                Order Management
            </h1>

            <p>
                Welcome,
                <?php
                echo htmlspecialchars(
                    $admin["first_name"] .
                    " " .
                    $admin["last_name"]
                );
                ?>
            </p>

        </div>

        <i class="fa-solid fa-boxes-stacked admin-icon"></i>

    </div>


    <!-- MESSAGES -->

    <?php if (isset($_GET["success"])): ?>

        <div class="message success">

            <i class="fa-solid fa-circle-check"></i>

            Order status updated successfully.

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


    <!-- SUMMARY CARDS -->

    <div class="cards">

        <div class="card">

            <i class="fa-solid fa-circle-check"></i>

            <h3>Confirmed</h3>

            <strong>
                <?php echo $counts["confirmed"]; ?>
            </strong>

        </div>


        <div class="card">

            <i class="fa-solid fa-gears"></i>

            <h3>Processing</h3>

            <strong>
                <?php echo $counts["processing"]; ?>
            </strong>

        </div>


        <div class="card">

            <i class="fa-solid fa-truck"></i>

            <h3>Shipped</h3>

            <strong>
                <?php echo $counts["shipped"]; ?>
            </strong>

        </div>


        <div class="card">

            <i class="fa-solid fa-box-open"></i>

            <h3>Delivered</h3>

            <strong>
                <?php echo $counts["delivered"]; ?>
            </strong>

        </div>

    </div>


    <!-- ORDERS -->

    <div class="table-container">

        <h2 class="table-title">

            <i class="fa-solid fa-list"></i>

            Bulk Purchase Orders

        </h2>


        <?php if ($result && $result->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>Order ID</th>

                        <th>Product</th>

                        <th>Category</th>

                        <th>Quantity</th>

                        <th>Participants</th>

                        <th>Bulk Price</th>

                        <th>Status</th>

                        <th>Closing Date</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                #<?php
                                echo $row["bulk_purchase_id"];
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
                                    $row["category"]
                                );
                                ?>
                            </td>


                            <td>
                                <?php
                                echo $row["total_quantity"];
                                ?>
                                /
                                <?php
                                echo $row["target_quantity"];
                                ?>
                            </td>


                            <td>
                                <?php
                                echo $row["participant_count"];
                                ?>
                            </td>


                            <td>
                                ₦<?php
                                echo number_format(
                                    $row["bulk_price"],
                                    2
                                );
                                ?>
                            </td>


                            <td>

                                <span
                                    class="status <?php
                                    echo htmlspecialchars(
                                        $row["status"]
                                    );
                                    ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        $row["status"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $row["closing_date"]
                                    )
                                );
                                ?>

                            </td>


                            <td>


                                <?php if (
                                    $row["status"] === "confirmed"
                                ): ?>

                                    <form
                                        method="POST"
                                        class="action-form"
                                        onsubmit="return confirm('Start processing this order?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="bulk_purchase_id"
                                            value="<?php
                                            echo $row[
                                                "bulk_purchase_id"
                                            ];
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="new_status"
                                            value="processing"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button process-button"
                                        >

                                            <i class="fa-solid fa-gears"></i>

                                            Process

                                        </button>

                                    </form>


                                <?php elseif (
                                    $row["status"] === "processing"
                                ): ?>

                                    <form
                                        method="POST"
                                        class="action-form"
                                        onsubmit="return confirm('Mark this order as shipped?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="bulk_purchase_id"
                                            value="<?php
                                            echo $row[
                                                "bulk_purchase_id"
                                            ];
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="new_status"
                                            value="shipped"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button ship-button"
                                        >

                                            <i class="fa-solid fa-truck"></i>

                                            Ship

                                        </button>

                                    </form>


                                <?php elseif (
                                    $row["status"] === "shipped"
                                ): ?>

                                    <form
                                        method="POST"
                                        class="action-form"
                                        onsubmit="return confirm('Mark this order as delivered?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="bulk_purchase_id"
                                            value="<?php
                                            echo $row[
                                                "bulk_purchase_id"
                                            ];
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="new_status"
                                            value="delivered"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button deliver-button"
                                        >

                                            <i class="fa-solid fa-box-open"></i>

                                            Deliver

                                        </button>

                                    </form>


                                <?php else: ?>

                                    <span style="color: #176b3a;">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Completed

                                    </span>

                                <?php endif; ?>


                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>


        <?php else: ?>

            <div class="empty">

                <i class="fa-solid fa-box-open"></i>

                <h3>No Orders Yet</h3>

                <p>
                    Confirmed bulk purchases will appear here.
                </p>

            </div>

        <?php endif; ?>


    </div>


</div>


</body>

</html>
