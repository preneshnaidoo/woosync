<?php
/**
 * WooSync Admin Class
 *
 * Handles admin menu registration and page rendering.
 *
 * @package WooSync
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// WOOSYNC_ADMIN CLASS
// ============================================================================

/**
 * WooSync_Admin class.
 *
 * Manages admin menu pages and renders the plugin's admin UI.
 *
 * @package WooSync
 * @since 1.0.0
 */
class WooSync_Admin {

    /**
     * @var WooSync_Vendor Vendor instance
     */
    protected $vendor;

    /**
     * @var WooSync_API API instance
     */
    protected $api;

    /**
     * @var string Current page slug
     */
    protected $current_page;

    /**
     * @var string Current tab
     */
    protected $current_tab;

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
        $this->current_page = sanitize_text_field( $_GET['page'] ?? 'woosync' );
        $this->current_tab = sanitize_text_field( $_GET['tab'] ?? 'dashboard' );
    }

    /**
     * Register admin menu pages.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_menus() {
        add_menu_page(
            __( 'WooSync', 'woosync' ),
            __( 'WooSync', 'woosync' ),
            'manage_options',
            'woosync',
            array( $this, 'render_main_page' ),
            'dashicons-update',
            80
        );

        add_submenu_page(
            'woosync',
            __( 'Dashboard', 'woosync' ),
            __( 'Dashboard', 'woosync' ),
            'manage_options',
            'woosync',
            array( $this, 'render_main_page' )
        );

        add_submenu_page(
            'woosync',
            __( 'Connect & Map', 'woosync' ),
            __( 'Connect & Map', 'woosync' ),
            'manage_options',
            'woosync&tab=connect',
            array( $this, 'render_main_page' )
        );

        add_submenu_page(
            'woosync',
            __( 'Sync Log', 'woosync' ),
            __( 'Sync Log', 'woosync' ),
            'manage_options',
            'woosync&tab=log',
            array( $this, 'render_main_page' )
        );

        add_submenu_page(
            'woosync',
            __( 'Settings', 'woosync' ),
            __( 'Settings', 'woosync' ),
            'manage_options',
            'woosync&tab=settings',
            array( $this, 'render_main_page' )
        );
    }

    /**
     * Render the main plugin page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_main_page() {
        // Handle tab routing via query string
        $tab = sanitize_text_field( $_GET['tab'] ?? 'dashboard' );
        
        switch ( $tab ) {
            case 'connect':
                $this->render_connect_page();
                break;
            case 'log':
                $this->render_log_page();
                break;
            case 'settings':
                $this->render_settings_page();
                break;
            case 'dashboard':
            default:
                $this->render_dashboard_page();
                break;
        }
    }

    /**
     * Render the dashboard page.
     *
     * @since 1.0.0
     * @return void
     */
    protected function render_dashboard_page() {
        $status = $this->vendor->get_simplified_status();
        $vendors = woosync_get_registered_vendors();
        $vendor_templates = woosync_get_vendor_templates();
        $total_products = (int) get_option( 'woosync_total_products', 0 );
        $last_sync = get_option( 'woosync_last_sync', '' );
        $markup_percent = get_option( 'woosync_markup_percent', 30 );
        ?>
        <div class="wrap woosync-wrap">
            <h1 class="wp-heading-inline">WooSync Dashboard</h1>
            
            <div class="woosync-dashboard-cards">
                <div class="woosync-card">
                    <h3>Status</h3>
                    <p>
                        <?php if ( $status['connected'] ) : ?>
                            <span class="woosync-status-connected">✅ Connected</span>
                        <?php else : ?>
                            <span class="woosync-status-disconnected">⚠️ Not Connected</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Vendor:</strong> <?php echo esc_html( $status['vendor_id'] ?: 'None' ); ?></p>
                    <p><strong>Field Mapping:</strong> <?php echo $status['has_mapping'] ? '✅ Configured' : '⚠️ Not Set'; ?></p>
                    <p><strong>Last Sync:</strong> <?php echo $last_sync ?: 'Never'; ?></p>
                    <p><strong>Total Synced:</strong> <?php echo number_format( $total_products ); ?> products</p>
                </div>

                <div class="woosync-card">
                    <h3>Quick Actions</h3>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=woosync&tab=connect' ) ); ?>" class="button button-primary">
                            Connect Vendor
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=woosync&tab=settings' ) ); ?>" class="button">
                            Settings
                        </a>
                    </p>
                </div>

                <div class="woosync-card">
                    <h3>Markup Settings</h3>
                    <p><strong>Default Markup:</strong> <?php echo esc_html( $markup_percent ); ?>%</p>
                    <p><em>Configure per-customer or per-role markup in Settings.</em></p>
                </div>
            </div>

            <hr>

            <h2>Registered Vendors</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vendor_templates as $vid => $template ) : 
                        $is_connected = $this->vendor->is_vendor_connected( $vid );
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo $template['icon']; ?> <?php echo esc_html( $template['name'] ); ?></strong>
                                <br><small><?php echo esc_html( $template['description'] ); ?></small>
                            </td>
                            <td>
                                <?php if ( $is_connected ) : ?>
                                    <span class="woosync-status-connected">✅ Connected</span>
                                <?php else : ?>
                                    <span class="woosync-status-disconnected">Not Connected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=woosync&tab=connect&vendor=' . $vid ) ); ?>" class="button">
                                    <?php echo $is_connected ? 'Manage' : 'Connect'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the connect & map page.
     *
     * @since 1.0.0
     * @return void
     */
    protected function render_connect_page() {
        $selected_vendor = sanitize_text_field( $_GET['vendor'] ?? $this->vendor->get_vendor_id() );
        $vendor_templates = woosync_get_vendor_templates();
        $vendor_credential_schemas = woosync_get_all_vendor_credential_schemas();
        $schema = $vendor_credential_schemas[ $selected_vendor ] ?? $vendor_credential_schemas['custom'];
        $credentials = $this->vendor->get_vendor_credentials( $selected_vendor );
        $endpoints = $this->vendor->get_endpoints();
        $mapping = get_option( 'woosync_field_mapping', array() );
        $mapping_rules = $this->vendor->get_field_mapping_rules();
        ?>
        <div class="wrap woosync-wrap">
            <h1 class="wp-heading-inline">Connect & Map</h1>
            
            <h2 class="nav-tab-wrapper">
                <?php foreach ( $vendor_templates as $vid => $template ) : 
                    $is_active = ( $vid === $selected_vendor );
                ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woosync&tab=connect&vendor=' . $vid ) ); ?>" 
                       class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
                        <?php echo $template['icon']; ?> <?php echo esc_html( $template['name'] ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="woosync-connect-section">
                <h3>API Credentials</h3>
                <p><?php echo esc_html( $schema['description'] ); ?></p>
                
                <form id="woosync-credentials-form" class="woosync-form">
                    <input type="hidden" name="action" value="woosync_save_credentials_simple">
                    <input type="hidden" name="vendor_id" value="<?php echo esc_attr( $selected_vendor ); ?>">
                    <?php wp_nonce_field( 'woosync_sync_nonce' ); ?>
                    
                    <table class="form-table">
                        <?php foreach ( $schema['fields'] as $field ) : 
                            $value = $credentials[ $field['key'] ] ?? $field['prefill'] ?? '';
                        ?>
                            <tr>
                                <th scope="row">
                                    <label for="woosync-<?php echo esc_attr( $field['key'] ); ?>">
                                        <?php echo esc_html( $field['label'] ); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php if ( $field['type'] === 'password' ) : ?>
                                        <input type="password" 
                                               id="woosync-<?php echo esc_attr( $field['key'] ); ?>"
                                               name="<?php echo esc_attr( $field['key'] ); ?>"
                                               value="<?php echo esc_attr( $value ); ?>"
                                               placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
                                               class="regular-text">
                                    <?php else : ?>
                                        <input type="<?php echo esc_attr( $field['type'] ); ?>" 
                                               id="woosync-<?php echo esc_attr( $field['key'] ); ?>"
                                               name="<?php echo esc_attr( $field['key'] ); ?>"
                                               value="<?php echo esc_attr( $value ); ?>"
                                               placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
                                               class="regular-text">
                                    <?php endif; ?>
                                    <?php if ( ! empty( $field['help'] ) ) : ?>
                                        <p class="description"><?php echo esc_html( $field['help'] ); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="woosync-test-connection" class="button">
                            Test Connection
                        </button>
                        <button type="submit" class="button button-primary">
                            Save Credentials
                        </button>
                    </p>
                </form>
                
                <div id="woosync-connection-result" class="notice" style="display:none;"></div>
            </div>

            <hr>

            <div class="woosync-connect-section">
                <h3>API Endpoints</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Path</th>
                            <th>Enabled</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $endpoints as $key => $ep ) : ?>
                            <tr>
                                <td><?php echo esc_html( $ep['label'] ); ?></td>
                                <td><code><?php echo esc_html( $ep['path'] ); ?></code></td>
                                <td>
                                    <label class="woosync-toggle">
                                        <input type="checkbox" 
                                               class="woosync-endpoint-toggle"
                                               data-endpoint="<?php echo esc_attr( $key ); ?>"
                                               <?php checked( $ep['enabled'], 1 ); ?>>
                                    </label>
                                </td>
                                <td>
                                    <button type="button" class="button woosync-save-endpoint" 
                                            data-endpoint="<?php echo esc_attr( $key ); ?>">
                                        Save
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <hr>

            <div class="woosync-connect-section">
                <h3>Field Mapping</h3>
                <p>
                    <button type="button" id="woosync-auto-detect" class="button">
                        Auto-Detect Fields
                    </button>
                </p>
                
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>WooCommerce Field</th>
                            <th>Vendor API Field</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $mapping_rules as $wc_key => $rule ) : 
                            $current_value = $mapping[ $wc_key ] ?? '';
                        ?>
                            <tr>
                                <td><?php echo esc_html( $wc_key ); ?></td>
                                <td>
                                    <input type="text" 
                                           class="woosync-field-mapping"
                                           data-wc-field="<?php echo esc_attr( $wc_key ); ?>"
                                           value="<?php echo esc_attr( $current_value ); ?>"
                                           placeholder="<?php echo esc_attr( implode( ' | ', $rule['patterns'] ) ); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="button" id="woosync-save-mapping" class="button button-primary">
                        Save Field Mapping
                    </button>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render the sync log page.
     *
     * @since 1.0.0
     * @return void
     */
    protected function render_log_page() {
        $log = (array) get_option( 'woosync_sync_log', array() );
        $log = array_slice( $log, -100 );
        ?>
        <div class="wrap woosync-wrap">
            <h1 class="wp-heading-inline">Sync Log</h1>
            
            <p>
                <button type="button" id="woosync-clear-log" class="button">
                    Clear Log
                </button>
            </p>

            <div class="woosync-log-container">
                <?php if ( empty( $log ) ) : ?>
                    <p><em>No log entries yet.</em></p>
                <?php else : ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $log as $entry ) : 
                                $parts = explode( '] ', $entry, 2 );
                                $timestamp = isset( $parts[0] ) ? substr( $parts[0], 1 ) : '';
                                $message = $parts[1] ?? $entry;
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $timestamp ); ?></td>
                                    <td><?php echo esc_html( $message ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     * @return void
     */
    protected function render_settings_page() {
        $markup_percent = get_option( 'woosync_markup_percent', 30 );
        $batch_size = get_option( 'woosync_batch_size', 200 );
        $sync_schedule = get_option( 'woosync_sync_schedule', 'daily' );
        ?>
        <div class="wrap woosync-wrap">
            <h1 class="wp-heading-inline">WooSync Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'woosync_settings_group' ); ?>
                
                <h2>Sync Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="woosync_batch_size">Batch Size</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="woosync_batch_size"
                                   name="woosync_batch_size"
                                   value="<?php echo esc_attr( $batch_size ); ?>"
                                   min="1" max="500" step="1"
                                   class="small-text">
                            <p class="description">Number of products to process per batch (1-500).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="woosync_sync_schedule">Sync Schedule</label>
                        </th>
                        <td>
                            <select id="woosync_sync_schedule" name="woosync_sync_schedule">
                                <option value="hourly" <?php selected( $sync_schedule, 'hourly' ); ?>>Hourly</option>
                                <option value="twicedaily" <?php selected( $sync_schedule, 'twicedaily' ); ?>>Twice Daily</option>
                                <option value="daily" <?php selected( $sync_schedule, 'daily' ); ?>>Daily</option>
                                <option value="weekly" <?php selected( $sync_schedule, 'weekly' ); ?>>Weekly</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2>Markup Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="woosync_markup_percent">Default Markup %</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="woosync_markup_percent"
                                   name="woosync_markup_percent"
                                   value="<?php echo esc_attr( $markup_percent ); ?>"
                                   min="0" max="500" step="0.01"
                                   class="small-text">
                            <p class="description">Percentage added to vendor price for customer display.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register plugin settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        register_setting( 'woosync_settings_group', 'woosync_vendors' );
        register_setting( 'woosync_settings_group', 'woosync_batch_size' );
        register_setting( 'woosync_settings_group', 'woosync_sync_schedule' );
        register_setting( 'woosync_settings_group', 'woosync_markup_percent' );
        register_setting( 'woosync_settings_group', 'woosync_field_mapping' );
        register_setting( 'woosync_settings_group', 'woosync_sync_log' );
        register_setting( 'woosync_settings_group', 'woosync_vendor_credentials' );
        register_setting( 'woosync_settings_group', 'woosync_vendor_tiers' );
    }
}
