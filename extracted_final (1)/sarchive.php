<?php
session_start();

// Check if the user is logged in and has admin privileges
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if the service ID is provided
if (isset($_GET['id'])) {
    // Sanitize the input
    $service_id = intval($_GET['id']);
} else {
    echo "Invalid request.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Service</title>
    <!-- Include SweetAlert2 CSS and JavaScript -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
</head>
<body>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, archive it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // If confirmed, send an AJAX request to update the status
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === XMLHttpRequest.DONE) {
                            if (xhr.status === 200) {
                                Swal.fire(
                                    'Archived!',
                                    'Your service has been archived.',
                                    'success'
                                ).then(() => {
                                    window.location.href = 'archives.php';
                                });
                            } else {
                                Swal.fire(
                                    'Error!',
                                    'There was an issue archiving the service.',
                                    'error'
                                );
                            }
                        }
                    };
                    xhr.send("id=<?php echo $service_id; ?>");
                } else {
                    window.location.href = 'services.php'; // Redirect if cancel is clicked
                }
            });
        });
    </script>
</body>
</html>

<?php
// Handle the POST request for archiving the service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    // Sanitize the input
    $service_id = intval($_POST['id']);

    // Establish database connection
    $servername = "localhost";
    $db_username = "u663034616_jeryl";
    $db_password = "Markjeryl-20";
    $database = "u663034616_stylesync";
    $conn = new mysqli($servername, $db_username, $db_password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Enable error reporting
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        // Update the service's status to 'archived'
        $update_sql = "UPDATE services SET status = 'archived' WHERE service_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $service_id);
        if (!$stmt->execute()) {
            throw new Exception("Error updating service status: " . $stmt->error);
        }
        echo "Service archived successfully.";
    } catch (Exception $e) {
        echo $e->getMessage();
    }

    // Close the database connection
    $conn->close();
    exit();
}
?>
