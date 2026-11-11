<?php
/**
 * User management for administrators: create, edit, deactivate/reactivate
 * accounts, and reset passwords. A new or reset account gets the person's own
 * ID as its first password, and they must change it at first login.
 */
require __DIR__ . '/includes/bootstrap.php';
$admin = require_role('admin');

$departments = fetch_all($conn, 'SELECT department_id, department_name FROM departments ORDER BY department_name');
$deptIds = array_map('intval', array_column($departments, 'department_id'));

/** Validate the shared name / role / department fields. Returns [errors, cleaned values]. */
function validate_user_fields(array $deptIds): array
{
    $errors = [];
    $name = trim($_POST['full_name'] ?? '');
    $role = (string)($_POST['role'] ?? '');
    $dept = (int)($_POST['department_id'] ?? 0);
    $senior = !empty($_POST['is_senior']) ? 1 : 0;

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Enter the full name (up to 100 characters).';
    }
    if (!in_array($role, USER_ROLES, true)) {
        $errors[] = 'Choose a valid role.';
    }
    if ($role === 'staff') {
        if (!in_array($dept, $deptIds, true)) {
            $errors[] = 'Staff accounts must be assigned to a department.';
        }
    } else {
        $dept = null;     // only staff belong to a department
        $senior = 0;
    }
    return [$errors, ['name' => $name, 'role' => $role, 'dept' => $dept, 'senior' => $senior]];
}

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'create') {
        [$errors, $f] = validate_user_fields($deptIds);
        $email = strtolower(trim($_POST['email'] ?? ''));
        $sid = strtoupper(trim($_POST['student_staff_id'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!preg_match('/^[A-Z0-9\/\-]{3,20}$/', $sid)) {
            $errors[] = 'Enter the student or staff ID (letters, numbers, "/" or "-", 3 to 20 characters).';
        }
        if (!$errors && fetch_one($conn, 'SELECT user_id FROM users WHERE email = ?', 's', [$email])) {
            $errors[] = 'That email address is already registered.';
        }
        if (!$errors && fetch_one($conn, 'SELECT user_id FROM users WHERE student_staff_id = ?', 's', [$sid])) {
            $errors[] = 'That ID is already registered.';
        }

        if ($errors) {
            foreach ($errors as $err) { flash('error', $err); }
        } else {
            $firstPassword = $sid;   // the person's own ID; they must change it at first login
            db_execute(
                $conn,
                'INSERT INTO users (full_name, email, password_hash, role, student_staff_id, department_id, is_senior, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
                'sssssii',
                [$f['name'], $email, password_hash($firstPassword, PASSWORD_DEFAULT), $f['role'], $sid, $f['dept'], $f['senior']]
            );
            log_activity($conn, $admin['id'], 'user_created', sprintf('%s (%s)', $email, $f['role']));
            flash('success', sprintf('Account created for %s. Their first password is their ID: %s. They must change it when they first log in.', $f['name'], $sid));
        }
        redirect('admin_users.php');
    }

    $target = $targetId ? fetch_one($conn, 'SELECT user_id, full_name, email, role, is_active, student_staff_id FROM users WHERE user_id = ?', 'i', [$targetId]) : null;
    if (!$target) {
        flash('error', 'That user could not be found.');
        redirect('admin_users.php');
    }
    $isSelf = $targetId === $admin['id'];

    if ($action === 'update') {
        [$errors, $f] = validate_user_fields($deptIds);
        if ($isSelf && $f['role'] !== 'admin') {
            $errors[] = 'You cannot remove your own administrator role.';
        }
        if ($errors) {
            foreach ($errors as $err) { flash('error', $err); }
            redirect('admin_users.php?edit=' . $targetId);
        }
        db_execute(
            $conn,
            'UPDATE users SET full_name = ?, role = ?, department_id = ?, is_senior = ? WHERE user_id = ?',
            'ssiii',
            [$f['name'], $f['role'], $f['dept'], $f['senior'], $targetId]
        );
        log_activity($conn, $admin['id'], 'user_updated', sprintf('%s -> role %s', $target['email'], $f['role']));
        flash('success', 'The account has been updated.');
        redirect('admin_users.php');
    }

    if ($action === 'toggle_active') {
        if ($isSelf) {
            flash('error', 'You cannot deactivate your own account.');
        } else {
            $newState = (int)$target['is_active'] ? 0 : 1;
            db_execute($conn, 'UPDATE users SET is_active = ? WHERE user_id = ?', 'ii', [$newState, $targetId]);
            log_activity($conn, $admin['id'], $newState ? 'user_activated' : 'user_deactivated', $target['email']);
            flash('success', sprintf('%s has been %s.', $target['full_name'], $newState ? 'reactivated' : 'deactivated'));
        }
        redirect('admin_users.php');
    }

    if ($action === 'reset_password') {
        // Reset to the person's own ID. If an account has no ID on file, use a random one instead.
        $resetTo = (string)($target['student_staff_id'] ?? '');
        $isRandom = ($resetTo === '');
        if ($isRandom) {
            $resetTo = generate_temp_password();
        }
        db_execute(
            $conn,
            'UPDATE users SET password_hash = ?, must_change_password = 1 WHERE user_id = ?',
            'si',
            [password_hash($resetTo, PASSWORD_DEFAULT), $targetId]
        );
        log_activity($conn, $admin['id'], 'password_reset', $target['email']);
        if ($isRandom) {
            flash('success', sprintf('Password reset for %s. This account has no ID on file, so the temporary password is: %s (shown only once). They must change it at next login.', $target['full_name'], $resetTo));
        } else {
            flash('success', sprintf('Password reset for %s. Their password is now their ID: %s. They must change it at next login.', $target['full_name'], $resetTo));
        }
        redirect('admin_users.php');
    }

    redirect('admin_users.php');
}

// ---- Page data ----
$editId = (int)($_GET['edit'] ?? 0);
$editUser = $editId ? fetch_one($conn, 'SELECT * FROM users WHERE user_id = ?', 'i', [$editId]) : null;

$users = fetch_all(
    $conn,
    "SELECT u.user_id, u.full_name, u.email, u.role, u.student_staff_id, u.is_senior, u.is_active, d.department_name
     FROM users u LEFT JOIN departments d ON d.department_id = u.department_id
     ORDER BY FIELD(u.role, 'admin', 'staff', 'student'), u.full_name"
);

/** Department drop-down used by the create and edit forms. */
function department_options(array $departments, ?int $selected): string
{
    $html = '<option value="">- none -</option>';
    foreach ($departments as $d) {
        $sel = ((int)$d['department_id'] === $selected) ? ' selected' : '';
        $html .= '<option value="' . (int)$d['department_id'] . '"' . $sel . '>' . e($d['department_name']) . '</option>';
    }
    return $html;
}

$pageTitle = 'Manage users';
require __DIR__ . '/includes/header.php';
?>
<h1>Manage users</h1>

<?php if ($editUser): ?>
<div class="card">
    <h3>Edit <?= e($editUser['full_name']) ?> <span class="muted small">(<?= e($editUser['email']) ?>)</span></h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="user_id" value="<?= (int)$editUser['user_id'] ?>">
        <div class="form-row">
            <div class="field">
                <label>Full name</label>
                <input type="text" name="full_name" value="<?= e($editUser['full_name']) ?>" maxlength="100" required>
            </div>
            <div class="field">
                <label>Role</label>
                <select name="role">
                    <?php foreach (USER_ROLES as $r): ?>
                        <option value="<?= e($r) ?>" <?= $editUser['role'] === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Department (staff only)</label>
                <select name="department_id"><?= department_options($departments, $editUser['department_id'] !== null ? (int)$editUser['department_id'] : null) ?></select>
            </div>
        </div>
        <div class="check">
            <input type="checkbox" id="is_senior_edit" name="is_senior" value="1" <?= (int)$editUser['is_senior'] ? 'checked' : '' ?>>
            <label for="is_senior_edit">Senior staff member (receives escalated complaints)</label>
        </div>
        <button type="submit">Save changes</button>
        <a class="btn btn-ghost" href="admin_users.php">Cancel</a>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>Add a user</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-row">
            <div class="field">
                <label>Full name</label>
                <input type="text" name="full_name" maxlength="100" required>
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" maxlength="100" required>
            </div>
            <div class="field">
                <label>Student / staff ID</label>
                <input type="text" name="student_staff_id" maxlength="20" required>
            </div>
            <div class="field">
                <label>Role</label>
                <select name="role">
                    <?php foreach (USER_ROLES as $r): ?>
                        <option value="<?= e($r) ?>" <?= $r === 'staff' ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Department (required for staff)</label>
                <select name="department_id"><?= department_options($departments, null) ?></select>
            </div>
        </div>
        <div class="check">
            <input type="checkbox" id="is_senior_new" name="is_senior" value="1">
            <label for="is_senior_new">Senior staff member (receives escalated complaints)</label>
        </div>
        <button type="submit">Create account</button>
        <span class="muted small">The ID you enter becomes the first password. The person must change it at first login.</span>
    </form>
</div>

<h2>All users (<?= count($users) ?>)</h2>
<div class="table-wrap">
<table>
    <tr><th>Name</th><th>Email</th><th>ID</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
        <td><?= e($u['full_name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['student_staff_id'] ?? '-') ?></td>
        <td><?= e(ucfirst($u['role'])) ?><?= (int)$u['is_senior'] ? ' <span class="muted small">(senior)</span>' : '' ?></td>
        <td><?= e($u['department_name'] ?? '-') ?></td>
        <td><?= (int)$u['is_active'] ? 'Active' : '<span style="color:var(--bad)">Deactivated</span>' ?></td>
        <td>
            <div class="inline-controls">
                <a class="btn btn-small btn-ghost" href="admin_users.php?edit=<?= (int)$u['user_id'] ?>">Edit</a>
                <form method="post" class="inline-form" onsubmit="return confirm('Reset this user\'s password to their ID?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                    <button type="submit" class="btn-small btn-ghost">Reset password</button>
                </form>
                <?php if ((int)$u['user_id'] !== $admin['id']): ?>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                    <button type="submit" class="btn-small <?= (int)$u['is_active'] ? 'btn-danger' : '' ?>"><?= (int)$u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>