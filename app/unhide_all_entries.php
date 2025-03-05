<?php
include('init.php');
include 'dbconnection.php';

// Eingeloggter Benutzer
$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username); // Schutz vor SQL-Injection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);

    // Alle Einträge wieder einblenden
    $stmt = $conn->prepare("UPDATE `$table_name` SET is_hidden = 0 WHERE entry_month = ? AND entry_year = ?");
    $stmt->bind_param("ii", $month, $year);

    if ($stmt->execute()) {
        // Versteckte Serienelemente mit hidden_override=1 und override=1 auf visible setzen
        $updateStmt = $conn->prepare("UPDATE `$table_name` SET hidden_override = 0 WHERE hidden_override = 1 AND override = 1 AND entry_month = ? AND entry_year = ?");
        $updateStmt->bind_param("ii", $month, $year);

        if ($updateStmt->execute()) {
            // Versteckte Serienelemente mit hidden_override=1 und override=0 löschen
            $deleteStmt = $conn->prepare("DELETE FROM `$table_name` WHERE hidden_override = 1 AND override = 0 AND entry_month = ? AND entry_year = ?");
            $deleteStmt->bind_param("ii", $month, $year);

            if ($deleteStmt->execute()) {
                echo "Alle Einträge des Monats wurden erfolgreich wieder eingeblendet, versteckte Serienelemente wurden aktualisiert und irrelevante ausgeblendete Einträge wurden entfernt.";
            } else {
                http_response_code(500);
                echo "Fehler beim Entfernen der irrelevanten ausgeblendeten Einträge.";
            }

            $deleteStmt->close();
        } else {
            http_response_code(500);
            echo "Fehler beim Aktualisieren der versteckten Serienelemente.";
        }

        $updateStmt->close();
    } else {
        http_response_code(500);
        echo "Fehler beim Wieder-Einblenden der Einträge.";
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo "Ungültige Anfragemethode.";
}
