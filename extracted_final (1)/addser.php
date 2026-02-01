<?php
$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $Sname = $_POST['Sname'];
    $description = $_POST['description'];
    $Sfee = $_POST['Sfee'];

    // Prepare the SQL statement to avoid SQL injection
    $stmt = $conn->prepare("INSERT INTO `services` (`Sname`, `description`, `Sfee`, `status`) VALUES (?, ?, ?, 'active')");
    $stmt->bind_param("ssd", $Sname, $description, $Sfee);

    if ($stmt->execute() === TRUE) {
        $service_id = $stmt->insert_id; // Get the auto-incremented service_id

        // Handle file upload
        $target_dir = "services/";
        $target_file = $target_dir . $service_id . ".jpg";
        
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            // Success message with SweetAlert
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
            title: 'Service added successfully',
            text: 'The service has been added and the photo uploaded successfully.',
            icon: 'success',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'Services.php';
            }
        });
    </script>
</body>
</html>";
        } else {
            echo "Error uploading file.";
        }
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
}
$conn->close();
?>
