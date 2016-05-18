<?php
session_start(); 
$conn = mysqli_connect($_SESSION['server'], $_SESSION['dbuser'], $_SESSION['pass'], $_SESSION['dbname'], '3306');
if (!$conn) {
    die('Could not connect to MySQL: ' . mysqli_connect_error());
}
$result = mysqli_query($conn, $_REQUEST[query]);
if($result){
    echo "ok deleted=".$conn->affected_rows;
}
else{
    echo "error";
}
mysqli_free_result($result);
mysqli_close($conn);



