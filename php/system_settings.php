
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

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['admin_username'] ?? 'Administrator';
$message = '';
$messageType = 'success';

$defaults = [
    'system_name' => 'SHRCMS',
    'school_name' => 'My School',
    'primary_color' => '#1454bd',
    'sidebar_color' => '#08285e',
    'records_per_page' => 10,
    'date_format' => 'M d, Y',
    'confirm_delete' => 1
];

// Ensure the default settings row exists.
$conn->query(
    "INSERT IGNORE INTO system_settings (id) VALUES (1)"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $systemName = trim($_POST['system_name'] ?? '');
    $schoolName = trim($_POST['school_name'] ?? '');
    $primaryColor = $_POST['primary_color'] ?? '';
    $sidebarColor = $_POST['sidebar_color'] ?? '';
    $recordsPerPage = filter_var(
        $_POST['records_per_page'] ?? null,
        FILTER_VALIDATE_INT
    );
    $dateFormat = $_POST['date_format'] ?? '';
    $confirmDelete = isset($_POST['confirm_delete']) ? 1 : 0;

    $validDateFormats = ['M d, Y', 'Y-m-d', 'd/m/Y'];
    $validPageSizes = [5, 10, 25, 50];

    if (
        $systemName === '' ||
        mb_strlen($systemName) > 150 ||
        $schoolName === '' ||
        mb_strlen($schoolName) > 150
    ) {
        $message = 'Enter a system name and school name within 150 characters.';
        $messageType = 'error';
    } elseif (
        !preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) ||
        !preg_match('/^#[0-9a-fA-F]{6}$/', $sidebarColor)
    ) {
        $message = 'Please choose valid colors.';
        $messageType = 'error';
    } elseif (
        !in_array($recordsPerPage, $validPageSizes, true) ||
        !in_array($dateFormat, $validDateFormats, true)
    ) {
        $message = 'Please select valid display settings.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare(
            'UPDATE system_settings
             SET system_name = ?,
                 school_name = ?,
                 primary_color = ?,
                 sidebar_color = ?,
                 records_per_page = ?,
                 date_format = ?,
                 confirm_delete = ?
             WHERE id = 1'
        );

        $stmt->bind_param(
            'ssssisi',
            $systemName,
            $schoolName,
            $primaryColor,
            $sidebarColor,
            $recordsPerPage,
            $dateFormat,
            $confirmDelete
        );

        if ($stmt->execute()) {
            $message = 'System settings saved successfully.';
        } else {
            $message = 'Unable to save settings. Please try again.';
            $messageType = 'error';
        }

        $stmt->close();
    }
}

$result = $conn->query(
    'SELECT system_name, school_name, primary_color, sidebar_color,
            records_per_page, date_format, confirm_delete, updated_at
     FROM system_settings WHERE id = 1'
);

$settings = $result
    ? array_merge($defaults, $result->fetch_assoc() ?: [])
    : $defaults;

$settings['confirm_delete'] = (int)$settings['confirm_delete'];
$settings['records_per_page'] = (int)$settings['records_per_page'];

