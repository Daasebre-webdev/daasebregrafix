<?php
session_start();
$config = require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check if this is a Google signup flow
if (isset($_SESSION['google_signup']) && $_SESSION['google_signup']) {
    header('Location: google_complete_signup.php');
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
        $config['db']['user'],
        $config['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found.");
    }

    // If password already exists, redirect to dashboard
    if (!empty($user['password'])) {
        header('Location: dashboard.php');
        exit;
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Set Your Password</title>
  <style>
    body { font-family: Arial; max-width: 400px; margin: 40px auto; }
    input { width: 100%; margin: 8px 0; padding: 10px; }
    button { padding: 10px; width: 100%; }
    .error { color: red; }
  </style>
</head>
<body>
<h2>Set Your Password</h2>

<?php if (isset($_SESSION['password_error'])): ?>
  <div class="error"><?= htmlspecialchars($_SESSION['password_error']) ?></div>
  <?php unset($_SESSION['password_error']); ?>
<?php endif; ?>

<form method="POST" action="set_password_inline.php">
  <input type="password" name="password" placeholder="New Password" required>
  <input type="password" name="confirm" placeholder="Confirm Password" required>
  <button type="submit">Set Password</button>
</form>
</body>
</html>