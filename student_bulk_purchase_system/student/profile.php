<?php

session_start();

require_once "../config/database.php";

/* =========================
   STUDENT AUTHENTICATION
========================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$success = "";
$error = "";


/* =========================
   GET STUDENT INFORMATION
========================= */

$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.email,
        u.status,
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
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    session_unset();
    session_destroy();

    header("Location: ../auth/login.php");
    exit();
}


/* =========================
   UPDATE PROFILE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $level = intval($_POST["level"] ?? 0);
    $phone_number = trim($_POST["phone_number"] ?? "");

    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($department) ||
        $level < 100 ||
        $level > 600 ||
        empty($phone_number)
    ) {

        $error = "Please fill in all required fields correctly.";

    } else {

        $update_stmt = $conn->prepare("
            UPDATE students
            SET
                first_name = ?,
                last_name = ?,
                department = ?,
                level = ?,
                phone_number = ?
            WHERE user_id = ?
        ");

        $update_stmt->bind_param(
            "sssisi",
            $first_name,
            $last_name,
            $department,
            $level,
            $phone_number,
            $user_id
        );

        if ($update_stmt->execute()) {

            $success = "Profile updated successfully.";

            /* Update displayed student information */
            $student["first_name"] = $first_name;
            $student["last_name"] = $last_name;
            $student["department"] = $department;
            $student["level"] = $level;
            $student["phone_number"] = $phone_number;

        } else {

            $error = "Unable to update your profile. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile - Student</title>

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

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-icon {
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

        .profile-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .profile-header {
            background: #0b7a53;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .profile-avatar {
            width: 85px;
            height: 85px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: white;
            color: #0b7a53;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
        }

        .profile-header h4 {
            margin-bottom: 5px;
        }

        .profile-header p {
            margin: 0;
            opacity: 0.9;
        }

        .profile-body {
            padding: 30px;
        }

        .form-label {
            font-weight: 600;
            font-size: 14px;
        }

        .form-control,
        .form-select {
            min-height: 45px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0b7a53;
            box-shadow: 0 0 0 0.2rem rgba(11, 122, 83, 0.12);
        }

        .readonly-field {
            background: #f3f4f6;
        }

        .btn-green {
            background: #0b7a53;
            color: white;
            border: none;
            padding: 10px 20px;
        }

        .btn-green:hover {
            background: #096342;
            color: white;
        }

        .account-info {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
        }

        .account-info h6 {
            font-weight: 700;
            margin-bottom: 15px;
        }

        .account-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .account-row:last-child {
            border-bottom: none;
        }

        .account-row span:first-child {
            color: #6b7280;
        }

        .status-active {
            color: #087443;
            font-weight: 600;
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

        <small>Student Panel</small>

    </div>


    <a href="dashboard.php">
        <i class="fa-solid fa-gauge"></i>
        Dashboard
    </a>


    <a href="bulk_purchases.php">
        <i class="fa-solid fa-layer-group"></i>
        Bulk Purchases
    </a>


    <a href="my_participations.php">
        <i class="fa-solid fa-users"></i>
        My Participations
    </a>


    <a href="payment_history.php">
        <i class="fa-solid fa-credit-card"></i>
        Payment History
    </a>


    <a href="notifications.php">
        <i class="fa-solid fa-bell"></i>
        Notifications
    </a>


    <a href="profile.php" class="active">
        <i class="fa-solid fa-user"></i>
        My Profile
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
            <i class="fa-solid fa-user"></i>
            My Profile
        </h5>


        <div class="student-info">

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $student["first_name"] . " " . $student["last_name"]
                    ) ?>
                </strong>

                <small class="text-muted d-block">
                    Student
                </small>

            </div>


            <div class="student-icon">

                <i class="fa-solid fa-user-graduate"></i>

            </div>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">


        <div class="page-title">

            <h3>My Profile</h3>

            <p>
                View and update your personal information.
            </p>

        </div>


        <?php if ($success !== ""): ?>

            <div class="alert alert-success">

                <i class="fa-solid fa-circle-check"></i>

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <div class="row">

            <div class="col-lg-8">


                <div class="profile-card">


                    <!-- PROFILE HEADER -->

                    <div class="profile-header">

                        <div class="profile-avatar">

                            <?= strtoupper(
                                substr($student["first_name"], 0, 1) .
                                substr($student["last_name"], 0, 1)
                            ) ?>

                        </div>


                        <h4>

                            <?= htmlspecialchars(
                                $student["first_name"] . " " . $student["last_name"]
                            ) ?>

                        </h4>


                        <p>

                            <?= htmlspecialchars($student["matric_number"]) ?>

                        </p>

                    </div>


                    <!-- PROFILE FORM -->

                    <div class="profile-body">

                        <form method="POST">


                            <div class="row g-4">


                                <div class="col-md-6">

                                    <label class="form-label">
                                        First Name
                                    </label>

                                    <input
                                        type="text"
                                        name="first_name"
                                        class="form-control"
                                        value="<?= htmlspecialchars($student["first_name"]) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Last Name
                                    </label>

                                    <input
                                        type="text"
                                        name="last_name"
                                        class="form-control"
                                        value="<?= htmlspecialchars($student["last_name"]) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Matric Number
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control readonly-field"
                                        value="<?= htmlspecialchars($student["matric_number"]) ?>"
                                        readonly
                                    >

                                    <small class="text-muted">
                                        Matric number cannot be changed here.
                                    </small>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Email Address
                                    </label>

                                    <input
                                        type="email"
                                        class="form-control readonly-field"
                                        value="<?= htmlspecialchars($student["email"]) ?>"
                                        readonly
                                    >

                                    <small class="text-muted">
                                        Email cannot be changed here.
                                    </small>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Department
                                    </label>

                                    <input
                                        type="text"
                                        name="department"
                                        class="form-control"
                                        value="<?= htmlspecialchars($student["department"]) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Level
                                    </label>

                                    <select
                                        name="level"
                                        class="form-select"
                                        required
                                    >

                                        <?php
                                        $levels = [100, 200, 300, 400, 500, 600];

                                        foreach ($levels as $level):
                                        ?>

                                            <option
                                                value="<?= $level ?>"
                                                <?= ($student["level"] == $level) ? "selected" : "" ?>
                                            >
                                                <?= $level ?> Level
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Phone Number
                                    </label>

                                    <input
                                        type="text"
                                        name="phone_number"
                                        class="form-control"
                                        value="<?= htmlspecialchars($student["phone_number"]) ?>"
                                        required
                                    >

                                </div>


                            </div>


                            <div class="mt-4">

                                <button
                                    type="submit"
                                    class="btn btn-green"
                                >

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Changes

                                </button>

                            </div>


                        </form>

                    </div>

                </div>


            </div>


            <!-- ACCOUNT INFORMATION -->

            <div class="col-lg-4">

                <div class="account-info">

                    <h6>
                        <i class="fa-solid fa-circle-info"></i>
                        Account Information
                    </h6>


                    <div class="account-row">

                        <span>Account Status</span>

                        <span class="status-active">

                            <i class="fa-solid fa-circle-check"></i>

                            <?= htmlspecialchars(
                                ucfirst($student["status"])
                            ) ?>

                        </span>

                    </div>


                    <div class="account-row">

                        <span>Student ID</span>

                        <strong>
                            <?= htmlspecialchars($student["student_id"]) ?>
                        </strong>

                    </div>


                    <div class="account-row">

                        <span>Matric Number</span>

                        <strong>
                            <?= htmlspecialchars($student["matric_number"]) ?>
                        </strong>

                    </div>


                    <div class="account-row">

                        <span>Level</span>

                        <strong>
                            <?= htmlspecialchars($student["level"]) ?>
                        </strong>

                    </div>


                    <div class="account-row">

                        <span>Department</span>

                        <strong>
                            <?= htmlspecialchars($student["department"]) ?>
                        </strong>

                    </div>


                </div>

            </div>

        </div>


    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>