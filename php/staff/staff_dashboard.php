
<?php
session_start();

require_once __DIR__ . '/../db.php';

// Check if the user is logged in and has the correct role
if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !isset($_SESSION['user_role']) ||
    !in_array($_SESSION['user_role'], ['Staff', 'Administrator'], true)
) {
    header('Location: ../login.php');
    exit();
}

// Get the username from the session
$username = $_SESSION['admin_username'] ?? 'Clinic Staff';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff Dashboard - SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
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
            <a href="staff_dashboard.php" class="active">
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

            <a href="equipment_inventory.php">
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
    <main class="staff-main">

        <!-- HEADER -->
        <header class="staff-header">
            <div>
                <p class="page-eyebrow">STAFF PORTAL</p>
                <h1>Dashboard</h1>
                <p class="header-subtitle">
                    Manage student health services and clinic resources.
                </p>
            </div>

            <div class="staff-user">
                <img src="../../image/logo2.png" alt="Staff profile">

                <div>
                    <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span>Clinic Staff</span>
                </div>
            </div>
        </header>

        <!-- WELCOME -->
        <section class="welcome-panel">
            <div>
                <span class="welcome-tag">SHRCMS STAFF PORTAL</span>

               <h2>Welcome back, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</h2>
                <p>
                    Manage student records, consultations, clinic visits,
                    medicines, equipment, and health reports in one place.
                </p>

                <a href="clinic_consultation.php" class="welcome-button">
                    Open Clinic Consultation →
                </a>
            </div>

            <div class="welcome-symbol" aria-hidden="true">✚</div>
        </section>

        <!-- QUICK ACCESS -->
        <section class="dashboard-section">
            <div class="section-heading">
                <div>
                    <h2>Clinic Management</h2>
                    <p>Choose a module to get started.</p>
                </div>
            </div>

            <div class="module-grid">

                <a href="student_records.php" class="module-card">
                    <div class="module-icon blue">♙</div>
                    <h3>Student Records</h3>
                    <p>Access student profiles and health information.</p>
                    <span class="module-link">Open records →</span>
                </a>

                <a href="clinic_consultation.php" class="module-card">
                    <div class="module-icon green">✚</div>
                    <h3>Clinic Consultation</h3>
                    <p>Record and manage student consultations.</p>
                    <span class="module-link">Open consultations →</span>
                </a>

                <a href="health_documentation.php" class="module-card">
                    <div class="module-icon orange">▤</div>
                    <h3>Health Documentation</h3>
                    <p>Manage examination results and health documents.</p>
                    <span class="module-link">View documents →</span>
                </a>

                <a href="clinic_visit_records.php" class="module-card">
                    <div class="module-icon purple">▣</div>
                    <h3>Clinic Visit Records</h3>
                    <p>Review student visits and consultation history.</p>
                    <span class="module-link">View visits →</span>
                </a>

                <a href="equipment_inventory.php" class="module-card">
                    <div class="module-icon teal">⚙</div>
                    <h3>Equipment Inventory</h3>
                    <p>Track equipment quantities, conditions, and maintenance.</p>
                    <span class="module-link">Manage equipment →</span>
                </a>

                <a href="medicine_inventory.php" class="module-card">
                    <div class="module-icon red">✚</div>
                    <h3>Medicine Inventory</h3>
                    <p>Monitor medicine stocks, expiration dates, and shortages.</p>
                    <span class="module-link">Manage medicines →</span>
                </a>

                <a href="clinic_reports.php" class="module-card">
                    <div class="module-icon navy">▥</div>
                    <h3>Clinic Reports</h3>
                    <p>Access clinic activity and inventory reports.</p>
                    <span class="module-link">View reports →</span>
                </a>

            </div>
        </section>

        <!-- INVENTORY SHORTCUTS -->
        <section class="dashboard-section inventory-section">
            <div class="section-heading">
                <div>
                    <h2>Inventory Management</h2>
                    <p>Quick access to clinic supplies and resources.</p>
                </div>
            </div>

            <div class="inventory-shortcuts">
                <div class="inventory-shortcut">
                    <div>
                        <span class="inventory-small-icon">⚙</span>
                        <div>
                            <h3>Equipment Inventory</h3>
                            <p>Equipment records and maintenance tracking</p>
                        </div>
                    </div>

                    <a href="equipment_inventory.php">Manage →</a>
                </div>

                <div class="inventory-shortcut">
                    <div>
                        <span class="inventory-small-icon medicine-icon">✚</span>
                        <div>
                            <h3>Medicine Inventory</h3>
                            <p>Stock monitoring and expiration tracking</p>
                        </div>
                    </div>

                    <a href="medicine_inventory.php">Manage →</a>
                </div>
            </div>
        </section>

        <footer class="staff-footer">
            SHRCMS — Student Health Record and Clinic Management System
        </footer>

    </main>

</body>
</html>
