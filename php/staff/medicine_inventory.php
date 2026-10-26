
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../db.php';

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !isset($_SESSION['user_role']) ||
    !in_array(
        $_SESSION['user_role'],
        ['Staff', 'Administrator'],
        true
    )
) {
    header('Location: ../login.php');
    exit();
}

if ($conn->connect_error) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($message) {
    header('Location: medicine_inventory.php?message=' . urlencode($message));
    exit();
}

// ADD, UPDATE, OR DELETE MEDICINE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['medicine_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
        $unit = trim($_POST['unit'] ?? 'pcs');
        $reorder = filter_var($_POST['reorder_level'] ?? null, FILTER_VALIDATE_INT);
        $expirationInput = trim($_POST['expiration_date'] ?? '');
        $supplier = trim($_POST['supplier'] ?? '');
        $location = trim($_POST['storage_location'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        // Validate required fields
        if (
            $name === '' ||
            $quantity === false || $quantity === null || $quantity < 0 ||
            $reorder === false || $reorder === null || $reorder < 0 ||
            $unit === ''
        ) {
            redirectWithMessage('Please enter valid medicine information.');
        }

        // Validate and normalize expiration date
        $expiration = null;

        if ($expirationInput !== '') {
            $dateObject = DateTime::createFromFormat('!Y-m-d', $expirationInput);
            $dateErrors = DateTime::getLastErrors();

            $validDate = $dateObject !== false
                && (
                    $dateErrors === false ||
                    (
                        $dateErrors['warning_count'] === 0 &&
                        $dateErrors['error_count'] === 0
                    )
                )
                && $dateObject->format('Y-m-d') === $expirationInput;

            if (!$validDate) {
                redirectWithMessage('Invalid expiration date. Please select a valid date.');
            }

            $expiration = $dateObject->format('Y-m-d');
        }

        if ($id > 0) {
            // UPDATE EXISTING MEDICINE
            $stmt = $conn->prepare(
                "UPDATE medicine_inventory
                 SET medicine_name = ?,
                     category = ?,
                     quantity = ?,
                     unit = ?,
                     reorder_level = ?,
                     expiration_date = ?,
                     supplier = ?,
                     storage_location = ?,
                     remarks = ?
                 WHERE id = ?"
            );

            // 10 parameters:
            // string, string, integer, string, integer,
            // string, string, string, string, integer
            $stmt->bind_param(
                'ssisissssi',
                $name,
                $category,
                $quantity,
                $unit,
                $reorder,
                $expiration,
                $supplier,
                $location,
                $remarks,
                $id
            );

        } else {
            // INSERT NEW MEDICINE
            $stmt = $conn->prepare(
                "INSERT INTO medicine_inventory
                 (
                     medicine_name,
                     category,
                     quantity,
                     unit,
                     reorder_level,
                     expiration_date,
                     supplier,
                     storage_location,
                     remarks
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            // 9 parameters:
            // string, string, integer, string, integer,
            // string, string, string, string
            $stmt->bind_param(
                'ssisis sss',
                $name,
                $category,
                $quantity,
                $unit,
                $reorder,
                $expiration,
                $supplier,
                $location,
                $remarks
            );
        }

        try {
            $stmt->execute();
            $stmt->close();

            redirectWithMessage(
                $id > 0
                    ? 'Medicine updated successfully.'
                    : 'Medicine added successfully.'
            );

        } catch (mysqli_sql_exception $exception) {
            $errorMessage = $exception->getMessage();
            $stmt->close();

            error_log('Medicine inventory save error: ' . $errorMessage);

            redirectWithMessage(
                'Unable to save medicine. Please check the database fields and expiration date.'
            );
        }
    }

    // DELETE MEDICINE
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare(
                "DELETE FROM medicine_inventory WHERE id = ?"
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }

        redirectWithMessage('Medicine record deleted.');
    }
}

// EDIT MODE
$editMedicine = null;

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM medicine_inventory WHERE id = ?"
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editMedicine = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editMedicine) {
        redirectWithMessage('Medicine record not found.');
    }
}

// SEARCH
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $conn->prepare(
        "SELECT * FROM medicine_inventory
         WHERE medicine_name LIKE ?
            OR category LIKE ?
         ORDER BY
             (expiration_date IS NULL OR expiration_date = '0000-00-00') ASC,
             expiration_date ASC,
             medicine_name ASC"
    );

    $term = '%' . $search . '%';
    $stmt->bind_param('ss', $term, $term);
    $stmt->execute();
    $medicines = $stmt->get_result();

} else {
    $medicines = $conn->query(
        "SELECT * FROM medicine_inventory
         ORDER BY
             (expiration_date IS NULL OR expiration_date = '0000-00-00') ASC,
             expiration_date ASC,
             medicine_name ASC"
    );
}

// DASHBOARD COUNTS
$totalTypes = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM medicine_inventory"
)->fetch_assoc()['total'];

$totalUnits = (int)$conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM medicine_inventory"
)->fetch_assoc()['total'];

