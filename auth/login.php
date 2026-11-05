<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirect('/index.php');
}

$errors = [];
$email = '';
$successMessage = getFlash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verifyCsrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // New session id after login (prevents session fixation)
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'] ?? $user['email'];

            if ($user['role'] === 'admin') {
                unset($_SESSION['return_to']);
                redirect('/index.php');
            }

            $returnTo = $_SESSION['return_to'] ?? null;
            unset($_SESSION['return_to']);
            if (is_string($returnTo) && preg_match('#^/product_desc\.php\?id=\d+$#', $returnTo)) {
                redirect($returnTo);
            }

            redirect('/index.php');
        }

        // Same message for "email not found" and "wrong password" on purpose
        $errors[] = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Kostku</title>
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
        <div class="auth-visual auth-visual--login"></div>
    </aside>

    <main class="auth-main">
        <section class="auth-card">
            <h1>Login to Kostku</h1>

            <?php if ($successMessage): ?>
                <div class="alert alert-success" role="status">
                    <p><?= e($successMessage) ?></p>
                </div>
            <?php endif; ?>

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
                       autocomplete="current-password" required>

                <button type="submit">Login</button>
            </form>

            <p class="auth-switch">Don't have an account? <a href="<?= BASE_URL ?>/auth/register.php">Sign up</a> for free!</p>
        </section>
    </main>
</body>
</html>
