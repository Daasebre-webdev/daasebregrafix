<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require 'config.php';

if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session, redirecting to signup.php");
    header('Location: signup.php');
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
        $config['db']['user'],
        $config['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User not found for ID: " . $_SESSION['user_id']);
        header('Location: signup.php');
        exit;
    }

    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        $recaptchaResponse = $_POST['g-recaptcha-response'];

        // reCAPTCHA check
        if (empty($recaptchaResponse)) {
            $errors[] = "Please complete the reCAPTCHA verification.";
        } else {
            $url = 'https://www.google.com/recaptcha/api/siteverify';
            $data = [
                'secret' => $config['recaptcha']['secret_key'],
                'response' => $recaptchaResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data)
                ]
            ];
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $response = json_decode($result);
            if (!$response->success) {
                $errors[] = "reCAPTCHA verification failed. Please try again.";
            }
        }

        // Validate password
        if (!$password || !$confirm) {
            $errors[] = "Both password and confirmation are required.";
        } elseif ($password !== $confirm) {
            $errors[] = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }

        if (empty($errors)) {
            // Generate 6-digit OTP
            $code = mt_rand(100000, 999999);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update user in DB
            $stmt = $pdo->prepare("UPDATE users SET password = ?, verify_token = ?, is_verified = 0 WHERE id = ?");
            $stmt->execute([$hashedPassword, $code, $_SESSION['user_id']]);

            // Send verification email
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
                $mail->addAddress($user['email'], $user['name']);
                $mail->isHTML(true);
                $mail->Subject = 'OTP Verification - UCC Hostel Management';
                $mail->Body = "Dear {$user['name']},<br>Your OTP for verification is: <b>$code</b>.<br>It will expire in 10 minutes.";
                $mail->AltBody = "Your 6-digit verification code is: $code";

                $mail->send();
                $_SESSION['email_to_verify'] = $user['email'];
                header('Location: verify_email.php');
                exit;
            } catch (Exception $e) {
                $errors[] = "Failed to send OTP email: {$mail->ErrorInfo}";
                error_log("Email Error: " . $e->getMessage());
            }
        }
    }
} catch (PDOException $e) {
    $errors[] = "Database connection error: " . $e->getMessage();
    error_log("PDO Error: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Signup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; max-width: 400px; margin: auto; background: #f8f8f8; }
        .container { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); text-align: center; }
        h2 { font-size: 2em; margin-bottom: 1.5rem; }
        p { margin-bottom: 1rem; }
        .input-group { display: flex; align-items: center; background: #e6f4ff; padding: 10px 15px; border-radius: 12px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .input-group i { margin-right: 10px; color: #333; }
        .input-group input { border: none; background: transparent; outline: none; width: 100%; font-size: 1em; }
        .btn { background: #2f773d; color: white; border: none; padding: 12px; border-radius: 999px; font-weight: bold; cursor: pointer; width: 100%; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); margin-top: 10px; }
        .btn:hover { background: #256632; }
        .error { color: red; margin-bottom: 10px; }
        .g-recaptcha { margin-bottom: 15px; display: flex; justify-content: center; background: #e6f4ff; padding: 10px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); max-width: 304px; margin-left: auto; margin-right: auto; }
        .grecaptcha-badge { visibility: hidden; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Complete Your Signup</h2>
        <p>Email: <?= htmlspecialchars($user['email']) ?></p>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $e) echo "<div>• $e</div>"; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Set Password" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm" placeholder="Confirm Password" required>
            </div>
            <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['recaptcha']['site_key']); ?>"></div>
            <button class="btn" type="submit">Verify and Send OTP</button>
        </form>
    </div>
</body>
</html>