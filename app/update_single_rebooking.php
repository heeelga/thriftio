<?php
session_start();
include 'dbconnection.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php?error=not_logged_in");
    exit;
}

// Eingabedaten abrufen
$id = intval($_POST['id']);
$amount = floatval($_POST['amount']);
$description = $_POST['description'];
$entry_month = isset($_POST['entry_month']) && is_numeric($_POST['entry_month']) ? intval($_POST['entry_month']) : date('n');
$entry_year = isset($_POST['entry_year']) && is_numeric($_POST['entry_year']) ? intval($_POST['entry_year']) : date('Y');
$source_account = $_POST['source_account'];
$target_account = $_POST['target_account'];
$username = $_SESSION['username'];

try {
    global $pdo;

    // Tabelle für Ursprungskonto und Zielkonto bestimmen
    $source_table = ($source_account === 'main') ? $username : $username . "_" . $source_account;
    $target_table = ($target_account === 'main') ? $username : $username . "_" . $target_account;

    // Prüfen, ob ein Override für diese Kombination existiert
    $checkOverride = $pdo->prepare("SELECT id FROM `$source_table` WHERE override = 1 AND override_id = ? AND entry_month = ? AND entry_year = ?");
    $checkOverride->execute([$id, $entry_month, $entry_year]);
    $existingOverride = $checkOverride->fetch(PDO::FETCH_ASSOC);

    if ($existingOverride) {
        // Override im Ursprungskonto aktualisieren
        $updateOverride = $pdo->prepare("UPDATE `$source_table` SET 
            amount = ?, 
            description = ?, 
            entry_month = ?, 
            entry_year = ?, 
            rebooking_partner = ?
            WHERE id = ?");
        $updateOverride->execute([
            $amount, $description, $entry_month, $entry_year, $target_account, $existingOverride['id']
        ]);

        // Override im Zielkonto aktualisieren
        $updateTargetOverride = $pdo->prepare("UPDATE `$target_table` SET 
            amount = ?, 
            description = ?, 
            entry_month = ?, 
            entry_year = ?, 
            rebooking_partner = ?
            WHERE override = 1 AND override_id = ?");
        $updateTargetOverride->execute([
            $amount, $description, $entry_month, $entry_year, $source_account, $id
        ]);
    } else {
        // Neues Override im Ursprungskonto erstellen
        $createOverride = $pdo->prepare("INSERT INTO `$source_table` (
            type, amount, description, entry_month, entry_year, rebooking_id, rebooking_partner, override, override_id
        ) VALUES ('expense', ?, ?, ?, ?, ?, ?, 1, ?)");
        $createOverride->execute([
            $amount, $description, $entry_month, $entry_year, $id, $target_account, $id
        ]);

        // Neues Override im Zielkonto erstellen
        $createTargetOverride = $pdo->prepare("INSERT INTO `$target_table` (
            type, amount, description, entry_month, entry_year, rebooking_id, rebooking_partner, override, override_id
        ) VALUES ('income', ?, ?, ?, ?, ?, ?, 1, ?)");
        $createTargetOverride->execute([
            $amount, $description, $entry_month, $entry_year, $id, $source_account, $id
        ]);
    }

    header("Location: index.php?success=update_successful");
    exit;
} catch (PDOException $e) {
    file_put_contents('debug_log.txt', "PDOException: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    header("Location: index.php?error=update_failed");
    exit;
}
?>
