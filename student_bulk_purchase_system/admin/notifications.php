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
   SEND NOTIFICATION
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $recipient = $_POST["recipient"] ?? "";
    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");
    $type = $_POST["type"] ?? "general";

    $allowed_types = [
        "order_update",
        "payment",
        "general",
        "alert"
    ];

    if (
        empty($recipient) ||
        empty($title) ||
        empty($message) ||
        !in_array($type, $allowed_types)
    ) {

        header("Location: notifications.php?error=invalid");
        exit();
    }


    /* =========================
       SEND TO ALL STUDENTS
    ========================= */

    if ($recipient === "all") {

        $students_stmt = $conn->prepare("
            SELECT student_id
            FROM students
        ");

        $students_stmt->execute();

        $students_result = $students_stmt->get_result();

        $conn->begin_transaction();

        try {

            $insert_stmt = $conn->prepare("
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

            while ($student = $students_result->fetch_assoc()) {

                $student_id = $student["student_id"];

                $insert_stmt->bind_param(
                    "iisss",
                    $admin_user_id,
                    $student_id,
                    $title,
                    $message,
                    $type
                );

                $insert_stmt->execute();
            }

            $conn->commit();

            header("Location: notifications.php?success=sent");
            exit();

        } catch (Exception $e) {

            $conn->rollback();

            header("Location: notifications.php?error=send");
            exit();
        }
    }


    /* =========================
       SEND TO ONE STUDENT
    ========================= */

    $student_id = intval($recipient);

    $student_check = $conn->prepare("
        SELECT student_id
        FROM students
        WHERE student_id = ?
        LIMIT 1
    ");

    $student_check->bind_param("i", $student_id);
    $student_check->execute();

    $student_result = $student_check->get_result();

    if ($student_result->num_rows !== 1) {

        header("Location: notifications.php?error=student");
        exit();
    }


    $stmt = $conn->prepare("
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

    $stmt->bind_param(
    "iisss",
    $admin_id,
    $student_id,
    $title,
    $message,
    $type
);
    

    if ($stmt->execute()) {

        header("Location: notifications.php?success=sent");
        exit();

    } else {

        header("Location: notifications.php?error=send");
        exit();
    }
}


/* =========================
   DELETE NOTIFICATION
========================= */

if (isset($_GET["delete"])) {

    $notification_id = intval($_GET["delete"]);

    $stmt = $conn->prepare("
        DELETE FROM notifications
        WHERE notification_id = ?
        AND sender_id = ?
    ");

    $stmt->bind_param(
        "ii",
        $notification_id,
        $admin_user_id
    );

    if ($stmt->execute()) {

        header("Location: notifications.php?success=deleted");
        exit();

    } else {

        header("Location: notifications.php?error=delete");
        exit();
    }
}


/* =========================
   GET STUDENTS
========================= */

$students = [];

$students_query = $conn->query("
    SELECT
        student_id,
        first_name,
        last_name,
        matric_number
    FROM students
    ORDER BY first_name ASC
");

if ($students_query) {

    while ($student = $students_query->fetch_assoc()) {

        $students[] = $student;
    }
}


/* =========================
   GET NOTIFICATIONS
========================= */

$notifications = [];

$notifications_query = $conn->query("
    SELECT
        n.notification_id,
        n.title,
        n.message,
        n.type,
        n.is_read,
        n.created_at,

        s.first_name,
        s.last_name,
        s.matric_number

    FROM notifications n

    INNER JOIN students s
        ON n.recipient_id = s.student_id

    WHERE n.sender_id = $admin_user_id

    ORDER BY n.created_at DESC
");

if ($notifications_query) {

    while ($notification = $notifications_query->fetch_assoc()) {

        $notifications[] = $notification;
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

    <title>Notifications | Admin</title>

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

        .card-box {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 25px;
        }

        .notification-item {
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 0;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: #e8f5ee;
            color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-title {
            font-weight: 600;
        }

        .small-text {
            color: #6b7280;
            font-size: 12px;
        }

        textarea {
            resize: vertical;
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


    <a href="notifications.php" class="nav-link active">

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

        <strong>
            Notifications
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
                Notification Management
            </h3>

            <p class="text-muted mb-0">
                Send important messages and updates to students.
            </p>

        </div>


        <?php if (isset($_GET["success"])): ?>

            <div class="alert alert-success">

                <i class="fa-solid fa-circle-check"></i>

                <?php

                if ($_GET["success"] === "sent") {

                    echo "Notification sent successfully.";

                } elseif ($_GET["success"] === "deleted") {

                    echo "Notification deleted successfully.";

                }

                ?>

            </div>

        <?php endif; ?>


        <?php if (isset($_GET["error"])): ?>

            <div class="alert alert-danger">

                <i class="fa-solid fa-circle-exclamation"></i>

                Unable to complete the requested action.

            </div>

        <?php endif; ?>


        <div class="row g-4">


            <!-- SEND NOTIFICATION -->

            <div class="col-lg-5">

                <div class="card-box">

                    <h5 class="fw-bold mb-4">

                        <i class="fa-solid fa-paper-plane text-success"></i>

                        Send Notification

                    </h5>


                    <form
                        method="POST"
                        action="notifications.php"
                    >


                        <div class="mb-3">

                            <label class="form-label">
                                Recipient
                            </label>

                            <select
                                name="recipient"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select recipient
                                </option>

                                <option value="all">
                                    All Students
                                </option>

                                <?php foreach ($students as $student): ?>

                                    <option
                                        value="<?php echo $student["student_id"]; ?>"
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $student["first_name"] .
                                            " " .
                                            $student["last_name"] .
                                            " (" .
                                            $student["matric_number"] .
                                            ")"
                                        );

                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Notification Type
                            </label>

                            <select
                                name="type"
                                class="form-select"
                                required
                            >

                                <option value="general">
                                    General
                                </option>

                                <option value="order_update">
                                    Order Update
                                </option>

                                <option value="payment">
                                    Payment
                                </option>

                                <option value="alert">
                                    Alert
                                </option>

                            </select>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Title
                            </label>

                            <input
                                type="text"
                                name="title"
                                class="form-control"
                                maxlength="255"
                                placeholder="Enter notification title"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Message
                            </label>

                            <textarea
                                name="message"
                                class="form-control"
                                rows="5"
                                placeholder="Enter your message..."
                                required
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-success w-100"
                        >

                            <i class="fa-solid fa-paper-plane"></i>

                            Send Notification

                        </button>

                    </form>

                </div>

            </div>


            <!-- SENT NOTIFICATIONS -->

            <div class="col-lg-7">

                <div class="card-box">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <h5 class="fw-bold mb-1">
                                Sent Notifications
                            </h5>

                            <p class="text-muted mb-0">
                                Recently sent messages
                            </p>

                        </div>

                        <span class="badge bg-success">

                            <?php echo count($notifications); ?>

                        </span>

                    </div>


                    <?php if (count($notifications) > 0): ?>

                        <?php foreach ($notifications as $notification): ?>

                            <div class="notification-item">

                                <div class="d-flex gap-3">


                                    <div class="notification-icon">

                                        <?php if ($notification["type"] === "payment"): ?>

                                            <i class="fa-solid fa-money-bill"></i>

                                        <?php elseif ($notification["type"] === "order_update"): ?>

                                            <i class="fa-solid fa-box"></i>

                                        <?php elseif ($notification["type"] === "alert"): ?>

                                            <i class="fa-solid fa-triangle-exclamation"></i>

                                        <?php else: ?>

                                            <i class="fa-solid fa-bell"></i>

                                        <?php endif; ?>

                                    </div>


                                    <div class="flex-grow-1">

                                        <div class="d-flex justify-content-between">

                                            <div class="notification-title">

                                                <?php
                                                echo htmlspecialchars(
                                                    $notification["title"]
                                                );
                                                ?>

                                            </div>


                                            <a
                                                href="notifications.php?delete=<?php echo $notification["notification_id"]; ?>"
                                                class="text-danger"
                                                onclick="return confirm('Delete this notification?');"
                                                title="Delete"
                                            >

                                                <i class="fa-solid fa-trash"></i>

                                            </a>

                                        </div>


                                        <div class="small-text mb-2">

                                            To:

                                            <?php

                                            echo htmlspecialchars(
                                                $notification["first_name"] .
                                                " " .
                                                $notification["last_name"]
                                            );

                                            ?>

                                            -

                                            <?php

                                            echo htmlspecialchars(
                                                $notification["matric_number"]
                                            );

                                            ?>

                                        </div>


                                        <p class="mb-2">

                                            <?php
                                            echo nl2br(
                                                htmlspecialchars(
                                                    $notification["message"]
                                                )
                                            );
                                            ?>

                                        </p>


                                        <div class="small-text">

                                            <i class="fa-regular fa-clock"></i>

                                            <?php

                                            echo date(
                                                "d M Y, h:i A",
                                                strtotime(
                                                    $notification["created_at"]
                                                )
                                            );

                                            ?>

                                            <span class="ms-3">

                                                <span class="badge bg-light text-dark">

                                                    <?php

                                                    echo htmlspecialchars(
                                                        ucfirst(
                                                            str_replace(
                                                                "_",
                                                                " ",
                                                                $notification["type"]
                                                            )
                                                        )
                                                    );

                                                    ?>

                                                </span>

                                            </span>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="text-center py-5">

                            <i
                                class="fa-solid fa-bell-slash fa-2x text-muted mb-3"
                            ></i>

                            <p class="text-muted mb-0">
                                No notifications have been sent yet.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>