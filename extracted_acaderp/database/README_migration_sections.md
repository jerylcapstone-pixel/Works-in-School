# Migration: Add Department, Program, and Major to Class Sections

## Overview
This migration adds three new columns to the `class_sections` table:
- `department_id` - Links section to a department
- `program_id` - Links section to a program  
- `major_id` - Links section to a major

## How to Run the Migration

### Option 1: Using phpMyAdmin
1. Open phpMyAdmin in your browser
2. Select your database (usually `semirols`)
3. Click on the "SQL" tab
4. Copy and paste the contents of `migration_add_dept_prog_major_to_sections.sql`
5. Click "Go" to execute

### Option 2: Using MySQL Command Line
```bash
mysql -u root -p semirols < migration_add_dept_prog_major_to_sections.sql
```

### Option 3: Using XAMPP Shell
```bash
cd C:\xampp\mysql\bin
mysql -u root -p
USE semirols;
SOURCE C:/xamp/htdocs/SemiRols/database/migration_add_dept_prog_major_to_sections.sql;
```

## What This Migration Does

1. **Adds Three New Columns** to `class_sections`:
   - `department_id INT NULL` - After course_id
   - `program_id INT NULL` - After department_id
   - `major_id INT NULL` - After program_id

2. **Adds Foreign Keys**:
   - Links to `departments` table (ON DELETE SET NULL)
   - Links to `programs` table (ON DELETE SET NULL)
   - Links to `majors` table if it exists (ON DELETE SET NULL)

3. **Adds Indexes** for better query performance:
   - `idx_department` on department_id
   - `idx_program` on program_id
   - `idx_major` on major_id

## Safe Migration
- The migration checks if columns already exist before adding them
- The migration checks if foreign keys already exist before adding them
- Can be run multiple times safely (idempotent)

## After Migration

Once the migration is complete, the system will automatically:
- Capture department, program, and major when creating new sections
- Store these values in the class_sections table
- Allow filtering and reporting by department/program/major

## Verification

After running the migration, verify it worked:

```sql
-- Check if columns were added
DESCRIBE class_sections;

-- Should see department_id, program_id, and major_id columns

-- Check foreign keys
SHOW CREATE TABLE class_sections;

-- Should see the new foreign key constraints
```

## Rollback (if needed)

If you need to undo this migration:

```sql
-- Remove foreign keys first
ALTER TABLE class_sections DROP FOREIGN KEY fk_section_department;
ALTER TABLE class_sections DROP FOREIGN KEY fk_section_program;
ALTER TABLE class_sections DROP FOREIGN KEY fk_section_major;

-- Remove indexes
ALTER TABLE class_sections DROP INDEX idx_department;
ALTER TABLE class_sections DROP INDEX idx_program;
ALTER TABLE class_sections DROP INDEX idx_major;

-- Remove columns
ALTER TABLE class_sections DROP COLUMN major_id;
ALTER TABLE class_sections DROP COLUMN program_id;
ALTER TABLE class_sections DROP COLUMN department_id;
```

