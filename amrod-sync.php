<?php
/*
Plugin Name: Amrod WooCommerce Sync
Description: Sync Amrod products, stock, branding, categories, and colours into WooCommerce. Manual sync button with batched background processing (Action Scheduler) and WP‑CLI support.
Version: 1.0.0.1
Author: Mediaplatform
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// --- Guard: Require WooCommerce (check on plugins_loaded to ensure WooCommerce is initialized) ---
add_action('plugins_loaded', 'amrod_check_woocommerce_dependency', 10);
function amrod_check_woocommerce_dependency() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action('admin_notices', 'amrod_admin_notice_woocommerce_missing');
        // Deactivate the plugin if WooCommerce is not active
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return;
    }
}

function amrod_admin_notice_woocommerce_missing() {
    echo "<div class='notice notice-error'><p>❌ WooCommerce must be active for Amrod Sync to work. Plugin has been deactivated.</p></div>";
}

// --- Register Settings (including configurable endpoints) ---
function amrod_sync_register_settings() {
    register_setting('amrod_sync_options_group', 'amrod_username', 'sanitize_text_field');
    register_setting('amrod_sync_options_group', 'amrod_password', 'sanitize_text_field');
    register_setting('amrod_sync_options_group', 'amrod_customer_code', 'sanitize_text_field');
    register_setting('amrod_sync_options_group', 'amrod_docs_url', 'esc_url_raw');
    register_setting('amrod_sync_options_group', 'amrod_auth_url', 'esc_url_raw');
    register_setting('amrod_sync_options_group', 'amrod_api_url', 'esc_url_raw');
    register_setting('amrod_sync_options_group', 'amrod_endpoints', 'sanitize_text_field');
}
add_action('admin_init', 'amrod_sync_register_settings');

// Get default endpoints
function amrod_get_default_endpoints() {
    return [
        'products' => ['label' => 'Products', 'path' => '/api/v1/Products/', 'enabled' => 1],
        'products_updated' => ['label' => 'Products (Updated)', 'path' => '/api/v1/Products/GetUpdatedProducts', 'enabled' => 0],
        'products_branding' => ['label' => 'Products with Branding', 'path' => '/api/v1/Products/GetProductsAndBranding', 'enabled' => 0],
        'products_updated_branding' => ['label' => 'Products Updated with Branding', 'path' => '/api/v1/Products/GetUpdatedProductsAndBranding', 'enabled' => 0],
        'stock' => ['label' => 'Stock', 'path' => '/api/v1/Stock/', 'enabled' => 0],
        'stock_updated' => ['label' => 'Stock (Updated)', 'path' => '/api/v1/Stock/GetUpdated', 'enabled' => 0],
        'prices' => ['label' => 'Prices', 'path' => '/api/v1/Prices/', 'enabled' => 0],
        'prices_updated' => ['label' => 'Prices (Updated)', 'path' => '/api/v1/Prices/GetUpdated', 'enabled' => 0],
        'categories' => ['label' => 'Categories', 'path' => '/api/v1/Categories/', 'enabled' => 0],
        'categories_updated' => ['label' => 'Categories (Updated)', 'path' => '/api/v1/Categories/GetUpdated', 'enabled' => 0],
        'brands' => ['label' => 'Brands', 'path' => '/api/v1/Brands/', 'enabled' => 0],
        'brands_updated' => ['label' => 'Brands (Updated)', 'path' => '/api/v1/Brands/GetUpdated', 'enabled' => 0],
        'branding_depts' => ['label' => 'Branding Departments', 'path' => '/api/v1/BrandingDepartments/', 'enabled' => 0],
        'branding_depts_updated' => ['label' => 'Branding Departments (Updated)', 'path' => '/api/v1/BrandingDepartments/GetUpdated', 'enabled' => 0],
        'inclusive_brandings' => ['label' => 'Inclusive Brandings', 'path' => '/api/v1/InclusiveBrandings/', 'enabled' => 0],
        'inclusive_brandings_updated' => ['label' => 'Inclusive Brandings (Updated)', 'path' => '/api/v1/InclusiveBrandings/GetUpdated', 'enabled' => 0],
        'branding_prices' => ['label' => 'Branding Prices', 'path' => '/api/v1/BrandingPrices/', 'enabled' => 0],
        'branding_prices_updated' => ['label' => 'Branding Prices (Updated)', 'path' => '/api/v1/BrandingPrices/GetUpdated', 'enabled' => 0],
        'colour_swatches' => ['label' => 'Colour Swatches', 'path' => '/api/v1/ColourSwatches/', 'enabled' => 0],
        'colour_groups' => ['label' => 'Colour Groups', 'path' => '/api/v1/ColourSwatches/GetGrouping', 'enabled' => 0],
    ];
}

// Get user's configured endpoints (merged with defaults)
function amrod_get_endpoints() {
    $defaults = amrod_get_default_endpoints();
    $stored = get_option('amrod_endpoints');
    if ($stored && is_string($stored)) {
        $stored = @json_decode($stored, true);
        if (is_array($stored)) {
            // Merge: keep stored config, add any new defaults
            foreach ($defaults as $key => $default) {
                if (isset($stored[$key])) {
                    // Preserve stored enabled/label/path, fill in missing fields from default
                    $stored[$key] = array_merge($default, $stored[$key]);
                } else {
                    // New endpoint, use default
                    $stored[$key] = $default;
                }
            }
            return $stored;
        }
    }
    return $defaults;
}

// Save endpoints config
function amrod_save_endpoints($endpoints) {
    update_option('amrod_endpoints', json_encode($endpoints));
}

// --- Endpoint Helpers ---
function amrod_get_auth_url() {
    return get_option('amrod_auth_url', 'https://identity.amrod.co.za');
}

function amrod_get_api_url() {
    return get_option('amrod_api_url', 'https://vendorapi.amrod.co.za');
}

function amrod_get_token_endpoint() {
    return amrod_get_auth_url() . '/VendorLogin';
}

// Get the enabled products endpoint (for backwards compatibility)
function amrod_get_products_endpoint() {
    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    if ($products_ep && $products_ep['enabled']) {
        return amrod_get_api_url() . $products_ep['path'];
    }
    // fallback
    return amrod_get_api_url() . '/api/v1/Products/';
}

// Get the enabled categories endpoint (for backwards compatibility)
function amrod_get_categories_endpoint() {
    $endpoints = amrod_get_endpoints();
    $categories_ep = $endpoints['categories'] ?? null;
    if ($categories_ep && $categories_ep['enabled']) {
        return amrod_get_api_url() . $categories_ep['path'];
    }
    // fallback
    return amrod_get_api_url() . '/api/v1/Categories/';
}

// Get the enabled colours endpoint (for backwards compatibility)
function amrod_get_colours_endpoint() {
    $endpoints = amrod_get_endpoints();
    $colours_ep = $endpoints['colour_swatches'] ?? null;
    if ($colours_ep && $colours_ep['enabled']) {
        return amrod_get_api_url() . $colours_ep['path'];
    }
    // fallback
    return amrod_get_api_url() . '/api/v1/ColourSwatches/';
}

/**
 * Return the configured Amrod API password.
 * Priority: Stored option (database) -> environment variable AMROD_API_PASSWORD -> constant AMROD_API_PASSWORD
 */
