<?php
/**
 * Reports for the administrator: complaints received in a period, breakdowns by
 * category, department and status, resolution rates and times, service ratings,
 * bar and pie charts, filters, and export to CSV or PDF. The PDF comes from the
 * browser's Print, "Save as PDF", using the print layout in style.css.
 */
require __DIR__ . '/includes/bootstrap.php';
$admin = require_role('admin');

/** A Y-m-d date string, or null if it is not a real date. */
function valid_date(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

/** Minutes shown as minutes, hours or days. */
function fmt_duration($minutes): string
{
    if ($minutes === null) {
        return '-';
    }
    $m = (float)$minutes;
    if ($m < 60) {
        return round($m) . ' min';
    }
    if ($m < 2880) {
        return number_format($m / 60, 1) . ' hours';
    }
    return number_format($m / 1440, 1) . ' days';
}

/** Stops spreadsheet programs from running a cell that starts with = + - or @ as a formula. */
function csv_cell($value): string
{
    $s = (string)$value;
    return ($s !== '' && strpos('=+-@', $s[0]) !== false) ? "'" . $s : $s;
}

// ---- Filters ----
$to = valid_date($_GET['to'] ?? null) ?? date('Y-m-d');
$from = valid_date($_GET['from'] ?? null) ?? date('Y-m-d', strtotime('-30 days'));
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$deptList = fetch_all($conn, 'SELECT department_id, department_name FROM departments ORDER BY department_name');
$catList = fetch_all($conn, 'SELECT category_id, category_name FROM categories ORDER BY category_name');
$deptNames = array_column($deptList, 'department_name', 'department_id');
$catNames = array_column($catList, 'category_name', 'category_id');

$deptFilter = (int)($_GET['department_id'] ?? 0);
if (!isset($deptNames[$deptFilter])) {
    $deptFilter = 0;
}
$catFilter = (int)($_GET['category_id'] ?? 0);
if (!isset($catNames[$catFilter])) {
    $catFilter = 0;
}
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, COMPLAINT_STATUSES, true)) {
    $statusFilter = '';
}

// One WHERE clause shared by every query below (all values are bound, never pasted in).
$where = 'c.created_at >= ? AND c.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
$types = 'ss';
$params = [$from, $to];
if ($deptFilter) {
    $where .= ' AND c.department_id = ?';
    $types .= 'i';
    $params[] = $deptFilter;
}
if ($catFilter) {
    $where .= ' AND c.category_id = ?';
    $types .= 'i';
    $params[] = $catFilter;
}
if ($statusFilter !== '') {
    $where .= ' AND c.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}

// Same filters, as a query string for the export links.
$filterQuery = ['from' => $from, 'to' => $to];
if ($deptFilter) { $filterQuery['department_id'] = $deptFilter; }
if ($catFilter) { $filterQuery['category_id'] = $catFilter; }
if ($statusFilter !== '') { $filterQuery['status'] = $statusFilter; }

// ---- CSV export (must run before any page output) ----
if (($_GET['export'] ?? '') === 'csv') {
    $rows = fetch_all(
        $conn,
        "SELECT c.ticket_number, c.title, d.department_name, cat.category_name, c.priority, c.status,
                c.created_at, c.resolved_at, r.full_name AS resolver, f.rating
         FROM complaints c
         JOIN departments d ON d.department_id = c.department_id
         JOIN categories cat ON cat.category_id = c.category_id
         LEFT JOIN users r ON r.user_id = c.resolved_by
         LEFT JOIN feedback f ON f.complaint_id = c.complaint_id
         WHERE $where
         ORDER BY c.created_at",
        $types,
        $params
    );
    log_activity($conn, $admin['id'], 'report_exported', sprintf('CSV %s to %s (%d rows)', $from, $to, count($rows)));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="complaints_report_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // lets Excel read accents correctly
    fputcsv($out, ['Reference', 'Title', 'Department', 'Category', 'Priority', 'Status', 'Submitted', 'Resolved', 'Resolved by', 'Hours to resolve', 'Rating']);
    foreach ($rows as $r) {
        $hours = $r['resolved_at'] !== null ? round((strtotime($r['resolved_at']) - strtotime($r['created_at'])) / 3600, 1) : '';
        fputcsv($out, array_map('csv_cell', [
            $r['ticket_number'], $r['title'], $r['department_name'], $r['category_name'], $r['priority'], $r['status'],
            $r['created_at'], $r['resolved_at'] ?? '', $r['resolver'] ?? '', $hours, $r['rating'] ?? '',
        ]));
    }
    fclose($out);
    exit;
}

// ---- Report data ----
$summary = fetch_one(
    $conn,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(c.status IN ('Resolved', 'Closed')), 0) AS resolved,
            COALESCE(SUM(c.status = 'Escalated'), 0) AS escalated,
            AVG(CASE WHEN c.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) END) AS avg_minutes
     FROM complaints c WHERE $where",
    $types,
    $params
);
$total = (int)$summary['total'];
$resolved = (int)$summary['resolved'];
$rate = $total > 0 ? (int)round($resolved / $total * 100) : 0;

