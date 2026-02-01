# Database Migration Instructions

## Migration: Enhanced Enrollment Fields

This migration adds comprehensive enrollment fields to the `admission_applications` table to match the MU Online Enrollment System requirements.

### Files
- `database/migration_enrollment_fields.sql` - SQL migration file

### Steps to Apply Migration

1. **Backup your database first!**
   ```sql
   -- Create backup
   mysqldump -u root -p academic_management_system > backup_before_migration.sql
   ```

2. **Run the migration:**
   
   **Option A: Using phpMyAdmin**
   - Open phpMyAdmin
   - Select the `academic_management_system` database
   - Click on "SQL" tab
   - Copy and paste the contents of `migration_enrollment_fields.sql`
   - Click "Go" to execute

   **Option B: Using MySQL Command Line**
   ```bash
   mysql -u root -p academic_management_system < database/migration_enrollment_fields.sql
   ```

   **Option C: Using PHP Script (Create a temporary file)**
   ```php
   <?php
   require_once 'config/database.php';
   $pdo = getDBConnection();
   $sql = file_get_contents('database/migration_enrollment_fields.sql');
   $pdo->exec($sql);
   echo "Migration completed!";
   ?>
   ```

### New Fields Added

The migration adds the following fields to `admission_applications`:

#### Student Type & Academic
- `student_type` (Old/New/Transferee/Returnee)
- `id_number`
- `year_level`
- `major`
- `term`

#### Personal Information
- `religion`
- `civil_status`
- `photo_path`

#### Family Information
- `father_lastname`, `father_firstname`, `father_middlename`
- `mother_lastname`, `mother_firstname`, `mother_middlename`, `mother_maiden_middlename`

#### Address Information
- `home_street`, `home_city`, `home_province`, `home_zipcode`
- `current_street`, `current_city`, `current_province`, `current_zipcode`

#### Contact & Guardian
- `parents_contact`
- `guardian_name`
- `guardian_contact`

#### Household Information
- `dswd_household_no`
- `monthly_income`
- `disability`
- `organizations` (JSON)

#### Distance Learning
- `internet_speed`
- `devices_available` (JSON)

#### MU Data Plan
- `data_provider`
- `data_plan_mobile`

#### Citizenship & Conduct
- `citizenship`
- `dual_citizenship`
- `disciplinary_history`
- `disciplinary_specification`

#### Pledges & Consents
- `pledge_no_drugs`
- `pledge_no_unlawful_acts`
- `consent_marketing`
- `consent_data_processing`
- `assistance_received`
- `pledge_attested_by`
- `pledge_relationship`

### Important Notes

1. **Photo Uploads**: Make sure the `uploads/student_photos/` directory exists and is writable:
   ```bash
   mkdir -p uploads/student_photos
   chmod 755 uploads/student_photos
   ```

2. **JSON Fields**: The `organizations` and `devices_available` fields use JSON format. The application handles encoding/decoding automatically.

3. **Backward Compatibility**: Existing applications will work fine - new fields are optional and will be NULL for existing records.

### Verification

After running the migration, verify the structure:
```sql
DESCRIBE admission_applications;
```

You should see all the new fields listed above.

### Rollback (if needed)

If you need to rollback, you can drop the columns:
```sql
-- Note: This will lose data! Only use if necessary.
ALTER TABLE admission_applications
DROP COLUMN student_type,
DROP COLUMN id_number,
-- ... (list all new columns)
-- But better to restore from backup!
```

### Support

If you encounter any issues:
1. Check MySQL error logs
2. Verify database user has ALTER TABLE permissions
3. Ensure no foreign key constraints are blocking the migration
4. Restore from backup if needed

