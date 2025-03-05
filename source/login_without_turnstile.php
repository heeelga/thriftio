<?php
if (session_status() === PHP_SESSION_NONE) {

ini_set('session.save_path', '/var/lib/php/sessions');
ini_set('session.gc_maxlifetime', 604800); // 7 Tage in Sekunden
ini_set('session.cookie_lifetime', 604800); // 7 Tage Cookie-Lifetime

session_set_cookie_params([
    'lifetime' => 604800,
    'path' => '/',
    'domain' => getenv('DOMAIN') ?: 'DOMAIN',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

    session_start();
}


include('dbconnection.php');

// Sprache aus der Umgebungsvariable oder Standardwert 'de'
$language = getenv('LANGUAGE') ?: 'de';

// Passende Sprachdatei laden
$languageFile = __DIR__ . "/languages/$language.json";

if (file_exists($languageFile)) {
    $translations = json_decode(file_get_contents($languageFile), true);
} else {
    $translations = json_decode(file_get_contents(__DIR__ . "/languages/de.json"), true);
}

$msg="";

// Funktion zur Ermittlung der ursprünglichen Client-IP
function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Es könnten mehrere IPs übergeben werden – die erste ist in der Regel die ursprüngliche
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function isBanned($ip) {
  global $pdo;
  
  $stmt = $pdo->prepare('SELECT count(*) AS count FROM logins WHERE ip_address = :ip AND login_status = 0 AND login_date >= DATE_SUB(NOW(), INTERVAL 120 MINUTE)');
  $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
  $stmt->execute();
  
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $count = $result['count'];
  
  if ($count >= 6) {
    return true;
  }
  
  return false;
}

$ip = getClientIp();

if (isBanned($ip)) {
  header("Location: https://www.google.de");
  exit;
}

if (isset($_POST['username'])) {
  $username = stripslashes($_REQUEST['username']); // entfernt Backslashes
  $username = mysqli_real_escape_string($conn, $username); // escaped Sonderzeichen
  $password = stripslashes($_REQUEST['password']);
  $password = mysqli_real_escape_string($conn, $password);

  // Überprüfen, ob der Benutzer in der Datenbank existiert
  $query = "SELECT * FROM `user` WHERE username='$username'";
  $result = mysqli_query($conn, $query) or die(mysqli_error($conn));
  $row = mysqli_fetch_assoc($result);
  
  // Nur wenn $row vorhanden ist, die Werte auslesen, ansonsten Default-Werte setzen
  if ($row) {
      $storedPassword = $row['password'];
  } else {
      $storedPassword = null;
  }

  if ($row && password_verify($password, $storedPassword)) {
    // Session-Variablen setzen
    $_SESSION['username'] = $username;
    $_SESSION['changed_password'] = $row['changed_password']; // Wird benötigt, um den Zustand auf anderen Seiten zu prüfen

    // Überprüfen, ob das Passwort geändert werden muss
    if ($row['changed_password'] == 0) {
      // Benutzer zur Passwortänderung weiterleiten
      header("Location: change_pass.php");
      exit;
    }

    // SET PRUEF auf 0
    $query5 = "UPDATE `user` SET pruef = '0' WHERE username='$username'";
    $result5 = mysqli_query($conn, $query5) or die(mysqli_error($conn));
    
    header("Location: index.php");
    
    include("dbconnection.php");
    $date = date("Y-m-d H:i:s");
    $ip = getClientIp();
    $location_data = file_get_contents("http://ip-api.com/json/{$ip}");
    $location_data = json_decode($location_data);
    if ($location_data && isset($location_data->status) && $location_data->status === 'success') {
        $city = $location_data->city;
        $country = $location_data->country;
    } else {
        $city = "unknown";
        $country = "unknown";
    }
    $query = "INSERT INTO logins (name, login_status, ip_address, city, country) VALUES ('$username', true, '$ip', '$city', '$country')";
    $result = mysqli_query($conn, $query);
    require_once 'pushover.php';
    pushover("$username hat sich aus $city, $country mit IP $ip eingeloggt!");
    
  } else {
    $msg = "<div class='error-message'>";
    if ($row && isset($row['active']) && $row['active'] == 0) {
      $msg .= $translations['account_locked'];
    } else {
      $msg .= $translations['wrong_login'];
    }
    $msg .= "</div><br><br>";
    
    include("dbconnection.php");
    $ip = getClientIp();
    $location_data = file_get_contents("http://ip-api.com/json/{$ip}");
    $location_data = json_decode($location_data);
    if ($location_data && isset($location_data->status) && $location_data->status === 'success') {
        $city = $location_data->city;
        $country = $location_data->country;
    } else {
        $city = "unknown";
        $country = "unknown";
    }
    $query = "INSERT INTO logins (name, login_status, ip_address, city, country) VALUES ('$username', false, '$ip', '$city', '$country')";
    $result = mysqli_query($conn, $query);
    require_once 'pushover.php';
    pushover("Fehlerhafter Login von $username aus $city, $country mit IP $ip !");
    
    // Update der fehlgeschlagenen Login-Versuche – nur wenn der Benutzer existiert
    if ($row) {
        $failed_logins = $row['failed_logins'];
        $failed_logins++; // Zähler erhöhen
        $query_update = "UPDATE `user` SET failed_logins=$failed_logins WHERE username='$username'";
        mysqli_query($conn, $query_update);
    }
  }
}
else { }
?>


<!DOCTYPE html>
<html lang="de_DE">
<head>

<link rel="icon" href="images/icon_small.jpg" type="image/jpg">
  <link rel="manifest" href="/manifest.json">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <link rel="apple-touch-icon" href="/images/icon_small.jpg">
  <link rel="stylesheet" href="login.css" />
  <meta name="viewport" content="width=device-width; initial-scale=1.0; minimum-scale=1.0; maximum-scale=1.0; user-scalable=0; shrink-to-fit=no"/>
  <style>
    .error-message {
      background-color: #E63946;
      color: white;
      padding: 10px;
      border-radius: 5px;
      text-align: center;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="form-container">
    <form class="login-form" action="" method="post">
      <center>
        <h2>Login</h2>
        <div class="form-group">
          <center><?php echo $msg ?></center>
          <input type="text" name="username" id="username" placeholder="<?php echo $translations['username'] ?? 'Username'; ?>" required autocomplete="on">
        </div>
        <div class="form-group">
          <input type="password" name="password" id="password" placeholder="<?php echo $translations['password'] ?? 'Password'; ?>" required autocomplete="on">
        </div>
        <div class="forgot-password">
          <a href="lost.php"><?php echo $translations['lost_password'] ?? 'Lost password?'; ?></a>
        </div>
        <button type="submit" class="login-button"><?php echo $translations['login_button'] ?? 'Login'; ?></button>
        <div class="status-message">
        </div>
      </center>
    </form>
  </div>
</div>
</body>
</html>
