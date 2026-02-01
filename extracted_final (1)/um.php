<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Redirect to login page if the user is not logged in
    header("Location: login.php");
    exit();
}

// Define allowed user types for the dashboard
$allowed_user_types = ['client', 'admin', 'stylist', 'sec']; // Modify as needed

// Check if the user type is allowed to access the dashboard
if (!in_array($_SESSION['user_type'], $allowed_user_types)) {
    // Redirect to an error page or display an access denied message
    echo "Access denied. You do not have permission to access this page.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js"></script>
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
                                <a class="nav-link active" href="um.php">User Management</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="Services.php">Services</a>
                            </li>
                        <?php } elseif ($_SESSION['user_type'] == 'stylist' || $_SESSION['user_type'] == 'sec') { ?>
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

    <div class="container mt-5">
        <p>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>. Your user type is <?php echo htmlspecialchars($_SESSION['user_type']); ?>.</p>
        <div class="search-container">
            <form action="" method="POST">
                <input type="text" placeholder="Search..." name="search" id="searchInput">
                <button type="submit"><i class="fa fa-search"></i></button>
            </form>
        </div>

        <div class="text-end mb-3">
            <?php if ($_SESSION['user_type'] == 'admin') { ?>
                <a href="Admin_add.php" class="btn btn-primary">Add Admin</a>
            <?php } ?>
        </div>

        <div class="table-responsive">
            <table class="table" id="userTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Username</th>
                        <th>User Type</th
                       <?php if ($_SESSION['user_type'] == 'admin') { ?>
                            <th>Action</th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
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

                    // Initialize search condition
                    $searchCondition = "";

                    // Check if search form is submitted
                    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
                        // Sanitize user input to prevent SQL injection
                        $searchInput = $conn->real_escape_string($_POST['search']);
                        // Construct search condition
                        $searchCondition = " WHERE user_id LIKE '%$searchInput%' OR username LIKE '%$searchInput%' OR email LIKE '%$searchInput%' OR Fname LIKE '%$searchInput%' OR Lname LIKE '%$searchInput%'";
                    }

                    // SQL query to fetch user data with search condition
                    $sql = "SELECT * FROM users" . $searchCondition;
                    $result = $conn->query($sql);

                    // Check if any users found
                    if ($result->num_rows > 0) {
                        // Output data of each row
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . $row['user_id'] . '</td>';
                            echo '<td>' . $row['email'] . '</td>';
                            echo '<td>' . $row['Fname'] . '</td>';
                            echo '<td>' . $row['Lname'] . '</td>';
                            echo '<td>' . $row['username'] . '</td>';
                            echo '<td>' . $row['user_type'] . '</td>';
                            echo '<td>';
                            if ($_SESSION['user_type'] == 'admin') {
                                echo '<a href="update_user.php?userId=' . $row['user_id'] . '" class="btn btn-primary btn-sm mr-1">Edit</a>';
                                echo '<form action="delete_user.php" method="post" style="display: inline;" onsubmit="return confirm(\'Are you sure you want to delete this user?\');">';
                                echo '<input type="hidden" name="userId" value="' . $row['user_id'] . '">';
                                echo '<button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button>';
                                echo '</form>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7">No users found</td></tr>';
                    }

                    // Close connection
                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
