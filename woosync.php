<?php
/**
 * Plugin Name: WooSync - Multi-Vendor WooCommerce Sync
 * Description: Enterprise-grade multi-vendor supplier sync for WooCommerce with smart auto-mapping, field mapping, progress tracking, and automated scheduling. Sync products from Amrod, Barron, Giftwrap, and custom suppliers.
 * Version: 3.0.0
 * Author: Mediaplatform
 * Author URI: https://mediaplatform.co.za
 * Plugin URI: https://mediaplatform.co.za/woosync
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woosync
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) exit;

// ===== CONSTANTS & CONFIGURATION =====
define('WOOSYNC_VERSION', '3.0.0');
define('WOOSYNC_PATH', plugin_dir_path(__FILE__));
define('WOOSYNC_URL', plugin_dir_url(__FILE__));
define('WOOSYNC_ASSETS', WOOSYNC_URL . 'assets/');
// ===== GITHUB AUTO-UPDATER =====
require_once WOOSYNC_PATH . 'includes/class-woosync-updater.php';


// ===== BACKWARDS COMPATIBILITY ALIASES =====
if (!defined('AMROD_SYNC_VERSION')) {
    define('AMROD_SYNC_VERSION', WOOSYNC_VERSION);
}
if (!defined('AMROD_SYNC_PATH')) {
    define('AMROD_SYNC_PATH', WOOSYNC_PATH);
}
if (!defined('AMROD_SYNC_URL')) {
    define('AMROD_SYNC_URL', WOOSYNC_URL);
}
if (!defined('AMROD_SYNC_ASSETS')) {
    define('AMROD_SYNC_ASSETS', WOOSYNC_ASSETS);
}


// ===== NOTIFICATIONS CLASS =====
require_once WOOSYNC_PATH . 'includes/class-woosync-notifications.php';

// ===== WOOCOMMERCE DEPENDENCY CHECK =====
add_action('plugins_loaded', 'woosync_check_woocommerce');
function woosync_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>❌ WooSync:</strong> WooCommerce must be active. Plugin deactivated.</p></div>';
        });
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

// ===== HOOKS FOR BACKWARDS COMPATIBILITY =====
register_activation_hook(__FILE__, function() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('WooSync requires WooCommerce to be active.');
    }
    set_transient('woosync_activated', 1, HOUR_IN_SECONDS);
    
    // Migrate legacy Amrod options if present
    woosync_migrate_legacy_options();
    
    // Schedule daily update check
    WooSync_Updater::schedule_update_check();
});

function woosync_migrate_legacy_options() {
    // Check if legacy Amrod options exist
    $legacy_username = get_option('amrod_username');
    $legacy_password = get_option('amrod_password');
    
    if ($legacy_username && !get_option('woosync_vendors')) {
        // Migrate Amrod as default vendor
        $vendors = get_option('woosync_vendors', []);
        
        // Only migrate if no vendors exist yet
        if (empty($vendors)) {
            $amrod_vendor = [
                'id' => 'amrod_default',
                'name' => 'Amrod',
                'slug' => 'amrod',
                'logo' => '',
                'enabled' => true,
                'auth_url' => get_option('amrod_auth_url', 'https://identity.amrod.co.za'),
                'api_url' => get_option('amrod_api_url', 'https://vendorapi.amrod.co.za'),
                'docs_url' => get_option('amrod_docs_url', 'https://newapidocs.amrod.co.za'),
                'username' => $legacy_username,
                'password' => $legacy_password,
                'customer_code' => get_option('amrod_customer_code'),
                'auth_type' => 'vendor_login',
                'endpoints' => get_option('amrod_endpoints', ''),
                'field_mapping' => get_option('amrod_field_mapping', ''),
                'sync_mode' => 'full',
                'last_sync' => get_option('amrod_last_sync', ''),
                'total_products' => get_option('amrod_total_products', 0),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            // Store as serialized array for vendor management
            update_option('woosync_vendors', [$amrod_vendor]);
            update_option('woosync_active_vendor', 'amrod_default');
        }
    }
}

// ===== ENQUEUE ASSETS =====
add_action('admin_enqueue_scripts', 'woosync_enqueue_assets');
function woosync_enqueue_assets($hook) {
    if (strpos($hook, 'woosync') === false) return;

    // Bootstrap 5 CSS
    wp_enqueue_style('bootstrap5-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css', [], '5.0.2');
    
    // Bootstrap 5 JS
    wp_enqueue_script('bootstrap5-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js', [], '5.0.2', true);

    // Chart.js
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);

    // Custom Admin CSS
    wp_enqueue_style('woosync-admin-css', WOOSYNC_ASSETS . 'css/admin.css', ['bootstrap5-css'], WOOSYNC_VERSION);

    // Custom JS
    wp_enqueue_script('woosync-sync-js', WOOSYNC_ASSETS . 'js/sync-progress.js', ['jquery', 'chart-js'], WOOSYNC_VERSION, true);
    wp_enqueue_script('woosync-mapping-js', WOOSYNC_ASSETS . 'js/field-mapping.js', ['jquery'], WOOSYNC_VERSION, true);
    wp_enqueue_script('woosync-wizard-js', WOOSYNC_ASSETS . 'js/wizard.js', ['jquery'], WOOSYNC_VERSION, true);
    wp_enqueue_script('woosync-promotions-js', WOOSYNC_ASSETS . 'js/promotions.js', ['jquery', 'jquery-ui-datepicker'], WOOSYNC_VERSION, true);
    wp_enqueue_script('woosync-optimizer-js', WOOSYNC_ASSETS . 'js/optimizer.js', ['jquery'], WOOSYNC_VERSION, true);

    // Localize data for JS
    wp_localize_script('woosync-promotions-js', 'woosyncPromotions', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('woosync_notifications'),
        'version' => WOOSYNC_VERSION
    ]);
    
    wp_localize_script('woosync-sync-js', 'woosyncData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('woosync_nonce'),
        'version' => WOOSYNC_VERSION
    ]);
}

// ===== REGISTER SETTINGS =====
add_action('admin_init', 'woosync_register_settings');
function woosync_register_settings() {
    register_setting('woosync_group', 'woosync_vendors');
    register_setting('woosync_group', 'woosync_active_vendor');
    register_setting('woosync_group', 'woosync_batch_size');
    register_setting('woosync_group', 'woosync_sync_schedule');
    register_setting('woosync_group', 'woosync_log_retain_days');
    register_setting('woosync_group', 'woosync_wizard_completed');
    register_setting('woosync_group', 'woosync_sync_log');
    register_setting('woosync_group', 'woosync_last_sync');
    register_setting('woosync_group', 'woosync_total_products');
    register_setting('woosync_group', 'woosync_notifications');
    register_setting('woosync_group', 'woosync_dismissed');
    register_setting('woosync_group', 'woosync_services');
    register_setting('woosync_group', 'woosync_show_services');
}

// ===== VENDOR LIBRARY - PRE-BUILT TEMPLATES =====
function woosync_get_vendor_templates() {
    return [
        'amrod' => [
            'name' => 'Amrod',
            'slug' => 'amrod',
            'description' => 'Amrod promotional merchandise and branded items',
            'logo' => '',
            'auth_type' => 'vendor_login',
            'auth_url' => 'https://identity.amrod.co.za',
            'api_url' => 'https://vendorapi.amrod.co.za',
            'docs_url' => 'https://newapidocs.amrod.co.za',
            'auth_payload_fields' => ['username', 'password', 'CustomerCode'],
            'endpoints' => [
                'products' => ['label' => 'Products', 'path' => '/api/v1/Products/', 'enabled' => 1],
                'products_updated' => ['label' => 'Products (Updated)', 'path' => '/api/v1/Products/GetUpdatedProducts', 'enabled' => 0],
                'products_branding' => ['label' => 'Products with Branding', 'path' => '/api/v1/Products/GetProductsAndBranding', 'enabled' => 0],
                'products_updated_branding' => ['label' => 'Products Updated with Branding', 'path' => '/api/v1/Products/GetUpdatedProductsAndBranding', 'enabled' => 0],
                'stock' => ['label' => 'Stock', 'path' => '/api/v1/Stock/', 'enabled' => 0],
                'stock_updated' => ['label' => 'Stock (Updated)', 'path' => '/api/v1/Stock/GetUpdated', 'enabled' => 0],
                'prices' => ['label' => 'Prices', 'path' => '/api/v1/Prices/', 'enabled' => 0],
                'prices_updated' => ['label' => 'Prices (Updated)', 'path' => '/api/v1/Prices/GetUpdated', 'enabled' => 0],
                'categories' => ['label' => 'Categories', 'path' => '/api/v1/Categories/', 'enabled' => 0],
                'brands' => ['label' => 'Brands', 'path' => '/api/v1/Brands/', 'enabled' => 0],
                'colour_swatches' => ['label' => 'Colour Swatches', 'path' => '/api/v1/ColourSwatches/', 'enabled' => 0],
            ],
            'field_mapping_template' => [
                'sku' => 'ProductCode',
                'name' => 'Description',
                'price' => 'Price',
                'description' => 'LongDescription',
                'colour' => 'Colour',
                'brand' => 'Brand',
                'weight' => 'Weight',
                'dimensions' => 'Dimensions',
                'stock' => 'Stock',
                'images' => 'Images',
                'categories' => 'Category',
                'sale_price' => 'SalePrice',
                'min_quantity' => 'MinQuantity',
                'price_breaks' => 'PriceBreaks'
            ],
            'detected_fields' => ['ProductCode', 'Description', 'Price', 'LongDescription', 'Colour', 'Brand', 'Weight', 'Dimensions', 'Stock', 'Images', 'Category', 'SalePrice', 'MinQuantity', 'PriceBreaks']
        ],
        'barron' => [
            'name' => 'Barron',
            'slug' => 'barron',
            'description' => 'Barron custom apparel and corporate wear',
            'logo' => '',
            'auth_type' => 'api_key',
            'auth_url' => 'https://api.barron.co.za',
            'api_url' => 'https://api.barron.co.za',
            'docs_url' => 'https://developer.barron.co.za',
            'auth_payload_fields' => ['api_key'],
            'endpoints' => [
                'products' => ['label' => 'Products', 'path' => '/v1/products', 'enabled' => 1],
                'products_updated' => ['label' => 'Products (Updated)', 'path' => '/v1/products/changes', 'enabled' => 0],
                'inventory' => ['label' => 'Inventory', 'path' => '/v1/inventory', 'enabled' => 0],
                'categories' => ['label' => 'Categories', 'path' => '/v1/categories', 'enabled' => 0],
            ],
            'field_mapping_template' => [
                'sku' => 'item_code',
                'name' => 'product_name',
                'price' => 'unit_price',
                'description' => 'product_description',
                'colour' => 'color',
                'size' => 'size',
                'brand' => 'brand_name',
                'weight' => 'weight_kg',
                'stock' => 'quantity_available',
                'images' => 'image_urls',
                'categories' => 'product_category'
            ],
            'detected_fields' => ['item_code', 'product_name', 'unit_price', 'product_description', 'color', 'size', 'brand_name', 'weight_kg', 'quantity_available', 'image_urls', 'product_category']
        ],
        'giftwrap' => [
            'name' => 'Giftwrap',
            'slug' => 'giftwrap',
            'description' => 'Giftwrap corporate gifting and promotional packs',
            'logo' => '',
            'auth_type' => 'bearer_token',
            'auth_url' => 'https://api.giftwrap.co.za',
            'api_url' => 'https://api.giftwrap.co.za',
            'docs_url' => 'https://docs.giftwrap.co.za',
            'auth_payload_fields' => ['client_id', 'client_secret'],
            'endpoints' => [
                'products' => ['label' => 'Products', 'path' => '/api/products', 'enabled' => 1],
                'products_updated' => ['label' => 'Products (Updated)', 'path' => '/api/products/since', 'enabled' => 0],
                'pricing' => ['label' => 'Pricing', 'path' => '/api/pricing', 'enabled' => 0],
            ],
            'field_mapping_template' => [
                'sku' => 'SKU',
                'name' => 'Title',
                'price' => 'CostPrice',
                'description' => 'Details',
                'weight' => 'Weight',
                'stock' => 'QtyInStock',
                'images' => 'ImageGallery',
                'categories' => 'Collection'
            ],
            'detected_fields' => ['SKU', 'Title', 'CostPrice', 'Details', 'Weight', 'QtyInStock', 'ImageGallery', 'Collection']
        ]
    ];
}

// ===== WOOCOMMERCE FIELD DEFINITIONS =====
function woosync_get_wc_fields() {
    return [
        'sku' => ['label' => 'SKU', 'type' => 'text', 'category' => 'basic'],
        'name' => ['label' => 'Product Name', 'type' => 'text', 'category' => 'basic'],
        'price' => ['label' => 'Regular Price', 'type' => 'price', 'category' => 'pricing'],
        'sale_price' => ['label' => 'Sale Price', 'type' => 'price', 'category' => 'pricing'],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'category' => 'basic'],
        'short_description' => ['label' => 'Short Description', 'type' => 'textarea', 'category' => 'basic'],
        'stock' => ['label' => 'Stock Quantity', 'type' => 'number', 'category' => 'inventory'],
        'stock_status' => ['label' => 'Stock Status', 'type' => 'select', 'category' => 'inventory'],
        'weight' => ['label' => 'Weight', 'type' => 'text', 'category' => 'shipping'],
        'dimensions' => ['label' => 'Dimensions (L×W×H)', 'type' => 'text', 'category' => 'shipping'],
        'images' => ['label' => 'Images', 'type' => 'images', 'category' => 'media'],
        'categories' => ['label' => 'Categories', 'type' => 'categories', 'category' => 'organization'],
        'tags' => ['label' => 'Tags', 'type' => 'text', 'category' => 'organization'],
        'brand' => ['label' => 'Brand', 'type' => 'text', 'category' => 'attributes'],
        'colour' => ['label' => 'Colour Attribute', 'type' => 'attribute', 'category' => 'attributes'],
        'size' => ['label' => 'Size Attribute', 'type' => 'attribute', 'category' => 'attributes'],
        'min_quantity' => ['label' => 'Minimum Quantity', 'type' => 'number', 'category' => 'pricing'],
        'price_breaks' => ['label' => 'Price Breaks', 'type' => 'json', 'category' => 'pricing'],
        'variants' => ['label' => 'Variants', 'type' => 'variants', 'category' => 'product_type'],
        'status' => ['label' => 'Product Status', 'type' => 'select', 'category' => 'basic'],
        'catalog_visibility' => ['label' => 'Catalog Visibility', 'type' => 'select', 'category' => 'basic'],
        'tax_status' => ['label' => 'Tax Status', 'type' => 'select', 'category' => 'pricing'],
        'tax_class' => ['label' => 'Tax Class', 'type' => 'select', 'category' => 'pricing'],
        'manage_stock' => ['label' => 'Manage Stock', 'type' => 'checkbox', 'category' => 'inventory'],
        'featured' => ['label' => 'Featured', 'type' => 'checkbox', 'category' => 'basic'],
        'meta' => ['label' => 'Custom Meta Field', 'type' => 'meta', 'category' => 'advanced']
    ];
}

// ===== SMART AUTO-MAPPING SYSTEM =====
function woosync_detect_api_fields($sample_data) {
    if (empty($sample_data) || !is_array($sample_data)) {
        return [];
    }
    
    // Flatten nested structures for field detection
    $flat_fields = [];
    woosync_flatten_array($sample_data, '', $flat_fields);
    
    return array_keys($flat_fields);
}

function woosync_flatten_array($array, $prefix, &$result) {
    foreach ($array as $key => $value) {
        $new_key = $prefix ? "{$prefix}.{$key}" : $key;
        
        if (is_array($value) && !empty($value)) {
            // Check if it's a simple array of primitives (like images URLs)
            if (woosync_is_primitive_array($value)) {
                $result[$new_key] = $value;
            } else {
                woosync_flatten_array($value, $new_key, $result);
            }
        } else {
            $result[$new_key] = $value;
        }
    }
}

function woosync_is_primitive_array($array) {
    if (!is_array($array) || empty($array)) return false;
    foreach ($array as $item) {
        if (is_array($item)) return false;
    }
    return true;
}

function woosync_fuzzy_match_fields($api_fields, $wc_fields) {
    $mappings = [];
    
    // Normalize and create lookup arrays
    $api_normalized = [];
    foreach ($api_fields as $field) {
        $api_normalized[strtolower($field)] = $field;
    }
    
    $wc_normalized = [];
    foreach ($wc_fields as $key => $config) {
        $wc_normalized[strtolower($config['label'])] = $key;
    }
    
    // Direct matches (high confidence)
    foreach ($api_normalized as $api_lower => $api_original) {
        foreach ($wc_normalized as $wc_lower => $wc_key) {
            if ($api_lower === $wc_lower) {
                $mappings[$wc_key] = [
                    'api_field' => $api_original,
                    'confidence' => 'high',
                    'match_type' => 'exact'
                ];
            }
        }
    }
    
    // Fuzzy matches (medium confidence)
    $fuzzy_patterns = [
        // SKU variations
        ['patterns' => ['productcode', 'itemcode', 'item_code', 'product_code', 'code', 'itemno', 'item_no', 'productid', 'product_id', 'sku', 'articleno', 'article_no'], 'wc_key' => 'sku'],
        // Name variations
        ['patterns' => ['description', 'name', 'productname', 'product_name', 'title', 'producttitle', 'product_title', 'itemname', 'item_name', 'product'], 'wc_key' => 'name'],
        // Price variations
        ['patterns' => ['price', 'unitprice', 'unit_price', 'costprice', 'cost_price', 'baseprice', 'base_price', 'sellingprice', 'selling_price', 'amount'], 'wc_key' => 'price'],
        // Sale price variations
        ['patterns' => ['saleprice', 'sale_price', 'specialprice', 'special_price', 'discountprice', 'discount_price', 'offerprice', 'offer_price'], 'wc_key' => 'sale_price'],
        // Description variations
        ['patterns' => ['longdescription', 'long_description', 'description', 'productdescription', 'product_description', 'details', 'productdetails', 'product_details', 'info'], 'wc_key' => 'description'],
        // Short description variations
        ['patterns' => ['shortdescription', 'short_description', 'summary', 'excerpt', 'brief'], 'wc_key' => 'short_description'],
        // Colour variations
        ['patterns' => ['colour', 'color', 'colourname', 'colour_name', 'colorname', 'color_name'], 'wc_key' => 'colour'],
        // Size variations
        ['patterns' => ['size', 'sizename', 'size_name'], 'wc_key' => 'size'],
        // Brand variations
        ['patterns' => ['brand', 'brandname', 'brand_name', 'make', 'manufacturer'], 'wc_key' => 'brand'],
        // Weight variations
        ['patterns' => ['weight', 'weightkg', 'weight_kg', 'weightlbs', 'weight_lbs', 'mass'], 'wc_key' => 'weight'],
        // Dimensions variations
        ['patterns' => ['dimensions', 'size', 'measurements', 'length', 'width', 'height', 'depth'], 'wc_key' => 'dimensions'],
        // Stock variations
        ['patterns' => ['stock', 'quantity', 'qty', 'available', 'instock', 'in_stock', 'stocklevel', 'stock_level', 'qtyavailable', 'qty_available'], 'wc_key' => 'stock'],
        // Images variations
        ['patterns' => ['images', 'image', 'imageurl', 'image_url', 'imageurls', 'image_urls', 'photos', 'picture', 'pictures', 'gallery'], 'wc_key' => 'images'],
        // Categories variations
        ['patterns' => ['category', 'categories', 'productcategory', 'product_category', 'collection', 'type', 'clas'], 'wc_key' => 'categories'],
        // Min quantity variations
        ['patterns' => ['minquantity', 'min_quantity', 'minimum', 'minorder', 'min_order', 'moq'], 'wc_key' => 'min_quantity'],
        // Price breaks variations
        ['patterns' => ['pricebreaks', 'price_breaks', 'volumepricing', 'volume_pricing', 'tierprice', 'tier_price', 'pricingtiers'], 'wc_key' => 'price_breaks'],
        // Tags variations
        ['patterns' => ['tags', 'keywords', 'labels'], 'wc_key' => 'tags'],
    ];
    
    foreach ($fuzzy_patterns as $pattern_group) {
        foreach ($api_normalized as $api_lower => $api_original) {
            foreach ($pattern_group['patterns'] as $pattern) {
                if (strpos($api_lower, $pattern) !== false || $api_lower === $pattern) {
                    $wc_key = $pattern_group['wc_key'];
                    // Skip if already mapped with higher confidence
                    if (!isset($mappings[$wc_key]) || $mappings[$wc_key]['confidence'] !== 'high') {
                        $mappings[$wc_key] = [
                            'api_field' => $api_original,
                            'confidence' => 'medium',
                            'match_type' => 'fuzzy'
                        ];
                    }
                }
            }
        }
    }
    
    return $mappings;
}

function woosync_get_confidence_label($confidence) {
    $labels = [
        'high' => ['label' => 'High', 'class' => 'success', 'icon' => '✅'],
        'medium' => ['label' => 'Medium', 'class' => 'warning', 'icon' => '⚠️'],
        'low' => ['label' => 'Low', 'class' => 'danger', 'icon' => '❌']
    ];
    return $labels[$confidence] ?? $labels['low'];
}

// ===== VENDOR MANAGEMENT =====
function woosync_get_vendors() {
    $vendors = get_option('woosync_vendors', []);
    return is_array($vendors) ? $vendors : [];
}

function woosync_get_active_vendor() {
    $active_id = get_option('woosync_active_vendor', '');
    $vendors = woosync_get_vendors();
    
    foreach ($vendors as $vendor) {
        if ($vendor['id'] === $active_id) {
            return $vendor;
        }
    }
    
    // Return first vendor if active not found
    return !empty($vendors) ? $vendors[0] : null;
}

function woosync_get_vendor_by_id($vendor_id) {
    $vendors = woosync_get_vendors();
    
    foreach ($vendors as $vendor) {
        if ($vendor['id'] === $vendor_id) {
            return $vendor;
        }
    }
    
    return null;
}

function woosync_save_vendor($vendor_data) {
    $vendors = woosync_get_vendors();
    $is_new = !isset($vendor_data['id']) || empty($vendor_data['id']);
    
    if ($is_new) {
        // Generate unique ID
        $vendor_data['id'] = sanitize_key($vendor_data['slug'] . '_' . time());
        $vendor_data['created_at'] = current_time('mysql');
    }
    
    $vendor_data['updated_at'] = current_time('mysql');
    
    if ($is_new) {
        $vendors[] = $vendor_data;
    } else {
        foreach ($vendors as $key => $v) {
            if ($v['id'] === $vendor_data['id']) {
                $vendors[$key] = $vendor_data;
                break;
            }
        }
    }
    
    update_option('woosync_vendors', $vendors);
    
    // Set as active if first vendor
    if (count($vendors) === 1) {
        update_option('woosync_active_vendor', $vendor_data['id']);
    }
    
    return $vendor_data;
}

function woosync_delete_vendor($vendor_id) {
    $vendors = woosync_get_vendors();
    
    foreach ($vendors as $key => $vendor) {
        if ($vendor['id'] === $vendor_id) {
            unset($vendors[$key]);
            break;
        }
    }
    
    update_option('woosync_vendors', array_values($vendors));
    
    // Update active vendor if needed
    $active_id = get_option('woosync_active_vendor', '');
    if ($active_id === $vendor_id) {
        $remaining = woosync_get_vendors();
        update_option('woosync_active_vendor', !empty($remaining) ? $remaining[0]['id'] : '');
    }
}

function woosync_get_vendor_endpoints($vendor) {
    if (!empty($vendor['endpoints']) && is_string($vendor['endpoints'])) {
        $decoded = @json_decode($vendor['endpoints'], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    
    // Return template endpoints if available
    $templates = woosync_get_vendor_templates();
    $slug = $vendor['slug'] ?? '';
    
    if (isset($templates[$slug])) {
        return $templates[$slug]['endpoints'];
    }
    
    return [];
}

function woosync_get_vendor_field_mapping($vendor) {
    if (!empty($vendor['field_mapping']) && is_string($vendor['field_mapping'])) {
        $decoded = @json_decode($vendor['field_mapping'], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    
    // Return template mapping if available
    $templates = woosync_get_vendor_templates();
    $slug = $vendor['slug'] ?? '';
    
    if (isset($templates[$slug])) {
        return $templates[$slug]['field_mapping_template'];
    }
    
    return [];
}

// ===== ADMIN MENU =====
add_action('admin_menu', 'woosync_register_menus');
function woosync_register_menus() {
    if (!class_exists('WooCommerce')) return;

    $active_vendor = woosync_get_active_vendor();
    $sync_status = !empty(get_option('woosync_last_sync')) ? '✅' : '❌';
    
    add_menu_page(
        'WooSync',
        'WooSync ' . $sync_status,
        'manage_options',
        'woosync',
        'woosync_render_main_page',
        'dashicons-update',
        56
    );

    add_submenu_page(
        'woosync',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'woosync',
        'woosync_render_main_page'
    );
    
    add_submenu_page(
        'woosync',
        'Vendors',
        'Vendors',
        'manage_options',
        'woosync-vendors',
        'woosync_render_vendors_page'
    );
    
    add_submenu_page(
        'woosync',
        'Sync Log',
        'Sync Log',
        'manage_options',
        'woosync-log',
        'woosync_render_log_page'
    );
    
    add_submenu_page(
        'woosync',
        'Promotions',
        'Promotions',
        'manage_options',
        'woosync-promotions',
        'woosync_render_promotions_page'
    );
}

// ===== ACTIVATION NOTICE =====
add_action('admin_notices', function() {
    if (get_transient('woosync_activated')) {
        delete_transient('woosync_activated');
        echo '<div class="alert alert-success alert-dismissible fade show"><strong>✅ WooSync activated!</strong> Connect your first vendor to begin syncing products.</div>';
    }
});

// ===== MAIN PAGE RENDER =====
function woosync_render_main_page() {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions');
    
    $active_tab = $_GET['tab'] ?? 'dashboard';
    $active_vendor = woosync_get_active_vendor();
    $vendors = woosync_get_vendors();
    
    // Check if first-time setup
    $wizard_completed = get_option('woosync_wizard_completed', false);
    $show_wizard = empty($vendors) || !$wizard_completed;
    
    if ($show_wizard && !isset($_GET['skip_wizard'])) {
        woosync_render_onboarding_wizard();
        return;
    }
    
    ?>
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="woosync-brand-header">
            <div>
                <h1 class="mb-0">WooSync</h1>
                <span class="version">v<?php echo WOOSYNC_VERSION; ?></span>
                <span class="powered-by">by <a href="https://mediaplatform.co.za" target="_blank" rel="noopener">Mediaplatform</a></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if ($active_vendor): ?>
                <div class="badge bg-primary">
                    Active: <?php echo esc_html($active_vendor['name']); ?>
                </div>
                <?php endif; ?>
                <img src="<?php echo WOOSYNC_ASSETS; ?>images/woosync-logo.svg" height="40" alt="WooSync">
            </div>
        </div>

        <!-- Notification Banners -->
        <?php
        $notifications_obj = WooSync_Notifications::get_instance();
        $active_notifications = $notifications_obj->get_active_notifications();
        foreach ($active_notifications as $id => $notification):
            if ($notification['type'] === 'banner'):
        ?>
        <div class="woosync-notification-banner" data-notification-id="<?php echo esc_attr($id); ?>">
            <div class="woosync-notification-content">
                <span class="dashicons <?php echo esc_attr($notification['icon'] ?? 'dashicons-info'); ?>"></span>
                <div class="woosync-notification-text">
                    <strong><?php echo esc_html($notification['title'] ?? ''); ?></strong>
                    <p><?php echo esc_html($notification['message'] ?? ''); ?></p>
                </div>
                <?php if (!empty($notification['link'])): ?>
                <a href="<?php echo esc_url($notification['link']); ?>" class="woosync-notification-cta" target="_blank" rel="noopener">
                    <?php echo esc_html($notification['cta_text'] ?? 'Learn More'); ?> →
                </a>
                <?php endif; ?>
                <button type="button" class="woosync-notification-dismiss" aria-label="Dismiss">
                    <span class="dashicons dashicons-dismiss"></span>
                </button>
            </div>
        </div>
        <?php 
            endif;
        endforeach;
        ?>

        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Vendors</h5>
                        <h2><?php echo count($vendors); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <h2><?php echo number_format(get_option('woosync_total_products', 0)); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Last Sync</h5>
                        <h6><?php 
                            $last_sync = get_option('woosync_last_sync');
                            echo $last_sync ? date('d M @ H:i', strtotime($last_sync)) : 'Never';
                        ?></h6>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Sync Status</h5>
                        <h6 id="syncStatusDisplay">Ready</h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>" href="?page=woosync&tab=dashboard">
                    📊 Dashboard
                </a>
            </li>
            <?php if ($active_vendor): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'sync' ? 'active' : ''; ?>" href="?page=woosync&tab=sync">
                    🔄 Sync
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'mapping' ? 'active' : ''; ?>" href="?page=woosync&tab=mapping">
                    🗺️ Field Mapping
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'optimizer' ? 'active' : ''; ?>" href="?page=woosync&tab=optimizer">
                    🚀 Product Optimizer
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" href="?page=woosync&tab=settings">
                    ⚙️ Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'updates' ? 'active' : ''; ?>" href="?page=woosync&tab=updates">
                    🔔 Updates
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <?php
            if ($active_tab === 'dashboard') woosync_tab_dashboard();
            elseif ($active_tab === 'sync' && $active_vendor) woosync_tab_sync();
            elseif ($active_tab === 'mapping' && $active_vendor) woosync_tab_field_mapping();
            elseif ($active_tab === 'settings' && $active_vendor) woosync_tab_settings();
            elseif ($active_tab === 'updates') woosync_tab_updates();
            elseif ($active_tab === 'optimizer' && $active_vendor) woosync_tab_optimizer();
            else woosync_tab_dashboard();
            ?>
        </div>
    </div>

    <!-- Footer -->
    <!-- Services from Mediaplatform -->
        <?php if (get_option('woosync_show_services', true)): ?>
        <div class="woosync-services-section">
            <h3><span class="dashicons dashicons-admin-plugins"></span> Services from Mediaplatform</h3>
            <div class="woosync-services-grid">
                <?php $notifications_obj->render_services(true); ?>
            </div>
            <p class="text-muted small mt-3">
                <a href="?page=woosync-promotions">Manage Promotions</a> | 
                <a href="https://mediaplatform.co.za" target="_blank">mediaplatform.co.za</a>
            </p>
        </div>
        <?php endif; ?>

        <div class="text-center text-muted small mt-5 pb-5">
            <p>© 2026 <a href="https://mediaplatform.co.za" target="_blank">Mediaplatform</a> | WooSync v<?php echo WOOSYNC_VERSION; ?></p>
        </div>
    <?php
}

// ===== ONBOARDING WIZARD =====
function woosync_render_onboarding_wizard() {
    $templates = woosync_get_vendor_templates();
    ?>
    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🚀 WooSync Setup Wizard</h5>
                    </div>
                    <div class="card-body">
                        <p class="lead">Welcome to WooSync! Let's connect your first supplier.</p>
                        
                        <!-- Step Indicators -->
                        <div class="d-flex justify-content-between mb-4">
                            <div class="text-center step-indicator active" data-step="1">
                                <div class="step-circle">1</div>
                                <div class="step-label">Select Vendor</div>
                            </div>
                            <div class="text-center step-indicator" data-step="2">
                                <div class="step-circle">2</div>
                                <div class="step-label">Connect</div>
                            </div>
                            <div class="text-center step-indicator" data-step="3">
                                <div class="step-circle">3</div>
                                <div class="step-label">Auto-Map Fields</div>
                            </div>
                            <div class="text-center step-indicator" data-step="4">
                                <div class="step-circle">4</div>
                                <div class="step-label">Preview</div>
                            </div>
                            <div class="text-center step-indicator" data-step="5">
                                <div class="step-circle">5</div>
                                <div class="step-label">Sync</div>
                            </div>
                        </div>

                        <form method="post" id="wizardForm">
                            <?php wp_nonce_field('woosync_wizard'); ?>
                            
                            <!-- Step 1: Select Vendor -->
                            <div class="wizard-step" data-step="1">
                                <h5>Select Your Supplier</h5>
                                <p class="text-muted">Choose a pre-configured supplier or add a custom one.</p>
                                
                                <div class="row">
                                    <?php foreach ($templates as $slug => $template): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card vendor-template-card" data-vendor="<?php echo esc_attr($slug); ?>">
                                            <div class="card-body text-center">
                                                <h5><?php echo esc_html($template['name']); ?></h5>
                                                <p class="text-muted small"><?php echo esc_html($template['description']); ?></p>
                                                <button type="button" class="btn btn-outline-primary select-vendor-btn" data-vendor="<?php echo esc_attr($slug); ?>">
                                                    Select
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-secondary" id="addCustomVendorBtn">
                                        ➕ Add Custom Vendor
                                    </button>
                                </div>
                                
                                <input type="hidden" name="vendor_template" id="vendorTemplate" value="">
                                <input type="hidden" name="vendor_name" id="vendorName" value="">
                                <input type="hidden" name="vendor_slug" id="vendorSlug" value="">
                            </div>

                            <!-- Step 2: Connect -->
                            <div class="wizard-step" data-step="2" style="display: none;">
                                <h5>Connect to <span id="selectedVendorName">Vendor</span></h5>
                                <p class="text-muted">Enter your API credentials.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">API Base URL</label>
                                    <input type="url" name="api_url" id="apiUrl" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Auth URL</label>
                                    <input type="url" name="auth_url" id="authUrl" class="form-control" required>
                                </div>
                                
                                <div id="credentialsFields">
                                    <!-- Dynamic fields based on vendor type -->
                                </div>
                                
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-secondary" id="testConnectionBtn">
                                        🔗 Test Connection
                                    </button>
                                    <span id="connectionStatus"></span>
                                </div>
                            </div>

                            <!-- Step 3: Auto-Map Fields -->
                            <div class="wizard-step" data-step="3" style="display: none;">
                                <h5>Auto-Detect Field Mapping</h5>
                                <p class="text-muted">We've detected the fields from your API. Review and adjust the mappings.</p>
                                
                                <div id="autoMappingResults" class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>WooCommerce Field</th>
                                                <th>API Field</th>
                                                <th>Confidence</th>
                                                <th>Sample Value</th>
                                            </tr>
                                        </thead>
                                        <tbody id="mappingResultsBody">
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">
                                                    Click "Auto-Detect" to analyze your API data
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <button type="button" class="btn btn-primary" id="autoDetectBtn">
                                    🔍 Auto-Detect Fields
                                </button>
                            </div>

                            <!-- Step 4: Preview -->
                            <div class="wizard-step" data-step="4" style="display: none;">
                                <h5>Preview Sample Products</h5>
                                <p class="text-muted">Review how your products will appear in WooCommerce.</p>
                                
                                <div id="previewArea" class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>SKU</th>
                                                <th>Name</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody id="previewBody">
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">
                                                    Preview will appear here
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Step 5: Sync -->
                            <div class="wizard-step" data-step="5" style="display: none;">
                                <h5>Ready to Sync!</h5>
                                <p class="text-muted">Your vendor is configured. Run your first sync.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Sync Mode</label>
                                    <select name="sync_mode" class="form-select">
                                        <option value="full">Full Sync (All Products)</option>
                                        <option value="incremental">Incremental Sync (Updated Only)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Batch Size</label>
                                    <input type="number" name="batch_size" value="200" min="50" max="500" class="form-control">
                                </div>
                            </div>

                            <!-- Navigation -->
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-secondary" id="wizardPrevBtn" disabled>
                                    ← Previous
                                </button>
                                <button type="button" class="btn btn-primary" id="wizardNextBtn">
                                    Next →
                                </button>
                                <button type="submit" class="btn btn-success" id="wizardFinishBtn" style="display: none;">
                                    🚀 Complete Setup & Sync
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== DASHBOARD TAB =====
function woosync_tab_dashboard() {
    $vendors = woosync_get_vendors();
    $active_vendor = woosync_get_active_vendor();
    $sync_log = array_reverse((array) get_option('woosync_sync_log', []));
    ?>
    <div class="tab-pane fade show active">
        <div class="row">
            <div class="col-md-8">
                <!-- Vendor Cards -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📦 Connected Vendors</h5>
                        <a href="<?php echo admin_url('admin.php?page=woosync-vendors'); ?>" class="btn btn-sm btn-primary">
                            ➕ Add Vendor
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($vendors)): ?>
                        <div class="alert alert-warning">
                            No vendors connected. <a href="?page=woosync&skip_wizard=1">Add your first vendor</a> to start syncing.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th>Status</th>
                                        <th>Products</th>
                                        <th>Last Sync</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vendors as $vendor): ?>
                                    <tr class="<?php echo $vendor['id'] === ($active_vendor['id'] ?? '') ? 'table-primary' : ''; ?>">
                                        <td>
                                            <strong><?php echo esc_html($vendor['name']); ?></strong>
                                            <?php if ($vendor['id'] === ($active_vendor['id'] ?? '')): ?>
                                            <span class="badge bg-primary ms-2">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $vendor['enabled'] ? '✅ Enabled' : '❌ Disabled'; ?>
                                        </td>
                                        <td><?php echo number_format($vendor['total_products'] ?? 0); ?></td>
                                        <td>
                                            <?php 
                                            echo !empty($vendor['last_sync']) 
                                                ? date('d M @ H:i', strtotime($vendor['last_sync'])) 
                                                : 'Never';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($vendor['id'] !== ($active_vendor['id'] ?? '')): ?>
                                                <button type="button" class="btn btn-outline-primary set-active-btn" 
                                                        data-vendor-id="<?php echo esc_attr($vendor['id']); ?>">
                                                    Set Active
                                                </button>
                                                <?php endif; ?>
                                                <a href="?page=woosync&tab=sync&vendor=<?php echo esc_attr($vendor['id']); ?>" 
                                                   class="btn btn-outline-secondary">
                                                    Sync
                                                </a>
                                                <a href="?page=woosync-vendors&action=edit&vendor=<?php echo esc_attr($vendor['id']); ?>" 
                                                   class="btn btn-outline-info">
                                                    Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Sync Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Recent Activity</h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($sync_log)): ?>
                        <p class="text-muted">No sync activity yet.</p>
                        <?php else: ?>
                        <div id="syncLogList">
                            <?php foreach (array_slice($sync_log, 0, 20) as $entry): ?>
                            <div class="log-entry small mb-2 p-2 <?php 
                                if (strpos($entry, '✅') !== false) echo 'text-success';
                                elseif (strpos($entry, '❌') !== false) echo 'text-danger';
                                elseif (strpos($entry, '⏳') !== false) echo 'text-warning';
                            ?>">
                                <?php echo esc_html($entry); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">⚡ Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($active_vendor): ?>
                        <a href="?page=woosync&tab=sync" class="btn btn-success w-100 mb-2">
                            🔄 Run Sync Now
                        </a>
                        <a href="?page=woosync&tab=mapping" class="btn btn-outline-primary w-100 mb-2">
                            🗺️ Manage Field Mapping
                        </a>
                        <?php endif; ?>
                        <a href="?page=woosync-vendors" class="btn btn-outline-secondary w-100 mb-2">
                            📦 Manage Vendors
                        </a>
                        <a href="?page=woosync-log" class="btn btn-outline-info w-100">
                            📋 View Full Log
                        </a>
                    </div>
                </div>

                <!-- Sync History Chart -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">📈 Sync History</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="syncHistoryChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== SYNC TAB =====
function woosync_tab_sync() {
    $active_vendor = woosync_get_active_vendor();
    $batch_size = get_option('woosync_batch_size', 200);
    $last_sync = $active_vendor['last_sync'] ?? get_option('woosync_last_sync', '');
    
    // Detect available sync modes
    $endpoints = woosync_get_vendor_endpoints($active_vendor);
    $has_full_sync = isset($endpoints['products']) && $endpoints['products']['enabled'];
    $has_incremental_sync = isset($endpoints['products_updated']) && $endpoints['products_updated']['enabled'];
    ?>
    <div class="tab-pane fade show active">
        <div class="row">
            <div class="col-md-8">
                <!-- Sync Controls -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">🔄 Sync Controls - <?php echo esc_html($active_vendor['name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="syncForm">
                            <?php wp_nonce_field('woosync_nonce'); ?>
                            
                            <div class="alert alert-info">
                                <strong>Last Sync:</strong> 
                                <?php echo $last_sync ? date('d M Y @ H:i:s', strtotime($last_sync)) : 'Never'; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Batch Size</label>
                                <input type="number" name="batch_size" value="<?php echo $batch_size; ?>" 
                                       min="50" max="500" class="form-control" placeholder="Records per batch">
                                <small class="text-muted">Recommended: 200</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sync Mode</label>
                                <div>
                                    <?php if ($has_full_sync): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sync_mode" 
                                               value="full" id="sync_full" checked>
                                        <label class="form-check-label" for="sync_full">
                                            🔄 Full Sync (All Products)
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_incremental_sync): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sync_mode" 
                                               value="incremental" id="sync_incremental"
                                               <?php echo !$has_full_sync ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sync_incremental">
                                            📝 Incremental Sync (Updated Only)
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!$has_full_sync && !$has_incremental_sync): ?>
                                    <div class="alert alert-warning">
                                        No sync endpoints enabled. Please configure endpoints in vendor settings.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="btn-group mb-4" role="group">
                                <button type="submit" name="action" value="run_sync" class="btn btn-success btn-lg">
                                    ▶️ Run Sync Now
                                </button>
                                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#cronModal">
                                    ⏰ Cron Setup
                                </button>
                            </div>
                        </form>

                        <!-- Progress Bar -->
                        <div id="syncProgress" style="display: none;">
                            <div class="progress mb-3" style="height: 30px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                     role="progressbar" id="progressBar" style="width: 0%;">
                                    <span id="progressText" class="fs-5">0%</span>
                                </div>
                            </div>
                            <div id="syncDetails" class="text-muted small"></div>
                        </div>
                    </div>
                </div>

                <!-- Sync Log -->
                <div class="card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between">
                        <h5 class="mb-0">📋 Sync Log</h5>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('woosync_nonce'); ?>
                            <button type="submit" name="clear_log" class="btn btn-sm btn-warning">Clear Log</button>
                        </form>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div id="syncLog">
                            <?php
                            $log = array_reverse((array) get_option('woosync_sync_log', []));
                            if (empty($log)) {
                                echo '<p class="text-muted">No log entries yet.</p>';
                            } else {
                                foreach (array_slice($log, 0, 30) as $entry) {
                                    echo '<div class="text-monospace small mb-2">' . esc_html($entry) . '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Sync Stats -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">📊 Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Products:</span>
                            <strong><?php echo number_format($active_vendor['total_products'] ?? 0); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sync Mode:</span>
                            <strong><?php echo $active_vendor['sync_mode'] ?? 'full'; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Endpoints:</span>
                            <strong><?php echo count(array_filter($endpoints, fn($e) => $e['enabled'])); ?> enabled</strong>
                        </div>
                    </div>
                </div>

                <!-- Visual Sync Progress -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🔄 Sync Progress</h5>
                    </div>
                    <div class="card-body">
                        <div id="visualProgress">
                            <div class="text-center text-muted">
                                <p>Run a sync to see progress</p>
                            </div>
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
                    <h5 class="modal-title">⏰ Setup Automatic Syncing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php woosync_render_cron_helper(); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== CRON HELPER =====
function woosync_render_cron_helper() {
    $domain = parse_url(get_home_url(), PHP_URL_HOST);
    $cron_code = sprintf("*/30 * * * * php %swp-cron.php?action=woosync_scheduled_sync", ABSPATH);
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

    <h6>Cron Schedule Options:</h6>
    <ul class="list-group">
        <li class="list-group-item"><code>*/5 * * * *</code> - Every 5 minutes</li>
        <li class="list-group-item"><code>*/15 * * * *</code> - Every 15 minutes</li>
        <li class="list-group-item"><code>*/30 * * * *</code> - Every 30 minutes (recommended)</li>
        <li class="list-group-item"><code>0 * * * *</code> - Hourly</li>
        <li class="list-group-item"><code>0 0 * * *</code> - Daily at midnight</li>
    </ul>
    <?php
}

