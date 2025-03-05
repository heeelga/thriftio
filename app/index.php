<?php
include('init.php');
include('global_functions.php');

// Funktion um Datum in Serie dynamisch anzupassen
if (!function_exists('getDisplayBookingDate')) {
    function getDisplayBookingDate(array $row, int $displayMonth, int $displayYear): string
    {
        if (!empty($row['recurring_day'])) {
            $day = (int)$row['recurring_day'];
        } elseif (!empty($row['booking_date'])) {
            $ts = strtotime($row['booking_date']);
            $day = (int)date('j', $ts);
        } else {
            $day = 1;
        }
        // Clampen an Monatsende
        $testDate    = sprintf('%04d-%02d-01', $displayYear, $displayMonth);
        $daysInMonth = (int) date('t', strtotime($testDate));
        if ($day > $daysInMonth) {
            $day = $daysInMonth;
        }
        return sprintf('%04d-%02d-%02d', $displayYear, $displayMonth, $day);
    }
}


// Funktion zur Überprüfung/Erstellung der Tabelle
function ensureUserTableExists($username, $conn) {
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $username); // Tabellennamen validieren

    $checkTableQuery = "
        SELECT COUNT(*)
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
          AND table_name = '$tableName'
    ";

    $result = $conn->query($checkTableQuery);
    if (!$result) {
        error_log("Fehler bei der Überprüfung der Tabelle: " . $conn->error);
        die("Fehler bei der Überprüfung der Tabelle: " . $conn->error);
    }
    $tableExists = $result->fetch_row()[0] > 0;

    if (!$tableExists) {
        $createTableQuery = "
            CREATE TABLE `$tableName` (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                type ENUM('income','expense') NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description VARCHAR(255) NOT NULL,
                recurring ENUM('no','monthly','quarterly','semiannually','annually') NOT NULL DEFAULT 'no',
                recurring_in_month VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                start_date VARCHAR(7) NOT NULL DEFAULT '00.0000',
                entry_month INT(11) NOT NULL,
                entry_year INT(11) NOT NULL,
                repeat_until_month INT(11) DEFAULT NULL,
                repeat_until_year INT(11) DEFAULT NULL,
                end_date VARCHAR(7) NOT NULL DEFAULT '00.0000',
                is_hidden TINYINT(1) NOT NULL DEFAULT 0,
                rebooking_id INT(11) DEFAULT 0,
                rebooking_partner VARCHAR(255) DEFAULT NULL,
                override TINYINT(1) NOT NULL DEFAULT 0,
                override_id INT(11) DEFAULT NULL,
                hidden_override TINYINT(1) NOT NULL DEFAULT 0,
                category VARCHAR(255) DEFAULT NULL,
                booking_date DATE NULL DEFAULT NULL,
                recurring_day INT NULL DEFAULT NULL
            )
        ";

        if (!$conn->query($createTableQuery)) {
            error_log("Fehler beim Erstellen der Tabelle: " . $conn->error);
            die("Fehler beim Erstellen der Tabelle: " . $conn->error);
        }
    }
}

include('menubar.php');
include('style.css');

// Sicherheitshalber Benutzername validieren (kein SQL-Injection)
if (empty($username) || !is_string($username)) {
    die("Fehler: Ungültiger Benutzername.");
}

include 'dbconnection.php';

// Sicherstellen, dass ein Benutzer eingeloggt ist
$releaseNotes = '';

// Prüfen, ob Release Notes angezeigt werden müssen
$query = "SELECT release_notes_available, release_notes_read FROM user WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && $user['release_notes_available'] && !$user['release_notes_read']) {
    // Release Notes aus der Textdatei lesen
    $releaseNotes = file_get_contents(__DIR__ . '/release_notes.txt');

    // Markiere als gelesen
    $updateQuery = "UPDATE user SET release_notes_read = TRUE WHERE username = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('s', $username);
    $stmt->execute();

    // Overlay anzeigen lassen
    $showOverlayScript = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('release-notes-overlay').style.display = 'flex';
            });
          </script>";
} else {
    $showOverlayScript = ""; // Keine Anzeige nötig
}
?>

    <!-- Release Notes Overlay -->
    <div id="release-notes-overlay" style="display:none;">
        <div class="release-modal">
            <button id="close-release-notes" onclick="closeReleaseNotes()">X</button>
            <h2>🎉Neues Release🎉</h2>
            <p><?php echo nl2br(htmlspecialchars($releaseNotes)); ?></p>
        </div>
    </div>

    <!-- JavaScript für den Schließen-Button -->
    <script>
    function closeReleaseNotes() {
        const overlay = document.getElementById('release-notes-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }
    </script>
    

<!-- Overlay für das Kreisdiagramm -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Chart.js einfügen -->
<div id="chart-overlay" class="overlay" style="display: none;">
    <div class="modal">
        <div id="close-chart-button" class="close-button">&times;</div>
        <h2>Kostenverteilung nach Kategorien</h2>
        <canvas id="categoryChart"></canvas>
    </div>
</div>

<!-- PHP: Overlay anzeigen -->
<?php echo $showOverlayScript;


// Monat und Jahr aus URL-Parametern abrufen
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Titel für den Monat
$monthNames = [
    1 => "Januar", 2 => "Februar", 3 => "März", 4 => "April", 5 => "Mai", 6 => "Juni",
    7 => "Juli", 8 => "August", 9 => "September", 10 => "Oktober", 11 => "November", 12 => "Dezember"
];
$currentMonthName = $monthNames[$month];
?>
<head>
  <style>
    /* Inhalt vorläufig verstecken */
    body {
      visibility: hidden;
    }
  </style>
  <script>
    (function() {
      // Sofortiges Setzen des Dark Mode, falls im Local Storage gespeichert
      if (localStorage.getItem('theme') === 'dark-theme') {
        document.body.classList.add('dark-theme');
      }
      // Nach DOMContentLoaded und einem minimalen Delay wird der Body wieder sichtbar
      window.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
          document.body.style.visibility = 'visible';
        }, 75); // 75ms Delay – diesen Wert kannst du je nach Bedarf anpassen
      });
    })();
  </script>
</head>



<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<link rel="apple-touch-icon" href="/images/icon_small.jpg">
<link rel="icon" href="/images/icon_small.jpg" type="image/jpg">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

</head>  
<body>

<!-- Floating Buttons über Plus Button -->
<div id="floating-action-buttons">
    <button id="main-fab" class="fab">
        <i class="fas fa-plus"></i>
    </button>
    <div id="fab-options" class="fab-options hidden">
        <button id="add-entry" class="fab-option" title="<?php echo $translations['new_entry'] ?>">
            <i class="fas fa-wallet"></i>
            <span class="fab-label"><?php echo $translations['new_entry'] ?></span>
        </button>
        <button id="add-rebooking" class="fab-option" title="<?php echo $translations['new_rebooking'] ?>">
            <i class="fas fa-exchange-alt"></i>
            <span class="fab-label"><?php echo $translations['new_rebooking'] ?></span>
        </button>
        <button id="add-savings" class="fab-option" title="<?php echo $translations['new_savings_account'] ?>">
            <i class="fas fa-piggy-bank"></i>
            <span class="fab-label"><?php echo $translations['new_savings_account'] ?></span>
        </button>
        <button id="unhide-all" class="fab-option" title="<?php echo $translations['unhide_all'] ?>">
            <i class="fas fa-eye"></i>
            <span class="fab-label"><?php echo $translations['unhide_all'] ?></span>
        </button>
        <!-- Neuer Hilfebutton ganz unten -->
        <button id="toggle-help" class="fab-option" title="Hilfe">
            <i class="fas fa-question-circle"></i>
            <span class="fab-label">Hilfe</span>
        </button>
    </div>
</div>



<div id="background">


<script>
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const searchOverlay = document.getElementById('search-overlay');
        if (searchOverlay && searchOverlay.style.display === 'flex') {
            searchOverlay.style.display = 'none';
        }
    }
});

window.addEventListener('click', (event) => {
    const searchOverlay = document.getElementById('search-overlay');
    if (searchOverlay && event.target === searchOverlay) {
        searchOverlay.style.display = 'none';
    }
});
</script>

    <?php
    include('calculate_dailybalance.php');
    ?>


<html lang="de" class="<?php echo $theme; ?>">

<title>Kontobewegungen - <?= htmlspecialchars($currentMonthName) ?> <?= htmlspecialchars($year) ?></title>

<div class="month-navigation" style="display: flex; align-items: center; justify-content: center; gap: 10px;">
    <?php
    $germanMonths = [
        1 => 'Januar',
        2 => 'Februar',
        3 => 'März',
        4 => 'April',
        5 => 'Mai',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'August',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Dezember',
    ];

    $currentMonth = date('n');
    $currentYear = date('Y');

    // Ermittlung des ersten und letzten Monats im Dropdown
    $startMonth = ($currentMonth - 3 <= 0) ? 12 + ($currentMonth - 3) : $currentMonth - 3;
    $startYear = ($currentMonth - 3 <= 0) ? $currentYear - 1 : $currentYear;
    $endMonth = ($currentMonth + 6 - 1) % 12 + 1;
    $endYear = $currentYear + floor(($currentMonth + 6 - 1) / 12);

    $isFirstMonth = ($month == $startMonth && $year == $startYear);
    $isLastMonth = ($month == $endMonth && $year == $endYear);
    ?>

    <!-- Zurück-Pfeil -->
    <button class="month-arrow" 
            onclick="location.href='index.php?month=<?= $month - 1 == 0 ? 12 : $month - 1 ?>&year=<?= $month - 1 == 0 ? $year - 1 : $year ?>'" 
            <?= $isFirstMonth ? 'disabled' : '' ?>>
        &larr;
    </button>

    <!-- Monats-Dropdown -->
    <select id="month-selector" onchange="changeMonth()" style="appearance: none; padding: 10px 15px; font-size: 16px; font-weight: 500; background-color: #f0f0f0; border: none; border-radius: 20px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); cursor: pointer; transition: all 0.2s ease;">
        <?php
        // Dropdown-Einträge (letzte 3 Monate bis 8 zukünftige Monate)
        for ($i = -3; $i <= 8; $i++) {
            // Berechnung von Monat und Jahr
            $dropdownMonth = ($currentMonth + $i - 1) % 12 + 1;
            $dropdownYear = $currentYear + floor(($currentMonth + $i - 1) / 12);

            // Wenn der Monat negativ wird, passen wir das Jahr entsprechend an
            if ($i < 0 && ($currentMonth + $i) <= 0) {
                $dropdownMonth = 12 + ($currentMonth + $i);
                $dropdownYear = $currentYear - 1;
            }

            $selected = ($dropdownMonth == $month && $dropdownYear == $year) ? 'selected' : '';
            $monthName = $germanMonths[$dropdownMonth] . " $dropdownYear";
            echo "<option value='$dropdownMonth-$dropdownYear' $selected>" . htmlspecialchars($monthName) . "</option>";
        }
        ?>
    </select>

    <!-- Vorwärts-Pfeil -->
    <button class="month-arrow" 
            onclick="location.href='index.php?month=<?= $month + 1 == 13 ? 1 : $month + 1 ?>&year=<?= $month + 1 == 13 ? $year + 1 : $year ?>'" 
            <?= $isLastMonth ? 'disabled' : '' ?>>
        &rarr;
    </button>
</div>


<div class="container">

<?php if ($month != date('n') || $year != date('Y')): ?>
<div class="home-button">
    <a href="index.php?month=<?= date('n') ?>&year=<?= date('Y') ?>">
        &#8592; <?php echo $translations['back_to_current_month'] ?? 'Back to current month'; ?>
    </a>
</div>

<?php endif; ?>

<div id="entries">

<?php
// -----------------------------------------------------------
// 1) VORBEREITUNG & FUNKTIONEN
// -----------------------------------------------------------

$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');

// Hilfsfunktion: Berechnet die Anzahl der vollen Monate zwischen zwei (Monat/Jahr)-Paaren
function getMonthDifference($fromMonth, $fromYear, $toMonth, $toYear) {
    return ($toYear - $fromYear) * 12 + ($toMonth - $fromMonth);
}

// Sorgt dafür, dass man von einem (m, y)-Paar sauber in den nächsten Monat geht
function incrementMonthYear(&$m, &$y) {
    $m++;
    if ($m > 12) {
        $m = 1;
        $y++;
    }
}

/**
 * Diese Funktion berechnet den Übertrag (Carryover) für einen gegebenen Monat (m/y)
 * und schreibt ihn als "Übertrag vom letzten Monat" in den Folgemonat.
 */
