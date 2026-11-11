<?php
/**
 * Complaint attachments: checking, saving and removing.
 * One optional file per complaint: PDF, JPG or PNG, up to 2 MB. Files are saved
 * under random names in /uploads (blocked from direct web access) and are served
 * only through download.php, which checks who may see the complaint.
 */
const ATTACHMENT_MAX_BYTES = 2 * 1024 * 1024;

/** Full path of a stored attachment. basename() stops any path tricks. */
function upload_path(string $storedName): string
{
    return BASE_PATH . '/uploads/' . basename($storedName);
}

/**
 * Check an uploaded file. Returns [upload details or null, error text or null].
 * Choosing no file is fine and returns [null, null].
 */
function check_attachment(?array $file): array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'The file is too large. The limit is 2 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'The file could not be uploaded. Please try again.'];
    }
    if ($file['size'] > ATTACHMENT_MAX_BYTES) {
        return [null, 'The file is too large. The limit is 2 MB.'];
    }

    // Work out the real type from the file's contents, not from its name.
    $ext = null;
    $info = @getimagesize($file['tmp_name']);
    if ($info !== false && $info[2] === IMAGETYPE_JPEG) {
        $ext = 'jpg';
    } elseif ($info !== false && $info[2] === IMAGETYPE_PNG) {
        $ext = 'png';
    } else {
        $handle = fopen($file['tmp_name'], 'rb');
        $head = $handle ? fread($handle, 5) : '';
        if ($handle) {
            fclose($handle);
        }
        if ($head === '%PDF-') {
            $ext = 'pdf';
        }
    }
    if ($ext === null) {
        return [null, 'Only PDF, JPG and PNG files are allowed.'];
    }

    $original = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string)$file['name']));
    $original = mb_substr($original, 0, 100);
    if ($original === '') {
        $original = 'attachment.' . $ext;
    }
    return [['tmp' => $file['tmp_name'], 'ext' => $ext, 'original' => $original], null];
}

/** Move a checked upload into the uploads folder. Returns the stored file name, or null. */
function store_attachment(array $upload): ?string
{
    $dir = BASE_PATH . '/uploads';
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log('Upload folder is missing or not writable: ' . $dir);
        return null;
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $upload['ext'];
    if (!move_uploaded_file($upload['tmp'], $dir . '/' . $stored)) {
        error_log('Could not move the uploaded file into ' . $dir);
        return null;
    }
    return $stored;
}

/** Remove a stored attachment (used if saving the complaint fails). */
function delete_attachment(string $storedName): void
{
    $path = upload_path($storedName);
    if (is_file($path)) {
        @unlink($path);
    }
}