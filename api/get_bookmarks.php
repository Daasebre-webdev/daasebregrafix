<?php
require_once '../lib/db.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:3000"); // Allow React to access

$userId = $_GET['user_id'] ?? null;
if (!$userId) die(json_encode([]));

$pdo = getDB(); // From your db.php connection
$stmt = $pdo->prepare("SELECT project_id FROM bookmarks WHERE user_id = ?");
$stmt->execute([$userId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>