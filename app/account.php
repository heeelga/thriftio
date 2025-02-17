<?php
include('init.php');
require('dbconnection.php');
include('auth.php');
include('menubar.php');
include('style.css');

// Prüfe, ob der Benutzer eingeloggt ist und das Passwort noch nicht geändert wurde
if (isset($_SESSION['username']) && isset($_SESSION['changed_password']) && $_SESSION['changed_password'] == 0) {
    // Um Endlosschleifen zu vermeiden, leite nicht um, wenn wir bereits in change_pass.php sind
    if (basename($_SERVER['PHP_SELF']) !== 'change_pass.php') {
        header("Location: change_pass.php");
        exit;
    }
}

// Sprache aus der Umgebungsvariable oder Standardwert 'de'
$language = getenv('LANGUAGE') ?: 'de';
$languageFile = __DIR__ . "/languages/$language.json";
if (file_exists($languageFile)) {
    $translations = json_decode(file_get_contents($languageFile), true);
} else {
    $translations = json_decode(file_get_contents(__DIR__ . "/languages/de.json"), true);
}

$username = $_SESSION['username'];

// Hole Benutzerinformationen aus der Datenbank
$query_fetch = "SELECT firstname, username, password, email FROM `user` WHERE username='$username'";
$result_fetch = mysqli_query($conn, $query_fetch);
$row_fetch = mysqli_fetch_assoc($result_fetch);

$firstname = $row_fetch['firstname'];
$username = $row_fetch['username'];
$password = $row_fetch['password'];
$email = $row_fetch['email'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <title><?php echo $translations['account']; ?></title>
  <style>
    .navbar {
      background-color: #007aff;
      color: white;
      padding: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .navbar a {
      color: white;
      text-decoration: none;
      margin: 0 10px;
      font-weight: bold;
    }
    .container {
      margin-top: 100px; /* Platz für die fixe Navbar */
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 20px;
    }
    .form-container {
      background-color: #ffffff;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      max-width: 400px;
      width: 100%;
      text-align: center;
    }
    h2 {
      font-size: 24px;
      margin-bottom: 20px;
      color: #007aff;
    }
    .form-group {
      margin-bottom: 20px;
      text-align: left;
    }
    .form-group label {
      font-weight: bold;
      margin-bottom: 5px;
      display: block;
    }
    .form-group input {
      width: 100%;
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 14px;
      background-color: #f9f9f9;
    }
    .change-button {
      background-color: #007aff;
      color: white;
      border: none;
      padding: 10px 20px;
      font-size: 14px;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .change-button:hover {
      background-color: #005bb5;
    }
    .change-button:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
  
  <div class="container">
    <div class="form-container">
      <h2><?php echo $translations['account']; ?></h2>
      
      <div class="form-group">
        <label for="username"><?php echo $translations['username']; ?></label>
        <input type="text" id="username" name="username" value="<?php echo $username; ?>" disabled>
        <center>
          <br>
          <button class="change-button" onclick="location.href='change_user.php'">
            <?php echo $translations['change']; ?>
          </button>
        </center>
      </div>
      
      <div class="form-group">
        <label for="password"><?php echo $translations['password']; ?></label>
        <input type="password" id="password" name="password" value="<?php echo $password; ?>" disabled>
        <center>
          <br>
          <button class="change-button" onclick="location.href='change_pass.php'">
            <?php echo $translations['change']; ?>
          </button>
        </center>
      </div>
      
      <div class="form-group">
        <label for="email"><?php echo $translations['your_email']; ?></label>
        <input type="email" id="email" name="email" value="<?php echo $email; ?>" disabled>
        <center>
          <br>
          <button class="change-button" onclick="location.href='change_email.php'">
            <?php echo $translations['change']; ?>
          </button>
        </center>
      </div>
    </div>
  </div>
</body>
</html>

<script>
    function toggleMenu() {
        const menuLinks = document.getElementById('menu-links');
        if (menuLinks.style.display === "block") {
            menuLinks.style.display = "none";
        } else {
            menuLinks.style.display = "block";
        }
    }

    // Schließt das Menü, wenn der Benutzer außerhalb des Menüs klickt
    window.onclick = function(event) {
        const menuLinks = document.getElementById('menu-links');
        if (event.target === menuLinks) {
            menuLinks.style.display = "none";
        }
    }
</script>

<!--Alle Benutzereinträge des Monats-->
<script>
    function deleteAllEntries() {
        if (confirm("Möchtest Du wirklich alle Einträge für den aktuellen Monat löschen?")) {
            // Sende eine Anfrage an den Server
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "delete_entries.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    alert("Alle Einträge des Monats wurden erfolgreich gelöscht.");
                    location.reload(); // Seite neu laden, um die Änderungen anzuzeigen
                } else {
                    alert("Fehler beim Löschen der Einträge: " + xhr.responseText);
                }
            };
            xhr.send(`month=${<?= $month ?>}&year=${<?= $year ?>}`);
        }
    }
</script>

<!--Alle Benutzereinträge löschen-->
<script>
    function deleteAllUserEntries() {
        if (confirm("Möchtest Du wirklich alle Einträge löschen? Diese Aktion kann nicht rückgängig gemacht werden!")) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "delete_all_user_entries.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    alert("Alle Einträge wurden erfolgreich gelöscht.");
                    location.reload(); // Seite neu laden, um die Änderungen anzuzeigen
                } else {
                    alert("Fehler beim Löschen der Einträge: " + xhr.responseText);
                }
            };
            xhr.send();
        }
    }
</script>
