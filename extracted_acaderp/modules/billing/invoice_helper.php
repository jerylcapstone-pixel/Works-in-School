<?php
/**
 * Invoice Helper Functions
 * Functions for automatic invoice generation
 */

require_once __DIR__ . '/../../config/config.php';

/**
 * Generate invoice number
 */
function generateInvoiceNumber($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoices");
    $count = $stmt->fetchColumn();
    $invoice_number = 'INV-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    
    // Check if it exists, if so increment
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
    $check_stmt->execute([$invoice_number]);
    while ($check_stmt->fetchColumn() > 0) {
        $count++;
        $invoice_number = 'INV-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
        $check_stmt->execute([$invoice_number]);
    }
    return $invoice_number;
}

/**
 * Automatically create or update invoice for a student when they enroll
 * This function uses the new program-based rate structure with separate lecture and laboratory rates
 */
function createInvoiceForStudent($pdo, $student_id, $semester, $academic_year, $due_date = null) {
    try {
        // Check if invoice already exists for this student/semester/academic_year
        $stmt = $pdo->prepare("SELECT invoice_id, discount, financial_aid_amount, paid_amount, due_date 
                              FROM invoices 
                              WHERE student_id = ? AND semester = ? AND academic_year = ?");
        $stmt->execute([$student_id, $semester, $academic_year]);
        $existing_invoice = $stmt->fetch();
        $invoice_exists = $existing_invoice !== false;
        $invoice_id = $existing_invoice ? $existing_invoice['invoice_id'] : null;
        
        // Get student info with program
        $stmt = $pdo->prepare("SELECT s.*, p.program_id, p.program_name, p.duration_years, 
                               d.department_id, d.department_name
                               FROM students s
                               LEFT JOIN programs p ON s.program_id = p.program_id
                               LEFT JOIN departments d ON p.department_id = d.department_id
                               WHERE s.student_id = ? AND s.status = 'Active'");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            return false;
        }
        
        // Check if new rate columns exist
        $lecture_rate_column_exists = false;
        $laboratory_rate_column_exists = false;
        try {
            $check_stmt = $pdo->query("SHOW COLUMNS FROM tuition_rates LIKE 'lecture_rate'");
            $lecture_rate_column_exists = $check_stmt->rowCount() > 0;
            $check_stmt = $pdo->query("SHOW COLUMNS FROM tuition_rates LIKE 'laboratory_rate'");
            $laboratory_rate_column_exists = $check_stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Columns don't exist - use old structure
        }
        
        if (!$lecture_rate_column_exists || !$laboratory_rate_column_exists) {
            error_log("New tuition rate structure not available. Please run migration: database/migration_redesign_tuition_rates.sql");
            return false;
        }
        
        // Get active tuition rate for student's program (if student has a program)
        $program_lecture_rate = 0;
        $program_laboratory_rate = 0;
        if ($student['program_id']) {
            $stmt = $pdo->prepare("SELECT lecture_rate, laboratory_rate, effective_date
                                  FROM tuition_rates 
                                  WHERE program_id = ? 
                                  AND status = 'Active'
                                  AND effective_date <= CURDATE()
                                  ORDER BY effective_date DESC 
                                  LIMIT 1");
            $stmt->execute([$student['program_id']]);
            $program_rate = $stmt->fetch();
            
            if ($program_rate) {
                $program_lecture_rate = (float)($program_rate['lecture_rate'] ?? 0);
                $program_laboratory_rate = (float)($program_rate['laboratory_rate'] ?? 0);
            }
        }
        
        // Get active general education rate (program_id IS NULL)
        $general_lecture_rate = 0;
        $general_laboratory_rate = 0;
        $stmt = $pdo->prepare("SELECT lecture_rate, laboratory_rate, effective_date
                              FROM tuition_rates 
                              WHERE program_id IS NULL 
                              AND status = 'Active'
                              AND effective_date <= CURDATE()
                              ORDER BY effective_date DESC 
                              LIMIT 1");
        $stmt->execute();
        $general_rate = $stmt->fetch();
        
        if ($general_rate) {
            $general_lecture_rate = (float)($general_rate['lecture_rate'] ?? 0);
            $general_laboratory_rate = (float)($general_rate['laboratory_rate'] ?? 0);
        }
        
        // If student has no program, they must use general education rate
        if (!$student['program_id'] && $general_lecture_rate <= 0) {
            error_log("Student $student_id has no program and no general education rate found. Cannot create invoice.");
            return false;
        }
        
        // If student has program but no program rate, check if general education rate exists
        if ($student['program_id'] && $program_lecture_rate <= 0 && $general_lecture_rate <= 0) {
            error_log("No active tuition rate found for program {$student['program_id']} and no general education rate. Cannot create invoice.");
            return false;
        }
        
        // Get enrolled courses with details for the semester/academic year
        $stmt = $pdo->prepare("SELECT e.enrollment_id, c.course_id, c.course_code, c.course_name, c.credits, 
                              c.course_type, c.department_id
                              FROM enrollments e
                              INNER JOIN class_sections cs ON e.section_id = cs.section_id
                              INNER JOIN courses c ON cs.course_id = c.course_id
                              WHERE e.student_id = ? 
                              AND e.status = 'Enrolled'
                              AND cs.semester = ?
                              AND cs.academic_year = ?");
        $stmt->execute([$student_id, $semester, $academic_year]);
        $enrolled_courses = $stmt->fetchAll();
        
        if (empty($enrolled_courses)) {
            // No enrollments found, skip invoice creation
            return false;
        }
        
        // Separate courses into program courses and general education courses
        // Also separate by lecture and laboratory
        $program_lecture_courses = [];
        $program_laboratory_courses = [];
        $general_lecture_courses = [];
        $general_laboratory_courses = [];
        
        foreach ($enrolled_courses as $course) {
            $course_type = strtolower(trim($course['course_type'] ?? 'Lecture'));
            $is_lab = in_array($course_type, ['lab', 'laboratory', 'laboratory course']) || 
                      strpos($course_type, 'lab') !== false;
            
            // Determine if course belongs to student's program
            $is_program_course = false;
            if ($student['program_id']) {
                // Check if course is in program_courses table
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM program_courses 
                                            WHERE program_id = ? AND course_id = ?");
                $check_stmt->execute([$student['program_id'], $course['course_id']]);
                $is_program_course = $check_stmt->fetchColumn() > 0;
                
                // Also check if course belongs to same department as student's program
                if (!$is_program_course && $student['department_id'] && $course['department_id'] && 
                    $course['department_id'] == $student['department_id']) {
                    $is_program_course = true;
                }
            }
            
            // Categorize course
            if ($is_program_course) {
                if ($is_lab) {
                    $program_laboratory_courses[] = $course;
                } else {
                    $program_lecture_courses[] = $course;
                }
            } else {
                // General education course (not in student's program)
                if ($is_lab) {
                    $general_laboratory_courses[] = $course;
                } else {
                    $general_lecture_courses[] = $course;
                }
            }
        }
        
        // Calculate tuition using appropriate rates
        $lecture_tuition = 0;
        $lecture_units = 0;
        $laboratory_tuition = 0;
        $laboratory_units = 0;
        
        // Program lecture courses
        $rate_to_use = $student['program_id'] && $program_lecture_rate > 0 ? $program_lecture_rate : $general_lecture_rate;
        foreach ($program_lecture_courses as $course) {
            $credits = (int)$course['credits'];
            $lecture_units += $credits;
            $lecture_tuition += $rate_to_use * $credits;
        }
        
        // General education lecture courses
        if ($general_lecture_rate > 0) {
            foreach ($general_lecture_courses as $course) {
                $credits = (int)$course['credits'];
                $lecture_units += $credits;
                $lecture_tuition += $general_lecture_rate * $credits;
            }
        }
        
        // Program laboratory courses
        $lab_rate_to_use = $student['program_id'] && $program_laboratory_rate > 0 ? $program_laboratory_rate : $general_laboratory_rate;
        if ($lab_rate_to_use > 0) {
            foreach ($program_laboratory_courses as $course) {
                $credits = (int)$course['credits'];
                $laboratory_units += $credits;
                $laboratory_tuition += $lab_rate_to_use * $credits;
            }
        }
        
        // General education laboratory courses
        if ($general_laboratory_rate > 0) {
            foreach ($general_laboratory_courses as $course) {
                $credits = (int)$course['credits'];
                $laboratory_units += $credits;
                $laboratory_tuition += $general_laboratory_rate * $credits;
            }
        }
        // If no laboratory rate is set, skip lab computation (no error)
        
        // Get standard fees
        $standard_fees = [];
        try {
            $stmt = $pdo->query("SELECT * FROM standard_fees WHERE is_active = 1 ORDER BY fee_category");
            $standard_fees = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Table doesn't exist
        }
        
        // Calculate subtotal (tuition + fees)
        $subtotal = $lecture_tuition + $laboratory_tuition;
        
        // Add standard fees
        foreach ($standard_fees as $fee) {
            $subtotal += (float)$fee['amount'];
        }
        
        // Set default due date if not provided (30 days from now)
        if (!$due_date) {
            if ($invoice_exists && $existing_invoice['due_date']) {
                // Keep existing due date if invoice exists
                $due_date = $existing_invoice['due_date'];
            } else {
                $due_date = date('Y-m-d', strtotime('+30 days'));
            }
        }
        
        // Preserve existing discount and financial aid if invoice exists
        $discount = $invoice_exists ? (float)$existing_invoice['discount'] : 0;
        $financial_aid_amount = $invoice_exists ? (float)$existing_invoice['financial_aid_amount'] : 0;
        
        // Get actual paid amount from payments table if invoice exists
        $paid_amount = 0;
        if ($invoice_exists) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            $payment_data = $stmt->fetch();
            $paid_amount = (float)($payment_data['total_paid'] ?? $existing_invoice['paid_amount'] ?? 0);
        }
        
        // Calculate totals
        $total_amount = max(0, $subtotal - $discount - $financial_aid_amount);
        $balance = max(0, $total_amount - $paid_amount);
        
        // Determine status based on payment
        $status = 'Pending';
        if ($paid_amount >= $total_amount && $total_amount > 0) {
            $status = 'Paid';
        } elseif ($paid_amount > 0 && $paid_amount < $total_amount) {
            $status = 'Partial';
        }
        
        if ($invoice_exists) {
            // Get current status to preserve if it's Overdue or Cancelled
            $stmt = $pdo->prepare("SELECT status FROM invoices WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            $current_status = $stmt->fetchColumn();
            
            // Preserve Overdue or Cancelled status, otherwise update based on payment
            if ($current_status === 'Overdue' || $current_status === 'Cancelled') {
                $status = $current_status;
            }
            
            // Update existing invoice
            $stmt = $pdo->prepare("UPDATE invoices SET 
                                  subtotal = ?, 
                                  total_amount = ?,
                                  paid_amount = ?,
                                  balance = ?,
                                  status = ?,
                                  updated_at = NOW()
                                  WHERE invoice_id = ?");
            $stmt->execute([$subtotal, $total_amount, $paid_amount, $balance, $status, $invoice_id]);
            
            // Delete existing invoice items to recalculate
            $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
        } else {
            // Generate invoice number for new invoice
            $invoice_number = generateInvoiceNumber($pdo);
            
            // Insert new invoice
            $stmt = $pdo->prepare("INSERT INTO invoices (student_id, invoice_number, semester, academic_year, due_date, 
                                  subtotal, discount, financial_aid_amount, total_amount, paid_amount, balance, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'Pending')");
            $stmt->execute([
                $student_id, $invoice_number, $semester, $academic_year, $due_date,
                $subtotal, $discount, $financial_aid_amount, $total_amount, $balance
            ]);
            
            $invoice_id = $pdo->lastInsertId();
        }
        
        // Add invoice items - separated by Lecture Tuition, Laboratory Tuition, and Other Fees
        
        // Calculate rates to use
        $program_lecture_rate_to_use = $student['program_id'] && $program_lecture_rate > 0 ? $program_lecture_rate : $general_lecture_rate;
        $program_lab_rate_to_use = $student['program_id'] && $program_laboratory_rate > 0 ? $program_laboratory_rate : $general_laboratory_rate;
        
        // 1. Program Lecture Tuition (if applicable)
        if (!empty($program_lecture_courses) && $program_lecture_rate_to_use > 0) {
            $program_lecture_total = 0;
            $program_lecture_units = 0;
            foreach ($program_lecture_courses as $course) {
                $credits = (int)$course['credits'];
                $program_lecture_units += $credits;
                $program_lecture_total += $program_lecture_rate_to_use * $credits;
            }
            if ($program_lecture_total > 0) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                                      VALUES (?, 'Tuition', ?, ?, ?, ?)");
                $stmt->execute([
                    $invoice_id,
                    'Lecture Tuition (Program Courses)',
                    $program_lecture_units,
                    $program_lecture_rate_to_use,
                    $program_lecture_total
                ]);
            }
        }
        
        // 2. General Education Lecture Tuition (if applicable)
        if (!empty($general_lecture_courses) && $general_lecture_rate > 0) {
            $general_lecture_total = 0;
            $general_lecture_units = 0;
            foreach ($general_lecture_courses as $course) {
                $credits = (int)$course['credits'];
                $general_lecture_units += $credits;
                $general_lecture_total += $general_lecture_rate * $credits;
            }
            if ($general_lecture_total > 0) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                                      VALUES (?, 'Tuition', ?, ?, ?, ?)");
                $stmt->execute([
                    $invoice_id,
                    'Lecture Tuition (General Education)',
                    $general_lecture_units,
                    $general_lecture_rate,
                    $general_lecture_total
                ]);
            }
        }
        
        // 3. Program Laboratory Tuition (if applicable)
        if (!empty($program_laboratory_courses) && $program_lab_rate_to_use > 0) {
            $program_lab_total = 0;
            $program_lab_units = 0;
            foreach ($program_laboratory_courses as $course) {
                $credits = (int)$course['credits'];
                $program_lab_units += $credits;
                $program_lab_total += $program_lab_rate_to_use * $credits;
            }
            if ($program_lab_total > 0) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                                      VALUES (?, 'Tuition', ?, ?, ?, ?)");
                $stmt->execute([
                    $invoice_id,
                    'Laboratory Tuition (Program Courses)',
                    $program_lab_units,
                    $program_lab_rate_to_use,
                    $program_lab_total
                ]);
            }
        }
        
        // 4. General Education Laboratory Tuition (if applicable)
        if (!empty($general_laboratory_courses) && $general_laboratory_rate > 0) {
            $general_lab_total = 0;
            $general_lab_units = 0;
            foreach ($general_laboratory_courses as $course) {
                $credits = (int)$course['credits'];
                $general_lab_units += $credits;
                $general_lab_total += $general_laboratory_rate * $credits;
            }
            if ($general_lab_total > 0) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                                      VALUES (?, 'Tuition', ?, ?, ?, ?)");
                $stmt->execute([
                    $invoice_id,
                    'Laboratory Tuition (General Education)',
                    $general_lab_units,
                    $general_laboratory_rate,
                    $general_lab_total
                ]);
            }
        }
        
        // 5. Other Fees (standard fees)
        foreach ($standard_fees as $fee) {
            if ((float)$fee['amount'] > 0) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                                      VALUES (?, 'Fees', ?, 1, ?, ?)");
                $stmt->execute([
                    $invoice_id,
                    $fee['fee_category'],
                    $fee['amount'],
                    $fee['amount']
                ]);
            }
        }
        
        return $invoice_id;
        
    } catch (Exception $e) {
        error_log("Error creating invoice for student $student_id: " . $e->getMessage());
        return false;
    }
}
