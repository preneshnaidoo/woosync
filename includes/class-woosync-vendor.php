<?php
/**
 * WooSync Vendor Manager
 *
 * Handles vendor configuration loading and management.
 * Vendors are loaded from files in includes/vendors/ directory.
 * Each vendor file defines its own endpoints, credential schema, and tier data.
 *
 * @package WooSync
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// VENDOR REGISTRY
// ============================================================================

/**
 * Get all registered vendor IDs.
 *
 * Scans the vendors directory for vendor data files.
 *
 * @since 1.0.0
 * @return array Array of vendor IDs
 */
function woosync_get_registered_vendors() {
    static $vendors = null;
    
    if ( $vendors === null ) {
        $vendors = array();
        $vendors_dir = WOOSYNC_PATH . 'includes/vendors/';
        
        if ( is_dir( $vendors_dir ) ) {
            $files = glob( $vendors_dir . 'class-woosync-*.php' );
            foreach ( $files as $file ) {
                $basename = basename( $file, '.php' );
                if ( preg_match( '/^class-woosync-([a-z0-9_-]+)$/', $basename, $matches ) ) {
                    $vendor_id = $matches[1];
                    // Skip the base class if it exists
                    if ( $vendor_id !== 'base' && $vendor_id !== 'interface' ) {
                        $vendors[] = $vendor_id;
                    }
                }
            }
        }
        
        /**
         * Filter the list of registered vendors.
         *
         * @since 1.0.0
         * @param array $vendors Array of vendor IDs
         */
        $vendors = apply_filters( 'woosync_registered_vendors', $vendors );
    }
    
    return $vendors;
}

/**
 * Load a vendor's data file.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @return bool True if loaded successfully
 */
function woosync_load_vendor( $vendor_id ) {
    static $loaded = array();
    
    if ( isset( $loaded[ $vendor_id ] ) ) {
        return $loaded[ $vendor_id ];
    }
    
    $file = WOOSYNC_PATH . 'includes/vendors/class-woosync-' . $vendor_id . '.php';
    
    if ( file_exists( $file ) ) {
        require_once $file;
        $loaded[ $vendor_id ] = true;
        return true;
    }
    
    return false;
}

/**
 * Get vendor definition.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @return array|null Vendor definition or null if not found
 */
function woosync_get_vendor_definition( $vendor_id ) {
    woosync_load_vendor( $vendor_id );
    
    $function = 'woosync_' . $vendor_id . '_get_vendor_definition';
    if ( function_exists( $function ) ) {
        return $function();
    }
    
    return null;
}

/**
 * Get vendor credential schema.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @return array Credential schema
 */
function woosync_get_vendor_credential_schema( $vendor_id ) {
    woosync_load_vendor( $vendor_id );
    
    $function = 'woosync_' . $vendor_id . '_get_credential_schema';
    if ( function_exists( $function ) ) {
        return $function();
    }
    
    // Return generic schema as fallback
    return array(
        'auth_type'   => 'custom',
        'label'       => 'Custom Vendor',
        'description' => 'Custom API configuration',
        'fields'      => array(
            array(
                'key'         => 'api_base_url',
                'label'       => 'API Base URL *',
                'placeholder' => 'https://api.example.com/',
                'required'    => true,
                'type'        => 'url',
                'help'        => 'Base URL for the vendor API',
            ),
        ),
        'support' => array(
            'email' => '',
            'docs'  => '',
            'note'  => 'Contact your vendor for API credentials.',
        ),
        'test_type' => 'custom',
    );
}

/**
 * Get vendor default endpoints.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @return array Endpoint definitions
 */
function woosync_get_vendor_default_endpoints( $vendor_id ) {
    woosync_load_vendor( $vendor_id );
    
    $function = 'woosync_' . $vendor_id . '_get_default_endpoints';
    if ( function_exists( $function ) ) {
        return $function();
    }
    
    // Return generic endpoints as fallback
    return array(
        'products' => array('label' => 'Products', 'path' => '/api/v1/Products/', 'enabled' => 1),
        'prices'   => array('label' => 'Prices', 'path' => '/api/v1/Prices/', 'enabled' => 0),
        'stock'    => array('label' => 'Stock', 'path' => '/api/v1/Stock/', 'enabled' => 0),
    );
}

/**
 * Get vendor pricing tiers.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @return array Tier definitions
 */