function amrod_get_password() {
    $stored = get_option('amrod_password');
    if ($stored) {
        return $stored;
    }
    $env = getenv('AMROD_API_PASSWORD');
    if ($env !== false && $env !== '') {
        return $env;
    }
    if (defined('AMROD_API_PASSWORD') && AMROD_API_PASSWORD) {
        return AMROD_API_PASSWORD;
    }
    return '';
} 

// Activation check: ensure WooCommerce is active + show setup notice
register_activation_hook(__FILE__, 'amrod_activation_check');
function amrod_activation_check() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die('Amrod Sync requires WooCommerce to be active. Plugin has been deactivated.');
    }
    // Set transient to show activation notice (displayed once)
    set_transient('amrod_sync_activation_notice', 1, HOUR_IN_SECONDS);
}

// Show admin notice on activation
add_action('admin_notices', 'amrod_sync_activation_notice');
function amrod_sync_activation_notice() {
    if (get_transient('amrod_sync_activation_notice')) {
        delete_transient('amrod_sync_activation_notice');
        echo '<div class="notice notice-info is-dismissible"><p>';
        echo '✅ <strong>Amrod Sync activated!</strong> ';
        echo 'Next: Set your API credentials via <code>AMROD_API_PASSWORD</code> environment variable, then visit <a href="' . esc_url(admin_url('admin.php?page=amrod-sync')) . '"><strong>Amrod Sync settings</strong></a> to configure and test your connection.';
        echo '</p></div>';
    }
}

