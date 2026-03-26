<?php
include 'db.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";

if(isset($_POST['login'])){
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if($email && $password){
        $stmt = $conn->prepare("SELECT id, password, name FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $hashed, $name);
        if($stmt->num_rows > 0){
            $stmt->fetch();
            if(password_verify($password, $hashed)){
                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $name;
                header("Location: test.php"); // Customer portal
                exit;
            } else {
                $message = "Incorrect password!";
            }
        } else {
            $message = "Email not registered!";
        }
        $stmt->close();
    } else {
        $message = "Fill all fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - KCAU Portal</title>
<style>
body {
    font-family: 'Roboto', sans-serif;
    background: #1a1a2e;
    color: #f0f0f0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}
.container {
    background: #162447;
    padding: 40px 30px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    width: 350px;
}
h2 {
    text-align: center;
    color: #FFD700;
    margin-bottom: 30px;
}
input[type="email"], input[type="password"] {
    width: 100%;
    padding: 10px;
    margin: 10px 0 20px 0;
    border-radius: 5px;
    border: 1px solid #444;
    background: #1f4068;
    color: #f0f0f0;
}
input[type="submit"] {
    width: 100%;
    padding: 12px;
    background: #003379;
    color: #FFD700;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
}
input[type="submit"]:hover {
    background: #FFD700;
    color: #003379;
}
.message {
    color: #ff5555;
    text-align: center;
    margin-bottom: 15px;
}
p {
    text-align: center;
    margin-top: 20px;
}
a {
    color: #FFD700;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
</head>
<body>
<div class="container">
    <h2>Login</h2>
    <?php if($message) echo "<div class='message'>$message</div>"; ?>
    <form method="POST">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="submit" name="login" value="Login">
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a></p>
</div>
</body>
</html>