$lowStock = (int)$conn->query(
    "SELECT COUNT(*) AS total
     FROM medicine_inventory
     WHERE quantity <= reorder_level"
)->fetch_assoc()['total'];

$expired = (int)$conn->query(
    "SELECT COUNT(*) AS total
     FROM medicine_inventory
     WHERE expiration_date IS NOT NULL
       AND expiration_date <> '0000-00-00'
       AND expiration_date < CURDATE()"
)->fetch_assoc()['total'];

$username = e($_SESSION['admin_username'] ?? 'Staff');
$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Medicine Inventory - SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
    <link rel="stylesheet" href="../../css/inventory.css">
</head>

<body class="staff-dashboard-body">

<aside class="staff-sidebar">
    <a href="staff_dashboard.php" class="staff-brand">
        <img src="../../image/logo2.png" alt="SHRCMS Logo">
        <div>
            <h2>SHRCMS</h2>
            <p>Clinic Management</p>
        </div>
    </a>

    <p class="sidebar-label">MAIN MENU</p>

    <nav class="staff-nav">
        <a href="staff_dashboard.php"><span>▦</span> Dashboard</a>
        <a href="student_records.php"><span>♙</span> Student Records</a>
        <a href="clinic_consultation.php"><span>✚</span> Clinic Consultation</a>
        <a href="health_documentation.php"><span>▤</span> Health Documentation</a>
        <a href="clinic_visit_records.php"><span>▣</span> Clinic Visit Records</a>

        <p class="sidebar-label">INVENTORY MANAGEMENT</p>

        <a href="equipment_inventory.php"><span>⚙</span> Equipment Inventory</a>
        <a href="medicine_inventory.php" class="active"><span>✚</span> Medicine Inventory</a>

        <p class="sidebar-label">REPORTS</p>
        <a href="clinic_reports.php"><span>▥</span> Clinic Reports</a>
        <a href="../logout.php" class="logout-link"><span>↪</span> Logout</a>
    </nav>

    <div class="sidebar-bottom">
        <p>Student Health Record</p>
        <small>Clinic Management System</small>
    </div>
</aside>

