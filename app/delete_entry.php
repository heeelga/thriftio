<?php
include('init.php');
include('dbconnection.php');

// Prüfen, ob die notwendigen Parameter vorhanden sind
if (!isset($_POST['id']) || !isset($_POST['table_name'])) {
    header("Location: index.php?error=missing_parameters");
    exit;
}

$entry_id   = intval($_POST['id']);
$table_name = $_POST['table_name'];
$username   = $_SESSION['username'] ?? '';

// Sicherheitscheck: Ist jemand eingeloggt?
if (!$username) {
    header("Location: index.php?error=not_logged_in");
    exit;
}

try {
    global $pdo;

    // 1) Den zu löschenden Eintrag abrufen
    $stmt = $pdo->prepare("
        SELECT 
            id,
            rebooking_id, 
            rebooking_partner, 
            override, 
            override_id,
            entry_month,
            entry_year
        FROM `$table_name` 
        WHERE id = ?
    ");
    $stmt->execute([$entry_id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        header("Location: index.php?error=entry_not_found");
        exit;
    }

    $rebooking_id      = intval($entry['rebooking_id']);
    $rebooking_partner = $entry['rebooking_partner'];
    $isOverride        = (intval($entry['override']) === 1);
    $overrideId        = intval($entry['override_id']);
    $entryMonth        = intval($entry['entry_month']);
    $entryYear         = intval($entry['entry_year']);

    // 2) Eintrag im aktuellen Konto löschen
    $stmtDelete = $pdo->prepare("DELETE FROM `$table_name` WHERE id = ?");
    $stmtDelete->execute([$entry_id]);

    // 3) Falls der Eintrag eine Umbuchung ist, auch den verknüpften Eintrag im Partnerkonto löschen
    if ($rebooking_id > 0 && $rebooking_partner) {
        $partner_table = ($rebooking_partner === 'main') 
            ? $username 
            : $username . "_" . $rebooking_partner;

        if ($isOverride) {
            // ==============
            // Es ist ein Override-Eintrag
            // => Nur die eine Override-Zeile im Partnerkonto löschen
            // ==============
            $stmtPartnerDel = $pdo->prepare("
                DELETE FROM `$partner_table` 
                WHERE override = 1
                  AND override_id = :overrideId
                  AND entry_month = :entryMonth
                  AND entry_year  = :entryYear
                LIMIT 1
            ");
            $stmtPartnerDel->execute([
                ':overrideId'  => $overrideId,
                ':entryMonth'  => $entryMonth,
                ':entryYear'   => $entryYear
            ]);
        } else {
            // ==============
            // Es ist der Original-Eintrag (override=0)
            // => komplette Umbuchung löschen
            //   (ggf. löscht man damit *alle* Overrides zu dieser Buchung)
            // ==============
            $stmtPartnerDel = $pdo->prepare("
                DELETE FROM `$partner_table`
                WHERE rebooking_id = :rebookingId
            ");
            $stmtPartnerDel->execute([':rebookingId' => $rebooking_id]);
        }
    }

    // Weiterleitung / Erfolg
    header("Location: index.php?success=entry_deleted");
    exit;

} catch (PDOException $e) {
    error_log("Fehler beim Löschen des Eintrags: " . $e->getMessage());
    header("Location: index.php?error=delete_failed");
    exit;
}