// Preview colors on this page.
$primaryColor = $settings['primary_color'];
$sidebarColor = $settings['sidebar_color'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | SHRCMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-dashboard.css">

    <style>
        :root {
            --admin-primary: <?= e($primaryColor) ?>;
            --admin-sidebar: <?= e($sidebarColor) ?>;
        }

        .settings-content {
            max-width: 1050px;
            margin: auto;
        }

        .settings-description {
            margin: -5px 0 25px;
            color: #718096;
            line-height: 1.7;
        }

        .settings-alert {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-left: 4px solid #159b68;
            background: #e5f8ee;
            color: #166744;
        }

        .settings-alert.error {
            border-left-color: #dc4545;
            background: #fff1f0;
            color: #a52d2d;
        }

        .settings-panel {
            margin-bottom: 24px;
            padding: 25px;
            border: 1px solid var(--admin-border);
            background: white;
            box-shadow: var(--admin-card-shadow);
        }

        .settings-panel h3 {
            margin: 0 0 7px;
            color: var(--admin-text);
            font-size: 17px;
        }

        .settings-panel > p {
            margin: 0 0 22px;
            color: var(--admin-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .settings-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .settings-field label {
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .settings-field input[type="text"],
        .settings-field select {
            width: 100%;
            min-height: 43px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #172b4d;
        }

        .settings-field input[type="color"] {
            width: 100%;
            height: 43px;
            padding: 4px;
            border: 1px solid #cbd5e1;
            background: white;
            cursor: pointer;
        }

        .settings-field small {
            color: #718096;
            font-size: 11px;
            line-height: 1.5;
        }

        .settings-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #334155;
            font-size: 13px;
            line-height: 1.6;
        }

        .settings-checkbox input {
            margin-top: 3px;
        }

        .settings-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 23px;
        }

        .settings-button {
            display: inline-block;
            padding: 12px 18px;
            border: 0;
            background: var(--admin-primary);
            color: white;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .settings-button.secondary {
            background: #eaf0fa;
            color: #173662;
        }

        .settings-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .preview-swatch {
            width: 32px;
            height: 32px;
            border: 1px solid rgba(0,0,0,.12);
        }

        .preview-text {
            color: #334155;
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .settings-panel {
                padding: 17px;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }

            .settings-actions .settings-button {
                width: 100%;
                text-align: center;
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
        <a href="system_reports.php">📑 System Reports</a>
        <a href="system_settings.php" class="active">⚙️ System Settings</a>
        <a href="logout.php" class="logout-link"
           onclick="return confirm('Are you sure you want to log out?');">
            🚪 Logout
        </a>
    </nav>
</aside>

<main class="admin-main-content">
    <header class="admin-header-bar">
        <h2>System Settings</h2>
        <div class="admin-profile-info">
            <img src="../image/logo2.png" alt="Administrator">
            <div class="admin-profile-text">
                <h4><?= e($username) ?></h4>
                <p>Administrator</p>
            </div>
        </div>
    </header>

    <div class="admin-dashboard-container settings-content">
        <div class="admin-section-title">Configuration</div>
        <p class="settings-description">
            Manage the system's general information and display preferences.
            Changes are saved to your SHRCMS database.
        </p>

        <?php if ($message !== ''): ?>
            <div class="settings-alert <?= $messageType === 'error' ? 'error' : '' ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="system_settings.php">
            <section class="settings-panel">
                <h3>General Information</h3>
                <p>Set the names displayed in your system.</p>

                <div class="settings-grid">
                    <div class="settings-field">
                        <label for="system_name">System Name</label>
                        <input type="text" id="system_name" name="system_name"
                               maxlength="150" required
                               value="<?= e($settings['system_name']) ?>">
                    </div>

                    <div class="settings-field">
                        <label for="school_name">School Name</label>
                        <input type="text" id="school_name" name="school_name"
                               maxlength="150" required
                               value="<?= e($settings['school_name']) ?>">
                    </div>
                </div>
            </section>

            <section class="settings-panel">
                <h3>Appearance</h3>
                <p>Customize the colors used in the admin dashboard.</p>

                <div class="settings-grid">
                    <div class="settings-field">
                        <label for="primary_color">Primary Color</label>
                        <input type="color" id="primary_color"
                               name="primary_color"
                               value="<?= e($settings['primary_color']) ?>">
                        <small>Used for primary buttons and active navigation.</small>
                    </div>

                    <div class="settings-field">
                        <label for="sidebar_color">Sidebar Color</label>
                        <input type="color" id="sidebar_color"
                               name="sidebar_color"
                               value="<?= e($settings['sidebar_color']) ?>">
                        <small>Changes the admin sidebar background.</small>
                    </div>
                </div>

                <div class="settings-preview">
                    <div class="preview-swatch" id="primaryPreview"
                         style="background: <?= e($settings['primary_color']) ?>"></div>
                    <div class="preview-swatch" id="sidebarPreview"
                         style="background: <?= e($settings['sidebar_color']) ?>"></div>
                    <div class="preview-text">
                        <strong>Color preview</strong><br>
                        First swatch: primary color. Second swatch: sidebar color.
                    </div>
                </div>
            </section>

            <section class="settings-panel">
                <h3>Display Preferences</h3>
                <p>Choose how records and dates should be displayed.</p>

                <div class="settings-grid">
                    <div class="settings-field">
                        <label for="records_per_page">Records Per Page</label>
                        <select id="records_per_page" name="records_per_page">
                            <?php foreach ([5, 10, 25, 50] as $number): ?>
                                <option value="<?= $number ?>"
                                    <?= $settings['records_per_page'] === $number ? 'selected' : '' ?>>
                                    <?= $number ?> records
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Pages must read this setting to apply it to their tables.</small>
                    </div>

                    <div class="settings-field">
                        <label for="date_format">Date Format</label>
                        <select id="date_format" name="date_format">
                            <option value="M d, Y"
                                <?= $settings['date_format'] === 'M d, Y' ? 'selected' : '' ?>>
                                Oct 17, 2026
                            </option>
                            <option value="Y-m-d"
                                <?= $settings['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>
                                2026-10-17
                            </option>
                            <option value="d/m/Y"
                                <?= $settings['date_format'] === 'd/m/Y' ? 'selected' : '' ?>>
                                17/10/2026
                            </option>
                        </select>
                        <small>Pages must use this setting when formatting dates.</small>
                    </div>
                </div>

                <div style="margin-top: 22px;">
                    <label class="settings-checkbox">
                        <input type="checkbox" name="confirm_delete" value="1"
                            <?= $settings['confirm_delete'] === 1 ? 'checked' : '' ?>>
                        <span>
                            <strong>Confirm before deleting records</strong><br>
                            Ask for confirmation before a delete action, where supported.
                        </span>
                    </label>
                </div>
            </section>

            <div class="settings-actions">
                <button type="submit" class="settings-button">
                    Save Settings
                </button>

                <a href="admin_dashboard.php"
                   class="settings-button secondary">
                    Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</main>

<script>
    const primaryInput = document.getElementById('primary_color');
    const sidebarInput = document.getElementById('sidebar_color');

    primaryInput.addEventListener('input', function () {
        document.getElementById('primaryPreview').style.background = this.value;
    });

    sidebarInput.addEventListener('input', function () {
        document.getElementById('sidebarPreview').style.background = this.value;
    });
</script>

</body>
</html>

<?php
$conn->close();
?>
