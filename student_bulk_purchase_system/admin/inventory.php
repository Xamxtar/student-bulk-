<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

$admin_user_id = $_SESSION["user_id"];

/* =========================
   GET ADMIN DETAILS
========================= */

$admin_stmt = $conn->prepare("
    SELECT first_name, last_name
    FROM admins
    WHERE user_id = ?
    LIMIT 1
");

$admin_stmt->bind_param("i", $admin_user_id);
$admin_stmt->execute();

$admin_result = $admin_stmt->get_result();
$admin = $admin_result->fetch_assoc();

$admin_name = $admin
    ? $admin["first_name"] . " " . $admin["last_name"]
    : "Administrator";


/* =========================
   UPDATE INVENTORY
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $inventory_id = intval($_POST["inventory_id"] ?? 0);
    $quantity = intval($_POST["quantity"] ?? -1);
    $reorder_level = intval($_POST["reorder_level"] ?? -1);

    if ($inventory_id <= 0 || $quantity < 0 || $reorder_level < 0) {

        header("Location: inventory.php?error=invalid");
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE inventory
        SET
            quantity_in_stock = ?,
            reorder_level = ?,
            last_restocked = NOW()
        WHERE inventory_id = ?
    ");

    $stmt->bind_param(
        "iii",
        $quantity,
        $reorder_level,
        $inventory_id
    );

    if ($stmt->execute()) {

        header("Location: inventory.php?success=updated");
        exit();

    } else {

        header("Location: inventory.php?error=update");
        exit();
    }
}


/* =========================
   GET INVENTORY
========================= */

$sql = "
    SELECT
        i.inventory_id,
        i.product_id,
        i.quantity_in_stock,
        i.reorder_level,
        i.last_restocked,
        i.updated_at,

        p.name AS product_name,
        p.category,
        p.unit_price,
        p.status AS product_status

    FROM inventory i

    INNER JOIN products p
        ON i.product_id = p.product_id

    ORDER BY p.name ASC
";

$result = $conn->query($sql);

$inventory = [];

$total_products = 0;
$total_stock = 0;
$low_stock = 0;
$out_of_stock = 0;

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $inventory[] = $row;

        $total_products++;

        $total_stock += $row["quantity_in_stock"];

        if ($row["quantity_in_stock"] == 0) {

            $out_of_stock++;

        } elseif (
            $row["quantity_in_stock"] <= $row["reorder_level"]
        ) {

            $low_stock++;
        }
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

    <title>Inventory | Admin</title>

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
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding-top: 20px;
            overflow-y: auto;
        }

        .brand {
            padding: 15px 25px 25px;
            color: #198754;
            font-size: 20px;
            font-weight: bold;
        }

        .nav-link {
            color: #374151;
            padding: 12px 25px;
            margin: 3px 10px;
            border-radius: 8px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #e8f5ee;
            color: #198754;
        }

        .nav-link i {
            width: 25px;
        }

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        .topbar {
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .content {
            padding: 30px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            height: 100%;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: #e8f5ee;
            color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-number {
            font-size: 25px;
            font-weight: bold;
            margin-top: 10px;
        }

        .table-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .table-card-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table th {
            background: #f8fafc;
            white-space: nowrap;
            font-size: 13px;
        }

        .table td {
            vertical-align: middle;
            white-space: nowrap;
            font-size: 13px;
        }

        .small-text {
            color: #6b7280;
            font-size: 12px;
        }

        .stock-number {
            font-size: 18px;
            font-weight: bold;
        }

    </style>

</head>

<body>


<!-- SIDEBAR -->

<div class="sidebar">

    <div class="brand">
        <i class="fa-solid fa-cart-shopping"></i>
        Bulk Purchase
    </div>

    <a href="dashboard.php" class="nav-link">
        <i class="fa-solid fa-gauge"></i>
        Dashboard
    </a>

    <a href="students.php" class="nav-link">
        <i class="fa-solid fa-user-graduate"></i>
        Students
    </a>

    <a href="products.php" class="nav-link">
        <i class="fa-solid fa-box"></i>
        Products
    </a>

    <a href="suppliers.php" class="nav-link">
        <i class="fa-solid fa-truck-field"></i>
        Suppliers
    </a>

    <a href="bulk_purchases.php" class="nav-link">
        <i class="fa-solid fa-layer-group"></i>
        Bulk Purchases
    </a>

    <a href="participants.php" class="nav-link">
        <i class="fa-solid fa-users"></i>
        Participants
    </a>

    <a href="payments.php" class="nav-link">
        <i class="fa-solid fa-money-bill-wave"></i>
        Payments
    </a>

    <a href="orders.php" class="nav-link">
        <i class="fa-solid fa-box-open"></i>
        Orders
    </a>

    <a href="inventory.php" class="nav-link active">
        <i class="fa-solid fa-warehouse"></i>
        Inventory
    </a>

    <a href="notifications.php" class="nav-link">
        <i class="fa-solid fa-bell"></i>
        Notifications
    </a>

    <a href="reports.php" class="nav-link">
        <i class="fa-solid fa-chart-column"></i>
        Reports
    </a>

    <hr>

    <a href="../auth/logout.php" class="nav-link text-danger">
        <i class="fa-solid fa-right-from-bracket"></i>
        Logout
    </a>

</div>


<!-- MAIN -->

<div class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <strong>
            Inventory Management
        </strong>

        <div>

            <i class="fa-solid fa-user-shield text-success"></i>

            <?php echo htmlspecialchars($admin_name); ?>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">

        <div class="mb-4">

            <h3 class="fw-bold">
                Inventory
            </h3>

            <p class="text-muted mb-0">
                Monitor product stock levels and update inventory quantities.
            </p>

        </div>


        <?php if (isset($_GET["success"])): ?>

            <div class="alert alert-success">

                <i class="fa-solid fa-circle-check"></i>

                Inventory updated successfully.

            </div>

        <?php endif; ?>


        <?php if (isset($_GET["error"])): ?>

            <div class="alert alert-danger">

                <i class="fa-solid fa-circle-exclamation"></i>

                Unable to update inventory. Please check the information provided.

            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <div class="row g-4 mb-4">

            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>

                    <div class="small-text">
                        Products in Inventory
                    </div>

                    <div class="stat-number">
                        <?php echo $total_products; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-cubes"></i>
                    </div>

                    <div class="small-text">
                        Total Stock
                    </div>

                    <div class="stat-number">
                        <?php echo $total_stock; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>

                    <div class="small-text">
                        Low Stock
                    </div>

                    <div class="stat-number text-warning">
                        <?php echo $low_stock; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>

                    <div class="small-text">
                        Out of Stock
                    </div>

                    <div class="stat-number text-danger">
                        <?php echo $out_of_stock; ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- INVENTORY TABLE -->

        <div class="table-card">

            <div class="table-card-header">

                <h5 class="mb-1">
                    Product Inventory
                </h5>

                <p class="text-muted mb-0">
                    Products currently registered in the inventory.
                </p>

            </div>


            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead>

                        <tr>

                            <th>Product</th>

                            <th>Category</th>

                            <th>Unit Price</th>

                            <th>Stock</th>

                            <th>Reorder Level</th>

                            <th>Status</th>

                            <th>Last Restocked</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($inventory) > 0): ?>

                        <?php foreach ($inventory as $row): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $row["product_name"]
                                        );
                                        ?>
                                    </strong>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["category"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    ₦<?php
                                    echo number_format(
                                        $row["unit_price"],
                                        2
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span class="stock-number">

                                        <?php
                                        echo $row["quantity_in_stock"];
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <?php
                                    echo $row["reorder_level"];
                                    ?>

                                </td>


                                <td>

                                    <?php if ($row["quantity_in_stock"] == 0): ?>

                                        <span class="badge bg-danger">
                                            Out of Stock
                                        </span>

                                    <?php elseif (
                                        $row["quantity_in_stock"]
                                        <=
                                        $row["reorder_level"]
                                    ): ?>

                                        <span class="badge bg-warning text-dark">
                                            Low Stock
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-success">
                                            In Stock
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php
                                    if ($row["last_restocked"]) {

                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $row["last_restocked"]
                                            )
                                        );

                                    } else {

                                        echo "Never";

                                    }
                                    ?>

                                </td>


                                <td>

                                    <button
                                        class="btn btn-sm btn-outline-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editInventory<?php echo $row["inventory_id"]; ?>"
                                    >

                                        <i class="fa-solid fa-pen"></i>

                                        Update

                                    </button>

                                </td>

                            </tr>


                            <!-- UPDATE MODAL -->

                            <div
                                class="modal fade"
                                id="editInventory<?php echo $row["inventory_id"]; ?>"
                                tabindex="-1"
                            >

                                <div class="modal-dialog">

                                    <div class="modal-content">

                                        <form
                                            method="POST"
                                            action="inventory.php"
                                        >

                                            <div class="modal-header">

                                                <h5 class="modal-title">

                                                    Update Inventory

                                                </h5>

                                                <button
                                                    type="button"
                                                    class="btn-close"
                                                    data-bs-dismiss="modal"
                                                ></button>

                                            </div>


                                            <div class="modal-body">

                                                <div class="mb-3">

                                                    <label class="form-label">
                                                        Product
                                                    </label>

                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        value="<?php echo htmlspecialchars($row["product_name"]); ?>"
                                                        readonly
                                                    >

                                                </div>


                                                <div class="mb-3">

                                                    <label class="form-label">
                                                        Quantity in Stock
                                                    </label>

                                                    <input
                                                        type="number"
                                                        name="quantity"
                                                        class="form-control"
                                                        min="0"
                                                        value="<?php echo $row["quantity_in_stock"]; ?>"
                                                        required
                                                    >

                                                </div>


                                                <div class="mb-3">

                                                    <label class="form-label">
                                                        Reorder Level
                                                    </label>

                                                    <input
                                                        type="number"
                                                        name="reorder_level"
                                                        class="form-control"
                                                        min="0"
                                                        value="<?php echo $row["reorder_level"]; ?>"
                                                        required
                                                    >

                                                </div>


                                                <input
                                                    type="hidden"
                                                    name="inventory_id"
                                                    value="<?php echo $row["inventory_id"]; ?>"
                                                >

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
                                                    class="btn btn-success"
                                                >

                                                    <i class="fa-solid fa-floppy-disk"></i>

                                                    Save Changes

                                                </button>

                                            </div>

                                        </form>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="8"
                                class="text-center py-5"
                            >

                                <i
                                    class="fa-solid fa-warehouse fa-2x text-muted mb-3"
                                ></i>

                                <p class="mb-0">
                                    No inventory records found.
                                </p>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>