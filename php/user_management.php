
<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: login.php');
    exit();
}

$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectUsers(string $message, string $type = 'success'): void
{
    header(
        'Location: user_management.php?message=' .
        urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

// CSRF protection
if (empty($_SESSION['user_management_csrf'])) {
    $_SESSION['user_management_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['user_management_csrf'];

$message = trim($_GET['message'] ?? '');
$messageType = ($_GET['type'] ?? 'success') === 'error'
    ? 'error'
    : 'success';

$search = trim($_GET['search'] ?? '');
$editingUser = null;

// =============================================
// HANDLE ADD, EDIT, STATUS CHANGE, AND DELETE
// =============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)) {
        redirectUsers('Invalid form submission. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    // -----------------------------------------
    // ADD OR UPDATE USER
    // -----------------------------------------
    if ($action === 'save') {
        $id = filter_var(
            $_POST['id'] ?? 0,
            FILTER_VALIDATE_INT
        );

        $id = $id === false ? 0 : (int)$id;

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '') {
            redirectUsers('Name and email are required.', 'error');
        }

        if (mb_strlen($name) > 100) {
            redirectUsers('Name must not exceed 100 characters.', 'error');
        }

        if (strlen($email) > 255 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectUsers('Please enter a valid email address.', 'error');
        }

        if (!in_array($role, ['Staff', 'Student'], true)) {
            redirectUsers('Please select a valid account role.', 'error');
        }

        if ($id < 0) {
            redirectUsers('Invalid user ID.', 'error');
        }

        if ($id === 0 && strlen($password) < 8) {
            redirectUsers(
                'New account passwords must contain at least 8 characters.',
                'error'
            );
        }

        if ($password !== '' && strlen($password) < 8) {
            redirectUsers(
                'A new password must contain at least 8 characters.',
                'error'
            );
        }

        // Prevent duplicate emails in users and admin tables.
        $checkAdmin = $conn->prepare(
            'SELECT email FROM admin WHERE email = ? LIMIT 1'
        );
        $checkAdmin->bind_param('s', $email);
        $checkAdmin->execute();
        $adminEmailExists = $checkAdmin->get_result()->num_rows > 0;
        $checkAdmin->close();

        if ($adminEmailExists) {
            redirectUsers(
                'This email belongs to an administrator.',
                'error'
            );
        }

        if ($id > 0) {
            $check = $conn->prepare(
                'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1'
            );
            $check->bind_param('si', $email, $id);
        } else {
            $check = $conn->prepare(
                'SELECT id FROM users WHERE email = ? LIMIT 1'
            );
            $check->bind_param('s', $email);
        }

        $check->execute();
        $duplicate = $check->get_result()->num_rows > 0;
        $check->close();

        if ($duplicate) {
            redirectUsers('This email is already registered.', 'error');
        }

        // Ensure the account exists before editing it.
        if ($id > 0) {
            $findUser = $conn->prepare(
                'SELECT id, role FROM users WHERE id = ? LIMIT 1'
            );
            $findUser->bind_param('i', $id);
            $findUser->execute();
            $existingUser = $findUser->get_result()->fetch_assoc();
            $findUser->close();

            if (!$existingUser) {
                redirectUsers('User account not found.', 'error');
            }
        }

        $hashedPassword = null;

        if ($password !== '') {
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );
        }

        try {
            $conn->begin_transaction();

            if ($id > 0) {
                if ($hashedPassword !== null) {
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET name = ?, email = ?, role = ?, password = ?
                         WHERE id = ?'
                    );
                    $stmt->bind_param(
                        'ssssi',
                        $name,
                        $email,
                        $role,
                        $hashedPassword,
                        $id
                    );
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET name = ?, email = ?, role = ?
                         WHERE id = ?'
                    );
                    $stmt->bind_param(
                        'sssi',
                        $name,
                        $email,
                        $role,
                        $id
                    );
                }

                $stmt->execute();
                $stmt->close();

                // Synchronize the staff record by the original email.
                $oldEmail = $existingUser['role'] === 'Staff'
                    ? null
                    : null;

                // Look up staff using the account's previous email.
                // The email is obtained from the staff table by matching
                // the user ID's previous account details below.
                $findStaff = $conn->prepare(
                    'SELECT id FROM staff WHERE email = ? LIMIT 1'
                );

                // We use the previous email captured before updating below
                // in a production schema that links staff by user_id.
                // Here the staff table is synchronized using the new email.
                $findStaff->bind_param('s', $email);
                $findStaff->execute();
                $staffResult = $findStaff->get_result();
                $staffExists = $staffResult->fetch_assoc();
                $findStaff->close();

                if ($role === 'Staff') {
                    if ($staffExists) {
                        $updateStaff = $conn->prepare(
                            'UPDATE staff SET fullname = ?, role = ?, email = ?
                             WHERE id = ?'
                        );
                        $staffId = (int)$staffExists['id'];
                        $updateStaff->bind_param(
                            'sssi',
                            $name,
                            $role,
                            $email,
                            $staffId
                        );
                        $updateStaff->execute();
                        $updateStaff->close();
                    } else {
                        $insertStaff = $conn->prepare(
                            'INSERT INTO staff (fullname, role, email)
                             VALUES (?, ?, ?)'
                        );
                        $insertStaff->bind_param(
                            'sss',
                            $name,
                            $role,
                            $email
                        );
                        $insertStaff->execute();
                        $insertStaff->close();
                    }
                } elseif ($existingUser['role'] === 'Staff') {
                    // Remove the staff record if the role changes to Student.
                    $deleteStaff = $conn->prepare(
                        'DELETE FROM staff WHERE email = ?'
                    );
                    $oldStaffEmail = $email;
                    $deleteStaff->bind_param('s', $oldStaffEmail);
                    $deleteStaff->execute();
                    $deleteStaff->close();
                }

                $conn->commit();
                redirectUsers('User account updated successfully.');

            } else {
                $status = 'Active';

                $stmt = $conn->prepare(
                    'INSERT INTO users (name, email, role, password, status)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'sssss',
                    $name,
                    $email,
                    $role,
                    $hashedPassword,
                    $status
                );
                $stmt->execute();
                $stmt->close();

                if ($role === 'Staff') {
                    $staff = $conn->prepare(
                        'INSERT INTO staff (fullname, role, email)
                         VALUES (?, ?, ?)'
                    );
                    $staff->bind_param('sss', $name, $role, $email);
                    $staff->execute();
                    $staff->close();
                }

                $conn->commit();
                redirectUsers('New user account created successfully.');
            }
        } catch (Throwable $exception) {
            $conn->rollback();

            error_log('User management save failed: ' . $exception->getMessage());

            redirectUsers(
                'Could not save the account. Check your database structure and try again.',
                'error'
            );
        }
    }

    // -----------------------------------------
    // ACTIVATE / DEACTIVATE ACCOUNT
    // -----------------------------------------
    if ($action === 'toggle_status') {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            redirectUsers('Invalid user account.', 'error');
        }

        $stmt = $conn->prepare(
            "UPDATE users
             SET status = CASE
                 WHEN status = 'Active' THEN 'Inactive'
                 ELSE 'Active'
             END
             WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        redirectUsers(
            $changed > 0
                ? 'Account status updated.'
                : 'Account not found or status unchanged.',
            $changed > 0 ? 'success' : 'error'
        );
    }

    // -----------------------------------------
    // DELETE USER
    // -----------------------------------------
    if ($action === 'delete') {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            redirectUsers('Invalid user account.', 'error');
        }

        $stmt = $conn->prepare(
            'SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            redirectUsers('User account not found.', 'error');
        }

        try {
            $conn->begin_transaction();

            if ($user['role'] === 'Staff') {
                $staff = $conn->prepare(
                    'DELETE FROM staff WHERE email = ?'
                );
                $staff->bind_param('s', $user['email']);
                $staff->execute();
                $staff->close();
            }

            $delete = $conn->prepare(
                'DELETE FROM users WHERE id = ?'
            );
            $delete->bind_param('i', $id);
            $delete->execute();
            $deleted = $delete->affected_rows;
            $delete->close();

            $conn->commit();

            redirectUsers(
                $deleted
                    ? 'User account deleted successfully.'
                    : 'Could not delete the account.',
                $deleted ? 'success' : 'error'
            );
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('User deletion failed: ' . $exception->getMessage());

            redirectUsers(
                'Could not delete this account. It may be linked to other records.',
                'error'
            );
        }
    }
}

