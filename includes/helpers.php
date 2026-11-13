<?php
/**
 * Shared helper functions: output escaping, CSRF protection, flash messages,
 * access control, database shortcuts, and small wrappers for the activity log,
 * notifications and status history.
 *
 * Written to run on PHP 7.4 or higher.
 */

const COMPLAINT_STATUSES   = ['Submitted', 'Received', 'Assigned', 'In Progress', 'Escalated', 'Resolved', 'Closed'];
const COMPLAINT_PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
const USER_ROLES           = ['student', 'staff', 'admin'];

// ---------------------------------------------------------------------------
// Output and request helpers
// ---------------------------------------------------------------------------

/** Escape a value for safe output in HTML (protects against XSS, NFR-08). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** CSS class for a status or priority label, e.g. "In Progress" -> "badge-in-progress". */
function badge_class(string $label): string
{
    return 'badge-' . strtolower(str_replace(' ', '-', $label));
}

function format_date(?string $date): string
{
    return $date ? date('d M Y, H:i', strtotime($date)) : '-';
}

/** Up to two capital letters from a name, for people without a profile picture. */
function initials(string $name): string
{
    $out = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)), 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

/** Random temporary password that avoids look-alike characters. */
function generate_temp_password(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Flash messages (shown once after a redirect)
// ---------------------------------------------------------------------------

function flash(string $type, string $text): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---------------------------------------------------------------------------
// CSRF protection for every POST form
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('This form has expired or is invalid. Please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------------------------------
// Authentication and role-based access control (FR-03, FR-05, NFR-07)
// ---------------------------------------------------------------------------

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'   => (int)$_SESSION['user_id'],
        'name' => (string)($_SESSION['user_name'] ?? ''),
        'role' => (string)($_SESSION['role'] ?? ''),
        'avatar' => (string)($_SESSION['avatar'] ?? ''),
    ];
}

function dashboard_for(string $role): string
{
    switch ($role) {
        case 'admin': return 'admin.php';
        case 'staff': return 'staff.php';
        default:      return 'student.php';
    }
}

/** Require a logged-in user; send people with a temporary password to change it first. */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME']) !== 'change_password.php') {
        redirect('change_password.php');
    }
    return $user;
}

/** Require one of the given roles; anyone else is sent back to their own dashboard. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        flash('error', 'You do not have access to that page.');
        redirect(dashboard_for($user['role']));
    }
    return $user;
}

// ---------------------------------------------------------------------------
// Database shortcuts - always use prepared statements (NFR-08)
// ---------------------------------------------------------------------------

/** Run a SELECT and return all rows as associative arrays. */
function fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Run a SELECT and return the first row, or null. */
function fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = fetch_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

/** Run an INSERT/UPDATE/DELETE and return the number of affected rows. */
function db_execute(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

// ---------------------------------------------------------------------------
// Activity log, notifications, status history
// ---------------------------------------------------------------------------

function log_activity(mysqli $conn, ?int $userId, string $action, string $details = ''): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    db_execute(
        $conn,
        'INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
        'isss',
        [$userId, $action, $details, $ip]
    );
}

function notify(mysqli $conn, int $userId, ?int $complaintId, string $message): void
{
    db_execute(
        $conn,
        'INSERT INTO notifications (complaint_id, user_id, message) VALUES (?, ?, ?)',
        'iis',
        [$complaintId, $userId, $message]
    );
}

function unread_count(mysqli $conn, int $userId): int
{
    $row = fetch_one($conn, 'SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [$userId]);
    return (int)($row['n'] ?? 0);
}

function add_status_history(mysqli $conn, int $complaintId, ?string $previous, string $new, ?int $userId, ?string $notes = null): void
{
    db_execute(
        $conn,
        'INSERT INTO status_history (complaint_id, previous_status, new_status, updated_by, notes) VALUES (?, ?, ?, ?, ?)',
        'issis',
        [$complaintId, $previous, $new, $userId, $notes]
    );
}

// ---------------------------------------------------------------------------
// First-time setup
// ---------------------------------------------------------------------------

/**
 * True when the system has no administrator yet (a brand-new installation).
 * The home page and the login page use it to point the owner to the setup page.
 */
function needs_admin_setup(mysqli $conn): bool
{
    try {
        return fetch_one($conn, "SELECT user_id FROM users WHERE role = 'admin' LIMIT 1") === null;
    } catch (mysqli_sql_exception $ex) {
        return true;   // the tables are missing: install.php explains what to do
    }
}
