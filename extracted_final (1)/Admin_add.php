<!DOCTYPE html>
<html>
<head>
    <title>Add Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <!-- Font Awesome for icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="admin.css">
    <style>
        /* Optional: Custom styles */
        .card {
            margin-top: 50px;
        }
        .input-group-append {
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
                        <li class="nav-item">
                            <a class="nav-link" href="home.php">Home</a>
                        </li>
                         <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Appointments</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="pending_appointments.php">Pending Appointments</a></li>
                                    <li><a class="dropdown-item " href="rescheduled.php">Rescheduled Appointments</a></li>
                                    <li><a class="dropdown-item" href="scheduled_appointments.php">Scheduled Appointments</a></li>
                                    <li><a class="dropdown-item" href="appointment.php">Completed Appointments</a></li>
                                    <li><a class="dropdown-item" href="cancelled.php">Cancelled Appointments</a></li>
                                </ul>
                            </li>
                        <?php if ($_SESSION['user_type'] == 'admin'||$_SESSION['user_type'] == 'sec' ) { ?>
                            <li class="nav-item active">
                                <a class="nav-link" href="um.php">User Management</a>
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
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 col-sm-8">
                <div class="card">
                    <div class="card-header text-center">
                        Add Admin
                    </div>
                    <div class="card-body">
                        <form action="add_admin.php" method="POST" onsubmit="return validateForm()">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="fname">First Name</label>
                                <input type="text" class="form-control" id="fname" name="fname" required>
                            </div>
                            <div class="form-group">
                                <label for="lname">Last Name</label>
                                <input type="text" class="form-control" id="lname" name="lname" required>
                            </div>
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword('password')">
                                            <i class="fa fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword('confirmPassword')">
                                            <i class="fa fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success btn-block">Add Admin</button>
                            <a href="admin_dashboard.php" class="btn btn-secondary btn-block">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            var passwordInput = document.getElementById(inputId);
            var eyeIcon = document.querySelector("#" + inputId + " + .input-group-append i");
            
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIcon.classList.remove("fa-eye");
                eyeIcon.classList.add("fa-eye-slash");
            } else {
                passwordInput.type = "password";
                eyeIcon.classList.remove("fa-eye-slash");
                eyeIcon.classList.add("fa-eye");
            }
        }
        
        function validateForm() {
            var password = document.getElementById("password").value;
            var confirmPassword = document.getElementById("confirmPassword").value;
            
            if (password !== confirmPassword) {
                alert("Passwords do not match. Please make sure your passwords match.");
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>