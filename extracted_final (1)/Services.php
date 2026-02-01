<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Redirect to login page if the user is not logged in
    header("Location: login.php");
    exit();
}

// Define allowed user types for the dashboard
$allowed_user_types = ['client', 'admin', 'stylist', 'sec'];

// Check if the user type is allowed to access the dashboard
if (!in_array($_SESSION['user_type'], $allowed_user_types)) {
    // Redirect to an error page or display an access denied message
    echo "Access denied. You do not have permission to access this page.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js"></script>
    <style>
        .card-title {
            font-size: 1.25rem;
            font-weight: bold;
        }
        .card-text {
            font-size: 1rem;
        }
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-footer .btn {
            flex: 1;
            margin: 0 5px;
            white-space: nowrap;
        }
        .card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .card-body {
            flex: 1;
        }
        .card img {
            height: 200px;
            object-fit: cover;
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
                        <a class="nav-link dropdown-toggle" href="appointments.php" role="button" data-bs-toggle="dropdown">Appointments</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="pending_appointments.php">Pending Appointments</a></li>
                            <li><a class="dropdown-item " href="rescheduled.php">Rescheduled Appointments</a></li>
                            <li><a class="dropdown-item" href="scheduled_appointments.php">Scheduled Appointments</a></li>
                            <li><a class="dropdown-item" href="appointments.php">Completed Appointments</a></li>
                            <li><a class="dropdown-item" href="cancelled.php">Cancelled Appointments</a></li>
                        </ul>
                    </li>
                    <?php if ($_SESSION['user_type'] == 'admin'||$_SESSION['user_type'] == 'sec' ||$_SESSION['user_type'] == 'sec' ) { ?>
                        <li class="nav-item">
                            <a class="nav-link" href="um.php">User Management</a>
                        </li>
                        <li class="nav-item active">
                            <a class="nav-link" href="Services.php">Services</a>
                        </li>
                    <?php } elseif ($_SESSION['user_type'] == 'stylist' || $_SESSION['user_type'] == 'sec' ) { ?>
                        <li class="nav-item active">
                            <a class="nav-link " href="Services.php">Services</a>
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
    <div class="row">
        <div class="col">
            <a href="addservices.php" class="btn btn-primary mb-3">Add Service</a>
        </div>
        <div class="col text-right">
            <a href="archives.php" class="btn btn-secondary mb-3">Archives</a>
        </div>
    </div>
    <div class="row">
        <div class="col text-center">
            <h2>Services</h2>
        </div>
    </div>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3">
        <?php
        // Establish connection to database
        $servername = "localhost";
        $db_username = "u663034616_jeryl";
        $db_password = "Markjeryl-20";
        $database = "u663034616_stylesync";
        $conn = new mysqli($servername, $db_username, $db_password, $database);

        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Fetch active services data from the database
        $sql = "SELECT * FROM services WHERE status = 'active'";
        $result = $conn->query($sql);

        // Check if there are any active services available
        if ($result->num_rows > 0) {
            // Loop through each row of data
            while ($row = $result->fetch_assoc()) {
                ?>
                <div class="col mb-4">
                    <div class="card shadow-sm">
                        <?php
                        // Display service image if available
                        $image_path = "services/" . $row['service_id'] . ".jpg";
                        if (file_exists($image_path)) {
                            ?>
                            <img class="card-img-top" src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($row['Sname']); ?>">
                            <?php
                        } else {
                            // Display a placeholder image if no image isavailable
?>
<img class="card-img-top" src="placeholder.jpg"; alt="Placeholder">
<?php
                     }
                     ?>
<div class="card-body">
<h5 class="card-title"><?php echo htmlspecialchars($row['Sname']); ?></h5>
<p class="card-text"><?php echo htmlspecialchars($row['description']); ?></p>
<p class="card-text">Service Fee: ₱<?php echo htmlspecialchars(number_format($row['Sfee'], 2)); ?></p>
<p class="card-text">Service ID: <?php echo htmlspecialchars($row['service_id']); ?></p>
</div>
<div class="card-footer">
<?php if ($_SESSION['user_type'] == 'admin') { ?>
<a href="editservices.php?id=<?php echo $row['service_id']; ?>" class="btn btn-primary">Edit</a>
<a href="sarchive.php?id=<?php echo $row['service_id']; ?>" class="btn btn-danger">Archive</a>
<?php } ?>
<a href="bookser.php?id=<?php echo $row['service_id']; ?>" class="btn btn-success">Book Now</a>
</div>
</div>
</div>
<?php
         }
     } else {
         // If no active services are available, display a message
         ?>
<div class="col-md-12">
<p>No active services available.</p>
</div>
<?php
     }
     // Close database connection
     $conn->close();
     ?>
</div>

</div>
</body>
</html>
