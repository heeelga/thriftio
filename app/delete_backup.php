<?php
include('init.php');

$username = $_SESSION['username']; // Benutzername
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
    unlink($backupDir . $backupFile); // Backup löschen
    echo json_encode(["success" => true, "message" => "Backup erfolgreich gelöscht."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Fehler beim Löschen: " . $e->getMessage()]);
}
?>
