<?php
/**
 * Complaint business rules shared by several pages:
 * loading a complaint, access checks, status changes and assignment.
 */

/** Load one complaint with the names needed for display, or null. */
function complaint_by_id(mysqli $conn, int $id): ?array
{
    return fetch_one(
        $conn,
        'SELECT c.*,
                u.full_name AS complainant_name, u.email AS complainant_email, u.student_staff_id AS complainant_id_no,
                d.department_name, cat.category_name,
                a.full_name AS assignee_name, r.full_name AS resolver_name
         FROM complaints c
         JOIN users u ON u.user_id = c.user_id
         JOIN departments d ON d.department_id = c.department_id
         JOIN categories cat ON cat.category_id = c.category_id
         LEFT JOIN users a ON a.user_id = c.assigned_to
         LEFT JOIN users r ON r.user_id = c.resolved_by
         WHERE c.complaint_id = ? LIMIT 1',
        'i',
        [$id]
    );
}

/** Who may open a complaint: its owner, the assigned staff member, or any admin. */
function can_view_complaint(array $user, array $complaint): bool
{
    if ($user['role'] === 'admin') {
        return true;
    }
    if ($user['role'] === 'student') {
        return (int)$complaint['user_id'] === $user['id'];
    }
    if ($user['role'] === 'staff') {
        return (int)$complaint['assigned_to'] === $user['id'];
    }
    return false;
}

/** Who may change a complaint: its assigned staff member, or any admin. */
function can_manage_complaint(array $user, array $complaint): bool
{
    return $user['role'] === 'admin'
        || ($user['role'] === 'staff' && (int)$complaint['assigned_to'] === $user['id']);
}

/** Statuses the given user may move this complaint to. */
function allowed_next_statuses(array $user, array $complaint): array
{
    $options = $user['role'] === 'admin'
        ? COMPLAINT_STATUSES
        : ['Received', 'In Progress', 'Resolved', 'Closed'];

    // Only a resolved complaint can be closed.
    if ($complaint['status'] !== 'Resolved') {
        $options = array_diff($options, ['Closed']);
    }
    return array_values(array_diff($options, [$complaint['status']]));
}

/**
 * Move a complaint to a new status. Records the history row, tells the
 * complainant (in-app and by email) and writes the activity log. The database
 * changes happen in one transaction; the email goes out only after they are saved.
 */
function change_complaint_status(mysqli $conn, array $complaint, string $newStatus, ?int $actorId, ?string $note = null): void
{
    if (!in_array($newStatus, COMPLAINT_STATUSES, true)) {
        throw new InvalidArgumentException('Unknown status: ' . $newStatus);
    }
    $oldStatus = $complaint['status'];
    if ($oldStatus === $newStatus) {
        return;
    }
    $id = (int)$complaint['complaint_id'];

    $conn->begin_transaction();
    try {
        if ($newStatus === 'Resolved') {
            db_execute($conn, 'UPDATE complaints SET status = ?, resolved_at = NOW(), resolved_by = ? WHERE complaint_id = ?', 'sii', [$newStatus, $actorId, $id]);
        } elseif ($newStatus === 'Closed') {
            db_execute($conn, 'UPDATE complaints SET status = ?, closed_at = NOW() WHERE complaint_id = ?', 'si', [$newStatus, $id]);
        } elseif ($newStatus === 'Escalated') {
            db_execute($conn, 'UPDATE complaints SET status = ?, escalated_at = NOW() WHERE complaint_id = ?', 'si', [$newStatus, $id]);
        } elseif ($oldStatus === 'Resolved' || $oldStatus === 'Closed') {
            // Re-opened: clear the resolution details.
            db_execute($conn, 'UPDATE complaints SET status = ?, resolved_at = NULL, resolved_by = NULL, closed_at = NULL WHERE complaint_id = ?', 'si', [$newStatus, $id]);
        } else {
            db_execute($conn, 'UPDATE complaints SET status = ? WHERE complaint_id = ?', 'si', [$newStatus, $id]);
        }

        add_status_history($conn, $id, $oldStatus, $newStatus, $actorId, $note);
        notify($conn, (int)$complaint['user_id'], $id, sprintf('Your complaint %s is now: %s.', $complaint['ticket_number'], $newStatus));
        log_activity($conn, $actorId, 'status_changed', sprintf('%s: %s -> %s', $complaint['ticket_number'], $oldStatus, $newStatus));
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }

    // Saved successfully: now email the student.
    email_user(
        $conn,
        (int)$complaint['user_id'],
        sprintf('Complaint %s: %s', $complaint['ticket_number'], $newStatus),
        status_email_message($complaint['ticket_number'], $newStatus),
        $id
    );
}

