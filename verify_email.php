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
        ob_clean();
        header('Content-Type: application/json');
        
        if (isset($_POST['code'])) {
            $code = trim($_POST['code']);
            
            if (!preg_match('/^\d{6}$/', $code)) {
                throw new Exception('Invalid code format');
            }

            $stmt = $pdo->prepare("SELECT id, verify_token, verify_token_expires_at FROM users WHERE email = ? AND verify_token = ?");
            $stmt->execute([$email, $code]);
            $user = $stmt->fetch();
            
            if ($user) {
                // FIX: Use server time to check expiration, not client time
                $serverTime = time();
                $expirationTime = strtotime($user['verify_token_expires_at']);
                
                if ($expirationTime < $serverTime) {
                    throw new Exception('Code expired. Please resend.');
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, agreed_to_terms = 1, verify_token = NULL, verify_token_expires_at = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Store Google details and picture if available from session
                if (isset($_SESSION['google_data'])) {
                    $googleData = $_SESSION['google_data'];
                    
                    // Update user with Google data including picture
                    $updateFields = [];
                    $updateValues = [];
                    
                    if (!empty($googleData['name'])) {
                        $updateFields[] = "name = ?";
                        $updateValues[] = $googleData['name'];
                    }
                    
                    if (!empty($googleData['email'])) {
                        $updateFields[] = "email = ?";
                        $updateValues[] = $googleData['email'];
                    }
                    
                    // FIX: Properly handle the Google picture URL
                    if (!empty($googleData['picture'])) {
                        $updateFields[] = "picture = ?";
                        $updateValues[] = $googleData['picture'];
                    }
                    
                    // Also update google_id if available
                    if (!empty($googleData['id'])) {
                        $updateFields[] = "google_id = ?";
                        $updateValues[] = $googleData['id'];
                    }
                    
                    // Only execute update if we have fields to update
                    if (!empty($updateFields)) {
                        $updateValues[] = $user['id']; // Add the user ID for WHERE clause
                        
                        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($updateValues);
                    }
                    
                    unset($_SESSION['google_data']); // Clean up session data
                }

                $_SESSION['user_id'] = $user['id'];
                unset($_SESSION['email_to_verify']);
                $pdo->commit();
                
                echo json_encode(['success' => true]);
                exit;
            }
            
            throw new Exception('Invalid verification code');
        } elseif (isset($_POST['resend']) && $_POST['resend'] === 'true') {
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
            
            // Store the expiration time in session for the countdown timer
            $_SESSION['code_expires_at'] = time() + 180;
            
            echo json_encode(['success' => true, 'expires_at' => $_SESSION['code_expires_at']]);
            exit;
        }
        
        throw new Exception('Invalid request');
    }

    // For GET requests, check if already verified
    $stmt = $pdo->prepare("SELECT id, is_verified, verify_token_expires_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && $user['is_verified']) {
        unset($_SESSION['email_to_verify']);
        header('Location: http://localhost:3000/dashboard');
        exit;
    }
    
    // Set the expiration time for the countdown timer
    if ($user && $user['verify_token_expires_at']) {
        $_SESSION['code_expires_at'] = strtotime($user['verify_token_expires_at']);
    } else {
        // Default to 3 minutes if no expiration time is set
        $_SESSION['code_expires_at'] = time() + 180;
        
        // Also update the database if no expiration is set
        if ($user) {
            $expires = date('Y-m-d H:i:s', $_SESSION['code_expires_at']);
            $stmt = $pdo->prepare("UPDATE users SET verify_token_expires_at = ? WHERE email = ?");
            $stmt->execute([$expires, $email]);
        }
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
    
    $errorMessage = htmlspecialchars($e->getMessage());
}

