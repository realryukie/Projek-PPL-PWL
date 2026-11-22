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
        <svg
            width="26"
            height="26"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <path d="M16 17l5-5-5-5"></path>
            <path d="M21 12H9"></path>
        </svg>
        Go to Web
    </a>
</header>
