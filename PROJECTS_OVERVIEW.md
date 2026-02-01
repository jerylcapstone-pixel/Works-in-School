# Works-in-School Projects Overview

This repository contains multiple academic and professional projects completed as part of school coursework. Below is a detailed description of each project with information about its purpose, technology stack, and key features.

---

## 1. **FinalReq** (3.2 MB)
**Archive:** `FinalReq.zip`

### Description
A Java-based desktop application built with NetBeans IDE for attendance management and time tracking.

### Technology Stack
- **Language:** Java (J2SE)
- **IDE:** NetBeans
- **Database:** db4o (object database)
- **Key Libraries:** AbsoluteLayout for GUI

### Key Features
- User account management (Accounts system)
- Time in/out tracking functionality
- Attendance database management using db4o
- GUI built with Swing and AbsoluteLayout
- Compiled into executable JAR (`Finalreq.jar`)

### Project Structure
```
FinalReq/
├── src/               # Java source files (TimeTime package)
├── build/            # Compiled classes
├── dist/             # Distribution with JAR and libraries
├── nbproject/        # NetBeans configuration
└── Attendance.db4o   # Database file
```

---

## 2. **Omandam** (31 KB)
**Archive:** `Omandam.zip`

### Description
A Java OOP application demonstrating inheritance and polymorphism with different employee types in an organizational context.

### Technology Stack
- **Language:** Java (J2SE)
- **IDE:** NetBeans
- **Build Tool:** Ant (build.xml included)

### Key Features
- Object-oriented design with inheritance hierarchy
- Multiple employee roles (Lawyer, Secretary, Legal_Secretary, Marketer, Employee)
- Polymorphic behavior implementation
- Class section management and organization

### Classes
- `Employee` (base class)
- `Lawyer`, `Secretary`, `Legal_Secretary`, `Marketer` (derived classes)
- `Omandam` (main application class)

---

## 3. **Omandam - Computer Repair Appointment System** (235 KB)
**Archive:** `Omandam-20260201T162643Z-3-001.zip`

### Description
A computer repair and appointment booking system with both application and database documentation.

### Contents
- **Source Code:** Nested `source_code_omandam.zip` for application source
- **Database:** `omandam.sql` - MySQL database schema and initial data
- **Documentation:** `Computer Repair Appointment System.docx` - System requirements and design
- **Accounts:** `accounts.txt` - Test user credentials

### Technology Stack
- **Language:** Java or other (see extracted source)
- **Database:** MySQL
- **System:** Appointment and service management

### Key Features
- Appointment scheduling system
- Computer repair service tracking
- User account authentication
- Database-driven backend

---

## 4. **AcadERP** (4.3 MB)
**Archive:** `acaderp.zip`

### Description
An Academic Enterprise Resource Planning (ERP) system for educational institutions with comprehensive database and feature management.

### Technology Stack
- **Language:** PHP (server-side)
- **Database:** MySQL
- **Frontend:** HTML/CSS/JavaScript
- **Framework:** Custom PHP framework

### Key Features
- **Multi-tenant Support:** Dark and light theme toggle
- **Academic Structure:** 
  - Department management with auto-generated codes
  - Program/Major tracking
  - Course management with major associations
  - Class section tracking (department, program, major)
- **Financial Management:** Tuition rate management and calculations
- **Enrollment System:** Enhanced enrollment fields and tracking
- **Admin Features:** Password recovery, feature toggles
- **Database Migrations:** Automated migration system for schema updates

### Database Components
```
database/
├── schema.sql                           # Main schema
├── migration_*.sql                      # Migration scripts
├── migration_*.php                      # PHP migration runners
├── run_migration.php                    # Migration execution tool
└── run_minor_course_rates_migration.php # Specific migrations
```

### Admin Tools
- `fix_admin_password.php` - Emergency admin access
- `install.php` - Initial setup and installation

---

## 5. **StyleSync - Hair Salon Booking System** (26 MB)
**Archive:** `final (1).zip`

### Description
A comprehensive web-based salon appointment and service management system for a hair styling business.

### Technology Stack
- **Language:** PHP (server-side), HTML/CSS/JavaScript (client-side)
- **Database:** MySQL
- **Frontend:** Bootstrap 5.3.3, Font Awesome icons
- **Styling:** Custom CSS with responsive design

### Key Features
- **User Management:**
  - User registration and login (`index.php`, `logout.php`)
  - User profile updates (`update_user.php`, `edit_user_process.php`)
  - Admin account management (`add_admin.php`)
- **Service Management:**
  - Add and manage services (`addservices.php`, `addser.php`)
  - Service booking system (`book.php`)
- **Appointment Management:**
  - Schedule management (`change_schedule.php`)
  - Appointment completion workflow (`complete_appointment.php`)
  - Appointment archives (`archives.php`)
- **Admin Dashboard:** User information management (`user_info.php`)
- **Social Media Integration:** Instagram and Gmail links
- **Theme Support:** Dark and light themes (`login.css`, `admin.css`, `about.css`)
- **Branding:** Logo and promotional images included

### File Organization
```
StyleSync/
├── index.php                    # Homepage/login
├── book.php                     # Booking system
├── *.php                        # Various management pages
├── *.css                        # Stylesheets (login, admin, about)
├── *.png, *.jpg                 # Images and assets
└── Database integration         # MySQL backend
```

---

## 6. **Semi-Finals Project** (11 MB)
**Archive:** `semi-finals.zip`

### Description
A Flask-based web application for image/file management with MySQL database integration and Bootstrap UI.

### Technology Stack
- **Language:** Python (Backend)
- **Framework:** Flask
- **Database:** MySQL (via Flask-MySQLDB)
- **Frontend:** Bootstrap 5, HTML/CSS/JavaScript
- **File Upload:** Werkzeug for secure file handling

### Key Features
- **File Upload System:** Secure file handling with `secure_filename`
- **Database Integration:** MySQL backend with Flask-MySQLDB
- **Responsive UI:** Bootstrap grid and styling
- **Logging:** Python logging for debugging
- **Static Assets:** CSS, JavaScript, and image resources
- **Templates:** HTML templates for dynamic rendering

### Project Structure
```
semi-finals/
├── app.py                      # Main Flask application
├── templates/
│   └── index.html              # Main HTML template
├── static/
│   ├── css/                    # Bootstrap and custom CSS
│   ├── js/                     # Bootstrap JavaScript
│   └── img/                    # Images (logo, manage, add icons)
└── uploads/                    # User uploaded files
```

### Configuration
- Database: MySQL (localhost)
- Upload folder: `uploads/`
- Flask templating: Jinja2

---

## Summary Table

| Project | Size | Language | Purpose | Status |
|---------|------|----------|---------|--------|
| FinalReq | 3.2 MB | Java | Attendance & Time Tracking | Desktop App |
| Omandam | 31 KB | Java | OOP Demo (Employee Classes) | Console App |
| Omandam Repair System | 235 KB | Java | Computer Repair Appointments | Service System |
| AcadERP | 4.3 MB | PHP | Academic Management System | Web App |
| StyleSync | 26 MB | PHP | Hair Salon Booking System | Web App |
| Semi-Finals | 11 MB | Python/Flask | Image/File Management | Web App |

---

## Notes
- All projects have been extracted to `extracted_[projectname]/` directories for easy access
- Most web applications require a database setup and server configuration
- Java projects can be opened and compiled in NetBeans IDE
- PHP projects require a web server (Apache/Nginx) and MySQL
- Flask application requires Python 3 and dependencies from requirements
