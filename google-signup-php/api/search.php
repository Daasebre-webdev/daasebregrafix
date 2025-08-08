<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once '../lib/db.php';

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: http://localhost:3000');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');

    $db = getDB();
    if (!$db) {
        throw new Exception('Failed to connect to the database.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['q'])) {
        $query = '%' . $_GET['q'] . '%';
        $stmt = $db->prepare("SELECT * FROM projects WHERE title LIKE ? OR description LIKE ?");
        $stmt->execute([$query, $query]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($projects);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid search query']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>