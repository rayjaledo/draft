
<?php
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';

$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['admin_username'] ?? 'Administrator';

// Count records in the existing database tables.
function getCount(mysqli $conn, string $table): int
{
    // Table names are fixed internally, not supplied by users.
    $allowed = ['students', 'admin', 'staff', 'consultations'];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");

    if (!$result) {
        return 0;
    }

    return (int)($result->fetch_assoc()['total'] ?? 0);
}

$studentCount = getCount($conn, 'students');
$adminCount = getCount($conn, 'admin');
$staffCount = getCount($conn, 'staff');
$consultationCount = getCount($conn, 'consultations');

// Choose the report type.
$reportType = $_GET['report'] ?? 'overview';

$allowedReports = ['overview', 'students', 'consultations'];

if (!in_array($reportType, $allowedReports, true)) {
    $reportType = 'overview';
}

$students = [];
$consultations = [];
$reportError = '';

// Student report.
if ($reportType === 'students') {
    $result = $conn->query(
        'SELECT student_id_no, fullname, age, gender,
                course_year, contact, created_at
         FROM students
         ORDER BY fullname ASC'
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } else {
        $reportError = 'Unable to load student records.';
    }
}

// Consultation report.
// This uses the consultations schema defined for this project.
if ($reportType === 'consultations') {
    $result = $conn->query(
        'SELECT c.consultation_date, c.reason, c.remarks,
                c.staff_name, s.student_id_no, s.fullname
         FROM consultations c
         LEFT JOIN students s ON c.student_id = s.id
         ORDER BY c.consultation_date DESC'
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $consultations[] = $row;
        }
    } else {
        $reportError = 'Unable to load consultation records. Check that the consultations table and its columns exist.';
    }
}

$reportTitles = [
    'overview' => 'System Overview Report',
    'students' => 'Student Records Report',
    'consultations' => 'Clinic Consultation Report'
];

