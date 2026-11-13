<?php
/**
 * Bootstrap - loaded at the top of every page.
 *
 * Loads the configuration, starts a hardened session, connects to MySQL and
 * enforces two rules on every request:
 *   - automatic logout after the inactivity timeout (NFR-09)
 *   - the logged-in account must still exist and be active (FR-06)
 */
define('BASE_PATH', dirname(__DIR__));

$configFile = BASE_PATH . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php. Copy config/config.sample.php to config/config.php and edit it.');
}
$config = require $configFile;

error_reporting(E_ALL);
ini_set('display_errors', !empty($config['debug']) ? '1' : '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/complaints.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/avatars.php';

// Friendly error page instead of a stack trace (NFR-15). Details go to the PHP error log.
set_exception_handler(function ($ex) use ($config) {
    error_log('Unhandled exception: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Something went wrong</title></head>'
       . '<body style="font-family:sans-serif;padding:40px;max-width:600px;margin:auto">'
       . '<h2>Something went wrong</h2>'
       . '<p>The request could not be completed and no data was lost. Please go back and try again, '
       . 'or contact the administrator if the problem continues.</p>';
    if (!empty($config['debug'])) {
        echo '<pre>' . htmlspecialchars($ex->getMessage()) . '</pre>';
    }
    echo '</body></html>';
});

// ---- Session ----
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (!defined('NO_SESSION')) {
    session_start();
}

// ---- Database ----
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $dbCfg = $config['db'];
    $conn = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('The system cannot reach its database right now. Please try again shortly or contact the administrator.');
}

// ---- Inactivity timeout and active-account check ----
if (!empty($_SESSION['user_id'])) {
    $timeout = (int)($config['session_timeout'] ?? 1800);
    $expired = isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $timeout;
    $row = $expired ? null : fetch_one(
        $conn,
        'SELECT full_name, role, is_active, must_change_password, avatar FROM users WHERE user_id = ?',
        'i',
        [(int)$_SESSION['user_id']]
    );

    if ($expired || !$row || !(int)$row['is_active']) {
        $notice = $expired
            ? sprintf('You were logged out after %d minutes of inactivity. Please log in again.', (int)round($timeout / 60))
            : 'Your session has ended. Please log in again.';
        $_SESSION = [];
        session_regenerate_id(true);
        flash('error', $notice);
        redirect('login.php');
    }

    // Keep session values in step with the database (role or name may have changed).
    $_SESSION['user_name'] = $row['full_name'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['avatar'] = (string)($row['avatar'] ?? '');
    $_SESSION['must_change_password'] = (int)$row['must_change_password'];
    $_SESSION['last_activity'] = time();
}
