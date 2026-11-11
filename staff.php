<?php
/**
 * Staff dashboard: the complaints assigned to the logged-in staff member.
 * Status updates, replies and notes are done on each complaint's own page.
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('staff');

$filter = (string)($_GET['status'] ?? '');
if ($filter !== '' && !in_array($filter, COMPLAINT_STATUSES, true)) {
    $filter = '';
}

// Counts for the summary cards
$counts = fetch_one(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(status IN ('Assigned','Received','Submitted')) AS new_items,
            SUM(status = 'In Progress') AS in_progress,
            SUM(status = 'Escalated') AS escalated,
            SUM(status IN ('Resolved','Closed')) AS done
     FROM complaints WHERE assigned_to = ?",
    'i',
    [$user['id']]
);

$sql = 'SELECT c.complaint_id, c.ticket_number, c.title, c.status, c.priority, c.created_at,
               u.full_name AS complainant, d.department_name, cat.category_name
        FROM complaints c
        JOIN users u ON u.user_id = c.user_id
        JOIN departments d ON d.department_id = c.department_id
        JOIN categories cat ON cat.category_id = c.category_id
        WHERE c.assigned_to = ?';
$types = 'i';
$params = [$user['id']];
if ($filter !== '') {
    $sql .= ' AND c.status = ?';
    $types .= 's';
    $params[] = $filter;
}
$sql .= " ORDER BY FIELD(c.priority, 'Urgent', 'High', 'Medium', 'Low'), c.created_at ASC";
$complaints = fetch_all($conn, $sql, $types, $params);

$pageTitle = 'Staff dashboard';
require __DIR__ . '/includes/header.php';
?>
<h1>Staff dashboard</h1>

<div class="grid-stats">
    <div class="stat"><div class="num"><?= (int)$counts['total'] ?></div><div class="label">Assigned to me</div></div>
    <div class="stat"><div class="num"><?= (int)$counts['new_items'] ?></div><div class="label">New</div></div>
    <div class="stat"><div class="num"><?= (int)$counts['in_progress'] ?></div><div class="label">In progress</div></div>
    <div class="stat"><div class="num"><?= (int)$counts['escalated'] ?></div><div class="label">Escalated</div></div>
    <div class="stat"><div class="num"><?= (int)$counts['done'] ?></div><div class="label">Resolved or closed</div></div>
</div>

<form method="get" class="filters">
    <div class="field">
        <label for="status">Filter by status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (COMPLAINT_STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= $filter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit">Apply</button>
    <?php if ($filter !== ''): ?><a class="btn btn-ghost" href="staff.php">Clear</a><?php endif; ?>
</form>

<?php if (!$complaints): ?>
    <div class="card"><p class="muted">No complaints to show.</p></div>
<?php else: ?>
<div class="table-wrap">
<table>
    <tr><th>Reference</th><th>Title</th><th>Complainant</th><th>Department</th><th>Priority</th><th>Status</th><th>Submitted</th><th></th></tr>
    <?php foreach ($complaints as $c): ?>
    <tr>
        <td><a href="complaint.php?id=<?= (int)$c['complaint_id'] ?>"><?= e($c['ticket_number']) ?></a></td>
        <td><?= e($c['title']) ?></td>
        <td><?= e($c['complainant']) ?></td>
        <td><?= e($c['department_name']) ?></td>
        <td><span class="badge <?= e(badge_class($c['priority'])) ?>"><?= e($c['priority']) ?></span></td>
        <td><span class="badge <?= e(badge_class($c['status'])) ?>"><?= e($c['status']) ?></span></td>
        <td class="small muted"><?= e(format_date($c['created_at'])) ?></td>
        <td><a class="btn btn-small" href="complaint.php?id=<?= (int)$c['complaint_id'] ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>