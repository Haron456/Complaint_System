<?php
// Run from the command line or cron: php cron_escalate.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

$n = escalate_overdue_complaints($conn, 50);
if ($n > 0) {
    log_activity($conn, null, 'auto_escalation', sprintf('%d complaint(s) escalated', $n));
}
echo date('Y-m-d H:i:s') . " - escalated $n complaint(s)\n";
