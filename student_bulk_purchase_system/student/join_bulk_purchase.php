<?php

session_start();

require_once "../config/database.php";

// Make sure the user is logged in
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

// Make sure the request is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: bulk_purchases.php");
    exit();
}

// Get submitted values
$bulk_purchase_id = isset($_POST["bulk_purchase_id"]) ? (int) $_POST["bulk_purchase_id"] : 0;
$quantity = isset($_POST["quantity"]) ? (int) $_POST["quantity"] : 0;

if ($bulk_purchase_id <= 0 || $quantity <= 0) {
    header("Location: bulk_purchases.php?error=invalid");
    exit();
}

try {

    // Start database transaction
    mysqli_begin_transaction($conn);

    /*
     * Get the student's student_id
     */
    $user_id = (int) $_SESSION["user_id"];

    $student_sql = "
        SELECT student_id
        FROM students
        WHERE user_id = ?
        LIMIT 1
    ";

    $student_stmt = mysqli_prepare($conn, $student_sql);
    mysqli_stmt_bind_param($student_stmt, "i", $user_id);
    mysqli_stmt_execute($student_stmt);

    $student_result = mysqli_stmt_get_result($student_stmt);
    $student = mysqli_fetch_assoc($student_result);

    mysqli_stmt_close($student_stmt);

    if (!$student) {
        throw new Exception("Student account was not found.");
    }

    $student_id = (int) $student["student_id"];


    /*
     * Lock the bulk purchase row.
     *
     * This prevents two students from joining at the same time
     * and causing the target quantity to be exceeded.
     */
    $purchase_sql = "
        SELECT
            bulk_purchase_id,
            product_id,
            target_quantity,
            bulk_price,
            closing_date,
            status
        FROM bulk_purchases
        WHERE bulk_purchase_id = ?
        FOR UPDATE
    ";

    $purchase_stmt = mysqli_prepare($conn, $purchase_sql);
    mysqli_stmt_bind_param($purchase_stmt, "i", $bulk_purchase_id);
    mysqli_stmt_execute($purchase_stmt);

    $purchase_result = mysqli_stmt_get_result($purchase_stmt);
    $purchase = mysqli_fetch_assoc($purchase_result);

    mysqli_stmt_close($purchase_stmt);

    if (!$purchase) {
        throw new Exception("Bulk purchase was not found.");
    }


    /*
     * Check that the bulk purchase is still open.
     */
    if ($purchase["status"] !== "open") {
        throw new Exception("This bulk purchase is no longer open for joining.");
    }


    /*
     * Check closing date.
     */
    if (strtotime($purchase["closing_date"]) < time()) {
        throw new Exception("The closing date for this bulk purchase has passed.");
    }


    /*
     * Check whether this student has already joined.
     */
    $duplicate_sql = "
        SELECT participant_id
        FROM bulk_purchase_participants
        WHERE bulk_purchase_id = ?
        AND student_id = ?
        LIMIT 1
    ";

    $duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);
    mysqli_stmt_bind_param(
        $duplicate_stmt,
        "ii",
        $bulk_purchase_id,
        $student_id
    );

    mysqli_stmt_execute($duplicate_stmt);

    $duplicate_result = mysqli_stmt_get_result($duplicate_stmt);
    $existing_participant = mysqli_fetch_assoc($duplicate_result);

    mysqli_stmt_close($duplicate_stmt);

    if ($existing_participant) {
        throw new Exception("You have already joined this bulk purchase.");
    }


    /*
     * Calculate the actual quantity already committed.
     * Cancelled participation is not counted.
     */
    $sum_sql = "
        SELECT COALESCE(SUM(quantity), 0) AS total_quantity
        FROM bulk_purchase_participants
        WHERE bulk_purchase_id = ?
        AND status != 'cancelled'
    ";

    $sum_stmt = mysqli_prepare($conn, $sum_sql);
    mysqli_stmt_bind_param($sum_stmt, "i", $bulk_purchase_id);
    mysqli_stmt_execute($sum_stmt);

    $sum_result = mysqli_stmt_get_result($sum_stmt);
    $sum_data = mysqli_fetch_assoc($sum_result);

    mysqli_stmt_close($sum_stmt);

    $current_quantity = (int) $sum_data["total_quantity"];

    $target_quantity = (int) $purchase["target_quantity"];

    $remaining_quantity = $target_quantity - $current_quantity;


    /*
     * Make sure the requested quantity does not exceed
     * the remaining quantity.
     */
    if ($quantity > $remaining_quantity) {
        throw new Exception(
            "You can only join with a maximum of " .
            $remaining_quantity .
            " item(s)."
        );
    }


    /*
     * Calculate subtotal.
     */
    $unit_price = (float) $purchase["bulk_price"];
    $subtotal = $quantity * $unit_price;


    /*
     * Insert the student's participation.
     */
    $insert_sql = "
        INSERT INTO bulk_purchase_participants
        (
            bulk_purchase_id,
            student_id,
            quantity,
            unit_price,
            subtotal,
            status
        )
        VALUES (?, ?, ?, ?, ?, 'joined')
    ";

    $insert_stmt = mysqli_prepare($conn, $insert_sql);

    mysqli_stmt_bind_param(
        $insert_stmt,
        "iiidd",
        $bulk_purchase_id,
        $student_id,
        $quantity,
        $unit_price,
        $subtotal
    );

    if (!mysqli_stmt_execute($insert_stmt)) {
        throw new Exception("Unable to join the bulk purchase.");
    }

    mysqli_stmt_close($insert_stmt);


    /*
     * Calculate the new total after the student joins.
     */
    $new_quantity = $current_quantity + $quantity;


    /*
     * Update the cached current_quantity and status.
     */
    if ($new_quantity >= $target_quantity) {

        $update_sql = "
            UPDATE bulk_purchases
            SET
                current_quantity = ?,
                status = 'target_reached'
            WHERE bulk_purchase_id = ?
        ";

    } else {

        $update_sql = "
            UPDATE bulk_purchases
            SET
                current_quantity = ?
            WHERE bulk_purchase_id = ?
        ";
    }

    $update_stmt = mysqli_prepare($conn, $update_sql);

    mysqli_stmt_bind_param(
        $update_stmt,
        "ii",
        $new_quantity,
        $bulk_purchase_id
    );

    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception("Unable to update bulk purchase quantity.");
    }

    mysqli_stmt_close($update_stmt);


    /*
     * Everything was successful.
     */
    mysqli_commit($conn);

    header(
        "Location: bulk_purchase_details.php?id=" .
        $bulk_purchase_id .
        "&joined=1"
    );

    exit();

} catch (Exception $e) {

    /*
     * Undo all database changes if something fails.
     */
    mysqli_rollback($conn);

    header(
        "Location: bulk_purchase_details.php?id=" .
        $bulk_purchase_id .
        "&error=" .
        urlencode($e->getMessage())
    );

    exit();
}

?>