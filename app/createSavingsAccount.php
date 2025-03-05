<?php
include('init.php');
include('dbconnection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $_SESSION['username']; // Vorausgesetzt, der Benutzer ist eingeloggt.
    $savingsName = preg_replace('/[^a-zA-Z0-9_]/', '', $data['savingsName']); // Validierung des Namens.
    $initialBalance = floatval($data['initialBalance']);
    $interestRate = floatval($data['interestRate']); // Eingabefeld für den Zinssatz

    if (empty($savingsName)) {
        echo json_encode(['message' => 'Ungültige Eingabedaten: Der Name des Sparkontos darf nicht leer sein.']);
        exit;
    }

    $tableName = $username . "_" . $savingsName;
    $balanceTableName = "Kontostand_" . $tableName;

    try {
        global $pdo;

        // Tabelle mit den gleichen Spalten wie die Hauptkontotabelle erstellen
        $stmt = $pdo->prepare("CREATE TABLE IF NOT EXISTS `$tableName` (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            type ENUM('income','expense') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description VARCHAR(255) NOT NULL,
            recurring ENUM('no','monthly','quarterly','semiannually','annually') NOT NULL DEFAULT 'no',
            recurring_in_month VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
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
            override_id INT(11) DEFAULT NULL
        )");
        $stmt->execute();

        // Tabelle für Kontostände erstellen
        $stmt = $pdo->prepare("CREATE TABLE IF NOT EXISTS `$balanceTableName` (
            entry_month INT NOT NULL,
            entry_year INT NOT NULL,
            balance DECIMAL(10, 2) NOT NULL,
            PRIMARY KEY (entry_month, entry_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $stmt->execute();

        // Startbilanztyp bestimmen
        $type = $initialBalance >= 0 ? 'income' : 'expense';

        // Startbilanz einfügen
        $stmt = $pdo->prepare("INSERT INTO `$tableName` (type, amount, description, entry_month, entry_year) 
            VALUES (?, ?, 'Startbilanz', MONTH(CURRENT_DATE()), YEAR(CURRENT_DATE()))");
        $stmt->execute([$type, abs($initialBalance)]);

        // Eintrag in der separaten Zinstabelle erstellen
$interestRate = isset($data['interestRate']) ? floatval($data['interestRate']) : null;

if ($interestRate === null || $interestRate < 0 || $interestRate > 100) {
    echo json_encode(['message' => 'Ungültige Eingabedaten: Zinssatz ist erforderlich und muss zwischen 0 und 100 liegen.']);
    exit;
}

// Eintrag in der separaten Zinstabelle erstellen
$interestStmt = $pdo->prepare("INSERT INTO savings_interest_rates (savings_name, interest_rate) VALUES (:savings_name, :interest_rate)");
$interestStmt->execute([
    'savings_name' => $tableName,
    'interest_rate' => $interestRate,
]);


        echo json_encode(['message' => 'Sparkonto erfolgreich erstellt']);
    } catch (PDOException $e) {
        echo json_encode(['message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>
