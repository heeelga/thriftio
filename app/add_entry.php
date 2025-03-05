<?php
include('init.php');
include 'dbconnection.php';

// Eingabedaten abrufen
$type         = $_POST['type'];
$amount       = $_POST['amount'];
// Konvertiere den Betrag: Tausche Komma gegen Punkt und wandle in float um
$amount = floatval(str_replace(',', '.', $amount));
$description  = $_POST['description'];
$recurring    = $_POST['recurring'];
$category     = isset($_POST['category']) ? trim($_POST['category']) : null;
$username     = $_SESSION['username'];

// --------------------------------------------------------------------------
// 1) Neuen Wert für das Buchungsdatum holen und daraus Monat und Jahr ableiten
// --------------------------------------------------------------------------
$booking_date = isset($_POST['booking_date']) ? $_POST['booking_date'] : null; // z. B. '2025-01-31'

// Aus dem Format YYYY-MM-DD die einzelnen Werte extrahieren:
if ($booking_date) {
    $time_stamp  = strtotime($booking_date);
    $entry_month = (int)date('n', $time_stamp); // Monat als Ganzzahl 1..12
    $entry_year  = (int)date('Y', $time_stamp); // Jahr als Ganzzahl, z. B. 2025
} else {
    // Fallback, falls kein Buchungsdatum übergeben wurde
    $entry_month = 0;
    $entry_year  = 0;
}
// --------------------------------------------------------------------------

// Wiederholungsende abrufen
$repeat_until_month = isset($_POST['repeat_until_month']) && $_POST['repeat_until_month'] !== ''
    ? intval($_POST['repeat_until_month'])
    : null;
$repeat_until_year = isset($_POST['repeat_until_year']) && $_POST['repeat_until_year'] !== ''
    ? intval($_POST['repeat_until_year'])
    : null;

// recurring_in_month berechnen, damit weiterhin die vorhandene Logik genutzt werden kann
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

// start_date befüllen für eventuelle Auswertungen
$start_date = str_pad($entry_month, 2, '0', STR_PAD_LEFT) . '.' . $entry_year;

// end_date, falls Wiederholungsende definiert
$end_date = '00.0000'; // Standardwert für unendliche Wiederholungen
if (!is_null($repeat_until_month) && !is_null($repeat_until_year)) {
    $end_date = str_pad($repeat_until_month, 2, '0', STR_PAD_LEFT) . '.' . $repeat_until_year;
}

// Daten in die benutzerspezifische Tabelle einfügen
$table_name = $conn->real_escape_string($username);

$sql = "INSERT INTO `$table_name`
        (type, amount, description, recurring, recurring_in_month, entry_month, entry_year,
         start_date, repeat_until_month, repeat_until_year, end_date, category, booking_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Fehler beim Vorbereiten des Statements: " . $conn->error
    ]);
    exit;
}

// Anpassen der Parameter (13 Werte in exakt derselben Reihenfolge wie im INSERT)
$stmt->bind_param(
    "sdssssississs",
    $type, 
    $amount,
    $description,
    $recurring,
    $recurring_in_month,
    $entry_month,
    $entry_year,
    $start_date,
    $repeat_until_month,
    $repeat_until_year,
    $end_date,
    $category,
    $booking_date
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Eintrag erfolgreich hinzugefügt."]);
} else {
    echo json_encode(["success" => false, "message" => "Fehler beim Hinzufügen des Eintrags: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
