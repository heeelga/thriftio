<?php 
include('init.php');
require('dbconnection.php');
$username = $_SESSION['username'];
$msg = '';
$passwordChanged = false; // Flag, ob Kennwort erfolgreich geändert wurde

if (isset($_POST['current_password'])) {
    $current_password = stripslashes($_POST['current_password']);
    $current_password = mysqli_real_escape_string($conn, $current_password);

    $new_password1 = stripslashes($_POST['new_password1']);
    $new_password1 = mysqli_real_escape_string($conn, $new_password1);

    $new_password2 = stripslashes($_POST['new_password2']);
    $new_password2 = mysqli_real_escape_string($conn, $new_password2);

    // Überprüfe, ob das aktuelle Kennwort korrekt ist
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
        // Aktualisiere das Kennwort und setze changed_password auf 1
        $new_password_bcrypt = password_hash($new_password1, PASSWORD_BCRYPT);
        $query_update = "UPDATE `user` SET password='$new_password_bcrypt', changed_password=1 WHERE username='$username'";
        $result_update = mysqli_query($conn, $query_update);

        if ($result_update) {
            $_SESSION['changed_password'] = 1;
            $msg = $translations['change_pass_success']; // Hier sollte der Text z. B. "Erfolgreich" bzw. "Succesfull" stehen
            $passwordChanged = true;
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
    .container {
      margin-top: 100px;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 20px;
      height: auto;
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

    /* Styles für den Fortschrittskreis */
    .progress-wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-top: 50px;
    }
    .progress-ring {
      transform: rotate(-90deg);
    }
    .progress-ring__circle {
      stroke-dasharray: 314; /* 2 * π * r (r=50) */
      stroke-dashoffset: 314;
      animation: progress 2s linear forwards;
    }
    @keyframes progress {
      to {
        stroke-dashoffset: 0;
      }
    }
    .progress-text {
      font-size: 16px;
      fill: #007aff;
      transform: rotate(90deg);
    }
  </style>
  
  <?php if($passwordChanged) { ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Nach 5 Sekunden Weiterleitung zu index.php
      setTimeout(function(){
        window.location.href = "index.php";
      }, 2000);
    });
  </script>
  <?php } ?>
</head>
<body>
  <div class="container">
    <div class="form-container">
      <?php if($passwordChanged) { ?>
        <h2><?php echo $translations['change_pass_success']; ?></h2>
        <!-- Fortschrittsanzeige -->
        <div class="progress-wrapper">
          <svg class="progress-ring" width="120" height="120">
            <circle class="progress-ring__circle" stroke="#007aff" stroke-width="8" fill="transparent" r="50" cx="60" cy="60"/>
            <text x="60" y="65" text-anchor="middle" class="progress-text"><?php echo $msg; ?></text>
          </svg>
        </div>
      <?php } else { ?>
        <form class="login-form" action="" method="post">
          <h2><?php echo $translations['change_pass']; ?></h2>
          <div class="form-group">
            <p class="success-message"><?php echo $msg; ?></p>
          </div>
          <div class="form-group">
            <input type="password" name="current_password" id="current_password" placeholder="<?php echo $translations['current_pass']; ?>" required>
          </div>
          <div class="form-group">
            <input type="password" name="new_password1" id="new_password1" placeholder="<?php echo $translations['new_pass']; ?>" required>
          </div>
          <div class="form-group">
            <input type="password" name="new_password2" id="new_password2" placeholder="<?php echo $translations['new_pass']; ?>" required>
          </div>
          <button class="change-button" type="submit"><?php echo $translations['change']; ?></button>
        </form>
        <br>
      <?php } ?>
    </div>
  </div>
</body>
</html>
