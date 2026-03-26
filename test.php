<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle new complaint submission
if(isset($_POST['submit'])){
    $user_id = intval($_POST['user_id']); // User enters their ID manually
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    if($user_id > 0 && $title != "" && $description != ""){
        $ticket_prefix = "TICK-";
        $ticket_number = $ticket_prefix . str_pad(rand(1, 9999), 4, "0", STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO complaints (user_id, title, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $description);
        $stmt->execute();
        $stmt->close();

        echo "<script>alert('Complaint submitted successfully! Your Ticket: $ticket_number');</script>";
    } else {
        echo "<script>alert('⚠ Please enter a valid User ID, title, and description');</script>";
    }
}

// Fetch all complaints for the entered user ID
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 1;
$sql = "SELECT * FROM complaints ORDER BY date_created DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Portal - KCAU Complaint System</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Roboto', sans-serif; background: #1a1a2e; color: #f0f0f0; margin:0; padding:0; }
header { background: #003379; color: #FFD700; padding: 15px 20px; text-align: center; font-size: 24px; font-weight: 700; }
.container { width: 90%; max-width: 900px; margin: 20px auto; background: #162447; padding: 20px; border-radius: 10px; box-shadow: 0 2px 15px rgba(0,0,0,0.5); }
h2 { color: #FFD700; }
form { margin-bottom: 30px; }
input[type="text"], textarea { width: 100%; padding: 10px; margin: 5px 0 15px; border-radius: 5px; border: 1px solid #444; background: #1f4068; color: #f0f0f0; }
textarea { resize: vertical; }
input[type="submit"] { background: #003379; color: #FFD700; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: 0.3s; }
input[type="submit"]:hover { background: #FFD700; color: #003379; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { padding: 10px; border: 1px solid #444; text-align: left; }
th { background: #003379; color: #FFD700; }
tr:nth-child(even) { background: #1f4068; }
.status-pending { color: #FFD700; font-weight: 600; }
.status-resolved { color: #00ff99; font-weight: 600; }
</style>
</head>
<body>

<header>Customer Portal - KCAU Complaint System</header>

<div class="container">

<h2>Submit New Complaint</h2>
<form method="POST" id="complaintForm">
    <label for="user_id">Your User ID:</label>
    <input type="text" name="user_id" placeholder="Enter your User ID" value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>" required>

    <label for="title">Complaint Title:</label>
    <input type="text" name="title" placeholder="Enter complaint title" required>

    <label for="description">Description:</label>
    <textarea name="description" rows="4" placeholder="Enter detailed description" required></textarea>

    <input type="submit" name="submit" value="Submit Complaint">
</form>

<h2>My Complaints</h2>
<table>
    <tr>
        <th>Ticket</th>
        <th>Title</th>
        <th>Description</th>
        <th>Status</th>
        <th>Date</th>
    </tr>
    <?php while($row = mysqli_fetch_assoc($result)) { ?>
    <tr>
        <td><?php echo "TICK-" . str_pad($row['id'], 4, "0", STR_PAD_LEFT); ?></td>
        <td><?php echo $row['title']; ?></td>
        <td><?php echo nl2br($row['description']); ?></td>
        <td class="status-<?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></td>
        <td><?php echo $row['date_created']; ?></td>
    </tr>
    <?php } ?>
</table>

</div>

<script>
// Confirm before submitting complaint
document.getElementById('complaintForm').addEventListener('submit', function(e){
    let userId = this.user_id.value.trim();
    let title = this.title.value.trim();
    let desc = this.description.value.trim();
    if(userId === "" || title === "" || desc === ""){
        alert('⚠ Please enter a valid User ID, title, and description');
        e.preventDefault();
    } else {
        return confirm('Submit this complaint now?');
    }
});

// Highlight status dynamically
document.querySelectorAll('td[class^="status-"]').forEach(function(td){
    if(td.textContent.toLowerCase() === 'pending'){
        td.style.backgroundColor = '#FFD70033'; // light gold
    } else if(td.textContent.toLowerCase() === 'resolved'){
        td.style.backgroundColor = '#00ff9933'; // light green
    }
});
</script>

</body>
</html>