// ===== FIELD MAPPING TAB =====
function woosync_tab_field_mapping() {
    $active_vendor = woosync_get_active_vendor();
    $mapping = woosync_get_vendor_field_mapping($active_vendor);
    $wc_fields = woosync_get_wc_fields();
    ?>
    <div class="tab-pane fade show active">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between">
                <h5 class="mb-0">🗺️ Field Mapping - <?php echo esc_html($active_vendor['name']); ?></h5>
                <button type="button" class="btn btn-light btn-sm" id="autoDetectMappingBtn">
                    🔍 Auto-Detect from API
                </button>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Map vendor API fields to WooCommerce product fields.</strong><br>
                    Auto-detected mappings are shown with confidence scores. You can override, add, or remove any mapping.
                </div>

                <form method="post" id="fieldMappingForm">
                    <?php wp_nonce_field('woosync_nonce'); ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="mappingTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;">WooCommerce Field</th>
                                    <th style="width: 5%;">→</th>
                                    <th style="width: 30%;">API Field</th>
                                    <th style="width: 15%;">Confidence</th>
                                    <th style="width: 20%;">Sample Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wc_fields as $wc_key => $config): 
                                    $current_mapping = $mapping[$wc_key] ?? '';
                                    $confidence = '';
                                    $confidence_class = '';
                                    $confidence_icon = '';
                                    
                                    if ($current_mapping) {
                                        // Determine confidence based on mapping quality
                                        $confidence = 'Medium';
                                        $confidence_class = 'warning';
                                        $confidence_icon = '⚠️';
                                        
                                        // Check if it's a known good mapping
                                        $templates = woosync_get_vendor_templates();
                                        $slug = $active_vendor['slug'] ?? '';
                                        if (isset($templates[$slug]['field_mapping_template'][$wc_key])) {
                                            if ($templates[$slug]['field_mapping_template'][$wc_key] === $current_mapping) {
                                                $confidence = 'High';
                                                $confidence_class = 'success';
                                                $confidence_icon = '✅';
                                            }
                                        }
                                    }
                                ?>
                                <tr data-wc-field="<?php echo esc_attr($wc_key); ?>">
                                    <td>
                                        <strong><?php echo esc_html($config['label']); ?></strong>
                                        <small class="text-muted d-block"><?php echo esc_html($config['category']); ?></small>
                                    </td>
                                    <td class="text-center">→</td>
                                    <td>
                                        <input type="text" 
                                               name="mapping[<?php echo esc_attr($wc_key); ?>]" 
                                               value="<?php echo esc_attr($current_mapping); ?>" 
                                               class="form-control form-control-sm api-field-input" 
                                               placeholder="e.g., <?php echo esc_attr($wc_key === 'name' ? 'Description' : 'FieldName'); ?>"
                                               list="api-fields-list">
                                        <datalist id="api-fields-list">
                                            <!-- Populated by JavaScript -->
                                        </datalist>
                                    </td>
                                    <td>
                                        <?php if ($current_mapping): ?>
                                        <span class="badge bg-<?php echo $confidence_class; ?>">
                                            <?php echo $confidence_icon; ?> <?php echo $confidence; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Not mapped</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="sample-value text-muted small">-</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-info me-2" id="testMappingBtn">
                            🧪 Test Mapping
                        </button>
                        <button type="submit" name="save_mapping" class="btn btn-success">
                            💾 Save Mapping
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="resetMappingBtn">
                            🔄 Reset to Template
                        </button>
                    </div>
                </form>

                <!-- Test Result -->
                <div id="mappingTestResult" style="display: none;" class="alert alert-success mt-3">
                    <h6>📦 Test Product Preview</h6>
                    <pre id="testProductData" class="mb-0"></pre>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== SETTINGS TAB =====
