<?php
require('dbconnection.php');
include("auth.php"); //include auth.php file on all secure pages ?>

<?php
$username = $_SESSION['username'];

// SET PRUEF VON NULL ZU 1
$query = "UPDATE `user` SET pruef = '1' WHERE username='$username'";
$result = mysqli_query($conn,$query) or die(mysqli_error());
header("Location: index.php"); // Redirect user to index.php:
?>