function calculateAndInsertCarryover($conn, $username, $m, $y, $translations) {
    // 1) Summe im Quellmonat berechnen
    $stmtCarry = $conn->prepare("
        SELECT COALESCE(SUM(
            CASE WHEN type = 'income' THEN amount ELSE -amount END
        ), 0) AS month_sum 
        FROM `$username`
        WHERE
        (
            -- Einzelbuchungen
            (recurring = 'no' AND entry_month = ? AND entry_year = ?)
            OR (
                -- Wiederkehrende Einträge, sofern gültig und ohne existierenden Override
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
                    SELECT override_id
                    FROM `$username`
                    WHERE override = 1 AND entry_month = ? AND entry_year = ?
                )
            )
            OR (
                -- Override-Einträge
                override = 1 AND entry_month = ? AND entry_year = ?
            )
        )
    ");
    // Platzhalter
    $stmtCarry->bind_param(
        "iiiiiiiiiiiii",
        $m,         // (1) Einzelbuchung: entry_month
        $y,         // (2) Einzelbuchung: entry_year
        $m,         // (3) FIND_IN_SET: aktueller Monat
        $y,         // (4) entry_year < ?
        $y,         // (5) entry_year = ?
        $m,         // (6) entry_month <= ?
        $y,         // (7) > ? (Enddatum-Jahr)
        $y,         // (8) = ? (Enddatum-Jahr)
        $m,         // (9) >= ? (Enddatum-Monat)
        $m,         // (10) override=1 / month
        $y,         // (11) override=1 / year
        $m,         // (12) override=1 / month
        $y          // (13) override=1 / year
    );
    $stmtCarry->execute();
    $stmtCarry->bind_result($monthSum);
    $stmtCarry->fetch();
    $stmtCarry->close();

    // 2) Berechne den Folgemonat und trage den Übertrag dort ein
    $nM = $m;
    $nY = $y;
    incrementMonthYear($nM, $nY);

    $carryDescrDE = "Übertrag vom letzten Monat";
    $carryDescrEN = "Carryover from last month";
    $carryDescription = ($translations['carryover'] ?? $carryDescrDE);

    $finalAmount = abs($monthSum);
    $signParam   = ($monthSum >= 0) ? 1 : -1;

    // Prüfen, ob im Folgemonat bereits ein Übertragseintrag existiert
    $checkStmt = $conn->prepare("
        SELECT id
        FROM `$username`
        WHERE description IN (?, ?)
          AND entry_month = ?
          AND entry_year = ?
    ");
    $checkStmt->bind_param("ssii", $carryDescrDE, $carryDescrEN, $nM, $nY);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Existierender Eintrag: aktualisieren
        $row = $checkResult->fetch_assoc();
        $updateStmt = $conn->prepare("
            UPDATE `$username`
            SET amount = ?,
                type   = IF(? >= 0, 'income', 'expense'),
                recurring = 'no'
            WHERE id = ?
        ");
        $updateStmt->bind_param("dii", $finalAmount, $signParam, $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Neuer Eintrag anlegen
        $insertStmt = $conn->prepare("
            INSERT INTO `$username`
            (description, amount, type, recurring, entry_month, entry_year)
            VALUES (?, ?, IF(? >= 0, 'income', 'expense'), 'no', ?, ?)
        ");
        $insertStmt->bind_param("sdiii", $carryDescription, $finalAmount, $signParam, $nM, $nY);
        $insertStmt->execute();
        $insertStmt->close();
    }
    $checkStmt->close();
}

// -----------------------------------------------------------
// 2) ÜBERTRAG FÜR ALLE ZWISCHENMONATE BERECHNEN
// -----------------------------------------------------------
$monthDiff = getMonthDifference($currentMonth, $currentYear, $month, $year);
if ($monthDiff > 0) {
    $tmpM = $currentMonth;
    $tmpY = $currentYear;
    while (true) {
        calculateAndInsertCarryover($conn, $username, $tmpM, $tmpY, $translations);
        incrementMonthYear($tmpM, $tmpY);
        if ($tmpM == $month && $tmpY == $year) {
            break;
        }
    }
}

// -----------------------------------------------------------
// 3) ABFRAGE DER OVERRIDES (SEPARAT) UND DANN DER NORMALEN EINTRÄGE
// -----------------------------------------------------------

// 3a) OVERRIDES für den aktuellen (gewählten) Monat
$sqlOverrides = "
SELECT * 
FROM `$username`
WHERE override = 1 
  AND entry_month = ? 
  AND entry_year = ?
";
$stmtOverrides = $conn->prepare($sqlOverrides);
$stmtOverrides->bind_param("ii", $month, $year);
$stmtOverrides->execute();
$resultOverrides = $stmtOverrides->get_result();

$overrides = [];
while ($row = $resultOverrides->fetch_assoc()) {
    // Key ist override_id -> wir überschreiben damit das Original
    $overrides[$row['override_id']] = $row;
}
$stmtOverrides->close();

// 3b) NORMALE EINTRÄGE (override=0) und ausgeblendete filtern
//    (Overrides werden hier NICHT geladen, damit sie nicht doppelt auftauchen)
$sqlEntries = "
SELECT *, 
    CASE 
        WHEN rebooking_id > 0 THEN 'rebooking'
        ELSE type
    END AS entry_type,
    CASE
        WHEN end_date IS NOT NULL 
             AND end_date != '00.0000' 
        THEN CAST(SUBSTRING_INDEX(end_date, '.', 1) AS UNSIGNED)
        ELSE NULL
    END AS repeat_until_month,
    CASE
        WHEN end_date IS NOT NULL 
             AND end_date != '00.0000' 
        THEN CAST(SUBSTRING_INDEX(end_date, '.', -1) AS UNSIGNED)
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
ORDER BY 
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

$stmtEntries = $conn->prepare($sqlEntries);
$stmtEntries->bind_param(
    "iiiiiiiiiiiii",
    $month, // 1 Hidden Overrides - Monat
    $year,  // 2 Hidden Overrides - Jahr
    $month, // 3 Hidden Overrides - Monat (Union)
    $year,  // 4 Hidden Overrides - Jahr (Union)
    $month, // 5 Einmalige Einträge - Monat
    $year,  // 6 Einmalige Einträge - Jahr
    $month, // 7 Wiederkehrende Einträge - Monat
    $year,  // 8 Wiederkehrende Einträge - Startjahr
    $year,  // 9 Wiederkehrende Einträge - Startjahr
    $month, // 10 Wiederkehrende Einträge - Startmonat
    $year,  // 11 Wiederkehrende Einträge - Endjahr
    $year,  // 12 Wiederkehrende Einträge - Endjahr
    $month  // 13 Wiederkehrende Einträge - Endmonat
);
$stmtEntries->execute();
$resultEntries = $stmtEntries->get_result();

$entries = [];
while ($row = $resultEntries->fetch_assoc()) {
    $entries[] = $row;
}
$stmtEntries->close();

// -----------------------------------------------------------
// 4) MERGEN: OVERRIDES ERSETZEN DAS ORIGINAL
// -----------------------------------------------------------

$sqlHiddenEntries = "
SELECT override_id 
FROM `$username`
WHERE hidden_override = 1 
  AND entry_month = ? 
  AND entry_year = ?
";
$stmtHidden = $conn->prepare($sqlHiddenEntries);
$stmtHidden->bind_param("ii", $month, $year);
$stmtHidden->execute();
$resultHidden = $stmtHidden->get_result();
$hiddenEntries = [];
while ($row = $resultHidden->fetch_assoc()) {
    $hiddenEntries[] = $row['override_id'];
}
$stmtHidden->close();

$finalEntries = [];
foreach ($entries as $entry) {
    // Falls ausgeblendet, überspringen
    if (in_array($entry['id'], $hiddenEntries)) {
        continue;
    }
    // Falls ein Override existiert -> diesen Datensatz nehmen
    if (isset($overrides[$entry['id']])) {
        $finalEntries[] = $overrides[$entry['id']];
    } else {
        $finalEntries[] = $entry;
    }
}

// -----------------------------------------------------------
// 5) EINTRÄGE AUSGEBEN
// -----------------------------------------------------------
foreach ($finalEntries as $row) {
    // Prüfen, ob das Enddatum überschritten ist
    if (!is_null($row['repeat_until_year']) && !is_null($row['repeat_until_month'])) {
        if (
            ($row['repeat_until_year'] < $year) ||
            ($row['repeat_until_year'] == $year && $row['repeat_until_month'] < $month)
        ) {
            continue;
        }
    }

    // CSS-Klasse je nach Typ
    $entryClass = ($row['rebooking_id'] > 0) 
        ? 'rebooking' 
        : (($row['type'] === 'income') ? 'income' : 'expense');

    $amount = number_format((float)$row['amount'], 2, ',', '.');

    // Umbuchungsrichtung "to" oder "from"
    $dataRebooking = '';
    if ($row['rebooking_id'] > 0) {
        $dataRebooking = ($row['type'] === 'expense') ? 'to' : 'from';
    }

    // Filter-Text (Beschreibung, Kategorie, Betrag, Typ)
    $beschreibung = $row['description'] ?? '';
    $kategorie    = $row['category'] ?? '';
    $unformatted  = (float)$row['amount'];
    $typ          = ($row['rebooking_id'] > 0) ? 'rebooking' : $row['type'];

    $filterTextRaw = mb_strtolower($beschreibung . ' ' . $kategorie . ' ' . $unformatted . ' ' . $typ, 'UTF-8');

    echo "<div class='entry-box $entryClass'
            data-id='" . $row['id'] . "'
            data-type='" . ($row['rebooking_id'] > 0 ? 'rebooking' : $row['type']) . "'
            data-amount='" . $row['amount'] . "'
            data-filter-text='" . htmlspecialchars($filterTextRaw, ENT_QUOTES) . "'
            data-rebooking='" . $dataRebooking . "'>";

    // Symbol für wiederkehrende Einträge
    if ($row['recurring'] !== 'no') {
        echo "<div class='recurring-icon' title='Serie'><i class='fas fa-sync-alt'></i></div>";
    }

    // Symbol für einzeln bearbeitete Serienelemente (Override)
    if ($row['override'] == 1) {
        echo "<div class='recurring-icon' title='Serie'><i class='fas fa-sync-alt'></i></div>";
        echo "<div class='override-icon' title='Einzelbearbeitetes Serienelement'><i class='fas fa-pencil-alt'></i></div>";
    }

    // Inhalt
    echo "<div class='entry-content'>";
        echo "<div class='entry-title'>" . htmlspecialchars($row['description']) . "</div>";
        echo "<br>";
        $currency = getenv('CURRENCY') ?: '€';
        echo "<div class='entry-amount'><span class='currency'>" . $amount . " " . $currency . "</span></div>";

        // Dynamisches Buchungsdatum
        $displayDate = getDisplayBookingDate($row, $month, $year);
        $lang = getenv('LANGUAGE'); 
        if (!empty($displayDate) && $displayDate !== '0000-00-00') {
            $ts = strtotime($displayDate);
            if ($lang === 'de') {
                $formattedDate = date('d.m.Y', $ts); 
            } else {
                $formattedDate = date('Y-m-d', $ts); 
            }
            echo "<div class='entry-date'>" . htmlspecialchars($formattedDate) . "</div>";
        }

        // Umbuchung?
        if ($row['rebooking_id'] > 0) {
            echo "<div class='rebooking-icon' title='Umbuchung'><i class='fas fa-arrows-alt-h'></i></div>";
            $rebookingDirection = ($row['type'] === 'expense') 
                ? $translations['rebooking_to'] 
                : $translations['rebooking_from'];
            $regularityText = $rebookingDirection . htmlspecialchars($row['rebooking_partner']);
            echo "<div class='entry-regulartype'>";
            if ($row['recurring'] !== 'no') {
                switch ($row['recurring']) {
                    case 'monthly':
                        $regularityText .= " (Monatlich)";
                        break;
                    case 'quarterly':
                        $regularityText .= " (Vierteljährlich)";
                        break;
                    case 'semiannually':
                        $regularityText .= " (Halbjährlich)";
                        break;
                    case 'annually':
                        $regularityText .= " (Jährlich)";
                        break;
                }
            }
            echo "</div>";
            echo "<div class='entry-regular'>" . htmlspecialchars($regularityText) . "</div>";
        } else {
            // Normaler Eintrag
            $regularityText = "";
            switch ($row['recurring']) {
                case 'monthly':
                    $regularityText .= 'Monatlich';
                    break;
                case 'quarterly':
                    $regularityText .= 'Vierteljährlich';
                    break;
                case 'semiannually':
                    $regularityText .= 'Halbjährlich';
                    break;
                case 'annually':
                    $regularityText .= 'Jährlich';
                    break;
                default:
                    $regularityText .= '';
            }
            echo "<div class='entry-regular'>" . htmlspecialchars($regularityText) . "</div>";
        }

        // Enddatum bei wiederkehrenden Einträgen
        if ($row['recurring'] !== 'no') {
            if (!is_null($row['repeat_until_year']) && !is_null($row['repeat_until_month'])) {
                echo "<div class='entry-end'>Bis " 
                     . $monthNames[$row['repeat_until_month']] . " " 
                     . $row['repeat_until_year'] . "</div>";
            } else {
                echo "<div class='infinite-icon' title='Fortlaufend'><i class='fa-solid fa-infinity'></i></div>";
            }
        }

        // ID + Kategorie
        echo "<div class='entry-id'>";
            if (!empty($row['category'])) {
                echo "<span class='entry-category-id' data-category='" 
                     . htmlspecialchars($row['category']) 
                     . "' onclick='highlightCategory(this)'>" 
                     . htmlspecialchars($row['category']) . "</span> - ";
            }
            echo "ID: " . htmlspecialchars($row['id']);
        echo "</div>";

        // Aktionen (wenn kein 'Übertrag')
        if ($row['description'] !== $translations['carryover']) {
            echo "<div class='entry-actions'>";

                // Labels
                $deleteLabel                   = $translations['delete'];
                $hideLabel                     = $translations['hide'];
                $hide_rebookingLabel           = $translations['hide_rebooking'];
                $hide_single_rebookingLabel    = $translations['hide_single_rebooking'];
                $editLabel                     = $translations['edit'];
                $edit_rebookingLabel           = $translations['edit_rebooking'];
                $edit_single_rebookingLabel    = $translations['edit_single_rebooking'];
                $edit_single_entryLabel        = $translations['edit_single_entry'];
                $hide_single_entryLabel        = $translations['hide_single_entry'];
                $hide_editedLabel              = $translations['hide_edited_entry'];
                $edit_editedLabel              = $translations['edit_edited_entry'];
                $delete_savings_accountLabel   = $translations['delete_savings_account'];
                $edit_savings_accountLabel     = $translations['edit_savings_account'];

                // Löschen
                echo "<button class='delete-button'
                        title='" . htmlspecialchars($deleteLabel) . "'
                        data-id='" . $row['id'] . "'
                        data-table='" . htmlspecialchars($username) . "'>
                        <i class='fas fa-trash'></i>
                      </button>";

                // -------------------------------------------------------
                // Umbuchung oder normaler Eintrag / Override-Check
                // -------------------------------------------------------
                if ($row['rebooking_id'] > 0) {
                    // --> Umbuchung
                    if ($row['override'] == 1 && $row['hidden_override'] == 0) {
                        // ---------------------------------------------------------
                        // Bereits einzeln bearbeitete Umbuchung (Override=1)
                        // => Nur den Button für EINZEL-UMBUCHUNG zeigen,
                        //    damit es nicht fälschlich eine neue Override-Zeile anlegt
                        // ---------------------------------------------------------
                        echo "<button class='edit-single-rebooking-button'
                                title='" . htmlspecialchars($edit_editedLabel) . "'
                                data-id='" . $row['id'] . "'
                                data-type='" . $row['type'] . "'
                                data-edit-mode='single-rebooking'>
                                <i class='fas fa-edit'></i>
                              </button>";

                        // Falls wiederkehrend
                        // (Man kann hier optional checken, ob man überhaupt was zusätzlich anzeigen will)
                        // Hier reicht i.d.R. der single-rebooking-Button aus.
                        
                        // Hide-Button für Override
                        echo "<button class='hide-override-button'
                                title='" . htmlspecialchars($hide_editedLabel) . "'
                                data-id='" . $row['id'] . "'>
                                <i class='fas fa-eye-slash'></i>
                              </button>";

                    } else {
                        // ---------------------------------------------------------
                        // Normale Umbuchung (keine einzeln bearbeitete Override-Zeile)
                        // ---------------------------------------------------------
                        echo "<button class='rebooking-edit-button'
                                title='" . htmlspecialchars($edit_rebookingLabel) . "'
                                data-id='" . $row['id'] . "'
                                onclick='handleRebookingEdit(this)'>
                                <i class='fas fa-edit'></i>
                              </button>";

                        // Falls wiederkehrend?
                        if ($row['recurring'] != 'no') {
                            echo "<button class='edit-single-rebooking-button'
                                    title='" . htmlspecialchars($edit_single_rebookingLabel) . "'
                                    data-id='" . $row['id'] . "'
                                    data-type='" . $row['type'] . "'
                                    data-edit-mode='single-rebooking'>
                                    <i class='fas fa-edit'></i>
                                  </button>";
                            if ($row['hidden_override'] == 0) {
                                echo "<button class='hide-single-button'
                                        title='" . htmlspecialchars($hide_single_rebookingLabel) . "'
                                        data-id='" . $row['id'] . "'
                                        data-month='" . $month . "'
                                        data-year='" . $year . "'>
                                        <i class='fas fa-eye-slash'></i>
                                      </button>";
                            }
                        } else {
                            // Nicht wiederkehrend
                            echo "<button class='hide-button'
                                    title='" . htmlspecialchars($hide_rebookingLabel) . "'
                                    data-id='" . $row['id'] . "'>
                                    <i class='fas fa-eye-slash'></i>
                                  </button>";
                        }
                    }
                } else {
                    // --> Normaler Eintrag
                    if ($row['override'] == 1 && $row['hidden_override'] == 0) {
                        // Einzelbearbeitetes Serienelement (normal)
                        echo "<button class='edit-button'
                                title='" . htmlspecialchars($edit_editedLabel) . "'
                                data-id='" . $row['id'] . "'
                                data-type='" . $row['type'] . "'
                                data-edit-mode='series'>
                                <i class='fas fa-edit'></i>
                              </button>";

                        echo "<button class='hide-override-button'
                                title='" . htmlspecialchars($hide_editedLabel) . "'
                                data-id='" . $row['id'] . "'>
                                <i class='fas fa-eye-slash'></i>
                              </button>";
                    } else {
                        // Komplett normaler Eintrag
                        echo "<button class='edit-button'
                                title='" . htmlspecialchars($editLabel) . "'
                                data-id='" . $row['id'] . "'
                                data-type='" . $row['type'] . "'
                                data-edit-mode='series'>
                                <i class='fas fa-edit'></i>
                              </button>";

                        if ($row['recurring'] != 'no') {
                            echo "<button class='edit-single-button'
                                    title='" . htmlspecialchars($edit_single_entryLabel) . "'
                                    data-id='" . $row['id'] . "'
                                    data-type='" . $row['type'] . "'
                                    data-edit-mode='single'>
                                    <i class='fas fa-edit'></i>
                                  </button>";
                            if ($row['hidden_override'] == 0) {
                                echo "<button class='hide-single-button'
                                        title='" . htmlspecialchars($hide_single_entryLabel) . "'
                                        data-id='" . $row['id'] . "'
                                        data-month='" . $month . "'
                                        data-year='" . $year . "'>
                                        <i class='fas fa-eye-slash'></i>
                                      </button>";
                            }
                        } else {
                            echo "<button class='hide-button'
                                    title='" . htmlspecialchars($hideLabel) . "'
                                    data-id='" . $row['id'] . "'>
                                    <i class='fas fa-eye-slash'></i>
                                  </button>";
                        }
                    }
                }

            echo "</div>"; // Ende entry-actions
        }
    echo "</div>"; // Ende entry-content
    echo "</div>"; // Ende entry-box
}

// -----------------------------------------------------------
// 6) GESAMTSUMME FÜR DEN GEWÄHLTEN MONAT BERECHNEN
// -----------------------------------------------------------
$stmt = $conn->prepare("
    SELECT SUM(
        CASE WHEN type = 'income' THEN amount ELSE -amount END
    ) AS total
    FROM `$username`
    WHERE 
        (
            -- Einmalige Einträge
            (recurring = 'no' AND entry_month = ? AND entry_year = ?)
            
            -- Wiederkehrende Einträge
            OR (
                recurring != 'no'
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
                AND id NOT IN (
                    SELECT override_id 
                    FROM `$username`
                    WHERE override = 1 AND entry_month = ? AND entry_year = ?
                )
            )
            -- Overrides
            OR (
                override = 1 AND entry_month = ? AND entry_year = ?
            )
        )
");
$stmt->bind_param(
    "iiiiiiiiiiiii",
    $month, // 1
    $year,  // 2
    $month, // 3
    $year,  // 4
    $year,  // 5
    $month, // 6
    $year,  // 7
    $year,  // 8
    $month, // 9
    $month, // 10
    $year,  // 11
    $month, // 12
    $year   // 13
);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$totalFormatted = number_format((float)$total, 2, ',', '.');
?>












<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Einfaches JS zum Abfangen der Klicks auf die Move-Buttons
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.btn-move');
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            const direction = this.getAttribute('data-direction');
            const entryId = this.getAttribute('data-id');

            // AJAX-Request an update_position.php
            // Wir schicken ID und Richtung ("up" oder "down") per POST
            fetch('update_position.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    'id': entryId,
                    'direction': direction
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Bei Erfolg Seite aktualisieren (oder AJAX neu laden)
                    window.location.reload();
                } else {
                    alert(data.message || 'Fehler beim Verschieben');
                }
            })
            .catch(err => {
                console.error(err);
                alert('AJAX-Fehler beim Verschieben');
            });
        });
    });
});
</script>





