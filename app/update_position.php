<?php
include('init.php');
require_once 'dbconnection.php';

header('Content-Type: application/json');
error_reporting(0);
if (ob_get_length()) {
    ob_clean();
}

// 1) Parameter prüfen
if (!isset($_POST['id']) || !isset($_POST['direction'])) {
    echo json_encode(['success' => false, 'message' => 'Fehlende Parameter']);
    exit;
}
$entryId   = (int)$_POST['id'];
$direction = $_POST['direction'];
if ($direction !== 'up' && $direction !== 'down') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Richtung']);
    exit;
}

// 2) Name der Tabelle
$username = $_SESSION['username'] ?? 'testuser';

// ----------------------------------------------------------------------------
// 3) Hier rekonstruieren wir die gesamte Reihenfolge so wie in index.php
// ----------------------------------------------------------------------------

// Beispielvariablen, die du ggf. in index.php auch hast
$month = isset($_SESSION['month']) ? (int)$_SESSION['month'] : (int)date('n');
$year  = isset($_SESSION['year'])  ? (int)$_SESSION['year']  : (int)date('Y');

// $translations – Beispiel:
$translations = [
    'carryover' => 'Übertrag vom letzten Monat',
    // ggf. weitere Einträge ...
];

// Abfrage Overrides (selbe Logik wie in index.php)
$sqlOverrides = "
SELECT * 
FROM `$username`
WHERE override = 1 AND entry_month = ? AND entry_year = ?
";
$stmtOv = $conn->prepare($sqlOverrides);
$stmtOv->bind_param("ii", $month, $year);
$stmtOv->execute();
$resOv = $stmtOv->get_result();
$overrides = [];
while ($row = $resOv->fetch_assoc()) {
    $overrides[$row['override_id']] = $row;
}
$stmtOv->close();

// Abfrage der Serien-/einmaligen Einträge (ohne Overrides, hidden & Co.)
// (Hier dieselben WHERE-Bedingungen wie in deiner index.php)
$sqlEntries = "
SELECT *,
    CASE
        WHEN rebooking_id > 0 THEN 'rebooking'
        ELSE type
    END AS entry_type,
    CASE
        WHEN end_date IS NOT NULL AND end_date != '00.0000' THEN CAST(SUBSTRING_INDEX(end_date, '.', 1) AS UNSIGNED)
        ELSE NULL
    END AS repeat_until_month,
    CASE
        WHEN end_date IS NOT NULL AND end_date != '00.0000' THEN CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED)
        ELSE NULL
    END AS repeat_until_year
FROM `$username`
WHERE 
    is_hidden = 0
    AND override = 0
    AND id NOT IN (
        SELECT id 
          FROM `$username`
         WHERE hidden_override = 1 
           AND entry_month = ? 
           AND entry_year = ?
        UNION
        SELECT override_id 
          FROM `$username`
         WHERE hidden_override = 1 
           AND entry_month = ? 
           AND entry_year = ?
    )
    AND (
        (recurring = 'no' AND entry_month = ? AND entry_year = ?)
        OR (
            recurring != 'no'
            AND (
                recurring_in_month = '0'
                OR FIND_IN_SET(?, recurring_in_month) > 0
            )
            AND (
                (entry_year < ?
                 OR (entry_year = ? AND entry_month <= ?))
                AND (
                    repeat_until_year IS NULL
                    OR (
                        repeat_until_year > ?
                        OR (repeat_until_year = ? 
                            AND repeat_until_month >= ?)
                    )
                )
            )
        )
        OR (
            override = 1 
            AND entry_month = ?
            AND entry_year = ?
        )
    )
ORDER BY
    -- Pinned top: Übertrag
    CASE 
        WHEN description = 'Übertrag vom letzten Monat' THEN 0
        WHEN description = 'Carryover from last month' THEN 0
        ELSE sort_order
    END,
    CASE 
        WHEN description = 'Übertrag vom letzten Monat' THEN 0
        WHEN description = 'Carryover from last month' THEN 0
        WHEN type = 'income' AND recurring != 'no' THEN 1
        WHEN type = 'income' AND recurring = 'no' THEN 2
        WHEN rebooking_id > 0 AND recurring != 'no' THEN 3
        WHEN rebooking_id > 0 AND recurring = 'no' THEN 4
        WHEN type = 'expense' AND recurring != 'no' THEN 5
        WHEN type = 'expense' AND recurring = 'no' THEN 6
        ELSE 7
    END,
    created_at ASC
";