$reportTitle = $reportTitles[$reportType];
$generatedAt = date('F d, Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports | SHRCMS</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body.admin-dashboard-body {
            margin: 0;
            background: #f3f6fb;
            color: #172b4d;
            font-family: Arial, Helvetica, sans-serif;
        }

        .admin-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 250px;
            min-height: 100vh;
            padding: 22px 15px;
            overflow-y: auto;
            background: #08285e;
            color: white;
            z-index: 10;
        }

        .admin-sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 7px 25px;
            border-bottom: 1px solid rgba(255,255,255,.18);
        }

        .admin-sidebar-brand img {
            width: 45px;
            height: 45px;
            object-fit: contain;
            background: white;
        }

        .admin-sidebar-brand h3 {
            margin: 0 0 5px;
            font-size: 19px;
        }

        .admin-sidebar-brand p {
            margin: 0;
            color: #d0dcf4;
            font-size: 10px;
            line-height: 1.5;
        }

        .admin-sidebar nav {
            display: grid;
            gap: 7px;
            margin-top: 25px;
        }

        .admin-sidebar nav a {
            display: block;
            padding: 13px 12px;
            color: #e4edff;
            text-decoration: none;
            font-size: 13px;
            border-left: 3px solid transparent;
            transition: background .2s;
        }

        .admin-sidebar nav a:hover,
        .admin-sidebar nav a.active {
            background: #1454bd;
            border-left-color: #7db3ff;
            color: white;
        }

        .admin-main-content {
            min-height: 100vh;
            margin-left: 250px;
        }

        .admin-header-bar {
            min-height: 82px;
            padding: 17px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            background: white;
            border-bottom: 1px solid #e4eaf3;
        }

        .admin-header-bar h2 {
            margin: 0;
            color: #102b59;
            font-size: 23px;
        }

        .admin-profile-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-profile-info img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            border: 1px solid #e1e8f2;
            background: white;
        }

        .admin-profile-text h4 {
            margin: 0 0 5px;
            font-size: 13px;
        }

        .admin-profile-text p {
            margin: 0;
            color: #64748b;
            font-size: 11px;
        }

        .admin-dashboard-container {
            padding: 28px 30px;
            max-width: 1500px;
            margin: auto;
        }

        .admin-section-title {
            margin: 0 0 17px;
            color: #183765;
            font-size: 19px;
            font-weight: 700;
        }

        .report-intro {
            margin-bottom: 25px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
        }

        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .admin-stat-card {
            min-width: 0;
            padding: 20px;
            background: white;
            border: 1px solid #e4eaf3;
            border-top: 4px solid #2463d4;
            box-shadow: 0 3px 12px rgba(17, 39, 75, .04);
        }

        .admin-stat-card.students {
            border-top-color: #20a56b;
        }

        .admin-stat-card.staff {
            border-top-color: #ed9b35;
        }

        .admin-stat-card.consultations {
            border-top-color: #805ad5;
        }

        .admin-stat-header {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            color: #64748b;
            font-size: 12px;
        }

        .admin-stat-number {
            margin-top: 16px;
            color: #102b59;
            font-size: 30px;
            font-weight: 700;
        }

        .report-panel {
            margin-bottom: 24px;
            padding: 23px;
            background: white;
            border: 1px solid #e2e8f0;
            box-shadow: 0 3px 12px rgba(17, 39, 75, .04);
        }

        .report-panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .report-panel-heading h3 {
            margin: 0 0 7px;
            font-size: 17px;
            color: #173662;
        }

        .report-panel-heading p {
            margin: 0;
            color: #718096;
            font-size: 12px;
        }

        .report-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .report-select {
            min-width: 200px;
            padding: 11px 12px;
            border: 1px solid #d7e0ee;
            background: white;
            color: #243b5b;
            font-size: 13px;
        }

        .report-button {
            display: inline-block;
            padding: 11px 16px;
            border: 0;
            background: #1454bd;
            color: white;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .report-button:hover {
            background: #0c3d91;
        }

        .report-button.secondary {
            background: #eaf0fa;
            color: #173662;
        }

        .report-button.secondary:hover {
            background: #dce6f6;
        }

        .report-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            white-space: nowrap;
        }

        .report-table th {
            padding: 13px 12px;
            text-align: left;
            background: #edf3fc;
            color: #23436f;
            font-weight: 700;
        }

        .report-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #e9eef5;
            color: #475569;
        }

        .report-table tbody tr:hover {
            background: #f8faff;
        }

        .report-empty {
            padding: 28px;
            text-align: center;
            color: #718096;
        }

        .report-notice {
            margin-bottom: 20px;
            padding: 13px 15px;
            background: #fff1f0;
            border-left: 4px solid #dc4545;
            color: #a52d2d;
            font-size: 13px;
        }

        .report-footer {
            padding: 20px 30px;
            color: #718096;
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 1100px) {
            .admin-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .admin-sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }

            .admin-sidebar nav {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .admin-main-content {
                margin-left: 0;
            }

            .admin-header-bar {
                padding: 16px;
                align-items: flex-start;
            }

            .admin-header-bar h2 {
                font-size: 19px;
            }

            .admin-profile-info img {
                display: none;
            }

            .admin-dashboard-container {
                padding: 20px 14px;
            }

            .report-panel {
                padding: 16px;
            }
        }

        @media (max-width: 420px) {
            .admin-stats-grid {
                grid-template-columns: 1fr;
            }

            .admin-header-bar {
                flex-direction: column;
            }

            .report-toolbar,
            .report-select,
            .report-toolbar .report-button {
                width: 100%;
            }
        }

        @media print {
            body.admin-dashboard-body {
                background: white;
                color: black;
            }

            .admin-sidebar,
            .admin-header-bar,
            .no-print,
            .report-footer {
                display: none !important;
            }

            .admin-main-content {
                margin: 0;
            }

            .admin-dashboard-container {
                max-width: none;
                padding: 0;
            }

            .report-panel,
            .admin-stat-card {
                box-shadow: none;
                border: 1px solid #ccc;
                break-inside: avoid;
            }

            .report-table {
                font-size: 10px;
                white-space: normal;
            }

            .report-table th,
            .report-table td {
                padding: 7px;
                color: black;
            }

            .report-table-wrap {
                overflow: visible;
            }

            @page {
                margin: 15mm;
            }
        }
    </style>
</head>

<body class="admin-dashboard-body">

<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <img src="../image/logo2.png" alt="SHRCMS Logo">
        <div>
            <h3>SHRCMS</h3>
            <p>Student Health Record &amp; Clinic Management System</p>
        </div>
    </div>

    <nav>
        <a href="admin_dashboard.php">📊 Dashboard</a>
        <a href="user_management.php">👥 User Management</a>
        <a href="staff_management.php">👨‍⚕️ Staff Management</a>
        <a href="system_reports.php" class="active">📑 System Reports</a>
       <a href="system_settings.php">⚙️ System Settings</a>
        <a href="logout.php" class="logout-link"
           onclick="return confirm('Are you sure you want to log out?');">
            🚪 Logout
        </a>
    </nav>
</aside>

