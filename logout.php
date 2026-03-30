<?php
session_start();
session_unset();   // remove all session variables
session_destroy(); // destroy the session
header("Location: index.php"); // go back to home page
exit;
?>