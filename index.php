<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';

$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']}",
        $config['db']['user'],
        $config['db']['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // Allow form to render on DB error
}

// Google Auth URL
$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');
$authUrl = $client->createAuthUrl();

// Redirect Google OAuth callback
if (isset($_GET['code'])) {
    header('Location: google_callback.php?' . $_SERVER['QUERY_STRING']);
    exit;
}

// Redirect if logged in
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

// Handle login
$errors = [];
$userNotFound = false;

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

                if ($user['is_verified']) {
                    header('Location: http://localhost:3000/dashboard');
                    exit;
                } else {
                    $_SESSION['email_to_verify'] = $email;
                    header('Location: verify_email.php');
                    exit;
                }
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $userNotFound = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Project Pulse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            min-height: 100vh;
        }
        .container { display: flex; width: 100%; }
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
        h2 { font-size: 2em; margin-bottom: 20px; }
        form { display: flex; flex-direction: column; }
        .input-group {
            display: flex;
            align-items: center;
            background: #e6f4ff;
            padding: 10px 15px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        .input-group i { margin-right: 10px; color: #333; }
        .input-group input {
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            font-size: 1em;
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
        .btn:hover { background: #256632; }
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
        .google-btn i { margin-right: 8px; font-size: 1.1em; }
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
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <h2>Login</h2>
            <form method="post" action="">
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                </div>
                <button class="btn" type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>

            <a class="google-btn" href="<?= htmlspecialchars($authUrl) ?>">
                <i class="fab fa-google"></i> Sign in with Google
            </a>

            <div class="link">
                Don't have an account? <a href="signup.php">Sign up here</a>
            </div>
        </div>

        <div class="right-panel">
            <div class="right-content">
                <h2>Welcome Back</h2>
                <p>Log in to access your personalized project topics and manage your account. Let's get started!</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('fa-eye-slash');
        }

        <?php if ($userNotFound): ?>
        Swal.fire({
            icon: 'info',
            title: 'Not Registered?',
            text: 'It looks like you haven’t signed up yet. Please sign up or use Google Sign-In.',
            confirmButtonColor: '#2f773d'
        });
        <?php elseif (!empty($errors)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: '<?= htmlspecialchars(implode(", ", $errors)) ?>',
            confirmButtonColor: '#d33'
        });
        <?php endif; ?>
    </script>
</body>
</html>
