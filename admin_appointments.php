<?php
session_start();

// Optional: Uncomment if you want this page to be restricted to admin login
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: login.php");
//     exit();
// }

// Database connection
$host = 'localhost';
$db = 'autism_appointments';
$user = 'root'; // Change if needed
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all appointments
$sql = "SELECT * FROM appointments ORDER BY appointment_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Appointments - Admin Panel</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        h2 { text-align: center; margin-top: 20px; }
        table { width: 90%; margin: 20px auto; border-collapse: collapse; }
        th, td { padding: 12px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { text-decoration: none; color: #007bff; }
    </style>
</head>
<body>

    <h2>All Booked Appointments</h2>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Parent Name</th>
            <th>Child Name</th>
            <th>Child Age</th>
            <th>Phone</th>
            <th>Doctor</th>
            <th>Appointment Date</th>
            <th>Notes</th>
            <th>Booked On</th>
        </tr>

        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>".htmlspecialchars($row['id'])."</td>";
                echo "<td>".htmlspecialchars($row['parent_name'])."</td>";
                echo "<td>".htmlspecialchars($row['child_name'])."</td>";
                echo "<td>".htmlspecialchars($row['child_age'])."</td>";
                echo "<td>".htmlspecialchars($row['phone'])."</td>";
                echo "<td>".htmlspecialchars($row['doctor'])."</td>";
                echo "<td>".htmlspecialchars($row['appointment_date'])."</td>";
                echo "<td>".htmlspecialchars($row['message'])."</td>";
                echo "<td>".htmlspecialchars($row['created_at'])."</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='9'>No appointments found.</td></tr>";
        }
        ?>

    </table>

    <div class="back-link">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>

</body>
</html>

<?php
$conn->close();
?>

