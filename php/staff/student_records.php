
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

$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

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

function redirectWithMessage(
    string $message,
    string $type = 'success'
): void {
    header(
        'Location: student_records.php?message=' .
        urlencode($message) .
        '&type=' . urlencode($type)
    );
    exit();
}

$message = trim((string)($_GET['message'] ?? ''));
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';
$search = trim((string)($_GET['search'] ?? ''));
$editingStudent = null;

/* =========================================
   HANDLE ADD, UPDATE, AND DELETE
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        redirectWithMessage(
            'Invalid form submission. Please try again.',
            'error'
        );
    }

    $action = $_POST['action'] ?? '';

    /* DELETE STUDENT */

    if ($action === 'delete') {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            redirectWithMessage('Invalid student record.', 'error');
        }

        // Find the student before checking related records.
        $check = $conn->prepare(
            'SELECT student_id_no FROM students WHERE id = ? LIMIT 1'
        );
        $check->bind_param('i', $id);
        $check->execute();
        $studentToDelete = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$studentToDelete) {
            redirectWithMessage(
                'Student record was not found.',
                'error'
            );
        }

        // Do not delete a student who has consultation records.
        $check = $conn->prepare(
            'SELECT id FROM consultations WHERE student_id = ? LIMIT 1'
        );
        $check->bind_param('i', $id);
        $check->execute();
        $hasConsultations = $check->get_result()->num_rows > 0;
        $check->close();

        if ($hasConsultations) {
            redirectWithMessage(
                'This student has consultation records and cannot be deleted.',
                'error'
            );
        }

        // Do not delete a student who has health documentation.
        $studentIdForHealth = $studentToDelete['student_id_no'];

        $check = $conn->prepare(
            'SELECT id FROM health_documentation
             WHERE student_id_no = ? LIMIT 1'
        );
        $check->bind_param('s', $studentIdForHealth);
        $check->execute();
        $hasHealthRecords = $check->get_result()->num_rows > 0;
        $check->close();

        if ($hasHealthRecords) {
            redirectWithMessage(
                'This student has health documentation records and cannot be deleted.',
                'error'
            );
        }

        $stmt = $conn->prepare(
            'DELETE FROM students WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        redirectWithMessage(
            $deleted
                ? 'Student record deleted successfully.'
                : 'Student record was not deleted.',
            $deleted ? 'success' : 'error'
        );
    }

    /* ADD OR UPDATE STUDENT */

    if ($action === 'save') {
        $id = filter_var(
            $_POST['id'] ?? 0,
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id < 0) {
            redirectWithMessage('Invalid student record ID.', 'error');
        }

        $id = (int)$id;

        $studentIdNo = trim((string)($_POST['student_id_no'] ?? ''));
        $fullname = trim((string)($_POST['fullname'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $ageInput = trim((string)($_POST['age'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? ''));
        $courseYear = trim((string)($_POST['course_year'] ?? ''));
        $contact = trim((string)($_POST['contact'] ?? ''));

        $age = null;

        // Required fields.
        if ($studentIdNo === '' || $fullname === '') {
            redirectWithMessage(
                'Student ID and full name are required.',
                'error'
            );
        }

        // Validate field lengths.
        if (
            mb_strlen($studentIdNo) > 50 ||
            mb_strlen($fullname) > 100 ||
            mb_strlen($email) > 100 ||
            mb_strlen($gender) > 20 ||
            mb_strlen($courseYear) > 50 ||
            mb_strlen($contact) > 20
        ) {
            redirectWithMessage(
                'One or more fields exceed the allowed length.',
                'error'
            );
        }

        // Validate email when provided.
        if (
            $email !== '' &&
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            redirectWithMessage(
                'Please enter a valid email address.',
                'error'
            );
        }

        // Validate age.
        if ($ageInput !== '') {
            $validatedAge = filter_var(
                $ageInput,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 120]]
            );

            if ($validatedAge === false) {
                redirectWithMessage(
                    'Please enter a valid age between 1 and 120.',
                    'error'
                );
            }

            $age = $validatedAge;
        }

        // Validate gender.
        if (!in_array($gender, ['', 'Male', 'Female', 'Other'], true)) {
            redirectWithMessage(
                'Please select a valid gender.',
                'error'
            );
        }

        // Check duplicate student ID.
        if ($id > 0) {
            $check = $conn->prepare(
                'SELECT id FROM students
                 WHERE student_id_no = ? AND id <> ?
                 LIMIT 1'
            );
            $check->bind_param('si', $studentIdNo, $id);
        } else {
            $check = $conn->prepare(
                'SELECT id FROM students
                 WHERE student_id_no = ?
                 LIMIT 1'
            );
            $check->bind_param('s', $studentIdNo);
        }

        $check->execute();
        $duplicateStudentId = $check->get_result()->num_rows > 0;
        $check->close();

        if ($duplicateStudentId) {
            redirectWithMessage(
                'That student ID already exists. Please use a unique student ID.',
                'error'
            );
        }

        // Check duplicate email, ignoring blank emails.
        if ($email !== '') {
            if ($id > 0) {
                $check = $conn->prepare(
                    'SELECT id FROM students
                     WHERE email = ? AND id <> ?
                     LIMIT 1'
                );
                $check->bind_param('si', $email, $id);
            } else {
                $check = $conn->prepare(
                    'SELECT id FROM students
                     WHERE email = ?
                     LIMIT 1'
                );
                $check->bind_param('s', $email);
            }

            $check->execute();
            $duplicateEmail = $check->get_result()->num_rows > 0;
            $check->close();

            if ($duplicateEmail) {
                redirectWithMessage(
                    'That email is already linked to another student.',
                    'error'
                );
            }
        }

        // UPDATE EXISTING STUDENT.
        if ($id > 0) {
            $stmt = $conn->prepare(
                'UPDATE students
                 SET student_id_no = ?,
                     fullname = ?,
                     age = ?,
                     gender = ?,
                     course_year = ?,
                     contact = ?,
                     email = ?
                 WHERE id = ?'
            );

            $stmt->bind_param(
                'ssissssi',
                $studentIdNo,
                $fullname,
                $age,
                $gender,
                $courseYear,
                $contact,
                $email,
                $id
            );

            if (!$stmt->execute()) {
                $stmt->close();
                redirectWithMessage(
                    'Unable to update the student record.',
                    'error'
                );
            }

            $stmt->close();

            redirectWithMessage(
                'Student record updated successfully.'
            );
        }

        // INSERT NEW STUDENT.
        $stmt = $conn->prepare(
            'INSERT INTO students
                (student_id_no, fullname, age, gender,
                 course_year, contact, email)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'ssissss',
            $studentIdNo,
            $fullname,
            $age,
            $gender,
            $courseYear,
            $contact,
            $email
        );

        if (!$stmt->execute()) {
            $stmt->close();
            redirectWithMessage(
                'Unable to add the student record.',
                'error'
            );
        }

        $stmt->close();

        redirectWithMessage('Student added successfully.');
    }
}

/* =========================================
   LOAD STUDENT FOR EDITING
========================================= */

$editId = filter_var(
    $_GET['edit'] ?? null,
    FILTER_VALIDATE_INT
);

if ($editId && $editId > 0) {
    $stmt = $conn->prepare(
        'SELECT id, student_id_no, fullname, age, gender,
                course_year, contact, email
         FROM students
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editingStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editingStudent) {
        $message = 'Student record not found.';
        $messageType = 'error';
    }
}

/* =========================================
   SEARCH AND DISPLAY STUDENTS
========================================= */

$sql = 'SELECT id, student_id_no, fullname, age, gender,
               course_year, contact, email, created_at
        FROM students';

if ($search !== '') {
    $sql .= ' WHERE student_id_no LIKE ?
              OR fullname LIKE ?
              OR course_year LIKE ?
              OR contact LIKE ?
              OR email LIKE ?';

    $term = '%' . $search . '%';

    $stmt = $conn->prepare($sql . ' ORDER BY fullname ASC');
    $stmt->bind_param(
        'sssss',
        $term,
        $term,
        $term,
        $term,
        $term
    );
} else {
    $stmt = $conn->prepare($sql . ' ORDER BY fullname ASC');
}

$stmt->execute();
$students = $stmt->get_result();
$stmt->close();

$countResult = $conn->query(
    'SELECT COUNT(*) AS total FROM students'
);
$totalStudents = (int)($countResult->fetch_assoc()['total'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Records | SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
    <link rel="stylesheet" href="../../css/student-records.css">
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
        <a href="student_records.php" class="active"><span>♙</span> Student Records</a>
        <a href="clinic_consultation.php"><span>✚</span> Clinic Consultation</a>
        <a href="health_documentation.php"><span>▤</span> Health Documentation</a>
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
            <h1>Student Records</h1>
            <p class="staff-header-subtitle">
                Manage and maintain student information.
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

    <section class="student-records-content">

        <?php if ($message !== ''): ?>
            <div class="sr-alert <?= $messageType === 'error' ? 'sr-alert-error' : 'sr-alert-success' ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <div class="sr-summary">
            <div>
                <span class="sr-summary-icon">♙</span>
                <div>
                    <p>Total Registered Students</p>
                    <h2><?= number_format($totalStudents) ?></h2>
                </div>
            </div>
            <span class="sr-summary-note">Student directory</span>
        </div>

        <section class="sr-panel" id="student-form">
            <div class="sr-panel-heading">
                <div>
                    <h2><?= $editingStudent ? 'Edit Student' : 'Add Student' ?></h2>
                    <p>
                        <?= $editingStudent
                            ? 'Update the student information below.'
                            : 'Enter the student information to register a record.' ?>
                    </p>
                </div>

                <?php if ($editingStudent): ?>
                    <a class="sr-button sr-button-light"
                       href="student_records.php">
                        Cancel Edit
                    </a>
                <?php endif; ?>
            </div>

            <form method="POST" action="student_records.php" class="sr-form">
                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id"
                       value="<?= e($editingStudent['id'] ?? 0) ?>">

                <div class="sr-form-grid">

                    <div class="sr-field">
                        <label for="student_id_no">Student ID <span>*</span></label>
                        <input type="text" id="student_id_no"
                               name="student_id_no" maxlength="50" required
                               value="<?= e($editingStudent['student_id_no'] ?? '') ?>"
                               placeholder="Enter student ID">
                    </div>

                    <div class="sr-field">
                        <label for="fullname">Full Name <span>*</span></label>
                        <input type="text" id="fullname" name="fullname"
                               maxlength="100" required
                               value="<?= e($editingStudent['fullname'] ?? '') ?>"
                               placeholder="Enter full name">
                    </div>

                    <div class="sr-field">
                        <label for="email">Student Login Email</label>
                        <input type="email" id="email" name="email"
                               maxlength="100"
                               value="<?= e($editingStudent['email'] ?? '') ?>"
                               placeholder="Enter the student's login email">
                    </div>

                    <div class="sr-field">
                        <label for="age">Age</label>
                        <input type="number" id="age" name="age"
                               min="1" max="120"
                               value="<?= e($editingStudent['age'] ?? '') ?>"
                               placeholder="Enter age">
                    </div>

                    <div class="sr-field">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select gender</option>

                            <?php foreach (['Male', 'Female', 'Other'] as $option): ?>
                                <option value="<?= e($option) ?>"
                                    <?= ($editingStudent['gender'] ?? '') === $option
                                        ? 'selected'
                                        : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sr-field">
                        <label for="course_year">Course / Year / Grade</label>
                        <input type="text" id="course_year" name="course_year"
                               maxlength="50"
                               value="<?= e($editingStudent['course_year'] ?? '') ?>"
                               placeholder="e.g. BSIT - 2nd Year">
                    </div>

                    <div class="sr-field">
                        <label for="contact">Contact Number</label>
                        <input type="text" id="contact" name="contact"
                               maxlength="20"
                               value="<?= e($editingStudent['contact'] ?? '') ?>"
                               placeholder="Enter contact number">
                    </div>

                </div>

                <div class="sr-form-actions">
                    <button type="submit" class="sr-button sr-button-primary">
                        <?= $editingStudent ? 'Save Changes' : '+ Add Student' ?>
                    </button>

                    <?php if (!$editingStudent): ?>
                        <button type="reset" class="sr-button sr-button-light">
                            Clear Form
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="sr-panel sr-list-panel">

            <div class="sr-panel-heading sr-list-heading">
                <div>
                    <h2>Registered Students</h2>
                    <p>Search and manage existing student records.</p>
                </div>

                <form class="sr-search" method="GET"
                      action="student_records.php">
                    <input type="search" name="search"
                           value="<?= e($search) ?>"
                           placeholder="Search ID, name, course, email..."
                           aria-label="Search students">

                    <button class="sr-button sr-button-primary" type="submit">
                        Search
                    </button>

                    <?php if ($search !== ''): ?>
                        <a class="sr-button sr-button-light"
                           href="student_records.php">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="sr-table-wrap">
                <table class="sr-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Course / Year</th>
                            <th>Contact</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($students->num_rows > 0): ?>

                        <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= e($student['student_id_no']) ?></strong>
                                </td>

                                <td><?= e($student['fullname']) ?></td>

                                <td><?= e($student['email'] ?: 'Not set') ?></td>

                                <td>
                                    <?= $student['age'] !== null
                                        ? e($student['age'])
                                        : '—' ?>
                                </td>

                                <td><?= e($student['gender'] ?: '—') ?></td>

                                <td><?= e($student['course_year'] ?: '—') ?></td>

                                <td><?= e($student['contact'] ?: '—') ?></td>

                                <td>
                                    <?= !empty($student['created_at'])
                                        ? e(date(
                                            'M d, Y',
                                            strtotime($student['created_at'])
                                        ))
                                        : '—' ?>
                                </td>

                                <td>
                                    <div class="sr-actions">
                                        <a class="sr-action-edit"
                                           href="student_records.php?edit=<?= (int)$student['id'] ?>#student-form">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="student_records.php"
                                              onsubmit="return confirm('Delete this student record? Students with clinic history cannot be deleted.');">
                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden"
                                                   name="action"
                                                   value="delete">
                                            <input type="hidden"
                                                   name="id"
                                                   value="<?= (int)$student['id'] ?>">

                                            <button type="submit"
                                                    class="sr-action-delete">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="9" class="sr-empty">
                                <span>♙</span>
                                <strong>No student records found</strong>
                                <p>
                                    <?= $search !== ''
                                        ? 'Try a different search term.'
                                        : 'Add a student using the form above.' ?>
                                </p>
                            </td>
                        </tr>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sr-table-footer">
                Showing <?= number_format($students->num_rows) ?> result(s)

                <?php if ($search !== ''): ?>
                    for <strong><?= e($search) ?></strong>
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
$conn->close();
?>
