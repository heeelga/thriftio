<?php
include('init.php');
include 'dbconnection.php';

// Prüfen, ob ein Nutzer eingeloggt ist
if (!isset($_SESSION['username'])) {
    echo json_encode(["success" => false, "message" => "Kein Nutzer eingeloggt."]);
    exit;
}

// Eingabedaten abrufen
$id              = intval($_POST['id']);
$amount          = floatval($_POST['amount'] ?? 0);
$description     = $_POST['description'] ?? '';
$entry_month     = isset($_POST['entry_month']) && is_numeric($_POST['entry_month']) ? intval($_POST['entry_month']) : date('n');
$entry_year      = isset($_POST['entry_year']) && is_numeric($_POST['entry_year'])   ? intval($_POST['entry_year'])  : date('Y');
$source_account  = $_POST['source_account'] ?? 'main';
$target_account  = $_POST['target_account'] ?? 'main';
$username        = $_SESSION['username'];

try {
    global $pdo;

    // Tabellen für Ursprungskonto und Zielkonto bestimmen
    $source_table = ($source_account === 'main') ? $username : $username . "_" . $source_account;
    $target_table = ($target_account === 'main') ? $username : $username . "_" . $target_account;

    // Prüfen, ob quell-/zielkonto identisch
    if ($source_account === $target_account) {
        echo json_encode(["success" => false, "message" => "Ursprungskonto und Zielkonto dürfen nicht identisch sein."]);
        exit;
    }

    // Prüfen, ob ein Override für diese Kombination existiert
    $checkOverride = $pdo->prepare("
        SELECT id 
        FROM `$source_table` 
        WHERE override = 1 
          AND override_id = ? 
          AND entry_month = ? 
          AND entry_year = ?
    ");
    $checkOverride->execute([$id, $entry_month, $entry_year]);
    $existingOverride = $checkOverride->fetch(PDO::FETCH_ASSOC);

    if ($existingOverride) {
        // Override im Ursprungskonto aktualisieren
        $updateOverride = $pdo->prepare("
            UPDATE `$source_table` 
            SET amount = ?, description = ?, entry_month = ?, entry_year = ?, rebooking_partner = ?
            WHERE id = ?
        ");
        $updateOverride->execute([
            $amount, 
            $description, 
            $entry_month, 
            $entry_year, 
            $target_account, 
            $existingOverride['id']
        ]);

        // Override im Zielkonto aktualisieren
        $updateTargetOverride = $pdo->prepare("
            UPDATE `$target_table` 
            SET amount = ?, description = ?, entry_month = ?, entry_year = ?, rebooking_partner = ?
            WHERE override = 1 
              AND override_id = ?
        ");
        $updateTargetOverride->execute([
            $amount, 
            $description, 
            $entry_month, 
            $entry_year, 
            $source_account, 
            $id
        ]);

    } else {
        // Neues Override im Ursprungskonto erstellen
        $createOverride = $pdo->prepare("
            INSERT INTO `$source_table` (
                type, amount, description, entry_month, entry_year, rebooking_id, rebooking_partner, override, override_id
            ) VALUES (
                'expense', ?, ?, ?, ?, ?, ?, 1, ?
            )
        ");
        $createOverride->execute([
            $amount, 
            $description, 
            $entry_month, 
            $entry_year, 
            $id, 
            $target_account, 
            $id
        ]);

        // Neues Override im Zielkonto erstellen
        $createTargetOverride = $pdo->prepare("
            INSERT INTO `$target_table` (
                type, amount, description, entry_month, entry_year, rebooking_id, rebooking_partner, override, override_id
            ) VALUES (
                'income', ?, ?, ?, ?, ?, ?, 1, ?
            )
        ");
        $createTargetOverride->execute([
            $amount, 
            $description, 
            $entry_month, 
            $entry_year, 
            $id, 
            $source_account, 
            $id
        ]);
    }

    // KEIN Redirect mehr, sondern JSON-Antwort:
    echo json_encode(["success" => true, "message" => "Einzel-Umbuchung erfolgreich aktualisiert."]);
    exit;
} catch (PDOException $e) {
    // Auf Fehler reagieren, Debug in Datei schreiben
    file_put_contents('debug_log.txt', "PDOException: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Fehler beim Aktualisieren der Umbuchung: " . $e->getMessage()]);
    exit;
}
