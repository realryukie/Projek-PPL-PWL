<header class="site-header">
    <div class="container site-header__inner<?= isset($searchBar) && is_array($searchBar) ? ' has-search' : '' ?>">
        <a class="brand" href="<?= BASE_URL ?>/index.php">
            <?php if (is_file(__DIR__ . '/../assets/img/logo.png')): ?>
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Kostku">
            <?php else: ?>
                <span class="brand__text">Kostku</span>
            <?php endif; ?>
        </a>

        <?php if (isset($searchBar) && is_array($searchBar)): ?>
            <form class="search-bar" method="get" action="<?= BASE_URL ?>/search.php">
                <label class="search-field">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"></path>
                        <circle cx="12" cy="9" r="2.3"></circle>
                    </svg>
                    <input type="search" name="location" placeholder="Lokasi" value="<?= e($searchBar['location']) ?>" aria-label="Lokasi">
                </label>

                <label class="search-field search-field--date">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <rect x="3.5" y="5" width="17" height="16" rx="1"></rect>
                        <path d="M7.5 3v4M16.5 3v4M3.5 10h17"></path>
                    </svg>
                    <input type="date" name="date_in" value="<?= e($searchBar['date_in']) ?>" aria-label="Tanggal masuk">
                </label>

                <label class="search-field search-field--type">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-7h6v7"></path>
                    </svg>
                    <select name="type" aria-label="Tipe kost">
                        <option value="">Semua</option>
                        <option value="male"<?= $searchBar['type'] === 'male' ? ' selected' : '' ?>>Kost putra</option>
                        <option value="female"<?= $searchBar['type'] === 'female' ? ' selected' : '' ?>>Kost putri</option>
                        <option value="mixed"<?= $searchBar['type'] === 'mixed' ? ' selected' : '' ?>>Campuran</option>
                        <option value="house"<?= $searchBar['type'] === 'house' ? ' selected' : '' ?>>Kontrakan</option>
                    </select>
                </label>

                <details class="search-filter">
                    <summary>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 6h16M7 12h10M10 18h4"></path>
                            <circle cx="8" cy="6" r="1.5" fill="currentColor"></circle>
                            <circle cx="15" cy="12" r="1.5" fill="currentColor"></circle>
                            <circle cx="12" cy="18" r="1.5" fill="currentColor"></circle>
                        </svg>
                        Filter
                    </summary>
                    <div class="search-filter__panel">
                        <label class="filter-field">
                            <span>Harga minimum / bulan</span>
                            <input type="number" name="min_price" min="0" step="10000" value="<?= e($searchBar['min_price']) ?>">
                        </label>
                        <label class="filter-field">
                            <span>Harga maksimum / bulan</span>
                            <input type="number" name="max_price" min="0" step="10000" value="<?= e($searchBar['max_price']) ?>">
                        </label>
                        <label class="filter-field">
                            <span>Urutkan</span>
                            <select name="sort">
                                <option value="recommended"<?= $searchBar['sort'] === 'recommended' ? ' selected' : '' ?>>Rekomendasi</option>
                                <option value="price_asc"<?= $searchBar['sort'] === 'price_asc' ? ' selected' : '' ?>>Harga terendah</option>
                                <option value="price_desc"<?= $searchBar['sort'] === 'price_desc' ? ' selected' : '' ?>>Harga tertinggi</option>
                                <option value="newest"<?= $searchBar['sort'] === 'newest' ? ' selected' : '' ?>>Terbaru</option>
                            </select>
                        </label>
                        <input type="hidden" name="date_out" value="<?= e($searchBar['date_out']) ?>">
                    </div>
                </details>

                <button class="search-submit" type="submit" aria-label="Cari kost">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="10.8" cy="10.8" r="6.8"></circle>
                        <path d="m16 16 5 5"></path>
                    </svg>
                </button>
            </form>
        <?php else: ?>
            <nav class="site-nav" aria-label="Menu utama">
                <a href="<?= BASE_URL ?>/index.php">Beranda</a>
                <a href="<?= BASE_URL ?>/profile.php?tab=favorites">Favorit</a>
                <?php if (isAdmin()): ?>
                    <a href="<?= BASE_URL ?>/admin/boarding_houses.php">Admin Panel</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <a class="account-link" href="<?= BASE_URL . (isLoggedIn() ? '/profile.php' : '/auth/login.php') ?>" aria-label="Akun">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 4a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7zm0 14.2a8 8 0 0 1-6-2.7c.03-2 4-3.1 6-3.1s5.97 1.1 6 3.1a8 8 0 0 1-6 2.7z"></path>
            </svg>
        </a>
    </div>
</header>
