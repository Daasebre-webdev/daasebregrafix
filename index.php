<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';

// Handle Google callback first before any output
if (isset($_GET['code'])) {
    $client = new Google_Client();
    $client->setClientId($config['google']['client_id']);
    $client->setClientSecret($config['google']['client_secret']);
    $client->setRedirectUri($config['google']['redirect_uri']);
    $client->addScope('email');
    $client->addScope('profile');
    
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['access_token'])) {
        $client->setAccessToken($token['access_token']);
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $_SESSION['google_data'] = [
            'id' => $google_account_info->id,
            'email' => $google_account_info->email,
            'name' => $google_account_info->name,
            'picture' => $google_account_info->picture,
            'given_name' => $google_account_info->givenName,
            'family_name' => $google_account_info->familyName
        ];
        
        $email = $google_account_info->email;

        $pdo = null;
        try {
            $pdo = new PDO(
                "mysql:host={$config['db']['host']};dbname={$config['db']['name']}",
                $config['db']['user'],
                $config['db']['pass']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $_SESSION['error'] = "Database connection failed. Please try again later.";
            header('Location: index.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            if ($user['is_verified']) {
                header('Location: http://localhost:3000/dashboard');
                exit;
            } else {
                $_SESSION['email_to_verify'] = $email;
                header('Location: verify_email.php');
                exit;
            }
        } else {
            header('Location: google_complete_signup.php');
            exit;
        }
    } else {
        error_log("Token retrieval failed: " . print_r($token, true));
        $_SESSION['error'] = "Google authentication failed. Please try again.";
        header('Location: index.php');
        exit;
    }
}

$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']}",
        $config['db']['user'],
        $config['db']['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');
$authUrl = $client->createAuthUrl();

$email = $_COOKIE['remember_email'] ?? '';
$password = $_COOKIE['remember_password'] ?? '';

if (!empty($email) && !empty($password) && $pdo && !$pdo->errorInfo()[1] && empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id, password, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_attempts'] = 0;
            if ($user['is_verified']) {
                header('Location: http://localhost:3000/dashboard');
                exit;
            } else {
                $_SESSION['email_to_verify'] = $email;
                header('Location: verify_email.php');
                exit;
            }
        }
    }
}

if (!empty($_SESSION['user_id']) && $pdo && basename($_SERVER['SCRIPT_NAME']) === 'index.php') {
    $stmt = $pdo->prepare("SELECT is_verified, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($user['is_verified']) {
            header('Location: http://localhost:3000/dashboard');
            exit;
        } else {
            $_SESSION['email_to_verify'] = $user['email'];
            header('Location: verify_email.php');
            exit;
        }
    }
}

