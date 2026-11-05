<?php
// General helpers used by several pages. Needs config.php (for BASE_URL) to be loaded first.

// 1000000 -> "Rp1.000.000"
function formatRupiah($amount): string
{
    return 'Rp' . number_format((int) $amount, 0, ',', '.');
}

// Database value -> text shown to the user
function statusLabel(string $status): string
{
    $labels = [
        'available' => 'Tersedia',
        'full'      => 'Penuh',
        'occupied'  => 'Terisi',
        'pending'   => 'Menunggu konfirmasi',
        'confirmed' => 'Dikonfirmasi',
        'rejected'  => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'completed' => 'Selesai',
    ];
    return $labels[$status] ?? $status;
}

function typeLabel(string $type): string
{
    $labels = [
        'male'   => 'Putra',
        'female' => 'Putri',
        'mixed'  => 'Campuran',
        'house'  => 'Rumah',
    ];
    return $labels[$type] ?? $type;
}

// Public URL of an uploaded file, or null when there is no file
function uploadUrl(string $subDir, ?string $fileName): ?string
{
    if (!$fileName) {
        return null;
    }
    return BASE_URL . '/uploads/' . $subDir . '/' . rawurlencode($fileName);
}

function uploadFileExists(string $subDir, ?string $fileName): bool
{
    if ($fileName === null || $fileName === '') {
        return false;
    }

    $path = dirname(__DIR__) . '/uploads/' . $subDir . '/' . basename($fileName);
    return is_file($path);
}

// "081234567890" / "+62 812-3456-7890" -> "6281234567890" (digits only, country code 62)
function normalizePhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (strpos($digits, '0') === 0) {
        $digits = '62' . substr($digits, 1);
    }
    return $digits;
}

function phoneWithCountryCode(?string $countryCode, ?string $phone): string
{
    $countryDigits = preg_replace('/\D+/', '', (string) $countryCode);
    $phoneDigits = preg_replace('/\D+/', '', (string) $phone);

    if ($phoneDigits === '' || $countryDigits === '' || strpos($phoneDigits, $countryDigits) === 0) {
        return $phoneDigits;
    }

    if (strpos($phoneDigits, '0') === 0) {
        $phoneDigits = substr($phoneDigits, 1);
    }

    return $countryDigits . $phoneDigits;
}

// "-0.03070000" -> "-0.0307"
function trimDecimal(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return rtrim(rtrim($value, '0'), '.');
}

// Generic query wrappers for the project.
function fetchAllPrepared(PDO $conn, string $sql, string $types = '', array $params = []): array
{
    if ($params === []) {
        return $conn->query($sql)->fetchAll();
    }

    $stmt = $conn->prepare($sql);
    bindPreparedParameters($stmt, $types, $params);
    $stmt->execute();

    return $stmt->fetchAll();
}

function executePrepared(PDO $conn, string $sql, string $types = '', array $params = []): int
{
    if ($params === []) {
        return $conn->exec($sql);
    }

    $stmt = $conn->prepare($sql);
    bindPreparedParameters($stmt, $types, $params);
    $stmt->execute();

    return $stmt->rowCount();
}

function bindPreparedParameters(PDOStatement $stmt, string $types, array $params): void
{
    if ($types !== '' && strlen($types) !== count($params)) {
        throw new InvalidArgumentException('The parameter type string must match the number of SQL parameters.');
    }

    foreach (array_values($params) as $index => $value) {
        $type = match ($types[$index] ?? '') {
            'i' => PDO::PARAM_INT,
            'b' => PDO::PARAM_BOOL,
            default => $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR,
        };
        $stmt->bindValue($index + 1, $value, $type);
    }
}

function databaseErrorCode(PDOException $exception): int
{
    return (int) ($exception->errorInfo[1] ?? 0);
}

function executeBookingWithRoomLock(PDO $conn, int $roomId, callable $operation): bool
{
    $conn->beginTransaction();

    try {
        $stmt = $conn->prepare(
            'SELECT r.id, r.status, r.price_per_month, b.status AS house_status
             FROM rooms r
             JOIN boarding_houses b ON b.id = r.boarding_house_id
             WHERE r.id = ?
             FOR UPDATE'
        );
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if (!$room || !$operation($room)) {
            $conn->rollBack();
            return false;
        }

        $conn->commit();
        return true;
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    }
}

function syncHouseMinPrice(PDO $conn, int $houseId): void
{
    $row = fetchOnePrepared(
        $conn,
        'SELECT MIN(price_per_month) AS min_price FROM rooms WHERE boarding_house_id = ?',
        'i',
        [$houseId]
    );

    if ($row === null) {
        return;
    }

    $minPrice = $row['min_price'] === null ? 0 : (int) $row['min_price'];

    executePrepared(
        $conn,
        'UPDATE boarding_houses SET price_per_month = ? WHERE id = ?',
        'ii',
        [$minPrice, $houseId]
    );
}

function fetchOnePrepared(PDO $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = fetchAllPrepared($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function isValidDate(string $value): bool
{
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($date === false) {
        return false;
    }

    return $date->format('Y-m-d') === $value;
}

function formatDate(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y', $timestamp);
}

function shortText(?string $value, int $limit = 80): string
{
    $text = (string) ($value ?? '');
    if ($limit <= 0) {
        return '';
    }

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    $trimmed = mb_substr($text, 0, max(0, $limit - 3));
    return rtrim($trimmed) . '...';
}

function houseDetailUrl(int $houseId): string
{
    return BASE_URL . '/product_desc.php?id=' . $houseId;
}

function calculateTotalPrice(int $monthlyPrice, string $checkIn, string $checkOut): int
{
    if ($checkIn === '' || $checkOut === '') {
        return 0;
    }

    $start = new DateTimeImmutable($checkIn);
    $end   = new DateTimeImmutable($checkOut);
    if ($end <= $start) {
        return 0;
    }

    $months = ((int) $end->format('Y') - (int) $start->format('Y')) * 12
        + (int) $end->format('n') - (int) $start->format('n');
    if ((int) $end->format('j') > (int) $start->format('j')) {
        $months++;
    }

    $months = max(1, $months);
    return $months * $monthlyPrice;
}

function isRoomAvailable(PDO $conn, int $roomId, string $checkIn, string $checkOut, int $excludeBookingId = 0): bool
{
    if ($roomId <= 0 || !isValidDate($checkIn) || !isValidDate($checkOut)) {
        return false;
    }

    $sql = 'SELECT id FROM bookings
            WHERE room_id = ?
              AND status IN (\'pending\', \'confirmed\')
              AND check_in_date < ?
              AND check_out_date > ?';

    $params = [$checkOut, $checkIn];
    if ($excludeBookingId > 0) {
        $sql .= ' AND id != ?';
        $params[] = $excludeBookingId;
    }

    $rows = fetchAllPrepared(
        $conn,
        $sql,
        'iss' . ($excludeBookingId > 0 ? 'i' : ''),
        array_merge([$roomId], $params)
    );

    return empty($rows);
}