<script>
// Event-Listener für den neuen Hilfebutton, der die Beschriftungen toggelt
document.getElementById('toggle-help').addEventListener('click', function(e) {
    e.stopPropagation(); // Verhindert, dass andere Event-Listener (z.B. am übergeordneten Container) fälschlicherweise reagieren
    var fabOptions = document.getElementById('fab-options');
    fabOptions.classList.toggle('show-labels');
});
</script>

<!--Scroll to top Button-->

<div class="scroll-to-top" id="scroll-to-top">
    <i class="fas fa-arrow-up"></i>
</div>

<!--Anzeige des Kontostands -->

        <div class="total-box" style="text-align: left;">
<?php $currency = getenv('CURRENCY') ?: '€'; ?>
<strong><?php echo $translations['balance_title'] ?? 'Balance:'; ?></strong> 
<span class="currency"><?= $totalFormatted ?> <?= $currency ?></span>
        </div>

<?php
//include 'dbconnection.php';

// Prüfen, ob Benutzer eingeloggt
//if (!isset($_SESSION['username'])) {
//    die("Fehler: Kein Benutzer eingeloggt.");
//}

//$username = $_SESSION['username'];

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
 * 1) Hilfsfunktionen: Differenz in Monaten & "Monat+1"
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
 * 2) FRÜHESTEN MONAT in der Haupt-Tabelle `$username` suchen
 *
 *    So finden wir den "kleinsten" Monat/Jahr, ab dem wir neu rechnen müssen.
 */
function getEarliestMonthYearInMainTable($conn, $username) {
    // Sucht den **kleinsten** (entry_year, entry_month) in `$username`
    // Falls leer, nimm den aktuellen Systemmonat/-jahr
    $sql = "
        SELECT entry_year, entry_month
        FROM `$username`
        WHERE entry_year > 0 AND entry_month > 0
        ORDER BY entry_year ASC, entry_month ASC
        LIMIT 1
    ";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return [
            'month' => (int)$row['entry_month'],
            'year'  => (int)$row['entry_year']
        ];
    } else {
        // Falls es gar keine Einträge gibt, nimm den aktuellen
        return [
            'month' => (int)date('n'),
            'year'  => (int)date('Y')
        ];
    }
}

/**
 * 3) Kontostand für EINEN Monat (m, y) berechnen und in $balanceTable speichern.
 *    Deine Logik "override=1 oder override=0, recurring, etc."
 */
