<?php
include('init.php');
include 'dbconnection.php';

$username   = $_SESSION['username'];
$table_name = $conn->real_escape_string($username);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1) Prüfen, ob eine ID übergeben wurde
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        die(json_encode(["success" => false, "message" => "Keine ID angegeben."]));
    }

    $id          = intval($_POST['id']);
    $type        = $_POST['type']         ?? '';
    $amount      = $_POST['amount']       ?? '';
    // Betrag umwandeln: Komma in Punkt ersetzen und in float konvertieren
    $amount = floatval(str_replace(',', '.', $amount));
    $description = $_POST['description']  ?? '';
    $recurring   = $_POST['recurring']    ?? 'no';
    $override    = isset($_POST['override']) ? intval($_POST['override']) : 0;
    $category    = isset($_POST['category']) ? trim($_POST['category'])   : null;

    // 2) Bestehende Daten inkl. booking_date aus DB laden
    $sql = "SELECT
                type,
                amount,
                description,
                recurring,
                recurring_in_month,
                repeat_until_month,
                repeat_until_year,
                end_date,
                entry_month,
                entry_year,
                booking_date
            FROM `$table_name`
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Fehler beim Vorbereiten des SELECT-Statements: " . $conn->error);
        die(json_encode(["success" => false, "message" => "Fehler beim Laden des Eintrags: " . $conn->error]));
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result       = $stmt->get_result();
    $existingData = $result->fetch_assoc();
    $stmt->close();

    if (!$existingData) {
        die(json_encode(["success" => false, "message" => "Eintrag nicht gefunden."]));
    }

    error_log("Bestehende Daten: " . json_encode($existingData));

    // 3) Neues Buchungsdatum aus POST übernehmen (falls vorhanden), sonst altes Datum beibehalten
    if (isset($_POST['booking_date']) && !empty($_POST['booking_date'])) {
        $booking_date = $_POST['booking_date']; // z. B. '2025-02-01'
    } else {
        $booking_date = $existingData['booking_date'];
    }

    if (empty($booking_date)) {
        $booking_date = date('Y-m-d');
    }

    // 4) Monat und Jahr automatisch aus booking_date ableiten
    $ts          = strtotime($booking_date);
    $entry_month = (int) date('n', $ts);
    $entry_year  = (int) date('Y', $ts);

    error_log("Neues Datum: booking_date=$booking_date => entry_month=$entry_month, entry_year=$entry_year");

    // 5) Bestehende Wiederholungs-Infos beibehalten
    $recurring_in_month  = $existingData['recurring_in_month'];
    $repeat_until_month  = $existingData['repeat_until_month'];
    $repeat_until_year   = $existingData['repeat_until_year'];
    $end_date            = $existingData['end_date'];

    // Neuberechnung bei Änderung von recurring:
    if ($recurring === 'monthly') {
        $recurring_in_month = '0';
    } elseif ($recurring === 'annually') {
        $recurring_in_month = strval($entry_month);
    } elseif ($recurring === 'semiannually') {
        $recurring_in_month = implode(',', [
            $entry_month,
            (($entry_month + 6 - 1) % 12) + 1
        ]);
    } elseif ($recurring === 'quarterly') {
        $recurring_in_month = implode(',', [
            $entry_month,
            (($entry_month + 3 - 1) % 12) + 1,
            (($entry_month + 6 - 1) % 12) + 1,
            (($entry_month + 9 - 1) % 12) + 1
        ]);
    }

    // Wiederholungsende prüfen und ggf. aktualisieren
    if (!empty($_POST['repeat_until_month']) && !empty($_POST['repeat_until_year'])) {
        $repeat_until_month = intval($_POST['repeat_until_month']);
        $repeat_until_year  = intval($_POST['repeat_until_year']);
        $end_date           = str_pad($repeat_until_month, 2, '0', STR_PAD_LEFT) . '.' . $repeat_until_year;
    }

    error_log("Aktualisierte Wiederholungsdaten: recurring_in_month=$recurring_in_month, repeat_until_month=$repeat_until_month, repeat_until_year=$repeat_until_year, end_date=$end_date");

    // 7) Override oder normales Update
    if ($override === 1) {
        // OVERRIDE: INSERT ... ON DUPLICATE KEY
        $sql = "INSERT INTO `$table_name`
                (
                    type,
                    amount,
                    description,
                    recurring,
                    recurring_in_month,
                    entry_month,
                    entry_year,
                    booking_date,
                    override,
                    override_id,
                    category,
                    end_date
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    type = VALUES(type),
                    amount = VALUES(amount),
                    description = VALUES(description),
                    recurring = VALUES(recurring),
                    recurring_in_month = VALUES(recurring_in_month),
                    booking_date = VALUES(booking_date),
                    category = VALUES(category),
                    end_date = VALUES(end_date)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Fehler beim Vorbereiten des INSERT-Statements: " . $conn->error);
            die(json_encode(["success" => false, "message" => "Fehler beim Vorbereiten des Statements: " . $conn->error]));
        }

        // WICHTIG: Hier wird der Parameter für booking_date als String (s) gebunden,
        // sodass das Datum im Format "YYYY-MM-DD" korrekt übergeben wird.
        $stmt->bind_param(
            "sdsssiisiss",
            $type,
            $amount,
            $description,
            $recurring,
            $recurring_in_month,
            $entry_month,
            $entry_year,
            $booking_date,
            $id,        // override_id
            $category,
            $end_date
        );
    } else {
        // NORMALES UPDATE
        $sql = "UPDATE `$table_name`
                SET
                    type = ?,
                    amount = ?,
                    description = ?,
                    recurring = ?,
                    recurring_in_month = ?,
                    entry_month = ?,
                    entry_year = ?,
                    booking_date = ?,
                    category = ?,
                    repeat_until_month = ?,
                    repeat_until_year = ?,
                    end_date = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Fehler beim Vorbereiten des UPDATE-Statements: " . $conn->error);
            die(json_encode(["success" => false, "message" => "Fehler beim Vorbereiten des Statements: " . $conn->error]));
        }
        $stmt->bind_param(
            "sdsssiissiisi",
            $type,
            $amount,
            $description,
            $recurring,
            $recurring_in_month,
            $entry_month,
            $entry_year,
            $booking_date,
            $category,
            $repeat_until_month,
            $repeat_until_year,
            $end_date,
            $id
        );
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Eintrag erfolgreich verarbeitet."]);
    } else {
        error_log("Fehler beim Ausführen des Statements: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Fehler beim Speichern: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
