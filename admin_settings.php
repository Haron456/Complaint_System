<?php
/**
 * Escalation settings for the administrator: how many hours each priority may
 * stay unresolved before it is escalated, and a button to run the check now.
 */
require __DIR__ . '/includes/bootstrap.php';
$admin = require_role('admin');

$order = ['Urgent', 'High', 'Medium', 'Low'];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_rules') {
        $hours = [];
        $bad = false;
        foreach ($order as $p) {
            $h = (int)($_POST['hours'][$p] ?? 0);
            if ($h < 1 || $h > 2160) {
                $bad = true;
            }
            $hours[$p] = $h;
        }
        if ($bad) {
            flash('error', 'Each time must be a whole number of hours between 1 and 2160 (90 days).');
        } else {
            foreach ($hours as $p => $h) {
                db_execute($conn, 'UPDATE escalation_rules SET escalate_after_hours = ? WHERE priority = ?', 'is', [$h, $p]);
            }
            log_activity($conn, $admin['id'], 'escalation_rules_changed', json_encode($hours));
            flash('success', 'The escalation times have been saved.');
        }
        redirect('admin_settings.php');
    }

    if ($action === 'run_escalation') {
        $n = escalate_overdue_complaints($conn);
        if ($n > 0) {
            log_activity($conn, $admin['id'], 'manual_escalation', sprintf('%d complaint(s) escalated', $n));
            flash('success', sprintf('%d complaint(s) escalated.', $n));
        } else {
            flash('info', 'No complaints are overdue.');
        }
        redirect('admin_settings.php');
    }

    redirect('admin_settings.php');
}

$rows = fetch_all($conn, 'SELECT priority, escalate_after_hours FROM escalation_rules');
$hoursFor = [];
foreach ($rows as $r) {
    $hoursFor[$r['priority']] = (int)$r['escalate_after_hours'];
}

$pageTitle = 'Settings';
require __DIR__ . '/includes/header.php';
?>
<h1>Settings</h1>

<div class="card">
    <h3>Escalation times</h3>
    <p class="muted">A complaint that is not resolved within this many hours of being submitted is escalated to senior staff. A complaint is escalated only once.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_rules">
        <div class="form-row">
            <?php foreach ($order as $p): ?>
            <div class="field">
                <label for="h_<?= e($p) ?>"><?= e($p) ?> priority (hours)</label>
                <input type="number" id="h_<?= e($p) ?>" name="hours[<?= e($p) ?>]" min="1" max="2160" value="<?= (int)($hoursFor[$p] ?? 96) ?>" required>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit">Save times</button>
    </form>
</div>

<div class="card">
    <h3>Run the escalation check</h3>
    <p class="muted">Looks for unresolved complaints that have passed their time and escalates them now. Mark at least one staff member as <strong>senior</strong> in each department (Users, Edit) so escalated complaints have somewhere to go.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="run_escalation">
        <button type="submit" class="btn-ghost">Run escalation check now</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>