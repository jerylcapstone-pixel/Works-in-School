# Academic Management System

A comprehensive **Web-Based Academic Management System** for universities built with PHP, MySQL, HTML, CSS, and JavaScript.

## Features

The system includes **10 Core Modules**:

1. **Student Information System (SIS)** - Central database for all student records (personal info, grades, progress)
2. **Curriculum Management** - Handles course catalog, degree requirements, and prerequisites
3. **Faculty & Staff Management (HR)** - Manages teacher and staff data, qualifications, and teaching assignments
4. **Online Admissions & Enrollment** - Digital gateway for new students (application, evaluation, approval, enrollment)
5. **Tuition & Academic Billing** - Calculates tuition based on enrolled courses and handles financial aid
6. **Student Advisory Management** - Allows faculty advisors to track students' academic progress, add notes, and monitor counseling logs
7. **Web-Based Gradebook & Transcripts** - Teachers enter grades; system generates official student transcripts
8. **Attendance Tracking System** - Records and monitors student attendance for academic policies
9. **Course Registration (Admin-Led)** - Lets admins or faculty enroll students, check prerequisites, and manage waitlists
10. **Classroom Resource Booking** - Allows booking of rooms, labs, or academic equipment

## System Architecture

- **Fully web-based, secure, and responsive**
- **Modular architecture** - each module can function independently but integrates with others via shared database tables
- **Centralized MySQL database** (SIS is the core)
- **Admin dashboard** for managing all modules
- **Role-based authentication** (Admin, Faculty, Student)
- **CRUD operations** (Create, Read, Update, Delete) for each module

## Project Structure

```
SemiRols/
├── assets/
│   ├── css/
│   │   └── style.css          # Main stylesheet
│   └── js/
│       └── main.js            # Main JavaScript
├── auth/
│   ├── login.php              # Login page
│   └── logout.php             # Logout handler
├── config/
│   ├── config.php             # Main configuration
│   └── database.php           # Database configuration
├── dashboard/
│   └── index.php              # Admin dashboard
├── database/
│   └── schema.sql             # Database schema
├── includes/
│   ├── header.php             # Common header
│   └── footer.php             # Common footer
├── modules/
│   ├── sis/                   # Student Information System
│   │   ├── index.php          # List students
│   │   ├── add.php            # Add student
│   │   ├── edit.php           # Edit student
│   │   ├── view.php           # View student details
│   │   └── delete.php         # Delete student
│   ├── faculty/               # Faculty Management (to be implemented)
│   ├── curriculum/            # Curriculum Management (to be implemented)
│   ├── admissions/            # Admissions & Enrollment (to be implemented)
│   ├── registration/          # Course Registration (to be implemented)
│   ├── grades/                # Gradebook & Transcripts (to be implemented)
│   ├── attendance/            # Attendance Tracking (to be implemented)
│   ├── advisory/              # Student Advisory (to be implemented)
│   ├── billing/               # Tuition & Billing (to be implemented)
│   └── booking/               # Resource Booking (to be implemented)
└── index.php                  # Main entry point
```

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.3+)
- Apache/Nginx web server
- XAMPP/WAMP/LAMP (recommended for local development)

### Setup Steps

1. **Clone or extract the project** to your web server directory:
   ```
   C:\xamp\htdocs\SemiRols\
   ```

2. **Create the MySQL Database**:
   - Open phpMyAdmin or MySQL command line
   - Create a new database named `academic_management_system`
   - Or edit `config/database.php` to use your preferred database name

3. **Import the Database Schema**:
   - Open phpMyAdmin
   - Select the `academic_management_system` database
   - Click "Import" tab
   - Choose `database/schema.sql` file
   - Click "Go" to import

   Or via command line:
   ```bash
   mysql -u root -p academic_management_system < database/schema.sql
   ```

