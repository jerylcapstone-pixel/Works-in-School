<?php
session_start();

// Check if the user is logged in and authorized
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Redirect unauthorized users to the login page
    header("Location: login.php");
    exit();
}

// Database connection details
$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve user ID and type from session
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// SQL query to fetch cancelled appointments based on user type
$sql = "SELECT a.*, DATE_FORMAT(a.date, '%Y-%m-%d %h:%i:%s %p') AS formatted_date, 
               DATE_FORMAT(DATE_ADD(a.date, INTERVAL 3 HOUR), '%Y-%m-%d %h:%i:%s %p') AS end_time_formatted,
               s.Sname, u.Fname AS client_fname, u.Lname AS client_lname, 
               stylist.Fname AS stylist_fname, stylist.Lname AS stylist_lname,
               a.hairstyle, a.hair_color
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN users u ON a.user_id = u.user_id
        LEFT JOIN users stylist ON a.assigned_stylist_id = stylist.user_id
        WHERE a.status = 'cancelled' ";

// Append user type specific condition to the SQL query
if ($user_type == 'client') {
    $sql .= "AND a.user_id = ? ";
} elseif ($user_type == 'stylist') {
    $sql .= "AND a.assigned_stylist_id = ? ";
}

// Append search criteria to the SQL query if search is set
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $sql .= "AND (s.Sname LIKE '%$search%' OR u.Fname LIKE '%$search%' OR u.Lname LIKE '%$search%' 
                  OR stylist.Fname LIKE '%$search%' OR stylist.Lname LIKE '%$search%' 
                  OR DATE_FORMAT(a.date, '%Y-%m-%d %h:%i:%s %p') LIKE '%$search%' 
                  OR a.total_payment LIKE '%$search%' OR a.hairstyle LIKE '%$search%' OR a.hair_color LIKE '%$search%')";
}

// Prepare and execute SQL query
$stmt = $conn->prepare($sql);
if ($user_type == 'client' || $user_type == 'stylist') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cancelled Appointments</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="Admin">
        <nav class="navbar navbar-expand-lg navbar-dark custom-navbar sticky-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <img src="logo.png" alt="Logo" style="max-height: 40px;">
                    StyleSync
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="collapsibleNavbar">
                    <ul class="navbar-nav ms-auto me-0">
                        <li class="nav-item">
                            <a class="nav-link" href="home.php">Home</a>
                        </li>
                        <li class="nav-item dropdown active">
                            <a class="nav-link dropdown-toggle" href="appointments.php" role="button" data-bs-toggle="dropdown">Appointments</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="pending_appointments.php">Pending Appointments</a></li>
                                <li><a class="dropdown-item" href="scheduled_appointments.php">Scheduled Appointments</a></li>
                                <li><a class="dropdown-item" href="appointments.php">Completed Appointments</a></li>
                                <li><a class="dropdown-item active" href="cancelled.php">Cancelled Appointments</a></li>
                            </ul>
                        </li>
                        <?php if ($_SESSION['user_type'] == 'admin' || $_SESSION['user_type'] == 'sec') { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="um.php">User Management</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } elseif ($_SESSION['user_type'] == 'stylist' || $_SESSION['user_type'] == 'sec') { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user_info.php">User Info</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>

    <div class="container mt-4">
        <h2>Cancelled Appointments</h2>
        <form method="GET">
            <div class="input-group mb-3">
                <input type="text" class="form-control" placeholder="Search..." name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i></button>
                </div>
            </div>
        </form>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Client</th>
                    <th>Stylist</th>
                    <th>Date</th>
                    <th>End Time</th>
                    <th>Hairstyle</th>
                    <th>Hair Color</th>
                    <th>Total Payment</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['Sname'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['client_fname'] . ' ' . $row['client_lname']); ?></td>
                        <td><?php echo isset($row['stylist_fname']) ? htmlspecialchars($row['stylist_fname'] . ' ' . $row['stylist_lname']) : 'Not Assigned'; ?></td>
                        <td><?php echo htmlspecialchars($row['formatted_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['end_time_formatted']); ?></td>
                        <td><?php echo isset($row['hairstyle']) ? htmlspecialchars($row['hairstyle']) : 'N/A'; ?></td>
                        <td><?php echo isset($row['hair_color']) ? htmlspecialchars($row['hair_color']) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($row['total_payment']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
