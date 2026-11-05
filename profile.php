<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/upload.php';

requireLogin();
$userId = (int) currentUserId();

$tab = $_GET['tab'] ?? 'account';
if (!in_array($tab, ['account', 'history', 'favorites'], true)) {
    $tab = 'account';
}

$countryCodes = [
    '+62'  => "\u{1F1EE}\u{1F1E9} Indonesia (+62)",
    '+60'  => "\u{1F1F2}\u{1F1FE} Malaysia (+60)",
    '+65'  => "\u{1F1F8}\u{1F1EC} Singapura (+65)",
    '+673' => "\u{1F1E7}\u{1F1F3} Brunei (+673)",
    '+66'  => "\u{1F1F9}\u{1F1ED} Thailand (+66)",
    '+61'  => "\u{1F1E6}\u{1F1FA} Australia (+61)",
    '+81'  => "\u{1F1EF}\u{1F1F5} Jepang (+81)",
    '+82'  => "\u{1F1F0}\u{1F1F7} Korea Selatan (+82)",
    '+44'  => "\u{1F1EC}\u{1F1E7} Britania Raya (+44)",
    '+1'   => "\u{1F1FA}\u{1F1F8} Amerika Serikat (+1)",
];

$userRows = fetchAllPrepared($conn, 'SELECT * FROM users WHERE id = ?', 'i', [$userId]);
$user     = $userRows[0] ?? null;
if (!$user) {
    redirect('/auth/logout.php');
}

$errors = [];
$form   = [
    'name'         => (string) ($user['name'] ?? ''),
    'email'        => (string) $user['email'],
    'description'  => (string) ($user['description'] ?? ''),
    'country_code' => (string) $user['country_code'],
    'phone_number' => (string) ($user['phone_number'] ?? ''),
];
$startEditing = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCsrf()) {
        setFlash('error', 'Sesi kedaluwarsa. Coba lagi.');
        redirect('/profile.php?tab=' . $tab);
    }

    if ($action === 'delete_history') {
        // Soft delete: only hidden from the user, the admin still sees the booking
        executePrepared(
            $conn,
            'UPDATE bookings SET hidden_by_user = 1 WHERE id = ? AND user_id = ?',
            'ii',
            [(int) ($_POST['id'] ?? 0), $userId]
        );
        setFlash('success', 'Histori dihapus.');
        redirect('/profile.php?tab=history');
    }

    if ($action === 'clear_history') {
        executePrepared($conn, 'UPDATE bookings SET hidden_by_user = 1 WHERE user_id = ?', 'i', [$userId]);
        setFlash('success', 'Histori dibersihkan.');
        redirect('/profile.php?tab=history');
    }

    if ($action === 'remove_favorite') {
        executePrepared(
            $conn,
            'DELETE FROM favorites WHERE user_id = ? AND boarding_house_id = ?',
            'ii',
            [$userId, (int) ($_POST['house_id'] ?? 0)]
        );
        setFlash('success', 'Dihapus dari favorit.');
        redirect('/profile.php?tab=favorites');
    }

    if ($action === 'clear_favorites') {
        executePrepared($conn, 'DELETE FROM favorites WHERE user_id = ?', 'i', [$userId]);
        setFlash('success', 'Favorit dibersihkan.');
        redirect('/profile.php?tab=favorites');
    }

    if ($action === 'update_profile') {
        $tab = 'account';
        foreach ($form as $field => $default) {
            $form[$field] = trim($_POST[$field] ?? '');
        }
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (mb_strlen($form['name']) > 100) {
            $errors[] = 'Nama maksimal 100 karakter.';
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 150) {
            $errors[] = 'Masukkan alamat email yang valid.';
        }
        if (mb_strlen($form['description']) > 500) {
            $errors[] = 'Deskripsi maksimal 500 karakter.';
        }
        if (!isset($countryCodes[$form['country_code']])) {
            $errors[] = 'Kode negara tidak valid.';
        }

        $phoneDigits = preg_replace('/\D+/', '', $form['phone_number']);
        if ($phoneDigits !== '' && (strlen($phoneDigits) < 6 || strlen($phoneDigits) > 15)) {
            $errors[] = 'Nomor telepon harus berisi 6 sampai 15 digit.';
        }

        if ($newPassword !== '' || $confirmPassword !== '') {
            if (strlen($newPassword) < 8) {
                $errors[] = 'Kata sandi baru minimal 8 karakter.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Konfirmasi sandi tidak sama dengan sandi baru.';
            }
        }

        $newPhoto  = null;
        $photoFile = $_FILES['profile_photo'] ?? null;
        if (empty($errors) && $photoFile && $photoFile['error'] !== UPLOAD_ERR_NO_FILE) {
            $newPhoto = saveUploadedImage($photoFile, 'profiles', $errors);
        }

        if (empty($errors)) {
            $sql    = 'UPDATE users SET name = ?, email = ?, description = ?, country_code = ?, phone_number = ?, profile_photo = ?';
            $types  = 'ssssss';
            $params = [
                $form['name'] === '' ? null : $form['name'],
                $form['email'],
                $form['description'] === '' ? null : $form['description'],
                $form['country_code'],
                $phoneDigits === '' ? null : $phoneDigits,
                $newPhoto ?? $user['profile_photo'],
            ];

            if ($newPassword !== '') {
                $sql     .= ', password = ?';
                $types   .= 's';
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            $sql     .= ' WHERE id = ?';
            $types   .= 'i';
            $params[] = $userId;

            try {
                executePrepared($conn, $sql, $types, $params);

                if ($newPhoto !== null) {
                    deleteUploadedFile('profiles', $user['profile_photo']);
                }

                $_SESSION['name'] = $form['name'] !== '' ? $form['name'] : $form['email'];
                setFlash('success', 'Profil berhasil diperbarui.');
                redirect('/profile.php?tab=account');
            } catch (PDOException $exception) {
                deleteUploadedFile('profiles', $newPhoto);
                // 1062 = duplicate entry (email already used by another account)
                if (databaseErrorCode($exception) === 1062) {
                    $errors[] = 'Email ini sudah dipakai akun lain.';
                } else {
                    $errors[] = 'Profil gagal disimpan. Coba lagi.';
                }
            }
        }

        $startEditing = true; // show the form open again with the typed values
    }
}

