<?php
ob_start();
include('init.php');
include("auth.php"); //include auth.php file on all secure pages
require('dbconnection.php');
include('menubar.php');
include('style.css');


// Verbindung zur Datenbank herstellen
require_once 'dbconnection.php'; // Passen Sie den Pfad entsprechend Ihrer Datei an

$username = $_SESSION['username'] ?? null;

if (!$username) {
    // Kein Benutzer eingeloggt
    echo '<meta http-equiv="refresh" content="0;url=index.php">';
    exit();
}

// Prüfen, ob der eingeloggte Benutzer Admin ist
$stmt = $conn->prepare("SELECT admin FROM user WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['admin'] != 1) {
        // Benutzer ist kein Admin
        echo '<meta http-equiv="refresh" content="0;url=index.php">';
        exit();
    }
} else {
    // Benutzer nicht gefunden
    echo '<meta http-equiv="refresh" content="0;url=index.php">';
    exit();
}

// Admin darf bleiben
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="buttons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

<style>
/* Felder für Benutzernamen, Vornamen und E-Mail untereinander anordnen */
form table td {
    display: block; /* Blockelement, damit Felder untereinander stehen */
    width: 100%; /* Volle Breite */
    margin-bottom: 15px; /* Abstand zwischen den Feldern */
}

/* Anpassung für Textfelder */
input[type="text"],
input[type="email"],
input[type="password"] {
    width: 100%; /* Volle Breite */
    padding: 10px; /* Innenabstand */
    font-size: 16px; /* Schriftgröße */
    border: 1px solid #ccc; /* Rahmenfarbe */
    border-radius: 5px; /* Abgerundete Ecken */
    box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1); /* Leichter Schatten */
    margin-bottom: 15px; /* Abstand zwischen den Feldern */
    transition: border-color 0.3s ease, box-shadow 0.3s ease; /* Weicher Übergang */
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus {
    border-color: #007aff; /* Rahmenfarbe bei Fokus */
    box-shadow: 0px 2px 8px rgba(0, 122, 255, 0.3); /* Schattenfarbe bei Fokus */
    outline: none; /* Entfernt den Standard-Fokusrahmen */
}


/* Dropdown-Menü-Styling */
select {
    width: 100%; /* Füllt den verfügbaren Platz */
    padding: 10px; /* Innenabstand */
    font-size: 16px; /* Schriftgröße */
    border: 1px solid #ccc; /* Rahmenfarbe */
    border-radius: 5px; /* Abgerundete Ecken */
    box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1); /* Leichter Schatten */
    margin-bottom: 15px; /* Abstand nach unten */
    background-color: #fff; /* Hintergrundfarbe */
    transition: border-color 0.3s ease, box-shadow 0.3s ease; /* Weicher Übergang */
}

select:focus {
    border-color: #007aff; /* Rahmenfarbe bei Fokus */
    box-shadow: 0px 2px 8px rgba(0, 122, 255, 0.3); /* Schattenfarbe bei Fokus */
    outline: none; /* Entfernt den Standard-Fokusrahmen */
}

/* Button-Styling */
button,
input[type="submit"] {
    display: inline-block; /* Stellt sicher, dass es wie ein Block-Element wirkt */
    padding: 10px 20px; /* Innenabstand */
    font-size: 16px; /* Schriftgröße */
    font-weight: bold; /* Fettschrift */
    color: #fff; /* Schriftfarbe */
    background-color: #007aff; /* Hintergrundfarbe */
    border: none; /* Kein Rahmen */
    border-radius: 5px; /* Abgerundete Ecken */
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1); /* Schatten */
    cursor: pointer; /* Zeigt Hand-Cursor */
    transition: background-color 0.3s ease, transform 0.2s ease; /* Weicher Übergang */
}

button:hover,
input[type="submit"]:hover {
    background-color: #005bb5; /* Dunkleres Blau bei Hover */
    transform: scale(1.05); /* Leichte Vergrößerung bei Hover */
}

button:active,
input[type="submit"]:active {
    background-color: #003f8a; /* Noch dunkler bei Klick */
    transform: scale(0.95); /* Leichtes Schrumpfen bei Klick */
}

