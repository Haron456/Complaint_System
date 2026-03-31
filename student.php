<?php
session_start();
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --------------------
// Redirect if not logged in as student
// --------------------
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'student'){
    header("Location: login.php?role=student");
    exit;
}

// Set user_id and user_name safely
$user_id = intval($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? '');

// --------------------
// Handle new complaint
// --------------------
if(isset($_POST['submit'])){
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    if($title !== "" && $description !== ""){
        $stmt = $conn->prepare("INSERT INTO complaints (user_id, title, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $description);
        $stmt->execute();

        $ticket_id = $stmt->insert_id;
        $ticket = "TICK-".str_pad($ticket_id,4,"0",STR_PAD_LEFT);
        $stmt->close();

        echo "<script>alert('Complaint submitted! Your Ticket: $ticket'); window.location.href='student.php';</script>";
        exit;
    } else {
        echo "<script>alert('Enter a valid title and description');</script>";
    }
}

// --------------------
// Handle student feedback
// --------------------
if(isset($_POST['submit_student_feedback'])){
    $feedback_id = intval($_POST['feedback_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    $stmt = $conn->prepare("UPDATE feedback SET student_rating=?, student_comment=? WHERE id=?");
    $stmt->bind_param("isi", $rating, $comment, $feedback_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Your feedback has been submitted!'); window.location.href='student.php';</script>";
    exit;
}

// --------------------
// Track complaint by ticket
// --------------------
$search_result = null;
if(isset($_GET['ticket'])){
    $ticket_input = str_replace("TICK-", "", strtoupper($_GET['ticket']));
    $ticket_id = intval($ticket_input);

    $stmt = $conn->prepare("SELECT * FROM complaints WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $ticket_id, $user_id);
    $stmt->execute();
    $search_result = $stmt->get_result();
    $stmt->close();
}

// --------------------
// Fetch user complaints
// --------------------
$stmt = $conn->prepare("SELECT * FROM complaints WHERE user_id=? ORDER BY date_created DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$complaints_result = $stmt->get_result();
$stmt->close();

// --------------------
// Fetch feedback
// --------------------
$stmt = $conn->prepare("
    SELECT f.id AS feedback_id, f.admin_comment, f.student_rating, f.student_comment, c.title AS complaint_title
    FROM feedback f
    INNER JOIN complaints c ON f.complaint_id = c.id
    WHERE c.user_id=?
    ORDER BY f.id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$feedback_result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Portal</title>
<style>
/* Base */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #0a0f2c, #111a3a);
    color: #fff;
    margin: 0;
    padding: 0;
}

/* Header */
.header {
    background: #0d2a66;
    padding: 20px;
    text-align: center;
    border-bottom: 3px solid gold;
}

.header h1 {
    margin: 0;
    color: gold;
}

.username {
    color: #ffd700cc;
    font-size: 18px;
    margin-top: 5px;
}

/* Main container */
.container {
    width: 60%;
    margin: 40px auto;
    background: #162447;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0px 0px 20px rgba(0,0,0,0.5);
}

/* Section titles */
h2 {
    color: gold;
    border-bottom: 2px solid gold;
    padding-bottom: 5px;
}

/* Inputs */
input, textarea {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: none;
    outline: none;
    background: #1f4068;
    color: white;
}

input::placeholder,
textarea::placeholder {
    color: #bbb;
}

/* Buttons */
button {
    background: gold;
    color: #000;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.3s;
}

button:hover {
    background: #ffd700cc;
    transform: scale(1.05);
}

/* Complaint table */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

table th, table td {
    padding: 10px;
    border-bottom: 1px solid #ccc;
    text-align: left;
}

table th {
    background: #1f4068;
    color: gold;
}

.status-pending { color: orange; font-weight: bold; }
.status-resolved { color: limegreen; font-weight: bold; }
.status-rejected { color: red; font-weight: bold; }

/* LOGOUT BUTTON - BOTTOM RIGHT POWER STYLE */
.logout-form {
    position: fixed;
    bottom: 20px;
    right: 20px;
}

.logout-form button {
    background: linear-gradient(135deg, #FFD700, #e6c200);
    color: #001233;
    border: none;
    padding: 12px 18px;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
    transition: all 0.3s ease;
}

.logout-form button:hover {
    transform: scale(1.08);
    box-shadow: 0 0 25px rgba(255, 215, 0, 0.9);
}

.logout-form button:active {
    transform: scale(0.95);
}

/* Fancy form input group */
.fancy-form .input-group {
    position: relative;
    margin-bottom: 20px;
}

.fancy-form .input-group input,
.fancy-form .input-group textarea {
    width: 100%;
    padding: 14px 12px;
    border-radius: 10px;
    border: 1px solid #1f4068;
    background: #0b1e3c;
    color: #fff;
    outline: none;
    transition: all 0.3s ease;
    resize: none;
}

.fancy-form .input-group input:focus,
.fancy-form .input-group textarea:focus {
    border-color: #FFD700;
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
}

.fancy-form .input-group label {
    position: absolute;
    top: 14px;
    left: 12px;
    color: #9ca3af;
    font-size: 14px;
    pointer-events: none;
    background: transparent;
    transition: all 0.3s ease;
}

.fancy-form .input-group input:focus + label,
.fancy-form .input-group input:valid + label,
.fancy-form .input-group textarea:focus + label,
.fancy-form .input-group textarea:valid + label {
    top: -8px;
    left: 10px;
    background: #162447;
    padding: 0 6px;
    font-size: 12px;
    color: #FFD700;
}

/* Fancy submit button */
.fancy-form .fancy-btn {
    width: 100%;
    padding: 14px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, #FFD700, #e6c200);
    color: #001233;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
}

.fancy-form .fancy-btn:hover {
    background: linear-gradient(135deg, #ffe566, #e6c200);
    transform: scale(1.05);
    box-shadow: 0 0 25px rgba(255, 215, 0, 0.9);
}

.fancy-form .fancy-btn:active {
    transform: scale(0.95);
}
</style>
</head>
<body>

<div class="header">
    <img src="KCA_UNIVERSITY_LOGO.png" class="logo">
    <h1>KCA Complaint System</h1>
    <div class="username">Welcome <?php echo $user_name; ?></div>
</div>

<div class="container">
<h2>Track Complaint</h2>
<form method="GET">
    <label>Enter Ticket Number:</label>
    <input type="text" name="ticket" placeholder="e.g. TICK-0001" required>
    <button type="submit">Check Status</button>
</form>

<?php if($search_result && mysqli_num_rows($search_result) > 0){ 
    $row = mysqli_fetch_assoc($search_result); ?>
<div style="margin-top:15px; padding:15px; background:#1f4068; border-radius:8px;">
    <strong>Result:</strong><br>
    Ticket: <?php echo "TICK-".str_pad($row['id'],4,"0",STR_PAD_LEFT); ?><br>
    Title: <?php echo htmlspecialchars($row['title']); ?><br>
    Status: <span class="status-<?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span>
</div>
<?php } elseif(isset($_GET['ticket'])){ ?>
<p style="color:red;">No complaint found for that ticket.</p>
<?php } ?>

<h2>Submit Complaint</h2>
<form method="POST" class="fancy-form">

    <div class="input-group">
        <input type="text" name="title" required>
        <label>Title</label>
    </div>

    <div class="input-group">
        <textarea name="description" rows="4" required></textarea>
        <label>Description</label>
    </div>

    <button type="submit" name="submit" class="fancy-btn">Submit Complaint</button>
</form>

<h2>My Complaints</h2>
<table>
<tr><th>Ticket</th><th>Title</th><th>Description</th><th>Status</th><th>Date</th></tr>
<?php while($row=mysqli_fetch_assoc($complaints_result)) { ?>
<tr>
    <td><?php echo "TICK-".str_pad($row['id'],4,"0",STR_PAD_LEFT); ?></td>
    <td><?php echo htmlspecialchars($row['title']); ?></td>
    <td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
    <td class="status-<?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></td>
    <td><?php echo $row['date_created']; ?></td>
</tr>
<?php } ?>
</table>

<h2>Feedback from Admin</h2>
<?php if(mysqli_num_rows($feedback_result) > 0){ ?>
<table>
<tr><th>Complaint</th><th>Admin Comment</th><th>Your Rating</th><th>Your Comment</th><th>Action</th></tr>
<?php while($frow=mysqli_fetch_assoc($feedback_result)) { 
    $alreadyRated = !is_null($frow['student_rating']); ?>
<tr>
    <td><?php echo htmlspecialchars($frow['complaint_title']); ?></td>
    <td><?php echo nl2br(htmlspecialchars($frow['admin_comment'] ?? '-')); ?></td>
    <td><?php echo $alreadyRated ? $frow['student_rating'] : '-'; ?></td>
    <td><?php echo $alreadyRated ? htmlspecialchars($frow['student_comment']) : '-'; ?></td>
    <td>
        <?php if(!$alreadyRated){ ?>
        <form method="POST">
            <input type="hidden" name="feedback_id" value="<?php echo $frow['feedback_id']; ?>">
            <label>Rating (1-5):</label>
            <input type="number" name="rating" min="1" max="5" required>
            <label>Comment:</label>
            <textarea name="comment" rows="2" required></textarea>
            <input type="submit" name="submit_student_feedback" value="Submit Feedback">
        </form>
        <?php } else { echo 'Done'; } ?>
    </td>
</tr>
<?php } ?>
</table>
<?php } else { echo "<p>No feedback yet.</p>"; } ?>

</div>

<form method="POST" action="logout.php" class="logout-form">
    <button type="submit">Logout</button>
</form>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const ticketInput = document.querySelector('input[name="ticket"]');
    if(ticketInput){
        ticketInput.addEventListener('input', function(){
            this.value = this.value.toUpperCase();
        });
    }
});
</script>
</body>
</html>