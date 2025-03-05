<?php
include('init.php');
include('dbconnection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['username'])) {
        die("Fehler: Kein Benutzer eingeloggt.");
    }

    // Eingeloggter Benutzer
    $username = $_SESSION['username'];

    // Dynamische Tabellenauswahl basierend auf dem Benutzernamen
    $table_name = $conn->real_escape_string($username); // Schutz vor SQL-Injection

    // SQL-Statement vorbereiten
    $stmt = $conn->prepare("DELETE FROM `$table_name`");

    if ($stmt->execute()) {
        echo "Erfolgreich gelöscht.";
    } else {
        echo "Fehler: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Ungültige Anfrage.";
}
?>
