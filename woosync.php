<?php
/*
Plugin Name: Amrod WooCommerce Sync
Description: Enterprise-grade sync for Amrod products, stock, categories, and colours into WooCommerce with field mapping, progress tracking, and automated cron scheduling.
Version: 3.4.1
Author: Mediaplatform
License: GPL-2.0+
Text Domain: amrod-sync
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ===== CONSTANTS & CONFIGURATION =====
define('AMROD_SYNC_VERSION', '3.4.1');
define('AMROD_SYNC_PATH', plugin_dir_path(__FILE__));
define('AMROD_SYNC_URL', plugin_dir_url(__FILE__));
define('AMROD_SYNC_ASSETS', AMROD_SYNC_URL . 'assets/');
// ===== AUTO-UPDATER =====
require_once AMROD_SYNC_PATH . 'includes/class-woosync-updater.php';
new WooSync_Updater(AMROD_SYNC_VERSION, plugin_basename(__FILE__), AMROD_SYNC_PATH);


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
    wp_enqueue_script('amrod-connect-map-js', AMROD_SYNC_ASSETS . 'js/connect-map.js', ['jquery'], AMROD_SYNC_VERSION, true);
    wp_enqueue_script('amrod-wizard-js', AMROD_SYNC_ASSETS . 'js/wizard.js', ['jquery', 'bootstrap5-js'], AMROD_SYNC_VERSION, true);
    wp_localize_script('amrod-wizard-js', 'amrodSyncData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('amrod_sync_nonce'),
        'assetsUrl' => AMROD_SYNC_ASSETS,
        'isWooSyncPage' => (strpos($hook, 'amrod-sync') !== false),
        'vendorTemplates' => array_values(amrod_get_vendor_templates()),
        'vendorCredentialSchemas' => amrod_get_vendor_credential_schemas(),
    ]);

    // Localize data for JS
    wp_localize_script('amrod-sync-js', 'amrodSyncData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('amrod_sync_nonce'),
        'assetsUrl' => AMROD_SYNC_ASSETS,
        'isWooSyncPage' => (strpos($hook, 'amrod-sync') !== false),
        'vendorTemplates' => array_values(amrod_get_vendor_templates()),
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
    register_setting('amrod_sync_group', 'amrod_auto_update');
    register_setting('amrod_sync_group', 'woosync_markup_percent');
    register_setting('amrod_sync_group', 'woosync_vendors');
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

// ===== AUTO-MAPPING FIELD RULES =====
function amrod_get_field_mapping_rules() {
    return [
        'sku' => ['patterns' => ['ProductCode', 'ItemCode', 'SKU', 'ArticeCode', 'ArticleCode', 'ProductId', 'ItemId'], 'confidence' => 100, 'description' => 'Unique product identifier'],
        'name' => ['patterns' => ['Description', 'ProductName', 'Name', 'Title', 'ProductDescription', 'ItemDescription'], 'confidence' => 100, 'description' => 'Product name/title'],
        'price' => ['patterns' => ['Price', 'UnitPrice', 'BasePrice', 'SellingPrice', 'ListPrice'], 'confidence' => 100, 'description' => 'Regular price'],
        'sale_price' => ['patterns' => ['SalePrice', 'DiscountPrice', 'SpecialPrice', 'PromoPrice', 'OfferPrice', 'ReducedPrice', 'SaleAmount'], 'confidence' => 90, 'description' => 'Sale/discounted price'],
        'description' => ['patterns' => ['LongDescription', 'Description', 'ProductDetails', 'Details', 'LongDesc', 'FullDescription'], 'confidence' => 100, 'description' => 'Full product description'],
        'short_description' => ['patterns' => ['ShortDescription', 'Summary', 'Excerpt', 'BriefDescription', 'Teaser'], 'confidence' => 80, 'description' => 'Short product summary'],
        'categories' => ['patterns' => ['CategoryName', 'Category', 'Categories', 'ProductCategory', 'CategoryPath', 'CategoryHierarchy', 'ProductGroup', 'GroupName', 'Collection'], 'confidence' => 100, 'description' => 'Product categories'],
        'brand' => ['patterns' => ['Brand', 'BrandName', 'Manufacturer', 'Make', 'Producer', 'Supplier', 'Vendor', 'BrandCode'], 'confidence' => 95, 'description' => 'Product brand/manufacturer'],
        'colour' => ['patterns' => ['Colour', 'Color', 'ColourSwatch', 'Swatch', 'SwatchName', 'ColourName', 'ColorName', 'ProductColor'], 'confidence' => 90, 'description' => 'Product colour attribute'],
        'size' => ['patterns' => ['Size', 'Dimensions', 'Dimension', 'Length', 'Width', 'Height', 'Weight', 'Capacity'], 'confidence' => 70, 'description' => 'Product size/dimensions'],
        'stock' => ['patterns' => ['Stock', 'StockLevel', 'Quantity', 'Qty', 'Available', 'AvailableStock', 'InStock', 'StockStatus'], 'confidence' => 90, 'description' => 'Stock quantity'],
        'image' => ['patterns' => ['ImageURL', 'Image', 'Photo', 'Picture', 'ProductImage', 'MainImage', 'ImageLink', 'ImagePath', 'PhotoURL'], 'confidence' => 95, 'description' => 'Product main image'],
        'images' => ['patterns' => ['Images', 'ImageURLs', 'Gallery', 'PhotoGallery', 'ImageGallery', 'AdditionalImages'], 'confidence' => 85, 'description' => 'Product image gallery'],
        'clearance' => ['patterns' => ['Clearance', 'OnSale', 'Liquidate', 'Special', 'Closeout', 'ClearanceFlag', 'IsClearance', 'IsOnSale', 'IsSpecial', 'IsLiquidate', 'ClearanceDeal', 'EndOfLine'], 'confidence' => 95, 'description' => 'Clearance/closeout flag'],
        'deal_of_day' => ['patterns' => ['DealOfTheDay', 'DealOfTheWeek', 'DailyDeal', 'Featured', 'IsFeatured', 'HotDeal', 'TopDeal', 'SpecialDeal', 'IsDeal'], 'confidence' => 90, 'description' => 'Deal of the day/week flag'],
        'banner_image' => ['patterns' => ['BannerImage', 'HeroImage', 'MarketingImage', 'PromoImage', 'BannerImageURL', 'HeroBanner', 'SpotlightImage', 'AdImage'], 'confidence' => 85, 'description' => 'Marketing banner/hero image'],
        'catalog_pdf' => ['patterns' => ['CatalogPDF', 'BrochureURL', 'Catalogue', 'CatalogURL', 'CataloguePDF', 'PDFLink', 'Brochure', 'Leaflet', 'ProductPDF', 'FactSheet'], 'confidence' => 80, 'description' => 'Product catalog PDF URL'],
        'special_message' => ['patterns' => ['SpecialMessage', 'PromoMessage', 'MarketingMessage', 'OfferMessage', 'DealMessage', 'Tagline', 'PromoText', 'SpecialText'], 'confidence' => 75, 'description' => 'Special/marketing message'],
        'sort_order' => ['patterns' => ['SortOrder', 'Sort', 'DisplayOrder', 'Priority', 'Rank', 'Position', 'Sequence', 'OrderBy'], 'confidence' => 60, 'description' => 'Product display sort order'],
    ];
}


// ===== VENDOR TEMPLATES WITH SUPPORT DATA =====
function amrod_get_vendor_templates() {
    return [
        'amrod' => [
            'id' => 'amrod',
            'name' => 'Amrod',
            'icon' => '🏭',
            'description' => 'Premium branded merchandise supplier with full API access for products, stock, pricing, and branding.',
            'auth_url' => 'https://identity.amrod.co.za',
            'api_base_url' => 'https://vendorapi.amrod.co.za',
            'docs_url' => 'https://newapidocs.amrod.co.za',
            'support' => [
                'email' => 'support@amrod.co.za',
                'docs_url' => 'https://newapidocs.amrod.co.za',
                'phone' => '',
                'address' => '',
            ],
        ],
        'barron' => [
            'id' => 'barron',
            'name' => 'Barron',
            'icon' => '🏢',
            'description' => 'Corporate clothing and workwear supplier.',
            'auth_url' => '',
            'api_base_url' => '',
            'docs_url' => '',
            'support' => [
                'email' => '',
                'docs_url' => '',
                'phone' => '',
                'address' => '',
            ],
        ],
        'smd' => [
            'id' => 'smd',
            'name' => 'SMD',
            'icon' => '📦',
            'description' => 'Promotional products and branded giveaways with Bearer Token + ClientAccessKey authentication.',
            'auth_url' => '',
            'api_base_url' => 'https://api.smdtechnologies.com/v1/',
            'docs_url' => '',
            'auth_type' => 'bearer_key',
            'support' => [
                'email' => '(in progress — contact SMD directly)',
                'docs_url' => '(in progress)',
                'phone' => '',
                'address' => '',
            ],
        ],
    ];
}

// ===== VENDOR CREDENTIAL SCHEMAS =====
// Defines credential fields and auth types per vendor
function amrod_get_vendor_credential_schemas() {
    return [
        'amrod' => [
            'auth_type' => 'vendor_login',
            'label' => 'Amrod Vendor Login',
            'description' => 'Uses Amrod's VendorLogin endpoint with username, password, and customer code.',
            'fields' => [
                [
                    'key' => 'auth_url',
                    'label' => 'Auth URL',
                    'placeholder' => 'https://identity.amrod.co.za',
                    'prefill' => 'https://identity.amrod.co.za',
                    'required' => true,
                    'type' => 'url',
                    'help' => 'Amrod identity/authentication endpoint URL',
                ],
                [
                    'key' => 'username',
                    'label' => 'Username *',
                    'placeholder' => 'user@email.com',
                    'required' => true,
                    'type' => 'text',
                    'help' => 'Your Amrod API username (same as login email)',
                ],
                [
                    'key' => 'password',
                    'label' => 'Password *',
                    'placeholder' => '••••••••',
                    'required' => true,
                    'type' => 'password',
                    'help' => 'Your Amrod API password',
                ],
                [
                    'key' => 'customer_code',
                    'label' => 'Customer Code *',
                    'placeholder' => 'e.g., MEDIAPLATFORM',
                    'required' => true,
                    'type' => 'text',
                    'help' => 'Your Amrod customer code. Find it in your Amrod account or contact support.',
                ],
            ],
            'support' => [
                'email' => 'support@amrod.co.za',
                'docs' => 'https://newapidocs.amrod.co.za',
                'note' => 'Your customer code is in your Amrod account. Contact support@amrod.co.za if you don't have one.',
            ],
            'test_type' => 'vendor_login',
        ],
        'smd' => [
            'auth_type' => 'bearer_key',
            'label' => 'SMD Bearer Token',
            'description' => 'Uses SMD API with Bearer Token + ClientAccessKey authentication.',
            'fields' => [
                [
                    'key' => 'api_base_url',
                    'label' => 'API Base URL *',
                    'placeholder' => 'https://api.smdtechnologies.com/v1/',
                    'prefill' => 'https://api.smdtechnologies.com/v1/',
                    'required' => true,
                    'type' => 'url',
                    'help' => 'SMD API base URL',
                ],
                [
                    'key' => 'bearer_token',
                    'label' => 'Bearer Token *',
                    'placeholder' => 'Your API token from SMD',
                    'required' => true,
                    'type' => 'password',
                    'help' => 'Your SMD API Bearer Token. Contact SMD to get this.',
                ],
                [
                    'key' => 'client_access_key',
                    'label' => 'Client Access Key *',
                    'placeholder' => 'Your ClientAccessKey from SMD',
                    'required' => true,
                    'type' => 'password',
                    'help' => 'Your SMD ClientAccessKey. Contact SMD to get this.',
                ],
            ],
            'support' => [
                'email' => '(in progress — contact SMD directly)',
                'docs' => '(in progress)',
                'note' => 'SMD uses a Bearer Token + ClientAccessKey. You'll receive these via email from SMD.',
            ],
            'test_type' => 'bearer_key',
        ],
        'barron' => [
            'auth_type' => 'custom',
            'label' => 'Barron Custom Auth',
            'description' => 'Barron uses custom authentication. Contact Barron for API credentials.',
            'fields' => [
                [
                    'key' => 'api_base_url',
                    'label' => 'API Base URL *',
                    'placeholder' => 'https://api.barron.com/v1/',
                    'required' => true,
                    'type' => 'url',
                    'help' => 'Barron API base URL',
                ],
                [
                    'key' => 'api_key',
                    'label' => 'API Key (optional)',
                    'placeholder' => 'Your Barron API key',
                    'required' => false,
                    'type' => 'password',
                    'help' => 'Your Barron API key (if required)',
                ],
            ],
            'support' => [
                'email' => '(in progress — contact Barron directly)',
                'docs' => '(in progress)',
                'note' => 'Contact Barron directly for API credentials and documentation.',
            ],
            'test_type' => 'custom',
        ],
        'custom' => [
            'auth_type' => 'custom',
            'label' => 'Custom Vendor',
            'description' => 'Configure any REST API. Fill in only the fields your API requires.',
            'fields' => [
                [
                    'key' => 'api_base_url',
                    'label' => 'API Base URL *',
                    'placeholder' => 'https://api.example.com/v1/',
                    'required' => true,
                    'type' => 'url',
                    'help' => 'Base URL for your vendor's API',
                ],
                [
                    'key' => 'auth_url',
                    'label' => 'Auth URL (optional)',
                    'placeholder' => 'https://auth.example.com/token',
                    'required' => false,
                    'type' => 'url',
                    'help' => 'Authentication endpoint URL (if separate from API)',
                ],
                [
                    'key' => 'username',
                    'label' => 'Username (optional)',
                    'placeholder' => 'user@email.com',
                    'required' => false,
                    'type' => 'text',
                    'help' => 'API username (if using basic/vendor login auth)',
                ],
                [
                    'key' => 'password',
                    'label' => 'Password (optional)',
                    'placeholder' => '••••••••',
                    'required' => false,
                    'type' => 'password',
                    'help' => 'API password (if using basic/vendor login auth)',
                ],
                [
                    'key' => 'bearer_token',
                    'label' => 'Bearer Token (optional)',
                    'placeholder' => 'Your bearer token',
                    'required' => false,
                    'type' => 'password',
                    'help' => 'Bearer token for API authentication',
                ],
                [
                    'key' => 'api_key',
                    'label' => 'API Key (optional)',
                    'placeholder' => 'Your API key',
                    'required' => false,
                    'type' => 'password',
                    'help' => 'Static API key (if required by your vendor)',
                ],
                [
                    'key' => 'customer_code',
                    'label' => 'Customer Code (optional)',
                    'placeholder' => 'e.g., MEDIAPLATFORM',
                    'required' => false,
                    'type' => 'text',
                    'help' => 'Customer/vendor code (if required by your vendor)',
                ],
            ],
            'support' => [
                'email' => '',
                'docs' => '',
                'note' => 'Fill in only the fields your API requires. Leave optional fields empty.',
            ],
            'test_type' => 'custom',
        ],
    ];
}

// Get credential schema for a specific vendor
function amrod_get_credential_schema($vendor_id) {
    $schemas = amrod_get_vendor_credential_schemas();
    return $schemas[$vendor_id] ?? $schemas['custom'];
}



// ===== SIMPLIFIED TAB STATUS =====
function amrod_get_simplified_status() {
    $connected = !empty(get_option('amrod_username')) && !empty(get_option('amrod_password'));
    $has_mapping = !empty(get_option('amrod_field_mapping'));
    $last_sync = get_option('amrod_last_sync');
    
    return [
        'connected' => $connected,
        'has_mapping' => $has_mapping,
        'last_sync' => $last_sync,
        'sync_status' => $last_sync ? 'green' : 'yellow',
        'connection_status' => $connected ? 'green' : 'red',
    ];
}

// ===== ADMIN MENU - CLEAN 4-TAB LAYOUT =====
add_action('admin_menu', 'amrod_register_simplified_menus');
function amrod_register_simplified_menus() {
    if (!class_exists('WooCommerce')) return;
    
    // Main menu: WooSync (Dashboard)
    add_menu_page(
        'WooSync',
        'WooSync',
        'manage_options',
        'amrod-sync',
        'amrod_render_main_page',
        'dashicons-update',
        56
    );

    // Submenu: Dashboard
    add_submenu_page(
        'amrod-sync',
        'Dashboard',
        '1. Dashboard',
        'manage_options',
        'amrod-sync',
        'amrod_render_main_page'
    );

    // Submenu: Connect & Map
    add_submenu_page(
        'amrod-sync',
        'Connect & Map',
        '2. Connect & Map',
        'manage_options',
        'amrod-sync-connect',
        'amrod_render_connect_map_page'
    );

    // Submenu: Sync Log
    add_submenu_page(
        'amrod-sync',
        'Sync Log',
        '3. Sync Log',
        'manage_options',
        'amrod-sync-log',
        'amrod_render_sync_log_page'
    );

    // Submenu: Settings (includes Promotions, Pricing, Promo Share as sub-tabs)
    add_submenu_page(
        'amrod-sync',
        'Settings',
        '4. Settings',
        'manage_options',
        'amrod-sync-settings',
        'amrod_render_settings_page'
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
        echo '<div class="alert alert-success alert-dismissible fade show"><strong>✅ WooSync activated!</strong> Setup your API credentials to begin syncing. <a href="#" onclick="window.resetWooSyncWizard(); return false;">Run Setup Wizard</a></div>';
    }
});


// ===== WIZARD TRIGGER ON ACTIVATION =====
add_action('admin_init', function() {
    if (get_transient('amrod_activated')) {
        delete_transient('amrod_activated');
        // Trigger wizard JS event
        add_action('admin_footer', function() {
            echo '<script>jQuery(document).ready(function(){ jQuery(document).trigger("woosync_activate_wizard"); });</script>';
        });
    }
});


// ===== BREADCRUMB HELPER =====
function amrod_breadcrumb($items) {
    echo '<nav aria-label="breadcrumb"><ol class="breadcrumb mb-3">';
    echo '<li class="breadcrumb-item"><a href="?page=amrod-sync">WooSync</a></li>';
    foreach ($items as $label => $url) {
        if ($url === false) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html($label) . '</li>';
        } else {
            echo '<li class="breadcrumb-item"><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
        }
    }
    echo '</ol></nav>';
}

// ===== MAIN PAGE RENDERER (handles all pages) =====
function amrod_render_main_page() {
    $page = $_GET['page'] ?? 'amrod-sync';
    $status = amrod_get_simplified_status();
    $last_sync = get_option('amrod_last_sync');
    $total_products = get_option('amrod_total_products', 0);
    $last_token_time = get_option('amrod_last_token_fetched');
    $batch_size = get_option('amrod_batch_size', 200);
    $auto_update = get_option('amrod_auto_update', 1);
    ?>
    <div class="container-fluid mt-4 amrod-container">
        <?php amrod_breadcrumb(['Dashboard' => false]); ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">📊 Dashboard</h1>
                <small class="text-muted">WooSync v<?php echo AMROD_SYNC_VERSION; ?></small>
            </div>
            <img src="<?php echo AMROD_SYNC_ASSETS; ?>images/mediaplatform-logo.svg" height="18" alt="Mediaplatform">
        </div>

        <!-- Quick Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="status-dot <?php echo $status['connection_status']; ?> mb-2"></div>
                        <h5 class="card-title">API Connection</h5>
                        <p class="card-text text-muted small"><?php echo $status['connected'] ? 'Connected to Amrod' : 'Not configured'; ?></p>
                        <a href="?page=amrod-sync-settings" class="btn btn-sm btn-outline-primary">Configure</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="status-dot <?php echo $status['sync_status']; ?> mb-2"></div>
                        <h5 class="card-title">Last Sync</h5>
                        <p class="card-text text-muted small"><?php echo $last_sync ? date('d M @ H:i', strtotime($last_sync)) : 'Never synced'; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?php echo number_format($total_products); ?></h5>
                        <p class="card-text text-muted small">Products Synced</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?php echo number_format(get_option('amrod_mapped_fields', 0)); ?></h5>
                        <p class="card-text text-muted small">Fields Mapped</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🚀 Quick Sync</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="quickSyncForm">
                            <?php wp_nonce_field('amrod_sync_nonce'); ?>
                            <div class="row align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Batch Size <span class="help-icon" data-bs-toggle="tooltip" title="Number of products to sync per batch. Recommended: 200">?</span></label>
                                    <input type="number" name="batch_size" value="<?php echo $batch_size; ?>" min="50" max="500" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Sync Mode</label>
                                    <select name="sync_mode" class="form-select">
                                        <option value="full">Full Sync (All Products)</option>
                                        <option value="batch">Batch Mode (Limit)</option>
                                        <option value="resume">Resume (From Last Offset)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="action" value="run_sync" class="btn btn-success w-100">
                                        ▶️ Start Sync Now
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Progress Bar -->
                        <div id="syncProgress" style="display:none;" class="mt-3">
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="progressBar" style="width: 0%">
                                    <span id="progressText">0%</span>
                                </div>
                            </div>
                            <div id="syncDetails" class="text-muted small mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">⚡ Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Token Expires:</span>
                            <span class="badge bg-<?php echo $last_token_time ? 'success' : 'warning'; ?>">
                                <?php echo $last_token_time ? date('H:i', strtotime($last_token_time) + 55*60) : 'Not fetched'; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Auto-Update:</span>
                            <span class="badge bg-<?php echo $auto_update ? 'success' : 'secondary'; ?>">
                                <?php echo $auto_update ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>API Endpoints:</span>
                            <span class="badge bg-info">
                                <?php echo count(array_filter(amrod_get_endpoints(), fn($e) => $e['enabled'])); ?> enabled
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Sync Log -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">📋 Recent Activity</h5>
                <a href="?page=amrod-sync-log" class="btn btn-sm btn-light">View All Logs →</a>
            </div>
            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                <div id="syncLog">
                    <?php
                    $log = array_reverse((array) get_option('amrod_sync_log', []));
                    if (empty($log)) {
                        echo '<p class="text-muted mb-0">No activity yet. Start a sync to see logs here.</p>';
                    } else {
                        foreach (array_slice($log, 0, 10) as $entry) {
                            $icon = '📝';
                            if (strpos($entry, '✅') !== false) $icon = '✅';
                            elseif (strpos($entry, '❌') !== false) $icon = '❌';
                            elseif (strpos($entry, '⏳') !== false) $icon = '⏳';
                            echo '<div class="d-flex align-items-start mb-2"><span class="me-2">' . $icon . '</span><span class="text-monospace small">' . esc_html($entry) . '</span></div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== CONNECT & MAP PAGE =====
function amrod_render_connect_map_page() {
    $status = amrod_get_simplified_status();
    $mapping = get_option('amrod_field_mapping', []);
    $endpoints = amrod_get_endpoints();
    ?>
    <div class="container-fluid mt-4 amrod-container">
        <?php amrod_breadcrumb(['Dashboard' => '?page=amrod-sync', 'Connect & Map' => false]); ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">🔗 Connect & Map</h1>
                <small class="text-muted">Configure API connection and field mappings</small>
            </div>
        </div>

        <!-- Two-Column Layout -->
        <div class="row">
            <!-- LEFT COLUMN: Connection + Mapping (40%) -->
            <div class="col-md-5">
                <!-- Vendor Tier Card -->
                <?php 
                $vendor_tier = amrod_get_vendor_tier('amrod');
                $tier = $vendor_tier['tier'] ?? 'Standard';
                $tier_active_since = $vendor_tier['tier_active_since'] ?? '';
                $tier_expiry = $vendor_tier['tier_expiry'] ?? '';
                $upgrade_url = $vendor_tier['upgrade_url'] ?? '';
                ?>
                <div class="card mb-3 tier-card tier-card-<?php echo strtolower($tier); ?>">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo amrod_get_tier_icon($tier); ?> Amrod — <?php echo $tier; ?> Tier</h5>
                        <span class="tier-badge <?php echo amrod_get_tier_color_class($tier); ?>"><?php echo $tier; ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Your pricing level</small>
                                <strong><?php echo $tier; ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Tier active since</small>
                                <strong><?php echo $tier_active_since ? date('M Y', strtotime($tier_active_since)) : 'Not set'; ?></strong>
                            </div>
                        </div>
                        <?php if ($tier_expiry): ?>
                        <div class="mt-2">
                            <small class="text-muted d-block">Tier expires</small>
                            <strong><?php echo $tier_expiry; ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" id="refreshTierBtn" class="btn btn-sm btn-outline-dark flex-grow-1">
                                🔄 Refresh Tier Status
                            </button>
                            <?php if ($upgrade_url): ?>
                            <a href="<?php echo esc_url($upgrade_url); ?>" target="_blank" class="btn btn-sm btn-warning">
                                ⬆️ Upgrade Tier
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Connection Status Card -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">🔌 API Connection</h5>
                        <div class="status-dot <?php echo $status['connection_status']; ?>"></div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Vendor / Customer Code <span class="help-icon" data-bs-toggle="tooltip" title="Your Amrod customer code">?</span></label>
                            <input type="text" id="vendorCode" value="<?php echo esc_attr(get_option('amrod_customer_code', '')); ?>" class="form-control" placeholder="e.g., MEDIAPLATFORM">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Username <span class="help-icon" data-bs-toggle="tooltip" title="Your Amrod API username">?</span></label>
                            <input type="text" id="apiUsername" value="<?php echo esc_attr(get_option('amrod_username', '')); ?>" class="form-control" placeholder="username@email.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Password <span class="help-icon" data-bs-toggle="tooltip" title="Your Amrod API password">?</span></label>
                            <div class="input-group">
                                <input type="password" id="apiPassword" class="form-control" placeholder="••••••••">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">👁</button>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" id="testConnectionBtn" class="btn btn-outline-primary flex-grow-1">
                                🔗 Test Connection
                            </button>
                            <button type="button" id="saveCredentialsBtn" class="btn btn-primary flex-grow-1">
                                💾 Save Credentials
                            </button>
                        </div>
                        <div id="connectionStatus" class="mt-3" style="display:none;"></div>
                    </div>
                </div>

                <!-- Field Mapping Card -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">🗺️ Field Mapping</h5>
                    </div>
                    <div class="card-body p-0">
                        <!-- Mapping Tabs -->
                        <ul class="nav nav-tabs" id="mappingTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" id="core-tab" data-bs-toggle="tab" data-bs-target="#core-fields" type="button">Core Fields</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-fields" type="button">📂 Categories</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="attributes-tab" data-bs-toggle="tab" data-bs-target="#attributes-fields" type="button">🏷️ Attributes</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="marketing-tab" data-bs-toggle="tab" data-bs-target="#marketing-fields" type="button">📣 Marketing</button>
                            </li>
                        </ul>

                        <div class="tab-content p-3" id="mappingTabContent">
                            <!-- Core Fields Tab -->
                            <div class="tab-pane fade show active" id="core-fields" role="tabpanel">
                                <?php amrod_render_mapping_tab('core', ['sku', 'name', 'price', 'sale_price', 'description', 'short_description', 'stock', 'image', 'images']); ?>
                            </div>
                            <!-- Categories Tab -->
                            <div class="tab-pane fade" id="categories-fields" role="tabpanel">
                                <?php amrod_render_mapping_tab('categories', ['categories']); ?>
                            </div>
                            <!-- Attributes Tab -->
                            <div class="tab-pane fade" id="attributes-fields" role="tabpanel">
                                <?php amrod_render_mapping_tab('attributes', ['brand', 'colour', 'size']); ?>
                            </div>
                            <!-- Marketing Tab -->
                            <div class="tab-pane fade" id="marketing-fields" role="tabpanel">
                                <?php amrod_render_mapping_tab('marketing', ['clearance', 'deal_of_day', 'banner_image', 'catalog_pdf', 'special_message', 'sort_order']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex gap-2">
                            <button type="button" id="autoDetectBtn" class="btn btn-outline-secondary flex-grow-1">
                                🔍 Auto-Detect
                            </button>
                            <button type="button" id="saveMappingBtn" class="btn btn-success flex-grow-1">
                                💾 Save Mapping
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Live Preview (60%) -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">👁 Live Preview</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search Bar -->
                        <div class="mb-3">
                            <label class="form-label">Search Products to Preview <span class="help-icon" data-bs-toggle="tooltip" title="Type to search products. Results update as you type.">?</span></label>
                            <div class="input-group">
                                <input type="text" id="productSearch" class="form-control" placeholder="Search by name, SKU, or keyword (e.g., cups)..." autocomplete="off">
                                <button class="btn btn-primary" type="button" id="searchBtn">🔍</button>
                            </div>
                            <small class="text-muted">Press Enter or click search to see all matching products</small>
                        </div>

                        <!-- Search Results Dropdown -->
                        <div id="searchResults" class="list-group mb-3" style="max-height: 200px; overflow-y: auto; display: none;"></div>

                        <!-- Product Preview Card -->
                        <div id="productPreview" class="border rounded p-3" style="background: #f8f9fa;">
                            <div class="text-center text-muted py-5">
                                <div class="mb-3" style="font-size: 48px;">📦</div>
                                <p class="mb-1">No product selected</p>
                                <small>Search for a product above to preview how it will appear in WooCommerce</small>
                            </div>
                        </div>

                        <!-- Tier Pricing Breakdown (hidden by default) -->
                        <div id="tierPricingBreakdown" class="mt-3" style="display: none;">
                            <div class="price-breakdown-box bg-light border rounded p-3">
                                <h6 class="mb-3">💰 Tier Pricing Breakdown</h6>
                                <div class="breakdown-row d-flex justify-content-between py-1 border-bottom">
                                    <span>Supplier Tier Price:</span>
                                    <span class="fw-bold text-primary" id="tierPriceDisplay">R0.00</span>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between py-1 border-bottom">
                                    <span>WooSync Markup (<span id="markupPercentDisplay">30</span>%):</span>
                                    <span class="text-success" id="markupAmountDisplay">+R0.00</span>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between py-1 border-bottom bg-dark text-white px-2 rounded">
                                    <span>Customer Sees:</span>
                                    <span class="fw-bold" id="customerPriceDisplay">R0.00</span>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between py-1 text-success">
                                    <span>Your Margin:</span>
                                    <span class="fw-bold" id="marginDisplay">R0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex gap-2">
                            <button type="button" id="syncThisProductBtn" class="btn btn-outline-primary flex-grow-1" disabled>
                                📦 Sync This Product
                            </button>
                            <button type="button" id="syncAllProductsBtn" class="btn btn-success flex-grow-1">
                                ▶️ Sync All Products
                            </button>
                        </div>
                    </div>
                </div>

                <!-- API Endpoints Quick Config -->
                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">🔌 API Endpoints <span class="help-icon" data-bs-toggle="tooltip" title="Select which API endpoints to use for syncing">?</span></h5>
                    </div>
                    <div class="card-body p-2">
                        <div class="row">
                            <?php 
                            $quick_endpoints = ['products', 'stock', 'prices', 'categories'];
                            foreach ($quick_endpoints as $key): 
                                $ep = $endpoints[$key] ?? null;
                                if ($ep):
                            ?>
                            <div class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input endpoint-toggle" type="checkbox" 
                                           id="ep_<?php echo $key; ?>" 
                                           data-endpoint="<?php echo $key; ?>"
                                           <?php checked($ep['enabled']); ?>>
                                    <label class="form-check-label" for="ep_<?php echo $key; ?>">
                                        <strong><?php echo $ep['label']; ?></strong>
                                        <small class="text-muted d-block"><?php echo $ep['path']; ?></small>
                                    </label>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== RENDER MAPPING TAB HELPER =====
function amrod_render_mapping_tab($category, $fields) {
    $mapping = get_option('amrod_field_mapping', []);
    $wc_fields = [
        'sku' => 'SKU',
        'name' => 'Product Name',
        'price' => 'Regular Price',
        'sale_price' => 'Sale Price',
        'description' => 'Description',
        'short_description' => 'Short Description',
        'categories' => 'Categories',
        'brand' => 'Brand',
        'colour' => 'Colour',
        'size' => 'Size',
        'stock' => 'Stock Qty',
        'image' => 'Main Image',
        'images' => 'Image Gallery',
        'clearance' => 'Clearance',
        'deal_of_day' => 'Deal of Day',
        'banner_image' => 'Banner Image',
        'catalog_pdf' => 'Catalog PDF',
        'special_message' => 'Special Msg',
        'sort_order' => 'Sort Order',
    ];
    ?>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light">
            <tr>
                <th style="width: 40%;">Amrod Field</th>
                <th style="width: 10%;"></th>
                <th style="width: 50%;">WooCommerce</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fields as $wc_key): ?>
            <tr>
                <td>
                    <input type="text" name="mapping[<?php echo $wc_key; ?>]" 
                           value="<?php echo esc_attr($mapping[$wc_key] ?? ''); ?>" 
                           class="form-control form-control-sm" 
                           placeholder="e.g., <?php echo $wc_key === 'sku' ? 'ProductCode' : ucfirst($wc_key); ?>">
                </td>
                <td class="text-center align-middle">→</td>
                <td class="align-middle">
                    <small><?php echo $wc_fields[$wc_key] ?? $wc_key; ?></small>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// ===== SYNC LOG PAGE =====
function amrod_render_sync_log_page() {
    $log = array_reverse((array) get_option('amrod_sync_log', []));
    $last_sync = get_option('amrod_last_sync');
    $total_products = get_option('amrod_total_products', 0);
    $vendor_tier = amrod_get_vendor_tier('amrod');
    $tier = $vendor_tier['tier'] ?? 'Standard';
    ?>
    <div class="container-fluid mt-4 amrod-container">
        <?php amrod_breadcrumb(['Dashboard' => '?page=amrod-sync', 'Sync Log' => false]); ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">📋 Sync Log</h1>
                <small class="text-muted">History, errors, and sync activity</small>
            </div>
            <form method="post" class="d-inline">
                <?php wp_nonce_field('amrod_sync_nonce'); ?>
                <button type="submit" name="clear_log" class="btn btn-outline-danger btn-sm">🗑️ Clear Log</button>
            </form>
        </div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo number_format($total_products); ?></h3>
                        <p class="text-muted mb-0">Total Products Synced</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo count($log); ?></h3>
                        <p class="text-muted mb-0">Log Entries</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo $last_sync ? date('d M H:i', strtotime($last_sync)) : 'Never'; ?></h3>
                        <p class="text-muted mb-0">Last Sync</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <span class="tier-badge <?php echo amrod_get_tier_color_class($tier); ?> fs-5 px-3 py-2">
                            <?php echo amrod_get_tier_icon($tier); ?> <?php echo $tier; ?> Tier
                        </span>
                        <p class="text-muted mb-0 mt-2">Your Pricing Level</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier Pricing Benefits Section -->
        <?php if ($tier !== 'Standard'): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0">💰 Tier Pricing Benefits</h5>
                <button type="button" id="refreshSavingsBtn" class="btn btn-sm btn-dark">
                    🔄 Refresh Savings
                </button>
            </div>
            <div class="card-body">
                <div id="tierSavingsSummary" class="mb-3">
                    <div class="alert alert-info">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Click "Refresh Savings" to load tier pricing comparison
                    </div>
                </div>
                <div id="tierSavingsTable" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Product</th>
                                <th>Standard Price</th>
                                <th>Your Tier Price</th>
                                <th>Savings/Unit</th>
                                <th>Savings %</th>
                            </tr>
                        </thead>
                        <tbody id="tierSavingsBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Click "Refresh Savings" to load tier pricing comparison
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Filter by Type</label>
                        <select id="logFilter" class="form-select">
                            <option value="all">All Entries</option>
                            <option value="success">✅ Success Only</option>
                            <option value="error">❌ Errors Only</option>
                            <option value="info">📝 Info Only</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" id="logSearch" class="form-control" placeholder="Search logs...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date Range</label>
                        <select id="logDateRange" class="form-select">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Entries -->
        <div class="card">
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <div id="logEntries">
                    <?php
                    if (empty($log)) {
                        echo '<div class="text-center text-muted py-5"><p>No log entries yet.</p></div>';
                    } else {
                        foreach ($log as $entry) {
                            $icon = '📝';
                            $row_class = '';
                            if (strpos($entry, '✅') !== false) { $icon = '✅'; $row_class = 'border-start border-success'; }
                            elseif (strpos($entry, '❌') !== false) { $icon = '❌'; $row_class = 'border-start border-danger'; }
                            elseif (strpos($entry, '⏳') !== false) { $icon = '⏳'; $row_class = 'border-start border-warning'; }
                            echo '<div class="log-entry p-2 mb-2 ' . $row_class . '" style="background: #f8f9fa; border-left: 3px solid #ccc;">';
                            echo '<span class="me-2">' . $icon . '</span>';
                            echo '<span class="text-monospace small">' . esc_html($entry) . '</span>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== PROMOTIONS PAGE =====
function amrod_render_promotions_page() {
    ?>
    <div class="container-fluid mt-4 amrod-container">
        <?php amrod_breadcrumb(['Dashboard' => '?page=amrod-sync', 'Promotions' => false]); ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">📣 Promotions</h1>
                <small class="text-muted">Banners, cross-sells, and notifications</small>
            </div>
        </div>

        <!-- Promo Cards -->
        <div class="row">
            <!-- Clearance Banners -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">🏷️ Clearance Banners</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Manage clearance and sale banners displayed on product pages.</p>
                        <div class="alert alert-info">
                            <small>Clearance products are automatically detected from the "Clearance" field mapping.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Banner Position</label>
                            <select class="form-select">
                                <option>Above Product Title</option>
                                <option>Below Product Image</option>
                                <option>Sidebar Widget</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Banner Text</label>
                            <input type="text" class="form-control" value="🔥 CLEARANCE - Limited Stock!" placeholder="Enter banner text...">
                        </div>
                        <button class="btn btn-primary">💾 Save Banner Settings</button>
                    </div>
                </div>
            </div>

            <!-- Cross-Sell Configuration -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">🔗 Cross-Sell Rules</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Automatically link related products during sync.</p>
                        <div class="alert alert-info">
                            <small>Cross-sells are suggested based on category and brand matches.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cross-Sell Mode</label>
                            <select class="form-select">
                                <option>Same Category</option>
                                <option>Same Brand</option>
                                <option>Both Category & Brand</option>
                                <option>Manual Selection</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max Cross-Sells per Product</label>
                            <input type="number" class="form-control" value="4" min="1" max="10">
                        </div>
                        <button class="btn btn-primary">💾 Save Cross-Sell Rules</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">🔔 Sync Notifications</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notifySyncComplete" checked>
                                    <label class="form-check-label" for="notifySyncComplete">
                                        Notify on Sync Complete
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notifyErrors" checked>
                                    <label class="form-check-label" for="notifyErrors">
                                        Notify on Errors
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notifyLowStock">
                                    <label class="form-check-label" for="notifyLowStock">
                                        Notify on Low Stock
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Email for Notifications</label>
                                    <input type="email" class="form-control" placeholder="admin@yoursite.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Low Stock Threshold</label>
                                    <input type="number" class="form-control" value="10" min="1">
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary">💾 Save Notification Settings</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== SETTINGS PAGE (Merged Settings + Updates) =====
function amrod_render_settings_page() {
    $username = get_option('amrod_username');
    $password = !empty(get_option('amrod_password'));
    $customer_code = get_option('amrod_customer_code');
    $auth_url = get_option('amrod_auth_url', 'https://identity.amrod.co.za');
    $api_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za');
    $auto_update = get_option('amrod_auto_update', 1);
    $batch_size = get_option('amrod_batch_size', 200);
    $endpoints = amrod_get_endpoints();
    ?>
    <div class="container-fluid mt-4 amrod-container">
        <?php amrod_breadcrumb(['Dashboard' => '?page=amrod-sync', 'Settings' => false]); ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">⚙️ Settings</h1>
                <small class="text-muted">Credentials, auto-update, and plugin info</small>
            </div>
        </div>

        <div class="row">
            <!-- API Credentials -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🔑 API Credentials</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="settingsForm">
                            <?php wp_nonce_field('amrod_sync_nonce'); ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Customer Code <span class="help-icon" data-bs-toggle="tooltip" title="Your Amrod customer/account code">?</span></label>
                                <input type="text" name="amrod_customer_code" value="<?php echo esc_attr($customer_code); ?>" class="form-control" placeholder="MEDIAPLATFORM">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username <span class="help-icon" data-bs-toggle="tooltip" title="Amrod API username">?</span></label>
                                <input type="text" name="amrod_username" value="<?php echo esc_attr($username); ?>" class="form-control" placeholder="user@email.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password <span class="help-icon" data-bs-toggle="tooltip" title="Amrod API password">?</span></label>
                                <div class="input-group">
                                    <input type="password" name="amrod_password" id="passwordField" class="form-control" placeholder="••••••••">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">👁</button>
                                </div>
                                <?php if ($password): ?>
                                <small class="text-success">Password is set</small>
                                <?php else: ?>
                                <small class="text-warning">No password saved</small>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Auth URL <span class="help-icon" data-bs-toggle="tooltip" title="Amrod identity/authentication endpoint">?</span></label>
                                <input type="url" name="amrod_auth_url" value="<?php echo esc_attr($auth_url); ?>" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">API URL <span class="help-icon" data-bs-toggle="tooltip" title="Amrod vendor API base URL">?</span></label>
                                <input type="url" name="amrod_api_url" value="<?php echo esc_attr($api_url); ?>" class="form-control">
                            </div>
                            <button type="submit" name="save_credentials" class="btn btn-success w-100">💾 Save Credentials</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sync Settings -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">⚡ Sync Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Batch Size <span class="help-icon" data-bs-toggle="tooltip" title="Number of products to process per batch">?</span></label>
                            <input type="number" name="amrod_batch_size" value="<?php echo $batch_size; ?>" min="50" max="500" class="form-control">
                            <small class="text-muted">Recommended: 200</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sync Schedule <span class="help-icon" data-bs-toggle="tooltip" title="How often to automatically sync">?</span></label>
                            <select name="amrod_sync_schedule" class="form-select">
                                <option value="manual">Manual Only</option>
                                <option value="hourly">Every Hour</option>
                                <option value="6hours">Every 6 Hours</option>
                                <option value="12hours">Every 12 Hours</option>
                                <option value="daily">Daily</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Auto-Update Toggle <span class="help-icon" data-bs-toggle="tooltip" title="Enable/disable automatic plugin updates">?</span></label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="amrod_auto_update" value="1" <?php checked($auto_update); ?>>
                                <label class="form-check-label">Enable Auto-Updates</label>
                            </div>
                        </div>
                        <button type="submit" form="settingsForm" class="btn btn-primary w-100">💾 Save Settings</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Vendor Tier Settings -->
        <?php 
        $vendor_tier = amrod_get_vendor_tier('amrod');
        $tier = $vendor_tier['tier'] ?? 'Standard';
        $tier_notes = $vendor_tier['tier_notes'] ?? '';
        $tier_pricing_endpoint = $vendor_tier['tier_pricing_endpoint'] ?? '/api/v1/Prices/';
        $tier_active_since = $vendor_tier['tier_active_since'] ?? '';
        $tier_expiry = $vendor_tier['tier_expiry'] ?? '';
        $upgrade_url = $vendor_tier['upgrade_url'] ?? '';
        $markup_percent = get_option('woosync_markup_percent', 30);
        ?>
        <div class="card mb-3 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">🏆 Vendor Tier Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Pricing Tier</label>
                            <select name="woosync_vendor_tier" class="form-select" id="tierSelect">
                                <option value="Standard" <?php selected($tier, 'Standard'); ?>>📋 Standard</option>
                                <option value="Bronze" <?php selected($tier, 'Bronze'); ?>>🥉 Bronze</option>
                                <option value="Silver" <?php selected($tier, 'Silver'); ?>>🥈 Silver</option>
                                <option value="Gold" <?php selected($tier, 'Gold'); ?>>🏆 Gold</option>
                                <option value="Platinum" <?php selected($tier, 'Platinum'); ?>>💎 Platinum</option>
                            </select>
                            <small class="text-muted">Select your pricing tier level. Auto-detected from API if available.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tier Pricing Endpoint</label>
                            <input type="text" name="woosync_tier_pricing_endpoint" 
                                   id="tierPricingEndpoint"
                                   value="<?php echo esc_attr($tier_pricing_endpoint); ?>" 
                                   class="form-control" 
                                   placeholder="/api/v1/Prices/">
                            <small class="text-muted">API endpoint for tier-specific pricing (Amrod: /api/v1/Prices/)</small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Tier Active Since</label>
                            <input type="text" class="form-control" value="<?php echo $tier_active_since ?: 'Not set'; ?>" readonly>
                            <small class="text-muted">When this tier was first detected</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Tier Expiry</label>
                            <input type="text" class="form-control" value="<?php echo $tier_expiry ?: 'No expiry'; ?>" readonly>
                            <small class="text-muted">When this tier expires (if applicable)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">WooSync Markup %</label>
                            <input type="number" name="woosync_markup_percent" 
                                   id="markupPercent"
                                   value="<?php echo esc_attr($markup_percent); ?>" 
                                   class="form-control" 
                                   min="0" max="200" step="0.5">
                            <small class="text-muted">Markup added to your tier price</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tier Notes (Internal)</label>
                    <textarea name="woosync_tier_notes" id="tierNotes" class="form-control" rows="2" 
                              placeholder="Internal notes about this tier..."><?php echo esc_textarea($tier_notes); ?></textarea>
                </div>
                <?php if (!empty($upgrade_url)): ?>
                <div class="alert alert-info">
                    <a href="<?php echo esc_url($upgrade_url); ?>" target="_blank" class="alert-link">
                        🔗 Upgrade Your Tier →
                    </a>
                </div>
                <?php endif; ?>
                <div class="d-flex gap-2">
                    <button type="button" id="saveTierSettingsBtn" class="btn btn-warning">
                        💾 Save Tier Settings
                    </button>
                    <button type="button" id="refreshTierStatusBtn" class="btn btn-outline-secondary">
                        🔄 Refresh from API
                    </button>
                </div>
            </div>
        </div>

        <!-- Help & Documentation -->
        <div class="docs-section">
            <h5>📖 Help & Documentation</h5>
            <p class="text-muted small mb-3">Access documentation and support resources for WooSync and connected vendors.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="https://newapidocs.amrod.co.za" target="_blank" class="docs-btn docs-btn-primary">
                    📖 Amrod API Docs
                </a>
                <a href="https://woocommerce.com/document/rest-api/" target="_blank" class="docs-btn docs-btn-outline">
                    🛒 WooCommerce REST API
                </a>
                <a href="https://mediaplatform.co.za/woosync-docs" target="_blank" class="docs-btn docs-btn-outline">
                    📚 WooSync User Guide
                </a>
                <a href="https://mediaplatform.co.za/support" target="_blank" class="docs-btn docs-btn-outline">
                    💬 Get Support
                </a>
            </div>
        </div>

        <!-- Plugin Info -->

        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">ℹ️ Plugin Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Version:</strong> <?php echo AMROD_SYNC_VERSION; ?></p>
                        <p class="mb-1"><strong>Author:</strong> Mediaplatform</p>
                        <p class="mb-1"><strong>Website:</strong> <a href="https://mediaplatform.co.za" target="_blank">mediaplatform.co.za</a></p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>WooCommerce:</strong> <?php echo class_exists('WooCommerce') ? '✅ Active' : '❌ Not Found'; ?></p>
                        <p class="mb-1"><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                        <p class="mb-1"><strong>WordPress:</strong> <?php global $wp_version; echo $wp_version; ?></p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Last Sync:</strong> <?php echo get_option('amrod_last_sync') ?: 'Never'; ?></p>
                        <p class="mb-1"><strong>Total Products:</strong> <?php echo number_format(get_option('amrod_total_products', 0)); ?></p>
                        <p class="mb-1"><strong>API Endpoints:</strong> <?php echo count(array_filter($endpoints, fn($e) => $e['enabled'])); ?> enabled</p>
                    </div>
                </div>
                <hr>
                <div class="d-flex gap-2">
                    <a href="https://mediaplatform.co.za/support" target="_blank" class="btn btn-outline-primary">📖 Documentation</a>
                    <a href="https://mediaplatform.co.za/support" target="_blank" class="btn btn-outline-secondary">💬 Get Support</a>
                    <form method="post" class="d-inline ms-auto">
                        <?php wp_nonce_field('amrod_sync_nonce'); ?>
                        <button type="submit" name="reset_all" class="btn btn-outline-danger" onclick="return confirm('Are you sure? This will clear all settings and logs.');">🗑️ Reset Plugin</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== HANDLE POST REQUESTS =====
add_action('admin_init', function() {
    if (empty($_POST)) return;
    if (!current_user_can('manage_options')) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'amrod_sync_nonce')) return;

    if (isset($_POST['save_credentials'])) {
        update_option('amrod_username', sanitize_text_field($_POST['amrod_username'] ?? ''));
        update_option('amrod_password', sanitize_text_field($_POST['amrod_password'] ?? ''));
        update_option('amrod_customer_code', sanitize_text_field($_POST['amrod_customer_code'] ?? ''));
        update_option('amrod_auth_url', esc_url_raw($_POST['amrod_auth_url'] ?? 'https://identity.amrod.co.za'));
        update_option('amrod_api_url', esc_url_raw($_POST['amrod_api_url'] ?? 'https://vendorapi.amrod.co.za'));
        wp_safe_redirect(add_query_arg('updated', '1'));
        exit;
    }

    if (isset($_POST['save_settings'])) {
        update_option('amrod_batch_size', intval($_POST['amrod_batch_size'] ?? 200));
        update_option('amrod_sync_schedule', sanitize_text_field($_POST['amrod_sync_schedule'] ?? 'manual'));
        update_option('amrod_auto_update', isset($_POST['amrod_auto_update']) ? 1 : 0);
        wp_safe_redirect(add_query_arg('updated', '1'));
        exit;
    }

    if (isset($_POST['clear_log'])) {
        delete_option('amrod_sync_log');
        wp_safe_redirect(add_query_arg('cleared', '1'));
        exit;
    }

    if (isset($_POST['reset_all'])) {
        $options = ['amrod_username', 'amrod_password', 'amrod_customer_code', 'amrod_auth_url', 'amrod_api_url', 'amrod_docs_url', 'amrod_endpoints', 'amrod_field_mapping', 'amrod_sync_log', 'amrod_last_sync', 'amrod_total_products', 'amrod_last_token_fetched', 'amrod_sync_schedule', 'amrod_batch_size', 'amrod_auto_update', 'amrod_mapped_fields'];
        foreach ($options as $opt) delete_option($opt);
        wp_safe_redirect(add_query_arg('reset', '1'));
        exit;
    }
});

// ===== UNINSTALL =====
register_uninstall_hook(__FILE__, function() {
    $options = ['amrod_username', 'amrod_password', 'amrod_customer_code', 'amrod_auth_url', 'amrod_api_url', 'amrod_docs_url', 'amrod_endpoints', 'amrod_field_mapping', 'amrod_sync_log', 'amrod_last_sync', 'amrod_total_products', 'amrod_last_token_fetched', 'amrod_sync_schedule', 'amrod_batch_size', 'amrod_auto_update', 'amrod_mapped_fields'];
    foreach ($options as $opt) delete_option($opt);
});

// ===== AJAX HANDLERS =====

// Test Connection AJAX
// Get credential schema for a vendor (AJAX)
add_action('wp_ajax_amrod_get_credential_schema', 'amrod_ajax_get_credential_schema');
function amrod_ajax_get_credential_schema() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? 'amrod');
    $schema = amrod_get_credential_schema($vendor_id);
    
    wp_send_json_success([
        'schema' => $schema,
        'vendor_id' => $vendor_id,
    ]);
}

// Test Connection AJAX (handles all vendor types)
add_action('wp_ajax_amrod_test_connection', 'amrod_ajax_test_connection');
function amrod_ajax_test_connection() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? 'amrod');
    $schema = amrod_get_credential_schema($vendor_id);
    $auth_type = $schema['auth_type'] ?? 'vendor_login';
    
    if ($auth_type === 'vendor_login') {
        // Amrod-style vendor login
        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = sanitize_text_field($_POST['password'] ?? '');
        $customer_code = sanitize_text_field($_POST['customer_code'] ?? '');
        $auth_url = esc_url_raw($_POST['auth_url'] ?? 'https://identity.amrod.co.za') . '/VendorLogin';
        
        if (empty($username) || empty($password) || empty($customer_code)) {
            wp_send_json_error('Username, password, and customer code are required for Amrod vendor login');
        }

        $payload = json_encode([
            'username' => $username,
            'password' => $password,
            'CustomerCode' => $customer_code
        ]);

        $response = wp_remote_post($auth_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $payload,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['token'])) {
                wp_send_json_success(['message' => 'Connection successful!', 'token' => substr($data['token'], 0, 10) . '...']);
            }
        }
        wp_send_json_error('Invalid credentials (HTTP ' . $code . ')');
        
    } elseif ($auth_type === 'bearer_key') {
        // SMD-style Bearer Token + ClientAccessKey
        $api_base_url = esc_url_raw($_POST['api_base_url'] ?? 'https://api.smdtechnologies.com/v1/');
        $bearer_token = sanitize_text_field($_POST['bearer_token'] ?? '');
        $client_access_key = sanitize_text_field($_POST['client_access_key'] ?? '');
        
        if (empty($bearer_token) || empty($client_access_key)) {
            wp_send_json_error('Bearer Token and Client Access Key are required for SMD');
        }

        $response = wp_remote_get($api_base_url . '/products', [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer_token,
                'ClientAccessKey' => $client_access_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            wp_send_json_success(['message' => 'SMD connection successful!']);
        }
        wp_send_json_error('SMD API error (HTTP ' . $code . ')');
        
    } else {
        // Custom auth - try GET on API base URL with provided auth
        $api_base_url = esc_url_raw($_POST['api_base_url'] ?? '');
        $bearer_token = sanitize_text_field($_POST['bearer_token'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = sanitize_text_field($_POST['password'] ?? '');
        
        if (empty($api_base_url)) {
            wp_send_json_error('API Base URL is required');
        }

        $headers = ['Content-Type' => 'application/json'];
        
        if (!empty($bearer_token)) {
            $headers['Authorization'] = 'Bearer ' . $bearer_token;
        }
        if (!empty($api_key)) {
            $headers['X-API-Key'] = $api_key;
        }
        
        $response = wp_remote_get($api_base_url, [
            'headers' => $headers,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            wp_send_json_success(['message' => 'Custom API connection successful!']);
        }
        wp_send_json_error('Custom API error (HTTP ' . $code . ')');
    }
}


// Save Credentials Simple AJAX (used by wizard - handles all vendor types)
add_action('wp_ajax_amrod_save_credentials_simple', 'amrod_ajax_save_credentials_simple');
function amrod_ajax_save_credentials_simple() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? 'amrod');
    $schema = amrod_get_credential_schema($vendor_id);
    $auth_type = $schema['auth_type'] ?? 'vendor_login';
    
    // Get all posted fields
    $posted_fields = $_POST;
    
    // Validate required fields for this vendor type
    $errors = [];
    foreach ($schema['fields'] as $field) {
        if ($field['required']) {
            $key = $field['key'];
            $value = $posted_fields[$key] ?? '';
            if (empty($value)) {
                $errors[] = $field['label'] . ' is required';
            }
        }
    }
    
    if (!empty($errors)) {
        wp_send_json_error('Please fill in required fields: ' . implode(', ', $errors));
    }
    
    // Build credentials array for this vendor
    $credentials = [
        'vendor_id' => $vendor_id,
        'auth_type' => $auth_type,
        'saved_at' => current_time('mysql'),
    ];
    
    // Save only the fields relevant to this vendor (and non-empty)
    foreach ($schema['fields'] as $field) {
        $key = $field['key'];
        $value = $posted_fields[$key] ?? '';
        
        if (!empty($value)) {
            if ($field['type'] === 'password') {
                // Store passwords with encryption note (in production, use proper encryption)
                $credentials[$key] = $value;
            } else {
                $credentials[$key] = sanitize_text_field($value);
            }
        }
    }
    
    // Store per-vendor credentials
    $vendor_creds = get_option('woosync_vendor_credentials', []);
    if (!is_array($vendor_creds)) $vendor_creds = [];
    $vendor_creds[$vendor_id] = $credentials;
    update_option('woosync_vendor_credentials', $vendor_creds);
    
    // Also update legacy options for Amrod (for backward compatibility)
    if ($vendor_id === 'amrod') {
        if (!empty($posted_fields['username'])) update_option('amrod_username', sanitize_text_field($posted_fields['username']));
        if (!empty($posted_fields['password'])) update_option('amrod_password', sanitize_text_field($posted_fields['password']));
        if (!empty($posted_fields['customer_code'])) update_option('amrod_customer_code', sanitize_text_field($posted_fields['customer_code']));
        if (!empty($posted_fields['auth_url'])) update_option('amrod_auth_url', esc_url_raw($posted_fields['auth_url']));
        if (!empty($posted_fields['api_base_url'])) update_option('amrod_api_url', esc_url_raw($posted_fields['api_base_url']));
    }
    
    // Store vendor in vendors list
    $vendors = get_option('woosync_vendors', []);
    if (!is_array($vendors)) $vendors = [];
    $vendors[$vendor_id] = array_merge($vendors[$vendor_id] ?? [], [
        'connected' => true,
        'connected_at' => current_time('mysql'),
        'auth_type' => $auth_type,
    ]);
    update_option('woosync_vendors', $vendors);
    
    wp_send_json_success(['message' => 'Credentials saved successfully!', 'vendor_id' => $vendor_id]);
}


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

// Search Products AJAX (with fuzzy matching)
add_action('wp_ajax_amrod_search_products', 'amrod_ajax_search_products');
function amrod_ajax_search_products() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    
    $token = amrod_get_token();
    if (!$token) {
        wp_send_json_error('Failed to obtain token');
    }

    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        wp_send_json_error('Products endpoint not enabled');
    }

    $products_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products)) {
        wp_send_json_error('Failed to fetch products');
    }

    // Fuzzy search matching
    $query_lower = strtolower($query);
    $results = [];
    
    foreach ($products as $product) {
        $name = strtolower($product['Description'] ?? '');
        $sku = strtolower($product['ProductCode'] ?? '');
        
        // Exact match
        if ($query_lower === '' || $query_lower === $name || $query_lower === $sku) {
            $results[] = $product;
            continue;
        }
        
        // Contains match
        if (strpos($name, $query_lower) !== false || strpos($sku, $query_lower) !== false) {
            $results[] = $product;
            continue;
        }
        
        // Fuzzy match - check if all query words match
        $query_words = array_filter(explode(' ', $query_lower));
        $match_count = 0;
        foreach ($query_words as $word) {
            if (strlen($word) >= 2 && (strpos($name, $word) !== false || strpos($sku, $word) !== false)) {
                $match_count++;
            }
        }
        if ($match_count === count($query_words) && count($query_words) > 0) {
            $results[] = $product;
        }
    }

    // Limit results
    $results = array_slice($results, 0, 50);
    
    // Format for display
    $formatted = array_map(function($p) {
        return [
            'id' => $p['ProductCode'] ?? '',
            'name' => $p['Description'] ?? 'Unknown',
            'sku' => $p['ProductCode'] ?? '',
            'price' => $p['Price'] ?? 0,
            'image' => $p['ImageURL'] ?? '',
            'category' => $p['CategoryName'] ?? '',
        ];
    }, $results);

    wp_send_json_success(['products' => $formatted, 'total' => count($results)]);
}

// Get Product Preview AJAX
add_action('wp_ajax_amrod_get_product_preview', 'amrod_ajax_get_product_preview');
function amrod_ajax_get_product_preview() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $product_code = sanitize_text_field($_POST['product_code'] ?? '');
    
    $token = amrod_get_token();
    if (!$token) {
        wp_send_json_error('Failed to obtain token');
    }

    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        wp_send_json_error('Products endpoint not enabled');
    }

    $products_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products)) {
        wp_send_json_error('Failed to fetch products');
    }

    // Find the product
    foreach ($products as $product) {
        if (($product['ProductCode'] ?? '') === $product_code) {
            $mapping = get_option('amrod_field_mapping', []);
            $preview = amrod_generate_preview($product, $mapping);
            wp_send_json_success($preview);
        }
    }
    
    wp_send_json_error('Product not found');
}

// Auto-detect fields AJAX
add_action('wp_ajax_amrod_auto_detect_fields', 'amrod_ajax_auto_detect_fields');
function amrod_ajax_auto_detect_fields() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $token = amrod_get_token();
    if (!$token) {
        wp_send_json_error('Failed to fetch token');
    }

    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        wp_send_json_error('Products endpoint not enabled');
    }

    $products_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products) || empty($products)) {
        wp_send_json_error('Failed to fetch products');
    }

    $sample = $products[0];
    $fields = array_keys($sample);
    
    $mapping = [];
    $rules = amrod_get_field_mapping_rules();
    
    foreach ($rules as $wc_key => $rule) {
        foreach ($rule['patterns'] as $pattern) {
            if (in_array($pattern, $fields)) {
                $mapping[$wc_key] = $pattern;
                break;
            }
        }
    }
    
    wp_send_json_success([
        'fields' => $fields,
        'mapping' => $mapping,
        'sample_product' => $sample
    ]);
}

// Save field mapping AJAX
add_action('wp_ajax_amrod_save_field_mapping', 'amrod_ajax_save_field_mapping');
function amrod_ajax_save_field_mapping() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $mapping = $_POST['mapping'] ?? [];
    update_option('amrod_field_mapping', $mapping);
    update_option('amrod_mapped_fields', count(array_filter($mapping)));
    
    wp_send_json_success(['message' => 'Field mapping saved successfully']);
}

// Save endpoint AJAX
add_action('wp_ajax_amrod_save_endpoint', 'amrod_ajax_save_endpoint');
function amrod_ajax_save_endpoint() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $endpoint_key = sanitize_text_field($_POST['endpoint'] ?? '');
    $enabled = intval($_POST['enabled'] ?? 0);
    
    $endpoints = amrod_get_endpoints();
    if (isset($endpoints[$endpoint_key])) {
        $endpoints[$endpoint_key]['enabled'] = $enabled;
        amrod_save_endpoints($endpoints);
        wp_send_json_success(['message' => 'Endpoint updated']);
    }
    
    wp_send_json_error('Endpoint not found');
}

// Refresh Tier Status AJAX
add_action('wp_ajax_amrod_refresh_tier_status', 'amrod_ajax_refresh_tier_status');
function amrod_ajax_refresh_tier_status() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? 'amrod');
    
    $token = amrod_get_token();
    if (!$token) {
        wp_send_json_error('Failed to obtain token');
    }
    
    $auth_url = get_option('amrod_auth_url', 'https://identity.amrod.co.za') . '/VendorLogin';
    $payload = json_encode([
        'username' => get_option('amrod_username'),
        'password' => amrod_get_password(),
        'CustomerCode' => get_option('amrod_customer_code')
    ]);

    $response = wp_remote_post($auth_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $payload,
        'timeout' => 20,
    ]);

    $tier_data = [
        'tier' => 'Standard',
        'tier_level' => 0,
        'tier_active_since' => current_time('mysql'),
    ];
    
    if (!is_wp_error($response)) {
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($data['tier'])) {
                $tier_data['tier'] = $data['tier'];
            } elseif (isset($data['pricingLevel'])) {
                $tier_data['tier'] = $data['pricingLevel'];
            } elseif (isset($data['customerTier'])) {
                $tier_data['tier'] = $data['customerTier'];
            } elseif (isset($data['PricingLevel'])) {
                $tier_data['tier'] = $data['PricingLevel'];
            }
            
            $tier_data['tier_level'] = amrod_get_tier_level($tier_data['tier']);
            
            if (isset($data['tierExpiry']) || isset($data['ExpiryDate'])) {
                $tier_data['tier_expiry'] = $data['tierExpiry'] ?? $data['ExpiryDate'];
            }
            
            if (isset($data['upgradeUrl']) || isset($data['upgrade_url'])) {
                $tier_data['upgrade_url'] = $data['upgradeUrl'] ?? $data['upgrade_url'];
            }
        }
    }
    
    amrod_save_vendor_tier($vendor_id, $tier_data);
    
    wp_send_json_success([
        'message' => 'Tier status refreshed',
        'tier' => $tier_data['tier'],
        'tier_level' => $tier_data['tier_level']
    ]);
}

// Save Tier Settings AJAX
add_action('wp_ajax_amrod_save_tier_settings', 'amrod_ajax_save_tier_settings');
function amrod_ajax_save_tier_settings() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? 'amrod');
    $tier = sanitize_text_field($_POST['tier'] ?? 'Standard');
    $tier_notes = sanitize_textarea_field($_POST['tier_notes'] ?? '');
    $tier_pricing_endpoint = sanitize_text_field($_POST['tier_pricing_endpoint'] ?? '/api/v1/Prices/');
    $markup_percent = floatval($_POST['markup_percent'] ?? 30);
    
    $tier_data = [
        'tier' => $tier,
        'tier_level' => amrod_get_tier_level($tier),
        'tier_notes' => $tier_notes,
        'tier_pricing_endpoint' => $tier_pricing_endpoint,
        'tier_active_since' => current_time('mysql'),
    ];
    
    amrod_save_vendor_tier($vendor_id, $tier_data);
    update_option('woosync_markup_percent', $markup_percent);
    
    wp_send_json_success(['message' => 'Tier settings saved']);
}

// Get Tier Savings AJAX
add_action('wp_ajax_amrod_get_tier_savings', 'amrod_ajax_get_tier_savings');
function amrod_ajax_get_tier_savings() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_tier = amrod_get_vendor_tier('amrod');
    $tier = $vendor_tier['tier'] ?? 'Standard';
    
    if ($tier === 'Standard') {
        wp_send_json_success([
            'products' => [],
            'total_savings' => 0,
            'product_count' => 0,
            'message' => 'Standard tier - no savings'
        ]);
    }
    
    $token = amrod_get_token();
    if (!$token) {
        wp_send_json_error('Failed to obtain token');
    }
    
    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    $prices_ep = $endpoints['prices'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        wp_send_json_error('Products endpoint not enabled');
    }
    
    $api_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za');
    
    $products_url = $api_url . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products)) {
        wp_send_json_error('Failed to fetch products');
    }
    
    $tier_prices = [];
    if ($prices_ep && $prices_ep['enabled']) {
        $prices_url = $api_url . $prices_ep['path'];
        $prices = amrod_get_endpoint($token, $prices_url);
        if ($prices && is_array($prices)) {
            foreach ($prices as $price_item) {
                $code = $price_item['ProductCode'] ?? $price_item['ItemCode'] ?? '';
                $tier_prices[$code] = floatval($price_item['Price'] ?? $price_item['TierPrice'] ?? 0);
            }
        }
    }
    
    $savings = [];
    $total_savings = 0;
    $mapping = get_option('amrod_field_mapping', []);
    
    foreach ($products as $product) {
        $code = $product[$mapping['sku'] ?? 'ProductCode'] ?? '';
        $name = $product[$mapping['name'] ?? 'Description'] ?? 'Unknown';
        $standard_price = floatval($product[$mapping['price'] ?? 'Price'] ?? 0);
        $tier_price = $tier_prices[$code] ?? $standard_price;
        
        if ($tier_price < $standard_price && $tier_price > 0) {
            $savings_per_unit = $standard_price - $tier_price;
            $total_savings += $savings_per_unit;
            
            $savings[] = [
                'code' => $code,
                'name' => $name,
                'standard_price' => $standard_price,
                'tier_price' => $tier_price,
                'savings_per_unit' => $savings_per_unit,
                'savings_percent' => round(($savings_per_unit / $standard_price) * 100, 1),
            ];
        }
    }
    
    usort($savings, function($a, $b) {
        return $b['savings_per_unit'] <=> $a['savings_per_unit'];
    });
    
    wp_send_json_success([
        'products' => array_slice($savings, 0, 100),
        'total_savings' => $total_savings,
        'product_count' => count($savings),
        'tier' => $tier
    ]);
}

// Get Product Preview with Tier AJAX
add_action('wp_ajax_amrod_get_product_preview_tier', 'amrod_ajax_get_product_preview_tier');
function amrod_ajax_get_product_preview_tier() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $product_code = sanitize_text_field($_POST['product_code'] ?? '');
    
    $token = amrod_get_token();
    if (!$token) {
        wp_send_json_error('Failed to obtain token');
    }

    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        wp_send_json_error('Products endpoint not enabled');
    }

    $products_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products)) {
        wp_send_json_error('Failed to fetch products');
    }

    foreach ($products as $product) {
        if (($product['ProductCode'] ?? '') === $product_code) {
            $mapping = get_option('amrod_field_mapping', []);
            $vendor_tier = amrod_get_vendor_tier('amrod');
            $preview = amrod_generate_preview_with_tier($product, $mapping, $vendor_tier);
            wp_send_json_success($preview);
        }
    }
    
    wp_send_json_error('Product not found');
}

// Sync single product AJAX
add_action('wp_ajax_amrod_sync_single_product', 'amrod_ajax_sync_single_product');
function amrod_ajax_sync_single_product() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $product_code = sanitize_text_field($_POST['product_code'] ?? '');
    
    $token = amrod_get_token();
    if (!$token) {
        wp_send_json_error('Failed to obtain token');
    }

    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        wp_send_json_error('Products endpoint not enabled');
    }

    $products_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products)) {
        wp_send_json_error('Failed to fetch products');
    }

    foreach ($products as $product) {
        if (($product['ProductCode'] ?? '') === $product_code) {
            $result = amrod_sync_single_product($product);
            if ($result) {
                wp_send_json_success(['message' => 'Product synced successfully']);
            } else {
                wp_send_json_error('Failed to sync product');
            }
        }
    }
    
    wp_send_json_error('Product not found');
}

// ===== HELPER FUNCTIONS =====

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

// ===== VENDOR TIER FUNCTIONS =====
function amrod_get_vendor_tier($vendor_id = 'amrod') {
    $vendors = get_option('woosync_vendors', []);
    if (!is_array($vendors)) {
        $vendors = [];
    }
    return $vendors[$vendor_id] ?? [
        'tier' => 'Standard',
        'tier_level' => 0,
        'tier_pricing_endpoint' => '/api/v1/Prices/',
        'tier_active_since' => '',
        'tier_expiry' => '',
        'tier_notes' => '',
        'last_tier_check' => '',
        'upgrade_url' => '',
    ];
}

function amrod_save_vendor_tier($vendor_id, $tier_data) {
    $vendors = get_option('woosync_vendors', []);
    if (!is_array($vendors)) {
        $vendors = [];
    }
    $vendors[$vendor_id] = array_merge(amrod_get_vendor_tier($vendor_id), $tier_data);
    $vendors[$vendor_id]['last_tier_check'] = current_time('mysql');
    update_option('woosync_vendors', $vendors);
}

function amrod_get_tier_level($tier_name) {
    $tiers = [
        'Standard' => 0,
        'Bronze' => 1,
        'Silver' => 2,
        'Gold' => 3,
        'Platinum' => 4,
    ];
    return $tiers[$tier_name] ?? 0;
}

function amrod_get_tier_color_class($tier_name) {
    $colors = [
        'Standard' => 'tier-standard',
        'Bronze' => 'tier-bronze',
        'Silver' => 'tier-silver',
        'Gold' => 'tier-gold',
        'Platinum' => 'tier-platinum',
    ];
    return $colors[$tier_name] ?? 'tier-standard';
}

function amrod_get_tier_icon($tier_name) {
    $icons = [
        'Standard' => '📋',
        'Bronze' => '🥉',
        'Silver' => '🥈',
        'Gold' => '🏆',
        'Platinum' => '💎',
    ];
    return $icons[$tier_name] ?? '📋';
}

function amrod_generate_preview_with_tier($product, $mapping, $vendor_tier) {
    $preview = amrod_generate_preview($product, $mapping);
    
    $tier = $vendor_tier['tier'] ?? 'Standard';
    $markup_percent = get_option('woosync_markup_percent', 30);
    
    $preview['tier'] = $tier;
    $preview['tier_price'] = $preview['price'];
    $preview['markup_percent'] = $markup_percent;
    $preview['markup_amount'] = 0;
    $preview['customer_price'] = $preview['price'];
    $preview['your_margin'] = 0;
    $preview['has_tier_pricing'] = ($tier !== 'Standard');
    
    if ($tier === 'Standard') {
        $preview['tier_price'] = $preview['price'];
        $preview['markup_amount'] = $preview['price'] * ($markup_percent / 100);
        $preview['customer_price'] = $preview['price'] + $preview['markup_amount'];
        $preview['your_margin'] = $preview['markup_amount'];
        return $preview;
    }
    
    $token = amrod_get_token();
    if ($token) {
        $api_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za');
        $prices_endpoint = $vendor_tier['tier_pricing_endpoint'] ?? '/api/v1/Prices/';
        $prices_url = $api_url . $prices_endpoint;
        
        $prices = amrod_get_endpoint($token, $prices_url);
        if ($prices && is_array($prices)) {
            $code = $product[$mapping['sku'] ?? 'ProductCode'] ?? '';
            foreach ($prices as $price_item) {
                $item_code = $price_item['ProductCode'] ?? $price_item['ItemCode'] ?? '';
                if ($item_code === $code) {
                    $tier_price = floatval($price_item['Price'] ?? $price_item['TierPrice'] ?? 0);
                    if ($tier_price > 0) {
                        $preview['tier_price'] = $tier_price;
                        $preview['markup_amount'] = $tier_price * ($markup_percent / 100);
                        $preview['customer_price'] = $tier_price + $preview['markup_amount'];
                        $preview['your_margin'] = $preview['markup_amount'];
                    }
                    break;
                }
            }
        }
    }
    
    return $preview;
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
        'username' => get_option('amrod_username'),
        'password' => amrod_get_password(),
        'CustomerCode' => get_option('amrod_customer_code')
    ]);

    $response = wp_remote_post($auth_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $payload,
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
    $log = array_slice($log, -500);
    update_option('amrod_sync_log', $log);
}

// Generate product preview array
function amrod_generate_preview($product, $mapping) {
    $preview = [
        'sku' => $product[$mapping['sku'] ?? 'ProductCode'] ?? '',
        'name' => $product[$mapping['name'] ?? 'Description'] ?? '',
        'price' => $product[$mapping['price'] ?? 'Price'] ?? 0,
        'description' => $product[$mapping['description'] ?? 'LongDescription'] ?? '',
        'category' => $product[$mapping['categories'] ?? 'CategoryName'] ?? '',
        'brand' => $product[$mapping['brand'] ?? 'Brand'] ?? '',
        'colour' => $product[$mapping['colour'] ?? 'Colour'] ?? '',
        'image' => $product[$mapping['image'] ?? 'ImageURL'] ?? '',
        'stock' => $product[$mapping['stock'] ?? 'Stock'] ?? 'N/A',
    ];
    
    return $preview;
}

// Sync single product to WooCommerce
function amrod_sync_single_product($product) {
    if (empty($product['ProductCode'])) return false;

    $sku = sanitize_text_field($product['ProductCode']);
    $existing_id = wc_get_product_id_by_sku($sku);

    try {
        if ($existing_id) {
            $wc_product = wc_get_product($existing_id);
        } else {
            $wc_product = new WC_Product_Simple();
            $wc_product->set_status('publish');
            $wc_product->set_catalog_visibility('visible');
        }

        if (!$wc_product || !is_a($wc_product, 'WC_Product')) return false;

        $mapping = get_option('amrod_field_mapping', []);
        
        $wc_product->set_sku($sku);
        $wc_product->set_name(sanitize_text_field($product['Description'] ?? 'Product'));
        
        $price_field = $mapping['price'] ?? 'Price';
        $wc_product->set_regular_price(floatval($product[$price_field] ?? 0));
        
        $desc_field = $mapping['description'] ?? 'LongDescription';
        $wc_product->set_description($product[$desc_field] ?? '');
        
        $category_ids = amrod_get_category_ids($product, $mapping);
        if (!empty($category_ids)) {
            $wc_product->set_category_ids($category_ids);
        }
        
        $product_id = $wc_product->save();
        
        if ($product_id && $product_id > 0) {
            amrod_sync_log("✅ Synced product: {$product['Description']} ({$sku})");
            return true;
        }
    } catch (Exception $e) {
        amrod_sync_log('❌ Error syncing product ' . $sku . ': ' . $e->getMessage());
    }
    
    return false;
}

// ===== SYNC BATCH FUNCTION (from previous version) =====
function amrod_sync_process_batch($offset = 0, $batch_size = 200) {
    $token = amrod_get_token();
    if (!$token) {
        return ['success' => false, 'processed_total' => 0, 'total' => 0, 'more' => false, 'error' => 'Failed to obtain token'];
    }

    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        return ['success' => false, 'processed_total' => 0, 'total' => 0, 'more' => false, 'error' => 'Products endpoint not enabled'];
    }

    $products_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products)) {
        return ['success' => false, 'processed_total' => 0, 'total' => 0, 'more' => false, 'error' => 'Failed to fetch products'];
    }

    $total = count($products);
    $batch = array_slice($products, $offset, $batch_size);
    $processed = 0;
    $mapping = get_option('amrod_field_mapping', []);

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
            
            $price_field = $mapping['price'] ?? 'Price';
            $wc_product->set_regular_price(floatval($p[$price_field] ?? 0));
            
            $desc_field = $mapping['description'] ?? 'LongDescription';
            $wc_product->set_description($p[$desc_field] ?? '');
            
            $category_ids = amrod_get_category_ids($p, $mapping);
            if (!empty($category_ids)) {
                $wc_product->set_category_ids($category_ids);
            }

            $product_id = $wc_product->save();
            if ($product_id && $product_id > 0) {
                $processed++;
            }
        } catch (Exception $e) {
            amrod_sync_log('❌ Error syncing product ' . $sku . ': ' . $e->getMessage());
            continue;
        }
    }

    $prev_total = (int) get_option('amrod_total_products', 0);
    update_option('amrod_total_products', $prev_total + $processed);
    update_option('amrod_last_sync', current_time('mysql'));

    $next_offset = $offset + $batch_size;
    $more = $next_offset < $total;
    
    amrod_sync_log("✅ Batch complete: {$processed} products synced");
    
    return [
        'success' => true,
        'processed' => $processed,
        'processed_total' => $prev_total + $processed,
        'total' => $total,
        'more' => $more,
        'next_offset' => $next_offset
    ];
}

// ===== HELPER FUNCTIONS FOR SYNC =====

function amrod_get_or_create_category($category_name) {
    $category_name = sanitize_text_field(trim($category_name));
    if (empty($category_name)) return 0;

    $term = get_term_by('name', $category_name, 'product_cat');
    if ($term) {
        return (int) $term->term_id;
    }

    $result = wp_insert_term($category_name, 'product_cat', [
        'description' => 'Created by WooSync',
        'parent' => 0,
    ]);

    if (is_wp_error($result)) {
        if ($result->get_error_code() === 'term_exists') {
            return (int) $result->get_error_data();
        }
        amrod_sync_log('❌ Failed to create category: ' . $result->get_error_message());
        return 0;
    }

    return (int) $result['term_id'];
}

function amrod_get_category_ids($product, $mapping) {
    $category_ids = [];
    $category_field = $mapping['categories'] ?? 'CategoryName';

    if (!isset($product[$category_field]) || empty($product[$category_field])) {
        return $category_ids;
    }

    $category_value = $product[$category_field];

    if (is_array($category_value)) {
        foreach ($category_value as $cat) {
            $cat_id = amrod_get_or_create_category($cat);
            if ($cat_id > 0) {
                $category_ids[] = $cat_id;
            }
        }
    } elseif (is_string($category_value) && strpos($category_value, ',') !== false) {
        $categories = array_map('trim', explode(',', $category_value));
        foreach ($categories as $cat) {
            $cat_id = amrod_get_or_create_category($cat);
            if ($cat_id > 0) {
                $category_ids[] = $cat_id;
            }
        }
    } elseif (is_string($category_value) && strpos($category_value, '|') !== false) {
        $categories = array_map('trim', explode('|', $category_value));
        foreach ($categories as $cat) {
            $cat_id = amrod_get_or_create_category($cat);
            if ($cat_id > 0) {
                $category_ids[] = $cat_id;
            }
        }
    } elseif (is_string($category_value) && strpos($category_value, '/') !== false) {
        $categories = array_map('trim', explode('/', $category_value));
        foreach ($categories as $cat) {
            $cat_id = amrod_get_or_create_category($cat);
            if ($cat_id > 0) {
                $category_ids[] = $cat_id;
            }
        }
    } else {
        $cat_id = amrod_get_or_create_category($category_value);
        if ($cat_id > 0) {
            $category_ids[] = $cat_id;
        }
    }

    return array_unique($category_ids);
}

// ===== PASSWORD VISIBILITY TOGGLE =====
add_action('admin_footer', function() {
    ?>
    <script>
    function togglePasswordVisibility() {
        const field = document.getElementById('passwordField');
        if (field) {
            field.type = field.type === 'password' ? 'text' : 'password';
        }
    }
    </script>
    <?php
});

// ===== PROMO SHARE TAB =====

// Register Promo Share submenu
add_action('admin_menu', 'amrod_register_promo_share_menu');
function amrod_register_promo_share_menu() {
    if (!class_exists('WooCommerce')) return;
    
    add_submenu_page(
        'amrod-sync',
        'Promo Share',
        '6. Promo Share',
        'manage_options',
        'amrod-sync-promo-share',
        'amrod_render_promo_share_page'
    );
}

// Enqueue promo share JS
add_action('admin_enqueue_scripts', 'amrod_enqueue_promo_share_assets');
function amrod_enqueue_promo_share_assets($hook) {
    if (strpos($hook, 'amrod-sync-promo-share') === false) return;
    
    // Bootstrap Icons
    wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css', [], '1.10.0');
    
    // Promo Share JS
    wp_enqueue_script('amrod-promo-share-js', AMROD_SYNC_ASSETS . 'js/promo-share.js', ['jquery'], AMROD_SYNC_VERSION, true);
    wp_enqueue_script('amrod-tier-settings-js', AMROD_SYNC_ASSETS . 'js/tier-settings.js', ['jquery'], AMROD_SYNC_VERSION, true);
    
    // Localize data
    wp_localize_script('amrod-promo-share-js', 'amrodSyncData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('amrod_sync_nonce'),
    ]);
}

// Fetch promos from Amrod API
function woosync_fetch_promos($vendor_id = 'amrod') {
    $cache_key = 'woosync_promos_' . $vendor_id;
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $token = amrod_get_token();
    if (!$token) {
        return ['error' => 'Failed to obtain API token'];
    }
    
    $endpoints = amrod_get_endpoints();
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        return ['error' => 'Products endpoint not enabled'];
    }
    
    $products_url = get_option('amrod_api_url', 'https://vendorapi.amrod.co.za') . $products_ep['path'];
    $products = amrod_get_endpoint($token, $products_url);
    
    if ($products === false || !is_array($products)) {
        return ['error' => 'Failed to fetch products from API'];
    }
    
    $mapping = get_option('amrod_field_mapping', []);
    
    // Filter and process promos
    $promos = [];
    $now = current_time('mysql');
    $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    foreach ($products as $product) {
        $is_promo = false;
        $promo = [
            'product_code' => $product[$mapping['sku'] ?? 'ProductCode'] ?? '',
            'sku' => $product[$mapping['sku'] ?? 'ProductCode'] ?? '',
            'name' => $product[$mapping['name'] ?? 'Description'] ?? '',
            'price' => floatval($product[$mapping['price'] ?? 'Price'] ?? 0),
            'sale_price' => floatval($product[$mapping['sale_price'] ?? 'SalePrice'] ?? 0),
            'image' => $product[$mapping['image'] ?? 'ImageURL'] ?? '',
            'banner_image' => $product[$mapping['banner_image'] ?? 'BannerImage'] ?? '',
            'hero_image' => $product['HeroImage'] ?? '',
            'marketing_image' => $product['MarketingImage'] ?? '',
            'product_url' => get_permalink(wc_get_product_id_by_sku($product[$mapping['sku'] ?? 'ProductCode'] ?? '')) ?: '#',
            'clearance' => false,
            'on_sale' => false,
            'special' => false,
            'deal_of_day' => false,
            'featured' => false,
            'campaign_type' => 'all',
            'campaign_end' => '',
            'is_new' => false,
            'created_at' => $product['CreatedAt'] ?? '',
        ];
        
        // Check promo flags
        $clearance_field = $mapping['clearance'] ?? 'Clearance';
        $deal_field = $mapping['deal_of_day'] ?? 'DealOfTheDay';
        
        // Clearance check
        if (!empty($product[$clearance_field]) && in_array(strtolower($product[$clearance_field]), ['true', '1', 'yes', 'clearance'])) {
            $promo['clearance'] = true;
            $is_promo = true;
        }
        
        // OnSale check
        if (!empty($product['OnSale']) && in_array(strtolower($product['OnSale']), ['true', '1', 'yes'])) {
            $promo['on_sale'] = true;
            $is_promo = true;
        }
        
        // Special check
        if (!empty($product['Special']) && in_array(strtolower($product['Special']), ['true', '1', 'yes'])) {
            $promo['special'] = true;
            $is_promo = true;
        }
        
        // Deal of the day check
        if (!empty($product[$deal_field]) && in_array(strtolower($product[$deal_field]), ['true', '1', 'yes'])) {
            $promo['deal_of_day'] = true;
            $is_promo = true;
        }
        
        // Featured check
        if (!empty($product['Featured']) && in_array(strtolower($product['Featured']), ['true', '1', 'yes'])) {
            $promo['featured'] = true;
            $is_promo = true;
        }
        
        // Has sale price (discounted item)
        if ($promo['sale_price'] > 0 && $promo['sale_price'] < $promo['price']) {
            $is_promo = true;
        }
        
        // Has banner/marketing image
        if (!empty($promo['banner_image']) || !empty($promo['hero_image']) || !empty($promo['marketing_image'])) {
            $is_promo = true;
        }
        
        // Check if new (added in last 7 days)
        if (!empty($promo['created_at'])) {
            try {
                $created = strtotime($promo['created_at']);
                if ($created && $created > strtotime($seven_days_ago)) {
                    $promo['is_new'] = true;
                }
            } catch (Exception $e) {
                // Ignore date parsing errors
            }
        }
        
        // Campaign end date
        if (!empty($product['CampaignEnd']) || !empty($product['SaleEndDate'])) {
            $promo['campaign_end'] = $product['CampaignEnd'] ?? $product['SaleEndDate'] ?? '';
        }
        
        // Set campaign type for filtering
        if ($promo['clearance']) {
            $promo['campaign_type'] = 'clearance';
        } elseif ($promo['deal_of_day']) {
            $promo['campaign_type'] = 'deal';
        } elseif ($promo['on_sale']) {
            $promo['campaign_type'] = 'sale';
        } elseif ($promo['featured']) {
            $promo['campaign_type'] = 'featured';
        }
        
        if ($is_promo) {
            $promos[] = $promo;
        }
    }
    
    // Cache for 1 hour
    set_transient($cache_key, $promos, HOUR_IN_SECONDS);
    update_option('woosync_last_promo_fetch', $now);
    
    return $promos;
}

// Promo Share Page Renderer
function amrod_render_promo_share_page() {
    $last_fetch = get_option('woosync_last_promo_fetch', 'Never');
    $connected = !empty(get_option('amrod_username')) && !empty(get_option('amrod_password'));
    ?>
    <div class="container-fluid mt-4 amrod-container" id="promoShareTab">
        <?php amrod_breadcrumb(['Dashboard' => '?page=amrod-sync', 'Promo Share' => false]); ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">📣 Promo Share</h1>
                <small class="text-muted">Share promotions and campaigns to social media</small>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <small class="text-muted">Last updated: <?php echo $last_fetch; ?></small>
                <button type="button" id="refreshPromosBtn" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Error Container -->
        <div id="promoError" style="display: none;"></div>

        <?php if (!$connected): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>API not connected.</strong> Please configure your Amrod API credentials in 
            <a href="?page=amrod-sync-settings" class="alert-link">Settings</a> to load promos.
        </div>
        <?php else: ?>
        
        <!-- Hero Banner -->
        <div id="promoHero">
            <div class="promo-hero-placeholder">
                <p class="text-muted">Loading promos...</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="promo-filter-bar">
            <button type="button" class="promo-filter-btn active" data-filter="all">All</button>
            <button type="button" class="promo-filter-btn" data-filter="clearance">🔥 Clearance</button>
            <button type="button" class="promo-filter-btn" data-filter="sale">🏷️ Sale</button>
            <button type="button" class="promo-filter-btn" data-filter="featured">⭐ Featured</button>
            <button type="button" class="promo-filter-btn" data-filter="deal">💰 Deal of the Day</button>
        </div>

        <!-- Promo Grid -->
        <div class="row" id="promoGrid">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <!-- Email Modal -->
    <div class="modal fade" id="promoEmailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-envelope me-2"></i>Send Promo Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="promoEmailForm">
                    <div class="modal-body">
                        <input type="hidden" id="emailProductCode" value="">
                        
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <p class="form-control-plaintext" id="emailProductName">-</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Subject</label>
                            <input type="text" id="emailSubject" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Recipients</label>
                            <div class="email-recipient-option">
                                <input type="radio" name="email_recipients" value="all" id="recipAll">
                                <label for="recipAll">All customers</label>
                            </div>
                            <div class="email-recipient-option">
                                <input type="radio" name="email_recipients" value="roles" id="recipRoles">
                                <label for="recipRoles">By role (select below)</label>
                            </div>
                            <div class="email-recipient-option">
                                <input type="radio" name="email_recipients" value="specific" id="recipSpecific">
                                <label for="recipSpecific">Specific users</label>
                            </div>
                            
                            <div id="emailUserList" style="display: none;">
                                <label class="form-label mt-2">Enter user IDs (comma separated)</label>
                                <input type="text" id="emailUserIds" class="form-control" placeholder="1, 5, 12">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="sendPromoEmailBtn" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

// AJAX: Fetch Promos
add_action('wp_ajax_woosync_fetch_promos', 'amrod_ajax_fetch_promos');
function amrod_ajax_fetch_promos() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'];
    
    if ($force_refresh) {
        delete_transient('woosync_promos_amrod');
    }
    
    $promos = woosync_fetch_promos('amrod');
    
    if (isset($promos['error'])) {
        wp_send_json_error($promos['error']);
    }
    
    wp_send_json_success(['promos' => $promos]);
}

// AJAX: Send Promo Email
add_action('wp_ajax_woosync_send_promo_email', 'amrod_ajax_send_promo_email');
function amrod_ajax_send_promo_email() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $product_code = sanitize_text_field($_POST['product_code'] ?? '');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $recipient_type = sanitize_text_field($_POST['recipient_type'] ?? 'all');
    $user_ids = sanitize_text_field($_POST['user_ids'] ?? '');
    
    if (empty($product_code) || empty($subject)) {
        wp_send_json_error('Product code and subject are required');
    }
    
    // Get the product
    $product_id = wc_get_product_id_by_sku($product_code);
    if (!$product_id) {
        wp_send_json_error('Product not found');
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Product not found');
    }
    
    // Get recipients
    $recipients = [];
    
    switch ($recipient_type) {
        case 'all':
            $customers = get_users(['role' => 'customer']);
            $recipients = array_map(function($user) {
                return $user->user_email;
            }, $customers);
            break;
            
        case 'roles':
            $subscribers = get_users(['role' => 'subscriber']);
            $recipients = array_map(function($user) {
                return $user->user_email;
            }, $subscribers);
            break;
            
        case 'specific':
            if (!empty($user_ids)) {
                $ids = array_map('intval', explode(',', $user_ids));
                foreach ($ids as $id) {
                    $user = get_user_by('id', $id);
                    if ($user) {
                        $recipients[] = $user->user_email;
                    }
                }
            }
            break;
    }
    
    if (empty($recipients)) {
        wp_send_json_error('No recipients found');
    }
    
    // Build email content
    $product_name = $product->get_name();
    $product_url = get_permalink($product_id);
    $image_url = get_the_post_thumbnail_url($product_id, 'large') ?: '';
    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();
    
    $message = "Check out this promo from MediaPlatform!\n\n";
    $message .= "{$product_name}\n";
    if ($sale_price) {
        $message .= "Was: R{$regular_price} | Now: R{$sale_price}\n";
    } else {
        $message .= "Price: R{$regular_price}\n";
    }
    $message .= "\nShop now: {$product_url}\n\n";
    $message .= "#promo #brandedmerch";
    
    // Send emails
    $sent = 0;
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    foreach ($recipients as $email) {
        $result = wp_mail($email, $subject, nl2br($message), $headers);
        if ($result) {
            $sent++;
        }
    }
    
    wp_send_json_success([
        'message' => "Email sent to {$sent} recipient(s)",
        'sent' => $sent,
        'total' => count($recipients)
    ]);
}

// =============================================================================
// TIERED PRICING SYSTEM - WooSync v3.2
// =============================================================================

// ===== REGISTER PRICING SETTINGS =====
add_action('admin_init', 'amrod_register_pricing_settings');
function amrod_register_pricing_settings() {
    register_setting('amrod_pricing_group', 'woosync_tiered_pricing_enabled');
    register_setting('amrod_pricing_group', 'woosync_default_markup');
    register_setting('amrod_pricing_group', 'woosync_role_markups');
    register_setting('amrod_pricing_group', 'woosync_user_markups');
    register_setting('amrod_pricing_group', 'woosync_minimum_margin');
    register_setting('amrod_pricing_group', 'woosync_maximum_discount');
    register_setting('amrod_pricing_group', 'woosync_clearance_minimum');
    register_setting('amrod_pricing_group', 'woosync_show_prices_logged_out');
    register_setting('amrod_pricing_group', 'woosync_customer_tiers');
}

// ===== ADD PRICING TAB TO MENU =====
add_action('admin_menu', 'amrod_register_pricing_menu');
function amrod_register_pricing_menu() {
    if (!class_exists('WooCommerce')) return;
    
    add_submenu_page(
        'amrod-sync',
        'Pricing',
        '6. Pricing',
        'manage_options',
        'amrod-sync-pricing',
        'amrod_render_pricing_page'
    );
}

// ===== ENQUEUE PRICING ASSETS =====
add_action('admin_enqueue_scripts', 'amrod_enqueue_pricing_assets');
function amrod_enqueue_pricing_assets($hook) {
    if (strpos($hook, 'amrod-sync-pricing') === false) return;
    
    // DataTables CSS (optional)
    wp_enqueue_style('amrod-pricing-css', AMROD_SYNC_ASSETS . 'css/admin.css', ['bootstrap5-css'], AMROD_SYNC_VERSION);
    
    // Pricing JS
    wp_enqueue_script('amrod-pricing-js', AMROD_SYNC_ASSETS . 'js/pricing.js', ['jquery'], AMROD_SYNC_VERSION, true);
    
    // Localize data
    wp_localize_script('amrod-pricing-js', 'amrodSyncData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('amrod_sync_nonce'),
    ]);
}

// ===== RENDER PRICING PAGE =====
function amrod_render_pricing_page() {
    $tiered_enabled = get_option('woosync_tiered_pricing_enabled', false);
    $default_markup = get_option('woosync_default_markup', 30);
    $role_markups = get_option('woosync_role_markups', []);
    $user_markups = get_option('woosync_user_markups', []);
    $min_margin = get_option('woosync_minimum_margin', 0);
    $max_discount = get_option('woosync_maximum_discount', 0);
    $clearance_min = get_option('woosync_clearance_minimum', 0);
    $show_logged_out = get_option('woosync_show_prices_logged_out', false);
    $customer_tiers = get_option('woosync_customer_tiers', []);
    
    // Get all WooCommerce roles
    global $wp_roles;
    $all_roles = $wp_roles->get_names();
    
    // Get default WooCommerce roles with their markups
    $default_roles = [
        'customer' => ['name' => 'Customer', 'markup' => $default_markup, 'enabled' => true],
        'subscriber' => ['name' => 'Subscriber', 'markup' => $default_markup, 'enabled' => true],
        'wholesale_customer' => ['name' => 'Wholesale', 'markup' => $default_markup - 10, 'enabled' => false],
    ];
    
    // Merge with stored markups
    $role_markups = is_array($role_markups) ? $role_markups : [];
    foreach ($all_roles as $slug => $name) {
        if (!isset($role_markups[$slug])) {
            $role_markups[$slug] = [
                'name' => $name,
                'markup' => $default_markup,
                'enabled' => ($slug === 'customer')
            ];
        }
    }
    ?>
    <div class="container-fluid mt-4 amrod-container">
        <?php amrod_breadcrumb(['Dashboard' => '?page=amrod-sync', 'Pricing' => false]); ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">💰 Pricing</h1>
                <small class="text-muted">Tiered markup pricing per user role and customer</small>
            </div>
            <div class="form-check form-switch pricing-toggle">
                <input class="form-check-input" type="checkbox" id="tieredPricingEnabled" <?php checked($tiered_enabled); ?>>
                <label class="form-check-label fw-bold" for="tieredPricingEnabled">
                    <?php echo $tiered_enabled ? '<span class="text-success">Active</span>' : '<span class="text-muted">Disabled</span>'; ?>
                </label>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="pricing-stat-card">
                    <div class="stat-value"><?php echo count($role_markups); ?></div>
                    <div class="stat-label">User Roles</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pricing-stat-card">
                    <div class="stat-value"><?php echo count($user_markups); ?></div>
                    <div class="stat-label">Custom Customers</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pricing-stat-card">
                    <div class="stat-value"><?php echo $default_markup; ?>%</div>
                    <div class="stat-label">Default Markup</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pricing-stat-card">
                    <div class="stat-value"><?php echo $min_margin; ?>%</div>
                    <div class="stat-label">Min Margin Floor</div>
                </div>
            </div>
        </div>

        <!-- Pricing Tabs -->
        <ul class="nav nav-tabs pricing-tabs mb-3" id="pricingTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="settings-tab" data-bs-toggle="tab" data-bs-target="#pricing-settings" type="button">⚙️ Settings</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="roles-tab" data-bs-toggle="tab" data-bs-target="#role-tiers" type="button">👥 Role Tiers</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customer-pricing" type="button">👤 Customer Pricing</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#price-preview" type="button">🔍 Price Preview</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="rules-tab" data-bs-toggle="tab" data-bs-target="#pricing-rules" type="button">📋 Rules</button>
            </li>
        </ul>

        <div class="tab-content" id="pricingTabContent">
            <!-- SETTINGS TAB -->
            <div class="tab-pane fade show active" id="pricing-settings" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card pricing-card">
                            <div class="card-header">
                                <h5 class="mb-0">⚙️ General Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Default/Blanket Markup %</label>
                                    <div class="input-group">
                                        <input type="number" id="defaultMarkup" class="form-control" value="<?php echo esc_attr($default_markup); ?>" min="0" max="500" step="1">
                                        <span class="input-group-text">%</span>
                                        <button class="btn btn-primary" id="saveDefaultMarkup">💾 Save</button>
                                    </div>
                                    <small class="text-muted">This is the fallback markup for all users/roles without custom pricing</small>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Actions</label>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-primary" id="applyBlanketAll">
                                            📋 Apply Blanket to All Roles
                                        </button>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="showPricesLoggedOut" <?php checked($show_logged_out); ?>>
                                        <label class="form-check-label" for="showPricesLoggedOut">
                                            Show prices with markup to logged-out users
                                        </label>
                                    </div>
                                    <small class="text-muted">When disabled, logged-out users see supplier prices</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card pricing-card">
                            <div class="card-header">
                                <h5 class="mb-0">📖 How Tiered Pricing Works</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6><strong>Priority Order (highest to lowest):</strong></h6>
                                    <ol class="mb-2">
                                        <li><strong>Per Individual Customer</strong> — Custom markup for specific customers</li>
                                        <li><strong>Per User Role</strong> — Markup applied to all users with that role</li>
                                        <li><strong>Per Product Base Price</strong> — Optional override (in product edit)</li>
                                        <li><strong>Default/Blanket Markup</strong> — Fallback for everyone else</li>
                                    </ol>
                                </div>
                                
                                <h6><strong>Key Features:</strong></h6>
                                <ul>
                                    <li>✅ Markup is applied at <strong>display time</strong> (client-side)</li>
                                    <li>✅ WooCommerce base prices stay unchanged</li>
                                    <li>✅ Google Shopping sees correct prices</li>
                                    <li>✅ Admin orders show base prices</li>
                                    <li>✅ You can still run sales on top of display prices</li>
                                </ul>
                                
                                <div class="alert alert-warning mt-3">
                                    <strong>⚠️ Important:</strong> The markup multiplier is calculated as:<br>
                                    <code>display_price = base_price × (1 + markup%)</code><br>
                                    So 30% markup means multiplying by 1.30
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROLE TIERS TAB -->
            <div class="tab-pane fade" id="role-tiers" role="tabpanel">
                <div class="card pricing-card">
                    <div class="card-header">
                        <h5 class="mb-0">👥 User Role Markup Tiers</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover role-tiers-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;">Role Name</th>
                                    <th style="width: 20%;">Markup %</th>
                                    <th style="width: 15%;">Status</th>
                                    <th style="width: 35%;">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($role_markups as $slug => $data): ?>
                                <tr class="<?php echo ($slug === 'customer') ? 'default-row' : ''; ?>">
                                    <td class="role-name">
                                        <?php echo esc_html($data['name'] ?? ucfirst($slug)); ?>
                                        <?php if ($slug === 'customer'): ?>
                                            <span class="badge bg-secondary ms-2">Default</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="markup-value" data-value="<?php echo esc_attr($data['markup'] ?? $default_markup); ?>" data-role="<?php echo esc_attr($slug); ?>">
                                            <?php echo esc_html($data['markup'] ?? $default_markup); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input role-markup-toggle" type="checkbox" data-role="<?php echo esc_attr($slug); ?>" <?php checked($data['enabled'] ?? false); ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php if ($slug === 'customer'): ?>
                                                Default tier for all new customers
                                            <?php elseif ($slug === 'subscriber'): ?>
                                                Subscribed newsletter users
                                            <?php elseif ($slug === 'wholesale_customer'): ?>
                                                Wholesale/volume buyers
                                            <?php else: ?>
                                                Role: <?php echo esc_html($slug); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            💡 Click on a markup value to edit inline. Changes save automatically.
                        </small>
                    </div>
                </div>
            </div>

            <!-- CUSTOMER PRICING TAB -->
            <div class="tab-pane fade" id="customer-pricing" role="tabpanel">
                <div class="row">
                    <div class="col-md-5">
                        <!-- Search & Add Customer -->
                        <div class="card pricing-card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0">🔍 Search & Add Customer</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <input type="text" id="customerSearch" class="form-control" placeholder="Search by name or email...">
                                    <div id="customerSearchResults" class="list-group" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                                </div>
                                
                                <div id="selectedCustomer" class="border rounded p-3" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong id="selectedCustomerName"></strong>
                                            <input type="hidden" id="selectedCustomerId">
                                        </div>
                                        <button class="btn btn-sm btn-outline-danger" onclick="clearCustomerSelection()">✕</button>
                                    </div>
                                </div>
                                
                                <div id="customerEditForm" class="mt-3" style="display: none;">
                                    <div class="mb-2">
                                        <label class="form-label small">Markup %</label>
                                        <input type="number" id="customerMarkup" class="form-control" min="0" max="500" step="1" value="30">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Tier Label</label>
                                        <select id="customerTier" class="form-select">
                                            <option value="none">None</option>
                                            <option value="gold">Gold</option>
                                            <option value="silver">Silver</option>
                                            <option value="bronze">Bronze</option>
                                            <option value="vip">VIP</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-success w-100" id="saveCustomerMarkup">💾 Save Customer Markup</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Import Customers -->
                        <div class="card pricing-card">
                            <div class="card-header">
                                <h5 class="mb-0">📥 Import from CSV</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">CSV format: <code>email,markup%,tier_label</code></p>
                                <form id="importCustomersForm" enctype="multipart/form-data">
                                    <?php wp_nonce_field('amrod_sync_nonce'); ?>
                                    <input type="hidden" name="action" value="woosync_import_customers">
                                    <div class="mb-2">
                                        <input type="file" name="import_file" class="form-control" accept=".csv">
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary w-100">📥 Import Customers</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <!-- Customer List with Custom Pricing -->
                        <div class="card pricing-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">👤 Customers with Custom Pricing</h5>
                                <button class="btn btn-sm btn-outline-primary" id="bulkActionBtn">☰ Bulk Actions</button>
                            </div>
                            <div class="card-body p-0">
                                <!-- Bulk Actions Bar -->
                                <div class="bulk-actions-bar" id="bulkActionsBar">
                                    <div class="row align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label small">Selected: <span id="bulkCount">0</span></label>
                                            <input type="hidden" id="bulkSelectedCustomers">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small">Set Markup %</label>
                                            <input type="number" id="bulkMarkup" class="form-control" min="0" max="500" step="1">
                                        </div>
                                        <div class="col-md-4">
                                            <button class="btn btn-primary btn-sm" id="bulkUpdateMarkup">Apply to Selected</button>
                                            <button class="btn btn-secondary btn-sm" onclick="$('#bulkActionsBar').removeClass('active')">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php
                                    $user_markups = is_array($user_markups) ? $user_markups : [];
                                    if (empty($user_markups)):
                                    ?>
                                    <div class="text-center text-muted py-5">
                                        <p>No customers with custom pricing yet.</p>
                                        <small>Search for a customer above to add custom pricing.</small>
                                    </div>
                                    <?php else: ?>
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 5%;"><input type="checkbox" id="selectAllCustomers"></th>
                                                <th style="width: 30%;">Customer</th>
                                                <th style="width: 20%;">Markup</th>
                                                <th style="width: 25%;">Tier</th>
                                                <th style="width: 20%;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($user_markups as $user_id => $data): 
                                                $user = get_userdata($user_id);
                                                if (!$user) continue;
                                            ?>
                                            <tr>
                                                <td><input type="checkbox" class="customer-checkbox" value="<?php echo esc_attr($user_id); ?>"></td>
                                                <td>
                                                    <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                                    <small class="text-muted"><?php echo esc_html($user->user_email); ?></small>
                                                </td>
                                                <td><span class="badge bg-primary"><?php echo esc_html($data['markup']); ?>%</span></td>
                                                <td>
                                                    <?php if (!empty($data['tier'])): ?>
                                                        <span class="tier-badge <?php echo esc_attr($data['tier']); ?>"><?php echo esc_html(ucfirst($data['tier'])); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="removeCustomerMarkup(<?php echo esc_attr($user_id); ?>)">🗑️</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRICE PREVIEW TAB -->
            <div class="tab-pane fade" id="price-preview" role="tabpanel">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card pricing-card">
                            <div class="card-header">
                                <h5 class="mb-0">🔍 Preview Configuration</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Select Product</label>
                                    <div class="position-relative">
                                        <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
                                        <div id="productSearchResults" class="list-group" style="display: none; position: absolute; z-index: 100; width: 100%; max-height: 200px; overflow-y: auto;"></div>
                                    </div>
                                    <select id="previewProduct" class="form-select mt-2">
                                        <option value="">— Select a product —</option>
                                        <?php
                                        $products = wc_get_products(['limit' => 100, 'status' => 'publish']);
                                        foreach ($products as $product):
                                        ?>
                                        <option value="<?php echo esc_attr($product->get_id()); ?>"><?php echo esc_html($product->get_name()); ?> (R<?php echo esc_html($product->get_price()); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Customer/User Role</label>
                                    <select id="previewRole" class="form-select">
                                        <option value="">— Default —</option>
                                        <?php foreach ($all_roles as $slug => $name): ?>
                                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Or Specific Customer</label>
                                    <select id="previewCustomer" class="form-select">
                                        <option value="">— None —</option>
                                        <?php
                                        $customers = get_users(['role' => 'customer', 'number' => 50]);
                                        foreach ($customers as $customer):
                                        ?>
                                        <option value="<?php echo esc_attr($customer->ID); ?>"><?php echo esc_html($customer->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card pricing-card">
                            <div class="card-header">
                                <h5 class="mb-0">💰 Price Preview</h5>
                            </div>
                            <div class="card-body">
                                <div id="pricePreviewResult" class="price-preview-box">
                                    <div class="text-center text-muted py-5">
                                        <div style="font-size: 48px; margin-bottom: 15px;">📊</div>
                                        <p>Select a product to see how pricing will appear</p>
                                        <small>Choose a customer or role to preview their specific pricing</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRICING RULES TAB -->
            <div class="tab-pane fade" id="pricing-rules" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card pricing-card rules-card">
                            <div class="card-header">
                                <h5 class="mb-0">📋 Pricing Rules & Floors</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Minimum Price Margin %</label>
                                    <div class="input-group">
                                        <input type="number" id="minimumMargin" class="form-control" value="<?php echo esc_attr($min_margin); ?>" min="0" max="100" step="1">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Don't let products go below supplier price + X%</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Maximum Discount %</label>
                                    <div class="input-group">
                                        <input type="number" id="maximumDiscount" class="form-control" value="<?php echo esc_attr($max_discount); ?>" min="0" max="100" step="1">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Cap how deep sales can go below display price</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Clearance Minimum Margin %</label>
                                    <div class="input-group">
                                        <input type="number" id="clearanceMinimum" class="form-control" value="<?php echo esc_attr($clearance_min); ?>" min="0" max="100" step="1">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Different rules for clearance products</small>
                                </div>
                                
                                <button class="btn btn-primary w-100" id="savePricingRules">💾 Save Pricing Rules</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card pricing-card">
                            <div class="card-header">
                                <h5 class="mb-0">🛒 Product Base Price Override</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Some products you may want a fixed margin on. Set the base price override in the product edit screen.</p>
                                
                                <div class="alert alert-info">
                                    <strong>How it works:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Edit any product in WooCommerce</li>
                                        <li>Find the "WooSync Pricing" meta box</li>
                                        <li>Set a custom base price</li>
                                        <li>This overrides the supplier price for this product</li>
                                    </ul>
                                </div>
                                
                                <div class="border rounded p-3 bg-light">
                                    <strong>Meta Key:</strong> <code>_woosync_base_price</code><br>
                                    <small class="text-muted">Stored per product in post meta</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function clearCustomerSelection() {
        jQuery('#selectedCustomer').hide();
        jQuery('#customerEditForm').hide();
        jQuery('#selectedCustomerId').val('');
        jQuery('#selectedCustomerName').text('');
    }
    
    function removeCustomerMarkup(userId) {
        if (!confirm('Remove custom pricing for this customer?')) return;
        
        jQuery.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_remove_customer_markup',
                nonce: amrodSyncData.nonce,
                customer_id: userId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    }
    
    jQuery('#bulkActionBtn').on('click', function() {
        jQuery('#bulkActionsBar').toggleClass('active');
    });
    
    jQuery('#selectAllCustomers').on('change', function() {
        var checked = jQuery(this).prop('checked');
        jQuery('.customer-checkbox').prop('checked', checked).trigger('change');
    });
    
    jQuery('#saveDefaultMarkup').on('click', function() {
        var markup = jQuery('#defaultMarkup').val();
        jQuery.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_save_default_markup',
                nonce: amrodSyncData.nonce,
                markup: markup
            },
            success: function(response) {
                if (response.success) {
                    alert('Default markup saved!');
                }
            }
        });
    });
    </script>
    <?php
}

// =============================================================================
// TIERED PRICING CALCULATION FUNCTIONS
// =============================================================================

/**
 * Get the markup percentage for a specific user
 * Priority: Individual customer > User role > Default
 */
