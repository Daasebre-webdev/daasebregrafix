
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require 'config.php';

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
$client->setPrompt('select_account');
$googleSignupUrl = $client->createAuthUrl();

// Google callback handling
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token['access_token']);

    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    $email = $google_account_info->email;
    $name = $google_account_info->name;

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
            $errors[] = "Email is already registered.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, is_verified, agreed_to_terms, login_method) VALUES (?, ?, 1, 1, 'google')");
            $stmt->execute([$name, $email]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            
            // Update verification status immediately for Google signups
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }

    if (empty($errors) && isset($_SESSION['user_id'])) {
        $_SESSION['google_signup'] = true;
        header('Location: google_complete_signup.php');
        exit;
    } else {
        header('Location: signup.php');
        exit;
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $recaptchaResponse = $_POST['g-recaptcha-response'];
    $agreeTerms = isset($_POST['agree_terms']) ? 1 : 0;

    // reCAPTCHA check
    if (empty($recaptchaResponse)) {
        $errors[] = "Please complete the reCAPTCHA verification.";
    } else {
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$config['recaptcha']['secret_key']}&response={$recaptchaResponse}&remoteip={$_SERVER['REMOTE_ADDR']}");
        if (!json_decode($verify)->success) {
            $errors[] = "reCAPTCHA verification failed.";
        }
    }

    // Form validation
    if (!$name || !$email || !$password || !$confirm) {
        $errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } elseif ($agreeTerms !== 1) {
        $errors[] = "You must agree to the Terms and Conditions.";
    }

    // Profile picture upload
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

    // Final registration
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
                
                // Force agreed_to_terms to be 1 since we validated it earlier
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, picture, verify_token, is_verified, agreed_to_terms) VALUES (?, ?, ?, ?, ?, 0, 1)");
                $stmt->execute([$name, $email, $hashedPassword, $picturePath, $code]);

                // Store user ID in session for verification
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
  <title>Sign Up</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      min-height: 100vh;
    }

    .container {
      display: flex;
      width: 100%;
    }

    .left-panel {
      flex: 1;
      background: #f8f8f8;
      padding: 60px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      border-top-right-radius: 100px;
      border-bottom-right-radius: 100px;
      box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
    }

    .right-panel {
      flex: 1;
      height: 100vh;
      position: relative;
      overflow: hidden;
    }

    .right-panel::before {
      content: '';
      position: absolute;
      top: 0;
      left: -50%;
      width: 150%;
      height: 100%;
      background-color: #2e7d32;
      clip-path: ellipse(50% 100% at 50% 50%);
      z-index: 0;
    }

    .right-panel::after {
      content: '';
      position: absolute;
      top: 50%;
      right: 10%;
      transform: translateY(-50%);
      width: 70%;
      height: 70%;
      background: url('https://www.svgrepo.com/show/508699/laptop.svg') no-repeat center center/contain;
      z-index: 1;
    }

    .right-content {
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 4rem;
      color: white;
      text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    }

    h2 {
      font-size: 2em;
      margin-bottom: 20px;
    }

    form {
      display: flex;
      flex-direction: column;
    }

   .input-group {
      display: flex;
      align-items: center;
      background: #e6f4ff;
      padding: 10px 15px;
      border-radius: 12px;
      margin-bottom: 15px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      position: relative; /* Add this to contain any absolute positioned elements */
    }

    .input-group i {
      margin-right: 10px;
      color: #333;
      z-index: 1; /* Ensure icons stay behind popup */
    }

    .input-group input {
      border: none;
      background: transparent;
      outline: none;
      width: 100%;
      font-size: 1em;
    }
/* Add this to your CSS */
.swal2-container {
  z-index: 99999 !important;
}

