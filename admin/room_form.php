<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/upload.php';

requireAdmin();

$mode = $_GET['mode'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);

if (!in_array($mode, ['add', 'edit'], true)) {
    redirect('/admin/rooms.php');
}

// "from=house" means the admin came from the Pilihan Kamar section of a kost edit page
$fromHouse = ($_GET['from'] ?? $_POST['from'] ?? '') === 'house';

// ------------------------------------------------------------
// Boarding houses for the dropdown
// ------------------------------------------------------------
$houses   = fetchAllPrepared($conn, 'SELECT id, name, city FROM boarding_houses ORDER BY name');
$houseIds = array_map('intval', array_column($houses, 'id'));

// ------------------------------------------------------------
// Load the existing room (edit) or the preset kost (add)
// ------------------------------------------------------------
$form = [
    'boarding_house_id' => '',
    'name'              => '',
    'room_type'         => '',
    'description'       => '',
    'price_per_month'   => '',
    'status'            => 'available',
];
$savedRoom  = null;
$savedPhoto = null;

if ($mode === 'edit') {
    $stmt = $conn->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$id]);
    $savedRoom = $stmt->fetch();

    if (!$savedRoom) {
        setFlash('error', 'Data kamar tidak ditemukan.');
        redirect('/admin/rooms.php');
    }

    $savedPhoto = $savedRoom['photo'];
    foreach ($form as $field => $default) {
        $form[$field] = (string) ($savedRoom[$field] ?? '');
    }
} else {
    $presetHouse = (int) ($_GET['house_id'] ?? 0);
    if ($presetHouse > 0) {
        $form['boarding_house_id'] = (string) $presetHouse;
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

    if (!verifyCsrf()) {
        $errors[] = 'Sesi kedaluwarsa. Coba lagi.';
    }

    $houseId = (int) $form['boarding_house_id'];
    if (!in_array($houseId, $houseIds, true)) {
        $errors[] = 'Pilih kost untuk kamar ini.';
    }
    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors[] = 'Nama kamar wajib diisi (maksimal 100 karakter).';
    }
    if ($form['room_type'] === '' || mb_strlen($form['room_type']) > 50) {
        $errors[] = 'Tipe kamar wajib diisi (maksimal 50 karakter).';
    }
    if (!in_array($form['status'], ['available', 'occupied'], true)) {
        $errors[] = 'Status tidak valid.';
    }

    $price = filter_var($form['price_per_month'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 4294967295],
    ]);
    if ($price === false) {
        $errors[] = 'Harga per bulan harus berupa angka bulat (tanpa titik atau koma).';
    }

    // --- photo (optional) ---
    $newPhoto  = null;
    $photoFile = $_FILES['photo'] ?? null;
    if (empty($errors) && $photoFile && $photoFile['error'] !== UPLOAD_ERR_NO_FILE) {
        $newPhoto = saveUploadedImage($photoFile, 'rooms', $errors);
    }

    // --- write to the database ---
    if (empty($errors)) {
        $description = $form['description'] === '' ? null : $form['description'];

        try {
            if ($mode === 'add') {
                $stmt = $conn->prepare(
                    'INSERT INTO rooms (boarding_house_id, name, room_type, description, price_per_month, status, photo)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $houseId, $form['name'], $form['room_type'], $description, $price, $form['status'], $newPhoto,
                ]);

                setFlash('success', 'Kamar berhasil ditambahkan.');
            } else {
                $photoToSave = $newPhoto ?? $savedPhoto;
                $stmt = $conn->prepare(
                    'UPDATE rooms
                     SET boarding_house_id = ?, name = ?, room_type = ?, description = ?,
                         price_per_month = ?, status = ?, photo = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $houseId, $form['name'], $form['room_type'], $description, $price, $form['status'], $photoToSave, $id,
                ]);

                if ($newPhoto !== null) {
                    deleteUploadedFile('rooms', $savedPhoto);
                }

                // The room moved to another kost: refresh the old kost too
                $oldHouseId = (int) $savedRoom['boarding_house_id'];
                if ($oldHouseId !== $houseId) {
                    syncHouseMinPrice($conn, $oldHouseId);
                }

                setFlash('success', 'Perubahan kamar berhasil disimpan.');
            }

            // Kost starting price follows the cheapest room
            syncHouseMinPrice($conn, $houseId);

            redirect($fromHouse
                ? '/product_desc.php?mode=edit&id=' . $houseId
                : '/admin/rooms.php');
        } catch (PDOException $exception) {
            deleteUploadedFile('rooms', $newPhoto);
            $errors[] = 'Data gagal disimpan. Coba lagi.';
        }
    }
}

