<?php
/**
 * Student self-registration.
 * Staff and administrator accounts are created by an administrator.
 */
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    redirect(dashboard_for($user['role']));
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'student_id' => ''];

if (is_post()) {
    csrf_check();
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email'] = strtolower(trim($_POST['email'] ?? ''));
    $old['student_id'] = strtoupper(trim($_POST['student_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Enter your full name (up to 100 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 100) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[A-Z0-9\/\-]{3,20}$/', $old['student_id'])) {
        $errors[] = 'Enter your student ID using letters, numbers, "/" or "-" (3 to 20 characters).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'The password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    // Reject duplicates
    if (!$errors) {
        if (fetch_one($conn, 'SELECT user_id FROM users WHERE email = ?', 's', [$old['email']])) {
            $errors[] = 'That email address is already registered.';
        }
        if (fetch_one($conn, 'SELECT user_id FROM users WHERE student_staff_id = ?', 's', [$old['student_id']])) {
            $errors[] = 'That student ID is already registered.';
        }
    }

    if (!$errors) {
        try {
            db_execute(
                $conn,
                "INSERT INTO users (full_name, email, password_hash, role, student_staff_id) VALUES (?, ?, ?, 'student', ?)",
                'ssss',
                [$old['full_name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['student_id']]
            );
            $newId = (int)$conn->insert_id;
            log_activity($conn, $newId, 'registered', 'Student account created');
            flash('success', 'Your account has been created. You can now log in.');
            redirect('login.php');
        } catch (mysqli_sql_exception $ex) {
            if ((int)$ex->getCode() === 1062) {   // duplicate key: someone registered at the same moment
                $errors[] = 'That email address or student ID is already registered.';
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle = 'Student registration';
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-box">
    <h1>Student registration</h1>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="100" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" maxlength="100" required>
        </div>
        <div class="field">
            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="student_id" value="<?= e($old['student_id']) ?>" maxlength="20" placeholder="e.g. 24/05621" required>
        </div>
        <div class="field">
            <label for="password">Password (at least 8 characters)</label>
            <input type="password" id="password" name="password" minlength="8" required>
        </div>
        <div class="field">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
        </div>
        <button type="submit" class="btn-block">Register</button>
    </form>
    <p class="small muted" style="text-align:center">Already registered? <a href="login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>