<main class="admin-main-content">
    <header class="admin-header-bar">
        <h2>System Reports</h2>

        <div class="admin-profile-info">
            <img src="../image/logo2.png" alt="Administrator">
            <div class="admin-profile-text">
                <h4><?= e($username) ?></h4>
                <p>Administrator</p>
            </div>
        </div>
    </header>

    <div class="admin-dashboard-container">
        <div class="admin-section-title">Reports Overview</div>
        <p class="report-intro">
            View system statistics and generate reports from the records
            stored in the Student Health Record and Clinic Management System.
        </p>

        <section class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-header">
                    <span>Total Admin Accounts</span>
                    <span>👥</span>
                </div>
                <div class="admin-stat-number">
                    <?= number_format($adminCount) ?>
                </div>
            </div>

            <div class="admin-stat-card students">
                <div class="admin-stat-header">
                    <span>Registered Students</span>
                    <span>🎓</span>
                </div>
                <div class="admin-stat-number">
                    <?= number_format($studentCount) ?>
                </div>
            </div>

            <div class="admin-stat-card staff">
                <div class="admin-stat-header">
                    <span>Clinic Staff</span>
                    <span>🏥</span>
                </div>
                <div class="admin-stat-number">
                    <?= number_format($staffCount) ?>
                </div>
            </div>

            <div class="admin-stat-card consultations">
                <div class="admin-stat-header">
                    <span>Total Consultations</span>
                    <span>✚</span>
                </div>
                <div class="admin-stat-number">
                    <?= number_format($consultationCount) ?>
                </div>
            </div>
        </section>

        <section class="report-panel no-print">
            <div class="report-panel-heading">
                <div>
                    <h3>Generate a Report</h3>
                    <p>Select a report type to view the corresponding records.</p>
                </div>
            </div>

            <form method="GET" action="system_reports.php"
                  class="report-toolbar">
                <select class="report-select" name="report" aria-label="Report type">
                    <option value="overview"
                        <?= $reportType === 'overview' ? 'selected' : '' ?>>
                        System Overview
                    </option>
                    <option value="students"
                        <?= $reportType === 'students' ? 'selected' : '' ?>>
                        Student Records
                    </option>
                    <option value="consultations"
                        <?= $reportType === 'consultations' ? 'selected' : '' ?>>
                        Clinic Consultations
                    </option>
                </select>

                <button type="submit" class="report-button">
                    View Report
                </button>

                <button type="button" class="report-button secondary"
                        onclick="window.print()">
                    🖨 Print / Save as PDF
                </button>
            </form>
        </section>

        <?php if ($reportError !== ''): ?>
            <div class="report-notice"><?= e($reportError) ?></div>
        <?php endif; ?>

        <section class="report-panel">
            <div class="report-panel-heading">
                <div>
                    <h3><?= e($reportTitle) ?></h3>
                    <p>Generated on <?= e($generatedAt) ?></p>
                </div>
                <span>
                    <?php if ($reportType === 'students'): ?>
                        <?= number_format(count($students)) ?> record(s)
                    <?php elseif ($reportType === 'consultations'): ?>
                        <?= number_format(count($consultations)) ?> record(s)
                    <?php else: ?>
                        Current system totals
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($reportType === 'overview'): ?>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Report Category</th>
                                <th>Total Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Administrator Accounts</td>
                                <td><?= number_format($adminCount) ?></td>
                            </tr>
                            <tr>
                                <td>Registered Students</td>
                                <td><?= number_format($studentCount) ?></td>
                            </tr>
                            <tr>
                                <td>Clinic Staff</td>
                                <td><?= number_format($staffCount) ?></td>
                            </tr>
                            <tr>
                                <td>Clinic Consultations</td>
                                <td><?= number_format($consultationCount) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($reportType === 'students'): ?>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Course / Year</th>
                                <th>Contact</th>
                                <th>Date Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($students): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= e($student['student_id_no']) ?></td>
                                    <td><?= e($student['fullname']) ?></td>
                                    <td><?= e($student['age'] ?? '—') ?></td>
                                    <td><?= e($student['gender'] ?: '—') ?></td>
                                    <td><?= e($student['course_year'] ?: '—') ?></td>
                                    <td><?= e($student['contact'] ?: '—') ?></td>
                                    <td>
                                        <?= !empty($student['created_at'])
                                            ? e(date('M d, Y', strtotime($student['created_at'])))
                                            : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="report-empty">
                                    No student records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($reportType === 'consultations'): ?>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Reason</th>
                                <th>Remarks</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($consultations): ?>
                            <?php foreach ($consultations as $consultation): ?>
                                <tr>
                                    <td><?= e($consultation['consultation_date']) ?></td>
                                    <td><?= e($consultation['student_id_no'] ?? '—') ?></td>
                                    <td><?= e($consultation['fullname'] ?? '—') ?></td>
                                    <td><?= e($consultation['reason']) ?></td>
                                    <td><?= e($consultation['remarks']) ?></td>
                                    <td><?= e($consultation['staff_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="report-empty">
                                    No consultation records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer class="report-footer">
        Student Health Record and Clinic Management System
    </footer>
</main>

</body>
</html>

<?php
$conn->close();
?>