$displayName = $user['name'] !== null && $user['name'] !== '' ? $user['name'] : $user['email'];
$avatarUrl   = uploadUrl('profiles', $user['profile_photo']);

$bookings  = [];
$favorites = [];

if ($tab === 'history') {
    $bookings = fetchAllPrepared(
        $conn,
        'SELECT id, boarding_house_id, boarding_house_name, room_name, room_photo, boarding_house_photo,
                price_at_booking, total_price, check_in_date, check_out_date, status
         FROM v_booking_detail
         WHERE user_id = ? AND hidden_by_user = 0
         ORDER BY created_at DESC, id DESC',
        'i',
        [$userId]
    );
}

if ($tab === 'favorites') {
    $favorites = fetchAllPrepared(
        $conn,
        'SELECT s.id, s.name, s.city, s.price_per_month, s.photo
         FROM favorites f
         JOIN v_boarding_house_summary s ON s.id = f.boarding_house_id
         WHERE f.user_id = ?
         ORDER BY f.created_at DESC',
        'i',
        [$userId]
    );
}

$trashIcon = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M6 6l1 14h10l1-14"></path><path d="M10 11v6M14 11v6"></path></svg>';

$pageTitle  = 'Profil - Kostku';
$extraCss   = ['profile.css'];
$extraJs    = ['profile.js'];
$hideFooter = true;
require __DIR__ . '/includes/header.php';
?>
    <div class="profile-layout">
        <aside class="profile-sidebar">
            <h2>PROFIL</h2>
            <nav aria-label="Menu profil">
                <a href="<?= BASE_URL ?>/profile.php?tab=account"   class="<?= $tab === 'account' ? 'is-active' : '' ?>">Detail Akun</a>
                <a href="<?= BASE_URL ?>/profile.php?tab=history"   class="<?= $tab === 'history' ? 'is-active' : '' ?>">Histori Pemesanan</a>
                <a href="<?= BASE_URL ?>/profile.php?tab=favorites" class="<?= $tab === 'favorites' ? 'is-active' : '' ?>">Favorit</a>
            </nav>
        </aside>

        <main class="profile-content">
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
            <?php endif; ?>

            <?php if ($tab === 'account'): ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?= e($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form id="profile-form" method="post" action="<?= BASE_URL ?>/profile.php?tab=account"
                      enctype="multipart/form-data" novalidate<?= $startEditing ? ' data-start-editing="1"' : '' ?>>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="profile-head">
                        <div class="profile-avatar-wrap">
                            <div id="avatar-preview" class="profile-avatar"<?= $avatarUrl ? ' style="background-image:url(\'' . e($avatarUrl) . '\')"' : '' ?>></div>
                            <input class="visually-hidden" type="file" id="profile_photo" name="profile_photo"
                                   accept="image/jpeg,image/png,image/webp">
                            <label class="avatar-edit" for="profile_photo" hidden>Ganti foto</label>
                        </div>

                        <div class="profile-head__name">
                            <h1><?= e($displayName) ?></h1>
                            <p>ID: <?= (int) $user['id'] ?></p>
                        </div>

                        <div class="profile-head__actions">
                            <button type="button" id="edit-profile" class="btn btn-orange">Edit Profil</button>
                            <a class="btn btn-red" href="<?= BASE_URL ?>/auth/logout.php">Log Out</a>
                        </div>
                    </div>

                    <div class="profile-field">
                        <label for="name">Nama:</label>
                        <input type="text" id="name" name="name" maxlength="100" value="<?= e($form['name']) ?>" data-editable readonly>
                    </div>

                    <div class="profile-field">
                        <label for="description">Desc:</label>
                        <textarea id="description" name="description" rows="2" maxlength="500" data-editable readonly><?= e($form['description']) ?></textarea>
                    </div>

                    <div class="profile-field">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" maxlength="150" value="<?= e($form['email']) ?>" data-editable readonly>
                    </div>

                    <div class="profile-field">
                        <label for="phone_number">Nomor Telepon:</label>
                        <div class="phone-row">
                            <select name="country_code" aria-label="Kode negara" data-editable disabled>
                                <?php foreach ($countryCodes as $code => $label): ?>
                                    <option value="<?= e($code) ?>"<?= $form['country_code'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="tel" id="phone_number" name="phone_number" maxlength="20" inputmode="numeric"
                                   value="<?= e($form['phone_number']) ?>" data-editable readonly>
                        </div>
                    </div>

                    <div class="profile-field">
                        <label for="new_password">Ubah Sandi:</label>
                        <div class="password-row">
                            <input type="password" id="new_password" name="new_password" autocomplete="new-password"
                                   placeholder="Kosongkan jika tidak diubah" data-editable readonly>
                            <button type="button" class="eye-button" data-toggle-password="new_password" aria-label="Tampilkan sandi" aria-pressed="false">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <div class="profile-field">
                        <label for="confirm_password">Konfirmasi Sandi:</label>
                        <div class="password-row">
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                                   data-editable readonly>
                            <button type="button" class="eye-button" data-toggle-password="confirm_password" aria-label="Tampilkan sandi" aria-pressed="false">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <div class="profile-save-bar" hidden>
                        <button type="submit" class="btn btn-green">Simpan</button>
                        <button type="button" class="btn btn-ghost" data-cancel-edit>Batal</button>
                    </div>
                </form>

            <?php elseif ($tab === 'history'): ?>
                <div class="list-head">
                    <h1>Histori</h1>
                    <?php if (!empty($bookings)): ?>
                        <form method="post" action="" onsubmit="return confirm('Bersihkan semua histori pemesanan?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="clear_history">
                            <button type="submit" class="btn btn-red">Bersihkan Histori</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($bookings)): ?>
                    <p class="empty-note">Belum ada histori pemesanan.</p>
                <?php endif; ?>

                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $thumbUrl = uploadUrl('rooms', $booking['room_photo']) ?? uploadUrl('boarding_houses', $booking['boarding_house_photo']);
                    ?>
                    <article class="list-card">
                        <div class="list-card__thumb list-card__thumb--wide"<?= $thumbUrl ? ' style="background-image:url(\'' . e($thumbUrl) . '\')"' : '' ?>></div>
                        <div class="list-card__info">
                            <a class="list-card__title" href="<?= e(houseDetailUrl((int) $booking['boarding_house_id'])) ?>"><?= e($booking['boarding_house_name']) ?></a>
                            <div class="list-card__line"><?= e($booking['room_name']) ?></div>
                            <div class="list-card__line"><?= e(formatRupiah($booking['total_price'])) ?></div>
                            <div class="list-card__line small"><?= e(formatRupiah($booking['price_at_booking'])) ?> / bulan</div>
                            <div class="list-card__line small"><?= e(formatDate($booking['check_in_date'])) ?> - <?= e(formatDate($booking['check_out_date'])) ?></div>
                            <span class="badge badge-<?= e($booking['status']) ?>"><?= e(statusLabel($booking['status'])) ?></span>
                        </div>
                        <form method="post" action="" onsubmit="return confirm('Hapus dari histori?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete_history">
                            <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
                            <button type="submit" class="icon-button" aria-label="Hapus dari histori"><?= $trashIcon ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="list-head">
                    <h1>Favorit</h1>
                    <?php if (!empty($favorites)): ?>
                        <form method="post" action="" onsubmit="return confirm('Bersihkan semua favorit?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="clear_favorites">
                            <button type="submit" class="btn btn-red">Bersihkan Favorit</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($favorites)): ?>
                    <p class="empty-note">Belum ada kost favorit. Buka detail kost lalu klik Tambah ke Favorit.</p>
                <?php endif; ?>

                <?php foreach ($favorites as $favorite): ?>
                    <?php $thumbUrl = uploadUrl('boarding_houses', $favorite['photo']); ?>
                    <article class="list-card">
                        <div class="list-card__thumb"<?= $thumbUrl ? ' style="background-image:url(\'' . e($thumbUrl) . '\')"' : '' ?>></div>
                        <div class="list-card__info">
                            <a class="list-card__title" href="<?= e(houseDetailUrl((int) $favorite['id'])) ?>"><?= e($favorite['name']) ?></a>
                            <div class="list-card__line"><?= e(formatRupiah($favorite['price_per_month'])) ?></div>
                            <div class="list-card__line small">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"></path><circle cx="12" cy="9.5" r="2.5"></circle></svg>
                                <?= e($favorite['city']) ?>
                            </div>
                        </div>
                        <form method="post" action="">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="remove_favorite">
                            <input type="hidden" name="house_id" value="<?= (int) $favorite['id'] ?>">
                            <button type="submit" class="icon-button" aria-label="Hapus dari favorit"><?= $trashIcon ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