function woosync_tab_settings() {
    $active_vendor = woosync_get_active_vendor();
    $batch_size = get_option('woosync_batch_size', 200);
    $sync_schedule = get_option('woosync_sync_schedule', '');
    ?>
    <div class="tab-pane fade show active">
        <div class="row">
            <div class="col-md-6">
                <!-- Current Vendor Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">📦 <?php echo esc_html($active_vendor['name']); ?> Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <?php wp_nonce_field('woosync_nonce'); ?>
                            
                            <div class="mb-3">
                                <label class="form-label">API Base URL</label>
                                <input type="url" name="api_url" value="<?php echo esc_attr($active_vendor['api_url'] ?? ''); ?>" class="form-control">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Auth URL</label>
                                <input type="url" name="auth_url" value="<?php echo esc_attr($active_vendor['auth_url'] ?? ''); ?>" class="form-control">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sync Mode</label>
                                <select name="sync_mode" class="form-select">
                                    <option value="full" <?php selected($active_vendor['sync_mode'] ?? 'full', 'full'); ?>>Full Sync</option>
                                    <option value="incremental" <?php selected($active_vendor['sync_mode'] ?? 'full', 'incremental'); ?>>Incremental Sync</option>
                                </select>
                            </div>

                            <button type="submit" name="save_vendor_settings" class="btn btn-primary">
                                💾 Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Global Settings -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">⚙️ Global Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <?php wp_nonce_field('woosync_nonce'); ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Default Batch Size</label>
                                <input type="number" name="batch_size" value="<?php echo $batch_size; ?>" min="50" max="500" class="form-control">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Auto Sync Schedule</label>
                                <select name="sync_schedule" class="form-select">
                                    <option value="">Manual Only</option>
                                    <option value="5min" <?php selected($sync_schedule, '5min'); ?>>Every 5 Minutes</option>
                                    <option value="15min" <?php selected($sync_schedule, '15min'); ?>>Every 15 Minutes</option>
                                    <option value="30min" <?php selected($sync_schedule, '30min'); ?>>Every 30 Minutes</option>
                                    <option value="hourly" <?php selected($sync_schedule, 'hourly'); ?>>Every Hour</option>
                                    <option value="daily" <?php selected($sync_schedule, 'daily'); ?>>Daily</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Log Retention (days)</label>
                                <input type="number" name="log_retain_days" value="<?php echo get_option('woosync_log_retain_days', 30); ?>" min="7" max="365" class="form-control">
                            </div>

                            <button type="submit" name="save_global_settings" class="btn btn-success">
                                💾 Save Global Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== VENDORS PAGE =====
function woosync_render_vendors_page() {
    $action = $_GET['action'] ?? 'list';
    $vendor_id = $_GET['vendor'] ?? '';
    
    if ($action === 'edit' && $vendor_id) {
        woosync_render_vendor_edit_page($vendor_id);
    } elseif ($action === 'add') {
        woosync_render_vendor_add_page();
    } else {
        woosync_render_vendors_list();
    }
}

function woosync_render_vendors_list() {
    $vendors = woosync_get_vendors();
    $active_vendor = woosync_get_active_vendor();
    $templates = woosync_get_vendor_templates();
    ?>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>📦 Vendor Management</h1>
            <a href="?page=woosync-vendors&action=add" class="btn btn-primary">➕ Add New Vendor</a>
        </div>

        <?php if (empty($vendors)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <h3 class="text-muted">No vendors configured</h3>
                <p>Add your first vendor to start syncing products.</p>
                <a href="?page=woosync-vendors&action=add" class="btn btn-primary btn-lg">➕ Add First Vendor</a>
            </div>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($vendors as $vendor): ?>
            <div class="col-md-4 mb-4">
                <div class="card <?php echo $vendor['id'] === ($active_vendor['id'] ?? '') ? 'border-primary' : ''; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo esc_html($vendor['name']); ?></h5>
                        <?php if ($vendor['id'] === ($active_vendor['id'] ?? '')): ?>
                        <span class="badge bg-primary">Active</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small"><?php echo esc_html($vendor['slug'] ?? ''); ?></p>
                        <div class="mb-2">
                            <strong>Products:</strong> <?php echo number_format($vendor['total_products'] ?? 0); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Last Sync:</strong> 
                            <?php echo !empty($vendor['last_sync']) ? date('d M @ H:i', strtotime($vendor['last_sync'])) : 'Never'; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong> <?php echo $vendor['enabled'] ? '✅ Enabled' : '❌ Disabled'; ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group w-100">
                            <?php if ($vendor['id'] !== ($active_vendor['id'] ?? '')): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm set-active-btn" 
                                    data-vendor-id="<?php echo esc_attr($vendor['id']); ?>">
                                Set Active
                            </button>
                            <?php endif; ?>
                            <a href="?page=woosync-vendors&action=edit&vendor=<?php echo esc_attr($vendor['id']); ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                Edit
                            </a>
                            <button type="button" class="btn btn-outline-danger btn-sm delete-vendor-btn"
                                    data-vendor-id="<?php echo esc_attr($vendor['id']); ?>"
                                    data-vendor-name="<?php echo esc_attr($vendor['name']); ?>">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Vendor Templates -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">📚 Available Vendor Templates</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($templates as $slug => $template): ?>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6><?php echo esc_html($template['name']); ?></h6>
                                <p class="small text-muted"><?php echo esc_html($template['description']); ?></p>
                                <code class="small"><?php echo esc_html($template['auth_type']); ?></code>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function woosync_render_vendor_add_page() {
    $templates = woosync_get_vendor_templates();
    ?>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>➕ Add New Vendor</h1>
            <a href="?page=woosync-vendors" class="btn btn-outline-secondary">← Back to Vendors</a>
        </div>

        <div class="row">
            <div class="col-md-3">
                <!-- Template Selection -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Select Template</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group" id="templateList">
                            <button type="button" class="list-group-item list-group-item-action active" data-template="custom">
                                ➕ Custom Vendor
                            </button>
                            <?php foreach ($templates as $slug => $template): ?>
                            <button type="button" class="list-group-item list-group-item-action" data-template="<?php echo esc_attr($slug); ?>">
                                <?php echo esc_html($template['name']); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Vendor Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="vendorForm">
                            <?php wp_nonce_field('woosync_nonce'); ?>
                            <input type="hidden" name="vendor_template" id="selectedTemplate" value="custom">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Vendor Name *</label>
                                        <input type="text" name="vendor_name" required class="form-control" placeholder="My Supplier">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Slug *</label>
                                        <input type="text" name="vendor_slug" required class="form-control" placeholder="my-supplier">
                                        <small class="text-muted">Unique identifier, lowercase with hyphens</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">API Base URL *</label>
                                        <input type="url" name="api_url" required class="form-control" placeholder="https://api.example.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Auth URL *</label>
                                        <input type="url" name="auth_url" required class="form-control" placeholder="https://auth.example.com">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Authentication Type</label>
                                <select name="auth_type" id="authTypeSelect" class="form-select">
                                    <option value="vendor_login">Vendor Login (Username/Password)</option>
                                    <option value="api_key">API Key</option>
                                    <option value="bearer_token">Bearer Token</option>
                                    <option value="oauth2">OAuth 2.0</option>
                                </select>
                            </div>

                            <div id="authCredentialsFields">
                                <!-- Dynamic fields based on auth type -->
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sync Mode</label>
                                <select name="sync_mode" class="form-select">
                                    <option value="full">Full Sync (All Products)</option>
                                    <option value="incremental">Incremental Sync (Updated Only)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <input type="checkbox" name="enabled" checked> Enable Vendor
                                </label>
                            </div>

                            <hr>

                            <h5>Endpoints Configuration</h5>
                            <div id="endpointsConfig">
                                <p class="text-muted">Configure API endpoints after saving the vendor.</p>
                            </div>

                            <div class="mt-4">
                                <button type="submit" name="save_vendor" class="btn btn-success btn-lg">
                                    💾 Save Vendor
                                </button>
                                <a href="?page=woosync-vendors" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function woosync_render_vendor_edit_page($vendor_id) {
    $vendor = woosync_get_vendor_by_id($vendor_id);
    if (!$vendor) {
        wp_die('Vendor not found');
    }
    
    $endpoints = woosync_get_vendor_endpoints($vendor);
    ?>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>✏️ Edit Vendor: <?php echo esc_html($vendor['name']); ?></h1>
            <a href="?page=woosync-vendors" class="btn btn-outline-secondary">← Back to Vendors</a>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Basic Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <?php wp_nonce_field('woosync_nonce'); ?>
                            <input type="hidden" name="vendor_id" value="<?php echo esc_attr($vendor_id); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Vendor Name</label>
                                        <input type="text" name="vendor_name" value="<?php echo esc_attr($vendor['name']); ?>" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Slug</label>
                                        <input type="text" name="vendor_slug" value="<?php echo esc_attr($vendor['slug']); ?>" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">API Base URL</label>
                                        <input type="url" name="api_url" value="<?php echo esc_attr($vendor['api_url']); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Auth URL</label>
                                        <input type="url" name="auth_url" value="<?php echo esc_attr($vendor['auth_url']); ?>" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sync Mode</label>
                                <select name="sync_mode" class="form-select">
                                    <option value="full" <?php selected($vendor['sync_mode'] ?? 'full', 'full'); ?>>Full Sync</option>
                                    <option value="incremental" <?php selected($vendor['sync_mode'] ?? 'full', 'incremental'); ?>>Incremental Sync</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <input type="checkbox" name="enabled" <?php checked($vendor['enabled'] ?? true); ?>> Enable Vendor
                                </label>
                            </div>

                            <button type="submit" name="save_vendor" class="btn btn-success">💾 Save Changes</button>
                        </form>
                    </div>
                </div>

                <!-- Endpoints -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">API Endpoints</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <?php wp_nonce_field('woosync_nonce'); ?>
                            <input type="hidden" name="vendor_id" value="<?php echo esc_attr($vendor_id); ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Enabled</th>
                                            <th>Label</th>
                                            <th>Path</th>
                                            <th>Full URL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($endpoints as $key => $ep): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="endpoints[<?php echo $key; ?>][enabled]" 
                                                       value="1" <?php checked($ep['enabled'] ?? false); ?>>
                                            </td>
                                            <td>
                                                <input type="text" name="endpoints[<?php echo $key; ?>][label]" 
                                                       value="<?php echo esc_attr($ep['label']); ?>" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input type="text" name="endpoints[<?php echo $key; ?>][path]" 
                                                       value="<?php echo esc_attr($ep['path']); ?>" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <code class="small"><?php echo esc_html($vendor['api_url'] . $ep['path']); ?></code>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" name="save_endpoints" class="btn btn-primary">💾 Save Endpoints</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Vendor Info -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Vendor Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>ID:</strong> <code><?php echo esc_html($vendor['id']); ?></code>
                        </div>
                        <div class="mb-2">
                            <strong>Auth Type:</strong> <?php echo esc_html($vendor['auth_type'] ?? 'vendor_login'); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Products:</strong> <?php echo number_format($vendor['total_products'] ?? 0); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Created:</strong> <?php echo esc_html($vendor['created_at'] ?? ''); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Last Updated:</strong> <?php echo esc_html($vendor['updated_at'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <!-- Test Connection -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Test Connection</h5>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn btn-outline-primary w-100" id="testVendorConnectionBtn">
                            🔗 Test API Connection
                        </button>
                        <div id="connectionTestResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== LOG PAGE =====
function woosync_render_log_page() {
    $log = array_reverse((array) get_option('woosync_sync_log', []));
    $filter = $_GET['filter'] ?? 'all';
    ?>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>📋 Sync Log</h1>
            <form method="post" class="d-inline">
                <?php wp_nonce_field('woosync_nonce'); ?>
                <button type="submit" name="clear_log" class="btn btn-danger" onclick="return confirm('Clear all log entries?');">
                    🗑️ Clear Log
                </button>
            </form>
        </div>

        <!-- Filters -->
        <div class="btn-group mb-3">
            <a href="?page=woosync-log&filter=all" class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All
            </a>
            <a href="?page=woosync-log&filter=success" class="btn btn-outline-success <?php echo $filter === 'success' ? 'active' : ''; ?>">
                ✅ Success
            </a>
            <a href="?page=woosync-log&filter=error" class="btn btn-outline-danger <?php echo $filter === 'error' ? 'active' : ''; ?>">
                ❌ Errors
            </a>
            <a href="?page=woosync-log&filter=warning" class="btn btn-outline-warning <?php echo $filter === 'warning' ? 'active' : ''; ?>">
                ⚠️ Warnings
            </a>
        </div>

        <div class="card">
            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                <?php if (empty($log)): ?>
                <p class="text-muted text-center py-5">No log entries yet.</p>
                <?php else: ?>
                <table class="table table-sm">
                    <thead class="sticky-top bg-light">
                        <tr>
                            <th style="width: 20%;">Timestamp</th>
                            <th style="width: 60%;">Message</th>
                            <th style="width: 20%;">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log as $entry): 
                            $type = 'info';
                            if (strpos($entry, '✅') !== false) $type = 'success';
                            elseif (strpos($entry, '❌') !== false) $type = 'error';
                            elseif (strpos($entry, '⚠️') !== false) $type = 'warning';
                            elseif (strpos($entry, '⏳') !== false) $type = 'warning';
                            
                            if ($filter !== 'all' && $filter !== $type) continue;
                        ?>
                        <tr class="table-<?php echo $type; ?>">
                            <td class="text-nowrap"><?php echo esc_html(substr($entry, 0, 19)); ?></td>
                            <td><?php echo esc_html(substr($entry, 21)); ?></td>
                            <td><span class="badge bg-<?php echo $type === 'error' ? 'danger' : ($type === 'success' ? 'success' : 'warning'); ?>"><?php echo ucfirst($type); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// ===== PROMOTIONS PAGE =====
function woosync_render_promotions_page() {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions');
    
    $notifications_obj = WooSync_Notifications::get_instance();
    $active_tab = $_GET['tab'] ?? 'notifications';
    $edit_id = $_GET['edit'] ?? '';
    
    if ($edit_id && $active_tab === 'notifications') {
        $notifications = $notifications_obj->get_notifications();
        $edit_notification = $notifications[$edit_id] ?? null;
    } else {
        $edit_notification = null;
    }
    
    ?>
    <div class="container-fluid mt-4 woosync-promotions-container">
        <div class="woosync-brand-header">
            <div>
                <h1 class="mb-1">Promotions</h1>
                <span class="powered-by">Manage notifications and cross-sells for WooSync users</span>
            </div>
            <div class="text-end">
                <img src="<?php echo WOOSYNC_ASSETS; ?>images/woosync-logo.svg" height="40" alt="WooSync">
            </div>
        </div>
        
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" href="?page=woosync-promotions&tab=notifications">
                    🔔 Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'services' ? 'active' : ''; ?>" href="?page=woosync-promotions&tab=services">
                    🛠️ Services
                </a>
            </li>
        </ul>
        
        <?php if ($active_tab === 'notifications'): ?>
            <?php woosync_render_notifications_tab($notifications_obj, $edit_notification); ?>
        <?php else: ?>
            <?php woosync_render_services_tab($notifications_obj); ?>
        <?php endif; ?>
    </div>
    <?php
}

function woosync_render_notifications_tab($notifications_obj, $edit_notification = null) {
    $notifications = $notifications_obj->get_notifications();
    $active_notifications = $notifications_obj->get_active_notifications();
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">📋 Notification List</h5>
                    <a href="?page=woosync-promotions&tab=notifications&action=add" class="btn btn-primary btn-sm">
                        ➕ Add Notification
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted text-center py-4">No notifications created yet.</p>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Stats</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $id => $notif): ?>
                                    <?php 
                                        $is_active = isset($active_notifications[$id]);
                                        $expiry = $notif['expiry_date'] ?? '';
                                        $expired = !empty($expiry) && strtotime($expiry) < time();
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($notif['title'] ?? 'Untitled'); ?></strong>
                                            <?php if ($expired): ?>
                                                <span class="badge bg-secondary ms-2">Expired</span>
                                            <?php elseif ($is_active): ?>
                                                <span class="badge bg-success ms-2">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary ms-2">Scheduled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-info"><?php echo esc_html($notif['type'] ?? 'banner'); ?></span></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo esc_html($notif['show_from'] ?? 'Now'); ?>
                                                <?php if (!empty($expiry)): ?>
                                                    → <?php echo esc_html($expiry); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                <span class="text-success">Views: <?php echo intval($notif['views'] ?? 0); ?></span>
                                                <span class="text-primary ms-2">Clicks: <?php echo intval($notif['clicks'] ?? 0); ?></span>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?page=woosync-promotions&tab=notifications&edit=<?php echo esc_attr($id); ?>" class="btn btn-outline-secondary">Edit</a>
                                                <form method="post" class="d-inline">
                                                    <?php wp_nonce_field('woosync_notifications'); ?>
                                                    <input type="hidden" name="notification_id" value="<?php echo esc_attr($id); ?>">
                                                    <button type="submit" name="woosync_delete_notification" class="btn btn-outline-danger btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="woosync-notification-editor">
                <h5 class="mb-3"><?php echo $edit_notification ? '✏️ Edit Notification' : '➕ Create Notification'; ?></h5>
                
                <form method="post">
                    <?php wp_nonce_field('woosync_notifications'); ?>
                    <?php if ($edit_notification): ?>
                        <input type="hidden" name="notification_id" value="<?php echo esc_attr($_GET['edit']); ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="notification_title" class="form-control" required value="<?php echo esc_attr($edit_notification['title'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="notification_message" class="form-control" rows="3"><?php echo esc_textarea($edit_notification['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Link (optional)</label>
                        <input type="url" name="notification_link" class="form-control" value="<?php echo esc_attr($edit_notification['link'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">CTA Button Text</label>
                        <input type="text" name="notification_cta" class="form-control" value="<?php echo esc_attr($edit_notification['cta_text'] ?? 'Learn More'); ?>">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Background Color</label>
                            <input type="color" name="notification_bg_color" class="form-control form-control-color" value="<?php echo esc_attr($edit_notification['background_color'] ?? '#1a1a2e'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Text Color</label>
                            <input type="color" name="notification_text_color" class="form-control form-control-color" value="<?php echo esc_attr($edit_notification['text_color'] ?? '#ffffff'); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="notification_type" class="form-select">
                            <option value="banner" <?php selected($edit_notification['type'] ?? 'banner', 'banner'); ?>>Banner (Top of pages)</option>
                            <option value="notice" <?php selected($edit_notification['type'] ?? 'banner', 'notice'); ?>>Admin Notice</option>
                        </select>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Show From</label>
                            <input type="date" name="notification_show_from" class="form-control" value="<?php echo esc_attr($edit_notification['show_from'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="notification_expiry" class="form-control" value="<?php echo esc_attr($edit_notification['expiry_date'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="woosync_save_notification" class="btn btn-success w-100">
                        💾 <?php echo $edit_notification ? 'Update Notification' : 'Create Notification'; ?>
                    </button>
                </form>
            </div>
            
            <div class="woosync-notification-preview mt-4">
                <h4>Preview</h4>
                <div id="preview_banner" class="woosync-notification-banner" style="background-color: <?php echo esc_attr($edit_notification['background_color'] ?? '#1a1a2e'); ?>;">
                    <div class="woosync-notification-content">
                        <span class="dashicons dashicons-info"></span>
                        <div class="woosync-notification-text">
                            <strong id="preview_title"><?php echo esc_html($edit_notification['title'] ?? 'Notification Title'); ?></strong>
                            <p id="preview_message"><?php echo esc_html($edit_notification['message'] ?? 'Your notification message will appear here.'); ?></p>
                        </div>
                        <?php if (!empty($edit_notification['link'])): ?>
                        <a href="#" id="preview_cta" class="woosync-notification-cta"><?php echo esc_html($edit_notification['cta_text'] ?? 'Learn More'); ?> →</a>
                        <?php else: ?>
                        <a href="#" id="preview_cta" class="woosync-notification-cta" style="display: none;">Learn More →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function woosync_render_services_tab($notifications_obj) {
    $services = $notifications_obj->get_services();
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">🛠️ Mediaplatform Services</h5>
                    <a href="?page=woosync-promotions&tab=services&action=add" class="btn btn-primary btn-sm">
                        ➕ Add Service
                    </a>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">These services are displayed as cross-sell cards in the WooSync dashboard. They link to Mediaplatform's service pages.</p>
                    
                    <div class="woosync-services-grid">
                        <?php foreach ($services as $service): ?>
                            <div class="woosync-service-card <?php echo $service['enabled'] ? '' : 'opacity-50'; ?>">
                                <div class="woosync-service-icon">
                                    <span class="dashicons <?php echo esc_attr($service['icon'] ?? 'dashicons-admin-generic'); ?>"></span>
                                </div>
                                <h4><?php echo esc_html($service['title']); ?></h4>
                                <p><?php echo esc_html($service['description']); ?></p>
                                <div class="d-flex gap-2 align-items-center">
                                    <?php if (!empty($service['link'])): ?>
                                    <a href="<?php echo esc_url($service['link']); ?>" class="woosync-service-cta" target="_blank" rel="noopener">
                                        <?php echo esc_html($service['cta_text'] ?? 'Learn More'); ?>
                                    </a>
                                    <?php endif; ?>
                                    <form method="post" class="ms-auto">
                                        <?php wp_nonce_field('woosync_notifications'); ?>
                                        <input type="hidden" name="service_id" value="<?php echo esc_attr($service['id']); ?>">
                                        <button type="submit" name="woosync_delete_service" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="woosync-notification-editor">
                <h5 class="mb-3">➕ Add Service</h5>
                
                <form method="post">
                    <?php wp_nonce_field('woosync_notifications'); ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Service Title</label>
                        <input type="text" name="service_title" class="form-control" required placeholder="Custom WooCommerce Development">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="service_description" class="form-control" rows="3" placeholder="Describe the service..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Icon (Dashicons class)</label>
                        <input type="text" name="service_icon" class="form-control" value="dashicons-admin-generic" placeholder="dashicons-cart">
                        <small class="text-muted">Use Dashicons classes: dashicons-cart, dashicons-rest-api, dashicons-art, etc.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Link URL</label>
                        <input type="url" name="service_link" class="form-control" placeholder="https://mediaplatform.co.za/services/...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">CTA Button Text</label>
                        <input type="text" name="service_cta" class="form-control" value="Learn More" placeholder="Get a Quote">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <input type="checkbox" name="service_enabled" value="1" checked> Enable Service
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <input type="checkbox" name="service_featured" value="1"> Featured (shown first)
                        </label>
                    </div>
                    
                    <button type="submit" name="woosync_save_service" class="btn btn-success w-100">
                        💾 Add Service
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
}


// ===== HANDLE POST REQUESTS =====
add_action('admin_init', 'woosync_handle_post_requests');
function woosync_handle_post_requests() {
    if (empty($_POST)) return;
    if (!current_user_can('manage_options')) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'woosync_nonce') && 
        !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'woosync_wizard')) return;

    // Save vendor
    if (isset($_POST['save_vendor'])) {
        $vendor_data = [
            'name' => sanitize_text_field($_POST['vendor_name'] ?? ''),
            'slug' => sanitize_key($_POST['vendor_slug'] ?? ''),
            'api_url' => esc_url_raw($_POST['api_url'] ?? ''),
            'auth_url' => esc_url_raw($_POST['auth_url'] ?? ''),
            'auth_type' => sanitize_key($_POST['auth_type'] ?? 'vendor_login'),
            'sync_mode' => sanitize_key($_POST['sync_mode'] ?? 'full'),
            'enabled' => isset($_POST['enabled']),
            'username' => sanitize_text_field($_POST['username'] ?? ''),
            'password' => sanitize_text_field($_POST['password'] ?? ''),
            'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
            'customer_code' => sanitize_text_field($_POST['customer_code'] ?? ''),
        ];
        
        // Handle existing vendor update
        if (!empty($_POST['vendor_id'])) {
            $existing = woosync_get_vendor_by_id($_POST['vendor_id']);
            if ($existing) {
                $vendor_data = array_merge($existing, $vendor_data);
            }
        }
        
        woosync_save_vendor($vendor_data);
        wp_safe_redirect(add_query_arg(['page' => 'woosync-vendors', 'updated' => '1']));
        exit;
    }

    // Save endpoints
    if (isset($_POST['save_endpoints'])) {
        $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? '');
        $vendor = woosync_get_vendor_by_id($vendor_id);
        
        if ($vendor && !empty($_POST['endpoints'])) {
            $vendor['endpoints'] = json_encode($_POST['endpoints']);
            woosync_save_vendor($vendor);
            wp_safe_redirect(add_query_arg(['page' => 'woosync-vendors', 'action' => 'edit', 'vendor' => $vendor_id, 'updated' => '1']));
            exit;
        }
    }

    // Save field mapping
    if (isset($_POST['save_mapping'])) {
        $active_vendor = woosync_get_active_vendor();
        if ($active_vendor && !empty($_POST['mapping'])) {
            $active_vendor['field_mapping'] = json_encode($_POST['mapping']);
            woosync_save_vendor($active_vendor);
            wp_safe_redirect(add_query_arg(['page' => 'woosync', 'tab' => 'mapping', 'updated' => '1']));
            exit;
        }
    }

    // Save global settings
    if (isset($_POST['save_global_settings'])) {
        update_option('woosync_batch_size', intval($_POST['batch_size'] ?? 200));
        update_option('woosync_sync_schedule', sanitize_key($_POST['sync_schedule'] ?? ''));
        update_option('woosync_log_retain_days', intval($_POST['log_retain_days'] ?? 30));
        wp_safe_redirect(add_query_arg(['page' => 'woosync', 'tab' => 'settings', 'updated' => '1']));
        exit;
    }

    // Save vendor settings
    if (isset($_POST['save_vendor_settings'])) {
        $active_vendor = woosync_get_active_vendor();
        if ($active_vendor) {
            $active_vendor['api_url'] = esc_url_raw($_POST['api_url'] ?? '');
            $active_vendor['auth_url'] = esc_url_raw($_POST['auth_url'] ?? '');
            $active_vendor['sync_mode'] = sanitize_key($_POST['sync_mode'] ?? 'full');
            woosync_save_vendor($active_vendor);
            wp_safe_redirect(add_query_arg(['page' => 'woosync', 'tab' => 'settings', 'updated' => '1']));
            exit;
        }
    }

    // Clear log
    if (isset($_POST['clear_log'])) {
        update_option('woosync_sync_log', []);
        wp_safe_redirect(add_query_arg(['page' => 'woosync-log', 'cleared' => '1']));
        exit;
    }

    // Wizard completion
    if (isset($_POST['wizard_complete'])) {
        update_option('woosync_wizard_completed', true);
        wp_safe_redirect(admin_url('admin.php?page=woosync'));
        exit;
    }
}

// ===== SYNC LOG =====
function woosync_sync_log($message) {
    $log = (array) get_option('woosync_sync_log', []);
    $log[] = '[' . current_time('mysql') . '] ' . $message;
    $log = array_slice($log, -500); // Keep last 500 entries
    update_option('woosync_sync_log', $log);
}

// ===== API FUNCTIONS =====
function woosync_get_token($vendor) {
    $cached = get_transient('woosync_token_' . $vendor['id']);
    if ($cached) return $cached;

    $auth_url = $vendor['auth_url'] . '/VendorLogin';
    $payload = json_encode([
        'username' => $vendor['username'] ?? '',
        'password' => $vendor['password'] ?? '',
        'CustomerCode' => $vendor['customer_code'] ?? ''
    ]);

    $response = wp_remote_post($auth_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $payload,
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        woosync_sync_log('❌ Failed to obtain token: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        woosync_sync_log("❌ Token endpoint returned HTTP {$code}");
        return false;
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        woosync_sync_log('❌ Invalid token response: ' . json_last_error_msg());
        return false;
    }

    $token = $data['token'] ?? false;
    if ($token) {
        set_transient('woosync_token_' . $vendor['id'], $token, 55 * MINUTE_IN_SECONDS);
        woosync_sync_log('✅ Token obtained successfully');
    }

    return $token;
}

function woosync_get_api_data($vendor, $endpoint_path, $token = null) {
    if (!$token) {
        $token = woosync_get_token($vendor);
        if (!$token) return false;
    }

    $url = $vendor['api_url'] . $endpoint_path;
    
    $response = wp_remote_get($url, [
        'headers' => ["Authorization" => "Bearer $token"],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        woosync_sync_log('❌ API error: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        woosync_sync_log("❌ API returned HTTP {$code}");
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        woosync_sync_log('❌ Invalid JSON response: ' . json_last_error_msg());
        return false;
    }

    return $data;
}

// ===== APPLY FIELD MAPPING =====
function woosync_apply_field_mapping($wc_product, $vendor_data, $mapping) {
    if (empty($vendor_data) || !is_array($vendor_data)) return $wc_product;
    if (!is_a($wc_product, 'WC_Product')) return $wc_product;

    // Get sample to determine value types
    foreach ($mapping as $wc_key => $api_field) {
        if (empty($api_field)) continue;

        $value = woosync_get_nested_value($vendor_data, $api_field);
        if ($value === null) continue;

        switch ($wc_key) {
            case 'sku':
                $wc_product->set_sku(sanitize_text_field($value));
                break;

            case 'name':
                $wc_product->set_name(sanitize_text_field($value));
                break;

            case 'price':
                $wc_product->set_regular_price(floatval($value));
                break;

            case 'sale_price':
                if (!empty($value)) {
                    $wc_product->set_sale_price(floatval($value));
                }
                break;

            case 'description':
                $wc_product->set_description($value);
                break;

            case 'short_description':
                $wc_product->set_short_description($value);
                break;

            case 'stock':
                $wc_product->set_stock_quantity(intval($value));
                $wc_product->set_manage_stock(true);
                break;

            case 'stock_status':
                $wc_product->set_stock_status($value === '0' ? 'outofstock' : 'instock');
                break;

            case 'weight':
                $wc_product->set_weight(sanitize_text_field($value));
                break;

            case 'dimensions':
                // Parse dimensions (expecting format like "10x20x30" or separate fields)
                $parts = explode('x', $value);
                if (count($parts) >= 3) {
                    $wc_product->set_dimensions([
                        'length' => floatval($parts[0]),
                        'width' => floatval($parts[1]),
                        'height' => floatval($parts[2])
                    ]);
                }
                break;

            case 'images':
                if (is_array($value) && !empty($value)) {
                    $image_ids = [];
                    foreach ($value as $image_url) {
                        if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                            $image_id = woosync_download_image($image_url, $wc_product->get_id());
                            if ($image_id) {
                                $image_ids[] = $image_id;
                            }
                        }
                    }
                    if (!empty($image_ids)) {
                        $wc_product->set_image_id($image_ids[0]);
                        if (count($image_ids) > 1) {
                            $wc_product->set_gallery_image_ids(array_slice($image_ids, 1));
                        }
                    }
                }
                break;

            case 'categories':
                if (!empty($value)) {
                    $category_ids = woosync_get_or_create_categories($value);
                    if (!empty($category_ids)) {
                        $wc_product->set_category_ids($category_ids);
                    }
                }
                break;

            case 'brand':
                // Set brand as product attribute or tag
                woosync_set_product_attribute($wc_product, 'brand', $value);
                break;

            case 'colour':
                woosync_set_product_attribute($wc_product, 'colour', $value);
                break;

            case 'size':
                woosync_set_product_attribute($wc_product, 'size', $value);
                break;

            case 'min_quantity':
                $wc_product->add_meta_data('_min_quantity', intval($value), true);
                break;

            case 'tags':
                if (!empty($value)) {
                    $tags = is_array($value) ? $value : explode(',', $value);
                    $tag_ids = [];
                    foreach ($tags as $tag) {
                        $tag = trim(sanitize_text_field($tag));
                        if ($tag) {
                            $term = get_term_by('name', $tag, 'product_tag');
                            if (!$term) {
                                $term = wp_insert_term($tag, 'product_tag');
                                $tag_ids[] = is_array($term) ? $term['term_id'] : 0;
                            } else {
                                $tag_ids[] = $term->term_id;
                            }
                        }
                    }
                    if (!empty($tag_ids)) {
                        $wc_product->set_tag_ids($tag_ids);
                    }
                }
                break;

            case 'status':
                $wc_product->set_status($value);
                break;

            case 'featured':
                $wc_product->set_featured($value === '1' || $value === 'true');
                break;

            case 'meta':
                // Custom meta field - stored as _custom_[key]
                $wc_product->add_meta_data('_custom_' . $api_field, $value, true);
                break;
        }
    }

    return $wc_product;
}

function woosync_get_nested_value($array, $key) {
    // Support dot notation for nested keys
    $keys = explode('.', $key);
    $value = $array;
    
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return null;
        }
    }
    
    return $value;
}

function woosync_set_product_attribute($product, $name, $value) {
    if (empty($value)) return;
    
    $attributes = $product->get_attributes();
    
    if (isset($attributes[$name])) {
        $attr = $attributes[$name];
        $attr->add_options([sanitize_title($value)]);
    } else {
        $attr = new WC_Product_Attribute();
        $attr->set_id(0);
        $attr->set_name($name);
        $attr->set_options([sanitize_title($value)]);
        $attr->set_visible(true);
        $attr->set_variation(false);
        
        $attributes[$name] = $attr;
        $product->set_attributes($attributes);
    }
}

function woosync_download_image($image_url, $product_id) {
    // Check if image already exists
    $attachment = get_posts([
        'post_type' => 'attachment',
        'meta_query' => [['key' => '_woo_sync_image_url', 'value' => $image_url]],
        'posts_per_page' => 1
    ]);
    
    if (!empty($attachment)) {
        return $attachment[0]->ID;
    }
    
    // Download new image
    $response = wp_remote_get($image_url, ['timeout' => 30]);
    if (is_wp_error($response)) return false;
    
    $image_data = wp_remote_retrieve_body($response);
    $filename = basename($image_url);
    
    $upload = wp_upload_bits($filename, null, $image_data);
    if ($upload['error']) return false;
    
    $attachment = [
        'post_title' => $filename,
        'post_content' => '',
        'post_status' => 'inherit',
        'post_mime_type' => $upload['type']
    ];
    
    $attach_id = wp_insert_attachment($attachment, $upload['file'], $product_id);
    if (!$attach_id || is_wp_error($attach_id)) return false;
    
    update_post_meta($attach_id, '_woo_sync_image_url', $image_url);
    
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $metadata = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $metadata);
    
    return $attach_id;
}

function woosync_get_or_create_categories($category_names) {
    $category_ids = [];
    $names = is_array($category_names) ? $category_names : [$category_names];
    
    foreach ($names as $name) {
        $name = trim(sanitize_text_field($name));
        if (empty($name)) continue;
        
        $term = get_term_by('name', $name, 'product_cat');
        if (!$term) {
            $term = wp_insert_term($name, 'product_cat');
            if (is_array($term)) {
                $category_ids[] = $term['term_id'];
            }
        } else {
            $category_ids[] = $term->term_id;
        }
    }
    
    return $category_ids;
}

// ===== SYNC PROCESSING =====
function woosync_process_sync($vendor, $sync_mode = 'full', $batch_size = 200, $offset = 0) {
    $token = woosync_get_token($vendor);
    if (!$token) {
        return ['success' => false, 'message' => 'Failed to obtain authentication token'];
    }

    $endpoints = woosync_get_vendor_endpoints($vendor);
    $mapping = woosync_get_vendor_field_mapping($vendor);
    
    // Determine endpoint based on sync mode
    $endpoint_key = $sync_mode === 'incremental' ? 'products_updated' : 'products';
    $endpoint = $endpoints[$endpoint_key] ?? null;
    
    if (!$endpoint || !$endpoint['enabled']) {
        return ['success' => false, 'message' => "Endpoint for {$sync_mode} sync not enabled"];
    }

    woosync_sync_log("⏳ Starting {$sync_mode} sync at offset {$offset}");
    
    $products = woosync_get_api_data($vendor, $endpoint['path'], $token);
    if (!$products || !is_array($products)) {
        return ['success' => false, 'message' => 'Failed to fetch products from API'];
    }

    $total = count($products);
    $batch = array_slice($products, $offset, $batch_size);
    $processed = 0;
    $errors = 0;

    foreach ($batch as $p) {
        if (empty($p['ProductCode']) && empty($p['productCode']) && empty($p['SKU']) && empty($p['sku'])) {
            $errors++;
            continue;
        }

        // Determine SKU field based on available data
        $sku = sanitize_text_field($p['ProductCode'] ?? $p['productCode'] ?? $p['SKU'] ?? $p['sku'] ?? '');
        if (empty($sku)) {
            $errors++;
            continue;
        }

        $existing_id = wc_get_product_id_by_sku($sku);

        try {
            if ($existing_id) {
                $wc_product = wc_get_product($existing_id);
            } else {
                $wc_product = new WC_Product_Simple();
                $wc_product->set_status('publish');
                $wc_product->set_catalog_visibility('visible');
            }

            if (!$wc_product || !is_a($wc_product, 'WC_Product')) {
                $errors++;
                continue;
            }

            // Apply field mapping
            $wc_product = woosync_apply_field_mapping($wc_product, $p, $mapping);
            $product_id = $wc_product->save();

            if ($product_id && $product_id > 0) {
                $processed++;
            } else {
                $errors++;
            }
        } catch (Exception $e) {
            $errors++;
            woosync_sync_log("❌ Error processing SKU {$sku}: " . $e->getMessage());
        }
    }

    // Update vendor stats
    $vendor['total_products'] = ($vendor['total_products'] ?? 0) + $processed;
    $vendor['last_sync'] = current_time('mysql');
    woosync_save_vendor($vendor);
    
    // Update global stats
    update_option('woosync_total_products', (int)get_option('woosync_total_products', 0) + $processed);
    update_option('woosync_last_sync', current_time('mysql'));

    $next_offset = $offset + $batch_size;
    $more = $next_offset < $total;

    woosync_sync_log("✅ Synced {$processed} products (Total: {$total}, More: " . ($more ? 'Yes' : 'No') . ")");

    return [
        'success' => true,
        'processed' => $processed,
        'errors' => $errors,
        'total' => $total,
        'more' => $more,
        'next_offset' => $next_offset
    ];
}

// ===== AJAX HANDLERS =====
add_action('wp_ajax_woosync_fetch_token', 'woosync_ajax_fetch_token');
function woosync_ajax_fetch_token() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor = woosync_get_active_vendor();
    if (!$vendor) {
        wp_send_json_error('No active vendor');
    }
    
    $token = woosync_get_token($vendor);
    if ($token) {
        wp_send_json_success(['token' => substr($token, 0, 6) . '...' . substr($token, -6)]);
    } else {
        wp_send_json_error('Failed to fetch token');
    }
}

add_action('wp_ajax_woosync_sync_batch', 'woosync_ajax_sync_batch');
function woosync_ajax_sync_batch() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? '');
    $vendor = $vendor_id ? woosync_get_vendor_by_id($vendor_id) : woosync_get_active_vendor();
    
    if (!$vendor) {
        wp_send_json_error('Vendor not found');
    }
    
    $sync_mode = sanitize_text_field($_POST['sync_mode'] ?? 'full');
    $batch_size = intval($_POST['batch_size'] ?? 200);
    $offset = intval($_POST['offset'] ?? 0);
    
    $result = woosync_process_sync($vendor, $sync_mode, $batch_size, $offset);
    wp_send_json_success($result);
}

add_action('wp_ajax_woosync_auto_detect_fields', 'woosync_ajax_auto_detect_fields');
function woosync_ajax_auto_detect_fields() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? '');
    $vendor = $vendor_id ? woosync_get_vendor_by_id($vendor_id) : woosync_get_active_vendor();
    
    if (!$vendor) {
        wp_send_json_error('Vendor not found');
    }
    
    $token = woosync_get_token($vendor);
    if (!$token) {
        wp_send_json_error('Failed to authenticate');
    }
    
    $endpoints = woosync_get_vendor_endpoints($vendor);
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        wp_send_json_error('Products endpoint not enabled');
    }
    
    $products = woosync_get_api_data($vendor, $products_ep['path'], $token);
    if (!$products || !is_array($products) || empty($products)) {
        wp_send_json_error('No products returned from API');
    }
    
    // Get first product as sample
    $sample = is_array($products[0]) ? $products[0] : $products;
    $api_fields = woosync_detect_api_fields($sample);
    $wc_fields = woosync_get_wc_fields();
    $mappings = woosync_fuzzy_match_fields($api_fields, $wc_fields);
    
    wp_send_json_success([
        'api_fields' => $api_fields,
        'mappings' => $mappings,
        'sample' => $sample
    ]);
}

add_action('wp_ajax_woosync_test_mapping', 'woosync_ajax_test_mapping');
function woosync_ajax_test_mapping() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? '');
    $vendor = $vendor_id ? woosync_get_vendor_by_id($vendor_id) : woosync_get_active_vendor();
    
    if (!$vendor) {
        wp_send_json_error('Vendor not found');
    }
    
    $token = woosync_get_token($vendor);
    if (!$token) {
        wp_send_json_error('Failed to authenticate');
    }
    
    $endpoints = woosync_get_vendor_endpoints($vendor);
    $products_ep = $endpoints['products'] ?? null;
    
    if (!$products_ep || !$products_ep['enabled']) {
        wp_send_json_error('Products endpoint not enabled');
    }
    
    $products = woosync_get_api_data($vendor, $products_ep['path'], $token);
    if (!$products || !is_array($products) || empty($products)) {
        wp_send_json_error('No products returned from API');
    }
    
    $sample = is_array($products[0]) ? $products[0] : $products;
    $mapping = woosync_get_vendor_field_mapping($vendor);
    
    // Create a test product object
    $test_product = new WC_Product_Simple();
    $test_product = woosync_apply_field_mapping($test_product, $sample, $mapping);
    
    $result = [
        'sku' => $test_product->get_sku(),
        'name' => $test_product->get_name(),
        'price' => $test_product->get_regular_price(),
        'sale_price' => $test_product->get_sale_price(),
        'description' => $test_product->get_description(),
        'stock' => $test_product->get_stock_quantity(),
        'weight' => $test_product->get_weight(),
        'dimensions' => $test_product->get_dimensions(),
        'categories' => $test_product->get_category_ids(),
        'status' => $test_product->get_status()
    ];
    
    wp_send_json_success($result);
}

add_action('wp_ajax_woosync_set_active_vendor', 'woosync_ajax_set_active_vendor');
function woosync_ajax_set_active_vendor() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? '');
    $vendor = woosync_get_vendor_by_id($vendor_id);
    
    if (!$vendor) {
        wp_send_json_error('Vendor not found');
    }
    
    update_option('woosync_active_vendor', $vendor_id);
    wp_send_json_success(['message' => 'Active vendor updated']);
}

