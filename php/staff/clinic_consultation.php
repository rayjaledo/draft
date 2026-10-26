
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: ../login.php');
    exit();
}

$conn->set_charset('utf8mb4');

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function validDate($date) {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    $errors = DateTime::getLastErrors();

    return $d !== false &&
        ($errors === false ||
        ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) &&
        $d->format('Y-m-d') === $date;
}

if (empty($_SESSION['consultation_csrf'])) {
    $_SESSION['consultation_csrf'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg = '';

$student_id = '';
$consultation_date = date('Y-m-d');
$reason = 'General Check-up';
$remarks = '';

$reasons = [
    'General Check-up',
    'Fever / Headache',
    'Injury / Wound',
    'Stomachache',
    'Cough / Cold',
    'Dizziness',
    'Physical Examination',
    'Others'
];

/* SAVE CONSULTATION */
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_consultation'])) {

    $csrf = $_POST['csrf_token'] ?? '';

    $student_id = trim($_POST['student_id'] ?? '');
    $consultation_date = trim($_POST['consultation_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $staff_name = $_SESSION['admin_username'] ?? 'Staff';

    if (!hash_equals($_SESSION['consultation_csrf'], $csrf)) {
        $error_msg = 'Your session token is invalid. Please refresh the page and try again.';
    } elseif (
        $student_id === '' ||
        !ctype_digit($student_id) ||
        !validDate($consultation_date) ||
        $consultation_date > date('Y-m-d') ||
        !in_array($reason, $reasons, true)
    ) {
        $error_msg = 'Please select a student, enter a valid date, and choose a reason.';
    } elseif (strlen($remarks) > 5000) {
        $error_msg = 'Remarks must not exceed 5,000 characters.';
    } else {
        try {
            // Confirm that the selected student exists.
            $check = $conn->prepare(
                "SELECT id FROM students WHERE id = ? LIMIT 1"
            );
            $check->bind_param('i', $student_id);
            $check->execute();
            $studentResult = $check->get_result();
            $studentExists = $studentResult->num_rows > 0;
            $check->close();

            if (!$studentExists) {
                $error_msg = 'The selected student could not be found. Please select a registered student.';
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO consultations
                    (student_id, consultation_date, reason, remarks, staff_name)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $studentIdInt = (int)$student_id;

                $stmt->bind_param(
                    'issss',
                    $studentIdInt,
                    $consultation_date,
                    $reason,
                    $remarks,
                    $staff_name
                );

                $stmt->execute();
                $stmt->close();

                // Prevent duplicate submissions after refreshing.
                $_SESSION['consultation_flash'] =
                    'Clinic consultation saved successfully!';

                header('Location: clinic_consultation.php');
                exit();
            }
        } catch (Throwable $ex) {
            error_log('Consultation save error: ' . $ex->getMessage());
            $error_msg = 'Unable to save the consultation. Please check your database structure and try again.';
        }
    }
}

/* ONE-TIME SUCCESS MESSAGE */
if (!empty($_SESSION['consultation_flash'])) {
    $success_msg = $_SESSION['consultation_flash'];
    unset($_SESSION['consultation_flash']);
}

/* STUDENT DROPDOWN */
$students = [];

try {
    $studentQuery = $conn->query(
        "SELECT id, student_id_no, fullname, course_year
         FROM students
         ORDER BY fullname ASC"
    );

    while ($row = $studentQuery->fetch_assoc()) {
        $students[] = $row;
    }
} catch (Throwable $ex) {
    error_log('Student list error: ' . $ex->getMessage());
    $error_msg = $error_msg ?: 'Unable to load the student list.';
}

/* SEARCH AND DATE FILTER */
$search = trim($_GET['search'] ?? '');
$filter_date = trim($_GET['date'] ?? '');

if ($filter_date !== '' && !validDate($filter_date)) {
    $filter_date = '';
}

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(s.fullname LIKE ? OR s.student_id_no LIKE ?
                      OR c.reason LIKE ? OR c.staff_name LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
    $types .= 'ssss';
}

if ($filter_date !== '') {
    $conditions[] = "c.consultation_date = ?";
    $params[] = $filter_date;
    $types .= 's';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

/* CONSULTATION STATISTICS */
$totalConsultations = 0;
$todayConsultations = 0;
$studentCount = count($students);

try {
    $result = $conn->query("SELECT COUNT(*) AS total FROM consultations");
    $totalConsultations = (int)$result->fetch_assoc()['total'];

    $today = date('Y-m-d');
    $stat = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM consultations
         WHERE consultation_date = ?"
    );
    $stat->bind_param('s', $today);
    $stat->execute();
    $todayConsultations = (int)$stat->get_result()->fetch_assoc()['total'];
    $stat->close();
} catch (Throwable $ex) {
    error_log('Consultation statistics error: ' . $ex->getMessage());
}

/* RECENT CONSULTATION RECORDS */
$consultations = [];

try {
    $sql = "SELECT c.id, c.student_id, c.consultation_date,
                   c.reason, c.remarks, c.staff_name,
                   s.student_id_no, s.fullname
            FROM consultations c
            INNER JOIN students s ON s.id = c.student_id
            $where
            ORDER BY c.consultation_date DESC, c.id DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $records = $stmt->get_result();

    while ($row = $records->fetch_assoc()) {
        $consultations[] = $row;
    }

    $stmt->close();
} catch (Throwable $ex) {
    error_log('Consultation list error: ' . $ex->getMessage());
    $error_msg = $error_msg ?: 'Unable to load consultation records.';
}

$username = e($_SESSION['admin_username'] ?? 'Staff');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Consultation - SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
   <link rel="stylesheet" href="../../css/consultation.css?v=2">
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
        <a href="clinic_consultation.php" class="active"><span>✚</span> Clinic Consultation</a>
        <a href="health_documentation.php"><span>▤</span> Health Documentation</a>
        <a href="clinic_visit_records.php"><span>▣</span> Clinic Visit Records</a>

        <p class="sidebar-label">INVENTORY MANAGEMENT</p>
        <a href="equipment_inventory.php"><span>⚙</span> Equipment Inventory</a>
        <a href="medicine_inventory.php"><span>✚</span> Medicine Inventory</a>

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
            <p class="page-eyebrow">STUDENT HEALTH SERVICES</p>
            <h1>Clinic Consultation</h1>
            <p class="header-subtitle">
                Record and review student clinic visits and consultation notes.
            </p>
        </div>

        <div class="staff-user">
            <img src="../../image/logo2.png" alt="Staff profile">
            <div>
                <strong><?= $username ?></strong>
                <span>Clinic Staff</span>
            </div>
        </div>
    </header>

    <?php if ($success_msg !== ''): ?>
        <div class="consultation-message success-message" role="status">
            <strong>✓ Success</strong>
            <p><?= e($success_msg) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error_msg !== ''): ?>
        <div class="consultation-message error-message" role="alert">
            <strong>! Notice</strong>
            <p><?= e($error_msg) ?></p>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->
    <section class="consultation-stats">
        <article class="consultation-stat">
            <span class="stat-icon">▤</span>
            <div>
                <p>Total Consultations</p>
                <h2><?= number_format($totalConsultations) ?></h2>
                <small>All recorded visits</small>
            </div>
        </article>

        <article class="consultation-stat">
            <span class="stat-icon">◷</span>
            <div>
                <p>Today's Consultations</p>
                <h2><?= number_format($todayConsultations) ?></h2>
                <small><?= e(date('F j, Y')) ?></small>
            </div>
        </article>

        <article class="consultation-stat">
            <span class="stat-icon">♙</span>
            <div>
                <p>Registered Students</p>
                <h2><?= number_format($studentCount) ?></h2>
                <small>Available for selection</small>
            </div>
        </article>
    </section>

    <!-- NEW CONSULTATION FORM -->
    <section class="consultation-panel">
        <div class="consultation-panel-heading">
            <div class="consultation-heading-icon">✚</div>
            <div>
                <h2>New Clinic Consultation</h2>
                <p>Enter the student's visit information below.</p>
            </div>
        </div>

        <div class="consultation-divider"></div>

        <form action="clinic_consultation.php" method="POST"
              class="consultation-form">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['consultation_csrf']) ?>">

            <div class="consultation-form-grid">

                <div class="consultation-field full-width">
                    <label for="student_id">Select Student <span class="required">*</span></label>

                    <select id="student_id" name="student_id" required>
                        <option value="">Choose a registered student</option>
                        <?php foreach ($students as $student): ?>
                            <option
                                value="<?= (int)$student['id'] ?>"
                                <?= $student_id === (string)$student['id'] ? 'selected' : '' ?>>
                                <?= e($student['student_id_no']) ?> —
                                <?= e($student['fullname']) ?>
                                <?= $student['course_year'] ? ' (' . e($student['course_year']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small>Select the student from the registered student list.</small>
                </div>

                <div class="consultation-field">
                    <label for="consultation_date">Consultation Date <span class="required">*</span></label>
                    <input type="date" id="consultation_date"
                           name="consultation_date"
                           value="<?= e($consultation_date) ?>"
                           max="<?= e(date('Y-m-d')) ?>" required>
                </div>

                <div class="consultation-field">
                    <label for="reason">Reason for Visit <span class="required">*</span></label>
                    <select id="reason" name="reason" required>
                        <?php foreach ($reasons as $option): ?>
                            <option value="<?= e($option) ?>"
                                <?= $reason === $option ? 'selected' : '' ?>>
                                <?= e($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="consultation-field full-width">
                    <label for="remarks">Notes / Remarks</label>
                    <textarea id="remarks" name="remarks" rows="5"
                              maxlength="5000"
                              placeholder="Record relevant observations, care provided, or follow-up instructions..."><?= e($remarks) ?></textarea>
                    <div class="remarks-footer">
                        <small>Document factual observations and actions taken.</small>
                        <small><span id="remarks-count">0</span>/5000</small>
                    </div>
                </div>
            </div>

            <div class="consultation-form-footer">
                <p><span class="required">*</span> Required fields</p>
                <button type="reset" class="consultation-reset">Clear Form</button>
                <button type="submit" name="save_consultation"
                        class="consultation-submit">
                    <span>＋</span> Save Consultation
                </button>
            </div>
        </form>
    </section>

    <!-- CONSULTATION HISTORY -->
    <section class="consultation-panel history-panel">
        <div class="consultation-panel-heading history-heading">
            <div>
                <h2>Consultation History</h2>
                <p>Search and review previously recorded clinic visits.</p>
            </div>
            <span class="record-count"><?= count($consultations) ?> records shown</span>
        </div>

        <form action="clinic_consultation.php" method="GET"
              class="consultation-search-form">
            <div class="search-input-wrap">
                <span>⌕</span>
                <input type="search" name="search"
                       value="<?= e($search) ?>"
                       placeholder="Search student, ID, reason, or staff...">
            </div>

            <input type="date" name="date" value="<?= e($filter_date) ?>"
                   aria-label="Filter by consultation date">

            <button type="submit" class="consultation-search-button">Search</button>
            <a href="clinic_consultation.php" class="consultation-reset-link">Reset</a>
        </form>

        <div class="consultation-table-wrap">
            <table class="consultation-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Date</th>
                        <th>Reason for Visit</th>
                        <th>Notes / Remarks</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($consultations)): ?>
                    <tr>
                        <td colspan="5" class="consultation-empty">
                            <span>▤</span>
                            <strong>No consultation records found</strong>
                            <p>Try another search or record a new consultation.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($consultations as $record): ?>
                        <tr>
                            <td>
                                <strong><?= e($record['fullname']) ?></strong>
                                <small class="student-number">
                                    <?= e($record['student_id_no']) ?>
                                </small>
                            </td>
                            <td><?= e(date('M j, Y', strtotime($record['consultation_date']))) ?></td>
                            <td><span class="reason-tag"><?= e($record['reason']) ?></span></td>
                            <td class="remarks-cell">
                                <?= $record['remarks'] !== ''
                                    ? nl2br(e($record['remarks']))
                                    : '<span class="no-remarks">No notes provided</span>' ?>
                            </td>
                            <td><?= e($record['staff_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="consultation-table-footer">
            <span>Showing up to 100 records, newest first.</span>
            <span>Keep student health information confidential.</span>
        </div>
    </section>

    <footer class="staff-footer">
        SHRCMS — Student Health Record and Clinic Management System
    </footer>

</main>

<script>
const remarks = document.getElementById('remarks');
const counter = document.getElementById('remarks-count');
const form = document.querySelector('.consultation-form');

function updateRemarksCount() {
    counter.textContent = remarks.value.length;
}

remarks.addEventListener('input', updateRemarksCount);

form.addEventListener('reset', function () {
    setTimeout(updateRemarksCount, 0);
});

updateRemarksCount();
</script>

</body>
</html>
