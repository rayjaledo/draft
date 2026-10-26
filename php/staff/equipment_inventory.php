

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


if (empty($_SESSION['equipment_csrf'])) {
    $_SESSION['equipment_csrf'] = bin2hex(random_bytes(32));
}

/* HELPER FUNCTIONS */
function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectEquipment(string $query = ''): void
{
    header('Location: equipment_inventory.php' . $query);
    exit();
}

function validDate(string $date): bool
{
    if ($date === '') {
        return true;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    $errors = DateTime::getLastErrors();

    return $parsed !== false
        && ($errors === false ||
            ($errors['warning_count'] === 0 &&
             $errors['error_count'] === 0))
        && $parsed->format('Y-m-d') === $date;
}

function nullableDate(string $date): ?string
{
    return $date === '' ? null : $date;
}

/* VALID CONDITION OPTIONS
   These should match your database ENUM values. */
$conditions = [
    'Good',
    'Needs Maintenance',
    'Under Repair',
    'Damaged'
];

/* HANDLE FORM SUBMISSIONS */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['equipment_csrf'], $token)
    ) {
        redirectEquipment('?status=invalid');
    }

    $action = $_POST['action'] ?? '';

    /* DELETE EQUIPMENT */
    if ($action === 'delete') {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id < 1) {
            redirectEquipment('?status=invalid');
        }

        $stmt = $conn->prepare(
            'DELETE FROM equipment_inventory WHERE id = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $deleted = $stmt->affected_rows;
        $stmt->close();

        redirectEquipment(
            $deleted > 0 ? '?status=deleted' : '?status=notfound'
        );
    }

    /* ADD OR UPDATE EQUIPMENT */
    if ($action === 'save') {
        $id = filter_var(
            $_POST['id'] ?? 0,
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id < 0) {
            redirectEquipment('?status=invalid');
        }

        $equipmentName = trim($_POST['equipment_name'] ?? '');
        $category = trim($_POST['category'] ?? '');

        $quantity = filter_var(
            $_POST['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        $condition = $_POST['equipment_condition'] ?? 'Good';

        $dateAcquired = trim($_POST['date_acquired'] ?? '');
        $lastMaintenance = trim($_POST['last_maintenance'] ?? '');
        $nextMaintenance = trim($_POST['next_maintenance'] ?? '');

        $location = trim($_POST['location'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        /* VALIDATION */
        if (
            $equipmentName === '' ||
            mb_strlen($equipmentName) > 150 ||
            mb_strlen($category) > 100 ||
            mb_strlen($location) > 150 ||
            mb_strlen($remarks) > 10000 ||
            $quantity === false ||
            $quantity < 0 ||
            !in_array($condition, $conditions, true) ||
            !validDate($dateAcquired) ||
            !validDate($lastMaintenance) ||
            !validDate($nextMaintenance)
        ) {
            redirectEquipment('?status=invalid');
        }

        $dateAcquired = nullableDate($dateAcquired);
        $lastMaintenance = nullableDate($lastMaintenance);
        $nextMaintenance = nullableDate($nextMaintenance);

        if ($id > 0) {
            /* UPDATE EXISTING RECORD */
            $stmt = $conn->prepare(
                "UPDATE equipment_inventory
                 SET equipment_name = ?,
                     category = ?,
                     quantity = ?,
                     equipment_condition = ?,
                     date_acquired = ?,
                     last_maintenance = ?,
                     next_maintenance = ?,
                     location = ?,
                     remarks = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                'ssissssssi',
                $equipmentName,
                $category,
                $quantity,
                $condition,
                $dateAcquired,
                $lastMaintenance,
                $nextMaintenance,
                $location,
                $remarks,
                $id
            );

            $stmt->execute();
            $stmt->close();

            redirectEquipment('?status=updated');
        } else {
            /* INSERT NEW RECORD */
            $stmt = $conn->prepare(
                "INSERT INTO equipment_inventory (
                    equipment_name,
                    category,
                    quantity,
                    equipment_condition,
                    date_acquired,
                    last_maintenance,
                    next_maintenance,
                    location,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'ssissssss',
                $equipmentName,
                $category,
                $quantity,
                $condition,
                $dateAcquired,
                $lastMaintenance,
                $nextMaintenance,
                $location,
                $remarks
            );

            $stmt->execute();
            $stmt->close();

            redirectEquipment('?status=added');
        }
    }

    redirectEquipment('?status=invalid');
}

/* STATUS MESSAGES */
$statusMessages = [
    'added' => 'Equipment added successfully.',
    'updated' => 'Equipment updated successfully.',
    'deleted' => 'Equipment deleted successfully.',
    'notfound' => 'Equipment record was not found.',
    'invalid' => 'Invalid input. Please check the information and try again.'
];

$status = $_GET['status'] ?? '';
$message = $statusMessages[$status] ?? '';
$messageType = in_array(
    $status,
    ['invalid', 'notfound'],
    true
) ? 'error' : 'success';

/* LOAD RECORD FOR EDITING */
$editEquipment = null;

$editId = filter_var(
    $_GET['edit'] ?? null,
    FILTER_VALIDATE_INT
);

if ($editId !== false && $editId !== null && $editId > 0) {
    $stmt = $conn->prepare(
        'SELECT * FROM equipment_inventory WHERE id = ?'
    );

    $stmt->bind_param('i', $editId);
    $stmt->execute();

    $result = $stmt->get_result();
    $editEquipment = $result->fetch_assoc();

    $stmt->close();

    if (!$editEquipment) {
        $message = 'Equipment record was not found.';
        $messageType = 'error';
    }
}

/* SEARCH RECORDS */
$search = trim($_GET['search'] ?? '');

$sql = 'SELECT * FROM equipment_inventory';
$searchParams = [];
$searchTypes = '';

if ($search !== '') {
    $sql .= " WHERE equipment_name LIKE ?
              OR category LIKE ?
              OR equipment_condition LIKE ?
              OR location LIKE ?
              OR remarks LIKE ?";

    $term = '%' . $search . '%';

    $searchParams = [
        $term,
        $term,
        $term,
        $term,
        $term
    ];

    $searchTypes = 'sssss';
}

$sql .= ' ORDER BY equipment_name ASC';

$stmt = $conn->prepare($sql);

if (!empty($searchParams)) {
    $stmt->bind_param($searchTypes, ...$searchParams);
}

$stmt->execute();
$result = $stmt->get_result();

$equipment = [];

while ($row = $result->fetch_assoc()) {
    $equipment[] = $row;
}

$stmt->close();

/* SUMMARY CARDS */
$summaryResult = $conn->query(
    "SELECT
        COUNT(*) AS total_types,
        COALESCE(SUM(quantity), 0) AS total_units,

        COALESCE(SUM(
            CASE
                WHEN quantity <= 5 THEN 1
                ELSE 0
            END
        ), 0) AS low_stock,

        COALESCE(SUM(
            CASE
                WHEN equipment_condition IN (
                    'Needs Maintenance',
                    'Under Repair',
                    'Damaged'
                )
                THEN 1
                ELSE 0
            END
        ), 0) AS attention_count,

        COALESCE(SUM(
            CASE
                WHEN next_maintenance IS NOT NULL
                 AND next_maintenance < CURDATE()
                THEN 1
                ELSE 0
            END
        ), 0) AS overdue_maintenance

     FROM equipment_inventory"
);

$summary = $summaryResult->fetch_assoc();

$username = $_SESSION['admin_username'] ?? 'Staff';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Equipment Inventory - SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
    <link rel="stylesheet" href="../../css/equipment-inventory.css">
</head>

<body class="staff-dashboard-body">

<!-- SIDEBAR -->
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
        <a href="staff_dashboard.php">
            <span>▦</span> Dashboard
        </a>

        <a href="student_records.php">
            <span>♙</span> Student Records
        </a>

        <a href="clinic_consultation.php">
            <span>✚</span> Clinic Consultation
        </a>

        <a href="health_documentation.php">
            <span>▤</span> Health Documentation
        </a>

        <a href="clinic_visit_records.php">
            <span>▣</span> Clinic Visit Records
        </a>

        <p class="sidebar-label">INVENTORY MANAGEMENT</p>

        <a href="equipment_inventory.php" class="active">
            <span>⚙</span> Equipment Inventory
        </a>

        <a href="medicine_inventory.php">
            <span>✚</span> Medicine Inventory
        </a>

        <p class="sidebar-label">REPORTS</p>

        <a href="clinic_reports.php">
            <span>▥</span> Clinic Reports
        </a>

        <a href="../logout.php" class="logout-link">
            <span>↪</span> Logout
        </a>
    </nav>

    <div class="sidebar-bottom">
        <p>Student Health Record</p>
        <small>Clinic Management System</small>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="staff-main ei-main">

    <header class="staff-header">
        <div>
            <p class="page-eyebrow">INVENTORY MANAGEMENT</p>
            <h1>Equipment Inventory</h1>
            <p class="header-subtitle">
                Manage clinic equipment, conditions, and maintenance schedules.
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
        <div class="ei-alert <?= e($messageType) ?>" role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <section class="ei-stats">
        <article class="ei-stat">
            <span>Total Equipment Types</span>
            <strong><?= (int)$summary['total_types'] ?></strong>
            <small>Registered equipment items</small>
        </article>

        <article class="ei-stat">
            <span>Total Units</span>
            <strong><?= (int)$summary['total_units'] ?></strong>
            <small>Combined quantity</small>
        </article>

        <article class="ei-stat">
            <span>Low Stock</span>
            <strong><?= (int)$summary['low_stock'] ?></strong>
            <small>Equipment types with 5 or fewer units</small>
        </article>

        <article class="ei-stat">
            <span>Needs Attention</span>
            <strong><?= (int)$summary['attention_count'] ?></strong>
            <small>
                <?= (int)$summary['overdue_maintenance'] ?>
                overdue maintenance schedule(s)
            </small>
        </article>
    </section>

    <!-- EQUIPMENT FORM -->
    <section class="ei-panel" id="equipment-form">
        <div class="ei-panel-heading">
            <div>
                <h2>
                    <?= $editEquipment ? 'Edit Equipment' : 'Add Equipment' ?>
                </h2>
                <p>Enter the equipment information below.</p>
            </div>

            <?php if ($editEquipment): ?>
                <a href="equipment_inventory.php"
                   class="ei-secondary-btn">
                    Cancel Editing
                </a>
            <?php endif; ?>
        </div>

        <form method="POST"
              action="equipment_inventory.php"
              class="ei-form">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['equipment_csrf']) ?>">

            <input type="hidden" name="action" value="save">

            <input type="hidden" name="id"
                   value="<?= (int)($editEquipment['id'] ?? 0) ?>">

            <div class="ei-field">
                <label for="equipment_name">Equipment Name *</label>
                <input type="text" id="equipment_name"
                       name="equipment_name" maxlength="150" required
                       placeholder="e.g. Digital Thermometer"
                       value="<?= e($editEquipment['equipment_name'] ?? '') ?>">
            </div>

            <div class="ei-field">
                <label for="category">Category</label>
                <input type="text" id="category" name="category"
                       maxlength="100"
                       placeholder="e.g. Diagnostic Equipment"
                       value="<?= e($editEquipment['category'] ?? '') ?>">
            </div>

            <div class="ei-field">
                <label for="quantity">Quantity *</label>
                <input type="number" id="quantity" name="quantity"
                       min="0" step="1" required
                       value="<?= e($editEquipment['quantity'] ?? 1) ?>">
            </div>

            <div class="ei-field">
                <label for="equipment_condition">Equipment Condition *</label>
                <select id="equipment_condition"
                        name="equipment_condition" required>
                    <?php
                    $currentCondition =
                        $editEquipment['equipment_condition'] ?? 'Good';

                    foreach ($conditions as $option):
                    ?>
                        <option value="<?= e($option) ?>"
                            <?= $currentCondition === $option ? 'selected' : '' ?>>
                            <?= e($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ei-field">
                <label for="date_acquired">Date Acquired</label>
                <input type="date" id="date_acquired"
                       name="date_acquired"
                       value="<?= e($editEquipment['date_acquired'] ?? '') ?>">
            </div>

            <div class="ei-field">
                <label for="last_maintenance">Last Maintenance</label>
                <input type="date" id="last_maintenance"
                       name="last_maintenance"
                       value="<?= e($editEquipment['last_maintenance'] ?? '') ?>">
            </div>

            <div class="ei-field">
                <label for="next_maintenance">Next Maintenance</label>
                <input type="date" id="next_maintenance"
                       name="next_maintenance"
                       value="<?= e($editEquipment['next_maintenance'] ?? '') ?>">
            </div>

            <div class="ei-field">
                <label for="location">Location</label>
                <input type="text" id="location" name="location"
                       maxlength="150"
                       placeholder="e.g. School Clinic"
                       value="<?= e($editEquipment['location'] ?? '') ?>">
            </div>

            <div class="ei-field ei-field-wide">
                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3"
                          maxlength="10000"
                          placeholder="Enter equipment details or maintenance notes..."><?= e($editEquipment['remarks'] ?? '') ?></textarea>
            </div>

            <div class="ei-form-actions">
                <button type="submit" class="ei-primary-btn">
                    <?= $editEquipment ? 'Save Changes' : 'Add Equipment' ?>
                </button>

                <?php if (!$editEquipment): ?>
                    <button type="reset" class="ei-secondary-btn">
                        Clear Form
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- EQUIPMENT TABLE -->
    <section class="ei-panel">
        <div class="ei-panel-heading">
            <div>
                <h2>Equipment Records</h2>
                <p>Search, review, edit, and delete clinic equipment.</p>
            </div>

            <button type="button"
                    class="ei-secondary-btn"
                    onclick="window.print()">
                Print Records
            </button>
        </div>

        <form method="GET"
              action="equipment_inventory.php"
              class="ei-search">

            <input type="search" name="search"
                   placeholder="Search equipment, category, condition, or location..."
                   value="<?= e($search) ?>">

            <button type="submit" class="ei-primary-btn">Search</button>

            <?php if ($search !== ''): ?>
                <a href="equipment_inventory.php"
                   class="ei-secondary-btn">
                    Clear
                </a>
            <?php endif; ?>
        </form>

        <div class="ei-table-wrap">
            <table class="ei-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Equipment</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                        <th>Date Acquired</th>
                        <th>Last Maintenance</th>
                        <th>Next Maintenance</th>
                        <th>Location</th>
                        <th>Remarks</th>
                        <th class="ei-actions-column">Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($equipment)): ?>
                    <tr>
                        <td colspan="11" class="ei-empty">
                            No equipment records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($equipment as $index => $item): ?>
                        <?php
                        $conditionValue = $item['equipment_condition'];

                        if ($conditionValue === 'Good') {
                            $conditionClass = 'good';
                        } elseif ($conditionValue === 'Damaged') {
                            $conditionClass = 'damaged';
                        } else {
                            $conditionClass = 'repair';
                        }

                        $overdue = !empty($item['next_maintenance'])
                            && $item['next_maintenance'] < date('Y-m-d');
                        ?>

                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td>
                                <strong><?= e($item['equipment_name']) ?></strong>
                            </td>

                            <td><?= e($item['category'] ?: '—') ?></td>

                            <td>
                                <span class="ei-quantity <?= (int)$item['quantity'] <= 5 ? 'low' : '' ?>">
                                    <?= (int)$item['quantity'] ?>
                                </span>
                            </td>

                            <td>
                                <span class="ei-status <?= e($conditionClass) ?>">
                                    <?= e($conditionValue) ?>
                                </span>
                            </td>

                            <td>
                                <?= !empty($item['date_acquired'])
                                    ? e(date('M d, Y', strtotime($item['date_acquired'])))
                                    : '—' ?>
                            </td>

                            <td>
                                <?= !empty($item['last_maintenance'])
                                    ? e(date('M d, Y', strtotime($item['last_maintenance'])))
                                    : '—' ?>
                            </td>

                            <td>
                                <?php if ($overdue): ?>
                                    <span class="ei-status damaged">
                                        Overdue
                                    </span>
                                    <br>
                                <?php endif; ?>

                                <?= !empty($item['next_maintenance'])
                                    ? e(date('M d, Y', strtotime($item['next_maintenance'])))
                                    : '—' ?>
                            </td>

                            <td><?= e($item['location'] ?: '—') ?></td>

                            <td class="ei-notes">
                                <?= e($item['remarks'] ?: '—') ?>
                            </td>

                            <td class="ei-row-actions">
                                <a class="ei-edit-btn"
                                   href="equipment_inventory.php?edit=<?= (int)$item['id'] ?>#equipment-form">
                                    Edit
                                </a>

                                <form method="POST"
                                      action="equipment_inventory.php"
                                      onsubmit="return confirm('Are you sure you want to delete this equipment record?');">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e($_SESSION['equipment_csrf']) ?>">

                                    <input type="hidden" name="action"
                                           value="delete">

                                    <input type="hidden" name="id"
                                           value="<?= (int)$item['id'] ?>">

                                    <button type="submit"
                                            class="ei-delete-btn">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="ei-record-count">
            Showing <?= count($equipment) ?>
            record<?= count($equipment) === 1 ? '' : 's' ?>
            <?= $search !== '' ? 'matching your search' : '' ?>.
        </p>
    </section>

    <footer class="staff-footer">
        SHRCMS — Student Health Record and Clinic Management System
    </footer>

</main>
</body>
</html>
