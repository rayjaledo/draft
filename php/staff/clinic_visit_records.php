
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

// ADD THIS LINE
$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

if ($conn->connect_error) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectVisit(string $message, string $type = 'success'): void
{
    header(
        'Location: clinic_visit_records.php?message=' .
        urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

function isValidVisitDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    $errors = DateTime::getLastErrors();

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date
        && ($errors === false || (
            $errors['warning_count'] === 0 &&
            $errors['error_count'] === 0
        ));
}

$message = trim($_GET['message'] ?? '');
$messageType = ($_GET['type'] ?? '') === 'error'
    ? 'error'
    : 'success';

$search = trim($_GET['search'] ?? '');
$editingVisit = null;

/*
|--------------------------------------------------------------------------
| Create, update, and delete visit records
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        redirectVisit(
            'Invalid form submission. Please refresh and try again.',
            'error'
        );
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            redirectVisit('Invalid visit record.', 'error');
        }

        $stmt = $conn->prepare(
            'DELETE FROM consultations WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $deleted = $stmt->affected_rows;
        $stmt->close();

        redirectVisit(
            $deleted
                ? 'Clinic visit record deleted successfully.'
                : 'Clinic visit record not found.',
            $deleted ? 'success' : 'error'
        );
    }

    if ($action === 'save') {
        $id = filter_var(
            $_POST['id'] ?? 0,
            FILTER_VALIDATE_INT
        );

        $studentId = filter_var(
            $_POST['student_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $visitDate = trim($_POST['consultation_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $staffName = trim(
            $_SESSION['admin_username'] ?? 'Clinic Staff'
        );

        if ($id === false || $id < 0) {
            redirectVisit('Invalid visit record ID.', 'error');
        }

        if (!$studentId || $studentId < 1) {
            redirectVisit('Please select a student.', 'error');
        }

        if (!isValidVisitDate($visitDate)) {
            redirectVisit('Please enter a valid visit date.', 'error');
        }

        if ($visitDate > date('Y-m-d')) {
            redirectVisit(
                'The visit date cannot be in the future.',
                'error'
            );
        }

        if ($reason === '') {
            redirectVisit(
                'Please enter the reason for the clinic visit.',
                'error'
            );
        }

        if (mb_strlen($reason) > 5000 || mb_strlen($remarks) > 10000) {
            redirectVisit(
                'The reason or remarks are too long.',
                'error'
            );
        }

        $staffName = mb_substr($staffName, 0, 100);

        // Verify that the selected student exists.
        $checkStudent = $conn->prepare(
            'SELECT id FROM students WHERE id = ? LIMIT 1'
        );
        $checkStudent->bind_param('i', $studentId);
        $checkStudent->execute();

        $studentExists =
            $checkStudent->get_result()->num_rows > 0;

        $checkStudent->close();

        if (!$studentExists) {
            redirectVisit(
                'The selected student does not exist.',
                'error'
            );
        }

        if ($id > 0) {
            // Confirm the visit exists before updating.
            $checkVisit = $conn->prepare(
                'SELECT id FROM consultations WHERE id = ? LIMIT 1'
            );
            $checkVisit->bind_param('i', $id);
            $checkVisit->execute();

            $visitExists =
                $checkVisit->get_result()->num_rows > 0;

            $checkVisit->close();

            if (!$visitExists) {
                redirectVisit('Visit record not found.', 'error');
            }

            $stmt = $conn->prepare(
                'UPDATE consultations
                 SET student_id = ?,
                     consultation_date = ?,
                     reason = ?,
                     remarks = ?,
                     staff_name = ?
                 WHERE id = ?'
            );

            $stmt->bind_param(
                'issssi',
                $studentId,
                $visitDate,
                $reason,
                $remarks,
                $staffName,
                $id
            );

            $stmt->execute();
            $stmt->close();

            redirectVisit(
                'Clinic visit record updated successfully.'
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO consultations
                 (student_id, consultation_date, reason, remarks, staff_name)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'issss',
                $studentId,
                $visitDate,
                $reason,
                $remarks,
                $staffName
            );

            $stmt->execute();
            $stmt->close();

            redirectVisit(
                'Clinic visit record saved successfully.'
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load a record for editing
|--------------------------------------------------------------------------
*/

$editId = filter_var(
    $_GET['edit'] ?? null,
    FILTER_VALIDATE_INT
);

if ($editId && $editId > 0) {
    $stmt = $conn->prepare(
        'SELECT * FROM consultations WHERE id = ? LIMIT 1'
    );

    $stmt->bind_param('i', $editId);
    $stmt->execute();

    $editingVisit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editingVisit) {
        $message = 'Clinic visit record not found.';
        $messageType = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| Load students for the form
|--------------------------------------------------------------------------
*/

$studentOptions = $conn->query(
    'SELECT id, student_id_no, fullname, course_year
     FROM students
     ORDER BY fullname ASC'
);

/*
|--------------------------------------------------------------------------
| Search and display visit records
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        c.id,
        c.student_id,
        c.consultation_date,
        c.reason,
        c.remarks,
        c.staff_name,
        s.student_id_no,
        s.fullname,
        s.course_year
    FROM consultations AS c
    INNER JOIN students AS s
        ON s.id = c.student_id
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= "
        WHERE s.student_id_no LIKE ?
           OR s.fullname LIKE ?
           OR s.course_year LIKE ?
           OR c.consultation_date LIKE ?
           OR c.reason LIKE ?
           OR c.remarks LIKE ?
           OR c.staff_name LIKE ?
    ";

    $term = '%' . $search . '%';

    $params = [
        $term, $term, $term, $term,
        $term, $term, $term
    ];

    $types = 'sssssss';
}

$sql .= ' ORDER BY c.consultation_date DESC, c.id DESC';

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$visits = $stmt->get_result();

$totalResult = $conn->query(
    'SELECT COUNT(*) AS total FROM consultations'
);
$totalVisits = (int)(
    $totalResult->fetch_assoc()['total'] ?? 0
);

$todayResult = $conn->query(
    'SELECT COUNT(*) AS total
     FROM consultations
     WHERE consultation_date = CURDATE()'
);
$todayVisits = (int)(
    $todayResult->fetch_assoc()['total'] ?? 0
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clinic Visit Records | SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
    <link rel="stylesheet" href="../../css/clinic-visit-records.css">
</head>

<body class="staff-dashboard-body">

<aside class="staff-sidebar">
    <div class="staff-brand">
        <img src="../../image/logo2.png" alt="SHRCMS Logo">
        <div>
            <strong>SHRCMS</strong>
            <span>Clinic Management</span>
        </div>
    </div>

    <nav class="staff-nav">
        <p class="staff-nav-label">MAIN MENU</p>

        <a href="staff_dashboard.php"><span>▦</span> Dashboard</a>
        <a href="student_records.php"><span>♙</span> Student Records</a>
        <a href="clinic_consultation.php"><span>✚</span> Clinic Consultation</a>
        <a href="health_documentation.php"><span>▤</span> Health Documentation</a>
        <a href="clinic_visit_records.php" class="active">
            <span>◷</span> Clinic Visit Records
        </a>

        <p class="staff-nav-label">INVENTORY &amp; REPORTS</p>

        <a href="equipment_inventory.php"><span>▣</span> Equipment Inventory</a>
        <a href="medicine_inventory.php"><span>⚕</span> Medicine Inventory</a>
        <a href="clinic_reports.php"><span>▥</span> Clinic Reports</a>
    </nav>

    <a href="../logout.php" class="staff-logout"
       onclick="return confirm('Are you sure you want to log out?');">
        ↪ Log Out
    </a>
</aside>

<main class="staff-main">

    <header class="staff-header">
        <div>
            <p class="staff-eyebrow">CLINIC MANAGEMENT</p>
            <h1>Clinic Visit Records</h1>
            <p class="staff-header-subtitle">
                Track student clinic visits and their consultation history.
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

    <section class="cvr-content">

        <?php if ($message !== ''): ?>
            <div class="cvr-alert <?= $messageType === 'error'
                ? 'cvr-alert-error'
                : 'cvr-alert-success' ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <div class="cvr-summary-grid">
            <div class="cvr-summary-card">
                <div class="cvr-summary-icon">◷</div>
                <div>
                    <p>Total Visit Records</p>
                    <h2><?= number_format($totalVisits) ?></h2>
                </div>
            </div>

            <div class="cvr-summary-card">
                <div class="cvr-summary-icon">▦</div>
                <div>
                    <p>Visits Today</p>
                    <h2><?= number_format($todayVisits) ?></h2>
                </div>
            </div>
        </div>

        <section class="cvr-panel" id="visit-form">
            <div class="cvr-panel-heading">
                <div>
                    <h2><?= $editingVisit
                        ? 'Edit Clinic Visit'
                        : 'Record a Clinic Visit' ?></h2>
                    <p>Enter the student's visit details below.</p>
                </div>

                <?php if ($editingVisit): ?>
                    <a href="clinic_visit_records.php"
                       class="cvr-button cvr-button-light">
                        Cancel Edit
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($studentOptions->num_rows === 0): ?>
                <div class="cvr-notice">
                    No students are registered yet.
                    Add a student through Student Records first.
                </div>
            <?php else: ?>

                <form method="POST"
                      action="clinic_visit_records.php"
                      class="cvr-form">

                    <input type="hidden" name="csrf_token"
                           value="<?= e($_SESSION['csrf_token']) ?>">

                    <input type="hidden" name="action" value="save">

                    <input type="hidden" name="id"
                           value="<?= (int)($editingVisit['id'] ?? 0) ?>">

                    <div class="cvr-form-grid">

                        <div class="cvr-field cvr-field-wide">
                            <label for="student_id">Student <span>*</span></label>

                            <select name="student_id" id="student_id" required>
                                <option value="">Select a student</option>

                                <?php
                                $studentOptions->data_seek(0);

                                while ($student = $studentOptions->fetch_assoc()):
                                ?>
                                    <option
                                        value="<?= (int)$student['id'] ?>"
                                        <?= (int)($editingVisit['student_id'] ?? 0)
                                            === (int)$student['id']
                                            ? 'selected'
                                            : '' ?>>

                                        <?= e($student['student_id_no']) ?>
                                        — <?= e($student['fullname']) ?>

                                        <?= !empty($student['course_year'])
                                            ? ' (' . e($student['course_year']) . ')'
                                            : '' ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="cvr-field">
                            <label for="consultation_date">Visit Date <span>*</span></label>

                            <input type="date"
                                   name="consultation_date"
                                   id="consultation_date"
                                   required
                                   max="<?= date('Y-m-d') ?>"
                                   value="<?= e(
                                       $editingVisit['consultation_date']
                                       ?? date('Y-m-d')
                                   ) ?>">
                        </div>

                        <div class="cvr-field cvr-field-wide">
                            <label for="reason">Reason for Visit <span>*</span></label>

                            <textarea name="reason"
                                      id="reason"
                                      rows="3"
                                      maxlength="5000"
                                      required
                                      placeholder="Describe why the student visited the clinic..."><?= e($editingVisit['reason'] ?? '') ?></textarea>
                        </div>

                        <div class="cvr-field cvr-field-wide">
                            <label for="remarks">Remarks / Assistance Provided</label>

                            <textarea name="remarks"
                                      id="remarks"
                                      rows="3"
                                      maxlength="10000"
                                      placeholder="Record observations, assistance provided, or follow-up instructions..."><?= e($editingVisit['remarks'] ?? '') ?></textarea>
                        </div>

                    </div>

                    <p class="cvr-privacy-note">
                        Record factual visit information. Medical diagnosis and
                        treatment decisions should be made by qualified healthcare professionals.
                    </p>

                    <div class="cvr-form-actions">
                        <button type="submit" class="cvr-button cvr-button-primary">
                            <?= $editingVisit
                                ? 'Save Changes'
                                : '+ Save Visit Record' ?>
                        </button>

                        <?php if (!$editingVisit): ?>
                            <button type="reset" class="cvr-button cvr-button-light">
                                Clear Form
                            </button>
                        <?php endif; ?>
                    </div>

                </form>
            <?php endif; ?>
        </section>

        <section class="cvr-panel">
            <div class="cvr-panel-heading cvr-list-heading">
                <div>
                    <h2>Visit History</h2>
                    <p>Review and manage saved clinic visit records.</p>
                </div>

                <form method="GET"
                      action="clinic_visit_records.php"
                      class="cvr-search">

                    <input type="search"
                           name="search"
                           value="<?= e($search) ?>"
                           placeholder="Search student, ID, reason..."
                           aria-label="Search clinic visit records">

                    <button type="submit" class="cvr-button cvr-button-primary">
                        Search
                    </button>

                    <?php if ($search !== ''): ?>
                        <a href="clinic_visit_records.php"
                           class="cvr-button cvr-button-light">
                            Clear
                        </a>
                    <?php endif; ?>

                    <button type="button"
                            class="cvr-button cvr-button-light"
                            onclick="window.print()">
                        Print
                    </button>
                </form>
            </div>

            <div class="cvr-table-wrap">
                <table class="cvr-table">
                    <thead>
                        <tr>
                            <th>Visit Date</th>
                            <th>Student</th>
                            <th>Reason for Visit</th>
                            <th>Remarks / Assistance</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($visits->num_rows > 0): ?>

                        <?php while ($visit = $visits->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= e(date(
                                        'M d, Y',
                                        strtotime($visit['consultation_date'])
                                    )) ?>
                                </td>

                                <td>
                                    <strong><?= e($visit['fullname']) ?></strong>
                                    <small class="cvr-student-number">
                                        <?= e($visit['student_id_no']) ?>
                                    </small>
                                    <?php if (!empty($visit['course_year'])): ?>
                                        <small class="cvr-student-number">
                                            <?= e($visit['course_year']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td class="cvr-long-text">
                                    <?= e($visit['reason']) ?>
                                </td>

                                <td class="cvr-long-text">
                                    <?= e($visit['remarks'] ?: '—') ?>
                                </td>

                                <td><?= e($visit['staff_name'] ?: '—') ?></td>

                                <td>
                                    <div class="cvr-actions">
                                        <a class="cvr-edit"
                                           href="clinic_visit_records.php?edit=<?= (int)$visit['id'] ?>#visit-form">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="clinic_visit_records.php"
                                              onsubmit="return confirm('Delete this clinic visit record? This cannot be undone.');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= e($_SESSION['csrf_token']) ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="delete">

                                            <input type="hidden"
                                                   name="id"
                                                   value="<?= (int)$visit['id'] ?>">

                                            <button type="submit" class="cvr-delete">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="cvr-empty">
                                <strong>No clinic visit records found.</strong>
                                <p>Add a visit or try a different search.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="cvr-table-footer">
                Showing <?= number_format($visits->num_rows) ?> record(s)
                <?php if ($search !== ''): ?>
                    matching <strong><?= e($search) ?></strong>
                <?php endif; ?>
            </div>
        </section>

    </section>

    <footer class="staff-footer">
        Student Health Record and Clinic Management System
    </footer>

</main>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
