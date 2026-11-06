```php
<?php

session_start();

require_once "../config/database.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// Only students
if ($_SESSION["user_type"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

// Check purchase ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: bulk_purchases.php");
    exit();
}

$bulk_purchase_id = (int) $_GET["id"];


// Get student information
$user_id = $_SESSION["user_id"];

$student_query = mysqli_prepare(
    $conn,
    "SELECT student_id, first_name, last_name
     FROM students
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($student_query, "i", $user_id);
mysqli_stmt_execute($student_query);

$student_result = mysqli_stmt_get_result($student_query);
$student = mysqli_fetch_assoc($student_result);

mysqli_stmt_close($student_query);

if (!$student) {
    die("Student profile not found.");
}

$student_id = $student["student_id"];


// Get bulk purchase details
$purchase_query = mysqli_prepare(
    $conn,
    "SELECT
        bp.bulk_purchase_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.start_date,
        bp.closing_date,
        bp.status,

        p.product_id,
        p.name AS product_name,
        p.description,
        p.category,
        p.image_url,
        p.min_bulk_quantity

     FROM bulk_purchases bp

     INNER JOIN products p
        ON bp.product_id = p.product_id

     WHERE bp.bulk_purchase_id = ?

     LIMIT 1"
);

mysqli_stmt_bind_param(
    $purchase_query,
    "i",
    $bulk_purchase_id
);

mysqli_stmt_execute($purchase_query);

$purchase_result = mysqli_stmt_get_result($purchase_query);
$purchase = mysqli_fetch_assoc($purchase_result);

mysqli_stmt_close($purchase_query);


if (!$purchase) {
    die("Bulk purchase not found.");
}


// Calculate remaining quantity
$target_quantity = (int) $purchase["target_quantity"];
$current_quantity = (int) $purchase["current_quantity"];

$remaining_quantity = $target_quantity - $current_quantity;

if ($remaining_quantity < 0) {
    $remaining_quantity = 0;
}


// Calculate progress
if ($target_quantity > 0) {
    $progress = ($current_quantity / $target_quantity) * 100;
} else {
    $progress = 0;
}

$progress = min($progress, 100);


// Check whether student already joined
$participant_query = mysqli_prepare(
    $conn,
    "SELECT quantity, subtotal, status
     FROM bulk_purchase_participants
     WHERE bulk_purchase_id = ?
     AND student_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $participant_query,
    "ii",
    $bulk_purchase_id,
    $student_id
);

mysqli_stmt_execute($participant_query);

$participant_result = mysqli_stmt_get_result($participant_query);
$participant = mysqli_fetch_assoc($participant_result);

mysqli_stmt_close($participant_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo htmlspecialchars($purchase["product_name"]); ?>
        - Bulk Purchase
    </title>

    <!-- Font Awesome -->
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
            background: #f5f7f9;
            color: #333;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e5e5;
            padding: 20px 15px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            margin-bottom: 30px;
            color: #0066cc;
        }

        .logo i {
            font-size: 24px;
            color: #008000;
        }

        .logo h2 {
            font-size: 18px;
        }

        .menu-title {
            font-size: 12px;
            color: #888;
            padding: 0 10px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 12px;
            margin-bottom: 5px;
            text-decoration: none;
            color: #555;
            border-radius: 7px;
        }

        .sidebar a i {
            width: 20px;
            text-align: center;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #eaf4ff;
            color: #0066cc;
        }

        .sidebar .logout {
            position: absolute;
            bottom: 20px;
            left: 15px;
            right: 15px;
            color: #d9534f;
        }

        .sidebar .logout:hover {
            background: #fff1f1;
        }

        /* MAIN */

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        .topbar {
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .topbar h3 {
            color: #333;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-icon {
            width: 40px;
            height: 40px;
            background: #eaf4ff;
            color: #0066cc;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* CONTENT */

        .content {
            padding: 30px;
            max-width: 1100px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #0066cc;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .product-section,
        .join-section {
            background: #ffffff;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            overflow: hidden;
        }

        .product-image {
            height: 300px;
            background: #eef5fb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0066cc;
            font-size: 75px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-content {
            padding: 25px;
        }

        .category {
            display: inline-block;
            padding: 6px 10px;
            background: #eaf4ff;
            color: #0066cc;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .product-content h1 {
            font-size: 25px;
            margin-bottom: 12px;
        }

        .description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .join-section {
            padding: 25px;
        }

        .join-section h2 {
            color: #0066cc;
            margin-bottom: 20px;
        }

        .price-box {
            background: #f5f9ff;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .price-label {
            color: #777;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .price {
            color: #008000;
            font-size: 25px;
            font-weight: bold;
        }

        .purchase-info {
            margin-bottom: 25px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eeeeee;
            font-size: 14px;
        }

        .info-row span:first-child {
            color: #777;
        }

        .progress-container {
            margin: 20px 0;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 7px;
            font-size: 13px;
        }

        .progress-bar {
            height: 10px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: #008000;
        }

        .join-form {
            margin-top: 25px;
        }

        .join-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .quantity-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        .quantity-input:focus {
            outline: none;
            border-color: #0066cc;
        }

        .total-box {
            background: #f5f5f5;
            padding: 15px;
            margin-top: 15px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }

        .total-value {
            color: #008000;
        }

        .join-button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 6px;
            background: #008000;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 18px;
        }

        .join-button:hover {
            background: #006600;
        }

        .join-button:disabled {
            background: #999;
            cursor: not-allowed;
        }

        .notice {
            padding: 13px;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 13px;
            line-height: 1.5;
        }

        .notice-info {
            background: #eaf4ff;
            color: #0066cc;
        }

        .notice-success {
            background: #eaf8ea;
            color: #008000;
        }

        .notice-warning {
            background: #fff8e5;
            color: #856404;
        }

        /* RESPONSIVE */

        @media (max-width: 800px) {

            .details-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                width: 70px;
                padding: 15px 10px;
            }

            .logo h2,
            .menu-title,
            .sidebar a span {
                display: none;
            }

            .logo {
                justify-content: center;
            }

            .sidebar a {
                justify-content: center;
            }

            .sidebar .logout {
                left: 10px;
                right: 10px;
            }

            .main {
                margin-left: 70px;
            }

            .topbar {
                padding: 0 15px;
            }

            .content {
                padding: 20px 15px;
            }

        }

    </style>

</head>

<body>


<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="logo">

        <i class="fa-solid fa-cart-shopping"></i>

        <h2>Student Purchase</h2>

    </div>

    <div class="menu-title">
        Main Menu
    </div>

    <a href="dashboard.php">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="bulk_purchases.php" class="active">
        <i class="fa-solid fa-cart-shopping"></i>
        <span>Bulk Purchases</span>
    </a>

    <a href="my_participations.php">
        <i class="fa-solid fa-users"></i>
        <span>My Purchases</span>
    </a>

    <a href="payment_history.php">
        <i class="fa-solid fa-credit-card"></i>
        <span>Payments</span>
    </a>

    <a href="notifications.php">
        <i class="fa-solid fa-bell"></i>
        <span>Notifications</span>
    </a>

    <div class="menu-title" style="margin-top: 25px;">
        Account
    </div>

    <a href="profile.php">
        <i class="fa-solid fa-user"></i>
        <span>My Profile</span>
    </a>

    <a href="../auth/logout.php" class="logout">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>

</aside>


<!-- MAIN -->

<main class="main">

    <header class="topbar">

        <h3>Bulk Purchase Details</h3>

        <div class="user-info">

            <span>
                <?php
                echo htmlspecialchars(
                    $student["first_name"] . " " . $student["last_name"]
                );
                ?>
            </span>

            <div class="user-icon">
                <i class="fa-solid fa-user"></i>
            </div>

        </div>

    </header>


    <section class="content">

        <a href="bulk_purchases.php" class="back-link">

            <i class="fa-solid fa-arrow-left"></i>

            Back to Bulk Purchases

        </a>


        <div class="details-grid">


            <!-- PRODUCT DETAILS -->

            <div class="product-section">

                <div class="product-image">

                    <?php if (!empty($purchase["image_url"])): ?>

                       <img src="/student_bulk_purchase_system/<?= htmlspecialchars($purchase['image_url']) ?>"
     alt="<?= htmlspecialchars($purchase['product_name']) ?>">
                    <?php else: ?>

                        <i class="fa-solid fa-box"></i>

                    <?php endif; ?>

                </div>


                <div class="product-content">

                    <span class="category">

                        <i class="fa-solid fa-tag"></i>

                        <?php
                        echo htmlspecialchars(
                            $purchase["category"]
                        );
                        ?>

                    </span>


                    <h1>
                        <?php
                        echo htmlspecialchars(
                            $purchase["product_name"]
                        );
                        ?>
                    </h1>


                    <p class="description">

                        <?php
                        echo htmlspecialchars(
                            $purchase["description"]
                        );
                        ?>

                    </p>


                    <div class="info-row">

                        <span>
                            <i class="fa-solid fa-calendar-plus"></i>
                            Start Date
                        </span>

                        <strong>
                            <?php
                            echo date(
                                "d M Y",
                                strtotime($purchase["start_date"])
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="info-row">

                        <span>
                            <i class="fa-solid fa-calendar-xmark"></i>
                            Closing Date
                        </span>

                        <strong>
                            <?php
                            echo date(
                                "d M Y",
                                strtotime($purchase["closing_date"])
                            );
                            ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- JOIN SECTION -->

            <div class="join-section">

                <h2>
                    <i class="fa-solid fa-users"></i>
                    Join Group Purchase
                </h2>


                <div class="price-box">

                    <div class="price-label">
                        Bulk Price Per Unit
                    </div>

                    <div class="price">

                        ₦<?php
                        echo number_format(
                            $purchase["bulk_price"],
                            2
                        );
                        ?>

                    </div>

                </div>


                <div class="purchase-info">

                    <div class="info-row">

                        <span>
                            Target Quantity
                        </span>

                        <strong>
                            <?php echo $target_quantity; ?>
                        </strong>

                    </div>


                    <div class="info-row">

                        <span>
                            Current Quantity
                        </span>

                        <strong>
                            <?php echo $current_quantity; ?>
                        </strong>

                    </div>


                    <div class="info-row">

                        <span>
                            Remaining Quantity
                        </span>

                        <strong>
                            <?php echo $remaining_quantity; ?>
                        </strong>

                    </div>


                    <div class="info-row">

                        <span>
                            Minimum Bulk Quantity
                        </span>

                        <strong>
                            <?php
                            echo (int) $purchase["min_bulk_quantity"];
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="progress-container">

                    <div class="progress-label">

                        <span>
                            Group Progress
                        </span>

                        <span>
                            <?php echo round($progress); ?>%
                        </span>

                    </div>

                    <div class="progress-bar">

                        <div
                            class="progress"
                            style="width: <?php echo $progress; ?>%;"
                        ></div>

                    </div>

                </div>


                <?php if ($participant): ?>

                    <div class="notice notice-success">

                        <i class="fa-solid fa-circle-check"></i>

                        You have already joined this bulk purchase.

                        <br><br>

                        Quantity:
                        <strong>
                            <?php echo $participant["quantity"]; ?>
                        </strong>

                        <br>

                        Subtotal:
                        <strong>
                            ₦<?php
                            echo number_format(
                                $participant["subtotal"],
                                2
                            );
                            ?>
                        </strong>

                    </div>


                <?php elseif ($purchase["status"] === "open" && $remaining_quantity > 0): ?>

                    <form
                        action="join_bulk_purchase.php"
                        method="POST"
                        class="join-form"
                    >

                        <input
                            type="hidden"
                            name="bulk_purchase_id"
                            value="<?php echo $bulk_purchase_id; ?>"
                        >


                        <label for="quantity">
                            Quantity You Want to Purchase
                        </label>


                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            class="quantity-input"
                            min="1"
                            max="<?php echo $remaining_quantity; ?>"
                            value="1"
                            required
                        >


                        <div class="total-box">

                            <span>
                                Estimated Total
                            </span>

                            <span
                                class="total-value"
                                id="totalAmount"
                            >
                                ₦<?php
                                echo number_format(
                                    $purchase["bulk_price"],
                                    2
                                );
                                ?>
                            </span>

                        </div>


                        <div class="notice notice-info">

                            <i class="fa-solid fa-circle-info"></i>

                            You will be able to review your participation
                            before the final payment process.

                        </div>


                        <button
                            type="submit"
                            class="join-button"
                        >

                            <i class="fa-solid fa-users"></i>

                            Join Bulk Purchase

                        </button>

                    </form>


                <?php elseif ($purchase["status"] === "target_reached"): ?>

                    <div class="notice notice-success">

                        <i class="fa-solid fa-circle-check"></i>

                        This bulk purchase has reached its target quantity.
                        New participants cannot join at this time.

                    </div>


                <?php else: ?>

                    <div class="notice notice-warning">

                        <i class="fa-solid fa-triangle-exclamation"></i>

                        This bulk purchase is currently unavailable.

                    </div>

                <?php endif; ?>


            </div>

        </div>

    </section>

</main>


<script>

const quantityInput = document.getElementById("quantity");
const totalAmount = document.getElementById("totalAmount");

const unitPrice = <?php echo (float) $purchase["bulk_price"]; ?>;

if (quantityInput) {

    quantityInput.addEventListener("input", function () {

        let quantity = parseInt(this.value) || 0;

        let total = quantity * unitPrice;

        totalAmount.textContent =
            "₦" + total.toLocaleString("en-NG", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

    });

}

</script>

</body>

</html>
```
