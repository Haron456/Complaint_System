<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){ 
    header("Location: login.php"); 
    exit; 
}

include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PHPMailer
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize a message for alerts
$alert_message = "";

// Fetch complaints with feedback
$complaints_result = mysqli_query($conn, "
SELECT c.id, u.name AS user_name, u.email AS user_email, c.title, c.description, c.status, c.date_created,
       f.admin_comment, f.student_rating, f.student_comment
FROM complaints c
LEFT JOIN users u ON c.user_id=u.id
LEFT JOIN feedback f ON f.complaint_id=c.id
ORDER BY c.date_created DESC
");

// Update complaint status
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $status = $_POST['status'];
    mysqli_query($conn, "UPDATE complaints SET status='$status' WHERE id=$id");
    $alert_message = "Complaint #$id status updated to $status.";
}

// Submit admin feedback
if(isset($_POST['submit_feedback'])){
    $complaint_id = intval($_POST['complaint_id']);
    $admin_comment = trim($_POST['admin_comment']);

    $check = mysqli_query($conn, "SELECT * FROM feedback WHERE complaint_id=$complaint_id");
    if(mysqli_num_rows($check)==0){
        mysqli_query($conn, "INSERT INTO feedback (complaint_id, admin_comment, date_created) VALUES ($complaint_id,'$admin_comment', NOW())");
    } else {
        mysqli_query($conn, "UPDATE feedback SET admin_comment='$admin_comment', date_created=NOW() WHERE complaint_id=$complaint_id");
    }

    // Get user email and complaint title
    $res = mysqli_query($conn, "
        SELECT u.email, c.title 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id=u.id 
        WHERE c.id=$complaint_id
    ");
    $row = mysqli_fetch_assoc($res);

    if($row && !empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)){
        $user_email = $row['email'];
        $complaint_title = $row['title'];

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'haronisaac51@gmail.com';
            $mail->Password   = 'poujzkgwaxhynseb';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('haronisaac51@gmail.com', 'KCA Complaint System');
            $mail->addAddress($user_email);

            $mail->isHTML(true);
            $mail->Subject = "Feedback on Complaint #$complaint_id";
            $mail->Body    = "
                <h3>Hello,</h3>
                <p>Your complaint '<b>$complaint_title</b>' has been reviewed.</p>
                <p><b>Admin Comment:</b> $admin_comment</p>
                <p>Thank you for using our system.</p>
            ";
            $mail->send();
            $alert_message = "Feedback submitted and email sent to $user_email successfully!";
        } catch (Exception $e) {
            $alert_message = "Feedback submitted, but email failed: {$mail->ErrorInfo}";
        }
    } else {
        $alert_message = "Feedback submitted, but no valid email found for user.";
    }
}

// --- Reporting & Analytics ---
$total_complaints_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM complaints");
$total_complaints = mysqli_fetch_assoc($total_complaints_res)['total'];

$status_res = mysqli_query($conn, "SELECT status, COUNT(*) AS count FROM complaints GROUP BY status");

$feedback_res = mysqli_query($conn, "SELECT COUNT(*) AS feedback_count FROM feedback");
$feedback_count = mysqli_fetch_assoc($feedback_res)['feedback_count'];

$avg_time_res = mysqli_query($conn, "
    SELECT AVG(TIMESTAMPDIFF(HOUR, c.date_created, f.date_created)) AS avg_response_hours
    FROM complaints c
    INNER JOIN feedback f ON f.complaint_id=c.id
");
$avg_response_hours = mysqli_fetch_assoc($avg_time_res)['avg_response_hours'] ?? 0;
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
    min-height: 100vh;
}

/* HEADER */
.header {
    text-align: center;
    padding: 25px 20px;
}
.logo {
    width: 90px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(255,215,0,0.5);
}
.header h1 {
    color: #FFD700;
    margin-top: 10px;
    font-size: 2.2rem;
    text-shadow: 0 0 8px rgba(255,215,0,0.7);
}

/* CONTAINER */
.container {
    width: 95%;
    max-width: 12000px;
    margin: 25px auto;
    background: #0f2a4d;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.8);
    border: 1px solid rgba(255,215,0,0.2);
}

/* SECTION TITLES */
h2 {
    color: #FFD700;
    border-bottom: 2px solid #FFD700;
    padding-bottom: 5px;
    margin-top: 35px;
    font-size: 1.6rem;
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
    text-align: left;
    font-weight: bold;
}

td {
    padding: 12px;
    border-bottom: 1px solid #1e3a5f;
    vertical-align: top;
}

tr:hover {
    background: rgba(255,215,0,0.08);
    transition: 0.3s;
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
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #1e3a5f;
    background: #0b1e3c;
    color: white;
    font-size: 0.95rem;
    transition: 0.3s;
}

