/**
 * Theme Toggle JavaScript
 * Handles dark/light mode switching and persistence
 */

(function() {
    'use strict';
    
    // Check if theme customization is enabled
    const themeEnabled = document.body.getAttribute('data-theme-enabled') === '1';
    if (!themeEnabled) {
        return;
    }
    
    const THEME_STORAGE_KEY = 'user_theme_preference';
    const THEME_API_URL = BASE_URL + 'modules/theme/update.php';
    
    /**
     * Get current theme from localStorage or default to light
     */
    function getStoredTheme() {
        return localStorage.getItem(THEME_STORAGE_KEY) || 'light';
    }
    
    /**
     * Save theme to localStorage
     */
    function saveThemeToStorage(theme) {
        localStorage.setItem(THEME_STORAGE_KEY, theme);
    }
    
    /**
     * Apply theme to document
     */
    function applyTheme(theme) {
        const html = document.documentElement;
        const body = document.body;
        
        if (theme === 'dark') {
            html.classList.add('theme-dark');
            body.classList.add('theme-dark');
        } else {
            html.classList.remove('theme-dark');
            body.classList.remove('theme-dark');
        }
    }
    
    /**
     * Save theme preference to server
     */
    function saveThemeToServer(theme) {
        if (typeof fetch === 'undefined') {
            return; // Fallback if fetch is not available
        }
        
        fetch(THEME_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'theme_mode=' + encodeURIComponent(theme)
        }).catch(function(error) {
            console.error('Failed to save theme preference:', error);
        });
    }
    
    /**
     * Toggle theme between light and dark
     */
    function toggleTheme() {
        const currentTheme = getStoredTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        applyTheme(newTheme);
        saveThemeToStorage(newTheme);
        saveThemeToServer(newTheme);
        
        // Update toggle button state
        const toggleBtn = document.getElementById('theme-toggle-btn');
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            toggleBtn.setAttribute('title', 'Switch to ' + (newTheme === 'dark' ? 'Light' : 'Dark') + ' Mode');
        }
    }
    
    /**
     * Initialize theme on page load
     */
    function initTheme() {
        const storedTheme = getStoredTheme();
        applyTheme(storedTheme);
        
        // Update toggle button if it exists
        const toggleBtn = document.getElementById('theme-toggle-btn');
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.className = storedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            toggleBtn.setAttribute('title', 'Switch to ' + (storedTheme === 'dark' ? 'Light' : 'Dark') + ' Mode');
            toggleBtn.addEventListener('click', toggleTheme);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
    
    // Expose toggle function globally for manual calls
    window.toggleTheme = toggleTheme;
    window.getCurrentTheme = getStoredTheme;
})();

