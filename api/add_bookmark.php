<?php
// api/add_bookmark.php
require_once '../lib/db.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:3000");

$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['user_id']) || empty($data['project_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Missing user_id or project_id']));
}

try {
    $pdo = getDB();
    
    // Check if bookmark already exists
    $checkStmt = $pdo->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND project_id = ?");
    $checkStmt->execute([$data['user_id'], $data['project_id']]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Already bookmarked']);
        exit;
    }

    // Add new bookmark
    $stmt = $pdo->prepare("INSERT INTO bookmarks (user_id, project_id) VALUES (?, ?)");
    $stmt->execute([$data['user_id'], $data['project_id']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>