$statusCounts = [];
foreach (fetch_all($conn, "SELECT c.status AS label, COUNT(*) AS n FROM complaints c WHERE $where GROUP BY c.status", $types, $params) as $row) {
    $statusCounts[$row['label']] = (int)$row['n'];
}

$byCategory = fetch_all(
    $conn,
    "SELECT cat.category_name AS label, COUNT(*) AS total,
            SUM(c.status IN ('Resolved', 'Closed')) AS resolved,
            AVG(CASE WHEN c.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) END) AS avg_minutes
     FROM complaints c JOIN categories cat ON cat.category_id = c.category_id
     WHERE $where GROUP BY cat.category_id, cat.category_name ORDER BY total DESC",
    $types,
    $params
);
$byDepartment = fetch_all(
    $conn,
    "SELECT d.department_name AS label, COUNT(*) AS total,
            SUM(c.status IN ('Resolved', 'Closed')) AS resolved,
            AVG(CASE WHEN c.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) END) AS avg_minutes
     FROM complaints c JOIN departments d ON d.department_id = c.department_id
     WHERE $where GROUP BY d.department_id, d.department_name ORDER BY total DESC",
    $types,
    $params
);

$ratingSummary = fetch_one(
    $conn,
    "SELECT COUNT(*) AS n, AVG(f.rating) AS avg_rating
     FROM feedback f JOIN complaints c ON c.complaint_id = f.complaint_id WHERE $where",
    $types,
    $params
);
$ratingCounts = [];
foreach (fetch_all($conn, "SELECT f.rating AS stars, COUNT(*) AS n FROM feedback f JOIN complaints c ON c.complaint_id = f.complaint_id WHERE $where GROUP BY f.rating", $types, $params) as $row) {
    $ratingCounts[(int)$row['stars']] = (int)$row['n'];
}
$maxRating = $ratingCounts ? max($ratingCounts) : 0;

// Pie chart of statuses, drawn with a CSS conic-gradient.
$statusColors = [
    'Submitted' => '#9aa5c0', 'Received' => '#4a8fe0', 'Assigned' => '#8a6fd6', 'In Progress' => '#e3b93e',
    'Escalated' => '#d9534f', 'Resolved' => '#2e9e63', 'Closed' => '#6b7488',
];
$pieStyle = '';
if ($total > 0) {
    $stops = [];
    $acc = 0.0;
    foreach (COMPLAINT_STATUSES as $s) {
        $n = $statusCounts[$s] ?? 0;
        if ($n === 0) {
            continue;
        }
        $share = $n / $total * 100;
        $stops[] = sprintf('%s %.2F%% %.2F%%', $statusColors[$s], $acc, $acc + $share);
        $acc += $share;
    }
    $pieStyle = 'background: conic-gradient(' . implode(', ', $stops) . ');';
}

// Text describing the filters, shown at the top of the printed report.
$filterText = 'From ' . $from . ' to ' . $to;
if ($deptFilter) { $filterText .= ' | Department: ' . $deptNames[$deptFilter]; }
if ($catFilter) { $filterText .= ' | Category: ' . $catNames[$catFilter]; }
if ($statusFilter !== '') { $filterText .= ' | Status: ' . $statusFilter; }

$csvLink = 'admin_reports.php?' . http_build_query($filterQuery + ['export' => 'csv']);

$pageTitle = 'Reports';
require __DIR__ . '/includes/header.php';
?>
<h1>Reports</h1>
<p class="print-only">KCAU Complaint and Feedback System. <?= e($filterText) ?>. Printed <?= e(date('d M Y, H:i')) ?>.</p>

