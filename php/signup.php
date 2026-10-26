
<?php
session_start();
require_once 'db.php';

$error_msg = '';
$name = '';
$email = '';

// Create a CSRF token for the signup form.
if (empty($_SESSION['signup_csrf'])) {
    $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and clean submitted values.
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Passwords are intentionally not repopulated after submission.
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // 1. Validate CSRF token.
    if (
        !is_string($csrf_token) ||
        !isset($_SESSION['signup_csrf']) ||
        !hash_equals($_SESSION['signup_csrf'], $csrf_token)
    ) {
        $error_msg = 'Your session has expired. Refresh the page and try again.';
    }

    // 2. Validate required fields.
    elseif (
        $name === '' ||
        $email === '' ||
        $password === '' ||
        $confirm_password === ''
    ) {
        $error_msg = 'Please complete all required fields.';
    }

    // 3. Validate name.
    elseif (strlen($name) > 150) {
        $error_msg = 'Your name must not exceed 150 characters.';
    }

    // 4. Validate email.
    elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 255
    ) {
        $error_msg = 'Please enter a valid email address.';
    }

    // 5. Validate password.
    elseif (strlen($password) < 8) {
        $error_msg = 'Your password must contain at least 8 characters.';
    }

    elseif (strlen($password) > 4096) {
        $error_msg = 'Your password is too long.';
    }

    elseif ($password !== $confirm_password) {
        $error_msg = 'Passwords do not match.';
    }

    // 6. Check database connection.
    elseif (
        !isset($conn) ||
        !($conn instanceof mysqli) ||
        $conn->connect_errno
    ) {
        $error_msg = 'Database connection failed. Please check db.php.';
    }

    else {
        try {
            // 7. Check whether the email is already registered.
            $check = $conn->prepare(
                'SELECT id FROM users WHERE email = ? LIMIT 1'
            );

            $check->bind_param('s', $email);
            $check->execute();
            $check->store_result();

            $email_exists = $check->num_rows > 0;
            $check->close();

            if ($email_exists) {
                $error_msg = 'This email address is already registered.';
            } else {
                // 8. Hash the password before saving it.
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashed_password === false) {
                    $error_msg = 'Unable to create your account. Please try again.';
                } else {
                    // 9. Create the student account.
                    $role = 'student';

                    $insert = $conn->prepare(
                        'INSERT INTO users (name, email, password, role)
                         VALUES (?, ?, ?, ?)'
                    );

                    $insert->bind_param(
                        'ssss',
                        $name,
                        $email,
                        $hashed_password,
                        $role
                    );

                    if ($insert->execute()) {
                        $insert->close();

                        // Generate a fresh token after successful registration.
                        $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));

                        header('Location: login.php?registered=1');
                        exit;
                    } else {
                        $database_error = $insert->error;
                        $database_errno = $insert->errno;
                        $insert->close();

                        error_log(
                            'SHRCMS signup failed (' .
                            $database_errno . '): ' . $database_error
                        );

                        if ($database_errno === 1062) {
                            $error_msg = 'This email address is already registered.';
                        } else {
                            $error_msg = 'Registration failed. Check your database table and columns, then try again.';
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Log technical details; show a safe message to the user.
            error_log('SHRCMS signup exception: ' . $e->getMessage());

            $error_msg = 'Unable to complete registration. Check your database connection and users table.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Sign Up - SHRCMS</title>

    <link rel="stylesheet" href="../css/signup.css">
</head>

<body class="login-wrapper">

    <div class="login-card">

        <!-- LEFT SECTION -->
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
                        Student Health Record &<br>
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

        <!-- RIGHT SECTION -->
        <div class="login-right-section">

            <h2>Create Student Account</h2>

            <p style="color:#64748b; margin:8px 0 22px;">
                Register using your personal information.
            </p>

            <?php if ($error_msg !== ''): ?>
                <div class="signup-message signup-error" role="alert">
                    <?= htmlspecialchars(
                        $error_msg,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <form action="signup.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['signup_csrf'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <!-- FULL NAME -->
                <div class="signup-field">
                    <label for="name">Full Name</label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="150"
                        autocomplete="name"
                        placeholder="Enter your full name"
                        value="<?= htmlspecialchars(
                            $name,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <!-- EMAIL -->
                <div class="signup-field">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="255"
                        autocomplete="email"
                        placeholder="Enter your email address"
                        value="<?= htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <!-- PASSWORD -->
                <div class="signup-field">
                    <label for="password">Password</label>

                    <div class="signup-password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            maxlength="4096"
                            autocomplete="new-password"
                            placeholder="Create a password"
                            required
                        >

                        <button
                            type="button"
                            class="signup-password-toggle"
                            data-password-target="password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >👁</button>
                    </div>

                    <small class="signup-hint">
                        Use at least 8 characters.
                    </small>
                </div>

                <!-- CONFIRM PASSWORD -->
                <div class="signup-field">
                    <label for="confirm_password">Confirm Password</label>

                    <div class="signup-password-wrap">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            maxlength="4096"
                            autocomplete="new-password"
                            placeholder="Re-enter your password"
                            required
                        >

                        <button
                            type="button"
                            class="signup-password-toggle"
                            data-password-target="confirm_password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >👁</button>
                    </div>
                </div>

                <!-- REGISTER BUTTON -->
                <button
                    type="submit"
                    class="login-submit-btn"
                >
                    Create Account
                </button>

            </form>

            <p class="signup-login-link">
                Already have an account?
                <a href="login.php">Login</a>
            </p>

        </div>
    </div>

    <script src="../js/signup.js"></script>

</body>
</html>
