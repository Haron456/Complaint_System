<?php
session_start();
if(isset($_SESSION['role'])){
    // If already logged in, redirect to their dashboard
    switch($_SESSION['role']){
        case 'admin':
            header("Location: admin.php");
            exit;
        case 'staff':
            header("Location: staff.php");
            exit;
        case 'student':
            header("Location: student.php");
            exit;
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

.button-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    margin-bottom: 50px;
}

button {
    width: 220px;
    padding: 15px;
    font-size: 16px;
    font-weight: bold;
    color: #001233;
    background: #FFD700;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: #e6c200;
    box-shadow: 0 0 12px rgba(255, 215, 0, 0.6);
}

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
    <form action="login.php" method="get">
        <button type="submit" name="role" value="admin">Admin Portal</button>
        <button type="submit" name="role" value="staff">Staff Portal</button>
        <button type="submit" name="role" value="student">Student Portal</button>
    </form>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> KCAU Complaint System — Inspired by KCA University
</footer>

</body>
</html>