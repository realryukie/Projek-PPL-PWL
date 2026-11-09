<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/upload.php';
require_once __DIR__ . '/includes/cards.php';

$banners = fetchAllPrepared($conn, 'SELECT id, title, image FROM banners WHERE is_active = 1 ORDER BY sort_order, id');

$slides = [];
$defaultSlides = [
    ['title' => 'Temukan kost impianmu', 'url' => BASE_URL . '/assets/img/banners/banner1.webp'],
    ['title' => 'Booking mudah dan cepat', 'url' => BASE_URL . '/assets/img/banners/banner2.webp'],
    ['title' => 'Kost terpercaya di Kalimantan Barat', 'url' => BASE_URL . '/assets/img/banners/banner3.jpg'],
];

foreach (array_slice($banners, 0, 3) as $index => $banner) {
    $file = basename($banner['image']);
    $imagePath = __DIR__ . '/assets/img/banners/' . $file;
    $slides[] = [
        'title' => $banner['title'] ?: $defaultSlides[$index]['title'],
        'url'   => is_file($imagePath)
            ? BASE_URL . '/assets/img/banners/' . rawurlencode($file)
            : $defaultSlides[$index]['url'],
    ];
}

for ($index = count($slides); $index < 3; $index++) {
    $slides[] = $defaultSlides[$index];
}

$recommended = fetchAllPrepared(
    $conn,
    "SELECT * FROM v_boarding_house_summary
     ORDER BY (status = 'available') DESC, avg_rating DESC, review_count DESC, id DESC
     LIMIT 4"
);
$recommended = resolveHouseCardPhotos($conn, $recommended);

$pageTitle = 'Kostku - Cari dan Pesan Kos';
$extraCss  = ['home.css'];
$extraJs   = ['carousel.js', 'home-scroll.js'];
$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
require __DIR__ . '/includes/page_head.php';
require __DIR__ . '/includes/header.php';
?>
    <main class="home-page">
        <?php if ($flashSuccess): ?>
            <div class="container"><div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="container"><div class="alert alert-error" role="alert"><?= e($flashError) ?></div></div>
        <?php endif; ?>

        <section class="hero" data-carousel aria-label="Banner">
            <?php foreach ($slides as $index => $slide): ?>
                <div class="hero__slide<?= $index === 0 ? ' is-active' : '' ?><?= $index === 2 ? ' hero__slide--lower' : '' ?>"
                     <?= $slide['url'] ? 'style="background-image:url(\'' . e($slide['url']) . '\')"' : '' ?>
                     role="img" aria-label="<?= e($slide['title'] ?? 'Banner') ?>"></div>
            <?php endforeach; ?>

            <a class="hero__button" href="<?= BASE_URL ?>/search.php">MULAI MENCARI KOST!</a>

            <?php if (count($slides) > 1): ?>
                <div class="hero__dots">
                    <?php foreach ($slides as $index => $slide): ?>
                        <button type="button" class="hero__dot<?= $index === 0 ? ' is-active' : '' ?>"
                                aria-label="Banner <?= $index + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="container home home-top">
            <section class="home-section" id="recommended">
                <h2 class="section-title">Rekomendasi Untuk Anda</h2>
                <?php if (empty($recommended)): ?>
                    <p class="empty-note">Belum ada kost yang tersedia.</p>
                <?php else: ?>
                    <div class="house-carousel" data-house-carousel aria-label="Rekomendasi kost">
                        <div class="house-carousel__viewport">
                            <div class="house-grid house-carousel__track">
                                <?php foreach ($recommended as $house): ?>
                                    <?php renderHouseCard($house); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <p class="home-more"><a href="<?= BASE_URL ?>/search.php">Lihat semua kost</a></p>
            </section>
        </div>

        <section class="home-dark">
            <div class="container home home-dark__inner">
                <section class="home-section scroll-reveal" id="about">
                    <h2 class="section-title">Tentang Kami</h2>
                    <p class="section-text">
                        KOSTKU adalah platform pencarian dan pemesanan kos terpercaya yang menghubungkan pencari hunian
                        dengan pemilik kos secara cepat, transparan, dan aman di berbagai kota di Indonesia.
                    </p>
                </section>

                <section class="home-section scroll-reveal" id="terms">
                    <h2 class="section-title">Syarat dan Ketentuan</h2>
                    <div class="info-cards">
                        <div class="info-card scroll-reveal">Penyewa wajib mengisi data diri dengan benar pada form Booking.</div>
                        <div class="info-card scroll-reveal">Konfirmasi dan persetujuan sewa sepenuhnya dikelola oleh admin / pemilik kos.</div>
                        <div class="info-card scroll-reveal">Pembayaran dilakukan langsung melalui kontak WhatsApp resmi yang tertera.</div>
                    </div>
                </section>
            </div>
        </section>

        <div class="container home home-bottom">
            <section class="home-section home-privacy scroll-reveal" id="privacy">
                <?php
                $privacyPhotoLeft = BASE_URL . '/assets/img/login-side.jpg';
                $privacyPhotoRight = BASE_URL . '/assets/img/register-side.jpg';
                ?>
                <img class="home-privacy__photo home-privacy__photo--left"
                     src="<?= e($privacyPhotoLeft) ?>" alt="Hunian yang tersedia di Kostku" loading="lazy">
                <div class="home-privacy__content">
                    <h2 class="section-title">Kebijakan Privasi</h2>
                    <p class="section-text">
                        KOSTKU menjaga kerahasiaan data pribadi Anda (seperti nama, nomor telepon, dan email).
                        Data Anda hanya digunakan untuk keperluan verifikasi pemesanan kos dan tidak akan
                        disebarluaskan kepada pihak ketiga.
                    </p>
                </div>
                <img class="home-privacy__photo home-privacy__photo--right"
                     src="<?= e($privacyPhotoRight) ?>" alt="Hunian yang tersedia di Kostku" loading="lazy">
            </section>

            <section class="home-section scroll-reveal" id="contract">
                <h2 class="section-title">Kontrak</h2>
                <div class="info-cards">
                    <div class="info-card scroll-reveal">Setiap transaksi dan kesepakatan sewa kamar di KOSTKU diatur melalui perjanjian kontrak tertulis atau digital antara penyewa dan pemilik kos.</div>
                    <div class="info-card scroll-reveal">Masa berlaku kontrak sewa dihitung berdasarkan tanggal masuk (check-in) yang disetujui dalam sistem.</div>
                    <div class="info-card scroll-reveal">Segala bentuk perubahan ketentuan sewa, perpanjangan waktu, atau pembatalan wajib dikonfirmasikan kepada admin paling lambat 3 hari sebelum jatuh tempo.</div>
                </div>
            </section>
        </div>
    </main>
<?php
require __DIR__ . '/includes/footer.php';
require __DIR__ . '/includes/page_end.php';
?>
