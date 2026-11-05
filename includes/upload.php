<?php
// Image upload helpers. Files are saved in kostku/uploads/<subDir>/ with a random name.

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

function uploadDir(string $subDir): string
{
    return dirname(__DIR__) . '/uploads/' . $subDir;
}

// Validates and saves an uploaded image.
// Returns the new file name, or null (and adds a message to $errors) when it fails.
function saveUploadedImage(array $file, string $subDir, array &$errors): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = 'Ukuran foto maksimal 5 MB.';
        } else {
            $errors[] = 'Upload foto gagal. Coba lagi.';
        }
        return null;
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        $errors[] = 'Ukuran foto maksimal 5 MB.';
        return null;
    }

    // getimagesize() reads the real file content, so a renamed .php file is rejected
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $imageInfo = @getimagesize($file['tmp_name']);
    $mimeType  = $imageInfo['mime'] ?? '';

    if (!isset($allowedTypes[$mimeType])) {
        $errors[] = 'Foto harus berformat JPG, PNG, atau WebP.';
        return null;
    }

    $directory = uploadDir($subDir);
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        $errors[] = 'Folder upload tidak bisa dibuat.';
        return null;
    }

    $fileName = bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mimeType];

    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $fileName)) {
        $errors[] = 'Foto gagal disimpan. Coba lagi.';
        return null;
    }

    return $fileName;
}

// Deletes a previously uploaded file (does nothing if it does not exist)
function deleteUploadedFile(string $subDir, ?string $fileName): void
{
    if (!$fileName) {
        return;
    }
    $path = uploadDir($subDir) . '/' . basename($fileName);
    if (is_file($path)) {
        unlink($path);
    }
}
