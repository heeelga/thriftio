<?php
include('init.php');
include 'dbconnection.php';

$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);

    // Berechnung des Endbetrags für den aktuellen Monat
    $sql = "
        SELECT SUM(CASE 
            WHEN type = 'income' THEN amount 
            ELSE -amount 
        END) AS total 
        FROM `$table_name`
        WHERE 
            (
                -- Einmalige Einträge: Nur für den spezifischen Monat und Jahr
                (recurring = 'no' AND entry_month = ? AND entry_year = ?)
                
                -- Wiederkehrende Einträge: Berücksichtigung des Start- und Enddatums
                OR (
                    recurring != 'no'
                    AND (
                        recurring_in_month = '0' -- Gilt für jeden Monat
                        OR FIND_IN_SET(?, recurring_in_month) > 0 -- Gilt für den spezifischen Monat
                    )
                    AND (
                        -- Startdatum muss erreicht sein
                        (entry_year < ? OR (entry_year = ? AND entry_month <= ?))
                        
                        -- Enddatum prüfen basierend auf `end_date`
                        AND (
                            end_date = '00.0000' -- Kein Enddatum gesetzt
                            OR (
                                CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED) > ? -- Endjahr noch nicht erreicht
                                OR (
                                    CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED) = ? 
                                    AND CAST(SUBSTRING_INDEX(end_date, '.', 1) AS UNSIGNED) >= ? -- Endmonat überschreiten
                                )
                            )
                        )
                    )
                )
            )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iiiiiiiii", 
        $month, $year, 
        $month, $year, $year, $month, 
        $year, $year, $month
    );
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();

    if ($balance === null) {
        $balance = 0; // Standardwert, falls keine Einträge vorhanden sind
    }

    // Tabelle Kontostand_{username} aktualisieren oder erstellen
    $balance_table = "Kontostand_" . $username;

    // Tabelle erstellen, falls sie nicht existiert
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `$balance_table` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `month` INT NOT NULL,
            `year` INT NOT NULL,
            `balance` DECIMAL(10, 2) NOT NULL,
            UNIQUE KEY `month_year` (`month`, `year`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($createTableSQL);

    // Kontostand aktualisieren oder einfügen
    $insertOrUpdateSQL = "
        INSERT INTO `$balance_table` (`month`, `year`, `balance`) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `balance` = VALUES(`balance`)
    ";
    $stmt = $conn->prepare($insertOrUpdateSQL);
    $stmt->bind_param("iid", $month, $year, $balance);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Kontostand aktualisiert.', 'balance' => $balance]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren des Kontostands.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage.']);
}
$conn->close();
?>
