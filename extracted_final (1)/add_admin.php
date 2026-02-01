<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>;
<?php
session_start();

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $email = $_POST['email'];
    $firstname = $_POST['fname'];
    $lastname = $_POST['lname'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password

    // Database credentials
$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";
    // Connect to the database
    $conn = new mysqli($servername, $db_username, $db_password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if email or username already exists
    $sql_check_existing = "SELECT COUNT(*) as total FROM users WHERE email = ? OR username = ?";
    $stmt_check_existing = $conn->prepare($sql_check_existing);
    $stmt_check_existing->bind_param("ss", $email, $username);
    $stmt_check_existing->execute();
    $result_check_existing = $stmt_check_existing->get_result();
    $row_check_existing = $result_check_existing->fetch_assoc();
    $total_existing = $row_check_existing['total'];
    $stmt_check_existing->close();

    // If there is a matching email or username, exit and return an error
    if ($total_existing > 0) {
        echo "Error: Email or username already exists. Admin user cannot be added.";
        exit();
    }

    // Set user type to admin
    $user_type = 'admin';

    // Prepare and execute SQL statement to insert user data into the database
    $sql_insert_user = "INSERT INTO users (email, Fname, Lname, username, password, user_type) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert_user = $conn->prepare($sql_insert_user);
    $stmt_insert_user->bind_param("ssssss", $email, $firstname, $lastname, $username, $password, $user_type);

    if ($stmt_insert_user->execute()) {
        // Display SweetAlert 2 confirmation dialog
?>
      <body>
        <script>
            Swal.fire({
                title: "Success!",
                text: "Admin user added successfully!",
                icon: "success"
            }).then(() => {
                window.location.href = "um.php?add=success";
            });
        </script>
        </body>
<?php
    } else {
?>
        
        <body>
        <script>
            Swal.fire({
                title: "Error!",
                text: "Failed to add admin user.",
                icon: "error"
            }).then(() => {
                window.location.href = "admin_dashboard.php?error=add_failed";
            });
        </script>
        </body>
<?php
    }
    // Close prepared statement and database connection
    $stmt_insert_user->close();
    $conn->close();
} else {
    // If not a POST request, redirect to the appropriate page
    header("Location: admin_dashboard.php");
    exit();
}
?>
