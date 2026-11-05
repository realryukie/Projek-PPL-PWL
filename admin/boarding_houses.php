<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/upload.php';

requireAdmin();

// ------------------------------------------------------------
// Delete (form POST from the Delete button)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrf()) {
        setFlash('error', 'Sesi kedaluwarsa. Coba lagi.');
        redirect('/admin/boarding_houses.php');
    }

    $deleteId = (int) ($_POST['id'] ?? 0);

    // Remember the photo name first so the file can be removed after the row is gone
    $stmt = $conn->prepare('SELECT photo FROM boarding_houses WHERE id = ?');
    $stmt->execute([$deleteId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('error', 'Data kost tidak ditemukan.');
        redirect('/admin/boarding_houses.php');
    }

    // Rows of the gallery and the rooms are deleted by the database (CASCADE),
    // so remember their photo files first and remove the files afterwards
    $galleryRows = fetchAllPrepared($conn, 'SELECT image FROM boarding_house_images WHERE boarding_house_id = ?', 'i', [$deleteId]);
    $roomRows    = fetchAllPrepared($conn, 'SELECT photo FROM rooms WHERE boarding_house_id = ?', 'i', [$deleteId]);

    try {
        $stmt = $conn->prepare('DELETE FROM boarding_houses WHERE id = ?');
        $stmt->execute([$deleteId]);

        deleteUploadedFile('boarding_houses', $existing['photo']);
        foreach ($galleryRows as $row) {
            deleteUploadedFile('boarding_houses', $row['image']);
        }
        foreach ($roomRows as $row) {
            deleteUploadedFile('rooms', $row['photo']);
        }
        setFlash('success', 'Data kost berhasil dihapus.');
    } catch (PDOException $exception) {
        // 1451 = a foreign key blocks the delete (a room of this kost already has bookings)
        if (databaseErrorCode($exception) === 1451) {
            setFlash('error', 'Kost tidak bisa dihapus karena kamarnya sudah punya data booking.');
        } else {
            setFlash('error', 'Terjadi kesalahan. Coba lagi.');
        }
    }

    redirect('/admin/boarding_houses.php');
}

// ------------------------------------------------------------
// List + search
// ------------------------------------------------------------
$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare(
        'SELECT id, name, total_rooms, price_per_month, status, city, photo
         FROM v_boarding_house_summary
         WHERE name LIKE ? OR city LIKE ? OR address LIKE ?
         ORDER BY id DESC'
    );
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $conn->prepare(
        'SELECT id, name, total_rooms, price_per_month, status, city, photo
         FROM v_boarding_house_summary
         ORDER BY id DESC'
    );
    $stmt->execute();
}
$houses = $stmt->fetchAll();

$pageTitle  = 'Data Kost';
$activeMenu = 'boarding_houses';
require __DIR__ . '/../includes/admin_header.php';
?>
            <h1>Tabel Pengelolaan Data Kost</h1>

            <div class="toolbar">
                <form method="get" action="">
                    <input type="search" name="q" placeholder="Cari Kost" value="<?= e($search) ?>" aria-label="Cari kost">
                    <button type="submit" class="btn btn-outline">Cari</button>
                </form>
                <a class="btn btn-green" href="<?= BASE_URL ?>/product_desc.php?mode=add">
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
                            <th>Nama Kost</th>
                            <th>Jumlah Kamar</th>
                            <th>Harga / Bulan</th>
                            <th>Status</th>
                            <th>Kota / Daerah</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($houses)): ?>
                            <tr>
                                <td colspan="6" class="empty-row">
                                    <?php if ($search !== ''): ?>
                                        Tidak ada kost yang cocok dengan "<?= e($search) ?>".
                                    <?php else: ?>
                                        Belum ada data kost. Klik Add untuk menambahkan kost pertama.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($houses as $house): ?>
                            <?php $photoUrl = uploadUrl('boarding_houses', $house['photo']); ?>
                            <tr>
                                <td>
                                    <div class="cell-name">
                                        <span class="thumb"<?= $photoUrl ? ' style="background-image:url(\'' . e($photoUrl) . '\')"' : '' ?>></span>
                                        <?= e($house['name']) ?>
                                    </div>
                                </td>
                                <td><?= (int) $house['total_rooms'] ?></td>
                                <td><?= e(formatRupiah($house['price_per_month'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= e($house['status']) ?>"><?= e(statusLabel($house['status'])) ?></span>
                                </td>
                                <td><?= e($house['city']) ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-small btn-orange"
                                           href="<?= BASE_URL ?>/product_desc.php?mode=edit&amp;id=<?= (int) $house['id'] ?>">Edit</a>

                                        <form method="post" action=""
                                              onsubmit="return confirm('Hapus kost &quot;<?= e(addslashes($house['name'])) ?>&quot;? Semua kamarnya ikut terhapus.');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $house['id'] ?>">
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
