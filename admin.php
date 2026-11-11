<?php
/**
 * Administrator dashboard: overview, all complaints, and assigning or
 * reassigning complaints to staff.
 * Reports, escalation settings and the activity log come in later phases.
 */
require __DIR__ . '/includes/bootstrap.php';
$admin = require_role('admin');

// ---- Assign or reassign ----
if (is_post() && ($_POST['action'] ?? '') === 'assign') {
    csrf_check();
    $complaint = complaint_by_id($conn, (int)($_POST['complaint_id'] ?? 0));
    $staffId = (int)($_POST['staff_id'] ?? 0);

    if (!$complaint) {
        flash('error', 'That complaint could not be found.');
    } elseif (in_array($complaint['status'], ['Resolved', 'Closed'], true)) {
        flash('error', 'A resolved or closed complaint cannot be reassigned.');
    } elseif (!assign_complaint($conn, $complaint, $staffId, $admin['id'])) {
        flash('error', 'Choose an active staff member.');
    } else {
        flash('success', sprintf('%s has been assigned.', $complaint['ticket_number']));
    }
    redirect('admin.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ---- Filters ----
$filterStatus = (string)($_GET['status'] ?? '');
if ($filterStatus !== '' && !in_array($filterStatus, COMPLAINT_STATUSES, true)) {
    $filterStatus = '';
}
$onlyUnassigned = !empty($_GET['unassigned']);

// ---- Summary ----
$counts = fetch_one(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(assigned_to IS NULL AND status NOT IN ('Resolved','Closed')) AS unassigned,
            SUM(status NOT IN ('Resolved','Closed')) AS open_items,
            SUM(status = 'Escalated') AS escalated,
            SUM(status IN ('Resolved','Closed')) AS done
     FROM complaints"
);

$staffList = fetch_all(
    $conn,
    "SELECT u.user_id, u.full_name, d.department_name
     FROM users u LEFT JOIN departments d ON d.department_id = u.department_id
     WHERE u.role = 'staff' AND u.is_active = 1
     ORDER BY d.department_name, u.full_name"
);

$sql = 'SELECT c.complaint_id, c.ticket_number, c.title, c.status, c.priority, c.created_at, c.assigned_to,
               u.full_name AS complainant, d.department_name, cat.category_name, a.full_name AS assignee
        FROM complaints c
        JOIN users u ON u.user_id = c.user_id
        JOIN departments d ON d.department_id = c.department_id
        JOIN categories cat ON cat.category_id = c.category_id
        LEFT JOIN users a ON a.user_id = c.assigned_to
        WHERE 1 = 1';
$types = '';
$params = [];
if ($filterStatus !== '') {
    $sql .= ' AND c.status = ?';
    $types .= 's';
    $params[] = $filterStatus;
}
if ($onlyUnassigned) {
    $sql .= ' AND c.assigned_to IS NULL';
}
$sql .= ' ORDER BY c.created_at DESC, c.complaint_id DESC LIMIT 200';
$complaints = fetch_all($conn, $sql, $types, $params);

$pageTitle = 'Admin dashboard';
require __DIR__ . '/includes/header.php';
?>
<h1>Administrator dashboard</h1>

<div class="grid-stats">
    <div class="stat"><div class="num"><?= (int)$counts['total'] ?></div><div class="label">Total complaints</div></div>
    <div class="stat"><div class="num"><?= (int)$counts['open_items'] ?></div><div class="label">Open</div></div>
    <div class="stat"><div class="num"><?= (int)$counts['unassigned'] ?></div><div class="label">Waiting to be assigned</div></div>
    <div class="stat"><div class="num"><?= (int)$counts['escalated'] ?></div><div class="label">Escalated</div></div>
    <div class="stat"><div class="num"><?= (int)$counts['done'] ?></div><div class="label">Resolved or closed</div></div>
</div>

<form method="get" class="filters">
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (COMPLAINT_STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="check" style="margin-bottom:0">
        <input type="checkbox" id="unassigned" name="unassigned" value="1" <?= $onlyUnassigned ? 'checked' : '' ?>>
        <label for="unassigned">Only unassigned</label>
    </div>
    <button type="submit">Apply</button>
    <?php if ($filterStatus !== '' || $onlyUnassigned): ?><a class="btn btn-ghost" href="admin.php">Clear</a><?php endif; ?>
</form>

<?php if (!$staffList): ?>
    <div class="alert alert-info">There are no active staff accounts yet. <a href="admin_users.php">Add staff</a> so complaints can be assigned.</div>
<?php endif; ?>

<h2>All complaints</h2>
<?php if (!$complaints): ?>
    <div class="card"><p class="muted">No complaints match.</p></div>
<?php else: ?>
<div class="table-wrap">
<table>
    <tr><th>Reference</th><th>Title</th><th>Complainant</th><th>Department</th><th>Priority</th><th>Status</th><th>Submitted</th><th>Assigned to</th></tr>
    <?php foreach ($complaints as $c): ?>
    <?php $locked = in_array($c['status'], ['Resolved', 'Closed'], true); ?>
    <tr>
        <td><a href="complaint.php?id=<?= (int)$c['complaint_id'] ?>"><?= e($c['ticket_number']) ?></a></td>
        <td><?= e($c['title']) ?></td>
        <td><?= e($c['complainant']) ?></td>
        <td><?= e($c['department_name']) ?></td>
        <td><span class="badge <?= e(badge_class($c['priority'])) ?>"><?= e($c['priority']) ?></span></td>
        <td><span class="badge <?= e(badge_class($c['status'])) ?>"><?= e($c['status']) ?></span></td>
        <td class="small muted"><?= e(format_date($c['created_at'])) ?></td>
        <td>
            <?php if ($locked): ?>
                <?= e($c['assignee'] ?? '-') ?>
            <?php else: ?>
            <form method="post" class="inline-controls">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="complaint_id" value="<?= (int)$c['complaint_id'] ?>">
                <select name="staff_id" required>
                    <option value="">- choose staff -</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int)$s['user_id'] ?>" <?= (int)$c['assigned_to'] === (int)$s['user_id'] ? 'selected' : '' ?>>
                            <?= e($s['full_name']) ?><?= $s['department_name'] ? ' (' . e($s['department_name']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-small"><?= $c['assigned_to'] ? 'Reassign' : 'Assign' ?></button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>