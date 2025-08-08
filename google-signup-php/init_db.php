<?php
require_once 'lib/db.php';

header('Content-Type: application/json');

$db = getDB();

$jsonFile = file_get_contents('projects.json');
$data = json_decode($jsonFile, true);

if ($data === null) {
    echo json_encode(['error' => 'Failed to decode JSON file']);
    exit;
}

$projects = $data['projects'];

try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO projects (id, title, category, description, details) VALUES (?, ?, ?, ?, ?)");

    foreach ($projects as $category => $projectList) {
        foreach ($projectList as $project) {
            $id = $project['id'];
            $title = $project['title'];
            $category = $project['category'];
            $description = $project['description'];
            $details = json_encode($project['details']);

            $stmt->execute([$id, $title, $category, $description, $details]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Database initialized successfully']);
} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>