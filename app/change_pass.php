<?php
include('init.php');
require('dbconnection.php');
include('menubar.php');
include('global_functions.php');
$username = $_SESSION['username'];
$msg = '';

if (isset($_POST['current_password'])) {
    $current_password = stripslashes($_POST['current_password']);
    $current_password = mysqli_real_escape_string($conn, $current_password);

    $new_password1 = stripslashes($_POST['new_password1']);
    $new_password1 = mysqli_real_escape_string($conn, $new_password1);

    $new_password2 = stripslashes($_POST['new_password2']);
    $new_password2 = mysqli_real_escape_string($conn, $new_password2);

    // Check if current password is correct
    $query_check = "SELECT password FROM `user` WHERE username='$username'";
    $result_check = mysqli_query($conn, $query_check);
    $row_check = mysqli_fetch_assoc($result_check);
    $stored_password = $row_check['password'];

    if (!password_verify($current_password, $stored_password)) {
        $msg = $translations['current_pass_wrong'];
    } elseif ($new_password1 !== $new_password2) {
        $msg = $translations['change_pass_no_match'];
    } elseif (strlen($new_password1) < 8 || !preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $new_password1)) {
        $msg = $translations['change_pass_unsecure'];
    } elseif ($new_password1 === 'chang3m3!' || hasInvalidExpression($new_password1)) {
        $msg = "Das neue Kennwort enthält nicht erlaubte Ausdrücke und ist unsicher.";
    } else {
        // Update password and set changed_password to 1
        $new_password_bcrypt = password_hash($new_password1, PASSWORD_BCRYPT);
        $query_update = "UPDATE `user` SET password='$new_password_bcrypt', changed_password=1 WHERE username='$username'";
        $result_update = mysqli_query($conn, $query_update);

        if ($result_update) {
            // Session-Variable aktualisieren, sodass der Nutzer danach vollen Zugriff hat
            $_SESSION['changed_password'] = 1;
            $msg = $translations['change_pass_success'];
            echo '<script>showMessage("'.$msg.'");</script>';
        } else {
            $msg = "Fehler beim Ändern des Kennworts.";
        }
    }
}

function hasInvalidExpression($password) {
    $invalidExpressions = array('abcd', 'passwort', 'password', 'passw0rd', 'admin', 'xyz', '1234');

    foreach ($invalidExpressions as $expression) {
        if (stripos($password, $expression) !== false) {
            return true;
        }
    }

    return false;
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="style.css" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  
<style>
.back-button-container {
    text-align: center; /* Zentriert den Button innerhalb des Containers */
    margin-top: 20px;
}

.btn-back {
    display: inline-block;
    width: 50px; /* Breite des Buttons */
    height: 50px; /* Höhe des Buttons (macht es rund mit border-radius) */
    border-radius: 50%; /* Runder Button */
    background-color: #007BFF; /* Blaue Hintergrundfarbe */
    color: white; /* Farbe des Pfeils */
    text-align: center;
    line-height: 50px; /* Zentriert den Pfeil vertikal */
    font-size: 24px; /* Größe des Pfeils */
    font-weight: bold;
    text-decoration: none; /* Entfernt die Unterstreichung */
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.btn-back::before {
    content: "←"; /* Zurück-Pfeil */
}

.btn-back:hover {
    background-color: #0056b3; /* Dunkleres Blau bei Hover */
    transform: translateY(-2px); /* Leichter Hover-Effekt */
}

.btn-back:active {
    background-color: #004080; /* Noch dunkler bei Aktivierung */
    transform: translateY(0); /* Rücksetzen bei Klick */
}

    .container {
      margin-top: 100px; /* Exakter Platz für die Navbar */
      display: flex;
      justify-content: center;
      align-items: flex-start; /* Inhalt direkt unter der Navbar */
      padding: 20px;
      height: auto; /* Verhindert unnötigen Platz */
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

<script>
  function showMessage(message) {
    alert(message);
    // Nach erfolgreicher Änderung wird auf account.php weitergeleitet.
    window.location.href = "account.php";
  }
</script>
</head>
<body>

<div class="container">
  <div class="form-container">
    <form class="login-form" action="" method="post">
      <h2><?php echo $translations['change_pass']?></h2>
      <div class="form-group">
        <p class="success-message"><?php echo $msg; ?></p>
      </div>
      <div class="form-group">
        <input type="password" name="current_password" id="current_password" placeholder='<?php echo $translations['current_pass']?>' required>
      </div>
      <div class="form-group">
        <input type="password" name="new_password1" id="new_password1" placeholder='<?php echo $translations['new_pass']?>' required>
      </div>
      <div class="form-group">
        <input type="password" name="new_password2" id="new_password2" placeholder='<?php echo $translations['new_pass']?>' required>
      </div>
      <button class="change-button" type="submit"><?php echo $translations['change']; ?></button>
    </form>
    <br>
    <div class="back-button-container">
      <a href="account.php" class="btn-back" aria-label="Zurück"></a>
    </div>
  </div>
</div>
</body>
</html>
