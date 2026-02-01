<?php
/**
 * View Announcement
 * Display single announcement details
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'View Announcement';
$pdo = getDBConnection();
$announcement_id = (int)($_GET['id'] ?? 0);

if (!$announcement_id) {
    header('Location: ' . BASE_URL . 'modules/announcements/index.php?error=Invalid announcement ID');
    exit;
}

// Get announcement data with creator info
$stmt = $pdo->prepare("SELECT a.*, u.username as created_by_username, d.department_name
                      FROM announcements a
                      LEFT JOIN users u ON a.created_by = u.user_id
                      LEFT JOIN departments d ON a.department_id = d.department_id
                      WHERE a.announcement_id = ?");
$stmt->execute([$announcement_id]);
$announcement = $stmt->fetch();

if (!$announcement) {
    header('Location: ' . BASE_URL . 'modules/announcements/index.php?error=Announcement not found');
    exit;
}

$is_expired = !empty($announcement['expiration_date']) && strtotime($announcement['expiration_date']) < time();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-bullhorn"></i> View Announcement</h1>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>modules/announcements/edit.php?id=<?php echo $announcement_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="detail-cards">
        <!-- Announcement Header Card -->
        <div class="detail-card" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
            <h3 style="color: white; border-bottom-color: rgba(255, 255, 255, 0.3); font-size: 1.75rem;">
                <i class="fas fa-bullhorn"></i> <?php echo sanitizeOutput($announcement['title']); ?>
            </h3>
            <div class="detail-grid" style="margin-top: 1.5rem;">
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Status</label>
                    <span style="color: white; font-size: 1.1rem;">
                        <span class="badge badge-<?php echo $announcement['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $announcement['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                        <?php if ($is_expired): ?>
                            <span class="badge badge-warning" style="margin-left: 0.5rem;">Expired</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Category</label>
                    <span style="color: white; font-size: 1rem;">
                        <span class="badge badge-info"><?php echo sanitizeOutput($announcement['category']); ?></span>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Visibility</label>
                    <span style="color: white; font-size: 1rem;">
                        <span class="badge badge-primary"><?php echo sanitizeOutput($announcement['visibility']); ?></span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Announcement Content -->
        <div class="detail-card">
            <h3><i class="fas fa-file-alt"></i> Announcement Content</h3>
            <div style="background: #f8fafc; padding: 2rem; border-radius: 12px; border-left: 4px solid #2563eb; line-height: 1.8; color: #334155; font-size: 1rem; white-space: pre-wrap;">
                <?php echo nl2br(sanitizeOutput($announcement['content'])); ?>
            </div>
        </div>

        <!-- Announcement Details -->
        <div class="detail-card">
            <h3><i class="fas fa-info-circle"></i> Announcement Details</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Category</label>
                    <span>
                        <span class="badge badge-info"><?php echo sanitizeOutput($announcement['category']); ?></span>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Target Audience</label>
                    <span>
                        <?php
                        $visibility_display = [
                            'All' => 'All (Students & Faculty)',
                            'All_Students' => 'All Students',
                            'All_Faculty' => 'All Faculty',
                            'Students_Department' => 'Students by Department',
                            'Faculty_Department' => 'Faculty by Department'
                        ];
                        echo sanitizeOutput($visibility_display[$announcement['visibility']] ?? $announcement['visibility']);
                        ?>
                    </span>
                </div>
                <?php if ($announcement['department_name']): ?>
                <div class="detail-item">
                    <label>Department</label>
                    <span><?php echo sanitizeOutput($announcement['department_name']); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <label>Status</label>
                    <span>
                        <span class="badge badge-<?php echo $announcement['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $announcement['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                        <?php if ($is_expired): ?>
                            <span class="badge badge-warning" style="margin-left: 0.5rem;">
                                <i class="fas fa-exclamation-triangle"></i> Expired
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Expiration Date</label>
                    <span>
                        <?php if ($announcement['expiration_date']): ?>
                            <?php echo date('F d, Y', strtotime($announcement['expiration_date'])); ?>
                            <?php if ($is_expired): ?>
                                <span style="color: #dc2626; font-weight: 600; margin-left: 0.5rem;">
                                    <i class="fas fa-times-circle"></i> Expired
                                </span>
                            <?php else: ?>
                                <span style="color: #059669; font-weight: 600; margin-left: 0.5rem;">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #64748b;">No expiration date set</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Publication Information -->
        <div class="detail-card">
            <h3><i class="fas fa-user-edit"></i> Publication Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Created By</label>
                    <span><strong><?php echo sanitizeOutput($announcement['created_by_username'] ?? 'N/A'); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Created At</label>
                    <span>
                        <i class="fas fa-calendar-alt" style="color: #2563eb;"></i> 
                        <?php echo date('F d, Y', strtotime($announcement['created_at'])); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Created Time</label>
                    <span>
                        <i class="fas fa-clock" style="color: #10b981;"></i> 
                        <?php echo date('g:i A', strtotime($announcement['created_at'])); ?>
                    </span>
                </div>
                <?php if ($announcement['updated_at'] != $announcement['created_at']): ?>
                <div class="detail-item">
                    <label>Last Updated</label>
                    <span>
                        <i class="fas fa-edit" style="color: #f59e0b;"></i> 
                        <?php echo date('F d, Y g:i A', strtotime($announcement['updated_at'])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Alert -->
        <?php if ($is_expired): ?>
        <div class="detail-card" style="background: #fef2f2; border: 2px solid #fecaca; border-left: 4px solid #dc2626;">
            <h3 style="background: transparent; color: #991b1b; border-bottom: 2px solid #fecaca;">
                <i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i> Expiration Notice
            </h3>
            <p style="margin: 0; color: #991b1b; line-height: 1.7; padding-top: 0.5rem;">
                <strong>This announcement has expired.</strong> It was set to expire on 
                <strong><?php echo date('F d, Y', strtotime($announcement['expiration_date'])); ?></strong>. 
                <?php if ($announcement['is_active']): ?>
                    The announcement is still marked as active but has passed its expiration date.
                <?php else: ?>
                    The announcement is currently inactive.
                <?php endif; ?>
            </p>
        </div>
        <?php elseif ($announcement['expiration_date'] && !$is_expired): ?>
        <div class="detail-card" style="background: #f0fdf4; border: 2px solid #bbf7d0; border-left: 4px solid #10b981;">
            <h3 style="background: transparent; color: #065f46; border-bottom: 2px solid #bbf7d0;">
                <i class="fas fa-check-circle" style="color: #10b981;"></i> Active Announcement
            </h3>
            <p style="margin: 0; color: #065f46; line-height: 1.7; padding-top: 0.5rem;">
                This announcement is currently active and will expire on 
                <strong><?php echo date('F d, Y', strtotime($announcement['expiration_date'])); ?></strong>.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

