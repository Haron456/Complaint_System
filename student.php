<?php
session_start();
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle new complaint
if(isset($_POST['submit'])){
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    if($title != "" && $description != ""){
        $stmt = $conn->prepare("INSERT INTO complaints (user_id, title, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $description);
        $stmt->execute();

        // Get last inserted ID (for ticket)
        $ticket_id = $stmt->insert_id;
        $ticket = "TICK-".str_pad($ticket_id,4,"0",STR_PAD_LEFT);

        $stmt->close();

        echo "<script>alert('Complaint submitted! Your Ticket: $ticket');</script>";
    } else {
        echo "<script>alert('Enter a valid title and description');</script>";
    }
}

// Track complaint by ticket
$search_result = null;
if(isset($_GET['ticket'])){
    $ticket_input = str_replace("TICK-", "", $_GET['ticket']);
    $ticket_id = intval($ticket_input);

    $search_sql = "SELECT * FROM complaints WHERE id=$ticket_id AND user_id=$user_id";
    $search_result = mysqli_query($conn, $search_sql);
}

// Fetch user complaints
$complaints_sql = "SELECT * FROM complaints WHERE user_id=$user_id ORDER BY date_created DESC";
$complaints_result = mysqli_query($conn, $complaints_sql);

// Fetch feedback
$feedback_sql = "
    SELECT f.rating, f.comment, c.title AS complaint_title
    FROM feedback f
    INNER JOIN complaints c ON f.complaint_id=c.id
    WHERE c.user_id=$user_id
    ORDER BY f.id DESC
";
$feedback_result = mysqli_query($conn, $feedback_sql);
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
.status-pending {
    color: orange;
    font-weight: bold;
}

.status-resolved {
    color: limegreen;
    font-weight: bold;
}

.status-rejected {
    color: red;
    font-weight: bold;
}
</style>
<script>
// Optional: confirm before submitting
document.addEventListener("DOMContentLoaded", function(){
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e){
        if(!confirm('Submit this complaint now?')){
            e.preventDefault();
        }
    });
});
</script>
</head>
<body>
<div class="header">
    <img src="KCA_UNIVERSITY_LOGO.png" class="logo">
    <h2>KCA COMPLAINT SYSTEM</h2>
    <div class="username">
        Student Portal - KCAU |<br> Welcome <?php echo $_SESSION['user_name']; ?>
    </div>
</div>
<div class="container">

<h2>Track Complaint</h2>
<form method="GET">
    <label>Enter Ticket Number:</label>
    <input type="text" name="ticket" placeholder="e.g. TICK-0001" required>
    <button type="submit">Check Status</button>
</form>

<?php if($search_result && mysqli_num_rows($search_result) > 0){ 
    $row = mysqli_fetch_assoc($search_result);
?>
<div style="margin-top:15px; padding:15px; background:#1f4068; border-radius:8px;">
    <strong>Result:</strong><br>
    Ticket: <?php echo "TICK-".str_pad($row['id'],4,"0",STR_PAD_LEFT); ?><br>
    Title: <?php echo htmlspecialchars($row['title']); ?><br>
    Status: <span class="status-<?php echo strtolower($row['status']); ?>">
        <?php echo ucfirst($row['status']); ?>
    </span>
</div>
<?php } elseif(isset($_GET['ticket'])){ ?>
<p style="color:red;">No complaint found for that ticket.</p>
<?php } ?>

<h2>Submit Complaint</h2>
<form method="POST">
    <label>Title:</label>
    <input type="text" name="title" required>
    <label>Description:</label>
    <textarea name="description" rows="4" required></textarea>
    <input type="submit" name="submit" value="Submit Complaint">
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

<h2>Feedback</h2>
<?php if(mysqli_num_rows($feedback_result) > 0){ ?>
<table>
<tr><th>Complaint</th><th>Rating</th><th>Comment</th></tr>
<?php while($frow=mysqli_fetch_assoc($feedback_result)) { ?>
<tr>
    <td><?php echo htmlspecialchars($frow['complaint_title']); ?></td>
    <td><?php echo $frow['rating']; ?></td>
    <td><?php echo nl2br(htmlspecialchars($frow['comment'])); ?></td>
</tr>
<?php } ?>
</table>
<?php } else { echo "<p>No feedback yet.</p>"; } ?>

</div>
</body>
<script>
document.addEventListener("DOMContentLoaded", function(){

    // Confirm submit
    const form = document.querySelector('form[method="POST"]');
    form.addEventListener('submit', function(e){
        if(!confirm('Submit this complaint now?')){
            e.preventDefault();
        }
    });

    // Auto uppercase ticket input
    const ticketInput = document.querySelector('input[name="ticket"]');
    if(ticketInput){
        ticketInput.addEventListener('input', function(){
            this.value = this.value.toUpperCase();
        });
    }
});
</script>
</html>