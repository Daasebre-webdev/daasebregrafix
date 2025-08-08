<?php
session_start(); // ✅ Needed to access session data

require_once '../lib/db.php';

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $response = [
        'loggedIn' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null,
    ];

    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['project'] = $project ?: null;
    } else {
        $stmt = $db->query("SELECT * FROM projects");
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['projects'] = $projects;
    }

    echo json_encode($response);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
