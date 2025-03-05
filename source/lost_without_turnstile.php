<?php
require('dbconnection.php');

// Sprache aus der Umgebungsvariable oder Standardwert 'de'
$language = getenv('LANGUAGE') ?: 'de';

// Passende Sprachdatei laden (z.B. de.json oder en.json)
$languageFile = __DIR__ . "/languages/{$language}.json";
if (file_exists($languageFile)) {
    $translations = json_decode(file_get_contents($languageFile), true);
} else {
    $translations = json_decode(file_get_contents(__DIR__ . "/languages/de.json"), true);
}

// Logging
$logMessage = "LANGUAGE set to: " . $language;
error_log($logMessage);

// APP_EMAIL aus der Umgebung auslesen
$appEmail = getenv('APP_EMAIL') ?: 'default@example.com';

$outputMessage = ""; // Variable für Statusmeldungen

if (isset($_POST['email'])) {
    $email = stripslashes($_REQUEST['email']);
    $email = mysqli_real_escape_string($conn, $email);

    // Überprüfen, ob der Benutzer in der Datenbank existiert
    $query = "SELECT * FROM `user` WHERE email='$email'";
    $result = mysqli_query($conn, $query) or die(mysqli_error($conn));
    $row = mysqli_fetch_array($result);
    
    if ($row) {
        $resetid = $row['id'];
        $resetmail = $row['email'];
        $resetfirstname = $row['firstname'];
        $resetusername = $row['username'];

        function generateRandomString($length = 16) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }

        $randompwd = generateRandomString();
        
        // Neues Passwort mit bcrypt hashen
        $hashedPwd = password_hash($randompwd, PASSWORD_BCRYPT);
        $query2 = "UPDATE `user` SET password='" . $hashedPwd . "' WHERE id='$resetid'";
        $result2 = mysqli_query($conn, $query2);

        // Mail - Anfang 
        require 'phpmailer/PHPMailerAutoload.php';

        $mail = new PHPMailer;
        $mail->SMTPDebug = 0;
        $mail->isSMTP();

        // SMTP-Konfigurationswerte aus Umgebungsvariablen auslesen
        $smtp_host     = getenv('SMTP_HOST') ?: 'default_host';
        $smtp_user     = getenv('SMTP_USER') ?: 'default_user';
        $smtp_password = getenv('SMTP_PASSWORD') ?: 'default_password';
        // Hier wird APP_EMAIL als Absender verwendet, daher entfällt die Nutzung von SMTP_SENDER
        // Ebenso setzen wir den Absendernamen auf "ThriftIO"
        
        $smtp_replyto = getenv('SMTP_REPLYTO') ?: 'default_replyto@example.com';

        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom($appEmail, "ThriftIO");
        $mail->addReplyTo($appEmail, "ThriftIO");

        // Empfänger hinzufügen
        $mail->addAddress($resetmail, $resetfirstname);

        $mail->isHTML(true);
        // Übersetzte Mail-Inhalte mit Platzhaltern:
        // 1. Empfängername, 2. neuer Benutzername, 3. neues Kennwort,
        // 4. APP_EMAIL, 5. Absendername ("ThriftIO")
        $mail->Subject = sprintf($translations['mail_subject'], "ThriftIO");
        $mail->Body    = sprintf($translations['mail_body_html'], $resetfirstname, $resetusername, $randompwd, $appEmail, "ThriftIO");
        $mail->AltBody = sprintf($translations['mail_body_alt'], $resetfirstname, $resetusername, $randompwd, $appEmail, "ThriftIO");

        if (!$mail->send()) {
            $outputMessage = $translations['mail_not_sent'] ?? 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
        } else {
            $outputMessage = $translations['mail_sent'] ?? 'Message has been sent';
        }
    } else {
        // Falls die eingegebene E-Mail-Adresse nicht in der DB gefunden wurde
        $outputMessage = $translations['email_not_found'] ?? 'E-Mail address not found in our database.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title><?php echo $translations['page_title'] ?? 'Reset Password'; ?></title>
    <style>
        .login-form button {
            margin-bottom: 10px;
        }
        .message {
            margin-top: 15px;
            color: green;
        }
        .error {
            margin-top: 15px;
            color: red;
        }
    </style>
    <link rel="stylesheet" href="login.css" />
</head>
<body>
<div class="content">
  <div class="container">
    <div class="form-container">
      <form class="login-form" action="" method="post">
        <h2><?php echo $translations['reset_password'] ?? 'Reset Password'; ?></h2>
        <div class="form-group">
          <input type="text" name="email" placeholder="<?php echo $translations['your_email'] ?? 'Your E-Mail address'; ?>" required />
        </div>
        <br>
        <center>
          <button type="submit" value="Reset" class="login-button"><?php echo $translations['submit'] ?? 'Submit'; ?></button>
        </center>
      </form>
      <?php if (!empty($outputMessage)): ?>
          <div class="<?php echo (strpos($outputMessage, 'could not') !== false) ? 'error' : 'message'; ?>">
              <?php echo $outputMessage; ?>
          </div>
      <?php endif; ?>
      <div class="forgot-password">
          <a href="login.php"><?php echo $translations['back_to_login'] ?? 'Back to Login'; ?></a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
