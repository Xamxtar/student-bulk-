```php
<?php

session_start();

require_once "../config/database.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// Only students can access this page
if ($_SESSION["user_type"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

// Get student information
$user_id = $_SESSION["user_id"];

$student_query = mysqli_prepare(
    $conn,
    "SELECT first_name, last_name
     FROM students
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($student_query, "i", $user_id);
mysqli_stmt_execute($student_query);

$student_result = mysqli_stmt_get_result($student_query);
$student = mysqli_fetch_assoc($student_result);

mysqli_stmt_close($student_query);


// Get available bulk purchases
$query = "
    SELECT
        bp.bulk_purchase_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.start_date,
        bp.closing_date,
        bp.status,
        p.name AS product_name,
        p.description,
        p.category,
        p.image_url
    FROM bulk_purchases bp
    INNER JOIN products p
        ON bp.product_id = p.product_id
    WHERE bp.status IN ('open', 'target_reached')
    ORDER BY bp.closing_date ASC
";

$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Bulk Purchases</title>

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
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: #0066cc;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #777;
        }

        /* PURCHASE GRID */

        .purchase-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 22px;
        }

        .purchase-card {
            background: #ffffff;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            overflow: hidden;
        }

        .product-image {
            height: 180px;
            background: #eef5fb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0066cc;
            font-size: 55px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .purchase-body {
            padding: 20px;
        }

        .purchase-body h2 {
            font-size: 19px;
            color: #333;
            margin-bottom: 8px;
        }

        .category {
            display: inline-block;
            padding: 5px 9px;
            background: #eaf4ff;
            color: #0066cc;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .purchase-info {
            border-top: 1px solid #eeeeee;
            padding-top: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .info-item span:first-child {
            color: #777;
        }

        .price {
            color: #008000;
            font-weight: bold;
        }

        .progress-container {
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #777;
            margin-bottom: 6px;
        }

        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: #008000;
            border-radius: 10px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 9px;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .status-open {
            background: #eaf8ea;
            color: #008000;
        }

        .status-reached {
            background: #eaf4ff;
            color: #0066cc;
        }

        .view-button {
            display: block;
            width: 100%;
            padding: 11px;
            text-align: center;
            text-decoration: none;
            background: #0066cc;
            color: white;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
        }

        .view-button:hover {
            background: #0052a3;
        }

        .empty-state {
            background: #ffffff;
            padding: 60px 20px;
            text-align: center;
            border-radius: 10px;
            border: 1px solid #e5e5e5;
        }

        .empty-state i {
            font-size: 45px;
            color: #0066cc;
            margin-bottom: 15px;
        }

        .empty-state h2 {
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #777;
        }

        /* RESPONSIVE */

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

        <h3>Bulk Purchases</h3>

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

        <div class="page-header">

            <h1>Available Bulk Purchases</h1>

            <p>
                Join a group purchase and benefit from bulk pricing.
            </p>

        </div>


        <div class="purchase-grid">

            <?php if (mysqli_num_rows($result) > 0): ?>

                <?php while ($purchase = mysqli_fetch_assoc($result)): ?>

                    <?php

                    $target = (int) $purchase["target_quantity"];
                    $current = (int) $purchase["current_quantity"];

                    if ($target > 0) {
                        $percentage = ($current / $target) * 100;
                    } else {
                        $percentage = 0;
                    }

                    $percentage = min($percentage, 100);

                    ?>

                    <div class="purchase-card">

                        <div class="product-image">

                            <?php if (!empty($purchase["image_url"])): ?>

                                <img
                                    src="/student_bulk_purchase_system/<?php echo htmlspecialchars($purchase["image_url"]); ?>"
                                    alt="<?php echo htmlspecialchars($purchase["product_name"]); ?>"
                                >

                            <?php else: ?>

                                <i class="fa-solid fa-box"></i>

                            <?php endif; ?>

                        </div>


                        <div class="purchase-body">

                            <span class="category">

                                <i class="fa-solid fa-tag"></i>

                                <?php
                                echo htmlspecialchars(
                                    $purchase["category"]
                                );
                                ?>

                            </span>


                            <h2>
                                <?php
                                echo htmlspecialchars(
                                    $purchase["product_name"]
                                );
                                ?>
                            </h2>


                            <p class="description">

                                <?php
                                echo htmlspecialchars(
                                    $purchase["description"]
                                );
                                ?>

                            </p>


                            <?php if ($purchase["status"] === "open"): ?>

                                <span class="status status-open">

                                    <i class="fa-solid fa-circle"></i>

                                    Open

                                </span>

                            <?php else: ?>

                                <span class="status status-reached">

                                    <i class="fa-solid fa-check"></i>

                                    Target Reached

                                </span>

                            <?php endif; ?>


                            <div class="purchase-info">

                                <div class="info-item">

                                    <span>
                                        Bulk Price
                                    </span>

                                    <span class="price">

                                        ₦<?php
                                        echo number_format(
                                            $purchase["bulk_price"],
                                            2
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="info-item">

                                    <span>
                                        Target Quantity
                                    </span>

                                    <span>
                                        <?php
                                        echo $target;
                                        ?>
                                    </span>

                                </div>


                                <div class="info-item">

                                    <span>
                                        Quantity Joined
                                    </span>

                                    <span>
                                        <?php
                                        echo $current;
                                        ?>
                                    </span>

                                </div>


                                <div class="info-item">

                                    <span>
                                        Closing Date
                                    </span>

                                    <span>
                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $purchase["closing_date"]
                                            )
                                        );
                                        ?>
                                    </span>

                                </div>


                                <div class="progress-container">

                                    <div class="progress-label">

                                        <span>
                                            Group Progress
                                        </span>

                                        <span>
                                            <?php
                                            echo round($percentage);
                                            ?>%
                                        </span>

                                    </div>


                                    <div class="progress-bar">

                                        <div
                                            class="progress"
                                            style="width: <?php echo $percentage; ?>%;"
                                        ></div>

                                    </div>

                                </div>


                                <a
                                    href="bulk_purchase_details.php?id=<?php echo $purchase["bulk_purchase_id"]; ?>"
                                    class="view-button"
                                >

                                    <i class="fa-solid fa-eye"></i>

                                    View Details

                                </a>

                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-solid fa-box-open"></i>

                    <h2>No Bulk Purchases Available</h2>

                    <p>
                        There are currently no open group purchases.
                        Please check again later.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

</body>

</html>
```