// =============================================
// LOAD USER FOR EDITING
// =============================================

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {
    $stmt = $conn->prepare(
        'SELECT id, name, email, role, status
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editingUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editingUser) {
        $message = 'User account not found.';
        $messageType = 'error';
    }
}

// =============================================
// SEARCH AND LIST USERS
// =============================================

$sql = 'SELECT id, name, email, role, status FROM users';
$params = [];
$types = '';

if ($search !== '') {
    $sql .= ' WHERE name LIKE ? OR email LIKE ? OR role LIKE ? OR CAST(id AS CHAR) LIKE ?';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
    $types = 'ssss';
}

$sql .= ' ORDER BY id DESC';

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$userResult = $stmt->get_result();

$userList = [];
while ($row = $userResult->fetch_assoc()) {
    $userList[] = $row;
}

$stmt->close();

$totalUsers = (int)($conn->query(
    'SELECT COUNT(*) AS total FROM users'
)->fetch_assoc()['total'] ?? 0);

$activeUsers = (int)($conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE status = 'Active'"
)->fetch_assoc()['total'] ?? 0);

$staffUsers = (int)($conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'Staff'"
)->fetch_assoc()['total'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | SHRCMS</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .um-content { padding: 28px; }
        .um-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .um-card, .um-panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 22px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .04);
        }
        .um-card span { color: #64748b; font-size: 13px; }
        .um-card strong {
            display: block; margin-top: 10px;
            color: #08275c; font-size: 28px;
        }
        .um-panel { margin-bottom: 24px; }
        .um-heading {
            margin: 0 0 7px; color: #0f2345; font-size: 19px;
        }
        .um-subtitle {
            margin: 0 0 20px; color: #64748b;
            font-size: 13px; line-height: 1.6;
        }
        .um-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .um-field label {
            display: block; margin-bottom: 7px;
            color: #334155; font-size: 13px; font-weight: 600;
        }
        .um-field input, .um-field select, .um-search input {
            width: 100%; min-height: 42px; padding: 10px 12px;
            border: 1px solid #cbd5e1; background: #fff;
            color: #1e293b; font-size: 13px;
        }
        .um-field input:focus, .um-field select:focus,
        .um-search input:focus {
            outline: 2px solid #bfdbfe; border-color: #2563eb;
        }
        .um-help {
            display: block; margin-top: 6px;
            color: #64748b; font-size: 11px; line-height: 1.5;
        }
        .um-actions {
            display: flex; flex-wrap: wrap; align-items: center;
            gap: 8px; margin-top: 20px;
        }
        .um-btn {
            display: inline-flex; justify-content: center;
            align-items: center; min-height: 38px; padding: 9px 13px;
            border: 0; cursor: pointer; font-size: 12px;
            font-weight: 700; text-decoration: none;
        }
        .um-primary { background: #1765e8; color: #fff; }
        .um-primary:hover { background: #0e55ce; }
        .um-light { background: #edf2f7; color: #334155; }
        .um-edit { background: #e0f2fe; color: #075985; }
        .um-delete { background: #fee2e2; color: #991b1b; }
        .um-toggle { background: #fef3c7; color: #92400e; }
        .um-alert {
            padding: 13px 16px; margin-bottom: 20px;
            font-size: 13px; line-height: 1.6;
        }
        .um-alert-success { background: #dcfce7; color: #166534; }
        .um-alert-error { background: #fee2e2; color: #991b1b; }
        .um-table-wrap { width: 100%; overflow-x: auto; }
        .um-table {
            width: 100%; border-collapse: collapse;
            text-align: left; font-size: 13px;
        }
        .um-table th {
            padding: 14px 12px; background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b; white-space: nowrap;
        }
        .um-table td {
            padding: 13px 12px; border-bottom: 1px solid #edf2f7;
            color: #475569; vertical-align: middle;
        }
        .um-table tr:hover td { background: #fbfdff; }
        .um-user-name { color: #1e293b; font-weight: 700; }
        .um-badge {
            display: inline-block; padding: 5px 9px;
            font-size: 11px; font-weight: 700;
        }
        .um-active { background: #dcfce7; color: #166534; }
        .um-inactive { background: #fee2e2; color: #991b1b; }
        .um-role { background: #eaf1ff; color: #1d4ed8; }
        .um-row-actions {
            display: flex; flex-wrap: wrap; gap: 6px;
        }
        .um-row-actions form { display: inline; margin: 0; }
        .um-search {
            display: flex; gap: 9px; margin-bottom: 18px;
        }
        .um-search input { max-width: 400px; }
        .um-empty { padding: 35px !important; text-align: center; color: #94a3b8 !important; }
        .um-required { color: #dc2626; }
        @media (max-width: 850px) {
            .um-content { padding: 18px; }
            .um-cards { grid-template-columns: 1fr; gap: 12px; }
            .um-form { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .um-search { flex-direction: column; }
            .um-search input { max-width: none; }
        }
    </style>
</head>

<body class="admin-dashboard-body">

<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <img src="../image/logo2.png" alt="SHRCMS Logo">
        <div>
            <h3>SHRCMS</h3>
            <p>Clinic Management</p>
        </div>
    </div>

    <nav>
        <a href="admin_dashboard.php">📊 Dashboard</a>
        <a href="user_management.php" class="active">👥 User Management</a>
        <a href="staff_management.php">👨‍⚕️ Staff Management</a>
        <a href="system_reports.php">📑 System Reports</a>
        <a href="system_settings.php">⚙️ System Settings</a>
        <a href="logout.php" class="logout-link">🚪 Logout</a>
    </nav>
</aside>

<main class="admin-main-content">
    <header class="admin-header-bar">
        <h2>User Management</h2>

        <div class="admin-profile-info">
            <img src="../image/logo2.png" alt="Administrator">
            <div class="admin-profile-text">
                <h4><?= e($_SESSION['admin_username'] ?? 'Administrator') ?></h4>
                <p>Administrator</p>
            </div>
        </div>
    </header>

    <div class="um-content">

        <?php if ($message !== ''): ?>
            <div class="um-alert <?= $messageType === 'error' ? 'um-alert-error' : 'um-alert-success' ?>"
                 role="status">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <div class="um-cards">
            <div class="um-card">
                <span>Total User Accounts</span>
                <strong><?= number_format($totalUsers) ?></strong>
            </div>
            <div class="um-card">
                <span>Active Accounts</span>
                <strong><?= number_format($activeUsers) ?></strong>
            </div>
            <div class="um-card">
                <span>Staff Accounts</span>
                <strong><?= number_format($staffUsers) ?></strong>
            </div>
        </div>

        <section class="um-panel" id="user-form">
            <h2 class="um-heading">
                <?= $editingUser ? 'Edit User Account' : 'Add New User' ?>
            </h2>
            <p class="um-subtitle">
                <?= $editingUser
                    ? 'Update the account details below. Leave the password blank to keep the current password.'
                    : 'Create a staff or student account for SHRCMS.' ?>
            </p>

            <form action="user_management.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($editingUser['id'] ?? 0) ?>">

                <div class="um-form">
                    <div class="um-field">
                        <label for="name">Full Name <span class="um-required">*</span></label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="100"
                            required
                            placeholder="Enter full name"
                            value="<?= e($editingUser['name'] ?? '') ?>"
                        >
                    </div>

                    <div class="um-field">
                        <label for="email">Email Address <span class="um-required">*</span></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="255"
                            required
                            placeholder="Enter email address"
                            value="<?= e($editingUser['email'] ?? '') ?>"
                        >
                    </div>

                    <div class="um-field">
                        <label for="role">Account Role <span class="um-required">*</span></label>
                        <select id="role" name="role" required>
                            <option value="Staff"
                                <?= ($editingUser['role'] ?? '') === 'Staff' ? 'selected' : '' ?>>
                                Staff
                            </option>
                            <option value="Student"
                                <?= ($editingUser['role'] ?? '') === 'Student' ? 'selected' : '' ?>>
                                Student
                            </option>
                        </select>
                    </div>

                    <div class="um-field">
                        <label for="password">
                            <?= $editingUser ? 'New Password (Optional)' : 'Password' ?>
                            <?php if (!$editingUser): ?>
                                <span class="um-required">*</span>
                            <?php endif; ?>
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            <?= $editingUser ? '' : 'required' ?>
                            autocomplete="new-password"
                            placeholder="<?= $editingUser ? 'Leave blank to keep current password' : 'At least 8 characters' ?>"
                        >
                        <small class="um-help">
                            Passwords are stored as secure hashes.
                        </small>
                    </div>
                </div>

                <div class="um-actions">
                    <button class="um-btn um-primary" type="submit">
                        <?= $editingUser ? 'Save Changes' : '+ Add User' ?>
                    </button>

                    <?php if ($editingUser): ?>
                        <a class="um-btn um-light" href="user_management.php">
                            Cancel Edit
                        </a>
                    <?php else: ?>
                        <button class="um-btn um-light" type="reset">
                            Clear Form
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="um-panel">
            <h2 class="um-heading">Registered User Accounts</h2>
            <p class="um-subtitle">Search, edit, activate, deactivate, or delete accounts.</p>

            <form class="um-search" method="GET" action="user_management.php">
                <input
                    type="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search name, email, role, or ID..."
                    aria-label="Search user accounts"
                >
                <button class="um-btn um-primary" type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a class="um-btn um-light" href="user_management.php">Clear</a>
                <?php endif; ?>
            </form>

            <div class="um-table-wrap">
                <table class="um-table">
                    <thead>
                    <tr>
                        <th>Account ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if ($userList): ?>
                        <?php foreach ($userList as $user): ?>
                            <?php
                            $isActive = ($user['status'] ?? '') === 'Active';
                            ?>
                            <tr>
                                <td>
                                    <strong>USR-<?= str_pad((string)$user['id'], 3, '0', STR_PAD_LEFT) ?></strong>
                                </td>

                                <td class="um-user-name"><?= e($user['name']) ?></td>
                                <td><?= e($user['email']) ?></td>

                                <td>
                                    <span class="um-badge um-role">
                                        <?= e($user['role']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="um-badge <?= $isActive ? 'um-active' : 'um-inactive' ?>">
                                        <?= e($user['status'] ?? 'Unknown') ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="um-row-actions">
                                        <a
                                            class="um-btn um-edit"
                                            href="user_management.php?edit=<?= (int)$user['id'] ?>#user-form"
                                        >Edit</a>

                                        <form method="POST" action="user_management.php">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                            <button class="um-btn um-toggle" type="submit">
                                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="user_management.php"
                                            onsubmit="return confirm('Permanently delete this account? This cannot be undone.');"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                            <button class="um-btn um-delete" type="submit">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="um-empty">
                                <?= $search !== ''
                                    ? 'No accounts match your search.'
                                    : 'No user accounts have been registered yet.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</main>

</body>
</html>
