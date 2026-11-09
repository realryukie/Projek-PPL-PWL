<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/upload.php';
require_once __DIR__ . '/includes/cards.php';

$queryString = static function (string $key, string $default = ''): string {
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
};

$location = $queryString('location');
$dateIn   = $queryString('date_in');
$dateOut  = $queryString('date_out');
$type     = $queryString('type');
$sort     = $queryString('sort', 'recommended');
$pageValue = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page     = max(1, $pageValue === false ? 1 : $pageValue);
$perPage  = 16;

$minPriceValue = $queryString('min_price');
$maxPriceValue = $queryString('max_price');
$minPrice = ($minPriceValue !== '' && ctype_digit($minPriceValue)) ? (int) $minPriceValue : null;
$maxPrice = ($maxPriceValue !== '' && ctype_digit($maxPriceValue)) ? (int) $maxPriceValue : null;

if (!in_array($type, ['male', 'female', 'mixed', 'house'], true)) {
    $type = '';
}
if (!in_array($sort, ['recommended', 'price_asc', 'price_desc', 'newest'], true)) {
    $sort = 'recommended';
}

$where  = [];
$types  = '';
$params = [];
$notice = null;

if ($location !== '') {
    $like    = '%' . $location . '%';
    $where[] = '(s.city LIKE ? OR s.name LIKE ? OR s.address LIKE ?)';
    $types  .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($type !== '') {
    $where[] = 's.type = ?';
    $types  .= 's';
    $params[] = $type;
}
if ($minPrice !== null) {
    $where[] = 's.price_per_month >= ?';
    $types  .= 'i';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 's.price_per_month <= ?';
    $types  .= 'i';
    $params[] = $maxPrice;
}

// Dates: only kost with at least one free room in that period
if ($dateIn !== '') {
    if (!isValidDate($dateIn)) {
        $notice = 'Format tanggal masuk tidak valid, filter tanggal diabaikan.';
    } else {
        if ($dateOut === '') {
            $dateOut = date('Y-m-d', strtotime($dateIn . ' +1 month'));
        }
        if (!isValidDate($dateOut) || $dateOut <= $dateIn) {
            $notice  = 'Tanggal keluar harus setelah tanggal masuk, filter tanggal diabaikan.';
            $dateOut = '';
        } else {
            $where[] = "EXISTS (
                SELECT 1 FROM rooms r
                WHERE r.boarding_house_id = s.id
                  AND s.status = 'available'
                  AND r.status = 'available'
                  AND NOT EXISTS (
                      SELECT 1 FROM bookings b
                      WHERE b.room_id = r.id
                        AND b.status IN ('pending', 'confirmed')
                        AND b.check_in_date < ?
                        AND b.check_out_date > ?
                  )
            )";
            $types  .= 'ss';
            array_push($params, $dateOut, $dateIn);
        }
    }
}

$whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

$orderSql = [
    'recommended' => "(s.status = 'available') DESC, s.avg_rating DESC, s.review_count DESC, s.id DESC",
    'price_asc'   => 's.price_per_month ASC, s.id DESC',
    'price_desc'  => 's.price_per_month DESC, s.id DESC',
    'newest'      => 's.id DESC',
][$sort];

$countRows  = fetchAllPrepared($conn, "SELECT COUNT(*) AS total FROM v_boarding_house_summary s $whereSql", $types, $params);
$totalItems = (int) $countRows[0]['total'];
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$houses = fetchAllPrepared(
    $conn,
    "SELECT s.* FROM v_boarding_house_summary s $whereSql ORDER BY $orderSql LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$perPage, $offset])
);
$houses = resolveHouseCardPhotos($conn, $houses);

function searchPageUrl(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;
    return BASE_URL . '/search.php?' . http_build_query($query);
}

$pageTitle = 'Cari Kos - Kostku';
$extraCss  = ['search.css'];
$searchBar = [
    'location'  => $location,
    'date_in'   => $dateIn,
    'date_out'  => $dateOut,
    'type'      => $type,
    'min_price' => $minPrice !== null ? (string) $minPrice : '',
    'max_price' => $maxPrice !== null ? (string) $maxPrice : '',
    'sort'      => $sort,
];
$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
require __DIR__ . '/includes/page_head.php';
require __DIR__ . '/includes/header.php';
?>
    <main class="container search-results">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="alert alert-error" role="alert"><?= e($notice) ?></div>
        <?php endif; ?>

        <p class="result-count">
            <?= $totalItems ?> kost ditemukan<?= $location !== '' ? ' untuk "' . e($location) . '"' : '' ?>
        </p>

        <?php if (empty($houses)): ?>
            <div class="empty-state">
                <p>Tidak ada kost yang cocok dengan pencarianmu.</p>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>/search.php">Hapus semua filter</a>
            </div>
        <?php else: ?>
            <div class="house-grid">
                <?php foreach ($houses as $house): ?>
                    <?php renderHouseCard($house); ?>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Halaman hasil">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(searchPageUrl($page - 1)) ?>" aria-label="Halaman sebelumnya">&larr;</a>
                    <?php endif; ?>

                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="is-current" aria-current="page"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= e(searchPageUrl($p)) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= e(searchPageUrl($page + 1)) ?>" aria-label="Halaman berikutnya">&rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
<?php
require __DIR__ . '/includes/footer.php';
require __DIR__ . '/includes/page_end.php';
?>