<main class="staff-main">

    <header class="staff-header">
        <div>
            <p class="page-eyebrow">INVENTORY MANAGEMENT</p>
            <h1>Medicine Inventory</h1>
            <p class="header-subtitle">
                Manage medicine supplies, stock levels, and expiration dates.
            </p>
        </div>

        <div class="staff-user">
            <img src="../../image/logo2.png" alt="Staff profile">
            <div>
                <strong><?= e($username) ?></strong>
                <span>Clinic Staff</span>
            </div>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="inventory-message"><?= e($message) ?></div>
    <?php endif; ?>

    <section class="inventory-stats">
        <article class="inventory-stat">
            <span>Total Medicine Types</span>
            <strong><?= $totalTypes ?></strong>
        </article>

        <article class="inventory-stat">
            <span>Total Units in Stock</span>
            <strong><?= $totalUnits ?></strong>
        </article>

        <article class="inventory-stat warning-stat">
            <span>Low-Stock Items</span>
            <strong><?= $lowStock ?></strong>
        </article>

        <article class="inventory-stat danger-stat">
            <span>Expired Medicines</span>
            <strong><?= $expired ?></strong>
        </article>
    </section>

    <!-- ADD / EDIT MEDICINE FORM -->
    <section class="inventory-panel">
        <div class="inventory-heading">
            <div>
                <h2><?= $editMedicine ? 'Edit Medicine' : 'Add Medicine' ?></h2>
                <p>Enter the medicine information below.</p>
            </div>
        </div>

        <form method="POST" action="medicine_inventory.php" class="inventory-form">
            <input type="hidden" name="action" value="save">

            <input type="hidden" name="id"
                   value="<?= e($editMedicine['id'] ?? '') ?>">

            <div class="form-field">
                <label for="medicine_name">Medicine Name *</label>
                <input
                    id="medicine_name"
                    name="medicine_name"
                    value="<?= e($editMedicine['medicine_name'] ?? '') ?>"
                    required
                    maxlength="150"
                >
            </div>

            <div class="form-field">
                <label for="category">Category</label>
                <input
                    id="category"
                    name="category"
                    value="<?= e($editMedicine['category'] ?? '') ?>"
                    placeholder="e.g. First aid"
                >
            </div>

            <div class="form-field">
                <label for="quantity">Quantity *</label>
                <input
                    id="quantity"
                    type="number"
                    name="quantity"
                    min="0"
                    step="1"
                    required
                    value="<?= e($editMedicine['quantity'] ?? '0') ?>"
                >
            </div>

            <div class="form-field">
                <label for="unit">Unit *</label>
                <input
                    id="unit"
                    name="unit"
                    required
                    value="<?= e($editMedicine['unit'] ?? 'pcs') ?>"
                    placeholder="pcs, boxes, bottles"
                >
            </div>

            <div class="form-field">
                <label for="reorder_level">Low-Stock Alert Level *</label>
                <input
                    id="reorder_level"
                    type="number"
                    name="reorder_level"
                    min="0"
                    step="1"
                    required
                    value="<?= e($editMedicine['reorder_level'] ?? '10') ?>"
                >
            </div>

            <div class="form-field">
                <label for="expiration_date">Expiration Date</label>
                <input
                    id="expiration_date"
                    type="date"
                    name="expiration_date"
                    value="<?php
                        $formExpiration = $editMedicine['expiration_date'] ?? '';
                        if (
                            $formExpiration === '0000-00-00' ||
                            $formExpiration === '0000-00-00 00:00:00'
                        ) {
                            $formExpiration = '';
                        }
                        echo e($formExpiration);
                    ?>"
                >
            </div>

            <div class="form-field">
                <label for="supplier">Supplier</label>
                <input
                    id="supplier"
                    name="supplier"
                    value="<?= e($editMedicine['supplier'] ?? '') ?>"
                >
            </div>

            <div class="form-field">
                <label for="storage_location">Storage Location</label>
                <input
                    id="storage_location"
                    name="storage_location"
                    value="<?= e($editMedicine['storage_location'] ?? '') ?>"
                >
            </div>

            <div class="form-field full-field">
                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="2"><?= e($editMedicine['remarks'] ?? '') ?></textarea>
            </div>

            <div class="form-actions full-field">
                <button type="submit" class="inventory-submit">
                    <?= $editMedicine ? 'Update Medicine' : 'Save Medicine' ?>
                </button>

                <?php if ($editMedicine): ?>
                    <a href="medicine_inventory.php" class="inventory-cancel">
                        Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- MEDICINE RECORDS -->
    <section class="inventory-panel">
        <div class="inventory-heading inventory-list-heading">
            <div>
                <h2>Medicine Records</h2>
                <p>Search and manage existing inventory.</p>
            </div>

            <form method="GET" class="inventory-search">
                <input
                    type="search"
                    name="search"
                    placeholder="Search medicine..."
                    value="<?= e($search) ?>"
                >

                <button type="submit">Search</button>

                <?php if ($search !== ''): ?>
                    <a href="medicine_inventory.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="inventory-table-wrap">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Quantity</th>
                        <th>Reorder Level</th>
                        <th>Expiration</th>
                        <th>Stock Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($medicines && $medicines->num_rows > 0): ?>

                    <?php while ($medicine = $medicines->fetch_assoc()): ?>

                        <?php
                        $expirationDate = trim(
                            (string)($medicine['expiration_date'] ?? '')
                        );

                        $hasValidExpiration =
                            $expirationDate !== '' &&
                            $expirationDate !== '0000-00-00' &&
                            $expirationDate !== '0000-00-00 00:00:00' &&
                            preg_match('/^\d{4}-\d{2}-\d{2}$/', $expirationDate) &&
                            strtotime($expirationDate) !== false;

                        $isExpired = $hasValidExpiration &&
                            $expirationDate < date('Y-m-d');

                        $isLow = (int)$medicine['quantity'] <=
                            (int)$medicine['reorder_level'];

                        $expiresSoon = $hasValidExpiration &&
                            !$isExpired &&
                            $expirationDate <= date('Y-m-d', strtotime('+30 days'));
                        ?>

                        <tr>
                            <td>
                                <strong><?= e($medicine['medicine_name']) ?></strong>
                                <small>
                                    <?= e($medicine['category'] ?: 'Uncategorized') ?>
                                </small>
                            </td>

                            <td>
                                <?= (int)$medicine['quantity'] ?>
                                <?= e($medicine['unit']) ?>
                            </td>

                            <td><?= (int)$medicine['reorder_level'] ?></td>

                            <td>
                                <?php if ($hasValidExpiration): ?>
                                    <?= e(date('F d, Y', strtotime($expirationDate))) ?>
                                <?php else: ?>
                                    <span class="expiration-not-set">Not set</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($isExpired): ?>
                                    <span class="status-pill status-danger">
                                        Expired
                                    </span>
                                <?php elseif ($isLow): ?>
                                    <span class="status-pill status-warning">
                                        Low Stock
                                    </span>
                                <?php elseif ($expiresSoon): ?>
                                    <span class="status-pill status-warning">
                                        Expires Soon
                                    </span>
                                <?php else: ?>
                                    <span class="status-pill status-good">
                                        In Stock
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="table-actions">
                                    <a
                                        class="edit-action"
                                        href="medicine_inventory.php?edit=<?= (int)$medicine['id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="medicine_inventory.php"
                                        onsubmit="return confirm('Delete this medicine record?');"
                                    >
                                        <input type="hidden" name="action" value="delete">

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$medicine['id'] ?>"
                                        >

                                        <button type="submit" class="delete-action">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty-state">
                            No medicine records found. Add a medicine using the form above.
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="staff-footer">
        SHRCMS — Student Health Record and Clinic Management System
    </footer>

</main>

</body>
</html>
