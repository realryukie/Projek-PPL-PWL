<?php
// Bottom part of user-facing pages (footer). Optional before including:
//   $extraJs - array of js file names inside assets/js/, e.g. ['product_desc.js']
$extraJs = $extraJs ?? [];
?>
    <?php if (empty($hideFooter)): ?>
        <footer class="site-footer">
            <div class="container site-footer__inner">
                <div class="site-footer__brand">
                    <?php if (is_file(__DIR__ . '/../assets/img/logo.png')): ?>
                        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Kostku">
                    <?php else: ?>
                        <strong>Kostku</strong>
                    <?php endif; ?>
                </div>

                <div>
                    <h2>Profil</h2>
                    <p>Platform pencarian dan pemesanan kos di Kalimantan Barat.</p>
                </div>

                <div>
                    <h2>Kostku</h2>
                    <ul>
                        <li><a href="<?= BASE_URL ?>/index.php#about">Tentang Kami</a></li>
                        <li><a href="<?= BASE_URL ?>/index.php#terms">Syarat dan Ketentuan</a></li>
                        <li><a href="<?= BASE_URL ?>/index.php#privacy">Kebijakan Privasi</a></li>
                        <li><a href="<?= BASE_URL ?>/index.php#contract">Kontrak</a></li>
                    </ul>
                </div>

                <div>
                    <h2>Tipe Hunian</h2>
                    <ul>
                        <li><a href="<?= BASE_URL ?>/search.php?type=male">Kost Putra</a></li>
                        <li><a href="<?= BASE_URL ?>/search.php?type=female">Kost Putri</a></li>
                        <li><a href="<?= BASE_URL ?>/search.php?type=mixed">Kost Campuran</a></li>
                        <li><a href="<?= BASE_URL ?>/search.php">Kontrakan</a></li>
                    </ul>
                </div>

                <div>
                    <h2>Hubungi Kami</h2>
                    <ul>
                        <li>kostku@gmail.com</li>
                        <li>+6211-2222-3333</li>
                        <li>Pontianak, Kalimantan Barat</li>
                    </ul>
                </div>
            </div>
        </footer>
    <?php endif; ?>

    <?php $footerScrollPath = __DIR__ . '/../assets/js/footer-scroll.js'; ?>
    <script src="<?= BASE_URL ?>/assets/js/footer-scroll.js?v=<?= (int) filemtime($footerScrollPath) ?>" defer></script>
    <?php foreach ($extraJs as $jsFile): ?>
        <?php $jsPath = __DIR__ . '/../assets/js/' . $jsFile; ?>
        <script src="<?= BASE_URL ?>/assets/js/<?= e($jsFile) ?>?v=<?= (int) filemtime($jsPath) ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>
