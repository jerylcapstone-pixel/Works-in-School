# Billing Fee Calculation Guide

## Overview
The billing system now properly calculates **ALL fees** for student invoices, including:
1. **Unit-Based Fees** (Tuition) - varies by enrolled units
2. **Standard Fees** - fixed fees required for all students

---

## How Invoice Generation Works

### When Creating a NEW Invoice

The system will automatically calculate and add:

#### 1. **Lecture Tuition** (Unit-Based)
- **Calculation**: `Number of Lecture Units × Lecture Rate per Unit`
- **Example**: 15 units × ₱500.00 = **₱7,500.00**
- **Description**: "Lecture Tuition - 15 unit(s) @ ₱500.00 per unit"

#### 2. **Laboratory Tuition** (Unit-Based)
- **Calculation**: `Number of Lab Units × Laboratory Rate per Unit`
- **Example**: 3 units × ₱600.00 = **₱1,800.00**
- **Description**: "Laboratory Tuition - 3 unit(s) @ ₱600.00 per unit"

#### 3. **Standard Fees** (Fixed - Required for ALL Students)
These fees are added to EVERY student invoice:
- Registration Fee
- Library Fee
- Athletic Fee
- Medical/Dental Fee
- Student ID Fee
- Computer Fee
- etc.

#### 4. **Laboratory Fee** (Conditional)
- Only added if the student has enrolled in lab courses
- This is separate from laboratory tuition

---

## Complete Invoice Example

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STUDENT INVOICE - First Semester 2025-26
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

UNIT-BASED FEES (Varies by Enrollment):
├─ Lecture Tuition
│  └─ 15 units × ₱500.00 = ₱7,500.00
│
└─ Laboratory Tuition  
   └─ 3 units × ₱600.00 = ₱1,800.00
                          ──────────
   Tuition Subtotal:      ₱9,300.00

STANDARD FEES (Required for ALL Students):
├─ Registration Fee      ₱1,000.00
├─ Library Fee          ₱500.00
├─ Athletic Fee         ₱300.00
├─ Medical/Dental Fee   ₱500.00
├─ Student ID           ₱200.00
├─ Computer Fee         ₱800.00
└─ Laboratory Fee       ₱1,000.00 (only if has lab courses)
                        ──────────
   Standard Fees Total:  ₱4,300.00

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TOTAL INVOICE AMOUNT:    ₱13,600.00
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## About Existing Invoices

### The Invoice in Your Screenshot (INV-000001)
The invoice you showed only contains standard fees:
- Charges: ₱1,000.00
- Matriculation Fee: ₱1,000.00
- Other Fees: ₱1,000.00
- Test Paper: ₱1,000.00
- **Total: ₱4,000.00**

**This invoice is MISSING the unit-based tuition fees!**

This happened because:
1. It was created before the billing fixes were implemented
2. It may have been created manually without proper calculation
3. The student's enrollment data wasn't properly fetched

---

## How to Fix Existing Invoices

### Option 1: Delete and Regenerate
1. Go to **Billing → Invoices**
2. Delete the old invoice (INV-000001)
3. Go to **Billing → Create Invoice**
4. Select the student and semester
5. The system will automatically calculate ALL fees including unit-based tuition

### Option 2: Bulk Regenerate All Invoices
1. Go to **Billing → Generate Invoices**
2. Select the semester (e.g., "First")
3. Select the academic year (e.g., "2025-26")
4. Click **Preview** to verify calculations
5. Click **Generate Invoices**

**Note**: The system will skip students who already have invoices for that semester. You'll need to delete old invoices first if you want to regenerate them.

---

## Testing the New System

### Test Case 1: Create Individual Invoice
1. Go to **Billing → Create Invoice**
2. Select a student who is enrolled in courses
3. Select semester and academic year
4. The system should automatically add:
   - ✅ Lecture tuition (based on lecture units)
   - ✅ Lab tuition (based on lab units)
   - ✅ All standard fees
   - ✅ Lab fee (only if student has lab courses)

### Test Case 2: Bulk Generate Invoices
1. Go to **Billing → Generate Invoices**
2. Fill in semester and academic year
3. Click **Preview** to see calculations
4. Verify each student has:
   - ✅ Unit-based tuition calculated correctly
   - ✅ Standard fees included
5. Click **Generate Invoices** to create them

---

## Required Configuration

### 1. Setup Tuition Rates
Go to **Billing → Tuition Rates** and configure:
- **Lecture Rate per Unit** (e.g., ₱500.00)
- **Laboratory Rate per Unit** (e.g., ₱600.00)
- Set for each program or use a general rate

### 2. Setup Standard Fees
Go to **Billing → Manage Standard Fees** and configure:
- Registration Fee
- Library Fee
- Athletic Fee
- Medical/Dental Fee
- Student ID Fee
- Computer Fee
- Laboratory Fee (conditional)
- etc.

### 3. Ensure Student Enrollments
Students must be enrolled in courses with:
- ✅ Status = "Enrolled"
- ✅ Courses have credits assigned
- ✅ Course type is set (Lecture vs Lab)
- ✅ Semester and academic year match

---

## Technical Changes Made

### Files Modified:
1. **`modules/billing/get_student_data.php`**
   - Now separates lecture units and lab units
   - Fetches separate lecture_rate and laboratory_rate
   - Returns detailed breakdown for calculation

2. **`modules/billing/invoice_add.php`**
   - Generates separate line items for lecture and lab tuition
   - Calculates tuition based on enrolled units
   - Automatically adds all standard fees

3. **`modules/billing/generate.php`**
   - Already working correctly (no changes needed)
   - Properly calculates both unit-based and standard fees

---

## Troubleshooting

### Issue: Invoice shows ₱0.00 for tuition
**Cause**: No tuition rate configured for the program
**Solution**: Go to Billing → Tuition Rates and add rates

### Issue: No units showing for student
**Cause**: Student is not enrolled in any courses
**Solution**: Enroll student in courses via Registration → Enroll

### Issue: Standard fees not appearing
**Cause**: Standard fees not configured or inactive
**Solution**: Go to Billing → Manage Standard Fees and activate them

### Issue: Old invoices don't have unit-based fees
**Cause**: Invoices created before the fix
**Solution**: Delete old invoices and regenerate them

---

## Summary

✅ **The billing system is now fixed!**

For **NEW invoices**, the system will automatically:
1. Calculate lecture tuition (units × lecture rate)
2. Calculate lab tuition (units × lab rate)
3. Add all standard fees required for students
4. Add laboratory fee if student has lab courses

For **EXISTING invoices** like INV-000001:
- They need to be deleted and regenerated to include unit-based tuition
- Or you can edit them manually to add the missing tuition items

---

**Next Steps:**
1. Delete invoice INV-000001
2. Create a new invoice for Benedict Ian Te
3. Verify it includes both unit-based tuition and standard fees
4. Test with multiple students to ensure consistency

