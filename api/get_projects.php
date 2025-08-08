<?php
require_once '../lib/db.php';
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$pdo = getDB();
$userId = $_GET['user_id'] ?? null;

try {
    // Get default projects (user_id IS NULL) + user's projects
    $query = "SELECT * FROM projects WHERE user_id IS NULL";
    $params = [];
    
    if ($userId) {
        $query .= " OR user_id = ?";
        $params[] = $userId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize by category like your original projects.json structure
    $organized = [];
    foreach ($projects as $project) {
        $category = $project['category'] ?? 'Uncategorized';
        if (!isset($organized[$category])) {
            $organized[$category] = [];
        }
        $organized[$category][] = $project;
    }
    
    echo json_encode([
        'success' => true,
        'projects' => $organized
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>