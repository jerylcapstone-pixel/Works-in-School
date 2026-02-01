        </main>
        
        <footer class="footer">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>. All rights reserved.</p>
            </div>
        </footer>
    <?php if (isLoggedIn()): ?>
    </div>
    <?php endif; ?>
    
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <script>
        // Sidebar Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const body = document.body;
            
            // Toggle sidebar
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    body.classList.toggle('sidebar-collapsed');
                    
                    // Show/hide overlay on mobile/tablet
                    if (window.innerWidth <= 1024) {
                        if (sidebar.classList.contains('collapsed')) {
                            sidebarOverlay.classList.add('active');
                        } else {
                            sidebarOverlay.classList.remove('active');
                        }
                    }
                });
            }
            
            // Close sidebar when clicking overlay (mobile)
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('collapsed');
                    body.classList.remove('sidebar-collapsed');
                    sidebarOverlay.classList.remove('active');
                });
            }
            
            // Close sidebar when clicking on a link (mobile/tablet)
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 1024) {
                        sidebar.classList.remove('collapsed');
                        body.classList.remove('sidebar-collapsed');
                        sidebarOverlay.classList.remove('active');
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024) {
                    sidebar.classList.remove('collapsed');
                    body.classList.remove('sidebar-collapsed');
                    sidebarOverlay.classList.remove('active');
                } else {
                    // On mobile/tablet, sidebar should be hidden by default
                    if (!sidebar.classList.contains('collapsed')) {
                        sidebar.classList.add('collapsed');
                    }
                }
            });
            
            // Initialize: Hide sidebar on mobile/tablet by default
            if (window.innerWidth <= 1024) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                body.classList.remove('sidebar-collapsed');
            }
        });
    </script>
    <?php if (isset($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <script src="<?php echo BASE_URL . $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
<?php 
// Load theme toggle JavaScript for students if theme customization is enabled
if (isLoggedIn() && hasRole(ROLE_STUDENT)) {
    require_once __DIR__ . '/../config/feature_toggles.php';
    $pdo = getDBConnection();
    if (isThemeCustomizationEnabled($pdo)) {
        echo '<script src="' . BASE_URL . 'assets/js/theme-toggle.js"></script>';
    }
}
?>
</body>
</html>