add_action('wp_ajax_woosync_delete_vendor', 'woosync_ajax_delete_vendor');
function woosync_ajax_delete_vendor() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? '');
    woosync_delete_vendor($vendor_id);
    
    wp_send_json_success(['message' => 'Vendor deleted']);
}


// ===== NOTIFICATION AJAX HANDLERS =====
add_action('wp_ajax_woosync_dismiss_notification', 'woosync_ajax_dismiss_notification');
function woosync_ajax_dismiss_notification() {
    check_ajax_referer('woosync_notifications');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $notification_id = sanitize_text_field($_POST['notification_id'] ?? '');
    $dismissed = get_user_meta(get_current_user_id(), 'woosync_dismissed', true) ?: [];
    
    if (!is_array($dismissed)) $dismissed = [];
    
    if (!in_array($notification_id, $dismissed)) {
        $dismissed[] = $notification_id;
        update_user_meta(get_current_user_id(), 'woosync_dismissed', $dismissed);
    }
    
    wp_send_json_success();
}

add_action('wp_ajax_woosync_record_notification_view', 'woosync_ajax_record_notification_view');
function woosync_ajax_record_notification_view() {
    check_ajax_referer('woosync_notifications');
    
    $notification_id = sanitize_text_field($_POST['notification_id'] ?? '');
    $notifications = get_option('woosync_notifications', []);
    
    if (isset($notifications[$notification_id])) {
        $notifications[$notification_id]['views'] = intval($notifications[$notification_id]['views'] ?? 0) + 1;
        update_option('woosync_notifications', $notifications);
    }
    
    wp_send_json_success();
}