$errors = [];
$userNotFound = false;
$wrongPassword = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    }

    if (empty($errors) && $pdo) {
        $stmt = $pdo->prepare("SELECT id, password, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['login_attempts'] = 0;
                if (isset($_POST['remember-me']) && $_POST['remember-me']) {
                    setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/');
                    setcookie('remember_password', $password, time() + (30 * 24 * 60 * 60), '/');
                } else {
                    setcookie('remember_email', '', time() - 3600, '/');
                    setcookie('remember_password', '', time() - 3600, '/');
                }
                if ($user['is_verified']) {
                    header('Location: http://localhost:3000/dashboard');
                    exit;
                } else {
                    $_SESSION['email_to_verify'] = $email;
                    header('Location: verify_email.php');
                    exit;
                }
            } else {
                $wrongPassword = true;
                $errors[] = "Invalid password";
                $_SESSION['login_attempts']++;
            }
        } else {
            $userNotFound = true;
            $errors[] = "No account found with this email. Please sign up.";
            $_SESSION['login_attempts']++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - ProjectPulse</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 py-4 px-2 sm:py-6 sm:px-4">
  <div class="max-w-md w-full mx-auto">
    <div class="text-center mb-6 sm:mb-8">
      <!-- Updated ProjectPulse Icon -->
      <div class="flex justify-center mb-3 sm:mb-4">
        <div class="w-20 h-20 sm:w-24 sm:h-24 bg-blue-100 rounded-full flex items-center justify-center shadow-md">
          <svg 
            class="w-12 h-12 sm:w-14 sm:h-14 text-blue-600" 
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
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Welcome to ProjectPulse</h1>
      <p class="mt-1 sm:mt-2 text-sm sm:text-base text-gray-600">Sign in to your account</p>
    </div>

        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-8">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 sm:mb-6 bg-red-50 border border-red-200 rounded-md sm:rounded-lg p-3 sm:p-4">
                    <div class="flex items-center">
                        <svg class="w-4 sm:w-5极速赛车开奖直播历史记录 h-4 sm:h-5 text-red-500 mr-1 sm:mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10极速赛车开奖直播历史记录l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs sm:text-sm text-red-800"><?= htmlspecialchars($_SESSION['error']) ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if ($userNotFound): ?>
                <div class="mb-4 sm:mb-6 bg-blue-50 border border-blue-200 rounded-md sm:rounded-lg p-3 sm:p-4">
                    <div class="flex items-center">
                        <svg class="w-4 sm:w-5 h-4 sm:h-5 text-blue-500 mr-1 sm:mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs sm:text-sm text-blue-800">
                            No account found with this email. 
                            <a href="signup.php" class="font-medium text-blue-600 hover:text-blue-500">Sign up here</a> or use Google Sign-In.
                        </span>
                    </div>
                </div>
            <?php elseif ($wrongPassword): ?>
                <div class="mb-4 sm:mb-6 bg-red-50 border border-red-200 rounded-md sm:rounded-lg p-3 sm:p-4">
                    <div class="flex items-center">
                        <svg class="w-4 sm:w-5 h-4 sm:h-5 text-red-500 mr-1 sm:mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 极速赛车开奖直播历史记录0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs sm:text-sm text-red-800">Invalid password. Please try again or <a href="forgot_password.php" class="font-medium text-red-600 hover:text-red-500">reset your password</a>.</span>
                    </div>
                </div>
            <?php elseif (!empty($errors)): ?>
                <div class="mb-4 sm极速赛车开奖直播历史记录:mb-6 bg-red-50 border border-red-200 rounded-md sm:rounded-lg p-3 sm:p-4">
                    <div class="flex items-center">
                        <svg class="w-4 sm:w-5 h-4 sm:h-5 text-red-500 mr-1 sm:mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293极速赛车开奖直播历史记录z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs sm:text-sm text-red-800"><?= htmlspecialchars(implode(", ", $errors)) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="space-y-4 sm:space-y-极速赛车开奖直播历史记录6" id极速赛车开奖直播历史记录="loginForm">
                <div>
                    <label for="email" class="block text-sm sm:text-base font-medium text-gray-700 mb-1 sm:mb-2">Email Address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors text-sm sm:text-base"
                        placeholder="Enter your email"
                        value="<?= htmlspecialchars($email) ?>"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm sm:text-base font-medium text-gray-700 mb-1 sm:mb-2">Password</label>
                    <div class="relative">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors pr-10 sm:极速赛车开奖直播历史记录pr-12 text-sm sm:text-base"
                            placeholder="Enter your password"
                            value="<?= htmlspecialchars($password) ?>"
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-2 sm:right-3 flex items-center"
                            onclick="togglePassword('password', this)"
                        >
                            <svg class="h-4 sm:h-5 w-4 sm:w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4极速赛车开奖直播历史记录.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between space-y-2 sm:space-y-0 sm:space-x-2">
                    <div class="flex items-center">
                        <input
                            id="remember-me"
                            name="remember-me"
                            type="checkbox"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            <?= isset($_COOKIE['remember_email']) ? 'checked' : '' ?>
                        >
                        <label for="remember-me" class="ml-2 block text-sm text-gray-700">Remember me</label>
                    </div>
                    <a
                        href="forgot_password.php"
                        id="forgotPasswordLink"
                        class="text-sm transition-colors <?= $_SESSION['login_attempts'] < 3 ? 'text-gray-400 cursor-not-allowed' : 'text-indigo-600 hover:text-indigo-500' ?>"
                        <?= $_SESSION['login_attempts'] < 3 ? 'onclick="return false;"' : '' ?>
                    >
                        Forgot password?
                    </a>
                </div>

                <button
                    type="submit"
                    id="loginButton"
                    class="w-full bg-indigo-600 text-white py-2 sm:py-3 px-4 rounded-md sm:rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors text-sm sm:text-base"
                >
                    Sign in
                </button>
            </form>

            <div class="mt-6 sm:mt-8">
                <div class="relative flex items-center">
                    <div class="flex-grow border-t border-gray-300"></div>
                    <span class="flex-shrink mx-4 text-gray-极速赛车开奖直播历史记录600 text-sm">Or continue with</span>
                    <div class="flex-grow border-t border-gray-300"></div>
                </div>

                <div class="mt-3 sm:mt-4 grid grid-cols-1 gap-2 sm:gap-3">
                    <a
                        href="<?= htmlspecialchars($authUrl) ?>"
                        id="googleSignInButton"
                        class="w-full inline-flex justify-center py-2 sm:py-3 px-3 sm:px-4 border border-gray-300 rounded-md sm:rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                    >
                        <svg class="w-5 sm:w-6 h-5 sm:h-6" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77极速赛车开奖直播历史记录h3.57c2.08-1.91 3.28-4.7 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14极速赛车开奖直播历史记录.09c-.22-.66-.35-1.36-.35-2.09s.13-极速赛车开奖直播历史记录1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <极速赛车开奖直播历史记录path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c极速赛车开奖直播历史记录.87-2.6 3.3-4.53 极速赛车开奖直播历史记录6.极速赛车开奖直播历史记录16-4.53z"/>
                        </svg>
                        <span class="ml-2">Sign in with Google</span>
                    </a>
                </div>
            </div>

            <div class="mt-4 sm:mt-6 text-center">
                <p class="text-xs sm:text-sm text-gray-600">
                    Don't have an account? <a href="signup.php" class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors">Sign up</a>
                </p>
            </div>
        </div>

        <!-- Spinner overlay -->
        <div id="spinnerOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white p-5 rounded-lg shadow-lg flex items-center">
                <div class="spinner mr-3"></div>
                <span>Signing in...</span>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);
            const isPasswordVisible = field.type === 'text';
            field.type = isPasswordVisible ? 'password' : 'text';
            const svg = button.querySelector('svg');
            if (isPasswordVisible) {
                svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532极速赛车开奖直播历史记录l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5极速赛车开奖直播极速赛车开奖直播历史记录历史记录c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
            } else {
                svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
            }
        }

        // Show spinner on form submission
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('spinnerOverlay').classList.remove('hidden');
            document.getElementById('loginButton').classList.add('btn-loading');
        });

        // Show spinner on Google sign-in
        document.getElementById('googleSignInButton').addEventListener('click', function() {
            document.getElementById('spinnerOverlay').classList.remove('hidden');
        });

        <?php if ($userNotFound): ?>
        Swal.fire({
            icon: 'info',
            title: 'Not Registered?',
            text: 'No account found with this email. Please sign up or use Google Sign-In.',
            confirmButtonText: 'Sign Up',
            confirmButtonColor: '#2f773d',
            showCancelButton: true,
            cancelButtonText: 'Try Again',
            customClass: { popup: 'text-sm' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'signup.php';
            }
        });
        <?php elseif ($wrongPassword): ?>
        Swal.fire({
            icon: 'error',
            title: 'Invalid Password',
            text: 'The password you entered is incorrect. Please try again or reset your password.',
            confirmButtonColor: '#d33',
            customClass: { popup: 'text-sm' }
        });
        <?php elseif (!empty($errors)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: '<?= htmlspecialchars(implode(", ", $errors)) ?>',
            confirmButtonColor: '#d33',
            customClass: { popup: 'text-sm' }
        });
        <?php endif; ?>
    </script>
</body>
</html>