/* Zusätzliche Anpassungen für Konsistenz */
textarea {
    resize: vertical; /* Benutzer kann die Höhe anpassen */
    min-height: 100px; /* Mindesthöhe */
}

</style>

</head>
<body>
<center>
<div class="form">
<h3><?php echo $translations['user_without_invitation']?></h3>
<style>
table, th, td {
  <!-- border: 1px solid black; -->
  border-collapse: collapse;
}
th, td {
  padding: 5px;
}
th {
  text-align: left;
}
</style>

<h4>
<?php
$query = "SELECT * FROM `user` WHERE `invite` IS NULL";
$result = mysqli_query($conn,$query) or die(mysql_error());

if(mysqli_num_rows($result) > 0){
    echo "<table>";
        echo "<tr>";
            echo "<th>ID</th>";
            echo "<th>Username</th>";
            echo"<th>&nbsp;</th>";
            echo "<th>Name</th>";
            echo"<th>&nbsp;</th>";
            echo "<th>E-Mail</th>";
            echo"<th>&nbsp;</th>";
            echo "<th>&nbsp;</th>";
            
            
        echo "</tr>";
    while($row = mysqli_fetch_array($result)){
        echo "<tr>";
            $del_id = $row['id'];
            $edituser[] = $row['id'];
            echo "<td><b>" . $row['id'] . "</b></td>";
            echo "<td>" . $row['username'] . "</td>";
            echo"<th>&nbsp;</th>";
            echo "<td>" . $row['firstname'] . "</td>";
            echo"<th>&nbsp;</th>";
            echo "<td>" . $row['email'] . "</td>";
            echo"<th>&nbsp;</th>";
            echo"<th&nbsp;</th>";
            echo"<th>&nbsp;</th>";
        
        echo "</tr>";
    }
    echo "</table>";
    // Free result set
    mysqli_free_result($result);
} else{
    echo $translations['everyone_invited'];
}
?>
</h4>
<br>
<div class="form">
<form name="edituser" action="" method="post">
<table>
    <tr>        
        <td>
        <!--Dropdown ID -->  
        <select name="ids" class="select-css">
        <option selected="selected">ID</option>
        <?php
        //Array blubb    
        foreach($edituser as $item)
            {          
                if ($item == 1)
                {
                    //weitergehen - hier gibt es nichts zu sehen
                }
                else
                {
                    echo '<option value="' . strtolower($item) . '">' . $item . '</option>';
                }       
            }
        ?>
        </select>
        </td>    
    </tr>    
    <tr>        
        
        <td>
           <input type="submit" name="sendinvite" value="<?php echo $translations['send_invitation']; ?>" style=""/>
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
    $smtp_replyto = getenv('SMTP_REPLYTO') ?: 'default_replyto@example.com'; // Reply-To-Adresse aus Umgebungsvariable

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

    $mail->SMTPDebug = 0; // Set verbose debug output for troubleshooting (use 0 for production)
    $mail->isSMTP(); // Set mailer to use SMTP

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
    $mail->Host = $smtp_host; // SMTP host
    $mail->SMTPAuth = true;  // Enable SMTP authentication
    $mail->Username = $smtp_user; // SMTP username
    $mail->Password = $smtp_password; // SMTP password
    $mail->SMTPSecure = 'tls'; // Enable TLS encryption, 'ssl' also accepted
    $mail->Port = 587; // TCP port to connect to

    $mail->setFrom($smtp_sender, $smtp_sender_name);
    $mail->addReplyTo($smtp_replyto, 'Support');
    $mail->addAddress($email, $vorname . " " . $name);

    $mail->isHTML(true); // Set email format to HTML

    // Sprache aus der Umgebungsvariablen oder Standardwert 'de'
    $language = getenv('LANGUAGE') ?: 'de';

    // Passende Sprachdatei laden
    $mailFile = __DIR__ . "/languages/mail_$language.json";

    if (file_exists($mailFile)) {
        $mailContent = json_decode(file_get_contents($mailFile), true);
    } else {
        // Fallback auf Deutsch, falls die Sprachdatei nicht existiert
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

    // PHPMailer konfigurieren
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

    header("Location: invite.php"); // Redirect
    // Mail Ende
}
ob_end_flush();
?>

</div>
</center>
</body>

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
</html>