add_action('wp_ajax_woosync_record_notification_click', 'woosync_ajax_record_notification_click');
function woosync_ajax_record_notification_click() {
    check_ajax_referer('woosync_notifications');
    
    $notification_id = sanitize_text_field($_POST['notification_id'] ?? '');
    $notifications = get_option('woosync_notifications', []);
    
    if (isset($notifications[$notification_id])) {
        $notifications[$notification_id]['clicks'] = intval($notifications[$notification_id]['clicks'] ?? 0) + 1;
        update_option('woosync_notifications', $notifications);
    }
    
    wp_send_json_success();
}

add_action('wp_ajax_woosync_test_connection', 'woosync_ajax_test_connection');
// ===== PRODUCT OPTIMIZER AJAX HANDLERS =====
add_action('wp_ajax_woosync_scan_products', 'woosync_ajax_scan_products');
function woosync_ajax_scan_products() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $products = wc_get_products([
        'limit' => -1,
        'status' => ['publish', 'private'],
        'return' => 'objects'
    ]);
    
    $results = [];
    $needs_attention = 0;
    $shopping_ready = 0;
    $total_score = 0;
    
    foreach ($products as $product) {
        $score = woosync_calculate_product_score($product);
        $checks = woosync_check_product_seo($product);
        
        $results[] = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'score' => $score,
            'checks' => $checks,
            'title_length' => strlen($product->get_name()),
            'description_length' => strlen(wp_strip_all_tags($product->get_description())),
            'image_size' => woosync_get_image_size_info($product),
            'alt_text' => woosync_get_product_image_alt($product),
            'price' => $product->get_price() ? 'R' . $product->get_price() : 'Not set',
            'brand' => woosync_get_product_brand($product),
            'taxonomy' => woosync_get_google_taxonomy($product),
            'has_schema' => woosync_product_has_schema($product),
            'category' => woosync_get_product_primary_category($product)
        ];
        
        $total_score += $score;
        if ($score < 70) $needs_attention++;
        if ($checks['title'] && $checks['description'] && $checks['image'] && $checks['price']) $shopping_ready++;
    }
    
    $count = count($results);
    $overall_score = $count > 0 ? round($total_score / $count) : 0;
    
    wp_send_json_success([
        'products' => $results,
        'total_products' => $count,
        'overall_score' => $overall_score,
        'needs_attention' => $needs_attention,
        'shopping_ready' => $shopping_ready
    ]);
}

