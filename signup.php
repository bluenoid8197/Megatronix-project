
<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/config/database.php';

// Redirect users who are already logged in.
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'seller') {
        header('Location: seller/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $nameLength = mb_strlen($name, 'UTF-8');

    if (
        $name === '' ||
        $email === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {

        $error = 'Please complete all fields.';

    } elseif ($nameLength < 3 || $nameLength > 20) {

        $error = 'Your username must contain between 3 and 20 characters.';

    } elseif (!preg_match('/\A[A-Za-z0-9_]+\z/', $name)) {

        $error = 'Your username can only contain letters, numbers, and underscores.';

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {

        $error = 'Please enter a valid email address.';

    } elseif (mb_strlen($password, 'UTF-8') < 8) {

        $error = 'Your password must contain at least 8 characters.';

    } elseif (strlen($password) > 72) {

        $error = 'Your password cannot exceed 72 bytes. Please use a shorter password.';

    } elseif ($password !== $confirmPassword) {

        $error = 'The passwords do not match.';

    } else {

        // Check whether the username is already taken.
        $checkUsername = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE LOWER(name) = LOWER(:name)
             LIMIT 1'
        );

        $checkUsername->execute([
            'name' => $name
        ]);

        if ($checkUsername->fetch()) {

            $error = 'This username is already taken. Please choose another one.';

        } else {

            // Check whether the email is already registered.
            $checkEmail = $pdo->prepare(
                'SELECT id
                 FROM users
                 WHERE LOWER(email) = LOWER(:email)
                 LIMIT 1'
            );

            $checkEmail->execute([
                'email' => $email
            ]);

            if ($checkEmail->fetch()) {

                $error = 'An account with this email address already exists.';

            } else {

                // Hash the password before saving it.
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role)
                     VALUES (:name, :email, :password, :role)'
                );

                $insert->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => 'buyer'
                ]);

                // Redirect to login after successful registration.
                header('Location: login.php?registered=1');
                exit;
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

    <title>Sign Up | Megatronix</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="styles.css">

</head>

<body>
    <main>
        <section class="hero login-section">
            <h1>Create Account</h1>
            <p class="login-description">Sign up to start exploring our products.</p>

            <?php if ($error !== ''): ?>
            <p class="login-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <?php endif; ?>

            <form method="POST" action="signup.php" class="login-form">

                <label for="name">Username</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Enter a username (3–20 characters)"
                    autocomplete="username"
                    minlength="3"
                    maxlength="20"
                    pattern="[A-Za-z0-9_]+"
                    title="Only letters, numbers, and underscores are allowed."
                    autocapitalize="none"
                    spellcheck="false"
                    value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required>

                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="email"
                    maxlength="150"
                    autocapitalize="none"
                    spellcheck="false"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required>

                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="At least 8 characters"
                    autocomplete="new-password"
                    minlength="8"
                    maxlength="72"
                    required>

                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Enter your password again"
                    autocomplete="new-password"
                    minlength="8"
                    maxlength="72"
                    required>

                <button type="submit" class="button">Create Account</button>
            </form>

            <p class="signup-text">
                Already have an account?
                <a href="login.php">Log in here</a>
            </p>

            <p class="back-home">
                <a href="index.php">Back to Home</a>
            </p>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-container">

            <div class="footer-brand">
                <h2>Megatronix</h2>
                <p>Your destination for electronics and gadgets.</p>
            </div>

            <div class="footer-links">
                <h3>Quick Links</h3>
                <a href="index.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="products.php">Browse Items</a>
                <a href="contact.php">Contact Us</a>
            </div>

            <div class="footer-contact">
                <h3>Customer Support</h3>
                <p>Have questions about our products?</p>
                <a href="contact.php">Get in touch with us</a>
            </div>

        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Megatronix. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Username validation.
        const usernameInput = document.getElementById('name');

        usernameInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^A-Za-z0-9_]/g, '');
        });

        // Password confirmation validation.
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        function validatePasswordMatch() {

            if (confirmPasswordInput.value !== '') {

                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity(
                        'The passwords do not match.'
                    );
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }

            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        }

        passwordInput.addEventListener('input', validatePasswordMatch);

        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    </script>
</body>

</html>
