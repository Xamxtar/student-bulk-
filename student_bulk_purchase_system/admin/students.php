<?php

session_start();

require_once "../config/database.php";

/* =========================
   ADMIN AUTHENTICATION
========================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   GET ADMIN INFORMATION
========================= */

$admin_user_id = $_SESSION["user_id"];

$admin_stmt = $conn->prepare("
    SELECT admin_id, first_name, last_name
    FROM admins
    WHERE user_id = ?
    LIMIT 1
");

$admin_stmt->bind_param("i", $admin_user_id);
$admin_stmt->execute();

$admin_result = $admin_stmt->get_result();
$admin = $admin_result->fetch_assoc();

if (!$admin) {
    session_unset();
    session_destroy();

    header("Location: ../auth/login.php");
    exit();
}

$admin_id = $admin["admin_id"];


/* =========================
   ACTIVATE / DEACTIVATE STUDENT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $user_id = intval($_POST["user_id"] ?? 0);

    if ($user_id > 0 && in_array($action, ["activate", "deactivate"])) {

        $new_status = ($action === "activate") ? "active" : "inactive";

        $stmt = $conn->prepare("
            UPDATE users
            SET status = ?
            WHERE user_id = ?
            AND user_type = 'student'
        ");

        $stmt->bind_param("si", $new_status, $user_id);

        if ($stmt->execute()) {
            header("Location: students.php?success=status");
            exit();
        }

        header("Location: students.php?error=update");
        exit();
    }
}


/* =========================
   SEARCH
========================= */

$search = trim($_GET["search"] ?? "");


/* =========================
   GET STUDENTS
========================= */

if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT
            u.user_id,
            u.email,
            u.status,
            u.created_at,
            s.student_id,
            s.first_name,
            s.last_name,
            s.matric_number,
            s.department,
            s.level,
            s.phone_number
        FROM users u
        INNER JOIN students s
            ON u.user_id = s.user_id
        WHERE u.user_type = 'student'
        AND (
            s.first_name LIKE ?
            OR s.last_name LIKE ?
            OR s.matric_number LIKE ?
            OR s.department LIKE ?
            OR u.email LIKE ?
        )
        ORDER BY s.created_at DESC
    ");

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "sssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            u.user_id,
            u.email,
            u.status,
            u.created_at,
            s.student_id,
            s.first_name,
            s.last_name,
            s.matric_number,
            s.department,
            s.level,
            s.phone_number
        FROM users u
        INNER JOIN students s
            ON u.user_id = s.user_id
        WHERE u.user_type = 'student'
        ORDER BY s.created_at DESC
    ");
}

$stmt->execute();
$students_result = $stmt->get_result();


/* =========================
   STUDENT STATISTICS
========================= */

$total_students = 0;
$active_students = 0;
$inactive_students = 0;

