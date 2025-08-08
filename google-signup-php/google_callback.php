<?php
session_start();
require 'vendor/autoload.php';
$config = require 'config.php';

// Setup Google Client
$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope("email");
$client->addScope("profile");

if (!isset($_GET['code'])) {
    exit('Google Auth Error: No code returned.');
}

try {
    // Exchange code for token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        throw new Exception('Google Auth Error: ' . $token['error']);
    }

    $client->setAccessToken($token['access_token']);
    $oauth2 = new Google_Service_Oauth2($client);
    $googleUser = $oauth2->userinfo->get();

    $googleId = $googleUser->id;
    $email = $googleUser->email;
    $name = $googleUser->name;
    $picture = $googleUser->picture;

    // Connect to DB
    $pdo = new PDO(
        'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['name'],
        $config['db']['user'],
        $config['db']['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Find user by Google ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Try finding by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingEmail = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingEmail) {
            // Link Google ID
            $stmt = $pdo->prepare("UPDATE users SET google_id = ?, name = ?, picture = ?, is_verified = 0 WHERE id = ?");
            $stmt->execute([$googleId, $name, $picture, $existingEmail['id']]);
            $userId = $existingEmail['id'];
        } else {
            // Insert new user with is_verified = 0 and no password yet
            $stmt = $pdo->prepare("INSERT INTO users (google_id, name, email, picture, is_verified, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$googleId, $name, $email, $picture]);
            $userId = $pdo->lastInsertId();
        }
    } else {
        $userId = $user['id'];
    }

    $_SESSION['user_id'] = $userId;

    // Fetch user info to check if password is set
    $stmt = $pdo->prepare("SELECT password, is_verified FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($user['password'])) {
        // No password yet — go to password + OTP setup
        header("Location: google_complete_signup.php");
        exit;
    }

    if (!$user['is_verified']) {
        // Password exists, but not verified yet
        header("Location: verify_email.php");
        exit;
    }

    // All good: redirect to dashboard
    header("Location: http://localhost:3000/dashboard");
    exit;
} catch (Exception $e) {
    exit('Google Auth Error: ' . $e->getMessage());
}
