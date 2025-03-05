<?php
include('init.php');
include 'dbconnection.php';

// Eingabedaten abrufen
$source_account = $_POST['source_account']; // Ursprungskonto
$target_account = $_POST['target_account']; // Zielkonto
$amount = floatval($_POST['amount']);
$description = $_POST['description'];
$recurring = $_POST['recurring'];
$entry_month = intval($_POST['entry_month']);
$entry_year = intval($_POST['entry_year']);

// Optional: Wiederholungsende abrufen
$repeat_until_month = isset($_POST['repeat_until_month']) && $_POST['repeat_until_month'] !== ''
    ? intval($_POST['repeat_until_month'])
    : null;
$repeat_until_year = isset($_POST['repeat_until_year']) && $_POST['repeat_until_year'] !== ''
    ? intval($_POST['repeat_until_year'])
    : null;

$username = $_SESSION['username'];

if ($source_account === $target_account) {
    echo json_encode(["error" => "Ursprungskonto und Zielkonto dürfen nicht identisch sein."]);
    exit;
}

try {
    global $pdo;

    // Neue `rebooking_id` generieren
    $stmt = $pdo->query("SELECT IFNULL(MAX(rebooking_id), 0) + 1 AS new_id FROM `$username`");
    $rebooking_id = $stmt->fetch(PDO::FETCH_ASSOC)['new_id'];

    // Ursprungskonto und Zielkonto bestimmen
    $source_table = ($source_account === 'main') ? $username : $username . "_" . $source_account;
    $target_table = ($target_account === 'main') ? $username : $username . "_" . $target_account;

    // Neue Spalte start_date berechnen (Format: MM.YYYY)
    $start_date = str_pad($entry_month, 2, '0', STR_PAD_LEFT) . '.' . $entry_year;

    // Neue Spalte end_date berechnen, falls Wiederholungsende definiert ist,
    // ansonsten Standardwert für unendliche Wiederholungen
    $end_date = '00.0000';
    if (!is_null($repeat_until_month) && !is_null($repeat_until_year)) {
        $end_date = str_pad($repeat_until_month, 2, '0', STR_PAD_LEFT) . '.' . $repeat_until_year;
    }

    // Berechnung der recurring_in_month
    $recurring_in_month = null;
    if ($recurring === 'monthly') {
        $recurring_in_month = '0';
    } elseif ($recurring === 'annually') {
        $recurring_in_month = strval($entry_month);
    } elseif ($recurring === 'semiannually') {
        $recurring_months = [];
        for ($i = 0; $i < 2; $i++) {
            $next_month = (($entry_month + ($i * 6) - 1) % 12) + 1;
            $recurring_months[] = $next_month;
        }
        $recurring_in_month = implode(',', $recurring_months);
    } elseif ($recurring === 'quarterly') {
        $recurring_months = [];
        for ($i = 0; $i < 4; $i++) {
            $next_month = (($entry_month + ($i * 3) - 1) % 12) + 1;
            $recurring_months[] = $next_month;
        }
        $recurring_in_month = implode(',', $recurring_months);
    }

    // Eintrag im Ursprungskonto (Belastung)
    $stmt = $pdo->prepare("INSERT INTO `$source_table` 
        (type, amount, description, entry_month, entry_year, start_date, end_date, recurring, recurring_in_month, repeat_until_month, repeat_until_year, rebooking_id, rebooking_partner) 
        VALUES ('expense', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $amount, 
        $description, 
        $entry_month, 
        $entry_year, 
        $start_date, 
        $end_date, 
        $recurring, 
        $recurring_in_month, 
        $repeat_until_month, 
        $repeat_until_year, 
        $rebooking_id, 
        $target_account
    ]);

    // Eintrag im Zielkonto (Gutschrift)
    $stmt = $pdo->prepare("INSERT INTO `$target_table` 
        (type, amount, description, entry_month, entry_year, start_date, end_date, recurring, recurring_in_month, repeat_until_month, repeat_until_year, rebooking_id, rebooking_partner) 
        VALUES ('income', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $amount, 
        $description, 
        $entry_month, 
        $entry_year, 
        $start_date, 
        $end_date, 
        $recurring, 
        $recurring_in_month, 
        $repeat_until_month, 
        $repeat_until_year, 
        $rebooking_id, 
        $source_account
    ]);

    echo json_encode(["success" => "Umbuchung erfolgreich hinzugefügt."]);
} catch (PDOException $e) {
    echo json_encode(["error" => "Fehler bei der Umbuchung: " . $e->getMessage()]);
}
?>
