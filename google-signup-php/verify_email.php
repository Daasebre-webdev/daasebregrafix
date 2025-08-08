<?php
session_start();

// Buffer output to prevent accidental output
ob_start();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load config
$config = require __DIR__ . '/config.php';

try {
    // Database connection
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Check session
    if (!isset($_SESSION['email_to_verify'])) {
        throw new Exception('Verification session expired');
    }

    $email = $_SESSION['email_to_verify'];

    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Clear any previous output
        ob_clean();
        header('Content-Type: application/json');
        
        if (isset($_POST['code'])) {
            // Verify code
            $code = trim($_POST['code']);
            
            if (!preg_match('/^\d{6}$/', $code)) {
                throw new Exception('Invalid code format');
            }

            $stmt = $pdo->prepare("SELECT id, verify_token_expires_at FROM users WHERE email = ? AND verify_token = ?");
            $stmt->execute([$email, $code]);
            
            if ($user = $stmt->fetch()) {
                if (strtotime($user['verify_token_expires_at']) < time()) {
                    throw new Exception('Code expired. Please resend.');
                }

                $pdo->beginTransaction();
$stmt = $pdo->prepare("UPDATE users SET is_verified = 1, agreed_to_terms = 1, verify_token = NULL, verify_token_expires_at = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                $_SESSION['user_id'] = $user['id'];
                unset($_SESSION['email_to_verify']);
                $pdo->commit();
                
                echo json_encode(['success' => true]);
                exit;
            }
            
            throw new Exception('Invalid verification code');
        } 
        elseif (isset($_POST['resend']) && $_POST['resend'] === 'true') {
            // Resend code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 180);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE users SET verify_token = ?, verify_token_expires_at = ? WHERE email = ?");
            $stmt->execute([$code, $expires, $email]);
            
            require __DIR__ . '/mailer.php';
            if (!sendVerificationCode($email, $code)) {
                throw new Exception('Failed to send verification email');
            }
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        }
        
        throw new Exception('Invalid request');
    }

    // For GET requests, check if already verified
    $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && $user['is_verified']) {
        unset($_SESSION['email_to_verify']);
        header('Location: http://localhost:3000/dashboard');
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
    
    // For GET requests, show error page
    $errorMessage = htmlspecialchars($e->getMessage());
}

// End output buffering and clean any accidental output
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .box {
            background: #fff;
            padding: 2em;
            border-radius: 10px;
            width: 360px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
        }
        .logo {
            max-width: 100px;
            margin-bottom: 10px;
        }
        .code-input {
            display: flex;
            justify-content: space-between;
            margin: 1em 0;
        }
        .code-input input {
            width: 40px;
            height: 50px;
            font-size: 24px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        .btn {
            background: #28a745;
            color: white;
            padding: 12px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn:disabled {
            background: #aaa;
            cursor: not-allowed;
        }
        .btn.loading {
            position: relative;
            color: transparent;
        }
        .btn.loading::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .timer {
            margin-top: 10px;
            font-weight: bold;
            color: #555;
        }
        .resend {
            margin-top: 10px;
            color: #999;
            cursor: not-allowed;
        }
        .resend.enabled {
            color: #007bff;
            cursor: pointer;
            text-decoration: underline;
        }
        .terms {
            margin-top: 10px;
            text-align: left;
        }
        .error-message {
            margin-top: 10px;
            color: #dc3545;
            min-height: 20px;
        }
        <?php if (isset($errorMessage)): ?>
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        <?php endif; ?>
    </style>
</head>
<body>
<div class="box">
    <?php if (isset($errorMessage)): ?>
    <div class="error-box">
        Error: <?= $errorMessage ?>
    </div>
    <?php endif; ?>
    
    <img class="logo" src="logo.png" alt="Logo">
    <h2>Email Verification</h2>
    <p>Enter the 6-digit code sent to<br><strong><?= htmlspecialchars($email) ?></strong></p>

    <div class="code-input">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" maxlength="1" class="digit" data-index="<?= $i ?>">
        <?php endfor; ?>
    </div>

    <div class="terms">
        <label>
            <input type="checkbox" id="agreeTerms">
            I agree to the <span id="showTerms" style="color:#007bff; cursor:pointer; text-decoration:underline;">Terms and Conditions</span>
        </label>
    </div>

    <button class="btn" id="verifyBtn">Verify</button>
    <div class="timer" id="timer">Code expires in 3:00</div>
    <div class="resend" id="resend">Resend Code</div>
    <div class="error-message" id="errorMessage"></div>
</div>

<script>
    const inputs = document.querySelectorAll('.digit');
    const verifyBtn = document.getElementById('verifyBtn');
    const resendBtn = document.getElementById('resend');
    const timerElem = document.getElementById('timer');
    const errorElem = document.getElementById('errorMessage');
    const agreeTerms = document.getElementById('agreeTerms');
    
    // Focus first input
    inputs[0].focus();
    
    // Input handling
    inputs.forEach((input, i) => {
        input.addEventListener('input', (e) => {
            if (input.value.length === 1 && i < inputs.length - 1) {
                inputs[i + 1].focus();
            }
            
            // Auto-submit if all fields are filled
            if (i === inputs.length - 1 && input.value.length === 1) {
                const allFilled = Array.from(inputs).every(i => i.value.length === 1);
                if (allFilled) {
                    verifyCode();
                }
            }
        });
        
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && input.value.length === 0 && i > 0) {
                inputs[i - 1].focus();
            }
        });
    });

    // Timer functionality
    let countdown = 60;
    updateTimer();
    
