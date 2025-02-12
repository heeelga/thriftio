<?php
include 'dbconnection.php';

// Prüfen, ob Benutzer eingeloggt
if (!isset($_SESSION['username'])) {
    die("Fehler: Kein Benutzer eingeloggt.");
}

$username = $_SESSION['username'];

// Tabelle für Kontostände
$balanceTable = "Kontostand_" . $username;

// Tabelle erstellen, falls nicht vorhanden
$conn->query("
    CREATE TABLE IF NOT EXISTS `$balanceTable` (
        entry_month INT NOT NULL,
        entry_year  INT NOT NULL,
        balance DECIMAL(10, 2) NOT NULL,
        PRIMARY KEY (entry_month, entry_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Zielmonat/-jahr (z.B. aus GET oder Default: aktuelles Datum)
$currentMonth = intval($_GET['month'] ?? date('n'));
$currentYear  = intval($_GET['year'] ?? date('Y'));

/** 
 * Hilfsfunktionen: 
 * Differenz in Monaten & "Monat+1"
 */
function getMonthDifferenceBalance($fromMonth, $fromYear, $toMonth, $toYear) {
    return ($toYear - $fromYear) * 12 + ($toMonth - $fromMonth);
}

function incrementMonthYearBalance(&$m, &$y) {
    $m++;
    if ($m > 12) {
        $m = 1;
        $y++;
    }
}

/**
 * Kontostand für EINEN Monat (m, y) berechnen und in $balanceTable speichern.
 * Hier wird sichergestellt, dass bei existierenden Override-Einträgen 
 * keine doppelten Summen entstehen.
 */
function computeAndStoreMonthBalance($conn, $username, $balanceTable, $m, $y) {
    // 1) Monats-Summe aus `$username` berechnen:
    //    -> Override-Einträge (override=1) direkt nehmen,
    //    -> ansonsten Serien/Einmal-Einträge (override=0) nur, wenn KEIN Override existiert.
    $stmt = $conn->prepare("
        SELECT 
            SUM(
                CASE WHEN type = 'income' THEN amount ELSE -amount END
            ) AS total
        FROM `$username`
        WHERE 
            (
                -- (A) Override-Eintrag in diesem Monat
                (override = 1
                 AND entry_month = ?
                 AND entry_year  = ?)
            )
            OR
            (
                -- (B) Normal (override=0), 
                --     NUR wenn nicht durch Override ersetzt
                override = 0
                AND (
                    (recurring = 'no' AND entry_month = ? AND entry_year = ?)
                    OR (
                        recurring != 'no'
                        AND id NOT IN (
                            SELECT override_id 
                            FROM `$username`
                            WHERE override = 1 
                              AND entry_month = ?
                              AND entry_year  = ?
                        )
                        AND (
                            recurring_in_month = '0'
                            OR FIND_IN_SET(?, recurring_in_month) > 0
                        )
                        AND (
                            (entry_year < ? OR (entry_year = ? AND entry_month <= ?))
                            AND (
                                repeat_until_year IS NULL
                                OR (
                                    repeat_until_year > ?
                                    OR (repeat_until_year = ? AND repeat_until_month >= ?)
                                )
                            )
                        )
                    )
                )
            )
    ");
    // Wir haben 13 Platzhalter:
    //  1,2  => override=1 (month/year)
    //  3,4  => recurring=no (month/year)
    //  5,6  => Subselect override=1 (month/year)
    //  7    => recurring_in_month
    //  8,9  => Startjahr
    //  10   => Startmonat
    //  11,12 => Endjahr
    //  13   => Endmonat
    $stmt->bind_param(
        "iiiiiiiiiiiii",
        // (A) override=1
        $m, $y,
        // (B) override=0 + recurring=no
        $m, $y,
        // subselect => override=1
        $m, $y,
        // recurring_in_month
        $m,
        // Startjahr
        $y, $y,
        // Startmonat
        $m,
        // Endjahr
        $y, $y,
        // Endmonat
        $m
    );
    $stmt->execute();
    $stmt->bind_result($currentBalance);
    $stmt->fetch();
    $stmt->close();

    // 2) In der Bilanz-Tabelle speichern/aktualisieren
    //    ACHTUNG: Doppeltes "d" (float) bei bind_param
    $stmt = $conn->prepare("
        INSERT INTO `$balanceTable` (entry_month, entry_year, balance)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE balance = ?
    ");
    $stmt->bind_param("iidd", $m, $y, $currentBalance, $currentBalance);
    $stmt->execute();
    $stmt->close();
}

/**
 * Diese Funktion berechnet für ALLE Monate von einem Startmonat/-jahr bis zum Zielmonat/-jahr
 * den Kontostand und speichert ihn in `$balanceTable`.
 */
function fillMissingBalancesUpTo($conn, $username, $balanceTable, $startM, $startY, $endM, $endY) {
    // Schleife: von (startM, startY) bis (endM, endY) inkl.
    $diff = getMonthDifferenceBalance($startM, $startY, $endM, $endY);
    if ($diff < 0) {
        // Falls der Start in der Zukunft liegt (im Vergleich zu endM/endY) -> kein Loop
        return;
    }

    // Temporäre Variablen
    $tempM = $startM;
    $tempY = $startY;

    for ($i = 0; $i <= $diff; $i++) {
        // Für den aktuellen (tempM, tempY) Monat Kontostand berechnen + eintragen
        computeAndStoreMonthBalance($conn, $username, $balanceTable, $tempM, $tempY);
        // Nächsten Monat
        incrementMonthYearBalance($tempM, $tempY);
    }
}


// ---------------------------------------------------
// 1) Letzten gespeicherten Monat/Jahr in `$balanceTable` ermitteln
//    der <= $currentMonth/$currentYear liegt.
// ---------------------------------------------------
$stmt = $conn->prepare("
    SELECT entry_month, entry_year
    FROM `$balanceTable`
    WHERE (entry_year < ?)
       OR (entry_year = ? AND entry_month <= ?)
    ORDER BY entry_year DESC, entry_month DESC
    LIMIT 1
");
$stmt->bind_param("iii", $currentYear, $currentYear, $currentMonth);
$stmt->execute();
$stmt->bind_result($lastStoredMonth, $lastStoredYear);
$foundAny = $stmt->fetch();
$stmt->close();

// Wenn noch gar nichts in der Bilanz-Tabelle steht, könntest du z.B.
// als Start (month/year) das real aktuelle Datum nehmen.
if (!$foundAny) {
    $lastStoredMonth = $currentMonth;
    $lastStoredYear  = $currentYear;
}

// ---------------------------------------------------
// 2) Alle Monate von "letztem gespeicherten" bis zum Zielmonat füllen
// ---------------------------------------------------
fillMissingBalancesUpTo(
    $conn, 
    $username, 
    $balanceTable, 
    $lastStoredMonth, 
    $lastStoredYear, 
    $currentMonth, 
    $currentYear
);

// ---------------------------------------------------
// 3) Jetzt existiert ein Eintrag für $currentMonth/$currentYear. 
//    Wir können also auch sicher den Vormonat abrufen.
// ---------------------------------------------------
$previousMonth = ($currentMonth === 1) ? 12 : $currentMonth - 1;
$previousYear  = ($currentMonth === 1) ? $currentYear - 1 : $currentYear;

// Kontostand dieses Vormonats abrufen
$stmt = $conn->prepare("
    SELECT balance
    FROM `$balanceTable`
    WHERE entry_month = ?
      AND entry_year  = ?
");
$stmt->bind_param("ii", $previousMonth, $previousYear);
$stmt->execute();
$stmt->bind_result($previousBalance);
$stmt->fetch();
$stmt->close();

// Kontostand des aktuellen Monats (nach dem Auffüllen)
$stmt = $conn->prepare("
    SELECT balance
    FROM `$balanceTable`
    WHERE entry_month = ? 
      AND entry_year  = ?
");
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$stmt->bind_result($currentBalance);
$stmt->fetch();
$stmt->close();

// Bilanz-Differenz
$balanceDifference = $currentBalance - ($previousBalance ?: 0);
$balanceClass = ($balanceDifference >= 0) ? 'positive-balance' : 'negative-balance';

// Anzeige
echo "<div class='total-box $balanceClass' style='text-align: left;'>";
echo $translations['record_title'] ?? 'Record to previous month:';
echo "<span>" . number_format($balanceDifference, 2, ',', '.') . " €</span>";
echo "</div>";
?>