<?php
session_start();

function showError($message) {
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
                text: "'.htmlspecialchars($message).'",
                icon: "error",
                showConfirmButton: true
            }).then(() => {
                window.location.href = "login.php";
            });
        </script>
    </body>
    </html>';
    exit();
}

if (!isset($_SESSION['username'], $_SESSION['user_id'], $_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

// Set the correct timezone for PHP
date_default_timezone_set('Asia/Manila'); // Change to your desired timezone

$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";
$conn = new mysqli($servername, $db_username, $db_password, $database);

if ($conn->connect_error) {
    showError("Connection failed: " . $conn->connect_error);
}

// Set the correct timezone for the MySQL connection
$conn->query("SET time_zone = '+08:00'"); // Change to your desired timezone offset

if (isset($_POST['service_id'], $_POST['date'])) {
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $selected_datetime = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);

    if (!$service_id || !$selected_datetime) {
        showError("Invalid service ID or date.");
    }

    // Convert to MySQL datetime format
    $start_datetime = date('Y-m-d H:i:s', strtotime($selected_datetime));
    $end_datetime = date('Y-m-d H:i:s', strtotime($selected_datetime . ' +3 hours'));

    $conn->begin_transaction();

    try {
        $sql_service = "SELECT Sfee FROM services WHERE service_id = ?";
        $stmt_service = $conn->prepare($sql_service);
        $stmt_service->bind_param("i", $service_id);
        $stmt_service->execute();
        $result_service = $stmt_service->get_result();
        if ($result_service->num_rows > 0) {
            $service = $result_service->fetch_assoc();
            $total_payment = (float)$service['Sfee']; // Ensure the value is treated as a float
        } else {
            throw new Exception("Service not found.");
        }
        $stmt_service->close();

        // Check if the service is related to hairstyling or hair coloring
        $hairstyle = filter_input(INPUT_POST, 'hairstyle', FILTER_SANITIZE_STRING);
        $hair_color = filter_input(INPUT_POST, 'hair_color', FILTER_SANITIZE_STRING);

        if (!empty($hairstyle)) {
            $hair_color = null;
        } elseif (!empty($hair_color)) {
            $hairstyle = null;
        } else {
            $hair_color = null;
            $hairstyle = null;
        }

        $sql = "INSERT INTO appointments (user_id, service_id, total_payment, date, end_time, hairstyle, hair_color, status, date_booked) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iidssss", $_SESSION['user_id'], $service_id, $total_payment, $start_datetime, $end_datetime, $hairstyle, $hair_color);

        if (!$stmt->execute()) {
            throw new Exception("There was an error booking your appointment. Please try again.");
        }

        $conn->commit();

        // Output success message with redirect after 1 second
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
            title: 'Booked successfully',
            text: 'The service was booked successfully.',
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

    } catch (Exception $e) {
        $conn->rollback();
        showError($e->getMessage());
    }

    $stmt->close();
} else {
    showError("Service ID or date not provided.");
}

$conn->close();
?>