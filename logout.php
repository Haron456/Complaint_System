<?php
require __DIR__ . '/includes/bootstrap.php';

// Logging out is a POST with a CSRF token so another site cannot log people out.
if (is_post() && ($user = current_user())) {
    csrf_check();
    log_activity($conn, $user['id'], 'logout', '');
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'You have been logged out.');
}
redirect('login.php');