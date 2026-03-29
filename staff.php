<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch all complaints with user names
$complaints_sql = "
SELECT c.id, u.name AS user_name, c.title, c.description, c.status, c.date_created
FROM complaints c
LEFT JOIN users u ON c.user_id=u.id
ORDER BY c.date_created DESC
";
$complaints_result = mysqli_query($conn, $complaints_sql);

// Update status
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $status = $_POST['status'];
    mysqli_query($conn, "UPDATE complaints SET status='$status' WHERE id=$id");
    header("Location: staff.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Dashboard</title>

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
select {
    padding: 6px;
    border-radius: 5px;
    border: 1px solid #1e3a5f;
    background: #0b1e3c;
    color: white;
}

/* BUTTON */
input[type="submit"] {
    margin-top: 2px;
    background: #FFD700;
    color: #001233;
    border: none;
    padding: 6px 12px;
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

/* RESPONSIVE */
@media(max-width:768px){
    th, td { font-size: 14px; padding: 8px; }
    .header h1 { font-size: 20px; }
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
    <h1>KCA Staff Dashboard</h1>
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

</div>

</body>
</html>