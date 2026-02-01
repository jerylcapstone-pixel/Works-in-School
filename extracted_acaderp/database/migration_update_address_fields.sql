-- =====================================================
-- Migration Script: Update Address Fields for Faculty Table
-- Changes: address, city, state, zip_code -> barangay, purok_street, zipcode, province, municipality
-- Run this script if you have an existing database
-- =====================================================

USE academic_management_system;

-- Step 1: Add new address columns
ALTER TABLE faculty 
ADD COLUMN barangay VARCHAR(100) NULL AFTER email,
ADD COLUMN purok_street VARCHAR(100) NULL AFTER barangay,
ADD COLUMN zipcode VARCHAR(20) NULL AFTER purok_street,
ADD COLUMN province VARCHAR(100) NULL AFTER zipcode,
ADD COLUMN municipality VARCHAR(100) NULL AFTER province;

-- Step 2: Optional - Migrate existing data if you have any
-- Note: This is a basic migration. You may need to manually review and update addresses
-- The old 'city' will be mapped to 'municipality' and 'state' to 'province'
-- Old 'address' field content could be split into barangay and purok_street manually
-- Old 'zip_code' will be copied to 'zipcode'

-- Uncomment the following lines if you want to migrate existing data:
-- UPDATE faculty 
-- SET municipality = COALESCE(city, ''),
--     province = COALESCE(state, ''),
--     zipcode = COALESCE(zip_code, '')
-- WHERE city IS NOT NULL OR state IS NOT NULL OR zip_code IS NOT NULL;

-- Step 3: After verifying the migration, drop old columns
-- WARNING: Only run this after you've verified all data has been migrated!
-- Uncomment when ready:

-- ALTER TABLE faculty 
-- DROP COLUMN address,
-- DROP COLUMN city,
-- DROP COLUMN state,
-- DROP COLUMN zip_code;

-- Note: The address field will not be automatically migrated as it needs manual review
-- Please manually update the barangay and purok_street fields based on your address data