<form method="get" class="card no-print">
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
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id">
                <option value="">All</option>
                <?php foreach ($deptList as $d): ?>
                    <option value="<?= (int)$d['department_id'] ?>" <?= $deptFilter === (int)$d['department_id'] ? 'selected' : '' ?>><?= e($d['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
                <option value="">All</option>
                <?php foreach ($catList as $c): ?>
                    <option value="<?= (int)$c['category_id'] ?>" <?= $catFilter === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                <?php foreach (COMPLAINT_STATUSES as $s): ?>
                    <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button type="submit">Show report</button>
    <a class="btn btn-ghost" href="admin_reports.php">Reset</a>
    <a class="btn btn-ghost" href="<?= e($csvLink) ?>">Export CSV</a>
    <button type="button" class="btn-ghost" onclick="window.print()">Print / Save as PDF</button>
</form>

<div class="grid-stats">
    <div class="stat"><div class="num"><?= $total ?></div><div class="label">Complaints received</div></div>
    <div class="stat"><div class="num"><?= $resolved ?></div><div class="label">Resolved or closed</div></div>
    <div class="stat"><div class="num"><?= $rate ?>%</div><div class="label">Resolution rate</div></div>
    <div class="stat"><div class="num" style="font-size:20px;padding:5px 0"><?= e(fmt_duration($summary['avg_minutes'])) ?></div><div class="label">Average resolution time</div></div>
    <div class="stat"><div class="num"><?= (int)$summary['escalated'] ?></div><div class="label">Currently escalated</div></div>
    <div class="stat"><div class="num"><?= (int)$ratingSummary['n'] > 0 ? e(number_format((float)$ratingSummary['avg_rating'], 1)) : '-' ?></div><div class="label">Average rating (<?= (int)$ratingSummary['n'] ?> ratings)</div></div>
</div>

<?php if ($total === 0): ?>
    <div class="card"><p class="muted">No complaints were received in this period with these filters. Try a wider date range.</p></div>
<?php else: ?>

<h2>Complaints by status</h2>
<div class="card chart-row">
    <div class="pie" style="<?= e($pieStyle) ?>"></div>
    <ul class="legend">
        <?php foreach (COMPLAINT_STATUSES as $s): ?>
            <?php $n = $statusCounts[$s] ?? 0; if ($n === 0) { continue; } ?>
            <li><span class="swatch" style="background:<?= e($statusColors[$s]) ?>"></span><?= e($s) ?>: <strong><?= $n ?></strong> (<?= (int)round($n / $total * 100) ?>%)</li>
        <?php endforeach; ?>
    </ul>
</div>

<h2>By category</h2>
<div class="table-wrap">
<table>
    <tr><th>Category</th><th>Complaints</th><th></th><th>Resolved</th><th>Resolution rate</th><th>Average resolution time</th></tr>
    <?php $maxCat = max(array_map('intval', array_column($byCategory, 'total'))); ?>
    <?php foreach ($byCategory as $r): ?>
    <tr>
        <td><?= e($r['label']) ?></td>
        <td><?= (int)$r['total'] ?></td>
        <td style="width:30%"><div class="bar"><span style="width:<?= (int)round($r['total'] / $maxCat * 100) ?>%"></span></div></td>
        <td><?= (int)$r['resolved'] ?></td>
        <td><?= (int)round($r['resolved'] / $r['total'] * 100) ?>%</td>
        <td><?= e(fmt_duration($r['avg_minutes'])) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<h2>By department</h2>
<div class="table-wrap">
<table>
    <tr><th>Department</th><th>Complaints</th><th></th><th>Resolved</th><th>Resolution rate</th><th>Average resolution time</th></tr>
    <?php $maxDept = max(array_map('intval', array_column($byDepartment, 'total'))); ?>
    <?php foreach ($byDepartment as $r): ?>
    <tr>
        <td><?= e($r['label']) ?></td>
        <td><?= (int)$r['total'] ?></td>
        <td style="width:30%"><div class="bar"><span style="width:<?= (int)round($r['total'] / $maxDept * 100) ?>%"></span></div></td>
        <td><?= (int)$r['resolved'] ?></td>
        <td><?= (int)round($r['resolved'] / $r['total'] * 100) ?>%</td>
        <td><?= e(fmt_duration($r['avg_minutes'])) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<h2>Service ratings</h2>
<div class="card">
<?php if ((int)$ratingSummary['n'] === 0): ?>
    <p class="muted">No ratings were given for complaints in this period.</p>
<?php else: ?>
    <p>Average rating <strong><?= e(number_format((float)$ratingSummary['avg_rating'], 1)) ?> / 5</strong> from <?= (int)$ratingSummary['n'] ?> rating(s).</p>
    <table>
        <?php for ($stars = 5; $stars >= 1; $stars--): ?>
            <?php $n = $ratingCounts[$stars] ?? 0; ?>
            <tr>
                <td style="width:90px"><?= $stars ?> star<?= $stars > 1 ? 's' : '' ?></td>
                <td><div class="bar"><span style="width:<?= $maxRating > 0 ? (int)round($n / $maxRating * 100) : 0 ?>%"></span></div></td>
                <td style="width:60px"><?= $n ?></td>
            </tr>
        <?php endfor; ?>
    </table>
<?php endif; ?>
</div>

<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>