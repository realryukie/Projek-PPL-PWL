<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/upload.php';
require_once __DIR__ . '/includes/gallery.php';

// This page has two faces:
//   product_desc.php?id=5               -> visitor view (detail, rooms, booking, feedback, favorite)
//   product_desc.php?mode=add           -> admin: empty page to fill in a new kost
//   product_desc.php?mode=edit&id=5     -> admin: same page filled with the kost data
$mode = $_GET['mode'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);

if (!in_array($mode, ['add', 'edit'], true)) {
    require __DIR__ . '/includes/product_view.php';
    exit;
}

requireAdmin(); // everything below is admin only

// ------------------------------------------------------------
// Load the existing record (edit mode)
// ------------------------------------------------------------
$savedPhoto    = null; // cover photo currently stored in the database
$savedHouse    = null;
$galleryImages = []; // extra photos (table boarding_house_images)

$form = [
    'name'            => '',
    'address'         => '',
    'city'            => '',
    'type'            => '',
    'description'     => '',
    'price_per_month' => '',
    'total_rooms'     => '',
    'contact'         => '',
    'status'          => 'available',
    'latitude'        => '',
    'longitude'       => '',
];

if ($mode === 'edit') {
    $stmt = $conn->prepare('SELECT * FROM boarding_houses WHERE id = ?');
    $stmt->execute([$id]);
    $savedHouse = $stmt->fetch();

    if (!$savedHouse) {
        setFlash('error', 'Data kost tidak ditemukan.');
        redirect('/admin/boarding_houses.php');
    }

    $savedPhoto    = $savedHouse['photo'];
    $galleryImages = fetchAllPrepared(
        $conn,
        'SELECT id, image, sort_order FROM boarding_house_images WHERE boarding_house_id = ? ORDER BY sort_order, id',
        'i',
        [$id]
    );
    foreach ($form as $field => $default) {
        $value = $savedHouse[$field] ?? '';
        $form[$field] = in_array($field, ['latitude', 'longitude'], true)
            ? trimDecimal($value)
            : (string) $value;
    }
}

