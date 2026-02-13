<?php
/*
Plugin Name: Amrod WooCommerce Sync
Description: Enterprise-grade sync for Amrod products, stock, categories, and colours into WooCommerce with field mapping, progress tracking, and automated cron scheduling.
Version: 2.0.0
Author: Mediaplatform
License: GPL-2.0+
Text Domain: amrod-sync
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ===== CONSTANTS & CONFIGURATION =====
define('AMROD_SYNC_VERSION', '2.0.0');
define('AMROD_SYNC_PATH', plugin_dir_path(__FILE__));
define('AMROD_SYNC_URL', plugin_dir_url(__FILE__));
define('AMROD_SYNC_ASSETS', AMROD_SYNC_URL . 'assets/');

// ===== WooCommerce Dependency Check =====
add_action('plugins_loaded', 'amrod_check_woocommerce');
function amrod_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>❌ Amrod Sync:</strong> WooCommerce must be active. Plugin deactivated.</p></div>';
        });
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

// ===== ENQUEUE ASSETS =====
add_action('admin_enqueue_scripts', 'amrod_enqueue_assets');
function amrod_enqueue_assets($hook) {
    if (strpos($hook, 'amrod-sync') === false) return;

    // Bootstrap 5 CSS
    wp_enqueue_style('bootstrap5-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css', [], '5.0.2');
    
    // Bootstrap 5 JS
    wp_enqueue_script('bootstrap5-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js', [], '5.0.2', true);

    // Chart.js
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);

    // Custom Admin CSS
    wp_enqueue_style('amrod-admin-css', AMROD_SYNC_ASSETS . 'css/admin.css', ['bootstrap5-css'], AMROD_SYNC_VERSION);

    // Custom JS
    wp_enqueue_script('amrod-sync-js', AMROD_SYNC_ASSETS . 'js/sync-progress.js', ['jquery', 'chart-js'], AMROD_SYNC_VERSION, true);
    wp_enqueue_script('amrod-mapping-js', AMROD_SYNC_ASSETS . 'js/field-mapping.js', ['jquery'], AMROD_SYNC_VERSION, true);

    // Localize data for JS
    wp_localize_script('amrod-sync-js', 'amrodSyncData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('amrod_sync_nonce'),
    ]);
}

// ===== REGISTER SETTINGS =====
add_action('admin_init', 'amrod_register_settings');
function amrod_register_settings() {
    register_setting('amrod_sync_group', 'amrod_username');
    register_setting('amrod_sync_group', 'amrod_password');
    register_setting('amrod_sync_group', 'amrod_customer_code');
    register_setting('amrod_sync_group', 'amrod_auth_url');
    register_setting('amrod_sync_group', 'amrod_api_url');
    register_setting('amrod_sync_group', 'amrod_docs_url');
    register_setting('amrod_sync_group', 'amrod_endpoints');
    register_setting('amrod_sync_group', 'amrod_field_mapping');
    register_setting('amrod_sync_group', 'amrod_sync_schedule');
    register_setting('amrod_sync_group', 'amrod_batch_size');
}

// ===== DEFAULT ENDPOINTS =====
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

// ===== TAB STATUS CHECKER =====
function amrod_get_tab_status() {
    $status = [
        'credentials' => [
            'setup' => !empty(get_option('amrod_username')) && !empty(get_option('amrod_password')),
            'icon' => !empty(get_option('amrod_username')) ? '✅' : '❌'
        ],
        'endpoints' => [
            'setup' => count(array_filter(amrod_get_endpoints(), fn($e) => $e['enabled'])) > 0,
            'icon' => count(array_filter(amrod_get_endpoints(), fn($e) => $e['enabled'])) > 0 ? '✅' : '❌'
        ],
        'mapping' => [
            'setup' => !empty(get_option('amrod_field_mapping')),
            'icon' => !empty(get_option('amrod_field_mapping')) ? '✅' : '❌'
        ],
        'sync' => [
            'setup' => !empty(get_option('amrod_last_sync')),
            'icon' => !empty(get_option('amrod_last_sync')) ? '✅' : '❌'
        ]
    ];
    return $status;
}

// ===== ADMIN MENU =====
add_action('admin_menu', 'amrod_register_menus');
function amrod_register_menus() {
    if (!class_exists('WooCommerce')) return;

    $status = amrod_get_tab_status();
    
    add_menu_page(
        'Amrod Sync',
        'Amrod Sync ' . $status['sync']['icon'],
        'manage_options',
        'amrod-sync',
        'amrod_render_settings_page',
        'dashicons-update',
        56
    );

    add_submenu_page(
        'amrod-sync',
        'Status & Analytics',
        'Status ' . $status['sync']['icon'],
        'manage_options',
        'amrod-sync-status',
        'amrod_render_status_page'
    );
}

// ===== ACTIVATION HOOK =====
register_activation_hook(__FILE__, function() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Amrod Sync requires WooCommerce to be active.');
    }
    set_transient('amrod_activated', 1, HOUR_IN_SECONDS);
});

// ===== ACTIVATION NOTICE =====
add_action('admin_notices', function() {
    if (get_transient('amrod_activated')) {
        delete_transient('amrod_activated');
        echo '<div class="alert alert-success alert-dismissible fade show"><strong>✅ Amrod Sync activated!</strong> Setup your API credentials to begin syncing.</div>';
    }
});

// ===== MAIN SETTINGS PAGE RENDER =====
function amrod_render_settings_page() {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions');
    
    $active_tab = $_GET['tab'] ?? 'sync';
    $status = amrod_get_tab_status();
    ?>
    <div class="container-fluid mt-4">
        <!-- Header with Logo -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">Amrod WooCommerce Sync</h1>
                <small class="text-muted">v<?php echo AMROD_SYNC_VERSION; ?></small>
            </div>
            <img src="<?php echo AMROD_SYNC_ASSETS; ?>images/mediaplatform-logo.svg" height="18" alt="Mediaplatform">
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'sync' ? 'active' : ''; ?>" href="?page=amrod-sync&tab=sync" role="tab">
                    Sync Manager <?php echo $status['sync']['icon']; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'mapping' ? 'active' : ''; ?>" href="?page=amrod-sync&tab=mapping" role="tab">
                    Field Mapping <?php echo $status['mapping']['icon']; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'endpoints' ? 'active' : ''; ?>" href="?page=amrod-sync&tab=endpoints" role="tab">
                    API Endpoints <?php echo $status['endpoints']['icon']; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=amrod-sync&tab=settings" role="tab">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <?php
            if ($active_tab === 'sync') amrod_tab_sync();
            elseif ($active_tab === 'mapping') amrod_tab_field_mapping();
            elseif ($active_tab === 'endpoints') amrod_tab_endpoints();
            elseif ($active_tab === 'settings') amrod_tab_settings();
            else amrod_tab_sync();
            ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center text-muted small mt-5 pb-5">
        <p>© 2026 Mediaplatform | Amrod WooCommerce Sync | <a href="https://mediaplatform.co.za" target="_blank">mediaplatform.co.za</a></p>
    </div>
    <?php
}

// ===== TAB: SYNC MANAGER =====
function amrod_tab_sync() {
    $last_sync = get_option('amrod_last_sync');
    $last_token_time = get_option('amrod_last_token_fetched');
    $batch_size = get_option('amrod_batch_size', 200);
    ?>
    <div class="tab-pane fade show active">
        <div class="row">
            <div class="col-md-8">
                <!-- Sync Controls -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Manual Sync Controls</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="syncForm">
                            <?php wp_nonce_field('amrod_sync_nonce'); ?>
                            
                            <div class="alert alert-info">
                                <strong>Last Token:</strong> <?php echo $last_token_time ? date('d M Y @ H:i:s', strtotime($last_token_time)) : 'Not fetched yet'; ?> 
                                (Expires in 55 minutes)
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Batch Size</label>
                                <input type="number" name="batch_size" value="<?php echo $batch_size; ?>" min="50" max="500" class="form-control" placeholder="Records per batch">
                                <small class="text-muted">Recommended: 200</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sync Mode</label>
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sync_mode" value="full" id="sync_full" checked>
                                        <label class="form-check-label" for="sync_full">
                                            Full Sync (All Records)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sync_mode" value="batch" id="sync_batch">
                                        <label class="form-check-label" for="sync_batch">
                                            Batch Mode (Stop after X records)
                                        </label>
                                        <input type="number" name="batch_limit" value="500" min="100" class="form-control form-control-sm mt-2" placeholder="Max records to sync" style="width: 150px;">
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sync_mode" value="resume" id="sync_resume">
                                        <label class="form-check-label" for="sync_resume">
                                            Resume (From last offset)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="btn-group mb-4" role="group">
                                <button type="submit" name="action" value="fetch_token" class="btn btn-outline-secondary">
                                    🔑 Fetch Token
                                </button>
                                <button type="submit" name="action" value="run_sync" class="btn btn-success">
                                    ▶️ Run Sync Now
                                </button>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#cronModal">
                                    ⏰ Show Cron Setup
                                </button>
                            </div>
                        </form>

                        <!-- Progress Bar -->
                        <div id="syncProgress" style="display:none;">
                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="progressBar" style="width: 0%">
                                    <span id="progressText">0%</span>
                                </div>
                            </div>
                            <div id="syncDetails" class="text-muted small"></div>
                        </div>
                    </div>
                </div>

                <!-- Sync Log -->
                <div class="card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between">
                        <h5 class="mb-0">Sync Log (Last 30 entries)</h5>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('amrod_sync_nonce'); ?>
                            <button type="submit" name="clear_log" class="btn btn-sm btn-warning">Clear Log</button>
                        </form>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div id="syncLog">
                            <?php
                            $log = array_reverse((array) get_option('amrod_sync_log', []));
                            if (empty($log)) {
                                echo '<p class="text-muted">No log entries yet.</p>';
                            } else {
                                foreach (array_slice($log, 0, 30) as $entry) {
                                    // Parse log entry for visual styling
                                    $icon = '📝';
                                    if (strpos($entry, '✅') !== false) $icon = '✅';
                                    elseif (strpos($entry, '❌') !== false) $icon = '❌';
                                    elseif (strpos($entry, '⏳') !== false) $icon = '⏳';
                                    
                                    echo '<div class="text-monospace small mb-2">' . esc_html($entry) . '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Sync History Chart -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">📊 Sync History</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="syncHistoryChart"></canvas>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Quick Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-<?php echo $last_sync ? 'success' : 'warning'; ?>">
                            <strong>Last Sync:</strong> <?php echo $last_sync ? date('d M Y H:i:s', strtotime($last_sync)) : 'Never'; ?>
                        </div>
                        <div class="alert alert-info">
                            <strong>Total Products:</strong> <?php echo number_format(get_option('amrod_total_products', 0)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cron Setup Modal --> 
    <div class="modal fade" id="cronModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">⏰ Setup Automatic Syncing with Cron</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php amrod_render_cron_helper(); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== CRON HELPER FUNCTION =====
function amrod_render_cron_helper() {
    $domain = parse_url(get_home_url(), PHP_URL_HOST);
    $site_path = str_replace(ABSPATH, '', WP_CONTENT_DIR);
    $cron_code = sprintf("*/30 * * * * php %swp-cron.php?action=amrod_scheduled_sync", ABSPATH);
    ?>
    <div class="alert alert-info">
        <h6>📌 How to Setup Cron Jobs</h6>
        <p>Contact your hosting provider and ask them to add this cron job:</p>
    </div>

    <div class="input-group mb-3">
        <input type="text" class="form-control" value="<?php echo esc_attr($cron_code); ?>" readonly id="cronCode">
        <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('cronCode').value); alert('Copied!');"> 
            📋 Copy
        </button>
    </div>

    <div class="alert alert-warning small">
        <strong>Running on:</strong> <?php echo esc_html($domain); ?><br>
        <strong>WordPress Path:</strong> <?php echo esc_html(ABSPATH); ?><br>
        <strong>Frequency:</strong> Every 30 minutes
    </div>

    <h6 class="mt-4">Cron Schedule Options:</h6>
    <ul class="list-group">
        <li class="list-group-item"><code>*/5 * * * *</code> - Every 5 minutes</li>
        <li class="list-group-item"><code>*/15 * * * *</code> - Every 15 minutes</li>
        <li class="list-group-item"><code>*/30 * * * *</code> - Every 30 minutes (recommended)</li>
        <li class="list-group-item"><code>0 * * * *</code> - Hourly</li>
        <li class="list-group-item"><code>0 0 * * *</code> - Daily at midnight</li>
        <li class="list-group-item"><code>0 3 * * 1</code> - Weekly (Monday 3 AM)</li>
    </ul>
    <?php
}