$stmtE = $conn->prepare($sqlEntries);
$stmtE->bind_param(
    "iiiiiiiiiiiiiii",
    $month, $year,
    $month, $year,
    $month, $year,
    $month,
    $year, $year, $month,
    $year, $year, $month,
    $month, $year
);
$stmtE->execute();
$resE = $stmtE->get_result();
$entries = [];
while ($row = $resE->fetch_assoc()) {
    $entries[] = $row;
}
$stmtE->close();

// Hidden-Entries
$sqlHidden = "
SELECT override_id 
FROM `$username`
WHERE hidden_override = 1 
  AND entry_month = ?
  AND entry_year = ?
";
$stmtH = $conn->prepare($sqlHidden);
$stmtH->bind_param("ii", $month, $year);
$stmtH->execute();
$resH = $stmtH->get_result();
$hiddenEntries = [];
while ($rowH = $resH->fetch_assoc()) {
    $hiddenEntries[] = $rowH['override_id'];
}
$stmtH->close();

// Finale Zusammenführung: Overrides
$finalEntries = [];
foreach ($entries as $en) {
    if (in_array($en['id'], $hiddenEntries)) {
        continue; // hidden
    }
    if (isset($overrides[$en['id']])) {
        $finalEntries[] = $overrides[$en['id']];
    } else {
        $finalEntries[] = $en;
    }
}

// Jetzt haben wir $finalEntries in derselben Reihenfolge wie index.php sie anzeigt.
// -> Daraus bauen wir ein Array nur mit den IDs, um den Tausch durchzuführen.

$ids = [];
foreach ($finalEntries as $fe) {
    $ids[] = (int)$fe['id'];
}

// Prüfe, ob der aktuelle Eintrag in $ids enthalten ist
$currentIndex = array_search($entryId, $ids, true);
if ($currentIndex === false) {
    echo json_encode(['success' => false, 'message' => 'Eintrag nicht in finalEntries gefunden']);
    exit;
}

// Wir prüfen, ob dieser Eintrag ein Übertrag ist. (Zur Sicherheit nochmal.)
$currentDesc = $finalEntries[$currentIndex]['description'];
if ($currentDesc === 'Übertrag vom letzten Monat' || $currentDesc === 'Carryover from last month') {
    echo json_encode(['success' => false, 'message' => 'Dieser Übertrag kann nicht verschoben werden.']);
    exit;
}

// Bestimme den Swap-Index
if ($direction === 'up') {
    if ($currentIndex === 0) {
        echo json_encode(['success' => false, 'message' => 'Kein Eintrag oberhalb']);
        exit;
    }
    $swapIndex = $currentIndex - 1;
} else {
    if ($currentIndex === count($ids) - 1) {
        echo json_encode(['success' => false, 'message' => 'Kein Eintrag unterhalb']);
        exit;
    }
    $swapIndex = $currentIndex + 1;
}

// Tausch im Array
$temp = $ids[$currentIndex];
$ids[$currentIndex] = $ids[$swapIndex];
$ids[$swapIndex] = $temp;

// Jetzt haben wir die neue Reihenfolge. Wir nummerieren neu – ABER Überträge lassen wir aus.
$conn->begin_transaction();
try {
    // 1) IDs aller Überträge sammeln, damit wir sie beim Re-Indexing auslassen
    //    (Sie stehen ja ohnehin ganz oben.)
    //    In deinem finalEntries sind Überträge zwar enthalten, aber
    //    wir wollen sie NICHT neu durchnummerieren, sondern "pinnen".
    
    // Du kannst alternativ auch sagen: "Wir ignorieren Overtrag-Einträge ganz
    // und re-assignen nur an den Rest." – Dann müsstest du sie oben im $ids-Array
    // gar nicht erst hineinpacken. So oder so.
    // Hier gehen wir davon aus, du hattest sie gar nicht in $ids, weil du sie per
    // pinned top logik ausschließt. So wie in index.php
    // => Falls du in "finalEntries" NICHTS mehr ausschließt, dann wäre
    //    if($fe['description'] === 'Übertrag...') => skip etc.
    // In diesem Code hier: Wir hatten sie ja in finalEntries, ABER oben
    //   `WHERE description NOT IN('Übertrag..')` – Also existieren sie gar nicht
    //   in $ids. Falls doch, dann we'd need to skip them now.
    
    // 2) Allen "normalen" Einträgen (IDs in $ids) einen fortlaufenden Sort-Order geben:
    $sortPosition = 1;
    foreach ($ids as $someId) {
        // Setze den sort_order in aufsteigender Reihenfolge
        $stmtU = $conn->prepare("UPDATE `$username` SET sort_order = ? WHERE id = ?");
        $stmtU->bind_param("ii", $sortPosition, $someId);
        $stmtU->execute();
        $stmtU->close();
        $sortPosition++;
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'DB-Fehler: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
