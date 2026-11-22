<aside class="admin-sidebar">
    <nav class="admin-menu" aria-label="Menu admin">
        <?php foreach ([
            'boarding_houses' => ['label' => 'Data Kost', 'href' => '/admin/boarding_houses.php'],
            'rooms' => ['label' => 'Data Kamar', 'href' => '/admin/rooms.php'],
            'bookings' => ['label' => 'Data Booking', 'href' => '/admin/bookings.php'],
        ] as $key => $item): ?>
            <a
                href="<?= BASE_URL . $item['href'] ?>"
                class="<?= $key === $activeMenu ? 'is-active' : '' ?>"
                <?= $key === $activeMenu ? 'aria-current="page"' : '' ?>>
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <a
        class="admin-user"
        href="<?= BASE_URL ?>/profile.php?tab=account"
        aria-label="Buka profil <?= e($_SESSION['name']) ?>">
        <svg
            width="40"
            height="40"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true">
            <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 4a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7zm0 14.2a8 8 0 0 1-6-2.7c.03-2 4-3.1 6-3.1s5.97 1.1 6 3.1a8 8 0 0 1-6 2.7z"></path>
        </svg>
        <div>
            <?= e($_SESSION['name']) ?>
            <small>ID: <?= (int) currentUserId() ?></small>
        </div>
    </a>
</aside>