function woosync_get_vendor_pricing_tiers( $vendor_id ) {
    woosync_load_vendor( $vendor_id );
    
    $function = 'woosync_' . $vendor_id . '_get_pricing_tiers';
    if ( function_exists( $function ) ) {
        return $function();
    }
    
    // Return generic tiers as fallback
    return array(
        'Standard' => array(
            'level'       => 0,
            'label'       => 'Standard',
            'description' => 'Base pricing',
            'color_class' => 'tier-standard',
            'icon'        => '📦',
        ),
    );
}

/**
 * Get tier level number from tier name.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @param string $tier_name Tier name
 * @return int Tier level
 */
function woosync_get_vendor_tier_level( $vendor_id, $tier_name ) {
    woosync_load_vendor( $vendor_id );
    
    $function = 'woosync_' . $vendor_id . '_get_tier_level';
    if ( function_exists( $function ) ) {
        return $function( $tier_name );
    }
    
    return 0;
}

/**
 * Get CSS color class for a tier name.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @param string $tier_name Tier name
 * @return string CSS class name
 */
function woosync_get_vendor_tier_color_class( $vendor_id, $tier_name ) {
    woosync_load_vendor( $vendor_id );
    
    $function = 'woosync_' . $vendor_id . '_get_tier_color_class';
    if ( function_exists( $function ) ) {
        return $function( $tier_name );
    }
    
    return 'tier-standard';
}

/**
 * Get emoji icon for a tier name.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @param string $tier_name Tier name
 * @return string Emoji icon
 */
function woosync_get_vendor_tier_icon( $vendor_id, $tier_name ) {
    woosync_load_vendor( $vendor_id );
    
    $function = 'woosync_' . $vendor_id . '_get_tier_icon';
    if ( function_exists( $function ) ) {
        return $function( $tier_name );
    }
    
    return '📦';
}

/**
 * Get vendor field mapping rules.
 *
 * @since 1.0.0
 * @param string $vendor_id Vendor identifier
 * @return array Field mapping rules
 */
function woosync_get_vendor_field_mapping_rules( $vendor_id ) {
    woosync_load_vendor( $vendor_id );
    
    $function = 'woosync_' . $vendor_id . '_get_field_mapping_rules';
    if ( function_exists( $function ) ) {
        return $function();
    }
    
    // Return generic rules as fallback
    return array(
        'sku'         => array('patterns' => array('sku', 'item_code', 'id')),
        'name'        => array('patterns' => array('name', 'title', 'description')),
        'description' => array('patterns' => array('description', 'long_description', 'details')),
        'price'       => array('patterns' => array('price', 'selling_price', 'cost')),
    );
}

/**
 * Get vendor templates for admin UI.
 *
 * Returns vendor definitions formatted for dropdown/selection UI.
 *
 * @since 1.0.0
 * @return array Vendor templates
 */
function woosync_get_vendor_templates() {
    $vendors = woosync_get_registered_vendors();
    $templates = array();
    
    foreach ( $vendors as $vendor_id ) {
        $definition = woosync_get_vendor_definition( $vendor_id );
        if ( $definition ) {
            $templates[ $vendor_id ] = $definition;
        }
    }
    
    return $templates;
}

/**
 * Get all vendor credential schemas.
 *
 * @since 1.0.0
 * @return array All credential schemas keyed by vendor ID
 */
function woosync_get_all_vendor_credential_schemas() {
    $vendors = woosync_get_registered_vendors();
    $schemas = array();
    
    foreach ( $vendors as $vendor_id ) {
        $schemas[ $vendor_id ] = woosync_get_vendor_credential_schema( $vendor_id );
    }
    
    // Add generic/custom option
    $schemas['custom'] = array(
        'auth_type'   => 'custom',
        'label'       => 'Custom Vendor',
        'description' => 'Configure any REST API with custom authentication.',
        'fields'      => array(
            array(
                'key'         => 'api_base_url',
                'label'       => 'API Base URL *',
                'placeholder' => 'https://api.example.com/v1/',
                'required'    => true,
                'type'        => 'url',
                'help'        => "Base URL for your vendor's API",
            ),
            array(
                'key'         => 'auth_url',
                'label'       => 'Auth URL (optional)',
                'placeholder' => 'https://auth.example.com/token',
                'required'    => false,
                'type'        => 'url',
                'help'        => 'Authentication endpoint URL (if separate from API)',
            ),
            array(
                'key'         => 'bearer_token',
                'label'       => 'Bearer Token (optional)',
                'placeholder' => 'Your bearer token',
                'required'    => false,
                'type'        => 'password',
                'help'        => 'Bearer token for API authentication',
            ),
            array(
                'key'         => 'api_key',
                'label'       => 'API Key (optional)',
                'placeholder' => 'Your API key',
                'required'    => false,
                'type'        => 'password',
                'help'        => 'Static API key (if required by your vendor)',
            ),
        ),
        'support' => array(
            'email' => '',
            'docs'  => '',
            'note'  => 'Fill in only the fields your API requires.',
        ),
        'test_type' => 'custom',
    );
    
    return $schemas;
}

