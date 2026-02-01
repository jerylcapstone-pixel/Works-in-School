<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Generate Invoices';
$pdo = getDBConnection();

$error = '';
$success = '';
$preview_data = [];

$selected_department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$selected_program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;

// Get all programs for filtering
try {
    $status_column = $pdo->query("SHOW COLUMNS FROM programs LIKE 'status'");
    $program_status_exists = $status_column && $status_column->rowCount() > 0;
} catch (PDOException $e) {
    $program_status_exists = false;
}

// Get all programs with their department_id for filtering
$programs = [];
    $program_query = $program_status_exists
    ? "SELECT program_id, program_name, department_id FROM programs WHERE status = 'Active' ORDER BY program_name"
    : "SELECT program_id, program_name, department_id FROM programs ORDER BY program_name";
    $stmt = $pdo->query($program_query);
$all_programs = $stmt ? $stmt->fetchAll() : [];

// Filter programs based on selected department (for initial display)
if ($selected_department_id) {
    $programs = array_filter($all_programs, function($prog) use ($selected_department_id) {
        return $prog['department_id'] == $selected_department_id;
    });
    $programs = array_values($programs); // Re-index array
} else {
    $programs = $all_programs;
}

// Get all departments for filtering
try {
    $dept_status_column = $pdo->query("SHOW COLUMNS FROM departments LIKE 'status'");
    $department_status_exists = $dept_status_column && $dept_status_column->rowCount() > 0;
} catch (PDOException $e) {
    $department_status_exists = false;
}

if ($department_status_exists) {
    $departments_stmt = $pdo->query("SELECT department_id, department_name FROM departments WHERE status = 'Active' ORDER BY department_name");
} else {
    $departments_stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
}
$departments = $departments_stmt ? $departments_stmt->fetchAll() : [];

$tuition_rates_by_program = [];
$general_tuition_rate = null;
try {
    $lecture_col = $pdo->query("SHOW COLUMNS FROM tuition_rates LIKE 'lecture_rate'");
    if ($lecture_col && $lecture_col->rowCount() > 0) {
        $status_col = $pdo->query("SHOW COLUMNS FROM tuition_rates LIKE 'status'");
        $tuition_rate_status_exists = $status_col && $status_col->rowCount() > 0;
        $rate_sql = "SELECT program_id, lecture_rate, laboratory_rate, amount FROM tuition_rates";
        if ($tuition_rate_status_exists) {
            $rate_sql .= " WHERE status = 'Active'";
        }
        $rates_stmt = $pdo->query($rate_sql);
        $rates = $rates_stmt ? $rates_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rates as $rate) {
            $key = isset($rate['program_id']) ? (string)$rate['program_id'] : 'general';

            $lecture_rate = isset($rate['lecture_rate']) && $rate['lecture_rate'] !== null
                ? (float)$rate['lecture_rate']
                : null;
            if (($lecture_rate === null || $lecture_rate <= 0) && isset($rate['amount']) && $rate['amount'] !== null) {
                $lecture_rate = (float)$rate['amount'];
            }

            $laboratory_rate = array_key_exists('laboratory_rate', $rate) && $rate['laboratory_rate'] !== null
                ? (float)$rate['laboratory_rate']
                : null;
            // If laboratory_rate is NULL in database, keep it as null (don't use lecture_rate)
            // This will be displayed as 0.00 in the form

            $tuition_rates_by_program[$key] = [
                'lecture_rate' => $lecture_rate,
                'laboratory_rate' => $laboratory_rate,
            ];

            if ($key === 'general') {
                $general_tuition_rate = $tuition_rates_by_program[$key];
            }
        }
    }
} catch (PDOException $e) {
    $tuition_rates_by_program = [];
    $general_tuition_rate = null;
}

if ($selected_program_id && isset($tuition_rates_by_program[(string)$selected_program_id])) {
    $default_rate_source = $tuition_rates_by_program[(string)$selected_program_id];
} elseif ($general_tuition_rate) {
    $default_rate_source = $general_tuition_rate;
} else {
    $default_rate_source = null;
}

$standard_fees = [];
$standard_fees_total = 0.0;
try {
    $standard_fees_table = $pdo->query("SHOW TABLES LIKE 'standard_fees'");
    if ($standard_fees_table && $standard_fees_table->rowCount() > 0) {
        $standard_fees_stmt = $pdo->query("SELECT fee_category, amount FROM standard_fees WHERE is_active = 1 ORDER BY fee_category");
        $standard_fees = $standard_fees_stmt ? $standard_fees_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($standard_fees as $fee) {
            $standard_fees_total += isset($fee['amount']) ? (float)$fee['amount'] : 0;
        }
    }
} catch (PDOException $e) {
    $standard_fees = [];
    $standard_fees_total = 0.0;
}

if (isset($_POST['tuition_rate'])) {
    $tuition_rate_value = sanitizeOutput($_POST['tuition_rate']);
} elseif (!empty($default_rate_source['lecture_rate'])) {
    $tuition_rate_value = number_format($default_rate_source['lecture_rate'], 2, '.', '');
} else {
    $tuition_rate_value = '';
}

$selected_lab_rate_active = false;
$lab_rate_numeric = null;
if (is_array($default_rate_source) && array_key_exists('laboratory_rate', $default_rate_source) && $default_rate_source['laboratory_rate'] !== null) {
    $lab_rate_numeric = (float)$default_rate_source['laboratory_rate'];
}

