<?php
include 'dbconnection.php';
include('init.php');

$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username);

if (isset($_GET['id']) && !empty($_GET['id']) && isset($_GET['edit_mode'])) {
    $id = intval($_GET['id']);
    $edit_mode = $_GET['edit_mode']; // 'series' oder 'single'

    try {
        $sql = "SELECT 
                    id,
                    type,
                    amount,
                    description,
                    recurring,
                    recurring_in_month,
                    entry_month,
                    entry_year,
                    start_date,
                    repeat_until_month,
                    repeat_until_year,
                    end_date,
                    category,
                    override,
                    override_id,
                    booking_date
                FROM `$table_name`
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Fehler beim Vorbereiten des Statements: " . $conn->error);
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            error_log("Geladene Daten: " . json_encode($row));

            if ($edit_mode === 'series') {
                $response = [
                    "id"                  => $row["id"],
                    "type"                => $row["type"],
                    "amount"              => $row["amount"],
                    "description"         => $row["description"],
                    "recurring"           => $row["recurring"],
                    "recurring_in_month"  => $row["recurring_in_month"],
                    "entry_month"         => $row["entry_month"],
                    "entry_year"          => $row["entry_year"],
                    "start_date"          => $row["start_date"],
                    "repeat_until_month"  => $row["repeat_until_month"],
                    "repeat_until_year"   => $row["repeat_until_year"],
                    "end_date"            => $row["end_date"],
                    "category"            => $row["category"],
                    "override"            => $row["override"],
                    "override_id"         => $row["override_id"],
                    "booking_date"        => $row["booking_date"]
                ];

                error_log("Rückgabe für Overlay (series): " . json_encode($response));
                http_response_code(200);
                echo json_encode($response);
                exit;
            }

            if ($edit_mode === 'single') {
                // Verwende die per GET übergebenen aktuellen Monats- und Jahreswerte,
                // falls diese vorhanden sind, ansonsten das Systemdatum.
                $currentMonth = isset($_GET['current_month']) ? intval($_GET['current_month']) : date("n");
                $currentYear  = isset($_GET['current_year'])  ? intval($_GET['current_year'])  : date("Y");

                // Ursprüngliches Buchungsdatum, z. B. "2025-01-27"
                $originalBookingDate = $row["booking_date"];
                // Extrahiere den Tag (z. B. "27")
                $originalDay = date("d", strtotime($originalBookingDate));
                // Erzeuge ein neues Datum, das den aktuellen Monat und das aktuelle Jahr mit dem ursprünglichen Tag kombiniert.
                $newBookingDate = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $originalDay);

                $response = [
                    "id"                  => $row["id"],
                    "type"                => $row["type"],
                    "amount"              => $row["amount"],
                    "description"         => $row["description"],
                    "recurring"           => $row["recurring"],
                    "recurring_in_month"  => $row["recurring_in_month"],
                    // Überschreibe entry_month/entry_year mit den aktuellen Werten
                    "entry_month"         => $currentMonth,
                    "entry_year"          => $currentYear,
                    "start_date"          => $row["start_date"],
                    "repeat_until_month"  => $row["repeat_until_month"],
                    "repeat_until_year"   => $row["repeat_until_year"],
                    "end_date"            => $row["end_date"],
                    "category"            => $row["category"],
                    "override"            => $row["override"],
                    "override_id"         => $row["override_id"],
                    // Gib das neu zusammengesetzte Buchungsdatum zurück
                    "booking_date"        => $newBookingDate
                ];

                error_log("Rückgabe für Overlay (single): " . json_encode($response));
                http_response_code(200);
                echo json_encode($response);
                exit;
            }

            http_response_code(400);
            echo json_encode(["error" => "Ungültiger Bearbeitungsmodus"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Eintrag nicht gefunden"]);
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Keine oder ungültige ID angegeben oder Bearbeitungsmodus fehlt"]);
}
?>
