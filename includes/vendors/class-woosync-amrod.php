<?php
/**
 * WooSync Amrod Vendor Data
 *
 * This file contains ALL Amrod-specific configuration:
 * - Vendor ID, name, and display data
 * - API endpoints (default paths)
 * - Authentication credentials schema
 * - Pricing tier definitions
 *
 * Adding a new vendor (e.g., Barron) = new file in includes/vendors/
 * Nothing else in the plugin changes.
 *
 * @package WooSync
 * @subpackage Vendors
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// VENDOR DEFINITION
// ============================================================================

/**
 * Amrod vendor definition array.
 *
 * @since 1.0.0
 * @return array Vendor configuration
 */
function woosync_amrod_get_vendor_definition() {
    return array(
        'id'          => 'amrod',
        'name'        => 'Amrod',
        'icon'        => '🏭',
        'description' => 'Premium branded merchandise supplier with full API access for products, stock, pricing, and branding.',
        'auth_url'    => 'https://identity.amrod.co.za',
        'api_base_url'=> 'https://vendorapi.amrod.co.za',
        'docs_url'    => 'https://newapidocs.amrod.co.za',
        'support'     => array(
            'email'   => 'support@amrod.co.za',
            'docs_url'=> 'https://newapidocs.amrod.co.za',
            'phone'   => '',
            'address' => '',
        ),
    );
}

// ============================================================================
// VENDOR CREDENTIAL SCHEMA
// ============================================================================

/**
 * Get Amrod credential schema.
 *
 * @since 1.0.0
 * @return array Credential schema
 */
function woosync_amrod_get_credential_schema() {
    return array(
        'auth_type'   => 'vendor_login',
        'label'       => 'Amrod Vendor Login',
        'description' => "Uses Amrod's VendorLogin endpoint with username, password, and customer code.",
        'fields'      => array(
            array(
                'key'         => 'auth_url',
                'label'       => 'Auth URL',
                'placeholder' => 'https://identity.amrod.co.za',
                'prefill'     => 'https://identity.amrod.co.za',
                'required'    => true,
                'type'        => 'url',
                'help'        => 'Amrod identity/authentication endpoint URL',
            ),
            array(
                'key'         => 'username',
                'label'       => 'Username *',
                'placeholder' => 'user@email.com',
                'required'    => true,
                'type'        => 'text',
                'help'        => 'Your Amrod API username (same as login email)',
            ),
            array(
                'key'         => 'password',
                'label'       => 'Password *',
                'placeholder' => '••••••••',
                'required'    => true,
                'type'        => 'password',
                'help'        => 'Your Amrod API password',
            ),
            array(
                'key'         => 'customer_code',
                'label'       => 'Customer Code *',
                'placeholder' => 'e.g., MEDIAPLATFORM',
                'required'    => true,
                'type'        => 'text',
                'help'        => 'Your Amrod customer code. Find it in your Amrod account or contact support.',
            ),
        ),
        'support' => array(
            'email' => 'support@amrod.co.za',
            'docs'  => 'https://newapidocs.amrod.co.za',
            'note'  => 'Your customer code is in your Amrod account. Contact support@amrod.co.za if you don\'t have one.',
        ),
        'test_type' => 'vendor_login',
    );
}

// ============================================================================
// VENDOR API ENDPOINTS (DEFAULT PATHS)
// ============================================================================

/**
 * Get Amrod default API endpoint paths.
 *
 * @since 1.0.0
 * @return array Default endpoint definitions
 */
function woosync_amrod_get_default_endpoints() {
    return array(
        'products'                    => array('label' => 'Products', 'path' => '/api/v1/Products/', 'enabled' => 1),
        'products_updated'            => array('label' => 'Products (Updated)', 'path' => '/api/v1/Products/GetUpdatedProducts', 'enabled' => 0),
        'products_branding'           => array('label' => 'Products with Branding', 'path' => '/api/v1/Products/GetProductsAndBranding', 'enabled' => 0),
        'products_updated_branding'   => array('label' => 'Products Updated with Branding', 'path' => '/api/v1/Products/GetUpdatedProductsAndBranding', 'enabled' => 0),
        'stock'                      => array('label' => 'Stock', 'path' => '/api/v1/Stock/', 'enabled' => 0),
        'stock_updated'               => array('label' => 'Stock (Updated)', 'path' => '/api/v1/Stock/GetUpdated', 'enabled' => 0),
        'prices'                     => array('label' => 'Prices', 'path' => '/api/v1/Prices/', 'enabled' => 0),
        'prices_updated'             => array('label' => 'Prices (Updated)', 'path' => '/api/v1/Prices/GetUpdated', 'enabled' => 0),
        'categories'                 => array('label' => 'Categories', 'path' => '/api/v1/Categories/', 'enabled' => 0),
        'categories_updated'         => array('label' => 'Categories (Updated)', 'path' => '/api/v1/Categories/GetUpdated', 'enabled' => 0),
        'brands'                     => array('label' => 'Brands', 'path' => '/api/v1/Brands/', 'enabled' => 0),
        'brands_updated'             => array('label' => 'Brands (Updated)', 'path' => '/api/v1/Brands/GetUpdated', 'enabled' => 0),
        'branding_depts'             => array('label' => 'Branding Departments', 'path' => '/api/v1/BrandingDepartments/', 'enabled' => 0),
        'branding_depts_updated'     => array('label' => 'Branding Departments (Updated)', 'path' => '/api/v1/BrandingDepartments/GetUpdated', 'enabled' => 0),
        'inclusive_brandings'        => array('label' => 'Inclusive Brandings', 'path' => '/api/v1/InclusiveBrandings/', 'enabled' => 0),
        'inclusive_brandings_updated'=> array('label' => 'Inclusive Brandings (Updated)', 'path' => '/api/v1/InclusiveBrandings/GetUpdated', 'enabled' => 0),
        'branding_prices'            => array('label' => 'Branding Prices', 'path' => '/api/v1/BrandingPrices/', 'enabled' => 0),
        'branding_prices_updated'    => array('label' => 'Branding Prices (Updated)', 'path' => '/api/v1/BrandingPrices/GetUpdated', 'enabled' => 0),
        'colour_swatches'            => array('label' => 'Colour Swatches', 'path' => '/api/v1/ColourSwatches/', 'enabled' => 0),
        'colour_groups'              => array('label' => 'Colour Groups', 'path' => '/api/v1/ColourSwatches/GetGrouping', 'enabled' => 0),
    );
}

