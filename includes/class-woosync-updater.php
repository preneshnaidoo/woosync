<?php
/**
 * WooSync GitHub Auto-Update System
 * 
 * Handles checking for updates, displaying notices, and performing
 * automatic or manual updates from GitHub releases.
 */

if (!defined('ABSPATH')) exit;

class WooSync_Updater {
    
    /**
     * GitHub repository API URL
     */
    const GITHUB_API_URL = 'https://api.github.com/repos/preneshnaidoo/amrod-sync';
    
    /**
     * GitHub releases URL (for download links)
     */
    const GITHUB_RELEASES_URL = 'https://github.com/preneshnaidoo/amrod-sync/releases';
    
    /**
     * Update check transient name
     */
    const TRANSIENT_UPDATE_CHECK = 'woosync_update_check';
    
    /**
     * Update available transient
     */
    const TRANSIENT_UPDATE_AVAILABLE = 'woosync_update_available';
    
    /**
     * Dismissed version transient
     */
    const TRANSIENT_UPDATE_DISMISSED = 'woosync_update_dismissed';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'check_for_updates']);
        add_action('wp_ajax_woosync_dismiss_update', [$this, 'ajax_dismiss_update']);
        add_action('wp_ajax_woosync_check_updates', [$this, 'ajax_check_updates']);
        add_action('wp_ajax_woosync_do_update', [$this, 'ajax_do_update']);
        add_action('wp_ajax_woosync_clear_dismissed', [$this, 'ajax_clear_dismissed']);
        
        // Schedule daily update check
        add_action('woosync_daily_update_check', [$this, 'scheduled_update_check']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notice']);
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Register update-related settings
     */
    public function register_settings() {
        register_setting('woosync_group', 'woosync_auto_update');
        register_setting('woosync_group', 'woosync_update_history');
        register_setting('woosync_group', 'woosync_last_update_check');
        register_setting('woosync_group', 'woosync_last_update_time');
        register_setting('woosync_group', 'woosync_update_backup_enabled');
    }
    
    /**
     * Check for updates (throttled to once per day)
     */
    public function check_for_updates() {
        // Check if we've already checked today
        $last_check = get_transient(self::TRANSIENT_UPDATE_CHECK);
        if ($last_check !== false) {
            return;
        }
        
        $this->scheduled_update_check();
        
        // Set transient for 24 hours
        set_transient(self::TRANSIENT_UPDATE_CHECK, time(), DAY_IN_SECONDS);
    }
    
    /**
     * Scheduled update check (called by cron or manually)
     */
    public function scheduled_update_check() {
        $latest = $this->fetch_latest_release();
        
        if (is_wp_error($latest)) {
            // Silent fail - will retry next day
            return;
        }
        
        $current_version = WOOSYNC_VERSION;
        $latest_version = ltrim($latest['tag_name'], 'v');
        
        // Compare versions
        if (version_compare($latest_version, $current_version, '>')) {
            // Update available
            set_transient(self::TRANSIENT_UPDATE_AVAILABLE, [
                'version' => $latest_version,
                'name' => $latest['name'] ?? $latest['tag_name'],
                'body' => $latest['body'] ?? '',
                'url' => $latest['html_url'] ?? '',
                'zip_url' => $latest['zipball_url'] ?? '',
                'published_at' => $latest['published_at'] ?? '',
            ], DAY_IN_SECONDS * 7); // Keep for 7 days
            
            update_option('woosync_last_update_check', current_time('mysql'));
        } else {
            // Already up to date
            delete_transient(self::TRANSIENT_UPDATE_AVAILABLE);
            update_option('woosync_last_update_check', current_time('mysql'));
        }
    }
    
    /**
     * Fetch latest release from GitHub API
     */
    public function fetch_latest_release() {
        $response = wp_remote_get(self::GITHUB_API_URL . '/releases/latest', [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'WooSync/' . WOOSYNC_VERSION
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        // Handle rate limiting (403) or other errors
        if ($code === 403) {
            return new WP_Error('rate_limited', 'GitHub API rate limit exceeded. Will retry tomorrow.');
        }
        
        if ($code !== 200) {
            return new WP_Error('api_error', 'GitHub API returned error code: ' . $code);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['tag_name'])) {
            return new WP_Error('parse_error', 'Failed to parse GitHub API response');
        }
        
        return $data;
    }
    
    /**
     * Get update info
     */
    public function get_update_info() {
        return get_transient(self::TRANSIENT_UPDATE_AVAILABLE);
    }
    
    /**
     * Check if update was dismissed
     */
    public function is_update_dismissed($version) {
        $dismissed = get_transient(self::TRANSIENT_UPDATE_DISMISSED);
        return $dismissed === $version;
    }
    
    /**
     * Dismiss update notice
     */
    public function ajax_dismiss_update() {
        check_ajax_referer('woosync_nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $version = sanitize_text_field($_POST['version'] ?? '');
        if ($version) {
            set_transient(self::TRANSIENT_UPDATE_DISMISSED, $version, DAY_IN_SECONDS * 30);
        }
        
        wp_send_json_success(['message' => 'Update dismissed']);
    }
    
    /**
     * Clear dismissed update (for testing)
     */
    public function ajax_clear_dismissed() {
        check_ajax_referer('woosync_nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        delete_transient(self::TRANSIENT_UPDATE_DISMISSED);
        wp_send_json_success(['message' => 'Dismissed state cleared']);
    }
    
    /**
     * Manually check for updates (AJAX)
     */
    public function ajax_check_updates() {
        check_ajax_referer('woosync_nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        // Clear transient to force fresh check
        delete_transient(self::TRANSIENT_UPDATE_CHECK);
        $this->scheduled_update_check();
        
        $update = $this->get_update_info();
        
        if ($update) {
            wp_send_json_success([
                'update_available' => true,
                'version' => $update['version'],
                'name' => $update['name'],
                'url' => $update['url']
            ]);
        } else {
            wp_send_json_success([
                'update_available' => false,
                'version' => WOOSYNC_VERSION
            ]);
        }
    }
    
    /**
     * Perform the update (AJAX)
     */
    public function ajax_do_update() {
        check_ajax_referer('woosync_nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $update = $this->get_update_info();
        if (!$update) {
            wp_send_json_error('No update available');
        }
        
        $result = $this->perform_update($update);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Perform the actual update
     */
    public function perform_update($update) {
        // Ensure backup directory exists
        $backup_dir = WP_CONTENT_DIR . '/backups/woosync';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        // Backup current version if enabled
        $backup_enabled = get_option('woosync_update_backup_enabled', true);
        if ($backup_enabled) {
            $backup_result = $this->backup_current_version($backup_dir);
            if (is_wp_error($backup_result)) {
                // Log but continue - backup failure shouldn't block update
                $this->log_update_event('backup_failed', $backup_result->get_error_message());
            }
        }
        
        // Download the update
        $download_result = $this->download_update($update['zip_url']);
        if (is_wp_error($download_result)) {
            $this->log_update_event('download_failed', $download_result->get_error_message());
            return $download_result;
        }
        
        // Extract and install
        $install_result = $this->install_update($download_result);
        if (is_wp_error($install_result)) {
            // Attempt rollback
            $this->rollback_to_backup($backup_dir);
            $this->log_update_event('install_failed_rollback', $install_result->get_error_message());
            return $install_result;
        }
        
        // Success
        $this->log_update_event('success', 'Updated to ' . $update['version']);
        update_option('woosync_last_update_time', current_time('mysql'));
        
        // Clear update transient
        delete_transient(self::TRANSIENT_UPDATE_AVAILABLE);
        
        return [
            'success' => true,
            'version' => $update['version'],
            'message' => 'Successfully updated to v' . $update['version']
        ];
    }
    
    /**
     * Backup current version
     */
    private function backup_current_version($backup_dir) {
        $plugin_file = WP_PLUGIN_DIR . '/woosync/woosync.php';
        $plugin_dir = WP_PLUGIN_DIR . '/woosync';
        
        if (!file_exists($plugin_file)) {
            return new WP_Error('backup_failed', 'Plugin file not found');
        }
        
        $backup_name = 'woosync-' . WOOSYNC_VERSION . '-' . date('Y-m-d-His') . '.zip';
        $backup_path = $backup_dir . '/' . $backup_name;
        
        // Use ZipArchive to create backup
        if (!class_exists('ZipArchive')) {
            return new WP_Error('backup_failed', 'ZipArchive not available');
        }
        
        $zip = new ZipArchive();
        if ($zip->open($backup_path, ZipArchive::CREATE) !== true) {
            return new WP_Error('backup_failed', 'Could not create backup file');
        }
        
        $this->add_directory_to_zip($zip, $plugin_dir, 'woosync');
        $zip->close();
        
        // Clean old backups (keep last 5)
        $this->clean_old_backups($backup_dir, 5);
        
        return $backup_path;
    }
    
    /**
     * Add directory to zip recursively
     */
    private function add_directory_to_zip($zip, $dir, $base_name) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $file_path = $file->getRealPath();
            $relative_path = $base_name . '/' . substr($file_path, strlen($dir) + 1);
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relative_path);
            } else {
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    
    /**
     * Clean old backups, keeping only $keep newest
     */
    private function clean_old_backups($backup_dir, $keep = 5) {
        $backups = glob($backup_dir . '/woosync-*.zip');
        if (!$backups) return;
        
        // Sort by modification time (newest first)
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Delete older backups
        $to_delete = array_slice($backups, $keep);
        foreach ($to_delete as $old_backup) {
            @unlink($old_backup);
        }
    }
    
    /**
     * Download update zip from GitHub
     */
    private function download_update($zip_url) {
        $response = wp_remote_get($zip_url, [
            'timeout' => 120,
            'stream' => true,
            'filename' => get_temp_dir() . 'woosync-update-' . time() . '.zip'
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('download_failed', 'Failed to download update (HTTP ' . $code . ')');
        }
        
        $download_file = wp_remote_retrieve_body($response);
        if (!$download_file || !file_exists($download_file)) {
            return new WP_Error('download_failed', 'Downloaded file not found');
        }
        
        return $download_file;
    }
    
    /**
     * Install the update
     */
    private function install_update($zip_file) {
        // Unzip the update
        $unzip_result = $this->unzip_file($zip_file);
        if (is_wp_error($unzip_result)) {
            return $unzip_result;
        }
        
        // Move files to plugin directory
        $plugin_dir = WP_PLUGIN_DIR . '/woosync';
        $extract_dir = dirname($zip_file) . '/woosync-extract';
        
        if (!file_exists($extract_dir)) {
            return new WP_Error('install_failed', 'Extract directory not found');
        }
        
        // Check if the extracted folder has the expected structure
        $extracted_files = scandir($extract_dir);
        $has_woosync_php = false;
        
        foreach ($extracted_files as $file) {
            if ($file === 'woosync.php' || strpos($file, 'woosync') === 0) {
                $has_woosync_php = true;
                break;
            }
        }
        
        // If files are in a subdirectory (preneshnaidoo-amrod-sync-hash/), extract properly
        $src_dir = $extract_dir;
        $handle = opendir($extract_dir);
        if ($handle) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry !== '.' && $entry !== '..' && is_dir($extract_dir . '/' . $entry)) {
                    // Check if this is the plugin root (contains woosync.php)
                    if (file_exists($extract_dir . '/' . $entry . '/woosync.php')) {
                        $src_dir = $extract_dir . '/' . $entry;
                        break;
                    }
                }
            }
            closedir($handle);
        }
        
        // Copy files to plugin directory
        $this->copy_directory($src_dir, $plugin_dir);
        
        // Clean up
        $this->delete_directory($extract_dir);
        @unlink($zip_file);
        
        return true;
    }
    
    /**
     * Unzip file using ZipArchive
     */
    private function unzip_file($zip_file) {
        $extract_to = dirname($zip_file) . '/woosync-extract';
        
        if (file_exists($extract_to)) {
            $this->delete_directory($extract_to);
        }
        
        wp_mkdir_p($extract_to);
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) {
            return new WP_Error('unzip_failed', 'Could not open zip file');
        }
        
        $zip->extractTo($extract_to);
        $zip->close();
        
        return $extract_to;
    }
    
    /**
     * Copy directory contents
     */
    private function copy_directory($src, $dest) {
        if (!file_exists($dest)) {
            wp_mkdir_p($dest);
        }
        
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            
            $src_path = $src . '/' . $file;
            $dest_path = $dest . '/' . $file;
            
            if (is_dir($src_path)) {
                $this->copy_directory($src_path, $dest_path);
            } else {
                @copy($src_path, $dest_path);
            }
        }
        closedir($dir);
    }
    
    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
    
    /**
     * Rollback to backup
     */
    private function rollback_to_backup($backup_dir) {
        $backups = glob($backup_dir . '/woosync-*.zip');
        if (!$backups) {
            return new WP_Error('rollback_failed', 'No backup found to rollback to');
        }
        
        // Get most recent backup
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latest_backup = $backups[0];
        $plugin_dir = WP_PLUGIN_DIR . '/woosync';
        
        // Delete current files
        $this->delete_directory($plugin_dir);
        
        // Extract backup
        $zip = new ZipArchive();
        if ($zip->open($latest_backup) !== true) {
            return new WP_Error('rollback_failed', 'Could not open backup file');
        }
        
        wp_mkdir_p($plugin_dir);
        $zip->extractTo($plugin_dir);
        $zip->close();
        
        $this->log_update_event('rollback_success', 'Rolled back to backup: ' . basename($latest_backup));
        
        return true;
    }
    
    /**
     * Log update event
     */
    private function log_update_event($event, $message) {
        $history = get_option('woosync_update_history', []);
        
        $history[] = [
            'event' => $event,
            'message' => $message,
            'time' => current_time('mysql'),
            'version' => WOOSYNC_VERSION
        ];
        
        // Keep last 50 events
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        
        update_option('woosync_update_history', $history);
    }
    
    /**
     * Get update history
     */
    public function get_update_history() {
        return get_option('woosync_update_history', []);
    }
    
    /**
     * Display admin notice for available update
     */
    public function admin_notice() {
        // Only show on WooSync pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'woosync') === false) {
            return;
        }
        
        $update = $this->get_update_info();
        if (!$update) return;
        
        // Check if dismissed
        if ($this->is_update_dismissed($update['version'])) {
            return;
        }
        
        $dismiss_url = wp_nonce_url(admin_url('admin-ajax.php?action=woosync_dismiss_update&version=' . urlencode($update['version'])), 'woosync_nonce');
        ?>
        <div class="notice notice-info woosync-update-notice" data-version="<?php echo esc_attr($update['version']); ?>">
            <p style="display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-update" style="font-size: 20px; width: 20px; height: 20px;"></span>
                <strong>WooSync v<?php echo esc_html($update['version']); ?></strong> is available!
                <?php if (!empty($update['name']) && $update['name'] !== $update['version']): ?>
                    — <?php echo esc_html($update['name']); ?>
                <?php endif; ?>
            </p>
            <p>
                <a href="<?php echo esc_url($update['url']); ?>" target="_blank" class="button button-primary">View Release Notes</a>
                <button type="button" class="button woosync-install-update" data-version="<?php echo esc_attr($update['version']); ?>">Update Now</button>
                <?php if (!empty($update['body'])): ?>
                    <a href="#TB_inline?width=600&height=400&inlineId=woosync-changelog" class="button thickbox">View Changelog</a>
                <?php endif; ?>
                <a href="<?php echo esc_url($dismiss_url); ?>" class="button-link woosync-dismiss-update">Dismiss</a>
            </p>
            <div id="woosync-changelog" style="display: none;">
                <div style="padding: 20px; max-height: 400px; overflow-y: auto;">
                    <?php echo wp_kses_post(wpautop($update['body'])); ?>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.woosync-install-update').on('click', function() {
                var $btn = $(this);
                var version = $btn.data('version');
                
                if (!confirm('Are you sure you want to update WooSync to v' + version + '? A backup will be created automatically.')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Updating...');
                
                $.ajax({
                    url: woosyncData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'woosync_do_update',
                        nonce: woosyncData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Update successful! WooSync has been updated to v' + response.data.version + '.');
                            location.reload();
                        } else {
                            alert('Update failed: ' + response.data);
                            $btn.prop('disabled', false).text('Update Now');
                        }
                    },
                    error: function() {
                        alert('Update failed: AJAX error');
                        $btn.prop('disabled', false).text('Update Now');
                    }
                });
            });
            
            $('.woosync-dismiss-update').on('click', function(e) {
                e.preventDefault();
                var $notice = $(this).closest('.woosync-update-notice');
                
                $.ajax({
                    url: $(this).attr('href'),
                    success: function() {
                        $notice.fadeOut();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get auto-update setting
     */
    public function get_auto_update_setting() {
        return get_option('woosync_auto_update', 'off');
    }
    
    /**
     * Check if auto-update is enabled for a given version type
     */
    public function is_auto_update_enabled($new_version) {
        $setting = $this->get_auto_update_setting();
        
        if ($setting === 'off') {
            return false;
        }
        
        if ($setting === 'all') {
            return true;
        }
        
        if ($setting === 'minor') {
            // Only auto-update minor/patch versions
            $current = WOOSYNC_VERSION;
            $current_parts = explode('.', $current);
            $new_parts = explode('.', $new_version);
            
            // Major version must match
            if ($current_parts[0] !== $new_parts[0]) {
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Schedule auto-update check
     */
    public static function schedule_update_check() {
        if (!wp_next_scheduled('woosync_daily_update_check')) {
            wp_schedule_event(time(), 'daily', 'woosync_daily_update_check');
        }
    }
    
    /**
     * Clear scheduled update check
     */
    public static function clear_scheduled_check() {
        wp_clear_scheduled_hook('woosync_daily_update_check');
    }
}

// Initialize the updater
new WooSync_Updater();
