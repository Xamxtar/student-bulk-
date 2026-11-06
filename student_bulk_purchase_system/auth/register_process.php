<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit();
}

// Get form data
$first_name = trim($_POST["first_name"]);
$last_name = trim($_POST["last_name"]);
$matric_number = trim($_POST["matric_number"]);
$department = trim($_POST["department"]);
$level = (int) $_POST["level"];
$phone_number = trim($_POST["phone_number"]);
$email = trim($_POST["email"]);
$password = $_POST["password"];
$confirm_password = $_POST["confirm_password"];

// Check passwords
if ($password !== $confirm_password) {
    die("Passwords do not match.");
}

// Check if email already exists
$check_email = mysqli_prepare(
    $conn,
    "SELECT user_id FROM users WHERE email = ?"
);

mysqli_stmt_bind_param($check_email, "s", $email);
mysqli_stmt_execute($check_email);
mysqli_stmt_store_result($check_email);

if (mysqli_stmt_num_rows($check_email) > 0) {
    die("This email is already registered.");
}

mysqli_stmt_close($check_email);

// Check if matric number already exists
$check_matric = mysqli_prepare(
    $conn,
    "SELECT student_id FROM students WHERE matric_number = ?"
);

mysqli_stmt_bind_param($check_matric, "s", $matric_number);
mysqli_stmt_execute($check_matric);
mysqli_stmt_store_result($check_matric);

if (mysqli_stmt_num_rows($check_matric) > 0) {
    die("This matric number is already registered.");
}

mysqli_stmt_close($check_matric);

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Start transaction
mysqli_begin_transaction($conn);

try {

    // Insert into users table
    $user_query = mysqli_prepare(
        $conn,
        "INSERT INTO users (email, password, user_type)
         VALUES (?, ?, 'student')"
    );

    mysqli_stmt_bind_param(
        $user_query,
        "ss",
        $email,
        $hashed_password
    );

    if (!mysqli_stmt_execute($user_query)) {
        throw new Exception("Failed to create user account.");
    }

    $user_id = mysqli_insert_id($conn);

    mysqli_stmt_close($user_query);

    // Insert into students table
    $student_query = mysqli_prepare(
        $conn,
        "INSERT INTO students
        (user_id, first_name, last_name, matric_number, department, level, phone_number)
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $student_query,
        "issssis",
        $user_id,
        $first_name,
        $last_name,
        $matric_number,
        $department,
        $level,
        $phone_number
    );

    if (!mysqli_stmt_execute($student_query)) {
        throw new Exception("Failed to create student profile.");
    }

    mysqli_stmt_close($student_query);

    // Save everything
    mysqli_commit($conn);

    echo "Registration successful! You can now log in.";

} catch (Exception $e) {

    // Undo changes if something goes wrong
    mysqli_rollback($conn);

    echo "Registration failed: " . $e->getMessage();
}

mysqli_close($conn);

?>