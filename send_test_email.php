<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // SMTP settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'workmuna310@gmail.com'; // your Gmail
    $mail->Password   = 'xfqskaljimhpppam'; // the 16 char password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Sender & Recipient
    $mail->setFrom('YOUR_EMAIL@gmail.com', 'WorkMuna');
    $mail->addAddress('YOUR_EMAIL@gmail.com'); // send to yourself first to test

    // Message
    $mail->isHTML(true);
    $mail->Subject = 'PHPMailer Test - WorkMuna';
    $mail->Body    = '<h3>If you see this, PHPMailer works! 🎉</h3>';

    $mail->send();
    echo "✅ Email sent successfully!";
} catch (Exception $e) {
    echo "❌ Error sending email: {$mail->ErrorInfo}";
}
