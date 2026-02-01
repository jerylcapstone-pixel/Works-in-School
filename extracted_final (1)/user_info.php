<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

// Establish database connection
$servername = "localhost";
$db_username = "u663034616_jeryl";
$db_password = "Markjeryl-20";
$database = "u663034616_stylesync";
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Fetch user information
$sql = "SELECT user_id, email, Fname, Lname, username, password, user_type FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_info = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_info'])) {
    $new_username = $_POST['new_username'];
    $new_email = $_POST['new_email'];
    $new_Fname = $_POST['Fname'];
    $new_Lname = $_POST['Lname'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Update users table
        $sql = "UPDATE users SET username = ?, email = ?, password = ?, Fname = ?, Lname = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $new_username, $new_email, $new_password, $new_Fname, $new_Lname, $user_id);

        // Execute the update and check for success
        if ($stmt->execute()) {
            $success_message = "User information updated successfully.";
        } else {
            $error_message = "Error updating user information.";
        }

        // Handle file upload
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['profile_pic']['tmp_name'];
            $file_name = $_FILES['profile_pic']['name'];
            $file_size = $_FILES['profile_pic']['size'];
            $file_type = $_FILES['profile_pic']['type'];
            $file_name_components = explode(".", $file_name);
            $file_extension = strtolower(end($file_name_components));

            // Allowed file types
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');

            if (in_array($file_extension, $allowed_extensions)) {
                // Delete previous profile picture named after user_id
                $profile_pic_path = "users/$user_id.*";
                foreach (glob($profile_pic_path) as $file) {
                    unlink($file);
                }

                // Rename the file with the user's ID
                $new_file_name = $user_id . '.' . $file_extension;
                $upload_file_dir = 'users/';
                $dest_path = $upload_file_dir . $new_file_name;

                // Move the file to the destination folder
                if (move_uploaded_file($file_tmp_path, $dest_path)) {
                    $success_message .= " Profile picture updated successfully.";
                } else {
                    $error_message = "Error moving the uploaded file.";
                }
            } else {
                $error_message = "Upload failed. Allowed file types: " . implode(", ", $allowed_extensions);
            }
        }

        // Refresh user info after update
        $stmt = $conn->prepare("SELECT user_id, email, Fname, Lname, username, password, user_type FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_info = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Info</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="admin.css">
    <style>
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
    <script>
        function togglePasswordVisibility() {
            var passwordField = document.getElementById("new_password");
            var confirmPasswordField = document.getElementById("confirm_password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
                confirmPasswordField.type = "text";
            } else {
                passwordField.type = "password";
                confirmPasswordField.type = "password";
            }
        }
    </script>
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
                        <li class="nav-item">
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
                        <?php if ($_SESSION['user_type'] == 'admin' || $_SESSION['user_type'] == 'sec') { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="um.php">User Management</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } elseif ($_SESSION['user_type'] == 'stylist' || $_SESSION['user_type'] == 'sec') { ?>
                            <li class="nav-item">
                                <a class="nav-link active" href="Services.php">Services</a>
                            </li>
                        <?php } ?>
                        <li class="nav-item active">
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
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4>User Information</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success_message)) { ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php } elseif (isset($error_message)) { ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php } ?>
                        
                        <?php if (!isset($_POST['edit'])) { ?>
                            <div class="form-group text-center">
                                <?php 
                                $profile_pic_path = glob("users/$user_id.*");
                                $profile_pic = !empty($profile_pic_path) ? $profile_pic_path[0] : 'users/default.jpg';
                                $profile_pic .= '?t=' . time(); // Cache-busting query parameter
                                ?>
                                <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="img-thumbnail mt-2 profile-pic">
                            </div>
                            <div class="form-group">
                                <label for="Firstname">FirstName:</label>
                                <input type="text" class="form-control" id="Fname" name="Fname" value="<?php echo htmlspecialchars($user_info['Fname']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="Lastname">Lastname:</label>
                                <input type="text" class="form-control" id="Lname" name="Lname" value="<?php echo htmlspecialchars($user_info['Lname']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_info['username']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_info['email']); ?>" readonly>
                            </div>
                            <div class="d-grid">
                                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                    <button type="submit" name="edit" class="btn btn-primary">Update</button>
                                </form>
                            </div>
                        <?php } else { ?>
                            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="current_password" value="<?php echo htmlspecialchars($user_info['password']); ?>">
                                <div class="form-group">
                                    <label for="profile_pic">Profile Picture:</label>
                                    <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                                </div>
                                <div class="form-group text-center">
                                    <?php 
                                    $profile_pic_path = glob("users/$user_id.*");
                                    $profile_pic = !empty($profile_pic_path) ? $profile_pic_path[0] : 'users/default.jpg';
                                    $profile_pic .= '?t=' . time(); // Cache-busting query parameter
                                    ?>
                                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="img-thumbnail mt-2 profile-pic">
                                </div>
                                <div class="form-group">
                                    <label for="new_firstname">New Firstname:</label>
                                    <input type="text" class="form-control" id="Fname" name="Fname" value="<?php echo htmlspecialchars($user_info['Fname']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_lastname">New Lastname:</label>
                                    <input type="text" class="form-control" id="Lname" name="Lname" value="<?php echo htmlspecialchars($user_info['Lname']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_username">New Username:</label>
                                    <input type="text" class="form-control" id="new_username" name="new_username" value="<?php echo htmlspecialchars($user_info['username']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_email">New Email:</label>
                                    <input type="email" class="form-control" id="new_email" name="new_email" value="<?php echo htmlspecialchars($user_info['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password:</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" value="<?php echo isset($_POST['new_password']) ? htmlspecialchars($_POST['new_password']) : htmlspecialchars($user_info['password']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm Password:</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" value="<?php echo isset($_POST['confirm_password']) ? htmlspecialchars($_POST['confirm_password']) : htmlspecialchars($user_info['password']); ?>" required>
                                </div>
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="show_password" onclick="togglePasswordVisibility()">
                                    <label class="form-check-label" for="show_password">Show Password</label>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="update_info" class="btn btn-primary">Update</button>
                                </div>
                                <div class="d-grid mt-2">
                                    <button type="submit" name="cancel" class="btn btn-secondary">Cancel</button>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
