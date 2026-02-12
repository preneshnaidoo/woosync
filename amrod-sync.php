<?php
/*
Plugin Name: Amrod WooCommerce Sync
Description: Sync Amrod products, stock, branding, categories, and colours into WooCommerce. Manual sync button with batched background processing (Action Scheduler) and WP‑CLI support.
Version: 1.0.0.1
Author: Mediaplatform
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// --- Guard: Require WooCommerce ---
if ( ! class_exists( 'WooCommerce' ) ) {
    add_action('admin_notices', function() {
        echo "<div class='notice notice-error'><p>❌ WooCommerce must be active for Amrod Sync to work.</p></div>";
    });
    return;
}

// --- Register Settings ---
function amrod_sync_register_settings() {
    register_setting('amrod_sync_options_group', 'amrod_username', 'sanitize_text_field');
    register_setting('amrod_sync_options_group', 'amrod_customer_code', 'sanitize_text_field');
    register_setting('amrod_sync_options_group', 'amrod_docs_url', 'esc_url_raw');
}
add_action('admin_init', 'amrod_sync_register_settings');

/**
 * Return the configured Amrod API password.
 * Priority: environment variable AMROD_API_PASSWORD -> constant AMROD_API_PASSWORD
 * Stored option support has been removed for security — set via env or wp-config.php.
 */
function amrod_get_password() {
    $env = getenv('AMROD_API_PASSWORD');
    if ($env !== false && $env !== '') {
        return $env;
    }
    if (defined('AMROD_API_PASSWORD') && AMROD_API_PASSWORD) {
        return AMROD_API_PASSWORD;
    }
    return '';
} 

// --- Admin Menu ---
function amrod_sync_menu() {
    add_menu_page(
        'Amrod Sync',                // Page title
        'Amrod Sync',                // Menu title
        'manage_options',            // Capability
        'amrod-sync',                // Menu slug
        'amrod_sync_settings_page',  // Callback function
        'dashicons-update',          // Icon
        56                           // Position
    );
}
add_action('admin_menu', 'amrod_sync_menu');
add_action('admin_menu', 'amrod_sync_status_submenu');

// --- Admin Status submenu (shows log & job info)
function amrod_sync_status_submenu() {
    add_submenu_page(
        'amrod-sync',                // parent slug (settings page)
        'Amrod Sync Status',         // page title
        'Status',                    // menu title
        'manage_options',            // capability
        'amrod-sync-status',         // menu slug
        'amrod_sync_status_page'     // callback
    );
}

