<?php
// Reusable card for one boarding house (Home recommendations, Search results).
// $house comes from the view v_boarding_house_summary.
function resolveHouseCardPhotos(PDO $conn, array $houses): array
{
    $missingCoverIds = [];
    foreach ($houses as $house) {
        if (!uploadFileExists('boarding_houses', $house['photo'] ?? null)) {
            $missingCoverIds[] = (int) $house['id'];
        }
    }

    $galleryPhotos = [];
    $missingCoverIds = array_values(array_unique($missingCoverIds));
    if ($missingCoverIds !== []) {
        $placeholders = implode(', ', array_fill(0, count($missingCoverIds), '?'));
        $galleryImages = fetchAllPrepared(
            $conn,
            "SELECT boarding_house_id, image
             FROM boarding_house_images
             WHERE boarding_house_id IN ($placeholders)
             ORDER BY boarding_house_id, sort_order, id",
            str_repeat('i', count($missingCoverIds)),
            $missingCoverIds
        );

        foreach ($galleryImages as $galleryImage) {
            $houseId = (int) $galleryImage['boarding_house_id'];
            if (!isset($galleryPhotos[$houseId])
                && uploadFileExists('boarding_houses', $galleryImage['image'])) {
                $galleryPhotos[$houseId] = $galleryImage['image'];
            }
        }
    }

    foreach ($houses as &$house) {
        $houseId = (int) $house['id'];
        if (uploadFileExists('boarding_houses', $house['photo'] ?? null)) {
            $house['card_photo_url'] = uploadUrl('boarding_houses', $house['photo']);
            $house['card_photo_alt'] = 'Foto ' . $house['name'];
        } elseif (isset($galleryPhotos[$houseId])) {
            $house['card_photo_url'] = uploadUrl('boarding_houses', $galleryPhotos[$houseId]);
            $house['card_photo_alt'] = 'Foto ' . $house['name'];
        } else {
            $house['card_photo_url'] = BASE_URL . '/assets/img/house-photo-placeholder.svg';
            $house['card_photo_alt'] = 'Foto kost belum tersedia untuk ' . $house['name'];
        }
    }
    unset($house);

    return $houses;
}

function renderHouseCard(array $house): void
{
    $url = houseDetailUrl((int) $house['id']);
    $photoUrl = $house['card_photo_url'] ?? BASE_URL . '/assets/img/house-photo-placeholder.svg';
    $photoAlt = $house['card_photo_alt'] ?? 'Foto kost belum tersedia untuk ' . $house['name'];
    ?>
    <article class="house-card">
        <a class="house-card__photo-link" href="<?= e($url) ?>">
            <img class="house-card__photo" src="<?= e($photoUrl) ?>" alt="<?= e($photoAlt) ?>" loading="lazy" decoding="async">
        </a>

        <div class="house-card__body">
            <h3 class="house-card__name"><a href="<?= e($url) ?>"><?= e($house['name']) ?></a></h3>
            <p class="house-card__desc"><?= e(shortText($house['description'], 70)) ?></p>
            <p class="house-card__meta">
                <?= e($house['city']) ?> - <?= e(typeLabel($house['type'])) ?>
                <?php if ((int) $house['review_count'] > 0): ?>
                    - &#9733; <?= e((string) $house['avg_rating']) ?>
                <?php endif; ?>
            </p>
            <p class="house-card__price"><?= e(formatRupiah($house['price_per_month'])) ?> / bulan</p>
        </div>

        <?php if ($house['status'] === 'full'): ?>
            <a class="house-card__button is-full" href="<?= e($url) ?>">PENUH</a>
        <?php else: ?>
            <a class="house-card__button" href="<?= e($url) ?>">BOOKING SEKARANG</a>
        <?php endif; ?>
    </article>
    <?php
}
