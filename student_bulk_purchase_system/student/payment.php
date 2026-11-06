<?php

session_start();

require_once "../config/database.php";

// Check login
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

$participant_id = isset($_GET["participant_id"])
    ? (int) $_GET["participant_id"]
    : 0;

if ($participant_id <= 0) {
    header("Location: my_participations.php");
    exit();
}


// Get student
$student_sql = "
    SELECT student_id, first_name, last_name
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
    die("Student account not found.");
}

$student_id = (int) $student["student_id"];


// Get participation details
$sql = "
    SELECT
        bpp.participant_id,
        bpp.bulk_purchase_id,
        bpp.quantity,
        bpp.unit_price,
        bpp.subtotal,
        bpp.status AS participation_status,

        bp.status AS bulk_status,
        bp.target_quantity,
        bp.current_quantity,
        bp.closing_date,

        p.name AS product_name,
        p.category,
        p.image_url,

        pay.payment_id,
        pay.amount_paid,
        pay.payment_method,
        pay.payment_status,
        pay.payment_reference,
        pay.paid_at

    FROM bulk_purchase_participants bpp

    INNER JOIN bulk_purchases bp
        ON bpp.bulk_purchase_id = bp.bulk_purchase_id

    INNER JOIN products p
        ON bp.product_id = p.product_id

    LEFT JOIN payments pay
        ON bpp.participant_id = pay.participant_id

    WHERE bpp.participant_id = ?
    AND bpp.student_id = ?

    ORDER BY pay.payment_id DESC

    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $participant_id,
    $student_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$participation = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$participation) {
    die("Participation record not found.");
}


// Check if already paid
if (
    isset($participation["payment_status"])
    && $participation["payment_status"] === "paid"
) {
    header(
        "Location: my_participations.php?message=already_paid"
    );
    exit();
}


