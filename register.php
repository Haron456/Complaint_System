<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";

if(isset($_POST['register'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role']; // handle role

    // Check if all fields are filled
    if($name && $email && $password && $confirm_password && $role){

        // Check if passwords match
        if($password !== $confirm_password){
            $message = "Passwords do not match!";
        } else {

            // Check if email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE email=?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if($check->num_rows > 0){
                $message = "Email already registered!";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $hashed, $role);
                $stmt->execute();
                $stmt->close();
                $message = "Registration successful! You can now login.";
            }

            $check->close();
        }

    } else {
        $message = "Please fill all fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - KCAU Portal</title>

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
    letter-spacing: 1px;
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
    color: #ffffff;
    outline: none;
    transition: 0.3s;
}

/* INPUT FOCUS GLOW */
.input-group input:focus {
    border-color: #FFD700;
    box-shadow: 0 0 8px rgba(255, 215, 0, 0.4);
}

/* FLOATING LABEL */
.input-group label {
    position: absolute;
    top: 12px;
    left: 12px;
    color: #9ca3af;
    font-size: 14px;
    transition: 0.3s;
    pointer-events: none;
    background: transparent;
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
    color: <?php echo strpos($message,"successful")!==false?"#00ffcc":"#ff4d4d"; ?>;
}

/* TOGGLE */
.toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    margin-bottom: 20px;
    color: #cbd5e1;
}

.toggle input {
    accent-color: #FFD700;
    width: 18px;
    height: 18px;
}

/* LINK */
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

<div class="header">
    <img src="KCA_UNIVERSITY_LOGO.png" class="logo">
    <h2>KCA Complaint System</h2>
</div>

<div class="container">
    <h2 style="text-align:center;">Create Account</h2>

    <?php if($message) echo "<div class='message'>$message</div>"; ?>

    <form method="POST">

        <div class="input-group">
            <input type="text" name="name" required>
            <label>Full Name</label>
        </div>

        <div class="input-group">
            <input type="email" name="email" required>
            <label>Email</label>
        </div>

        <div class="input-group">
            <input type="password" name="password" id="password" required>
            <label>Password</label>
        </div>

        <div class="input-group">
            <input type="password" name="confirm_password" id="confirm_password" required>
            <label>Confirm Password</label>
        </div>


        <div class="input-group">
    <select name="role" required>
        <option value="">--Select Role--</option>
        <option value="student">Student</option>
        <option value="staff">Staff</option>
        <option value="admin">Admin</option>
    </select>
    <label>Role</label>
</div>
        <div class="toggle">
            <input type="checkbox" onclick="togglePassword()">
            <span>Show Password</span>
        </div>

        <button type="submit" name="register">Register</button>

    </form>

    <p>Already have an account? <a href="login.php">Login</a></p>
</div>

<script>
function togglePassword() {
    let pass = document.getElementById("password");
    let confirm = document.getElementById("confirm_password");

    pass.type = pass.type === "password" ? "text" : "password";
    confirm.type = confirm.type === "password" ? "text" : "password";
}
</script>

</body>
</html>