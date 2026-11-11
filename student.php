<?php
/**
 * Student dashboard: submit a complaint (with an optional attachment), track by
 * reference number, and see the list of own complaints with their status.
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('student');

$departments = fetch_all($conn, 'SELECT department_id, department_name FROM departments ORDER BY department_name');
$categories = fetch_all($conn, 'SELECT category_id, category_name FROM categories ORDER BY category_name');
$deptIds = array_map('intval', array_column($departments, 'department_id'));
$catIds = array_map('intval', array_column($categories, 'category_id'));

$errors = [];
$old = ['department_id' => 0, 'category_id' => 0, 'title' => '', 'description' => ''];

// ---- Submit a complaint ----
if (is_post() && ($_POST['action'] ?? '') === 'submit_complaint') {
    csrf_check();
    $old['department_id'] = (int)($_POST['department_id'] ?? 0);
    $old['category_id'] = (int)($_POST['category_id'] ?? 0);
    $old['title'] = trim($_POST['title'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');

    if (!in_array($old['department_id'], $deptIds, true)) {
        $errors[] = 'Choose the department concerned.';
    }
    if (!in_array($old['category_id'], $catIds, true)) {
        $errors[] = 'Choose a complaint category.';
    }
    if ($old['title'] === '' || mb_strlen($old['title']) > 150) {
        $errors[] = 'Enter a short title (up to 150 characters).';
    }
    if ($old['description'] === '' || mb_strlen($old['description']) > 5000) {
        $errors[] = 'Describe the complaint (up to 5000 characters).';
    }

    // Optional attachment: checked now, saved only if everything else is valid.
    $stored = null;
    [$upload, $uploadError] = check_attachment($_FILES['attachment'] ?? null);
    if ($uploadError) {
        $errors[] = $uploadError;
    }
    if (!$errors && $upload) {
        $stored = store_attachment($upload);
        if ($stored === null) {
            $errors[] = 'The file could not be saved on the server. Please try again, or submit without the file.';
        }
    }

    if (!$errors) {
        $placeholder = 'TMP-' . bin2hex(random_bytes(6));   // replaced below once the ID is known
        $conn->begin_transaction();
        try {
            db_execute(
                $conn,
                'INSERT INTO complaints (ticket_number, user_id, department_id, category_id, title, description, attachment, attachment_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                'siiissss',
                [$placeholder, $user['id'], $old['department_id'], $old['category_id'], $old['title'], $old['description'], $stored, $upload['original'] ?? null]
            );
            $complaintId = (int)$conn->insert_id;
            $ticket = 'TICK-' . str_pad((string)$complaintId, 4, '0', STR_PAD_LEFT);
            db_execute($conn, 'UPDATE complaints SET ticket_number = ? WHERE complaint_id = ?', 'si', [$ticket, $complaintId]);

            add_status_history($conn, $complaintId, null, 'Submitted', $user['id'], 'Complaint submitted');
            notify($conn, $user['id'], $complaintId, sprintf('We have received your complaint. Reference number: %s.', $ticket));
            foreach (fetch_all($conn, "SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1") as $adm) {
                notify($conn, (int)$adm['user_id'], $complaintId, sprintf('New complaint %s needs to be assigned.', $ticket));
            }
            log_activity($conn, $user['id'], 'complaint_submitted', $ticket);
            $conn->commit();
        } catch (Throwable $ex) {
            $conn->rollback();
            if ($stored !== null) {
                delete_attachment($stored);   // do not leave an orphan file behind
            }
            throw $ex;
        }

        // Saved successfully: confirm by email.
        email_user(
            $conn,
            $user['id'],
            sprintf('Complaint %s received', $ticket),
            sprintf('We have received your complaint "%s". Your reference number is %s. You will be notified by email and in the system as it progresses.', $old['title'], $ticket),
            $complaintId
        );

        flash('success', sprintf('Your complaint has been received. Reference number: %s. You can track it from your dashboard.', $ticket));
        redirect('student.php');
    }
}

// ---- Track by reference number ----
$trackMessage = '';
if (isset($_GET['ticket']) && trim($_GET['ticket']) !== '') {
    $ticketSearch = strtoupper(trim($_GET['ticket']));
    $found = fetch_one($conn, 'SELECT complaint_id FROM complaints WHERE ticket_number = ? AND user_id = ?', 'si', [$ticketSearch, $user['id']]);
    if ($found) {
        redirect('complaint.php?id=' . (int)$found['complaint_id']);
    }
    $trackMessage = 'No complaint of yours was found with that reference number.';
}

// ---- Own complaints ----
$complaints = fetch_all(
    $conn,
    'SELECT c.complaint_id, c.ticket_number, c.title, c.status, c.priority, c.created_at,
            d.department_name, cat.category_name, f.feedback_id
     FROM complaints c
     JOIN departments d ON d.department_id = c.department_id
     JOIN categories cat ON cat.category_id = c.category_id
     LEFT JOIN feedback f ON f.complaint_id = c.complaint_id
     WHERE c.user_id = ?
     ORDER BY c.created_at DESC, c.complaint_id DESC',
    'i',
    [$user['id']]
);

$pageTitle = 'Student dashboard';
require __DIR__ . '/includes/header.php';
?>
<h1>Welcome, <?= e($user['name']) ?></h1>

<div class="card">
    <h3>Track a complaint</h3>
    <form method="get" class="inline-controls">
        <input type="text" name="ticket" placeholder="Reference number, e.g. TICK-0001" value="<?= e($_GET['ticket'] ?? '') ?>" required style="min-width:260px">
        <button type="submit">Check status</button>
    </form>
    <?php if ($trackMessage): ?><p style="color:var(--bad)"><?= e($trackMessage) ?></p><?php endif; ?>
</div>

<div class="card">
    <h3>Submit a complaint</h3>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit_complaint">
        <div class="form-row">
            <div class="field">
                <label for="department_id">Department concerned</label>
                <select id="department_id" name="department_id" required>
                    <option value="">- choose -</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>" <?= $old['department_id'] === (int)$d['department_id'] ? 'selected' : '' ?>><?= e($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">- choose -</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['category_id'] ?>" <?= $old['category_id'] === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" maxlength="150" required>
        </div>
        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5" maxlength="5000" required><?= e($old['description']) ?></textarea>
        </div>
        <div class="field">
            <label for="attachment">Attachment (optional: PDF, JPG or PNG, up to 2 MB)</label>
            <input type="file" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
        </div>
        <button type="submit">Submit complaint</button>
    </form>
</div>

<h2>My complaints (<?= count($complaints) ?>)</h2>
<?php if (!$complaints): ?>
    <p class="muted">You have not submitted any complaints yet.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <tr><th>Reference</th><th>Title</th><th>Department</th><th>Category</th><th>Status</th><th>Submitted</th><th></th></tr>
    <?php foreach ($complaints as $c): ?>
    <tr>
        <td><a href="complaint.php?id=<?= (int)$c['complaint_id'] ?>"><?= e($c['ticket_number']) ?></a></td>
        <td><?= e($c['title']) ?></td>
        <td><?= e($c['department_name']) ?></td>
        <td><?= e($c['category_name']) ?></td>
        <td><span class="badge <?= e(badge_class($c['status'])) ?>"><?= e($c['status']) ?></span></td>
        <td class="small muted"><?= e(format_date($c['created_at'])) ?></td>
        <td>
            <?php if (in_array($c['status'], ['Resolved', 'Closed'], true) && !$c['feedback_id']): ?>
                <a class="btn btn-small" href="complaint.php?id=<?= (int)$c['complaint_id'] ?>#rate">Rate service</a>
            <?php else: ?>
                <a class="btn btn-small btn-ghost" href="complaint.php?id=<?= (int)$c['complaint_id'] ?>">View</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>