function computeAndStoreMonthBalance($conn, $username, $balanceTable, $m, $y) {
    // Monats-Summe aus `$username` holen
    $stmt = $conn->prepare("
        SELECT SUM(
            CASE WHEN type = 'income' THEN amount ELSE -amount END
        ) AS total
        FROM `$username`
        WHERE 
        (
            (override = 1 AND entry_month = ? AND entry_year = ?)
            OR (
                override = 0
                AND (
                    (recurring = 'no' AND entry_month = ? AND entry_year = ?)
                    OR (
                        recurring != 'no'
                        AND id NOT IN (
                            SELECT override_id 
                            FROM `$username` 
                            WHERE override = 1 AND entry_month = ? AND entry_year = ?
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
        )
    ");
    // 13 Platzhalter
    $stmt->bind_param(
        "iiiiiiiiiiiii",
        $m, $y,  
        $m, $y,  
        $m, $y,  
        $m,        
        $y, $y,    
        $m,        
        $y, $y,    
        $m         
    );
    $stmt->execute();
    $stmt->bind_result($currentBalance);
    $stmt->fetch();
    $stmt->close();

    // In der Bilanz-Tabelle speichern
    $stmt = $conn->prepare("
        INSERT INTO `$balanceTable` (entry_month, entry_year, balance)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE balance = ?
    ");
    // 2 x double => "d"
    $stmt->bind_param("iidd", $m, $y, $currentBalance, $currentBalance);
    $stmt->execute();
    $stmt->close();
}

/**
 * 4) Diese Funktion berechnet für ALLE Monate von (startM, startY) bis (endM, endY)
 *    den Kontostand und speichert ihn in `$balanceTable`.
 */
function fillMissingBalancesUpTo($conn, $username, $balanceTable, $startM, $startY, $endM, $endY) {
    $diff = getMonthDifferenceBalance($startM, $startY, $endM, $endY);
    if ($diff < 0) {
        return;
    }

    $tempM = $startM;
    $tempY = $startY;

    for ($i = 0; $i <= $diff; $i++) {
        computeAndStoreMonthBalance($conn, $username, $balanceTable, $tempM, $tempY);
        incrementMonthYearBalance($tempM, $tempY);
    }
}

// ---------------------------------------------------
// 1) Stets vom FRÜHESTEN in `$username` vorhandenen Monat neu berechnen
// ---------------------------------------------------
$earliest = getEarliestMonthYearInMainTable($conn, $username);
$earliestMonth = $earliest['month'];
$earliestYear  = $earliest['year'];

// ---------------------------------------------------
// 2) ALLE Monate von $earliestMonth/$earliestYear bis $currentMonth/$currentYear füllen
// ---------------------------------------------------
fillMissingBalancesUpTo(
    $conn,
    $username,
    $balanceTable,
    $earliestMonth,
    $earliestYear,
    $currentMonth,
    $currentYear
);

// ---------------------------------------------------
// 3) Kontostand "aktueller" Monat + Vormonat
// ---------------------------------------------------
$previousMonth = ($currentMonth === 1) ? 12 : $currentMonth - 1;
$previousYear  = ($currentMonth === 1) ? $currentYear - 1 : $currentYear;

// Kontostand Vormonat
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

// Kontostand aktueller Monat
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

// Bilanz
$previousBalance = $previousBalance ?? 0.0;
$currentBalance  = $currentBalance  ?? 0.0;

$balanceDifference = $currentBalance - $previousBalance;
$balanceClass = ($balanceDifference >= 0) ? 'positive-balance' : 'negative-balance';

// Anzeige
echo "<div class='total-box $balanceClass' style='text-align: left;'>";
echo $translations['record_title'] ?? 'Record to previous month:';
//$currency = getenv('CURRENCY') ?: '€';
echo "<span>" . number_format($balanceDifference, 2, ',', '.') . " " . $currency . "</span>";
echo "</div>";


//include 'dbconnection.php';

// Angemeldeten Benutzernamen abrufen
//$username = $_SESSION['username'];

// Zielmonat/-jahr, in den/ das der Nutzer springt
$targetMonth = intval($_GET['month'] ?? date('n'));
$targetYear  = intval($_GET['year'] ?? date('Y'));

// -------------------------------
// 1) Vorbereitungen & Funktionen
// -------------------------------

// Falls du nicht immer vom real aktuellen Monat starten willst,
// kannst du die folgenden Werte anpassen (oder aus deiner Session holen).
$startMonth = (int)date('n');
$startYear  = (int)date('Y');

// FUNKTION 1: Differenz in vollen Monaten
function getMonthDifferenceSavings($fromMonth, $fromYear, $toMonth, $toYear) {
    return ($toYear - $fromYear) * 12 + ($toMonth - $fromMonth);
}

// FUNKTION 2: Erhöht (m, y) um 1 Monat, inkl. Jahreswechsel
function incrementMonthYearSavings(&$m, &$y) {
    $m++;
    if ($m > 12) {
        $m = 1;
        $y++;
    }
}

// FUNKTION 3: Für EINEN bestimmten Monat ($m, $y) den Saldo berechnen 
// und diesen als Carryover in den Folgemonat eintragen (oder updaten).
function calculateAndInsertCarryoverForSavings($pdo, $table, $m, $y, $translations) {
    // 1) Saldo für $m/$y berechnen (nach deiner Override-/Recurring-Logik)
    $carryStmt = $pdo->prepare("
        SELECT SUM(
            CASE 
                WHEN override = 1 THEN 
                    CASE WHEN type = 'income' THEN amount ELSE -amount END
                WHEN override = 0 
                     AND description NOT IN (
                         SELECT description
                         FROM `$table`
                         WHERE override = 1
                           AND entry_month = :m
                           AND entry_year   = :y
                     )
                THEN 
                    CASE WHEN type = 'income' THEN amount ELSE -amount END
                ELSE 0
            END
        ) AS total
        FROM `$table`
        WHERE 
            (
                (override IN (0,1))
                AND recurring = 'no' 
                AND entry_month = :m 
                AND entry_year  = :y
            )
            OR
            (
                (override IN (0,1))
                AND recurring != 'no'
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
        ");
    $carryStmt->execute([
        'm' => $m,
        'y' => $y,
    ]);
    $carryResult = $carryStmt->fetch(PDO::FETCH_ASSOC);
    $carrySum = $carryResult['total'] ?? 0.0;

    // 2) Folgemonat bestimmen
    $nextM = $m; 
    $nextY = $y;
    incrementMonthYearSavings($nextM, $nextY);

    // 3) Carryover-Eintrag in $nextM/$nextY
    $carryoverDescription = $translations['carryover'] ?? 'Übertrag vom letzten Monat';

    $checkStmt = $pdo->prepare("
        SELECT id 
        FROM `$table`
        WHERE entry_month = :nm
          AND entry_year  = :ny
          AND description = :descr
    ");
    $checkStmt->execute([
        'nm'    => $nextM,
        'ny'    => $nextY,
        'descr' => $carryoverDescription,
    ]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // Positiv = 'income', Negativ = 'expense'
    $carryType   = ($carrySum >= 0) ? 'income' : 'expense';
    $carryAmount = abs($carrySum);

    if ($existing) {
        // Aktualisieren
        $updateStmt = $pdo->prepare("
            UPDATE `$table`
            SET amount = :amount,
                type   = :type
            WHERE id = :id
        ");
        $updateStmt->execute([
            'amount' => $carryAmount,
            'type'   => $carryType,
            'id'     => $existing['id'],
        ]);
    } else {
        // Neu einfügen
        $insertStmt = $pdo->prepare("
            INSERT INTO `$table`
            (type, amount, description, recurring, created_at, entry_month, entry_year)
            VALUES (:type, :amount, :descr, 'no', NOW(), :nm, :ny)
        ");
        $insertStmt->execute([
            'type'   => $carryType,
            'amount' => $carryAmount,
            'descr'  => $carryoverDescription,
            'nm'     => $nextM,
            'ny'     => $nextY,
        ]);
    }
}


// --------------------------------------
// 2) Alle Sparkonten-Tabellen abarbeiten
// --------------------------------------

try {
    global $pdo;

    // Tabellen, die dem Muster "username_*" entsprechen
    $stmt = $pdo->prepare("SHOW TABLES LIKE :pattern");
    $stmt->execute(['pattern' => "{$username}_%"]);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Prüfen, ob wir in die Zukunft springen
    $monthDiff = getMonthDifferenceSavings($startMonth, $startYear, $targetMonth, $targetYear);

    // Falls der Zielmonat in der Zukunft liegt, Monat für Monat Carryover bilden
    if ($monthDiff > 0) {
        $tmpM = $startMonth;
        $tmpY = $startYear;

        while (true) {
            // Für jede Sparkonto-Tabelle den Carryover ermitteln
            foreach ($tables as $table) {
                calculateAndInsertCarryoverForSavings($pdo, $table, $tmpM, $tmpY, $translations);
            }

            incrementMonthYearSavings($tmpM, $tmpY);

            if ($tmpM == $targetMonth && $tmpY == $targetYear) {
                break;
            }
        }
    }

    // ---------------------------
    // 3) Jetzt im Zielmonat Zinsen & Summen berechnen
    // ---------------------------

    function addOrUpdateMonthlyInterestSavings(PDO $pdo, $tableName, $m, $y) {
        try {
            // Zinssatz abrufen
            $interestStmt = $pdo->prepare("
                SELECT interest_rate 
                FROM savings_interest_rates 
                WHERE savings_name = :savings_name
            ");
            $interestStmt->execute(['savings_name' => $tableName]);
            $interestResult = $interestStmt->fetch(PDO::FETCH_ASSOC);
            $interestRate   = $interestResult['interest_rate'] ?? 0;

            // Kontostand für ZIEL-Monat berechnen
            $balanceStmt = $pdo->prepare("
                SELECT SUM(
                    CASE WHEN type='income' THEN amount ELSE -amount END
                ) AS total
                FROM `$tableName`
                WHERE entry_month = :m
                  AND entry_year  = :y
            ");
            $balanceStmt->execute([
                'm' => $m,
                'y' => $y,
            ]);
            $balanceResult  = $balanceStmt->fetch(PDO::FETCH_ASSOC);
            $currentBalance = $balanceResult['total'] ?? 0.0;

            // Monatszins
            $interestAmount = round($currentBalance * ($interestRate / 100) / 12, 2);

            // Eintrag checken
            $interestDescription = "Zinsen für $m/$y";
            $checkStmt = $pdo->prepare("
                SELECT id 
                FROM `$tableName`
                WHERE description = :descr
                  AND entry_month = :m
                  AND entry_year  = :y
            ");
            $checkStmt->execute([
                'descr' => $interestDescription,
                'm'     => $m,
                'y'     => $y,
            ]);
            $found = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($found) {
                // Update
                $updateStmt = $pdo->prepare("
                    UPDATE `$tableName`
                    SET amount = :amt
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    'amt' => $interestAmount,
                    'id'  => $found['id'],
                ]);
            } else {
                // Insert
                $insertStmt = $pdo->prepare("
                    INSERT INTO `$tableName`
                    (type, amount, description, entry_month, entry_year, created_at)
                    VALUES ('income', :amt, :descr, :m, :y, NOW())
                ");
                $insertStmt->execute([
                    'amt'   => $interestAmount,
                    'descr' => $interestDescription,
                    'm'     => $m,
                    'y'     => $y,
                ]);
            }
        } catch (PDOException $e) {
            die("Fehler bei der Zinsberechnung: " . $e->getMessage());
        }
    }

    $savings = [];

    // Jetzt für den Zielmonat Zinsen eintragen und Summen berechnen
    foreach ($tables as $table) {
        // 1) Zinsen hinzufügen/aktualisieren
        addOrUpdateMonthlyInterestSavings($pdo, $table, $targetMonth, $targetYear);

        // 2) Zinssatz wieder abrufen (falls du ihn mit anzeigen möchtest)
        $interestStmt = $pdo->prepare("
            SELECT interest_rate 
            FROM savings_interest_rates 
            WHERE savings_name = :savings_name
        ");
        $interestStmt->execute(['savings_name' => $table]);
        $interestResult = $interestStmt->fetch(PDO::FETCH_ASSOC);
        $interestRate   = $interestResult['interest_rate'] ?? 0;

        // 3) Gesamtsumme für den Zielmonat berechnen
        $sumStmt = $pdo->prepare("
            SELECT 
                SUM(
                    CASE 
                        WHEN override = 1 THEN 
                            CASE WHEN type = 'income' THEN amount ELSE -amount END
                        WHEN override = 0 
                             AND description NOT IN (
                                 SELECT description
                                 FROM `$table`
                                 WHERE override = 1 
                                   AND entry_month = :m
                                   AND entry_year  = :y
                             )
                        THEN 
                            CASE WHEN type = 'income' THEN amount ELSE -amount END
                        ELSE 0 
                    END
                ) AS total
            FROM `$table`
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
        $sumStmt->execute([
            'm' => $targetMonth,
            'y' => $targetYear,
        ]);
        $res   = $sumStmt->fetch(PDO::FETCH_ASSOC);
        $total = $res['total'] ?? 0;

        // Sparkontonamen ohne "{$username}_"
        $savingsName = str_replace("{$username}_", '', $table);

        // Daten sammeln
        $savings[] = [
            'name'          => $savingsName,
            'total'         => $total,
            'interest_rate' => $interestRate,
        ];
    }

    // Ab hier kannst du das Array $savings ausgeben oder weiterverarbeiten.

} catch (PDOException $e) {
    die("Fehler bei der Abfrage: " . $e->getMessage());
}
?>

<!-- Anzeige der Sparkonten -->
<h3><?php echo $translations['savings_title']?></h3>

<?php foreach ($savings as $saving): ?>
    <div class="total-box" style="display: flex; align-items: center; margin-bottom: 10px;">
        <!-- Löschen-Button -->
        <button class="savings-delete-button" 
                title="Sparkonto löschen" 
                onclick="confirmDeleteSavings('<?= htmlspecialchars($saving['name']); ?>')" 
                style="margin-right: 10px;">
            <i class="fas fa-trash"></i>
        </button>

        <!-- Bearbeiten-Button -->
        <button class="savings-edit-button" 
                title="Sparkonto bearbeiten" 
                onclick="openEditSavingsOverlay('<?= htmlspecialchars($saving['name']); ?>', '<?= htmlspecialchars($saving['interest_rate']); ?>')"
                style="margin-right: 10px;">
            <i class="fas fa-edit"></i>
        </button>

        <!-- Verlauf-Button -->
        <button class="savings-history-button"
                title="Verlauf anzeigen"
                onclick="openSavingsHistoryOverlay('<?= htmlspecialchars($saving['name']); ?>')"
                style="margin-right: 10px;">
            <i class="fas fa-chart-line"></i>
        </button>

        <!-- Inhalt des Sparkontos -->
        <div style="flex-grow: 1;">
            <!-- Name des Sparkontos -->
            <strong><?= htmlspecialchars($saving['name']) ?>:</strong>
            <div>
                <!-- Betrag mit Zinsen -->
                <span class="<?= $saving['total'] < 0 ? 'negative' : '' ?>">
                   <?php $currency = getenv('CURRENCY') ?: '€'; ?>
                    <?= number_format($saving['total'], 2, ',', '.') ?> <?= $currency ?>
                </span>
            </div>
            <!-- Zinssatz -->
            <div class="savings-interest-rate">
                <?php echo $translations['interest_rate']?>: <?= number_format($saving['interest_rate'], 2, ',', '.') ?> %
            </div>
        </div>
    </div>
<?php endforeach; ?>


<!-- Overlay für den Verlauf -->
<div id="savingsHistoryOverlay" class="overlay" style="display: none;">
    <div class="overlay-content">
        <span class="close-button" onclick="closeSavingsHistoryOverlay()">&times;</span>

        <h2>Verlauf Sparkonto: <span id="overlaySavingsName"></span></h2>

        <!-- Formular zur Auswahl des Zeitraums -->
        <div>
            <label for="historyStart">Start:</label>
            <input type="month" id="historyStart" />
            <label for="historyEnd">Ende:</label>
            <input type="month" id="historyEnd" />
            <button id="updateHistoryRangeButton">Aktualisieren</button>
        </div>

        <!-- Canvas für Chart.js -->
        <canvas id="savingsHistoryChart" width="400" height="200"></canvas>
    </div>
</div>

<style>
    /* Einfaches Beispiel für Overlay-Design */
    .overlay {
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0,0,0,0.7); 
        z-index: 9999;
    }
    .overlay-content {
        background: #fff; 
        width: 80%; 
        margin: 50px auto; 
        padding: 20px; 
        position: relative;
    }
    .close-button {
        position: absolute; 
        top: 10px; 
        right: 20px; 
        font-size: 2em; 
        cursor: pointer;
    }
</style>


<!-- Bearbeitungs-Overlay Sparkonten -->

<div class="overlay" id="edit-savings-overlay" style="display: none;">
    <div class="modal">
        <div class="close-button" onclick="closeEditSavingsOverlay()">×</div>
        <h2><?php echo $translations['edit_savings_account']?></h2>
        <form id="edit-savings-form" method="POST">
            <label for="edit-savings-name"><?php echo $translations['savings_account_name']?></label>
            <input type="text" id="edit-savings-name" name="edit-savings-name" required>

            <label for="edit-interest-rate"><?php echo $translations['interest_rate']?>(%)</label>
            <input type="number" id="edit-interest-rate" name="edit-interest-rate" required step="0.01" min="0" max="100">

            <button type="submit" id="edit-savings-submit-button"><?php echo $translations['save']?></button>
        </form>
    </div>
</div>


<script>
function openEditSavingsOverlay(name, interestRate) {
    const overlay = document.getElementById('edit-savings-overlay');
    const nameInput = document.getElementById('edit-savings-name');
    const rateInput = document.getElementById('edit-interest-rate');

    if (!overlay || !nameInput || !rateInput) {
        console.error('Ein oder mehrere Elemente wurden nicht gefunden.');
        return;
    }

    nameInput.value = name; // Name setzen
    rateInput.value = interestRate; // Zinssatz setzen
    overlay.style.display = 'flex'; // Overlay anzeigen
}

function closeEditSavingsOverlay() {
    const overlay = document.getElementById('edit-savings-overlay');
    if (overlay) {
        overlay.style.display = 'none'; // Overlay ausblenden
    }
}

document.getElementById('edit-savings-form').addEventListener('submit', function(event) {
    event.preventDefault();

    const savingsName = document.getElementById('edit-savings-name').value.trim();
    const interestRate = parseFloat(document.getElementById('edit-interest-rate').value);

    if (!savingsName || isNaN(interestRate) || interestRate < 0 || interestRate > 100) {
        alert('Bitte geben Sie einen gültigen Namen und Zinssatz ein.');
        return;
    }

    fetch('/editSavings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ savingsName, interestRate }),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Fehler beim Speichern der Änderungen.');
        }
        return response.json();
    })
    .then(data => {
        alert(data.message);
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        alert('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.');
    });
});
</script>

<script>
function confirmDeleteSavings(savingsName) {
    if (confirm(`Möchtest Du das Sparkonto "${savingsName}" wirklich löschen?`)) {
        fetch('delete_savings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ savingsName })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Sparkonto erfolgreich gelöscht.');
                window.location.reload(); // Seite aktualisieren
            } else {
                alert('Fehler beim Löschen des Sparkontos: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Fehler beim Löschen des Sparkontos:', error);
            alert('Ein Fehler ist aufgetreten.');
        });
    }
}

// Entferne eventuelle globale Event-Handler auf .delete-button
document.querySelectorAll('.savings-delete-button').forEach(button => {
    button.addEventListener('click', event => {
        event.stopPropagation(); // Verhindert Ausführung globaler Handler
    });
});

</script>

</div>

<!-- Overlays-->
<!-- Overlay für Sparkonto -->
<div class="overlay" id="savings-overlay" style="display: none;">
    <div class="modal">
        <div class="close-button" onclick="closeSavingsOverlay()">&times;</div>
        <h2><?php echo $translations['create_savingsaccount']?></h2>
        <form id="savings-form" method="POST" action="/createSavingsAccount.php">
            <label for="savings-name"><?php echo $translations['name_savingsaccount']?></label>
            <input type="text" id="savings-name" name="savings-name" required>

<label for="initial-balance"><?php echo $translations['balance_savingsaccount'] ?></label>
<input type="text" id="initial-balance" name="initial-balance" required
       pattern="^\d+([.,]\d{1,2})?$"
       title="Bitte gib eine Zahl mit maximal zwei Nachkommastellen ein."
       inputmode="decimal">


            <label for="interest-rate"><?php echo $translations['interest_savingsaccount']?></label>
            <input type="number" id="interest-rate" name="interestRate" required step="0.01" min="0" max="100" pattern="^\\d+(\\.\\d{1,2})?$" title="Bitte gib einen Zinssatz mit maximal zwei Nachkommastellen ein.">

            <button type="submit" id="savings-submit-button"><?php echo $translations['create']?></button>
        </form>
    </div>
</div>

<script>
  // Beispiel: Sprachwert dynamisch setzen (z.B. aus PHP)
  // 'de' für Deutsch, 'en' für Englisch
  var language = 'de'; // Dynamisch z.B. via: var language = '<?php echo $language; ?>';
  var decimalSeparator = language === 'de' ? ',' : '.';

  const initialBalanceInput = document.getElementById('initial-balance');

  initialBalanceInput.addEventListener('input', function() {
      let value = this.value;
      
      // Erlaubt nur Ziffern, Komma und Punkt
      value = value.replace(/[^0-9.,]/g, '');
      
      // Unerwünschte Dezimaltrenner in den korrekten umwandeln:
      if (language === 'de') {
          // In Deutsch: Alle Punkte durch Komma ersetzen
          value = value.replace(/\./g, ',');
      } else {
          // In Englisch: Alle Kommata durch Punkt ersetzen
          value = value.replace(/,/g, '.');
      }
      
      // Falls der Wert direkt mit dem Dezimaltrenner beginnt, wird eine führende Null eingefügt
      if (value.startsWith(decimalSeparator)) {
          value = '0' + value;
      }
      
      // Stellt sicher, dass nur ein Dezimaltrenner vorhanden ist
      const firstSeparatorIndex = value.indexOf(decimalSeparator);
      if (firstSeparatorIndex !== -1) {
          let before = value.slice(0, firstSeparatorIndex + 1);
          let after = value.slice(firstSeparatorIndex + 1).replace(new RegExp('\\' + decimalSeparator, 'g'), '');
          value = before + after;
      }
      
      // Begrenzt die Nachkommastellen auf maximal 2
      if (value.indexOf(decimalSeparator) !== -1) {
          const parts = value.split(decimalSeparator);
          parts[1] = parts[1].substring(0, 2);
          value = parts[0] + decimalSeparator + parts[1];
      }
      
      this.value = value;
  });
</script>


<?php
//include 'dbconnection.php'; // Verbindung zur Datenbank

// Benutzername aus der Session abrufen
if (!isset($_SESSION['username'])) {
    echo "Fehler: Kein Benutzer eingeloggt.";
    exit;
}

//$username = $_SESSION['username'];

// Funktion zum Laden der Kategorien
function getUserCategories($username, $conn) {
    $categories = [];

    // Escape den Benutzernamen, um SQL-Injection zu verhindern
    $tableName = mysqli_real_escape_string($conn, $username);

    // Abfrage, um Kategorien zu laden
    $sql = "SELECT DISTINCT category FROM `$tableName` WHERE category IS NOT NULL";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row['category'];
        }
    } else {
        error_log("Fehler beim Abrufen der Kategorien: " . mysqli_error($conn));
    }

    return $categories;
}

// Kategorien laden
$categories = getUserCategories($username, $conn);

// Fallback, falls keine Kategorien gefunden wurden
if (empty($categories)) {
    $categories = [];
}

if (!$result) {
    error_log("SQL Fehler: " . mysqli_error($conn));
}
?>

<!-- Overlay für Bewegungen -->
<div class="overlay" id="overlay" style="display: none;">
    <div class="modal">
        <div class="close-button" onclick="closeOverlay()">×</div>
        <h2 id="overlay-title"><?php echo $translations['add_entry']?></h2>
        <form id="overlay-form" method="POST" action="add_entry.php">
            <input type="hidden" id="edit-id" name="id"> <!-- Verstecktes Feld für die ID beim Bearbeiten -->

            <label><?php echo $translations['type']?></label>
            <div class="entry-type-selection">
<button
    type="button"
    id="entry-type-income-btn"
    class="entry-type-btn entry-type-income inactive"
    onclick="setEntryType('income')">
    ➕
    <?php echo $translations['add_income'] ?? 'Einnahme hinzufügen' ?>
</button>

<button
    type="button"
    id="entry-type-expense-btn"
    class="entry-type-btn entry-type-expense active"
    onclick="setEntryType('expense')">
    ➖
    <?php echo $translations['add_expense'] ?? 'Ausgabe hinzufügen' ?>
</button>

            </div>
            <input type="hidden" id="entry-type" name="type" value="expense"> <!-- Verstecktes Feld zur Speicherung -->

            <label for="amount"><?php echo $translations['amount']?></label>
<!-- Beispiel: input type="text" mit passendem inputmode und pattern -->
<input type="text" id="amount" name="amount" required 
       pattern="^\d+([.,]\d{1,2})?$" 
       title="Bitte geben Sie eine Zahl mit maximal zwei Nachkommastellen ein." 
       inputmode="decimal">


            <label for="description"><?php echo $translations['description'] ?></label>
            <input type="text" id="description" name="description" required>

            <label for="recurring"><?php echo $translations['regularity'] ?></label>
            <select id="recurring" name="recurring" required onchange="toggleRepeatUntilFields(this.value)">
                <option value="no">Nein</option>
                <option value="monthly">Monatlich</option>
                <option value="quarterly">Vierteljährig</option>
                <option value="semiannually">Halbjährig</option>
                <option value="annually">Jährlich</option>
            </select>

            <div id="repeat-until-fields" style="display: none; text-align: left;">
                <label for="repeat_until_month"><?php echo $translations['repeat_until_month'] ?></label>
                <select id="repeat_until_month" name="repeat_until_month">
                    <option value="">Unendlich</option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?= $num ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <br><br>
                <label for="repeat_until_year"><?php echo $translations['repeat_until_year'] ?></label>
                <input type="number" id="repeat_until_year" name="repeat_until_year" placeholder='<?php echo $translations['like_2025']?>'>
            </div>

            <!-- Neues Datumsfeld für den tagesgenauen Buchungseintrag -->
            <label for="booking_date"><?php echo $translations['booking_date'] ?? 'Buchungsdatum' ?></label>
<center>            <input type="date" id="booking_date" name="booking_date" required> </center>

            <label for="category"><?php echo $translations['category'] ?></label>
            <input list="categories" id="category" name="category" placeholder='<?php echo $translations['choose_category']?>''>
            <datalist id="categories">
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <button type="submit" id="overlay-submit-button"><?php echo $translations['save'] ?></button>
        </form>
    </div>
</div>

<script>
  // Beispiel: Sprachwert wird über PHP oder Umgebungsvariablen gesetzt
  var language = 'de'; // z. B. 'de' oder 'en'
  var decimalSeparator = language === 'de' ? ',' : '.';
  const amountInput = document.getElementById('amount');

  amountInput.addEventListener('input', function() {
      let value = this.value;
      
      // Erlaubt nur Ziffern, Punkte und Kommata
      value = value.replace(/[^0-9.,]/g, '');
      
      // Konvertiere alle unerwünschten Dezimaltrenner in den korrekten
      if (language === 'de') {
          // Ersetze jeden Punkt durch ein Komma
          value = value.replace(/\./g, ',');
      } else {
          // Für Englisch: Ersetze jedes Komma durch einen Punkt
          value = value.replace(/,/g, '.');
      }
      
      // Falls der Wert direkt mit dem Dezimaltrenner beginnt, wird eine führende Null eingefügt
      if (value.startsWith(decimalSeparator)) {
          value = '0' + value;
      }
      
      // Stellt sicher, dass nur ein Dezimaltrenner vorhanden ist
      const firstSeparatorIndex = value.indexOf(decimalSeparator);
      if (firstSeparatorIndex !== -1) {
          let before = value.slice(0, firstSeparatorIndex + 1);
          let after = value.slice(firstSeparatorIndex + 1).replace(new RegExp('\\' + decimalSeparator, 'g'), '');
          value = before + after;
      }
      
      // Begrenzt die Nachkommastellen auf maximal 2
      if (value.indexOf(decimalSeparator) !== -1) {
          const parts = value.split(decimalSeparator);
          parts[1] = parts[1].substring(0, 2);
          value = parts[0] + decimalSeparator + parts[1];
      }
      
      this.value = value;
  });
</script>



<style>
/* Container für die Auswahl */
.entry-type-selection {
    display: flex;
    margin-bottom: 10px;
}

/* Basis-Button-Stil */
.entry-type-btn {
    flex: 1;
    padding: 12px;
    border: 2px solid transparent;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: background 0.3s, border 0.3s, opacity 0.3s;
}

/* Einnahme Button */
.entry-type-income {
    background-color: #28a745 !important; /* Dunkelgrün */
    color: white !important;
    border-radius: 10px 0 0 10px;
}

/* Ausgabe Button */
.entry-type-expense {
    background-color: #dc3545 !important; /* Dunkelrot */
    color: white !important;
    border-radius: 0 10px 10px 0;
}

/* Light Mode: Helleres Grün & Rot mit schwarzer Schrift */
@media (prefers-color-scheme: light) {
    .entry-type-income {
        background-color: #a8e6a2 !important; /* Hellgrün */
        color: black !important;
    }

    .entry-type-expense {
        background-color: #f8a5a5 !important; /* Hellrot */
        color: black !important;
    }
}

/* Hover-Effekte */
.entry-type-btn:hover {
    filter: brightness(90%) !important;
}

/* Aktiver Button */
.entry-type-btn.active {
    border: 2px solid black !important;
    box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2) !important;
    opacity: 1 !important; /* Voll sichtbar */
}

/* Inaktiver Button (ausgegraut) */
.entry-type-btn.inactive {
    opacity: 0.5 !important;
    //cursor: not-allowed !important;
    filter: grayscale(50%) !important;
}
</style>

<script>
function setEntryType(type) {
    let typeInput = document.getElementById("entry-type");
    let incomeBtn = document.getElementById("entry-type-income-btn");
    let expenseBtn = document.getElementById("entry-type-expense-btn");

    // Sicherstellen, dass die Elemente existieren
    if (!typeInput || !incomeBtn || !expenseBtn) {
        console.error("Fehler: Elemente für Ein-/Ausgabe-Buttons nicht gefunden.");
        return;
    }

    typeInput.value = type;

    // Zuerst alle Buttons zurücksetzen
    incomeBtn.classList.remove("active", "inactive");
    expenseBtn.classList.remove("active", "inactive");

    // Aktiven Button hervorheben, den anderen ausgrauen
    if (type === "income") {
        incomeBtn.classList.add("active");
        expenseBtn.classList.add("inactive");
    } else {
        expenseBtn.classList.add("active");
        incomeBtn.classList.add("inactive");
    }
}
</script>


<script>
function openOverlay() {
    document.getElementById("overlay").style.display = "flex";
}

function closeOverlay() {
    document.getElementById("overlay").style.display = "none";
}
</script>


<!--Overlay für Umbuchungen-->
<div class="overlay" id="rebooking-overlay" style="display: none;">
    <div class="modal">
        <div class="close-button" onclick="closeRebookingOverlay()">×</div>
        <h2><?php echo $translations['add_rebooking']?></h2>
        <form id="rebooking-form" method="POST" action="add_rebooking.php">
            <!-- Ursprungskonto -->
            <label for="source_account"><?php echo $translations['source'] ?? 'Source account:'; ?></label>
            <select id="source_account" name="source_account" required>
                <option value="main"><?php echo $translations['main']?></option>
                <?php
                // Dynamische Liste der Sparkonten
                $username = $_SESSION['username'];
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$username . "_%"]);
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($tables as $table) {
                    $accountName = str_replace($username . "_", '', $table);
                    echo "<option value=\"$accountName\">" . htmlspecialchars($accountName) . "</option>";
                }
                ?>
            </select>

            <!-- Zielkonto -->
            <label for="target_account"><?php echo $translations['target'] ?></label>
            <select id="target_account" name="target_account" required>
                <option value="main"><?php echo $translations['main']?></option>
                <?php
                foreach ($tables as $table) {
                    $accountName = str_replace($username . "_", '', $table);
                    echo "<option value=\"$accountName\">" . htmlspecialchars($accountName) . "</option>";
                }
                ?>
            </select>

            <!-- Wiederholungsoption -->
            <label for="recurring-rebooking"><?php echo $translations['regularity'] ?? 'Regular?'; ?></label>
            <select id="recurring-rebooking" name="recurring" required onchange="toggleRepeatUntilFields(this.value, 'rebooking-overlay')">
                <option value="no">Nein</option>
                <option value="monthly">Monatlich</option>
                <option value="quarterly">Vierteljährig</option>
                <option value="semiannually">Halbjährig</option>
                <option value="annually">Jährlich</option>
            </select>

            <!-- Felder für Wiederholungsende -->
            <div class="repeat-until-fields" style="display: none; text-align: left;">
                <label for="repeat_until_month-rebooking"><?php echo $translations['repeat_until_month'] ?? 'Repeat until month:'; ?></label>
                <select id="repeat_until_month-rebooking" name="repeat_until_month">
                    <option value="">Unendlich</option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?= $num ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <br><br>
                <label for="repeat_until_year-rebooking"><?php echo $translations['repeat_until_year'] ?? 'Repeat until year:'; ?></label>
                <input type="number" id="repeat_until_year-rebooking" name="repeat_until_year" placeholder="z.B. 2025">
            </div>

            <!-- Buchungszeitraum -->
            <label for="entry_month-rebooking"><?php echo $translations['booking_month'] ?? 'Booking month:'; ?></label>
            <select id="entry_month-rebooking" name="entry_month" required>
                <?php foreach ($monthNames as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>

            <label for="entry_year-rebooking"><?php echo $translations['booking_year'] ?? 'Booking year:'; ?></label>
            <input type="number" id="entry_year-rebooking" name="entry_year" value="<?= $year ?>" required>

            <!-- Betrag im Umbuchungsoverlay -->
            <label for="amount-rebooking"><?php echo $translations['amount'] ?? 'Amount:'; ?></label>
            <input type="text" id="amount-rebooking" name="amount" required
                   pattern="^\d+([.,]\d{1,2})?$"
                   title="Bitte gib eine Zahl mit maximal zwei Nachkommastellen ein."
                   inputmode="decimal">

            <!-- Beschreibung -->
            <label for="description-rebooking"><?php echo $translations['description'] ?? 'Description:'; ?></label>
            <input type="text" id="description-rebooking" name="description" required>

            <button type="submit" id="rebooking-submit-button"><?php echo $translations['save'] ?? 'Save'; ?></button>
        </form>
    </div>
</div>

<script>
  // Beispiel: Der Sprachwert wird aus PHP oder Umgebungsvariablen übernommen
  // Setze hier 'de' (Deutsch) oder 'en' (Englisch)
  var language = 'de'; // Diesen Wert dynamisch via PHP setzen, falls gewünscht
  // Bestimme den Dezimaltrenner anhand der Sprache
  var decimalSeparator = language === 'de' ? ',' : '.';

  const amountRebookingInput = document.getElementById('amount-rebooking');

  amountRebookingInput.addEventListener('input', function() {
      let value = this.value;
      
      // Erlaubt nur Ziffern, Komma und Punkt
      value = value.replace(/[^0-9.,]/g, '');
      
      // Unerwünschte Dezimaltrenner in den korrekten umwandeln
      if (language === 'de') {
          // In Deutsch: Alle Punkte durch Komma ersetzen
          value = value.replace(/\./g, ',');
      } else {
          // In Englisch: Alle Kommata durch Punkt ersetzen
          value = value.replace(/,/g, '.');
      }
      
      // Falls der Wert direkt mit dem Dezimaltrenner beginnt, wird eine führende Null eingefügt
      if (value.startsWith(decimalSeparator)) {
          value = '0' + value;
      }
      
      // Stellt sicher, dass nur ein Dezimaltrenner vorhanden ist
      const firstSeparatorIndex = value.indexOf(decimalSeparator);
      if (firstSeparatorIndex !== -1) {
          let before = value.slice(0, firstSeparatorIndex + 1);
          let after = value.slice(firstSeparatorIndex + 1).replace(new RegExp('\\' + decimalSeparator, 'g'), '');
          value = before + after;
      }
      
      // Begrenzt die Nachkommastellen auf maximal 2
      if (value.indexOf(decimalSeparator) !== -1) {
          const parts = value.split(decimalSeparator);
          parts[1] = parts[1].substring(0, 2); // maximal zwei Nachkommastellen
          value = parts[0] + decimalSeparator + parts[1];
      }
      
      this.value = value;
  });

  // Beim Absenden des Formulars wird der Dezimaltrenner von Komma in Punkt umgewandelt,
  // damit der Server den Wert korrekt als Dezimalzahl interpretiert.
  const rebookingForm = document.getElementById('rebooking-form');
  rebookingForm.addEventListener('submit', function(e) {
      if(language === 'de'){
          amountRebookingInput.value = amountRebookingInput.value.replace(',', '.');
      }
  });
</script>




<!-- Overlay für Umbuchungen bearbeiten -->
<div class="overlay" id="edit-rebooking-overlay" style="display: none;">
    <div class="modal">
        <div class="close-button" onclick="closeEditRebookingOverlay()">×</div>
        <h2><?php echo $translations['edit_rebooking_title'] ?? 'Umbuchung bearbeiten'; ?></h2>
        <form id="edit-rebooking-form" method="POST" action="update_rebooking.php">
            <input type="hidden" id="edit-rebooking-id" name="id"> <!-- Verstecktes Feld für die ID -->

            <label for="edit-source-account"><?php echo $translations['source'] ?></label>
            <select id="edit-source-account" name="source_account" required>
                <option value="main">Hauptkonto</option>
                <?php
                foreach ($tables as $table) {
                    $accountName = str_replace($username . "_", '', $table);
                    echo "<option value=\"$accountName\">" . htmlspecialchars($accountName) . "</option>";
                }
                ?>
            </select>

            <label for="edit-target-account"><?php echo $translations['target'] ?></label>
            <select id="edit-target-account" name="target_account" required>
                <option value="main">Hauptkonto</option>
                <?php
                foreach ($tables as $table) {
                    $accountName = str_replace($username . "_", '', $table);
                    echo "<option value=\"$accountName\">" . htmlspecialchars($accountName) . "</option>";
                }
                ?>
            </select>

            <label for="edit-amount"><?php echo $translations['amount'] ?></label>
            <input 
                type="text" 
                id="edit-amount" 
                name="amount" 
                required
                pattern="^\d+([.,]\d{1,2})?$"
                title="Bitte gib eine Zahl mit maximal zwei Nachkommastellen ein."
                inputmode="decimal"
            >

            <label for="edit-description"><?php echo $translations['description']?></label>
            <input type="text" id="edit-description" name="description" required>

            <!-- Regelmäßig -->
            <label for="edit-recurring"><?php echo $translations['regularity'] ?></label>
            <select id="edit-recurring" name="recurring" required onchange="toggleRepeatUntilFieldsEdit(this.value)">
                <option value="no">Nein</option>
                <option value="monthly">Monatlich</option>
                <option value="quarterly">Vierteljährig</option>
                <option value="semiannually">Halbjährig</option>
                <option value="annually">Jährlich</option>
            </select>

            <!-- Felder für Wiederholen bis -->
            <div id="edit-repeat-until-fields" style="display: none; text-align: left;">
                <label for="edit-repeat-until-month"><?php echo $translations['repeat_until_month'] ?></label>  
                <select id="edit-repeat-until-month" name="repeat_until_month">
                    <option value="">Unendlich</option>
                    <?php
                    // Monatsnamen
                    $months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                               'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                    foreach ($months as $num => $name) {
                        echo "<option value='" . ($num + 1) . "'>$name</option>";
                    }
                    ?>
                </select> 
                <br><br>
                <label for="edit-repeat-until-year"><?php echo $translations['repeat_until_year'] ?></label>
                <input type="number" id="edit-repeat-until-year" name="repeat_until_year" placeholder="z.B. 2025">
            </div>

            <!-- Felder für Buchungsmonat und -jahr -->
            <label for="edit-entry-month"><?php echo $translations['booking_month'] ?></label>
            <select id="edit-entry-month" name="entry_month" required>
                <option value="">Monat auswählen</option>
                <?php
                foreach ($months as $num => $name) {
                    echo "<option value='" . ($num + 1) . "'>$name</option>";
                }
                ?>
            </select>

            <label for="edit-entry-year"><?php echo $translations['booking_year'] ?></label>
            <input type="number" id="edit-entry-year" name="entry_year" placeholder="z.B. 2025" required>

            <button type="submit" id="edit-rebooking-submit-button"><?php echo $translations['save'] ?></button>
        </form>
    </div>
</div>



<script>
function closeEditRebookingOverlay() {
    document.getElementById('edit-rebooking-overlay').style.display = 'none';
}
function toggleRepeatUntilFieldsEdit(recurringValue) {
    const repeatUntilFields = document.getElementById('edit-repeat-until-fields');
    if (recurringValue === 'no') {
        repeatUntilFields.style.display = 'none';
    } else {
        repeatUntilFields.style.display = 'block';
    }
}
</script>

<script>
// Sprachwert (z. B. 'de' oder 'en')
var language = 'de';
var decimalSeparator = language === 'de' ? ',' : '.';

// Bei Eingabe Komma/Punkt validieren
const editAmountInput = document.getElementById('edit-amount');
editAmountInput.addEventListener('input', function() {
    let value = this.value;
    // Erlaube nur Ziffern, Komma und Punkt
    value = value.replace(/[^0-9.,]/g, '');
    if (language === 'de') {
        value = value.replace(/\./g, ',');
    } else {
        value = value.replace(/,/g, '.');
    }
    // Falls direkt Dezimaltrenner -> 0 voranstellen
    if (value.startsWith(decimalSeparator)) {
        value = '0' + value;
    }
    // Nur 1 Dezimaltrenner
    const firstIndex = value.indexOf(decimalSeparator);
    if (firstIndex !== -1) {
        let before = value.slice(0, firstIndex + 1);
        let after  = value.slice(firstIndex + 1).replace(new RegExp('\\' + decimalSeparator, 'g'), '');
        value = before + after;
    }
    // Max. 2 Nachkommastellen
    if (value.indexOf(decimalSeparator) !== -1) {
        const parts = value.split(decimalSeparator);
        parts[1] = parts[1].substring(0, 2);
        value = parts[0] + decimalSeparator + parts[1];
    }
    this.value = value;
});

// Beim Absenden: Komma->Punkt umwandeln
const editRebookingForm = document.getElementById('edit-rebooking-form');
editRebookingForm.addEventListener('submit', function(e) {
    if (language === 'de') {
        editAmountInput.value = editAmountInput.value.replace(',', '.');
    }
});
</script>

<script>
// Füge AJAX + neue Animation hinzu
document.addEventListener('DOMContentLoaded', () => {

    // 1) Grüne Kreis-Haken-Animation
    function showCheckAnimationAndReload() {
        // Falls noch nicht vorhanden, Styles anhängen
        if (!document.getElementById('check-animation-style')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'check-animation-style';
            styleEl.innerHTML = `
                @keyframes fillCircle {
                    0% { fill: transparent; }
                    100% { fill: #4CAF50; }
                }
                @keyframes fadeInCheck {
                    0% { opacity: 0; }
                    100% { opacity: 1; }
                }
                .check-animation-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.2);
                    backdrop-filter: blur(5px);
                    -webkit-backdrop-filter: blur(5px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999;
                }
                .checkmark-svg {
                    width: 100px;
                    height: 100px;
                    overflow: visible;
                }
                .checkmark__circle {
                    stroke: #4CAF50;
                    stroke-width: 4;
                    fill: transparent;
                    animation: fillCircle 0.6s ease forwards;
                }
                .checkmark__check {
                    stroke: white;
                    stroke-width: 4;
                    fill: none;
                    opacity: 0;
                    animation: fadeInCheck 0.3s ease forwards;
                    animation-delay: 0.6s;
                }
            `;
            document.head.appendChild(styleEl);
        }

        const container = document.createElement('div');
        container.className = 'check-animation-container';

        const svgNS = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(svgNS, "svg");
        svg.classList.add("checkmark-svg");
        svg.setAttribute("viewBox", "0 0 52 52");

        const circle = document.createElementNS(svgNS, "circle");
        circle.classList.add("checkmark__circle");
        circle.setAttribute("cx", "26");
        circle.setAttribute("cy", "26");
        circle.setAttribute("r", "25");

        const check = document.createElementNS(svgNS, "path");
        check.classList.add("checkmark__check");
        check.setAttribute("d", "M14 27l7 7 16-16");

        svg.appendChild(circle);
        svg.appendChild(check);
        container.appendChild(svg);
        document.body.appendChild(container);

        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    // 2) AJAX-Submit an update_rebooking.php (muss JSON statt Redirect liefern!)
    const form = document.getElementById('edit-rebooking-form');
    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault(); // Verhindert Standard-Submit
            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                // => Wir erwarten { success: true/false, message: "..."}
                if (result.success) {
                    showCheckAnimationAndReload();
                } else {
                    alert('Fehler: ' + (result.message || 'Umbuchung konnte nicht bearbeitet werden.'));
                }
            } catch (err) {
                console.error('Fehler beim Bearbeiten der Umbuchung:', err);
                alert('Fehler beim Bearbeiten der Umbuchung.');
            }
        });
    }
});
</script>



<!-- Overlay für einzelne Umbuchungen bearbeiten -->
<div class="overlay" id="edit-single-rebooking-overlay" style="display: none;">
    <div class="modal">
        <div class="close-button" onclick="closeSingleRebookingOverlay()">×</div>
        <h2>Einzelne Umbuchung bearbeiten</h2>
        <form id="edit-single-rebooking-form" method="POST" action="update_single_rebooking.php">
            <input type="hidden" id="edit-single-rebooking-id" name="id">
            <input type="hidden" id="edit-single-rebooking-override" name="override" value="1">

            <label for="edit-single-source-account"><?php echo $translations['source'] ?></label>
            <select id="edit-single-source-account" name="source_account" required>
                <option value="main">Hauptkonto</option>
                <?php
                foreach ($tables as $table) {
                    $accountName = str_replace($username . "_", '', $table);
                    echo "<option value=\"$accountName\">" . htmlspecialchars($accountName) . "</option>";
                }
                ?>
            </select>

            <label for="edit-single-target-account"><?php echo $translations['target'] ?></label>
            <select id="edit-single-target-account" name="target_account" required>
                <option value="main">Hauptkonto</option>
                <?php
                foreach ($tables as $table) {
                    $accountName = str_replace($username . "_", '', $table);
                    echo "<option value=\"$accountName\">" . htmlspecialchars($accountName) . "</option>";
                }
                ?>
            </select>

            <label for="edit-single-amount"><?php echo $translations['amount'] ?></label>
            <input type="text" id="edit-single-amount" name="amount" required
                   pattern="^\d+([.,]\d{1,2})?$"
                   title="Bitte geben Sie eine Zahl mit maximal zwei Nachkommastellen ein."
                   inputmode="decimal">

            <label for="edit-single-description"><?php echo $translations['description'] ?></label>
            <input type="text" id="edit-single-description" name="description" required>

            <label for="edit-single-recurring"><?php echo $translations['regularity'] ?></label>
            <select id="edit-single-recurring" name="recurring" required disabled>
                <option value="no" selected>Nein</option>
                <option value="monthly">Monatlich</option>
                <option value="quarterly">Vierteljährig</option>
                <option value="semiannually">Halbjährig</option>
                <option value="annually">Jährlich</option>
            </select>
            <!-- Verstecktes Feld, damit der Wert "no" beim Absenden übertragen wird -->
            <input type="hidden" name="recurring" value="no">

            <label for="edit-single-entry-month"><?php echo $translations['booking_month'] ?></label>
            <select id="edit-single-entry-month" name="entry_month" required>
                <option value="">Monat auswählen</option>
                <?php
                foreach ($months as $num => $name) {
                    echo "<option value='" . ($num + 1) . "'>$name</option>";
                }
                ?>
            </select>

            <label for="edit-single-entry-year"><?php echo $translations['booking_year'] ?></label>
            <input type="number" id="edit-single-entry-year" name="entry_year" placeholder="z.B. 2025" required>

            <button type="submit" id="edit-single-rebooking-submit-button"><?php echo $translations['save'] ?></button>
        </form>
    </div>
</div>

<script>
  // Beispiel: Der Sprachwert wird aus PHP oder Umgebungsvariablen übernommen.
  // Hier z. B. 'de' für Deutsch oder 'en' für Englisch.
  var language = 'de'; // Dynamisch via PHP setzen, z.B. var language = '<?php echo $language; ?>';
  var decimalSeparator = language === 'de' ? ',' : '.';

  const editSingleAmountInput = document.getElementById('edit-single-amount');

  editSingleAmountInput.addEventListener('input', function() {
      let value = this.value;
      
      // Erlaubt nur Ziffern, Komma und Punkt
      value = value.replace(/[^0-9.,]/g, '');
      
      // Unerwünschte Dezimaltrenner in den korrekten umwandeln:
      if (language === 'de') {
          // In Deutsch: Alle Punkte durch Komma ersetzen
          value = value.replace(/\./g, ',');
      } else {
          // In Englisch: Alle Kommata durch Punkt ersetzen
          value = value.replace(/,/g, '.');
      }
      
      // Falls der Wert direkt mit dem Dezimaltrenner beginnt, wird eine führende Null eingefügt
      if (value.startsWith(decimalSeparator)) {
          value = '0' + value;
      }
      
      // Stellt sicher, dass nur ein Dezimaltrenner vorhanden ist
      const firstSeparatorIndex = value.indexOf(decimalSeparator);
      if (firstSeparatorIndex !== -1) {
          let before = value.slice(0, firstSeparatorIndex + 1);
          let after = value.slice(firstSeparatorIndex + 1).replace(new RegExp('\\' + decimalSeparator, 'g'), '');
          value = before + after;
      }
      
      // Begrenzt die Nachkommastellen auf maximal 2
      if (value.indexOf(decimalSeparator) !== -1) {
          const parts = value.split(decimalSeparator);
          parts[1] = parts[1].substring(0, 2);
          value = parts[0] + decimalSeparator + parts[1];
      }
      
      this.value = value;
  });

  // Beim Absenden des Formulars wird der Dezimaltrenner von Komma in Punkt umgewandelt,
  // damit der Server den Wert korrekt als Dezimalzahl interpretiert.
  const editSingleRebookingForm = document.getElementById('edit-single-rebooking-form');
  editSingleRebookingForm.addEventListener('submit', function(e) {
      if (language === 'de') {
          editSingleAmountInput.value = editSingleAmountInput.value.replace(',', '.');
      }
  });
</script>



<script>
function closeSingleRebookingOverlay() {
    document.getElementById('edit-single-rebooking-overlay').style.display = 'none';
}
</script>


<script>
function handleRebookingEdit(button) {
    const entryId = button.dataset.id;

    // Daten für die Umbuchung abrufen
    fetch(`get_rebooking_details.php?id=${entryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            // Overlay öffnen
            const overlay = document.getElementById("edit-rebooking-overlay");
            overlay.style.display = "flex";

            // Felder mit den Daten füllen
            document.getElementById("edit-rebooking-id").value = data.id;
            document.getElementById("edit-source-account").value = data.source_account;
            document.getElementById("edit-target-account").value = data.target_account;
            document.getElementById("edit-amount").value = data.amount;
            document.getElementById("edit-description").value = data.description;

            // Regelmäßig
            const recurringField = document.getElementById("edit-recurring");
            recurringField.value = data.recurring || "no";
            toggleRepeatUntilFieldsEdit(data.recurring);

            // Wiederholend bis
            const repeatUntilMonthField = document.getElementById("edit-repeat-until-month");
            const repeatUntilYearField = document.getElementById("edit-repeat-until-year");
            
            repeatUntilMonthField.value = data.repeat_until_month || "";
            repeatUntilYearField.value = data.repeat_until_year || "";

            // Buchungsmonat und -jahr
            const entryMonthField = document.getElementById("edit-entry-month");
            const entryYearField = document.getElementById("edit-entry-year");

            entryMonthField.value = data.entry_month || "";
            entryYearField.value = data.entry_year || "";
        })
        .catch(error => {
            console.error("Fehler beim Laden der Umbuchungsdaten:", error);
            alert("Fehler beim Laden der Umbuchungsdaten.");
        });
}
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {

    // Einmalige Funktion für das neue Animations-Overlay:
    function showCheckAnimationAndReload() {
        // Falls noch nicht vorhanden, Styles anfügen
        if (!document.getElementById('check-animation-style')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'check-animation-style';
            styleEl.innerHTML = `
                @keyframes fillCircle {
                    0% { fill: transparent; }
                    100% { fill: #4CAF50; }
                }
                @keyframes fadeInCheck {
                    0% { opacity: 0; }
                    100% { opacity: 1; }
                }
                .check-animation-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.2);
                    backdrop-filter: blur(5px);
                    -webkit-backdrop-filter: blur(5px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999;
                }
                .checkmark-svg {
                    width: 100px;
                    height: 100px;
                    overflow: visible;
                }
                .checkmark__circle {
                    stroke: #4CAF50;
                    stroke-width: 4;
                    fill: transparent;
                    animation: fillCircle 0.6s ease forwards;
                }
                .checkmark__check {
                    stroke: white;
                    stroke-width: 4;
                    fill: none;
                    opacity: 0;
                    animation: fadeInCheck 0.3s ease forwards;
                    animation-delay: 0.6s;
                }
            `;
            document.head.appendChild(styleEl);
        }

        // Container + SVG
        const container = document.createElement('div');
        container.className = 'check-animation-container';

        const svgNS = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(svgNS, "svg");
        svg.classList.add("checkmark-svg");
        svg.setAttribute("viewBox", "0 0 52 52");

        // Kreis
        const circle = document.createElementNS(svgNS, "circle");
        circle.classList.add("checkmark__circle");
        circle.setAttribute("cx", "26");
        circle.setAttribute("cy", "26");
        circle.setAttribute("r", "25");

        // Haken
        const check = document.createElementNS(svgNS, "path");
        check.classList.add("checkmark__check");
        check.setAttribute("d", "M14 27l7 7 16-16");

        svg.appendChild(circle);
        svg.appendChild(check);
        container.appendChild(svg);
        document.body.appendChild(container);

        // Seite nach ~1s neu laden
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    const rebookingForm = document.getElementById("rebooking-form");
    if (rebookingForm) {
        rebookingForm.addEventListener("submit", function (event) {
            event.preventDefault(); // Standard-Submit unterbinden

            const formData = new FormData(this);

            fetch("add_rebooking.php", {
                method: "POST",
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    // Statt alert & location.reload => Animation + reload
                    showCheckAnimationAndReload();
                }
            })
            .catch(error => {
                console.error("Fehler:", error);
                alert("Ein Fehler ist aufgetreten.");
            });
        });
    }
});
</script>


<script>
function openRebookingOverlay() {
    document.getElementById("rebooking-overlay").style.display = "flex";
}

function closeRebookingOverlay() {
    document.getElementById("rebooking-overlay").style.display = "none";
}
</script>


<script>
    // Funktion zum Anzeigen/Verstecken der Felder für Wiederholungsende
    function toggleRepeatUntilFields(recurringValue, overlayId) {
        const repeatUntilFields = document.querySelector(`#${overlayId} .repeat-until-fields`);
        if (recurringValue === 'no') {
            repeatUntilFields.style.display = 'none';
        } else {
            repeatUntilFields.style.display = 'block';
        }
    }

    // Event-Listener für Bewegungen
    document.getElementById('recurring').addEventListener('change', function () {
        toggleRepeatUntilFields(this.value, 'overlay'); // 'overlay' für Bewegungen
    });

    // Event-Listener für Umbuchungen
    document.getElementById('recurring-rebooking').addEventListener('change', function () {
        toggleRepeatUntilFields(this.value, 'rebooking-overlay'); // 'rebooking-overlay' für Umbuchungen
    });
</script>

<!--Bei Monatswechsel Kontostand speichern-->
<script>
function updateBalance(month, year) {
    fetch('update_balance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `month=${month}&year=${year}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Kontostand aktualisiert:', data.balance);
        } else {
            console.error('Fehler beim Aktualisieren des Kontostands.');
        }
    })
    .catch(error => console.error('Fehler:', error));
}

// Monat ändern
function changeMonth() {
    const selector = document.getElementById('month-selector');
    const [month, year] = selector.value.split('-').map(Number);

    // Kontostand aktualisieren
    updateBalance(month, year);

    // Seite neu laden
    window.location.href = `index.php?month=${month}&year=${year}`;
}
</script>

<!--Schließen Button für Overlay-->
<script>
    function closeOverlay() {
        const overlay = document.getElementById('overlay');
        overlay.style.display = 'none';
    }
</script>


<!--Buttons Ausblenden wenn Overlay geöffnet-->
<script>
    function openOverlay() {
        const overlay = document.getElementById('overlay');
        overlay.style.display = 'block';
        
        // Buttons ausblenden
        document.getElementById('add-button').style.display = 'none';
        document.getElementById('scroll-to-top').style.display = 'none';
    }

    function closeOverlay() {
        const overlay = document.getElementById('overlay');
        overlay.style.display = 'none';
        
        // Buttons wieder einblenden
        document.getElementById('add-button').style.display = 'block';
        document.getElementById('scroll-to-top').style.display = 'block';
    }
</script>


    <div class="total-container">

<?php $currency = getenv('CURRENCY') ?: '€'; ?>
<div id="total-sum-container" style="text-align: center; margin-top: 20px; font-size: 18px; display: none;">
    Summe der ausgewählten Einträge: <span id="total-sum">0.00 <?= $currency ?></span>
</div>
<?php echo "<p>$displayText: " . number_format($finalSum ?? 0, 2, ',', '.') . " $currency</p>"; ?>


<script>
document.addEventListener('DOMContentLoaded', () => {
    // Daten aus sessionStorage lesen
    const storedId    = sessionStorage.getItem('highlightId');
    const storedYear  = sessionStorage.getItem('highlightYear');
    const storedMonth = sessionStorage.getItem('highlightMonth');

    if (storedId && storedYear && storedMonth) {
        // Checken, ob wir tatsächlich im passenden Monat/Jahr sind:
        const urlParams = new URLSearchParams(window.location.search);
        const currentYear  = urlParams.get('year');
        const currentMonth = urlParams.get('month');
        
        if (currentYear == storedYear && currentMonth == storedMonth) {
            const boxToHighlight = document.querySelector(`.entry-box[data-id="${storedId}"]`);
            console.log("Gefundenes Element:", boxToHighlight);
            if (boxToHighlight) {
                // Berechne die Position des Elements und ziehe einen Offset ab,
                // damit der Eintrag nicht von der Menubar überlagert wird.
                const headerOffset = 200; // Passe diesen Wert an die Höhe deiner Menubar an
                const elementPosition = boxToHighlight.getBoundingClientRect().top + window.pageYOffset;
                const offsetPosition = elementPosition - headerOffset;
                window.scrollTo({
                    top: offsetPosition,
                    behavior: "smooth"
                });
                
                // Markiere die Box, indem du die "selected"-Klasse hinzufügst
                boxToHighlight.classList.add('selected');
                setTimeout(() => {
                    boxToHighlight.classList.remove('selected');
                }, 4000);
            } else {
                console.log("Kein Element mit data-id=" + storedId + " gefunden!");
            }
        }
        // SessionStorage wieder leeren, damit es nur 1x passiert
        sessionStorage.removeItem('highlightId');
        sessionStorage.removeItem('highlightYear');
        sessionStorage.removeItem('highlightMonth');
    }
});
</script>

</body>

    <!-- Buttons für Massenaktionen -->
<div class="bulk-action-container hidden">
    <button id="bulk-delete" class="bulk-button" title="Löschen">
        <i class="fas fa-trash"></i>
    </button>
    <button id="bulk-hide" class="bulk-button" title="Ausblenden">
        <i class="fas fa-eye-slash"></i>
    </button>
    <button id="bulk-cancel" class="bulk-button" title="Abbrechen">
        <i class="fas fa-times"></i>
    </button>
</div>
    
<!--Monate Dropdownmenü-->
<script>
    function changeMonth() {
        const selectedValue = document.getElementById('month-selector').value;
        const [selectedMonth, selectedYear] = selectedValue.split('-');
        window.location.href = `index.php?month=${selectedMonth}&year=${selectedYear}`;
    }
</script>

<script>
// Funktion zum Schließen des Bearbeiten-Overlays für Umbuchungen
function closeEditRebookingOverlay() {
    document.getElementById("edit-rebooking-overlay").style.display = "none";
}

// Event-Listener hinzufügen, um das Overlay auch bei einem Klick außerhalb zu schließen
window.addEventListener("click", function (event) {
    const overlay = document.getElementById("edit-rebooking-overlay");
    if (event.target === overlay) {
        closeEditRebookingOverlay();
    }
});

// Event-Listener für das X-Symbol
document.querySelector(".close-button").addEventListener("click", closeEditRebookingOverlay);
</script>

<script>
// Funktion für das Öffnen des Sparkonto-Overlays
document.getElementById("add-savings").addEventListener("click", function () {
    document.getElementById("savings-overlay").style.display = "flex";
});

// Funktion für das Schließen des Sparkonto-Overlays
function closeSavingsOverlay() {
    document.getElementById("savings-overlay").style.display = "none";
}

// Klick außerhalb des Overlays schließt es
window.addEventListener("click", function (event) {
    const overlay = document.getElementById("savings-overlay");
    if (event.target === overlay) {
        closeSavingsOverlay();
    }
});


// Klick außerhalb des Umbuchungs-Overlays schließt es
window.addEventListener("click", function (event) {
    const overlay = document.getElementById("rebooking-overlay");
    if (event.target === overlay) {
        closeRebookingOverlay();
    }
});


// Form-Submit für das Sparkonto
document.getElementById("savings-form").addEventListener("submit", function (event) {
    event.preventDefault();
    const savingsName = document.getElementById("savings-name").value.trim();
    const initialBalance = parseFloat(document.getElementById("initial-balance").value);
    const interestRate = parseFloat(document.getElementById("interest-rate").value);

    // Validierung der Eingaben
    if (!savingsName) {
        alert("Bitte geben Sie einen gültigen Namen für das Sparkonto ein.");
        return;
    }

    if (isNaN(initialBalance)) {
        alert("Bitte geben Sie eine gültige Startbilanz ein.");
        return;
    }

    if (isNaN(interestRate) || interestRate < 0 || interestRate > 100) {
        alert("Bitte geben Sie einen gültigen Zinssatz zwischen 0 und 100 ein.");
        return;
    }

    // Daten senden
    fetch("/createSavingsAccount.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ savingsName, initialBalance, interestRate }),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error("Fehler beim Hinzufügen des Sparkontos.");
            }
            return response.json();
        })
        .then((data) => {
            alert(data.message); // Erfolgsmeldung anzeigen
            if (data.message === "Sparkonto erfolgreich erstellt") {
                window.location.reload(); // Seite neu laden
            }
        })
        .catch((error) => {
            console.error("Fehler:", error);
            alert("Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.");
        });
});

