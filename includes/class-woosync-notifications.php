<?php
/**
 * WooSync Notification System
 * 
 * Handles promotional banners, notifications, and cross-sell functionality
 * for WooSync powered by Mediaplatform.
 *
 * @package WooSync
 * @subpackage Notifications
 */

if (!defined('ABSPATH')) exit;

class WooSync_Notifications {
    
    /**
     * Option key for announcements
     */
    const ANNOUNCEMENTS_OPTION = 'woosync_announcements';
    
    /**
     * Option key for notifications
     */
    const NOTIFICATIONS_OPTION = 'woosync_notifications';
    
    /**
     * Option key for dismissed notifications
     */
    const DISMISSED_OPTION = 'woosync_dismissed';
    
    /**
     * Option key for cross-sell services
     */
    const SERVICES_OPTION = 'woosync_services';
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('woosync_cron_expire_notifications', [$this, 'expire_notifications']);
        
        if (!wp_next_scheduled('woosync_cron_expire_notifications')) {
            wp_schedule_event(time(), 'daily', 'woosync_cron_expire_notifications');
        }
    }
    
    /**
     * Handle form actions
     */
    public function handle_actions() {
        if (!current_user_can('manage_options')) return;
        
        if (isset($_POST['woosync_save_notification']) && wp_verify_nonce($_POST['woosync_nonce'], 'woosync_notifications')) {
            $this->save_notification();
        }
        
        if (isset($_POST['woosync_delete_notification']) && wp_verify_nonce($_POST['woosync_nonce'], 'woosync_notifications')) {
            $this->delete_notification();
        }
        
        if (isset($_POST['woosync_save_service']) && wp_verify_nonce($_POST['woosync_nonce'], 'woosync_notifications')) {
            $this->save_service();
        }
        
        if (isset($_POST['woosync_delete_service']) && wp_verify_nonce($_POST['woosync_nonce'], 'woosync_notifications')) {
            $this->delete_service();
        }
        
        if (isset($_POST['woosync_dismiss_notification']) && wp_verify_nonce($_POST['woosync_nonce'], 'woosync_notifications')) {
            $this->dismiss_notification();
        }
    }
    
    /**
     * Get all announcements
     */
    public function get_announcements() {
        $announcements = get_option(self::ANNOUNCEMENTS_OPTION, []);
        return is_array($announcements) ? $announcements : [];
    }
    
    /**
     * Get all notifications
     */
    public function get_notifications() {
        $notifications = get_option(self::NOTIFICATIONS_OPTION, []);
        return is_array($notifications) ? $notifications : [];
    }
    
    /**
     * Get active notifications
     */
    public function get_active_notifications() {
        $notifications = $this->get_notifications();
        $dismissed = $this->get_dismissed();
        $active = [];
        $now = current_time('mysql');
        
        foreach ($notifications as $id => $notification) {
            if (!in_array($id, $dismissed)) {
                $show_from = $notification['show_from'] ?? '';
                $expiry = $notification['expiry_date'] ?? '';
                
                if ((empty($show_from) || strtotime($show_from) <= strtotime($now)) &&
                    (empty($expiry) || strtotime($expiry) > strtotime($now))) {
                    $active[$id] = $notification;
                }
            }
        }
        
        return $active;
    }
    
    /**
     * Get dismissed notification IDs
     */
    public function get_dismissed() {
        $dismissed = get_user_meta(get_current_user_id(), self::DISMISSED_OPTION, true);
        return is_array($dismissed) ? $dismissed : [];
    }
    
    /**
     * Get cross-sell services
     */
    public function get_services() {
        $services = get_option(self::SERVICES_OPTION, []);
        
        if (empty($services)) {
            $services = $this->get_default_services();
        }
        
        return is_array($services) ? $services : [];
    }
    
    /**
     * Get default Mediaplatform services
     */
    private function get_default_services() {
        return [
            'woocommerce_dev' => [
                'id' => 'woocommerce_dev',
                'title' => 'Custom WooCommerce Development',
                'description' => 'Need custom features or integrations? We build bespoke WooCommerce solutions tailored to your business.',
                'icon' => 'dashicons-cart',
                'link' => 'https://mediaplatform.co.za/services/woocommerce-development/',
                'cta_text' => 'Get a Quote',
                'enabled' => true,
                'featured' => true
            ],
            'api_integration' => [
                'id' => 'api_integration',
                'title' => 'API Integration Services',
                'description' => 'Connect your store to supplier APIs, ERPs, or custom systems with our integration expertise.',
                'icon' => 'dashicons-rest-api',
                'link' => 'https://mediaplatform.co.za/services/api-integrations/',
                'cta_text' => 'Learn More',
                'enabled' => true,
                'featured' => false
            ],
            'wordpress_hosting' => [
                'id' => 'wordpress_hosting',
                'title' => 'Premium WordPress Hosting',
                'description' => 'Fast, secure, and managed WordPress hosting optimized for WooCommerce stores.',
                'icon' => 'dashicons-admin-hosting',
                'link' => 'https://mediaplatform.co.za/services/wordpress-hosting/',
                'cta_text' => 'View Plans',
                'enabled' => true,
                'featured' => false
            ],
            'branding' => [
                'id' => 'branding',
                'title' => 'Branding & Design',
                'description' => 'Professional brand identity, logo design, and marketing materials for your business.',
                'icon' => 'dashicons-art',
                'link' => 'https://mediaplatform.co.za/services/branding/',
                'cta_text' => 'Start Project',
                'enabled' => true,
                'featured' => false
            ]
        ];
    }
    
    /**
     * Save notification
     */
    public function save_notification() {
        $notification = [
            'title' => sanitize_text_field($_POST['notification_title'] ?? ''),
            'message' => sanitize_textarea_field($_POST['notification_message'] ?? ''),
            'link' => esc_url_raw($_POST['notification_link'] ?? ''),
            'cta_text' => sanitize_text_field($_POST['notification_cta'] ?? 'Learn More'),
            'background_color' => sanitize_hex_color($_POST['notification_bg_color'] ?? '#1a1a2e'),
            'text_color' => sanitize_hex_color($_POST['notification_text_color'] ?? '#ffffff'),
            'icon' => sanitize_html_class($_POST['notification_icon'] ?? 'dashicons-info'),
            'type' => sanitize_key($_POST['notification_type'] ?? 'banner'),
            'show_from' => sanitize_text_field($_POST['notification_show_from'] ?? ''),
            'expiry_date' => sanitize_text_field($_POST['notification_expiry'] ?? ''),
            'target_audience' => sanitize_key($_POST['notification_audience'] ?? 'all'),
            'created_at' => current_time('mysql'),
            'views' => 0,
            'clicks' => 0
        ];
        
        $notifications = $this->get_notifications();
        $id = sanitize_key($_POST['notification_id'] ?? time());
        
        if (isset($_POST['notification_id']) && isset($notifications[$_POST['notification_id']])) {
            $notification['views'] = $notifications[$_POST['notification_id']]['views'] ?? 0;
            $notification['clicks'] = $notifications[$_POST['notification_id']]['clicks'] ?? 0;
        }
        
        $notifications[$id] = $notification;
        update_option(self::NOTIFICATIONS_OPTION, $notifications);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Notification saved successfully.</p></div>';
        });
    }
    
    /**
     * Delete notification
     */
    public function delete_notification() {
        $id = sanitize_key($_POST['notification_id'] ?? '');
        $notifications = $this->get_notifications();
        
        if (isset($notifications[$id])) {
            unset($notifications[$id]);
            update_option(self::NOTIFICATIONS_OPTION, $notifications);
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Notification deleted.</p></div>';
            });
        }
    }
    
    /**
     * Dismiss notification
     */
    public function dismiss_notification() {
        $id = sanitize_key($_POST['notification_id'] ?? '');
        $dismissed = $this->get_dismissed();
        
        if (!in_array($id, $dismissed)) {
            $dismissed[] = $id;
            update_user_meta(get_current_user_id(), self::DISMISSED_OPTION, $dismissed);
        }
        
        wp_send_json_success();
    }
    
    /**
     * Save service
     */
    public function save_service() {
        $service = [
            'id' => sanitize_key($_POST['service_id'] ?? time()),
            'title' => sanitize_text_field($_POST['service_title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['service_description'] ?? ''),
            'icon' => sanitize_html_class($_POST['service_icon'] ?? 'dashicons-admin-generic'),
            'link' => esc_url_raw($_POST['service_link'] ?? ''),
            'cta_text' => sanitize_text_field($_POST['service_cta'] ?? 'Learn More'),
            'enabled' => isset($_POST['service_enabled']),
            'featured' => isset($_POST['service_featured'])
        ];
        
        $services = $this->get_services();
        $id = $service['id'];
        
        $services[$id] = $service;
        update_option(self::SERVICES_OPTION, $services);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Service saved successfully.</p></div>';
        });
    }
    
    /**
     * Delete service
     */
    public function delete_service() {
        $id = sanitize_key($_POST['service_id'] ?? '');
        $services = $this->get_services();
        
        if (isset($services[$id])) {
            unset($services[$id]);
            update_option(self::SERVICES_OPTION, $services);
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Service deleted.</p></div>';
            });
        }
    }
    
    /**
     * Push a notification programmatically
     * 
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $link Optional link
     * @param string $cta_text CTA button text
     * @param string $type Type: banner, notice, email
     * @param array $extra Additional options
     * @return bool Success
     */
    public static function push_notification($title, $message, $link = '', $cta_text = 'Learn More', $type = 'banner', $extra = []) {
        $notification = wp_parse_args($extra, [
            'title' => sanitize_text_field($title),
            'message' => sanitize_textarea_field($message),
            'link' => esc_url_raw($link),
            'cta_text' => sanitize_text_field($cta_text),
            'background_color' => $extra['background_color'] ?? '#1a1a2e',
            'text_color' => $extra['text_color'] ?? '#ffffff',
            'icon' => $extra['icon'] ?? 'dashicons-info',
            'type' => sanitize_key($type),
            'show_from' => $extra['show_from'] ?? '',
            'expiry_date' => $extra['expiry_date'] ?? '',
            'target_audience' => $extra['target_audience'] ?? 'all',
            'created_at' => current_time('mysql'),
            'views' => 0,
            'clicks' => 0
        ]);
        
        $notifications = get_option(self::NOTIFICATIONS_OPTION, []);
        if (!is_array($notifications)) $notifications = [];
        
        $id = 'notif_' . time() . '_' . wp_generate_uuid4();
        $notifications[$id] = $notification;
        
        return update_option(self::NOTIFICATIONS_OPTION, $notifications);
    }
    
    /**
     * Expire old notifications via cron
     */
    public function expire_notifications() {
        $notifications = $this->get_notifications();
        $now = current_time('mysql');
        $changed = false;
        
        foreach ($notifications as $id => $notification) {
            $expiry = $notification['expiry_date'] ?? '';
            if (!empty($expiry) && strtotime($expiry) < strtotime($now)) {
                unset($notifications[$id]);
                $changed = true;
            }
        }
        
        if ($changed) {
            update_option(self::NOTIFICATIONS_OPTION, $notifications);
        }
    }
    
    /**
     * Render notification banner
     */
    public function render_banner($notification) {
        $bg_color = esc_attr($notification['background_color'] ?? '#1a1a2e');
        $text_color = esc_attr($notification['text_color'] ?? '#ffffff');
        $id = esc_attr(key($notification) ?: 'banner');
        
        ?>
        <div class="woosync-notification-banner" style="background-color: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>;" data-notification-id="<?php echo $id; ?>">
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
    }
    
    /**
     * Render services cards
     */
    public function render_services($featured_only = false) {
        $services = $this->get_services();
        $output = [];
        
        foreach ($services as $service) {
            if (!$service['enabled']) continue;
            if ($featured_only && !$service['featured']) continue;
            
            $output[] = $service;
        }
        
        if (empty($output)) return;
        
        foreach ($output as $service):
            $icon_class = sanitize_html_class($service['icon'] ?? 'dashicons-admin-generic');
            ?>
            <div class="woosync-service-card">
                <div class="woosync-service-icon">
                    <span class="dashicons <?php echo $icon_class; ?>"></span>
                </div>
                <h4><?php echo esc_html($service['title']); ?></h4>
                <p><?php echo esc_html($service['description']); ?></p>
                <?php if (!empty($service['link'])): ?>
                <a href="<?php echo esc_url($service['link']); ?>" class="woosync-service-cta" target="_blank" rel="noopener">
                    <?php echo esc_html($service['cta_text'] ?? 'Learn More'); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php
        endforeach;
    }
}

// Initialize notifications
WooSync_Notifications::get_instance();