function woosync_get_user_markup($user_id) {
    if (!$user_id) {
        return get_option('woosync_default_markup', 30) / 100;
    }
    
    // Check for individual customer markup first (highest priority)
    $user_markups = get_option('woosync_user_markups', []);
    if (isset($user_markups[$user_id])) {
        return floatval($user_markups[$user_id]['markup']) / 100;
    }
    
    // Fall back to user role markup
    $user = get_userdata($user_id);
    if ($user && !empty($user->roles)) {
        $role_markup = woosync_get_user_role_markup($user->roles[0]);
        if ($role_markup !== null) {
            return $role_markup;
        }
    }
    
    // Default fallback
    return get_option('woosync_default_markup', 30) / 100;
}

/**
 * Get markup for a specific user role
 */
function woosync_get_user_role_markup($role) {
    $role_markups = get_option('woosync_role_markups', []);
    
    if (isset($role_markups[$role]) && isset($role_markups[$role]['enabled']) && $role_markups[$role]['enabled']) {
        return floatval($role_markups[$role]['markup']) / 100;
    }
    
    return null; // Role doesn't have custom markup
}

/**
 * Get the base price for a product (with optional override)
 */
function woosync_get_product_base_price($product_id, $vendor_id = 0) {
    // Check for product-specific base price override
    $override_price = get_post_meta($product_id, '_woosync_base_price', true);
    if ($override_price !== '' && $override_price !== false) {
        return floatval($override_price);
    }
    
    // Otherwise get the WooCommerce regular price
    $product = wc_get_product($product_id);
    if ($product) {
        return floatval($product->get_regular_price());
    }
    
    return 0;
}

