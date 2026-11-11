<?php
/**
 * Serves a complaint's attachment, only to people who may see the complaint:
 * the student who owns it, the assigned staff member, or an admin.
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$complaint = $id ? complaint_by_id($conn, $id) : null;

if (!$complaint || !can_view_complaint($user, $complaint) || empty($complaint['attachment'])) {
    http_response_code(404);
    exit('File not found.');
}

$path = upload_path($complaint['attachment']);
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
$ext = strtolower(pathinfo($complaint['attachment'], PATHINFO_EXTENSION));

if (!is_file($path) || !isset($types[$ext])) {
    http_response_code(404);
    exit('File not found.');
}

$name = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)($complaint['attachment_name'] ?? ('attachment.' . $ext)));

header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