.input-group i.fa-lock {
  position: relative;
  z-index: 1;
}
    #strength {
      height: 8px;
      margin-top: 4px;
      border-radius: 6px;
      background: #ddd;
      overflow: hidden;
    }

    #strength-bar {
      height: 100%;
      transition: 0.3s;
      width: 0%;
      background-color: red;
    }

    .btn {
      background: #2f773d;
      color: white;
      border: none;
      padding: 12px;
      border-radius: 999px;
      font-weight: bold;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
      margin-top: 10px;
    }

    .btn:hover {
      background: #256632;
    }

    .google-btn {
      background-color: #fff;
      color: #333;
      padding: 10px;
      margin-top: 20px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #ccc;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
    }

    .google-btn i {
      margin-right: 8px;
      font-size: 1.1em;
    }

    .link {
      margin-top: 20px;
      font-size: 0.95em;
      text-align: center;
    }

    .link a {
      color: #2f773d;
      text-decoration: none;
      font-weight: 500;
    }

    .right-panel img {
      max-width: 300px;
      margin-top: 30px;
    }

    .error {
      color: red;
      margin-bottom: 10px;
    }

    .g-recaptcha {
      margin-bottom: 15px;
      display: flex;
      justify-content: center;
      background: #e6f4ff;
      padding: 10px;
      border-radius: 12px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      max-width: 304px;
      margin-left: auto;
      margin-right: auto;
    }

    .grecaptcha-badge {
      visibility: hidden;
    }

    .terms {
      margin: 15px 0;
      font-size: 0.9em;
    }

    .terms label {
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .terms input[type="checkbox"] {
      margin-right: 8px;
    }

    #showTerms {
      color: #007bff;
      cursor: pointer;
      text-decoration: underline;
    }

    #showTerms:hover {
      text-decoration: none;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="left-panel">
      <h2>Sign Up</h2>
      <?php if (!empty($errors)): ?>
        <div class="error">
          <?php foreach ($errors as $e) echo "<div>• $e</div>"; ?>
        </div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <div class="input-group">
          <i class="fas fa-user"></i>
          <input type="text" name="name" placeholder="Username" required>
        </div>
        <div class="input-group">
          <i class="fas fa-envelope"></i>
          <input type="email" name="email" placeholder="Email" required>
        </div>
        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" id="password" placeholder="Password" required>
        </div>
        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input type="password" name="confirm" id="confirm" placeholder="Confirm Password" required>
        </div>
        <div id="strength">
          <div id="strength-bar"></div>
        </div>
        <input type="file" name="picture" accept="image/*">
        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['recaptcha']['site_key']); ?>"></div>
        <div class="terms">
          <label>
            <input type="checkbox" name="agree_terms" id="agreeTerms" style="display: none;">
            <input type="checkbox" id="visualAgreeTerms">
            I agree to the <span id="showTerms">Terms and Conditions</span>
          </label>
        </div>
        <button class="btn" type="submit"><i class="fas fa-user-plus"></i> Sign Up</button>
      </form>
      <a class="google-btn" href="<?= htmlspecialchars($googleSignupUrl) ?>">
        <i class="fab fa-google"></i> Sign up with Google
      </a>
      <div class="link">Already have an account? <a href="index.php">Sign In</a></div>
      <div style="margin-top: 20px; text-align: center;">
        <button onclick="window.location.href='http://localhost:3000/'" class="btn" style="background:#e0e0e0;color:#2f773d;">Back to Project Pulse Home</button>
      </div>
    </div>
    <div class="right-panel">
      <div class="right-content">
        <h2>Unlock Your Potential</h2>
        <p>Sign up today and discover a project that aligns with your skills, interests, and future goals. Our platform will guide you through the process and help you turn your ideas into reality.</p>
        <img src="images/signup.jpg" alt="Sign Up Illustration" />
      </div>
    </div>
  </div>
<script>
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strength-bar');

    passwordInput.addEventListener('input', () => {
      const val = passwordInput.value;
      let strength = 0;

      if (val.match(/[a-z]+/)) strength += 1;
      if (val.match(/[A-Z]+/)) strength += 1;
      if (val.match(/[0-9]+/)) strength += 1;
      if (val.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength += 1;
      if (val.length >= 8) strength += 1;

      strengthBar.style.width = `${strength * 20}%`;

      if (strength <= 2) {
        strengthBar.style.backgroundColor = 'red';
      } else if (strength === 3 || strength === 4) {
        strengthBar.style.backgroundColor = 'orange';
      } else {
        strengthBar.style.backgroundColor = 'green';
      }
    });
// Terms and Conditions handling
document.getElementById('showTerms').addEventListener('click', (e) => {
    e.preventDefault();
    
    // Hide the lock icons
    const lockIcons = document.querySelectorAll('.input-group i.fa-lock');
    lockIcons.forEach(icon => {
        icon.style.visibility = 'hidden';
    });

    Swal.fire({
        title: 'Terms and Conditions',
        html: `
            <div style="text-align: left; max-height: 60vh; overflow-y: auto; font-size: 14px;">
                <h3>Project Pulse Terms of Service</h3>
                <p>Last Updated: ${new Date().toLocaleDateString()}</p>
                
                <h4>1. Acceptance of Terms</h4>
                <p>By using Project Pulse, you agree to these terms and our Privacy Policy.</p>
                
                <h4>2. User Responsibilities</h4>
                <ul>
                    <li>You must provide accurate registration information</li>
                    <li>You are responsible for maintaining the confidentiality of your account</li>
                    <li>You agree to use the service for lawful purposes only</li>
                </ul>
                
                <h4>3. Intellectual Property</h4>
                <p>All content and trademarks are property of Project Pulse.</p>
                
                <h4>4. Limitation of Liability</h4>
                <p>Project Pulse is not liable for any indirect, incidental, or consequential damages.</p>
            </div>
        `,
        confirmButtonText: 'I Understand',
        width: '800px',
        didClose: () => {
            // Show the lock icons again when popup closes
            lockIcons.forEach(icon => {
                icon.style.visibility = 'visible';
            });
        }
    });
});

    // Form submission validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const agreeCheckbox = document.getElementById('agreeTerms');
        
        if (!agreeCheckbox.checked) {
            e.preventDefault();
            Swal.fire({
                title: 'Terms Not Accepted',
                text: 'You must agree to the Terms and Conditions to register',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });

    // Sync the hidden and visual checkboxes
    document.getElementById('visualAgreeTerms').addEventListener('change', function() {
        document.getElementById('agreeTerms').checked = this.checked;
    });
  </script>
</body>
</html>