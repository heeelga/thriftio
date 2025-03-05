<?php
include('init.php');
include('dbconnection.php');

    // Eingeloggter Benutzer
    $username = $_SESSION['username'];

    // Monat und Jahr aus der Anfrage abrufen
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);

    // Dynamische Tabellenauswahl basierend auf dem Benutzernamen
    $table_name = $conn->real_escape_string($username);

    try {
        global $pdo;

        // Abrufen aller Einträge für den angegebenen Monat und Jahr
        $stmt = $pdo->prepare("
            SELECT id, rebooking_id, rebooking_partner 
            FROM `$table_name` 
            WHERE entry_month = ? AND entry_year = ?
        ");
        $stmt->execute([$month, $year]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Löschen der Einträge und ihrer Partner
        foreach ($entries as $entry) {
            $entry_id = intval($entry['id']);
            $rebooking_id = intval($entry['rebooking_id']);
            $rebooking_partner = $entry['rebooking_partner'];

            // Eintrag im aktuellen Konto löschen
            $stmt = $pdo->prepare("DELETE FROM `$table_name` WHERE id = ?");
            $stmt->execute([$entry_id]);

            // Falls es sich um eine Umbuchung handelt, auch den Partner-Eintrag löschen
            if ($rebooking_id > 0 && $rebooking_partner) {
                $partner_table = ($rebooking_partner === 'main') ? $username : $username . "_" . $rebooking_partner;

                try {
                    $stmt = $pdo->prepare("DELETE FROM `$partner_table` WHERE rebooking_id = ?");
                    $stmt->execute([$rebooking_id]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Löschen in der Partner-Tabelle `$partner_table`: " . $e->getMessage());
                }
            }
        }

        echo "Einträge erfolgreich gelöscht.";
    } catch (PDOException $e) {
        // Fehlerprotokollierung (optional)
        error_log("Fehler beim Löschen der Einträge: " . $e->getMessage());
        echo "Fehler: " . $e->getMessage();
    }

    $pdo = null; // Verbindung schließen
} else {
    echo "Ungültige Anfrage.";
}
?>
