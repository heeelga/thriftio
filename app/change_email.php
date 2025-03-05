<?php
include('init.php');
include('dbconnection.php');
include('global_functions.php');
include('menubar.php');


// Prüfe, ob der Benutzer eingeloggt ist und das Passwort noch nicht geändert wurde
if (isset($_SESSION['username']) && isset($_SESSION['changed_password']) && $_SESSION['changed_password'] == 0) {
    // Um Endlosschleifen zu vermeiden, leite nicht um, wenn wir bereits in change_pass.php sind
    if (basename($_SERVER['PHP_SELF']) !== 'change_pass.php') {
        header("Location: change_pass.php");
        exit;
    }
}

$username = $_SESSION['username'];
$msg = '';

if (isset($_POST['current_email'])) {
    $current_email = stripslashes($_POST['current_email']);
    $current_email = mysqli_real_escape_string($conn, $current_email);

    $new_email = stripslashes($_POST['new_email']);
    $new_email = mysqli_real_escape_string($conn, $new_email);

    // Check if current email is correct
    $query_check = "SELECT email FROM `user` WHERE username='$username'";
    $result_check = mysqli_query($conn, $query_check);
    $row_check = mysqli_fetch_assoc($result_check);
    $stored_email = $row_check['email'];

    if ($current_email !== $stored_email) {
        $msg = "Die aktuelle E-Mail-Adresse ist nicht korrekt.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Die neue E-Mail-Adresse hat kein korrektes Format.";
    } else {
        // Update email
        $query_update = "UPDATE `user` SET email='$new_email' WHERE username='$username'";
        $result_update = mysqli_query($conn, $query_update);

        if ($result_update) {
            $msg = "Die E-Mail-Adresse wurde erfolgreich geändert.";
            echo '<script>showMessage("'.$msg.'");</script>';
        } else {
            $msg = "Fehler beim Ändern der E-Mail-Adresse.";
        }
    }
}

// Fetch the current email address
$query_fetch = "SELECT email FROM `user` WHERE username='$username'";
$result_fetch = mysqli_query($conn, $query_fetch);
$row_fetch = mysqli_fetch_assoc($result_fetch);
$current_email = $row_fetch['email'];
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
    window.location.href = "login.php";
  }
</script>
</head>
<body>

<div class="container">
  <div class="form-container">
    <form class="login-form" action="" method="post">
      <h2><?php echo $translations['change_email']; ?></h2>
      <div class="form-group">
        <p class="success-message"><?php echo $msg; ?></p>
      </div>
      <div class="form-group">
        <input type="email" name="current_email" id="current_email" placeholder="Aktuelle E-Mail-Adresse" value="<?php echo $current_email; ?>" required readonly class="rounded-input">
      </div>
      <div class="form-group">
        <input type="email" name="new_email" id="new_email" placeholder='<?php echo $translations['new_email']?>' required class="rounded-input">
      </div>
      <button class="change-button" button type="submit" class="login-button"><?php echo $translations['change']; ?></button>
    </form>
    <br>
<div class="back-button-container">
    <a href="account.php" class="btn-back" aria-label="Zurück"></a>
</div></div></div>

</body>
</html>