// ------------------------------------------------------------
// Values used by the page
// ------------------------------------------------------------
$photoUrl = uploadUrl('rooms', $savedPhoto);

$backHouseId = (int) $form['boarding_house_id'];
$backUrl = ($fromHouse && $backHouseId > 0)
    ? BASE_URL . '/product_desc.php?mode=edit&id=' . $backHouseId
    : BASE_URL . '/admin/rooms.php';

$pageTitle  = $mode === 'add' ? 'Tambah Kamar' : 'Edit Kamar';
$activeMenu = 'rooms';
$extraJs    = ['product_desc.js']; // photo preview
require __DIR__ . '/../includes/admin_header.php';
?>
            <h1><?= $mode === 'add' ? 'Tambah Kamar' : 'Edit Kamar' ?></h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($houses)): ?>
                <div class="alert alert-error" role="alert">
                    Belum ada data kost. Tambahkan kost dulu sebelum menambah kamar.
                    <a href="<?= BASE_URL ?>/product_desc.php?mode=add">Tambah kost</a>
                </div>
            <?php else: ?>
            <div class="form-card">
                <form method="post" action="" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="from" value="<?= $fromHouse ? 'house' : '' ?>">

                    <div class="form-grid">
                        <div>
                            <div id="photo-preview"
                                 class="photo-box<?= $photoUrl ? ' has-image' : '' ?>"
                                 <?= $photoUrl ? 'style="background-image:url(\'' . e($photoUrl) . '\')"' : '' ?>>
                                <span class="photo-box__empty">Belum ada foto</span>
                            </div>
                            <input class="visually-hidden" type="file" id="photo" name="photo"
                                   accept="image/jpeg,image/png,image/webp">
                            <label class="photo-button" for="photo"><?= $photoUrl ? 'Ganti foto' : 'Pilih foto' ?></label>
                            <p class="hint">JPG, PNG, atau WebP. Maksimal 5 MB.</p>
                        </div>

                        <div>
                            <div class="field">
                                <label for="boarding_house_id">Kost</label>
                                <select id="boarding_house_id" name="boarding_house_id" required>
                                    <option value="">Pilih kost</option>
                                    <?php foreach ($houses as $house): ?>
                                        <option value="<?= (int) $house['id'] ?>"<?= (int) $form['boarding_house_id'] === (int) $house['id'] ? ' selected' : '' ?>>
                                            <?= e($house['name']) ?> (<?= e($house['city']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field-row">
                                <div class="field">
                                    <label for="name">Nama kamar</label>
                                    <input type="text" id="name" name="name" maxlength="100"
                                           value="<?= e($form['name']) ?>" placeholder="Pilihan 1" required>
                                </div>
                                <div class="field">
                                    <label for="room_type">Tipe kamar</label>
                                    <input type="text" id="room_type" name="room_type" maxlength="50"
                                           value="<?= e($form['room_type']) ?>" placeholder="Standard / Deluxe / VIP" required>
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
                                </div>
                                <div class="field">
                                    <label for="status">Status</label>
                                    <select id="status" name="status">
                                        <?php foreach (['available', 'occupied'] as $statusOption): ?>
                                            <option value="<?= e($statusOption) ?>"<?= $form['status'] === $statusOption ? ' selected' : '' ?>>
                                                <?= e(statusLabel($statusOption)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">Simpan</button>
                        <a class="btn btn-outline" href="<?= e($backUrl) ?>">Batal</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
