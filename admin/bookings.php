<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi kedaluwarsa. Coba lagi.');
        redirect('/admin/bookings.php');
    }

    $deleted = executePrepared($conn, 'DELETE FROM bookings WHERE id = ?', 'i', [(int) ($_POST['id'] ?? 0)]);
    setFlash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Data booking berhasil dihapus.' : 'Data booking tidak ditemukan.');
    redirect('/admin/bookings.php');
}

$search = trim($_GET['q'] ?? '');

$sql = 'SELECT d.id, d.user_id, d.booker_name, d.boarding_house_name, d.room_name, d.contact,
               d.check_in_date, d.check_out_date, d.total_price, d.status,
               u.profile_photo
        FROM v_booking_detail d
        LEFT JOIN users u ON u.id = d.user_id';

if ($search !== '') {
    $like     = '%' . $search . '%';
    $bookings = fetchAllPrepared(
        $conn,
        $sql . ' WHERE d.booker_name LIKE ? OR d.boarding_house_name LIKE ? OR d.contact LIKE ? OR d.room_name LIKE ?
                 ORDER BY d.id DESC',
        'ssss',
        [$like, $like, $like, $like]
    );
} else {
    $bookings = fetchAllPrepared($conn, $sql . ' ORDER BY d.id DESC');
}

$pageTitle  = 'Data Booking';
$activeMenu = 'bookings';
require __DIR__ . '/../includes/admin_header.php';
?>
            <h1>Tabel Pengelolaan Data Booking</h1>

            <div class="toolbar">
                <form method="get" action="">
                    <input type="search" name="q" placeholder="Cari Booking" value="<?= e($search) ?>" aria-label="Cari booking">
                    <button type="submit" class="btn btn-outline">Cari</button>
                </form>
                <a class="btn btn-green" href="<?= BASE_URL ?>/admin/booking_form.php?mode=add">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 8v8M8 12h8"></path>
                    </svg>
                    Add
                </a>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama Pemesan</th>
                            <th>Nama Kost</th>
                            <th>Kontak</th>
                            <th>Tanggal Masuk</th>
                            <th>Tanggal Keluar</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" class="empty-row">
                                    <?php if ($search !== ''): ?>
                                        Tidak ada booking yang cocok dengan "<?= e($search) ?>".
                                    <?php else: ?>
                                        Belum ada data booking.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <div class="cell-name booking-person">
                                        <?php if (uploadFileExists('profiles', $booking['profile_photo'])): ?>
                                            <img class="booking-person__photo"
                                                 src="<?= e(uploadUrl('profiles', $booking['profile_photo'])) ?>"
                                                 alt="" aria-hidden="true">
                                        <?php else: ?>
                                            <span class="booking-person__photo booking-person__photo--empty" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"></path>
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                        <span><?= e($booking['booker_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?= e($booking['boarding_house_name']) ?><br>
                                    <small><?= e($booking['room_name']) ?></small>
                                </td>
                                <td><?= e($booking['contact']) ?></td>
                                <td><?= e(formatDate($booking['check_in_date'])) ?></td>
                                <td><?= e(formatDate($booking['check_out_date'])) ?></td>
                                <td><?= e(formatRupiah($booking['total_price'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= e($booking['status']) ?>"><?= e(statusLabel($booking['status'])) ?></span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-small btn-orange"
                                           href="<?= BASE_URL ?>/admin/booking_form.php?mode=edit&amp;id=<?= (int) $booking['id'] ?>">Edit</a>

                                        <form method="post" action=""
                                              onsubmit="return confirm('Hapus booking dari &quot;<?= e(addslashes($booking['booker_name'])) ?>&quot;?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
                                            <button type="submit" class="btn btn-small btn-red">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
