<?php
require_once __DIR__ . '/../includes/auth.php';

// Already logged in? No need to register again.
if (isLoggedIn()) {
    redirect('/index.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verifyCsrf()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // role is not set here: the database default is 'user'
            $stmt = $conn->prepare('INSERT INTO users (email, password) VALUES (?, ?)');
            $stmt->execute([$email, $passwordHash]);

            setFlash('success', 'Account created. You can log in now.');
            redirect('/auth/login.php');
        } catch (PDOException $exception) {
            // 1062 = duplicate entry (email already exists, see UNIQUE key on users.email)
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                $errors[] = 'This email is already registered.';
            } else {
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - Kostku</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Inter:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/auth.css') ?>">
</head>
<body>
    <aside class="auth-side">
        <?php if (is_file(__DIR__ . '/../assets/img/logo.png')): ?>
            <img class="auth-logo" src="<?= BASE_URL ?>/assets/img/logo.png" alt="Kostku">
        <?php else: ?>
            <div class="auth-logo auth-logo--text">Kostku</div>
        <?php endif; ?>
        <div class="auth-visual auth-visual--register"></div>
    </aside>

    <main class="auth-main">
        <section class="auth-card">
            <h1>Register to Kostku</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <label class="visually-hidden" for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Email..."
                       value="<?= e($email) ?>" autocomplete="email" required>

                <label class="visually-hidden" for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Password..."
                       autocomplete="new-password" minlength="8" required>

                <button type="submit">Register</button>
            </form>

            <p class="auth-switch">Have an account already? <a href="<?= BASE_URL ?>/auth/login.php">Log In!</a></p>
        </section>
    </main>
</body>
</html>
