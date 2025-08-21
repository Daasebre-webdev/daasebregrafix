<?php
ini_set('session.cookie_samesite', 'Lax'); // or 'None' if cross-site
ini_set('session.cookie_secure', '0'); // 0 for HTTP, 1 for HTTPS

session_start();
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Database connection
$host = 'localhost';
$db = 'project_selector';
$user = 'root';
$pass = '';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $mysqli->prepare("SELECT id, name, email, picture, is_verified, agreed_to_terms FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();

    if (!$userData) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    if ((int)$userData['is_verified'] !== 1 || (int)$userData['agreed_to_terms'] !== 1) {
        http_response_code(403);
        echo json_encode(['error' => 'User not verified or has not agreed to terms']);
        exit;
    }

    // Process picture URL
    if (!empty($userData['picture'])) {
        if (!preg_match('/^https?:\/\//i', $userData['picture'])) {
            // For local files, ensure proper URL structure
            $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $userData['picture'] = rtrim($baseUrl, '/') . '/uploads/' . basename($userData['picture']);
        }
    }

    unset($userData['is_verified'], $userData['agreed_to_terms']);
    echo json_encode($userData);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}