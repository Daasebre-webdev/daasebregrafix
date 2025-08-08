<?php
require_once '../lib/db.php';
header("Content-Type: application/json");

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ?");
$stmt->execute([$userId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>