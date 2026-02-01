<?php
/**
 * Invoice Delete Handler
 * Handles invoice deletion with validation
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$pdo = getDBConnection();
$invoice_id = (int)($_GET['id'] ?? 0);

if ($invoice_id > 0) {
    try {
        // Check if invoice exists
        $stmt = $pdo->prepare("SELECT invoice_id, invoice_number, student_id, total_amount, status 
                              FROM invoices WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Invoice not found.');
            exit;
        }
        
        // Check if invoice has payments (prevent deletion if payments exist)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $payment_count = $stmt->fetchColumn();
        
        if ($payment_count > 0) {
            header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Cannot delete invoice with existing payments. Please delete payments first or cancel the invoice instead.');
            exit;
        }
        
        // Check if invoice is already cancelled
        if ($invoice['status'] === 'Cancelled') {
            // Allow deletion of cancelled invoices
        } elseif ($invoice['status'] === 'Paid') {
            header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Cannot delete a paid invoice. Please cancel it instead.');
            exit;
        }
        
        // Delete invoice items first (though CASCADE should handle this)
        $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        
        // Delete invoice
        $stmt = $pdo->prepare("DELETE FROM invoices WHERE invoice_id = ?");
        if ($stmt->execute([$invoice_id])) {
            header('Location: ' . BASE_URL . 'modules/billing/index.php?success=Invoice ' . urlencode($invoice['invoice_number']) . ' deleted successfully.');
            exit;
        } else {
            header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Failed to delete invoice.');
            exit;
        }
        
    } catch (PDOException $e) {
        error_log("Error deleting invoice: " . $e->getMessage());
        header('Location: ' . BASE_URL . 'modules/billing/index.php?error=An error occurred while deleting the invoice.');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Invalid invoice ID.');
    exit;
}
?>

