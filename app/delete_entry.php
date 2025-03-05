<?php
include('init.php');
include 'dbconnection.php';

// Prüfen, ob die notwendigen Parameter vorhanden sind
if (!isset($_POST['id']) || !isset($_POST['table_name'])) {
    header("Location: index.php?error=missing_parameters");
    exit;
}

// Eingabedaten abrufen
$entry_id = intval($_POST['id']);
$table_name = $_POST['table_name'];
$username = $_SESSION['username'];

try {
    global $pdo;

    // Abrufen von rebooking_id und rebooking_partner des zu löschenden Eintrags
    $stmt = $pdo->prepare("SELECT rebooking_id, rebooking_partner FROM `$table_name` WHERE id = ?");
    $stmt->execute([$entry_id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        header("Location: index.php?error=entry_not_found");
        exit;
    }

    $rebooking_id = intval($entry['rebooking_id']);
    $rebooking_partner = $entry['rebooking_partner'];

    // Eintrag im aktuellen Konto löschen
    $stmt = $pdo->prepare("DELETE FROM `$table_name` WHERE id = ?");
    $stmt->execute([$entry_id]);

    // Falls der Eintrag eine Umbuchung ist, auch den verknüpften Eintrag löschen
    if ($rebooking_id > 0 && $rebooking_partner) {
        // Tabelle des Partnerkontos bestimmen
        $partner_table = ($rebooking_partner === 'main') ? $username : $username . "_" . $rebooking_partner;

        // Verknüpften Eintrag im Partnerkonto löschen
        $stmt = $pdo->prepare("DELETE FROM `$partner_table` WHERE rebooking_id = ?");
        $stmt->execute([$rebooking_id]);
    }

    // Erfolgreiche Weiterleitung
    header("Location: index.php?success=entry_deleted");
    exit;

} catch (PDOException $e) {
    // Fehlerprotokollierung (optional)
    error_log("Fehler beim Löschen des Eintrags: " . $e->getMessage());

    // Weiterleitung mit Fehlercode
    header("Location: index.php?error=delete_failed");
    exit;
}
?>
