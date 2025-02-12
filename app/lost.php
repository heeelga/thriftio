<?php
require('dbconnection.php');

// Sprache aus der Umgebungsvariable oder Standardwert 'de'
$language = getenv('LANGUAGE') ?: 'de';

// Passende Sprachdatei laden
$languageFile = __DIR__ . "/languages/$language.json";
if (file_exists($languageFile)) {
    $translations = json_decode(file_get_contents($languageFile), true);
} else {
    $translations = json_decode(file_get_contents(__DIR__ . "/languages/de.json"), true);
}

// reCAPTCHA-Schlüssel aus den Umgebungsvariablen laden und trimmen
$recaptcha_sitekey = trim(getenv('RECAPTCHA_SITEKEY') ?: 'default_site_key');
$recaptcha_secret  = trim(getenv('RECAPTCHA_SECRET') ?: 'default_secret');

// Logging: Überprüfe, ob die Schlüssel korrekt geladen wurden
error_log('RECAPTCHA_SITEKEY: ' . $recaptcha_sitekey);
error_log('RECAPTCHA_SECRET: ' . $recaptcha_secret);

$outputMessage = ""; // Variable für Statusmeldungen

if (isset($_POST['email'])) {
    // reCAPTCHA-Überprüfung für v3: Token aus dem versteckten Feld auslesen
    if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
        $token = $_POST['g-recaptcha-response'];
        error_log('Token erhalten: ' . $token);
    } else {
        $outputMessage = $translations['captcha_missing'] ?? 'Bitte bestätigen Sie, dass Sie kein Roboter sind.';
    }
    
    if (empty($outputMessage)) {
        $ip = $_SERVER['REMOTE_ADDR'];
        // Anfrage an Google reCAPTCHA v3 API
        $verifyUrl = "https://www.google.com/recaptcha/api/siteverify?secret=" . urlencode($recaptcha_secret) . "&response=" . urlencode($token) . "&remoteip=" . urlencode($ip);
        $response = file_get_contents($verifyUrl);
        $responseKeys = json_decode($response, true);
        
        // Protokolliere die komplette Antwort von Google für Debug-Zwecke
        error_log('reCAPTCHA response: ' . print_r($responseKeys, true));
        
        // Für reCAPTCHA v3: Überprüfe success, Score und die erwartete Aktion
        if (
            !isset($responseKeys["success"]) || !$responseKeys["success"] ||
            !isset($responseKeys["score"]) || $responseKeys["score"] < 0.5 ||
            !isset($responseKeys["action"]) || $responseKeys["action"] !== 'reset_password'
        ) {
            $outputMessage = $translations['captcha_failed'] ?? 'Captcha Verifizierung fehlgeschlagen.';
        }
    }
    
    if (empty($outputMessage)) {
        // Weiter mit der Verarbeitung, da Captcha erfolgreich war.
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
            $smtp_host         = getenv('SMTP_HOST') ?: 'default_host';
            $smtp_user         = getenv('SMTP_USER') ?: 'default_user';
            $smtp_password     = getenv('SMTP_PASSWORD') ?: 'default_password';
            $smtp_sender       = getenv('SMTP_SENDER') ?: 'default_sender@example.com';
            $smtp_sender_name  = getenv('SMTP_SENDER_NAME') ?: 'Default Sender';
            $smtp_replyto      = getenv('SMTP_REPLYTO') ?: 'default_replyto@example.com';
            $smtp_replyto_name = getenv('SMTP_REPLYTO_NAME') ?: 'Default ReplyTo';

            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_password;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom($smtp_sender, $smtp_sender_name);
            $mail->addReplyTo($smtp_replyto, $smtp_replyto_name);

            // Empfänger hinzufügen
            $mail->addAddress($resetmail, $resetfirstname);

            $mail->isHTML(true);
            $mail->Subject = 'Dein ThriftIO-Account';
            $mail->Body    = '<p>Hallo ' . $resetfirstname . ',</p>
                              <p>das Kennwort f&uuml;r Deinen ThriftIO-Account wurde zur&uuml;ck gesetzt.</p>
                              <br>
                              <p>Dein neuer Benutzername lautet: <b>' . $resetusername . '</b></p>
                              <p>Dein neues Kennwort lautet: <b>' . $randompwd . '</b><br></p>
                              <p>Bitte &auml;ndere dies sobald wie m&ouml;glich!</p>
                              <p>Solltest Du das Zur&uuml;cksetzen Deines Kennworts nicht beauftragt haben,</p>
                              <p>schicke mir bitte eine Mail an die Dir bekannte Mailadresse</a>!</p>
                              <br>
                              <p>VG dein ThriftIO Admin</p>';

            $mail->AltBody = 'Hallo ' . $resetfirstname . ',
das Kennwort f&uuml;r Deinen ThriftIO-Account wurde zur&uuml;ck gesetzt.

Dein neuer Benutzername lautet: ' . $resetusername . '
Dein neues Kennwort lautet: ' . $randompwd . '
Bitte &auml;ndere dies sobald wie m&ouml;glich!
Solltest Du das Zur&uuml;cksetzen Deines Kennworts nicht beauftragt haben,
schicke mir bitte eine Mail an die Dir bekannte Mailadresse!

VG dein ThriftIO Admin';

            if (!$mail->send()) {
                $outputMessage = $translations['mail_not_sent'] ?? 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
            } else {
                $outputMessage = $translations['mail_sent'] ?? 'Message has been sent';
            }
        } else {
            $outputMessage = $translations['email_not_found'] ?? 'E-Mail address not found in our database.';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Login</title>
    <!-- reCAPTCHA v3-Script laden mit dynamischem Site Key -->
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo $recaptcha_sitekey; ?>"></script>
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
        <h2><?php echo $translations['reset_password'] ?? 'Reset password'; ?></h2>
        <div class="form-group">
          <input type="text" name="email" placeholder="<?php echo $translations['your_email'] ?? 'Your E-Mail address'; ?>" required />
        </div>
        <br>
        <!-- Verstecktes Feld für den reCAPTCHA-Token -->
        <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
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

<!-- JavaScript zum Abrufen des reCAPTCHA v3-Tokens -->
<script>
grecaptcha.ready(function() {
    document.querySelector('.login-form').addEventListener('submit', function(e) {
        e.preventDefault(); // Verhindert das direkte Abschicken
        grecaptcha.execute('<?php echo $recaptcha_sitekey; ?>', {action: 'reset_password'}).then(function(token) {
            console.log('Token received:', token);
            document.getElementById('g-recaptcha-response').value = token;
            e.target.submit();
        }).catch(function(error) {
            console.error('Error retrieving token:', error);
        });
    });
});
</script>
</body>
</html>
