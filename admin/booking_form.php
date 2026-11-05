<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$mode = $_GET['mode'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);

if (!in_array($mode, ['add', 'edit'], true)) {
    redirect('/admin/bookings.php');
}

$statuses = ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'];

// All rooms (with their kost) for the dropdown
$rooms   = fetchAllPrepared(
    $conn,
    'SELECT r.id, r.name, r.price_per_month, b.name AS house_name
     FROM rooms r JOIN boarding_houses b ON b.id = r.boarding_house_id
     ORDER BY b.name, r.name'
);
$roomIds = array_map('intval', array_column($rooms, 'id'));

$form = [
    'room_id'          => '',
    'booker_name'      => '',
    'contact'          => '',
    'check_in_date'    => '',
    'check_out_date'   => '',
    'price_at_booking' => '',
    'status'           => 'pending',
];

if ($mode === 'edit') {
    $rows = fetchAllPrepared($conn, 'SELECT * FROM bookings WHERE id = ?', 'i', [$id]);
    if (empty($rows)) {
        setFlash('error', 'Data booking tidak ditemukan.');
        redirect('/admin/bookings.php');
    }
    foreach ($form as $field => $default) {
        $form[$field] = (string) $rows[0][$field];
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

    $roomId = (int) $form['room_id'];
    if (!in_array($roomId, $roomIds, true)) {
        $errors[] = 'Pilih kamar.';
    }
    if ($form['booker_name'] === '' || mb_strlen($form['booker_name']) > 100) {
        $errors[] = 'Nama pemesan wajib diisi (maksimal 100 karakter).';
    }

    $contact = normalizePhone($form['contact']);
    if (strlen($contact) < 9 || strlen($contact) > 15) {
        $errors[] = 'Kontak harus berisi 9 sampai 15 digit.';
    }

    $datesOk = true;
    if (!isValidDate($form['check_in_date'])) {
        $errors[] = 'Tanggal masuk tidak valid.';
        $datesOk = false;
    }
    if (!isValidDate($form['check_out_date'])) {
        $errors[] = 'Tanggal keluar tidak valid.';
        $datesOk = false;
    }
    if ($datesOk && $form['check_out_date'] <= $form['check_in_date']) {
        $errors[] = 'Tanggal keluar harus setelah tanggal masuk.';
        $datesOk = false;
    }

    if (!in_array($form['status'], $statuses, true)) {
        $errors[] = 'Status tidak valid.';
    }

    // Price: empty = use the price of the chosen room
    $price = null;
    if ($form['price_at_booking'] === '') {
        foreach ($rooms as $room) {
            if ((int) $room['id'] === $roomId) {
                $price = (int) $room['price_per_month'];
            }
        }
    } else {
        $price = filter_var($form['price_at_booking'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 4294967295],
        ]);
    }
    if ($price === false || $price === null) {
        $errors[] = 'Harga harus berupa angka bulat (tanpa titik atau koma).';
    }

    // Total = price per month x number of months (a started month counts as a full month)
    $totalPrice = 0;
    if ($datesOk && $price !== false && $price !== null) {
        $totalPrice = calculateTotalPrice($price, $form['check_in_date'], $form['check_out_date']);
        if ($totalPrice > 4294967295) {
            $errors[] = 'Total harga terlalu besar. Periksa harga dan rentang tanggal.';
        }
    }

    if (empty($errors)) {
        try {
            $saved = executeBookingWithRoomLock($conn, $roomId, function (array $lockedRoom) use (
                $conn, $roomId, $form, $contact, $price, $totalPrice, $mode, $id, &$errors
            ): bool {
                if (in_array($form['status'], ['pending', 'confirmed'], true)) {
                    if ($lockedRoom['status'] !== 'available' || $lockedRoom['house_status'] !== 'available') {
                        $errors[] = 'Booking aktif hanya bisa dibuat untuk kost dan kamar yang tersedia.';
                        return false;
                    }

                    if (!isRoomAvailable(
                        $conn,
                        $roomId,
                        $form['check_in_date'],
                        $form['check_out_date'],
                        $mode === 'edit' ? $id : 0
                    )) {
                        $errors[] = 'Kamar ini sudah dipesan pada tanggal tersebut (booking lain yang masih aktif).';
                        return false;
                    }
                }

                if ($mode === 'add') {
                    // user_id stays empty: this booking was typed in by the admin
                    executePrepared(
                        $conn,
                        'INSERT INTO bookings (room_id, booker_name, contact, check_in_date, check_out_date, price_at_booking, total_price, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        'issssiis',
                        [$roomId, $form['booker_name'], $contact, $form['check_in_date'], $form['check_out_date'], $price, $totalPrice, $form['status']]
                    );
                } else {
                    executePrepared(
                        $conn,
                        'UPDATE bookings
                         SET room_id = ?, booker_name = ?, contact = ?, check_in_date = ?, check_out_date = ?,
                             price_at_booking = ?, total_price = ?, status = ?
                         WHERE id = ?',
                        'issssiisi',
                        [$roomId, $form['booker_name'], $contact, $form['check_in_date'], $form['check_out_date'], $price, $totalPrice, $form['status'], $id]
                    );
                }

                return true;
            });

            if ($saved) {
                setFlash('success', $mode === 'add' ? 'Booking berhasil ditambahkan.' : 'Perubahan booking berhasil disimpan.');
                redirect('/admin/bookings.php');
            }
            if (empty($errors)) {
                $errors[] = 'Kamar tidak ditemukan. Pilih kamar lain.';
            }
        } catch (PDOException $exception) {
            $errors[] = 'Data gagal disimpan. Coba lagi.';
        }
    }
}

