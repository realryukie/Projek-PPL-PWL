<?php
// Visitor view of the Product Desc page.
// Included by product_desc.php when the URL has no mode=add / mode=edit.
// (auth.php, helpers.php and upload.php are already loaded by product_desc.php)

$houseId = (int) ($_GET['id'] ?? 0);
$self    = '/product_desc.php?id=' . $houseId;

$houseRows = fetchAllPrepared($conn, 'SELECT * FROM v_boarding_house_summary WHERE id = ?', 'i', [$houseId]);
$house     = $houseRows[0] ?? null;

if (!$house) {
    setFlash('error', 'Kost tidak ditemukan.');
    redirect('/index.php');
}

$rooms = fetchAllPrepared(
    $conn,
    'SELECT id, name, room_type, description, price_per_month, status, photo
     FROM rooms WHERE boarding_house_id = ? ORDER BY price_per_month, id',
    'i',
    [$houseId]
);
$availableRooms = array_values(array_filter($rooms, function ($room) {
    return $room['status'] === 'available';
}));
if ($house['status'] !== 'available') {
    $availableRooms = [];
}

$userId      = currentUserId();
$currentUser = null;
if ($userId) {
    $userRows    = fetchAllPrepared($conn, 'SELECT name, country_code, phone_number FROM users WHERE id = ?', 'i', [$userId]);
    $currentUser = $userRows[0] ?? null;
}

$bookingForm = [
    'booker_name'   => $currentUser['name'] ?? '',
    'contact'       => phoneWithCountryCode($currentUser['country_code'] ?? null, $currentUser['phone_number'] ?? null),
    'check_in_date' => '',
    'check_out_date' => '',
    'room_id'       => (string) (int) ($_GET['room'] ?? 0),
];
if ($bookingForm['room_id'] === '0') {
    $bookingForm['room_id'] = '';
}

