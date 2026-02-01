<?php
/**
 * View Library Material
 * Display material details
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'View Library Material';
$pdo = getDBConnection();
$material_id = (int)($_GET['id'] ?? 0);

if (!$material_id) {
    header('Location: ' . BASE_URL . 'modules/library/index.php?error=Invalid material ID');
    exit;
}

// Get material details
$stmt = $pdo->prepare("SELECT lm.*, lc.category_name, d.department_name, p.program_name, u.username as uploaded_by_username
                      FROM library_materials lm
                      LEFT JOIN library_categories lc ON lm.category_id = lc.category_id
                      LEFT JOIN departments d ON lm.department_id = d.department_id
                      LEFT JOIN programs p ON lm.program_id = p.program_id
                      LEFT JOIN users u ON lm.uploaded_by = u.user_id
                      WHERE lm.material_id = ?");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: ' . BASE_URL . 'modules/library/index.php?error=Material not found');
    exit;
}

// Format file size
$file_size = (int)$material['file_size'];
$size_formatted = '';
if ($file_size < 1024) {
    $size_formatted = $file_size . ' B';
} elseif ($file_size < 1048576) {
    $size_formatted = number_format($file_size / 1024, 2) . ' KB';
} else {
    $size_formatted = number_format($file_size / 1048576, 2) . ' MB';
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-book-open"></i> Library Material Details</h1>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>modules/library/edit.php?id=<?php echo $material_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?php echo BASE_URL; ?>modules/library/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Library
            </a>
        </div>
    </div>

    <div class="detail-cards">
        <!-- Material Header Card -->
        <div class="detail-card" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
            <h3 style="color: white; border-bottom-color: rgba(255, 255, 255, 0.3);">
                <i class="fas fa-book"></i> <?php echo sanitizeOutput($material['title']); ?>
            </h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Status</label>
                    <span style="color: white; font-size: 1.1rem;">
                        <span class="badge badge-<?php echo $material['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $material['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Category</label>
                    <span style="color: white; font-size: 1rem;"><?php echo sanitizeOutput($material['category_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Access Level</label>
                    <span style="color: white; font-size: 1rem;">
                        <span class="badge badge-primary"><?php echo sanitizeOutput($material['access_level']); ?></span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Basic Information -->
        <div class="detail-card">
            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Title</label>
                    <span><strong><?php echo sanitizeOutput($material['title']); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Author</label>
                    <span><?php echo sanitizeOutput($material['author'] ?: 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Publisher</label>
                    <span><?php echo sanitizeOutput($material['publisher'] ?: 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Publication Date</label>
                    <span><?php echo $material['publication_date'] ? date('F d, Y', strtotime($material['publication_date'])) : 'N/A'; ?></span>
                </div>
                <div class="detail-item">
                    <label>ISBN</label>
                    <span><?php echo sanitizeOutput($material['isbn'] ?: 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Category</label>
                    <span><?php echo sanitizeOutput($material['category_name'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- File Information -->
        <div class="detail-card">
            <h3><i class="fas fa-file"></i> File Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>File Name</label>
                    <span><?php echo sanitizeOutput($material['file_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>File Type</label>
                    <span>
                        <span class="badge badge-info"><?php echo strtoupper($material['file_extension']); ?></span>
                    </span>
                </div>
                <div class="detail-item">
                    <label>File Size</label>
                    <span><strong><?php echo $size_formatted; ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Downloads</label>
                    <span>
                        <i class="fas fa-download" style="color: #2563eb;"></i> 
                        <strong><?php echo (int)$material['download_count']; ?></strong>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Views</label>
                    <span>
                        <i class="fas fa-eye" style="color: #10b981;"></i> 
                        <strong><?php echo (int)$material['view_count']; ?></strong>
                    </span>
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="detail-card">
            <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Department</label>
                    <span><?php echo sanitizeOutput($material['department_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Program</label>
                    <span><?php echo sanitizeOutput($material['program_name'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Description & Keywords -->
        <?php if (!empty($material['description']) || !empty($material['keywords'])): ?>
        <div class="detail-card">
            <h3><i class="fas fa-align-left"></i> Additional Information</h3>
            <?php if (!empty($material['description'])): ?>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.75rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Description</label>
                <div style="background: #f8fafc; padding: 1.25rem; border-radius: 8px; border-left: 4px solid #2563eb; line-height: 1.7; color: #475569;">
                    <?php echo nl2br(sanitizeOutput($material['description'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($material['keywords'])): ?>
            <div>
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.75rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Keywords</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <?php 
                    $keywords = explode(',', $material['keywords']);
                    foreach ($keywords as $keyword): 
                        $keyword = trim($keyword);
                        if (!empty($keyword)):
                    ?>
                        <span class="badge badge-secondary" style="font-size: 0.875rem; padding: 0.5rem 0.75rem;">
                            <i class="fas fa-tag"></i> <?php echo sanitizeOutput($keyword); ?>
                        </span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Upload Information -->
        <div class="detail-card">
            <h3><i class="fas fa-upload"></i> Upload Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Uploaded By</label>
                    <span><?php echo sanitizeOutput($material['uploaded_by_username'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Upload Date</label>
                    <span><?php echo date('F d, Y g:i A', strtotime($material['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <!-- File Actions -->
        <div class="detail-card" style="background: #f8fafc; border: 2px solid #e2e8f0;">
            <h3 style="background: transparent; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.75rem;">
                <i class="fas fa-download"></i> File Actions
            </h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem;">
                <a href="<?php echo BASE_URL; ?>modules/library/download.php?id=<?php echo $material_id; ?>" 
                   class="btn btn-primary" 
                   style="flex: 1; min-width: 200px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem;">
                    <i class="fas fa-download"></i> Download File
                </a>
                <a href="<?php echo BASE_URL; ?>modules/library/preview.php?id=<?php echo $material_id; ?>" 
                   class="btn btn-info" 
                   target="_blank"
                   style="flex: 1; min-width: 200px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem;">
                    <i class="fas fa-eye"></i> Preview File
                </a>
            </div>
            <div style="margin-top: 1rem; padding: 1rem; background: #eff6ff; border-radius: 8px; border-left: 4px solid #2563eb;">
                <p style="margin: 0; color: #1e40af; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> Downloading or previewing this file will increment the download/view count.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

