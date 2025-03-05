<?php
// include 'dbconnection.php';

if (!isset($_SESSION['username'])) {
    die("Benutzer ist nicht eingeloggt.");
}
$username = $_SESSION['username'];

/**
 * Hilfsfunktion:
 * Formatiert ein Datum (YYYY-MM-DD) je nach LANGUAGE-Variable
 */
function formatDateByLanguageDailyBalance($date, $lang) {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    $ts = strtotime($date);
    if ($lang === 'de') {
        return date('d.m.Y', $ts);  // z. B. 05.02.2025
    } else {
        return date('Y-m-d', $ts);  // z. B. 2025-02-05
    }
}

/**
 * Bestimmt das "effektive Datum" für eine Buchung in diesem Monat.
 */
function getMonthlyEffectiveDateDailyBalance(?string $bookingDate, ?int $recurringDay, int $displayMonth, int $displayYear): string {
    if (!empty($recurringDay)) {
        $day = $recurringDay;
    } elseif (!empty($bookingDate) && $bookingDate !== '0000-00-00') {
        $ts = strtotime($bookingDate);
        $day = (int) date('j', $ts);
    } else {
        $day = 1;
    }

    // Wie viele Tage hat der Monat?
    $startOfMonth = sprintf('%04d-%02d-01', $displayYear, $displayMonth);
    $daysInMonth = (int) date('t', strtotime($startOfMonth));
    if ($day > $daysInMonth) {
        $day = $daysInMonth;
    }

    return sprintf('%04d-%02d-%02d', $displayYear, $displayMonth, $day);
}

// 1) Sprache ermitteln
$lang = getenv('LANGUAGE'); // z. B. 'de' oder 'en'

// 2) Monat und Jahr festlegen
if (isset($_GET['month']) && isset($_GET['year'])) {
    $month = (int) $_GET['month'];
    $year  = (int) $_GET['year'];
} else {
    $month = (int) date('n');
    $year  = (int) date('Y');
}

// 3) Vormonat berechnen (zur Ermittlung des Übertrags)
$previousMonth = $month - 1;
$previousYear  = $year;
if ($previousMonth < 1) {
    $previousMonth = 12;
    $previousYear--;
}

/**
 * Hier die neue, umbenannte Summenabfrage für den Vormonat.
 * (z. B. getCarryoverForLastMonthDailyBalance)
 */
$sqlCarry = "
    SELECT SUM(
        CASE WHEN type = 'income' THEN amount ELSE -amount END
    ) AS total 
    FROM $username
    WHERE
        (
            (recurring = 'no' AND entry_month = ? AND entry_year = ?)
            OR (
                recurring != 'no'
                AND (
                    recurring_in_month = '0'
                    OR FIND_IN_SET(?, recurring_in_month) > 0
                )
                AND (
                    (entry_year < ? OR (entry_year = ? AND entry_month <= ?))
                    AND (
                        end_date = '00.0000'
                        OR (
                            CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED) > ?
                            OR (
                                CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED) = ?
                                AND CAST(SUBSTRING_INDEX(end_date, '.', 1) AS UNSIGNED) >= ?
                            )
                        )
                    )
                )
                AND id NOT IN (
                    SELECT override_id FROM $username
                    WHERE override = 1 AND entry_month = ? AND entry_year = ?
                )
            )
            OR (
                override = 1 AND entry_month = ? AND entry_year = ?
            )
        )
";
$stmt = $conn->prepare($sqlCarry);
$stmt->bind_param(
    "iiiiiiiiiiiii",
    $previousMonth, 
    $previousYear,
    $previousMonth,
    $previousYear,
    $previousYear,
    $previousMonth,
    $previousYear,
    $previousYear,
    $previousMonth,
    $previousMonth,
    $previousYear,
    $previousMonth,
    $previousYear
);
$stmt->execute();
$stmt->bind_result($carryover);
$stmt->fetch();
$stmt->close();

// Eintrag "Übertrag vom letzten Monat" anlegen/aktualisieren
$carryoverDescription = $translations['carryover'] ?? 'Übertrag vom letzten Monat';

