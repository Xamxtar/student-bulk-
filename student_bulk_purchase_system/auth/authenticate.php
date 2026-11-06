<?php
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "../config/database.php";
session_start();

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$email = trim($_POST["email"]);
$password = $_POST["password"];

// Find the user
$query = mysqli_prepare(
    $conn,
    "SELECT user_id, email, password, user_type, status
     FROM users
     WHERE email = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($query, "s", $email);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);

// Check if user exists
if (!$user) {
    die("Invalid email or password.");
}

// Check account status
if ($user["status"] !== "active") {
    die("Your account is not active. Please contact the administrator.");
}

// Check password
if (!password_verify($password, $user["password"])) {
    die("Invalid email or password.");
}

// Store login information in session
$_SESSION["user_id"] = $user["user_id"];
$_SESSION["email"] = $user["email"];
$_SESSION["user_type"] = $user["user_type"];

// Redirect according to user type
if ($user["user_type"] === "student") {

    header("Location: ../student/dashboard.php");
    exit();

} elseif ($user["user_type"] === "admin") {

    header("Location: ../admin/dashboard.php");
    exit();

} else {

    session_destroy();
    die("Invalid user type.");

}

?>