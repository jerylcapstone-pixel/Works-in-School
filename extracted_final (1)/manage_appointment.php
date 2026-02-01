<?php
session_start();

$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";
$conn = new mysqli($servername, $db_username, $db_password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stylist_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $app_id = $_POST['app_id'];
    $action = $_POST['action'];

    if ($action == 'accept') {
        // Check if the stylist is free at the specified date and time
        $sql = "SELECT date, DATE_ADD(date, INTERVAL 3 HOUR) AS end_time FROM appointments WHERE app_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pending_appointment = $result->fetch_assoc();
        $appointment_date = $pending_appointment['date'];
        $appointment_end_time = $pending_appointment['end_time'];

        // Debugging: Log appointment date and end time
        error_log("Appointment ID: $app_id, Appointment Date: $appointment_date, Appointment End Time: $appointment_end_time");

        // Check for conflicting appointments
        $sql = "SELECT * FROM appointments WHERE assigned_stylist_id = ? AND status = 'scheduled' AND (
                    (date <= ? AND DATE_ADD(date, INTERVAL 3 HOUR) > ?) OR
                    (date < ? AND DATE_ADD(date, INTERVAL 3 HOUR) >= ?)
                )";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $stylist_id, $appointment_date, $appointment_date, $appointment_end_time, $appointment_end_time);
        $stmt->execute();
        $result = $stmt->get_result();

        // Debugging: Check if any conflicting appointments were found
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                error_log("Conflicting Appointment: " . print_r($row, true));
            }
            echo '<!DOCTYPE html>
                        <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Schedule Conflict</title>
                            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                        </head>
                        <body>
                            <script>
                    Swal.fire({
                        title: "Schedule Conflict",
                        text: "You already have a client scheduled at that time.",
                        icon: "warning",
                        confirmButtonColor: "#3085d6",
                        confirmButtonText: "OK"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "pending_appointments.php";
                        }
                    });
                  </script>
                        </body>
                        </html>';
            exit();
        } else {
            // Update the status of the appointment to 'scheduled'
            $sql = "UPDATE appointments SET assigned_stylist_id = ?, status = 'scheduled' WHERE app_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $stylist_id, $app_id);
            if ($stmt->execute()) {
                // Display SweetAlert for success
                echo '<!DOCTYPE html>
                        <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Booking Successful</title>
                            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                        </head>
                        <body>
                            <script>
                                Swal.fire({
                                    title: "Booking Successful!",
                                    text: "Redirecting to the dashboard...",
                                    icon: "success",
                                    showConfirmButton: false
                                });
                                setTimeout(function() {
                                    window.location.href = "scheduled_appointments.php";
                                }, 3000); // Redirect after 3 seconds
                            </script>
                        </body>
                        </html>';
                exit();
            } else {
                // SweetAlert for error
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
                            text: "Error approving appointment. Please try again.",
                            icon: "error",
                            confirmButtonColor: "#3085d6",
                            confirmButtonText: "OK"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = "pending_appointments.php";
                            }
                        });
                      </script>
                        </body>
                        </html>';
                exit();
            }
        }
    } elseif ($action == 'cancel') {
        // Cancel the appointment and update the status to 'pending'
        $sql = "UPDATE appointments SET assigned_stylist_id = NULL, status = 'pending' WHERE app_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $app_id);
        if ($stmt->execute()) {
            // Display SweetAlert for success
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
                                text: "Redirecting...",
                                icon: "success",
                                showConfirmButton: false
                            });
                            setTimeout(function() {
                                window.location.href = "pending_appointments.php";
                            }, 3000); // Redirect after 3 seconds
                        </script>
                    </body>
                    </html>';
            exit();
        } else {
            // SweetAlert for error
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
                        text: "Error cancelling appointment. Please try again.",
                        icon: "error",
                        confirmButtonColor: "#3085d6",
                        confirmButtonText: "OK"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "pending_appointments.php";
                        }
                    });
                  </script>
                    </body>
                    </html>';
            exit();
        }
    }

    $stmt->close();
}

$conn->close();
?>
