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
    SELECT first_name, last_name, role
    FROM admins
    WHERE user_id = ?
    LIMIT 1
");

$admin_stmt->bind_param("i", $user_id);
$admin_stmt->execute();

$admin_result = $admin_stmt->get_result();
$admin = $admin_result->fetch_assoc();

$admin_name = $admin
    ? $admin["first_name"] . " " . $admin["last_name"]
    : "Administrator";

$upload_dir = __DIR__ . "/../uploads/products/";
$upload_web_path = "uploads/products/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function uploadProductImage(string $field_name, string $upload_dir, string $upload_web_path, bool $required = false): array
{
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]["error"] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return ["success" => false, "error" => "Please select a product image."];
        }
        return ["success" => true, "path" => null];
    }

    $file = $_FILES[$field_name];

    if ($file["error"] !== UPLOAD_ERR_OK) {
        return ["success" => false, "error" => "The image upload failed."];
    }

    if ($file["size"] > 5 * 1024 * 1024) {
        return ["success" => false, "error" => "Product image must not exceed 5 MB."];
    }

    $image_info = @getimagesize($file["tmp_name"]);
    if ($image_info === false) {
        return ["success" => false, "error" => "The selected file is not a valid image."];
    }

    $allowed_types = [
        IMAGETYPE_JPEG => "jpg",
        IMAGETYPE_PNG  => "png",
        IMAGETYPE_WEBP => "webp",
        IMAGETYPE_GIF  => "gif"
    ];

    $image_type = $image_info[2] ?? 0;

    if (!isset($allowed_types[$image_type])) {
        return ["success" => false, "error" => "Only JPG, PNG, WEBP, and GIF images are allowed."];
    }

    $extension = $allowed_types[$image_type];
    $filename = "product_" . bin2hex(random_bytes(8)) . "." . $extension;
    $destination = $upload_dir . $filename;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        return ["success" => false, "error" => "Unable to save the product image."];
    }

    return ["success" => true, "path" => $upload_web_path . $filename];
}


