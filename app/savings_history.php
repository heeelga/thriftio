<?php
// ----------------------------------------------------------
// A) Session + DB-Verbindung
// ----------------------------------------------------------
include('init.php');
require_once 'dbconnection.php';

// Optional: Stelle sicher, dass PHP-Warnungen NICHT ausgegeben werden (um JSON nicht zu zerstören)
ini_set('display_errors', 0);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Beispielhafte Übersetzungen, falls du $translations brauchst
$translations = [
   'carryover' => 'Carryover-Eintrag' // Nur als Beispiel
];

/**
 * Hilfsfunktion: errechnet die Differenz in Monaten zwischen [startMonth,startYear] und [endMonth,endYear].
 * Gibt z.B. 0 zurück, wenn End genau Start ist, 5 wenn 5 Monate in der Zukunft, etc.
 */
function getMonthDifferenceSavings(int $startMonth, int $startYear, int $endMonth, int $endYear): int
{
    // (year2 - year1)*12 + (month2 - month1)
    return ($endYear - $startYear) * 12 + ($endMonth - $startMonth);
}

/**
 * Erhöht Monat+Jahr um 1 Monat.
 * Bsp: (12, 2023) => (1, 2024)
 */
function incrementMonthYearSavings(&$m, &$y): void
{
    $m++;
    if ($m > 12) {
        $m = 1;
        $y++;
    }
}

/**
 * Dummy-Beispiel einer Funktion, die für einen Monat
 * Carryover (also zukünftige wiederkehrende Einträge) in die DB schreibt.
 *
 * Du hast in deinem Code bereits so eine Funktion:
 *   calculateAndInsertCarryoverForSavings($pdo, $table, $tmpM, $tmpY, $translations);
 * Füge hier einfach **deine** echte Implementierung ein.
 */
function calculateAndInsertCarryoverForSavings(PDO $pdo, string $table, int $month, int $year, array $translations): void
{
    // Hier deine echte Logik,
    // z.B. Umbuchungen für recurring=monthly anlegen, falls noch nicht vorhanden, etc.
    // 
    // Dies ist nur ein Platzhalter:
    // -------------------------------------------
    // echo "Carryover done: $table $month/$year<br>"; // <== NICHT AUSGEBEN, sonst zerstörst du JSON!
    // -------------------------------------------
}

/**
 * Diese Funktion erzeugt für ALLE Sparkonten des Users
 * in allen Monaten von [startMonth, startYear] bis [endMonth, endYear]
 * die nötigen Future-Einträge (Carryover). So liegen Einträge für
 * die Zukunft wirklich in der Tabelle.
 */
function ensureCarryoverUpTo(PDO $pdo, string $username, int $startMonth, int $startYear, int $endMonth, int $endYear, array $translations): void
{
    // Tabellen, die dem Muster "username_*" entsprechen
    $stmt = $pdo->prepare("SHOW TABLES LIKE :pattern");
    $stmt->execute(['pattern' => "{$username}_%"]);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Differenz
    $monthDiff = getMonthDifferenceSavings($startMonth, $startYear, $endMonth, $endYear);
    if ($monthDiff <= 0) {
        // Keine Zukunft, nichts zu tun
        return;
    }

    // Jetzt monatsweise hochzählen
    $tmpM = $startMonth;
    $tmpY = $startYear;

    while (true) {
        foreach ($tables as $table) {
            calculateAndInsertCarryoverForSavings($pdo, $table, $tmpM, $tmpY, $translations);
        }

        incrementMonthYearSavings($tmpM, $tmpY);

        if ($tmpM == $endMonth && $tmpY == $endYear) {
            break;
        }
    }
}

/**
 * Deine vorhandene Funktion, die anhand der DB-Daten die Summen (inkl. override-Logik) ermittelt.
 */
function getMonthlyTotalsForSavings(PDO $pdo, string $tableName, int $startMonth, int $startYear, int $endMonth, int $endYear): array
{
    $results = [];

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

        $yearMonth = sprintf('%04d-%02d', $currentYear, $currentMonth);
        $results[] = [
            'yearMonth' => $yearMonth,
            'total'     => (float)$total,
        ];

        // Abbruch, wenn Enddatum erreicht
        if ($currentMonth == $endMonth && $currentYear == $endYear) {
            break;
        }

        // Nächster Monat
        incrementMonthYearSavings($currentMonth, $currentYear);
    }

    return $results;
}

// ----------------------------------------------------------
// B) Action = get_history => JSON-Ausgabe
// ----------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');

    // Eingeloggter Benutzer
    $username = $_SESSION['username'] ?? 'guest';

    // Parameter
    $savingsName = $_GET['savings_name'] ?? '';
    $startMonth  = (int)($_GET['start_month']  ?? 0);
    $startYear   = (int)($_GET['start_year']   ?? 0);
    $endMonth    = (int)($_GET['end_month']    ?? 0);
    $endYear     = (int)($_GET['end_year']     ?? 0);

    // Eigentliche Tabelle => z.B. "guest_markus_JT"
    $tableName = $username . '_' . $savingsName;

    try {
        // 1) Zukünftige Monate in DB anlegen (Carryover)
        ensureCarryoverUpTo($pdo, $username, $startMonth, $startYear, $endMonth, $endYear, $translations);

        // 2) Jetzt Summen abrufen
        $resultData = getMonthlyTotalsForSavings($pdo, $tableName, $startMonth, $startYear, $endMonth, $endYear);

        echo json_encode([
            'status' => 'ok',
            'data'   => $resultData
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

// ----------------------------------------------------------
// Falls kein "get_history", ggf. HTML ausgeben oder gar nichts
// ----------------------------------------------------------

// Hier kannst du optional eine HTML-Seite ausgeben, 
// falls du diese Datei direkt aufrufst. 
?>