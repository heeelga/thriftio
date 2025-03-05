<?php
include('init.php');
include 'dbconnection.php';

$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username);

$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['ids']) && is_array($data['ids']) && count($data['ids']) > 0) {
    // Platzhalter für Prepared Statement erstellen
    $placeholders = implode(',', array_fill(0, count($data['ids']), '?'));

    // IDs in Integer konvertieren, um Sicherheit zu gewährleisten
    $ids = array_map('intval', $data['ids']);

    try {
        // Prüfen, ob sich wiederholende Elemente in der Auswahl enthalten sind
        $checkRecurringSql = "SELECT id FROM `$table_name` WHERE id IN ($placeholders) AND recurring != 'no'";
        $checkRecurringStmt = $conn->prepare($checkRecurringSql);

        if (!$checkRecurringStmt) {
            throw new Exception("Fehler beim Erstellen der Überprüfungsabfrage: " . $conn->error);
        }

        $checkRecurringStmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $checkRecurringStmt->execute();
        $result = $checkRecurringStmt->get_result();

        if ($result->num_rows > 0) {
            // Es wurden wiederholende Einträge gefunden
            echo json_encode(['success' => false, 'message' => 'Die Auswahl enthält Serienelemente und kann nicht ausgeblendet werden. Serien können nur einzeln ausgeblendet werden.']);
            $checkRecurringStmt->close();
            exit;
        }

        $checkRecurringStmt->close();

        // Update der ausgewählten Einträge
        $stmt = $conn->prepare("UPDATE `$table_name` SET is_hidden = 1 WHERE id IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Die ausgewählten Einträge wurden erfolgreich ausgeblendet.']);
            } else {
                throw new Exception("Fehler beim Ausführen des Updates: " . $stmt->error);
            }

            $stmt->close();
        } else {
            throw new Exception("Fehler beim Erstellen der Update-Abfrage: " . $conn->error);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage.']);
}

$conn->close();
?>
