<?php
include('init.php');
include 'dbconnection.php';

// Eingeloggter Benutzer
$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username); // Schutz vor SQL-Injection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("UPDATE `$table_name` SET is_hidden = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "Eintrag erfolgreich ausgeblendet.";
    } else {
        http_response_code(500);
        echo "Fehler beim Ausblenden des Eintrags.";
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo "Ungültige Anfragemethode.";
}
