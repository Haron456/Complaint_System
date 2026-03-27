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
<title>Customer Portal</title>
<style>
/* Base */
body {
    font-family: 'Roboto', sans-serif;
    background: #1a1a2e;
    color: #f0f0f0;
    margin: 0;
    padding: 0;
}
header {
    background: #003379;
    padding: 20px;
    text-align: center;
    color: #FFD700;
    font-size: 28px;
    font-weight: bold;
    letter-spacing: 1px;
}
.container {
    width: 90%;
    max-width: 1000px;
    margin: 30px auto;
    background: #162447;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
}

/* Form */
form label {
    display: block;
    margin: 10px 0 5px;
    font-weight: 500;
}
input[type="text"], textarea {
    width: 100%;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #444;
    background: #1f4068;
    color: #f0f0f0;
}
input[type="submit"] {
    background: #003379;
    color: #FFD700;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    transition: 0.3s;
}
input[type="submit"]:hover {
    background: #FFD700;
    color: #003379;
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    padding: 12px;
    border: 1px solid #444;
    text-align: left;
}
th {
    background: #003379;
    color: #FFD700;
}
tr:nth-child(even) {
    background: #1f4068;
}
.status-pending {
    color: #FFD700;
    font-weight: bold;
}
.status-resolved {
    color: #00ff99;
    font-weight: bold;
}

/* Hover effect */
tr:hover {
    background: #2c3e50;
}

/* Section titles */
h2 {
    color: #FFD700;
    margin-top: 30px;
    border-bottom: 2px solid #FFD700;
    padding-bottom: 5px;
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

<header>
    Customer Portal - KCAU | Welcome <?php echo $_SESSION['user_name']; ?>
</header>
<div class="container">

<h2>Track Complaint</h2>
<form method="GET">
    <label>Enter Ticket Number:</label>
    <input type="text" name="ticket" placeholder="e.g. TICK-0001" required>
    <input type="submit" value="Check Status">
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