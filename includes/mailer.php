<?php
/**
 * Email sending (PHPMailer over SMTP). Email is a convenience on top of the
 * in-app notifications: if sending fails, the problem is logged and the
 * system carries on normally.
 *
 * The messages are written to look like normal transactional mail and not like
 * bulk mail: the sender matches the signed-in account, the headers use the
 * sender's own domain, there is a real plain-text version, and links are left
 * out when the system address is only a local one.
 */
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send one HTML email. Returns true on success. On failure it returns false
 * and puts a readable reason in $error; it never throws.
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody, ?string &$error = null, ?string $textBody = null): bool
{
    global $config;
    $cfg = $config['mail'] ?? [];

    if (empty($cfg['enabled'])) {
        $error = 'Email is switched off in config/config.php.';
        return false;
    }
    if (empty($cfg['username']) || empty($cfg['password'])) {
        $error = 'The email username or password is not set in config/config.php.';
        return false;
    }

    $autoload = BASE_PATH . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        $error = 'PHPMailer is not installed. Run "composer install" in the project folder.';
        error_log('Email skipped: ' . $error);
        return false;
    }
    require_once $autoload;

    try {
        $fromEmail = (string)($cfg['from_email'] ?? $cfg['username']);
        $fromName = (string)($cfg['from_name'] ?? 'KCAU Complaint System');
        $domain = substr((string)strrchr($fromEmail, '@'), 1);
        if ($domain === '') {
            $domain = 'localhost';
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($cfg['port'] ?? 587);
        $mail->Timeout    = 10;
        $mail->CharSet    = 'UTF-8';

        // Identify the message with the sender's own domain, not "localhost".
        $mail->Hostname   = $domain;
        $mail->Helo       = $domain;
        $mail->MessageID  = '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
        $mail->XMailer    = ' ';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?? trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlBody)), ENT_QUOTES, 'UTF-8'));

        $mail->send();
        return true;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        error_log('Email to ' . $toEmail . ' failed: ' . $ex->getMessage());
        return false;
    }
}

/** Plain-language email text for each status the student can see. */
function status_email_message(string $ticket, string $status): string
{
    switch ($status) {
        case 'Received':
            return "Your complaint $ticket has been received by the department and is being reviewed.";
        case 'Assigned':
            return "Your complaint $ticket has been assigned to a staff member who will look into it.";
        case 'In Progress':
            return "Staff are now working on your complaint $ticket.";
        case 'Escalated':
            return "Your complaint $ticket has been escalated to senior staff for further attention.";
        case 'Resolved':
            return "Good news: your complaint $ticket has been marked as resolved.\n\n"
                 . "Please log in to read the response and rate the service you received.";
        case 'Closed':
            return "Your complaint $ticket has been closed.";
        default:
            return "The status of your complaint $ticket is now: $status.";
    }
}

/**
 * Email one user. Never throws: if the email cannot be sent, the problem is
 * logged and the system carries on (the in-app notification still exists).
 */
function email_user(mysqli $conn, int $userId, string $subject, string $message, ?int $complaintId = null): bool
{
    global $config;
    $person = fetch_one($conn, 'SELECT full_name, email FROM users WHERE user_id = ? AND is_active = 1', 'i', [$userId]);
    if (!$person) {
        return false;
    }

    // A link is only useful (and only looks trustworthy) when the system has a real public address.
    $url = null;
    $base = rtrim((string)($config['base_url'] ?? ''), '/');
    $host = (string)parse_url($base, PHP_URL_HOST);
    $isLocal = $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || substr($host, -6) === '.local';
    if (!$isLocal) {
        $url = $base . ($complaintId ? '/complaint.php?id=' . $complaintId : '/login.php');
    }

    $footer = 'You are receiving this message because you have an account on the KCA University Complaint and Feedback System. '
            . 'It is an automatic message. If you need help, please contact the system administrator.';

    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;color:#1d2748;line-height:1.5">'
          . '<h3 style="color:#182858;margin:0 0 12px">KCA University Complaint System</h3>'
          . '<p>Hello ' . e($person['full_name']) . ',</p>'
          . '<p>' . nl2br(e($message)) . '</p>'
          . ($url !== null
              ? '<p><a href="' . e($url) . '">Open the complaint system</a></p>'
              : '<p>Log in to the system to see the details.</p>')
          . '<hr style="border:none;border-top:1px solid #dde2ee;margin:20px 0">'
          . '<p style="color:#6b7280;font-size:12px">' . e($footer) . '</p>'
          . '</div>';

    $text = "Hello " . $person['full_name'] . ",\n\n" . $message . "\n\n"
          . ($url !== null ? "Open the complaint system: " . $url . "\n\n" : "Log in to the system to see the details.\n\n")
          . "--\nKCA University Complaint and Feedback System\n" . $footer . "\n";

    $error = null;
    return send_email($person['email'], $person['full_name'], $subject, $html, $error, $text);
}