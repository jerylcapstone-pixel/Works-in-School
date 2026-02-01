<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$rate_id = isset($_POST['rate_id']) ? (int)$_POST['rate_id'] : 0;

if (!$rate_id) {
    header('Location: ' . BASE_URL . 'modules/billing/tuition_rates.php?error=Invalid rate ID');
    exit;
}

$pdo = getDBConnection();

try {
    // Check if rate exists
    $stmt = $pdo->prepare("SELECT rate_id FROM tuition_rates WHERE rate_id = ?");
    $stmt->execute([$rate_id]);
    if (!$stmt->fetch()) {
        header('Location: ' . BASE_URL . 'modules/billing/tuition_rates.php?error=Tuition rate not found');
        exit;
    }
    
    // Delete the rate
    $stmt = $pdo->prepare("DELETE FROM tuition_rates WHERE rate_id = ?");
    $stmt->execute([$rate_id]);
    
    header('Location: ' . BASE_URL . 'modules/billing/tuition_rates.php?success=' . urlencode('Tuition rate deleted successfully.'));
    exit;
    
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'modules/billing/tuition_rates.php?error=' . urlencode('Error deleting tuition rate: ' . $e->getMessage()));
    exit;
}

