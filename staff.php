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
<title>Staff Dashboard - KCAU Complaint System</title>
<style>
/* --- General Styles --- */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #1a1a2e;
    color: #f0f0f0;
    margin: 0;
    padding: 0;
}
header {
    background-color: #003379;
    color: #FFD700;
    text-align: center;
    padding: 15px 0;
    font-size: 26px;
    font-weight: bold;
}
.container {
    width: 90%;
    max-width: 1200px;
    margin: 20px auto;
    background-color: #162447;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
}
h2 {
    color: #FFD700;
    margin-bottom: 15px;
}

/* --- Table Styles --- */
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 12px;
    border: 1px solid #444;
    text-align: left;
}
th {
    background-color: #003379;
    color: #FFD700;
}
tr:nth-child(even) {
    background-color: #1f4068;
}

.status-pending {
    color: #FFD700;
    font-weight: 600;
}
.status-resolved {
    color: #00ff99;
    font-weight: 600;
}

select, input[type="submit"] {
    padding: 6px 10px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}
input[type="submit"] {
    background-color: #003379;
    color: #FFD700;
    transition: 0.3s;
}
input[type="submit"]:hover {
    background-color: #FFD700;
    color: #003379;
}

/* --- Responsive --- */
@media(max-width: 768px){
    th, td { font-size: 14px; padding: 8px; }
    header { font-size: 20px; }
}
</style>
</head>
<body>

<header>Staff Dashboard - KCAU Complaint System</header>

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
    <td><?php echo $row['user_name']; ?></td>
    <td><?php echo $row['title']; ?></td>
    <td><?php echo nl2br($row['description']); ?></td>
    <td class="status-<?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></td>
    <td><?php echo $row['date_created']; ?></td>
    <td>
        <form method="POST" class="status-form">
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

<script>
// Confirm before updating status
document.querySelectorAll('.status-form').forEach(form => {
    form.addEventListener('submit', function(e){
        let select = form.querySelector('select[name="status"]');
        if(!confirm(`Are you sure you want to change status to "${select.value}"?`)){
            e.preventDefault();
        }
    });
});
</script>

</body>
</html>