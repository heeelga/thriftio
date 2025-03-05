<?php
include('init.php');
include 'dbconnection.php';

$username = $_SESSION['username'];
$data = json_decode(file_get_contents('php://input'), true);

$entryId = intval($data['id']);

if (!$entryId) {
    echo json_encode(["success" => false, "message" => "Ungültige ID."]);
    exit;
}

try {
    $table_name = $conn->real_escape_string($username);

    // Update hidden_override für den entsprechenden Eintrag
    $stmt = $conn->prepare("UPDATE `$table_name` SET hidden_override = 1 WHERE id = ?");
    $stmt->bind_param("i", $entryId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Der Eintrag wurde erfolgreich ausgeblendet."]);
    } else {
        echo json_encode(["success" => false, "message" => "Der Eintrag konnte nicht aktualisiert werden oder existiert nicht."]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Datenbankfehler: " . $e->getMessage()]);
}
