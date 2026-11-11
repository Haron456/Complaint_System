<?php
/**
 * Change own password. People given a temporary password are sent here
 * first and cannot use the rest of the system until they change it.
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$errors = [];
$forced = !empty($_SESSION['must_change_password']);

if (is_post()) {
    csrf_check();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $row = fetch_one($conn, 'SELECT password_hash FROM users WHERE user_id = ?', 'i', [$user['id']]);

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'The new password must be at least 8 characters long.';
    } elseif ($new !== $confirm) {
        $errors[] = 'The two new passwords do not match.';
    } elseif ($new === $current) {
        $errors[] = 'Choose a new password that is different from the current one.';
    }

    if (!$errors) {
        db_execute(
            $conn,
            'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?',
            'si',
            [password_hash($new, PASSWORD_DEFAULT), $user['id']]
        );
        $_SESSION['must_change_password'] = 0;
        session_regenerate_id(true);
        log_activity($conn, $user['id'], 'password_changed', '');
        flash('success', 'Your password has been changed.');
        redirect(dashboard_for($user['role']));
    }
}

$pageTitle = 'Change password';
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-box">
    <h1>Change password</h1>
    <?php if ($forced): ?>
        <div class="alert alert-info">You are using a temporary password. Please choose your own before continuing.</div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <div class="field">
            <label for="new_password">New password (at least 8 characters)</label>
            <input type="password" id="new_password" name="new_password" minlength="8" required>
        </div>
        <div class="field">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
        </div>
        <button type="submit" class="btn-block">Change password</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>