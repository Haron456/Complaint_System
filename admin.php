<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Update complaint status
if(isset($_POST['update'])){
    $id = $_POST['id'];
    $status = $_POST['status'];
    $sql = "UPDATE complaints SET status='$status' WHERE id='$id'";
    mysqli_query($conn, $sql);
    header("Location: admin.php"); 
    exit;
}

// Handle feedback submission
if(isset($_POST['submit_feedback'])){
    $complaint_id = $_POST['complaint_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    $insert_sql = "INSERT INTO feedback (complaint_id, rating, comment)
                   VALUES ('$complaint_id', '$rating', '$comment')";
    mysqli_query($conn, $insert_sql);
    header("Location: admin.php"); 
    exit;
}

// Fetch complaints for table
$complaints_sql = "SELECT c.id, u.name AS user_name, c.title, c.description, c.status, c.date_created
                   FROM complaints c
                   LEFT JOIN users u ON c.user_id = u.id
                   ORDER BY c.date_created DESC";
$complaints_result = mysqli_query($conn, $complaints_sql);

// Fetch feedback pending
$feedback_sql = "SELECT c.id, c.title FROM complaints c
                 LEFT JOIN feedback f ON c.id = f.complaint_id
                 WHERE c.status='resolved' AND f.id IS NULL";
$feedback_result = mysqli_query($conn, $feedback_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - KCAU Complaint System</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Roboto', sans-serif; margin:0; padding:0; background: #1a1a2e; color:#f0f0f0; }
    header { background: #003379; color: #FFD700; padding:15px 20px; text-align:center; font-size:24px; font-weight:bold; }
    .sidebar { width: 220px; background: #162447; height: 100vh; position: fixed; top:0; left:0; padding-top:60px; }
    .sidebar a { display:block; color:#FFD700; padding:15px 20px; text-decoration:none; font-weight:500; }
    .sidebar a:hover { background:#003379; color:#fff; }
    .main { margin-left:220px; padding:20px; }
    .container { background:#162447; padding:20px; border-radius:10px; box-shadow:0 2px 15px rgba(0,0,0,0.5); margin-bottom:20px; }
    h2 { color:#FFD700; }
    table { width:100%; border-collapse:collapse; margin-top:15px; }
    th, td { padding:10px; border:1px solid #444; text-align:left; }
    th { background:#003379; color:#FFD700; }
    tr:nth-child(even) { background:#1f4068; }
    .status-pending { color: #FFD700; font-weight:600; }
    .status-resolved { color: #00ff99; font-weight:600; }
    input, select, textarea { padding:8px; margin:5px 0 15px; border-radius:5px; border:1px solid #444; background:#1f4068; color:#f0f0f0; width:100%; }
    input[type="submit"] { background:#003379; color:#FFD700; border:none; padding:10px 20px; border-radius:5px; cursor:pointer; transition:0.3s; }
    input[type="submit"]:hover { background:#FFD700; color:#003379; }
</style>
</head>
<body>

<header>KCAU Admin Dashboard</header>

<div class="sidebar">
    <a href="#dashboard">Dashboard</a>
    <a href="#feedback">Feedback</a>
</div>

<div class="main">
    <div class="container" id="dashboard">
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
            <?php while($row = mysqli_fetch_assoc($complaints_result)) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo $row['user_name']; ?></td>
                <td><?php echo $row['title']; ?></td>
                <td><?php echo $row['description']; ?></td>
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
    </div>

    <div class="container" id="feedback">
        <h2>Submit Feedback (Resolved)</h2>
        <?php if(mysqli_num_rows($feedback_result) > 0){ ?>
        <form method="POST">
            <label>Select Complaint:</label>
            <select name="complaint_id">
                <?php while($frow = mysqli_fetch_assoc($feedback_result)){ ?>
                    <option value="<?php echo $frow['id']; ?>"><?php echo $frow['title']; ?></option>
                <?php } ?>
            </select>

            <label>Rating (1–5):</label>
            <input type="number" name="rating" min="1" max="5" required>

            <label>Comment:</label>
            <textarea name="comment" rows="3" placeholder="Write feedback"></textarea>

            <input type="submit" name="submit_feedback" value="Submit Feedback">
        </form>
        <?php } else { echo "<p>No resolved complaints pending feedback.</p>"; } ?>
    </div>
</div>

<script>
// Confirm before updating status
document.querySelectorAll('form').forEach(form => {
    if(form.querySelector('select[name="status"]')) {
        form.addEventListener('submit', function(e){
            let status = form.querySelector('select[name="status"]').value;
            if(!confirm(`Are you sure you want to mark this complaint as "${status}"?`)){
                e.preventDefault();
            }
        });
    }
});

// Live rating display for feedback
let ratingInput = document.querySelector('input[name="rating"]');
if(ratingInput){
    let ratingLabel = document.createElement('span');
    ratingLabel.style.marginLeft = '10px';
    ratingLabel.style.fontWeight = 'bold';
    ratingInput.parentNode.insertBefore(ratingLabel, ratingInput.nextSibling);

    ratingLabel.textContent = ratingInput.value;

    ratingInput.addEventListener('input', function(){
        ratingLabel.textContent = ratingInput.value;
    });
}

// Smooth scroll for sidebar links
document.querySelectorAll('.sidebar a').forEach(link => {
    link.addEventListener('click', function(e){
        e.preventDefault();
        let target = document.querySelector(link.getAttribute('href'));
        if(target){
            window.scrollTo({top: target.offsetTop - 20, behavior:'smooth'});
        }
    });
});
</script>

</body>
</html>