// Service Worker Registrierung
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/sw.js").then(() => {
        console.log("Service Worker registriert.");
    });
}

</script>
</html>
<?php
include('button_functions.php');
?>

<!-- Swipe Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  let startX = 0, startY = 0, endX = 0, endY = 0;
  let moved = false;        // Flag, ob sich der Finger bewegt hat
  let skipSwipe = false;
  const threshold = 100;
  const MIN_Y_FOR_SWIPE = 150; // Swipes werden nur registriert, wenn der Touch unterhalb dieses Y-Werts startet

  // Bereiche, in denen kein Swipe ausgelöst werden soll (z. B. Menü, Overlays)
  const IGNORE_SELECTORS = [
    '.menu-bar',
    '.overlay',
    '.burger-menu',
    '.menu-links'
  ];

  function isIgnoredArea(target) {
    return IGNORE_SELECTORS.some(selector => target.closest(selector));
  }

  // PHP-Variablen, die definieren, ob wir am Anfang oder Ende der Monatsliste sind
  const isFirstMonth = <?= json_encode($isFirstMonth) ?>;
  const isLastMonth  = <?= json_encode($isLastMonth) ?>;

  document.addEventListener('touchstart', function(event) {
    // Prüfen, ob der Bulk‑Mode aktiv ist (über globales window.bulkMode)
    if (window.bulkMode === true) {
      skipSwipe = true;
      return;
    }

    // Touch in einem ignorierten Bereich (z. B. Menü oder Overlay) -> Swipe deaktivieren
    if (isIgnoredArea(event.target)) {
      skipSwipe = true;
      return;
    }

    // Wenn der Touch sehr weit oben startet, ignorieren (z. B. obere 150px)
    const touchY = event.touches[0].clientY;
    if (touchY < MIN_Y_FOR_SWIPE) {
      skipSwipe = true;
      return;
    }

    // Initialisiere Variablen für diese Geste
    skipSwipe = false;
    moved = false; // noch keine Bewegung registriert
    startX = event.touches[0].clientX;
    startY = touchY;
    // Setze endX/endY initial auf den Startwert, um unerwünschte Swipe-Trigger zu vermeiden
    endX = startX;
    endY = startY;
  });

  document.addEventListener('touchmove', function(event) {
    if (skipSwipe) return;
    moved = true; // Es fand eine Bewegung statt
    endX = event.touches[0].clientX;
    endY = event.touches[0].clientY;
  });

  document.addEventListener('touchend', function() {
    if (skipSwipe) {
      skipSwipe = false;
      return;
    }

    // Wenn keine Bewegung stattgefunden hat, nicht als Swipe werten
    if (!moved) {
      startX = startY = endX = endY = 0;
      return;
    }

    const diffX = endX - startX;
    const diffY = endY - startY;

    if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > threshold) {
      if (diffX > 0) {
        // Wischen nach rechts -> vorheriger Monat
        if (!isFirstMonth) {
          location.href = 'index.php?month=<?= $month - 1 == 0 ? 12 : $month - 1 ?>&year=<?= $month - 1 == 0 ? $year - 1 : $year ?>';
        }
      } else {
        // Wischen nach links -> nächster Monat
        if (!isLastMonth) {
          location.href = 'index.php?month=<?= $month + 1 == 13 ? 1 : $month + 1 ?>&year=<?= $month + 1 == 13 ? $year + 1 : $year ?>';
        }
      }
    }

    // Reset der Variablen für den nächsten Touch
    startX = startY = endX = endY = 0;
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const overlay = document.getElementById('chartOverlay');
  const closeBtn = document.getElementById('closeOverlay');
  const updateBtn = document.getElementById('updateChart');
  let sparkontoChart;  // Chart.js Instanz

  // Click-Event für "Verlauf anzeigen"-Buttons
  document.querySelectorAll('.btn-show-chart').forEach(btn => {
    btn.addEventListener('click', () => {
      const sparkontoName = btn.dataset.sparkonto;
      // Standardzeitraum (z.B. -3 und +9 Monate)
      const defaultStart = getRelativeMonthString(-3); 
      const defaultEnd   = getRelativeMonthString(+9);

      // Input-Felder vorbelegen
      document.getElementById('chartStart').value = defaultStart;
      document.getElementById('chartEnd').value   = defaultEnd;

      // Overlay anzeigen
      overlay.style.display = 'block';

      // Daten laden und Chart anzeigen
      loadChartData(sparkontoName, defaultStart, defaultEnd);
    });
  });

  closeBtn.addEventListener('click', () => {
    overlay.style.display = 'none';
  });

  // Aktualisieren-Button (wenn User das Datum ändert)
  updateBtn.addEventListener('click', () => {
    const sparkontoName = document.querySelector('.btn-show-chart').dataset.sparkonto; 
    // Falls du mehrere Sparkontos hast, müsste man den Namen merkbar machen
    // z.B. in einer globalen Variable "currentSparkonto"
    const startVal = document.getElementById('chartStart').value;
    const endVal   = document.getElementById('chartEnd').value;
    loadChartData(sparkontoName, startVal, endVal);
  });

  // AJAX-Funktion
  function loadChartData(konto, start, end) {
    // Baue Request-URL
    const url = `get_sparkonto_data.php?username=${encodeURIComponent(konto.split('_')[0])}&start=${start}&end=${end}`;
    
    fetch(url)
      .then(res => res.json())
      .then(data => {
        // data = Array von Objekten { month: "YYYY-MM", amount: number }

        // X-Achse: Monate, Y-Achse: Beträge
        const labels = data.map(d => d.month);
        const values = data.map(d => d.amount);

        // Falls Chart schon existiert => destroy
        if (sparkontoChart) {
          sparkontoChart.destroy();
        }

        const ctx = document.getElementById('sparkontoChart').getContext('2d');
        sparkontoChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: labels,
            datasets: [{
              label: 'Verlauf',
              data: values,
              borderColor: 'blue',
              fill: false
            }]
          },
          options: {
            responsive: true,
            scales: {
              y: {
                beginAtZero: true
              }
            }
          }
        });
      })
      .catch(err => console.error(err));
  }

  // Hilfsfunktion: z.B. "2025-02" => -3 => "2024-11"
  function getRelativeMonthString(offset) {
    const now = new Date();
    // Aktuelles Jahr/Monat
    let y = now.getFullYear();
    let m = now.getMonth() + 1; // 0-basiert -> +1

    // offset anwenden
    m += offset;
    while (m > 12) {
      m -= 12;
      y++;
    }
    while (m < 1) {
      m += 12;
      y--;
    }

    // Monat 2-stellig
    const mm = (m < 10) ? '0' + m : '' + m;
    return `${y}-${mm}`;
  }
});
</script>

