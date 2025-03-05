<?php
$db_host = getenv('DB_HOST') ?: 'db'; // Datenbankhost
$db_name = getenv('MYSQL_DATABASE') ?: 'finance'; // Datenbankname
$db_user = getenv('MYSQL_USER') ?: 'finance_user'; // Datenbankbenutzername
$db_pass = getenv('MYSQL_PASSWORD') ?: 'finance_password'; // Datenbankpasswort

//pruefe Datenbankverbindung - falls Fehler gebe dies aus
if (mysqli_connect_errno())
  {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  }

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

//$GLOBALS['conn'] = $conn;

//$conn->close();

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    // Einstellung der Fehlerbehandlung auf "Exception" Modus
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Fehlermeldung ausgeben
    die("Connection failed: " . $e->getMessage());
}
?>