// ============================================================================
// WOOSYNC_VENDOR CLASS
// ============================================================================

/**
 * WooSync_Vendor class.
 *
 * Manages vendor configuration for the active vendor.
 * This class provides a unified interface for accessing vendor-specific data.
 *
 * @package WooSync
 * @since 1.0.0
 */
class WooSync_Vendor {

    /**
     * @var string Current vendor ID
     */
    private $vendor_id;

    /**
     * @var string Option prefix
     */
    private $prefix;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string $vendor_id Vendor identifier
     * @param string $prefix    Option name prefix
     */
    public function __construct( $vendor_id = '', $prefix = 'woosync_' ) {
        $this->vendor_id = $vendor_id ?: $this->get_active_vendor_id();
        $this->prefix    = $prefix;
    }

    /**
     * Get the active vendor ID from settings.
     *
     * @since 1.0.0
     * @return string Vendor ID
     */
    public function get_active_vendor_id() {
        $vendors = get_option( 'woosync_vendors', array() );
        
        // Find the first connected vendor
        foreach ( $vendors as $vid => $data ) {
            if ( ! empty( $data['connected'] ) ) {
                return $vid;
            }
        }
        
        // Default to first registered vendor
        $registered = woosync_get_registered_vendors();
        return ! empty( $registered ) ? $registered[0] : '';
    }

    /**
     * Get current vendor ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_vendor_id() {
        return $this->vendor_id;
    }

    /**
     * Get option prefix.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_prefix() {
        return $this->prefix;
    }

    /**
     * Get vendor definition.
     *
     * @since 1.0.0
     * @return array|null
     */
    public function get_vendor_definition() {
        return woosync_get_vendor_definition( $this->vendor_id );
    }

    /**
     * Get credential schema for this vendor.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_credential_schema() {
        return woosync_get_vendor_credential_schema( $this->vendor_id );
    }

    /**
     * Get default endpoints for this vendor.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_default_endpoints() {
        return woosync_get_vendor_default_endpoints( $this->vendor_id );
    }

    /**
     * Get stored or default endpoints.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_endpoints() {
        $defaults = $this->get_default_endpoints();
        $stored   = get_option( $this->prefix . 'endpoints' );

        if ( $stored && is_string( $stored ) ) {
            $stored = @json_decode( $stored, true );
            if ( is_array( $stored ) ) {
                foreach ( $defaults as $key => $default ) {
                    if ( isset( $stored[ $key ] ) ) {
                        $stored[ $key ] = array_merge( $default, $stored[ $key ] );
                    } else {
                        $stored[ $key ] = $default;
                    }
                }
                return $stored;
            }
        }

        return $defaults;
    }

    /**
     * Save endpoint configuration.
     *
     * @since 1.0.0
     * @param array $endpoints
     * @return bool
     */
    public function save_endpoints( $endpoints ) {
        return update_option( $this->prefix . 'endpoints', json_encode( $endpoints ) );
    }

    /**
     * Get pricing tiers for this vendor.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_pricing_tiers() {
        return woosync_get_vendor_pricing_tiers( $this->vendor_id );
    }

    /**
     * Get tier level.
     *
     * @since 1.0.0
     * @param string $tier_name
     * @return int
     */
    public function get_tier_level( $tier_name ) {
        return woosync_get_vendor_tier_level( $this->vendor_id, $tier_name );
    }

    /**
     * Get tier color class.
     *
     * @since 1.0.0
     * @param string $tier_name
     * @return string
     */
    public function get_tier_color_class( $tier_name ) {
        return woosync_get_vendor_tier_color_class( $this->vendor_id, $tier_name );
    }