<script>
// Globale Variable für Chart-Instanz, damit wir sie bei neuem Laden zerstören können
let savingsHistoryChartInstance = null;

// Diese Funktion öffnet das Overlay
function openSavingsHistoryOverlay(savingsName) {
    // Sparkonto-Namen im Overlay anzeigen
    document.getElementById('overlaySavingsName').innerText = savingsName;

    // Overlay sichtbar machen
    document.getElementById('savingsHistoryOverlay').style.display = 'block';

    // Standard-Zeitraum setzen. Beispiel: Letzte 3 Monate bis nächste 9 Monate
    // Hier kannst du alternativ deinen "targetMonth"/"targetYear" verwenden.
    const now = new Date();
    // standardStart = 3 Monate zurück
    const start = new Date(now.getFullYear(), now.getMonth() - 3, 1);
    // standardEnd = 9 Monate in die Zukunft
    const end   = new Date(now.getFullYear(), now.getMonth() + 9, 1);

    // input type="month" braucht Format YYYY-MM
    const startVal = `${start.getFullYear()}-${String(start.getMonth()+1).padStart(2,'0')}`;
    const endVal   = `${end.getFullYear()}-${String(end.getMonth()+1).padStart(2,'0')}`;

    document.getElementById('historyStart').value = startVal;
    document.getElementById('historyEnd').value   = endVal;

    // Daten direkt laden
    loadSavingsHistoryData(savingsName, startVal, endVal);
}

