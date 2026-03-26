<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch all complaints with user names
$complaints_result = mysqli_query($conn, "
SELECT c.id, u.name AS user_name, c.title, c.description, c.status, c.date_created
FROM complaints c
LEFT JOIN users u ON c.user_id=u.id
ORDER BY c.date_created DESC
");

// Fetch resolved complaints for feedback
$feedback_result = mysqli_query($conn, "
SELECT * FROM complaints WHERE status='resolved' ORDER BY date_created DESC
");

// Handle status update
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $status = $_POST['status'];
    mysqli_query($conn, "UPDATE complaints SET status='$status' WHERE id=$id");
    header("Location: admin.php");
    exit;
}

// Handle feedback submission
if(isset($_POST['submit_feedback'])){
    $complaint_id = intval($_POST['complaint_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $check = mysqli_query($conn, "SELECT * FROM feedback WHERE complaint_id=$complaint_id");
    if(mysqli_num_rows($check)==0){
        mysqli_query($conn, "INSERT INTO feedback (complaint_id,rating,comment) VALUES ($complaint_id,$rating,'$comment')");
    }
    header("Location: admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
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
    max-width: 1100px;
    margin: 30px auto;
    background: #162447;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
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
tr:hover {
    background: #2c3e50;
}
.status-pending {
    color: #FFD700;
    font-weight: bold;
}
.status-resolved {
    color: #00ff99;
    font-weight: bold;
}

/* Forms */
form label {
    display: block;
    margin: 10px 0 5px;
    font-weight: 500;
}
input[type="number"], select, textarea {
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

/* Section Titles */
h2 {
    color: #FFD700;
    margin-top: 30px;
    border-bottom: 2px solid #FFD700;
    padding-bottom: 5px;
}
</style>
<script>
// Confirm before updating status
document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll('form').forEach(form => {
        if(form.querySelector('select[name="status"]')){
            form.addEventListener('submit', function(e){
                let status = form.querySelector('select[name="status"]').value;
                if(!confirm(`Mark this complaint as "${status}"?`)) e.preventDefault();
            });
        }
    });
});
</script>
</head>
<body>

<header>Admin Dashboard - KCAU</header>
<div class="container">

<h2>All Complaints</h2>
<table>
<tr><th>ID</th><th>User</th><th>Title</th><th>Description</th><th>Status</th><th>Date</th><th>Action</th></tr>
<?php while($row=mysqli_fetch_assoc($complaints_result)){ ?>
<tr>
<td><?php echo $row['id']; ?></td>
<td><?php echo htmlspecialchars($row['user_name']); ?></td>
<td><?php echo htmlspecialchars($row['title']); ?></td>
<td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
<td class="status-<?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></td>
<td><?php echo $row['date_created']; ?></td>
<td>
<form method="POST">
<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
<select name="status">
<option value="pending" <?php if($row['status']=='pending') echo 'selected'; ?>>Pending</option>
<option value="resolved" <?php if($row['status']=='resolved') echo 'selected'; ?>>Resolved</option>
</select>
<input type="submit" name="update" value="Update">
</form>
</td>
</tr>
<?php } ?>
</table>

<h2>Submit Feedback</h2>
<form method="POST">
<label>Complaint:</label>
<select name="complaint_id" required>
<?php while($frow=mysqli_fetch_assoc($feedback_result)){ 
    $exists=mysqli_num_rows(mysqli_query($conn,"SELECT * FROM feedback WHERE complaint_id=".$frow['id']))>0;
?>
<option value="<?php echo $frow['id']; ?>" <?php if($exists) echo 'disabled'; ?>>
ID <?php echo $frow['id']; ?> - <?php echo htmlspecialchars($frow['title']); ?> <?php if($exists) echo '(Feedback given)'; ?>
</option>
<?php } ?>
</select>
<label>Rating (1-5):</label>
<input type="number" name="rating" min="1" max="5" required>
<label>Comment:</label>
<textarea name="comment"></textarea>
<input type="submit" name="submit_feedback" value="Submit Feedback">
</form>

</div>
</body>
</html>