// ============================================================================
// PRICING TIERS
// ============================================================================

/**
 * Get Amrod pricing tier definitions.
 *
 * @since 1.0.0
 * @return array Tier definitions keyed by tier name
 */
function woosync_amrod_get_pricing_tiers() {
    return array(
        'Standard' => array(
            'level'       => 0,
            'label'       => 'Standard',
            'description' => 'Base pricing without volume discounts',
            'color_class' => 'tier-standard',
            'icon'        => '📦',
        ),
        'Bronze'   => array(
            'level'       => 1,
            'label'       => 'Bronze',
            'description' => '5-10% volume discount tier',
            'color_class' => 'tier-bronze',
            'icon'        => '🥉',
        ),
        'Silver'   => array(
            'level'       => 2,
            'label'       => 'Silver',
            'description' => '10-15% volume discount tier',
            'color_class' => 'tier-silver',
            'icon'        => '🥈',
        ),
        'Gold'     => array(
            'level'       => 3,
            'label'       => 'Gold',
            'description' => '15-20% volume discount tier',
            'color_class' => 'tier-gold',
            'icon'        => '🥇',
        ),
        'Platinum' => array(
            'level'       => 4,
            'label'       => 'Platinum',
            'description' => '20%+ volume discount tier',
            'color_class' => 'tier-platinum',
            'icon'        => '💎',
        ),
    );
}

/**
 * Get tier level number from tier name.
 *
 * @since 1.0.0
 * @param string $tier_name Tier name
 * @return int Tier level (0-4)
 */
function woosync_amrod_get_tier_level( $tier_name ) {
    $tiers = woosync_amrod_get_pricing_tiers();
    return isset( $tiers[ $tier_name ]['level'] ) ? $tiers[ $tier_name ]['level'] : 0;
}

/**
 * Get CSS color class for a tier name.
 *
 * @since 1.0.0
 * @param string $tier_name Tier name
 * @return string CSS class name
 */
function woosync_amrod_get_tier_color_class( $tier_name ) {
    $tiers = woosync_amrod_get_pricing_tiers();
    return isset( $tiers[ $tier_name ]['color_class'] ) ? $tiers[ $tier_name ]['color_class'] : 'tier-standard';
}

/**
 * Get emoji icon for a tier name.
 *
 * @since 1.0.0
 * @param string $tier_name Tier name
 * @return string Emoji icon
 */
function woosync_amrod_get_tier_icon( $tier_name ) {
    $tiers = woosync_amrod_get_pricing_tiers();
    return isset( $tiers[ $tier_name ]['icon'] ) ? $tiers[ $tier_name ]['icon'] : '📦';
}

// ============================================================================
// FIELD MAPPING DEFAULTS
// ============================================================================

/**
 * Get Amrod field mapping rules for auto-detection.
 *
 * These patterns are used to auto-map vendor API fields to WooCommerce fields.
 *
 * @since 1.0.0
 * @return array Field mapping rules
 */
function woosync_amrod_get_field_mapping_rules() {
    return array(
        'sku'           => array('patterns' => array('ProductCode', 'item_code', 'sku', 'ItemCode')),
        'name'          => array('patterns' => array('Description', 'ProductName', 'name', 'Name', 'Title')),
        'description'   => array('patterns' => array('LongDescription', 'description', 'Description', 'long_description')),
        'price'         => array('patterns' => array('Price', 'price', 'selling_price', 'SellingPrice')),
        'regular_price' => array('patterns' => array('ListPrice', 'list_price', 'regular_price', 'RegularPrice')),
        'stock_quantity'=> array('patterns' => array('StockOnHand', 'stock_on_hand', 'stock_quantity', 'Quantity')),
        'stock_status'  => array('patterns' => array('IsInStock', 'is_in_stock', 'in_stock', 'StockStatus')),
        'categories'    => array('patterns' => array('CategoryName', 'category_name', 'Category', 'category')),
        'images'        => array('patterns' => array('ImageURL', 'image_url', 'ImageUrl', 'image', 'Image')),
        'brand'         => array('patterns' => array('BrandName', 'brand_name', 'Brand', 'brand')),
        'color'         => array('patterns' => array('ColourName', 'colour_name', 'Color', 'colour', 'ColorName')),
        'size'          => array('patterns' => array('Size', 'size', 'SizeName')),
        'material'     => array('patterns' => array('Material', 'material', 'Composition')),
        'weight'        => array('patterns' => array('Weight', 'weight', 'WeightKg')),
        'dimensions'   => array('patterns' => array('Dimensions', 'dimensions', 'SizeDimensions')),
    );
}