$pageTitle  = $mode === 'add' ? 'Tambah Booking' : 'Edit Booking';
$activeMenu = 'bookings';
require __DIR__ . '/../includes/admin_header.php';
?>
            <h1><?= $mode === 'add' ? 'Tambah Booking' : 'Edit Booking' ?></h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($rooms)): ?>
                <div class="alert alert-error" role="alert">
                    Belum ada data kamar. Tambahkan kamar dulu sebelum membuat booking.
                    <a href="<?= BASE_URL ?>/admin/room_form.php?mode=add">Tambah kamar</a>
                </div>
            <?php else: ?>
            <div class="form-card">
                <form method="post" action="" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                    <div class="field">
                        <label for="room_id">Kamar</label>
                        <select id="room_id" name="room_id" required>
                            <option value="">Pilih kamar</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?= (int) $room['id'] ?>"<?= (int) $form['room_id'] === (int) $room['id'] ? ' selected' : '' ?>>
                                    <?= e($room['house_name']) ?> - <?= e($room['name']) ?> (<?= e(formatRupiah($room['price_per_month'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="booker_name">Nama pemesan</label>
                            <input type="text" id="booker_name" name="booker_name" maxlength="100"
                                   value="<?= e($form['booker_name']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="contact">Kontak (WhatsApp)</label>
                            <input type="tel" id="contact" name="contact" maxlength="20"
                                   value="<?= e($form['contact']) ?>" placeholder="08123456789" required>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="check_in_date">Tanggal masuk</label>
                            <input type="date" id="check_in_date" name="check_in_date"
                                   value="<?= e($form['check_in_date']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="check_out_date">Tanggal keluar</label>
                            <input type="date" id="check_out_date" name="check_out_date"
                                   value="<?= e($form['check_out_date']) ?>" required>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="price_at_booking">Harga / bulan (Rp)</label>
                            <input type="number" id="price_at_booking" name="price_at_booking" min="0" step="1"
                                   value="<?= e($form['price_at_booking']) ?>">
                            <p class="hint">Kosongkan untuk memakai harga kamar yang dipilih. Total harga dihitung otomatis (harga x jumlah bulan).</p>
                        </div>
                        <div class="field">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <?php foreach ($statuses as $statusOption): ?>
                                    <option value="<?= e($statusOption) ?>"<?= $form['status'] === $statusOption ? ' selected' : '' ?>>
                                        <?= e(statusLabel($statusOption)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">Simpan</button>
                        <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/bookings.php">Batal</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
