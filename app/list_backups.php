<?php
include('init.php');
require_once 'dbconnection.php'; // Verbindung einbinden

$username = $_SESSION['username'];

// Backup-Verzeichnis außerhalb des Webroots
$backupDir = '/var/backups/finance/';

if (!is_dir($backupDir)) {
    die(json_encode(["success" => false, "message" => "Backup-Verzeichnis nicht gefunden."]));
}

// Alle Dateien im Backup-Verzeichnis filtern, die mit "backup_<username>_" beginnen
$files = array_filter(scandir($backupDir), function($file) use ($username, $backupDir) {
    return strpos($file, "backup_{$username}_") === 0 && is_file($backupDir . $file);
});

$backups = array();

foreach ($files as $file) {
    // Erwartetes Format: backup_<username>_<YYYY-MM-DD>_<HH-MM-SS>.sql
    $parts = explode('_', $file);
    
    if (count($parts) >= 4 && isset($parts[2]) && isset($parts[3])) {
        // $parts[2] enthält das Datum im Format YYYY-MM-DD
        // $parts[3] enthält die Uhrzeit plus Endung, z.B. HH-MM-SS.sql
        $datePart = $parts[2];
        // Entferne die Endung .sql aus dem Zeitteil
        $timePart = preg_replace('/\.sql$/', '', $parts[3]);
        
        // Formatierung des Datums: von YYYY-MM-DD zu DD.MM.YYYY
        $formattedDate = date("d.m.Y", strtotime($datePart));
        // Formatierung der Zeit: Bindestriche zu Doppelpunkten, sodass HH:MM:SS entsteht
        $formattedTime = str_replace('-', ':', $timePart);
        
        $display = $formattedDate . " - " . $formattedTime;
    } else {
        // Falls das erwartete Format nicht passt, wird der Original-Dateiname als Anzeige genutzt
        $display = $file;
    }
    
    // Für jede Backup-Datei wird ein Objekt mit "file" und "display" erstellt
    $backups[] = [
       'file' => $file,
       'display' => $display
    ];
}

echo json_encode(array_values($backups));
?>
