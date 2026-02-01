// Function to open the pop-up overlay and populate form fields
    function openPopup(userId, username, password, fname, lname, email, userType) {
        // Fetch user data based on userId and populate the edit form fields
        document.getElementById("overlay").style.display = "flex";
        document.getElementById("userId").value = userId; // Set the value of hidden field
        document.getElementById("username").value = username;
        document.getElementById("password").value = password;
        document.getElementById("Fname").value = fname;
        document.getElementById("Lname").value = lname;
        document.getElementById("email").value = email;
        document.getElementById("user_type").value = userType;
    }

    // Function to close the pop-up overlay
     function closeUpdate() {
        var overlay = document.getElementById('overlay');
        overlay.style.display = 'none';
    }

    // Function to toggle password visibility
    function togglePasswordVisibility() {
        var passwordInput = document.getElementById('password');
        var eyeIcon = document.querySelector(".toggle-password i");

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
    function autoSubmitSearch() {
        document.getElementById("searchForm").submit();
    }
    
    