<?php
/**
 * WooSync Settings Class
 *
 * Handles settings API and field mapping configuration.
 *
 * @package WooSync
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// WOOSYNC_SETTINGS CLASS
// ============================================================================

/**
 * WooSync_Settings class.
 *
 * Manages WordPress settings API integration for plugin configuration.
 *
 * @package WooSync
 * @since 1.0.0
 */
class WooSync_Settings {

    /**
     * @var WooSync_Vendor Vendor instance
     */
    protected $vendor;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param WooSync_Vendor $vendor
     */
    public function __construct( $vendor ) {
        $this->vendor = $vendor;
    }

    /**
     * Get vendor instance.
     *
     * @since 1.0.0
     * @return WooSync_Vendor
     */
    public function get_vendor() {
        return $this->vendor;
    }

    /**
     * Register plugin settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        // Core settings
        register_setting(
            'woosync_settings_group',
            'woosync_vendors',
            array(
                'type'              => 'array',
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        register_setting(
            'woosync_settings_group',
            'woosync_batch_size',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 200,
            )
        );

        register_setting(
            'woosync_settings_group',
            'woosync_sync_schedule',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'daily',
            )
        );

        register_setting(
            'woosync_settings_group',
            'woosync_markup_percent',
            array(
                'type'              => 'number',
                'sanitize_callback' => 'floatval',
                'default'           => 30,
            )
        );

        register_setting(
            'woosync_settings_group',
            'woosync_field_mapping',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_field_mapping' ),
            )
        );

        register_setting(
            'woosync_settings_group',
            'woosync_sync_log',
            array(
                'type' => 'array',
            )
        );

        register_setting(
            'woosync_settings_group',
            'woosync_vendor_credentials',
            array(
                'type' => 'array',
            )
        );

        register_setting(
            'woosync_settings_group',
            'woosync_vendor_tiers',
            array(
                'type' => 'array',
            )
        );
    }

    /**
     * Sanitize field mapping.
     *
     * @since 1.0.0
     * @param array $input Raw input
     * @return array Sanitized
     */
    public function sanitize_field_mapping( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $input as $key => $value ) {
            $sanitized[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
        }
        return $sanitized;
    }

    /**
     * Get a setting value.
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed  $default Default value
     * @return mixed
     */
    public function get_setting( $key, $default = null ) {
        $value = get_option( 'woosync_' . $key, $default );
        return $value !== false ? $value : $default;
    }

    /**
     * Update a setting value.
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed  $value Setting value
     * @return bool
     */
    public function update_setting( $key, $value ) {
        return update_option( 'woosync_' . $key, $value );
    }

    /**
     * Get default field mapping for the current vendor.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_default_field_mapping() {
        $rules = $this->vendor->get_field_mapping_rules();
        $mapping = array();

        foreach ( $rules as $wc_key => $rule ) {
            if ( ! empty( $rule['patterns'] ) ) {
                $mapping[ $wc_key ] = $rule['patterns'][0];
            }
        }

        return $mapping;
    }

    /**
     * Reset field mapping to defaults.
     *
     * @since 1.0.0
     * @return bool
     */
    public function reset_field_mapping() {
        $default = $this->get_default_field_mapping();
        return update_option( 'woosync_field_mapping', $default );
    }

    /**
     * Export settings for backup.
     *
     * @since 1.0.0
     * @return array
     */
    public function export_settings() {
        return array(
            'version' => WOOSYNC_VERSION,
            'exported' => current_time( 'mysql' ),
            'settings' => array(
                'woosync_vendors' => get_option( 'woosync_vendors' ),
                'woosync_batch_size' => get_option( 'woosync_batch_size' ),
                'woosync_sync_schedule' => get_option( 'woosync_sync_schedule' ),
                'woosync_markup_percent' => get_option( 'woosync_markup_percent' ),
                'woosync_field_mapping' => get_option( 'woosync_field_mapping' ),
            ),
        );
    }

    /**
     * Import settings from backup.
     *
     * @since 1.0.0
     * @param array $data Import data
     * @return bool|WP_Error
     */
    public function import_settings( $data ) {
        if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
            return new WP_Error( 'woosync_import_invalid', __( 'Invalid import data format.', 'woosync' ) );
        }

        foreach ( $data['settings'] as $key => $value ) {
            update_option( $key, $value );
        }

        return true;
    }
}
