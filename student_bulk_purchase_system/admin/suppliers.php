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


/*
|--------------------------------------------------------------------------
| Add Supplier
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_supplier"])) {

    $name = trim($_POST["name"] ?? "");
    $contact_person = trim($_POST["contact_person"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $address = trim($_POST["address"] ?? "");

    if (empty($name)) {
        header("Location: suppliers.php?error=invalid");
        exit();
    }

    /*
    | Check if supplier already exists
    */

    $check_stmt = $conn->prepare("
        SELECT supplier_id
        FROM suppliers
        WHERE name = ?
        LIMIT 1
    ");

    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();

    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        header("Location: suppliers.php?error=exists");
        exit();
    }


    /*
    | Insert supplier
    */

    $stmt = $conn->prepare("
        INSERT INTO suppliers
        (
            name,
            contact_person,
            email,
            phone_number,
            address,
            status
        )
        VALUES (?, ?, ?, ?, ?, 'active')
    ");

    $stmt->bind_param(
        "sssss",
        $name,
        $contact_person,
        $email,
        $phone_number,
        $address
    );

    if ($stmt->execute()) {

        header("Location: suppliers.php?success=added");
        exit();

    } else {

        header("Location: suppliers.php?error=database");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| Edit Supplier
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_supplier"])) {

    $supplier_id = intval($_POST["supplier_id"] ?? 0);

    $name = trim($_POST["name"] ?? "");
    $contact_person = trim($_POST["contact_person"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $address = trim($_POST["address"] ?? "");

    if ($supplier_id <= 0 || empty($name)) {
        header("Location: suppliers.php?error=invalid");
        exit();
    }


    /*
    | Check duplicate supplier name
    */

    $check_stmt = $conn->prepare("
        SELECT supplier_id
        FROM suppliers
        WHERE name = ?
        AND supplier_id != ?
        LIMIT 1
    ");

    $check_stmt->bind_param(
        "si",
        $name,
        $supplier_id
    );

    $check_stmt->execute();

    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        header("Location: suppliers.php?error=exists");
        exit();
    }


    /*
    | Update supplier
    */

    $stmt = $conn->prepare("
        UPDATE suppliers
        SET
            name = ?,
            contact_person = ?,
            email = ?,
            phone_number = ?,
            address = ?
        WHERE supplier_id = ?
    ");

    $stmt->bind_param(
        "sssssi",
        $name,
        $contact_person,
        $email,
        $phone_number,
        $address,
        $supplier_id
    );

    if ($stmt->execute()) {

        header("Location: suppliers.php?success=updated");
        exit();

    } else {

        header("Location: suppliers.php?error=database");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| Change Supplier Status
|--------------------------------------------------------------------------
*/

if (isset($_GET["status"]) && isset($_GET["id"])) {

    $supplier_id = intval($_GET["id"]);
    $status = $_GET["status"];

    $allowed_statuses = [
        "active",
        "inactive"
    ];

    if (
        $supplier_id > 0 &&
        in_array($status, $allowed_statuses, true)
    ) {

        $stmt = $conn->prepare("
            UPDATE suppliers
            SET status = ?
            WHERE supplier_id = ?
        ");

        $stmt->bind_param(
            "si",
            $status,
            $supplier_id
        );

        $stmt->execute();
    }

    header("Location: suppliers.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Get Suppliers
|--------------------------------------------------------------------------
*/

$suppliers = [];

$result = $conn->query("
    SELECT
        s.supplier_id,
        s.name,
        s.contact_person,
        s.email,
        s.phone_number,
        s.address,
        s.status,
        s.created_at,
        COUNT(p.product_id) AS product_count
    FROM suppliers s
    LEFT JOIN products p
        ON s.supplier_id = p.supplier_id
    GROUP BY
        s.supplier_id,
        s.name,
        s.contact_person,
        s.email,
        s.phone_number,
        s.address,
        s.status,
        s.created_at
    ORDER BY s.created_at DESC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
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

    <title>Suppliers - Admin</title>


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

            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }


        .page-title {
            font-weight: 700;
            color: #1f2937;
        }


        /* Card */

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


        /* Status */

        .status-badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }


        .status-active {
            background: #d1e7dd;
            color: #0f5132;
        }


        .status-inactive {
            background: #f8d7da;
            color: #842029;
        }


        /* Supplier Icon */

        .supplier-icon {
            width: 40px;
            height: 40px;

            background: #e8f5ee;
            color: #198754;

            border-radius: 8px;

            display: flex;
            align-items: center;
            justify-content: center;
        }


        /* Mobile */

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


    <a href="suppliers.php" class="active">

        <i class="fa-solid fa-truck-field"></i>

        <span>
            Suppliers
        </span>

    </a>


    <a href="bulk_purchases.php">

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

                Supplier Management

            </div>

            <small class="text-muted">

                Manage suppliers connected to the purchasing system

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

            if ($_GET["success"] === "added") {

                echo "Supplier added successfully.";

            } elseif ($_GET["success"] === "updated") {

                echo "Supplier updated successfully.";

            }

            ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php

            if ($_GET["error"] === "invalid") {

                echo "Please provide valid supplier information.";

            } elseif ($_GET["error"] === "exists") {

                echo "A supplier with this name already exists.";

            } else {

                echo "An error occurred while processing the request.";

            }

            ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         ADD SUPPLIER BUTTON
    ====================================================== -->

    <div class="d-flex justify-content-end mb-3">

        <button
            class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#addSupplierModal"
        >

            <i class="fa-solid fa-plus"></i>

            Add Supplier

        </button>

    </div>



    <!-- =====================================================
         SUPPLIER TABLE
    ====================================================== -->

    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>

                            <th>Supplier</th>

                            <th>Contact Person</th>

                            <th>Email</th>

                            <th>Phone</th>

                            <th>Products</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($suppliers)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="text-center py-5"
                            >

                                <i
                                    class="fa-solid fa-truck-field fa-2x text-muted mb-2"
                                ></i>

                                <br>

                                No suppliers found.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($suppliers as $supplier): ?>

                            <tr>


                                <!-- Supplier -->

                                <td>

                                    <div
                                        class="d-flex align-items-center gap-2"
                                    >

                                        <div class="supplier-icon">

                                            <i
                                                class="fa-solid fa-truck-field"
                                            ></i>

                                        </div>


                                        <div>

                                            <strong>

                                                <?php

                                                echo htmlspecialchars(
                                                    $supplier["name"]
                                                );

                                                ?>

                                            </strong>


                                            <br>


                                            <small
                                                class="text-muted"
                                            >

                                                <?php

                                                echo htmlspecialchars(
                                                    $supplier["address"]
                                                    ?: "No address"
                                                );

                                                ?>

                                            </small>

                                        </div>

                                    </div>

                                </td>



                                <!-- Contact Person -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $supplier["contact_person"]
                                        ?: "Not provided"
                                    );

                                    ?>

                                </td>



                                <!-- Email -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $supplier["email"]
                                        ?: "Not provided"
                                    );

                                    ?>

                                </td>



                                <!-- Phone -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $supplier["phone_number"]
                                        ?: "Not provided"
                                    );

                                    ?>

                                </td>



                                <!-- Product Count -->

                                <td>

                                    <span class="badge bg-primary">

                                        <?php

                                        echo number_format(
                                            $supplier["product_count"]
                                        );

                                        ?>

                                    </span>

                                </td>



                                <!-- Status -->

                                <td>

                                    <span
                                        class="status-badge status-<?php
                                        echo htmlspecialchars(
                                            $supplier["status"]
                                        );
                                        ?>"
                                    >

                                        <?php

                                        echo ucfirst(
                                            $supplier["status"]
                                        );

                                        ?>

                                    </span>

                                </td>



                                <!-- Actions -->

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


                                            <!-- Edit -->

                                            <li>

                                                <button
                                                    class="dropdown-item"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editSupplier<?php echo $supplier["supplier_id"]; ?>"
                                                >

                                                    <i
                                                        class="fa-solid fa-pen text-primary"
                                                    ></i>

                                                    Edit

                                                </button>

                                            </li>



                                            <!-- Activate / Deactivate -->

                                            <?php if ($supplier["status"] === "active"): ?>

                                                <li>

                                                    <a
                                                        class="dropdown-item"
                                                        href="suppliers.php?status=inactive&id=<?php echo $supplier["supplier_id"]; ?>"
                                                        onclick="return confirm('Deactivate this supplier?');"
                                                    >

                                                        <i
                                                            class="fa-solid fa-ban text-warning"
                                                        ></i>

                                                        Deactivate

                                                    </a>

                                                </li>

                                            <?php else: ?>

                                                <li>

                                                    <a
                                                        class="dropdown-item"
                                                        href="suppliers.php?status=active&id=<?php echo $supplier["supplier_id"]; ?>"
                                                    >

                                                        <i
                                                            class="fa-solid fa-check text-success"
                                                        ></i>

                                                        Activate

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
     ADD SUPPLIER MODAL
========================================================= -->

<div
    class="modal fade"
    id="addSupplierModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">


            <form method="POST">


                <div class="modal-header">

                    <h5 class="modal-title">

                        <i
                            class="fa-solid fa-truck-field text-success"
                        ></i>

                        Add New Supplier

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>



                <div class="modal-body">


                    <div class="row">


                        <!-- Supplier Name -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Supplier Name

                            </label>


                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                placeholder="Enter supplier name"
                                required
                            >

                        </div>



                        <!-- Contact Person -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Contact Person

                            </label>


                            <input
                                type="text"
                                name="contact_person"
                                class="form-control"
                                placeholder="Enter contact person's name"
                            >

                        </div>



                        <!-- Email -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Email Address

                            </label>


                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                placeholder="supplier@example.com"
                            >

                        </div>



                        <!-- Phone -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Phone Number

                            </label>


                            <input
                                type="text"
                                name="phone_number"
                                class="form-control"
                                placeholder="Enter phone number"
                            >

                        </div>



                        <!-- Address -->

                        <div class="col-12 mb-3">

                            <label class="form-label">

                                Address

                            </label>


                            <textarea
                                name="address"
                                class="form-control"
                                rows="3"
                                placeholder="Enter supplier address"
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
                        name="add_supplier"
                        class="btn btn-success"
                    >

                        <i class="fa-solid fa-plus"></i>

                        Add Supplier

                    </button>


                </div>


            </form>

        </div>

    </div>

</div>



<!-- =========================================================
     EDIT SUPPLIER MODALS
========================================================= -->

<?php foreach ($suppliers as $supplier): ?>


<div
    class="modal fade"
    id="editSupplier<?php echo $supplier["supplier_id"]; ?>"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">


            <form method="POST">


                <input
                    type="hidden"
                    name="supplier_id"
                    value="<?php echo $supplier["supplier_id"]; ?>"
                >


                <div class="modal-header">


                    <h5 class="modal-title">

                        <i
                            class="fa-solid fa-pen text-primary"
                        ></i>

                        Edit Supplier

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>


                </div>



                <div class="modal-body">


                    <div class="row">


                        <!-- Supplier Name -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Supplier Name

                            </label>


                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($supplier["name"]); ?>"
                                required
                            >

                        </div>



                        <!-- Contact Person -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Contact Person

                            </label>


                            <input
                                type="text"
                                name="contact_person"
                                class="form-control"
                                value="<?php echo htmlspecialchars($supplier["contact_person"]); ?>"
                            >

                        </div>



                        <!-- Email -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Email Address

                            </label>


                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?php echo htmlspecialchars($supplier["email"]); ?>"
                            >

                        </div>



                        <!-- Phone -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Phone Number

                            </label>


                            <input
                                type="text"
                                name="phone_number"
                                class="form-control"
                                value="<?php echo htmlspecialchars($supplier["phone_number"]); ?>"
                            >

                        </div>



                        <!-- Address -->

                        <div class="col-12 mb-3">

                            <label class="form-label">

                                Address

                            </label>


                            <textarea
                                name="address"
                                class="form-control"
                                rows="3"
                            ><?php echo htmlspecialchars($supplier["address"]); ?></textarea>

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
                        name="edit_supplier"
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



<!-- Bootstrap JavaScript -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>