function woosync_calculate_product_score($product) {
    $checks = woosync_check_product_seo($product);
    $score = 0;
    
    // Title (15 points)
    if ($checks['title']) {
        $title_len = strlen($product->get_name());
        if ($title_len >= 30 && $title_len <= 150) $score += 15;
        elseif ($title_len >= 10) $score += 10;
        else $score += 5;
    }
    
    // Description (15 points)
    if ($checks['description']) {
        $desc_len = strlen(wp_strip_all_tags($product->get_description()));
        if ($desc_len >= 100) $score += 15;
        elseif ($desc_len >= 50) $score += 10;
        else $score += 5;
    }
    
    // Image (15 points)
    if ($checks['image']) $score += 15;
    
    // Alt text (10 points)
    if ($checks['alt_text']) $score += 10;
    
    // Price (10 points)
    if ($checks['price']) $score += 10;
    
    // Brand (10 points)
    if ($checks['brand']) $score += 10;
    
    // Taxonomy (10 points)
    if ($checks['taxonomy']) $score += 10;
    
    // Schema (5 points)
    if ($checks['schema']) $score += 5;
    
    return $score;
}

function woosync_check_product_seo($product) {
    return [
        'title' => strlen($product->get_name()) >= 10,
        'description' => strlen(wp_strip_all_tags($product->get_description())) >= 20,
        'image' => $product->get_image_id() > 0,
        'alt_text' => !empty(woosync_get_product_image_alt($product)),
        'price' => $product->get_price() !== '',
        'brand' => !empty(woosync_get_product_brand($product)),
        'taxonomy' => !empty(woosync_get_google_taxonomy($product)),
        'schema' => woosync_product_has_schema($product)
    ];
}