// Process payment submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $payment_method = trim($_POST["payment_method"] ?? "");
    $payment_reference = trim($_POST["payment_reference"] ?? "");

    $allowed_methods = [
        "cash",
        "bank_transfer",
        "card",
        "mobile_money"
    ];

    // Validate payment method
    if (!in_array($payment_method, $allowed_methods, true)) {

        header(
            "Location: payment.php?participant_id=" .
            $participant_id .
            "&error=invalid_method"
        );

        exit();
    }


    // Require payment reference
    if ($payment_reference === "") {

        header(
            "Location: payment.php?participant_id=" .
            $participant_id .
            "&error=reference_required"
        );

        exit();
    }


    // Check for duplicate payment reference
    $reference_sql = "
        SELECT payment_id
        FROM payments
        WHERE payment_reference = ?
        LIMIT 1
    ";

    $reference_stmt = mysqli_prepare(
        $conn,
        $reference_sql
    );

    mysqli_stmt_bind_param(
        $reference_stmt,
        "s",
        $payment_reference
    );

    mysqli_stmt_execute($reference_stmt);

    $reference_result = mysqli_stmt_get_result(
        $reference_stmt
    );

    $existing_reference = mysqli_fetch_assoc(
        $reference_result
    );

    mysqli_stmt_close($reference_stmt);

    if ($existing_reference) {

        header(
            "Location: payment.php?participant_id=" .
            $participant_id .
            "&error=duplicate_reference"
        );

        exit();
    }


    // Payment amount
    $amount = (float) $participation["subtotal"];


    // Insert payment as pending
    $payment_sql = "
        INSERT INTO payments
        (
            participant_id,
            amount_paid,
            payment_method,
            payment_status,
            payment_reference
        )
        VALUES (?, ?, ?, 'pending', ?)
    ";

    $payment_stmt = mysqli_prepare(
        $conn,
        $payment_sql
    );

    mysqli_stmt_bind_param(
        $payment_stmt,
        "idss",
        $participant_id,
        $amount,
        $payment_method,
        $payment_reference
    );

    if (mysqli_stmt_execute($payment_stmt)) {

        mysqli_stmt_close($payment_stmt);

        header(
            "Location: my_participations.php?payment=submitted"
        );

        exit();

    } else {

        mysqli_stmt_close($payment_stmt);

        $error_message = urlencode(
            "Unable to submit payment. Please try again."
        );

        header(
            "Location: payment.php?participant_id=" .
            $participant_id .
            "&error=" .
            $error_message
        );

        exit();
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

    <title>Make Payment</title>

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
            background: #f5f7fa;
            color: #333;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 25px 15px;
        }

        .logo {
            text-align: center;
            color: #0b7a3b;
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 13px;
            text-decoration: none;
            color: #444;
            padding: 13px 15px;
            margin-bottom: 7px;
            border-radius: 8px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #eaf7ef;
            color: #0b7a3b;
        }

        .sidebar a i {
            width: 20px;
            text-align: center;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .topbar {
            background: #ffffff;
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #0b7a3b;
        }

        .topbar h1 {
            font-size: 24px;
            color: #1f2937;
        }

        .topbar p {
            margin-top: 6px;
            color: #777;
        }

        .payment-container {
            max-width: 900px;
            margin: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 25px;
        }

        .card h2 {
            font-size: 19px;
            margin-bottom: 20px;
            color: #1f2937;
        }

        .product {
            display: flex;
            gap: 18px;
            align-items: center;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .product-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            background: #eef2f7;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image i {
            font-size: 28px;
            color: #6b7280;
        }

        .product h3 {
            font-size: 17px;
            margin-bottom: 7px;
        }

        .category {
            display: inline-block;
            background: #eaf2ff;
            color: #1559a6;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 12px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .label {
            color: #777;
        }

        .value {
            font-weight: bold;
        }

        .total {
            color: #0b7a3b;
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            font-size: 14px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        .form-group select:focus,
        .form-group input:focus {
            border-color: #0b7a3b;
        }

        .help-text {
            margin-top: 6px;
            color: #777;
            font-size: 12px;
        }

        .btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #0b7a3b;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            background: #096331;
        }

        .back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #1559a6;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .notice {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .notice-error {
            background: #fdecec;
            color: #b42318;
            border: 1px solid #f5c2c0;
        }

        .notice-info {
            background: #eaf2ff;
            color: #1559a6;
            border: 1px solid #c8dcfa;
        }

        @media (max-width: 850px) {

            .payment-container {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                width: 70px;
                padding: 20px 8px;
            }

            .logo {
                font-size: 0;
            }

            .logo::after {
                content: "SB";
                font-size: 18px;
            }

            .sidebar a span {
                display: none;
            }

            .sidebar a {
                justify-content: center;
            }

            .main {
                margin-left: 70px;
                padding: 15px;
            }

        }

    </style>

</head>

<body>


<!-- Sidebar -->

<div class="sidebar">

    <div class="logo">
        Student Bulk Purchase
    </div>

    <a href="dashboard.php">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="bulk_purchases.php">
        <i class="fa-solid fa-users"></i>
        <span>Bulk Purchases</span>
    </a>

    <a href="my_participations.php">
        <i class="fa-solid fa-cart-shopping"></i>
        <span>My Participations</span>
    </a>

    <a href="payment_history.php" class="active">
        <i class="fa-solid fa-credit-card"></i>
        <span>Payment History</span>
    </a>

    <a href="notifications.php">
        <i class="fa-solid fa-bell"></i>
        <span>Notifications</span>
    </a>

    <a href="profile.php">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>

</div>


<!-- Main -->