/**
 * Assign (or reassign) a complaint to a staff member. Returns false if the
 * chosen person is not an active staff account. The staff member is emailed,
 * and so is the student if the status moved to Assigned.
 */
function assign_complaint(mysqli $conn, array $complaint, int $staffId, ?int $actorId): bool
{
    $staff = fetch_one($conn, "SELECT user_id, full_name FROM users WHERE user_id = ? AND role = 'staff' AND is_active = 1", 'i', [$staffId]);
    if (!$staff) {
        return false;
    }
    $id = (int)$complaint['complaint_id'];
    $oldStatus = $complaint['status'];
    $newStatus = in_array($oldStatus, ['Submitted', 'Received'], true) ? 'Assigned' : $oldStatus;
    $isReassign = !empty($complaint['assigned_to']);

    $conn->begin_transaction();
    try {
        db_execute($conn, 'UPDATE complaints SET assigned_to = ?, status = ? WHERE complaint_id = ?', 'isi', [$staffId, $newStatus, $id]);

        if ($newStatus !== $oldStatus) {
            add_status_history($conn, $id, $oldStatus, $newStatus, $actorId, 'Assigned to ' . $staff['full_name']);
            notify($conn, (int)$complaint['user_id'], $id, sprintf('Your complaint %s has been assigned to a staff member.', $complaint['ticket_number']));
        }
        notify($conn, $staffId, $id, sprintf('Complaint %s has been %s to you.', $complaint['ticket_number'], $isReassign ? 'reassigned' : 'assigned'));
        log_activity($conn, $actorId, $isReassign ? 'complaint_reassigned' : 'complaint_assigned', sprintf('%s -> %s', $complaint['ticket_number'], $staff['full_name']));
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }

    // Saved successfully: now send the emails.
    email_user(
        $conn,
        $staffId,
        sprintf('Complaint %s assigned to you', $complaint['ticket_number']),
        sprintf('Complaint %s ("%s") has been %s to you. Please log in to review it.', $complaint['ticket_number'], $complaint['title'], $isReassign ? 'reassigned' : 'assigned'),
        $id
    );
    if ($newStatus !== $oldStatus) {
        email_user(
            $conn,
            (int)$complaint['user_id'],
            sprintf('Complaint %s: %s', $complaint['ticket_number'], $newStatus),
            status_email_message($complaint['ticket_number'], $newStatus),
            $id
        );
    }
    return true;
}

/**
 * Escalate every complaint that has stayed unresolved longer than the time
 * allowed for its priority (the hours in escalation_rules). A complaint is
 * escalated only once. It moves to a senior staff member of its department
 * (the one with the fewest open complaints), the student is told, and the
 * admins and the previous handler are notified. Returns how many were escalated.
 */