function woosync_get_image_size_info($product) {
    $image_id = $product->get_image_id();
    if (!$image_id) return 'No image';
    
    $file = get_attached_file($image_id);
    if ($file && file_exists($file)) {
        $size = filesize($file);
        if ($size > 1024 * 1024) return round($size / (1024 * 1024), 1) . ' MB';
        return round($size / 1024) . ' KB';
    }
    return 'Unknown size';
}

function woosync_get_product_image_alt($product) {
    $image_id = $product->get_image_id();
    if (!$image_id) return '';
    return get_post_meta($image_id, '_wp_attachment_image_alt', true);
}

function woosync_get_product_brand($product) {
    $brand = $product->get_attribute('brand');
    if ($brand) return $brand;
    
    $brands = wp_get_post_terms($product->get_id(), 'product_brand');
    if (!empty($brands)) return $brands[0]->name;
    
    return '';
}

function woosync_get_google_taxonomy($product) {
    return $product->get_meta('_google_product_category');
}

function woosync_product_has_schema($product) {
    return !empty($product->get_meta('_woosync_schema_markup'));
}

function woosync_get_product_primary_category($product) {
    $terms = wp_get_post_terms($product->get_id(), 'product_cat');
    if (!empty($terms)) return $terms[0]->name;
    return 'Uncategorized';
}

// Quick fix AJAX handler
add_action('wp_ajax_woosync_apply_quick_fix', 'woosync_ajax_apply_quick_fix');
function woosync_ajax_apply_quick_fix() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $product_id = intval($_POST['product_id']);
    $fix_type = sanitize_text_field($_POST['fix_type']);
    
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Product not found');
    }
    
    $vendor = woosync_get_active_vendor();
    
    switch ($fix_type) {
        case 'optimize_title':
            $title = $product->get_name();
            if (strlen($title) > 150) {
                $product->set_name(substr($title, 0, 147) . '...');
                $product->save();
            }
            break;
            
        case 'generate_description':
            $description = woosync_generate_product_description($product, $vendor);
            if ($description) {
                $product->set_description($description);
                $product->save();
            }
            break;
            
        case 'add_alt_text':
            $alt = woosync_generate_alt_text($product);
            $image_id = $product->get_image_id();
            if ($image_id && $alt) {
                update_post_meta($image_id, '_wp_attachment_image_alt', $alt);
            }
            break;
            
        case 'set_brand':
            $brand = woosync_get_vendor_brand_for_product($product, $vendor);
            if ($brand) {
                woosync_set_product_attribute($product, 'brand', $brand);
                $product->save();
            }
            break;
            
        case 'map_taxonomy':
            $taxonomy = woosync_suggest_google_taxonomy($product);
            if ($taxonomy) {
                $product->update_meta_data('_google_product_category', $taxonomy);
                $product->save();
            }
            break;
    }
    
    $new_score = woosync_calculate_product_score($product);
    
    wp_send_json_success([
        'message' => 'Quick fix applied',
        'new_score' => $new_score
    ]);
}

function woosync_generate_product_description($product, $vendor) {
    $name = $product->get_name();
    $sku = $product->get_sku();
    $price = $product->get_price();
    
    $description = '<p><strong>' . esc_html($name) . '</strong>';
    if ($sku) $description .= ' (SKU: ' . esc_html($sku) . ')';
    $description .= '</p>';
    $description .= '<p>Premium quality product available at ';
    if ($price) $description .= 'R' . number_format(floatval($price), 2);
    $description .= '. Order now for fast delivery.</p>';
    $description .= '<p>Features include high-quality materials and excellent craftsmanship.</p>';
    
    return $description;
}

function woosync_generate_alt_text($product) {
    $name = $product->get_name();
    $sku = $product->get_sku();
    return $name . ' - Product Image' . ($sku ? ' | SKU: ' . $sku : '');
}

function woosync_get_vendor_brand_for_product($product, $vendor) {
    $source_product_code = $product->get_meta('_source_product_code');
    if ($source_product_code && $vendor) {
        // Could fetch from API if needed
    }
    
    return 'Generic Brand';
}

function woosync_suggest_google_taxonomy($product) {
    $categories = wp_get_post_terms($product->get_id(), 'product_cat');
    
    $taxonomy_map = [
        'clothing' => 1663,
        'shirt' => 1663,
        't-shirt' => 1663,
        'pants' => 207,
        'shoes' => 187,
        'footwear' => 187,
        'electronics' => 267,
        'accessories' => 1664,
        'gift' => 611,
        'home' => 20624,
        'kitchen' => 6918,
        'sports' => 248,
        'toys' => 4726
    ];
    
    foreach ($categories as $cat) {
        $cat_name = strtolower($cat->name);
        foreach ($taxonomy_map as $keyword => $tax_id) {
            if (strpos($cat_name, $keyword) !== false) {
                return $tax_id;
            }
        }
    }
    
    return 1663;
}

// Batch action AJAX handler
add_action('wp_ajax_woosync_batch_action', 'woosync_ajax_batch_action');
function woosync_ajax_batch_action() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
    $batch_action = sanitize_text_field($_POST['batch_action']);
    $fix_types = isset($_POST['fix_types']) ? $_POST['fix_types'] : [];
    
    if (empty($product_ids)) {
        $products = wc_get_products([
            'limit' => -1,
            'status' => ['publish', 'private']
        ]);
        $product_ids = array_map(function($p) { return $p->get_id(); }, $products);
    }
    
    $updated = 0;
    $vendor = woosync_get_active_vendor();
    
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) continue;
        
        switch ($batch_action) {
            case 'batch_generate_descriptions':
                $desc = woosync_generate_product_description($product, $vendor);
                if ($desc) {
                    $product->set_description($desc);
                    $product->save();
                    $updated++;
                }
                break;
                
            case 'batch_optimize_titles':
                $title = $product->get_name();
                if (strlen($title) > 150) {
                    $product->set_name(substr($title, 0, 147) . '...');
                    $product->save();
                    $updated++;
                }
                break;
                
            case 'batch_add_alt_text':
                $alt = woosync_generate_alt_text($product);
                $image_id = $product->get_image_id();
                if ($image_id && $alt && !get_post_meta($image_id, '_wp_attachment_image_alt', true)) {
                    update_post_meta($image_id, '_wp_attachment_image_alt', $alt);
                    $updated++;
                }
                break;
                
            case 'batch_set_brands':
                $brand = woosync_get_vendor_brand_for_product($product, $vendor);
                if ($brand && !$product->get_attribute('brand')) {
                    woosync_set_product_attribute($product, 'brand', $brand);
                    $product->save();
                    $updated++;
                }
                break;
                
            case 'batch_map_taxonomy':
                $taxonomy = woosync_suggest_google_taxonomy($product);
                if ($taxonomy && !$product->get_meta('_google_product_category')) {
                    $product->update_meta_data('_google_product_category', $taxonomy);
                    $product->save();
                    $updated++;
                }
                break;
                
            case 'batch_apply_fixes':
                foreach ($fix_types as $fix_type) {
                    $_POST['product_id'] = $product_id;
                    $_POST['fix_type'] = $fix_type;
                    ob_start();
                    woosync_ajax_apply_quick_fix();
                    ob_end_clean();
                    $updated++;
                }
                break;
        }
    }
    
    wp_send_json_success([
        'message' => 'Batch action completed',
        'updated' => $updated
    ]);
}

// Schema generator AJAX handler
add_action('wp_ajax_woosync_generate_schema', 'woosync_ajax_generate_schema');
function woosync_ajax_generate_schema() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error('Product not found');
    }
    
    $schema = woosync_generate_product_schema($product);
    
    $product->update_meta_data('_woosync_schema_markup', $schema);
    $product->save();
    
    wp_send_json_success([
        'product_name' => $product->get_name(),
        'schema' => $schema
    ]);
}

function woosync_generate_product_schema($product) {
    $price = $product->get_price();
    $price_val = $price ? floatval($price) : 0;
    $currency = get_woocommerce_currency();
    
    $availability = 'OutOfStock';
    if ($product->is_in_stock()) {
        $availability = $product->get_stock_status() === 'onbackorder' ? 'PreOrder' : 'InStock';
    }
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->get_name(),
        'sku' => $product->get_sku(),
        'description' => wp_strip_all_tags($product->get_description()),
        'image' => [],
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => $currency,
            'price' => number_format($price_val, 2, '.', ''),
            'availability' => 'https://schema.org/' . $availability,
            'seller' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name')
            ]
        ]
    ];
    
    $image_id = $product->get_image_id();
    if ($image_id) {
        $image_url = wp_get_attachment_url($image_id);
        if ($image_url) {
            $schema['image'] = $image_url;
        }
    }
    
    $brand = woosync_get_product_brand($product);
    if ($brand) {
        $schema['brand'] = [
            '@type' => 'Brand',
            'name' => $brand
        ];
    }
    
    $gtin = $product->get_meta('_gtin');
    if ($gtin) {
        $schema['gtin'] = $gtin;
    }
    
    $mpn = $product->get_meta('_mpn');
    if ($mpn) {
        $schema['mpn'] = $mpn;
    }
    
    $rating = $product->get_average_rating();
    if ($rating > 0) {
        $review_count = $product->get_review_count();
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format($rating, 1),
            'reviewCount' => $review_count
        ];
    }
    
    return $schema;
}

