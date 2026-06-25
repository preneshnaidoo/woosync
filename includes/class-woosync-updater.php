<?php
/**
 * WooSync GitHub Auto-Updater
 * Handles automatic plugin updates from GitHub releases
 */

if (!defined('ABSPATH')) exit;

class WooSync_Updater {
    /**
     * GitHub repository owner
     */
    private $owner = 'preneshnaidoo';
    
    /**
     * GitHub repository name
     */
    private $repo = 'woosync';
    
    /**
     * Current plugin version
     */
    private $version;
    
    /**
     * Plugin basename (e.g., 'amrod-sync/amrod-sync.php')
     */
    private $basename;
    
    /**
     * Plugin directory path
     */
    private $plugin_path;
    
    /**
     * GitHub API URL for releases
     */
    private $api_url = 'https://api.github.com/repos/preneshnaidoo/woosync/releases';

    /**
     * Constructor
     */
    public function __construct($version, $basename, $plugin_path) {
        $this->version = $version;
        $this->basename = $basename;
        $this->plugin_path = $plugin_path;
        
        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugins_api'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'upgrader_pre_download'], 10, 3);
    }

    /**
     * Check for plugin updates from GitHub
     */
    public function check_for_update($transient) {
        // Skip if check disabled or in AJAX context
        if (empty(get_option('amrod_auto_update', 1))) {
            return $transient;
        }
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return $transient;
        }
        
        // Skip if already checking (prevent loops)
        if (empty($transient)) {
            return $transient;
        }
        
        $latest = $this->get_latest_release();
        
        if (!$latest || !isset($latest['tag_name'])) {
            return $transient;
        }
        
        $latest_version = ltrim($latest['tag_name'], 'v');
        
        // Compare versions
        if (version_compare($latest_version, $this->version, '>')) {
            $download_url = $this->get_download_url($latest);
            
            if (!$download_url) {
                return $transient;
            }
            
            $transient->response[$this->basename] = (object) [
                'id' => $latest['id'] ?? 0,
                'slug' => 'amrod-sync',
                'plugin' => $this->basename,
                'new_version' => $latest_version,
                'url' => 'https://github.com/preneshnaidoo/woosync',
                'package' => $download_url,
                'tested' => '6.4',
                'requires' => '5.0',
                'requires_php' => '7.4',
            ];
        }
        
        return $transient;
    }

    /**
     * Get latest release info from GitHub API
     */
    private function get_latest_release() {
        $cache_key = 'woosync_latest_release';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $response = wp_remote_get($this->api_url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WooSync-WordPress/1.0',
            ],
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $releases = json_decode($body, true);
        
        if (!is_array($releases) || empty($releases)) {
            return null;
        }
        
        // Get the first (latest) release
        $latest = $releases[0];
        
        // Cache for 1 hour
        set_transient($cache_key, $latest, HOUR_IN_SECONDS);
        
        return $latest;
    }

    /**
     * Get download URL from release assets
     */
    private function get_download_url($release) {
        if (isset($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                // Look for .zip file
                if (isset($asset['name']) && strpos($asset['name'], '.zip') !== false) {
                    return $asset['browser_download_url'];
                }
            }
        }
        
        // Fallback: construct download URL from browser_download_url pattern
        if (isset($release['zipball_url'])) {
            return null; // Let WordPress handle it differently
        }
        
        // Try to find the zip in the tag URL
        $tag_name = $release['tag_name'] ?? '';
        if (!empty($tag_name)) {
            return "https://github.com/{$this->owner}/{$this->repo}/archive/refs/tags/{$tag_name}.zip";
        }
        
        return null;
    }

    /**
     * Override plugins_api to provide update info
     */
    public function plugins_api($default, $action, $args) {
        if ($action !== 'plugin_information') {
            return $default;
        }
        
        if (!isset($args->slug) || $args->slug !== 'amrod-sync') {
            return $default;
        }
        
        $latest = $this->get_latest_release();
        
        if (!$latest) {
            return $default;
        }
        
        $latest_version = ltrim($latest['tag_name'] ?? $this->version, 'v');
        
        return (object) [
            'name' => 'WooSync',
            'slug' => 'amrod-sync',
            'version' => $latest_version,
            'author' => '<a href="https://mediaplatform.co.za">MediaPlatform</a>',
            'homepage' => 'https://github.com/preneshnaidoo/woosync',
            'download_link' => $this->get_download_url($latest),
            'sections' => [
                'description' => 'Enterprise-grade sync for Amrod products, stock, categories, and colours into WooCommerce.',
                'installation' => 'Upload the plugin files to your WordPress plugins directory and activate.',
                'changelog' => $latest['body'] ?? 'See GitHub releases for changelog.',
            ],
        ];
    }

    /**
     * Pre-download hook to handle GitHub URLs
     */
    public function upgrader_pre_download($download, $package, $upgrader) {
        if (strpos($package, 'github.com') !== false) {
            // Clear any cached data
            delete_transient('woosync_latest_release');
        }
        return $download;
    }

    /**
     * Force refresh of update cache
     */
    public function refresh_update_cache() {
        delete_transient('woosync_latest_release');
        
        // Clear WordPress plugin update transients
        delete_site_transient('update_plugins');
        
        // Force WordPress to check for updates
        wp_update_plugins();
    }
}
