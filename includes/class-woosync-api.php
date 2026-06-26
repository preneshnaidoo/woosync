<?php
/**
 * WooSync API Client
 *
 * Handles HTTP/API operations for the active vendor.
 * Uses vendor-specific credential schema to determine authentication method.
 *
 * @package WooSync
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// STATIC API HELPER FUNCTIONS
// ============================================================================

/**
 * Mask a token for safe display (show first 6 + last 6 chars).
 *
 * @since 1.0.0
 * @param string $token
 * @return string
 */
function woosync_mask_token_for_display( $token ) {
    if ( ! $token ) {
        return '';
    }
    return substr( $token, 0, 6 ) . '...' . substr( $token, -6 );
}

/**
 * Append a timestamped message to the sync log option.
 *
 * @since 1.0.0
 * @param string $message
 * @return void
 */
function woosync_sync_log( $message ) {
    $log = (array) get_option( 'woosync_sync_log', array() );
    $log[] = '[' . current_time( 'mysql' ) . '] ' . $message;
    $log = array_slice( $log, -500 );
    update_option( 'woosync_sync_log', $log );
}

// ============================================================================
// WOOSYNC_API CLASS
// ============================================================================

/**
 * WooSync_API class.
 *
 * Manages HTTP/API operations for the active vendor.
 * Handles token acquisition, endpoint requests, and vendor-specific authentication.
 *
 * @package WooSync
 * @since 1.0.0
 */
class WooSync_API {

    /**
     * @var WooSync_Vendor Vendor instance
     */
    protected $vendor;

