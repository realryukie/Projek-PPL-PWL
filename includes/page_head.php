<?php
// Document head for user-facing pages.
require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? 'Kostku';
$extraCss  = $extraCss ?? [];
$siteCssPath = __DIR__ . '/../assets/css/site.css';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        if (new URLSearchParams(window.location.search).has('scroll_to')) {
            window.history.scrollRestoration = 'manual';
        }
    </script>
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Inter:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/site.css?v=<?= (int) filemtime($siteCssPath) ?>">
    <?php foreach ($extraCss as $cssFile): ?>
        <?php $cssPath = __DIR__ . '/../assets/css/' . $cssFile; ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= e($cssFile) ?>?v=<?= (int) filemtime($cssPath) ?>">
    <?php endforeach; ?>
</head>
<body>
