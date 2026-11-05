<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/upload.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $fromHouse  = (int) ($_POST['from_house'] ?? 0);
    $redirectTo = $fromHouse > 0
        ? '/product_desc.php?mode=edit&id=' . $fromHouse
        : '/admin/rooms.php';

    if (!verifyCsrf()) {
        setFlash('error', 'Sesi kedaluwarsa. Coba lagi.');
        redirect($redirectTo);
    }

    $deleteId = (int) ($_POST['id'] ?? 0);

    $stmt = $conn->prepare('SELECT photo, boarding_house_id FROM rooms WHERE id = ?');
    $stmt->execute([$deleteId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('error', 'Data kamar tidak ditemukan.');
        redirect($redirectTo);
    }

    try {
        $stmt = $conn->prepare('DELETE FROM rooms WHERE id = ?');
        $stmt->execute([$deleteId]);

        deleteUploadedFile('rooms', $existing['photo']);
        syncHouseMinPrice($conn, (int) $existing['boarding_house_id']);
        setFlash('success', 'Data kamar berhasil dihapus.');
    } catch (PDOException $exception) {
        // 1451 = a booking still points to this room
        if (databaseErrorCode($exception) === 1451) {
            setFlash('error', 'Kamar tidak bisa dihapus karena sudah punya data booking.');
        } else {
            setFlash('error', 'Terjadi kesalahan. Coba lagi.');
        }
    }

    redirect($redirectTo);
}

$search = trim($_GET['q'] ?? '');

$sql = 'SELECT r.id, r.name, r.room_type, r.price_per_month, r.status, r.photo, b.name AS house_name
        FROM rooms r
        JOIN boarding_houses b ON b.id = r.boarding_house_id';

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare($sql . ' WHERE r.name LIKE ? OR b.name LIKE ? OR r.room_type LIKE ? ORDER BY r.id DESC');
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $conn->prepare($sql . ' ORDER BY r.id DESC');
    $stmt->execute();
}
$rooms = $stmt->fetchAll();

$pageTitle  = 'Data Kamar';
$activeMenu = 'rooms';
require __DIR__ . '/../includes/admin_header.php';
?>
            <h1>Tabel Pengelolaan Data Kamar</h1>

            <div class="toolbar">
                <form method="get" action="">
                    <input type="search" name="q" placeholder="Cari Kamar" value="<?= e($search) ?>" aria-label="Cari kamar">
                    <button type="submit" class="btn btn-outline">Cari</button>
                </form>
                <a class="btn btn-green" href="<?= BASE_URL ?>/admin/room_form.php?mode=add">
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
                            <th>Nama Kamar</th>
                            <th>Nama Kost</th>
                            <th>Tipe Kamar</th>
                            <th>Harga / Bulan</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rooms)): ?>
                            <tr>
                                <td colspan="6" class="empty-row">
                                    <?php if ($search !== ''): ?>
                                        Tidak ada kamar yang cocok dengan "<?= e($search) ?>".
                                    <?php else: ?>
                                        Belum ada data kamar. Klik Add untuk menambahkan kamar pertama.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($rooms as $room): ?>
                            <?php $photoUrl = uploadUrl('rooms', $room['photo']); ?>
                            <tr>
                                <td>
                                    <div class="cell-name">
                                        <span class="thumb"<?= $photoUrl ? ' style="background-image:url(\'' . e($photoUrl) . '\')"' : '' ?>></span>
                                        <?= e($room['name']) ?>
                                    </div>
                                </td>
                                <td><?= e($room['house_name']) ?></td>
                                <td><?= e($room['room_type']) ?></td>
                                <td><?= e(formatRupiah($room['price_per_month'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= e($room['status']) ?>"><?= e(statusLabel($room['status'])) ?></span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-small btn-orange"
                                           href="<?= BASE_URL ?>/admin/room_form.php?mode=edit&amp;id=<?= (int) $room['id'] ?>">Edit</a>

                                        <form method="post" action=""
                                              onsubmit="return confirm('Hapus kamar &quot;<?= e(addslashes($room['name'])) ?>&quot;?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
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
