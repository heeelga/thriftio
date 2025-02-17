<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prüfe, ob der Benutzer eingeloggt ist und das Passwort noch nicht geändert wurde
if (isset($_SESSION['username']) && isset($_SESSION['changed_password']) && $_SESSION['changed_password'] == 0) {
    // Um Endlosschleifen zu vermeiden, leite nicht um, wenn wir bereits in change_pass.php sind
    if (basename($_SERVER['PHP_SELF']) !== 'change_pass.php') {
        header("Location: change_pass.php");
        exit;
    }
}

// Stellen Sie sicher, dass in der Session der Username gespeichert ist
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    // Stellen Sie eine Datenbankverbindung her.
    // Beispiel: Nehmen wir an, die Datei db_connect.php enthält den Verbindungsaufbau und setzt $conn
    include_once 'dbconnection.php';

    // Führe die Abfrage aus, ob der Benutzer Admin ist
    $stmt = $conn->prepare("SELECT admin FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $isAdmin = ($row['admin'] == 1);
    } else {
        $isAdmin = false;
    }
    $stmt->close();

// Sprache aus der Umgebungsvariable oder Standardwert 'de'
$language = getenv('LANGUAGE') ?: 'de';

// Passende Sprachdatei laden
$languageFile = __DIR__ . "/languages/$language.json";

if (file_exists($languageFile)) {
    $translations = json_decode(file_get_contents($languageFile), true);
} else {
    $translations = json_decode(file_get_contents(__DIR__ . "/languages/de.json"), true);
}    

    // Logging zum Debuggen:
    error_log("Username: " . $username);
    error_log("isAdmin (DB-Abfrage): " . var_export($isAdmin, true));
} else {
    $isAdmin = false;
    error_log("Kein Username in der Session gefunden.");
}
?>
