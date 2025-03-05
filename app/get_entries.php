<?php
include 'dbconnection.php';
include('init.php');

// Den Benutzernamen des eingeloggten Benutzers abrufen
$username = $_SESSION['username'];

if (isset($_GET['month']) && isset($_GET['year'])) {
    $month = intval($_GET['month']);
    $year = intval($_GET['year']);

    // Filterung in der Abfrage nach Monat, Jahr und Benutzername
    $sql = "SELECT * FROM Bewegungen WHERE entry_month = ? AND entry_year = ? AND username = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $month, $year, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $entryClass = ($row["type"] == "income") ? "income" : "expense";
            $amount = number_format((float)$row["amount"], 2, ',', '.');
            $recurringText = ($row["recurring"] === "no") ? "Nein" : ucfirst($row["recurring"]);

            echo "<div class='entry-box $entryClass'>";
            echo "<div class='entry-title'>" . htmlspecialchars($row["description"]) . "</div>";
            echo "<div class='entry-amount'><span class='currency'>" . $amount . " €</span></div>";
            echo "<div class='entry-regular'>Regelmäßig: " . htmlspecialchars($recurringText) . "</div>";
            echo "</div>";
        }
    } else {
        echo "<p>Keine Einträge für diesen Monat gefunden.</p>";
    }

    $stmt->close();
    $conn->close();
}
?>
