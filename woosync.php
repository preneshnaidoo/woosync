<?php
/**
 * Plugin Name: WooSync
 * Description: Enterprise-grade vendor sync for WooCommerce. Connect multiple suppliers, map fields, and sync products with tier-based pricing support.
 * Version: 1.0.0
 * Author: MediaPlatform
 * License: GPL-2.0+
 * Text Domain: woosync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// CONSTANTS
// ============================================================================

/**
 * Plugin version.
 *
 * @since 1.0.0
 */
define( 'WOOSYNC_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 *
 * @since 1.0.0
 */
define( 'WOOSYNC_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @since 1.0.0
 */
define( 'WOOSYNC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Assets directory URL.
 *
 * @since 1.0.0
 */
define( 'WOOSYNC_ASSETS', WOOSYNC_URL . 'assets/' );

// ============================================================================
// WOOCOMMERCE DEPENDENCY CHECK
// ============================================================================

/**
 * Check WooCommerce is active.
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'plugins_loaded', 'woosync_check_woocommerce' );
function woosync_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'woosync_woocommerce_missing_notice' );
        add_action( 'admin_init', 'woosync_deactivate_self' );
    }
}

/**
 * Display WooCommerce missing notice.
 *
 * @since 1.0.0
 * @return void
 */
function woosync_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>WooSync:</strong> WooCommerce must be active for this plugin to work.
            Please install and activate WooCommerce first.
        </p>
    </div>
    <?php
}

/**
 * Deactivate the plugin if WooCommerce is missing.
 *
 * @since 1.0.0
 * @return void
 */
function woosync_deactivate_self() {
    deactivate_plugins( plugin_basename( __FILE__ ) );
}

// ============================================================================
// REQUIRE CLASS FILES
// ============================================================================

/**
 * Load all plugin class files.
 *
 * @since 1.0.0
 * @return void
 */
function woosync_load_includes() {
    $includes_dir = WOOSYNC_PATH . 'includes/';

    require_once $includes_dir . 'class-woosync-vendor.php';
    require_once $includes_dir . 'class-woosync-api.php';
    require_once $includes_dir . 'class-woosync-sync.php';
    require_once $includes_dir . 'class-woosync-settings.php';
    require_once $includes_dir . 'class-woosync-admin.php';
    require_once $includes_dir . 'class-woosync-ajax.php';
}
add_action( 'plugins_loaded', 'woosync_load_includes', 5 );

// ============================================================================
// SETTINGS REGISTRATION
// ============================================================================

/**
 * Register plugin settings.
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'admin_init', 'woosync_register_settings' );
function woosync_register_settings() {
    // Register the vendors option that stores the list of active vendors
    register_setting(
        'woosync_vendors_group',
        'woosync_vendors',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'woosync_sanitize_vendors',
            'default'           => array(),
        )
    );

    // Other core settings
    register_setting( 'woosync_vendors_group', 'woosync_batch_size', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 200,
    ) );

    register_setting( 'woosync_vendors_group', 'woosync_sync_schedule', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'daily',
    ) );

    register_setting( 'woosync_vendors_group', 'woosync_markup_percent', array(
        'type'              => 'number',
        'sanitize_callback' => 'floatval',
        'default'           => 30,
    ) );

    register_setting( 'woosync_vendors_group', 'woosync_field_mapping', array(
        'type'              => 'array',
        'sanitize_callback' => 'woosync_sanitize_field_mapping',
        'default'           => array(),
    ) );

    register_setting( 'woosync_vendors_group', 'woosync_sync_log', array(
        'type' => 'array',
    ) );

    register_setting( 'woosync_vendors_group', 'woosync_vendor_credentials', array(
        'type' => 'array',
    ) );

    register_setting( 'woosync_vendors_group', 'woosync_vendor_tiers', array(
        'type' => 'array',
    ) );

    register_setting( 'woosync_vendors_group', 'woosync_endpoints', array(
        'type' => 'string',
    ) );
}

/**
 * Sanitize vendors option.
 *
 * @since 1.0.0
 * @param mixed $input
 * @return array
 */
function woosync_sanitize_vendors( $input ) {
    if ( ! is_array( $input ) ) {
        return array();
    }

    $sanitized = array();
    foreach ( $input as $vendor_id => $data ) {
        $sanitized[ sanitize_key( $vendor_id ) ] = array(
            'connected'    => ! empty( $data['connected'] ),
            'connected_at' => sanitize_text_field( $data['connected_at'] ?? '' ),
            'auth_type'    => sanitize_text_field( $data['auth_type'] ?? '' ),
        );
    }
    return $sanitized;
}

/**
 * Sanitize field mapping.
 *
 * @since 1.0.0
 * @param mixed $input
 * @return array
 */
function woosync_sanitize_field_mapping( $input ) {
    if ( ! is_array( $input ) ) {
        return array();
    }

    $sanitized = array();
    foreach ( $input as $key => $value ) {
        $sanitized[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
    }
    return $sanitized;
}

// ============================================================================
// ADMIN MENU REGISTRATION
// ============================================================================

/**
 * Register admin menu pages.
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'admin_menu', 'woosync_register_admin_menu' );
function woosync_register_admin_menu() {
    // Get instances for admin class
    $vendor = new WooSync_Vendor();
    $api = new WooSync_API( $vendor );
    $admin = new WooSync_Admin( $vendor, $api );

    $admin->register_menus();
}

// ============================================================================
// AJAX HOOK REGISTRATION
// ============================================================================

/**
 * Register AJAX hooks.
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'init', 'woosync_register_ajax_hooks' );
function woosync_register_ajax_hooks() {
    // Only register for logged-in users
    if ( ! is_user_logged_in() ) {
        return;
    }

    $vendor = new WooSync_Vendor();
    $api = new WooSync_API( $vendor );

    WooSync_Ajax::register_hooks( $vendor, $api );
}

// ============================================================================
// ASSET ENQUEUEING
// ============================================================================

/**
 * Enqueue admin assets.
 *
 * @since 1.0.0
 * @param string $hook Current admin page hook
 * @return void
 */
add_action( 'admin_enqueue_scripts', 'woosync_enqueue_assets' );
function woosync_enqueue_assets( $hook ) {
    // Only load on our plugin pages
    if ( strpos( $hook, 'woosync' ) === false ) {
        return;
    }

    // Bootstrap 5 CSS (local)
    wp_enqueue_style(
        'woosync-bootstrap5',
        WOOSYNC_ASSETS . 'css/bootstrap.min.css',
        array(),
        '5.3.3'
    );

    // Bootstrap 5 JS (local)
    wp_enqueue_script(
        'woosync-bootstrap5',
        WOOSYNC_ASSETS . 'js/bootstrap.min.js',
        array(),
        '5.3.3',
        true
    );

    // Chart.js (local)
    wp_enqueue_script(
        'woosync-chartjs',
        WOOSYNC_ASSETS . 'js/chart.min.js',
        array(),
        '4.4.1',
        true
    );

    // Plugin admin CSS
    wp_enqueue_style(
        'woosync-admin',
        WOOSYNC_ASSETS . 'css/admin.css',
        array( 'woosync-bootstrap5' ),
        WOOSYNC_VERSION
    );

    // Main plugin JS
    wp_enqueue_script(
        'woosync-admin',
        WOOSYNC_ASSETS . 'js/admin.js',
        array( 'jquery', 'woosync-bootstrap5', 'woosync-chartjs' ),
        WOOSYNC_VERSION,
        true
    );

    // Localize data for JavaScript
    $vendor_templates = array_values( woosync_get_vendor_templates() );
    $vendor_schemas = woosync_get_all_vendor_credential_schemas();

    wp_localize_script(
        'woosync-admin',
        'woosyncData',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'woosync_sync_nonce' ),
            'assetsUrl' => WOOSYNC_ASSETS,
            'isWooSyncPage' => true,
            'vendorTemplates' => $vendor_templates,
            'vendorCredentialSchemas' => $vendor_schemas,
            'strings' => array(
                'testing' => __( 'Testing connection...', 'woosync' ),
                'success' => __( 'Connection successful!', 'woosync' ),
                'error' => __( 'Connection failed', 'woosync' ),
                'saving' => __( 'Saving...', 'woosync' ),
                'saved' => __( 'Saved!', 'woosync' ),
            ),
        )
    );
}

// ============================================================================
// ACTIVATION / DEACTIVATION HOOKS
// ============================================================================

/**
 * Plugin activation.
 *
 * @since 1.0.0
 * @return void
 */
register_activation_hook( __FILE__, 'woosync_activate' );
function woosync_activate() {
    // Initialize vendors option
    if ( false === get_option( 'woosync_vendors' ) ) {
        update_option( 'woosync_vendors', array() );
    }

    // Initialize other options
    if ( false === get_option( 'woosync_batch_size' ) ) {
        update_option( 'woosync_batch_size', 200 );
    }

    if ( false === get_option( 'woosync_sync_schedule' ) ) {
        update_option( 'woosync_sync_schedule', 'daily' );
    }

    if ( false === get_option( 'woosync_markup_percent' ) ) {
        update_option( 'woosync_markup_percent', 30 );
    }

    if ( false === get_option( 'woosync_field_mapping' ) ) {
        update_option( 'woosync_field_mapping', array() );
    }

    if ( false === get_option( 'woosync_sync_log' ) ) {
        update_option( 'woosync_sync_log', array() );
    }

    if ( false === get_option( 'woosync_vendor_credentials' ) ) {
        update_option( 'woosync_vendor_credentials', array() );
    }

    if ( false === get_option( 'woosync_vendor_tiers' ) ) {
        update_option( 'woosync_vendor_tiers', array() );
    }

    // Clear any cached transients
    delete_transient( 'woosync_token' );

    woosync_sync_log( 'Plugin activated' );
}

/**
 * Plugin deactivation.
 *
 * @since 1.0.0
 * @return void
 */
register_deactivation_hook( __FILE__, 'woosync_deactivate' );
function woosync_deactivate() {
    // Clear cached tokens
    $vendors = woosync_get_registered_vendors();
    foreach ( $vendors as $vendor_id ) {
        delete_transient( 'woosync_' . $vendor_id . '_token' );
    }

    woosync_sync_log( 'Plugin deactivated' );
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get WooSync instances.
 *
 * Returns initialized instances for use in other plugins/themes.
 *
 * @since 1.0.0
 * @return array Array with 'vendor', 'api', 'sync' keys
 */
function woosync_get_instances() {
    static $instances = null;

    if ( $instances === null ) {
        $vendor = new WooSync_Vendor();
        $api = new WooSync_API( $vendor );
        $sync = new WooSync_Sync( $vendor, $api );

        $instances = array(
            'vendor' => $vendor,
            'api' => $api,
            'sync' => $sync,
        );
    }

    return $instances;
}

/**
 * Get the sync engine instance.
 *
 * @since 1.0.0
 * @return WooSync_Sync
 */
function woosync_get_sync() {
    $instances = woosync_get_instances();
    return $instances['sync'];
}

/**
 * Get the API instance.
 *
 * @since 1.0.0
 * @return WooSync_API
 */
function woosync_get_api() {
    $instances = woosync_get_instances();
    return $instances['api'];
}

/**
 * Get the vendor instance.
 *
 * @since 1.0.0
 * @return WooSync_Vendor
 */
function woosync_get_vendor() {
    $instances = woosync_get_instances();
    return $instances['vendor'];
}
