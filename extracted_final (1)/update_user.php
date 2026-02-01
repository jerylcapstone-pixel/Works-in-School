<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js"></script>
     <style>
      

  .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
        }

        body {
            background-image: url("admin.png");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
    </style>
</head>
<body>
 <div class="Admin">
        <nav class="navbar navbar-expand-lg navbar-dark custom-navbar sticky-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <img src="logo.png" alt="Logo" style="max-height: 40px;">
                    StyleSync
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="collapsibleNavbar">
                    <ul class="navbar-nav ms-auto me-0">
                        <li class="nav-item ">
                            <a class="nav-link" href="home.php">Home</a>
                        </li>
                         <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="appointments.php" role="button" data-bs-toggle="dropdown">Appointments</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="pending_appointments.php">Pending Appointments</a></li>
                                    <li><a class="dropdown-item " href="rescheduled.php">Rescheduled Appointments</a></li>
                                    <li><a class="dropdown-item" href="scheduled_appointments.php">Scheduled Appointments</a></li>
                                    <li><a class="dropdown-item" href="appointments.php">Completed Appointments</a></li>
                                    <li><a class="dropdown-item" href="cancelled.php">Cancelled Appointments</a></li>
                                </ul>
                            </li>
                        <?php if ($_SESSION['user_type'] == 'admin'||$_SESSION['user_type'] == 'sec' ) { ?>
                            <li class="nav-item">
                                <a class="nav-link active" href="um.php">User Management</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } elseif ($_SESSION['user_type'] == 'stylist' || $_SESSION['user_type'] == 'sec' ) { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } ?>
                         <li class="nav-item">
                        <a class="nav-link" href="user_info.php">User Info</a>
                    </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header text-center">
                        Edit User
                    </div>
                    <div class="card-body">
                        <form action="edit_user_process.php" method="POST">
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

                                // Check if user ID is provided and valid
                                if (isset($_GET['userId']) && !empty($_GET['userId'])) {
                                    // Retrieve user ID from URL parameter
                                    $userId = $_GET['userId'];

                                    // Query to fetch user data from the users table
                                    $query = "SELECT * FROM users WHERE user_id = $userId";

                                    // Execute the query
                                    $result = mysqli_query($conn, $query);

                                    // Check if the query was successful
                                    if ($result && mysqli_num_rows($result) > 0) {
                                        // Fetch user data
                                        $user = mysqli_fetch_assoc($result);
                                        // Retrieve password separately
                                        $password = $user['password'];
                                    } else {
                                        // Handle case where user ID is not found
                                        echo "<div class='alert alert-danger' role='alert'>User not found!</div>";
                                        exit; // Stop further execution
                                    }
                                } else {
                                    // Handle case where user ID is not provided
                                    echo "<div class='alert alert-danger' role='alert'>User ID not provided!</div>";
                                    exit; // Stop further execution
                                }
                            ?>
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo $user['username']; ?>" required>
                            </div>
                            <div class="form-group password-container">
                                <label for="password">New Password:</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" value="<?php echo $password; ?>" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text toggle-password" onclick="togglePassword('password')">
                                            <i class="fa fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group password-container">
                                <label for="confirmPassword">Confirm New Password:</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" value="<?php echo $password; ?>" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text toggle-password" onclick="togglePassword('confirmPassword')">
                                            <i class="fa fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="fname">First Name:</label>
                                <input type="text" class="form-control" id="fname" name="fname" value="<?php echo $user['Fname']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="lname">Last Name:</label>
                                <input type="text" class="form-control" id="lname" name="lname" value="<?php echo $user['Lname']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="user_type">User Type:</label>
                                <select class="form-control" id="user_type" name="user_type" required>
                                    <option value="admin" <?php if($user['user_type'] == 'admin') echo 'selected'; ?>>Admin</option>
                                    <option value="user" <?php if($user['user_type'] == 'user') echo 'selected'; ?>>User</option>
                                    <option value="sec" <?php if($user['user_type'] == 'sec') echo 'selected'; ?>>Secretary</option>
                                    <option value="stylist" <?php if($user['user_type'] == 'stylist') echo 'selected'; ?>>Stylist</option>
                                </select>
                            </div>
                            <input type="hidden" name="userId" value="<?php echo $userId; ?>"> <!-- Hidden field to store user ID -->
                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-primary">Save</button>
                                <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        function togglePassword(inputId) {
            var passwordInput = document.getElementById(inputId);
            var eyeIcon = document.querySelector("#" + inputId + " + .input-group-append .fa");

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIcon.classList.remove("fa-eye-slash");
                eyeIcon.classList.add("fa-eye");
            } else {
                passwordInput.type = "password";
                eyeIcon.classList.remove("fa-eye");
                eyeIcon.classList.add("fa-eye-slash");
            }
        }
    </script>
</body>
</html>