    /**
     * Get tier icon.
     *
     * @since 1.0.0
     * @param string $tier_name
     * @return string
     */
    public function get_tier_icon( $tier_name ) {
        return woosync_get_vendor_tier_icon( $this->vendor_id, $tier_name );
    }

    /**
     * Get field mapping rules.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_field_mapping_rules() {
        return woosync_get_vendor_field_mapping_rules( $this->vendor_id );
    }

    /**
     * Get vendor tier data from options.
     *
     * @since 1.0.0
     * @param string $vendor_id Vendor ID (optional, uses current if empty)
     * @return array Tier data
     */
    public function get_vendor_tier( $vendor_id = '' ) {
        $vendor_id = $vendor_id ?: $this->vendor_id;
        $tiers = get_option( 'woosync_vendor_tiers', array() );
        return isset( $tiers[ $vendor_id ] ) ? $tiers[ $vendor_id ] : array(
            'tier' => 'Standard',
            'tier_level' => 0,
            'tier_active_since' => '',
        );
    }

    /**
     * Save vendor tier data to options.
     *
     * @since 1.0.0
     * @param string $vendor_id Vendor ID
     * @param array  $tier_data Tier data
     * @return bool
     */
    public function save_vendor_tier( $vendor_id, $tier_data ) {
        $tiers = get_option( 'woosync_vendor_tiers', array() );
        $tiers[ $vendor_id ] = array_merge( $tiers[ $vendor_id ] ?? array(), $tier_data );
        return update_option( 'woosync_vendor_tiers', $tiers );
    }

    /**
     * Get vendor credentials from options.
     *
     * @since 1.0.0
     * @param string $vendor_id Vendor ID (optional, uses current if empty)
     * @return array Credentials
     */
    public function get_vendor_credentials( $vendor_id = '' ) {
        $vendor_id = $vendor_id ?: $this->vendor_id;
        $creds = get_option( 'woosync_vendor_credentials', array() );
        return isset( $creds[ $vendor_id ] ) ? $creds[ $vendor_id ] : array();
    }

    /**
     * Get a specific credential value.
     *
     * @since 1.0.0
     * @param string $key Credential key
     * @param mixed  $default Default value
     * @param string $vendor_id Vendor ID (optional)
     * @return mixed
     */
    public function get_credential( $key, $default = '', $vendor_id = '' ) {
        $creds = $this->get_vendor_credentials( $vendor_id );
        return isset( $creds[ $key ] ) ? $creds[ $key ] : $default;
    }

    /**
     * Get API base URL for this vendor.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_api_base_url() {
        $creds = $this->get_vendor_credentials();
        $definition = $this->get_vendor_definition();
        
        if ( ! empty( $creds['api_base_url'] ) ) {
            return $creds['api_base_url'];
        }
        
        return $definition['api_base_url'] ?? '';
    }

    /**
     * Get auth URL for this vendor.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_auth_url() {
        $creds = $this->get_vendor_credentials();
        $definition = $this->get_vendor_definition();
        
        if ( ! empty( $creds['auth_url'] ) ) {
            return $creds['auth_url'];
        }
        
        return $definition['auth_url'] ?? '';
    }

    /**
     * Get auth type for this vendor.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_auth_type() {
        $schema = $this->get_credential_schema();
        return $schema['auth_type'] ?? 'custom';
    }

    /**
     * Check if vendor is connected.
     *
     * @since 1.0.0
     * @param string $vendor_id Vendor ID (optional)
     * @return bool
     */
    public function is_vendor_connected( $vendor_id = '' ) {
        $vendor_id = $vendor_id ?: $this->vendor_id;
        $vendors = get_option( 'woosync_vendors', array() );
        return ! empty( $vendors[ $vendor_id ]['connected'] );
    }

    /**
     * Get simplified status for admin UI.
     *
     * @since 1.0.0
     * @return array Status data
     */
    public function get_simplified_status() {
        $connected = $this->is_vendor_connected();
        $mapping = get_option( 'woosync_field_mapping', array() );
        $has_mapping = ! empty( $mapping );
        $last_sync = get_option( 'woosync_last_sync', '' );
        
        return array(
            'connected'    => $connected,
            'has_mapping'  => $has_mapping,
            'last_sync'    => $last_sync,
            'vendor_id'    => $this->vendor_id,
        );
    }
}
