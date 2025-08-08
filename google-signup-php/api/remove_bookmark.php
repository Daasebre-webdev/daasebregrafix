<?php
require_once '../lib/db.php';
header("Content-Type: application/json");

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['user_id']) || empty($data['project_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE user_id = ? AND project_id = ?");
    $stmt->execute([$data['user_id'], $data['project_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>