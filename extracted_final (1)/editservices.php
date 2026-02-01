<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

// Define allowed user types for the dashboard
$allowed_user_types = ['admin', 'stylist', 'sec'];

// Check if the user type is allowed to access the dashboard
if (!in_array($_SESSION['user_type'], $allowed_user_types)) {
    echo "Access denied. You do not have permission to access this page.";
    exit();
}

// Establish connection to the database
$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize the $service variable
$service = [];

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $service_id = $_POST['service_id'];
    $Sname = $_POST['Sname'];
    $description = $_POST['description'];
    $Sfee = $_POST['Sfee'];

    // Update the service details in the database
    $sql_update = "UPDATE services SET Sname=?, description=?, Sfee=? WHERE service_id=?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ssdi", $Sname, $description, $Sfee, $service_id);

    if ($stmt->execute()) {
        // Handle file upload if a new file is provided
        if (!empty($_FILES["photo"]["name"])) {
            $target_dir = "services/";
            $target_file = $target_dir . $service_id . ".jpg";

            if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                echo '<body>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script>
                    Swal.fire({
                        title: "Do you want to save changes?",
                        showDenyButton: true,
                        showCancelButton: true,
                        confirmButtonText: "Save",
                        denyButtonText: `Don\'t save`
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire("Service updated successfully!", "", "success").then(() => {
                                window.location.href = "Services.php";
                            });
                        } else if (result.isDenied) {
                            Swal.fire("Changes are not saved", "", "info");
                        }
                    });
                </script>
                </body>';
            } else {
                echo "Error uploading file.";
            }
        } else {
            echo '<body>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                Swal.fire({
                    title: "Do you want to save changes?",
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: "Save",
                    denyButtonText: `Don\'t save`
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire("Service updated successfully!", "", "success").then(() => {
                            window.location.href = "Services.php";
                        });
                    } else if (result.isDenied) {
                        Swal.fire("Changes are not saved", "", "info");
                    }
                });
            </script>
            </body>';
        }
    } else {
        echo "Error: " . $stmt->error;
    }
} else {
    // Get the service details
    if (isset($_GET['id'])) {
        $service_id = $_GET['id'];
        $sql = "SELECT * FROM services WHERE service_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $service = $result->fetch_assoc();
        } else {
            echo "Service not found.";
            exit();
        }
    } else {
        echo "No service ID provided.";
        exit();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service</title>
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
                        <?php if ($_SESSION['user_type'] == 'admin'||$_SESSION['user_type'] == 'sec' ) { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="um.php">User Management</a>
                            </li>
                            <li class="nav-item active">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } elseif($_SESSION['user_type'] == 'stylist' || $_SESSION['user_type'] == 'sec' ) { ?>
                            <li class="nav-item active">
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
                <h2>Edit Service</h2>
            </div>
            <div class="card-body">
    <form method="post" action="editservices.php" enctype="multipart/form-data">
     <input type="hidden" name="service_id" value="<?php echo isset($service['service_id']) ? htmlspecialchars($service['service_id']) : ''; ?>">
        <div class="form-group">
            <label for="Sname">Service Name</label>
            <input type="text" class="form-control" id="Sname" name="Sname" value="<?php echo isset($service['Sname']) ? htmlspecialchars($service['Sname']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea class="form-control" id="description" name="description" required><?php echo isset($service['description']) ? htmlspecialchars($service['description']) : ''; ?></textarea>
        </div>
        <div class="form-group">
            <label for="Sfee">Service Fee</label>
            <input type="number" step="0.01" class="form-control" id="Sfee" name="Sfee" value="<?php echo isset($service['Sfee']) ? htmlspecialchars($service['Sfee']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="photo">Service Photo</label>
            <input type="file" class="form-control-file" id="photo" name="photo">
            <?php
            if (isset($service['service_id'])) {
                $image_path = "services/" . $service['service_id'] . ".jpg";
                if (file_exists($image_path)) {
                    echo "<img src='$image_path' alt='" . htmlspecialchars($service['Sname']) . "' style='max-width: 200px; margin-top: 10px;'>";
                }
            }
            ?>
        </div>
        <div class="form-group mt-3">
            <button type="submit" class="btn btn-primary" name="action" value="update">Update Service</button>
            <a href="Services.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</div>
</div>
</body>
</html>
