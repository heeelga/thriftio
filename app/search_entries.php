<?php
// — FEHLERREPORTING FÜR DEBUGGING (ggf. im Live-Betrieb ausschalten) —
ini_set('display_errors', 0);
ini_set('log_errors', 1);
//ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

include('init.php');
include 'dbconnection.php';


$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username);

// Suchbegriff:
$searchTerm = isset($_GET['q']) ? "%".$conn->real_escape_string($_GET['q'])."%" : "";
if ($searchTerm === "") {
    error_log("search_entries.php: Suchbegriff leer.");
    echo json_encode([]);
    exit;
}

/*
  Idee:
  1) Erstes Subselect (Alias base):
     - Wir holen alle Einträge, die zur Suche passen (WHERE description LIKE ?).
     - Wir bereiten eine Spalte 'orig_date' (aus entry_year und entry_month) auf.
     - Wir bereiten eine Spalte 'interval_months' vor, die bei recurring='monthly' → 1, 'quarterly'→3, 'semiannually'→6, 'annually'→12, sonst 0.
     
  2) Zweites Subselect (Alias c) liefert einmalig den aktuellen Monatsanfang (z. B. 2025-02-01).

  3) In der äußeren SELECT-Berechnung bilden wir
     next_occurrence:
       - Ist interval_months=0 (nicht wiederkehrend) ODER orig_date >= current_month_start?
         → Benutze orig_date als next_occurrence
       - Sonst:
         → next_occurrence = orig_date + (CEIL( TIMESTAMPDIFF(MONTH, orig_date, current_month_start ) / interval_months ) * interval_months) MONTH
            (also das nächste Vielfache des Intervalls nach dem aktuellen Monatsanfang)

  4) WHERE next_occurrence >= c.current_month_start
     → Wir wollen nur künftige (oder in diesem Monat liegende) Ereignisse.

  5) ORDER BY next_occurrence ASC
     → Sortierung nach Datum

  6) LIMIT 10
*/

$sql = "
SELECT 
    base.id,
    base.description,
    base.amount,
    base.recurring,
    base.entry_month,
    base.entry_year,
    DATE_FORMAT(
      CASE 
        WHEN base.interval_months = 0 
             OR base.orig_date >= c.current_month_start 
        THEN base.orig_date
        ELSE DATE_ADD(
               base.orig_date,
               INTERVAL (
                 CEIL(
                   TIMESTAMPDIFF(MONTH, base.orig_date, c.current_month_start) 
                   / base.interval_months
                 )
                 * base.interval_months
               ) MONTH
             )
      END
    , '%Y-%m-%d') AS date
FROM 
(
  -- Unterabfrage: hole alle relevanten Felder + 2 Hilfsspalten
  SELECT
      id,
      description,
      amount,
      recurring,             -- 'no','monthly','quarterly','semiannually','annually'
      entry_month,
      entry_year,
      -- Originaldatum
      STR_TO_DATE(CONCAT(entry_year, '-', entry_month, '-01'), '%Y-%m-%d') AS orig_date,
      -- Intervall (in Monaten) je nach recurring
      CASE 
        WHEN recurring = 'monthly'      THEN 1
        WHEN recurring = 'quarterly'    THEN 3
        WHEN recurring = 'semiannually' THEN 6
        WHEN recurring = 'annually'     THEN 12
        ELSE 0
      END AS interval_months
  FROM `$table_name`
  WHERE description LIKE ?
) base
-- CROSS JOIN: einmalig den aktuellen Monatsanfang ermitteln
CROSS JOIN (
  SELECT DATE_FORMAT(CURDATE(), '%Y-%m-01') AS current_month_start
) c
-- Nur Datensätze, deren nächste Wiederholung >= Monatsanfang
WHERE 
(
  CASE 
    WHEN base.interval_months = 0 
         OR base.orig_date >= c.current_month_start 
    THEN base.orig_date
    ELSE DATE_ADD(
           base.orig_date,
           INTERVAL (
             CEIL(
               TIMESTAMPDIFF(MONTH, base.orig_date, c.current_month_start) 
               / base.interval_months
             )
             * base.interval_months
           ) MONTH
         )
  END
) >= c.current_month_start
ORDER BY date ASC
LIMIT 10
";

error_log("search_entries.php: SQL-Query: " . $sql);

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("search_entries.php: Prepare fehlgeschlagen: " . $conn->error);
    echo json_encode(["error" => "SQL-Fehler: " . $conn->error]);
    exit;
}

$stmt->bind_param("s", $searchTerm);
if (!$stmt->execute()) {
    error_log("search_entries.php: Execute fehlgeschlagen: " . $stmt->error);
    echo json_encode(["error" => "SQL-Fehler: " . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
if (!$result) {
    error_log("search_entries.php: get_result() fehlgeschlagen: " . $stmt->error);
    echo json_encode(["error" => "SQL-Fehler: " . $stmt->error]);
    exit;
}

$entries = [];
while ($row = $result->fetch_assoc()) {
    $entries[] = [
        'id'          => $row['id'],
        'description' => $row['description'],
        'amount'      => $row['amount'],
        'recurring'   => $row['recurring'],
        'date'        => $row['date'],
        'entry_month' => $row['entry_month'],
        'entry_year'  => $row['entry_year']
    ];
}

$stmt->close();
$conn->close();

error_log("search_entries.php: Gefundene Einträge: " . print_r($entries, true));
echo json_encode($entries);
?>
