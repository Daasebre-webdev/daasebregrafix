<?php
session_start();
$config = require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = new PDO(
    "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
    $config['db']['user'],
    $config['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Get user info
$stmt = $pdo->prepare("SELECT name, email, picture, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check password presence
$stmtPwd = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmtPwd->execute([$_SESSION['user_id']]);
$passwordSet = !empty($stmtPwd->fetchColumn());

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <style>
    body { font-family: Arial; margin: 20px; }
    .header { display: flex; justify-content: space-between; align-items: center; }
    .avatar { width: 50px; border-radius: 50%; }
    .card { padding: 20px; border: 1px solid #ccc; border-radius: 8px; max-width: 400px; margin: 20px auto; }
    .logout { text-decoration: none; color: #f00; }
    .modal { display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; justify-content: center; align-items: center; background: rgba(0,0,0,0.6); z-index: 9999; }
    .modal-content { background: white; padding: 20px; border-radius: 8px; max-width: 400px; width: 90%; box-shadow: 0 0 10px rgba(0,0,0,0.3); text-align: center; }
    .modal-content input { width: 100%; padding: 10px; margin: 5px 0; }
    .modal-content button { padding: 10px 20px; margin-top: 10px; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>
    <?php if ($user['picture']): ?>
      <img class="avatar" src="<?= htmlspecialchars($user['picture']) ?>" alt="Avatar">
    <?php endif; ?>
    <a class="logout" href="logout.php">Logout</a>
  </div>

  <div class="card">
    <p>Email: <?= htmlspecialchars($user['email']) ?></p>
    <p>Joined: <?= date('F Y', strtotime($user['created_at'])) ?></p>
  </div>

  <?php if (!$passwordSet): ?>
    <div class="modal">
      <div class="modal-content">
        <h3>Set Your Password</h3>
        <?php if (!empty($_SESSION['password_error'])): ?>
          <p style="color:red"><?= $_SESSION['password_error'] ?></p>
          <?php unset($_SESSION['password_error']); ?>
        <?php elseif (!empty($_SESSION['password_success'])): ?>
          <p style="color:green">Password set successfully!</p>
          <?php unset($_SESSION['password_success']); ?>
        <?php endif; ?>
        <form method="post" action="set_password_inline.php">
          <input type="password" name="password" placeholder="New Password" required>
          <input type="password" name="confirm" placeholder="Confirm Password" required>
          <button type="submit">Set Password</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</body>
</html>