// ------------------------------------------------------------
// Save (form POST)
// ------------------------------------------------------------
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $field => $default) {
        $form[$field] = trim($_POST[$field] ?? '');
    }
    $form['city'] = mb_convert_case($form['city'], MB_CASE_TITLE, 'UTF-8');

    if (!verifyCsrf()) {
        $errors[] = 'Sesi kedaluwarsa. Coba lagi.';
    }

    // --- validate the text fields ---
    if ($form['name'] === '' || mb_strlen($form['name']) > 150) {
        $errors[] = 'Nama kost wajib diisi (maksimal 150 karakter).';
    }
    if ($form['address'] === '' || mb_strlen($form['address']) > 255) {
        $errors[] = 'Alamat wajib diisi (maksimal 255 karakter).';
    }
    if ($form['city'] === '' || mb_strlen($form['city']) > 100) {
        $errors[] = 'Kota / daerah wajib diisi (maksimal 100 karakter).';
    }
    if (!in_array($form['type'], ['male', 'female', 'mixed', 'house'], true)) {
        $errors[] = 'Pilih tipe hunian.';
    }
    if (!in_array($form['status'], ['available', 'full'], true)) {
        $errors[] = 'Status tidak valid.';
    }

    $price = filter_var($form['price_per_month'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 4294967295],
    ]);
    if ($price === false) {
        $errors[] = 'Harga per bulan harus berupa angka bulat (tanpa titik atau koma).';
    }

    $totalRooms = filter_var($form['total_rooms'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 1000],
    ]);
    if ($totalRooms === false) {
        $errors[] = 'Jumlah kamar harus berupa angka bulat antara 1 dan 1000.';
    }

    // Contact is stored as digits with country code, e.g. 6281234567890
    $contact = normalizePhone($form['contact']);
    if (strlen($contact) < 9 || strlen($contact) > 15) {
        $errors[] = 'Kontak WhatsApp harus berisi 9 sampai 15 digit.';
    }

    // Coordinates: both filled or both empty
    $latitude  = null;
    $longitude = null;
    if ($form['latitude'] !== '' || $form['longitude'] !== '') {
        if (
            !is_numeric($form['latitude']) || !is_numeric($form['longitude'])
            || (float) $form['latitude'] < -90 || (float) $form['latitude'] > 90
            || (float) $form['longitude'] < -180 || (float) $form['longitude'] > 180
        ) {
            $errors[] = 'Isi latitude (-90 sampai 90) dan longitude (-180 sampai 180) dengan angka, atau kosongkan keduanya.';
        } else {
            $latitude  = number_format((float) $form['latitude'], 8, '.', '');
            $longitude = number_format((float) $form['longitude'], 8, '.', '');
        }
    }

    // --- photo (optional) ---
    $newPhoto = null;
    $photoFile = $_FILES['photo'] ?? null;
    if (empty($errors) && $photoFile && $photoFile['error'] !== UPLOAD_ERR_NO_FILE) {
        $newPhoto = saveUploadedImage($photoFile, 'boarding_houses', $errors);
    }

    // --- gallery photos (optional, several at once) ---
    $deleteImageIds = array_map('intval', (array) ($_POST['delete_images'] ?? []));
    $newGallery     = [];
    if (empty($errors)) {
        $incomingFiles = collectGalleryUploads($_FILES['gallery'] ?? null);

        $keptCount = 0;
        foreach ($galleryImages as $image) {
            if (!in_array((int) $image['id'], $deleteImageIds, true)) {
                $keptCount++;
            }
        }

        if ($keptCount + count($incomingFiles) > MAX_GALLERY_IMAGES) {
            $errors[] = 'Galeri maksimal ' . MAX_GALLERY_IMAGES . ' foto.';
        } else {
            foreach ($incomingFiles as $incoming) {
                $savedName = saveUploadedImage($incoming, 'boarding_houses', $errors);
                if ($savedName !== null) {
                    $newGallery[] = $savedName;
                }
            }
        }
    }

    // Something failed: do not leave freshly uploaded files behind
    if (!empty($errors)) {
        foreach ($newGallery as $fileName) {
            deleteUploadedFile('boarding_houses', $fileName);
        }
        $newGallery = [];
        deleteUploadedFile('boarding_houses', $newPhoto);
        $newPhoto = null;
    }

    // --- write to the database ---
    if (empty($errors)) {
        $description = $form['description'] === '' ? null : $form['description'];

        try {
            if ($mode === 'add') {
                $stmt = $conn->prepare(
                    'INSERT INTO boarding_houses
                        (name, address, city, description, type, price_per_month, total_rooms, contact, status, latitude, longitude, photo)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $form['name'], $form['address'], $form['city'], $description, $form['type'],
                    $price, $totalRooms, $contact, $form['status'], $latitude, $longitude, $newPhoto,
                ]);
                $newId = (int) $conn->lastInsertId();

                applyGalleryChanges($conn, $newId, [], $newGallery);

                setFlash('success', 'Kost berhasil ditambahkan. Kamu bisa mengubah datanya di halaman ini.');
                redirect('/product_desc.php?mode=edit&id=' . $newId);
            }

            // edit: keep the old photo unless a new one was uploaded
            $photoToSave = $newPhoto ?? $savedPhoto;
            $stmt = $conn->prepare(
                'UPDATE boarding_houses
                 SET name = ?, address = ?, city = ?, description = ?, type = ?, price_per_month = ?,
                     total_rooms = ?, contact = ?, status = ?, latitude = ?, longitude = ?, photo = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $form['name'], $form['address'], $form['city'], $description, $form['type'],
                $price, $totalRooms, $contact, $form['status'], $latitude, $longitude, $photoToSave, $id,
            ]);

            if ($newPhoto !== null) {
                deleteUploadedFile('boarding_houses', $savedPhoto);
            }

            applyGalleryChanges($conn, $id, $deleteImageIds, $newGallery);

            setFlash('success', 'Perubahan berhasil disimpan.');
            redirect('/product_desc.php?mode=edit&id=' . $id);
        } catch (PDOException $exception) {
            // The row was not saved, so do not leave the new photo behind
            deleteUploadedFile('boarding_houses', $newPhoto);
            foreach ($newGallery as $fileName) {
                deleteUploadedFile('boarding_houses', $fileName);
            }
            $errors[] = 'Data gagal disimpan. Coba lagi.';
        }
    }
}

// ------------------------------------------------------------
// Values used by the page
// ------------------------------------------------------------
$photoUrl = uploadUrl('boarding_houses', $savedPhoto);

