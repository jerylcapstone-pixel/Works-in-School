<?php
session_start();

// Check if the user is logged in and authorized
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Redirect unauthorized users to the login page
    header("Location: login.php");
    exit();
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Establish a database connection
    $servername = "localhost";
    $db_username = "u663034616_jeryl";
    $db_password = "Markjeryl-20";
    $database = "u663034616_stylesync";
    $conn = new mysqli($servername, $db_username, $db_password, $database);

    // Check the database connection
    if ($conn->connect_error) {
        die(json_encode(array("status" => "error", "message" => "Connection failed: " . $conn->connect_error)));
    }

    // Validate input and sanitize data
    $app_id = $_POST['app_id'];
    $selected_datetime = $_POST['date'];

    // Format the start and end date in 12-hour format
    $new_date = date('Y-m-d h:i:s A', strtotime($selected_datetime));
    $end_date = date('Y-m-d h:i:s A', strtotime($selected_datetime . ' +3 hours'));

    // Update the appointment date and end time in the database
    $update_sql = "UPDATE appointments SET status = 'rescheduled', date = ?, end_time = ? WHERE app_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $new_date, $end_date, $app_id);

    if ($update_stmt->execute()) {
        echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Appointment Rescheduled</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
    <script>
        Swal.fire({
            title: 'Success',
            text: 'The appointment was rescheduled successfully.',
            icon: 'success',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'pending_appointments.php';
            }
        });
    </script>
</body>
</html>";
    } else {
        echo "Error: " . $conn->error;
    }

    $update_stmt->close();
}

$conn->close();
?>
