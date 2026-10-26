

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

// Display the logged-in user's name.
$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error. Check your db.php configuration.');
}

$conn->set_charset('utf8mb4');


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function goBack(string $message, string $type = 'success'): void
{
    header(
        'Location: health_documentation.php?message=' .
        urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

function validDate(string $date): bool
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

/*
|--------------------------------------------------------------------------
| Page messages and search
|--------------------------------------------------------------------------
*/

$message = trim($_GET['message'] ?? '');
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';
$search = trim($_GET['search'] ?? '');
$editingDocument = null;

/*
|--------------------------------------------------------------------------
| Save, update, and delete
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        goBack('Invalid form submission. Refresh and try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            goBack('Invalid examination record.', 'error');
        }

        $stmt = $conn->prepare(
            'DELETE FROM health_documentation WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        goBack(
            $deleted
                ? 'Physical examination record deleted successfully.'
                : 'Examination record not found.',
            $deleted ? 'success' : 'error'
        );
    }

    if ($action === 'save') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $studentIdNo = trim($_POST['student_id_no'] ?? '');
        $examinationDate = trim($_POST['examination_date'] ?? '');
        $heightInput = trim($_POST['height_cm'] ?? '');
        $weightInput = trim($_POST['weight_kg'] ?? '');
        $temperatureInput = trim($_POST['temperature'] ?? '');
        $bloodPressure = trim($_POST['blood_pressure'] ?? '');
        $findings = trim($_POST['findings'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $recordedBy = trim($_SESSION['admin_username'] ?? 'Clinic Staff');

        if ($id === false || $id < 0) {
            goBack('Invalid examination record ID.', 'error');
        }

        if ($studentIdNo === '') {
            goBack('Please select a student.', 'error');
        }

        if (!validDate($examinationDate)) {
            goBack('Please enter a valid examination date.', 'error');
        }

        if ($examinationDate > date('Y-m-d')) {
            goBack('The examination date cannot be in the future.', 'error');
        }

        /*
         * Validate optional measurements.
         * Empty measurements are stored as NULL.
         */
        $measurements = [
            'Height' => [$heightInput, 30, 250],
            'Weight' => [$weightInput, 1, 500],
            'Temperature' => [$temperatureInput, 25, 45]
        ];

        foreach ($measurements as $label => [$value, $min, $max]) {
            if ($value !== '' &&
                (!is_numeric($value) ||
                 (float)$value < $min ||
                 (float)$value > $max)) {
                goBack(
                    $label . ' is outside the accepted input range.',
                    'error'
                );
            }
        }

        if ($bloodPressure !== '' && mb_strlen($bloodPressure) > 20) {
            goBack('Blood pressure must not exceed 20 characters.', 'error');
        }

        if ($findings === '' && $remarks === '' &&
            $heightInput === '' && $weightInput === '' &&
            $temperatureInput === '' && $bloodPressure === '') {
            goBack(
                'Enter at least one examination measurement, finding, or remark.',
                'error'
            );
        }

        $height = $heightInput === '' ? null : (float)$heightInput;
        $weight = $weightInput === '' ? null : (float)$weightInput;
        $temperature = $temperatureInput === ''
            ? null
            : (float)$temperatureInput;

        $recordedBy = mb_substr($recordedBy, 0, 100);

        // Confirm the student exists using the school's student ID number.
        $checkStudent = $conn->prepare(
            'SELECT student_id_no FROM students
             WHERE student_id_no = ? LIMIT 1'
        );
        $checkStudent->bind_param('s', $studentIdNo);
        $checkStudent->execute();
        $studentExists = $checkStudent->get_result()->num_rows > 0;
        $checkStudent->close();

        if (!$studentExists) {
            goBack('The selected student does not exist.', 'error');
        }

        if ($id > 0) {
            $check = $conn->prepare(
                'SELECT id FROM health_documentation WHERE id = ? LIMIT 1'
            );
            $check->bind_param('i', $id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$exists) {
                goBack('Examination record not found.', 'error');
            }

            $stmt = $conn->prepare(
                'UPDATE health_documentation
                 SET student_id_no = ?,
                     examination_date = ?,
                     height_cm = ?,
                     weight_kg = ?,
                     temperature = ?,
                     blood_pressure = ?,
                     findings = ?,
                     remarks = ?,
                     recorded_by = ?
                 WHERE id = ?'
            );

            $stmt->bind_param(
                'ssdddssssi',
                $studentIdNo,
                $examinationDate,
                $height,
                $weight,
                $temperature,
                $bloodPressure,
                $findings,
                $remarks,
                $recordedBy,
                $id
            );

            $stmt->execute();
            $stmt->close();

            goBack('Physical examination record updated successfully.');
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO health_documentation
                 (
                    student_id_no,
                    examination_date,
                    height_cm,
                    weight_kg,
                    temperature,
                    blood_pressure,
                    findings,
                    remarks,
                    recorded_by
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'ssdddssss',
                $studentIdNo,
                $examinationDate,
                $height,
                $weight,
                $temperature,
                $bloodPressure,
                $findings,
                $remarks,
                $recordedBy
            );

            $stmt->execute();
            $stmt->close();

            goBack('Physical examination record saved successfully.');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load record for editing
|--------------------------------------------------------------------------
*/

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {
    $stmt = $conn->prepare(
        'SELECT * FROM health_documentation WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editingDocument = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editingDocument) {
        $message = 'Examination record not found.';
        $messageType = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| Student options
|--------------------------------------------------------------------------
*/

$studentOptions = $conn->query(
    'SELECT student_id_no, fullname, course_year
     FROM students
     ORDER BY fullname ASC'
);

/*
|--------------------------------------------------------------------------
| Examination records and search
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        hd.id,
        hd.student_id_no,
        hd.examination_date,
        hd.height_cm,
        hd.weight_kg,
        hd.temperature,
        hd.blood_pressure,
        hd.findings,
        hd.remarks,
        hd.recorded_by,
        hd.created_at,
        s.fullname,
        s.course_year
    FROM health_documentation AS hd
    LEFT JOIN students AS s
        ON s.student_id_no = hd.student_id_no
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= "
        WHERE hd.student_id_no LIKE ?
           OR s.fullname LIKE ?
           OR hd.examination_date LIKE ?
           OR hd.findings LIKE ?
           OR hd.remarks LIKE ?
           OR hd.recorded_by LIKE ?
    ";

    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term, $term, $term];
    $types = 'ssssss';
}

$sql .= ' ORDER BY hd.examination_date DESC, hd.id DESC';

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$documents = $stmt->get_result();

$totalResult = $conn->query(
    'SELECT COUNT(*) AS total FROM health_documentation'
);
$totalDocuments = (int)($totalResult->fetch_assoc()['total'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Documentation | SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
    <link rel="stylesheet" href="../../css/health-documentation.css">
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
        <a href="health_documentation.php" class="active"><span>▤</span> Health Documentation</a>
        <a href="clinic_visit_records.php"><span>◷</span> Clinic Visit Records</a>

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
            <h1>Health Documentation</h1>
            <p class="staff-header-subtitle">
                Record and manage student physical examination records.
            </p>
        </div>

        <div class="staff-user">
            <img src="../../image/logo2.png" alt="Staff profile">
    
    <div>
        <strong><?= e($username) ?></strong>
        <small>Clinic Staff</small>
    </div>
</div>
    </header>

    <section class="hd-content">

        <?php if ($message !== ''): ?>
            <div class="hd-alert <?= $messageType === 'error' ? 'hd-alert-error' : 'hd-alert-success' ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <div class="hd-summary">
            <div class="hd-summary-icon">▤</div>
            <div>
                <p>Total Examination Records</p>
                <h2><?= number_format($totalDocuments) ?></h2>
            </div>
        </div>

        <section class="hd-panel" id="documentation-form">
            <div class="hd-panel-heading">
                <div>
                    <h2>
                        <?= $editingDocument
                            ? 'Edit Examination Record'
                            : 'Add Physical Examination' ?>
                    </h2>
                    <p>Enter the student's examination measurements and observations.</p>
                </div>

                <?php if ($editingDocument): ?>
                    <a href="health_documentation.php"
                       class="hd-button hd-button-light">Cancel Edit</a>
                <?php endif; ?>
            </div>

            <?php if ($studentOptions->num_rows === 0): ?>
                <div class="hd-form-notice">
                    No student records are available. Add a student in Student Records first.
                </div>
            <?php else: ?>
                <form method="POST" action="health_documentation.php" class="hd-form">
                    <input type="hidden" name="csrf_token"
                           value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id"
                           value="<?= (int)($editingDocument['id'] ?? 0) ?>">

                    <div class="hd-form-grid">
                        <div class="hd-field hd-field-wide">
                            <label for="student_id_no">Student <span>*</span></label>
                            <select name="student_id_no" id="student_id_no" required>
                                <option value="">Select a student</option>
                                <?php
                                $studentOptions->data_seek(0);
                                while ($student = $studentOptions->fetch_assoc()):
                                ?>
                                    <option
                                        value="<?= e($student['student_id_no']) ?>"
                                        <?= ($editingDocument['student_id_no'] ?? '') === $student['student_id_no']
                                            ? 'selected' : '' ?>>
                                        <?= e($student['student_id_no']) ?>
                                        — <?= e($student['fullname']) ?>
                                        <?= !empty($student['course_year'])
                                            ? ' (' . e($student['course_year']) . ')' : '' ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="hd-field">
                            <label for="examination_date">Examination Date <span>*</span></label>
                            <input type="date" name="examination_date"
                                   id="examination_date" required
                                   max="<?= date('Y-m-d') ?>"
                                   value="<?= e($editingDocument['examination_date'] ?? date('Y-m-d')) ?>">
                        </div>

                        <div class="hd-field">
                            <label for="height_cm">Height (cm)</label>
                            <input type="number" name="height_cm" id="height_cm"
                                   min="30" max="250" step="0.01"
                                   placeholder="e.g. 165"
                                   value="<?= e($editingDocument['height_cm'] ?? '') ?>">
                        </div>

                        <div class="hd-field">
                            <label for="weight_kg">Weight (kg)</label>
                            <input type="number" name="weight_kg" id="weight_kg"
                                   min="1" max="500" step="0.01"
                                   placeholder="e.g. 55.5"
                                   value="<?= e($editingDocument['weight_kg'] ?? '') ?>">
                        </div>

                        <div class="hd-field">
                            <label for="temperature">Temperature (°C)</label>
                            <input type="number" name="temperature" id="temperature"
                                   min="25" max="45" step="0.1"
                                   placeholder="e.g. 36.8"
                                   value="<?= e($editingDocument['temperature'] ?? '') ?>">
                        </div>

                        <div class="hd-field">
                            <label for="blood_pressure">Blood Pressure</label>
                            <input type="text" name="blood_pressure" id="blood_pressure"
                                   maxlength="20" placeholder="e.g. 120/80"
                                   value="<?= e($editingDocument['blood_pressure'] ?? '') ?>">
                        </div>

                        <div class="hd-field hd-field-wide">
                            <label for="findings">Findings / Observations</label>
                            <textarea name="findings" id="findings" rows="3"
                                      placeholder="Record factual examination findings..."><?= e($editingDocument['findings'] ?? '') ?></textarea>
                        </div>

                        <div class="hd-field hd-field-wide">
                            <label for="remarks">Remarks / Follow-up Notes</label>
                            <textarea name="remarks" id="remarks" rows="3"
                                      placeholder="Enter additional remarks or follow-up instructions..."><?= e($editingDocument['remarks'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <p class="hd-privacy-note">
                        Record factual observations. Medical diagnosis and treatment decisions
                        should be made by qualified healthcare professionals.
                    </p>

                    <div class="hd-form-actions">
                        <button type="submit" class="hd-button hd-button-primary">
                            <?= $editingDocument ? 'Save Changes' : '+ Save Examination Record' ?>
                        </button>

                        <?php if (!$editingDocument): ?>
                            <button type="reset" class="hd-button hd-button-light">
                                Clear Form
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="hd-panel">
            <div class="hd-panel-heading hd-list-heading">
                <div>
                    <h2>Physical Examination Records</h2>
                    <p>Search, review, print, or manage saved examination records.</p>
                </div>

                <form method="GET" action="health_documentation.php" class="hd-search">
                    <input type="search" name="search"
                           value="<?= e($search) ?>"
                           placeholder="Search student, ID, findings..."
                           aria-label="Search examination records">

                    <button type="submit" class="hd-button hd-button-primary">
                        Search
                    </button>

                    <?php if ($search !== ''): ?>
                        <a href="health_documentation.php"
                           class="hd-button hd-button-light">Clear</a>
                    <?php endif; ?>

                    <button type="button" class="hd-button hd-button-light"
                            onclick="window.print()">Print</button>
                </form>
            </div>

            <div class="hd-table-wrap">
                <table class="hd-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Height</th>
                            <th>Weight</th>
                            <th>Temperature</th>
                            <th>Blood Pressure</th>
                            <th>Findings</th>
                            <th>Remarks</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($documents->num_rows > 0): ?>
                        <?php while ($document = $documents->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= e(date(
                                        'M d, Y',
                                        strtotime($document['examination_date'])
                                    )) ?>
                                </td>

                                <td>
                                    <strong><?= e($document['fullname'] ?? 'Student not found') ?></strong>
                                    <small class="hd-student-number">
                                        <?= e($document['student_id_no']) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= $document['height_cm'] !== null
                                        ? e($document['height_cm']) . ' cm' : '—' ?>
                                </td>

                                <td>
                                    <?= $document['weight_kg'] !== null
                                        ? e($document['weight_kg']) . ' kg' : '—' ?>
                                </td>

                                <td>
                                    <?= $document['temperature'] !== null
                                        ? e($document['temperature']) . ' °C' : '—' ?>
                                </td>

                                <td><?= e($document['blood_pressure'] ?: '—') ?></td>
                                <td class="hd-long-text">
                                    <?= e($document['findings'] ?: '—') ?>
                                </td>
                                <td class="hd-long-text">
                                    <?= e($document['remarks'] ?: '—') ?>
                                </td>
                                <td><?= e($document['recorded_by'] ?: '—') ?></td>

                                <td>
                                    <div class="hd-actions">
                                        <a class="hd-edit"
                                           href="health_documentation.php?edit=<?= (int)$document['id'] ?>#documentation-form">
                                            Edit
                                        </a>

                                        <form method="POST" action="health_documentation.php"
                                              onsubmit="return confirm('Delete this examination record? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id"
                                                   value="<?= (int)$document['id'] ?>">
                                            <button type="submit" class="hd-delete">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="hd-empty">
                                <strong>No examination records found.</strong>
                                <p>Add an examination record or try a different search.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="hd-table-footer">
                Showing <?= number_format($documents->num_rows) ?> record(s)
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
