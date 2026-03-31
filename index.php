<?php
session_start();

// If already logged in, redirect to their portal
if(isset($_SESSION['role'])){
    switch($_SESSION['role']){
        case 'admin': header("Location: admin.php"); exit;
        case 'staff': header("Location: staff.php"); exit;
        case 'student': header("Location: student.php"); exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>KCAU Complaint System - Home</title>
<style>
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #0b1e3c, #001233);
    color: #f1f5f9;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    min-height: 100vh;
    margin: 0;
}

/* HEADER */
.header {
    text-align: center;
    padding: 40px 20px 20px 20px;
}

.header img {
    width: 100px;
}

.header h1 {
    color: #FFD700;
    margin: 10px 0;
    font-size: 32px;
}

h1.page-title {
    color: #FFD700;
    margin: 10px 0 20px 0;
    text-align: center;
}

h2.page-login{
    color: #FFD700;
    margin: 10px 0 20px 0;
    text-align: center;
}

/* BUTTONS */
.button-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    margin-bottom: 50px;
}

.button-container a {
    text-decoration: none;
    width: 240px;
}

.button-container button {
    width: 100%;
    padding: 15px;
    font-size: 16px;
    font-weight: bold;
    color: #001233;
    background: linear-gradient(135deg, #FFD700, #e6c200);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.button-container button:hover {
    transform: scale(1.05);
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
}

/* FOOTER */
footer {
    text-align: center;
    padding: 15px;
    background: #0b1e3c;
    color: #fff;
    font-size: 14px;
    width: 100%;
    position: fixed;
    bottom: 0;
}
</style>
</head>
<body>

<div class="header">
    <img src="KCA_UNIVERSITY_LOGO.png" class="logo" alt="KCAU Logo">
    <h1>Welcome To Our Homepage</h1>
</div>

<h1 class="page-title">KCAU Complaint System</h1>

<h2 class="page-login">Login HERE</h2>

<div class="button-container">
    <a href="login.php?role=admin"><button>Admin Portal</button></a>
    <a href="login.php?role=staff"><button>Staff Portal</button></a>
    <a href="login.php?role=student"><button>Student Portal</button></a>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> KCAU Complaint System — Inspired by KCA University
</footer>

</body>
</html>


