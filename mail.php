<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'haronisaac51@gmail.com';  // your Gmail
    $mail->Password   = 'poujzkgwaxhynseb';        // your App Password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('haronisaac51@gmail.com', 'KCA Complaint System');
    $mail->addAddress('haronisaac51@gmail.com'); // test to yourself

    $mail->isHTML(true);
    $mail->Subject = 'PHPMailer Test';
    $mail->Body    = '<h3>This is a test email from PHPMailer!</h3>';

    $mail->send();
    echo "Test email sent successfully!";
} catch (Exception $e) {
    echo "Mailer Error: {$mail->ErrorInfo}";
}