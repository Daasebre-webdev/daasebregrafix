<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require 'config.php';

// Check if we have Google data in session
if (!isset($_SESSION['google_data'])) {
    error_log("No google_data in session, redirecting to signup.php");
    header('Location: signup.php');
    exit;
}

$googleData = $_SESSION['google_data'];

try {
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
        $config['db']['user'],
        $config['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

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

        // Sequential password validation
        $passwordStrength = 0;
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        } else {
            $passwordStrength = 1; // Met length requirement
            if (!preg_match("/[a-z]/", $password)) {
                $errors[] = "Password must contain at least 1 lowercase letter.";
            } else {
                $passwordStrength = 2; // Met lowercase
                if (!preg_match("/[A-Z]/", $password)) {
                    $errors[] = "Password must contain at least 1 uppercase letter.";
                } else {
                    $passwordStrength = 3; // Met uppercase
                    if (!preg_match("/[0-9]/", $password)) {
                        $errors[] = "Password must contain at least 1 number.";
                    } else {
                        $passwordStrength = 4; // Met number
                        if (!preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
                            $errors[] = "Password must contain at least 1 special character.";
                        } else {
                            $passwordStrength = 5; // Met all criteria
                        }
                    }
                }
            }
        }

        if (!$password || !$confirm) {
            $errors[] = "Both password and confirmation are required.";
        } elseif ($password !== $confirm) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            // Generate 6-digit OTP
            $code = mt_rand(100000, 999999);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Check if user already exists with this email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$googleData['email']]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                // Update existing user
                $stmt = $pdo->prepare("UPDATE users SET password = ?, google_id = ?, picture = ?, verify_token = ?, is_verified = 0 WHERE id = ?");
                $stmt->execute([
                    $hashedPassword, 
                    $googleData['id'],
                    $googleData['picture'],
                    $code,
                    $existingUser['id']
                ]);
                $_SESSION['user_id'] = $existingUser['id'];
            } else {
                // Create new user with Google data
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, google_id, picture, verify_token, login_method) VALUES (?, ?, ?, ?, ?, ?, 'google')");
                $stmt->execute([
                    $googleData['name'],
                    $googleData['email'],
                    $hashedPassword,
                    $googleData['id'],
                    $googleData['picture'],
                    $code
                ]);
                $_SESSION['user_id'] = $pdo->lastInsertId();
            }

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

                $mail->setFrom($config['smtp']['username'], 'Project Pulse');
                $mail->addAddress($googleData['email'], $googleData['name']);
                $mail->isHTML(true);
                $mail->Subject = 'OTP Verification - Project Pulse';
                
                // Create verification URL with code as parameter
                $verificationUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                                   "://" . $_SERVER['HTTP_HOST'] . 
                                   dirname($_SERVER['PHP_SELF']) . 
                                   "/verify_email.php?code=" . $code;
                
                // HTML email content with styling
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { text-align: center; padding: 20px 0; }
                        .logo-container { display: inline-flex; align-items: center; }
                        .logo-circle { width: 40px; height: 40px; background-color: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-right: 10px; }
                        .logo-text { font-size: 24px; font-weight: 600; color: #2563eb; font-family: 'Inter', sans-serif; }
                        .content { background-color: #f9fafb; padding: 30px; border-radius: 8px; margin-top: 20px; }
                        .button { display: inline-block; padding: 12px 24px; background-color: #4f46e5; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                        .code { font-size: 24px; font-weight: bold; letter-spacing: 3px; text-align: center; margin: 20px 0; padding: 15px; background-color: #e5e7eb; border-radius: 4px; }
                        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <div class='logo-container'>
                                <div class='logo-circle'>
                                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='#2563eb' stroke-width='2'>
                                        <path stroke-linecap='round' stroke-linejoin='round' d='M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' />
                                    </svg>
                                </div>
                                <span class='logo-text'>Project Pulse</span>
                            </div>
                        </div>
                        <div class='content'>
                            <h2>Email Verification</h2>
                            <p>Dear {$googleData['name']},</p>
                            <p>Thank you for signing up with Project Pulse. To complete your registration, please verify your email address using the 6-digit code below:</p>
                            
                            <div class='code'>{$code}</div>
                            
                            <p>Alternatively, you can click the button below to verify your email:</p>
                            
                            <p style='text-align: center;'>
                                <a href='{$verificationUrl}' class='button'>Verify Email Address</a>
                            </p>
                            
                            <p>This verification code will expire in 10 minutes for security reasons.</p>
                            
                            <p>If you did not create an account with Project Pulse, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " Project Pulse. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                $mail->AltBody = "Dear {$googleData['name']},\n\nYour OTP for verification is: {$code}.\n\nAlternatively, you can verify your email by visiting: {$verificationUrl}\n\nIt will expire in 10 minutes.";

                $mail->send();
                $_SESSION['email_to_verify'] = $googleData['email'];
                
                // Preserve Google data for verify_email.php
                $_SESSION['google_data'] = $googleData;
                
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
    <title>Complete Signup - Project Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        .input-group input {
            padding-left: 35px;
        }
        #strength {
            margin-top: 0.5rem;
        }
        #strength-bar {
            height: 0.25rem;
            border-radius: 0.375rem;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        #strength-text {
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: #4b5563;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 py-4 px-2 sm:py-6 sm:px-4">
    <div class="max-w-md w-full mx-auto">
        <div class="text-center mb-6 sm:mb-8">
            <div class="flex justify-center mb-3 sm:mb-4">
                <!-- Project Pulse Logo -->
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center shadow-md mr-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                    </div>
                    <span class="text-xl font-semibold text-blue-600">Project Pulse</span>
                </div>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Complete Signup</h1>
            <p class="mt-1 sm:mt-2 text-sm sm:text-base text-gray-600">Finish setting up your account</p>
        </div>

        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-8">
            <?php if (!empty($errors)): ?>
                <div class="mb-4 sm:mb-6 bg-red-50 border border-red-200 rounded-md sm:rounded-lg p-3 sm:p-4">
                    <div class="flex items-center">
                        <svg class="w-4 sm:w-5 h-4 sm:h-5 text-red-500 mr-1 sm:mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs sm:text-sm text-red-800"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <p class="text-center mb-4 sm:mb-6 text-sm sm:text-base text-gray-600">Email: <strong><?= htmlspecialchars($googleData['email']) ?></strong></p>

            <form method="post" class="space-y-4 sm:space-y-6">
                <div>
                    <label for="password" class="block text-sm sm:text-base font-medium text-gray-700 mb-1 sm:mb-2">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Set Password" required class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors text-sm sm:text-base" oninput="updatePasswordStrength(this.value)">
                    </div>
                    <div id="strength">
                        <div id="strength-bar" class="h-1 sm:h-2 rounded-lg bg-red-500"></div>
                        <p id="strength-text" class="text-xs sm:text-sm text-gray-600 mt-1"></p>
                    </div>
                </div>
                <div>
                    <label for="confirm" class="block text-sm sm:text-base font-medium text-gray-700 mb-1 sm:mb-2">Confirm Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm" name="confirm" placeholder="Confirm Password" required class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors text-sm sm:text-base">
                    </div>
                </div>
                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($config['recaptcha']['site_key']) ?>"></div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 sm:py-3 px-4 rounded-md sm:rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors text-sm sm:text-base">Verify and Send OTP</button>
            </form>
        </div>
    </div>

    <script>
        function updatePasswordStrength(password) {
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            let strength = 0;
            let message = '';

            // Reset animation
            strengthBar.style.width = '0%';
            strengthBar.style.backgroundColor = 'red';

            // Sequential validation with animation
            const animateStep = (targetStrength, targetMessage, delay) => {
                return new Promise(resolve => {
                    setTimeout(() => {
                        strength = targetStrength;
                        message = targetMessage;
                        strengthBar.style.width = `${strength * 20}%`;
                        if (strength <= 2) {
                            strengthBar.style.backgroundColor = 'red';
                        } else if (strength === 3 || strength === 4) {
                            strengthBar.style.backgroundColor = 'orange';
                        } else {
                            strengthBar.style.backgroundColor = 'green';
                        }
                        strengthText.textContent = message;
                        resolve();
                    }, delay);
                });
            };

            (async () => {
                if (password.length >= 6) {
                    await animateStep(1, 'Meets minimum length (6 characters)', 200);
                    if (/[a-z]/.test(password)) {
                        await animateStep(2, 'Includes lowercase letter', 200);
                        if (/[A-Z]/.test(password)) {
                            await animateStep(3, 'Includes uppercase letter', 200);
                            if (/\d/.test(password)) {
                                await animateStep(4, 'Includes number', 200);
                                if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                                    await animateStep(5, 'Meets all criteria', 200);
                                } else {
                                    strengthText.textContent = 'Missing special character';
                                }
                            } else {
                                strengthText.textContent = 'Missing number';
                            }
                        } else {
                            strengthText.textContent = 'Missing uppercase letter';
                        }
                    } else {
                        strengthText.textContent = 'Missing lowercase letter';
                    }
                } else {
                    strengthText.textContent = 'Too short (less than 6 characters)';
                }
            })();
        }
    </script>
</body>
</html>