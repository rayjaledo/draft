

<?php
session_start();

// Correct database path for a file inside php/staff/
require_once __DIR__ . '/../db.php';

// Allow logged-in staff and administrators
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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function validReportDate(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    return $date !== false
        && (
            $errors === false ||
            (
                $errors['warning_count'] === 0 &&
                $errors['error_count'] === 0
            )
        )
        && $date->format('Y-m-d') === $value;
}

function fetchRows(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): array {
    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $rows;
}

// Get the logged-in user's name
$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

// Report filters
$today = date('Y-m-d');
$firstDay = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $firstDay;
$endDate = $_GET['end_date'] ?? $today;
$reportType = $_GET['report'] ?? 'overview';

$allowedReports = [
    'overview',
    'visits',
    'examinations',
    'students',
    'equipment'
];

// Validate report dates
if (
    !is_string($startDate) ||
    !is_string($endDate) ||
    !validReportDate($startDate) ||
    !validReportDate($endDate) ||
    $startDate > $endDate
) {
    $startDate = $firstDay;
    $endDate = $today;
}

// Validate report type
if (
    !is_string($reportType) ||
    !in_array($reportType, $allowedReports, true)
) {
    $reportType = 'overview';
}

$reportTitles = [
    'overview' => 'Clinic Visit Report',
    'visits' => 'Clinic Visit Report',
    'examinations' => 'Student Health Examination Report',
    'students' => 'Student Registration Report',
    'equipment' => 'Equipment Inventory Report'
];

$reportTitle = $reportTitles[$reportType];

$summary = [
    'students' => 0,
    'visits' => 0,
    'examinations' => 0,
    'equipment_types' => 0,
    'equipment_units' => 0,
    'equipment_attention' => 0,
    'overdue_maintenance' => 0
];

$reportRows = [];
$reportError = '';

$reportTitles = [
    'overview' => 'Clinic Visit Report',
    'visits' => 'Clinic Visit Report',
    'examinations' => 'Student Health Examination Report',
    'students' => 'Student Registration Report',
    'equipment' => 'Equipment Inventory Report'
];

$reportTitle = $reportTitles[$reportType];

/* ---------------------------------
   LOAD REPORT DATA
--------------------------------- */

