<?php
include('init.php');
require_once 'dbconnection.php'; // Verbindung einbinden

$username = $_SESSION['username']; // Benutzername = Haupttabellenname

// Backup-Verzeichnis außerhalb des Webroots
$backupDir = '/var/backups/finance/'; 

// JSON-Daten aus der Anfrage lesen
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!isset($data['backup_file'])) {
    die(json_encode(["success" => false, "message" => "Kein Backup-File angegeben."]));
}

$backupFile = $data['backup_file'];

if (strpos($backupFile, "backup_{$username}_") !== 0 || !file_exists($backupDir . $backupFile)) {
    die(json_encode(["success" => false, "message" => "Ungültiges Backup."]));
}

try {
    // Tabellen, die mit dem Benutzernamen oder `savings_interest_rates` beginnen, abrufen
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

    // Alle relevanten Tabellen leeren
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `$table`");
    }

    // Backup einspielen
    $command = "mysql -h$db_host -u$db_user -p'$db_pass' $db_name < " . escapeshellarg($backupDir . $backupFile);
    exec($command, $output, $result);

    if ($result === 0) {
        echo json_encode(["success" => true, "message" => "Backup erfolgreich wiederhergestellt."]);
    } else {
        echo json_encode(["success" => false, "message" => "Fehler beim Wiederherstellen des Backups."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Datenbankfehler: " . $e->getMessage()]);
}
?>
