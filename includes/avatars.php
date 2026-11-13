<?php
/**
 * Profile pictures: checking, cropping to a square, saving and removing.
 * JPG or PNG up to 2 MB. The picture is cropped to a 300 x 300 square and saved
 * as a JPG under a random name in /uploads (blocked from direct web access).
 * avatar.php shows it to the people who are allowed to see it.
 */
const AVATAR_SIZE = 300;

/** Check an uploaded picture. Returns [details or null, error text or null]. */
function check_avatar(?array $file): array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, 'Choose a picture first.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > ATTACHMENT_MAX_BYTES) {
        return [null, 'The picture is too large. The limit is 2 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'The picture could not be uploaded. Please try again.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return [null, 'Only JPG and PNG pictures are allowed.'];
    }
    if ($info[0] > 6000 || $info[1] > 6000) {
        return [null, 'The picture is too large in pixels (up to 6000 x 6000).'];
    }
    return [['tmp' => $file['tmp_name'], 'type' => $info[2]], null];
}

/** Crop to a centred square, resize and save. Returns the stored file name, or null. */
function store_avatar(array $pic): ?string
{
    $dir = BASE_PATH . '/uploads';
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log('Upload folder is missing or not writable: ' . $dir);
        return null;
    }
    $base = 'avatar_' . bin2hex(random_bytes(12));

    if (function_exists('imagecreatetruecolor') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng')) {
        $src = $pic['type'] === IMAGETYPE_PNG ? @imagecreatefrompng($pic['tmp']) : @imagecreatefromjpeg($pic['tmp']);
        if ($src === false) {
            return null;
        }
        // Phone photos: turn the picture the right way up.
        if ($pic['type'] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($pic['tmp']);
            $angle = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
            if ($angle !== 0) {
                $rotated = imagerotate($src, $angle, 0);
                if ($rotated !== false) {
                    imagedestroy($src);
                    $src = $rotated;
                }
            }
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $out = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // white behind transparent PNGs
        imagecopyresampled($out, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), AVATAR_SIZE, AVATAR_SIZE, $side, $side);
        $saved = imagejpeg($out, $dir . '/' . $base . '.jpg', 88);
        imagedestroy($src);
        imagedestroy($out);
        return $saved ? $base . '.jpg' : null;
    }

    // No image library on the server: keep the original picture as it is.
    $name = $base . ($pic['type'] === IMAGETYPE_PNG ? '.png' : '.jpg');
    return move_uploaded_file($pic['tmp'], $dir . '/' . $name) ? $name : null;
}
