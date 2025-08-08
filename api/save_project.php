<?php
require_once '../lib/db.php';
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$pdo = getDB();
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['user_id']) || empty($data['title'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO projects (user_id, title, category, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $data['user_id'],
        $data['title'],
        $data['category'] ?? 'Uncategorized',
        $data['description'] ?? ''
    ]);
    
    // Return the newly created project
    $projectId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $newProject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'project' => $newProject
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>