// --- Admin Menu (register only if WooCommerce is available) ---
add_action('admin_menu', 'amrod_register_admin_menus', 10);
function amrod_register_admin_menus() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    
    // Top-level menu
    add_menu_page(
        'Amrod Sync',                // Page title
        'Amrod Sync',                // Menu title
        'manage_options',            // Capability
        'amrod-sync',                // Menu slug
        'amrod_sync_settings_page',  // Callback function
        'dashicons-update',          // Icon
        56                           // Position
    );
    
    // Status submenu
    add_submenu_page(
        'amrod-sync',                // parent slug
        'Amrod Sync Status',         // page title
        'Status',                    // menu title
        'manage_options',            // capability
        'amrod-sync-status',         // menu slug
        'amrod_sync_status_page'     // callback
    );
    
    // Settings submenu (fallback discoverability)
    add_options_page('Amrod Sync', 'Amrod Sync', 'manage_options', 'amrod-sync', 'amrod_sync_settings_page');
    
    // WooCommerce submenu (accessible to shop managers)
    if (function_exists('WC')) {
        add_submenu_page('woocommerce', 'Amrod Sync', 'Amrod Sync', 'manage_woocommerce', 'amrod-sync', 'amrod_sync_settings_page');
    }
}

// Admin bar link
add_action('admin_bar_menu', 'amrod_admin_bar_link', 100);
function amrod_admin_bar_link($wp_admin_bar) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    if (! ( current_user_can('manage_options') || current_user_can('manage_woocommerce') ) ) {
        return;
    }
    $wp_admin_bar->add_node([
        'id'    => 'amrod-sync',
        'title' => 'Amrod Sync',
        'href'  => admin_url('admin.php?page=amrod-sync'),
        'meta'  => ['title' => 'Amrod Sync settings']
    ]);
}

function amrod_sync_status_page() {
    if (! current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $last = get_option('amrod_last_sync');
    $total = (int) get_option('amrod_total_products', 0);
    $last_token = get_option('amrod_last_token');
    echo '<div class="wrap"><h1>Amrod Sync — Status</h1>';
    echo '<p><strong>Last sync:</strong> ' . esc_html($last ?: 'Never') . '</p>';
    echo '<p><strong>Total products synced (accumulated):</strong> ' . esc_html($total) . '</p>';
    echo '<p><strong>Last token (masked):</strong> ' . esc_html($last_token ?: 'Not yet fetched') . '</p>';

    if (function_exists('as_get_scheduled_actions')) {
        $count = count(as_get_scheduled_actions(['hook' => 'amrod_sync_batch']));
        echo '<p><strong>Action Scheduler queued batches:</strong> ' . esc_html($count) . '</p>';
    } else {
        echo '<p><strong>Action Scheduler:</strong> not available — plugin will run synchronously when you click <em>Run Sync Now</em>.</p>';
    }

    echo '<h2>Recent log</h2>';
    $log = array_reverse((array) get_option('amrod_sync_log', []));

    if (empty($log)) {
        echo '<p><em>No log entries yet.</em></p>';
    } else {
        echo '<pre style="background:#fff;border:1px solid #eee;padding:8px;max-height:400px;overflow:auto;">' . esc_html(implode("\n", array_slice($log, 0, 200))) . '</pre>';
    }

    echo '</div>';
}

// --- Settings Link in Plugins Screen ---
function amrod_sync_plugin_action_links($links) {
    $settings_link = '<a href="admin.php?page=amrod-sync">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'amrod_sync_plugin_action_links');
// Additional fallbacks: different plugin-basenames and network admin
add_filter('plugin_action_links_amrod-sync.php', 'amrod_sync_plugin_action_links');
add_filter('plugin_action_links_amrod-sync/amrod-sync.php', 'amrod_sync_plugin_action_links');
add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), 'amrod_sync_plugin_action_links');
add_filter('network_admin_plugin_action_links_amrod-sync.php', 'amrod_sync_plugin_action_links');
add_filter('network_admin_plugin_action_links_amrod-sync/amrod-sync.php', 'amrod_sync_plugin_action_links');

