<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Redirect to login page if the user is not logged in
    header("Location: login.php");
    exit();
}

// Define allowed user types for the dashboard
$allowed_user_types = ['client', 'admin', 'stylist', 'sec']; // Modify as needed

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
    <title>Add Service</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js"></script>
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
                        <li class="nav-item ">
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
                       <?php if ($_SESSION['user_type'] == 'admin'||$_SESSION['user_type'] == 'sec' ||$_SESSION['user_type'] == 'stylist' ) { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="um.php">User Management</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } elseif ($_SESSION['user_type'] == 'stylist' || $_SESSION['user_type'] == 'sec' ) { ?>
                            <li class="nav-item">
                                <a class="nav-link active" href="Services.php">Services</a>
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
        <div class="card">
            <div class="card-header">
                <h2>Add Services</h2>
            </div>
            <div class="card-body">
                <form action="addser.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="Sname">Service Name:</label>
                        <input type="text" class="form-control" id="Sname" name="Sname" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="Sfee">Service Fee (₱):</label>
                        <input type="number" step="0.01" class="form-control" id="Sfee" name="Sfee" required>
                    </div>
                    <div class="form-group">
                        <label for="photo">Service Photo</label>
                        <input type="file" name="photo" id="photo" class="py-1 px-2" style="border: none; border-width: 1px; border-style: solid; background-color: white; border-color: gray; border-radius: 3px;" onchange="previewImage(this);">
                        <img id="preview" src="#" alt="Preview" style="display: none; max-width: 100px; max-height: 100px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">Submit</button>
                    <button type="button" class="btn btn-secondary btn-lg" onclick="location.href='Services.php'">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            var preview = document.getElementById('preview');
            var file = input.files[0];
            var reader = new FileReader();

            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };

            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
