<?php
include('init.php');
require('dbconnection.php');
include('menubar.php');
include('global_functions.php');
include('style.css');

// Prüfen, ob der eingeloggte Benutzer Admin ist
$stmt = $conn->prepare("SELECT admin FROM user WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['admin'] != 1) {
        echo '<meta http-equiv="refresh" content="0;url=index.php">';
        exit();
    }
} else {
    echo '<meta http-equiv="refresh" content="0;url=index.php">';
    exit();
}
?>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Site</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <link rel="apple-touch-icon" href="/images/icon_small.jpg">
  <link rel="icon" href="images/icon_small.jpg" type="image/jpg">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Einbindung von Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  
  <style>
    /* Formularelemente, Buttons & Co. */
    form table td {
        display: block;
        width: 100%;
        margin-bottom: 15px;
    }
    input[type="text"],
    input[type="email"],
    input[type="password"] {
        width: 100%;
        padding: 10px;
        font-size: 16px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 15px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus {
        border-color: #007aff;
        box-shadow: 0px 2px 8px rgba(0, 122, 255, 0.3);
        outline: none;
    }
    select {
        width: 100%;
        padding: 10px;
        font-size: 16px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 15px;
        background-color: #fff;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    select:focus {
        border-color: #007aff;
        box-shadow: 0px 2px 8px rgba(0, 122, 255, 0.3);
        outline: none;
    }
    button,
    input[type="submit"] {
        display: inline-block;
        padding: 10px 20px;
        font-size: 16px;
        font-weight: bold;
        color: #fff;
        background-color: #007aff;
        border: none;
        border-radius: 5px;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }
    button:hover,
    input[type="submit"]:hover {
        background-color: #005bb5;
        transform: scale(1.02);
    }
    button:active,
    input[type="submit"]:active {
        background-color: #003f8a;
        transform: scale(0.95);
    }
    textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    /* Container für den Admin-Bereich */
    .admin-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 0 10px;
    }
    
    /* Styling der entry-boxen (angepasst an die Hauptseite) */
    .entry-box, .total-box {
      color: #000000; /* Feste Schriftfarbe im Light Mode */
      background: linear-gradient(to bottom, var(--background-color), #f0f0f0);
      border: 1px solid #ccc;
      border-radius: 10px;
      padding: 1rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      margin-bottom: 20px;
    }
    .entry-box:hover, .total-box:hover {
      transform: scale(1.02);
      box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
    }
    .dark-theme .entry-box, .dark-theme .total-box {
      background: linear-gradient(to bottom, #2a2a2a, #3a3a3a);
      color: #ffffff;
      border: 1px solid #555555;
      border-radius: 10px;
      padding: 10px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    }
    .dark-theme .entry-box:hover, .dark-theme .total-box:hover {
      transform: scale(1.02);
      box-shadow: 0 6px 8px rgba(0, 0, 0, 0.4);
    }
    .entry-box.selected {
        border: 2px solid #007BFF;
        background-color: rgba(0, 123, 255, 0.1);
    }
    /* Reduzierte Höhe für die Eintragsboxen */
    .entry-box {
        position: relative;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
        font-size: 15px;
        line-height: 1.2;
    }
    
    /* Benutzerliste: Tabelle mit einheitlicher Ausrichtung */
    .user-list-table {
        width: 100%;
        border-collapse: collapse;
        color: inherit;
    }
    .user-list-table th,
    .user-list-table td {
        padding: 8px;
        text-align: left;
        color: inherit;
    }
    
    /* Zusätzliche Formatierung für die Tabelle innerhalb der neuen Einladung-Box */
    .invite-table th, .invite-table td {
      padding: 5px;
      text-align: left;
    }
  </style>
  
  <?php
  // Zweite Prüfung, ob der Benutzer Admin ist
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  require 'dbconnection.php';
  $username = $_SESSION['username'];
  $sql = "SELECT admin FROM user WHERE username = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
      $row = $result->fetch_assoc();
      if ($row['admin'] == 1) {
          // Admin: alles ok
      } else {
          header("Location: index.php");
          exit();
      }
  } else {
      header("Location: index.php");
      exit();
  }
  $stmt->close();
  ?>
  
</head>
<body>
<center>
  <div class="admin-container">
  
    <!-- Box 1: Release Notes -->
    <div class="entry-box">
      <h3><?php echo $translations['new_release_notes']; ?></h3>
      <?php
      if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_notes'])) {
          $notes = $_POST['release_notes'];
          file_put_contents('release_notes.txt', $notes);
          $updateQuery = "UPDATE user SET release_notes_available = TRUE, release_notes_read = FALSE";
          if ($conn->query($updateQuery) === TRUE) {
              echo "Release Notes gespeichert und an alle User verteilt!";
          } else {
              echo "Fehler beim Aktualisieren der User: " . $conn->error;
          }
      }
      ?>
      <form method="POST">
        <textarea name="release_notes" rows="10" cols="50" placeholder='<?php echo $translations['set_release_notes']?>'></textarea>
        <br><br>
        <button type="submit" name="save_notes"><?php echo $translations['save']; ?></button>
      </form>
    </div>
    
    <!-- Box 2: Benutzerliste -->
    <div class="entry-box">
      <h3><?php echo $translations['user_list']; ?></h3>
      <?php
      $query = "SELECT id, username, firstname, email FROM user";
      $result = mysqli_query($conn, $query) or die(mysqli_error($conn));
      if (mysqli_num_rows($result) > 0) {
          echo "<table class='user-list-table'>";
          echo "<tr>";
          echo "<th>ID</th>";
          echo "<th>Username</th>";
          echo "<th>Name</th>";
          echo "<th>E-Mail</th>";
          echo "</tr>";
          while ($row = mysqli_fetch_array($result)) {
              echo "<tr>";
              echo "<td><b>" . $row['id'] . "</b></td>";
              echo "<td>" . $row['username'] . "</td>";
              echo "<td>" . $row['firstname'] . "</td>";
              echo "<td>" . $row['email'] . "</td>";
              echo "</tr>";
          }
          echo "</table>";
          mysqli_free_result($result);
      } else {
          echo "Keine Benutzer gefunden.";
      }
      ?>
    </div>
    
    <!-- Box 3: Benutzerverwaltung -->
    <div class="entry-box">
      <h3><?php echo $translations['user_administration']; ?></h3>

      <h4><?php echo $translations['create_user']; ?></h4>
      <?php
      if (isset($_POST['submit_user'])) {
          $username = stripslashes($_POST['username']);
          $username = mysqli_real_escape_string($conn, $username);
          $email = stripslashes($_POST['email']);
          $email = mysqli_real_escape_string($conn, $email);
          $password = stripslashes($_POST['password']);
          $password = mysqli_real_escape_string($conn, $password);
          $firstname = stripslashes($_POST['firstname']);
          $firstname = mysqli_real_escape_string($conn, $firstname);

          $hashed_password = password_hash($password, PASSWORD_BCRYPT);
          $query = "INSERT INTO user (username, firstname, email, password) VALUES ('$username', '$firstname', '$email', '$hashed_password')";
          $result = mysqli_query($conn, $query);
          if ($result) {
              header("Location: admin.php");
          }
      }
      ?>
      <form name="registration" action="" method="post">
        <table>
          <tr>
            <td><input type="text" name="username" placeholder='<?php echo $translations['username']?>' required /></td>
            <td><input type="text" name="firstname" placeholder='<?php echo $translations['name']?>' required /></td>
            <td><input type="email" name="email" placeholder='<?php echo $translations['email_address']?>' required /></td>
          </tr>
          <tr>
            <td><input type="password" name="password" value="chang3m3!" readonly required/></td>
          </tr>
          <tr>
            <td>
              <center>
                <input type="submit" name="submit_user" value="<?php echo $translations['save']; ?>" />
              </center>
            </td>
          </tr>
        </table>
      </form>
      <br>
      
      <!-- Benutzer löschen -->
      <h4><?php echo $translations['delete_user']; ?></h4>
      <form name="edituser" action="" method="post">
        <table>
          <tr>
            <td>
              <select name="ids" class="select-css">
                <option selected="selected">ID</option>
                <?php
                $query = "SELECT id FROM user";
                $result = mysqli_query($conn, $query);
                while ($row = mysqli_fetch_assoc($result)) {
                    echo '<option value="' . $row['id'] . '">' . $row['id'] . '</option>';
                }
                ?>
              </select>
            </td>
          </tr>
          <tr>
            <td><input type="submit" name="delete" value="<?php echo $translations['delete']; ?>" style="background-color: #ff0000;"/></td>
          </tr>
        </table>
      </form>
    </div>
    
    <!-- Box 4: Einladungs-Funktion (aus invite.php) -->
    <div class="entry-box">
      <h3><?php echo $translations['user_without_invitation']; ?></h3>
      <?php
      // Hier beginnt der Code aus invite.php (ohne Includes und HTML-Struktur)

      // Nutzer ohne Einladung laden
      $query = "SELECT * FROM `user` WHERE `invite` IS NULL";
      $result = mysqli_query($conn, $query) or die(mysql_error());

      if (mysqli_num_rows($result) > 0) {
          echo "<table class='invite-table'>";
          echo "<tr>";
          echo "<th>ID</th>";
          echo "<th>Username</th>";
          echo "<th>&nbsp;</th>";
          echo "<th>Name</th>";
          echo "<th>&nbsp;</th>";
          echo "<th>E-Mail</th>";
          echo "<th>&nbsp;</th>";
          echo "</tr>";

          // Array definieren, um IDs zu sammeln
          $edituser = [];

          while ($row = mysqli_fetch_array($result)) {
              $del_id = $row['id'];
              $edituser[] = $row['id'];
              echo "<tr>";
              echo "<td><b>" . $row['id'] . "</b></td>";
              echo "<td>" . $row['username'] . "</td>";
              echo "<td>&nbsp;</td>";
              echo "<td>" . $row['firstname'] . "</td>";
              echo "<td>&nbsp;</td>";
              echo "<td>" . $row['email'] . "</td>";
              echo "<td>&nbsp;</td>";
              echo "</tr>";
          }
          echo "</table>";
          mysqli_free_result($result);
      } else {
          echo $translations['everyone_invited'];
      }
      ?>
      <br>
      <div class="form">
        <form name="edituser" action="" method="post">
          <table>
            <tr>
              <td>
                <!--Dropdown für ID -->  
                <select name="ids" class="select-css">
                  <option selected="selected">ID</option>
                  <?php
                  if (!empty($edituser)) {
                      foreach($edituser as $item) {
                          // ID 1 überspringen, falls nötig
                          if ($item == 1) {
                              continue;
                          } else {
                              echo '<option value="' . strtolower($item) . '">' . $item . '</option>';
                          }
                      }
                  }
                  ?>
                </select>
              </td>    
            </tr>
            <tr>
              <td>
                <input type="submit" name="sendinvite" value="<?php echo $translations['send_invitation']; ?>" />
              </td>    
            </tr>
          </table>
        </form>
      </div>

      <?php
      if (isset($_REQUEST['sendinvite'])) {
          $auswahl = $_POST['ids'];

          // Query ausführen
          $query3 = "SELECT * FROM `user` WHERE id='$auswahl'";
          $result3 = mysqli_query($conn, $query3) or die(mysqli_error($conn));
          $row = mysqli_fetch_array($result3);

          $name = $row['username'];
          $vorname = $row['firstname'];
          $email = $row['email'];
          $password = "chang3m3!"; // Festes Klartextpasswort
          $base_url = getenv('BASE_URL') ?: 'https://default.example.com'; // BASE_URL aus Umgebungsvariablen oder Standard
          $smtp_replyto = getenv('SMTP_REPLYTO') ?: 'default_replyto@example.com'; // Reply-To-Adresse

          // Debugging-Ausgabe
          echo "Debugging Daten:";
          echo "<br>Name: $name";
          echo "<br>Vorname: $vorname";
          echo "<br>E-Mail: $email";
          echo "<br>Password: $password<br>";
          echo "<br>BASE_URL: $base_url<br>";
          echo "<br>SMTP Reply-To: $smtp_replyto<br>";

          // Mail-Anfang
          require 'phpmailer/PHPMailerAutoload.php';

          $mail = new PHPMailer;

          $mail->SMTPDebug = 0; 
          $mail->isSMTP(); 

          // SMTP-Konfigurationswerte aus Umgebungsvariablen
          $smtp_host = getenv('SMTP_HOST') ?: 'default_host';
          $smtp_user = getenv('SMTP_USER') ?: 'default_user';
          $smtp_password = getenv('SMTP_PASSWORD') ?: 'default_password';
          $smtp_sender = getenv('SMTP_SENDER') ?: 'default_sender@example.com';
          $smtp_sender_name = getenv('SMTP_SENDER_NAME') ?: 'Default Sender';

          // Debugging SMTP-Konfiguration
          echo "<br>SMTP Debugging:";
          echo "<br>SMTP Host: $smtp_host";
          echo "<br>SMTP User: $smtp_user";
          echo "<br>SMTP Sender: $smtp_sender<br>";

          // SMTP-Konfiguration
          $mail->Host = $smtp_host;
          $mail->SMTPAuth = true;
          $mail->Username = $smtp_user;
          $mail->Password = $smtp_password;
          $mail->SMTPSecure = 'tls';
          $mail->Port = 587;

          $mail->setFrom($smtp_sender, $smtp_sender_name);
          $mail->addReplyTo($smtp_replyto, 'Support');
          $mail->addAddress($email, $vorname . " " . $name);

          $mail->isHTML(true); // HTML-Format

          // Sprache aus der Umgebungsvariablen oder Standardwert 'de'
          $language = getenv('LANGUAGE') ?: 'de';
          $mailFile = __DIR__ . "/languages/mail_$language.json";

          if (file_exists($mailFile)) {
              $mailContent = json_decode(file_get_contents($mailFile), true);
          } else {
              // Fallback auf Deutsch
              $mailContent = json_decode(file_get_contents(__DIR__ . "/languages/mail_de.json"), true);
          }

          // Platzhalter einfügen
          $mailSubject = $mailContent['subject'];
          $mailBodyHtml = str_replace(
              ['{vorname}', '{name}', '{password}', '{base_url}', '{smtp_replyto}'],
              [htmlspecialchars($vorname), htmlspecialchars($name), htmlspecialchars($password), htmlspecialchars($base_url), htmlspecialchars($smtp_replyto)],
              $mailContent['body_html']
          );
          $mailBodyText = str_replace(
              ['{vorname}', '{name}', '{password}', '{base_url}', '{smtp_replyto}'],
              [$vorname, $name, $password, $base_url, $smtp_replyto],
              $mailContent['body_text']
          );

          $mail->Subject = $mailSubject;
          $mail->Body = $mailBodyHtml;
          $mail->AltBody = $mailBodyText;

          if (!$mail->send()) {
              echo "<br><strong>Fehler beim Senden der Mail:</strong> " . $mail->ErrorInfo;
          } else {
              echo "<br><strong>Mail erfolgreich gesendet!</strong>";
          }

          // Datenbank-Update
          $query4 = "UPDATE `user` SET invite = '1' WHERE id='$auswahl'";
          $result4 = mysqli_query($conn, $query4) or die(mysqli_error($conn));

          // Zurück auf admin.php
          header("Location: admin.php");
          exit();
      }

      // Ende des invite.php-Codes
      ?>
    </div>
    
  </div>
</center>
<?php
// Benutzer löschen, wenn gewünscht
if (isset($_POST['delete'])) {
    $auswahl = $_POST['ids'];
    $query2 = "DELETE FROM user WHERE id='$auswahl'";
    $result2 = mysqli_query($conn, $query2);
    header("Location: admin.php");
    exit();
}
?>
</body>
</html>
