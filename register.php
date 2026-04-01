<?php
session_start();
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";

// Get role from GET (from index.php portal buttons)
$role = isset($_GET['role']) ? $_GET['role'] : '';

if(!$role){
    header("Location: index.php");
    exit;
}

if(isset($_POST['register'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $code = isset($_POST['code']) ? trim($_POST['code']) : "";

    // Check required fields
    if(!$name || !$email || !$password || !$confirm_password){
        $message = "Please fill all fields!";
    } elseif($password !== $confirm_password){
        $message = "Passwords do not match!";
    } else {
        // ROLE-BASED CODE VALIDATION
        if($role === "admin"){
            $stmt = $conn->prepare("SELECT id FROM super_admin WHERE admin_code=?");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $stmt->store_result();
            if($stmt->num_rows === 0){
                $message = "Invalid Super Admin Code!";
            }
            $stmt->close();
        } elseif($role === "staff"){
            $stmt = $conn->prepare("SELECT id FROM super_staff WHERE staff_code=?");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $stmt->store_result();
            if($stmt->num_rows === 0){
                $message = "Invalid Staff Code!";
            }
            $stmt->close();
        }

        // If no error → continue registration
        if(empty($message)){
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if($stmt->num_rows > 0){
                $message = "Email already registered!";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insert = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $insert->bind_param("ssss", $name, $email, $hashed, $role);
                $insert->execute();
                $insert->close();

                header("Location: login.php?role=$role&registered=success");
                exit;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo ucfirst($role); ?> Registration - KCAU Portal</title>
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

/* HOME BUTTON */
.home-btn {
    position: absolute;
    top: 20px;
    right: 25px;
}

.home-btn a {
    text-decoration: none;
    background: linear-gradient(135deg, #FFD700, #e6c200);
    color: #001233;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: bold;
    box-shadow: 0 0 10px rgba(255,215,0,0.5);
    transition: 0.3s;
}

.home-btn a:hover {
    transform: scale(1.05);
    box-shadow: 0 0 18px rgba(255,215,0,0.8);
}

.header { text-align:center; margin-top:30px; }
.logo { width:85px; }
.header h2 { color:#FFD700; margin-top:10px; }

.container {
    background:#0f2a4d; padding:35px; border-radius:12px;
    box-shadow:0 10px 40px rgba(0,0,0,0.8);
    width:360px; margin-top:20px; border:1px solid rgba(255,215,0,0.2);
}

.input-group { position:relative; margin-bottom:25px; }
.input-group input { width:100%; padding:12px; border:1px solid #1e3a5f; border-radius:6px; background:#0b1e3c; color:#fff; }

.input-group label {
    position:absolute; top:12px; left:12px;
    color:#9ca3af; font-size:14px;
    transition:0.3s; pointer-events:none;
}

.input-group input:focus + label,
.input-group input:valid + label {
    top:-8px; left:8px;
    background:#0f2a4d;
    padding:0 5px;
    font-size:12px;
    color:#FFD700;
}

button {
    width:100%; padding:12px;
    background:#FFD700;
    color:#001233;
    border:none;
    border-radius:6px;
    font-weight:bold;
    cursor:pointer;
}

button:hover { background:#e6c200; }

.message {
    text-align:center;
    margin-bottom:15px;
    color:#ff4d4d;
}

.toggle {
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:20px;
}

a { color:#FFD700; }
</style>
</head>
<body>

<!-- HOME BUTTON -->
<div class="home-btn">
    <a href="index.php">Home</a>
</div>

<div class="header">
    <img src="KCA_UNIVERSITY_LOGO.png" class="logo">
    <h2>KCA Complaint System</h2>
</div>

<div class="container">
    <h2 style="text-align:center;"><?php echo ucfirst($role); ?> Registration</h2>

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

        <button type="submit" name="register">Register</button>
    </form>

    <p>Already have an account? <a href="login.php?role=<?php echo $role; ?>">Login Here</a></p>
</div>

<script>
function togglePassword(){
    let pwd = document.getElementById("password");
    let cpwd = document.getElementById("confirm_password");
    pwd.type = pwd.type === "password" ? "text" : "password";
    cpwd.type = cpwd.type === "password" ? "text" : "password";
}
</script>

</body>
</html>