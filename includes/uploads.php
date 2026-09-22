<?php
/**
 * Secure image uploads for featured images.
 *
 * - size limit from config
 * - MIME type detected from file contents (finfo), not the browser-supplied type or extension
 * - image is decoded and re-encoded with GD, which strips any embedded payload/metadata
 * - random file name with an extension we choose; stored in assets/uploads (script execution disabled)
 */

const UPLOAD_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const UPLOAD_MAX_WIDTH = 1600;

/**
 * @return array{path: ?string, error: ?string} path relative to app root, e.g. assets/uploads/ab12.webp
 */
function handle_image_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['path' => null, 'error' => 'The image could not be uploaded. Please try again.'];
    }
    if ($file['size'] > config('uploads.max_bytes')) {
        return ['path' => null, 'error' => 'Images must be ' . round(config('uploads.max_bytes') / 1048576, 1) . ' MB or smaller.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!isset(UPLOAD_TYPES[$mime]) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['path' => null, 'error' => 'Only JPG, PNG or WebP images are allowed.'];
    }
    if (@getimagesize($file['tmp_name']) === false) {
        return ['path' => null, 'error' => 'The file is not a valid image.'];
    }

    $image = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if ($image === false) {
        return ['path' => null, 'error' => 'The image could not be processed.'];
    }
    if (imagesx($image) > UPLOAD_MAX_WIDTH) {
        $image = imagescale($image, UPLOAD_MAX_WIDTH);
    }

    $name = bin2hex(random_bytes(16)) . '.' . UPLOAD_TYPES[$mime];
    $relative = 'assets/uploads/' . $name;
    $target = APP_ROOT . '/' . $relative;

    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($image, $target, 82),
        'image/png'  => imagepng($image, $target, 7),
        'image/webp' => imagewebp($image, $target, 80),
    };
    imagedestroy($image);

    return $saved ? ['path' => $relative, 'error' => null] : ['path' => null, 'error' => 'The image could not be saved.'];
}

/** Delete a previously uploaded file, only ever inside assets/uploads. */
function delete_upload(?string $relative): void
{
    if (!$relative || !preg_match('#^assets/uploads/[a-f0-9]{32}\.(jpg|png|webp)$#', $relative)) {
        return;
    }
    $file = APP_ROOT . '/' . $relative;
    if (is_file($file)) {
        @unlink($file);
    }
}
