<?php
/**
 * My profile: the person's details, profile picture and a link to change the password.
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $current = fetch_one($conn, 'SELECT avatar FROM users WHERE user_id = ?', 'i', [$user['id']]);
    $oldFile = (string)($current['avatar'] ?? '');

    if ($action === 'upload_avatar') {
        [$pic, $problem] = check_avatar($_FILES['avatar'] ?? null);
        if ($problem !== null) {
            flash('error', $problem);
        } else {
            $stored = store_avatar($pic);
            if ($stored === null) {
                flash('error', 'The picture could not be saved on the server. Please try again, or try a different picture.');
            } else {
                db_execute($conn, 'UPDATE users SET avatar = ? WHERE user_id = ?', 'si', [$stored, $user['id']]);
                if ($oldFile !== '') {
                    delete_attachment($oldFile);
                }
                log_activity($conn, $user['id'], 'avatar_changed', '');
                flash('success', 'Your profile picture has been updated.');
            }
        }
    } elseif ($action === 'remove_avatar' && $oldFile !== '') {
        db_execute($conn, 'UPDATE users SET avatar = NULL WHERE user_id = ?', 'i', [$user['id']]);
        delete_attachment($oldFile);
        log_activity($conn, $user['id'], 'avatar_removed', '');
        flash('success', 'Your profile picture has been removed.');
    }
    redirect('profile.php');
}

$me = fetch_one(
    $conn,
    'SELECT u.full_name, u.email, u.role, u.student_staff_id, u.is_senior, u.avatar, u.created_at, d.department_name
     FROM users u LEFT JOIN departments d ON d.department_id = u.department_id
     WHERE u.user_id = ?',
    'i',
    [$user['id']]
);
// The login before this one (the current login is the newest entry).
$previous = fetch_one($conn, "SELECT created_at FROM activity_log WHERE user_id = ? AND action = 'login' ORDER BY log_id DESC LIMIT 1 OFFSET 1", 'i', [$user['id']]);

$roleLabel = ucfirst($me['role']) . ((int)$me['is_senior'] ? ' (senior)' : '');
$idLabel = $me['role'] === 'student' ? 'Student ID' : 'Staff ID';

$pageTitle = 'My profile';
require __DIR__ . '/includes/header.php';
?>
<h1>My profile</h1>

<div class="profile-grid">
    <aside class="card profile-card">
        <?php if ((string)$me['avatar'] !== ''): ?>
            <img class="avatar avatar-xl" src="avatar.php?v=<?= e(substr(md5((string)$me['avatar']), 0, 8)) ?>" alt="Your profile picture">
        <?php else: ?>
            <span class="avatar avatar-initials avatar-xl"><?= e(initials($me['full_name'])) ?></span>
        <?php endif; ?>
        <h2><?= e($me['full_name']) ?></h2>
        <span class="badge badge-in-progress"><?= e($roleLabel) ?></span>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_avatar">
            <input type="file" name="avatar" accept=".jpg,.jpeg,.png" required>
            <button type="submit" class="btn-block">Upload picture</button>
            <p class="small muted">JPG or PNG, up to 2 MB. It is cropped to a square.</p>
        </form>
        <?php if ((string)$me['avatar'] !== ''): ?>
        <form method="post" onsubmit="return confirm('Remove your profile picture?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="btn-block btn-ghost">Remove picture</button>
        </form>
        <?php endif; ?>
    </aside>

    <div>
        <div class="card">
            <h3>Your details</h3>
            <dl class="kv">
                <dt>Full name</dt><dd><?= e($me['full_name']) ?></dd>
                <dt>Email</dt><dd><?= e($me['email']) ?></dd>
                <dt><?= e($idLabel) ?></dt><dd><?= e($me['student_staff_id'] ?? '-') ?></dd>
                <dt>Role</dt><dd><?= e($roleLabel) ?></dd>
                <?php if ($me['role'] === 'staff'): ?>
                    <dt>Department</dt><dd><?= e($me['department_name'] ?? '-') ?></dd>
                <?php endif; ?>
                <dt>Member since</dt><dd><?= e(format_date($me['created_at'])) ?></dd>
                <dt>Previous login</dt><dd><?= e($previous ? format_date($previous['created_at']) : '-') ?></dd>
            </dl>
            <p class="small muted">To change your name or email address, please contact the administrator.</p>
        </div>

        <div class="card">
            <h3>Password</h3>
            <p class="muted">Choose a new password at any time. Use at least 8 characters.</p>
            <a class="btn btn-ghost" href="change_password.php">Change password</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
