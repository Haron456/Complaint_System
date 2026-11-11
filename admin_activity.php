<?php
/**
 * Activity log for the administrator: an audit trail of who did what and when
 * (logins, account changes, assignments, status changes, escalations, exports).
 */
require __DIR__ . '/includes/bootstrap.php';
$admin = require_role('admin');

const LOG_PAGE_SIZE = 50;

/** Readable wording for the action codes stored in the log. */
const ACTION_LABELS = [
    'login'                    => 'Logged in',
    'logout'                   => 'Logged out',
    'registered'               => 'Registered an account',
    'installed'                => 'Set up the system',
    'password_changed'         => 'Changed own password',
    'password_reset'           => 'Reset a password',
    'user_created'             => 'Created an account',
    'user_updated'             => 'Updated an account',
    'user_activated'           => 'Reactivated an account',
    'user_deactivated'         => 'Deactivated an account',
    'complaint_submitted'      => 'Submitted a complaint',
    'complaint_assigned'       => 'Assigned a complaint',
    'complaint_reassigned'     => 'Reassigned a complaint',
    'status_changed'           => 'Changed a status',
    'priority_changed'         => 'Changed a priority',
    'response_added'           => 'Replied to a student',
    'internal_note_added'      => 'Added an internal note',
    'feedback_submitted'       => 'Rated the service',
    'complaint_escalated'      => 'Escalated a complaint',
    'auto_escalation'          => 'Automatic escalation',
    'manual_escalation'        => 'Ran the escalation check',
    'escalation_rules_changed' => 'Changed escalation times',
    'report_exported'          => 'Exported a report',
];

/** A Y-m-d date string, or null if it is not a real date. */
function log_date(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

// ---- Filters ----
$to = log_date($_GET['to'] ?? null) ?? date('Y-m-d');
$from = log_date($_GET['from'] ?? null) ?? date('Y-m-d', strtotime('-30 days'));
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$knownActions = array_column(fetch_all($conn, 'SELECT DISTINCT action FROM activity_log ORDER BY action'), 'action');
$actionFilter = (string)($_GET['action'] ?? '');
if (!in_array($actionFilter, $knownActions, true)) {
    $actionFilter = '';
}
$search = trim((string)($_GET['q'] ?? ''));

$where = 'l.created_at >= ? AND l.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
$types = 'ss';
$params = [$from, $to];
if ($actionFilter !== '') {
    $where .= ' AND l.action = ?';
    $types .= 's';
    $params[] = $actionFilter;
}
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR l.details LIKE ?)';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}

$count = fetch_one(
    $conn,
    "SELECT COUNT(*) AS n FROM activity_log l LEFT JOIN users u ON u.user_id = l.user_id WHERE $where",
    $types,
    $params
);
$total = (int)$count['n'];
$pages = max(1, (int)ceil($total / LOG_PAGE_SIZE));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * LOG_PAGE_SIZE;

$rows = fetch_all(
    $conn,
    "SELECT l.created_at, l.action, l.details, l.ip_address, u.full_name, u.role
     FROM activity_log l LEFT JOIN users u ON u.user_id = l.user_id
     WHERE $where
     ORDER BY l.log_id DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [LOG_PAGE_SIZE, $offset])
);

// Filters as a query string, for the page links.
$filterQuery = ['from' => $from, 'to' => $to];
if ($actionFilter !== '') { $filterQuery['action'] = $actionFilter; }
if ($search !== '') { $filterQuery['q'] = $search; }

$pageTitle = 'Activity log';
require __DIR__ . '/includes/header.php';
?>
<h1>Activity log</h1>

<form method="get" class="card">
    <div class="form-row">
        <div class="field">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>" required>
        </div>
        <div class="field">
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>" required>
        </div>
        <div class="field">
            <label for="action">Action</label>
            <select id="action" name="action">
                <option value="">All</option>
                <?php foreach ($knownActions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e(ACTION_LABELS[$a] ?? $a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="q">Search (name, email or details)</label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>" maxlength="100">
        </div>
    </div>
    <button type="submit">Show</button>
    <a class="btn btn-ghost" href="admin_activity.php">Reset</a>
</form>

<p class="muted"><?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?> found. Newest first.</p>

<?php if (!$rows): ?>
    <div class="card"><p class="muted">No activity matches these filters.</p></div>
<?php else: ?>
<div class="table-wrap">
<table>
    <tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP address</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td class="small muted"><?= e(format_date($r['created_at'])) ?></td>
        <td><?= $r['full_name'] !== null ? e($r['full_name']) . ' <span class="muted small">(' . e($r['role']) . ')</span>' : '<span class="muted">System</span>' ?></td>
        <td><?= e(ACTION_LABELS[$r['action']] ?? $r['action']) ?></td>
        <td class="small"><?= e($r['details'] ?? '') ?></td>
        <td class="small muted"><?= e($r['ip_address'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<?php if ($pages > 1): ?>
<p class="inline-controls">
    <?php if ($page > 1): ?>
        <a class="btn btn-small btn-ghost" href="admin_activity.php?<?= e(http_build_query($filterQuery + ['page' => $page - 1])) ?>">&larr; Newer</a>
    <?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
        <a class="btn btn-small btn-ghost" href="admin_activity.php?<?= e(http_build_query($filterQuery + ['page' => $page + 1])) ?>">Older &rarr;</a>
    <?php endif; ?>
</p>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>