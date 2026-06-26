<?php
/**
 * WooSync Sync Engine
 *
 * Core sync logic for product synchronization.
 * Uses the active vendor for vendor-specific operations.
 *
 * @package WooSync
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// WOOSYNC_SYNC CLASS
// ============================================================================

/**
 * WooSync_Sync class.
 *
 * Core sync engine that handles product synchronization.
 * Delegates vendor-specific operations to the active vendor.
 *
 * @package WooSync
 * @since 1.0.0
 */
class WooSync_Sync {

    /**
     * @var WooSync_Vendor Vendor instance
     */
    protected $vendor;

    /**
     * @var WooSync_API API instance
     */
    protected $api;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param WooSync_Vendor $vendor
     * @param WooSync_API    $api
     */
    public function __construct( $vendor, $api ) {
        $this->vendor = $vendor;
        $this->api = $api;
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
     * Get API instance.
     *
     * @since 1.0.0
     * @return WooSync_API
     */
    public function get_api() {
        return $this->api;
    }

    // ========================================================================
    // TIER HELPERS
    // ========================================================================

    /**
     * Get tier level for a tier name.
     *
     * @since 1.0.0
     * @param string $tier_name
     * @return int
     */
    public function get_tier_level( $tier_name ) {
        return $this->vendor->get_tier_level( $tier_name );
    }

    /**
     * Get tier color class.
     *
     * @since 1.0.0
     * @param string $tier_name
     * @return string
     */
    public function get_tier_color_class( $tier_name ) {
        return $this->vendor->get_tier_color_class( $tier_name );
    }

    /**
     * Get tier icon.
     *
     * @since 1.0.0
     * @param string $tier_name
     * @return string
     */
    public function get_tier_icon( $tier_name ) {
        return $this->vendor->get_tier_icon( $tier_name );
    }

    /**
     * Get vendor tier data.
     *
     * @since 1.0.0
     * @param string $vendor_id
     * @return array
     */
    public function get_vendor_tier( $vendor_id = '' ) {
        return $this->vendor->get_vendor_tier( $vendor_id );
    }

    /**
     * Save vendor tier data.
     *
     * @since 1.0.0
     * @param string $vendor_id
     * @param array  $tier_data
     * @return bool
     */
    public function save_vendor_tier( $vendor_id, $tier_data ) {
        return $this->vendor->save_vendor_tier( $vendor_id, $tier_data );
    }

    // ========================================================================
    // LOGGING
    // ========================================================================

    /**
     * Append a message to the sync log.
     *
     * @since 1.0.0
     * @param string $message
     * @return void
     */
    public function sync_log( $message ) {
        $this->api->sync_log( $message );
    }

    // ========================================================================
    // CATEGORY HELPERS
    // ========================================================================

    /**
     * Fetch or create a WooCommerce product category term.
     *
     * @since 1.0.0
     * @param string $category_name
     * @return int term_id or 0 on failure
     */
    public function get_or_create_category( $category_name ) {
        if ( empty( $category_name ) ) {
            return 0;
        }

        $category_name = sanitize_text_field( $category_name );

        // Try to find existing term
        $term = get_term_by( 'name', $category_name, 'product_cat' );
        if ( $term ) {
            return (int) $term->term_id;
        }

        // Create new term
        $result = wp_insert_term( $category_name, 'product_cat' );
        if ( is_wp_error( $result ) ) {
            $this->sync_log( 'Failed to create category: ' . $result->get_error_message() );
            return 0;
        }

        return (int) $result['term_id'];
    }

    /**
     * Map a raw product array to WooCommerce category IDs.
     *
     * @since 1.0.0
     * @param array $product Raw product data
     * @param array $mapping Field mapping array
     * @return int[] Array of term IDs
     */
    public function get_category_ids( $product, $mapping ) {
        $category_field = $mapping['categories'] ?? 'CategoryName';
        $category_value = $product[ $category_field ] ?? '';

        if ( empty( $category_value ) ) {
            return array();
        }

        $category_ids = array();

        // Handle array value
        if ( is_array( $category_value ) ) {
            foreach ( $category_value as $cat_name ) {
                $term_id = $this->get_or_create_category( $cat_name );
                if ( $term_id > 0 ) {
                    $category_ids[] = $term_id;
                }
            }
            return $category_ids;
        }

        // Handle string value with various delimiters
        $delimiters = array( ',', '|', '/' );
        $parts = array( $category_value );

        foreach ( $delimiters as $delimiter ) {
            if ( strpos( $category_value, $delimiter ) !== false ) {
                $parts = explode( $delimiter, $category_value );
                break;
            }
        }

        foreach ( $parts as $cat_name ) {
            $cat_name = trim( $cat_name );
            if ( ! empty( $cat_name ) ) {
                $term_id = $this->get_or_create_category( $cat_name );
                if ( $term_id > 0 ) {
                    $category_ids[] = $term_id;
                }
            }
        }

        return $category_ids;
    }

    // ========================================================================
    // PREVIEW GENERATION
    // ========================================================================

    /**
     * Generate a preview array from a raw product.
     *
     * @since 1.0.0
     * @param array $product Raw product data
     * @param array $mapping Field mapping array
     * @return array Preview data
     */
    public function generate_preview( $product, $mapping ) {
        return $this->api->generate_preview( $product, $mapping );
    }

    /**
     * Generate a tier-aware preview array.
     *
     * @since 1.0.0
     * @param array $product Raw product data
     * @param array $mapping Field mapping array
     * @param array $vendor_tier Tier data
     * @return array Preview with tier fields
     */
    public function generate_preview_with_tier( $product, $mapping, $vendor_tier ) {
        return $this->api->generate_preview_with_tier( $product, $mapping, $vendor_tier );
    }

    // ========================================================================
    // PRODUCT SYNC
    // ========================================================================

    /**
     * Sync a single product to WooCommerce.
     *
     * @since 1.0.0
     * @param array $product Raw product data
     * @return bool True on success
     */
    public function sync_single_product( $product ) {
        return $this->api->sync_single_product( $product );
    }

    /**
     * Process one batch of products.
     *
     * @since 1.0.0
     * @param int $offset Starting index
     * @param int $batch_size Number of products
     * @return array Batch result
     */
    public function sync_process_batch( $offset = 0, $batch_size = 200 ) {
        return $this->api->sync_process_batch( $offset, $batch_size );
    }
}
