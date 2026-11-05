<?php
// Top part of every admin page (topbar + sidebar).
// Before including it, set: $pageTitle (browser tab title) and $activeMenu (menu key).
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

requireAdmin(); // safety net: nobody but admins gets past this line

$pageTitle  = $pageTitle ?? 'Admin Panel';
$activeMenu = $activeMenu ?? '';
$adminCssPath = __DIR__ . '/../assets/css/admin.css';
$extraCss = $extraCss ?? [];

// Keep menu links enabled only for admin sections that are implemented.
$menuItems = [
    'boarding_houses' => ['label' => 'Data Kost',    'href' => '/admin/boarding_houses.php', 'ready' => true],
    'rooms'           => ['label' => 'Data Kamar',   'href' => '/admin/rooms.php',           'ready' => true],
    'bookings'        => ['label' => 'Data Booking', 'href' => '/admin/bookings.php',        'ready' => true],
];

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Kostku</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Inter:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=<?= (int) filemtime($adminCssPath) ?>">
    <?php foreach ($extraCss as $cssFile): ?>
        <?php $cssPath = __DIR__ . '/../assets/css/' . $cssFile; ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= e($cssFile) ?>?v=<?= (int) filemtime($cssPath) ?>">
    <?php endforeach; ?>
</head>
<body>
    <header class="admin-topbar">
        <div class="admin-topbar__brand">
            <?php if (is_file(__DIR__ . '/../assets/img/logo.png')): ?>
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Kostku">
            <?php else: ?>
                <strong>Kostku</strong>
            <?php endif; ?>
            <span>Admin Panel</span>
        </div>
        <a class="admin-web-link" href="<?= BASE_URL ?>/index.php">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <path d="M16 17l5-5-5-5"></path>
                <path d="M21 12H9"></path>
            </svg>
            Go to Web
        </a>
    </header>

    <div class="admin-shell">
        <aside class="admin-sidebar">
            <nav class="admin-menu" aria-label="Menu admin">
                <?php foreach ($menuItems as $key => $item): ?>
                    <?php if (!$item['ready']): ?>
                        <span class="is-disabled" title="Belum tersedia"><?= e($item['label']) ?></span>
                    <?php else: ?>
                        <a href="<?= BASE_URL . $item['href'] ?>"
                           class="<?= $key === $activeMenu ? 'is-active' : '' ?>"
                           <?= $key === $activeMenu ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <a class="admin-user" href="<?= BASE_URL ?>/profile.php?tab=account" aria-label="Buka profil <?= e($_SESSION['name']) ?>">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 4a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7zm0 14.2a8 8 0 0 1-6-2.7c.03-2 4-3.1 6-3.1s5.97 1.1 6 3.1a8 8 0 0 1-6 2.7z"></path>
                </svg>
                <div>
                    <?= e($_SESSION['name']) ?>
                    <small>ID: <?= (int) currentUserId() ?></small>
                </div>
            </a>
        </aside>

        <main class="admin-content">
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
            <?php endif; ?>
