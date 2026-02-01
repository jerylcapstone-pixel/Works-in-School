<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
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

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $userId = $_POST['userId'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $user_type = $_POST['user_type'];
    $email = $_POST['email']; // Retrieve email field

    // Check each field for changes and add it to the update query if it's not empty or unchanged
    // Prepare update query
    $sql = "UPDATE users SET ";
    // Initialize an array to store the parameters for binding
    $params = array();
    // Initialize a string to store the types of parameters for binding
    $types = "";

    // Check if the new username is already used by another user
    $checkUsernameQuery = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
    $checkUsernameStmt = $conn->prepare($checkUsernameQuery);
    $checkUsernameStmt->bind_param("si", $username, $userId); // Assuming user_id is an integer
    $checkUsernameStmt->execute();
    $checkUsernameResult = $checkUsernameStmt->get_result();

    if ($checkUsernameResult->num_rows > 0) {
        echo "Error: Username already exists. Please choose a different username.";
        exit(); // Stop further processing
    }

    if (!empty($username)) {
        // If the username is unique, add it to the update query
        $sql .= "username=?, ";
        $params[] = $username;
        $types .= "s";
    }
    if (!empty($password)) {
        $sql .= "password=?, ";
        $params[] = $password;
        $types .= "s";
    }
    if (!empty($fname)) {
        $sql .= "Fname=?, ";
        $params[] = $fname;
        $types .= "s";
    }
    if (!empty($lname)) {
        $sql .= "Lname=?, ";
        $params[] = $lname;
        $types .= "s";
    }
    if (!empty($user_type)) {
        $sql .= "user_type=?, ";
        $params[] = $user_type;
        $types .= "s";
    }
    if (!empty($email)) {
        $sql .= "email=?, ";
        $params[] = $email;
        $types .= "s";
    }

    // Remove the trailing comma and space from the update query
    $sql = rtrim($sql, ", ");

    // Add the WHERE clause to the update query
    $sql .= " WHERE user_id=?";

    // Add the user ID to the parameters array
    $params[] = $userId;

    // Add the type for the user ID parameter
    $types .= "i";

    // Prepare the statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param($types, ...$params);

    // Execute statement
    if ($stmt->execute()) {
         echo '<body>
                <script>
                        Swal.fire({
                            title: "Do you want to save changes?",
                            showDenyButton: true,
                            showCancelButton: true,
                            confirmButtonText: "Save",
                            denyButtonText: `Don\'t save`
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Swal.fire("Service updated successfully!", "", "success").then(() => {
                                    window.location.href = "um.php";
                                });
                            } else if (result.isDenied) {
                                Swal.fire("Changes are not saved", "", "info");
                            }
                        });
                      </script>
                      </body>';
    } else {
        echo "Error updating record: " . $stmt->error;
    }

    // Close statement
    $stmt->close();
}

// Close connection
$conn->close();
?>