try {
    // Registered students within selected dates.
    $rows = fetchRows(
        $conn,
        "SELECT COUNT(*) AS total
         FROM students
         WHERE created_at >= ?
           AND created_at < DATE_ADD(?, INTERVAL 1 DAY)",
        'ss',
        [$startDate, $endDate]
    );

    $summary['students'] = (int)$rows[0]['total'];

    // Clinic visits within selected dates.
    $rows = fetchRows(
        $conn,
        "SELECT COUNT(*) AS total
         FROM consultations
         WHERE consultation_date >= ?
           AND consultation_date < DATE_ADD(?, INTERVAL 1 DAY)",
        'ss',
        [$startDate, $endDate]
    );

    $summary['visits'] = (int)$rows[0]['total'];

    // Health examinations within selected dates.
    $rows = fetchRows(
        $conn,
        "SELECT COUNT(*) AS total
         FROM health_documentation
         WHERE examination_date BETWEEN ? AND ?",
        'ss',
        [$startDate, $endDate]
    );

    $summary['examinations'] = (int)$rows[0]['total'];

    // Current equipment summary.
    $rows = fetchRows(
        $conn,
        "SELECT
            COUNT(*) AS total_types,
            COALESCE(SUM(quantity), 0) AS total_units,
            COALESCE(SUM(
                CASE
                    WHEN equipment_condition IN (
                        'Needs Maintenance',
                        'Under Repair',
                        'Damaged'
                    )
                    THEN 1 ELSE 0
                END
            ), 0) AS attention_count,
            COALESCE(SUM(
                CASE
                    WHEN next_maintenance IS NOT NULL
                     AND next_maintenance < CURDATE()
                    THEN 1 ELSE 0
                END
            ), 0) AS overdue_count
         FROM equipment_inventory"
    );

    $summary['equipment_types'] =
        (int)$rows[0]['total_types'];

    $summary['equipment_units'] =
        (int)$rows[0]['total_units'];

    $summary['equipment_attention'] =
        (int)$rows[0]['attention_count'];

    $summary['overdue_maintenance'] =
        (int)$rows[0]['overdue_count'];

    // Generate the selected report.
    if (
        $reportType === 'overview' ||
        $reportType === 'visits'
    ) {
        $reportRows = fetchRows(
            $conn,
            "SELECT
                c.id,
                c.consultation_date,
                s.student_id_no,
                s.fullname,
                s.course_year,
                c.reason,
                c.remarks,
                c.staff_name
             FROM consultations c
             LEFT JOIN students s ON s.id = c.student_id
             WHERE c.consultation_date >= ?
               AND c.consultation_date < DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY c.consultation_date DESC, c.id DESC",
            'ss',
            [$startDate, $endDate]
        );

    } elseif ($reportType === 'examinations') {
        $reportRows = fetchRows(
            $conn,
            "SELECT
                h.id,
                h.student_id_no,
                s.fullname,
                s.course_year,
                h.examination_date,
                h.height_cm,
                h.weight_kg,
                h.temperature,
                h.blood_pressure,
                h.findings,
                h.remarks,
                h.recorded_by
             FROM health_documentation h
             LEFT JOIN students s
                ON s.student_id_no = h.student_id_no
             WHERE h.examination_date BETWEEN ? AND ?
             ORDER BY h.examination_date DESC, h.id DESC",
            'ss',
            [$startDate, $endDate]
        );

    } elseif ($reportType === 'students') {
        $reportRows = fetchRows(
            $conn,
            "SELECT
                id,
                student_id_no,
                fullname,
                age,
                gender,
                course_year,
                contact,
                created_at
             FROM students
             WHERE created_at >= ?
               AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY fullname ASC",
            'ss',
            [$startDate, $endDate]
        );

    } elseif ($reportType === 'equipment') {
        // Inventory shows all currently registered equipment.
        $reportRows = fetchRows(
            $conn,
            "SELECT
                id,
                equipment_name,
                category,
                quantity,
                equipment_condition,
                date_acquired,
                last_maintenance,
                next_maintenance,
                location,
                remarks
             FROM equipment_inventory
             ORDER BY equipment_name ASC"
        );
    }

    /* ---------------------------------
       CSV EXPORT
    --------------------------------- */

    if (
        isset($_GET['export']) &&
        $_GET['export'] === 'csv'
    ) {
        $fileNames = [
            'overview' => 'clinic-visits',
            'visits' => 'clinic-visits',
            'examinations' => 'health-examinations',
            'students' => 'student-records',
            'equipment' => 'equipment-inventory'
        ];

        $filename = $fileNames[$reportType]
            . '-' . $startDate
            . '-to-' . $endDate . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header(
            'Content-Disposition: attachment; filename="' .
            $filename . '"'
        );

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        if (!empty($reportRows)) {
            fputcsv($output, array_keys($reportRows[0]));

            foreach ($reportRows as $row) {
                fputcsv($output, array_values($row));
            }
        } else {
            fputcsv($output, ['No records found']);
        }

        fclose($output);
        exit();
    }

} catch (Throwable $exception) {
    error_log(
        'Clinic reports error: ' . $exception->getMessage()
    );

    // Display detailed error while debugging.
    // Remove the detailed message after fixing the issue.
    $reportError = $exception->getMessage();
    $reportRows = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clinic Reports - SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
    <link rel="stylesheet" href="../../css/clinic-reports.css">
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
        <a href="medicine_inventory.php"><span>✚</span> Medicine Inventory</a>

        <p class="sidebar-label">REPORTS</p>

        <a href="clinic_reports.php" class="active">
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

<main class="staff-main cr-main">

    <header class="staff-header">
        <div>
            <p class="page-eyebrow">REPORTS AND ANALYTICS</p>
            <h1>Clinic Reports</h1>
            <p class="header-subtitle">
                Review clinic activity, student records, and equipment status.
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

    <?php if ($reportError !== ''): ?>
        <div class="cr-alert" role="alert">
            <strong>Unable to load the report.</strong>
            <p><?= e($reportError) ?></p>
            <p>Check the PHP error log and verify your database column names.</p>
        </div>
    <?php endif; ?>

    <section class="cr-stats">
        <article class="cr-stat">
            <span>New Students</span>
            <strong><?= $summary['students'] ?></strong>
            <small>Registered during selected dates</small>
        </article>

        <article class="cr-stat">
            <span>Clinic Visits</span>
            <strong><?= $summary['visits'] ?></strong>
            <small>Consultations during selected dates</small>
        </article>

        <article class="cr-stat">
            <span>Health Examinations</span>
            <strong><?= $summary['examinations'] ?></strong>
            <small>Examinations recorded during selected dates</small>
        </article>

        <article class="cr-stat">
            <span>Equipment Units</span>
            <strong><?= $summary['equipment_units'] ?></strong>
            <small>
                <?= $summary['equipment_types'] ?> equipment types registered
            </small>
        </article>
    </section>

    <section class="cr-panel">
        <div class="cr-heading">
            <div>
                <h2>Generate a Report</h2>
                <p>Select a report type and date range.</p>
            </div>
        </div>

        <form method="GET"
              action="clinic_reports.php"
              class="cr-filter"
              id="reportForm">

            <div class="cr-field">
                <label for="report">Report Type</label>

                <select name="report" id="report">
                    <option value="overview"
                        <?= $reportType === 'overview' ? 'selected' : '' ?>>
                        Overview / Clinic Visits
                    </option>

                    <option value="visits"
                        <?= $reportType === 'visits' ? 'selected' : '' ?>>
                        Clinic Visits
                    </option>

                    <option value="examinations"
                        <?= $reportType === 'examinations' ? 'selected' : '' ?>>
                        Health Examinations
                    </option>

                    <option value="students"
                        <?= $reportType === 'students' ? 'selected' : '' ?>>
                        Student Registrations
                    </option>

                    <option value="equipment"
                        <?= $reportType === 'equipment' ? 'selected' : '' ?>>
                        Equipment Inventory
                    </option>
                </select>
            </div>

            <div class="cr-field">
                <label for="start_date">Start Date</label>
                <input type="date"
                       id="start_date"
                       name="start_date"
                       value="<?= e($startDate) ?>"
                       max="<?= e($endDate) ?>"
                       required>
            </div>

            <div class="cr-field">
                <label for="end_date">End Date</label>
                <input type="date"
                       id="end_date"
                       name="end_date"
                       value="<?= e($endDate) ?>"
                       min="<?= e($startDate) ?>"
                       required>
            </div>

            <div class="cr-filter-actions">
                <button type="submit" class="cr-primary-btn">
                    Generate Report
                </button>
            </div>
        </form>
    </section>

    <section class="cr-panel">
        <div class="cr-heading cr-report-heading">
            <div>
                <h2><?= e($reportTitle) ?></h2>

                <p>
                    Report period:
                    <?= e(date('M d, Y', strtotime($startDate))) ?>
                    to
                    <?= e(date('M d, Y', strtotime($endDate))) ?>

                    <?php if ($reportType === 'equipment'): ?>
                        — all registered equipment
                    <?php endif; ?>
                </p>
            </div>

            <div class="cr-actions">
                <a class="cr-secondary-btn"
                   href="clinic_reports.php?report=<?= urlencode($reportType) ?>&amp;start_date=<?= urlencode($startDate) ?>&amp;end_date=<?= urlencode($endDate) ?>&amp;export=csv">
                    Export CSV
                </a>

                <button type="button"
                        class="cr-secondary-btn"
                        id="printReport">
                    Print Report
                </button>
            </div>
        </div>

        <div class="cr-table-wrap">
            <table class="cr-table">
                <thead>
                <tr>
                    <?php if (
                        $reportType === 'overview' ||
                        $reportType === 'visits'
                    ): ?>
                        <th>Visit Date</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Course / Year</th>
                        <th>Reason</th>
                        <th>Remarks</th>
                        <th>Staff</th>

                    <?php elseif ($reportType === 'examinations'): ?>
                        <th>Examination Date</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Course / Year</th>
                        <th>Height (cm)</th>
                        <th>Weight (kg)</th>
                        <th>Temperature</th>
                        <th>Blood Pressure</th>
                        <th>Findings</th>
                        <th>Remarks</th>
                        <th>Recorded By</th>

                    <?php elseif ($reportType === 'students'): ?>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Course / Year</th>
                        <th>Contact</th>
                        <th>Date Registered</th>

                    <?php elseif ($reportType === 'equipment'): ?>
                        <th>Equipment</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                        <th>Date Acquired</th>
                        <th>Last Maintenance</th>
                        <th>Next Maintenance</th>
                        <th>Location</th>
                        <th>Remarks</th>
                    <?php endif; ?>
                </tr>
                </thead>

                <tbody>
                <?php if (empty($reportRows)): ?>
                    <tr>
                        <td colspan="11" class="cr-empty">
                            <?= $reportError !== ''
                                ? 'Report could not be loaded.'
                                : 'No records found for the selected report and dates.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportRows as $row): ?>
                        <tr>
                        <?php if (
                            $reportType === 'overview' ||
                            $reportType === 'visits'
                        ): ?>
                            <td><?= e($row['consultation_date']) ?></td>
                            <td><?= e($row['student_id_no'] ?? '—') ?></td>
                            <td><?= e($row['fullname'] ?? 'Student not found') ?></td>
                            <td><?= e($row['course_year'] ?? '—') ?></td>
                            <td><?= e($row['reason'] ?? '—') ?></td>
                            <td><?= e($row['remarks'] ?? '—') ?></td>
                            <td><?= e($row['staff_name'] ?? '—') ?></td>

                        <?php elseif ($reportType === 'examinations'): ?>
                            <td><?= e($row['examination_date']) ?></td>
                            <td><?= e($row['student_id_no']) ?></td>
                            <td><?= e($row['fullname'] ?? 'Student not found') ?></td>
                            <td><?= e($row['course_year'] ?? '—') ?></td>
                            <td><?= e($row['height_cm'] ?? '—') ?></td>
                            <td><?= e($row['weight_kg'] ?? '—') ?></td>
                            <td><?= e($row['temperature'] ?? '—') ?></td>
                            <td><?= e($row['blood_pressure'] ?? '—') ?></td>
                            <td><?= e($row['findings'] ?? '—') ?></td>
                            <td><?= e($row['remarks'] ?? '—') ?></td>
                            <td><?= e($row['recorded_by'] ?? '—') ?></td>

                        <?php elseif ($reportType === 'students'): ?>
                            <td><?= e($row['student_id_no']) ?></td>
                            <td><?= e($row['fullname']) ?></td>
                            <td><?= e($row['age'] ?? '—') ?></td>
                            <td><?= e($row['gender'] ?? '—') ?></td>
                            <td><?= e($row['course_year'] ?? '—') ?></td>
                            <td><?= e($row['contact'] ?? '—') ?></td>
                            <td><?= e($row['created_at']) ?></td>

                        <?php elseif ($reportType === 'equipment'): ?>
                            <td><?= e($row['equipment_name']) ?></td>
                            <td><?= e($row['category'] ?? '—') ?></td>
                            <td><?= (int)$row['quantity'] ?></td>
                            <td><?= e($row['equipment_condition']) ?></td>
                            <td><?= e($row['date_acquired'] ?? '—') ?></td>
                            <td><?= e($row['last_maintenance'] ?? '—') ?></td>
                            <td><?= e($row['next_maintenance'] ?? '—') ?></td>
                            <td><?= e($row['location'] ?? '—') ?></td>
                            <td><?= e($row['remarks'] ?? '—') ?></td>
                        <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="cr-record-count">
            Total records:
            <strong><?= count($reportRows) ?></strong>
        </p>
    </section>

    <section class="cr-panel">
        <div class="cr-heading">
            <div>
                <h2>Equipment Status Summary</h2>
                <p>Current inventory status across all registered equipment.</p>
            </div>
        </div>

        <div class="cr-equipment-summary">
            <div>
                <span>Equipment Needing Attention</span>
                <strong><?= $summary['equipment_attention'] ?></strong>
            </div>

            <div>
                <span>Overdue Maintenance</span>
                <strong><?= $summary['overdue_maintenance'] ?></strong>
            </div>
        </div>
    </section>

    <footer class="staff-footer">
        SHRCMS — Student Health Record and Clinic Management System
    </footer>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('reportForm');
    const printButton = document.getElementById('printReport');

    if (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector(
                'button[type="submit"]'
            );

            if (button) {
                button.disabled = true;
                button.textContent = 'Generating...';
            }
        });
    }

    if (printButton) {
        printButton.addEventListener('click', function () {
            window.print();
        });
    }
});
</script>

</body>
</html>