$stats_query = $conn->query("
    SELECT
        COUNT(*) AS total_students,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_students,
        SUM(CASE WHEN status != 'active' THEN 1 ELSE 0 END) AS inactive_students
    FROM users
    WHERE user_type = 'student'
");

if ($stats_query) {
    $stats = $stats_query->fetch_assoc();

    $total_students = $stats["total_students"] ?? 0;
    $active_students = $stats["active_students"] ?? 0;
    $inactive_students = $stats["inactive_students"] ?? 0;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Students - Admin</title>

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
            color: #333;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 22px;
            background: #0b7a53;
            color: white;
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
        }

        .sidebar-header small {
            opacity: 0.9;
        }

        .sidebar a {
            display: block;
            padding: 13px 20px;
            color: #374151;
            text-decoration: none;
            font-size: 14px;
        }

        .sidebar a i {
            width: 25px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #eaf7f1;
            color: #0b7a53;
        }

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }

        .topbar {
            height: 70px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .topbar h5 {
            margin: 0;
            font-weight: 600;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-icon {
            width: 40px;
            height: 40px;
            background: #eaf7f1;
            color: #0b7a53;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .content {
            padding: 30px;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h3 {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .page-title p {
            color: #6b7280;
            margin: 0;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            height: 100%;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eaf7f1;
            color: #0b7a53;
            font-size: 18px;
        }

        .stat-card h4 {
            margin: 12px 0 3px;
            font-weight: 700;
        }

        .stat-card p {
            color: #6b7280;
            margin: 0;
            font-size: 14px;
        }

        .content-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-top: 25px;
            overflow: hidden;
        }

        .card-header-custom {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .search-box {
            max-width: 500px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            margin: 0 !important;
        }

        th {
            background: #f8fafc !important;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            vertical-align: middle;
            font-size: 13px;
        }

        .student-avatar {
            width: 38px;
            height: 38px;
            background: #eaf7f1;
            color: #0b7a53;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #dff7e9;
            color: #087443;
        }

        .status-inactive {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-green {
            background: #0b7a53;
            color: white;
            border: none;
        }

        .btn-green:hover {
            background: #096342;
            color: white;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 45px;
            margin-bottom: 15px;
            color: #9ca3af;
        }

        @media (max-width: 900px) {

            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .topbar {
                padding: 0 15px;
            }

            .content {
                padding: 15px;
            }

        }

    </style>

</head>

<body>


<!-- SIDEBAR -->

<div class="sidebar">

    <div class="sidebar-header">

        <h4>
            <i class="fa-solid fa-cart-shopping"></i>
            Bulk Purchase
        </h4>

        <small>Admin Panel</small>

    </div>


    <a href="dashboard.php">
        <i class="fa-solid fa-gauge"></i>
        Dashboard
    </a>

    <a href="students.php" class="active">
        <i class="fa-solid fa-user-graduate"></i>
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
        <i class="fa-solid fa-layer-group"></i>
        Bulk Purchases
    </a>

    <a href="participants.php">
        <i class="fa-solid fa-users"></i>
        Participants
    </a>

    <a href="payments.php">
        <i class="fa-solid fa-credit-card"></i>
        Payments
    </a>

    <a href="orders.php">
        <i class="fa-solid fa-cart-flatbed"></i>
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

    <hr>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        Logout
    </a>

</div>


<!-- MAIN CONTENT -->

<div class="main-content">


    <!-- TOPBAR -->

    <div class="topbar">

        <h5>
            <i class="fa-solid fa-user-graduate"></i>
            Student Management
        </h5>

        <div class="admin-info">

            <div>
                <strong>
                    <?= htmlspecialchars($admin["first_name"] . " " . $admin["last_name"]) ?>
                </strong>

                <small class="text-muted d-block">
                    Administrator
                </small>
            </div>

            <div class="admin-icon">
                <i class="fa-solid fa-user-shield"></i>
            </div>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">


        <!-- PAGE TITLE -->

        <div class="page-title">

            <h3>Students</h3>

            <p>
                Manage registered students and their account status.
            </p>

        </div>


        <!-- ALERTS -->

        <?php if (isset($_GET["success"]) && $_GET["success"] === "status"): ?>

            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Student account status updated successfully.
            </div>

        <?php endif; ?>


        <?php if (isset($_GET["error"])): ?>

            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                Unable to update the student account.
            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <div class="row g-4">

            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <h4><?= $total_students ?></h4>

                    <p>Total Students</p>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>

                    <h4><?= $active_students ?></h4>

                    <p>Active Students</p>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>

                    <h4><?= $inactive_students ?></h4>

                    <p>Inactive Students</p>

                </div>

            </div>

        </div>


        <!-- STUDENTS TABLE -->

        <div class="content-card">

            <div class="card-header-custom">

                <div class="row align-items-center g-3">

                    <div class="col-md-6">

                        <h5 class="mb-1">
                            <i class="fa-solid fa-user-graduate"></i>
                            Registered Students
                        </h5>

                        <small class="text-muted">
                            View and manage student accounts.
                        </small>

                    </div>


                    <div class="col-md-6">

                        <form method="GET" class="d-flex search-box ms-auto">

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search student..."
                                value="<?= htmlspecialchars($search) ?>"
                            >

                            <button class="btn btn-green ms-2">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                Search
                            </button>

                        </form>

                    </div>

                </div>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>

                            <th>Student</th>

                            <th>Matric Number</th>

                            <th>Department</th>

                            <th>Level</th>

                            <th>Phone</th>

                            <th>Email</th>

                            <th>Status</th>

                            <th>Registered</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if ($students_result->num_rows > 0): ?>

                        <?php while ($student = $students_result->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <div class="d-flex align-items-center gap-2">

                                        <div class="student-avatar">

                                            <?= strtoupper(
                                                substr($student["first_name"], 0, 1) .
                                                substr($student["last_name"], 0, 1)
                                            ) ?>

                                        </div>

                                        <div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $student["first_name"] . " " . $student["last_name"]
                                                ) ?>
                                            </strong>

                                        </div>

                                    </div>

                                </td>


                                <td>
                                    <?= htmlspecialchars($student["matric_number"]) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars($student["department"]) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars($student["level"]) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars($student["phone_number"]) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars($student["email"]) ?>
                                </td>


                                <td>

                                    <?php if ($student["status"] === "active"): ?>

                                        <span class="status-badge status-active">
                                            <i class="fa-solid fa-circle-check"></i>
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge status-inactive">
                                            <i class="fa-solid fa-circle-xmark"></i>
                                            <?= htmlspecialchars(ucfirst($student["status"])) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>
                                    <?= date("d M Y", strtotime($student["created_at"])) ?>
                                </td>


                                <td>

                                    <?php if ($student["status"] === "active"): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Deactivate this student account?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="deactivate"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $student["user_id"] ?>"
                                            >

                                            <button class="btn btn-sm btn-outline-danger">

                                                <i class="fa-solid fa-user-slash"></i>

                                                Deactivate

                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Activate this student account?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="activate"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $student["user_id"] ?>"
                                            >

                                            <button class="btn btn-sm btn-outline-success">

                                                <i class="fa-solid fa-user-check"></i>

                                                Activate

                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="9">

                                <div class="empty-state">

                                    <i class="fa-solid fa-user-graduate"></i>

                                    <h5>No students found</h5>

                                    <p>
                                        There are currently no students matching your search.
                                    </p>

                                </div>

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
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>