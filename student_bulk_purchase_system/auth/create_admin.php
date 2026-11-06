<?php

require_once "../config/database.php";

$first_name = "System";
$last_name = "Administrator";
$email = "admin@university.edu";
$password = "admin123";
$phone_number = "08000000000";
$role = "super_admin";

try {

    // Check if admin email already exists
    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        die("Admin account already exists.");
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Start transaction
    $conn->begin_transaction();

    // Insert into users
    $stmt = $conn->prepare("
        INSERT INTO users (email, password, user_type, status)
        VALUES (?, ?, 'admin', 'active')
    ");

    $stmt->bind_param(
        "ss",
        $email,
        $hashed_password
    );

    $stmt->execute();

    $user_id = $conn->insert_id;

    // Insert into admins
    $admin = $conn->prepare("
        INSERT INTO admins
        (user_id, first_name, last_name, role, phone_number)
        VALUES (?, ?, ?, ?, ?)
    ");

    $admin->bind_param(
        "issss",
        $user_id,
        $first_name,
        $last_name,
        $role,
        $phone_number
    );

    $admin->execute();

    $conn->commit();

    echo "Admin account created successfully.<br><br>";
    echo "Email: admin@university.edu<br>";
    echo "Password: admin123<br><br>";
    echo "Delete this file after creating the account.";

} catch (Exception $e) {

    $conn->rollback();

    echo "Error creating admin: " . $e->getMessage();
}
?>