<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
        $config['db']['user'],
        $config['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if ($password !== $confirm) {
            $_SESSION['password_error'] = "Passwords do not match.";
            header('Location: set_password.php');
            exit;
        } elseif (strlen($password) < 6) {
            $_SESSION['password_error'] = "Password must be at least 6 characters.";
            header('Location: set_password.php');
            exit;
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $isVerified = $stmt->fetchColumn();

            if (!$isVerified) {
                // Generate OTP and update verify_token
                $code = mt_rand(100000, 999999);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, verify_token = ?, is_verified = 0 WHERE id = ?");
                $stmt->execute([$hash, $code, $_SESSION['user_id']]);

                // Send OTP email
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $config['smtp']['username'];
                    $mail->Password = $config['smtp']['password'];
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;

                    $mail->setFrom($config['smtp']['username'], 'Project Topic Selector');
                    $mail->addAddress($user['email']);
                    $mail->isHTML(true);
                    $mail->Subject = 'OTP Verification - UCC Hostel Management';
                    $mail->Body = "Dear {$user['name']},<br>Your OTP for verification is: <b>$code</b>.<br>It will expire in 10 minutes.";
                    $mail->AltBody = "Your 6-digit verification code is: $code";

                    $mail->send();
                    $_SESSION['email_to_verify'] = $user['email'];
                    header('Location: verify_email.php');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['password_error'] = "Email sending failed: {$mail->ErrorInfo}";
                    header('Location: set_password.php');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $_SESSION['user_id']]);
                $_SESSION['password_success'] = true;
                header('Location: dashboard.php');
                exit;
            }
        }
    }
} catch (PDOException $e) {
    $_SESSION['password_error'] = "Error: " . $e->getMessage();
    header('Location: set_password.php');
    exit;
}