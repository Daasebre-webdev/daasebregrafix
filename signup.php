<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = [
    'db' => [
        'host' => 'localhost',
        'name' => 'project_selector',
        'user' => 'root',
        'pass' => ''
    ],
    'google' => [
        'client_id' => '343168144505-0fhp2lht81n2d09jfokou2alsgfbqmm7.apps.googleusercontent.com',
        'client_secret' => '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',
        'redirect_uri' => 'http://localhost/Google_signup/signup.php'
    ],
    'smtp' => [
        'username' => 'uccmarket848@gmail.com',
        'password' => 'btur fkly mtpd edmi'
    ],
    'recaptcha' => [
        'site_key' => '6LdecGYrAAAAAKnkc61iivG44inWC0hjqkVXMubj',
        'secret_key' => '6LdecGYrAAAAAKtzsfjTAhIiR7LlPHrzjCWqyXPK'
    ]
];

// Google callback handling - This must be at the very top before any output
if (isset($_GET['code'])) {
    $client = new Google_Client();
    $client->setClientId($config['google']['client_id']);
    $client->setClientSecret($config['google']['client_secret']);
    $client->setRedirectUri($config['google']['redirect_uri']);
    $client->addScope('email');
    $client->addScope('profile');
    
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        if (isset($token['access_token'])) {
            $client->setAccessToken($token['access_token']);
            $google_oauth = new Google_Service_Oauth2($client);
            $google_account_info = $google_oauth->userinfo->get();
            $email = $google_account_info->email;
            $name = $google_account_info->name;
            $google_id = $google_account_info->id;

            try {
                $pdo = new PDO(
                    "mysql:host={$config['db']['host']};dbname={$config['db']['name']}",
                    $config['db']['user'],
                    $config['db']['pass']
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Check if user exists by email
                $stmt = $pdo->prepare("SELECT id, is_verified, login_method FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingUser) {
                    // User exists, check if they used Google before
                    if ($existingUser['login_method'] !== 'google') {
                        $_SESSION['errors'] = ["This email is already registered with a different method."];
                        header('Location: signup.php');
                        exit;
                    }
                    
                    // Existing Google user, log them in
                    $_SESSION['user_id'] = $existingUser['id'];
                    
                    if ($existingUser['is_verified']) {
                        header('Location: http://localhost:3000/dashboard');
                        exit;
                    } else {
                        $_SESSION['email_to_verify'] = $email;
                        header('Location: verify_email.php');
                        exit;
                    }
                } else {
                    // New Google user, create account
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, is_verified, agreed_to_terms, login_method) VALUES (?, ?, ?, 1, 1, 'google')");
                    $stmt->execute([$name, $email, $google_id]);
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    header('Location: google_complete_signup.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $_SESSION['errors'] = ["Database error. Please try again later."];
                header('Location: signup.php');
                exit;
            }
        } else {
            error_log("Token retrieval failed: " . print_r($token, true));
            $_SESSION['errors'] = ["Failed to authenticate with Google. Please try again."];
            header('Location: signup.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Google auth error: " . $e->getMessage());
        $_SESSION['errors'] = ["Google authentication failed. Please try again."];
        header('Location: signup.php');
        exit;
    }
}

// If already logged in and verified, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']}",
        $config['db']['user'],
        $config['db']['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['is_verified']) {
        header('Location: http://localhost:3000/dashboard');
        exit;
    }
}

// Google signup URL setup
$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');
$googleSignupUrl = $client->createAuthUrl();

// Check for any errors passed via session
$errors = [];
if (!empty($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $recaptchaResponse = $_POST['g-recaptcha-response'];
    $agreeTerms = isset($_POST['agree_terms']) ? 1 : 0;

    if (empty($recaptchaResponse)) {
        $errors[] = "Please complete the reCAPTCHA verification.";
    } else {
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$config['recaptcha']['secret_key']}&response={$recaptchaResponse}&remoteip={$_SERVER['REMOTE_ADDR']}");
        if (!json_decode($verify)->success) {
            $errors[] = "reCAPTCHA verification failed.";
        }
    }

    if (!$name || !$email || !$password || !$confirm) {
        $errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } elseif ($agreeTerms !== 1) {
        $errors[] = "You must agree to the Terms and Conditions.";
    }

    // Sequential password validation
    $passwordStrength = 0;
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
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

    $picturePath = null;
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['picture']['type'], $allowedTypes)) {
            $errors[] = "Invalid file type.";
        }
        if ($_FILES['picture']['size'] > 2 * 1024 * 1024) {
            $errors[] = "File size exceeds 2MB.";
        }

        if (empty($errors)) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['picture']['name']));
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $targetPath)) {
                $picturePath = 'uploads/' . $filename;
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo = new PDO(
                "mysql:host={$config['db']['host']};dbname={$config['db']['name']}",
                $config['db']['user'],
                $config['db']['pass']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already exists.";
            } else {
                $code = mt_rand(100000, 999999);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, picture, verify_token, is_verified, agreed_to_terms, login_method) VALUES (?, ?, ?, ?, ?, 0, 1, 'email')");
                $stmt->execute([$name, $email, $hashedPassword, $picturePath, $code]);
                $_SESSION['user_id'] = $pdo->lastInsertId();

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp']['username'];
                $mail->Password = $config['smtp']['password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;
                $mail->setFrom($config['smtp']['username'], 'Project Topic Selector');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'OTP Verification - UCC Hostel Management';
                $mail->Body = "Dear $name,<br>Your OTP for verification is: <b>$code</b>.<br>It will expire in 10 minutes.";
                $mail->AltBody = "Your 6-digit verification code is: $code";
                $mail->send();

                $_SESSION['email_to_verify'] = $email;
                header('Location: verify_email.php');
                exit;
            }
        } catch (Exception $e) {
            $errors[] = "Failed: {$e->getMessage()}";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Project Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            z-index: 10;
        }
        .input-group input {
            padding-left: 35px;
        }
        #strength-bar {
            transition: width 0.3s, background-color 0.3s;
            width: 0%;
        }
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.25rem;
            font-weight: bold;
            color: #6b7280;
            font-size: 14px;
            flex-shrink: 0;
        }
        .step-line {
            flex: 1;
            height: 2px;
            background-color: #e5e7eb;
            margin: 0 0.25rem;
            align-self: center;
            max-width: 80px;
        }
        .step-line.active {
            background-color: #4f46e5;
        }
        .step.active {
            background-color: #4f46e5;
            color: white;
        }
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .navigation-buttons button {
            flex: 1;
            min-width: 120px;
        }
        
        /* SweetAlert Responsiveness */
        @media (max-width: 640px) {
            .swal2-popup {
                width: 90% !important;
                margin: 0 5% !important;
                padding: 1rem !important;
                font-size: 14px !important;
            }
            .swal2-title {
                font-size: 1.25rem !important;
            }
            .swal2-html-container {
                font-size: 14px !important;
                max-height: 60vh !important;
                overflow-y: auto !important;
            }
        }
        
        /* ReCAPTCHA Responsiveness */
        @media (max-width: 400px) {
            .g-recaptcha {
                transform: scale(0.85);
                transform-origin: 0 0;
            }
        }
        
        @media (max-width: 340px) {
            .g-recaptcha {
                transform: scale(0.8);
            }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 py-4 px-2 sm:py-6 sm:px-4 lg:py-8">
    <div class="max-w-md w-full mx-auto">
        <div class="text-center mb-4 sm:mb-6 md:mb-8">
            <div class="flex justify-center mb-2 sm:mb-3 md:mb-4">
                <div class="w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 bg-blue-100 rounded-full flex items-center justify-center shadow-md">
                    <svg 
                        class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 text-blue-600" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path 
                            stroke-linecap="round" 
                            stroke-linejoin="round" 
                            stroke-width="2" 
                            d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 
                            6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 
                            002-2V7a2 2 0 00-2-2H7a2 2 0 
                            00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" 
                        />
                    </svg>
                </div>
            </div>
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-900">Sign Up</h1>
            <p class="mt-1 sm:mt-2 text-xs sm:text-sm md:text-base text-gray-600">Create your account</p>
        </div>

        <div class="step-indicator mb-4 sm:mb-6">
            <div class="step active">1</div>
            <div class="step-line"></div>
            <div class="step">2</div>
        </div>

        <div class="bg-white rounded-lg sm:rounded-xl md:rounded-2xl shadow-lg p-4 sm:p-6 md:p-8">
            <?php if (!empty($errors)): ?>
                <div class="mb-3 sm:mb-4 md:mb-6 bg-red-50 border border-red-200 rounded-md sm:rounded-lg p-2 sm:p-3 md:p-4">
                    <div class="flex items-center">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4 md:w-5 md:h-5 text-red-500 mr-1 sm:mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs sm:text-sm text-red-800"><?= htmlspecialchars(implode(", ", $errors)) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="space-y-3 sm:space-y-4 md:space-y-6" id="signupForm">
                <!-- Step 1: Basic Information -->
                <div class="form-step active" id="step1">
                    <div>
                        <label for="name" class="block text-xs sm:text-sm md:text-base font-medium text-gray-700 mb-1 sm:mb-2">Username</label>
                        <div class="input-group">
                            <i class="fas fa-user text-xs sm:text-sm"></i>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                placeholder="Username"
                                required
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors text-xs sm:text-sm md:text-base"
                            >
                        </div>
                    </div>
                    <div>
                        <label for="email" class="block text-xs sm:text-sm md:text-base font-medium text-gray-700 mb-1 sm:mb-2">Email Address</label>
                        <div class="input-group">
                            <i class="fas fa-envelope text-xs sm:text-sm"></i>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                placeholder="Email"
                                required
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors text-xs sm:text-sm md:text-base"
                            >
                        </div>
                    </div>
                    <div>
                        <label for="password" class="block text-xs sm:text-sm md:text-base font-medium text-gray-700 mb-1 sm:mb-2">Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock text-xs sm:text-sm"></i>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Password"
                                required
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors text-xs sm:text-sm md:text-base"
                                oninput="updatePasswordStrength(this.value)"
                            >
                        </div>
                        <div id="strength" class="mt-1 sm:mt-2">
                            <div id="strength-bar" class="h-1 sm:h-2 rounded-lg bg-red-500"></div>
                            <p id="strength-text" class="text-xs text-gray-600 mt-1"></p>
                        </div>
                    </div>
                    <div>
                        <label for="confirm" class="block text-xs sm:text-sm md:text-base font-medium text-gray-700 mb-1 sm:mb-2">Confirm Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock text-xs sm:text-sm"></i>
                            <input
                                id="confirm"
                                name="confirm"
                                type="password"
                                placeholder="Confirm Password"
                                required
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors text-xs sm:text-sm md:text-base"
                            >
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <div></div> <!-- Empty div for spacing -->
                        <button type="button" id="nextBtn" class="bg-indigo-600 text-white py-2 px-3 sm:px-4 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors text-xs sm:text-sm md:text-base">
                            Next <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Additional Information -->
                <div class="form-step" id="step2">
                    <div>
                        <label for="picture" class="block text-xs sm:text-sm md:text-base font-medium text-gray-700 mb-1 sm:mb-2">Profile Picture</label>
                        <input
                            id="picture"
                            name="picture"
                            type="file"
                            accept="image/*"
                            class="w-full text-xs sm:text-sm md:text-base text-gray-500 file:mr-2 file:py-1 sm:file:py-2 file:px-2 sm:file:px-3 md:file:px-4 file:rounded-full file:border-0 file:text-xs file:sm:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        >
                    </div>
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($config['recaptcha']['site_key']) ?>"></div>
                    <div class="terms mt-3 sm:mt-4">
                        <label class="flex items-start">
                            <input
                                type="checkbox"
                                name="agree_terms"
                                id="agreeTerms"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded mt-1 mr-2"
                            >
                            <span class="text-xs sm:text-sm md:text-base text-gray-700">I agree to the <span id="showTerms" class="text-indigo-600 hover:text-indigo-500 cursor-pointer">Terms and Conditions</span></span>
                        </label>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" id="prevBtn" class="bg-gray-300 text-gray-800 py-2 px-3 sm:px-4 rounded-md font-medium hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors text-xs sm:text-sm md:text-base">
                            <i class="fas fa-arrow-left mr-1"></i> Previous
                        </button>
                        <button
                            type="submit"
                            class="bg-indigo-600 text-white py-2 px-3 sm:px-4 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors text-xs sm:text-sm md:text-base"
                        >
                            <i class="fas fa-user-plus"></i> Sign Up
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-3 sm:mt-4 grid grid-cols-1 gap-2 sm:gap-3">
                <button
                    type="button"
                    id="googleSignupBtn"
                    onclick="redirectWithSpinner('<?= htmlspecialchars($googleSignupUrl) ?>')"
                    class="relative w-full inline-flex justify-center items-center py-2 sm:py-3 px-2 sm:px-3 md:px-4 border border-gray-300 rounded-md sm:rounded-lg shadow-sm bg-white text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                    <!-- Spinner (hidden by default) -->
                    <svg id="spinner" class="hidden animate-spin h-4 w-4 sm:h-5 sm:w-5 text-gray-600 absolute left-2 sm:left-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>

                    <!-- Google icon -->
                    <svg id="googleIcon" class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.91 3.28-4.7 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>

                    <span id="btnText" class="ml-1 sm:ml-2">Sign up with Google</span>
                </button>
            </div>

            <div class="mt-3 sm:mt-4 md:mt-6 text-center">
                <a href="index.php" class="text-xs sm:text-sm text-gray-600 font-medium text-indigo-600 hover:text-indigo-500 transition-colors">
                    Already have an account? Sign in
                </a>
            </div>
            <div class="mt-3 sm:mt-4 md:mt-6 text-center">
                <button
                    onclick="window.location.href='http://localhost:3000/'"
                    class="w-full bg-gray-200 text-gray-800 py-2 sm:py-3 px-3 sm:px-4 rounded-md sm:rounded-lg font-medium hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors text-xs sm:text-sm md:text-base"
                >
                    Back to Project Pulse Home
                </button>
            </div>
        </div>
    </div>

    <script>
        // Form navigation functionality
        let currentStep = 1;
        const totalSteps = 2;
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        const steps = document.querySelectorAll('.step');
        const stepLines = document.querySelectorAll('.step-line');
        
        function updateStepIndicator() {
            steps.forEach((step, index) => {
                if (index + 1 <= currentStep) {
                    step.classList.add('active');
                } else {
                    step.classList.remove('active');
                }
            });
            
            stepLines.forEach((line, index) => {
                if (index + 1 < currentStep) {
                    line.classList.add('active');
                } else {
                    line.classList.remove('active');
                }
            });
        }
        
        function goToStep(step) {
            // Hide all steps
            document.querySelectorAll('.form-step').forEach(formStep => {
                formStep.classList.remove('active');
            });
            
            // Show the current step
            document.getElementById(`step${step}`).classList.add('active');
            
            currentStep = step;
            updateStepIndicator();
        }
        
        nextBtn.addEventListener('click', () => {
            // Validate step 1 fields before proceeding
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm').value;
            
            if (!name || !email || !password || !confirm) {
                Swal.fire({
                    title: 'Missing Information',
                    text: 'Please fill in all fields before proceeding',
                    icon: 'warning',
                    confirmButtonColor: '#4f46e5',
                    customClass: {
                        popup: 'text-sm sm:text-base',
                        title: 'text-lg sm:text-xl',
                        confirmButton: 'text-xs sm:text-sm'
                    }
                });
                return;
            }
            
            if (password !== confirm) {
                Swal.fire({
                    title: 'Passwords Mismatch',
                    text: 'Your passwords do not match',
                    icon: 'error',
                    confirmButtonColor: '#d33',
                    customClass: {
                        popup: 'text-sm sm:text-base',
                        title: 'text-lg sm:text-xl',
                        confirmButton: 'text-xs sm:text-sm'
                    }
                });
                return;
            }
            
            goToStep(2);
        });
        
        prevBtn.addEventListener('click', () => {
            goToStep(1);
        });

        function updatePasswordStrength(password) {
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            let strength = 0;
            let message = '';

            if (password.length >= 8) {
                strength = 1;
                message = 'Meets minimum length (8 characters)';
                if (/[a-z]/.test(password)) {
                    strength = 2;
                    message = 'Includes lowercase letter';
                    if (/[A-Z]/.test(password)) {
                        strength = 3;
                        message = 'Includes uppercase letter';
                        if (/\d/.test(password)) {
                            strength = 4;
                            message = 'Includes number';
                            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                                strength = 5;
                                message = 'Meets all criteria';
                            } else {
                                message = 'Missing special character';
                            }
                        } else {
                            message = 'Missing number';
                        }
                    } else {
                        message = 'Missing uppercase letter';
                    }
                } else {
                    message = 'Missing lowercase letter';
                }
            } else {
                message = 'Too short (less than 8 characters)';
            }

            strengthBar.style.width = `${strength * 20}%`;
            if (strength <= 2) {
                strengthBar.style.backgroundColor = 'red';
            } else if (strength === 3 || strength === 4) {
                strengthBar.style.backgroundColor = 'orange';
            } else {
                strengthBar.style.backgroundColor = 'green';
            }
            strengthText.textContent = message;
        }

        document.getElementById('showTerms').addEventListener('click', (e) => {
            e.preventDefault();
            Swal.fire({
                title: 'Terms and Conditions',
                html: `
                    <div style="text-align: left; max-height: 60vh; overflow-y: auto; font-size: 14px;">
                        <h3 style="font-size: 1.1em; margin-bottom: 0.5rem;">Project Pulse Terms of Service</h3>
                        <p style="margin-bottom: 1rem;">Last Updated: ${new Date().toLocaleDateString()}</p>
                        
                        <h4 style="font-size: 1em; margin-bottom: 0.5rem;">1. Acceptance of Terms</h4>
                        <p style="margin-bottom: 1rem;">By using Project Pulse, you agree to these terms and our Privacy Policy.</p>
                        
                        <h4 style="font-size: 1em; margin-bottom: 0.5rem;">2. User Responsibilities</h4>
                        <ul style="padding-left: 1.5rem; margin-bottom: 1rem;">
                            <li style="margin-bottom: 0.5rem;">You must provide accurate registration information</li>
                            <li style="margin-bottom: 0.5rem;">You are responsible for maintaining the confidentiality of your account</li>
                            <li>You agree to use the service for lawful purposes only</li>
                        </ul>
                        
                        <h4 style="font-size: 1em; margin-bottom: 0.5rem;">3. Intellectual Property</h4>
                        <p style="margin-bottom: 1rem;">All content and trademarks are property of Project Pulse.</p>
                        
                        <h4 style="font-size: 1em; margin-bottom: 0.5rem;">4. Limitation of Liability</h4>
                        <p>Project Pulse is not liable for any indirect, incidental, or consequential damages.</p>
                    </div>
                `,
                confirmButtonText: 'I Understand',
                width: window.innerWidth < 640 ? '90%' : '800px',
                customClass: {
                    popup: 'text-sm sm:text-base',
                    title: 'text-lg sm:text-xl',
                    confirmButton: 'text-xs sm:text-sm',
                    htmlContainer: 'text-xs sm:text-sm'
                }
            });
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const agreeCheckbox = document.getElementById('agreeTerms');
            if (!agreeCheckbox.checked) {
                e.preventDefault();
                Swal.fire({
                    title: 'Terms Not Accepted',
                    text: 'You must agree to the Terms and Conditions to register',
                    icon: 'error',
                    confirmButtonColor: '#d33',
                    customClass: {
                        popup: 'text-sm sm:text-base',
                        title: 'text-lg sm:text-xl',
                        confirmButton: 'text-xs sm:text-sm'
                    }
                });
            }
        });
        
        function redirectWithSpinner(url) {
            const btn = document.getElementById("googleSignupBtn");
            const spinner = document.getElementById("spinner");
            const googleIcon = document.getElementById("googleIcon");
            const btnText = document.getElementById("btnText");

            // Show spinner, hide icon/text
            spinner.classList.remove("hidden");
            googleIcon.classList.add("hidden");
            btnText.textContent = "Redirecting...";

            // Disable button to prevent multiple clicks
            btn.disabled = true;

            // Redirect after short delay (spinner visible)
            setTimeout(() => {
                window.location.href = url;
            }, 800);
        }
    </script>
</body>
</html>