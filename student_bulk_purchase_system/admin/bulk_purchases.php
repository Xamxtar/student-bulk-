<?php

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| Admin Access Check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Get Admin Information
|--------------------------------------------------------------------------
*/

$user_id = $_SESSION["user_id"];

$admin_stmt = $conn->prepare("
    SELECT admin_id, first_name, last_name, role
    FROM admins
    WHERE user_id = ?
    LIMIT 1
");

$admin_stmt->bind_param("i", $user_id);
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

$admin_name = $admin["first_name"] . " " . $admin["last_name"];


/*
|--------------------------------------------------------------------------
| Create Bulk Purchase
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_bulk_purchase"])) {

    $product_id = intval($_POST["product_id"] ?? 0);
    $target_quantity = intval($_POST["target_quantity"] ?? 0);
    $bulk_price = floatval($_POST["bulk_price"] ?? 0);
    $start_date = trim($_POST["start_date"] ?? "");
    $closing_date = trim($_POST["closing_date"] ?? "");

    /*
    | Basic validation
    */

    if (
        $product_id <= 0 ||
        $target_quantity <= 0 ||
        $bulk_price <= 0 ||
        empty($start_date) ||
        empty($closing_date)
    ) {
        header("Location: bulk_purchases.php?error=invalid");
        exit();
    }


    /*
    | Make sure closing date is after start date
    */

    if (strtotime($closing_date) <= strtotime($start_date)) {
        header("Location: bulk_purchases.php?error=date");
        exit();
    }


    /*
    | Get product information
    */

    $product_stmt = $conn->prepare("
        SELECT
            product_id,
            name,
            min_bulk_quantity,
            status
        FROM products
        WHERE product_id = ?
        LIMIT 1
    ");

    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();

    $product_result = $product_stmt->get_result();

    if ($product_result->num_rows !== 1) {
        header("Location: bulk_purchases.php?error=product");
        exit();
    }

    $product = $product_result->fetch_assoc();


    /*
    | Product must be available
    */

    if ($product["status"] !== "available") {
        header("Location: bulk_purchases.php?error=unavailable");
        exit();
    }


    /*
    | Target quantity must meet product minimum bulk quantity
    */

    if ($target_quantity < $product["min_bulk_quantity"]) {
        header("Location: bulk_purchases.php?error=minimum");
        exit();
    }


    /*
    | Create bulk purchase
    */

    $stmt = $conn->prepare("
        INSERT INTO bulk_purchases
        (
            product_id,
            created_by,
            target_quantity,
            current_quantity,
            bulk_price,
            start_date,
            closing_date,
            status
        )
        VALUES (?, ?, ?, 0, ?, ?, ?, 'open')
    ");

    $stmt->bind_param(
        "iiidss",
        $product_id,
        $admin_id,
        $target_quantity,
        $bulk_price,
        $start_date,
        $closing_date
    );

    if ($stmt->execute()) {

        header("Location: bulk_purchases.php?success=created");
        exit();

    } else {

        header("Location: bulk_purchases.php?error=database");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| Update Bulk Purchase Status
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_status"])
) {

    $bulk_purchase_id = intval($_POST["bulk_purchase_id"] ?? 0);
    $new_status = $_POST["new_status"] ?? "";

    $allowed_statuses = [
        "closed",
        "cancelled"
    ];

    if (
        $bulk_purchase_id <= 0 ||
        !in_array($new_status, $allowed_statuses, true)
    ) {
        header("Location: bulk_purchases.php?error=invalid");
        exit();
    }


    /*
    | Lock the bulk purchase before changing status
    */

    $conn->begin_transaction();

    try {

        $lock_stmt = $conn->prepare("
            SELECT
                bulk_purchase_id,
                status
            FROM bulk_purchases
            WHERE bulk_purchase_id = ?
            FOR UPDATE
        ");

        $lock_stmt->bind_param(
            "i",
            $bulk_purchase_id
        );

        $lock_stmt->execute();

        $lock_result = $lock_stmt->get_result();

        if ($lock_result->num_rows !== 1) {
            throw new Exception("Bulk purchase not found.");
        }

        $bulk = $lock_result->fetch_assoc();


        /*
        | Only open purchases can be manually
        | closed or cancelled from this page.
        */

        if ($bulk["status"] !== "open") {
            throw new Exception(
                "This bulk purchase cannot be changed from its current status."
            );
        }


        /*
        | Update status
        */

        $update_stmt = $conn->prepare("
            UPDATE bulk_purchases
            SET status = ?
            WHERE bulk_purchase_id = ?
        ");

        $update_stmt->bind_param(
            "si",
            $new_status,
            $bulk_purchase_id
        );

        if (!$update_stmt->execute()) {
            throw new Exception("Unable to update status.");
        }


        $conn->commit();

        header("Location: bulk_purchases.php?success=status");
        exit();

    } catch (Exception $e) {

        $conn->rollback();

        header("Location: bulk_purchases.php?error=status");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| Get Products
|--------------------------------------------------------------------------
*/

$products = [];

$product_result = $conn->query("
    SELECT
        product_id,
        name,
        category,
        unit_price,
        min_bulk_quantity
    FROM products
    WHERE status = 'available'
    ORDER BY name ASC
");

if ($product_result) {

    while ($row = $product_result->fetch_assoc()) {
        $products[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Get Bulk Purchases
|--------------------------------------------------------------------------
*/

$bulk_purchases = [];

$bulk_result = $conn->query("
    SELECT
        bp.bulk_purchase_id,
        bp.product_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.start_date,
        bp.closing_date,
        bp.status,
        bp.created_at,

        p.name AS product_name,
        p.category,

        COUNT(
            CASE
                WHEN bpp.status != 'cancelled'
                THEN bpp.participant_id
            END
        ) AS participant_count

    FROM bulk_purchases bp

    INNER JOIN products p
        ON bp.product_id = p.product_id

    LEFT JOIN bulk_purchase_participants bpp
        ON bp.bulk_purchase_id = bpp.bulk_purchase_id

    GROUP BY
        bp.bulk_purchase_id,
        bp.product_id,
        bp.target_quantity,
        bp.current_quantity,
        bp.bulk_price,
        bp.start_date,
        bp.closing_date,
        bp.status,
        bp.created_at,
        p.name,
        p.category

    ORDER BY bp.created_at DESC
");

if ($bulk_result) {

    while ($row = $bulk_result->fetch_assoc()) {
        $bulk_purchases[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/

$total_bulk_purchases = count($bulk_purchases);

$open_count = 0;
$target_reached_count = 0;
$processing_count = 0;
$completed_count = 0;

foreach ($bulk_purchases as $bulk) {

    if ($bulk["status"] === "open") {
        $open_count++;
    }

    if ($bulk["status"] === "target_reached") {
        $target_reached_count++;
    }

    if ($bulk["status"] === "processing") {
        $processing_count++;
    }

    if ($bulk["status"] === "delivered") {
        $completed_count++;
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

    <title>Bulk Purchases - Admin</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        body {
            background: #f5f7fa;
            font-family: Arial, sans-serif;
        }


        /* Sidebar */

        .sidebar {
            width: 250px;
            height: 100vh;

            position: fixed;

            left: 0;
            top: 0;

            background: #ffffff;

            border-right: 1px solid #e5e7eb;

            padding: 20px 15px;

            overflow-y: auto;
        }


        .sidebar h4 {
            color: #198754;

            font-weight: 700;

            margin-bottom: 30px;
        }


        .sidebar a {
            display: block;

            text-decoration: none;

            color: #374151;

            padding: 12px 15px;

            margin-bottom: 5px;

            border-radius: 8px;
        }


        .sidebar a:hover,
        .sidebar a.active {
            background: #198754;

            color: #ffffff;
        }


        .sidebar i {
            width: 25px;
        }


        /* Main */

        .main {
            margin-left: 250px;

            padding: 25px;
        }


        /* Topbar */

        .topbar {
            background: #ffffff;

            padding: 15px 20px;

            border-radius: 12px;

            margin-bottom: 25px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.04);
        }


        .page-title {
            font-weight: 700;

            color: #1f2937;
        }


        /* Cards */

        .summary-card {
            background: #ffffff;

            border: none;

            border-radius: 12px;

            padding: 20px;

            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.05);
        }


        .summary-icon {
            width: 45px;
            height: 45px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #e8f5ee;

            color: #198754;

            font-size: 20px;
        }


        .summary-number {
            font-size: 24px;

            font-weight: 700;

            color: #1f2937;
        }


        .summary-label {
            color: #6b7280;

            font-size: 14px;
        }


        /* Main Card */

        .card {
            border: none;

            border-radius: 12px;

            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.05);
        }


        /* Table */

        .table th {
            background: #f8f9fa;

            white-space: nowrap;
        }


        .table td {
            vertical-align: middle;
        }


        /* Progress */

        .progress {
            height: 8px;

            border-radius: 10px;
        }


        /* Status */

        .status-badge {
            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;
        }


        .status-open {
            background: #d1e7dd;

            color: #0f5132;
        }


        .status-target_reached {
            background: #cff4fc;

            color: #055160;
        }


        .status-payment {
            background: #fff3cd;

            color: #664d03;
        }


        .status-confirmed {
            background: #cfe2ff;

            color: #084298;
        }


        .status-processing {
            background: #e2e3e5;

            color: #41464b;
        }


        .status-shipped {
            background: #cfe2ff;

            color: #084298;
        }


        .status-delivered {
            background: #d1e7dd;

            color: #0f5132;
        }


        .status-closed {
            background: #e2e3e5;

            color: #41464b;
        }


        .status-cancelled {
            background: #f8d7da;

            color: #842029;
        }


        @media (max-width: 900px) {

            .sidebar {
                width: 70px;
            }

            .sidebar h4,
            .sidebar span {
                display: none;
            }

            .sidebar a {
                text-align: center;
            }

            .sidebar i {
                width: auto;
            }

            .main {
                margin-left: 70px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">


    <h4>

        <i class="fa-solid fa-cart-shopping"></i>

        <span>
            Bulk Purchase
        </span>

    </h4>


    <a href="dashboard.php">

        <i class="fa-solid fa-gauge"></i>

        <span>
            Dashboard
        </span>

    </a>


    <a href="students.php">

        <i class="fa-solid fa-users"></i>

        <span>
            Students
        </span>

    </a>


    <a href="products.php">

        <i class="fa-solid fa-box"></i>

        <span>
            Products
        </span>

    </a>


    <a href="suppliers.php">

        <i class="fa-solid fa-truck-field"></i>

        <span>
            Suppliers
        </span>

    </a>


    <a href="bulk_purchases.php" class="active">

        <i class="fa-solid fa-cart-plus"></i>

        <span>
            Bulk Purchases
        </span>

    </a>


    <a href="participants.php">

        <i class="fa-solid fa-user-group"></i>

        <span>
            Participants
        </span>

    </a>


    <a href="payments.php">

        <i class="fa-solid fa-money-bill-wave"></i>

        <span>
            Payments
        </span>

    </a>


    <a href="orders.php">

        <i class="fa-solid fa-boxes-stacked"></i>

        <span>
            Orders
        </span>

    </a>


    <a href="inventory.php">

        <i class="fa-solid fa-warehouse"></i>

        <span>
            Inventory
        </span>

    </a>


    <a href="notifications.php">

        <i class="fa-solid fa-bell"></i>

        <span>
            Notifications
        </span>

    </a>


    <a href="reports.php">

        <i class="fa-solid fa-chart-column"></i>

        <span>
            Reports
        </span>

    </a>


    <hr>


    <a href="../auth/logout.php">

        <i class="fa-solid fa-right-from-bracket"></i>

        <span>
            Logout
        </span>

    </a>


</div>



<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main">


    <!-- Topbar -->

    <div class="topbar">

        <div>

            <div class="page-title">
                Bulk Purchase Management
            </div>

            <small class="text-muted">
                Create and monitor student group purchases
            </small>

        </div>


        <div>

            <i class="fa-solid fa-user-shield text-success"></i>

            <?php
            echo htmlspecialchars($admin_name);
            ?>

        </div>

    </div>



    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if (isset($_GET["success"])): ?>

        <div class="alert alert-success">

            <i class="fa-solid fa-circle-check"></i>

            <?php

            if ($_GET["success"] === "created") {

                echo "Bulk purchase created successfully.";

            } elseif ($_GET["success"] === "status") {

                echo "Bulk purchase status updated successfully.";

            }

            ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php

            if ($_GET["error"] === "invalid") {

                echo "Please provide valid information.";

            } elseif ($_GET["error"] === "date") {

                echo "Closing date must be after the start date.";

            } elseif ($_GET["error"] === "product") {

                echo "The selected product could not be found.";

            } elseif ($_GET["error"] === "unavailable") {

                echo "The selected product is currently unavailable.";

            } elseif ($_GET["error"] === "minimum") {

                echo "The target quantity is below the product's minimum bulk quantity.";

            } elseif ($_GET["error"] === "status") {

                echo "The bulk purchase status could not be changed.";

            } else {

                echo "An error occurred while processing the request.";

            }

            ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         SUMMARY CARDS
    ====================================================== -->

    <div class="row g-3 mb-4">


        <div class="col-md-3">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-number">

                            <?php
                            echo $total_bulk_purchases;
                            ?>

                        </div>

                        <div class="summary-label">
                            Total Purchases
                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-cart-shopping"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-md-3">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-number">

                            <?php
                            echo $open_count;
                            ?>

                        </div>

                        <div class="summary-label">
                            Open
                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-door-open"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-md-3">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-number">

                            <?php
                            echo $target_reached_count;
                            ?>

                        </div>

                        <div class="summary-label">
                            Target Reached
                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-bullseye"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-md-3">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-number">

                            <?php
                            echo $completed_count;
                            ?>

                        </div>

                        <div class="summary-label">
                            Delivered
                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-circle-check"></i>

                    </div>

                </div>

            </div>

        </div>


    </div>



    <!-- =====================================================
         CREATE BUTTON
    ====================================================== -->

    <div class="d-flex justify-content-end mb-3">

        <button
            class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#createBulkPurchaseModal"
        >

            <i class="fa-solid fa-plus"></i>

            Create Bulk Purchase

        </button>

    </div>



    <!-- =====================================================
         BULK PURCHASE TABLE
    ====================================================== -->

    <div class="card">

        <div class="card-body">


            <div class="table-responsive">

                <table class="table table-hover align-middle">


                    <thead>

                        <tr>

                            <th>Product</th>

                            <th>Target</th>

                            <th>Progress</th>

                            <th>Bulk Price</th>

                            <th>Participants</th>

                            <th>Closing Date</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($bulk_purchases)): ?>

                        <tr>

                            <td
                                colspan="8"
                                class="text-center py-5"
                            >

                                <i
                                    class="fa-solid fa-cart-plus fa-2x text-muted mb-2"
                                ></i>

                                <br>

                                No bulk purchases found.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($bulk_purchases as $bulk): ?>


                            <?php

                            $target = (int)$bulk["target_quantity"];

                            $current = (int)$bulk["current_quantity"];

                            $percentage = $target > 0
                                ? ($current / $target) * 100
                                : 0;

                            $percentage = min(
                                100,
                                max(0, $percentage)
                            );

                            ?>


                            <tr>


                                <!-- Product -->

                                <td>

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $bulk["product_name"]
                                        );

                                        ?>

                                    </strong>

                                    <br>

                                    <small class="text-muted">

                                        <?php

                                        echo htmlspecialchars(
                                            $bulk["category"]
                                        );

                                        ?>

                                    </small>

                                </td>



                                <!-- Target -->

                                <td>

                                    <?php
                                    echo number_format($current);
                                    ?>

                                    /

                                    <?php
                                    echo number_format($target);
                                    ?>

                                </td>



                                <!-- Progress -->

                                <td style="min-width: 150px;">

                                    <div
                                        class="progress mb-1"
                                    >

                                        <div
                                            class="progress-bar bg-success"
                                            role="progressbar"
                                            style="width: <?php echo $percentage; ?>%;"
                                        ></div>

                                    </div>


                                    <small class="text-muted">

                                        <?php

                                        echo number_format(
                                            $percentage,
                                            1
                                        );

                                        ?>%

                                    </small>

                                </td>



                                <!-- Price -->

                                <td>

                                    ₦<?php

                                    echo number_format(
                                        $bulk["bulk_price"],
                                        2
                                    );

                                    ?>

                                </td>



                                <!-- Participants -->

                                <td>

                                    <span class="badge bg-primary">

                                        <?php

                                        echo number_format(
                                            $bulk["participant_count"]
                                        );

                                        ?>

                                    </span>

                                </td>



                                <!-- Closing Date -->

                                <td>

                                    <?php

                                    echo date(
                                        "M d, Y",
                                        strtotime(
                                            $bulk["closing_date"]
                                        )
                                    );

                                    ?>

                                    <br>

                                    <small class="text-muted">

                                        <?php

                                        echo date(
                                            "h:i A",
                                            strtotime(
                                                $bulk["closing_date"]
                                            )
                                        );

                                        ?>

                                    </small>

                                </td>



                                <!-- Status -->

                                <td>

                                    <span
                                        class="status-badge status-<?php
                                        echo htmlspecialchars(
                                            $bulk["status"]
                                        );
                                        ?>"
                                    >

                                        <?php

                                        echo ucwords(
                                            str_replace(
                                                "_",
                                                " ",
                                                $bulk["status"]
                                            )
                                        );

                                        ?>

                                    </span>

                                </td>



                                <!-- Action -->

                                <td>

                                    <div class="dropdown">


                                        <button
                                            class="btn btn-sm btn-light"
                                            data-bs-toggle="dropdown"
                                        >

                                            <i
                                                class="fa-solid fa-ellipsis-vertical"
                                            ></i>

                                        </button>


                                        <ul class="dropdown-menu">


                                            <?php if ($bulk["status"] === "open"): ?>


                                                <li>

                                                    <form
                                                        method="POST"
                                                        onsubmit="return confirm('Close this bulk purchase? Students will no longer be able to join it.');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="bulk_purchase_id"
                                                            value="<?php echo $bulk["bulk_purchase_id"]; ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="new_status"
                                                            value="closed"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="update_status"
                                                            class="dropdown-item"
                                                        >

                                                            <i
                                                                class="fa-solid fa-lock text-warning"
                                                            ></i>

                                                            Close Purchase

                                                        </button>

                                                    </form>

                                                </li>


                                                <li>

                                                    <form
                                                        method="POST"
                                                        onsubmit="return confirm('Cancel this bulk purchase?');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="bulk_purchase_id"
                                                            value="<?php echo $bulk["bulk_purchase_id"]; ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="new_status"
                                                            value="cancelled"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="update_status"
                                                            class="dropdown-item text-danger"
                                                        >

                                                            <i
                                                                class="fa-solid fa-xmark"
                                                            ></i>

                                                            Cancel Purchase

                                                        </button>

                                                    </form>

                                                </li>


                                            <?php else: ?>


                                                <li>

                                                    <span
                                                        class="dropdown-item-text text-muted"
                                                    >

                                                        <i
                                                            class="fa-solid fa-circle-info"
                                                        ></i>

                                                        No actions available

                                                    </span>

                                                </li>


                                            <?php endif; ?>


                                        </ul>

                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>



<!-- =========================================================
     CREATE BULK PURCHASE MODAL
========================================================= -->

<div
    class="modal fade"
    id="createBulkPurchaseModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">


            <form method="POST">


                <div class="modal-header">

                    <h5 class="modal-title">

                        <i
                            class="fa-solid fa-cart-plus text-success"
                        ></i>

                        Create Bulk Purchase

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>



                <div class="modal-body">


                    <div class="row">


                        <!-- Product -->

                        <div class="col-md-12 mb-3">

                            <label class="form-label">

                                Product

                            </label>


                            <select
                                name="product_id"
                                id="productSelect"
                                class="form-select"
                                required
                            >

                                <option value="">

                                    Select Product

                                </option>


                                <?php foreach ($products as $product): ?>

                                    <option
                                        value="<?php echo $product["product_id"]; ?>"
                                        data-min="<?php echo $product["min_bulk_quantity"]; ?>"
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $product["name"]
                                        );

                                        ?>

                                        -

                                        ₦<?php

                                        echo number_format(
                                            $product["unit_price"],
                                            2
                                        );

                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>


                            <small
                                id="minimumQuantityText"
                                class="text-muted"
                            >

                                Select a product to see its minimum
                                bulk quantity.

                            </small>

                        </div>



                        <!-- Target Quantity -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Target Quantity

                            </label>


                            <input
                                type="number"
                                name="target_quantity"
                                id="targetQuantity"
                                class="form-control"
                                min="1"
                                required
                            >


                            <small class="text-muted">

                                Total quantity required to complete
                                the group purchase.

                            </small>

                        </div>



                        <!-- Bulk Price -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Bulk Price Per Unit (₦)

                            </label>


                            <input
                                type="number"
                                name="bulk_price"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                required
                            >


                            <small class="text-muted">

                                Price students will pay per unit.

                            </small>

                        </div>



                        <!-- Start Date -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Start Date and Time

                            </label>


                            <input
                                type="datetime-local"
                                name="start_date"
                                id="startDate"
                                class="form-control"
                                required
                            >

                        </div>



                        <!-- Closing Date -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Closing Date and Time

                            </label>


                            <input
                                type="datetime-local"
                                name="closing_date"
                                id="closingDate"
                                class="form-control"
                                required
                            >

                        </div>


                    </div>


                </div>



                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >

                        Cancel

                    </button>


                    <button
                        type="submit"
                        name="create_bulk_purchase"
                        class="btn btn-success"
                    >

                        <i class="fa-solid fa-plus"></i>

                        Create Bulk Purchase

                    </button>


                </div>


            </form>

        </div>

    </div>

</div>



<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<script>

    /*
    |--------------------------------------------------------------------------
    | Show Minimum Bulk Quantity
    |--------------------------------------------------------------------------
    */

    const productSelect =
        document.getElementById("productSelect");

    const minimumQuantityText =
        document.getElementById("minimumQuantityText");

    const targetQuantity =
        document.getElementById("targetQuantity");


    if (productSelect) {

        productSelect.addEventListener("change", function () {

            const selectedOption =
                this.options[this.selectedIndex];

            const minimum =
                selectedOption.getAttribute("data-min");


            if (minimum) {

                minimumQuantityText.textContent =
                    "Minimum bulk quantity: " + minimum;

                targetQuantity.min = minimum;

            } else {

                minimumQuantityText.textContent =
                    "Select a product to see its minimum bulk quantity.";

                targetQuantity.min = 1;

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Dates
    |--------------------------------------------------------------------------
    */

    const startDate =
        document.getElementById("startDate");

    const closingDate =
        document.getElementById("closingDate");


    if (startDate && closingDate) {

        startDate.addEventListener("change", function () {

            closingDate.min = this.value;

        });

    }

</script>


</body>

</html>