<div class="main">

    <div class="topbar">

        <h1>
            <i class="fa-solid fa-credit-card"></i>
            Make Payment
        </h1>

        <p>
            Submit your payment details for this bulk purchase.
        </p>

    </div>


    <a
        href="my_participations.php"
        class="back"
    >
        <i class="fa-solid fa-arrow-left"></i>
        Back to My Participations
    </a>


    <?php if (isset($_GET["error"])): ?>

        <div class="notice notice-error">

            <?php

            $error = $_GET["error"];

            if ($error === "invalid_method") {
                echo "Please select a valid payment method.";
            } elseif ($error === "reference_required") {
                echo "Please enter your payment reference.";
            } elseif ($error === "duplicate_reference") {
                echo "This payment reference has already been used.";
            } else {
                echo htmlspecialchars($error);
            }

            ?>

        </div>

    <?php endif; ?>


    <div class="payment-container">


        <!-- Order Summary -->

        <div class="card">

            <h2>
                <i class="fa-solid fa-box"></i>
                Purchase Summary
            </h2>


            <div class="product">

                <div class="product-image">

                    <?php if (!empty($participation["image_url"])): ?>

                        <img
                            src="/student_bulk_purchase_system/<?php echo htmlspecialchars(
                                $participation["image_url"]
                            ); ?>"
                            alt="Product"
                        >

                    <?php else: ?>

                        <i class="fa-solid fa-box"></i>

                    <?php endif; ?>

                </div>


                <div>

                    <h3>
                        <?php echo htmlspecialchars(
                            $participation["product_name"]
                        ); ?>
                    </h3>

                    <span class="category">
                        <?php echo htmlspecialchars(
                            $participation["category"]
                        ); ?>
                    </span>

                </div>

            </div>


            <div class="summary-row">

                <span class="label">
                    Quantity
                </span>

                <span class="value">
                    <?php echo (int) $participation["quantity"]; ?>
                </span>

            </div>


            <div class="summary-row">

                <span class="label">
                    Unit Price
                </span>

                <span class="value">
                    ₦<?php echo number_format(
                        $participation["unit_price"],
                        2
                    ); ?>
                </span>

            </div>


            <div class="summary-row">

                <span class="label">
                    Amount to Pay
                </span>

                <span class="value total">
                    ₦<?php echo number_format(
                        $participation["subtotal"],
                        2
                    ); ?>
                </span>

            </div>


            <div class="summary-row">

                <span class="label">
                    Group Status
                </span>

                <span class="value">

                    <?php

                    echo htmlspecialchars(
                        ucwords(
                            str_replace(
                                "_",
                                " ",
                                $participation["bulk_status"]
                            )
                        )
                    );

                    ?>

                </span>

            </div>

        </div>


        <!-- Payment Form -->

        <div class="card">

            <h2>
                <i class="fa-solid fa-money-bill-transfer"></i>
                Payment Details
            </h2>


            <form
                method="POST"
                action="payment.php?participant_id=<?php echo $participant_id; ?>"
            >


                <div class="form-group">

                    <label for="payment_method">
                        Payment Method
                    </label>

                    <select
                        name="payment_method"
                        id="payment_method"
                        required
                    >

                        <option value="">
                            Select payment method
                        </option>

                        <option value="bank_transfer">
                            Bank Transfer
                        </option>

                        <option value="cash">
                            Cash
                        </option>

                        <option value="card">
                            Card
                        </option>

                        <option value="mobile_money">
                            Mobile Money
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="payment_reference">
                        Payment Reference
                    </label>

                    <input
                        type="text"
                        name="payment_reference"
                        id="payment_reference"
                        placeholder="Enter payment reference"
                        maxlength="100"
                        required
                    >

                    <p class="help-text">
                        Enter the transaction or payment reference
                        provided by your payment method.
                    </p>

                </div>


                <div class="notice notice-info">

                    <i class="fa-solid fa-circle-info"></i>

                    Your payment will initially be marked as
                    <strong>Pending</strong> and must be verified
                    by an administrator.

                </div>


                <button
                    type="submit"
                    class="btn"
                >
                    <i class="fa-solid fa-paper-plane"></i>
                    Submit Payment
                </button>

            </form>

        </div>

    </div>

</div>

</body>

</html>