/**
 * Calculate the display price with markup applied
 */
function woosync_calculate_display_price($product_id, $user_id = 0) {
    $base_price = woosync_get_product_base_price($product_id);
    $markup = woosync_get_user_markup($user_id);
    
    // Apply minimum margin floor if set
    $min_margin = get_option('woosync_minimum_margin', 0) / 100;
    $min_price = $base_price * (1 + $min_margin);
    
    // Calculate display price
    $display_price = $base_price * (1 + $markup);
    
    // Ensure we don't go below minimum margin
    if ($display_price < $min_price) {
        $display_price = $min_price;
    }
    
    return $display_price;
}

/**
 * Format a price with South African Rand symbol
 */
function woosync_format_markup_price($price, $markup = null) {
    if ($markup !== null) {
        $final_price = $price * (1 + $markup);
    } else {
        $final_price = $price;
    }
    
    return 'R' . number_format($final_price, 2);
}

// =============================================================================
// WOOCOMMERCE PRICE HOOKS - Display Tiered Pricing on Frontend
// =============================================================================

/**
 * Filter product price to show tiered pricing to logged-in users
 */
add_filter('woocommerce_product_get_price', 'woosync_filter_product_price', 10, 2);
add_filter('woocommerce_product_get_sale_price', 'woosync_filter_product_price', 10, 2);
function woosync_filter_product_price($price, $product) {
    // Only apply if tiered pricing is enabled
    if (!get_option('woosync_tiered_pricing_enabled', false)) {
        return $price;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        // Show markup to logged-out users if enabled
        if (!get_option('woosync_show_prices_logged_out', false)) {
            return $price;
        }
        // Use default markup for logged-out users
        $user_id = 0;
    } else {
        $user_id = get_current_user_id();
    }
    
    // Get the calculated display price
    $display_price = woosync_calculate_display_price($product->get_id(), $user_id);
    
    return $display_price;
}

