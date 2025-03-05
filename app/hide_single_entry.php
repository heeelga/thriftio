<?php
include('init.php');
include 'dbconnection.php';

$username = $_SESSION['username'];
$data = json_decode(file_get_contents('php://input'), true);

$entryId = intval($data['id']);
$month = intval($data['month']);
$year = intval($data['year']);

if (!$entryId || !$month || !$year) {
    echo json_encode(["success" => false, "message" => "Ungültige Daten."]);
    exit;
}

try {
    $table_name = $conn->real_escape_string($username);

    // Prüfen, ob der Eintrag bereits ausgeblendet wurde
    $checkSql = "SELECT id FROM `$table_name` WHERE override_id = ? AND entry_month = ? AND entry_year = ? AND hidden_override = 1";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("iii", $entryId, $month, $year);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Dieses Serienelement wurde bereits ausgeblendet."]);
        exit;
    }

    // Neuen Ausblenden-Eintrag erstellen
    $sql = "INSERT INTO `$table_name` (type, amount, description, recurring, entry_month, entry_year, hidden_override, override_id)
            SELECT type, amount, description, recurring, ?, ?, 1, id FROM `$table_name` WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $month, $year, $entryId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Fehler beim Ausblenden des Serienelements."]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Datenbankfehler: " . $e->getMessage()]);
}
?>