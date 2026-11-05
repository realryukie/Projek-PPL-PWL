<?php
// Gallery photos of a boarding house (table boarding_house_images).
// Files are stored in uploads/boarding_houses/ together with the cover photo.

const MAX_GALLERY_IMAGES = 10;

// Turns PHP's awkward $_FILES['gallery'] structure into a normal list of files
function collectGalleryUploads(?array $filesField): array
{
    $files = [];
    if (!$filesField || !isset($filesField['name']) || !is_array($filesField['name'])) {
        return $files;
    }
    foreach ($filesField['name'] as $index => $name) {
        if ($filesField['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = [
            'name'     => $name,
            'type'     => $filesField['type'][$index],
            'tmp_name' => $filesField['tmp_name'][$index],
            'error'    => $filesField['error'][$index],
            'size'     => $filesField['size'][$index],
        ];
    }
    return $files;
}

// Deletes the chosen images and appends the new files after the last position.
function applyGalleryChanges(PDO $conn, int $houseId, array $deleteIds, array $newFiles): void
{
    foreach ($deleteIds as $imageId) {
        $rows = fetchAllPrepared(
            $conn,
            'SELECT image FROM boarding_house_images WHERE id = ? AND boarding_house_id = ?',
            'ii',
            [$imageId, $houseId]
        );
        if ($rows) {
            executePrepared($conn, 'DELETE FROM boarding_house_images WHERE id = ?', 'i', [$imageId]);
            deleteUploadedFile('boarding_houses', $rows[0]['image']);
        }
    }

    if (!empty($newFiles)) {
        $rows  = fetchAllPrepared(
            $conn,
            'SELECT COALESCE(MAX(sort_order), 0) AS last_order FROM boarding_house_images WHERE boarding_house_id = ?',
            'i',
            [$houseId]
        );
        $order = (int) $rows[0]['last_order'];

        foreach ($newFiles as $fileName) {
            $order = min(255, $order + 1);
            executePrepared(
                $conn,
                'INSERT INTO boarding_house_images (boarding_house_id, image, sort_order) VALUES (?, ?, ?)',
                'isi',
                [$houseId, $fileName, $order]
            );
        }
    }
}