4. **Configure Database Connection**:
   - Open `config/database.php`
   - Update database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'academic_management_system');
     ```

5. **Configure Base URL**:
   - Open `config/config.php`
   - Update `BASE_URL` if your project is in a subdirectory:
     ```php
     define('BASE_URL', 'http://localhost/SemiRols/');
     ```

6. **Start Web Server**:
   - Start Apache and MySQL from XAMPP/WAMP control panel
   - Navigate to: `http://localhost/SemiRols/`

## Default Login Credentials

**Username:** `admin`  
**Password:** `admin123`

⚠️ **IMPORTANT:** Change the default password after first login!

To change the admin password, you can update it in the database:
```sql
UPDATE users SET password_hash = '$2y$10$YOUR_HASH_HERE' WHERE username = 'admin';
```

Generate a new hash using PHP:
```php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
```

## Usage

### Admin Dashboard

1. Log in with admin credentials
2. Access the dashboard to see system statistics
3. Navigate to different modules via the navigation menu

### Student Information System (SIS)

1. Click "Students" in the navigation menu
2. **Add Student**: Click "Add New Student" button
3. **View Student**: Click the eye icon on any student row
4. **Edit Student**: Click the edit icon
5. **Delete Student**: Click the delete icon (only if student has no enrollments)
6. **Search/Filter**: Use the search box and status filter at the top

### Adding Other Modules

The system is designed to be modular. To add functionality for other modules:

1. Create module folder under `modules/` (e.g., `modules/faculty/`)
2. Follow the same structure as the SIS module:
   - `index.php` - List records
   - `add.php` - Add new record
   - `edit.php` - Edit existing record
   - `view.php` - View record details
   - `delete.php` - Delete record
3. Integrate with existing database tables
4. Add navigation links in `includes/header.php`

## Security Features

- ✅ **Prepared Statements** - All database queries use PDO prepared statements to prevent SQL injection
- ✅ **Input Sanitization** - All user input is sanitized before processing
- ✅ **Output Encoding** - All output is encoded to prevent XSS attacks
- ✅ **Session Management** - Secure session handling with role-based access control
- ✅ **Password Hashing** - Passwords are hashed using PHP's `password_hash()` function

## Database Schema

The database includes tables for:

- Users and authentication
- Students (SIS core)
- Faculty and departments
- Programs and courses
- Prerequisites and program courses
- Admission applications
- Class sections and enrollments
- Grades and transcripts
- Attendance records
- Advisory assignments and notes
- Tuition rates, financial aid, invoices, and payments
- Rooms, equipment, and bookings

See `database/schema.sql` for complete schema with relationships and indexes.

## Technology Stack

- **Backend:** PHP 7.4+ (PDO for database)
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Icons:** Font Awesome 6.4
- **Design:** Modern, responsive CSS with CSS Grid and Flexbox

## Development Notes

### Naming Conventions

- **Files:** lowercase with underscores (e.g., `add_student.php`)
- **Functions:** camelCase (e.g., `getDBConnection()`)
- **Variables:** snake_case (e.g., `$student_id`)
- **Constants:** UPPER_SNAKE_CASE (e.g., `BASE_URL`)

### Database Conventions

- **Tables:** lowercase, plural (e.g., `students`, `courses`)
- **Primary Keys:** `{table_name}_id` (e.g., `student_id`)
- **Foreign Keys:** `{referenced_table}_id` (e.g., `program_id`)
- **Timestamps:** `created_at`, `updated_at`

## Future Enhancements

- Complete implementation of all 10 modules
- Email notifications
- PDF report generation
- Advanced search and filtering
- Data export functionality (CSV, Excel)
- API endpoints for mobile apps
- Real-time notifications
- Audit logging

## License

This project is open source and available for educational and commercial use.

## Support

For issues or questions, please refer to the code comments or create an issue in the repository.

## Credits

Developed as a complete Academic Management System for university administration.

---

**Version:** 1.0.0  
**Last Updated:** 2024
