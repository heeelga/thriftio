<?php
/**
 * Gibt ein Array der Form
 * [
 *   ['yearMonth' => 'YYYY-MM', 'total' => 123.45],
 *   ['yearMonth' => 'YYYY-MM', 'total' => 234.56],
 *   ...
 * ]
 * zurück.
 */
function getMonthlyTotalsForSavings(PDO $pdo, string $tableName, int $startMonth, int $startYear, int $endMonth, int $endYear): array
{
    $results = [];

    // Hilfsfunktionen, um Monate zu inkrementieren.
    $currentMonth = $startMonth;
    $currentYear  = $startYear;

    while (true) {
        // Die Monats-Summe nach bekannter Override-Logik ermitteln
        $stmt = $pdo->prepare("
            SELECT 
                SUM(
                    CASE 
                        WHEN override = 1 THEN 
                            CASE WHEN type = 'income' THEN amount ELSE -amount END
                        WHEN override = 0 
                             AND description NOT IN (
                                 SELECT description
                                 FROM $tableName
                                 WHERE override = 1 
                                   AND entry_month = :m
                                   AND entry_year  = :y
                             )
                        THEN 
                            CASE WHEN type = 'income' THEN amount ELSE -amount END
                        ELSE 0 
                    END
                ) AS total
            FROM $tableName
            WHERE 
                (
                    (recurring = 'no' AND entry_month = :m AND entry_year = :y)
                    OR (
                        recurring != 'no'
                        AND (
                            recurring_in_month = '0'
                            OR FIND_IN_SET(:m, recurring_in_month) > 0
                        )
                        AND (
                            (entry_year < :y OR (entry_year = :y AND entry_month <= :m))
                            AND (
                                end_date = '00.0000'
                                OR (
                                    CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED) > :y
                                    OR (
                                        CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED) = :y
                                        AND CAST(SUBSTRING_INDEX(end_date, '.', 1) AS UNSIGNED) >= :m
                                    )
                                )
                            )
                        )
                    )
                )
        ");
        $stmt->execute([
            'm' => $currentMonth,
            'y' => $currentYear,
        ]);
        $row   = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = $row['total'] ?? 0;

        // YYYY-MM-Format zusammenbauen
        $yearMonth = sprintf('%04d-%02d', $currentYear, $currentMonth);

        // Ergebnis in Array schreiben
        $results[] = [
            'yearMonth' => $yearMonth,
            'total'     => (float)$total,
        ];

        // Abbruchbedingung, falls wir am Enddatum angekommen sind
        if ($currentMonth == $endMonth && $currentYear == $endYear) {
            break;
        }

        // Monat hochzählen
        $currentMonth++;
        if ($currentMonth > 12) {
            $currentMonth = 1;
            $currentYear++;
        }
    }

    return $results;
}


// +----------------------------------------+
// | 1) DB Connection + Session einbinden   |
// +----------------------------------------+
require_once 'dbconnection.php'; 
session_start(); // falls benötigt

// Hier kannst du optional Fehlermeldungen in ein Log leiten, statt sie ins HTML zu schreiben
// ini_set('display_errors', 0);
// error_reporting(E_ALL);

// +----------------------------------------+
// | 2) Prüfen, ob "action=get_history"     |
// +----------------------------------------+
if (isset($_GET['action']) && $_GET['action'] === 'get_history') {
    // Wir geben JSON aus:
    header('Content-Type: application/json; charset=utf-8');

    // Eingeloggter Benutzer:
    $username = $_SESSION['username'] ?? 'guest';

    // Parameter:
    $savingsName = $_GET['savings_name'] ?? '';
    $startMonth  = (int)($_GET['start_month']  ?? 0);
    $startYear   = (int)($_GET['start_year']   ?? 0);
    $endMonth    = (int)($_GET['end_month']    ?? 0);
    $endYear     = (int)($_GET['end_year']     ?? 0);

    // Tabellenname:
    $tableName = $username . '_' . $savingsName;

    // Deine Funktion zum Ermitteln der Daten:
    // (Stelle sicher, dass sie NICHTS ausgibt, sondern nur zurückgibt!)
    try {
        $resultData = getMonthlyTotalsForSavings($pdo, $tableName, $startMonth, $startYear, $endMonth, $endYear);

        echo json_encode([
            'status' => 'ok',
            'data'   => $resultData
        ]);
    } catch (Exception $ex) {
        echo json_encode([
            'status'  => 'error',
            'message' => $ex->getMessage()
        ]);
    }

    // Nach dem JSON-Output *unbedingt* beenden, damit kein HTML mehr folgt
    exit;
}

// +----------------------------------------+
// | 3) Falls "action" != get_history       |
// |    ggf. HTML ausgeben oder nichts tun  |
// +----------------------------------------+
?>