function amrod_sync_status_page() {
    if (! current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $last = get_option('amrod_last_sync');
    $total = (int) get_option('amrod_total_products', 0);
            $last_token = get_option('amrod_last_token');
    echo '<div class="wrap"><h1>Amrod Sync — Status</h1>';
    echo '<p><strong>Last sync:</strong> ' . esc_html($last) . '</p>';
    echo '<p><strong>Total products synced (accumulated):</strong> ' . esc_html($total) . '</p>';
    echo '<p><strong>Last token (masked):</strong> ' . esc_html($last_token) . '</p>';

    if (function_exists('as_get_scheduled_actions')) {
        $count = count(as_get_scheduled_actions(['hook' => 'amrod_sync_batch']));
        echo '<p><strong>Action Scheduler queued batches:</strong> ' . esc_html($count) . '</p>';
    } else {
        echo '<p><strong>Action Scheduler:</strong> not available — plugin will run synchronously when you click <em>Run Sync Now</em>.</p>';
    }

    echo '<h2>Recent log</h2>';
    $log = array_reverse((array) get_option('amrod_sync_log', []));

    if (empty($log)) {
        echo '<p><em>No log entries yet.</em></p>';
    } else {
        echo '<pre style="background:#fff;border:1px solid #eee;padding:8px;max-height:400px;overflow:auto;">' . esc_html(implode("\n", array_slice($log, 0, 200))) . '</pre>';
    }

    echo '</div>';
}

// --- Settings Link in Plugins Screen ---
function amrod_sync_plugin_action_links($links) {
    $settings_link = '<a href="admin.php?page=amrod-sync">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'amrod_sync_plugin_action_links');

// --- Settings Page ---
function amrod_sync_settings_page() {
    ?>
    <div class="wrap">
        <h1>Amrod Sync Settings (v1.1)</h1>
        <?php settings_errors('amrod_sync_options_group'); ?>

        <?php if ($errors = get_option('amrod_errors')): ?>

            <div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_html($errors); ?></p></div>
        <?php elseif ($last = get_option('amrod_last_sync')): ?>
            <div class="notice notice-success is-dismissible"><p>✅ Last sync completed at <?php echo esc_html($last); ?></p></div>
        <?php endif; ?>

        <h2>Step 1: Credentials (environment preferred)</h2>
        <p style="max-width:60em">Request a Vendor API <strong>username</strong> and <strong>Customer Code</strong> from Amrod support. For the API password we <strong>strongly recommend</strong> using an environment variable or wp-config constant (preferred for security): <code>AMROD_API_PASSWORD</code>.
        <br><strong>Note:</strong> password must be provided via the environment variable or wp-config constant; the plugin no longer accepts or uses a stored password option.</p>
        <form method="post" action="options.php">
            <?php settings_fields('amrod_sync_options_group'); ?>
            <table class="form-table">
                <tr><th>Username <span title="Your Amrod API username">?</span></th><td><input type="text" name="amrod_username" value="<?php echo esc_attr(get_option('amrod_username')); ?>" /></td></tr>
                                        <tr><th>Password</th><td><em>Managed via environment variable <code>AMROD_API_PASSWORD</code> or wp-config constant. The plugin does not accept a password in the UI.</em></td></tr>
                <tr><th>Customer Code <span title="Provided by Amrod, usually 6 characters">?</span></th><td><input type="text" name="amrod_customer_code" value="<?php echo esc_attr(get_option('amrod_customer_code')); ?>" /></td></tr>
                <tr><th>Docs URL <span title="Link to latest Amrod API docs">?</span></th><td><input type="text" name="amrod_docs_url" value="<?php echo esc_attr(get_option('amrod_docs_url', 'https://newapidocs.amrod.co.za/#intro')); ?>" /></td></tr>
            </table>

            <h2>Step 2: Save Credentials</h2>
            <p>Click "Save Changes" to store your Amrod credentials. This plugin performs manual syncs only — use the buttons below to run a sync.</p>

            <?php submit_button(); ?>
        </form>

        <h2>Step 3: Run Manual Sync</h2>
        <p>Click <strong>Fetch Token</strong> to validate credentials and cache an access token for syncing. The token is cached transiently and shown masked for security.</p>
        <form method="post">
            <?php wp_nonce_field('amrod_sync_action','amrod_sync_nonce'); ?>
            <input type="submit" name="run_sync" class="button button-primary" value="Run Sync Now">
            <input type="submit" name="get_token" class="button" value="Fetch Token">
            <span style="margin-left:1em;color:#666">Last token: <code><?php echo esc_html(get_option('amrod_last_token')); ?></code></span>
        </form>

        <h2>Step 4: Stats</h2>
        <p>Last Sync: <?php echo esc_html(get_option('amrod_last_sync')); ?></p>
        <p>Total Products Synced: <?php echo esc_html(get_option('amrod_total_products')); ?></p>

        <h3>Recent sync log</h3>
        <?php $log = array_reverse((array) get_option('amrod_sync_log', [])); ?>
        <?php if (empty($log)): ?>
            <p><em>No log entries yet.</em></p>
        <?php else: ?>
            <ul style="font-family:monospace;background:#fff;padding:8px;border:1px solid #eee;max-height:200px;overflow:auto;">
                <?php foreach (array_slice($log, 0, 10) as $l): ?>
                    <li><?php echo esc_html($l); ?></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" style="margin-top:.5em">
                <?php wp_nonce_field('amrod_sync_action','amrod_sync_nonce'); ?>
                <input type="hidden" name="clear_sync_log" value="1" />
                <input type="submit" class="button" value="Clear sync log">
            </form>
        <?php endif; ?>

        <h2>Documentation</h2>
        <p>Plugin Version: 1.1</p>
        <p><a href="<?php echo esc_url(get_option('amrod_docs_url')); ?>" target="_blank">View Latest Amrod Docs</a></p>
    </div>
    <?php
}

// --- Token ---
function amrod_mask_token_for_display($token) {
    if (! $token) return '';
    return substr($token, 0, 6) . '...' . substr($token, -6);
}

function amrod_get_token() {
    // return cached token if available
    $cached = get_transient('amrod_token');
    if ($cached) {
        return $cached;
    }

    $auth_url = "https://identity.amrod.co.za/VendorLogin";
    $payload = json_encode([
        'username'     => get_option('amrod_username'),
        'password'     => amrod_get_password(),
        'CustomerCode' => get_option('amrod_customer_code')
    ]);

    $response = wp_remote_post($auth_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => $payload,
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        update_option('amrod_errors', 'Token request failed: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body_raw = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        update_option('amrod_errors', "Token endpoint returned HTTP {$code}");
        return false;
    }

    $body = json_decode($body_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        update_option('amrod_errors', 'Token: invalid JSON: ' . json_last_error_msg());
        return false;
    }

    $token = $body['token'] ?? false;
    if ($token) {
        // cache for 55 minutes (avoid repeated auth calls)
        set_transient('amrod_token', $token, 55 * MINUTE_IN_SECONDS);
        // store masked token for admin display only
        update_option('amrod_last_token', amrod_mask_token_for_display($token));
    }

    return $token;
}  

// --- Endpoint Helper ---
function amrod_get_endpoint($token, $endpoint, $retries = 2) {
    $url = "https://vendorapi.amrod.co.za/" . ltrim($endpoint, '/');

    for ($attempt = 0; $attempt <= $retries; $attempt++) {
        $response = wp_remote_get($url, [
            'headers' => ["Authorization" => "Bearer $token"],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            $err = $response->get_error_message();
            // if final attempt, record error
            if ($attempt === $retries) {
                update_option('amrod_errors', "Endpoint error ({$endpoint}): {$err}");
                return false;
            }
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code === 200) {
                $decoded = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // clear previous errors on success
                    update_option('amrod_errors', '');
                    return $decoded;
                }

                // invalid JSON
                if ($attempt === $retries) {
                    update_option('amrod_errors', "Invalid JSON from {$endpoint}: " . json_last_error_msg());
                    return false;
                }
            } else {
                if ($attempt === $retries) {
                    update_option('amrod_errors', "Endpoint {$endpoint} returned HTTP {$code}");
                    return false;
                }
            }
        }

        // exponential backoff before retrying
        sleep((int) pow(2, $attempt));
    }

    return false;
}



// --- Sync logging helper ---
function amrod_sync_log($message) {
    $log = (array) get_option('amrod_sync_log', []);
    $log[] = '[' . current_time('mysql') . '] ' . $message;
    // keep last 100 messages
    $log = array_slice($log, -100);
    update_option('amrod_sync_log', $log);
}

// Process a single batch of products and return array with status
function amrod_sync_process_batch($offset = 0, $batch_size = 200) {
    $token = amrod_get_token();
    if (! $token) {
        amrod_sync_log('Failed to obtain token.');
        return ['success' => false, 'processed' => 0, 'more' => false];
    }

    $products = amrod_get_endpoint($token, 'products');
    if ($products === false || ! is_array($products)) {
        amrod_sync_log('Products endpoint did not return valid data.');
        return ['success' => false, 'processed' => 0, 'more' => false];
    }

    $total = count($products);
    $batch = array_slice($products, $offset, $batch_size);
    $processed = 0;

    foreach ($batch as $p) {
        if (! isset($p['ProductCode'])) continue;

        $existing_id = wc_get_product_id_by_sku($p['ProductCode']);
        $wc_product = $existing_id ? wc_get_product($existing_id) : new WC_Product();
        $wc_product->set_sku($p['ProductCode']);
        $wc_product->set_name($p['Description'] ?? '');
        $wc_product->set_regular_price($p['Price'] ?? 0);
        $wc_product->set_description($p['LongDescription'] ?? '');
        $wc_product->save();
        $processed++;
    }

    // update counters (accumulate)
    $prev_total = (int) get_option('amrod_total_products', 0);
    update_option('amrod_total_products', $prev_total + $processed);
    update_option('amrod_last_sync', current_time('mysql'));

    amrod_sync_log("Processed batch: offset={$offset}, count={$processed}");

    $next_offset = $offset + $batch_size;
    $more = $next_offset < $total;

    return ['success' => true, 'processed' => $processed, 'more' => $more, 'next_offset' => $next_offset, 'total' => $total];
}

// Background batch handler using Action Scheduler (if available)
add_action('amrod_sync_batch', 'amrod_sync_batch_handler');
function amrod_sync_batch_handler($args = []) {
    $offset = isset($args['offset']) ? (int) $args['offset'] : 0;
    $batch_size = isset($args['batch_size']) ? (int) $args['batch_size'] : 200;

    $res = amrod_sync_process_batch($offset, $batch_size);

    if ($res['success'] && $res['more']) {
        $next = ['offset' => $res['next_offset'], 'batch_size' => $batch_size];
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('amrod_sync_batch', $next);
        } else {
            // fallback to scheduling a single WP action shortly
            wp_schedule_single_event(time() + 2, 'amrod_sync_batch', [$next]);
        }
    }
}

// Backwards-compatible wrapper: runs synchronously (small catalogs) or enqueues background work
function amrod_sync_products($batch_size = 200, $offset = 0) {
    // prefer Action Scheduler background processing when available
    if (function_exists('as_enqueue_async_action')) {
        // reset counters
        update_option('amrod_total_products', 0);
        as_enqueue_async_action('amrod_sync_batch', ['offset' => 0, 'batch_size' => $batch_size]);
        amrod_sync_log('Enqueued background sync (Action Scheduler).');
        return true;
    }

    // fallback: synchronous batched processing to avoid PHP timeout
    $offset = (int) $offset;
    $processed_total = 0;
    do {
        $res = amrod_sync_process_batch($offset, $batch_size);
        if (! $res['success']) break;
        $processed_total += $res['processed'];
        $offset = $res['next_offset'];
    } while ($res['more']);

    amrod_sync_log("Synchronous sync completed. processed={$processed_total}");
    return true;
}

// --- Manual Actions ---
// Manual POST actions are handled securely by `amrod_handle_post_actions` (nonce + capability checks).

// Handle manual button posts (nonce + capability checks)
add_action('admin_init', 'amrod_handle_post_actions');
function amrod_handle_post_actions() {
    if (empty($_POST) || ! isset($_POST['amrod_sync_nonce'])) {
        return;
    }
    if (! current_user_can('manage_options')) {
        return;
    }
    if (! wp_verify_nonce($_POST['amrod_sync_nonce'], 'amrod_sync_action')) {
        return;
    }

    if (isset($_POST['get_token'])) {
        $token = amrod_get_token();
        if ($token) {
            // amrod_get_token already stores a masked display value
            update_option('amrod_errors', '');
            add_settings_error('amrod_sync_options_group', 'token_ok', 'Token fetched', 'updated');
        } else {
            add_settings_error('amrod_sync_options_group', 'token_failed', 'Failed to fetch token', 'error');
        }
    }

    if (! empty($_POST['clear_sync_log'])) {
        update_option('amrod_sync_log', []);
        add_settings_error('amrod_sync_options_group', 'log_cleared', 'Sync log cleared', 'updated');
    }

    if (isset($_POST['run_sync'])) {
        // enqueue background batch (Action Scheduler) or run batched fallback
        amrod_sync_products();
        add_settings_error('amrod_sync_options_group', 'sync_ok', 'Manual sync started/completed (check log).', 'updated');
    }
}

/**
 * WP‑CLI commands for Amrod Sync
 *
 * Commands:
 *   wp amrod-sync run [--batch=<n>] [--background]
 *   wp amrod-sync status
 *   wp amrod-sync clear-log
 */
if ( defined('WP_CLI') && WP_CLI ) {
    class Amrod_Sync_CLI {
        /**
         * Run the Amrod product sync.
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function run( $args, $assoc_args ) {
            $batch = isset($assoc_args['batch']) ? (int) $assoc_args['batch'] : 200;
            $background = ! empty($assoc_args['background']);

            if ($background && function_exists('as_enqueue_async_action')) {
                // enqueue and exit
                update_option('amrod_total_products', 0);
                as_enqueue_async_action('amrod_sync_batch', ['offset' => 0, 'batch_size' => $batch]);
                WP_CLI::success('Enqueued background sync (Action Scheduler).');
                return;
            }

            // synchronous fallback
            WP_CLI::log('Starting synchronous sync...');
            amrod_sync_products($batch, 0);
            WP_CLI::success('Sync finished. Check `wp amrod-sync status` for details.');
        }

        /**
         * Show status and recent log.
         */
        public function status() {
            $last = get_option('amrod_last_sync');
            $total = (int) get_option('amrod_total_products', 0);
            $log = (array) get_option('amrod_sync_log', []);

            WP_CLI::print_value(['last_sync' => $last, 'total_products' => $total, 'log_tail' => array_slice($log, -10)]);
        }

        /**
         * Clear the sync log.
         */
        public function clear_log() {
            update_option('amrod_sync_log', []);
            WP_CLI::success('Sync log cleared.');
        }
    }

    WP_CLI::add_command('amrod-sync', 'Amrod_Sync_CLI');
}

/**
 * Optional uninstall: remove plugin options when plugin is deleted.
 * If you prefer to keep data, remove this hook.
 */
register_uninstall_hook(__FILE__, function() {
    $keys = [
        'amrod_username', 'amrod_customer_code', 'amrod_docs_url',
        'amrod_last_sync', 'amrod_total_products', 'amrod_errors', 'amrod_sync_log', 'amrod_last_token'
    ];
    foreach ($keys as $k) {
        delete_option($k);
    }
});

