<?php
session_start();
include 'dbconnection.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php?error=not_logged_in");
    exit;
}

// Funktion zur dynamischen Weiterleitung
function redirect_back($params = []) {
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    $query = http_build_query($params);
    $separator = strpos($referer, '?') === false ? '?' : '&';
    header("Location: $referer$separator$query");
    exit;
}

// Eingabedaten abrufen
$id = intval($_POST['id']);
$source_account = $_POST['source_account'];
$target_account = $_POST['target_account'];
$amount = floatval($_POST['amount']);
$description = $_POST['description'];
$recurring = $_POST['recurring'];
$entry_month = isset($_POST['entry_month']) && is_numeric($_POST['entry_month']) ? intval($_POST['entry_month']) : date('n');
$entry_year = isset($_POST['entry_year']) && is_numeric($_POST['entry_year']) ? intval($_POST['entry_year']) : date('Y');
$repeat_until_month = isset($_POST['repeat_until_month']) && $_POST['repeat_until_month'] !== '' ? intval($_POST['repeat_until_month']) : null;
$repeat_until_year = isset($_POST['repeat_until_year']) && $_POST['repeat_until_year'] !== '' ? intval($_POST['repeat_until_year']) : null;
$override = isset($_POST['override']) ? intval($_POST['override']) : 0;
$username = $_SESSION['username'];

if ($source_account === $target_account) {
    redirect_back(['error' => 'same_accounts']);
}

// `recurring_in_month` berechnen
if ($recurring === 'monthly') {
    $recurring_in_month = '0';
} elseif ($recurring === 'annually') {
    $recurring_in_month = strval($entry_month);
} elseif ($recurring === 'semiannually') {
    $recurring_in_month = implode(',', [$entry_month, (($entry_month + 6 - 1) % 12) + 1]);
} elseif ($recurring === 'quarterly') {
    $recurring_in_month = implode(',', [$entry_month, (($entry_month + 3 - 1) % 12) + 1, (($entry_month + 6 - 1) % 12) + 1, (($entry_month + 9 - 1) % 12) + 1]);
} else {
    $recurring_in_month = null;
}

// Startdatum berechnen
$start_date = str_pad($entry_month, 2, '0', STR_PAD_LEFT) . '.' . $entry_year;

// Enddatum berechnen
$end_date = '00.0000';
if (!is_null($repeat_until_month) && !is_null($repeat_until_year)) {
    $end_date = str_pad($repeat_until_month, 2, '0', STR_PAD_LEFT) . '.' . $repeat_until_year;
}

try {
    global $pdo;

    if ($override === 1) {
        // Override: Prüfen, ob bereits ein Override existiert
        $checkOverride = $pdo->prepare("SELECT id FROM `$username` WHERE override = 1 AND override_id = ? AND entry_month = ? AND entry_year = ?");
        $checkOverride->execute([$id, $entry_month, $entry_year]);
        $existingOverride = $checkOverride->fetch(PDO::FETCH_ASSOC);

        if ($existingOverride) {
            // Override aktualisieren
            $updateOverride = $pdo->prepare("UPDATE `$username` SET 
                amount = ?, 
                description = ?, 
                source_account = ?, 
                target_account = ?, 
                recurring = ?, 
                recurring_in_month = ?, 
                repeat_until_month = ?, 
                repeat_until_year = ?, 
                start_date = ?, 
                end_date = ? 
                WHERE id = ?");
            $updateOverride->execute([
                $amount, $description, $source_account, $target_account, $recurring, 
                $recurring_in_month, $repeat_until_month, $repeat_until_year, $start_date, $end_date, $existingOverride['id']
            ]);
        } else {
            // Neues Override erstellen
            $createOverride = $pdo->prepare("INSERT INTO `$username` (
                amount, description, source_account, target_account, recurring, recurring_in_month, repeat_until_month, repeat_until_year, override, override_id, entry_month, entry_year
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)");
            $createOverride->execute([
                $amount, $description, $source_account, $target_account, $recurring, 
                $recurring_in_month, $repeat_until_month, $repeat_until_year, $id, $entry_month, $entry_year
            ]);
        }
    } else {
        // Normale Bearbeitung (ohne Override)
        $stmt = $pdo->prepare("SELECT rebooking_id, rebooking_partner FROM `$username` WHERE id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            redirect_back(['error' => 'entry_not_found']);
        }

        $rebooking_id = $entry['rebooking_id'];
        $new_source_table = ($source_account === 'main') ? $username : $username . "_" . $source_account;
        $new_target_table = ($target_account === 'main') ? $username : $username . "_" . $target_account;

        // Ursprungskonto aktualisieren (expense)
        $stmt = $pdo->prepare("UPDATE `$new_source_table` SET 
            amount = ?, 
            description = ?, 
            rebooking_partner = ?, 
            recurring = ?, 
            recurring_in_month = ?, 
            entry_month = ?, 
            entry_year = ?, 
            repeat_until_month = ?, 
            repeat_until_year = ?, 
            start_date = ?, 
            end_date = ? 
            WHERE rebooking_id = ? AND type = 'expense'");
        $stmt->execute([
            $amount, $description, $target_account, $recurring, $recurring_in_month,
            $entry_month, $entry_year, $repeat_until_month, $repeat_until_year,
            $start_date, $end_date, $rebooking_id
        ]);

        // Zielkonto aktualisieren (income)
        $stmt = $pdo->prepare("UPDATE `$new_target_table` SET 
            amount = ?, 
            description = ?, 
            rebooking_partner = ?, 
            recurring = ?, 
            recurring_in_month = ?, 
            entry_month = ?, 
            entry_year = ?, 
            repeat_until_month = ?, 
            repeat_until_year = ?, 
            start_date = ?, 
            end_date = ? 
            WHERE rebooking_id = ? AND type = 'income'");
        $stmt->execute([
            $amount, $description, $source_account, $recurring, $recurring_in_month,
            $entry_month, $entry_year, $repeat_until_month, $repeat_until_year,
            $start_date, $end_date, $rebooking_id
        ]);
    }

    redirect_back(['success' => 'update_successful']);
} catch (PDOException $e) {
    redirect_back(['error' => 'update_failed']);
}
?>