/**
 * Filter variations price
 */
add_filter('woocommerce_product_variation_get_price', 'woosync_filter_variation_price', 10, 2);
function woosync_filter_variation_price($price, $product) {
    return woosync_filter_product_price($price, $product);
}

// =============================================================================
// AJAX HANDLERS FOR PRICING
// =============================================================================

// Save Role Markup
add_action('wp_ajax_woosync_save_role_markup', 'woosync_ajax_save_role_markup');
function woosync_ajax_save_role_markup() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $role = sanitize_text_field($_POST['role']);
    $markup = floatval($_POST['markup']);
    
    $role_markups = get_option('woosync_role_markups', []);
    if (!isset($role_markups[$role])) {
        $role_markups[$role] = [];
    }
    $role_markups[$role]['markup'] = $markup;
    
    update_option('woosync_role_markups', $role_markups);
    wp_send_json_success(['message' => 'Role markup saved']);
}

// Toggle Role Markup
add_action('wp_ajax_woosync_toggle_role_markup', 'woosync_ajax_toggle_role_markup');
function woosync_ajax_toggle_role_markup() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $role = sanitize_text_field($_POST['role']);
    $enabled = intval($_POST['enabled']);
    
    $role_markups = get_option('woosync_role_markups', []);
    if (!isset($role_markups[$role])) {
        $role_markups[$role] = [];
    }
    $role_markups[$role]['enabled'] = $enabled;
    
    update_option('woosync_role_markups', $role_markups);
    wp_send_json_success(['message' => 'Role status updated']);
}

