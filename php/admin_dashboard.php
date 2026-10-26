<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Pag-ihap sa mga estudyante gikan sa 'students' table
$student_count = 0;
$result_students = $conn->query("SELECT COUNT(*) as total FROM students");
if ($result_students) {
    $row = $result_students->fetch_assoc();
    $student_count = $row['total'];
}

// Pag-ihap sa mga admin gikan sa 'admin' table (Total Users)
$admin_count = 0;
$result_admin = $conn->query("SELECT COUNT(*) as total FROM admin");
if ($result_admin) {
    $row_admin = $result_admin->fetch_assoc();
    $admin_count = $row_admin['total'];
}

// Pag-ihap sa mga staff gikan sa 'staff' table
$staff_count = 0;
$result_staff = $conn->query("SELECT COUNT(*) as total FROM staff");
if ($result_staff) {
    $row_staff = $result_staff->fetch_assoc();
    $staff_count = $row_staff['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SHRCMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-dashboard.css">
</head>
<body class="admin-dashboard-body">

    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <img src="../image/logo2.png" alt="Logo">
            <div>
                <h3>SHRCMS</h3>
                <p>Student Health Record & Clinic Management System</p>
            </div>
        </div>
        <nav>
            <a href="admin_dashboard.php" class="active">📊 Dashboard</a>
            <a href="user_management.php">👥 User Management</a>
            <a href="staff_management.php">👨‍⚕️ Staff Management</a>
            <a href="system_reports.php">📑 System Reports</a>
            <a href="system_settings.php">⚙️ System Settings</a>
            <a href="logout.php" class="logout-link">🚪 Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-main-content">
        <!-- Header Bar -->
        <div class="admin-header-bar">
            <h2>Admin Dashboard</h2>
            <div class="admin-profile-info">
                <img src="../image/logo2.png" alt="Admin">
                <div class="admin-profile-text">
                    <h4><?php echo htmlspecialchars($_SESSION['admin_username']); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
        </div>

        <!-- Dashboard Body Content -->
        <div class="admin-dashboard-container">
            <div class="admin-section-title">Overview</div>
            
            <!-- Stats Cards -->
            <div class="admin-stats-grid">
                <div class="admin-stat-card users">
                    <div class="admin-stat-header">
                        <span>Total Users (Admin)</span>
                        <span>👥</span>
                    </div>
                    <div class="admin-stat-number"><?php echo $admin_count; ?></div>
                </div>

                <div class="admin-stat-card students">
                    <div class="admin-stat-header">
                        <span>Students</span>
                        <span>🎓</span>
                    </div>
                    <div class="admin-stat-number"><?php echo $student_count; ?></div>
                </div>

                <div class="admin-stat-card staff">
                    <div class="admin-stat-header">
                        <span>Staff</span>
                        <span>🏥</span>
                    </div>
                    <div class="admin-stat-number"><?php echo $staff_count; ?></div> <!-- Nakuha na gikan sa database -->
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="admin-section-title">Quick Actions</div>
            <div class="admin-actions-grid">
                <a href="user_management.php" class="admin-action-btn users-action">
                    <span>👥</span> Manage Users
                </a>
                <a href="staff_management.php" class="admin-action-btn staff-action">
                    <span>👨‍⚕️</span> Manage Staff
                </a>
                <a href="system_settings.php" class="admin-action-btn settings-action">
                    <span>⚙️</span> System Settings
                </a>
            </div>
        </div>
    </div>

</body>
</html>