// End output buffering and clean any accidental output
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification - Project Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        }
        .code-input input {
            width: 40px;
            height: 50px;
            font-size: 24px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 0;
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
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 py-4 px-2 sm:py-6 sm:px-4">
    <div class="max-w-md w-full mx-auto">
        <div class="text-center mb-6 sm:mb-8">
            <div class="flex justify-center mb-3 sm:mb-4">
                <div class="w-14 h-14 sm:w-16 sm:h-16 bg-indigo-600 rounded-lg flex items-center justify-center">
                    <svg class="w-7 sm:w-8 h-7 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                    </svg>
                </div>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Email Verification</h1>
            <p class="mt-1 sm:mt-2 text-sm sm:text-base text-gray-600">Verify your account</p>
        </div>

        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-8">
            <?php if (isset($errorMessage)): ?>
                <div class="mb-4 sm:mb-6 bg-red-50 border border-red-200 rounded-md sm:rounded-lg p-3 sm:p-4">
                    <div class="flex items-center">
                        <svg class="w-4 sm:w-5 h-4 sm:h-5 text-red-500 mr-1 sm:mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs sm:text-sm text-red-800"><?= $errorMessage ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <p class="text-center mb-4 sm:mb-6 text-sm sm:text-base text-gray-600">Enter the 6-digit code sent to<br><strong><?= htmlspecialchars($email) ?></strong></p>

            <div class="code-input flex justify-between mb-4 sm:mb-6">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" maxlength="1" class="digit w-10 sm:w-12 h-12 sm:h-14 text-center border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors" data-index="<?= $i ?>" autocomplete="one-time-code">
                <?php endfor; ?>
            </div>

            <div class="terms mb-4 sm:mb-6">
                <label class="flex items-center">
                    <input type="checkbox" id="agreeTerms" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm sm:text-base text-gray-700">I agree to the <span id="showTerms" class="text-indigo-600 hover:text-indigo-500 cursor-pointer">Terms and Conditions</span></span>
                </label>
            </div>

            <button class="btn w-full bg-indigo-600 text-white py-2 sm:py-3 px-4 rounded-md sm:rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors text-sm sm:text-base" id="verifyBtn">Verify</button>
            <div class="timer mt-4 sm:mt-6 text-sm sm:text-base text-gray-600" id="timer">Code expires in 3:00</div>
            <div class="resend mt-4 sm:mt-6 text-sm sm:text-base text-gray-400 cursor-not-allowed" id="resend">Resend Code</div>
            <div class="error-message mt-4 sm:mt-6 text-xs sm:text-sm text-red-600" id="errorMessage"></div>
        </div>
    </div>

    <script>
        const inputs = document.querySelectorAll('.digit');
        const verifyBtn = document.getElementById('verifyBtn');
        const resendBtn = document.getElementById('resend');
        const timerElem = document.getElementById('timer');
        const errorElem = document.getElementById('errorMessage');
        const agreeTerms = document.getElementById('agreeTerms');
        
        // Get the expiration time from PHP session
        let codeExpiresAt = <?php echo $_SESSION['code_expires_at'] ?? (time() + 180); ?>;
        
        // Timer functionality
        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            const timeLeft = codeExpiresAt - now;
            
            if (timeLeft <= 0) {
                timerElem.textContent = 'Code expired.';
                verifyBtn.disabled = true;
                
                // Enable resend button
                resendBtn.classList.remove('cursor-not-allowed', 'text-gray-400');
                resendBtn.classList.add('text-indigo-600', 'hover:text-indigo-500', 'cursor-pointer');
                resendBtn.innerHTML = 'Resend Code';
                return;
            }
            
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElem.textContent = `Code expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Keep resend button disabled during countdown
            resendBtn.classList.add('cursor-not-allowed', 'text-gray-400');
            resendBtn.classList.remove('text-indigo-600', 'hover:text-indigo-500', 'cursor-pointer');
        }
        
        // Update timer immediately and then every second
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);

        // Focus first input
        inputs[0].focus();
        
        // Input handling with autofill support
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

            // Autofill handling
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text/plain').replace(/\D/g, '').slice(0, 6);
                if (pasteData.length === 6) {
                    inputs.forEach((input, idx) => {
                        input.value = pasteData[idx] || '';
                    });
                    inputs[5].dispatchEvent(new Event('input')); // Trigger auto-submit
                }
            });
        });

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
                    credentials: 'include',
                    body: new URLSearchParams({ code })
                });

                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Verification failed');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Verification Successful',
                    text: 'You are now verified and will be redirected.',
                    confirmButtonColor: '#28a745',
                    timer: 2000,
                    willClose: () => {
                        window.location.href = 'http://localhost:3000/dashboard';
                    }
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message,
                    confirmButtonColor: '#d33',
                    customClass: { popup: 'text-sm' }
                });
            } finally {
                verifyBtn.disabled = false;
                verifyBtn.classList.remove('loading');
            }
        }

        verifyBtn.addEventListener('click', verifyCode);

        resendBtn.addEventListener('click', function() {
            if (!this.classList.contains('text-indigo-600')) return;
            
            this.innerHTML = 'Sending...';
            this.style.pointerEvents = 'none';
            
            fetch('verify_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'include',
                body: new URLSearchParams({ resend: 'true' })
            })
            .then(async response => {
                const data = await response.json();
                resendBtn.innerHTML = 'Resend Code';
                resendBtn.style.pointerEvents = 'auto';
                
                if (data.success) {
                    // Update the expiration time
                    codeExpiresAt = data.expires_at || (Math.floor(Date.now() / 1000) + 180);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Code Resent',
                        text: 'Check your email inbox for the new code.',
                        confirmButtonColor: '#28a745',
                        customClass: { popup: 'text-sm' }
                    });
                    
                    // Reset inputs and focus
                    inputs.forEach(input => input.value = '');
                    inputs[0].focus();
                    verifyBtn.disabled = false;
                    
                    // Update timer immediately
                    updateTimer();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to Resend',
                        text: data.message || 'Could not resend verification code.',
                        confirmButtonColor: '#d33',
                        customClass: { popup: 'text-sm' }
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
                    confirmButtonColor: '#d33',
                    customClass: { popup: 'text-sm' }
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
                width: '800px',
                customClass: { popup: 'text-sm' }
            });
        });
    </script>
</body>
</html>