// Apply Blanket to All Roles
add_action('wp_ajax_woosync_apply_blanket_to_all', 'woosync_ajax_apply_blanket_to_all');
function woosync_ajax_apply_blanket_to_all() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $markup = floatval($_POST['markup']);
    
    $role_markups = get_option('woosync_role_markups', []);
    foreach ($role_markups as $slug => &$data) {
        $data['markup'] = $markup;
        $data['enabled'] = true;
    }
    
    update_option('woosync_role_markups', $role_markups);
    wp_send_json_success(['message' => 'Blanket applied to all roles']);
}

// Search Customers
add_action('wp_ajax_woosync_search_customers', 'woosync_ajax_search_customers');
function woosync_ajax_search_customers() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $query = sanitize_text_field($_POST['query']);
    
    $args = [
        'search' => "*{$query}*",
        'search_columns' => ['display_name', 'user_email'],
        'number' => 20,
    ];
    
    $users = get_users($args);
    $user_markups = get_option('woosync_user_markups', []);
    
    $results = [];
    foreach ($users as $user) {
        $markup = isset($user_markups[$user->ID]) ? $user_markups[$user->ID]['markup'] : 0;
        $results[] = [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'markup' => $markup,
        ];
    }
    
    wp_send_json_success(['customers' => $results]);
}

