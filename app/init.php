<?php
// Stelle sicher, dass keinerlei Output vor diesem Tag erfolgt!
// Optional: Output Buffering aktivieren, um ungewollte Ausgaben zu verhindern.
ob_start();

// Nur wenn noch keine Session aktiv ist, die Einstellungen vornehmen und die Session starten.
if (session_status() === PHP_SESSION_NONE) {
    // Session-Speicherort und Lebensdauer konfigurieren
    ini_set('session.save_path', '/var/lib/php/sessions');
    ini_set('session.gc_maxlifetime', 604800); // 7 Tage
    ini_set('session.cookie_lifetime', 604800); // 7 Tage

    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
//        'domain' => getenv('DOMAIN') ?: $_SERVER['HTTP_HOST'],
//        'secure' => true,    // Nur bei HTTPS verwenden; andernfalls auf false setzen
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
} else {
    // Falls die Session bereits aktiv ist, können die Einstellungen nicht mehr geändert werden.
    error_log("Session already active. Session settings cannot be modified.");
}

// Authentifizierungs-Check: Falls kein Benutzer eingeloggt ist und nicht auf der Login-Seite, umleiten.
if (!isset($_SESSION['username']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: login.php");
    exit();
}

// Falls der Benutzer eingeloggt ist, aber sein Passwort noch nicht geändert wurde, umleiten (außer auf change_initial_pass.php).
if (isset($_SESSION['username']) && isset($_SESSION['changed_password']) && $_SESSION['changed_password'] == 0) {
    if (basename($_SERVER['PHP_SELF']) !== 'change_initial_pass.php') {
        header("Location: change_initial_pass.php");
        exit();
    }
}

// Weitere Initialisierungen, wenn der Benutzer eingeloggt ist.
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    include_once 'dbconnection.php';

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

    $language = getenv('LANGUAGE') ?: 'de';
    $languageFile = __DIR__ . "/languages/$language.json";

    if (file_exists($languageFile)) {
        $translations = json_decode(file_get_contents($languageFile), true);
    } else {
        $translations = json_decode(file_get_contents(__DIR__ . "/languages/de.json"), true);
    }

    error_log("Username: " . $username);
    error_log("isAdmin (DB-Abfrage): " . var_export($isAdmin, true));
} else {
    $isAdmin = false;
    error_log("Kein Username in der Session gefunden.");
}
?>
