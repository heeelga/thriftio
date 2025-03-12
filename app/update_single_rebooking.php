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

    // Prüfen, ob Quell-/Zielkonto identisch
    if ($source_account === $target_account) {
        echo json_encode(["success" => false, "message" => "Ursprungskonto und Zielkonto dürfen nicht identisch sein."]);
        exit;
    }

    // ======================
    // 1) Override im Source-Konto checken
    // ======================
    $checkOverrideSource = $pdo->prepare("
        SELECT id
        FROM `$source_table`
        WHERE override = 1
          AND override_id = ?
          AND entry_month = ?
          AND entry_year = ?
    ");
    $checkOverrideSource->execute([$id, $entry_month, $entry_year]);
    $existingOverrideSource = $checkOverrideSource->fetch(PDO::FETCH_ASSOC);

    if ($existingOverrideSource) {
        // Update
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
            $existingOverrideSource['id']
        ]);
    } else {
        // Neu anlegen
        $createOverride = $pdo->prepare("
            INSERT INTO `$source_table` (
                type, amount, description, entry_month, entry_year, 
                rebooking_id, rebooking_partner, override, override_id
            ) VALUES (
                'expense', ?, ?, ?, ?, ?, ?, 1, ?
            )
        ");
        $createOverride->execute([
            $amount,
            $description,
            $entry_month,
            $entry_year,
            $id, // rebooking_id
            $target_account, 
            $id  // override_id
        ]);
    }

    // ======================
    // 2) Override im Target-Konto checken
    // ======================
    $checkOverrideTarget = $pdo->prepare("
        SELECT id
        FROM `$target_table`
        WHERE override = 1
          AND override_id = ?
          AND entry_month = ?
          AND entry_year = ?
    ");
    $checkOverrideTarget->execute([$id, $entry_month, $entry_year]);
    $existingOverrideTarget = $checkOverrideTarget->fetch(PDO::FETCH_ASSOC);

    if ($existingOverrideTarget) {
        // Update
        $updateTargetOverride = $pdo->prepare("
            UPDATE `$target_table`
            SET amount = ?, description = ?, entry_month = ?, entry_year = ?, rebooking_partner = ?
            WHERE id = ?
        ");
        $updateTargetOverride->execute([
            $amount,
            $description,
            $entry_month,
            $entry_year,
            $source_account,
            $existingOverrideTarget['id']
        ]);
    } else {
        // Neu anlegen
        $createTargetOverride = $pdo->prepare("
            INSERT INTO `$target_table` (
                type, amount, description, entry_month, entry_year, 
                rebooking_id, rebooking_partner, override, override_id
            ) VALUES (
                'income', ?, ?, ?, ?, ?, ?, 1, ?
            )
        ");
        $createTargetOverride->execute([
            $amount,
            $description,
            $entry_month,
            $entry_year,
            $id, // rebooking_id
            $source_account,
            $id  // override_id
        ]);
    }

    echo json_encode(["success" => true, "message" => "Einzel-Umbuchung erfolgreich aktualisiert."]);
    exit;

} catch (PDOException $e) {
    file_put_contents('debug_log.txt', "PDOException: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Fehler beim Aktualisieren der Umbuchung: " . $e->getMessage()]);
    exit;
}