// Get Customer Markup
add_action('wp_ajax_woosync_get_customer_markup', 'woosync_ajax_get_customer_markup');
function woosync_ajax_get_customer_markup() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $customer_id = intval($_POST['customer_id']);
    $user_markups = get_option('woosync_user_markups', []);
    
    $markup = isset($user_markups[$customer_id]) ? $user_markups[$customer_id]['markup'] : 0;
    $tier = isset($user_markups[$customer_id]) ? $user_markups[$customer_id]['tier'] : '';
    
    wp_send_json_success(['markup' => $markup, 'tier' => $tier]);
}

// Save Customer Markup
add_action('wp_ajax_woosync_save_customer_markup', 'woosync_ajax_save_customer_markup');
function woosync_ajax_save_customer_markup() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $customer_id = intval($_POST['customer_id']);
    $markup = floatval($_POST['markup']);
    $tier = sanitize_text_field($_POST['tier']);
    
    $user_markups = get_option('woosync_user_markups', []);
    $user_markups[$customer_id] = [
        'markup' => $markup,
        'tier' => $tier,
    ];
    
    update_option('woosync_user_markups', $user_markups);
    wp_send_json_success(['message' => 'Customer markup saved']);
}

// Bulk Update Customers
add_action('wp_ajax_woosync_bulk_update_customers', 'woosync_ajax_bulk_update_customers');
function woosync_ajax_bulk_update_customers() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $customer_ids = $_POST['customer_ids'];
    $markup = floatval($_POST['markup']);
    
    if (is_string($customer_ids)) {
        $customer_ids = json_decode($customer_ids, true);
    }
    
    $user_markups = get_option('woosync_user_markups', []);
    $updated = 0;
    
    foreach ($customer_ids as $id) {
        $id = intval($id);
        if (!isset($user_markups[$id])) {
            $user_markups[$id] = [];
        }
        $user_markups[$id]['markup'] = $markup;
        $updated++;
    }
    
    update_option('woosync_user_markups', $user_markups);
    wp_send_json_success(['updated' => $updated]);
}