/*
|--------------------------------------------------------------------------
| Add Product
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_product"])) {

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $unit_price = floatval($_POST["unit_price"] ?? 0);
    $min_bulk_quantity = intval($_POST["min_bulk_quantity"] ?? 0);
    $supplier_id = intval($_POST["supplier_id"] ?? 0);

    if (
        empty($name) ||
        empty($category) ||
        $unit_price <= 0 ||
        $min_bulk_quantity <= 0 ||
        $supplier_id <= 0
    ) {
        header("Location: products.php?error=invalid");
        exit();
    }

    $image = uploadProductImage("product_image", $upload_dir, $upload_web_path, true);

    if (!$image["success"]) {
        header("Location: products.php?error=image&message=" . urlencode($image["error"]));
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO products
        (
            supplier_id,
            name,
            description,
            category,
            unit_price,
            min_bulk_quantity,
            image_url,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
    ");

    $stmt->bind_param(
        "isssdis",
        $supplier_id,
        $name,
        $description,
        $category,
        $unit_price,
        $min_bulk_quantity,
        $image["path"]
    );

    if ($stmt->execute()) {

        $product_id = $stmt->insert_id;

        $inventory_stmt = $conn->prepare("
            INSERT INTO inventory
            (
                product_id,
                quantity_in_stock,
                reorder_level
            )
            VALUES (?, 0, 10)
        ");

        $inventory_stmt->bind_param("i", $product_id);
        $inventory_stmt->execute();

        header("Location: products.php?success=added");
        exit();

    } else {

        if (!empty($image["path"])) {
            $uploaded_file = $upload_dir . basename($image["path"]);
            if (file_exists($uploaded_file)) {
                unlink($uploaded_file);
            }
        }

        header("Location: products.php?error=database");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| Edit Product
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_product"])) {

    $product_id = intval($_POST["product_id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $unit_price = floatval($_POST["unit_price"] ?? 0);
    $min_bulk_quantity = intval($_POST["min_bulk_quantity"] ?? 0);
    $supplier_id = intval($_POST["supplier_id"] ?? 0);

    if (
        $product_id <= 0 ||
        empty($name) ||
        empty($category) ||
        $unit_price <= 0 ||
        $min_bulk_quantity <= 0 ||
        $supplier_id <= 0
    ) {
        header("Location: products.php?error=invalid");
        exit();
    }

    $old_stmt = $conn->prepare("
        SELECT image_url
        FROM products
        WHERE product_id = ?
        LIMIT 1
    ");
    $old_stmt->bind_param("i", $product_id);
    $old_stmt->execute();
    $old_result = $old_stmt->get_result();
    $old_product = $old_result->fetch_assoc();

    if (!$old_product) {
        header("Location: products.php?error=invalid");
        exit();
    }

    $image = uploadProductImage("product_image", $upload_dir, $upload_web_path, false);

    if (!$image["success"]) {
        header("Location: products.php?error=image&message=" . urlencode($image["error"]));
        exit();
    }

    if ($image["path"] !== null) {

        $stmt = $conn->prepare("
            UPDATE products
            SET
                supplier_id = ?,
                name = ?,
                description = ?,
                category = ?,
                unit_price = ?,
                min_bulk_quantity = ?,
                image_url = ?
            WHERE product_id = ?
        ");

        $stmt->bind_param(
            "isssdisi",
            $supplier_id,
            $name,
            $description,
            $category,
            $unit_price,
            $min_bulk_quantity,
            $image["path"],
            $product_id
        );

    } else {

        $stmt = $conn->prepare("
            UPDATE products
            SET
                supplier_id = ?,
                name = ?,
                description = ?,
                category = ?,
                unit_price = ?,
                min_bulk_quantity = ?
            WHERE product_id = ?
        ");

        $stmt->bind_param(
            "isssdii",
            $supplier_id,
            $name,
            $description,
            $category,
            $unit_price,
            $min_bulk_quantity,
            $product_id
        );
    }

    if ($stmt->execute()) {

        if ($image["path"] !== null && !empty($old_product["image_url"])) {
            $old_file = $upload_dir . basename($old_product["image_url"]);

            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        header("Location: products.php?success=updated");
        exit();
    }

    if ($image["path"] !== null) {
        $uploaded_file = $upload_dir . basename($image["path"]);

        if (file_exists($uploaded_file)) {
            unlink($uploaded_file);
        }
    }

    header("Location: products.php?error=database");
    exit();
}

/*
|--------------------------------------------------------------------------
| Change Product Status
|--------------------------------------------------------------------------
*/

