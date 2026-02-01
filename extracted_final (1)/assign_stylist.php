<?php
session_start();

// Check if the user is logged in and authorized
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Redirect unauthorized users to the login page
    header("Location: login.php");
    exit();
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Use the selected stylist ID from the form
    $assigned_stylist_id = $_POST['assigned_stylist_id'];

    // Database connection details
    $servername = "localhost";
    $db_username = "u663034616_jeryl";
    $db_password = "Markjeryl-20";
    $database = "u663034616_stylesync";

    // Create a new database connection
    $conn = new mysqli($servername, $db_username, $db_password, $database);

    // Check if the database connection is successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Prepare and execute SQL query to update the assigned stylist for the appointment
    $update_sql = "UPDATE appointments SET assigned_stylist_id = ?, status = 'pending' WHERE app_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ii", $assigned_stylist_id, $_POST['app_id']);
    $stmt->execute();

    // Check if the update was successful
    if ($stmt->affected_rows > 0) {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stylist Assigned</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        Swal.fire({
            title: "Stylist Assigned",
            text: "The appointment has been successfully assigned to a stylist and set to scheduled status.",
            icon: "success",
            confirmButtonText: "OK"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "pending_appointments.php";
            }
        });
    </script>
</body>
</html>';
    } else {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        Swal.fire({
            title: "Error",
            text: "Failed to assign stylist to the appointment.",
            icon: "error",
            confirmButtonText: "OK"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "pending_appointments.php";
            }
        });
    </script>
</body>
</html>';
    }

    // Close the prepared statement and database connection
    $stmt->close();
    $conn->close();
} else {
    // Redirect users if the request method is not POST
    header("Location: index.php");
    exit();
}
?>
