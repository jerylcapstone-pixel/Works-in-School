<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $email = $_POST['email'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $username = $_POST['username'];
    $password = $_POST['password']; // Don't hash the password
    
     date_default_timezone_set('Asia/Manila'); // Change to your desired timezone
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
   $conn->query("SET time_zone = '+08:00'"); // Change to your desired timezone offset


    // Sanitize inputs to prevent SQL injection
    $email = $conn->real_escape_string($email);
    $firstname = $conn->real_escape_string($firstname);
    $lastname = $conn->real_escape_string($lastname);
    $username = $conn->real_escape_string($username);
    // Password should not be sanitized

    // Check if email or username already exists
    $sql_check_existing = "SELECT COUNT(*) as total FROM users WHERE BINARY email = '$email' OR BINARY username = '$username'";
    $result_check_existing = $conn->query($sql_check_existing);
    if ($result_check_existing) {
        $row_check_existing = $result_check_existing->fetch_assoc();
        $total_existing = $row_check_existing['total'];
        if ($total_existing > 0) {
            // Email or username already exists, show SweetAlert and redirect to signup page with error message
            echo '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Sign Up</title>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                </head>
                <body>
                    <script>
                        Swal.fire({
                            title: "Error!",
                            text: "Email or username already exists.",
                            icon: "error",
                            confirmButtonText: "OK"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = "signup.php";
                            }
                        });
                    </script>
                </body>
                </html>';
            $conn->close();
            exit();
        }
    } else {
        die("Error executing query: " . $conn->error);
    }

    // Set user type
    $user_type = 'client'; // New users are set as clients by default

    // Check if this is the first registered user to set as admin
    $sql_check_first_user = "SELECT COUNT(*) as total FROM users";
    $result_check_first_user = $conn->query($sql_check_first_user);
    if ($result_check_first_user) {
        $row_check_first_user = $result_check_first_user->fetch_assoc();
        $total_users = $row_check_first_user['total'];
        if ($total_users == 0) {
            $user_type = 'admin'; // First registered user is set as admin
        }
    } else {
        die("Error executing query: " . $conn->error);
    }

    // Insert user data into the database
    $sql_insert_user = "INSERT INTO users (email, Fname, Lname, username, password, user_type) VALUES ('$email', '$firstname', '$lastname', '$username', '$password', '$user_type')";
    if ($conn->query($sql_insert_user) === TRUE) {
        // Signup successful, show SweetAlert and redirect to login page with success message
        echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Sign Up</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        title: "Sign Up Successful!",
                        text: "You can now log in.",
                        icon: "success",
                        showConfirmButton: false
                    });
                    setTimeout(function(){
                        window.location.href = "login.php";
                    }, 2000); // Redirect after 2 seconds
                </script>
            </body>
            </html>';
    } else {
        // Signup failed, show SweetAlert and redirect back to signup page with error message
        echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Sign Up</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        title: "Error!",
                        text: "Sign up failed. Please try again later.",
                        icon: "error",
                        confirmButtonText: "OK"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "signup.php";
                        }
                    });
                </script>
            </body>
            </html>';
    }

    // Close database connection
    $conn->close();
}
?>
