<?php
/**
 * Document Requests Management
 * Admin view to manage all document requests
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'Document Requests';
$pdo = getDBConnection();

// Check if document requests feature is enabled
if (!isDocumentRequestsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Document Request feature is currently disabled');
    exit;
}

$error = '';
$success = '';

// Check if document_requests table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'document_requests'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    $error = 'The document_requests table does not exist. Please run the migration: database/migration_document_requests.sql';
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$document_type_filter = isset($_GET['document_type']) ? sanitizeInput($_GET['document_type']) : '';

// Build query with filters
$query = "SELECT dr.*, s.student_number, s.first_name, s.last_name,
          u.username as requested_by_username, u2.username as approved_by_username, 
          u3.username as rejected_by_username, u4.username as uploaded_by_username
          FROM document_requests dr
          INNER JOIN students s ON dr.student_id = s.student_id
          LEFT JOIN users u ON dr.requested_by = u.user_id
          LEFT JOIN users u2 ON dr.approved_by = u2.user_id
          LEFT JOIN users u3 ON dr.rejected_by = u3.user_id
          LEFT JOIN users u4 ON dr.uploaded_by = u4.user_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $query .= " AND dr.status = ?";
    $params[] = $status_filter;
}

if (!empty($document_type_filter)) {
    $query .= " AND dr.document_type = ?";
    $params[] = $document_type_filter;
}

$query .= " ORDER BY dr.request_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => count($requests),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'ready' => 0
];
foreach ($requests as $req) {
    if ($req['status'] === 'Pending') $stats['pending']++;
    if ($req['status'] === 'Approved') $stats['approved']++;
    if ($req['status'] === 'Rejected') $stats['rejected']++;
    if ($req['status'] === 'Ready for Download') $stats['ready']++;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> Document Requests</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.1rem; margin-bottom: 1rem;">Statistics</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <span style="font-size: 0.875rem; color: #64748b; font-weight: 600;">Total Requests</span>
                <span style="font-size: 1.5rem; font-weight: 700; color: #1e293b;"><?php echo $stats['total']; ?></span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <span style="font-size: 0.875rem; color: #64748b; font-weight: 600;">Pending</span>
                <span class="badge badge-warning" style="display: inline-block; width: fit-content;"><?php echo $stats['pending']; ?></span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <span style="font-size: 0.875rem; color: #64748b; font-weight: 600;">Approved</span>
                <span class="badge badge-info" style="display: inline-block; width: fit-content;"><?php echo $stats['approved']; ?></span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <span style="font-size: 0.875rem; color: #64748b; font-weight: 600;">Ready for Download</span>
                <span class="badge badge-success" style="display: inline-block; width: fit-content;"><?php echo $stats['ready']; ?></span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <span style="font-size: 0.875rem; color: #64748b; font-weight: 600;">Rejected</span>
                <span class="badge badge-danger" style="display: inline-block; width: fit-content;"><?php echo $stats['rejected']; ?></span>
            </div>
        </div>
    </div>

    <div class="filters-card">
        <h2>Filters</h2>
        <form method="GET" action="">
            <div class="form-group">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" class="form-control" 
                       placeholder="Search by student number or name..." 
                       value="<?php echo sanitizeOutput($search); ?>">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="Ready for Download" <?php echo $status_filter === 'Ready for Download' ? 'selected' : ''; ?>>Ready for Download</option>
                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="form-group">
                <label for="document_type">Document Type</label>
                <select id="document_type" name="document_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Transcript" <?php echo $document_type_filter === 'Transcript' ? 'selected' : ''; ?>>Transcript</option>
                    <option value="Certificate of Enrollment" <?php echo $document_type_filter === 'Certificate of Enrollment' ? 'selected' : ''; ?>>Certificate of Enrollment</option>
                    <option value="Certificate of Good Moral Character" <?php echo $document_type_filter === 'Certificate of Good Moral Character' ? 'selected' : ''; ?>>Certificate of Good Moral Character</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="<?php echo BASE_URL; ?>modules/documents/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div style="padding: 1.5rem 1.5rem 0;">
            <h2 style="margin: 0; font-size: 1.25rem;">Document Requests (<?php echo count($requests); ?>)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Document Type</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="5" class="text-center">No document requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): 
                            $status_badge = [
                                'Pending' => 'warning',
                                'Approved' => 'info',
                                'Rejected' => 'danger',
                                'Ready for Download' => 'success',
                                'Completed' => 'secondary'
                            ];
                            $badge = $status_badge[$request['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitizeOutput($request['student_number']); ?></strong><br>
                                <small><?php echo sanitizeOutput($request['first_name'] . ' ' . $request['last_name']); ?></small>
                            </td>
                            <td><?php echo sanitizeOutput($request['document_type']); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($request['request_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $badge; ?>">
                                    <?php echo sanitizeOutput($request['status']); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/documents/view.php?id=<?php echo $request['request_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