// Remove Customer Markup
add_action('wp_ajax_woosync_remove_customer_markup', 'woosync_ajax_remove_customer_markup');
function woosync_ajax_remove_customer_markup() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $customer_id = intval($_POST['customer_id']);
    $user_markups = get_option('woosync_user_markups', []);
    unset($user_markups[$customer_id]);
    
    update_option('woosync_user_markups', $user_markups);
    wp_send_json_success(['message' => 'Customer markup removed']);
}

// Import Customers from CSV
add_action('wp_ajax_woosync_import_customers', 'woosync_ajax_import_customers');
function woosync_ajax_import_customers() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    if (empty($_FILES['import_file'])) {
        wp_send_json_error('No file uploaded');
    }
    
    $file = $_FILES['import_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('Upload error');
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        wp_send_json_error('Could not read file');
    }
    
    $user_markups = get_option('woosync_user_markups', []);
    $imported = 0;
    
    // Skip header row
    fgetcsv($handle);
    
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < 2) continue;
        
        $email = sanitize_email($data[0]);
        $markup = floatval($data[1]);
        $tier = isset($data[2]) ? sanitize_text_field($data[2]) : '';
        
        $user = get_user_by('email', $email);
        if ($user) {
            $user_markups[$user->ID] = [
                'markup' => $markup,
                'tier' => $tier,
            ];
            $imported++;
        }
    }
    
    fclose($handle);
    update_option('woosync_user_markups', $user_markups);
    
    wp_send_json_success(['imported' => $imported]);
}

