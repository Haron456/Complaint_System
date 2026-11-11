<?php
/**
 * First-time setup: creates the first administrator account.
 * The home page shows a button to this page on a brand-new system.
 * It refuses to do anything once an administrator exists, so it is safe to leave in place.
 */
require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$schemaMissing = false;
$alreadyInstalled = false;

try {
    $existing = fetch_one($conn, "SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
    $alreadyInstalled = (bool)$existing;
} catch (mysqli_sql_exception $ex) {
    $schemaMissing = true;   // the database tables have not been created yet
}

$old = ['full_name' => '', 'email' => '', 'staff_id' => ''];

if (!$schemaMissing && !$alreadyInstalled && is_post()) {
    csrf_check();
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email'] = strtolower(trim($_POST['email'] ?? ''));
    $old['staff_id'] = strtoupper(trim($_POST['staff_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['staff_id'] !== '' && !preg_match('/^[A-Z0-9\/\-]{3,20}$/', $old['staff_id'])) {
        $errors[] = 'The staff ID may only use letters, numbers, "/" or "-".';
    }
    if (strlen($password) < 8) {
        $errors[] = 'The password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $staffId = $old['staff_id'] === '' ? null : $old['staff_id'];
        db_execute(
            $conn,
            "INSERT INTO users (full_name, email, password_hash, role, student_staff_id) VALUES (?, ?, ?, 'admin', ?)",
            'ssss',
            [$old['full_name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $staffId]
        );
        log_activity($conn, (int)$conn->insert_id, 'installed', 'First administrator created');
        flash('success', 'The administrator account has been created. Log in below to start using the system.');
        redirect('login.php?portal=admin');
    }
}

$pageTitle = 'Set up the administrator';
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-box">
    <h1>Welcome</h1>
    <?php if ($schemaMissing): ?>
        <div class="alert alert-error">The database has not been prepared yet. Please ask the person who installed the system to import the database file (<strong>database/schema.sql</strong>), then reload this page.</div>
    <?php elseif ($alreadyInstalled): ?>
        <div class="alert alert-info">The system is already set up. There is nothing more to do here.</div>
        <a class="btn btn-block" href="login.php?portal=admin">Go to log in</a>
    <?php else: ?>
        <p class="muted">Create the administrator account. The administrator adds staff, assigns complaints and reads the reports. You only do this once.</p>
        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label for="full_name">Your full name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="100" required autofocus>
            </div>
            <div class="field">
                <label for="email">Your email address</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" maxlength="100" required>
            </div>
            <div class="field">
                <label for="staff_id">Staff ID (you can leave this empty)</label>
                <input type="text" id="staff_id" name="staff_id" value="<?= e($old['staff_id']) ?>" maxlength="20">
            </div>
            <div class="field">
                <label for="password">Choose a password (at least 8 characters)</label>
                <input type="password" id="password" name="password" minlength="8" required>
            </div>
            <div class="field">
                <label for="confirm_password">Type the password again</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
            </div>
            <button type="submit" class="btn-block">Create the administrator account</button>
            <p class="small muted">Write the password down somewhere safe. You will use this email and password to log in.</p>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
