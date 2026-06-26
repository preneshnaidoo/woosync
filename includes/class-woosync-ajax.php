<?php
/**
 * WooSync AJAX Handlers
 *
 * Handles all AJAX requests for the plugin.
 * Uses the active vendor for vendor-specific operations.
 *
 * @package WooSync
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// WOOSYNC_AJAX CLASS
// ============================================================================

/**
 * WooSync_Ajax class.
 *
 * Handles all AJAX requests for the plugin.
 * Each method corresponds to a wp_ajax_* action.
 *
 * @package WooSync
 * @since 1.0.0
 */
class WooSync_Ajax {

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

    // ========================================================================
    // AJAX ACTIONS
    // ========================================================================

    /**
     * Get credential schema for a vendor.
     *
     * @action wp_ajax_woosync_get_credential_schema
     * @since 1.0.0
     */
    public function woosync_ajax_get_credential_schema() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $vendor_id = sanitize_text_field( $_POST['vendor_id'] ?? '' );
        if ( empty( $vendor_id ) ) {
            $vendor_id = $this->vendor->get_vendor_id();
        }

        $schema = woosync_get_vendor_credential_schema( $vendor_id );

        wp_send_json_success( array(
            'schema' => $schema,
            'vendor_id' => $vendor_id,
        ) );
    }

    /**
     * Test API connection.
     *
     * @action wp_ajax_woosync_test_connection
     * @since 1.0.0
     */
    public function woosync_ajax_test_connection() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $vendor_id = sanitize_text_field( $_POST['vendor_id'] ?? '' );
        if ( empty( $vendor_id ) ) {
            $vendor_id = $this->vendor->get_vendor_id();
        }

        $schema = woosync_get_vendor_credential_schema( $vendor_id );
        $auth_type = $schema['auth_type'] ?? 'custom';

        // Build credentials from POST
        $credentials = array();
        foreach ( $schema['fields'] as $field ) {
            $key = $field['key'];
            if ( $field['type'] === 'password' ) {
                $credentials[ $key ] = sanitize_text_field( $_POST[ $key ] ?? '' );
            } else {
                $credentials[ $key ] = $field['type'] === 'url' 
                    ? esc_url_raw( $_POST[ $key ] ?? '' )
                    : sanitize_text_field( $_POST[ $key ] ?? '' );
            }
        }

        $result = $this->test_vendor_connection( $vendor_id, $auth_type, $credentials, $schema );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    /**
     * Test vendor connection.
     *
     * @since 1.0.0
     * @param string $vendor_id
     * @param string $auth_type
     * @param array  $credentials
     * @param array  $schema
     * @return array
     */
    protected function test_vendor_connection( $vendor_id, $auth_type, $credentials, $schema ) {
        $definition = woosync_get_vendor_definition( $vendor_id );

        switch ( $auth_type ) {
            case 'vendor_login':
                $auth_url = $credentials['auth_url'] ?? $definition['auth_url'] ?? '';
                if ( empty( $auth_url ) || empty( $credentials['username'] ) || empty( $credentials['password'] ) ) {
                    return array( 'success' => false, 'message' => 'Auth URL, username, and password are required' );
                }

                $payload = json_encode( array(
                    'username'      => $credentials['username'],
                    'password'      => $credentials['password'],
                    'CustomerCode'  => $credentials['customer_code'] ?? '',
                ) );

                $response = wp_remote_post( $auth_url . '/VendorLogin', array(
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body'    => $payload,
                    'timeout' => 20,
                ) );

                if ( is_wp_error( $response ) ) {
                    return array( 'success' => false, 'message' => 'Connection failed: ' . $response->get_error_message() );
                }

                $code = wp_remote_retrieve_response_code( $response );
                if ( $code === 200 ) {
                    $data = json_decode( wp_remote_retrieve_body( $response ), true );
                    if ( isset( $data['token'] ) ) {
                        return array( 'success' => true, 'message' => 'Connection successful!', 'token' => substr( $data['token'], 0, 10 ) . '...' );
                    }
                }
                return array( 'success' => false, 'message' => 'Invalid credentials (HTTP ' . $code . ')' );

            case 'bearer_key':
                $api_base_url = $credentials['api_base_url'] ?? $definition['api_base_url'] ?? '';
                if ( empty( $api_base_url ) || empty( $credentials['bearer_token'] ) ) {
                    return array( 'success' => false, 'message' => 'API Base URL and Bearer Token are required' );
                }

                $response = wp_remote_get( $api_base_url . '/products', array(
                    'headers' => array(
                        'Authorization'   => 'Bearer ' . $credentials['bearer_token'],
                        'ClientAccessKey' => $credentials['client_access_key'] ?? '',
                        'Content-Type'    => 'application/json',
                    ),
                    'timeout' => 20,
                ) );

                if ( is_wp_error( $response ) ) {
                    return array( 'success' => false, 'message' => 'Connection failed: ' . $response->get_error_message() );
                }

                $code = wp_remote_retrieve_response_code( $response );
                if ( $code === 200 ) {
                    return array( 'success' => true, 'message' => 'Connection successful!' );
                }
                return array( 'success' => false, 'message' => 'API error (HTTP ' . $code . ')' );

            case 'custom':
            default:
                $api_base_url = $credentials['api_base_url'] ?? '';
                if ( empty( $api_base_url ) ) {
                    return array( 'success' => false, 'message' => 'API Base URL is required' );
                }

                $headers = array( 'Content-Type' => 'application/json' );
                if ( ! empty( $credentials['bearer_token'] ) ) {
                    $headers['Authorization'] = 'Bearer ' . $credentials['bearer_token'];
                }
                if ( ! empty( $credentials['api_key'] ) ) {
                    $headers['X-API-Key'] = $credentials['api_key'];
                }

                $response = wp_remote_get( $api_base_url, array(
                    'headers' => $headers,
                    'timeout' => 20,
                ) );

                if ( is_wp_error( $response ) ) {
                    return array( 'success' => false, 'message' => 'Connection failed: ' . $response->get_error_message() );
                }

                $code = wp_remote_retrieve_response_code( $response );
                if ( $code >= 200 && $code < 300 ) {
                    return array( 'success' => true, 'message' => 'Connection successful!' );
                }
                return array( 'success' => false, 'message' => 'API error (HTTP ' . $code . ')' );
        }
    }

    /**
     * Save vendor credentials.
     *
     * @action wp_ajax_woosync_save_credentials_simple
     * @since 1.0.0
     */
    public function woosync_ajax_save_credentials_simple() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $vendor_id = sanitize_text_field( $_POST['vendor_id'] ?? '' );
        if ( empty( $vendor_id ) ) {
            $vendor_id = $this->vendor->get_vendor_id();
        }

        $schema = woosync_get_vendor_credential_schema( $vendor_id );
        $auth_type = $schema['auth_type'] ?? 'custom';
        $posted = $_POST;

        // Validate required fields
        $errors = array();
        foreach ( $schema['fields'] as $field ) {
            if ( $field['required'] ) {
                $key = $field['key'];
                if ( empty( $posted[ $key ] ) ) {
                    $errors[] = $field['label'] . ' is required';
                }
            }
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( 'Please fill in required fields: ' . implode( ', ', $errors ) );
        }

        // Build credentials array
        $credentials = array(
            'vendor_id' => $vendor_id,
            'auth_type' => $auth_type,
            'saved_at'  => current_time( 'mysql' ),
        );

        foreach ( $schema['fields'] as $field ) {
            $key = $field['key'];
            $value = $posted[ $key ] ?? '';

            if ( ! empty( $value ) ) {
                if ( $field['type'] === 'password' ) {
                    $credentials[ $key ] = $value;
                } else {
                    $credentials[ $key ] = sanitize_text_field( $value );
                }
            }
        }

        // Store per-vendor credentials
        $vendor_creds = get_option( 'woosync_vendor_credentials', array() );
        if ( ! is_array( $vendor_creds ) ) {
            $vendor_creds = array();
        }
        $vendor_creds[ $vendor_id ] = $credentials;
        update_option( 'woosync_vendor_credentials', $vendor_creds );

        // Store vendor in vendors list
        $vendors = get_option( 'woosync_vendors', array() );
        if ( ! is_array( $vendors ) ) {
            $vendors = array();
        }
        $vendors[ $vendor_id ] = array_merge( $vendors[ $vendor_id ] ?? array(), array(
            'connected'    => true,
            'connected_at' => current_time( 'mysql' ),
            'auth_type'    => $auth_type,
        ) );
        update_option( 'woosync_vendors', $vendors );

        wp_send_json_success( array( 'message' => 'Credentials saved successfully!', 'vendor_id' => $vendor_id ) );
    }

    /**
     * Fetch a fresh auth token.
     *
     * @action wp_ajax_woosync_fetch_token
     * @since 1.0.0
     */
    public function woosync_ajax_fetch_token() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        // Clear cached token
        delete_transient( 'woosync_' . $this->vendor->get_vendor_id() . '_token' );

        $token = $this->api->get_token();
        if ( $token ) {
            update_option( 'woosync_last_token_fetched', current_time( 'mysql' ) );
            wp_send_json_success( array( 'token' => $this->api->mask_token_for_display( $token ) ) );
        } else {
            wp_send_json_error( 'Failed to fetch token' );
        }
    }

    /**
     * Sync a batch of products.
     *
     * @action wp_ajax_woosync_sync_batch
     * @since 1.0.0
     */
    public function woosync_ajax_sync_batch() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $offset = intval( $_POST['offset'] ?? 0 );
        $batch_size = intval( $_POST['batch_size'] ?? 200 );

        $result = $this->api->sync_process_batch( $offset, $batch_size );
        wp_send_json_success( $result );
    }

    /**
     * Search products with fuzzy matching.
     *
     * @action wp_ajax_woosync_search_products
     * @since 1.0.0
     */
    public function woosync_ajax_search_products() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $query = sanitize_text_field( $_POST['query'] ?? '' );

        $token = $this->api->get_token();
        if ( ! $token ) {
            wp_send_json_error( 'Failed to obtain token' );
        }

        $endpoints = $this->vendor->get_endpoints();
        $products_ep = $endpoints['products'] ?? null;

        if ( ! $products_ep || ! $products_ep['enabled'] ) {
            wp_send_json_error( 'Products endpoint not enabled' );
        }

        $products_url = $this->api->get_api_url() . $products_ep['path'];
        $products = $this->api->get_endpoint( $token, $products_url );

        if ( $products === false || ! is_array( $products ) ) {
            wp_send_json_error( 'Failed to fetch products' );
        }

        // Fuzzy search matching
        $query_lower = strtolower( $query );
        $results = array();

        $mapping = get_option( 'woosync_field_mapping', array() );
        $sku_field = $mapping['sku'] ?? 'ProductCode';
        $name_field = $mapping['name'] ?? 'Description';

        foreach ( $products as $product ) {
            $name = strtolower( $product[ $name_field ] ?? '' );
            $sku = strtolower( $product[ $sku_field ] ?? '' );

            // Exact match
            if ( $query_lower === '' || $query_lower === $name || $query_lower === $sku ) {
                $results[] = $product;
                continue;
            }

            // Contains match
            if ( strpos( $name, $query_lower ) !== false || strpos( $sku, $query_lower ) !== false ) {
                $results[] = $product;
                continue;
            }

            // Fuzzy match — all query words must match
            $query_words = array_filter( explode( ' ', $query_lower ) );
            $match_count = 0;
            foreach ( $query_words as $word ) {
                if ( strlen( $word ) >= 2 && ( strpos( $name, $word ) !== false || strpos( $sku, $word ) !== false ) ) {
                    $match_count++;
                }
            }
            if ( $match_count === count( $query_words ) && count( $query_words ) > 0 ) {
                $results[] = $product;
            }
        }

        $results = array_slice( $results, 0, 50 );

        $formatted = array_map( function( $p ) use ( $mapping ) {
            $sku_field = $mapping['sku'] ?? 'ProductCode';
            $name_field = $mapping['name'] ?? 'Description';
            $price_field = $mapping['price'] ?? 'Price';
            $image_field = $mapping['images'] ?? 'ImageURL';
            $category_field = $mapping['categories'] ?? 'CategoryName';

            return array(
                'id'       => $p[ $sku_field ] ?? '',
                'name'     => $p[ $name_field ] ?? 'Unknown',
                'sku'      => $p[ $sku_field ] ?? '',
                'price'    => $p[ $price_field ] ?? 0,
                'image'    => $p[ $image_field ] ?? '',
                'category' => $p[ $category_field ] ?? '',
            );
        }, $results );

        wp_send_json_success( array( 'products' => $formatted, 'total' => count( $results ) ) );
    }

    /**
     * Get a single product preview.
     *
     * @action wp_ajax_woosync_get_product_preview
     * @since 1.0.0
     */
    public function woosync_ajax_get_product_preview() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $product_code = sanitize_text_field( $_POST['product_code'] ?? '' );

        $token = $this->api->get_token();
        if ( ! $token ) {
            wp_send_json_error( 'Failed to obtain token' );
        }

        $endpoints = $this->vendor->get_endpoints();
        $products_ep = $endpoints['products'] ?? null;

        if ( ! $products_ep || ! $products_ep['enabled'] ) {
            wp_send_json_error( 'Products endpoint not enabled' );
        }

        $products_url = $this->api->get_api_url() . $products_ep['path'];
        $products = $this->api->get_endpoint( $token, $products_url );

        if ( $products === false || ! is_array( $products ) ) {
            wp_send_json_error( 'Failed to fetch products' );
        }

        $mapping = get_option( 'woosync_field_mapping', array() );
        $sku_field = $mapping['sku'] ?? 'ProductCode';

        foreach ( $products as $product ) {
            if ( ( $product[ $sku_field ] ?? '' ) === $product_code ) {
                $preview = $this->api->generate_preview( $product, $mapping );
                wp_send_json_success( $preview );
            }
        }

        wp_send_json_error( 'Product not found' );
    }

    /**
     * Auto-detect field mappings from the first product sample.
     *
     * @action wp_ajax_woosync_auto_detect_fields
     * @since 1.0.0
     */
    public function woosync_ajax_auto_detect_fields() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $token = $this->api->get_token();
        if ( ! $token ) {
            wp_send_json_error( 'Failed to fetch token' );
        }

        $endpoints = $this->vendor->get_endpoints();
        $products_ep = $endpoints['products'] ?? null;

        if ( ! $products_ep || ! $products_ep['enabled'] ) {
            wp_send_json_error( 'Products endpoint not enabled' );
        }

        $products_url = $this->api->get_api_url() . $products_ep['path'];
        $products = $this->api->get_endpoint( $token, $products_url );

        if ( $products === false || ! is_array( $products ) || empty( $products ) ) {
            wp_send_json_error( 'Failed to fetch products' );
        }

        $sample = $products[0];
        $fields = array_keys( $sample );

        $mapping = array();
        $rules = $this->vendor->get_field_mapping_rules();

        foreach ( $rules as $wc_key => $rule ) {
            foreach ( $rule['patterns'] as $pattern ) {
                if ( in_array( $pattern, $fields, true ) ) {
                    $mapping[ $wc_key ] = $pattern;
                    break;
                }
            }
        }

        wp_send_json_success( array(
            'fields' => $fields,
            'mapping' => $mapping,
            'sample_product' => $sample,
        ) );
    }

    /**
     * Save field mapping configuration.
     *
     * @action wp_ajax_woosync_save_field_mapping
     * @since 1.0.0
     */
    public function woosync_ajax_save_field_mapping() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $mapping = $_POST['mapping'] ?? array();
        update_option( 'woosync_field_mapping', $mapping );
        update_option( 'woosync_mapped_fields', count( array_filter( $mapping ) ) );

        wp_send_json_success( array( 'message' => 'Field mapping saved successfully' ) );
    }

    /**
     * Enable or disable an API endpoint.
     *
     * @action wp_ajax_woosync_save_endpoint
     * @since 1.0.0
     */
    public function woosync_ajax_save_endpoint() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $endpoint_key = sanitize_text_field( $_POST['endpoint'] ?? '' );
        $enabled = intval( $_POST['enabled'] ?? 0 );

        $endpoints = $this->vendor->get_endpoints();
        if ( isset( $endpoints[ $endpoint_key ] ) ) {
            $endpoints[ $endpoint_key ]['enabled'] = $enabled;
            $this->vendor->save_endpoints( $endpoints );
            wp_send_json_success( array( 'message' => 'Endpoint updated' ) );
        }

        wp_send_json_error( 'Endpoint not found' );
    }

    /**
     * Refresh vendor tier status from the API.
     *
     * @action wp_ajax_woosync_refresh_tier_status
     * @since 1.0.0
     */
    public function woosync_ajax_refresh_tier_status() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $vendor_id = sanitize_text_field( $_POST['vendor_id'] ?? $this->vendor->get_vendor_id() );
        $token = $this->api->get_token();
        if ( ! $token ) {
            wp_send_json_error( 'Failed to obtain token' );
        }

        $creds = $this->vendor->get_vendor_credentials( $vendor_id );
        $auth_url = $creds['auth_url'] ?? '';
        $definition = woosync_get_vendor_definition( $vendor_id );
        if ( empty( $auth_url ) ) {
            $auth_url = $definition['auth_url'] ?? '';
        }

        $tier_data = array(
            'tier' => 'Standard',
            'tier_level' => 0,
            'tier_active_since' => current_time( 'mysql' ),
        );

        if ( ! empty( $auth_url ) ) {
            $payload = json_encode( array(
                'username'     => $creds['username'] ?? '',
                'password'     => $creds['password'] ?? '',
                'CustomerCode'  => $creds['customer_code'] ?? '',
            ) );

            $response = wp_remote_post( $auth_url . '/VendorLogin', array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => $payload,
                'timeout' => 20,
            ) );

            if ( ! is_wp_error( $response ) ) {
                $code = wp_remote_retrieve_response_code( $response );
                if ( $code === 200 ) {
                    $data = json_decode( wp_remote_retrieve_body( $response ), true );

                    $tier_keys = array( 'tier', 'pricingLevel', 'customerTier', 'PricingLevel' );
                    foreach ( $tier_keys as $key ) {
                        if ( isset( $data[ $key ] ) ) {
                            $tier_data['tier'] = $data[ $key ];
                            break;
                        }
                    }

                    $tier_data['tier_level'] = woosync_get_vendor_tier_level( $vendor_id, $tier_data['tier'] );

                    if ( isset( $data['tierExpiry'] ) || isset( $data['ExpiryDate'] ) ) {
                        $tier_data['tier_expiry'] = $data['tierExpiry'] ?? $data['ExpiryDate'];
                    }
                    if ( isset( $data['upgradeUrl'] ) || isset( $data['upgrade_url'] ) ) {
                        $tier_data['upgrade_url'] = $data['upgradeUrl'] ?? $data['upgrade_url'];
                    }
                }
            }
        }

        $this->vendor->save_vendor_tier( $vendor_id, $tier_data );

        wp_send_json_success( array(
            'message' => 'Tier status refreshed',
            'tier' => $tier_data['tier'],
            'tier_level' => $tier_data['tier_level'],
        ) );
    }

    /**
     * Save vendor tier settings.
     *
     * @action wp_ajax_woosync_save_tier_settings
     * @since 1.0.0
     */
    public function woosync_ajax_save_tier_settings() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $vendor_id = sanitize_text_field( $_POST['vendor_id'] ?? $this->vendor->get_vendor_id() );
        $tier = sanitize_text_field( $_POST['tier'] ?? 'Standard' );
        $tier_notes = sanitize_textarea_field( $_POST['tier_notes'] ?? '' );
        $tier_pricing_endpoint = sanitize_text_field( $_POST['tier_pricing_endpoint'] ?? '/api/v1/Prices/' );
        $markup_percent = floatval( $_POST['markup_percent'] ?? 30 );

        $tier_data = array(
            'tier' => $tier,
            'tier_level' => woosync_get_vendor_tier_level( $vendor_id, $tier ),
            'tier_notes' => $tier_notes,
            'tier_pricing_endpoint' => $tier_pricing_endpoint,
            'tier_active_since' => current_time( 'mysql' ),
        );

        $this->vendor->save_vendor_tier( $vendor_id, $tier_data );
        update_option( 'woosync_markup_percent', $markup_percent );

        wp_send_json_success( array( 'message' => 'Tier settings saved' ) );
    }

    /**
     * Get tier pricing savings comparison.
     *
     * @action wp_ajax_woosync_get_tier_savings
     * @since 1.0.0
     */
    public function woosync_ajax_get_tier_savings() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $vendor_id = sanitize_text_field( $_POST['vendor_id'] ?? $this->vendor->get_vendor_id() );
        $vendor_tier = $this->vendor->get_vendor_tier( $vendor_id );
        $tier = $vendor_tier['tier'] ?? 'Standard';

        if ( $tier === 'Standard' ) {
            wp_send_json_success( array(
                'products' => array(),
                'total_savings' => 0,
                'product_count' => 0,
                'message' => 'Standard tier - no savings',
            ) );
        }

        $token = $this->api->get_token();
        if ( ! $token ) {
            wp_send_json_error( 'Failed to obtain token' );
        }

        $endpoints = $this->vendor->get_endpoints();
        $products_ep = $endpoints['products'] ?? null;
        $prices_ep = $endpoints['prices'] ?? null;
        if ( ! $products_ep || ! $products_ep['enabled'] ) {
            wp_send_json_error( 'Products endpoint not enabled' );
        }

        $api_url = $this->api->get_api_url();
        $products_url = $api_url . $products_ep['path'];
        $products = $this->api->get_endpoint( $token, $products_url );
        if ( $products === false || ! is_array( $products ) ) {
            wp_send_json_error( 'Failed to fetch products' );
        }

        $tier_prices = array();
        if ( $prices_ep && $prices_ep['enabled'] ) {
            $prices_url = $api_url . $prices_ep['path'];
            $prices = $this->api->get_endpoint( $token, $prices_url );
            if ( $prices && is_array( $prices ) ) {
                foreach ( $prices as $price_item ) {
                    $code = $price_item['ProductCode'] ?? $price_item['ItemCode'] ?? '';
                    $tier_prices[ $code ] = floatval( $price_item['Price'] ?? $price_item['TierPrice'] ?? 0 );
                }
            }
        }

        $savings = array();
        $total_savings = 0;
        $mapping = get_option( 'woosync_field_mapping', array() );
        $sku_field = $mapping['sku'] ?? 'ProductCode';
        $name_field = $mapping['name'] ?? 'Description';
        $price_field = $mapping['price'] ?? 'Price';

        foreach ( $products as $product ) {
            $code = $product[ $sku_field ] ?? '';
            $name = $product[ $name_field ] ?? 'Unknown';
            $standard_price = floatval( $product[ $price_field ] ?? 0 );
            $tier_price = $tier_prices[ $code ] ?? $standard_price;

            if ( $tier_price < $standard_price && $tier_price > 0 ) {
                $savings_per_unit = $standard_price - $tier_price;
                $total_savings += $savings_per_unit;

                $savings[] = array(
                    'code' => $code,
                    'name' => $name,
                    'standard_price' => $standard_price,
                    'tier_price' => $tier_price,
                    'savings_per_unit' => $savings_per_unit,
                    'savings_percent' => round( ( $savings_per_unit / $standard_price ) * 100, 1 ),
                );
            }
        }

        usort( $savings, function( $a, $b ) {
            return $b['savings_per_unit'] <=> $a['savings_per_unit'];
        } );

        wp_send_json_success( array(
            'products' => array_slice( $savings, 0, 100 ),
            'total_savings' => $total_savings,
            'product_count' => count( $savings ),
            'tier' => $tier,
        ) );
    }

    /**
     * Get product preview with tier pricing breakdown.
     *
     * @action wp_ajax_woosync_get_product_preview_tier
     * @since 1.0.0
     */
    public function woosync_ajax_get_product_preview_tier() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $product_code = sanitize_text_field( $_POST['product_code'] ?? '' );

        $token = $this->api->get_token();
        if ( ! $token ) {
            wp_send_json_error( 'Failed to obtain token' );
        }

        $endpoints = $this->vendor->get_endpoints();
        $products_ep = $endpoints['products'] ?? null;

        if ( ! $products_ep || ! $products_ep['enabled'] ) {
            wp_send_json_error( 'Products endpoint not enabled' );
        }

        $products_url = $this->api->get_api_url() . $products_ep['path'];
        $products = $this->api->get_endpoint( $token, $products_url );

        if ( $products === false || ! is_array( $products ) ) {
            wp_send_json_error( 'Failed to fetch products' );
        }

        $mapping = get_option( 'woosync_field_mapping', array() );
        $sku_field = $mapping['sku'] ?? 'ProductCode';
        $vendor_tier = $this->vendor->get_vendor_tier();

        foreach ( $products as $product ) {
            if ( ( $product[ $sku_field ] ?? '' ) === $product_code ) {
                $preview = $this->api->generate_preview_with_tier( $product, $mapping, $vendor_tier );
                wp_send_json_success( $preview );
            }
        }

        wp_send_json_error( 'Product not found' );
    }

    /**
     * Sync a single product to WooCommerce.
     *
     * @action wp_ajax_woosync_sync_single_product
     * @since 1.0.0
     */
    public function woosync_ajax_sync_single_product() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $product_code = sanitize_text_field( $_POST['product_code'] ?? '' );

        $token = $this->api->get_token();
        if ( ! $token ) {
            wp_send_json_error( 'Failed to obtain token' );
        }

        $endpoints = $this->vendor->get_endpoints();
        $products_ep = $endpoints['products'] ?? null;
        if ( ! $products_ep || ! $products_ep['enabled'] ) {
            wp_send_json_error( 'Products endpoint not enabled' );
        }

        $products_url = $this->api->get_api_url() . $products_ep['path'];
        $products = $this->api->get_endpoint( $token, $products_url );

        if ( $products === false || ! is_array( $products ) ) {
            wp_send_json_error( 'Failed to fetch products' );
        }

        $mapping = get_option( 'woosync_field_mapping', array() );
        $sku_field = $mapping['sku'] ?? 'ProductCode';

        foreach ( $products as $product ) {
            if ( ( $product[ $sku_field ] ?? '' ) === $product_code ) {
                $result = $this->api->sync_single_product( $product );
                if ( $result ) {
                    wp_send_json_success( array( 'message' => 'Product synced successfully' ) );
                } else {
                    wp_send_json_error( 'Failed to sync product' );
                }
            }
        }

        wp_send_json_error( 'Product not found' );
    }

    /**
     * Fetch promos from the vendor API.
     *
     * @action wp_ajax_woosync_fetch_promos
     * @since 1.0.0
     */
    public function woosync_ajax_fetch_promos() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $force_refresh = isset( $_POST['force_refresh'] ) && $_POST['force_refresh'];
        $vendor_id = sanitize_text_field( $_POST['vendor_id'] ?? $this->vendor->get_vendor_id() );

        if ( $force_refresh ) {
            delete_transient( 'woosync_promos_' . $vendor_id );
        }

        $promos = $this->api->fetch_promos( $vendor_id );

        if ( isset( $promos['error'] ) ) {
            wp_send_json_error( $promos['error'] );
        }

        wp_send_json_success( array( 'promos' => $promos ) );
    }

    /**
     * Send a promo email to selected recipients.
     *
     * @action wp_ajax_woosync_send_promo_email
     * @since 1.0.0
     */
    public function woosync_ajax_send_promo_email() {
        check_ajax_referer( 'woosync_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $product_code = sanitize_text_field( $_POST['product_code'] ?? '' );
        $subject = sanitize_text_field( $_POST['subject'] ?? '' );
        $recipient_type = sanitize_text_field( $_POST['recipient_type'] ?? 'all' );
        $user_ids = sanitize_text_field( $_POST['user_ids'] ?? '' );

        if ( empty( $product_code ) || empty( $subject ) ) {
            wp_send_json_error( 'Product code and subject are required' );
        }

        $product_id = wc_get_product_id_by_sku( $product_code );
        if ( ! $product_id ) {
            wp_send_json_error( 'Product not found' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found' );
        }

        // Build recipient list
        $recipients = array();

        switch ( $recipient_type ) {
            case 'all':
                $customers = get_users( array( 'role' => 'customer' ) );
                $recipients = array_map( function( $user ) {
                    return $user->user_email;
                }, $customers );
                break;

            case 'roles':
                $subscribers = get_users( array( 'role' => 'subscriber' ) );
                $recipients = array_map( function( $user ) {
                    return $user->user_email;
                }, $subscribers );
                break;

            case 'specific':
                if ( ! empty( $user_ids ) ) {
                    $ids = array_map( 'intval', explode( ',', $user_ids ) );
                    foreach ( $ids as $id ) {
                        $user = get_user_by( 'id', $id );
                        if ( $user ) {
                            $recipients[] = $user->user_email;
                        }
                    }
                }
                break;
        }

        if ( empty( $recipients ) ) {
            wp_send_json_error( 'No recipients found' );
        }

        // Build email content
        $product_name = $product->get_name();
        $product_url = get_permalink( $product_id );
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();

        $message = "Check out this promo from MediaPlatform!\n\n";
        $message .= "{$product_name}\n";
        if ( $sale_price ) {
            $message .= "Was: R{$regular_price} | Now: R{$sale_price}\n";
        } else {
            $message .= "Price: R{$regular_price}\n";
        }
        $message .= "\nShop now: {$product_url}\n\n";
        $message .= "#promo #brandedmerch";

        // Send emails
        $sent = 0;
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        foreach ( $recipients as $email ) {
            $result = wp_mail( $email, $subject, nl2br( $message ), $headers );
            if ( $result ) {
                $sent++;
            }
        }

        wp_send_json_success( array(
            'message' => "Email sent to {$sent} recipient(s)",
            'sent' => $sent,
            'total' => count( $recipients ),
        ) );
    }

    // ========================================================================
    // STATIC HOOK REGISTRATION
    // ========================================================================

    /**
     * Get the list of all AJAX action names handled by this class.
     *
     * @since 1.0.0
     * @return string[]
     */
    public static function get_actions() {
        return array(
            'woosync_get_credential_schema',
            'woosync_test_connection',
            'woosync_save_credentials_simple',
            'woosync_fetch_token',
            'woosync_sync_batch',
            'woosync_search_products',
            'woosync_get_product_preview',
            'woosync_auto_detect_fields',
            'woosync_save_field_mapping',
            'woosync_save_endpoint',
            'woosync_refresh_tier_status',
            'woosync_save_tier_settings',
            'woosync_get_tier_savings',
            'woosync_get_product_preview_tier',
            'woosync_sync_single_product',
            'woosync_fetch_promos',
            'woosync_send_promo_email',
        );
    }

    /**
     * Register all AJAX hooks.
     *
     * @since 1.0.0
     * @param WooSync_Vendor $vendor
     * @param WooSync_API    $api
     */
    public static function register_hooks( $vendor, $api ) {
        $instance = new self( $vendor, $api );

        foreach ( self::get_actions() as $action ) {
            add_action( 'wp_ajax_' . $action, array( $instance, $action ) );
        }
    }
}
