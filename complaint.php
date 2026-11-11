<?php
/**
 * One complaint, for every role. Shows the details, status history and
 * responses. Depending on the role it also offers:
 *   students    - rate the service once the complaint is resolved
 *   staff/admin - update status, reply to the student, add an internal note
 * Access is checked on every request.
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$complaint = $id ? complaint_by_id($conn, $id) : null;

if (!$complaint || !can_view_complaint($user, $complaint)) {
    flash('error', 'That complaint could not be found.');
    redirect(dashboard_for($user['role']));
}

$canManage = can_manage_complaint($user, $complaint);
$isOwner = $user['role'] === 'student' && (int)$complaint['user_id'] === $user['id'];
$self = 'complaint.php?id=' . $id;

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';

        // ---- Change priority ----
    if ($action === 'set_priority' && $canManage) {
        $newPriority = (string)($_POST['priority'] ?? '');
        if (!in_array($newPriority, COMPLAINT_PRIORITIES, true)) {
            flash('error', 'Choose a valid priority.');
        } elseif ($newPriority !== $complaint['priority']) {
            db_execute($conn, 'UPDATE complaints SET priority = ? WHERE complaint_id = ?', 'si', [$newPriority, $id]);
            log_activity($conn, $user['id'], 'priority_changed', sprintf('%s: %s -> %s', $complaint['ticket_number'], $complaint['priority'], $newPriority));
            flash('success', 'Priority changed to ' . $newPriority . '.');
        }
        redirect($self);
    }

    // ---- Update status ----
    if ($action === 'update_status' && $canManage) {
        $newStatus = (string)($_POST['new_status'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if (!in_array($newStatus, allowed_next_statuses($user, $complaint), true)) {
            flash('error', 'That status change is not allowed.');
        } else {
            change_complaint_status($conn, $complaint, $newStatus, $user['id'], $note !== '' ? mb_substr($note, 0, 1000) : null);
            flash('success', 'Status updated to ' . $newStatus . '.');
        }
        redirect($self);
    }

    // ---- Escalate to a senior staff member (possibly in another department) ----
    if ($action === 'escalate' && $canManage) {
        $seniorId = (int)($_POST['senior_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '' || mb_strlen($reason) > 500) {
            flash('error', 'Give a short reason for escalating (up to 500 characters).');
            redirect($self);
        }
        $problem = escalate_complaint_manually($conn, $complaint, $seniorId, $user['id'], $reason);
        if ($problem !== null) {
            flash('error', $problem);
            redirect($self);
        }
        flash('success', 'The complaint has been escalated.');
        // The assigned staff member no longer handles it, so send them back to their dashboard.
        redirect($user['role'] === 'admin' ? $self : dashboard_for($user['role']));
    }
    // ---- Reply to the student or add an internal note ----
    if ($action === 'add_response' && $canManage) {
        $message = trim($_POST['message'] ?? '');
        $internal = (($_POST['visibility'] ?? '') === 'internal') ? 1 : 0;
        if ($message === '' || mb_strlen($message) > 3000) {
            flash('error', 'Enter a message (up to 3000 characters).');
        } else {
            db_execute(
                $conn,
                'INSERT INTO complaint_responses (complaint_id, user_id, message, is_internal) VALUES (?, ?, ?, ?)',
                'iisi',
                [$id, $user['id'], $message, $internal]
            );
            if (!$internal) {
                notify($conn, (int)$complaint['user_id'], $id, sprintf('There is a new response on your complaint %s.', $complaint['ticket_number']));
                email_user(
                    $conn,
                    (int)$complaint['user_id'],
                    sprintf('New response on complaint %s', $complaint['ticket_number']),
                    sprintf("There is a new response on your complaint %s:\n\n%s", $complaint['ticket_number'], $message),
                    $id
                );
            }
            log_activity($conn, $user['id'], $internal ? 'internal_note_added' : 'response_added', $complaint['ticket_number']);
            flash('success', $internal ? 'Internal note saved.' : 'Your response has been sent to the student.');
        }
        redirect($self);
    }

    // ---- Student rating ----
    if ($action === 'rate' && $isOwner) {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comments'] ?? '');
        if (!in_array($complaint['status'], ['Resolved', 'Closed'], true)) {
            flash('error', 'You can rate the service once the complaint is resolved.');
        } elseif ($rating < 1 || $rating > 5) {
            flash('error', 'Choose a rating from 1 to 5.');
        } elseif (mb_strlen($comment) > 1000) {
            flash('error', 'The comment is too long (up to 1000 characters).');
        } elseif (fetch_one($conn, 'SELECT feedback_id FROM feedback WHERE complaint_id = ?', 'i', [$id])) {
            flash('error', 'You have already rated this complaint.');
        } else {
            try {
                db_execute(
                    $conn,
                    'INSERT INTO feedback (complaint_id, user_id, rating, comments) VALUES (?, ?, ?, ?)',
                    'iiis',
                    [$id, $user['id'], $rating, $comment !== '' ? $comment : null]
                );
                log_activity($conn, $user['id'], 'feedback_submitted', $complaint['ticket_number']);
                flash('success', 'Thank you for your feedback.');
            } catch (mysqli_sql_exception $ex) {
                if ((int)$ex->getCode() === 1062) {   // rated at the same moment from another tab
                    flash('error', 'You have already rated this complaint.');
                } else {
                    throw $ex;
                }
            }
        }
        redirect($self);
    }

    redirect($self);
}

// ---- Data for display ----
$history = fetch_all(
    $conn,
    'SELECT h.previous_status, h.new_status, h.notes, h.updated_at, u.full_name
     FROM status_history h LEFT JOIN users u ON u.user_id = h.updated_by
     WHERE h.complaint_id = ? ORDER BY h.updated_at DESC, h.history_id DESC',
    'i',
    [$id]
);

$responseSql = 'SELECT r.message, r.is_internal, r.created_at, u.full_name, u.role
                FROM complaint_responses r LEFT JOIN users u ON u.user_id = r.user_id
                WHERE r.complaint_id = ?' . ($user['role'] === 'student' ? ' AND r.is_internal = 0' : '') . '
                ORDER BY r.created_at ASC, r.response_id ASC';
$responses = fetch_all($conn, $responseSql, 'i', [$id]);

$feedback = fetch_one($conn, 'SELECT rating, comments, submitted_at FROM feedback WHERE complaint_id = ?', 'i', [$id]);
$nextStatuses = $canManage ? allowed_next_statuses($user, $complaint) : [];
$canEscalate = $canManage && !in_array($complaint['status'], ['Resolved', 'Closed', 'Escalated'], true);
$seniors = $canEscalate ? fetch_all(
    $conn,
    "SELECT u.user_id, u.full_name, d.department_name
     FROM users u LEFT JOIN departments d ON d.department_id = u.department_id
     WHERE u.role = 'staff' AND u.is_senior = 1 AND u.is_active = 1 AND u.user_id <> ?
     ORDER BY d.department_name, u.full_name",
    'i',
    [(int)($complaint['assigned_to'] ?? 0)]
) : [];

$pageTitle = $complaint['ticket_number'];
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(dashboard_for($user['role'])) ?>">&larr; Back to dashboard</a></p>

<div class="card">
    <h1><?= e($complaint['ticket_number']) ?> &mdash; <?= e($complaint['title']) ?></h1>
    <div class="detail-grid">
        <div><div class="k">Status</div><span class="badge <?= e(badge_class($complaint['status'])) ?>"><?= e($complaint['status']) ?></span></div>
        <div><div class="k">Priority</div><span class="badge <?= e(badge_class($complaint['priority'])) ?>"><?= e($complaint['priority']) ?></span></div>
        <div><div class="k">Department</div><?= e($complaint['department_name']) ?></div>
        <div><div class="k">Category</div><?= e($complaint['category_name']) ?></div>
        <div><div class="k">Submitted</div><?= e(format_date($complaint['created_at'])) ?></div>
        <div><div class="k">Handled by</div><?= e($complaint['assignee_name'] ?? 'Not yet assigned') ?></div>
        <?php if ($user['role'] !== 'student'): ?>
            <div><div class="k">Complainant</div><?= e($complaint['complainant_name']) ?> (<?= e($complaint['complainant_id_no'] ?? '-') ?>)<br><span class="small muted"><?= e($complaint['complainant_email']) ?></span></div>
        <?php endif; ?>
        <?php if ($complaint['resolved_at']): ?>
            <div><div class="k">Resolved</div><?= e(format_date($complaint['resolved_at'])) ?><?= $complaint['resolver_name'] ? ' by ' . e($complaint['resolver_name']) : '' ?></div>
        <?php endif; ?>
    </div>
    <div class="k small muted">DESCRIPTION</div>
    <p><?= nl2br(e($complaint['description'])) ?></p>
        <?php if (!empty($complaint['attachment'])): ?>
        <p><a class="btn btn-small btn-ghost" href="download.php?id=<?= (int)$complaint['complaint_id'] ?>" target="_blank">Open attachment: <?= e($complaint['attachment_name'] ?? 'file') ?></a></p>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="card">
    <h3>Update status</h3>
    <?php if ($nextStatuses): ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="form-row">
            <div class="field">
                <label for="new_status">New status</label>
                <select id="new_status" name="new_status">
                    <?php foreach ($nextStatuses as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="note">Note (optional, visible to staff only)</label>
                <input type="text" id="note" name="note" maxlength="1000">
            </div>
        </div>
        <button type="submit">Update status</button>
    </form>
    <?php else: ?>
        <p class="muted">No further status changes are available.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="card">
    <h3>Priority</h3>
    <form method="post" class="inline-controls">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_priority">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <select name="priority">
            <?php foreach (COMPLAINT_PRIORITIES as $p): ?>
                <option value="<?= e($p) ?>" <?= $complaint['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-small">Change priority</button>
    </form>
    <p class="muted small">Higher priorities are escalated sooner if they are not resolved in time.</p>
</div>
<?php endif; ?>

<?php if ($canEscalate): ?>
<div class="card">
    <h3>Escalate to senior staff</h3>
    <?php if (!$seniors): ?>
        <p class="muted">No senior staff members are available. An administrator can mark staff as senior under Users, then Edit.</p>
    <?php else: ?>
    <form method="post" onsubmit="return confirm('Escalate this complaint? It will be passed to the senior staff member you chose.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="escalate">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="form-row">
            <div class="field">
                <label for="senior_id">Senior staff member</label>
                <select id="senior_id" name="senior_id" required>
                    <option value="">- choose -</option>
                    <?php foreach ($seniors as $s): ?>
                        <option value="<?= (int)$s['user_id'] ?>"><?= e($s['full_name']) ?><?= $s['department_name'] ? ' (' . e($s['department_name']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="reason">Reason for escalating</label>
                <input type="text" id="reason" name="reason" maxlength="500" required>
            </div>
        </div>
        <button type="submit">Escalate</button>
        <span class="muted small">Choosing a senior from another department moves the complaint to that department.</span>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<h2>Responses</h2>
<?php if (!$responses): ?>
    <p class="muted">No responses yet.</p>
<?php endif; ?>
<?php foreach ($responses as $r): ?>
    <div class="response <?= (int)$r['is_internal'] ? 'internal' : '' ?>">
        <div class="meta">
            <?= e($r['full_name'] ?? 'Staff') ?> &middot; <?= e(format_date($r['created_at'])) ?>
            <?php if ((int)$r['is_internal']): ?><span class="badge badge-internal">Internal note</span><?php endif; ?>
        </div>
        <?= nl2br(e($r['message'])) ?>
    </div>
<?php endforeach; ?>

<?php if ($canManage): ?>
<div class="card">
    <h3>Reply or add a note</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_response">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="field">
            <label for="message">Message</label>
            <textarea id="message" name="message" maxlength="3000" required></textarea>
        </div>
        <div class="field">
            <label for="visibility">Who can see it</label>
            <select id="visibility" name="visibility" style="max-width:320px">
                <option value="public">Reply to the student</option>
                <option value="internal">Internal note (staff and admin only)</option>
            </select>
        </div>
        <button type="submit">Save</button>
    </form>
</div>
<?php endif; ?>

<h2>Rating</h2>
<?php if ($feedback): ?>
    <div class="card">
        <p><strong><?= (int)$feedback['rating'] ?> / 5</strong> <span class="muted small">&middot; <?= e(format_date($feedback['submitted_at'])) ?></span></p>
        <?php if ($feedback['comments'] !== null && $feedback['comments'] !== ''): ?><p><?= nl2br(e($feedback['comments'])) ?></p><?php endif; ?>
    </div>
<?php elseif ($isOwner && in_array($complaint['status'], ['Resolved', 'Closed'], true)): ?>
    <div class="card" id="rate">
        <h3>How was the service?</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="rate">
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div class="field">
                <label for="rating">Rating (1 = very poor, 5 = excellent)</label>
                <select id="rating" name="rating" required style="max-width:200px">
                    <option value="">- choose -</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="field">
                <label for="comments">Comment (optional)</label>
                <textarea id="comments" name="comments" maxlength="1000"></textarea>
            </div>
            <button type="submit">Submit rating</button>
        </form>
    </div>
<?php else: ?>
    <p class="muted">No rating yet<?= $isOwner ? '. You can rate the service once the complaint is resolved.' : '.' ?></p>
<?php endif; ?>

<h2>Status history</h2>
<div class="table-wrap">
<table>
    <tr><th>When</th><th>Change</th><?php if ($user['role'] !== 'student'): ?><th>By</th><th>Note</th><?php endif; ?></tr>
    <?php foreach ($history as $h): ?>
    <tr>
        <td class="small muted"><?= e(format_date($h['updated_at'])) ?></td>
        <td>
            <?= $h['previous_status'] ? e($h['previous_status']) . ' &rarr; ' : '' ?><span class="badge <?= e(badge_class($h['new_status'])) ?>"><?= e($h['new_status']) ?></span>
        </td>
        <?php if ($user['role'] !== 'student'): ?>
            <td><?= e($h['full_name'] ?? '-') ?></td>
            <td><?= e($h['notes'] ?? '') ?></td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>