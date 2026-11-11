<?php
/**
 * Single login for students, staff and administrators.
 * The role comes from the account, so each person lands on their own dashboard.
 */
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    redirect(dashboard_for($user['role']));
}

$error = '';
$email = '';

// Which portal was chosen on the home page. This only changes the wording; every role logs in the same way.
$portals = [
    'student' => ['Student portal', 'Log in to lodge complaints, follow them and rate the service.'],
    'staff'   => ['Staff portal', 'Log in to handle the complaints assigned to you.'],
    'admin'   => ['Administrator portal', 'Log in to assign complaints, manage users and read reports.'],
];
[$portalTitle, $portalText] = $portals[(string)($_GET['portal'] ?? '')] ?? ['Log in', 'Students, staff and administrators all log in here.'];

if (is_post()) {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $account = fetch_one(
        $conn,
        'SELECT user_id, full_name, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1',
        's',
        [$email]
    );

    if (!$account || !password_verify($password, $account['password_hash'])) {
        // Same message for unknown email and wrong password, so accounts cannot be guessed.
        $error = 'Incorrect email or password.';
    } elseif (!(int)$account['is_active']) {
        $error = 'This account has been deactivated. Please contact the administrator.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$account['user_id'];
        $_SESSION['user_name'] = $account['full_name'];
        $_SESSION['role'] = $account['role'];
        $_SESSION['last_activity'] = time();
        log_activity($conn, (int)$account['user_id'], 'login', '');
        redirect(dashboard_for($account['role']));
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-box">
    <h1><?= e($portalTitle) ?></h1>
    <p class="muted"><?= e($portalText) ?></p>
    <?php if (needs_admin_setup($conn)): ?>
        <div class="alert alert-info">This system has no administrator yet. <a href="install.php">Set up the administrator account</a> first.</div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="check">
            <input type="checkbox" id="show" onclick="document.getElementById('password').type = this.checked ? 'text' : 'password'">
            <label for="show">Show password</label>
        </div>
        <button type="submit" class="btn-block">Log in</button>
    </form>
    <p class="small muted" style="text-align:center">Students: <a href="register.php">create an account</a>. Staff accounts are created by the administrator.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
