<?php

$host = "sql106.infinityfree.com";
$username = "if0_43007812";
$password = "9FunOgXJlec3MbT";
$database = "if0_43007812_student_bulk_purchase_system";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

?>