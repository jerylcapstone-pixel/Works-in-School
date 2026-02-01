<?php
session_start();

$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";

$message = ""; // Variable to hold error/success messages

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Create connection
    $conn = new mysqli($servername, $db_username, $db_password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Sanitize inputs to prevent SQL injection
    $username = $conn->real_escape_string($username);

    // Check if the username exists in the USERS table
    $check_username_query = "SELECT * FROM users WHERE BINARY username='$username'";
    $result_check_username = $conn->query($check_username_query);

    if ($result_check_username->num_rows > 0) {
        $row = $result_check_username->fetch_assoc();
        $stored_password = $row['password']; // Retrieve stored hashed password from the database

        // Check if the provided password matches the stored hashed password
        if ($password === $stored_password) {
            // Store user information in session variables
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['user_type'] = $row['user_type'];

            // Redirect to dashboard
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Login</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        title: "Login Successful!",
                        text: "Redirecting to the dashboard...",
                        icon: "success",
                        showConfirmButton: false
                    });
                    setTimeout(function(){
                        window.location.href = "home.php";
                    }, 3000); // Redirect after 3 seconds
                </script>
            </body>
            </html>';
        exit();
        } else {
            // Incorrect password
            $message = "Incorrect password.";
        }
    } else {
        // Username not registered
        $message = "Username not registered.";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="login.css"> <!-- Added login.css for custom styles -->
    <style>
        /* Optional: Custom styles */
        .card {
            margin-top: 50px;
        }
        .input-group-append {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 col-sm-8">
                <div class="card">
                    <div class="card-header text-center">
                         <img src="logo.png" alt="Logo" style="max-height: 80px;">
                        <br><h2>Login</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)) : ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="fa fa-eye" id="togglePassword"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </form>
                        <div class="mt-3 text-center">
                            <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById("togglePassword").addEventListener("click", function() {
            var passwordInput = document.getElementById("password");
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                document.getElementById("togglePassword").classList.remove("fa-eye");
                document.getElementById("togglePassword").classList.add("fa-eye-slash");
            } else {
                passwordInput.type = "password";
                document.getElementById("togglePassword").classList.remove("fa-eye-slash");
                document.getElementById("togglePassword").classList.add("fa-eye");
            }
        });
    </script>
</body>
</html>
