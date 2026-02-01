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

// Check if the request method is POST and if the app_id is set
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['app_id'])) {
    $appointment_id = $_POST['app_id'];

    // Prepare and execute SQL query to update the appointment status to 'cancelled'
    $update_sql = "UPDATE appointments SET status = 'cancelled' WHERE app_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();

    // Check if the update was successful
    if ($stmt->affected_rows > 0) {
        // Show success message and redirect
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Appointment Cancelled</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: "Appointment Cancelled",
                    text: "The appointment has been successfully cancelled.",
                    icon: "success",
                    confirmButtonText: "OK"
                }).then(() => {
                    window.location.href = "cancelled.php";
                });
            </script>
        </body>
        </html>';
    } else {
        // Show error message
       echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Appointment Cancelled</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: "Error",
                    text: "Error please try again!",
                    icon: "error",
                    confirmButtonText: "OK"
                }).then(() => {
                    window.location.href = "pending_appointments.php";
                });
            </script>
        </body>
        </html>';
    }

    // Close the prepared statement and database connection
    $stmt->close();
} else {
    // Redirect users if the request method is not POST or app_id is not set
    header("Location: pending_appointments.php");
    exit();
}

$conn->close();
?>
