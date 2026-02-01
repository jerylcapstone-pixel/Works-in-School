<?php
session_start();

// Check if the user ID is provided
if (!isset($_POST['userId'])) {
    header("Location: admin_dashboard.php?error=missing_user_id");
    exit();
}

// Retrieve the user ID from the POST request
$user_id = $_POST['userId'];

// Database connection parameters
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

// Construct SQL query to delete the user
$sql = "DELETE FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);

// Execute the query
if ($stmt->execute()) {
    ?>
    <body>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <script>
        Swal.fire({
            title: "Success!",
            text: "User deleted successfully!",
            icon: "success"
        }).then(() => {
            window.location.href = "admin_dashboard.php?delete=success";
        });
    </script>
    </body>
    <?php
} else {
    ?>
    <body>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <script>
        Swal.fire({
            title: "Error!",
            text: "Failed to delete user.",
            icon: "error"
        }).then(() => {
            window.location.href = "admin_dashboard.php?error=delete_failed";
        });
    </script>
    </body>
    <?php
}

// Close statement and connection
$stmt->close();
$conn->close();
?>