function escalate_overdue_complaints(mysqli $conn, int $limit = 20): int
{
    $due = fetch_all(
        $conn,
        "SELECT c.complaint_id, r.escalate_after_hours
         FROM complaints c
         JOIN escalation_rules r ON r.priority = c.priority
         WHERE c.status NOT IN ('Resolved', 'Closed', 'Escalated')
           AND c.escalated_at IS NULL
           AND TIMESTAMPDIFF(HOUR, c.created_at, NOW()) >= r.escalate_after_hours
         ORDER BY c.created_at ASC
         LIMIT ?",
        'i',
        [$limit]
    );

    $count = 0;
    foreach ($due as $row) {
        $complaint = complaint_by_id($conn, (int)$row['complaint_id']);
        if (!$complaint) {
            continue;
        }
        $id = (int)$complaint['complaint_id'];
        $hours = (int)$row['escalate_after_hours'];
        $previous = !empty($complaint['assigned_to']) ? (int)$complaint['assigned_to'] : null;

        // 1. Mark it escalated (also tells the student by notification and email).
        change_complaint_status($conn, $complaint, 'Escalated', null, sprintf('Escalated automatically: not resolved within %d hours.', $hours));

        // 2. Hand it to a senior staff member of the same department, if there is one.
        $senior = fetch_one(
            $conn,
            "SELECT u.user_id
             FROM users u
             LEFT JOIN complaints c ON c.assigned_to = u.user_id AND c.status NOT IN ('Resolved', 'Closed')
             WHERE u.role = 'staff' AND u.is_senior = 1 AND u.is_active = 1 AND u.department_id = ?
             GROUP BY u.user_id
             ORDER BY COUNT(c.complaint_id) ASC, u.user_id ASC
             LIMIT 1",
            'i',
            [(int)$complaint['department_id']]
        );
        $seniorId = $senior ? (int)$senior['user_id'] : null;
        $moved = false;
        if ($seniorId !== null && $seniorId !== $previous) {
            $fresh = complaint_by_id($conn, $id);
            $moved = assign_complaint($conn, $fresh, $seniorId, null);
        }

        // 3. Tell the admins and the previous handler (the senior was told by the assignment above).
        $notifyIds = array_map('intval', array_column(
            fetch_all($conn, "SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1"),
            'user_id'
        ));
        if ($previous !== null && $previous !== $seniorId) {
            $notifyIds[] = $previous;
        }
        $message = sprintf(
            'Complaint %s has been escalated because it was not resolved within %d hours.%s',
            $complaint['ticket_number'],
            $hours,
            $moved ? ' It has been passed to a senior staff member.' : ' No senior staff member is available in its department, so it stays with its current handler.'
        );
        foreach (array_unique($notifyIds) as $uid) {
            notify($conn, $uid, $id, $message);
            email_user($conn, $uid, sprintf('Escalated: complaint %s', $complaint['ticket_number']), $message, $id);
        }
        $count++;
    }
    return $count;
}

/**
 * Escalate a complaint by hand to a chosen senior staff member (FR-24).
 * If that senior belongs to another department, the complaint moves there too.
 * Returns an error message, or null when it worked.
 */
function escalate_complaint_manually(mysqli $conn, array $complaint, int $seniorId, ?int $actorId, string $reason): ?string
{
    if (in_array($complaint['status'], ['Resolved', 'Closed', 'Escalated'], true)) {
        return 'This complaint cannot be escalated in its current status.';
    }
    $senior = fetch_one(
        $conn,
        "SELECT user_id, full_name, department_id FROM users WHERE user_id = ? AND role = 'staff' AND is_senior = 1 AND is_active = 1",
        'i',
        [$seniorId]
    );
    if (!$senior) {
        return 'Choose an active senior staff member.';
    }
    if ((int)$complaint['assigned_to'] === $seniorId) {
        return 'The complaint is already assigned to that person. Choose another senior staff member.';
    }

    $id = (int)$complaint['complaint_id'];

    // Another department? Then the complaint moves there.
    if ($senior['department_id'] !== null && (int)$senior['department_id'] !== (int)$complaint['department_id']) {
        db_execute($conn, 'UPDATE complaints SET department_id = ? WHERE complaint_id = ?', 'ii', [(int)$senior['department_id'], $id]);
    }

    // 1. Mark it escalated (tells the student by notification and email).
    change_complaint_status($conn, $complaint, 'Escalated', $actorId, 'Escalated by staff: ' . $reason);

    // 2. Hand it to the senior (notifies and emails them).
    $fresh = complaint_by_id($conn, $id);
    assign_complaint($conn, $fresh, $seniorId, $actorId);

    log_activity($conn, $actorId, 'complaint_escalated', sprintf('%s -> %s', $complaint['ticket_number'], $senior['full_name']));

    // 3. Tell the admins (in-app).
    $who = 'A staff member';
    if ($actorId !== null) {
        $actor = fetch_one($conn, 'SELECT full_name FROM users WHERE user_id = ?', 'i', [$actorId]);
        $who = $actor ? $actor['full_name'] : $who;
    }
    $message = sprintf('Complaint %s was escalated by %s to %s.', $complaint['ticket_number'], $who, $senior['full_name']);
    foreach (fetch_all($conn, "SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1") as $adm) {
        if ((int)$adm['user_id'] !== (int)$actorId) {
            notify($conn, (int)$adm['user_id'], $id, $message);
        }
    }
    return null;
}