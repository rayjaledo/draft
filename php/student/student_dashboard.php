
<?php
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    ($_SESSION['user_role'] ?? '') !== 'Student'
) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['admin_username'] ?? 'Student';
$studentIdNo = trim((string)($_SESSION['student_id_no'] ?? ''));

$student = null;
$totalVisits = 0;
$totalHealthRecords = 0;
$todayVisits = 0;
$recentVisits = [];
$databaseMessage = '';

// Find the student's health profile using the ID number saved at login.
if ($studentIdNo !== '') {
    $stmt = $conn->prepare(
        'SELECT id, student_id_no, fullname, age, gender, course_year
         FROM students
         WHERE student_id_no = ?
         LIMIT 1'
    );

    if ($stmt) {
        $stmt->bind_param('s', $studentIdNo);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Optional fallback if your login system stores the students.id value.
if (!$student && !empty($_SESSION['student_record_id'])) {
    $recordId = (int) $_SESSION['student_record_id'];

    $stmt = $conn->prepare(
        'SELECT id, student_id_no, fullname, age, gender, course_year
         FROM students
         WHERE id = ?
         LIMIT 1'
    );

    if ($stmt) {
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($student) {
    $studentDatabaseId = (int) $student['id'];
    $studentIdNo = $student['student_id_no'];
    $username = $student['fullname'];

    // Total clinic consultations for this student.
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM consultations
         WHERE student_id = ?'
    );

    if ($stmt) {
        $stmt->bind_param('i', $studentDatabaseId);
        $stmt->execute();
        $totalVisits = (int) (
            $stmt->get_result()->fetch_assoc()['total'] ?? 0
        );
        $stmt->close();
    }

    // Total health documentation records for this student.
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM health_documentation
         WHERE student_id_no = ?'
    );

    if ($stmt) {
        $stmt->bind_param('s', $studentIdNo);
        $stmt->execute();
        $totalHealthRecords = (int) (
            $stmt->get_result()->fetch_assoc()['total'] ?? 0
        );
        $stmt->close();
    }

    // Consultations recorded today.
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM consultations
         WHERE student_id = ?
         AND DATE(consultation_date) = CURDATE()'
    );

    if ($stmt) {
        $stmt->bind_param('i', $studentDatabaseId);
        $stmt->execute();
        $todayVisits = (int) (
            $stmt->get_result()->fetch_assoc()['total'] ?? 0
        );
        $stmt->close();
    }

    // Most recent clinic visits.
    $stmt = $conn->prepare(
        'SELECT consultation_date, reason, remarks, staff_name
         FROM consultations
         WHERE student_id = ?
         ORDER BY consultation_date DESC, id DESC
         LIMIT 5'
    );

    if ($stmt) {
        $stmt->bind_param('i', $studentDatabaseId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $recentVisits[] = $row;
        }

        $stmt->close();
    }
} else {
    $databaseMessage =
        'Your student account is not linked to a student health profile yet. '
        . 'Please contact the clinic staff or administrator.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard | SHRCMS</title>

    <link rel="stylesheet" href="../../css/student-dashboard.css">
</head>

<body class="student-dashboard-body">

<div class="student-layout">

    <!-- SIDEBAR -->
    <aside class="student-sidebar">

        <div class="student-brand">
            <img src="../../image/logo2.png" alt="SHRCMS Logo">

            <div class="student-brand-text">
                <strong>SHRCMS</strong>
                <span>Student Health Record &amp; Clinic Management System</span>
            </div>
        </div>

        <nav class="student-navigation">

            <p class="student-nav-label">STUDENT MENU</p>

            <a href="student_dashboard.php" class="student-nav-link active">
                <span class="nav-icon">▦</span>
                <span>Dashboard</span>
            </a>

            <a href="student_health_records.php" class="student-nav-link">
                <span class="nav-icon">▤</span>
                <span>My Health Records</span>
            </a>

            <a href="student_visit_history.php" class="student-nav-link">
                <span class="nav-icon">◷</span>
                <span>Clinic Visit History</span>
            </a>

            <a href="student_profile.php" class="student-nav-link">
                <span class="nav-icon">♙</span>
                <span>My Profile</span>
            </a>

        </nav>

        <div class="student-sidebar-bottom">
            <a
                href="../logout.php"
                class="student-nav-link student-logout"
                onclick="return confirm('Are you sure you want to log out?');"
            >
                <span class="nav-icon">↪</span>
                <span>Log Out</span>
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="student-main">

        <!-- HEADER -->
        <header class="student-header">

            <div class="student-header-title">
                <h1>Student Dashboard</h1>
                <p>View your health information and clinic activity.</p>
            </div>

            <div class="student-profile">

                <div class="student-avatar">
                    ♙
                </div>

                <div class="student-profile-info">
                    <strong><?= e($username) ?></strong>
                    <span>Student</span>
                </div>

            </div>

        </header>

        <!-- DASHBOARD CONTENT -->
        <section class="student-content">

            <?php if ($databaseMessage !== ''): ?>
                <div class="student-notice">
                    <strong>Student profile not found</strong>
                    <p><?= e($databaseMessage) ?></p>
                </div>
            <?php endif; ?>

            <!-- WELCOME BANNER -->
            <section class="student-welcome">

                <div class="student-welcome-text">
                    <span class="student-eyebrow">STUDENT PORTAL</span>

                    <h2>
                        Welcome, <?= e($username) ?>!
                    </h2>

                    <p>
                        Keep track of your clinic visits and health records
                        through your personal student dashboard.
                    </p>

                    <?php if ($student): ?>
                        <div class="student-id-badge">
                            Student ID:
                            <strong><?= e($student['student_id_no']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="student-welcome-icon">
                    <span>✚</span>
                </div>

            </section>

            <!-- SUMMARY -->
            <div class="student-section-heading">
                <div>
                    <h2>Health Overview</h2>
                    <p>A summary of your recorded clinic information.</p>
                </div>
            </div>

            <section class="student-stats-grid">

                <article class="student-stat-card">
                    <div class="student-stat-top">
                        <span class="student-stat-label">Clinic Visits</span>
                        <span class="student-stat-icon icon-blue">✚</span>
                    </div>

                    <strong class="student-stat-number">
                        <?= number_format($totalVisits) ?>
                    </strong>

                    <span class="student-stat-description">
                        Total recorded consultations
                    </span>
                </article>

                <article class="student-stat-card">
                    <div class="student-stat-top">
                        <span class="student-stat-label">Health Records</span>
                        <span class="student-stat-icon icon-green">♡</span>
                    </div>

                    <strong class="student-stat-number">
                        <?= number_format($totalHealthRecords) ?>
                    </strong>

                    <span class="student-stat-description">
                        Recorded examination records
                    </span>
                </article>

                <article class="student-stat-card">
                    <div class="student-stat-top">
                        <span class="student-stat-label">Visits Today</span>
                        <span class="student-stat-icon icon-orange">◷</span>
                    </div>

                    <strong class="student-stat-number">
                        <?= number_format($todayVisits) ?>
                    </strong>

                    <span class="student-stat-description">
                        Consultations recorded today
                    </span>
                </article>

            </section>

            <!-- STUDENT DETAILS -->
            <?php if ($student): ?>
                <section class="student-details-card">

                    <div class="student-panel-heading">
                        <div>
                            <h2>My Student Information</h2>
                            <p>Basic information linked to your account.</p>
                        </div>
                        <span class="student-details-symbol">♙</span>
                    </div>

                    <div class="student-details-grid">

                        <div class="student-detail">
                            <span>Full Name</span>
                            <strong><?= e($student['fullname']) ?></strong>
                        </div>

                        <div class="student-detail">
                            <span>Student ID</span>
                            <strong><?= e($student['student_id_no']) ?></strong>
                        </div>

                        <div class="student-detail">
                            <span>Age</span>
                            <strong>
                                <?= $student['age'] !== null
                                    && $student['age'] !== ''
                                    ? e($student['age'])
                                    : 'Not provided' ?>
                            </strong>
                        </div>

                        <div class="student-detail">
                            <span>Gender</span>
                            <strong>
                                <?= e($student['gender'] ?: 'Not provided') ?>
                            </strong>
                        </div>

                        <div class="student-detail">
                            <span>Course / Year / Grade</span>
                            <strong>
                                <?= e($student['course_year'] ?: 'Not provided') ?>
                            </strong>
                        </div>

                    </div>

                </section>
            <?php endif; ?>

            <!-- RECENT CLINIC ACTIVITY -->
            <section class="student-panel">

                <div class="student-panel-heading">
                    <div>
                        <h2>Recent Clinic Activity</h2>
                        <p>Your five most recent recorded clinic visits.</p>
                    </div>

                    <a href="student_visit_history.php" class="student-view-link">
                        View History <span>→</span>
                    </a>
                </div>

                <div class="student-table-container">

                    <table class="student-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reason for Visit</th>
                                <th>Clinic Staff</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (!$student): ?>

                                <tr>
                                    <td colspan="4" class="student-empty">
                                        Your student record is not linked to this account.
                                    </td>
                                </tr>

                            <?php elseif (count($recentVisits) > 0): ?>

                                <?php foreach ($recentVisits as $visit): ?>
                                    <tr>
                                        <td>
                                            <?= !empty($visit['consultation_date'])
                                                ? e(date(
                                                    'M d, Y',
                                                    strtotime($visit['consultation_date'])
                                                ))
                                                : '—' ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e($visit['reason'] ?: 'Not specified') ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= e($visit['staff_name'] ?: 'Clinic Staff') ?>
                                        </td>

                                        <td>
                                            <?= e($visit['remarks'] ?: 'No remarks recorded') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="4" class="student-empty">
                                        <span class="student-empty-icon">▤</span>
                                        <strong>No clinic visits yet</strong>
                                        <p>Your recorded clinic visits will appear here.</p>
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>
                    </table>

                </div>

                <div class="student-table-footer">
                    Showing <?= number_format(count($recentVisits)) ?> recent visit(s)
                </div>

            </section>

            <!-- FOOTER -->
            <footer class="student-footer">
                Student Health Record and Clinic Management System
                <span>SHRCMS</span>
            </footer>

        </section>

    </main>

</div>

</body>
</html>

<?php
$conn->close();
?>