$bookingErrors = [];
$reviewErrors  = [];
$reviewForm    = ['rating' => '', 'comment' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCsrf()) {
        setFlash('error', 'Sesi kedaluwarsa. Coba lagi.');
        redirect($self);
    }

    // These actions need an account. After login the visitor comes back here.
    if (!isLoggedIn()) {
        $_SESSION['return_to'] = $self;
        setFlash('error', 'Silakan login dulu untuk melanjutkan.');
        redirect('/auth/login.php');
    }

    if ($action === 'favorite') {
        $existing = fetchAllPrepared(
            $conn,
            'SELECT id FROM favorites WHERE user_id = ? AND boarding_house_id = ?',
            'ii',
            [$userId, $houseId]
        );

        if ($existing) {
            executePrepared($conn, 'DELETE FROM favorites WHERE user_id = ? AND boarding_house_id = ?', 'ii', [$userId, $houseId]);
            setFlash('success', 'Kost dihapus dari favorit.');
        } else {
            executePrepared($conn, 'INSERT IGNORE INTO favorites (user_id, boarding_house_id) VALUES (?, ?)', 'ii', [$userId, $houseId]);
            setFlash('success', 'Kost ditambahkan ke favorit.');
        }
        redirect($self);
    }

    if ($action === 'book') {
        foreach ($bookingForm as $field => $default) {
            $bookingForm[$field] = trim($_POST[$field] ?? '');
        }

        if ($bookingForm['booker_name'] === '' || mb_strlen($bookingForm['booker_name']) > 100) {
            $bookingErrors[] = 'Nama wajib diisi (maksimal 100 karakter).';
        }

        $contact = normalizePhone($bookingForm['contact']);
        if (strlen($contact) < 9 || strlen($contact) > 15) {
            $bookingErrors[] = 'Kontak harus berisi 9 sampai 15 digit.';
        }

        $room   = null;
        $roomId = (int) $bookingForm['room_id'];
        foreach ($rooms as $candidate) {
            if ((int) $candidate['id'] === $roomId) {
                $room = $candidate;
            }
        }
        if (!$room || $room['status'] !== 'available') {
            $bookingErrors[] = 'Pilih kamar yang tersedia.';
        }

        $checkIn  = $bookingForm['check_in_date'];
        $checkOut = $bookingForm['check_out_date'];
        $datesOk  = true;

        if (!isValidDate($checkIn)) {
            $bookingErrors[] = 'Tanggal masuk tidak valid.';
            $datesOk = false;
        } elseif ($checkIn < date('Y-m-d')) {
            $bookingErrors[] = 'Tanggal masuk tidak boleh sebelum hari ini.';
            $datesOk = false;
        }
        if (!isValidDate($checkOut)) {
            $bookingErrors[] = 'Tanggal keluar tidak valid.';
            $datesOk = false;
        } elseif ($datesOk && $checkOut <= $checkIn) {
            $bookingErrors[] = 'Tanggal keluar harus setelah tanggal masuk.';
            $datesOk = false;
        }

        if ($house['status'] !== 'available') {
            $bookingErrors[] = 'Kost ini sedang penuh dan tidak menerima booking.';
        }

        // Total = price per month x number of months (a started month counts as a full month)
        $totalPrice = 0;
        if (empty($bookingErrors)) {
            try {
                $saved = executeBookingWithRoomLock($conn, $roomId, function (array $lockedRoom) use (
                    $conn, $userId, $roomId, $bookingForm, $contact, $checkIn, $checkOut, &$totalPrice, &$bookingErrors
                ): bool {
                    if ($lockedRoom['status'] !== 'available' || $lockedRoom['house_status'] !== 'available') {
                        $bookingErrors[] = 'Kost atau kamar ini sedang tidak tersedia.';
                        return false;
                    }
                    if (!isRoomAvailable($conn, $roomId, $checkIn, $checkOut)) {
                        $bookingErrors[] = 'Kamar ini sudah dipesan pada tanggal tersebut. Pilih tanggal atau kamar lain.';
                        return false;
                    }

                    $price = (int) $lockedRoom['price_per_month'];
                    $totalPrice = calculateTotalPrice($price, $checkIn, $checkOut);
                    if ($totalPrice > 4294967295) {
                        $bookingErrors[] = 'Rentang tanggal terlalu panjang.';
                        return false;
                    }

                    executePrepared(
                        $conn,
                        'INSERT INTO bookings (user_id, room_id, booker_name, contact, check_in_date, check_out_date, price_at_booking, total_price)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        'iissssii',
                        [$userId, $roomId, $bookingForm['booker_name'], $contact, $checkIn, $checkOut, $price, $totalPrice]
                    );
                    return true;
                });

                if ($saved) {
                    setFlash('success', 'Booking terkirim. Admin akan mengonfirmasi lewat kontak WhatsApp yang kamu isi.');
                    redirect($self . '#booking');
                }
            } catch (PDOException $exception) {
                $bookingErrors[] = 'Booking gagal disimpan. Coba lagi.';
            }
        }
    }

    if ($action === 'review') {
        $reviewForm['rating']  = trim($_POST['rating'] ?? '');
        $reviewForm['comment'] = trim($_POST['comment'] ?? '');

        $rating = filter_var($reviewForm['rating'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
        if ($rating === false) {
            $reviewErrors[] = 'Pilih rating 1 sampai 5 bintang.';
        }
        if (mb_strlen($reviewForm['comment']) > 1000) {
            $reviewErrors[] = 'Komentar maksimal 1000 karakter.';
        }

        if (empty($reviewErrors)) {
            $comment = $reviewForm['comment'] === '' ? null : $reviewForm['comment'];
            executePrepared(
                $conn,
                'INSERT INTO reviews (user_id, boarding_house_id, rating, comment) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)',
                'iiis',
                [$userId, $houseId, $rating, $comment]
            );
            setFlash('success', 'Terima kasih! Feedback kamu sudah disimpan.');
            redirect($self . '#feedback');
        }
    }
}

$isFavorite = false;
if ($userId) {
    $isFavorite = !empty(fetchAllPrepared(
        $conn,
        'SELECT id FROM favorites WHERE user_id = ? AND boarding_house_id = ?',
        'ii',
        [$userId, $houseId]
    ));

    // Existing review of this user (only when the form was not just submitted with errors)
    if ($reviewForm['rating'] === '' && $reviewForm['comment'] === '') {
        $ownReview = fetchAllPrepared(
            $conn,
            'SELECT rating, comment FROM reviews WHERE user_id = ? AND boarding_house_id = ?',
            'ii',
            [$userId, $houseId]
        );
        if ($ownReview) {
            $reviewForm['rating']  = (string) $ownReview[0]['rating'];
            $reviewForm['comment'] = (string) ($ownReview[0]['comment'] ?? '');
        }
    }
}

$reviews = fetchAllPrepared(
    $conn,
    'SELECT rv.rating, rv.comment, rv.updated_at, u.name
     FROM reviews rv JOIN users u ON u.id = rv.user_id
     WHERE rv.boarding_house_id = ?
     ORDER BY rv.updated_at DESC LIMIT 5',
    'i',
    [$houseId]
);

// Slider photos: cover photo first, then the gallery. Files that do not exist on disk are skipped.
$gallerySlides = [];
if (uploadFileExists('boarding_houses', $house['photo'])) {
    $gallerySlides[] = uploadUrl('boarding_houses', $house['photo']);
}
$galleryRows = fetchAllPrepared(
    $conn,
    'SELECT image FROM boarding_house_images WHERE boarding_house_id = ? ORDER BY sort_order, id',
    'i',
    [$houseId]
);
foreach ($galleryRows as $galleryRow) {
    if (uploadFileExists('boarding_houses', $galleryRow['image'])) {
        $gallerySlides[] = uploadUrl('boarding_houses', $galleryRow['image']);
    }
}

$contactNumber = preg_replace('/\D+/', '', (string) $house['contact']);

$mapUrl = null;
if ($house['latitude'] !== null && $house['longitude'] !== null) {
    $lat    = (float) $house['latitude'];
    $lng    = (float) $house['longitude'];
    $mapUrl = sprintf(
        'https://www.openstreetmap.org/export/embed.html?bbox=%.6F%%2C%.6F%%2C%.6F%%2C%.6F&layer=mapnik&marker=%.6F%%2C%.6F',
        $lng - 0.006, $lat - 0.004, $lng + 0.006, $lat + 0.004, $lat, $lng
    );
}

$pageTitle = $house['name'] . ' - Kostku';
$extraCss  = ['product_desc.css', 'product_view.css'];
$extraJs   = ['product_view.js'];
require __DIR__ . '/header.php';
?>
    <main class="container product">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
        <?php endif; ?>

        <section class="product-top">
            <?php if (empty($gallerySlides)): ?>
                <div class="photo-box"><span>Belum ada foto</span></div>
            <?php else: ?>
                <div class="gallery" data-gallery role="group" aria-roledescription="carousel" aria-label="Foto <?= e($house['name']) ?>">
                    <?php foreach ($gallerySlides as $index => $slideUrl): ?>
                        <div class="gallery__slide<?= $index === 0 ? ' is-active' : '' ?>"
                             style="background-image:url('<?= e($slideUrl) ?>')"
                             role="img" aria-label="Foto <?= $index + 1 ?> dari <?= count($gallerySlides) ?>"></div>
                    <?php endforeach; ?>

                    <?php if (count($gallerySlides) > 1): ?>
                        <button type="button" class="gallery__nav gallery__nav--prev" data-gallery-prev aria-label="Foto sebelumnya">&#8249;</button>
                        <button type="button" class="gallery__nav gallery__nav--next" data-gallery-next aria-label="Foto berikutnya">&#8250;</button>
                        <div class="gallery__dots">
                            <?php foreach ($gallerySlides as $index => $slideUrl): ?>
                                <button type="button" class="gallery__dot<?= $index === 0 ? ' is-active' : '' ?>" aria-label="Foto <?= $index + 1 ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="product-info">
                <h1><?= e($house['name']) ?></h1>
                <p class="product-address"><?= e($house['address']) ?>, <?= e($house['city']) ?></p>
                <p class="product-desc"><?= nl2br(e($house['description'] ?? '')) ?></p>

                <dl class="info-list">
                    <div><dt>Harga:</dt><dd><?= e(formatRupiah($house['price_per_month'])) ?> / bulan</dd></div>
                    <div><dt>Tipe:</dt><dd><?= e(typeLabel($house['type'])) ?></dd></div>
                    <div><dt>Status:</dt><dd><span class="badge badge-<?= e($house['status']) ?>"><?= e(statusLabel($house['status'])) ?></span></dd></div>
                    <div><dt>Kontak:</dt>
                        <dd>
                            <?php if ($contactNumber !== ''): ?>
                                <a href="https://wa.me/<?= e($contactNumber) ?>" target="_blank" rel="noopener">+<?= e($contactNumber) ?> (WhatsApp)</a>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>

                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="favorite">
                    <button type="submit" class="favorite-button<?= $isFavorite ? ' is-active' : '' ?>">
                        <?= $isFavorite ? '&#9829; Hapus dari Favorit' : '&#9825; Tambah ke Favorit' ?>
                    </button>
                </form>
            </div>
        </section>

        <section class="product-section" id="rooms">
            <h2>Pilihan Kamar</h2>
            <?php if (empty($rooms)): ?>
                <p class="hint">Belum ada pilihan kamar untuk kost ini.</p>
            <?php else: ?>
                <div class="room-grid">
                    <?php foreach ($rooms as $room): ?>
                        <?php $roomPhotoUrl = uploadUrl('rooms', $room['photo']); ?>
                        <article class="room-card">
                            <div class="room-card__photo"<?= $roomPhotoUrl ? ' style="background-image:url(\'' . e($roomPhotoUrl) . '\')"' : '' ?>></div>
                            <div class="room-card__body">
                                <div class="room-card__name"><?= e($room['name']) ?></div>
                                <div class="room-card__meta"><?= e($room['room_type']) ?></div>
                                <?php if (!empty($room['description'])): ?>
                                    <div class="room-card__meta">Desc: <?= e($room['description']) ?></div>
                                <?php endif; ?>
                                <div class="room-card__price"><?= e(formatRupiah($room['price_per_month'])) ?></div>
                                <span class="badge badge-<?= e($room['status']) ?>"><?= e(statusLabel($room['status'])) ?></span>
                            </div>
                            <?php if ($room['status'] === 'available'): ?>
                                <div class="room-card__actions">
                                    <button type="button" class="btn btn-small btn-green"
                                            data-choose-room="<?= (int) $room['id'] ?>">Pilih kamar ini</button>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="product-section" id="booking">
            <h2>Booking</h2>
            <div class="map-grid">
                <?php if ($mapUrl): ?>
                    <iframe class="map-preview" src="<?= e($mapUrl) ?>" title="Peta lokasi kost" loading="lazy"></iframe>
                <?php else: ?>
                    <div class="map-empty">MAPS<br>Lokasi belum diatur</div>
                <?php endif; ?>

                <form class="booking-form" method="post" action="#booking" novalidate>
                    <h3>Form Booking</h3>

                    <?php if (!isLoggedIn()): ?>
                        <p class="hint">Kamu perlu <a href="<?= BASE_URL ?>/auth/login.php">login</a> untuk mengirim booking.</p>
                    <?php endif; ?>
                    <?php if ($house['status'] !== 'available'): ?>
                        <p class="hint">Kost ini sedang penuh dan tidak menerima booking.</p>
                    <?php endif; ?>

                    <?php if (!empty($bookingErrors)): ?>
                        <div class="alert alert-error" role="alert">
                            <?php foreach ($bookingErrors as $error): ?>
                                <p><?= e($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="book">

                    <div class="field">
                        <label for="booker_name">Nama</label>
                        <input type="text" id="booker_name" name="booker_name" maxlength="100"
                               value="<?= e($bookingForm['booker_name']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="contact">Kontak (WhatsApp)</label>
                        <input type="tel" id="contact" name="contact" maxlength="20"
                               value="<?= e($bookingForm['contact']) ?>" placeholder="08123456789" required>
                    </div>
                    <div class="field-row booking-form__date-fields">
                        <div class="field">
                            <label for="check_in_date">Tanggal Masuk</label>
                            <input type="date" id="check_in_date" name="check_in_date" min="<?= date('Y-m-d') ?>"
                                   value="<?= e($bookingForm['check_in_date']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="check_out_date">Tanggal Keluar</label>
                            <input type="date" id="check_out_date" name="check_out_date" min="<?= date('Y-m-d') ?>"
                                   value="<?= e($bookingForm['check_out_date']) ?>" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="room_id">Kamar</label>
                        <select id="room_id" name="room_id" required<?= empty($availableRooms) ? ' disabled' : '' ?>>
                            <option value=""><?= empty($availableRooms) ? 'Tidak ada kamar tersedia' : 'Pilih kamar' ?></option>
                            <?php foreach ($availableRooms as $room): ?>
                                <option value="<?= (int) $room['id'] ?>" data-price="<?= (int) $room['price_per_month'] ?>"<?= $bookingForm['room_id'] === (string) $room['id'] ? ' selected' : '' ?>>
                                    <?= e($room['name']) ?> - <?= e($room['room_type']) ?> (<?= e(formatRupiah($room['price_per_month'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <p class="booking-estimate" id="booking-estimate" aria-live="polite"></p>

                    <button type="submit" class="btn btn-green" style="width:100%"<?= empty($availableRooms) ? ' disabled' : '' ?>>Kirim</button>
                </form>
            </div>
        </section>

        <section class="product-section" id="feedback">
            <h2>Feedback</h2>

            <form class="review-form" method="post" action="#feedback" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="review">

                <?php if (!empty($reviewErrors)): ?>
                    <div class="alert alert-error" role="alert">
                        <?php foreach ($reviewErrors as $error): ?>
                            <p><?= e($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="feedback-top">
                    <div class="feedback-rating">
                        <fieldset class="star-input">
                            <legend class="visually-hidden">Rating Pengalaman Kost Anda</legend>
                            <div class="star-input__stars">
                                <?php for ($star = 5; $star >= 1; $star--): ?>
                                    <input type="radio" id="star<?= $star ?>" name="rating" value="<?= $star ?>"<?= $reviewForm['rating'] === (string) $star ? ' checked' : '' ?>>
                                    <label for="star<?= $star ?>" title="<?= $star ?> bintang"><span class="visually-hidden"><?= $star ?> bintang</span>&#9733;</label>
                                <?php endfor; ?>
                            </div>
                        </fieldset>
                        <span class="rating-text">
                            <?php if ((int) $house['review_count'] > 0): ?>
                                Avg star (<?= e((string) $house['avg_rating']) ?>)
                            <?php else: ?>
                                Belum ada ulasan
                            <?php endif; ?>
                        </span>
                    </div>
                    <p class="feedback-prompt">Rating Pengalaman Kost Anda!!</p>
                </div>

                <div class="field">
                    <label class="visually-hidden" for="comment">Komentar</label>
                    <textarea id="comment" name="comment" rows="4" maxlength="1000"
                              placeholder="Type your feedback here...."><?= e($reviewForm['comment']) ?></textarea>
                </div>
                <div class="feedback-actions">
                    <button type="submit" class="btn btn-green">Send Feedback</button>
                </div>
                <?php if (!isLoggedIn()): ?>
                    <p class="hint">Login dulu untuk mengirim feedback.</p>
                <?php endif; ?>
            </form>

            <?php if (!empty($reviews)): ?>
                <ul class="review-list">
                    <?php foreach ($reviews as $review): ?>
                        <li>
                            <div class="review-list__head">
                                <strong><?= e($review['name'] ?: 'Pengguna') ?></strong>
                                <span class="stars" aria-label="<?= (int) $review['rating'] ?> dari 5 bintang"><?= str_repeat('&#9733;', (int) $review['rating']) . str_repeat('&#9734;', 5 - (int) $review['rating']) ?></span>
                                <small><?= e(formatDate($review['updated_at'])) ?></small>
                            </div>
                            <?php if (!empty($review['comment'])): ?>
                                <p><?= nl2br(e($review['comment'])) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/footer.php'; ?>
