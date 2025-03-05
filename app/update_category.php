<?php
include('init.php');
include 'dbconnection.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? intval($data['id']) : null;
$category = isset($data['category']) ? trim($data['category']) : null;

if (!$id) {
    echo json_encode(["success" => false, "message" => "Keine ID angegeben."]);
    exit;
}

$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username);

$sql = "UPDATE `$table_name` SET category = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Fehler beim Vorbereiten des Statements: " . $conn->error]);
    exit;
}

$stmt->bind_param("si", $category, $id);
if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Kategorie erfolgreich aktualisiert."]);
} else {
    echo json_encode(["success" => false, "message" => "Fehler beim Aktualisieren der Kategorie: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