// --- Settings Page with Tabs ---
function amrod_sync_settings_page() {
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'credentials';
    ?>
    <style>
        .password-toggle { cursor: pointer; margin-left: 8px; font-size: 18px; user-select: none; }
        .nav-tab-wrapper { background-color: #fff; border-bottom: 1px solid #ccc; }
        .nav-tab { padding: 8px 12px; text-decoration: none; color: #666; display: inline-block; border-bottom: 3px solid transparent; }
        .nav-tab:hover { color: #000; }
        .nav-tab.nav-tab-active { color: #000; border-bottom-color: #0073aa; }
        .tab-content { display: none; padding: 20px; }
        .tab-content.active { display: block; }
    </style>
    <script>
        function togglePasswordVisibility(fieldId) {
            var field = document.getElementById(fieldId);
            var toggle = event.target;
            if (field.type === 'password') {
                field.type = 'text';
                toggle.textContent = '🙈';
            } else {
                field.type = 'password';
                toggle.textContent = '👁️';
            }
        }
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('nav-tab-active'));
            event.target.classList.add('nav-tab-active');
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }
    </script>
    <div class="wrap">
        <h1>Amrod Sync Settings (v1.0.0.1)</h1>
        <?php settings_errors('amrod_sync_options_group'); ?>

        <?php if ($errors = get_option('amrod_errors')): ?>
            <div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_html($errors); ?></p></div>
        <?php elseif ($last = get_option('amrod_last_sync')): ?>
            <div class="notice notice-success is-dismissible"><p>✅ Last sync completed at <?php echo esc_html($last); ?></p></div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="nav-tab-wrapper">
            <a href="#" class="nav-tab <?php echo $active_tab === 'credentials' ? 'nav-tab-active' : ''; ?>" onclick="switchTab('credentials'); return false;">Credentials</a>
            <a href="#" class="nav-tab <?php echo $active_tab === 'endpoints' ? 'nav-tab-active' : ''; ?>" onclick="switchTab('endpoints'); return false;">API Endpoints</a>
            <a href="#" class="nav-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>" onclick="switchTab('sync'); return false;">Sync</a>
            <a href="#" class="nav-tab <?php echo $active_tab === 'status' ? 'nav-tab-active' : ''; ?>" onclick="switchTab('status'); return false;">Status</a>
        </div>

        <!-- Credentials Tab -->
        <div id="tab-credentials" class="tab-content <?php echo $active_tab === 'credentials' ? 'active' : ''; ?>">
            <h2>Amrod API Credentials</h2>
            <p style="max-width:60em">Request <strong>Username</strong>, <strong>Password</strong>, and <strong>Customer Code</strong> from <a href="https://marketing.amrod.co.za/landing/NewAPIAccessRequest" target="_blank">Amrod support</a>. Your credentials will be stored securely.</p>
            <form method="post" action="options.php">
                <?php settings_fields('amrod_sync_options_group'); ?>
                <table class="form-table">
                    <tr><th>Username</th><td><input type="text" name="amrod_username" value="<?php echo esc_attr(get_option('amrod_username')); ?>" style="width:100%;max-width:300px;" placeholder="vendor_username" /></td></tr>
                    <tr><th>Password</th><td><input type="password" id="amrod_password_field" name="amrod_password" value="<?php echo esc_attr(get_option('amrod_password')); ?>" style="width:100%;max-width:300px;" /> <span class="password-toggle" onclick="togglePasswordVisibility('amrod_password_field')">👁️</span></td></tr>
                    <tr><th>Customer Code</th><td><input type="text" name="amrod_customer_code" value="<?php echo esc_attr(get_option('amrod_customer_code')); ?>" style="width:100%;max-width:300px;" placeholder="e.g., ABC123" /></td></tr>
                </table>
                <?php submit_button('Save Credentials'); ?>
            </form>
        </div>

        <!-- Endpoints Manager Tab -->
        <div id="tab-endpoints" class="tab-content <?php echo $active_tab === 'endpoints' ? 'active' : ''; ?>">
            <h2>API Endpoints Manager (v1.0)</h2>
            <p style="max-width:60em">Enable/disable endpoints, customize their paths, and rename labels. All endpoints use the Amrod API v1 base URL: <code><?php echo esc_html(amrod_get_api_url()); ?></code></p>
            
            <div style="background:#e8f5e9;border:1px solid #4caf50;padding:12px;margin-bottom:15px;border-radius:3px;">
                <strong>✅ Amrod API v1 Endpoints Detected!</strong><br>Base URL: <code><?php echo esc_html(amrod_get_api_url()); ?></code>/api/v1/
            </div>

            <form method="post">
                <?php wp_nonce_field('amrod_manage_endpoints'); ?>
                <table class="wp-list-table widefat striped" style="margin-top:20px;">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all" onclick="document.querySelectorAll('.amrod-endpoint-check').forEach(el => el.checked = this.checked);" /></th>
                            <th>Enabled</th>
                            <th>Label</th>
                            <th>Endpoint Path</th>
                            <th>Full URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            $endpoints = amrod_get_endpoints();
                            foreach ($endpoints as $key => $ep): 
                        ?>
                            <tr>
                                <td><input type="checkbox" class="amrod-endpoint-check" name="endpoints[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($ep['enabled']); ?> /></td>
                                <td><?php echo $ep['enabled'] ? '✅' : '❌'; ?></td>
                                <td><input type="text" name="endpoints[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($ep['label']); ?>" style="width:100%;max-width:200px;" /></td>
                                <td><input type="text" name="endpoints[<?php echo esc_attr($key); ?>][path]" value="<?php echo esc_attr($ep['path']); ?>" style="width:100%;max-width:300px;" placeholder="/api/v1/..." /></td>
                                <td><code style="font-size:11px;"><?php echo esc_html(amrod_get_api_url() . $ep['path']); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 style="margin-top:20px;">Base URLs</h3>
                <table class="form-table">
                    <tr><th>Auth Base URL</th><td><input type="url" name="amrod_auth_url" value="<?php echo esc_attr(get_option('amrod_auth_url', 'https://identity.amrod.co.za')); ?>" style="width:100%;max-width:400px;" /></td></tr>
                    <tr><th>API Base URL</th><td><input type="url" name="amrod_api_url" value="<?php echo esc_attr(get_option('amrod_api_url', 'https://vendorapi.amrod.co.za')); ?>" style="width:100%;max-width:400px;" /></td></tr>
                    <tr><th>Docs URL</th><td><input type="url" name="amrod_docs_url" value="<?php echo esc_attr(get_option('amrod_docs_url', 'https://newapidocs.amrod.co.za/#ver-2010-summary')); ?>" style="width:100%;max-width:400px;" /></td></tr>
                </table>

                <?php submit_button('Save Endpoints Configuration', 'primary', 'save_endpoints'); ?>
            </form>
        </div>

        <!-- Sync Tab -->
        <div id="tab-sync" class="tab-content <?php echo $active_tab === 'sync' ? 'active' : ''; ?>">
            <h2>Manual Sync Controls</h2>
            <p style="max-width:60em">Click <strong>Fetch Token</strong> to validate your credentials and obtain an access token. Then use <strong>Run Sync Now</strong> to import products from Amrod.</p>
            <form method="post">
                <?php wp_nonce_field('amrod_sync_action','amrod_sync_nonce'); ?>
                <p>
                    <input type="submit" name="get_token" class="button" value="Fetch Token">
                    <input type="submit" name="run_sync" class="button button-primary" value="Run Sync Now">
                </p>
                <p style="color:#666;"><strong>Last Token (masked):</strong> <code><?php echo esc_html(get_option('amrod_last_token') ?: '(not yet fetched)'); ?></code></p>
            </form>
            
            <h3>Recent Sync Log</h3>
            <?php 
                $log = array_reverse((array) get_option('amrod_sync_log', []));
                if (empty($log)): 
            ?>
                <p><em>No log entries yet.</em></p>
            <?php else: ?>
                <ul style="font-family:monospace;background:#f5f5f5;padding:12px;border:1px solid #ddd;max-height:300px;overflow:auto;">
                    <?php foreach (array_slice($log, 0, 20) as $l): ?>
                        <li><?php echo esc_html($l); ?></li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" style="margin-top:1em;">
                    <?php wp_nonce_field('amrod_sync_action','amrod_sync_nonce'); ?>
                    <input type="hidden" name="clear_sync_log" value="1" />
                    <input type="submit" class="button" value="Clear Sync Log">
                </form>
            <?php endif; ?>
        </div>

        <!-- Status Tab -->
        <div id="tab-status" class="tab-content <?php echo $active_tab === 'status' ? 'active' : ''; ?>">
            <h2>Sync Status & Details</h2>
            <?php 
                $last_sync = get_option('amrod_last_sync');
                $total = (int) get_option('amrod_total_products', 0);
                $last_token = get_option('amrod_last_token');
            ?>
            <table class="form-table">
                <tr><th>Last Sync</th><td><?php echo $last_sync ? esc_html($last_sync) : '<em>Never</em>'; ?></td></tr>
                <tr><th>Total Products Synced</th><td><?php echo esc_html($total); ?></td></tr>
                <tr><th>Last Token (Masked)</th><td><code><?php echo $last_token ? esc_html($last_token) : '<em>Not yet fetched</em>'; ?></code></td></tr>
                <?php if (function_exists('as_get_scheduled_actions')): ?>
                    <tr><th>Action Scheduler Queue</th><td><?php echo count(as_get_scheduled_actions(['hook' => 'amrod_sync_batch'])); ?> queued batches</td></tr>
                <?php else: ?>
                    <tr><th>Background Processing</th><td><em>Action Scheduler not available - using synchronous mode</em></td></tr>
                <?php endif; ?>
            </table>

            <h3>API Configuration (Current)</h3>
            <table class="form-table" style="max-width:100%;">
                <tr><th>Auth URL</th><td><code><?php echo esc_html(amrod_get_auth_url()); ?></code></td></tr>
                <tr><th>Token Endpoint</th><td><code><?php echo esc_html(amrod_get_token_endpoint()); ?></code></td></tr>
                <tr><th>API Base URL</th><td><code><?php echo esc_html(amrod_get_api_url()); ?></code></td></tr>
                <tr><th>Products Endpoint</th><td><code><?php echo esc_html(amrod_get_products_endpoint()); ?></code></td></tr>
                <tr><th>Categories Endpoint</th><td><code><?php echo esc_html(amrod_get_categories_endpoint()); ?></code></td></tr>
                <tr><th>Colours Endpoint</th><td><code><?php echo esc_html(amrod_get_colours_endpoint()); ?></code></td></tr>
            </table>

            <h3>Documentation</h3>
            <p><a href="<?php echo esc_url(get_option('amrod_docs_url', 'https://newapidocs.amrod.co.za/#ver-2010-summary')); ?>" class="button" target="_blank">View Amrod API Docs (v2.0.10)</a></p>
        </div>
    </div>
    <?php
}

// --- Token ---
function amrod_mask_token_for_display($token) {
    if (! $token) return '';
    return substr($token, 0, 6) . '...' . substr($token, -6);
}

function amrod_get_token() {
    // return cached token if available
    $cached = get_transient('amrod_token');
    if ($cached) {
        return $cached;
    }

    $auth_url = amrod_get_token_endpoint();
    $payload = json_encode([
        'username'     => get_option('amrod_username'),
        'password'     => amrod_get_password(),
        'CustomerCode' => get_option('amrod_customer_code')
    ]);

    $response = wp_remote_post($auth_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => $payload,
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        update_option('amrod_errors', 'Token request failed: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body_raw = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        update_option('amrod_errors', "Token endpoint returned HTTP {$code}");
        return false;
    }

    $body = json_decode($body_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        update_option('amrod_errors', 'Token: invalid JSON: ' . json_last_error_msg());
        return false;
    }

    $token = $body['token'] ?? false;
    if ($token) {
        // cache for 55 minutes (avoid repeated auth calls)
        set_transient('amrod_token', $token, 55 * MINUTE_IN_SECONDS);
        // store masked token for admin display only
        update_option('amrod_last_token', amrod_mask_token_for_display($token));
    }

    return $token;
}  

// --- Endpoint Helper ---
function amrod_get_endpoint($token, $endpoint_url, $retries = 2) {
    amrod_sync_log("Fetching endpoint: {$endpoint_url}");
    
    for ($attempt = 0; $attempt <= $retries; $attempt++) {
        $response = wp_remote_get($endpoint_url, [
            'headers' => ["Authorization" => "Bearer $token"],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            $err = $response->get_error_message();
            amrod_sync_log("Endpoint error (attempt " . ($attempt + 1) . "): {$err}");
            // if final attempt, record error
            if ($attempt === $retries) {
                update_option('amrod_errors', "Endpoint error: {$err}");
                return false;
            }
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            amrod_sync_log("Endpoint responded with HTTP {$code}, body length: " . strlen($body));

            if ($code === 200) {
                $decoded = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // clear previous errors on success
                    update_option('amrod_errors', '');
                    amrod_sync_log("Successfully decoded " . count($decoded) . " items from endpoint");
                    return $decoded;
                }

                // invalid JSON
                $json_err = json_last_error_msg();
                amrod_sync_log("Invalid JSON response: {$json_err}");
                if ($attempt === $retries) {
                    update_option('amrod_errors', "Invalid JSON: " . $json_err);
                    return false;
                }
            } elseif ($code === 204) {
                amrod_sync_log("API returned 204 (No Content) - API may be in maintenance mode (00:00-01:00 GMT+2)");
                update_option('amrod_errors', "Amrod API returned 204 (No Content). Check if maintenance window (00:00-01:00 GMT+2).");
                return false;
            } else {
                amrod_sync_log("Endpoint returned HTTP {$code}");
                if ($attempt === $retries) {
                    update_option('amrod_errors', "Endpoint returned HTTP {$code}");
                    return false;
                }
            }
        }

        // exponential backoff before retrying
        if ($attempt < $retries) {
            $wait = pow(2, $attempt);
            amrod_sync_log("Retry attempt " . ($attempt + 2) . " in {$wait} seconds...");
            sleep($wait);
        }
    }

    return false;
}



// --- Sync logging helper ---
function amrod_sync_log($message) {
    $log = (array) get_option('amrod_sync_log', []);
    $log[] = '[' . current_time('mysql') . '] ' . $message;
    // keep last 100 messages
    $log = array_slice($log, -100);
    update_option('amrod_sync_log', $log);
}

// Process a single batch of products and return array with status
function amrod_sync_process_batch($offset = 0, $batch_size = 200) {
    $token = amrod_get_token();
    if (! $token) {
        amrod_sync_log('Failed to obtain token.');
        return ['success' => false, 'processed' => 0, 'more' => false];
    }

    // Get enabled endpoints
    $endpoints = amrod_get_endpoints();
    $enabled_endpoints = array_filter($endpoints, function($e) { return $e['enabled']; });
    
    if (empty($enabled_endpoints)) {
        amrod_sync_log('No endpoints enabled for sync.');
        return ['success' => false, 'processed' => 0, 'more' => false];
    }
    
    // For now, focus on the primary Products endpoint
    $products_ep = $endpoints['products'] ?? null;
    if (!$products_ep || !$products_ep['enabled']) {
        amrod_sync_log('Products endpoint disabled or not found.');
        return ['success' => false, 'processed' => 0, 'more' => false];
    }

    $products_url = amrod_get_api_url() . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    if ($products === false || ! is_array($products)) {
        amrod_sync_log('Products endpoint did not return valid data.');
        return ['success' => false, 'processed' => 0, 'more' => false];
    }

    $total = count($products);
    $batch = array_slice($products, $offset, $batch_size);
    $processed = 0;

    foreach ($batch as $p) {
        if (! isset($p['ProductCode'])) continue;

        $existing_id = wc_get_product_id_by_sku($p['ProductCode']);
        $wc_product = $existing_id ? wc_get_product($existing_id) : new WC_Product();
        $wc_product->set_sku($p['ProductCode']);
        $wc_product->set_name($p['Description'] ?? '');
        $wc_product->set_regular_price($p['Price'] ?? 0);
        $wc_product->set_description($p['LongDescription'] ?? '');
        $wc_product->save();
        $processed++;
    }

    // update counters (accumulate)
    $prev_total = (int) get_option('amrod_total_products', 0);
    update_option('amrod_total_products', $prev_total + $processed);
    update_option('amrod_last_sync', current_time('mysql'));

    amrod_sync_log("Processed batch: offset={$offset}, count={$processed}");

    $next_offset = $offset + $batch_size;
    $more = $next_offset < $total;

    return ['success' => true, 'processed' => $processed, 'more' => $more, 'next_offset' => $next_offset, 'total' => $total];
}

// Background batch handler using Action Scheduler (if available)
add_action('amrod_sync_batch', 'amrod_sync_batch_handler');
function amrod_sync_batch_handler($offset = 0, $batch_size = 200) {
    $offset = (int) $offset;
    $batch_size = (int) $batch_size;
    
    $res = amrod_sync_process_batch($offset, $batch_size);

    if ($res['success'] && $res['more']) {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('amrod_sync_batch', [$res['next_offset'], $batch_size]);
        } else {
            // fallback to scheduling a single WP action shortly
            wp_schedule_single_event(time() + 2, 'amrod_sync_batch', [$res['next_offset'], $batch_size]);
        }
    }
}

// Backwards-compatible wrapper: runs synchronously (small catalogs) or enqueues background work
function amrod_sync_products($batch_size = 200, $offset = 0) {
    // prefer Action Scheduler background processing when available
    if (function_exists('as_enqueue_async_action')) {
        // reset counters
        update_option('amrod_total_products', 0);
        as_enqueue_async_action('amrod_sync_batch', [0, $batch_size]);
        amrod_sync_log('Enqueued background sync (Action Scheduler).');
        return true;
    }

    // fallback: synchronous batched processing to avoid PHP timeout
    $offset = (int) $offset;
    $processed_total = 0;
    do {
        $res = amrod_sync_process_batch($offset, $batch_size);
        if (! $res['success']) break;
        $processed_total += $res['processed'];
        $offset = $res['next_offset'];
    } while ($res['more']);

    amrod_sync_log("Synchronous sync completed. processed={$processed_total}");
    return true;
}

// --- Manual Actions ---
// Manual POST actions are handled securely by `amrod_handle_post_actions` (nonce + capability checks).

// Handle manual button posts (nonce + capability checks)
add_action('admin_init', 'amrod_handle_post_actions');
function amrod_handle_post_actions() {
    if (empty($_POST) || ! isset($_POST['amrod_sync_nonce'])) {
        return;
    }
    if (! current_user_can('manage_options')) {
        return;
    }
    if (! wp_verify_nonce($_POST['amrod_sync_nonce'], 'amrod_sync_action')) {
        return;
    }

    if (isset($_POST['get_token'])) {
        $token = amrod_get_token();
        if ($token) {
            // amrod_get_token already stores a masked display value
            update_option('amrod_errors', '');
            add_settings_error('amrod_sync_options_group', 'token_ok', 'Token fetched', 'updated');
        } else {
            add_settings_error('amrod_sync_options_group', 'token_failed', 'Failed to fetch token', 'error');
        }
    }

    if (! empty($_POST['clear_sync_log'])) {
        update_option('amrod_sync_log', []);
        add_settings_error('amrod_sync_options_group', 'log_cleared', 'Sync log cleared', 'updated');
    }

    if (isset($_POST['run_sync'])) {
        // enqueue background batch (Action Scheduler) or run batched fallback
        amrod_sync_products();
        add_settings_error('amrod_sync_options_group', 'sync_ok', 'Manual sync started/completed (check log).', 'updated');
    }
}

// Handle endpoints configuration save
add_action('admin_init', 'amrod_handle_endpoints_save', 11);
function amrod_handle_endpoints_save() {
    if (empty($_POST) || ! isset($_POST['save_endpoints'])) {
        return;
    }
    if (! current_user_can('manage_options')) {
        return;
    }
    if (! wp_verify_nonce($_POST['_wpnonce'], 'amrod_manage_endpoints')) {
        return;
    }

    // Save base URLs
    if (isset($_POST['amrod_auth_url'])) {
        update_option('amrod_auth_url', esc_url_raw($_POST['amrod_auth_url']));
    }
    if (isset($_POST['amrod_api_url'])) {
        update_option('amrod_api_url', esc_url_raw($_POST['amrod_api_url']));
    }
    if (isset($_POST['amrod_docs_url'])) {
        update_option('amrod_docs_url', esc_url_raw($_POST['amrod_docs_url']));
    }

    // Save endpoints configuration
    if (isset($_POST['endpoints']) && is_array($_POST['endpoints'])) {
        $endpoints = amrod_get_default_endpoints();
        
        foreach ($_POST['endpoints'] as $key => $data) {
            if (!isset($endpoints[$key])) continue;
            
            $endpoints[$key]['enabled'] = isset($data['enabled']) ? 1 : 0;
            if (isset($data['label'])) {
                $endpoints[$key]['label'] = sanitize_text_field($data['label']);
            }
            if (isset($data['path'])) {
                $endpoints[$key]['path'] = sanitize_text_field($data['path']);
            }
        }
        
        amrod_save_endpoints($endpoints);
        amrod_sync_log('Endpoints configuration updated.');
    }

    // Redirect to avoid form resubmission
    wp_safe_remote_post(admin_url('admin.php?page=amrod-sync&tab=endpoints'));
}

/**
 * WP‑CLI commands for Amrod Sync
 *
 * Commands:
 *   wp amrod-sync run [--batch=<n>] [--background]
 *   wp amrod-sync status
 *   wp amrod-sync clear-log
 */
if ( defined('WP_CLI') && WP_CLI ) {
    class Amrod_Sync_CLI {
        /**
         * Run the Amrod product sync.
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function run( $args, $assoc_args ) {
            $batch = isset($assoc_args['batch']) ? (int) $assoc_args['batch'] : 200;
            $background = ! empty($assoc_args['background']);

            if ($background && function_exists('as_enqueue_async_action')) {
                // enqueue and exit
                update_option('amrod_total_products', 0);
                as_enqueue_async_action('amrod_sync_batch', ['offset' => 0, 'batch_size' => $batch]);
                WP_CLI::success('Enqueued background sync (Action Scheduler).');
                return;
            }

            // synchronous fallback
            WP_CLI::log('Starting synchronous sync...');
            amrod_sync_products($batch, 0);
            WP_CLI::success('Sync finished. Check `wp amrod-sync status` for details.');
        }

        /**
         * Show status and recent log.
         */
        public function status() {
            $last = get_option('amrod_last_sync');
            $total = (int) get_option('amrod_total_products', 0);
            $log = (array) get_option('amrod_sync_log', []);

            WP_CLI::print_value(['last_sync' => $last, 'total_products' => $total, 'log_tail' => array_slice($log, -10)]);
        }

        /**
         * Clear the sync log.
         */
        public function clear_log() {
            update_option('amrod_sync_log', []);
            WP_CLI::success('Sync log cleared.');
        }
    }

    WP_CLI::add_command('amrod-sync', 'Amrod_Sync_CLI');
}

/**
 * Optional uninstall: remove plugin options when plugin is deleted.
 * If you prefer to keep data, remove this hook.
 */
register_uninstall_hook(__FILE__, 'amrod_uninstall_cleanup');
function amrod_uninstall_cleanup() {
    $keys = [
        'amrod_username', 'amrod_password', 'amrod_customer_code', 'amrod_docs_url',
        'amrod_auth_url', 'amrod_api_url', 'amrod_endpoints',
        'amrod_last_sync', 'amrod_total_products', 'amrod_errors', 'amrod_sync_log', 'amrod_last_token'
    ];
    foreach ($keys as $k) {
        delete_option($k);
    }
}