let timerInterval = setInterval(updateTimer, 1000); // Changed from const to let
    
    function updateTimer() {
        const minutes = Math.floor(countdown / 60);
        const seconds = countdown % 60;
        timerElem.textContent = `Code expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (countdown <= 0) {
            resendBtn.classList.add('enabled');
            timerElem.textContent = 'Code expired. Please resend.';
            verifyBtn.disabled = true;
        } else {
            resendBtn.classList.remove('enabled');
        }
        
        countdown--;
        
        if (countdown < 0) {
            clearInterval(timerInterval);
        }
    }

    async function verifyCode() {
        errorElem.textContent = '';
        
        if (!agreeTerms.checked) {
            errorElem.textContent = 'You must agree to the Terms and Conditions';
            return;
        }

        const code = Array.from(inputs).map(input => input.value).join('');
        if (code.length !== 6) {
            errorElem.textContent = 'Please enter the full 6-digit code';
            return;
        }

        verifyBtn.disabled = true;
        verifyBtn.classList.add('loading');

        try {
            const response = await fetch('verify_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ code })
            });

            const data = await response.json();
            
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Verification failed');
            }

            window.location.href = 'http://localhost:3000/dashboard';
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message,
                confirmButtonColor: '#d33'
            });
        } finally {
            verifyBtn.disabled = false;
            verifyBtn.classList.remove('loading');
        }
    }

    verifyBtn.addEventListener('click', verifyCode);


resendBtn.addEventListener('click', function() {
    // Only proceed if resend is enabled (timer has expired)
    if (!this.classList.contains('enabled')) return;
    
    // Show loading state on resend button
    this.innerHTML = 'Sending...';
    this.style.pointerEvents = 'none';
    
    fetch('verify_email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ resend: 'true' })
    })
    .then(async response => {
        const data = await response.json();
        resendBtn.innerHTML = 'Resend Code';
        resendBtn.style.pointerEvents = 'auto';
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Code Resent',
                text: 'Check your email inbox for the new code.',
                confirmButtonColor: '#28a745'
            });
            
            // Reset timer and UI
            countdown = 60;
            clearInterval(timerInterval); // Now we can modify timerInterval
            timerInterval = setInterval(updateTimer, 1000); // Reassign the interval
            inputs.forEach(input => input.value = '');
            inputs[0].focus();
            verifyBtn.disabled = false;
            resendBtn.classList.remove('enabled');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed to Resend',
                text: data.message || 'Could not resend verification code.',
                confirmButtonColor: '#d33'
            });
        }
    })
    .catch(error => {
        resendBtn.innerHTML = 'Resend Code';
        resendBtn.style.pointerEvents = 'auto';
        console.error('Fetch error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not connect to the server. Please try again.',
            confirmButtonColor: '#d33'
        });
    });
});

    document.getElementById('showTerms').addEventListener('click', () => {
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
            width: '800px'
        });
    });
</script>
</body>
</html>