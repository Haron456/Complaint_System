<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";

if(isset($_POST['register'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']); // added

    //  Check if all fields are filled
    if($name && $email && $password && $confirm_password){

        //  Check if passwords match
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
                $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $email, $hashed);
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
input[type="text"], input[type="email"], input[type="password"] {
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
    color: <?php echo strpos($message,"successful")!==false?"#00ff99":"#ff5555"; ?>;
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
    <h2>Register</h2>
    <?php if($message) echo "<div class='message'>$message</div>"; ?>
  <form method="POST">
    <input type="text" name="name" placeholder="Full Names" required>

    <input type="email" name="email" placeholder="Email" required>

    <input type="password" name="password" placeholder="Password" required>

    <input type="password" name="confirm_password" placeholder="Confirm Password" required>

    <input type="checkbox" onclick="togglePassword()"> Show Password

    <input type="submit" name="register" value="Register">
</form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
</div>
</body>

<script>
function togglePassword() {
    let pass = document.querySelector('input[name="password"]');
    let confirm = document.querySelector('input[name="confirm_password"]');

    if (pass.type === "password") {
        pass.type = "text";
        confirm.type = "text";
    } else {
        pass.type = "password";
        confirm.type = "password";
    }
}
</script>
</html>