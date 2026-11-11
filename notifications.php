<?php
/**
 * In-app notifications for every role.
 * Unread alerts are highlighted and counted in the navigation bar.
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        db_execute($conn, 'UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?', 'ii', [(int)($_POST['id'] ?? 0), $user['id']]);
    } elseif ($action === 'mark_all') {
        db_execute($conn, 'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', 'i', [$user['id']]);
        flash('success', 'All notifications marked as read.');
    }
    redirect('notifications.php');
}

$notifications = fetch_all(
    $conn,
    'SELECT notification_id, complaint_id, message, is_read, created_at
     FROM notifications WHERE user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 100',
    'i',
    [$user['id']]
);
$unread = 0;
foreach ($notifications as $n) {
    if (!(int)$n['is_read']) { $unread++; }
}

$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<h1>Notifications</h1>

<div class="card">
<?php if (!$notifications): ?>
    <p class="muted">You have no notifications yet.</p>
<?php else: ?>
    <?php if ($unread > 0): ?>
    <form method="post" style="margin-bottom:10px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all">
        <button type="submit" class="btn-small">Mark all as read (<?= (int)$unread ?>)</button>
    </form>
    <?php endif; ?>
    <div class="table-wrap">
    <table>
        <tr><th>Message</th><th>Received</th><th></th></tr>
        <?php foreach ($notifications as $n): ?>
        <tr class="<?= (int)$n['is_read'] ? '' : 'unread' ?>">
            <td>
                <?= (int)$n['is_read'] ? '' : '<span class="badge badge-in-progress">New</span> ' ?>
                <?= e($n['message']) ?>
                <?php if ($n['complaint_id']): ?> <a href="complaint.php?id=<?= (int)$n['complaint_id'] ?>">View complaint</a><?php endif; ?>
            </td>
            <td class="small muted"><?= e(format_date($n['created_at'])) ?></td>
            <td>
                <?php if (!(int)$n['is_read']): ?>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" value="<?= (int)$n['notification_id'] ?>">
                    <button type="submit" class="btn-small btn-ghost">Mark read</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>