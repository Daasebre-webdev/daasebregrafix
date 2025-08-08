// api/verify_session.php
<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

$response = [
    'loggedIn' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'session_id' => session_id()
];

echo json_encode($response);
?>