    /**
     * @var string Transient/cache key prefix
     */
    protected $cache_prefix;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param WooSync_Vendor $vendor Vendor instance
     */
    public function __construct( WooSync_Vendor $vendor ) {
        $this->vendor = $vendor;
        $this->cache_prefix = 'woosync_' . $vendor->get_vendor_id() . '_';
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
     * Get API base URL.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_api_url() {
        return $this->vendor->get_api_base_url();
    }

    /**
     * Get auth URL.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_auth_url() {
        return $this->vendor->get_auth_url();
    }

    /**
     * Get auth type.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_auth_type() {
        return $this->vendor->get_auth_type();
    }

    /**
     * Get a credential value.
     *
     * @since 1.0.0
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get_credential( $key, $default = '' ) {
        return $this->vendor->get_credential( $key, $default );
    }

    /**
     * Append a message to the sync log.
     *
     * @since 1.0.0
     * @param string $message
     * @return void
     */
    public function sync_log( $message ) {
        woosync_sync_log( $message );
    }

    // ========================================================================
    // TOKEN MANAGEMENT
    // ========================================================================

    /**
     * Get cached token or obtain a fresh one.
     *
     * @since 1.0.0
     * @return string|false
     */
    public function get_token() {
        $cached = get_transient( $this->cache_prefix . 'token' );
        if ( $cached ) {
            return $cached;
        }

        $auth_type = $this->get_auth_type();
        $token = false;

        switch ( $auth_type ) {
            case 'vendor_login':
                $token = $this->obtain_token_vendor_login();
                break;

            case 'bearer_key':
                // For bearer_key auth, the token is the bearer token itself
                $token = $this->get_credential( 'bearer_token', '' );
                break;

            case 'api_key':
                // For api_key auth, no token acquisition needed
                $token = $this->get_credential( 'api_key', '' );
                break;

            case 'custom':
            default:
                $token = $this->obtain_token_custom();
                break;
        }

        if ( $token ) {
            // Cache for 55 minutes for vendor_login, indefinite for static tokens
            $cache_time = ( $auth_type === 'vendor_login' ) ? 55 * MINUTE_IN_SECONDS : 0;
            if ( $cache_time > 0 ) {
                set_transient( $this->cache_prefix . 'token', $token, $cache_time );
            }
            update_option( $this->cache_prefix . 'last_token', woosync_mask_token_for_display( $token ) );
            $this->sync_log( '✅ Token obtained successfully' );
        }

        return $token;
    }

    /**
     * Obtain token via vendor_login authentication.
     *
     * @since 1.0.0
     * @return string|false
     */
    protected function obtain_token_vendor_login() {
        $auth_url = $this->get_auth_url();
        if ( empty( $auth_url ) ) {
            $this->sync_log( 'Auth URL not configured' );
            return false;
        }

        $payload = json_encode( array(
            'username'      => $this->get_credential( 'username', '' ),
            'password'      => $this->get_credential( 'password', '' ),
            'CustomerCode' => $this->get_credential( 'customer_code', '' ),
        ) );

        $response = wp_remote_post( $auth_url . '/VendorLogin', array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => $payload,
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->sync_log( 'Failed to obtain token: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            $this->sync_log( "Token endpoint returned HTTP {$code}" );
            return false;
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->sync_log( 'Invalid token response: ' . json_last_error_msg() );
            return false;
        }

        return $data['token'] ?? false;
    }

    /**
     * Obtain token via custom authentication.
     *
     * @since 1.0.0
     * @return string|false
     */
    protected function obtain_token_custom() {
        $auth_url = $this->get_credential( 'auth_url', '' );
        if ( empty( $auth_url ) ) {
            // Try to use bearer token directly
            $bearer = $this->get_credential( 'bearer_token', '' );
            if ( ! empty( $bearer ) ) {
                return $bearer;
            }
            $this->sync_log( 'No auth URL or bearer token configured' );
            return false;
        }

        $payload = json_encode( array(
            'username' => $this->get_credential( 'username', '' ),
            'password' => $this->get_credential( 'password', '' ),
        ) );

        $response = wp_remote_post( $auth_url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => $payload,
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->sync_log( 'Custom auth failed: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            $this->sync_log( "Custom auth returned HTTP {$code}" );
            return false;
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->sync_log( 'Invalid auth response: ' . json_last_error_msg() );
            return false;
        }

        return $data['token'] ?? $data['access_token'] ?? false;
    }

    /**
     * Mask a token for safe display.
     *
     * @since 1.0.0
     * @param string $token
     * @return string
     */
    public function mask_token_for_display( $token ) {
        return woosync_mask_token_for_display( $token );
    }

    // ========================================================================
    // ENDPOINT REQUESTS
    // ========================================================================

    /**
     * Perform an authenticated GET request to an API endpoint.
     *
     * @since 1.0.0
     * @param string $token         Bearer token
     * @param string $endpoint_url  Full URL to request
     * @param int    $retries      Number of retries on failure
     * @return array|false
     */
    public function get_endpoint( $token, $endpoint_url, $retries = 2 ) {
        $auth_type = $this->get_auth_type();
        $headers = array( 'Content-Type' => 'application/json' );

        // Add auth headers based on auth type
        switch ( $auth_type ) {
            case 'bearer_key':
                $headers['Authorization'] = 'Bearer ' . $token;
                $headers['ClientAccessKey'] = $this->get_credential( 'client_access_key', '' );
                break;

            case 'api_key':
                $headers['X-API-Key'] = $token;
                break;

            case 'vendor_login':
            case 'custom':
            default:
                $headers['Authorization'] = 'Bearer ' . $token;
                break;
        }

        for ( $attempt = 0; $attempt <= $retries; $attempt++ ) {
            $response = wp_remote_get( $endpoint_url, array(
                'headers' => $headers,
                'timeout' => 20,
            ) );

            if ( is_wp_error( $response ) ) {
                if ( $attempt === $retries ) {
                    $this->sync_log( 'Endpoint error: ' . $response->get_error_message() );
                    return false;
                }
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );

                if ( $code === 200 ) {
                    $decoded = json_decode( $body, true );
                    if ( json_last_error() === JSON_ERROR_NONE ) {
                        return $decoded;
                    }
                    if ( $attempt === $retries ) {
                        $this->sync_log( 'Invalid JSON: ' . json_last_error_msg() );
                        return false;
                    }
                } else {
                    if ( $attempt === $retries ) {
                        $this->sync_log( "Endpoint returned HTTP {$code}" );
                        return false;
                    }
                }
            }

            if ( $attempt < $retries ) {
                sleep( pow( 2, $attempt ) );
            }
        }

        return false;
    }

    // ========================================================================
    // PRODUCT SYNC HELPERS
    // ========================================================================

    /**
     * Generate a preview array from a raw product and field mapping.
     *
     * @since 1.0.0
     * @param array $product Raw product data
     * @param array $mapping Field mapping array
     * @return array Preview data
     */
    public function generate_preview( $product, $mapping ) {
        $sku_field = $mapping['sku'] ?? 'ProductCode';
        $name_field = $mapping['name'] ?? 'Description';
        $price_field = $mapping['price'] ?? 'Price';
        $desc_field = $mapping['description'] ?? 'LongDescription';
        $image_field = $mapping['images'] ?? 'ImageURL';
        $category_field = $mapping['categories'] ?? 'CategoryName';

        return array(
            'sku'         => $product[ $sku_field ] ?? '',
            'name'        => $product[ $name_field ] ?? 'Unknown',
            'price'       => floatval( $product[ $price_field ] ?? 0 ),
            'description' => $product[ $desc_field ] ?? '',
            'image'       => $product[ $image_field ] ?? '',
            'category'    => $product[ $category_field ] ?? '',
            'raw'         => $product,
        );
    }

    /**
     * Generate a tier-aware preview array.
     *
     * @since 1.0.0
     * @param array $product     Raw product data
     * @param array $mapping     Field mapping array
     * @param array $vendor_tier Tier data
     * @return array Preview with tier fields
     */
    public function generate_preview_with_tier( $product, $mapping, $vendor_tier ) {
        $preview = $this->generate_preview( $product, $mapping );
        $tier = $vendor_tier['tier'] ?? 'Standard';
        $markup_percent = floatval( get_option( 'woosync_markup_percent', 30 ) );

        $preview['tier'] = $tier;
        $preview['tier_price'] = $preview['price'];
        $preview['markup_percent'] = $markup_percent;
        $preview['markup_amount'] = 0;
        $preview['customer_price'] = $preview['price'];
        $preview['your_margin'] = 0;
        $preview['has_tier_pricing'] = ( $tier !== 'Standard' );

        if ( $tier === 'Standard' ) {
            $preview['tier_price'] = $preview['price'];
            $preview['markup_amount'] = $preview['price'] * ( $markup_percent / 100 );
            $preview['customer_price'] = $preview['price'] + $preview['markup_amount'];
            $preview['your_margin'] = $preview['markup_amount'];
            return $preview;
        }

        $token = $this->get_token();
        if ( ! $token ) {
            return $preview;
        }

        $endpoints = $this->vendor->get_endpoints();
        $prices_ep = $endpoints['prices'] ?? null;

        if ( ! $prices_ep || ! $prices_ep['enabled'] ) {
            return $preview;
        }

        $api_url = $this->get_api_url();
        $prices_url = $api_url . $prices_ep['path'];
        $prices = $this->get_endpoint( $token, $prices_url );

        if ( $prices && is_array( $prices ) ) {
            $sku_field = $mapping['sku'] ?? 'ProductCode';
            $code = $product[ $sku_field ] ?? '';

            foreach ( $prices as $price_item ) {
                $item_code = $price_item['ProductCode'] ?? $price_item['ItemCode'] ?? '';
                if ( $item_code === $code ) {
                    $tier_price = floatval( $price_item['Price'] ?? $price_item['TierPrice'] ?? 0 );
                    if ( $tier_price > 0 ) {
                        $preview['tier_price'] = $tier_price;
                        $preview['markup_amount'] = $tier_price * ( $markup_percent / 100 );
                        $preview['customer_price'] = $tier_price + $preview['markup_amount'];
                        $preview['your_margin'] = $preview['markup_amount'];
                    }
                    break;
                }
            }
        }

        return $preview;
    }

    /**
     * Fetch promos from the vendor API.
     *
     * @since 1.0.0
     * @param string $vendor_id Vendor ID
     * @return array Promo data
     */
    public function fetch_promos( $vendor_id ) {
        $token = $this->get_token();
        if ( ! $token ) {
            return array( 'error' => 'Failed to obtain token' );
        }

        $endpoints = $this->vendor->get_endpoints();
        $promos_ep = $endpoints['promos'] ?? null;

        if ( ! $promos_ep || ! $promos_ep['enabled'] ) {
            return array( 'error' => 'Promos endpoint not enabled' );
        }

        $api_url = $this->get_api_url();
        $promos_url = $api_url . $promos_ep['path'];
        $promos = $this->get_endpoint( $token, $promos_url );

        if ( $promos === false ) {
            return array( 'error' => 'Failed to fetch promos' );
        }

        return is_array( $promos ) ? $promos : array();
    }

    /**
     * Sync a single product to WooCommerce.
     *
     * @since 1.0.0
     * @param array $product Raw product data
     * @return bool True on success
     */
    public function sync_single_product( $product ) {
        $mapping = get_option( 'woosync_field_mapping', array() );
        $sku_field = $mapping['sku'] ?? 'ProductCode';
        $sku = sanitize_text_field( $product[ $sku_field ] ?? '' );

        if ( empty( $sku ) ) {
            return false;
        }

        $existing_id = wc_get_product_id_by_sku( $sku );

        try {
            if ( $existing_id ) {
                $wc_product = wc_get_product( $existing_id );
            } else {
                $wc_product = new WC_Product_Simple();
                $wc_product->set_status( 'publish' );
                $wc_product->set_catalog_visibility( 'visible' );
            }

            if ( ! $wc_product || ! is_a( $wc_product, 'WC_Product' ) ) {
                return false;
            }

            $name_field = $mapping['name'] ?? 'Description';
            $price_field = $mapping['price'] ?? 'Price';
            $desc_field = $mapping['description'] ?? 'LongDescription';

            $wc_product->set_sku( $sku );
            $wc_product->set_name( sanitize_text_field( $product[ $name_field ] ?? 'Product' ) );
            $wc_product->set_regular_price( floatval( $product[ $price_field ] ?? 0 ) );
            $wc_product->set_description( $product[ $desc_field ] ?? '' );

            $product_id = $wc_product->save();

            if ( $product_id && $product_id > 0 ) {
                $this->sync_log( "✅ Synced product: {$product[ $name_field ]} ({$sku})" );
                return true;
            }
        } catch ( Exception $e ) {
            $this->sync_log( '❌ Error syncing product ' . $sku . ': ' . $e->getMessage() );
        }

        return false;
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
        $token = $this->get_token();
        if ( ! $token ) {
            return array(
                'success' => false,
                'processed_total' => 0,
                'total' => 0,
                'more' => false,
                'error' => 'Failed to obtain token',
            );
        }

        $endpoints = $this->vendor->get_endpoints();
        $products_ep = $endpoints['products'] ?? null;

        if ( ! $products_ep || ! $products_ep['enabled'] ) {
            return array(
                'success' => false,
                'processed_total' => 0,
                'total' => 0,
                'more' => false,
                'error' => 'Products endpoint not enabled',
            );
        }

        $api_url = $this->get_api_url();
        $products_url = $api_url . $products_ep['path'];
        $products = $this->get_endpoint( $token, $products_url );

        if ( $products === false || ! is_array( $products ) ) {
            return array(
                'success' => false,
                'processed_total' => 0,
                'total' => 0,
                'more' => false,
                'error' => 'Failed to fetch products',
            );
        }

        $total = count( $products );
        $batch = array_slice( $products, $offset, $batch_size );
        $processed = 0;
        $mapping = get_option( 'woosync_field_mapping', array() );

        foreach ( $batch as $p ) {
            if ( $this->sync_single_product( $p ) ) {
                $processed++;
            }
        }

        $prev_total = (int) get_option( 'woosync_total_products', 0 );
        update_option( 'woosync_total_products', $prev_total + $processed );
        update_option( 'woosync_last_sync', current_time( 'mysql' ) );

        $next_offset = $offset + $batch_size;
        $more = $next_offset < $total;

        $this->sync_log( "✅ Batch complete: {$processed} products synced" );

        return array(
            'success' => true,
            'processed' => $processed,
            'processed_total' => $prev_total + $processed,
            'total' => $total,
            'more' => $more,
            'next_offset' => $next_offset,
        );
    }
}
