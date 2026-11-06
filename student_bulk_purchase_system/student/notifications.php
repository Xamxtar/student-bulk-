<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION["user_id"];


/* =========================
   GET STUDENT DETAILS
========================= */

$student_stmt = $conn->prepare("
    SELECT
        student_id,
        first_name,
        last_name
    FROM students
    WHERE user_id = ?
    LIMIT 1
");

$student_stmt->bind_param("i", $user_id);
$student_stmt->execute();

$student_result = $student_stmt->get_result();

if ($student_result->num_rows !== 1) {
    session_unset();
    session_destroy();

    header("Location: ../auth/login.php");
    exit();
}

$student = $student_result->fetch_assoc();

$student_id = $student["student_id"];

$student_name =
    $student["first_name"] . " " . $student["last_name"];


/* =========================
   MARK NOTIFICATION AS READ
========================= */

if (isset($_GET["read"])) {

    $notification_id = intval($_GET["read"]);

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ?
        AND recipient_id = ?
    ");

    $stmt->bind_param(
        "ii",
        $notification_id,
        $student_id
    );

    $stmt->execute();

    header("Location: notifications.php");
    exit();
}


/* =========================
   MARK ALL AS READ
========================= */

if (isset($_GET["read_all"])) {

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_id = ?
        AND is_read = 0
    ");

    $stmt->bind_param(
        "i",
        $student_id
    );

    $stmt->execute();

    header("Location: notifications.php");
    exit();
}


/* =========================
   GET NOTIFICATIONS
========================= */

$stmt = $conn->prepare("
    SELECT
        notification_id,
        title,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    WHERE recipient_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param(
    "i",
    $student_id
);

$stmt->execute();

$result = $stmt->get_result();

$notifications = [];

$unread_count = 0;

while ($row = $result->fetch_assoc()) {

    $notifications[] = $row;

    if ($row["is_read"] == 0) {
        $unread_count++;
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

    <title>Notifications | Student</title>

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

        .page-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .page-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
        }

        .notification {
            padding: 20px 25px;
            border-bottom: 1px solid #e5e7eb;
            transition: 0.2s;
        }

        .notification:hover {
            background: #f8fafc;
        }

        .notification:last-child {
            border-bottom: none;
        }

        .notification.unread {
            background: #f0fdf4;
            border-left: 4px solid #198754;
        }

        .notification-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 10px;
            background: #e8f5ee;
            color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .notification-title {
            font-size: 16px;
            font-weight: 600;
        }

        .notification-message {
            color: #4b5563;
            line-height: 1.6;
        }

        .small-text {
            color: #6b7280;
            font-size: 12px;
        }

        .unread-dot {
            width: 8px;
            height: 8px;
            background: #198754;
            border-radius: 50%;
            display: inline-block;
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


    <a href="bulk_purchases.php" class="nav-link">

        <i class="fa-solid fa-layer-group"></i>

        Bulk Purchases

    </a>


    <a href="my_participations.php" class="nav-link">

        <i class="fa-solid fa-users"></i>

        My Participations

    </a>


    <a href="payment_history.php" class="nav-link">

        <i class="fa-solid fa-money-bill-wave"></i>

        Payment History

    </a>


    <a href="notifications.php" class="nav-link active">

        <i class="fa-solid fa-bell"></i>

        Notifications

        <?php if ($unread_count > 0): ?>

            <span class="badge bg-danger ms-1">

                <?php echo $unread_count; ?>

            </span>

        <?php endif; ?>

    </a>


    <a href="profile.php" class="nav-link">

        <i class="fa-solid fa-user"></i>

        Profile

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

            <i class="fa-solid fa-user text-success"></i>

            <?php echo htmlspecialchars($student_name); ?>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">


        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h3 class="fw-bold mb-1">
                    Notifications
                </h3>

                <p class="text-muted mb-0">
                    Stay updated with your bulk purchase activities.
                </p>

            </div>


            <?php if ($unread_count > 0): ?>

                <a
                    href="notifications.php?read_all=1"
                    class="btn btn-outline-success"
                >

                    <i class="fa-solid fa-check-double"></i>

                    Mark All as Read

                </a>

            <?php endif; ?>

        </div>


        <!-- NOTIFICATION LIST -->

        <div class="page-card">

            <div class="page-header">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h5 class="fw-bold mb-1">

                            <i class="fa-solid fa-bell text-success"></i>

                            Your Notifications

                        </h5>

                        <div class="small-text">

                            <?php echo count($notifications); ?>
                            total notification(s)

                        </div>

                    </div>


                    <?php if ($unread_count > 0): ?>

                        <span class="badge bg-success">

                            <?php echo $unread_count; ?>

                            Unread

                        </span>

                    <?php else: ?>

                        <span class="badge bg-secondary">

                            All Read

                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <?php if (count($notifications) > 0): ?>


                <?php foreach ($notifications as $notification): ?>

                    <div
                        class="notification
                        <?php
                        echo $notification["is_read"] == 0
                            ? "unread"
                            : "";
                        ?>"
                    >

                        <div class="d-flex gap-3">


                            <!-- ICON -->

                            <div class="notification-icon">

                                <?php if ($notification["type"] === "payment"): ?>

                                    <i class="fa-solid fa-money-bill-wave"></i>

                                <?php elseif (
                                    $notification["type"] === "order_update"
                                ): ?>

                                    <i class="fa-solid fa-box-open"></i>

                                <?php elseif (
                                    $notification["type"] === "alert"
                                ): ?>

                                    <i class="fa-solid fa-triangle-exclamation"></i>

                                <?php else: ?>

                                    <i class="fa-solid fa-bell"></i>

                                <?php endif; ?>

                            </div>


                            <!-- CONTENT -->

                            <div class="flex-grow-1">


                                <div class="d-flex justify-content-between align-items-start">

                                    <div>

                                        <div class="notification-title">

                                            <?php
                                            echo htmlspecialchars(
                                                $notification["title"]
                                            );
                                            ?>

                                            <?php if (
                                                $notification["is_read"] == 0
                                            ): ?>

                                                <span class="unread-dot ms-2"></span>

                                            <?php endif; ?>

                                        </div>

                                        <div class="small-text mt-1">

                                            <i class="fa-regular fa-clock"></i>

                                            <?php

                                            echo date(
                                                "d M Y, h:i A",
                                                strtotime(
                                                    $notification["created_at"]
                                                )
                                            );

                                            ?>

                                        </div>

                                    </div>


                                    <?php if (
                                        $notification["is_read"] == 0
                                    ): ?>

                                        <a
                                            href="notifications.php?read=<?php echo $notification["notification_id"]; ?>"
                                            class="btn btn-sm btn-outline-success"
                                        >

                                            <i class="fa-solid fa-check"></i>

                                            Mark as Read

                                        </a>

                                    <?php endif; ?>

                                </div>


                                <div class="notification-message mt-3">

                                    <?php

                                    echo nl2br(
                                        htmlspecialchars(
                                            $notification["message"]
                                        )
                                    );

                                    ?>

                                </div>


                                <div class="mt-3">

                                    <?php

                                    $type_label = ucfirst(
                                        str_replace(
                                            "_",
                                            " ",
                                            $notification["type"]
                                        )
                                    );

                                    ?>

                                    <span class="badge bg-light text-dark">

                                        <?php
                                        echo htmlspecialchars($type_label);
                                        ?>

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <div class="text-center py-5">

                    <i
                        class="fa-solid fa-bell-slash fa-3x text-muted mb-3"
                    ></i>

                    <h5>
                        No Notifications
                    </h5>

                    <p class="text-muted mb-0">

                        You don't have any notifications at the moment.

                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>