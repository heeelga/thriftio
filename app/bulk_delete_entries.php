<?php
include('init.php');
include 'dbconnection.php';

$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username);

$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['ids']) && is_array($data['ids']) && count($data['ids']) > 0) {
    // IDs in Integer konvertieren
    $ids = array_map('intval', $data['ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $conn->begin_transaction();

        foreach ($ids as $entryId) {
            $entryId = intval($entryId);

            // Prüfen, ob der Eintrag eine Serie ist
            $checkRecurringSql = "SELECT recurring FROM `$table_name` WHERE id = ?";
            $checkRecurringStmt = $conn->prepare($checkRecurringSql);
            if (!$checkRecurringStmt) {
                throw new Exception("Fehler beim Erstellen des CheckRecurring-Statements: " . $conn->error);
            }
            $checkRecurringStmt->bind_param("i", $entryId);
            $checkRecurringStmt->execute();
            $checkRecurringStmt->bind_result($recurring);
            $checkRecurringStmt->fetch();
            $checkRecurringStmt->close();

            if ($recurring !== 'no') {
                // Serienelement gefunden, Verarbeitung abbrechen
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Die Auswahl enthält Serienelemente und kann nicht gelöscht werden. Serien können nur einzeln gelöscht werden.']);
                exit;
            }

            // Normale Einträge dauerhaft löschen
            $deleteSql = "DELETE FROM `$table_name` WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            if (!$deleteStmt) {
                throw new Exception("Fehler beim Erstellen des Delete-Statements: " . $conn->error);
            }
            $deleteStmt->bind_param("i", $entryId);

            if (!$deleteStmt->execute()) {
                throw new Exception("Fehler beim Löschen der ID $entryId: " . $conn->error);
            }

            $deleteStmt->close();
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Die ausgewählten Einträge wurden erfolgreich gelöscht.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage.']);
}

$conn->close();
?>
