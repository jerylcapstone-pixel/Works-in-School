<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
<?php
session_start();

// Check if the user is logged in and is a stylist
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'stylist') {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";
$conn = new mysqli($servername, $db_username, $db_password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include SweetAlert2 library


// Update the appointment status to 'completed'
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['app_id'])) {
    $app_id = $_POST['app_id'];
    $stylist_id = $_SESSION['user_id'];
    
    // Ensure that the stylist is completing their own appointment
    $sql = "UPDATE appointments SET status = 'completed' WHERE app_id = ? AND assigned_stylist_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $app_id, $stylist_id);
    
    if ($stmt->execute()) {
      
        echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Service Added</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
    <script>
        Swal.fire({
            title: 'Appointment is completed',
            text: 'The appointment has concluded',
            icon: 'success',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'appointments.php';
            }
        });
    </script>
</body>
</html>";
    } else {
        echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Service Added</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
    <script>
        Swal.fire({
            title: 'Error',
            text: 'The appointment failed to finish.',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'scheduled_appointments.php';
            }
        });
    </script>
</body>
</html>";
    }
    
    $stmt->close();
}

$conn->close();
?>