if (isset($_GET["status"]) && isset($_GET["id"])) {

    $product_id = intval($_GET["id"]);
    $new_status = $_GET["status"];

    $allowed_statuses = [
        "available",
        "unavailable",
        "discontinued"
    ];

    if (
        $product_id > 0 &&
        in_array($new_status, $allowed_statuses, true)
    ) {

        $stmt = $conn->prepare("
            UPDATE products
            SET status = ?
            WHERE product_id = ?
        ");

        $stmt->bind_param(
            "si",
            $new_status,
            $product_id
        );

        $stmt->execute();
    }

    header("Location: products.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Get Suppliers
|--------------------------------------------------------------------------
*/

$suppliers = [];

$supplier_result = $conn->query("
    SELECT supplier_id, name
    FROM suppliers
    WHERE status = 'active'
    ORDER BY name ASC
");

if ($supplier_result) {

    while ($row = $supplier_result->fetch_assoc()) {
        $suppliers[] = $row;
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
        p.product_id,
        p.name,
        p.description,
        p.category,
        p.unit_price,
        p.min_bulk_quantity,
        p.image_url,
        p.supplier_id,
        p.status,
        p.created_at,
        s.name AS supplier_name,
        COALESCE(i.quantity_in_stock, 0) AS quantity_in_stock
    FROM products p
    LEFT JOIN suppliers s
        ON p.supplier_id = s.supplier_id
    LEFT JOIN inventory i
        ON p.product_id = i.product_id
    ORDER BY p.created_at DESC
");

if ($product_result) {

    while ($row = $product_result->fetch_assoc()) {
        $products[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Products - Admin</title>

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

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 20px 15px;
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

        .main {
            margin-left: 250px;
            padding: 25px;
        }

        .topbar {
            background: #ffffff;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .page-title {
            font-weight: 700;
            color: #1f2937;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table th {
            background: #f8f9fa;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-available {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-unavailable {
            background: #fff3cd;
            color: #664d03;
        }

        .status-discontinued {
            background: #f8d7da;
            color: #842029;
        }

        .product-icon {
            width: 40px;
            height: 40px;
            background: #e8f5ee;
            color: #198754;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
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
        <span>Bulk Purchase</span>
    </h4>

    <a href="dashboard.php">
        <i class="fa-solid fa-gauge"></i>
        <span>Dashboard</span>
    </a>

    <a href="students.php">
        <i class="fa-solid fa-users"></i>
        <span>Students</span>
    </a>

    <a href="products.php" class="active">
        <i class="fa-solid fa-box"></i>
        <span>Products</span>
    </a>

    <a href="suppliers.php">
        <i class="fa-solid fa-truck-field"></i>
        <span>Suppliers</span>
    </a>

    <a href="bulk_purchases.php">
        <i class="fa-solid fa-cart-plus"></i>
        <span>Bulk Purchases</span>
    </a>

    <a href="participants.php">
        <i class="fa-solid fa-user-group"></i>
        <span>Participants</span>
    </a>

    <a href="payments.php">
        <i class="fa-solid fa-money-bill-wave"></i>
        <span>Payments</span>
    </a>

    <a href="orders.php">
        <i class="fa-solid fa-boxes-stacked"></i>
        <span>Orders</span>
    </a>

    <a href="inventory.php">
        <i class="fa-solid fa-warehouse"></i>
        <span>Inventory</span>
    </a>

    <a href="notifications.php">
        <i class="fa-solid fa-bell"></i>
        <span>Notifications</span>
    </a>

    <a href="reports.php">
        <i class="fa-solid fa-chart-column"></i>
        <span>Reports</span>
    </a>

    <hr>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
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
                Product Management
            </div>

            <small class="text-muted">
                Manage products available for bulk purchasing
            </small>

        </div>

        <div>

            <i class="fa-solid fa-user-shield text-success"></i>

            <?php echo htmlspecialchars($admin_name); ?>

        </div>

    </div>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if (isset($_GET["success"])): ?>

        <div class="alert alert-success">

            <i class="fa-solid fa-circle-check"></i>

            <?php

            if ($_GET["success"] === "added") {
                echo "Product added successfully.";
            } elseif ($_GET["success"] === "updated") {
                echo "Product updated successfully.";
            }

            ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php

            if ($_GET["error"] === "invalid") {
                echo "Please provide valid product information.";
            } elseif ($_GET["error"] === "image") {
                echo htmlspecialchars($_GET["message"] ?? "Please provide a valid product image.");
            } else {
                echo "An error occurred while processing the request.";
            }

            ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ADD PRODUCT BUTTON
    ====================================================== -->

    <div class="d-flex justify-content-end mb-3">

        <button
            class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#addProductModal"
        >

            <i class="fa-solid fa-plus"></i>

            Add Product

        </button>

    </div>


    <!-- =====================================================
         PRODUCT TABLE
    ====================================================== -->

    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>

                            <th>Product</th>

                            <th>Category</th>

                            <th>Supplier</th>

                            <th>Unit Price</th>

                            <th>Min. Bulk Qty</th>

                            <th>Stock</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($products)): ?>

                        <tr>

                            <td colspan="8" class="text-center py-4">

                                <i class="fa-solid fa-box-open fa-2x text-muted mb-2"></i>

                                <br>

                                No products found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($products as $product): ?>

                            <tr>

                                <td>

                                    <div class="d-flex align-items-center gap-2">

                                        <div class="product-icon">

                                            <i class="fa-solid fa-box"></i>

                                        </div>

                                        <div>

                                            <strong>
                                                <?php
                                                echo htmlspecialchars($product["name"]);
                                                ?>
                                            </strong>

                                            <br>

                                            <small class="text-muted">

                                                <?php

                                                echo htmlspecialchars(
                                                    $product["description"] ?: "No description"
                                                );

                                                ?>

                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars($product["category"]);
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $product["supplier_name"] ?? "Not assigned"
                                    );
                                    ?>
                                </td>

                                <td>

                                    ₦<?php
                                    echo number_format(
                                        $product["unit_price"],
                                        2
                                    );
                                    ?>

                                </td>

                                <td>
                                    <?php
                                    echo number_format(
                                        $product["min_bulk_quantity"]
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo number_format(
                                        $product["quantity_in_stock"]
                                    );
                                    ?>
                                </td>

                                <td>

                                    <span class="status-badge
                                        status-<?php
                                        echo htmlspecialchars(
                                            $product["status"]
                                        );
                                        ?>">

                                        <?php
                                        echo ucfirst(
                                            $product["status"]
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <div class="dropdown">

                                        <button
                                            class="btn btn-sm btn-light"
                                            data-bs-toggle="dropdown"
                                        >

                                            <i class="fa-solid fa-ellipsis-vertical"></i>

                                        </button>

                                        <ul class="dropdown-menu">

                                            <li>

                                                <button
                                                    class="dropdown-item"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editProduct<?php echo $product["product_id"]; ?>"
                                                >

                                                    <i class="fa-solid fa-pen text-primary"></i>

                                                    Edit

                                                </button>

                                            </li>

                                            <?php if ($product["status"] === "available"): ?>

                                                <li>

                                                    <a
                                                        class="dropdown-item"
                                                        href="products.php?status=unavailable&id=<?php echo $product["product_id"]; ?>"
                                                        onclick="return confirm('Make this product unavailable?');"
                                                    >

                                                        <i class="fa-solid fa-ban text-warning"></i>

                                                        Make Unavailable

                                                    </a>

                                                </li>

                                            <?php else: ?>

                                                <li>

                                                    <a
                                                        class="dropdown-item"
                                                        href="products.php?status=available&id=<?php echo $product["product_id"]; ?>"
                                                    >

                                                        <i class="fa-solid fa-check text-success"></i>

                                                        Make Available

                                                    </a>

                                                </li>

                                            <?php endif; ?>

                                            <?php if ($product["status"] !== "discontinued"): ?>

                                                <li>

                                                    <a
                                                        class="dropdown-item text-danger"
                                                        href="products.php?status=discontinued&id=<?php echo $product["product_id"]; ?>"
                                                        onclick="return confirm('Discontinue this product?');"
                                                    >

                                                        <i class="fa-solid fa-trash"></i>

                                                        Discontinue

                                                    </a>

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
     ADD PRODUCT MODAL
========================================================= -->

<div
    class="modal fade"
    id="addProductModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form method="POST" enctype="multipart/form-data">

                <div class="modal-header">

                    <h5 class="modal-title">

                        <i class="fa-solid fa-box text-success"></i>

                        Add New Product

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Product Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                placeholder="e.g. Wireless Mouse"
                                required
                            >

                        </div>


                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Category
                            </label>

                            <input
                                type="text"
                                name="category"
                                class="form-control"
                                placeholder="e.g. Computer Accessories"
                                required
                            >

                        </div>


                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Supplier
                            </label>

                            <select
                                name="supplier_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Supplier
                                </option>

                                <?php foreach ($suppliers as $supplier): ?>

                                    <option
                                        value="<?php echo $supplier["supplier_id"]; ?>"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $supplier["name"]
                                        );
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Unit Price (₦)
                            </label>

                            <input
                                type="number"
                                name="unit_price"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                required
                            >

                        </div>


                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Minimum Bulk Quantity
                            </label>

                            <input
                                type="number"
                                name="min_bulk_quantity"
                                class="form-control"
                                min="1"
                                required
                            >

                        </div>


                        <div class="col-12 mb-3">

                            <label class="form-label">
                                Product Image
                            </label>

                            <input
                                type="file"
                                name="product_image"
                                class="form-control"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                required
                            >

                            <small class="text-muted">
                                JPG, PNG, WEBP or GIF. Maximum size: 5 MB.
                            </small>

                        </div>


                        <div class="col-12 mb-3">

                            <label class="form-label">
                                Description
                            </label>

                            <textarea
                                name="description"
                                class="form-control"
                                rows="4"
                                placeholder="Enter product description"
                            ></textarea>

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
                        name="add_product"
                        class="btn btn-success"
                    >

                        <i class="fa-solid fa-plus"></i>

                        Add Product

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     EDIT PRODUCT MODALS
========================================================= -->

<?php foreach ($products as $product): ?>

<div
    class="modal fade"
    id="editProduct<?php echo $product["product_id"]; ?>"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form method="POST" enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="product_id"
                    value="<?php echo $product["product_id"]; ?>"
                >

                <div class="modal-header">

                    <h5 class="modal-title">

                        <i class="fa-solid fa-pen text-primary"></i>

                        Edit Product

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Product Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($product["name"]); ?>"
                                required
                            >

                        </div>


                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Category
                            </label>

                            <input
                                type="text"
                                name="category"
                                class="form-control"
                                value="<?php echo htmlspecialchars($product["category"]); ?>"
                                required
                            >

                        </div>


                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Supplier
                            </label>

                            <select
                                name="supplier_id"
                                class="form-select"
                                required
                            >

                                <?php foreach ($suppliers as $supplier): ?>

                                    <option
                                        value="<?php echo $supplier["supplier_id"]; ?>"
                                        <?php
                                        echo (
                                            $supplier["supplier_id"]
                                            == $product["supplier_id"]
                                        )
                                            ? "selected"
                                            : "";
                                        ?>
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $supplier["name"]
                                        );
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Unit Price (₦)
                            </label>

                            <input
                                type="number"
                                name="unit_price"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                value="<?php echo $product["unit_price"]; ?>"
                                required
                            >

                        </div>


                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Minimum Bulk Quantity
                            </label>

                            <input
                                type="number"
                                name="min_bulk_quantity"
                                class="form-control"
                                min="1"
                                value="<?php echo $product["min_bulk_quantity"]; ?>"
                                required
                            >

                        </div>


                        <div class="col-12 mb-3">

                            <label class="form-label">
                                Product Image
                            </label>

                            <?php if (!empty($product["image_url"])): ?>
                                <div class="mb-2">
                                    <img src="/student_bulk_purchase_system/<?= htmlspecialchars($product['image_url']) ?>"
     alt="<?= htmlspecialchars($product['name']) ?>"
     style="width:60px; height:60px; object-fit:cover; border-radius:8px;">
                                </div>
                            <?php endif; ?>

                            <input
                                type="file"
                                name="product_image"
                                class="form-control"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                            >

                            <small class="text-muted">
                                Leave empty to keep the current image. JPG, PNG, WEBP or GIF, maximum 5 MB.
                            </small>

                        </div>


                        <div class="col-12 mb-3">

                            <label class="form-label">
                                Description
                            </label>

                            <textarea
                                name="description"
                                class="form-control"
                                rows="4"
                            ><?php echo htmlspecialchars($product["description"]); ?></textarea>

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
                        name="edit_product"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-save"></i>

                        Save Changes

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php endforeach; ?>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>