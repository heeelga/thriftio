<?php
// Umgebungsvariablen auslesen
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('MYSQL_DATABASE') ?: 'finance';
$db_user = getenv('MYSQL_USER') ?: 'root';
$db_pass = getenv('MYSQL_PASSWORD') ?: '';
$app_user = getenv('APP_USER') ?: 'default_user';
$app_password = getenv('APP_PASSWORD') ?: 'default_password';
$app_firstname = getenv('APP_FIRSTNAME') ?: 'default_firstname';
$app_email = getenv('APP_EMAIL') ?: 'defaultmail@default.xyz';

// Verbindung zur Datenbank herstellen
try {
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Datenbank erstellen, falls sie nicht existiert
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name");
    $pdo->exec("USE $db_name");

    echo "Datenbank '$db_name' erfolgreich erstellt oder bereits vorhanden.<br>";

    // Tabelle für den Benutzer erstellen
    $tableName = "`" . str_replace("`", "``", $app_user) . "`"; // Tabellennamen sicher erstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS $tableName (
        id INT AUTO_INCREMENT PRIMARY KEY,
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
        hidden_override TINYINT(1) DEFAULT 0,
        category VARCHAR(255) DEFAULT NULL
    )");
    echo "Tabelle '$app_user' erfolgreich erstellt oder bereits vorhanden.<br>";

    // Tabelle `user` erstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    failed_logins INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

    echo "Tabelle 'user' erfolgreich erstellt oder bereits vorhanden.<br>";

    // Fehlende Spalten in der Tabelle `user` hinzufügen
    $columnsToAdd = [
        'release_notes_available' => "TINYINT(1) DEFAULT 0",
        'release_notes_read' => "TINYINT(1) DEFAULT 0",
        'changed_password' => "TINYINT(1) DEFAULT 0",
        'admin' => "TINYINT(1) DEFAULT 0",
        'error_logging' => "TINYINT(1) DEFAULT 0",
        'invite' => "TINYINT(4) NULL DEFAULT NULL",
        'pruef' => "TINYINT(4) NULL DEFAULT NULL"
    ];

    foreach ($columnsToAdd as $column => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM user LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE user ADD $column $definition");
            echo "Spalte '$column' erfolgreich hinzugefügt.<br>";
        }
    }

    // Tabelle `logins` erstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS logins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        login_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        login_status VARCHAR(255),
        ip_address VARCHAR(255),
        city VARCHAR(255),
        country VARCHAR(255)
    )");
    echo "Tabelle 'logins' erfolgreich erstellt oder bereits vorhanden.<br>";

    // Tabelle `savings_interest_rates` erstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS savings_interest_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        savings_name VARCHAR(255) NOT NULL UNIQUE,
        interest_rate FLOAT DEFAULT 0
    )");
    echo "Tabelle 'savings_interest_rates' erfolgreich erstellt oder bereits vorhanden.<br>";

// Dynamischen Benutzer hinzufügen
if (!empty($app_user) && !empty($app_password)) {
    $hashed_password = password_hash($app_password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT IGNORE INTO user (username, password, firstname, email, admin) VALUES (:username, :password, :firstname, :email, :admin)");
    $stmt->execute([
        ':username' => $app_user,
        ':password' => $hashed_password,
        ':firstname' => $app_firstname,
        ':email' => $app_email,
        ':admin' => 1, // Admin-Flag setzen
    ]);
    echo "Benutzer '$app_user' erfolgreich mit Admin-Rechten hinzugefügt oder bereits vorhanden.<br>";
} else {
    echo "APP_USER oder APP_PASSWORD nicht gesetzt. Kein Benutzer wurde erstellt.<br>";
}


} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage();
}
?>
