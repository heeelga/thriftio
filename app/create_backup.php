<?php
include('init.php');
require_once 'dbconnection.php'; // Verbindung einbinden

$username = $_SESSION['username']; // Benutzername = Haupttabellenname

// Verzeichnis außerhalb des Webroots definieren
$backupDir = '/var/backups/finance/'; // Sicherstellen, dass dieses Verzeichnis existiert und beschreibbar ist

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true); // Verzeichnis erstellen, falls es nicht existiert
}

$filename = $backupDir . "backup_{$username}_" . date('Y-m-d_H-i-s') . ".sql";

try {
    // Tabellen, die gesichert werden sollen (Haupttabellen und passende Einträge in `savings_interest_rates`)
    $query = $pdo->prepare("
        SHOW TABLES 
        WHERE Tables_in_{$db_name} LIKE :user_pattern 
        OR Tables_in_{$db_name} LIKE :savings_pattern
    ");
    $query->execute([
        ':user_pattern' => $username . '%',
        ':savings_pattern' => 'savings_interest_rates_' . $username . '%'
    ]);
    $tables = $query->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        die(json_encode(["success" => false, "message" => "Keine Tabellen gefunden für Benutzer."]));
    }

    // Tabellen in ein Backup-Dump exportieren
    $tableList = implode(' ', array_map('escapeshellarg', $tables));
    $command = "mysqldump -h$db_host -u$db_user -p'$db_pass' $db_name $tableList > " . escapeshellarg($filename);
    exec($command, $output, $result);

    if ($result === 0) {
        echo json_encode(["success" => true, "message" => "Backup erstellt!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Fehler beim Erstellen des Backups."]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Systemfehler: " . $e->getMessage()]);
}
?>
