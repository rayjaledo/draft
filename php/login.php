
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Accept either email or student ID.
    $login_identifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login_identifier === '' || $password === '') {

        $error_msg = 'Please enter your school ID or email and password.';

    } elseif (!isset($conn) || !$conn) {

        $error_msg = 'Database connection failed.';

    } else {

        $account_found = false;

        // ==========================================
        // 1. CHECK ADMIN ACCOUNT USING EMAIL
        // ==========================================
        $stmt = $conn->prepare(
            "SELECT * FROM admin WHERE email = ? LIMIT 1"
        );

        if (!$stmt) {

            $error_msg = 'Unable to process your login. Please try again.';

        } else {

            $stmt->bind_param('s', $login_identifier);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {

                $account_found = true;
                $admin = $result->fetch_assoc();
                $stored_password = (string) $admin['password'];

                if (
                    password_verify($password, $stored_password) ||
                    hash_equals($stored_password, $password)
                ) {

                    session_regenerate_id(true);

                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['user_role'] = 'Administrator';

                    header('Location: admin_dashboard.php');
                    exit;

                } else {

                    $error_msg = 'Invalid email or password.';
                }
            }

            $stmt->close();
        }

        // ==========================================
        // 2. CHECK STAFF/STUDENT ACCOUNT
        //    USING EMAIL OR student_id_no
        // ==========================================
        if (!$account_found && $error_msg === '') {

            $stmt_user = $conn->prepare(
                "SELECT * FROM users
                 WHERE email = ? OR student_id_no = ?
                 LIMIT 2"
            );

            if (!$stmt_user) {

                $error_msg = 'Unable to process your login. Please try again.';

            } else {

                $stmt_user->bind_param(
                    'ss',
                    $login_identifier,
                    $login_identifier
                );

                $stmt_user->execute();
                $result_user = $stmt_user->get_result();

                if ($result_user && $result_user->num_rows === 1) {

                    $user = $result_user->fetch_assoc();
                    $stored_password = (string) $user['password'];

                    if (
                        password_verify($password, $stored_password) ||
                        hash_equals($stored_password, $password)
                    ) {

                        $role = strtolower(trim($user['role'] ?? ''));

                        if (!in_array($role, ['staff', 'student'], true)) {

                            $error_msg = 'This account role is not authorized.';

                        } else {

                            session_regenerate_id(true);

                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_username'] = $user['name'];
                            $_SESSION['user_role'] = ucfirst($role);
                            $_SESSION['user_id'] = $user['id'];

                            if ($role === 'staff') {
                                header('Location: staff/staff_dashboard.php');
                                exit;
                            }

                            header('Location: student/student_dashboard.php');
                            exit;
                        }

                    } else {

                        $error_msg = 'Invalid email or password.';
                    }

                } elseif ($result_user && $result_user->num_rows > 1) {

                    $error_msg = 'This login ID matches multiple accounts. Please contact the administrator.';

                } else {

                    $error_msg = 'Invalid email or password.';
                }

                $stmt_user->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - SHRCMS</title>

    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="login-wrapper">

    <div class="login-card">

        <div class="login-left-section">

            <div class="login-brand">

                <img
                    src="../image/logo2.png"
                    alt="SHRCMS Logo"
                    class="login-logo-img"
                >

                <div>
                    <h2>SHRCMS</h2>
                    <p>
                        Student Health Record &amp;<br>
                        Clinic Management System
                    </p>
                </div>

            </div>

            <div class="login-quote">
                <h3>
                    “Better health,<br>
                    better learning.”
                </h3>
            </div>

            <div class="login-illustration"></div>

        </div>

        <div class="login-right-section">

            <h2>Login to Your Account</h2>

            <p style="color:#64748b; margin:8px 0 22px;">
                Welcome back! Please enter your credentials.
            </p>

            <?php if (
                isset($_GET['registered']) &&
                $_GET['registered'] === '1'
            ): ?>

                <div style="
                    color:#166534;
                    background:#dcfce7;
                    padding:10px;
                    border-radius:6px;
                    margin-bottom:15px;
                    font-size:14px;
                ">
                    Account created successfully! You can now log in.
                </div>

            <?php endif; ?>

            <?php if ($error_msg !== ''): ?>

                <div
                    class="login-error"
                    role="alert"
                    style="
                        color:#dc2626;
                        background:#fee2e2;
                        padding:10px;
                        border-radius:6px;
                        margin-bottom:15px;
                        font-size:14px;
                    "
                >
                    <?= htmlspecialchars(
                        $error_msg,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <form action="login.php" method="POST">

                <div class="login-input-group">

                    <span class="input-icon">✉️</span>

                    <input
                        type="text"
                        name="email"
                        required
                        maxlength="255"
                        autocomplete="username"
                        placeholder="Enter School ID or Email Address"
                        value="<?= htmlspecialchars(
                            $_POST['email'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="login-input-group">

                    <span class="input-icon">🔒</span>

                    <input
                        type="password"
                        name="password"
                        id="loginPassword"
                        required
                        autocomplete="current-password"
                        placeholder="Enter Password"
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Show password"
                        aria-pressed="false"
                        style="
                            background:none;
                            border:none;
                            cursor:pointer;
                        "
                    >
                        👁
                    </button>

                </div>

                <div class="login-options">

                    <label>
                        <input
                            type="checkbox"
                            name="remember_me"
                            value="1"
                        >
                        Remember me
                    </label>

                    <a href="forgot_password.php" class="forgot-link">
                        Forgot password?
                    </a>

                </div>

                <button
                    type="submit"
                    class="login-submit-btn"
                >
                    Login
                </button>

            </form>

            <p style="
                text-align:center;
                margin-top:22px;
                font-size:14px;
                color:#64748b;
            ">
                Don't have an account?

                <a
                    href="signup.php"
                    style="
                        color:#2563eb;
                        text-decoration:none;
                        font-weight:600;
                    "
                >
                    Sign Up
                </a>
            </p>

        </div>

    </div>

    <script src="../js/login.js"></script>

</body>
</html>
