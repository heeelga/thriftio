<?php
require('dbconnection.php');

// Sprache aus der Umgebungsvariable oder Standardwert 'de'
$language = getenv('LANGUAGE') ?: 'de';

// Passende Sprachdatei für lost.php laden (lost_de.json bzw. lost_en.json)
$languageFile = __DIR__ . "/languages/lost_{$language}.json";
if (file_exists($languageFile)) {
    $translations = json_decode(file_get_contents($languageFile), true);
} else {
    $translations = json_decode(file_get_contents(__DIR__ . "/languages/lost_de.json"), true);
}

// Cloudflare Turnstile Schlüssel aus den Umgebungsvariablen laden und trimmen
$turnstile_sitekey = trim(getenv('TURNSTILE_SITEKEY') ?: 'default_site_key');
$turnstile_secret  = trim(getenv('TURNSTILE_SECRET') ?: 'default_secret');

$outputMessage = ""; // Variable für Statusmeldungen

if (isset($_POST['email'])) {
    // Cloudflare Turnstile Überprüfung: Token aus dem versteckten Feld auslesen
    if (isset($_POST['cf-turnstile-response']) && !empty($_POST['cf-turnstile-response'])) {
        $token = $_POST['cf-turnstile-response'];
//        error_log('Turnstile token received: ' . $token);
    } else {
        $outputMessage = $translations['captcha_missing'] ?? 'Bitte bestätigen Sie, dass Sie kein Roboter sind.';
    }
    
    if (empty($outputMessage)) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $verifyUrl = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
        $data = http_build_query([
          'secret'   => $turnstile_secret,
          'response' => $token,
          'remoteip' => $ip
        ]);
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $data,
            ],
        ];
        $context  = stream_context_create($options);
        $response = file_get_contents($verifyUrl, false, $context);
        $responseKeys = json_decode($response, true);
//        error_log('Turnstile response: ' . print_r($responseKeys, true));
        
        if (!isset($responseKeys["success"]) || !$responseKeys["success"]) {
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
            // Übersetzte Mail-Inhalte einsetzen (mithilfe von sprintf, um Variablen einzusetzen)
            $mail->Subject = $translations['mail_subject'];
            $mail->Body    = sprintf($translations['mail_body_html'], $resetfirstname, $resetusername, $randompwd);
            $mail->AltBody = sprintf($translations['mail_body_alt'], $resetfirstname, $resetusername, $randompwd);

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
    <center>
    <title><?php echo $translations['page_title'] ?? 'Reset Password'; ?></title>
    <!-- Cloudflare Turnstile Script laden -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
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
        <!-- Hidden Feld für Turnstile Token -->
        <input type="hidden" id="cf-turnstile-response" name="cf-turnstile-response">
        <!-- Cloudflare Turnstile Widget (mit "compact" Größe) -->
        <div class="cf-turnstile" data-sitekey="<?php echo $turnstile_sitekey; ?>" data-size="compact" data-callback="onTurnstileSuccess"></div>
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
</center>
<script>
// Falls das Token noch nicht gesetzt ist, blockieren wir das Abschicken des Formulars
document.querySelector('.login-form').addEventListener('submit', function(e) {
    if (document.getElementById('cf-turnstile-response').value === "") {
        e.preventDefault();
        alert("<?php echo $translations['captcha_alert'] ?? 'Bitte verifiziere, dass du ein Mensch bist.'; ?>");
    }
});
// Callback, wenn die Challenge erfolgreich gelöst wurde
function onTurnstileSuccess(token) {
//    console.log("Turnstile token received:", token);
    document.getElementById('cf-turnstile-response').value = token;
}
</script>
</body>
</html>