// If laboratory_rate is NULL in database, display as 0.00 (don't use lecture_rate)
if ($lab_rate_numeric !== null) {
    $lab_rate_value = number_format(max(0, $lab_rate_numeric), 2, '.', '');
    $selected_lab_rate_active = $lab_rate_numeric > 0;
} else {
    // laboratory_rate is NULL in database - display as 0.00 (not lecture_rate)
    $lab_rate_value = '0.00';
    $selected_lab_rate_active = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    try {
        $pdo->beginTransaction();
        
        $semester = sanitizeInput($_POST['semester'] ?? '');
        $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
        $due_date = sanitizeInput($_POST['due_date'] ?? '');
        $program_id = $selected_program_id;
        $department_id = $selected_department_id;
        $manual_tuition_rate = isset($_POST['tuition_rate']) ? (float)$_POST['tuition_rate'] : 0;
        $semester_like = '%' . strtolower($semester) . '%';
        
        // Validation
        if (!$semester || !$academic_year || !$due_date) {
            throw new Exception('Please fill in all required fields.');
        }
        
        if ($manual_tuition_rate <= 0 && empty($tuition_rates_by_program)) {
            throw new Exception('Tuition rate must be greater than 0.');
        }
        
        // Build query to get enrolled students - using same logic as get_student_data.php
        // First get list of eligible students (without enrollment calculation)
        $query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.program_id,
                 p.program_name, p.department_id, d.department_name
                 FROM students s
                 LEFT JOIN programs p ON s.program_id = p.program_id
                 LEFT JOIN departments d ON p.department_id = d.department_id
                 WHERE s.status = 'Active'
                 AND NOT EXISTS (
                     SELECT 1 FROM invoices i 
                     WHERE i.student_id = s.student_id 
                     AND i.semester = ? 
                     AND i.academic_year = ?
                 )";
        
        $params = [$semester, $academic_year];
        
        if ($program_id) {
            $query .= " AND s.program_id = ?";
            $params[] = $program_id;
        }
        
        if ($department_id) {
            $query .= " AND p.department_id = ?";
            $params[] = $department_id;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $students = $stmt->fetchAll();
        
        if (empty($students)) {
            throw new Exception('No eligible students found for invoice generation. They may already have invoices for this semester.');
        }
        
        $generated_count = 0;
        $student_rate_cache = [];
 
        foreach ($students as $student) {
            $rate_cache_key = $student['program_id'] ? (string)$student['program_id'] : 'general';
            // Generate invoice number
            $invoice_count = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
            $invoice_number = 'INV-' . str_pad($invoice_count + $generated_count + 1, 6, '0', STR_PAD_LEFT);
            
            // Check if invoice number exists
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
            $check_stmt->execute([$invoice_number]);
            while ($check_stmt->fetchColumn() > 0) {
                $invoice_count++;
                $invoice_number = 'INV-' . str_pad($invoice_count + $generated_count + 1, 6, '0', STR_PAD_LEFT);
                $check_stmt->execute([$invoice_number]);
            }
            
            // Determine applicable rates - using same logic as get_student_data.php
            if (!isset($student_rate_cache[$student['student_id']])) {
            $lecture_rate_for_student = null;
            $lab_rate_for_student = null;
                $lab_rate_configured = false; // Track if lab rate was actually configured (not NULL)
                $rate_category = 'Program';
                
                // Check if lecture_rate and laboratory_rate columns exist
                $has_lecture_lab_rates = false;
                try {
                    $check_cols = $pdo->query("SHOW COLUMNS FROM tuition_rates LIKE 'lecture_rate'");
                    $has_lecture_lab_rates = $check_cols && $check_cols->rowCount() > 0;
                } catch (PDOException $e) {
                    $has_lecture_lab_rates = false;
                }
                
                // Try to get program-specific rate first
                if ($student['program_id'] && $has_lecture_lab_rates) {
                    $stmt = $pdo->prepare("SELECT lecture_rate, laboratory_rate, amount
                                          FROM tuition_rates 
                                          WHERE program_id = ? 
                                          AND status = 'Active'
                                          ORDER BY effective_date DESC 
                                          LIMIT 1");
                    $stmt->execute([$student['program_id']]);
                    $rate = $stmt->fetch();
                    
                    if ($rate) {
                        $lecture_rate_for_student = isset($rate['lecture_rate']) && $rate['lecture_rate'] !== null 
                            ? (float)$rate['lecture_rate'] 
                            : null;
                        
                        // If lecture_rate is null or 0, fallback to amount
                        if (($lecture_rate_for_student === null || $lecture_rate_for_student <= 0) && isset($rate['amount']) && $rate['amount'] > 0) {
                            $lecture_rate_for_student = (float)$rate['amount'];
                        }
                        
                        // Check if laboratory_rate exists and is not NULL in database
                        if (isset($rate['laboratory_rate']) && $rate['laboratory_rate'] !== null) {
                            $lab_rate_for_student = (float)$rate['laboratory_rate'];
                            $lab_rate_configured = true; // Rate was configured (even if 0)
                        } else {
                            // laboratory_rate is NULL in database - set to 0 (don't use lecture_rate)
                            $lab_rate_for_student = 0;
                            $lab_rate_configured = true; // Still add the item, but with 0 rate
                        }
                        
                        $rate_category = 'Program';
                    }
                } elseif ($student['program_id']) {
                    // Fallback to old schema (amount only) - in old schema, lab rate = lecture rate
                    $stmt = $pdo->prepare("SELECT amount
                                          FROM tuition_rates 
                                          WHERE program_id = ? 
                                          AND status = 'Active'
                                          ORDER BY effective_date DESC 
                                          LIMIT 1");
                    $stmt->execute([$student['program_id']]);
                    $rate = $stmt->fetch();
                    
                    if ($rate && isset($rate['amount'])) {
                        $lecture_rate_for_student = (float)$rate['amount'];
                        $lab_rate_for_student = $lecture_rate_for_student;
                        $lab_rate_configured = true; // In old schema, lab rate is same as lecture
                        $rate_category = 'Program';
            }
                }
                
                // If no program rate found, try general rate
                if ($lecture_rate_for_student === null && $has_lecture_lab_rates) {
                    $stmt = $pdo->query("SELECT lecture_rate, laboratory_rate, amount
                                        FROM tuition_rates 
                                        WHERE (program_id IS NULL OR program_id = 0)
                                        AND status = 'Active'
                                        ORDER BY effective_date DESC 
                                        LIMIT 1");
                    $general_rate = $stmt ? $stmt->fetch() : null;
                    
                    if ($general_rate) {
                        $lecture_rate_for_student = isset($general_rate['lecture_rate']) && $general_rate['lecture_rate'] !== null 
                            ? (float)$general_rate['lecture_rate'] 
                            : null;
                        
                        if (($lecture_rate_for_student === null || $lecture_rate_for_student <= 0) && isset($general_rate['amount']) && $general_rate['amount'] > 0) {
                            $lecture_rate_for_student = (float)$general_rate['amount'];
                        }
                        
                        // Check if laboratory_rate exists and is not NULL in database
                        if (!$lab_rate_configured && isset($general_rate['laboratory_rate']) && $general_rate['laboratory_rate'] !== null) {
                            $lab_rate_for_student = (float)$general_rate['laboratory_rate'];
                            $lab_rate_configured = true;
                        } elseif (!$lab_rate_configured) {
                            // laboratory_rate is NULL in general rate too - set to 0 (don't use lecture_rate)
                            $lab_rate_for_student = 0;
                            $lab_rate_configured = true; // Still add the item, but with 0 rate
                        }
                        
                        $rate_category = 'General';
                    }
                } elseif ($lecture_rate_for_student === null) {
                    // Fallback to old schema
                    $stmt = $pdo->query("SELECT amount
                                        FROM tuition_rates 
                                        WHERE (program_id IS NULL OR program_id = 0)
                                        AND status = 'Active'
                                        ORDER BY effective_date DESC 
                                        LIMIT 1");
                    $general_rate = $stmt ? $stmt->fetch() : null;
                    
                    if ($general_rate && isset($general_rate['amount'])) {
                        $lecture_rate_for_student = (float)$general_rate['amount'];
                        if (!$lab_rate_configured) {
                            $lab_rate_for_student = $lecture_rate_for_student;
                            $lab_rate_configured = true;
                        }
                        $rate_category = 'General';
                    }
                }
                
                // Final fallback to manual tuition rate if provided
                if ($lecture_rate_for_student === null || $lecture_rate_for_student <= 0) {
                    $lecture_rate_for_student = $manual_tuition_rate > 0 ? $manual_tuition_rate : 0;
                }
                
                // Ensure lab_rate is always set (NULL in database = 0, don't use lecture_rate)
            if ($lab_rate_for_student === null) {
                    $lab_rate_for_student = 0;
                    $lab_rate_configured = true; // Still add the item with 0 rate
            } elseif ($lab_rate_for_student < 0) {
                $lab_rate_for_student = 0;
            }

                // Cache the rates for this student
                $student_rate_cache[$student['student_id']] = [
                    'lecture_rate' => $lecture_rate_for_student,
                    'laboratory_rate' => $lab_rate_for_student,
                    'lab_rate_configured' => $lab_rate_configured, // Track if lab rate was configured
                    'rate_category' => $rate_category
                ];
            } else {
                // Use cached rates
                $cached_rates = $student_rate_cache[$student['student_id']];
                $lecture_rate_for_student = $cached_rates['lecture_rate'] ?? 0;
                // If laboratory_rate is null in cache, it means it was NULL in database, so set to 0
                $lab_rate_for_student = isset($cached_rates['laboratory_rate']) && $cached_rates['laboratory_rate'] !== null 
                    ? $cached_rates['laboratory_rate'] 
                    : 0;
                $rate_category = isset($cached_rates['rate_category']) ? $cached_rates['rate_category'] : 'Program';
            }

            // Calculate enrollment units for this student - using EXACT same logic as get_student_data.php
            $lecture_credits = 0;
            $lab_credits = 0;
            $has_lab_courses = false;
            
            // First, try exact match with semester and academic year
            if ($semester && $academic_year) {
                $enrollment_query = "SELECT 
                                    SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                                    SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits,
                                    COUNT(CASE WHEN c.course_type IN ('Lab', 'laboratory') OR LOWER(c.course_type) LIKE '%lab%' THEN 1 END) as lab_count
                                    FROM enrollments e
                                    INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                    INNER JOIN courses c ON cs.course_id = c.course_id
                                    WHERE e.student_id = ? 
                                    AND e.status = 'Enrolled'
                                    AND cs.academic_year = ?
                                    AND LOWER(cs.semester) LIKE ?";
                $enroll_stmt = $pdo->prepare($enrollment_query);
                $enroll_stmt->execute([$student['student_id'], $academic_year, $semester_like]);
                $enrollment = $enroll_stmt->fetch();
                
                $lecture_credits = (float)($enrollment['lecture_credits'] ?? 0);
                $lab_credits = (float)($enrollment['lab_credits'] ?? 0);
                $has_lab_courses = (int)($enrollment['lab_count'] ?? 0) > 0;
            }
            
            // If no units found with exact match, try without academic year constraint (in case format differs)
            if (($lecture_credits == 0 && $lab_credits == 0) && $semester) {
                $enrollment_query = "SELECT 
                                    SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                                    SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits,
                                    COUNT(CASE WHEN c.course_type IN ('Lab', 'laboratory') OR LOWER(c.course_type) LIKE '%lab%' THEN 1 END) as lab_count,
                                    cs.academic_year
                                    FROM enrollments e
                                    INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                    INNER JOIN courses c ON cs.course_id = c.course_id
                                    WHERE e.student_id = ? 
                                    AND e.status = 'Enrolled'
                                    AND cs.semester = ?
                                    GROUP BY cs.academic_year
                                    ORDER BY cs.academic_year DESC
                                    LIMIT 1";
                $enroll_stmt = $pdo->prepare($enrollment_query);
                $enroll_stmt->execute([$student['student_id'], $semester]);
                $enrollment = $enroll_stmt->fetch();
                
                if ($enrollment) {
                    $lecture_credits = (float)($enrollment['lecture_credits'] ?? 0);
                    $lab_credits = (float)($enrollment['lab_credits'] ?? 0);
                    $has_lab_courses = (int)($enrollment['lab_count'] ?? 0) > 0;
                }
            }
            
            // If still no units, try getting most recent enrollments (fallback)
            if ($lecture_credits == 0 && $lab_credits == 0) {
                // Get the most recent semester/academic year for this student
                $recent_query = "SELECT cs.semester, cs.academic_year
                                FROM enrollments e
                                INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                WHERE e.student_id = ?
                                AND e.status IN ('Enrolled', 'Completed')
                                ORDER BY cs.academic_year DESC, 
                                         CASE cs.semester 
                                             WHEN 'First' THEN 1
                                             WHEN '2nd' THEN 2
                                             WHEN 'Summer' THEN 3
                                             ELSE 4
                                         END DESC
                                LIMIT 1";
                $recent_stmt = $pdo->prepare($recent_query);
                $recent_stmt->execute([$student['student_id']]);
                $recent = $recent_stmt->fetch();
                
                if ($recent) {
                    // Use the most recent semester/academic year found
                    $enrollment_query = "SELECT 
                                        SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                                        SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits,
                                        COUNT(CASE WHEN c.course_type IN ('Lab', 'laboratory') OR LOWER(c.course_type) LIKE '%lab%' THEN 1 END) as lab_count
                                        FROM enrollments e
                                        INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                        INNER JOIN courses c ON cs.course_id = c.course_id
                                        WHERE e.student_id = ? 
                                        AND e.status IN ('Enrolled', 'Completed')
                                        AND cs.semester = ?
                                        AND cs.academic_year = ?";
                    $enroll_stmt = $pdo->prepare($enrollment_query);
                    $enroll_stmt->execute([$student['student_id'], $recent['semester'], $recent['academic_year']]);
                    $enrollment = $enroll_stmt->fetch();
                    
                    if ($enrollment) {
                        $lecture_credits = (float)($enrollment['lecture_credits'] ?? 0);
                        $lab_credits = (float)($enrollment['lab_credits'] ?? 0);
                        $has_lab_courses = (int)($enrollment['lab_count'] ?? 0) > 0;
                    }
                }
                
                // Last resort: get all enrollments regardless of semester/year
                if ($lecture_credits == 0 && $lab_credits == 0) {
                    $enrollment_query = "SELECT 
                                        SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                                        SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits,
                                        COUNT(CASE WHEN c.course_type IN ('Lab', 'laboratory') OR LOWER(c.course_type) LIKE '%lab%' THEN 1 END) as lab_count
                                        FROM enrollments e
                                        INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                        INNER JOIN courses c ON cs.course_id = c.course_id
                                        WHERE e.student_id = ? 
                                        AND e.status IN ('Enrolled', 'Completed')";
                    $enroll_stmt = $pdo->prepare($enrollment_query);
                    $enroll_stmt->execute([$student['student_id']]);
                    $enrollment = $enroll_stmt->fetch();
                    
                    if ($enrollment) {
                        $lecture_credits = (float)($enrollment['lecture_credits'] ?? 0);
                        $lab_credits = (float)($enrollment['lab_credits'] ?? 0);
                        $has_lab_courses = (int)($enrollment['lab_count'] ?? 0) > 0;
                    }
                }
            }
            
            $total_credits = $lecture_credits + $lab_credits;

            // Skip students with no enrolled units
            if ($total_credits == 0) {
                continue;
            }

            // Insert invoice placeholder (totals will be updated after adding items)
            $inv_stmt = $pdo->prepare("INSERT INTO invoices (student_id, invoice_number, semester, academic_year, due_date, 
                                     subtotal, discount, financial_aid_amount, total_amount, paid_amount, balance, status)
                                     VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 'Pending')");
            $inv_stmt->execute([
                $student['student_id'], $invoice_number, $semester, $academic_year, $due_date
            ]);
            $invoice_id = $pdo->lastInsertId();

            $standard_fee_total_student = 0;
            $tuition_amount = 0; // Initialize tuition amount
            
            // Add lecture tuition item (always add if student has lecture credits enrolled)
            if ($lecture_credits > 0) {
                // Ensure rate is set (use 0 if not configured, so it shows in invoice)
                $final_lecture_rate = $lecture_rate_for_student > 0 ? $lecture_rate_for_student : 0;
                $final_lecture_tuition = $lecture_credits * $final_lecture_rate;
                
                if ($final_lecture_rate > 0) {
                    $description = "Lecture Tuition - {$lecture_credits} unit(s) @ ₱" . number_format($final_lecture_rate, 2) . " per unit";
                    if ($rate_category === 'General') {
                        $description .= ' [General Rate]';
                    }
                } else {
                    $description = "Lecture Tuition - {$lecture_credits} unit(s) (No tuition rate configured)";
                }
                
                $item_stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                                           VALUES (?, 'Tuition', ?, ?, ?, ?)");
                $item_stmt->execute([
                    $invoice_id,
                    $description,
                    $lecture_credits,
                    $final_lecture_rate,
                    $final_lecture_tuition
                ]);
                
                // Add to tuition amount
                $tuition_amount += $final_lecture_tuition;
            }

            // Add laboratory tuition item if student has lab credits enrolled
            // If lab_rate is NULL in database, it will be 0 (not lecture_rate)
            if ($lab_credits > 0) {
                // Lab rate: if NULL in database, it's already set to 0 (not lecture_rate)
                $final_lab_rate = $lab_rate_for_student !== null ? (float)$lab_rate_for_student : 0;
                $final_lab_tuition = $lab_credits * $final_lab_rate;
                
                $description = "Laboratory Tuition - {$lab_credits} unit(s) @ ₱" . number_format($final_lab_rate, 2) . " per unit";
                if ($rate_category === 'General' && $final_lab_rate > 0) {
                    $description .= ' [General Rate]';
                }
                
                $item_stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                                           VALUES (?, 'Tuition', ?, ?, ?, ?)");
                $item_stmt->execute([
                    $invoice_id,
                    $description,
                    $lab_credits,
                    $final_lab_rate,
                    $final_lab_tuition
                ]);
                
                // Add to tuition amount
                $tuition_amount += $final_lab_tuition;
            }
            
            // Add standard fees if any - using same logic as invoice_add.php
            if (!empty($standard_fees)) {
                foreach ($standard_fees as $fee) {
                    $fee_category = strtolower($fee['fee_category']);
                    $amount = isset($fee['amount']) ? (float)$fee['amount'] : 0;
                    
                    // Skip if amount is 0
                    if ($amount <= 0) {
                        continue;
                    }
                    
                    // Only add Laboratory Fee if student has lab courses (same as invoice_add.php)
                    if ($fee_category === 'laboratory fee' && !$has_lab_courses) {
                        continue;
                    }
                    
                    $standard_fee_total_student += $amount;
                    $item_stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                                               VALUES (?, 'Fee', ?, 1, ?, ?)");
                    $item_stmt->execute([
                        $invoice_id,
                        $fee['fee_category'],
                        $amount,
                        $amount
                    ]);
                }
            }
            
            $subtotal = $tuition_amount + $standard_fee_total_student;
            $total_amount = $subtotal;
            $balance = $total_amount;
            
            // Update invoice totals
            $update_stmt = $pdo->prepare("UPDATE invoices SET subtotal = ?, total_amount = ?, balance = ? WHERE invoice_id = ?");
            $update_stmt->execute([$subtotal, $total_amount, $balance, $invoice_id]);

            $generated_count++;
        }
        unset($student_rate_cache);
        
        $pdo->commit();
        $success = "Successfully generated {$generated_count} invoice(s) for {$semester} {$academic_year}.";
        header('Location: ' . BASE_URL . 'modules/billing/index.php?success=' . urlencode($success));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Preview mode - show what would be generated
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview'])) {
    $semester = sanitizeInput($_POST['semester'] ?? '');
    $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
    $program_id = $selected_program_id;
    $department_id = $selected_department_id;
    $tuition_rate = (float)($_POST['tuition_rate'] ?? 0);
    $additional_fees = $standard_fees_total;
    $semester_like = '%' . strtolower($semester) . '%';
    
    if ($semester && $academic_year) {
        $query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.program_id,
                 p.program_name, d.department_name,
                 SUM(CASE WHEN cs.academic_year = ? AND e.status = 'Enrolled' AND LOWER(cs.semester) LIKE ? AND COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                 SUM(CASE WHEN cs.academic_year = ? AND e.status = 'Enrolled' AND LOWER(cs.semester) LIKE ? AND COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits
                 FROM students s
                 LEFT JOIN programs p ON s.program_id = p.program_id
                 LEFT JOIN departments d ON p.department_id = d.department_id
                 LEFT JOIN enrollments e ON s.student_id = e.student_id
                 LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                 LEFT JOIN courses c ON cs.course_id = c.course_id
                 WHERE s.status = 'Active'
                 AND NOT EXISTS (
                     SELECT 1 FROM invoices i 
                     WHERE i.student_id = s.student_id 
                     AND i.semester = ? 
                     AND i.academic_year = ?
                 )";
        
        $params = [$academic_year, $semester_like, $academic_year, $semester_like, $semester, $academic_year];
        
        if ($program_id) {
            $query .= " AND s.program_id = ?";
            $params[] = $program_id;
        }
        
        if ($department_id) {
            $query .= " AND p.department_id = ?";
            $params[] = $department_id;
        }
        
        $query .= " GROUP BY s.student_id, s.student_number, s.first_name, s.last_name, s.program_id, p.program_name, d.department_name
                   ORDER BY s.student_number";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $preview_data = $stmt->fetchAll();
        
        // Calculate totals for preview
        foreach ($preview_data as &$student) {
            $lecture_credits = (float)($student['lecture_credits'] ?? 0);
            $lab_credits = (float)($student['lab_credits'] ?? 0);
            $total_credits = $lecture_credits + $lab_credits;

            $program_key = $student['program_id'] ? (string)$student['program_id'] : 'general';
            $rate_source = $tuition_rates_by_program[$program_key] ?? $general_tuition_rate ?? null;

            $lecture_rate_for_student = null;
            $lab_rate_for_student = null;

            if ($rate_source && isset($rate_source['lecture_rate']) && $rate_source['lecture_rate'] !== null) {
                $lecture_rate_for_student = (float)$rate_source['lecture_rate'];
            }
            if (($lecture_rate_for_student === null || $lecture_rate_for_student <= 0) && $general_tuition_rate && isset($general_tuition_rate['lecture_rate']) && $general_tuition_rate['lecture_rate'] !== null) {
                $lecture_rate_for_student = (float)$general_tuition_rate['lecture_rate'];
            }
            if ($lecture_rate_for_student === null || $lecture_rate_for_student <= 0) {
                $lecture_rate_for_student = $tuition_rate > 0 ? $tuition_rate : 0;
            }

            if ($rate_source && array_key_exists('laboratory_rate', $rate_source) && $rate_source['laboratory_rate'] !== null) {
                $lab_rate_for_student = (float)$rate_source['laboratory_rate'];
            } elseif ($general_tuition_rate && array_key_exists('laboratory_rate', $general_tuition_rate) && $general_tuition_rate['laboratory_rate'] !== null) {
                $lab_rate_for_student = (float)$general_tuition_rate['laboratory_rate'];
            }

            // If laboratory_rate is NULL in database, set to 0 (don't use lecture_rate)
            if ($lab_rate_for_student === null) {
                $lab_rate_for_student = 0;
            } elseif ($lab_rate_for_student < 0) {
                $lab_rate_for_student = 0;
            }

            $lecture_tuition = $lecture_credits * $lecture_rate_for_student;
            $lab_tuition = $lab_credits * $lab_rate_for_student;
            $tuition_amount = $lecture_tuition + $lab_tuition;

            $student['lecture_credits'] = $lecture_credits;
            $student['lab_credits'] = $lab_credits;
            $student['lecture_rate'] = $lecture_rate_for_student;
            $student['lab_rate'] = $lab_rate_for_student;
            $student['tuition_amount'] = $tuition_amount;
            $standard_fee_total_student = 0;
            $fee_parts = [];
            if (!empty($standard_fees)) {
                foreach ($standard_fees as $fee) {
                    $amount = isset($fee['amount']) ? (float)$fee['amount'] : 0;
                    if ($amount <= 0) {
                        continue;
                    }
                    if (strcasecmp($fee['fee_category'], 'Laboratory Fee') === 0 && $student['lab_credits'] <= 0) {
                        continue;
                    }
                    $standard_fee_total_student += $amount;
                    $fee_parts[] = "{$fee['fee_category']}: ₱" . number_format($amount, 2);
                }
            }
            $student['total_amount'] = $tuition_amount + $standard_fee_total_student;
            $student['tuition_breakdown'] = '₱' . number_format($tuition_amount, 2);

            $parts = [];
            if ($lecture_credits > 0) {
                $parts[] = "Lecture: {$lecture_credits} cr × ₱" . number_format($lecture_rate_for_student, 2);
            }
            if ($lab_credits > 0) {
                $parts[] = "Lab: {$lab_credits} cr × ₱" . number_format($lab_rate_for_student, 2);
            }
            if (!empty($parts)) {
                $student['tuition_breakdown'] .= '<br><small>' . implode(' | ', $parts) . '</small>';
            }

            $student['standard_fee_breakdown'] = $standard_fee_total_student > 0
                ? '<small>' . implode(' | ', $fee_parts) . '</small>'
                : '<small>No standard fees configured</small>';
            $student['standard_fee_total'] = $standard_fee_total_student;
        }
        unset($student);
    }
}

$current_year = date('Y');
$next_year = date('Y', strtotime('+1 year'));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Generate Invoices</h1>
        <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="form-card">
        <h2 style="margin-bottom: 1rem;">Bulk Invoice Generation</h2>
        <p style="color: #64748b; margin-bottom: 1.5rem; font-size: 0.95rem;">
            This will automatically generate invoices for all enrolled students based on their course credits. 
            Students who already have invoices for the selected semester will be skipped.
        </p>
        
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="semester">Semester <span class="text-danger">*</span></label>
                    <select id="semester" name="semester" class="form-control" required>
                        <option value="">-- Select Semester --</option>
                        <option value="First">First</option>
                        <option value="2nd">2nd</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="academic_year">Academic Year <span class="text-danger">*</span></label>
                    <input type="text" id="academic_year" name="academic_year" class="form-control" 
                           value="<?php echo sanitizeOutput($current_year . '-' . substr($next_year, -2)); ?>" 
                           placeholder="e.g., 2024-25" required>
                </div>
                
                <div class="form-group">
                    <label for="due_date">Due Date <span class="text-danger">*</span></label>
                    <input type="date" id="due_date" name="due_date" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="department_id">Department (Optional)</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo ($selected_department_id == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="program_id">Program (Optional)</label>
                    <select id="program_id" name="program_id" class="form-control">
                        <option value="">All Programs</option>
                        <?php foreach ($all_programs as $prog): ?>
                            <option value="<?php echo $prog['program_id']; ?>" 
                                    data-department-id="<?php echo $prog['department_id']; ?>"
                                    <?php echo ($selected_program_id == $prog['program_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($prog['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Lecture Rate per Unit</label>
                    <input type="hidden" id="tuition_rate" name="tuition_rate" value="<?php echo sanitizeOutput($tuition_rate_value); ?>" data-autofill="<?php echo $tuition_rate_value === '' ? 'true' : 'false'; ?>">
                    <input type="number" id="tuition_rate_display" class="form-control" value="<?php echo sanitizeOutput($tuition_rate_value); ?>" readonly>
                    <small class="text-muted">Auto-filled from Tuition Rate Setup</small>
                </div>
                <div class="form-group">
                    <label>Laboratory Rate per Unit</label>
                    <input type="number" id="lab_rate_display" class="form-control" value="<?php echo sanitizeOutput($lab_rate_value); ?>" readonly>
                    <small class="text-muted">Auto-filled from Tuition Rate Setup (shows 0.00 if not set in database)</small>
                </div>
            </div>
            
            <?php if (!empty($standard_fees)): ?>
            <div class="card" style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.1rem; margin-bottom: 0.75rem;">Standard Fees (Read-only)</h3>
                <p class="text-muted" style="font-size: 0.875rem; margin-bottom: 1rem;">These fees will be added to every invoice in addition to tuition.</p>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fee Category</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standard_fees as $fee): ?>
                            <?php 
                                $fee_category = $fee['fee_category'];
                                $is_lab_fee = strcasecmp($fee_category, 'Laboratory Fee') === 0;
                                $initial_display = $is_lab_fee && !$selected_lab_rate_active ? 'style="display: none;"' : '';
                            ?>
                            <tr class="standard-fee-row" data-fee-category="<?php echo htmlspecialchars($fee_category); ?>" <?php echo $initial_display; ?> >
                                <td><?php echo sanitizeOutput($fee['fee_category']); ?></td>
                                <td class="text-right">₱<?php echo number_format($fee['amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="background-color: #f5f5f5; font-weight: bold;">
                                <td style="text-align: right;">Total Standard Fees:</td>
                                <td class="text-right">₱<?php echo number_format($standard_fees_total, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
                No standard fees are currently configured. You can manage fees under Billing → Manage Standard Fees.
            </div>
            <?php endif; ?>
            
            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" name="preview" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> Preview
                </button>
                <button type="submit" name="generate" class="btn btn-primary" onclick="return confirm('Are you sure you want to generate invoices? This action cannot be undone.');">
                    <i class="fas fa-file-invoice-dollar"></i> Generate Invoices
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($preview_data)): ?>
        <div class="table-card">
            <div style="padding: 1.5rem 1.5rem 0;">
                <h2 style="margin: 0; font-size: 1.25rem;">Preview (<?php echo count($preview_data); ?> students)</h2>
                <p class="text-muted" style="margin-top: 0.5rem; font-size: 0.875rem;">These students will receive invoices:</p>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student Number</th>
                            <th>Name</th>
                            <th>Program</th>
                            <th>Department</th>
                            <th>Lecture Credits</th>
                            <th>Lab Credits</th>
                            <th>Total Credits</th>
                            <th>Tuition</th>
                            <th>Standard Fees</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        foreach ($preview_data as $student): 
                            $grand_total += $student['total_amount'];
                        ?>
                            <tr>
                                <td><strong><?php echo sanitizeOutput($student['student_number']); ?></strong></td>
                                <td><?php echo sanitizeOutput($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><?php echo sanitizeOutput($student['program_name'] ?? 'N/A'); ?></td>
                                <td><?php echo sanitizeOutput($student['department_name'] ?? 'N/A'); ?></td>
                                <td><?php echo $student['lecture_credits']; ?></td>
                                <td><?php echo $student['lab_credits']; ?></td>
                                <td><?php echo $student['lecture_credits'] + $student['lab_credits']; ?></td>
                                <td><?php echo $student['tuition_breakdown']; ?></td>
                                <td>
                                    ₱<?php echo number_format($student['standard_fee_total'], 2); ?>
                                    <br><?php echo $student['standard_fee_breakdown']; ?>
                                </td>
                                <td><strong>₱<?php echo number_format($student['total_amount'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background-color: #f5f5f5; font-weight: bold;">
                            <td colspan="9" style="text-align: right;">Total:</td>
                            <td>₱<?php echo number_format($grand_total, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const programRates = <?php echo json_encode($tuition_rates_by_program, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const generalRate = <?php echo json_encode($general_tuition_rate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const programSelect = document.getElementById('program_id');
    const departmentSelect = document.getElementById('department_id');
    const tuitionInput = document.getElementById('tuition_rate');
    const tuitionDisplay = document.getElementById('tuition_rate_display');
    const labRateDisplay = document.getElementById('lab_rate_display');
    const labFeeRow = document.querySelector('.standard-fee-row[data-fee-category="Laboratory Fee"]');

    // Store all programs for filtering
    let allProgramsData = [];
    
    // Filter programs based on selected department
    function filterProgramsByDepartment() {
        if (!programSelect || !departmentSelect) {
            return;
        }
        
        const selectedDepartmentId = departmentSelect.value;
        const currentProgramValue = programSelect.value;
        
        // Store all programs on first call
        if (allProgramsData.length === 0) {
            const programOptions = programSelect.querySelectorAll('option');
            programOptions.forEach(function(option) {
                if (option.value !== '') {
                    const deptId = option.getAttribute('data-department-id') || '';
                    allProgramsData.push({
                        value: option.value,
                        text: option.textContent.trim(),
                        departmentId: deptId
                    });
                }
            });
        }
        
        // Clear and rebuild program options
        programSelect.innerHTML = '<option value="">All Programs</option>';
        
        // Add filtered programs
        allProgramsData.forEach(function(program) {
            if (!selectedDepartmentId || selectedDepartmentId === '' || program.departmentId === selectedDepartmentId) {
                const option = document.createElement('option');
                option.value = program.value;
                option.textContent = program.text;
                option.setAttribute('data-department-id', program.departmentId);
                
                // Restore selection if it matches
                if (program.value === currentProgramValue) {
                    option.selected = true;
                }
                
                programSelect.appendChild(option);
            }
        });
        
        // If current selected program was removed, clear selection
        if (currentProgramValue && !programSelect.querySelector(`option[value="${currentProgramValue}"]`)) {
            programSelect.value = '';
            applyTuitionRate(true);
        }
    }

    function getRateForProgram(programId) {
        if (programId && programRates && programRates[programId] && programRates[programId].lecture_rate !== null) {
            return parseFloat(programRates[programId].lecture_rate);
        }
        if (generalRate && generalRate.lecture_rate !== null) {
            return parseFloat(generalRate.lecture_rate);
        }
        return null;
    }

    function getLabRateForProgram(programId) {
        let labRate = null;
        // Check program-specific laboratory_rate
        if (programId && programRates && programRates[programId] && programRates[programId].laboratory_rate !== null) {
            labRate = parseFloat(programRates[programId].laboratory_rate);
        }
        // Check general laboratory_rate
        if ((labRate === null || Number.isNaN(labRate)) && generalRate && generalRate.laboratory_rate !== null) {
            labRate = parseFloat(generalRate.laboratory_rate);
        }
        // If laboratory_rate is NULL in database, return 0 (don't use lecture_rate)
        return labRate !== null && !Number.isNaN(labRate) ? Math.max(0, labRate) : 0;
    }

    function applyTuitionRate(force = false) {
        if (!tuitionInput) {
            return;
        }
        const currentValue = tuitionInput.value.trim();
        const selectedProgram = programSelect ? programSelect.value : '';
        const rate = getRateForProgram(selectedProgram);
        const shouldAutoFill = tuitionInput.dataset.autofill === 'true' || currentValue === '' || force;

        if (rate !== null && shouldAutoFill) {
            tuitionInput.value = rate.toFixed(2);
            tuitionInput.dataset.autofill = 'true';
            if (tuitionDisplay) {
                tuitionDisplay.value = rate.toFixed(2);
            }
        }

        const labRate = getLabRateForProgram(selectedProgram);
        if (labRateDisplay) {
            labRateDisplay.value = labRate.toFixed(2);
        }
        if (labFeeRow) {
            labFeeRow.style.display = labRate > 0 ? '' : 'none';
        }

        if (rate === null && shouldAutoFill) {
            tuitionInput.value = '';
            if (tuitionDisplay) {
                tuitionDisplay.value = '';
            }
            if (labRateDisplay) {
                labRateDisplay.value = '0.00';
            }
            if (labFeeRow) {
                labFeeRow.style.display = 'none';
            }
        }
    }

    if (tuitionInput) {
        if (tuitionInput.value === '') {
            applyTuitionRate(true);
        }
    }

    if (programSelect) {
        programSelect.addEventListener('change', function() {
            applyTuitionRate(true);
        });
    }

    if (departmentSelect) {
        departmentSelect.addEventListener('change', function() {
            filterProgramsByDepartment();
            // Clear program selection when department changes
            if (programSelect) {
                programSelect.value = '';
                applyTuitionRate(true);
            }
        });
    }

    // Ensure correct initial state
    filterProgramsByDepartment();
    applyTuitionRate(false);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

