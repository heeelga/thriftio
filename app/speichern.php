<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            background-color: black;
        }
        
        #container {
            text-align: center;
            color: white;
        }
    </style>
</head>
<body>
    <div id="container">
<?php
include("auth.php");
require('dbconnection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_SESSION['username'];
    $gewicht = $_POST['gewicht'];
    $fett = $_POST['fett'];
    $muskeln = $_POST['muskeln'];
    $viszeral = $_POST['viszeral'];
    $oberarm_l = $_POST['oberarm_l'];
    $oberarm_angespannt_l = $_POST['oberarm_angespannt_l'];
    $oberarm_r = $_POST['oberarm_r'];
    $oberarm_angespannt_r = $_POST['oberarm_angespannt_r'];
    $brust = $_POST['brust'];
    $bauch = $_POST['bauch'];
    $unterarm_l = $_POST['unterarm_l'];
    $unterarm_r = $_POST['unterarm_r'];
    $oberschenkel_l = $_POST['oberschenkel_l'];
    $oberschenkel_r = $_POST['oberschenkel_r'];

    $query = "INSERT INTO Werte (Username, Datum, Gewicht, Fett, Muskeln, Viszeral, oberarm_l, oberarm_angespannt_l, oberarm_r, oberarm_angespannt_r, brust, bauch, unterarm_l, unterarm_r, oberschenkel_l, oberschenkel_r) 
          VALUES ('$username', CURDATE(), '$gewicht', '$fett', '$muskeln', '$viszeral', '$oberarm_l', '$oberarm_angespannt_l', '$oberarm_r', '$oberarm_angespannt_r', '$brust', '$bauch', '$unterarm_l', '$unterarm_r', '$oberschenkel_l', '$oberschenkel_r')";

    
    mysqli_query($conn, $query) or die(mysql_error());

    header("Location: index.php");
    exit();
}
?>


        <br>
        <br>

        <?php
        header("refresh: 5; url = index.php");
        echo '<span style="color: #F2F2F2;">Weiterleitung zur Startseite erfolgt in:</span>';
        ?>

        <p id="demo" style="color: #F2F2F2;"></p>

        <script>
        // Set the date we're counting down to
        var countDownDate = new Date().getTime() + 5000; // 5 Sekunden

        // Update the count down every 1 second
        var x = setInterval(function() {
            // Get today's date and time
            var now = new Date().getTime();

            // Find the distance between now and the count down date
            var distance = countDownDate - now;

            // Time calculations for days, hours, minutes and seconds
            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Output the result in an element with id="demo"
            document.getElementById("demo").innerHTML = seconds + "s ";

            // If the count down is over, write some text
            if (distance < 0) {
                clearInterval(x);
                document.getElementById("demo").innerHTML = "";
            }
        }, 1000);
        </script>
    </div>
</body>
</html>
