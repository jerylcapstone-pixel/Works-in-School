<?php
session_start();

// Check if the user is logged in and authorized
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Redirect unauthorized users to the login page
    header("Location: login.php");
    exit();
}

// Establish a database connection
$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Check the database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch the appointment details if app_id is provided
if (isset($_POST['app_id'])) {
    $app_id = $_POST['app_id'];
    $sql_appointment = "SELECT a.*, s.Sname, s.description, s.Sfee 
                        FROM appointments a
                        JOIN services s ON a.service_id = s.service_id 
                        WHERE a.app_id = ?";
    $stmt_appointment = $conn->prepare($sql_appointment);
    $stmt_appointment->bind_param("i", $app_id);
    $stmt_appointment->execute();
    $result_appointment = $stmt_appointment->get_result();
    $appointment = $result_appointment->fetch_assoc();
    $stmt_appointment->close();
} else {
    die("Appointment ID not provided.");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reschedule Appointment</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="admin.css">
    <style>
        .card-container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
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
                         <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle active" href="appointments.php" role="button" data-bs-toggle="dropdown">Appointments</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item active" href="pending_appointments.php">Pending Appointments</a></li>
                                    <li><a class="dropdown-item " href="rescheduled.php">Rescheduled Appointments</a></li>
                                    <li><a class="dropdown-item" href="scheduled_appointments.php">Scheduled Appointments</a></li>
                                    <li><a class="dropdown-item" href="appointments.php">Completed Appointments</a></li>
                                    <li><a class="dropdown-item" href="cancelled.php">Cancelled Appointments</a></li>
                                </ul>
                            </li>
                        <?php if ($_SESSION['user_type'] == 'admin'||$_SESSION['user_type'] == 'sec' ) { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="um.php">User Management</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } elseif ($_SESSION['user_type'] == 'stylist' || $_SESSION['user_type'] == 'sec' ) { ?>
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
    <div class="card card-container">
        <div class="card-header">
            <h2 class="card-title">Reschedule Appointment</h2>
        </div>
        <div class="card-body">
            <form action="reschedule_pappointments.php" method="post">
                <input type="hidden" name="app_id" value="<?php echo $appointment['app_id']; ?>">
                <input type="hidden" name="service_id" value="<?php echo $appointment['service_id']; ?>">
                <div class="form-group">
                    <label for="service_name">Service Name:</label>
                    <input type="text" class="form-control" id="service_name" name="service_name" value="<?php echo htmlspecialchars($appointment['Sname']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="service_description">Service Description:</label>
                    <textarea class="form-control" id="service_description" name="service_description" rows="3" disabled><?php echo htmlspecialchars($appointment['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="service_fee">Service Fee:</label>
                    <input type="text" class="form-control" id="service_fee" name="service_fee" value="<?php echo htmlspecialchars($appointment['Sfee']); ?>" disabled>
                </div>
                <div class="form-group">
    <label for="date">Select Date and Time:</label>
    <input type="datetime-local" class="form-control" id="date" name="date" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
</div>

                <button type="submit" class="btn btn-primary">Reschedule</button>
                <a href="home.php" class="btn btn-secondary">Cancel</a>
                </form>
        </div>
    </div>
</div>
</body>
</html>

           
