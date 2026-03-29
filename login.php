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
                session_regenerate_id(true);

                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $name;

                header("Location: student.php");
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
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #0b1e3c, #001233);
    color: #f1f5f9;
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 0;
}

/* HEADER */
.header {
    text-align: center;
    margin-top: 30px;
}
.logo {
    width: 85px;
}
.header h2 {
    color: #FFD700;
    margin-top: 10px;
}

/* CONTAINER */
.container {
    background: #0f2a4d;
    padding: 35px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.8);
    width: 360px;
    margin-top: 20px;
    border: 1px solid rgba(255, 215, 0, 0.2);
}

/* INPUT GROUP */
.input-group {
    position: relative;
    margin-bottom: 25px;
}

.input-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #1e3a5f;
    border-radius: 6px;
    background: #0b1e3c;
    color: #fff;
    outline: none;
    transition: 0.3s;
}

.input-group input:focus {
    border-color: #FFD700;
    box-shadow: 0 0 8px rgba(255, 215, 0, 0.4);
}

/* FLOAT LABEL */
.input-group label {
    position: absolute;
    top: 12px;
    left: 12px;
    color: #9ca3af;
    font-size: 14px;
    transition: 0.3s;
    pointer-events: none;
}

.input-group input:focus + label,
.input-group input:valid + label {
    top: -8px;
    left: 8px;
    background: #0f2a4d;
    padding: 0 5px;
    font-size: 12px;
    color: #FFD700;
}

/* BUTTON */
button {
    width: 100%;
    padding: 12px;
    background: #FFD700;
    color: #001233;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: #e6c200;
    box-shadow: 0 0 12px rgba(255, 215, 0, 0.6);
}

/* MESSAGE */
.message {
    text-align: center;
    margin-bottom: 15px;
    color: #ff4d4d;
}

/* TOGGLE */
.toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    color: #cbd5e1;
}

.toggle input {
    accent-color: #FFD700;
}

/* LINKS */
p {
    text-align: center;
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

<!-- HEADER -->
<div class="header">
    <img src="KCA_UNIVERSITY_LOGO.png" class="logo">
    <h2>KCA Complaint System</h2>
</div>

<!-- LOGIN BOX -->
<div class="container">
    <h2 style="text-align:center;">Login</h2>

    <?php if($message) echo "<div class='message'>$message</div>"; ?>

    <form method="POST">

        <div class="input-group">
            <input type="email" name="email" required>
            <label>Email</label>
        </div>

        <div class="input-group">
            <input type="password" name="password" id="password" required>
            <label>Password</label>
        </div>

        <div class="toggle">
            <input type="checkbox" onclick="togglePassword()">
            <span>Show Password</span>
        </div>

        <button type="submit" name="login">Login</button>

    </form>

    <p>Don't have an account? <a href="register.php">Register</a></p>
</div>

<script>
function togglePassword() {
    let pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
}
</script>

</body>
</html>