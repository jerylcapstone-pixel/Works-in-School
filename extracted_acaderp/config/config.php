<?php
/**
 * Application Configuration File
 * Contains all application-wide constants, helper functions, and configurations
 */

require_once __DIR__ . '/database.php';

// Application constants
define('APP_NAME', 'AcadeERP');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://acaderp.orbit-node.com/');

// Email configuration
define('EMAIL_FROM_ADDRESS', 'geennanaodanozar@gmail.com');
define('EMAIL_FROM_NAME', 'Academic Management System');
define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_USERNAME', 'geennanaodanozar@gmail.com');
define('EMAIL_SMTP_PASSWORD', 'xmrq fgpz syrm avza');
define('EMAIL_SMTP_ENCRYPTION', 'tls');
define('USE_SMTP', true);

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_FACULTY', 'faculty');
define('ROLE_STUDENT', 'student');

// Password requirements
define('PASSWORD_MIN_LENGTH', 6);

// Session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security functions
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Authentication functions
function requireAuth($allowed_roles = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
    
    if ($allowed_roles !== null) {
        if (is_array($allowed_roles)) {
            if (!in_array($_SESSION['user_role'], $allowed_roles)) {
                header('Location: ' . BASE_URL . 'dashboard/index.php?error=Access denied');
                exit;
            }
        } else {
            if ($_SESSION['user_role'] !== $allowed_roles) {
                header('Location: ' . BASE_URL . 'dashboard/index.php?error=Access denied');
                exit;
            }
        }
    }
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Email sending function
function sendEmail($to, $subject, $message) {
    if (USE_SMTP && !empty(EMAIL_SMTP_HOST) && !empty(EMAIL_SMTP_USERNAME) && !empty(EMAIL_SMTP_PASSWORD)) {
        return sendEmailSMTP($to, $subject, $message);
    } else {
        // Fallback to PHP mail()
        $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM_ADDRESS . ">\r\n";
        $headers .= "Reply-To: " . EMAIL_FROM_ADDRESS . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
}

// SMTP email sending function
function sendEmailSMTP($to, $subject, $message) {
    $host = EMAIL_SMTP_HOST;
    $port = EMAIL_SMTP_PORT;
    $username = EMAIL_SMTP_USERNAME;
    $password = EMAIL_SMTP_PASSWORD;
    $encryption = EMAIL_SMTP_ENCRYPTION;
    
    // Create socket connection
    if ($encryption === 'ssl' || $port == 465) {
        $socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);
    } else {
        $socket = @fsockopen($host, $port, $errno, $errstr, 30);
    }
    
    if (!$socket) {
        error_log("SMTP Connection Error: $errstr ($errno)");
        return false;
    }
    
    // Read initial response
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '220') {
        error_log("SMTP Initial Response Error: $response");
        fclose($socket);
        return false;
    }
    
    // Send EHLO
    fputs($socket, "EHLO $host\r\n");
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    
    // Enable TLS if needed
    if (($encryption === 'tls' || $encryption === 'TLS') && $port == 587) {
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '220') {
            error_log("SMTP STARTTLS Error: $response");
            fclose($socket);
            return false;
        }
        
        // Enable crypto
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!stream_socket_enable_crypto($socket, true, $crypto_method)) {
            error_log("SMTP TLS Enable Error");
            fclose($socket);
            return false;
        }
        
        // Send EHLO again after TLS
        fputs($socket, "EHLO $host\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
    }
    
    // Authenticate
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '334') {
        error_log("SMTP AUTH Error: $response");
        fclose($socket);
        return false;
    }
    
    fputs($socket, base64_encode($username) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '334') {
        error_log("SMTP Username Error: $response");
        fclose($socket);
        return false;
    }
    
    fputs($socket, base64_encode($password) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '235') {
        error_log("SMTP Password Error: $response");
        fclose($socket);
        return false;
    }
    
    // Send email
    $from_address = EMAIL_FROM_ADDRESS;
    $from_name = EMAIL_FROM_NAME;
    
    fputs($socket, "MAIL FROM: <$from_address>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '250') {
        error_log("SMTP MAIL FROM Error: $response");
        fclose($socket);
        return false;
    }
    
    fputs($socket, "RCPT TO: <$to>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '250') {
        error_log("SMTP RCPT TO Error: $response");
        fclose($socket);
        return false;
    }
    
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '354') {
        error_log("SMTP DATA Error: $response");
        fclose($socket);
        return false;
    }
    
    $email_headers = "From: $from_name <$from_address>\r\n";
    $email_headers .= "To: <$to>\r\n";
    $email_headers .= "Subject: $subject\r\n";
    $email_headers .= "MIME-Version: 1.0\r\n";
    $email_headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $email_headers .= "\r\n";
    
    fputs($socket, $email_headers . $message . "\r\n.\r\n");
    $response = fgets($socket, 515);
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return substr($response, 0, 3) === '250';
}

// Format schedule display (handles JSON schedule data or comma-separated days)
function formatScheduleDisplay($schedule_day, $start_time, $end_time) {
    if (empty($schedule_day)) {
        return 'TBA';
    }
    
    // Try to decode as JSON first (new format with times per day)
    $schedule_data = json_decode($schedule_day, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($schedule_data)) {
        // New format: JSON with times per day
        $schedule_parts = [];
        foreach ($schedule_data as $day => $times) {
            $start = !empty($times['start']) ? date('g:i A', strtotime($times['start'])) : '';
            $end = !empty($times['end']) ? date('g:i A', strtotime($times['end'])) : '';
            if ($start && $end) {
                $schedule_parts[] = $day . ' ' . $start . ' - ' . $end;
            } else {
                $schedule_parts[] = $day;
            }
        }
        return implode('<br>', $schedule_parts);
    } else {
        // Old format: comma-separated days with single time
        $days = explode(',', $schedule_day);
        $days = array_map('trim', $days);
        
        // Format days
        $formatted_days = implode(', ', $days);
        
        // Format time
        $time_str = '';
        if (!empty($start_time) && !empty($end_time)) {
            $start = date('g:i A', strtotime($start_time));
            $end = date('g:i A', strtotime($end_time));
            $time_str = ' ' . $start . ' - ' . $end;
        }
        
        return $formatted_days . $time_str;
    }
}
?>
