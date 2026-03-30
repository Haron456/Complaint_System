<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){ 
    header("Location: login.php"); 
    exit; 
}

include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch complaints
$complaints_result = mysqli_query($conn, "
SELECT c.id, u.name AS user_name, c.title, c.description, c.status, c.date_created
FROM complaints c
LEFT JOIN users u ON c.user_id=u.id
ORDER BY c.date_created DESC
");

// Fetch resolved complaints
$feedback_result = mysqli_query($conn, "
SELECT * FROM complaints WHERE status='resolved' ORDER BY date_created DESC
");

// Update status
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $status = $_POST['status'];
    mysqli_query($conn, "UPDATE complaints SET status='$status' WHERE id=$id");
    header("Location: admin.php");
    exit;
}

// Submit feedback
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
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #0b1e3c, #001233);
    color: #f1f5f9;
    margin: 0;
}

/* HEADER */
.header {
    text-align: center;
    padding: 20px;
}
.logo {
    width: 80px;
}
.header h1 {
    color: #FFD700;
    margin-top: 10px;
}

/* CONTAINER */
.container {
    width: 90%;
    max-width: 110000px;
    margin: 20px auto;
    background: #0f2a4d;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.8);
    border: 1px solid rgba(255,215,0,0.2);
}

/* SECTION TITLES */
h2 {
    color: #FFD700;
    border-bottom: 2px solid #FFD700;
    padding-bottom: 5px;
    margin-top: 30px;
}

/* TABLE */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th {
    background: #FFD700;
    color: #001233;
    padding: 12px;
}

td {
    padding: 12px;
    border-bottom: 1px solid #1e3a5f;
}

tr:hover {
    background: rgba(255,215,0,0.05);
}

/* STATUS COLORS */
.status-pending {
    color: #FFD700;
    font-weight: bold;
}

.status-resolved {
    color: #00ff99;
    font-weight: bold;
}

/* FORM ELEMENTS */
select, input[type="number"], textarea {
    width: 100%;
    padding: 8px;
    border-radius: 5px;
    border: 1px solid #1e3a5f;
    background: #0b1e3c;
    color: white;
}

textarea {
    resize: none;
}

/* BUTTON */
input[type="submit"] {
    margin-top: 8px;
    background: #FFD700;
    color: #001233;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.3s;
}

input[type="submit"]:hover {
    background: #e6c200;
    box-shadow: 0 0 8px rgba(255,215,0,0.6);
}

/* SMALL FORM INSIDE TABLE */
table form {
    display: flex;
    gap: 5px;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll('form').forEach(form => {
        if(form.querySelector('select[name="status"]')){
            form.addEventListener('submit', function(e){
                let status = form.querySelector('select[name="status"]').value;
                if(!confirm(`Mark this complaint as "${status}"?`)){
                    e.preventDefault();
                }
            });
        }
    });
});
</script>

</head>

<body>

<div class="header">
    <img src="KCA_UNIVERSITY_LOGO.png" class="logo">
    <h1>KCA Admin Dashboard</h1>
</div>

<div class="container">

<h2>All Complaints</h2>

<table>
<tr>
<th>ID</th>
<th>User</th>
<th>Title</th>
<th>Description</th>
<th>Status</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php while($row=mysqli_fetch_assoc($complaints_result)){ ?>
<tr>
<td><?php echo $row['id']; ?></td>
<td><?php echo htmlspecialchars($row['user_name']); ?></td>
<td><?php echo htmlspecialchars($row['title']); ?></td>
<td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
<td class="status-<?php echo strtolower($row['status']); ?>">
    <?php echo ucfirst($row['status']); ?>
</td>
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
    $exists = mysqli_num_rows(mysqli_query($conn,"SELECT * FROM feedback WHERE complaint_id=".$frow['id']))>0;
?>
<option value="<?php echo $frow['id']; ?>" <?php if($exists) echo 'disabled'; ?>>
ID <?php echo $frow['id']; ?> - <?php echo htmlspecialchars($frow['title']); ?> <?php if($exists) echo '(Done)'; ?>
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
<form method="POST" action="logout.php" style="text-align:right; margin:10px;">
    <button type="submit">Logout</button>
</form>
</body>
</html>