<?php
session_start();
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";

// Detect role from GET (from index.php buttons)
$role = isset($_GET['role']) ? $_GET['role'] : '';

if(!$role){
    header("Location: index.php");
    exit;
}

// If already logged in, redirect to their dashboard
if(isset($_SESSION['role']) && $_SESSION['role'] === $role){
    switch($role){
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

if(isset($_POST['login'])){
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $code = isset($_POST['code']) ? trim($_POST['code']) : "";

    // Check role-specific code if needed
    if($role === "admin"){
        $stmt = $conn->prepare("SELECT * FROM super_admin WHERE admin_code=? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0){
            $message = "Invalid Super Admin Code!";
        }
    } elseif($role === "staff"){
        $stmt = $conn->prepare("SELECT * FROM super_staff WHERE staff_code=? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0){
            $message = "Invalid Staff Code!";
        }
    }

    // Only check login if code is valid or student
    if(!$message){
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND role=? LIMIT 1");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 1){
            $user = $result->fetch_assoc();
            if(password_verify($password, $user['password'])){
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                switch($user['role']){
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
            } else {
                $message = "Incorrect Password!";
            }
        } else {
            $message = "No account found with this email and role!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo ucfirst($role); ?> Login - KCAU Portal</title>
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
.header { text-align: center; padding: 40px 20px 20px 20px; }
.header img { width: 100px; }
.header h1 { color: #FFD700; margin: 10px 0; font-size: 32px; }
h2.page-title{ color: #FFD700; margin: 10px 0 20px 0; text-align: center; }
.container { background: #0f2a4d; padding: 35px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.8); width: 360px; margin-top: 20px; border: 1px solid rgba(255, 215, 0, 0.2); }
.input-group { position: relative; margin-bottom: 25px; }
.input-group input { width: 100%; padding: 12px; border: 1px solid #1e3a5f; border-radius: 6px; background: #0b1e3c; color: #ffffff; outline: none; transition: 0.3s; }
.input-group input:focus { border-color: #FFD700; box-shadow: 0 0 8px rgba(255, 215, 0, 0.4); }
.input-group label { position: absolute; top: 12px; left: 12px; color: #9ca3af; font-size: 14px; transition: 0.3s; pointer-events: none; background: transparent; }
.input-group input:focus + label,
.input-group input:valid + label { top: -8px; left: 8px; background: #0f2a4d; padding: 0 5px; font-size: 12px; color: #FFD700; }
button { width: 100%; padding: 12px; background: #FFD700; color: #001233; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s; }
button:hover { background: #e6c200; box-shadow: 0 0 12px rgba(255, 215, 0, 0.6); }
.message { text-align: center; margin-bottom: 15px; color: #ff4d4d; }
.toggle { display: flex; align-items: center; gap: 10px; font-size: 14px; margin-bottom: 20px; color: #cbd5e1; }
.toggle input { accent-color: #FFD700; width: 18px; height: 18px; }
p { text-align: center; }
a { color: #FFD700; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="header">
    <img src="KCA_UNIVERSITY_LOGO.png" class="logo" alt="KCAU Logo">
    <h1>Login Portal</h1>
</div>

<div class="container">
    <h2 class="page-title"><?php echo ucfirst($role); ?> Login</h2>

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

        <?php if($role === 'admin' || $role === 'staff'): ?>
        <div class="input-group">
            <input type="text" name="code" required>
            <label><?php echo $role === 'admin' ? 'Super Admin Code' : 'Staff Code'; ?></label>
        </div>
        <?php endif; ?>

        <div class="toggle">
            <input type="checkbox" onclick="togglePassword()">
            <span>Show Password</span>
        </div>

        <button type="submit" name="login">Login</button>
    </form>

    <p>Don't have an account? <a href="register.php?role=<?php echo $role; ?>">Register Here</a></p>
</div>

<script>
function togglePassword(){
    let pwd = document.getElementById("password");
    pwd.type = (pwd.type === "password") ? "text" : "password";
}
</script>

</body>
</html>