$stmt = $conn->prepare("
    SELECT id
    FROM $username
    WHERE entry_month = ?
      AND entry_year  = ?
      AND description = ?
");
$stmt->bind_param("iis", $month, $year, $carryoverDescription);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Update vorhandener Eintrag
    $row = $res->fetch_assoc();
    $entryId = $row['id'];
    $stmt = $conn->prepare("
        UPDATE $username
        SET amount = ?, type = ?
        WHERE id = ?
    ");
    $type = ($carryover > 0) ? 'income' : 'expense';
    $carryoverAmount = abs($carryover ?? 0);
    $stmt->bind_param("dsi", $carryoverAmount, $type, $entryId);
    $stmt->execute();
    $stmt->close();
} else {
    // Neu einfügen
    $stmt = $conn->prepare("
        INSERT INTO $username
            (type, amount, description, recurring, created_at, entry_month, entry_year)
        VALUES (?, ?, ?, 'no', NOW(), ?, ?)
    ");
    $type = ($carryover > 0) ? 'income' : 'expense';
    $carryoverAmount = abs($carryover ?? 0);
    $stmt->bind_param("sdsii", $type, $carryoverAmount, $carryoverDescription, $month, $year);
    $stmt->execute();
    $stmt->close();
}

// 5) Summe für den gewählten Monat
$todayMonth = (int) date('n');
$todayYear  = (int) date('Y');

$limitDate = null;
$displayText = '';
if ($year < $todayYear || ($year === $todayYear && $month < $todayMonth)) {
    // vergangener Monat
    $displayText = ($lang === 'de')
        ? "Kontostand am letzten Tag des Monats $month/$year"
        : "Balance on the last day of $month/$year";
} elseif ($year == $todayYear && $month == $todayMonth) {
    // aktueller Monat
    $todayIso = date('Y-m-d');
    $limitDate = $todayIso;
    $dateFmt = formatDateByLanguageDailyBalance($todayIso, $lang);
    $displayText = ($lang === 'de')
        ? "Kontostand am $dateFmt"
        : "Balance on $dateFmt";
} else {
    // zukünftiger Monat
    $firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
    $limitDate = $firstOfMonth;
    $dateFmt = formatDateByLanguageDailyBalance($firstOfMonth, $lang);
    $displayText = ($lang === 'de')
        ? "Kontostand am $dateFmt"
        : "Balance on $dateFmt";
}

// Alle Einträge für diesen Monat laden
$sqlAll = "
    SELECT
        id, type, amount, description,
        booking_date, recurring_day,
        recurring, recurring_in_month,
        entry_month, entry_year,
        override, override_id,
        end_date
    FROM $username
    WHERE
        (
            (recurring = 'no' AND entry_month = ? AND entry_year = ?)
            OR (
                recurring != 'no'
                AND (
                    recurring_in_month = '0'
                    OR FIND_IN_SET(?, recurring_in_month) > 0
                )
                AND (
                    (entry_year < ? OR (entry_year = ? AND entry_month <= ?))
                    AND (
                        end_date = '00.0000'
                        OR (
                            CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED) > ?
                            OR (
                                CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED) = ?
                                AND CAST(SUBSTRING_INDEX(end_date, '.', 1) AS UNSIGNED) >= ?
                            )
                        )
                    )
                )
                AND id NOT IN (
                    SELECT override_id FROM $username
                    WHERE override = 1
                      AND entry_month = ?
                      AND entry_year  = ?
                )
            )
            OR (
                override = 1
                AND entry_month = ?
                AND entry_year  = ?
            )
        )
";
$stmtAll = $conn->prepare($sqlAll);
$stmtAll->bind_param(
    "iiiiiiiiiiiii",
    $month, $year,
    $month,
    $year, $year, $month,
    $year, $year, $month,
    $month, $year,
    $month, $year
);
$stmtAll->execute();
$resAll = $stmtAll->get_result();

$finalSum = 0.0;
while ($row = $resAll->fetch_assoc()) {
    $bookingDate  = $row['booking_date']   ?? null;
    $recurringDay = $row['recurring_day']  ?? null;

    $effectiveDate = getMonthlyEffectiveDateDailyBalance($bookingDate, (int)$recurringDay, $month, $year);
    if ($limitDate !== null && $effectiveDate > $limitDate) {
        continue;
    }

    if ($row['type'] === 'income') {
        $finalSum += (float)$row['amount'];
    } else {
        $finalSum -= (float)$row['amount'];
    }
}
$stmtAll->close();

if ($lang === 'de') {
    $formattedSum = number_format($finalSum, 2, ',', '.');
} else {
    $formattedSum = number_format($finalSum, 2, '.', ',');
}

//echo "<p>$displayText: $formattedSum €</p>";

//$conn->close();