// Overlay schließen
function closeSavingsHistoryOverlay() {
    document.getElementById('savingsHistoryOverlay').style.display = 'none';
}

// Funktion zum Neu-Laden der Daten, wenn der Benutzer den Zeitraum ändert
document.getElementById('updateHistoryRangeButton').addEventListener('click', function() {
    const savingsName = document.getElementById('overlaySavingsName').innerText;
    const startVal    = document.getElementById('historyStart').value; // z.B. "2023-05"
    const endVal      = document.getElementById('historyEnd').value;   // z.B. "2024-03"
    loadSavingsHistoryData(savingsName, startVal, endVal);
});

// Diese Funktion ruft per AJAX die Daten ab und zeichnet den Chart
function loadSavingsHistoryData(savingsName, startVal, endVal) {
    // startVal = "YYYY-MM" => splitten
    const [startYear, startMonth] = startVal.split('-');
    const [endYear,   endMonth]   = endVal.split('-');

    const url = `savings_history.php?action=get_history&savings_name=${encodeURIComponent(savingsName)}`
              + `&start_month=${parseInt(startMonth)}&start_year=${parseInt(startYear)}`
              + `&end_month=${parseInt(endMonth)}&end_year=${parseInt(endYear)}`;

    fetch(url)
      .then(response => response.json())
      .then(json => {
          if (json.status === 'ok') {
              const dataArray = json.data; 
              // dataArray = [ {yearMonth: "2023-01", total: 123.45}, ...]

              // Falls schon ein Chart existiert, zerstören:
              if (savingsHistoryChartInstance) {
                  savingsHistoryChartInstance.destroy();
              }

              // Labels und Werte für den Chart vorbereiten
              const labels = dataArray.map(item => item.yearMonth);
              const values = dataArray.map(item => item.total);

              // Chart initialisieren
const ctx = document.getElementById('savingsHistoryChart').getContext('2d');
savingsHistoryChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,  // ["2023-01", "2023-02", ...]
        datasets: [{
            label: 'Verlauf',
            data: values,   // z.B. [100, 50, -20, 30, ...]
            fill: true,
            // Der "segment"-Abschnitt: Hier definieren wir Farbwechsel,
            // abhängig davon, ob y < 0 oder y >= 0:
            segment: {
                borderColor: ctx => ctx.p1.parsed.y < 0 ? 'red' : 'green',
                backgroundColor: ctx => ctx.p1.parsed.y < 0
                  ? 'rgba(255, 0, 0, 0.2)'
                  : 'rgba(0, 255, 0, 0.2)'
            },
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

          } else {
              alert('Fehler: ' + json.message);
          }
      })
      .catch(err => {
          console.error('AJAX-Fehler', err);
      });
}
</script>
