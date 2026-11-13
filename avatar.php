<?php
/**
 * Shows a profile picture. People see their own; administrators can see anyone's.
 * The pictures live in /uploads, which cannot be opened directly from the web.
 */
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if (!$user) {
    http_response_code(404);
    exit;
}
$id = (int)($_GET['u'] ?? $user['id']);
if ($id !== $user['id'] && $user['role'] !== 'admin') {
    http_response_code(404);
    exit;
}

$row = fetch_one($conn, 'SELECT avatar FROM users WHERE user_id = ?', 'i', [$id]);
$file = (string)($row['avatar'] ?? '');
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$path = $file !== '' ? upload_path($file) : '';

if ($path === '' || !is_file($path) || !isset($types[$ext])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
