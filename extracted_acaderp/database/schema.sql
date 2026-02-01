-- =====================================================
-- Academic Management System - Database Schema
-- Version 1.0.0
-- =====================================================

-- Create database (uncomment if creating new database)
CREATE DATABASE IF NOT EXISTS academic_management_system;
USE academic_management_system;

-- =====================================================
-- 1. USER AUTHENTICATION & ROLES
-- =====================================================

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_role ENUM('admin', 'faculty', 'student') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (user_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. STUDENT INFORMATION SYSTEM (SIS) - CORE MODULE
-- =====================================================

CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    student_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'USA',
    enrollment_date DATE NOT NULL,
    graduation_date DATE,
    status ENUM('Active', 'Graduated', 'Transferred', 'Suspended', 'Withdrawn') DEFAULT 'Active',
    program_id INT,
    advisor_id INT,
    gpa DECIMAL(3,2) DEFAULT 0.00,
    total_credits INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_student_number (student_number),
    INDEX idx_status (status),
    INDEX idx_program (program_id),
    INDEX idx_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. FACULTY & STAFF MANAGEMENT (HR)
-- =====================================================

CREATE TABLE IF NOT EXISTS faculty (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    employee_id INT UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other'),
    phone VARCHAR(20),
    email VARCHAR(100),
    barangay VARCHAR(100),
    purok_street VARCHAR(100),
    zipcode VARCHAR(20),
    province VARCHAR(100),
    municipality VARCHAR(100),
    hire_date DATE NOT NULL,
    department_id INT,
    position VARCHAR(100),
    qualification TEXT,
    specialization TEXT,
    status ENUM('Active', 'On Leave', 'Retired', 'Terminated') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_employee_id (employee_id),
    INDEX idx_department (department_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_code VARCHAR(10) UNIQUE NOT NULL,
    department_name VARCHAR(100) NOT NULL,
    head_faculty_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (head_faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL,
    INDEX idx_code (department_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. CURRICULUM MANAGEMENT
-- =====================================================

CREATE TABLE IF NOT EXISTS programs (
    program_id INT AUTO_INCREMENT PRIMARY KEY,
    program_code VARCHAR(20) UNIQUE NOT NULL,
    program_name VARCHAR(200) NOT NULL,
    department_id INT,
    degree_type ENUM('Associate', 'Bachelor', 'Master', 'Doctorate', 'Certificate') NOT NULL,
    total_credits INT NOT NULL,
    duration_years INT,
    description TEXT,
    status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    INDEX idx_program_code (program_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(200) NOT NULL,
    department_id INT,
    credits INT NOT NULL,
    description TEXT,
    course_type ENUM('Lecture', 'Lab', 'Seminar', 'Workshop', 'Online', 'Hybrid') DEFAULT 'Lecture',
    status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    INDEX idx_course_code (course_code),
    INDEX idx_department (department_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prerequisites (
    prerequisite_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    required_course_id INT NOT NULL,
    minimum_grade VARCHAR(2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (required_course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY unique_prerequisite (course_id, required_course_id),
    INDEX idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS program_courses (
    program_course_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    course_id INT NOT NULL,
    is_core BOOLEAN DEFAULT FALSE,
    is_elective BOOLEAN DEFAULT FALSE,
    semester_year INT,
    semester_term ENUM('Fall', 'Spring', 'Summer') DEFAULT 'Fall',
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY unique_program_course (program_id, course_id),
    INDEX idx_program (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. ONLINE ADMISSIONS & ENROLLMENT
-- =====================================================

CREATE TABLE IF NOT EXISTS admission_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(20) UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    address TEXT,
    program_id INT NOT NULL,
    application_date DATE NOT NULL,
    status ENUM('Pending', 'Under Review', 'Approved', 'Rejected', 'Waitlisted') DEFAULT 'Pending',
    gpa_previous DECIMAL(3,2),
    previous_school VARCHAR(200),
    notes TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_program (program_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. CLASSROOM RESOURCE BOOKING (Created before course registration)
-- =====================================================

CREATE TABLE IF NOT EXISTS rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    building_name VARCHAR(100),
    room_type ENUM('Classroom', 'Laboratory', 'Lecture Hall', 'Computer Lab', 'Library', 'Meeting Room', 'Other') NOT NULL,
    capacity INT,
    equipment TEXT,
    amenities TEXT,
    status ENUM('Available', 'Maintenance', 'Reserved', 'Closed') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_room_number (room_number),
    INDEX idx_type (room_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. COURSE REGISTRATION (ADMIN-LED)
-- =====================================================

CREATE TABLE IF NOT EXISTS class_sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    department_id INT NULL,
    program_id INT NULL,
    major_id INT NULL,
    section_number VARCHAR(10) NOT NULL,
    faculty_id INT,
    semester VARCHAR(20) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    schedule_day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_id INT,
    max_enrollment INT NOT NULL,
    current_enrollment INT DEFAULT 0,
    waitlist_max INT DEFAULT 0,
    status ENUM('Open', 'Full', 'Closed', 'Cancelled') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL,
    UNIQUE KEY unique_section (course_id, section_number, semester, academic_year),
    INDEX idx_course (course_id),
    INDEX idx_department (department_id),
    INDEX idx_program (program_id),
    INDEX idx_major (major_id),
    INDEX idx_faculty (faculty_id),
    INDEX idx_semester (semester, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    section_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    enrollment_type ENUM('Regular', 'Waitlist', 'Audit') DEFAULT 'Regular',
    status ENUM('Enrolled', 'Completed', 'Dropped', 'Withdrawn', 'Failed') DEFAULT 'Enrolled',
    final_grade VARCHAR(2),
    points DECIMAL(5,2),
    registered_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES class_sections(section_id) ON DELETE RESTRICT,
    FOREIGN KEY (registered_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_enrollment (student_id, section_id),
    INDEX idx_student (student_id),
    INDEX idx_section (section_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. WEB-BASED GRADEBOOK & TRANSCRIPTS
-- =====================================================

CREATE TABLE IF NOT EXISTS grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    assignment_type ENUM('Quiz', 'Assignment', 'Midterm', 'Final', 'Project', 'Participation', 'Other') NOT NULL,
    assignment_name VARCHAR(200) NOT NULL,
    points_earned DECIMAL(6,2) NOT NULL,
    points_possible DECIMAL(6,2) NOT NULL,
    percentage DECIMAL(5,2),
    letter_grade VARCHAR(2),
    due_date DATE,
    submitted_date DATE,
    graded_by INT,
    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_type (assignment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transcripts (
    transcript_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    generated_date DATE NOT NULL,
    generated_by INT,
    pdf_path VARCHAR(255),
    is_official BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. ATTENDANCE TRACKING SYSTEM
-- =====================================================

CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Excused', 'Tardy') DEFAULT 'Absent',
    notes TEXT,
    marked_by INT,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (enrollment_id, attendance_date),
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_date (attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. STUDENT ADVISORY MANAGEMENT
-- =====================================================

CREATE TABLE IF NOT EXISTS advisory_assignments (
    advisory_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    advisor_id INT NOT NULL,
    assigned_date DATE NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES faculty(faculty_id) ON DELETE RESTRICT,
    UNIQUE KEY unique_advisory (student_id, advisor_id, status),
    INDEX idx_student (student_id),
    INDEX idx_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advisory_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    advisory_id INT NOT NULL,
    note_type ENUM('Meeting', 'Progress', 'Warning', 'Recommendation', 'Other') DEFAULT 'Meeting',
    note_text TEXT NOT NULL,
    meeting_date DATE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (advisory_id) REFERENCES advisory_assignments(advisory_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_advisory (advisory_id),
    INDEX idx_date (meeting_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. TUITION & ACADEMIC BILLING
-- =====================================================

CREATE TABLE IF NOT EXISTS tuition_rates (
    rate_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT,
    rate_type ENUM('Per Credit', 'Flat Rate', 'Per Semester') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    effective_date DATE NOT NULL,
    expiry_date DATE,
    description TEXT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL,
    INDEX idx_program (program_id),
    INDEX idx_effective (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_aid (
    aid_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    aid_type ENUM('Scholarship', 'Grant', 'Loan', 'Work Study', 'Other') NOT NULL,
    aid_name VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    semester VARCHAR(20),
    academic_year VARCHAR(10),
    status ENUM('Pending', 'Approved', 'Disbursed', 'Cancelled') DEFAULT 'Pending',
    disbursement_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    semester VARCHAR(20) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    financial_aid_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Partial', 'Paid', 'Overdue', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_invoice_number (invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item_type ENUM('Tuition', 'Fees', 'Books', 'Lab Fee', 'Other') NOT NULL,
    description VARCHAR(200) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    payment_method ENUM('Cash', 'Check', 'Credit Card', 'Bank Transfer', 'Online', 'Other') NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    processed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE RESTRICT,
    FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_invoice (invoice_id),
    INDEX idx_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. CLASSROOM RESOURCE BOOKING (continued)
-- =====================================================

CREATE TABLE IF NOT EXISTS equipment (
    equipment_id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_name VARCHAR(200) NOT NULL,
    equipment_type ENUM('Computer', 'Projector', 'Lab Equipment', 'Furniture', 'Other') NOT NULL,
    serial_number VARCHAR(100),
    location VARCHAR(100),
    status ENUM('Available', 'In Use', 'Maintenance', 'Reserved', 'Out of Service') DEFAULT 'Available',
    purchase_date DATE,
    warranty_expiry DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_type (equipment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type ENUM('Room', 'Equipment') NOT NULL,
    resource_id INT NOT NULL,
    booked_by INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose TEXT,
    status ENUM('Pending', 'Confirmed', 'Cancelled', 'Completed') DEFAULT 'Pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booked_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_booked_by (booked_by),
    INDEX idx_date (booking_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADD FOREIGN KEY CONSTRAINTS (Add room_id FK to class_sections)
-- =====================================================

-- Note: rooms table must be created before class_sections
-- This FK was referenced in class_sections but needs to be added after rooms exists

-- =====================================================
-- INSERT DEFAULT DATA (Sample Admin User)
-- =====================================================

-- Default admin user (password: admin123 - CHANGE THIS!)
INSERT INTO users (username, email, password_hash, user_role, status) 
VALUES ('admin', 'admin@university.edu', '$2y$10$7ev6tJ6Hev5yIqMLsXAQR.enVPpNTK4FI70CqdT2N7h8g1xLoGk8O', 'admin', 'active')
ON DUPLICATE KEY UPDATE username=username;
