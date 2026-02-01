-- Migration: Add comprehensive enrollment fields to admission_applications table
-- Date: 2024

ALTER TABLE admission_applications
-- Student Type & Academic Info
ADD COLUMN student_type ENUM('Old', 'New', 'Transferee', 'Returnee') DEFAULT 'New' AFTER student_number,
ADD COLUMN id_number VARCHAR(50) NULL AFTER student_number,
ADD COLUMN year_level VARCHAR(20) NULL AFTER program_id,
ADD COLUMN major VARCHAR(100) NULL AFTER year_level,
ADD COLUMN term VARCHAR(50) NULL AFTER major,

-- Personal Information
ADD COLUMN religion VARCHAR(100) NULL AFTER gender,
ADD COLUMN civil_status ENUM('Single', 'Married', 'Widowed', 'Divorced', 'Separated') NULL AFTER religion,
ADD COLUMN photo_path VARCHAR(255) NULL AFTER civil_status,

-- Family Information
ADD COLUMN father_lastname VARCHAR(100) NULL AFTER address,
ADD COLUMN father_firstname VARCHAR(100) NULL AFTER father_lastname,
ADD COLUMN father_middlename VARCHAR(100) NULL AFTER father_firstname,
ADD COLUMN mother_lastname VARCHAR(100) NULL AFTER father_middlename,
ADD COLUMN mother_firstname VARCHAR(100) NULL AFTER mother_lastname,
ADD COLUMN mother_middlename VARCHAR(100) NULL AFTER mother_firstname,
ADD COLUMN mother_maiden_middlename VARCHAR(100) NULL AFTER mother_middlename,

-- Address Information
ADD COLUMN home_street VARCHAR(255) NULL AFTER mother_maiden_middlename,
ADD COLUMN home_city VARCHAR(100) NULL AFTER home_street,
ADD COLUMN home_province VARCHAR(100) NULL AFTER home_city,
ADD COLUMN home_zipcode VARCHAR(20) NULL AFTER home_province,
ADD COLUMN current_street VARCHAR(255) NULL AFTER home_zipcode,
ADD COLUMN current_city VARCHAR(100) NULL AFTER current_street,
ADD COLUMN current_province VARCHAR(100) NULL AFTER current_city,
ADD COLUMN current_zipcode VARCHAR(20) NULL AFTER current_province,

-- Contact & Guardian Information
ADD COLUMN parents_contact VARCHAR(20) NULL AFTER phone,
ADD COLUMN guardian_name VARCHAR(255) NULL AFTER parents_contact,
ADD COLUMN guardian_contact VARCHAR(20) NULL AFTER guardian_name,

-- Household Information
ADD COLUMN dswd_household_no VARCHAR(50) NULL AFTER guardian_contact,
ADD COLUMN monthly_income DECIMAL(10,2) NULL AFTER dswd_household_no,
ADD COLUMN disability VARCHAR(255) NULL AFTER monthly_income,

-- Organization Affiliations (JSON format)
ADD COLUMN organizations JSON NULL AFTER disability,

-- Distance Learning Resources
ADD COLUMN internet_speed ENUM('Mobile Cellular Data', 'Less than 1 mbps', '1-4 mbps', '5-9 mbps', '10 and up mbps', 'none') NULL AFTER organizations,
ADD COLUMN devices_available JSON NULL AFTER internet_speed,

-- MU Data Plan
ADD COLUMN data_provider ENUM('Globe Telecommunications', 'Smart Communications') NULL AFTER devices_available,
ADD COLUMN data_plan_mobile VARCHAR(20) NULL AFTER data_provider,

-- Citizenship & Conduct
ADD COLUMN citizenship VARCHAR(100) NULL AFTER data_plan_mobile,
ADD COLUMN dual_citizenship BOOLEAN DEFAULT 0 AFTER citizenship,
ADD COLUMN disciplinary_history BOOLEAN DEFAULT 0 AFTER dual_citizenship,
ADD COLUMN disciplinary_specification TEXT NULL AFTER disciplinary_history,

-- Pledges & Consents
ADD COLUMN pledge_no_drugs BOOLEAN DEFAULT 0 AFTER disciplinary_specification,
ADD COLUMN pledge_no_unlawful_acts BOOLEAN DEFAULT 0 AFTER pledge_no_drugs,
ADD COLUMN consent_marketing BOOLEAN DEFAULT 0 AFTER pledge_no_unlawful_acts,
ADD COLUMN consent_data_processing BOOLEAN DEFAULT 0 AFTER consent_marketing,
ADD COLUMN assistance_received BOOLEAN DEFAULT 0 AFTER consent_data_processing,

-- Student's Pledge
ADD COLUMN pledge_attested_by VARCHAR(255) NULL AFTER assistance_received,
ADD COLUMN pledge_relationship VARCHAR(100) NULL AFTER pledge_attested_by;

