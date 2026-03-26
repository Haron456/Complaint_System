<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle status update
if(isset($_POST['update'])){
    $id = $_POST['id'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE complaints SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Complaint status updated successfully!');</script>";
}

// Fetch all complaints
$sql = "SELECT c.id, u.name AS user_name, c.title, c.description, c.status, c.date_created
        FROM complaints c
        LEFT JOIN users u ON c.user_id = u.id
        ORDER BY c.date_created DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Dashboard - KCAU Complaint System</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Roboto', sans-serif; background: #1a1a2e; color: #f0f0f0; margin:0; padding:0; }
header { background: #003379; color: #FFD700; padding: 15px 20px; text-align: center; font-size: 24px; font-weight: 700; }
.container { width: 90%; max-width: 1100px; margin: 20px auto; background: #162447; padding: 20px; border-radius: 10px; box-shadow: 0 2px 15px rgba(0,0,0,0.5); }
h2 { color: #FFD700; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { padding: 10px; border: 1px solid #444; text-align: left; vertical-align: top; }
th { background: #003379; color: #FFD700; }
tr:nth-child(even) { background: #1f4068; }
.status-pending { color: #FFD700; font-weight: 600; }
.status-resolved { color: #00ff99; font-weight: 600; }
select, input[type="submit"] { padding: 5px 10px; border-radius: 5px; border:none; cursor:pointer; width: 100%; }
input[type="submit"] { background: #003379; color: #FFD700; transition: 0.3s; }
input[type="submit"]:hover { background: #FFD700; color: #003379; }
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
    <th>Date Created</th>
    <th>Action</th>
</tr>

<?php while($row = mysqli_fetch_assoc($result)) { ?>
<tr>
    <td><?php echo $row['id']; ?></td>
    <td><?php echo $row['user_name']; ?></td>
    <td><?php echo $row['title']; ?></td>
    <td><?php echo nl2br($row['description']); ?></td>
    <td class="status-<?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></td>
    <td><?php echo $row['date_created']; ?></td>
    <td>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
            <input type="hidden" name="old_status" value="<?php echo $row['status']; ?>">
            <label>Status:</label>
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
// Confirm status update and show demo notification
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e){
        let oldStatus = form.querySelector('input[name="old_status"]').value;
        let newStatus = form.querySelector('select[name="status"]').value;
        
        if(!confirm(`Are you sure you want to mark this complaint as "${newStatus}"?`)){
            e.preventDefault();
            return;
        }

        if(oldStatus !== newStatus){
            alert(`Notification: The complaint status has changed from "${oldStatus}" to "${newStatus}"!`);
        }
    });
});
</script>

</body>
</html>