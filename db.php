<?php
$host = "localhost";
$user = "root";        // default XAMPP user
$password = "";        // default XAMPP password
$dbname = "complaint_system";

$conn = new mysqli($host, $user, $password, $dbname);
if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}
?>