function woosync_ajax_test_connection() {
    check_ajax_referer('woosync_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    
    $vendor_id = sanitize_text_field($_POST['vendor_id'] ?? '');
    $vendor = $vendor_id ? woosync_get_vendor_by_id($vendor_id) : woosync_get_active_vendor();
    
    if (!$vendor) {
        wp_send_json_error('Vendor not found');
    }
    
    $token = woosync_get_token($vendor);
    if ($token) {
        wp_send_json_success(['message' => 'Connection successful']);
    } else {
        wp_send_json_error('Connection failed');
    }
}

// ===== CRON SCHEDULED SYNC =====
add_action('admin_init', function() {
    if (isset($_GET['action']) && $_GET['action'] === 'woosync_scheduled_sync') {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $vendor = woosync_get_active_vendor();
        if ($vendor) {
            woosync_process_sync($vendor, $vendor['sync_mode'] ?? 'full');
        }
        
        wp_safe_redirect(admin_url('admin.php?page=woosync'));
        exit;
    }
});

// ===== UNINSTALL =====
function woosync_uninstall() {
    $options = [
        'woosync_vendors', 'woosync_active_vendor', 'woosync_batch_size', 
        'woosync_sync_schedule', 'woosync_log_retain_days', 'woosync_wizard_completed',
        'woosync_sync_log', 'woosync_last_sync', 'woosync_total_products',
        'woosync_auto_update', 'woosync_update_history', 'woosync_last_update_check',
        'woosync_last_update_time', 'woosync_update_backup_enabled'
    ];
    foreach ($options as $opt) delete_option($opt);
    
    // Clear transients
    delete_transient('woosync_activated');
    delete_transient('woosync_update_check');
    delete_transient('woosync_update_available');
    delete_transient('woosync_update_dismissed');
    
    // Clear scheduled update check
    if (class_exists('WooSync_Updater')) {
        WooSync_Updater::clear_scheduled_check();
    }
}
register_uninstall_hook(__FILE__, 'woosync_uninstall');


// ===== DEACTIVATION =====
function woosync_deactivate() {
    if (class_exists('WooSync_Updater')) {
        WooSync_Updater::clear_scheduled_check();
    }
}
register_deactivation_hook(__FILE__, 'woosync_deactivate');


// ===== UPDATES TAB =====
function woosync_tab_updates() {
    $updater = new WooSync_Updater();
    $update_info = $updater->get_update_info();
    $auto_update = $updater->get_auto_update_setting();
    $update_history = $updater->get_update_history();
    $last_check = get_option('woosync_last_update_check', 'Never');
    $last_update = get_option('woosync_last_update_time', 'Never');
    $backup_enabled = get_option('woosync_update_backup_enabled', true);
    
    // Calculate if update is available
    $update_available = !empty($update_info);
    $current_version = WOOSYNC_VERSION;
    $latest_version = $update_info['version'] ?? $current_version;
    ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">🔔 Updates</h5>
        </div>
        <div class="card-body">
            <?php if ($update_available): ?>
                <div class="alert alert-success d-flex align-items-center gap-2">
                    <span class="dashicons dashicons-update" style="font-size: 24px;"></span>
                    <div>
                        <strong>WooSync v<?php echo esc_html($latest_version); ?> is available!</strong>
                        <?php if (!empty($update_info['name']) && $update_info['name'] !== $update_info['version']): ?>
                            <br><?php echo esc_html($update_info['name']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info d-flex align-items-center gap-2">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 24px;"></span>
                    <div>
                        <strong>You're running the latest version!</strong>
                        <br>WooSync v<?php echo esc_html($current_version); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <h6>📋 Version Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Current Version:</strong></td>
                            <td><code><?php echo esc_html($current_version); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Latest Version:</strong></td>
                            <td><code><?php echo esc_html($latest_version); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Last Checked:</strong></td>
                            <td><?php echo esc_html($last_check); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Updated:</strong></td>
                            <td><?php echo esc_html($last_update); ?></td>
                        </tr>
                    </table>
                    
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-primary" id="woosync-check-updates">
                            🔄 Check for Updates
                        </button>
                        <?php if ($update_available): ?>
                            <a href="<?php echo esc_url($update_info['url'] ?? 'https://github.com/preneshnaidoo/amrod-sync/releases'); ?>" 
                               target="_blank" class="btn btn-outline-secondary">
                                📝 View Release Notes
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6>⚙️ Automatic Updates</h6>
                    <form method="post" action="options.php">
                        <?php settings_fields('woosync_group'); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Auto-Update Preference:</label>
                            <select name="woosync_auto_update" class="form-select">
                                <option value="off" <?php selected($auto_update, 'off'); ?>>Off (Manual updates only)</option>
                                <option value="minor" <?php selected($auto_update, 'minor'); ?>>Minor updates only (security & bug fixes)</option>
                                <option value="all" <?php selected($auto_update, 'all'); ?>>All updates (including major)</option>
                            </select>
                            <small class="text-muted">
                                Automatic updates install silently in the background, like WordPress core plugins.
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <input type="checkbox" name="woosync_update_backup_enabled" value="1" 
                                       <?php checked($backup_enabled, true); ?>> 
                                Create backup before updating
                            </label>
                            <small class="text-muted d-block">
                                Backups are stored in wp-content/backups/woosync/
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-success">💾 Save Settings</button>
                    </form>
                </div>
            </div>
            
            <?php if ($update_available && !empty($update_info['body'])): ?>
            <div class="mt-4">
                <h6>📝 Release Notes</h6>
                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                    <?php echo wp_kses_post(wpautop($update_info['body'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($update_history)): ?>
            <div class="mt-4">
                <h6>📜 Update History</h6>
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Event</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($update_history) as $event): ?>
                        <tr>
                            <td><small><?php echo esc_html($event['time']); ?></small></td>
                            <td><span class="badge bg-<?php echo $event['event'] === 'success' ? 'success' : ($event['event'] === 'rollback_success' ? 'warning' : 'secondary'); ?>">
                                <?php echo esc_html($event['event']); ?>
                            </span></td>
                            <td><small><?php echo esc_html($event['message']); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $("#woosync-check-updates").on("click", function() {
            var $btn = $(this);
            $btn.prop("disabled", true).text("Checking...");
            
            $.ajax({
                url: woosyncData.ajaxUrl,
                type: "POST",
                data: {
                    action: "woosync_check_updates",
                    nonce: woosyncData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.update_available) {
                            alert("Update available: v" + response.data.version);
                            location.reload();
                        } else {
                            alert("You're running the latest version!");
                            $btn.prop("disabled", false).text("🔄 Check for Updates");
                        }
                    } else {
                        alert("Error checking for updates");
                        $btn.prop("disabled", false).text("🔄 Check for Updates");
                    }
                },
                error: function() {
                    alert("Error checking for updates");
                    $btn.prop("disabled", false).text("🔄 Check for Updates");
                }
            });
        });
    });
    </script>
    <?php
}



// ===== PRODUCT OPTIMIZER TAB =====
function woosync_tab_optimizer() {
    $products = wc_get_products([
        'limit' => 1,
        'status' => ['publish', 'private']
    ]);
    $total_products = wp_count_posts('product');
    $total_count = $total_products ? $total_products->publish + $total_products->hold : 0;
    ?>
    
    <div id="productOptimizerTab" class="woosync-optimizer-container">
        <!-- Header -->
        <div class="woosync-brand-header">
            <div>
                <h1>🚀 Product Optimizer</h1>
                <p class="text-muted mb-0">Optimize your products for Google Shopping, Facebook/Meta Shop, and general SEO</p>
            </div>
            <div class="text-end">
                <button type="button" class="btn btn-primary" id="runProductScan">
                    🔍 Run Full Scan
                </button>
            </div>
        </div>
        
        <!-- Alerts Container -->
        <div id="optimizerAlerts"></div>
        
        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="woosync-score-card">
                    <div class="score-value text-success" id="avgQualityScore">0%</div>
                    <div class="score-label">Average Quality Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="woosync-score-card">
                    <div class="score-value" id="productsScanned">0</div>
                    <div class="score-label">Products Scanned</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="woosync-score-card">
                    <div class="score-value text-warning" id="productsNeedingAttention">0</div>
                    <div class="score-label">Needing Attention</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="woosync-score-card">
                    <div class="score-value text-success" id="productsReadyForShopping">0</div>
                    <div class="score-label">Shopping Ready</div>
                </div>
            </div>
        </div>
        
        <!-- Score Summary (Hidden by default) -->
        <div id="scoreSummary" style="display: none;">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">📊 Quality Score by Category</h5>
                </div>
                <div class="card-body">
                    <div class="row" id="categoryBreakdown"></div>
                </div>
            </div>
        </div>
        
        <!-- Batch Actions -->
        <div class="woosync-batch-actions">
            <h5>⚡ Quick Batch Actions</h5>
            <p class="text-muted small mb-3">Apply fixes to selected products or all products</p>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <button type="button" class="batch-action-btn w-100" id="batchGenerateDescriptions" data-action="batch_generate_descriptions">
                        ✨ Generate Descriptions
                    </button>
                </div>
                <div class="col-md-4 mb-3">
                    <button type="button" class="batch-action-btn w-100" id="batchOptimizeTitles" data-action="batch_optimize_titles">
                        ✂️ Optimize Titles (150 char max)
                    </button>
                </div>
                <div class="col-md-4 mb-3">
                    <button type="button" class="batch-action-btn w-100" id="batchAddAltText" data-action="batch_add_alt_text">
                        🏷️ Add Alt Text to Images
                    </button>
                </div>
                <div class="col-md-4 mb-3">
                    <button type="button" class="batch-action-btn w-100" id="batchSetBrands" data-action="batch_set_brands">
                        🏷️ Set Brand Attribute
                    </button>
                </div>
                <div class="col-md-4 mb-3">
                    <button type="button" class="batch-action-btn w-100" id="batchMapTaxonomy" data-action="batch_map_taxonomy">
                        📁 Map Google Taxonomy
                    </button>
                </div>
                <div class="col-md-4 mb-3">
                    <button type="button" class="woosync-export-btn" id="exportQualityReport">
                        📥 Export CSV Report
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Scan Results Table -->
        <div class="card" id="scanResultsTable" style="display: none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">📋 Scan Results</h5>
                <div>
                    <label class="me-2">
                        <input type="checkbox" id="selectAllProducts"> Select All
                    </label>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Product</th>
                                <th style="width: 80px;">Score</th>
                                <th style="width: 300px;">Checks</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="scanResultsBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Plugin Recommendations -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">🔌 Recommended Plugins</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="woosync-plugin-card">
                            <div class="plugin-icon">📊</div>
                            <h6>Yoast SEO <span class="woosync-suggested-badge">⭐ Suggested by WooSync</span></h6>
                            <p>Complete SEO solution with schema markup, meta tags, and content analysis for WooCommerce products.</p>
                            <a href="https://wordpress.org/plugins/wordpress-seo/" target="_blank" class="plugin-link">
                                View on WordPress.org →
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="woosync-plugin-card">
                            <div class="plugin-icon">🛒</div>
                            <h6>WooCommerce Google Product Feed <span class="woosync-suggested-badge">⭐ Suggested by WooSync</span></h6>
                            <p>Generate and submit product feeds to Google Merchant Center for Shopping ads.</p>
                            <a href="https://wordpress.org/plugins/woo-gutenberg-products-feed/" target="_blank" class="plugin-link">
                                View on WordPress.org →
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="woosync-plugin-card">
                            <div class="plugin-icon">📘</div>
                            <h6>Facebook for WooCommerce <span class="woosync-suggested-badge">⭐ Suggested by WooSync</span></h6>
                            <p>Sync your WooCommerce products with Facebook Catalog for dynamic ads and Shop.</p>
                            <a href="https://wordpress.org/plugins/facebook-for-woocommerce/" target="_blank" class="plugin-link">
                                View on WordPress.org →
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="woosync-plugin-card">
                            <div class="plugin-icon">⭐</div>
                            <h6>Social Rocket <span class="woosync-suggested-badge">⭐ Suggested by WooSync</span></h6>
                            <p>Collect and display reviews with social proof to boost conversions and trust.</p>
                            <a href="https://wordpress.org/plugins/social-rocket/" target="_blank" class="plugin-link">
                                View on WordPress.org →
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="woosync-plugin-card">
                            <div class="plugin-icon">📸</div>
                            <h6>Instagram Shopping <span class="woosync-suggested-badge">⭐ Suggested by WooSync</span></h6>
                            <p>Tag products in Instagram posts and stories for seamless shopping experience.</p>
                            <a href="https://help.instagram.com/288026558325244" target="_blank" class="plugin-link">
                                Instagram Guide →
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="woosync-plugin-card">
                            <div class="plugin-icon">🔍</div>
                            <h6>Rank Math SEO <span class="woosync-suggested-badge">⭐ Suggested by WooSync</span></h6>
                            <p>Modern SEO plugin with AI-powered content optimization and advanced schema.</p>
                            <a href="https://wordpress.org/plugins/seo-by-rank-math/" target="_blank" class="plugin-link">
                                View on WordPress.org →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tutorial Links -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">📚 Setup Tutorials</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="https://support.google.com/merchants/answer/6364310" target="_blank" class="woosync-tutorial-card text-decoration-none">
                            <div class="tutorial-icon google">🔵</div>
                            <div class="tutorial-content">
                                <div class="tutorial-title">Google Merchant Center Setup Guide</div>
                                <div class="tutorial-platform">Google • Official Documentation</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="https://woocommerce.com/documentation/posts/google-product-feed/" target="_blank" class="woosync-tutorial-card text-decoration-none">
                            <div class="tutorial-icon google">🛒</div>
                            <div class="tutorial-content">
                                <div class="tutorial-title">WooCommerce Google Shopping Feed Setup</div>
                                <div class="tutorial-platform">Google • WooCommerce Guide</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="https://www.facebook.com/business/news/setting-up-facebook-shop" target="_blank" class="woosync-tutorial-card text-decoration-none">
                            <div class="tutorial-icon facebook">📘</div>
                            <div class="tutorial-content">
                                <div class="tutorial-title">Facebook Shop Setup Guide</div>
                                <div class="tutorial-platform">Facebook • Meta Commerce</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="https://help.instagram.com/402025958105621" target="_blank" class="woosync-tutorial-card text-decoration-none">
                            <div class="tutorial-icon instagram">📸</div>
                            <div class="tutorial-content">
                                <div class="tutorial-title">Instagram Shopping Setup</div>
                                <div class="tutorial-platform">Instagram • Meta Commerce</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="https://yoast.com/woocommerce-seo/" target="_blank" class="woosync-tutorial-card text-decoration-none">
                            <div class="tutorial-icon youtube">📹</div>
                            <div class="tutorial-content">
                                <div class="tutorial-title">Yoast SEO for WooCommerce Tutorial</div>
                                <div class="tutorial-platform">Yoast • Official Guide</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="https://www.youtube.com/results?search_query=woocommerce+google+shopping+feed+setup" target="_blank" class="woosync-tutorial-card text-decoration-none">
                            <div class="tutorial-icon youtube">▶️</div>
                            <div class="tutorial-content">
                                <div class="tutorial-title">Video: WooCommerce Google Feed Setup</div>
                                <div class="tutorial-platform">YouTube • Video Tutorials</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- External Resources -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">🔗 External Resources</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="https://merchants.google.com" target="_blank" class="woosync-resource-link">
                            <span class="resource-icon">🔵</span>
                            <span>Google Merchant Center</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="https://www.facebook.com/commerce" target="_blank" class="woosync-resource-link">
                            <span class="resource-icon">📘</span>
                            <span>Facebook Commerce Manager</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="https://www.google.com/basepages/producttype/taxonomy with GTIN" target="_blank" class="woosync-resource-link">
                            <span class="resource-icon">📁</span>
                            <span>Google Product Taxonomy ID Lookup</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="https://schema.org/Product" target="_blank" class="woosync-resource-link">
                            <span class="resource-icon">📋</span>
                            <span>Schema.org Product Documentation</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Details Modal -->
    <div class="modal fade" id="productDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 id="detailProductName">Product Name</h4>
                            <small class="text-muted" id="detailProductSku">SKU: ---</small>
                        </div>
                        <div class="text-end">
                            <div class="h2 mb-0" id="detailScore">0%</div>
                            <small class="text-muted">Quality Score</small>
                        </div>
                    </div>
                    
                    <h6>SEO Checks</h6>
                    <div id="productCheckDetails"></div>
                    
                    <h6 class="mt-4">Quick Fixes</h6>
                    <div id="productQuickFixes"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Schema Modal -->
    <div class="modal fade" id="schemaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📋 JSON-LD Product Schema</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Product:</strong> <span id="schemaProductName"></span></p>
                    <p class="text-muted small">This schema has been saved to the product meta. Add this to your theme or use an SEO plugin to output it.</p>
                    <pre class="woosync-schema-output" id="schemaOutput"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="copySchemaBtn">📋 Copy to Clipboard</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Copy schema button
        $('#copySchemaBtn').on('click', function() {
            var schema = $('#schemaOutput').text();
            navigator.clipboard.writeText(schema).then(function() {
                alert('Schema copied to clipboard!');
            });
        });
    });
    </script>
    <?php
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
