<?php
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

// Session-based authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // ✅ Fetch user data including is_verified and agreed_to_terms
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

    // ✅ Check if the user is verified and has accepted terms
    if ((int)$userData['is_verified'] !== 1 || (int)$userData['agreed_to_terms'] !== 1) {
        http_response_code(403);
        echo json_encode(['error' => 'User not verified or has not agreed to terms']);
        exit;
    }

    // ✅ Return user data (excluding is_verified and agreed_to_terms)
    unset($userData['is_verified'], $userData['agreed_to_terms']);
    echo json_encode($userData);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
