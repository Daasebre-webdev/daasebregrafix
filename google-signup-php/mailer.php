<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendVerificationCode($email, $code) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'uccmarket848@gmail.com';
        $mail->Password = 'btur fkly mtpd edmi'; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use TLS
        $mail->Port = 587;

        $mail->setFrom('uccmarket848@gmail.com', 'Project Topic Selector');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email';
        $mail->Body = "Your verification code is: <strong>$code</strong>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // 🔴 This will show the error in the browser
        echo 'Mailer Error: ' . $mail->ErrorInfo;
        return false;
    }
}
?>