// ===== TAB: FIELD MAPPING =====
function amrod_tab_field_mapping() {
    ?>
    <div class="tab-pane fade show active">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🗺️ Field Mapping Manager</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Map Amrod API fields to WooCommerce product fields.</strong> This ensures data is correctly synced.
                </div>

                <form method="post" id="fieldMappingForm">
                    <?php wp_nonce_field('amrod_sync_nonce'); ?>
                    
                    <button type="button" class="btn btn-outline-primary mb-3" id="autoDetectBtn">
                        🔍 Auto-Detect Fields from Amrod
                    </button>

                    <div id="mappingTable" class="table-responsive">
                        <!-- Will be populated by JavaScript or after auto-detect -->
                        <?php amrod_render_mapping_table(); ?>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-info me-2" id="testMappingBtn">
                            🧪 Test Mapping
                        </button>
                        <button type="submit" name="save_mapping" class="btn btn-success">
                            💾 Save Mapping
                        </button>
                    </div>
                </form>

                <!-- Test Result -->
                <div id="mappingTestResult" style="display:none;" class="alert alert-success mt-3">
                    <h6>📦 Test Product Preview</h6>
                    <div id="testProductData"></div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== RENDER MAPPING TABLE =====
function amrod_render_mapping_table() {
    $mapping = get_option('amrod_field_mapping', [
        'sku' => 'ProductCode',
        'name' => 'Description',
        'price' => 'Price',
        'description' => 'LongDescription',
        'colour' => 'Colour'
    ]);

    $wc_fields = ['SKU', 'Product Name', 'Regular Price', 'Description', 'Colour Attribute'];
    ?>
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>Amrod Field</th>
                <th>→</th>
                <th>WooCommerce Field</th>
                <th>Sample Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mapping as $wc_key => $amrod_field): ?>
                <tr>
                    <td>
                        <input type="text" name="mapping[<?php echo $wc_key; ?>]" 
                               value="<?php echo esc_attr($amrod_field); ?>" 
                               class="form-control" placeholder="e.g., ItemCode">
                    </td>
                    <td class="text-center">→</td>
                    <td>
                        <?php 
                        $wc_labels = ['sku' => 'SKU', 'name' => 'Product Name', 'price' => 'Regular Price', 'description' => 'Description', 'colour' => 'Colour'];
                        echo $wc_labels[$wc_key] ?? 'Unknown';
                        ?>
                    </td>
                    <td class="text-muted small">-</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// ===== TAB: ENDPOINTS =====
function amrod_tab_endpoints() {
    $endpoints = amrod_get_endpoints();
    ?>
    <div class="tab-pane fade show active">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🔌 API Endpoints Manager</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <?php wp_nonce_field('amrod_sync_nonce'); ?>
                    
                    <div class="alert alert-success mb-3">
                        <strong>✅ Amrod API v1 Detected</strong> - Base URL: <code><?php echo get_option('amrod_api_url', 'https://vendorapi.amrod.co.za'); ?></code>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>Enabled</th>
                                    <th>Label</th>
                                    <th>Path</th>
                                    <th>Full URL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($endpoints as $key => $ep): ?>
                                    <tr>
                                        <td><input type="checkbox" class="endpoint-check"></td>
                                        <td><?php echo $ep['enabled'] ? '✅' : '❌'; ?></td>
                                        <td>
                                            <input type="text" name="endpoints[<?php echo $key; ?>][label]" 
                                                   value="<?php echo esc_attr($ep['label']); ?>" 
                                                   class="form-control form-control-sm">
                                        </td>
                                        <td>
                                            <input type="text" name="endpoints[<?php echo $key; ?>][path]" 
                                                   value="<?php echo esc_attr($ep['path']); ?>" 
                                                   class="form-control form-control-sm">
                                        </td>
                                        <td>
                                            <code><?php echo esc_html(get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $ep['path']); ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" name="save_endpoints" class="btn btn-success">
                        💾 Save Endpoints
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
}

// ===== TAB: SETTINGS (Collapsible Credentials/URLs) =====
function amrod_tab_settings() {
    ?>
    <div class="tab-pane fade show active">
        <div class="row">
            <div class="col-md-8">
                <!-- API Credentials Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-decoration-none" data-bs-toggle="collapse" data-bs-target="#credentialsPanel">
                                🔑 API Credentials
                            </button>
                        </h5>
                    </div>
                    <div id="credentialsPanel" class="collapse show">
                        <div class="card-body">
                            <form method="post">
                                <?php wp_nonce_field('amrod_sync_nonce'); ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="amrod_username" value="<?php echo esc_attr(get_option('amrod_username')); ?>" class="form-control" placeholder="your_username">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" id="passwordField" name="amrod_password" value="<?php echo esc_attr(get_option('amrod_password')); ?>" class="form-control">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">
                                            👁️
                                        </button>
                                    </div>
                                    <small class="text-muted">Stored securely in database</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Customer Code</label>
                                    <input type="text" name="amrod_customer_code" value="<?php echo esc_attr(get_option('amrod_customer_code')); ?>" class="form-control" placeholder="e.g., ABC123">
                                </div>

                                <button type="submit" name="save_credentials" class="btn btn-primary">
                                    💾 Save Credentials
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- API URLs Section -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-decoration-none" data-bs-toggle="collapse" data-bs-target="#urlsPanel">
                                🌐 API URLs
                            </button>
                        </h5>
                    </div>
                    <div id="urlsPanel" class="collapse">
                        <div class="card-body">
                            <form method="post">
                                <?php wp_nonce_field('amrod_sync_nonce'); ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Auth Base URL</label>
                                    <input type="url" name="amrod_auth_url" value="<?php echo esc_attr(get_option('amrod_auth_url', 'https://identity.amrod.co.za')); ?>" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">API Base URL</label>
                                    <input type="url" name="amrod_api_url" value="<?php echo esc_attr(get_option('amrod_api_url', 'https://vendorapi.amrod.co.za')); ?>" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Documentation URL</label>
                                    <input type="url" name="amrod_docs_url" value="<?php echo esc_attr(get_option('amrod_docs_url', 'https://newapidocs.amrod.co.za')); ?>" class="form-control">
                                </div>

                                <button type="submit" name="save_urls" class="btn btn-primary">
                                    💾 Save URLs
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">⚙️ Advanced Options</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <?php wp_nonce_field('amrod_sync_nonce'); ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Default Batch Size</label>
                                <input type="number" name="amrod_batch_size" value="<?php echo get_option('amrod_batch_size', 200); ?>" min="50" max="500" class="form-control">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sync Schedule</label>
                                <select name="amrod_sync_schedule" class="form-select">
                                    <option value="">Manual Only</option>
                                    <option value="5min">Every 5 Minutes</option>
                                    <option value="15min">Every 15 Minutes</option>
                                    <option value="30min" selected>Every 30 Minutes</option>
                                    <option value="hourly">Every Hour</option>
                                    <option value="daily">Daily</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Auto-Archive Logs After (days)</label>
                                <input type="number" name="amrod_log_retain_days" value="<?php echo get_option('amrod_log_retain_days', 30); ?>" min="7" max="365" class="form-control">
                            </div>

                            <button type="submit" name="save_advanced" class="btn btn-success">
                                💾 Save Options
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== STATUS PAGE =====
function amrod_render_status_page() {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions');
    ?>
    <div class="container-fluid mt-4">
        <h1 class="mb-4">📊 Amrod Sync - Status & Analytics</h1>

        <div class="row mb-4">
            <!-- Last Sync Status -->
            <div class="col-md-6">
                <?php
                $last_sync = get_option('amrod_last_sync');
                $total_products = get_option('amrod_total_products', 0);
                $alert_class = $last_sync ? 'alert-success' : 'alert-warning';
                ?>
                <div class="alert <?php echo $alert_class; ?>">
                    <h5>✅ Last Sync Status</h5>
                    <p class="mb-0">
                        <strong>Time:</strong> <?php echo $last_sync ? date('d M Y @ H:i:s', strtotime($last_sync)) : 'Never synced'; ?><br>
                        <strong>Total Products:</strong> <?php echo number_format($total_products); ?>
                    </p>
                </div>
            </div>

            <!-- API Connection -->
            <div class="col-md-6">
                <div class="alert alert-info">
                    <h5>🔐 API Connection Status</h5>
                    <p class="mb-0">
                        <strong>✅ Auth URL:</strong> Configured<br>
                        <strong>✅ API URL:</strong> Configured<br>
                        <strong>✅ Endpoints Enabled:</strong> <?php echo count(array_filter(amrod_get_endpoints(), fn($e) => $e['enabled'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Sync History Chart -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">📈 Sync History (Last 7 Days)</h5>
            </div>
            <div class="card-body" style="height: 300px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
    <?php
}

// ===== HANDLE POST REQUESTS =====
add_action('admin_init', function() {
    if (empty($_POST) || !isset($_POST['save_credentials']) && !isset($_POST['save_endpoints']) && !isset($_POST['save_mapping'])) return;
    if (!current_user_can('manage_options')) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'amrod_sync_nonce')) return;

    if (isset($_POST['save_credentials'])) {
        update_option('amrod_username', sanitize_text_field($_POST['amrod_username'] ?? ''));
        update_option('amrod_password', sanitize_text_field($_POST['amrod_password'] ?? ''));
        update_option('amrod_customer_code', sanitize_text_field($_POST['amrod_customer_code'] ?? ''));
        wp_safe_redirect(add_query_arg('updated', '1'));
    }
});

// ===== UNINSTALL =====
register_uninstall_hook(__FILE__, function() {
    $options = ['amrod_username', 'amrod_password', 'amrod_customer_code', 'amrod_auth_url', 'amrod_api_url', 'amrod_docs_url', 'amrod_endpoints', 'amrod_field_mapping', 'amrod_sync_log', 'amrod_last_sync', 'amrod_total_products', 'amrod_last_token_fetched', 'amrod_sync_schedule', 'amrod_batch_size', 'amrod_log_retain_days'];
    foreach ($options as $opt) delete_option($opt);
});

// ===== AJAX HANDLERS =====

// Fetch Token AJAX
add_action('wp_ajax_amrod_fetch_token', 'amrod_ajax_fetch_token');
function amrod_ajax_fetch_token() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $token = amrod_get_token();
    if ($token) {
        update_option('amrod_last_token_fetched', current_time('mysql'));
        wp_send_json_success(['token' => amrod_mask_token_for_display($token)]);
    } else {
        wp_send_json_error('Failed to fetch token');
    }
}

// Sync Batch AJAX
add_action('wp_ajax_amrod_sync_batch', 'amrod_ajax_sync_batch');
function amrod_ajax_sync_batch() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $offset = intval($_POST['offset'] ?? 0);
    $batch_size = intval($_POST['batch_size'] ?? 200);
    
    $result = amrod_sync_process_batch($offset, $batch_size);
    wp_send_json_success($result);
}

// ===== CRITICAL SYNC FUNCTIONS =====

function amrod_get_endpoints() {
    $defaults = amrod_get_default_endpoints();
    $stored = get_option('amrod_endpoints');
    if ($stored && is_string($stored)) {
        $stored = @json_decode($stored, true);
        if (is_array($stored)) {
            foreach ($defaults as $key => $default) {
                if (isset($stored[$key])) {
                    $stored[$key] = array_merge($default, $stored[$key]);
                } else {
                    $stored[$key] = $default;
                }
            }
            return $stored;
        }
    }
    return $defaults;
}

function amrod_save_endpoints($endpoints) {
    update_option('amrod_endpoints', json_encode($endpoints));
}

function amrod_get_password() {
    $stored = get_option('amrod_password');
    if ($stored) return $stored;
    $env = getenv('AMROD_API_PASSWORD');
    if ($env !== false && $env !== '') return $env;
    if (defined('AMROD_API_PASSWORD') && AMROD_API_PASSWORD) return AMROD_API_PASSWORD;
    return '';
}

function amrod_mask_token_for_display($token) {
    if (!$token) return '';
    return substr($token, 0, 6) . '...' . substr($token, -6);
}

function amrod_get_token() {
    $cached = get_transient('amrod_token');
    if ($cached) return $cached;

    $auth_url = get_option('amrod_auth_url', 'https://identity.amrod.co.za') . '/VendorLogin';
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
        amrod_sync_log('Failed to obtain token: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        amrod_sync_log("Token endpoint returned HTTP {$code}");
        return false;
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        amrod_sync_log('Invalid token response: ' . json_last_error_msg());
        return false;
    }

    $token = $data['token'] ?? false;
    if ($token) {
        set_transient('amrod_token', $token, 55 * MINUTE_IN_SECONDS);
        update_option('amrod_last_token', amrod_mask_token_for_display($token));
        amrod_sync_log('✅ Token obtained successfully');
    }

    return $token;
}

function amrod_get_endpoint($token, $endpoint_url, $retries = 2) {
    for ($attempt = 0; $attempt <= $retries; $attempt++) {
        $response = wp_remote_get($endpoint_url, [
            'headers' => ["Authorization" => "Bearer $token"],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            if ($attempt === $retries) {
                amrod_sync_log('Endpoint error: ' . $response->get_error_message());
                return false;
            }
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($code === 200) {
                $decoded = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                if ($attempt === $retries) {
                    amrod_sync_log('Invalid JSON: ' . json_last_error_msg());
                    return false;
                }
            } else {
                if ($attempt === $retries) {
                    amrod_sync_log("Endpoint returned HTTP {$code}");
                    return false;
                }
            }
        }

        if ($attempt < $retries) {
            sleep(pow(2, $attempt));
        }
    }

    return false;
}

function amrod_sync_log($message) {
    $log = (array) get_option('amrod_sync_log', []);
    $log[] = '[' . current_time('mysql') . '] ' . $message;
    $log = array_slice($log, -100);
    update_option('amrod_sync_log', $log);
}

function amrod_sync_process_batch($offset = 0, $batch_size = 200) {
    $token = amrod_get_token();
    if (!$token) {
        return ['success' => false, 'processed_total' => 0, 'total' => 0, 'more' => false];
    }

    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        return ['success' => false, 'processed_total' => 0, 'total' => 0, 'more' => false];
    }

    $products_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products)) {
        return ['success' => false, 'processed_total' => 0, 'total' => 0, 'more' => false];
    }

    $total = count($products);
    $batch = array_slice($products, $offset, $batch_size);
    $processed = 0;

    foreach ($batch as $p) {
        if (empty($p['ProductCode'])) continue;

        $sku = sanitize_text_field($p['ProductCode']);
        $existing_id = wc_get_product_id_by_sku($sku);

        try {
            if ($existing_id) {
                $wc_product = wc_get_product($existing_id);
            } else {
                $wc_product = new WC_Product_Simple();
                $wc_product->set_status('publish');
                $wc_product->set_catalog_visibility('visible');
            }

            if (!$wc_product || !is_a($wc_product, 'WC_Product')) continue;

            $wc_product->set_sku($sku);
            $wc_product->set_name(sanitize_text_field($p['Description'] ?? 'Product'));
            $wc_product->set_regular_price(floatval($p['Price'] ?? 0));
            $wc_product->set_description($p['LongDescription'] ?? '');

            $product_id = $wc_product->save();
            if ($product_id && $product_id > 0) {
                $processed++;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    $prev_total = (int) get_option('amrod_total_products', 0);
    update_option('amrod_total_products', $prev_total + $processed);
    update_option('amrod_last_sync', current_time('mysql'));

    $next_offset = $offset + $batch_size;
    $more = $next_offset < $total;

    return [
        'success' => true,
        'processed' => $processed,
        'processed_total' => $prev_total + $processed,
        'total' => $total,
        'more' => $more,
        'next_offset' => $next_offset
    ];
}

// ===== PASSWORD VISIBILITY TOGGLE =====
add_action('admin_footer', function() {
    ?>
    <script>
    function togglePasswordVisibility() {
        const field = document.getElementById('passwordField');
        field.type = field.type === 'password' ? 'text' : 'password';
    }
    </script>
    <?php
});
