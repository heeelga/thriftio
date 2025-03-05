<?php
include('init.php');
include('dbconnection.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['username'])) {
        echo json_encode(["error" => "Benutzer ist nicht eingeloggt."]);
        exit;
    }

    $username = $_SESSION['username'];
    $entry_id = intval($_GET['id']);

    try {
        global $pdo;

        // Eintragsdetails aus der Haupttabelle abrufen
        $stmt = $pdo->prepare("SELECT id, rebooking_id, rebooking_partner, type, amount, description, entry_month, entry_year, recurring, repeat_until_month, repeat_until_year, end_date, override_id FROM `$username` WHERE id = ?");
        $stmt->execute([$entry_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            echo json_encode(["error" => "Eintrag nicht gefunden."]);
            exit;
        }

        // Falls es sich um einen Override handelt, Originaldetails laden und
        // den effective Wert für "id" auf die Original-ID setzen.
        if (!is_null($entry['override_id'])) {
            $original_id = intval($entry['override_id']);
            // Ersetze die ID durch die Original-ID
            $entry['id'] = $original_id;

            // Zusätzliche Originaldetails abrufen (optional)
            $stmtOriginal = $pdo->prepare("SELECT amount, description, recurring, repeat_until_month, repeat_until_year FROM `$username` WHERE id = ?");
            $stmtOriginal->execute([$original_id]);
            $original_entry = $stmtOriginal->fetch(PDO::FETCH_ASSOC);
            if ($original_entry) {
                $entry['original_amount'] = $original_entry['amount'];
                $entry['original_description'] = $original_entry['description'];
                $entry['original_recurring'] = $original_entry['recurring'];
                $entry['original_repeat_until_month'] = $original_entry['repeat_until_month'];
                $entry['original_repeat_until_year'] = $original_entry['repeat_until_year'];
            }
        }

        // Zusätzliche Informationen für Umbuchungen abrufen
        if ($entry['rebooking_id'] > 0) {
            $partner_table = ($entry['rebooking_partner'] === 'main') ? $username : $username . "_" . $entry['rebooking_partner'];

            $stmt = $pdo->prepare("SELECT id, type, amount, description, rebooking_partner FROM `$partner_table` WHERE rebooking_id = ?");
            $stmt->execute([$entry['rebooking_id']]);
            $partner_entry = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($partner_entry) {
                // Umgekehrte Zuordnung: Quelle und Ziel
                if ($entry['type'] === 'expense') {
                    $entry['target_account'] = ($entry['rebooking_partner'] === 'main') ? 'main' : $entry['rebooking_partner'];
                    $entry['source_account'] = ($partner_entry['rebooking_partner'] === 'main') ? 'main' : str_replace($username . "_", '', $partner_entry['rebooking_partner']);
                } else {
                    $entry['target_account'] = ($partner_entry['rebooking_partner'] === 'main') ? 'main' : str_replace($username . "_", '', $partner_entry['rebooking_partner']);
                    $entry['source_account'] = ($entry['rebooking_partner'] === 'main') ? 'main' : $entry['rebooking_partner'];
                }
            }
        }

        // Zusätzliche Felder für "Regelmäßig?" und "Wiederholen bis"
        $entry['recurring'] = $entry['recurring'] ?? 'no';

        // Enddatum aus `end_date` extrahieren, falls gesetzt
        if (!empty($entry['end_date']) && $entry['end_date'] !== '00.0000') {
            list($end_month, $end_year) = explode('.', $entry['end_date']);
            $entry['repeat_until_month'] = intval($end_month);
            $entry['repeat_until_year'] = intval($end_year);
        } else {
            $entry['repeat_until_month'] = null;
            $entry['repeat_until_year'] = null;
        }

        echo json_encode($entry);
    } catch (PDOException $e) {
        echo json_encode(["error" => "Fehler beim Abrufen der Daten: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "Ungültige Anfrage."]);
}
?>