select:focus, textarea:focus, input[type="number"]:focus {
    outline: none;
    border-color: #FFD700;
    box-shadow: 0 0 8px rgba(255,215,0,0.5);
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
    padding: 9px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

input[type="submit"]:hover {
    background: #e6c200;
    box-shadow: 0 0 10px rgba(255,215,0,0.7);
}

/* SMALL FORM INSIDE TABLE */
table form {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

textarea {
    resize: vertical; /* allow vertical resizing */
    min-height: 120px; /* bigger default height */
    max-height: 300px; /* optional max height */
    padding: 12px;
    font-size: 0.95rem;
    line-height: 1.4;
    border-radius: 6px;
    border: 1px solid #1e3a5f;
    background: #0b1e3c;
    color: white;
}

/* LOGOUT BUTTON */
.logout-form {
    position: fixed;
    bottom: 20px;
    right: 20px;
}
.logout-form button {
    background: linear-gradient(135deg,#FFD700,#e6c200);
    color: #001233;
    border: none;
    padding: 14px 18px;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 0 15px rgba(255,215,0,0.5);
    transition: all 0.3s ease;
}
.logout-form button:hover {
    transform: scale(1.08);
    box-shadow: 0 0 25px rgba(255,215,0,0.9);
}
.logout-form button:active {
    transform: scale(0.95);
}

/* ANALYTICS SECTION */
.analytics-card {
    background: #0b1e3c;
    padding: 20px;
    margin: 15px 0;
    border-radius: 12px;
    border: 1px solid rgba(255,215,0,0.2);
    box-shadow: 0 8px 25px rgba(0,0,0,0.5);
}

.analytics-card p {
    font-size: 1.05rem;
    margin: 10px 0;
}
.analytics-card strong {
    color: #FFD700;
}

/* CHARTS */
canvas {
    background: rgba(0,0,0,0.2);
    border-radius: 10px;
    padding: 10px;
    margin-top: 15px;
}

/* RESPONSIVE */
@media (max-width: 768px){
    .header h1 { font-size: 1.8rem; }
    .container { padding: 20px; }
    input[type="submit"], select, textarea { font-size: 0.9rem; }
}
.alert {
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
    color: #001233;
    background: #FFD700;
    
}
</style>
</head>
<body>

<div class="header">
<img src="KCA_UNIVERSITY_LOGO.png" class="logo">
<h1>KCA Admin Dashboard</h1>
</div>

<div class="container">

<?php if($alert_message != "") { ?>
<div class="alert"><?php echo htmlspecialchars($alert_message); ?></div>
<?php } ?>

<h2>All Complaints</h2>
<table>
<tr>
<th>ID</th><th>User</th><th>Title</th><th>Description</th><th>Status</th><th>Admin Comment</th><th>Student Rating</th><th>Student Comment</th><th>Actions</th>
</tr>
<?php while($row=mysqli_fetch_assoc($complaints_result)){ ?>
<tr>
<td><?php echo $row['id']; ?></td>
<td><?php echo htmlspecialchars($row['user_name']); ?></td>
<td><?php echo htmlspecialchars($row['title']); ?></td>
<td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
<td class="status-<?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></td>
<td><?php echo nl2br(htmlspecialchars($row['admin_comment'] ?? '-')); ?></td>
<td><?php echo $row['student_rating'] ?? '-'; ?></td>
<td><?php echo nl2br(htmlspecialchars($row['student_comment'] ?? '-')); ?></td>
<td>
<form method="POST">
<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
<select name="status">
<option value="pending" <?php if($row['status']=='pending') echo 'selected'; ?>>Pending</option>
<option value="resolved" <?php if($row['status']=='resolved') echo 'selected'; ?>>Resolved</option>
</select>
<input type="submit" name="update" value="Update">
</form>

<form method="POST" style="margin-top:5px;">
<input type="hidden" name="complaint_id" value="<?php echo $row['id']; ?>">
<textarea name="admin_comment" placeholder="Write admin feedback..."><?php echo htmlspecialchars($row['admin_comment'] ?? ''); ?></textarea>
<input type="submit" name="submit_feedback" value="Submit Feedback">
</form>
</td>
</tr>
<?php } ?>
</table>

<h2>Reporting & Analytics</h2>
<div class="analytics-card">
<p><strong>Total Complaints:</strong> <?php echo $total_complaints; ?></p>
<p><strong>Complaints with Feedback:</strong> <?php echo $feedback_count; ?></p>
<p><strong>Average Response Time:</strong> <?php echo round($avg_response_hours,2); ?> hours</p>
<canvas id="statusChart" width="400" height="200"></canvas>
</div>

</div>

<form method="POST" action="logout.php" class="logout-form">
<button type="submit">Logout</button>
</form>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Status chart
const ctx = document.getElementById('statusChart').getContext('2d');
const statusData = {
    labels: [
        <?php while($row=mysqli_fetch_assoc($status_res)){ echo "'".ucfirst($row['status'])."',"; } ?>
    ],
    datasets: [{
        label: 'Number of Complaints',
        data: [
            <?php mysqli_data_seek($status_res,0); while($row=mysqli_fetch_assoc($status_res)){ echo $row['count'].","; } ?>
        ],
        backgroundColor: ['#FFD700','#00ff99','#ff4d4d'],
        borderColor: ['#FFD700','#00ff99','#ff4d4d'],
        borderWidth: 1
    }]
};
new Chart(ctx,{ type:'bar', data:statusData, options:{ responsive:true, plugins:{ legend:{ display:false }, title:{ display:true, text:'Complaints by Status' } }, scales:{ y:{ beginAtZero:true } } } });
</script>

</body>
</html>