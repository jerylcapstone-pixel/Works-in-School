-- =====================================================
-- Sample Data for Academic Management System
-- This file contains sample data for all tables
-- =====================================================

USE academic_management_system;

-- =====================================================
-- 1. USERS (Admin, Faculty, Students)
-- =====================================================
INSERT INTO users (username, email, password_hash, user_role, status) VALUES
-- Admin users
('admin', 'admin@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
('registrar', 'registrar@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),

-- Faculty users
('prof.smith', 'prof.smith@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),
('prof.jones', 'prof.jones@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),
('prof.williams', 'prof.williams@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),
('prof.brown', 'prof.brown@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),
('prof.davis', 'prof.davis@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),

-- Student users
('student001', 'john.doe@student.school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student002', 'jane.smith@student.school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student003', 'michael.johnson@student.school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student004', 'emily.davis@student.school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student005', 'david.wilson@student.school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student006', 'sarah.martinez@student.school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student007', 'james.anderson@student.school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student008', 'lisa.thomas@student.school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active')
ON DUPLICATE KEY UPDATE username = username;

-- =====================================================
-- 2. DEPARTMENTS
-- =====================================================
INSERT INTO departments (department_code, department_name, description, status) VALUES
('CS', 'Computer Science', 'Department of Computer Science and Information Technology', 'Active'),
('ENG', 'English', 'Department of English Language and Literature', 'Active'),
('MATH', 'Mathematics', 'Department of Mathematics and Statistics', 'Active'),
('EDU', 'Education', 'Department of Education and Teaching', 'Active'),
('BUS', 'Business', 'Department of Business Administration', 'Active'),
('SCI', 'Science', 'Department of Natural Sciences', 'Active')
ON DUPLICATE KEY UPDATE department_code = department_code;

-- =====================================================
-- 3. PROGRAMS
-- =====================================================
INSERT INTO programs (program_code, program_name, department_id, degree_type, total_credits, duration_years, description, status) VALUES
('BSCS', 'Bachelor of Science in Computer Science', 1, 'Bachelor', 120, 4, 'Comprehensive computer science program covering programming, algorithms, and software engineering', 'Active'),
('BSIT', 'Bachelor of Science in Information Technology', 1, 'Bachelor', 120, 4, 'IT program focusing on network administration and system management', 'Active'),
('BAENG', 'Bachelor of Arts in English', 2, 'Bachelor', 120, 4, 'English literature and language studies program', 'Active'),
('BSMATH', 'Bachelor of Science in Mathematics', 3, 'Bachelor', 120, 4, 'Pure and applied mathematics program', 'Active'),
('BEE', 'Bachelor of Elementary Education', 4, 'Bachelor', 120, 4, 'Elementary education teacher preparation program', 'Active'),
('BSBA', 'Bachelor of Science in Business Administration', 5, 'Bachelor', 120, 4, 'Business management and administration program', 'Active')
ON DUPLICATE KEY UPDATE program_code = program_code;

-- =====================================================
-- 4. FACULTY
-- =====================================================
INSERT INTO faculty (user_id, employee_id, first_name, last_name, middle_name, department_id, position, specialization, status) VALUES
(3, 'FAC001', 'Robert', 'Smith', 'A', 1, 'Professor', 'Computer Science', 'Active'),
(4, 'FAC002', 'Mary', 'Jones', 'B', 2, 'Associate Professor', 'English Literature', 'Active'),
(5, 'FAC003', 'John', 'Williams', 'C', 3, 'Professor', 'Mathematics', 'Active'),
(6, 'FAC004', 'Patricia', 'Brown', 'D', 4, 'Assistant Professor', 'Education', 'Active'),
(7, 'FAC005', 'Michael', 'Davis', 'E', 5, 'Associate Professor', 'Business Management', 'Active')
ON DUPLICATE KEY UPDATE employee_id = employee_id;

-- =====================================================
-- 5. STUDENTS
-- =====================================================
INSERT INTO students (user_id, student_number, first_name, last_name, middle_name, date_of_birth, gender, program_id, enrollment_date, status) VALUES
(8, '2024-001', 'John', 'Doe', 'A', '2003-05-15', 'Male', 1, '2024-08-15', 'Active'),
(9, '2024-002', 'Jane', 'Smith', 'B', '2003-07-20', 'Female', 1, '2024-08-15', 'Active'),
(10, '2024-003', 'Michael', 'Johnson', 'C', '2003-03-10', 'Male', 2, '2024-08-15', 'Active'),
(11, '2024-004', 'Emily', 'Davis', 'D', '2003-09-25', 'Female', 3, '2024-08-15', 'Active'),
(12, '2024-005', 'David', 'Wilson', 'E', '2003-11-30', 'Male', 4, '2024-08-15', 'Active'),
(13, '2024-006', 'Sarah', 'Martinez', 'F', '2003-01-18', 'Female', 5, '2024-08-15', 'Active'),
(14, '2024-007', 'James', 'Anderson', 'G', '2003-06-12', 'Male', 6, '2024-08-15', 'Active'),
(15, '2024-008', 'Lisa', 'Thomas', 'H', '2003-04-08', 'Female', 1, '2024-08-15', 'Active')
ON DUPLICATE KEY UPDATE student_number = student_number;

-- =====================================================
-- 6. COURSES
-- =====================================================
INSERT INTO courses (course_code, course_name, department_id, credits, description, course_type, status) VALUES
('CS101', 'Introduction to Programming', 1, 3, 'Basic programming concepts and logic', 'Lecture', 'Active'),
('CS102', 'Data Structures', 1, 3, 'Fundamental data structures and algorithms', 'Lecture', 'Active'),
('CS201', 'Object-Oriented Programming', 1, 3, 'OOP principles and design patterns', 'Lecture', 'Active'),
('CS202', 'Database Systems', 1, 3, 'Database design and SQL', 'Lecture', 'Active'),
('CS301', 'Web Development', 1, 3, 'HTML, CSS, JavaScript, and web frameworks', 'Lecture', 'Active'),
('CS302', 'Software Engineering', 1, 3, 'Software development lifecycle and methodologies', 'Lecture', 'Active'),
('ENG101', 'English Composition', 2, 3, 'Writing and communication skills', 'Lecture', 'Active'),
('ENG201', 'World Literature', 2, 3, 'Survey of world literature', 'Lecture', 'Active'),
('MATH101', 'Calculus I', 3, 4, 'Differential and integral calculus', 'Lecture', 'Active'),
('MATH102', 'Linear Algebra', 3, 3, 'Matrix operations and vector spaces', 'Lecture', 'Active'),
('EDU101', 'Foundations of Education', 4, 3, 'Introduction to educational principles', 'Lecture', 'Active'),
('EDU201', 'Child Development', 4, 3, 'Developmental psychology for educators', 'Lecture', 'Active'),
('BUS101', 'Introduction to Business', 5, 3, 'Business fundamentals and principles', 'Lecture', 'Active'),
('BUS201', 'Principles of Management', 5, 3, 'Management theories and practices', 'Lecture', 'Active')
ON DUPLICATE KEY UPDATE course_code = course_code;

-- =====================================================
-- 7. CLASS SECTIONS
-- =====================================================
INSERT INTO class_sections (section_code, course_id, faculty_id, semester, academic_year, schedule_day, start_time, end_time, room, max_students, status) VALUES
('CS101-A', 1, 1, 'First', '2024-2025', 'Monday,Wednesday,Friday', '08:00:00', '09:00:00', 'Room 101', 30, 'Active'),
('CS102-A', 2, 1, 'First', '2024-2025', 'Tuesday,Thursday', '10:00:00', '11:30:00', 'Room 102', 25, 'Active'),
('ENG101-A', 7, 2, 'First', '2024-2025', 'Monday,Wednesday,Friday', '09:00:00', '10:00:00', 'Room 201', 35, 'Active'),
('MATH101-A', 9, 3, 'First', '2024-2025', 'Tuesday,Thursday', '08:00:00', '09:30:00', 'Room 301', 40, 'Active'),
('EDU101-A', 11, 4, 'First', '2024-2025', 'Monday,Wednesday', '14:00:00', '15:30:00', 'Room 401', 30, 'Active'),
('BUS101-A', 13, 5, 'First', '2024-2025', 'Tuesday,Thursday', '13:00:00', '14:30:00', 'Room 501', 35, 'Active')
ON DUPLICATE KEY UPDATE section_code = section_code;

-- =====================================================
-- 8. ENROLLMENTS
-- =====================================================
INSERT INTO enrollments (student_id, section_id, enrollment_date, status, grade) VALUES
(1, 1, '2024-08-20', 'Enrolled', NULL),
(2, 1, '2024-08-20', 'Enrolled', NULL),
(8, 1, '2024-08-20', 'Enrolled', NULL),
(1, 2, '2024-08-20', 'Enrolled', NULL),
(2, 2, '2024-08-20', 'Enrolled', NULL),
(3, 3, '2024-08-20', 'Enrolled', NULL),
(4, 4, '2024-08-20', 'Enrolled', NULL),
(5, 5, '2024-08-20', 'Enrolled', NULL),
(6, 6, '2024-08-20', 'Enrolled', NULL)
ON DUPLICATE KEY UPDATE enrollment_date = enrollment_date;

-- =====================================================
-- 9. TUITION RATES
-- =====================================================
INSERT INTO tuition_rates (program_id, lecture_rate, laboratory_rate, effective_date, status) VALUES
(1, 500.00, 600.00, '2024-08-01', 'Active'),
(2, 500.00, 600.00, '2024-08-01', 'Active'),
(3, 450.00, NULL, '2024-08-01', 'Active'),
(4, 500.00, NULL, '2024-08-01', 'Active'),
(5, 450.00, NULL, '2024-08-01', 'Active'),
(6, 550.00, NULL, '2024-08-01', 'Active')
ON DUPLICATE KEY UPDATE effective_date = effective_date;

-- =====================================================
-- 10. STANDARD FEES
-- =====================================================
INSERT INTO standard_fees (fee_name, fee_type, amount, description, is_required, status) VALUES
('Registration Fee', 'One-time', 500.00, 'Semester registration fee', 1, 'Active'),
('Library Fee', 'Per Semester', 200.00, 'Library access and resources', 1, 'Active'),
('Laboratory Fee', 'Per Semester', 300.00, 'Laboratory equipment and materials', 1, 'Active'),
('Student Activity Fee', 'Per Semester', 150.00, 'Student organization activities', 1, 'Active'),
('Medical Fee', 'Per Semester', 100.00, 'Health services', 1, 'Active'),
('Athletic Fee', 'Per Semester', 100.00, 'Sports and athletic facilities', 0, 'Active')
ON DUPLICATE KEY UPDATE fee_name = fee_name;

-- =====================================================
-- 11. ANNOUNCEMENTS
-- =====================================================
INSERT INTO announcements (title, content, category, visibility, target_audience, department_id, created_by, expiration_date, is_active) VALUES
('Welcome to New Academic Year', 'Welcome all students and faculty to the new academic year 2024-2025!', 'General', 'All', 'All', NULL, 1, '2024-12-31', 1),
('Midterm Examination Schedule', 'Midterm examinations will be held from November 15-20, 2024. Please check your schedules.', 'Academic', 'All', 'Students', NULL, 1, '2024-11-20', 1),
('Faculty Meeting', 'All faculty members are required to attend the monthly meeting on October 5, 2024 at 2:00 PM.', 'Faculty', 'Faculty', 'Faculty', NULL, 1, '2024-10-05', 1),
('Tuition Payment Deadline', 'Reminder: Tuition payment deadline is October 15, 2024. Late payments will incur penalties.', 'Financial', 'All', 'Students', NULL, 1, '2024-10-15', 1),
('Computer Science Department Event', 'CS Department will host a coding competition on October 20, 2024. All CS students are welcome!', 'Events', 'Department', 'Students', 1, 1, '2024-10-20', 1)
ON DUPLICATE KEY UPDATE title = title;

-- =====================================================
-- 12. QUESTION BANKS
-- =====================================================
INSERT INTO question_banks (name, description, created_by) VALUES
('CS101 Midterm Questions', 'Question bank for Introduction to Programming midterm exam', 1),
('MATH101 Final Questions', 'Question bank for Calculus I final examination', 3),
('General Knowledge Bank', 'General knowledge questions for various subjects', 1)
ON DUPLICATE KEY UPDATE name = name;

-- =====================================================
-- 13. QUESTIONS
-- =====================================================
INSERT INTO questions (question_bank_id, question_text, question_type, points, correct_answer, explanation, created_by) VALUES
(1, 'What is a variable in programming?', 'Multiple Choice', 2.00, 'A', 'A variable is a storage location with a name', 1),
(1, 'Python is a compiled language.', 'True/False', 1.00, 'False', 'Python is an interpreted language', 1),
(1, 'Explain the concept of loops in programming.', 'Essay', 5.00, NULL, 'Loops allow repeated execution of code blocks', 1),
(2, 'What is the derivative of x^2?', 'Short Answer', 3.00, '2x', 'Using power rule: d/dx(x^2) = 2x', 3),
(2, 'The integral of 2x is x^2 + C', 'True/False', 2.00, 'True', 'The antiderivative of 2x is x^2 + C', 3)
ON DUPLICATE KEY UPDATE question_text = question_text;

-- =====================================================
-- 14. QUESTION OPTIONS
-- =====================================================
INSERT INTO question_options (question_id, option_label, option_text, is_correct) VALUES
(1, 'A', 'A storage location with a name', 1),
(1, 'B', 'A function', 0),
(1, 'C', 'A data type', 0),
(1, 'D', 'A loop', 0)
ON DUPLICATE KEY UPDATE option_label = option_label;

-- =====================================================
-- 15. EXAMINATIONS
-- =====================================================
INSERT INTO examinations (title, description, course_id, created_by, start_date, end_date, duration_minutes, total_points, passing_score, is_published) VALUES
('CS101 Midterm Exam', 'Midterm examination for Introduction to Programming', 1, 1, '2024-11-15 08:00:00', '2024-11-15 10:00:00', 120, 100.00, 60.00, 1),
('MATH101 Quiz 1', 'First quiz for Calculus I', 9, 3, '2024-10-10 10:00:00', '2024-10-10 11:00:00', 60, 50.00, 30.00, 1)
ON DUPLICATE KEY UPDATE title = title;

-- =====================================================
-- 16. EXAM QUESTIONS
-- =====================================================
INSERT INTO exam_questions (exam_id, question_id, question_order, points) VALUES
(1, 1, 1, 2.00),
(1, 2, 2, 1.00),
(1, 3, 3, 5.00),
(2, 4, 1, 3.00),
(2, 5, 2, 2.00)
ON DUPLICATE KEY UPDATE question_order = question_order;

-- =====================================================
-- 17. LIBRARY CATEGORIES
-- =====================================================
INSERT INTO library_categories (category_name, description) VALUES
('Textbooks', 'Academic textbooks and course materials'),
('Research Papers', 'Academic research papers and journals'),
('Reference Books', 'Reference materials and dictionaries'),
('E-Books', 'Digital books and electronic resources'),
('Videos', 'Educational videos and tutorials')
ON DUPLICATE KEY UPDATE category_name = category_name;

-- =====================================================
-- 18. LIBRARY MATERIALS
-- =====================================================
INSERT INTO library_materials (title, description, category_id, file_name, file_path, file_size, file_type, file_extension, author, access_level, department_id, uploaded_by, is_active) VALUES
('Introduction to Algorithms', 'Comprehensive guide to algorithms and data structures', 1, 'algorithms.pdf', 'uploads/library/algorithms.pdf', 5242880, 'application/pdf', 'pdf', 'Thomas H. Cormen', 'Public', 1, 1, 1),
('Database Design Fundamentals', 'Basic concepts of database design', 1, 'database.pdf', 'uploads/library/database.pdf', 3145728, 'application/pdf', 'pdf', 'John Smith', 'Public', 1, 1, 1),
('Web Development Guide', 'Complete guide to modern web development', 4, 'webdev.pdf', 'uploads/library/webdev.pdf', 4194304, 'application/pdf', 'pdf', 'Jane Doe', 'Public', 1, 1, 1)
ON DUPLICATE KEY UPDATE title = title;

-- =====================================================
-- 19. DOCUMENT TYPES
-- =====================================================
INSERT INTO document_types (type_name, description, is_active) VALUES
('Transcript', 'Official academic transcript', 1),
('Certificate of Enrollment', 'Proof of current enrollment', 1),
('Certificate of Good Moral Character', 'Certification of student''s good moral standing', 1)
ON DUPLICATE KEY UPDATE type_name = type_name;

-- =====================================================
-- 20. DOCUMENT REQUESTS
-- =====================================================
INSERT INTO document_requests (student_id, document_type_id, request_date, status, requested_by, notes) VALUES
(1, 1, '2024-09-15 10:00:00', 'Approved', 8, 'Need for scholarship application'),
(2, 2, '2024-09-20 14:30:00', 'Pending', 9, 'Required for internship'),
(3, 3, '2024-09-25 09:15:00', 'Approved', 10, 'For job application')
ON DUPLICATE KEY UPDATE request_date = request_date;

-- =====================================================
-- 21. USER PREFERENCES (Theme)
-- =====================================================
INSERT INTO user_preferences (user_id, theme_mode) VALUES
(8, 'light'),
(9, 'dark'),
(10, 'light')
ON DUPLICATE KEY UPDATE theme_mode = theme_mode;

-- =====================================================
-- 22. SYSTEM SETTINGS
-- =====================================================
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('announcements_enabled', '1', 'Enable/Disable the School News & Announcements feature (1 = enabled, 0 = disabled)'),
('examinations_enabled', '1', 'Enable/Disable the Online Examination & Assessments feature (1 = enabled, 0 = disabled)'),
('library_enabled', '1', 'Enable/Disable the Digital Library & Research Hub feature (1 = enabled, 0 = disabled)'),
('document_requests_enabled', '1', 'Enable/Disable the Document Request System feature (1 = enabled, 0 = disabled)'),
('theme_customization_enabled', '1', 'Enable/Disable the Student Theme Customization feature (1 = enabled, 0 = disabled)')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- =====================================================
-- 23. ROOMS
-- =====================================================
INSERT INTO rooms (room_number, room_name, building, capacity, room_type, equipment, status) VALUES
('101', 'Computer Lab 1', 'Main Building', 30, 'Laboratory', '30 computers, projector', 'Available'),
('102', 'Computer Lab 2', 'Main Building', 25, 'Laboratory', '25 computers, smart board', 'Available'),
('201', 'Lecture Hall A', 'Main Building', 50, 'Lecture', 'Projector, sound system', 'Available'),
('301', 'Math Lab', 'Science Building', 40, 'Laboratory', 'Calculators, whiteboards', 'Available'),
('401', 'Education Lab', 'Education Building', 30, 'Laboratory', 'Teaching materials', 'Available'),
('501', 'Business Lab', 'Business Building', 35, 'Laboratory', 'Computers, presentation tools', 'Available')
ON DUPLICATE KEY UPDATE room_number = room_number;

-- =====================================================
-- 24. EQUIPMENT
-- =====================================================
INSERT INTO equipment (equipment_name, equipment_type, serial_number, location, status, purchase_date) VALUES
('Laptop Computer', 'Computer', 'LAP001', 'Computer Lab 1', 'Available', '2023-01-15'),
('Projector', 'Projector', 'PROJ001', 'Lecture Hall A', 'Available', '2023-02-20'),
('Smart Board', 'Lab Equipment', 'SB001', 'Computer Lab 2', 'Available', '2023-03-10'),
('Printer', 'Other', 'PRINT001', 'Library', 'Available', '2023-04-05')
ON DUPLICATE KEY UPDATE serial_number = serial_number;

-- =====================================================
-- 25. ADVISORY ASSIGNMENTS
-- =====================================================
INSERT INTO advisory_assignments (student_id, advisor_id, assigned_date, status) VALUES
(1, 1, '2024-08-15', 'Active'),
(2, 1, '2024-08-15', 'Active'),
(3, 1, '2024-08-15', 'Active'),
(4, 2, '2024-08-15', 'Active'),
(5, 3, '2024-08-15', 'Active'),
(6, 4, '2024-08-15', 'Active'),
(7, 5, '2024-08-15', 'Active'),
(8, 1, '2024-08-15', 'Active')
ON DUPLICATE KEY UPDATE assigned_date = assigned_date;

-- =====================================================
-- END OF SAMPLE DATA
-- =====================================================
-- Note: Password hash is for 'password' - users should change it after first login
-- =====================================================