// Room options of this kost (only in edit mode, the kost must be saved first)
$rooms = [];
if ($mode === 'edit') {
    $stmt = $conn->prepare(
        'SELECT id, name, room_type, description, price_per_month, status, photo
         FROM rooms
         WHERE boarding_house_id = ?
         ORDER BY price_per_month, id'
    );
    $stmt->execute([$id]);
    $rooms = $stmt->fetchAll();
}

// Map preview uses the coordinates already saved in the database
$mapUrl = null;
if ($savedHouse && $savedHouse['latitude'] !== null && $savedHouse['longitude'] !== null) {
    $lat = (float) $savedHouse['latitude'];
    $lng = (float) $savedHouse['longitude'];
    $mapUrl = sprintf(
        'https://www.openstreetmap.org/export/embed.html?bbox=%.6F%%2C%.6F%%2C%.6F%%2C%.6F&layer=mapnik&marker=%.6F%%2C%.6F',
        $lng - 0.006, $lat - 0.004, $lng + 0.006, $lat + 0.004, $lat, $lng
    );
}

$pageTitle = ($mode === 'add' ? 'Tambah Kost' : 'Edit Kost') . ' - Kostku';
$activeMenu = 'boarding_houses';
$extraCss  = ['product_desc.css'];
$extraJs   = ['product_desc.js'];
require __DIR__ . '/includes/admin_header.php';
?>
    <div class="product-page">
    <h1><?= $mode === 'add' ? 'Tambah Kost' : 'Edit Kost' ?></h1>

    <div class="product">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <section class="product-top">
                <div>
                    <div id="photo-preview"
                         class="photo-box<?= $photoUrl ? ' has-image' : '' ?>"
                         <?= $photoUrl ? 'style="background-image:url(\'' . e($photoUrl) . '\')"' : '' ?>>
                        <span class="photo-box__empty">Belum ada foto</span>
                    </div>

                    <input class="visually-hidden" type="file" id="photo" name="photo"
                           accept="image/jpeg,image/png,image/webp">
                    <label class="photo-button" for="photo">
                        <?= $photoUrl ? 'Ganti foto' : 'Pilih foto' ?>
                    </label>
                    <p class="hint">JPG, PNG, atau WebP. Maksimal 5 MB.</p>
                </div>

                <div>
                    <div class="field">
                        <label for="name">Nama kost</label>
                        <input type="text" id="name" name="name" maxlength="150"
                               value="<?= e($form['name']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="address">Alamat</label>
                        <input type="text" id="address" name="address" maxlength="255"
                               value="<?= e($form['address']) ?>" required>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="city">Kota / Daerah</label>
                            <input type="text" id="city" name="city" maxlength="100"
                                   value="<?= e($form['city']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="type">Tipe hunian</label>
                            <select id="type" name="type" required>
                                <option value="">Pilih tipe</option>
                                <?php foreach (['male', 'female', 'mixed', 'house'] as $typeOption): ?>
                                    <option value="<?= e($typeOption) ?>"<?= $form['type'] === $typeOption ? ' selected' : '' ?>>
                                        <?= e(typeLabel($typeOption)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label for="description">Deskripsi</label>
                        <textarea id="description" name="description"><?= e($form['description']) ?></textarea>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="price_per_month">Harga / bulan (Rp)</label>
                            <input type="number" id="price_per_month" name="price_per_month" min="0" step="1"
                                   value="<?= e($form['price_per_month']) ?>" required>
                            <p class="hint">Isi dengan harga mulai dari (kamar termurah).</p>
                        </div>
                        <div class="field">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <?php foreach (['available', 'full'] as $statusOption): ?>
                                    <option value="<?= e($statusOption) ?>"<?= $form['status'] === $statusOption ? ' selected' : '' ?>>
                                        <?= e(statusLabel($statusOption)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="total_rooms">Jumlah kamar</label>
                            <input type="number" id="total_rooms" name="total_rooms" min="1" max="1000" step="1"
                                   value="<?= e($form['total_rooms']) ?>" required>
                            <p class="hint">Total kamar di kost ini. Beda dengan tipe kamar.</p>
                        </div>
                        <div class="field">
                            <label for="contact">Kontak (WhatsApp)</label>
                            <input type="tel" id="contact" name="contact" maxlength="20"
                                   value="<?= e($form['contact']) ?>" placeholder="08123456789" required>
                            <p class="hint">Nomor 08... otomatis disimpan sebagai 628...</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="product-section">
                <h2>Galeri Foto</h2>
                <p class="hint">Foto tambahan untuk slider di halaman detail (maksimal <?= MAX_GALLERY_IMAGES ?> foto, masing-masing 5 MB). Foto utama di atas dipakai sebagai gambar sampul.</p>

                <?php if (!empty($galleryImages)): ?>
                    <div class="gallery-admin">
                        <?php foreach ($galleryImages as $image): ?>
                            <?php $imageUrl = uploadFileExists('boarding_houses', $image['image']) ? uploadUrl('boarding_houses', $image['image']) : null; ?>
                            <div class="gallery-admin__item">
                                <span class="gallery-admin__thumb"<?= $imageUrl ? ' style="background-image:url(\'' . e($imageUrl) . '\')"' : '' ?>></span>
                                <label class="gallery-admin__check">
                                    <input type="checkbox" name="delete_images[]" value="<?= (int) $image['id'] ?>"> Hapus
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="gallery">Tambah foto galeri</label>
                    <input type="file" id="gallery" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp">
                </div>
            </section>

            <section class="product-section">
                <div class="section-head">
                    <h2>Pilihan Kamar</h2>
                    <?php if ($mode === 'edit'): ?>
                        <a class="btn btn-green btn-small"
                           href="<?= BASE_URL ?>/admin/room_form.php?mode=add&amp;house_id=<?= (int) $id ?>&amp;from=house">Tambah Kamar</a>
                    <?php endif; ?>
                </div>

                <?php if ($mode === 'add'): ?>
                    <p class="hint">Simpan data kost dulu, lalu pilihan kamar bisa ditambahkan di bagian ini.</p>
                <?php elseif (empty($rooms)): ?>
                    <p class="hint">Belum ada pilihan kamar. Klik Tambah Kamar untuk membuat yang pertama.</p>
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
                                <div class="room-card__actions">
                                    <a class="btn btn-small btn-orange"
                                       href="<?= BASE_URL ?>/admin/room_form.php?mode=edit&amp;id=<?= (int) $room['id'] ?>&amp;from=house">Edit</a>
                                    <button type="submit" form="delete-room-<?= (int) $room['id'] ?>"
                                            class="btn btn-small btn-red">Hapus</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="product-section">
                <h2>Lokasi di peta</h2>
                <div class="map-grid">
                    <div>
                        <div class="field-row">
                            <div class="field">
                                <label for="latitude">Latitude</label>
                                <input type="text" id="latitude" name="latitude" inputmode="decimal"
                                       value="<?= e($form['latitude']) ?>" placeholder="-0.0263">
                            </div>
                            <div class="field">
                                <label for="longitude">Longitude</label>
                                <input type="text" id="longitude" name="longitude" inputmode="decimal"
                                       value="<?= e($form['longitude']) ?>" placeholder="109.3425">
                            </div>
                        </div>
                        <p class="hint">Cara dapat koordinat: buka Google Maps, klik kanan di lokasi kost, lalu klik angka koordinatnya untuk menyalin. Boleh dikosongkan.</p>
                    </div>

                    <?php if ($mapUrl): ?>
                        <iframe class="map-preview" src="<?= e($mapUrl) ?>" title="Peta lokasi kost" loading="lazy"></iframe>
                    <?php else: ?>
                        <div class="map-empty">Peta muncul di sini setelah koordinat disimpan.</div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="form-actions">
                <button type="submit" class="btn btn-green">Simpan</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>/admin/boarding_houses.php">Batal</a>
            </div>
        </form>

        <?php /* Delete forms live outside the main form (forms cannot be nested).
                 The Hapus buttons above point to them with the "form" attribute. */ ?>
        <?php foreach ($rooms as $room): ?>
            <form id="delete-room-<?= (int) $room['id'] ?>" method="post" action="<?= BASE_URL ?>/admin/rooms.php"
                  onsubmit="return confirm('Hapus kamar &quot;<?= e(addslashes($room['name'])) ?>&quot;?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
                <input type="hidden" name="from_house" value="<?= (int) $id ?>">
            </form>
        <?php endforeach; ?>
    </div>
    </div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
