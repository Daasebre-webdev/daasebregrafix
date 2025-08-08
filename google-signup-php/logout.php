<?php
session_start(); // Required to access and destroy the session

// CORS headers
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Unset all session variables
    $_SESSION = [];

    // Remove session cookie
    if (ini_get("session.use_cookies")) {
        setcookie(session_name(), '', time() - 42000, '/');
    }

    // Destroy session
    session_destroy();

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Logged out']);
    exit;
} else {
    http_response_code(405); // Method not allowed
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
