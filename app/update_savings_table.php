<?php
include('init.php');
include 'dbconnection.php';

$username = $_SESSION['username'];

try {
    global $pdo;

    // Tabellen abrufen, die mit `username_` beginnen
    $stmt = $pdo->query("SHOW TABLES LIKE '{$username}_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Prüfen, ob die Spalten bereits existieren
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'override'");
        $stmt->execute();
        $overrideExists = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'override_id'");
        $stmt->execute();
        $overrideIdExists = $stmt->fetch(PDO::FETCH_ASSOC);

        // Spalten hinzufügen, falls sie nicht existieren
        if (!$overrideExists) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `override` TINYINT(1) DEFAULT 0");
        }

        if (!$overrideIdExists) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `override_id` INT DEFAULT NULL");
        }

        echo "Tabelle `$table` wurde aktualisiert.<br>";
    }

    echo "Alle Tabellen wurden erfolgreich überprüft und ggf. aktualisiert.";
} catch (PDOException $e) {
    echo "Fehler bei der Tabellenanpassung: " . $e->getMessage();
}
?>