// Calculate Price (for preview)
add_action('wp_ajax_woosync_calculate_price', 'woosync_ajax_calculate_price');
function woosync_ajax_calculate_price() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $product_id = intval($_POST['product_id']);
    $customer_id = isset($_POST['customer_id']) && !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $role = sanitize_text_field($_POST['role'] ?? '');
    
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Product not found');
    }
    
    $supplier_price = floatval($product->get_regular_price());
    $base_price = woosync_get_product_base_price($product_id);
    
    // Determine markup
    $markup_percent = get_option('woosync_default_markup', 30);
    
    if ($customer_id > 0) {
        $user_markups = get_option('woosync_user_markups', []);
        if (isset($user_markups[$customer_id])) {
            $markup_percent = $user_markups[$customer_id]['markup'];
        }
    } elseif (!empty($role)) {
        $role_markups = get_option('woosync_role_markups', []);
        if (isset($role_markups[$role]) && isset($role_markups[$role]['enabled']) && $role_markups[$role]['enabled']) {
            $markup_percent = $role_markups[$role]['markup'];
        }
    }
    
    $markup = $markup_percent / 100;
    $display_price = $base_price * (1 + $markup);
    $profit = $display_price - $base_price;
    $margin_percent = $base_price > 0 ? round(($profit / $display_price) * 100, 1) : 0;
    
    wp_send_json_success([
        'supplier_price' => $supplier_price,
        'base_price' => $base_price,
        'display_price' => $display_price,
        'markup_percent' => $markup_percent,
        'profit' => $profit,
        'margin_percent' => $margin_percent . '%',
    ]);
}

// Toggle Tiered Pricing
add_action('wp_ajax_woosync_toggle_tiered_pricing', 'woosync_ajax_toggle_tiered_pricing');
function woosync_ajax_toggle_tiered_pricing() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $enabled = intval($_POST['enabled']);
    update_option('woosync_tiered_pricing_enabled', $enabled);
    
    wp_send_json_success(['message' => 'Tiered pricing ' . ($enabled ? 'enabled' : 'disabled')]);
}

// Save Pricing Rules
add_action('wp_ajax_woosync_save_pricing_rules', 'woosync_ajax_save_pricing_rules');
function woosync_ajax_save_pricing_rules() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $rules = $_POST['rules'];
    
    update_option('woosync_minimum_margin', floatval($rules['minimum_margin']));
    update_option('woosync_maximum_discount', floatval($rules['maximum_discount']));
    update_option('woosync_clearance_minimum', floatval($rules['clearance_minimum']));
    
    wp_send_json_success(['message' => 'Pricing rules saved']);
}

// Save Default Markup
add_action('wp_ajax_woosync_save_default_markup', 'woosync_ajax_save_default_markup');
function woosync_ajax_save_default_markup() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $markup = floatval($_POST['markup']);
    update_option('woosync_default_markup', $markup);
    
    wp_send_json_success(['message' => 'Default markup saved']);
}

// Search Products for Preview
add_action('wp_ajax_woosync_search_products_for_preview', 'woosync_ajax_search_products_for_preview');
function woosync_ajax_search_products_for_preview() {
    check_ajax_referer('amrod_sync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $query = sanitize_text_field($_POST['query']);
    
    $products = wc_get_products([
        'search' => $query,
        'limit' => 20,
        'status' => 'publish',
    ]);
    
    $results = [];
    foreach ($products as $product) {
        $results[] = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
        ];
    }
    
    wp_send_json_success(['products' => $results]);
}

// =============================================================================
// PRODUCT META BOX - Base Price Override
// =============================================================================

add_action('add_meta_boxes', 'woosync_add_pricing_meta_box');
function woosync_add_pricing_meta_box() {
    add_meta_box(
        'woosync_pricing_meta_box',
        'WooSync Pricing',
        'woosync_render_pricing_meta_box',
        'product',
        'side',
        'default'
    );
}

function woosync_render_pricing_meta_box($post) {
    wp_nonce_field('woosync_pricing_meta_box', 'woosync_pricing_nonce');
    
    $base_price = get_post_meta($post->ID, '_woosync_base_price', true);
    ?>
    <label class="form-label">Base Price Override</label>
    <div class="input-group mb-2">
        <span class="input-group-text">R</span>
        <input type="number" name="woosync_base_price" class="form-control" value="<?php echo esc_attr($base_price); ?>" step="0.01" min="0">
    </div>
    <small class="text-muted">Leave empty to use supplier price</small>
    <?php
}

add_action('save_post', 'woosync_save_pricing_meta_box', 10, 2);
function woosync_save_pricing_meta_box($post_id, $post) {
    if (!isset($_POST['woosync_pricing_nonce']) || !wp_verify_nonce($_POST['woosync_pricing_nonce'], 'woosync_pricing_meta_box')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['woosync_base_price'])) {
        $base_price = $_POST['woosync_base_price'];
        if ($base_price === '' || $base_price === null) {
            delete_post_meta($post_id, '_woosync_base_price');
        } else {
            update_post_meta($post_id, '_woosync_base_price', floatval($base_price));
        }
    }
}
