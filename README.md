# WooSync - Multi-Vendor WooCommerce Sync

Connect your WooCommerce store to multiple suppliers and automatically sync product data including pricing, stock levels, images, and more.

## Description

WooSync is an enterprise-grade WordPress plugin that synchronizes product data from multiple suppliers (vendors) into WooCommerce. Originally developed for Amrod, WooSync has been expanded to support any supplier API including Barron, Giftwrap, and custom integrations.

### Key Features

*   **Multi-Vendor Support** - Connect multiple suppliers with separate credentials, endpoints, and field mappings for each vendor.
*   **Smart Auto-Mapping** - Intelligent field matching with confidence scores automatically maps supplier fields to WooCommerce products.
*   **Flexible Sync Modes** - Full sync for complete catalog updates or incremental sync for only changed products.
*   **Visual Progress Tracking** - Real-time progress bars and detailed sync logs keep you informed during synchronization.
*   **Automated Scheduling** - Set up cron jobs for hands-free periodic syncing.
*   **Onboarding Wizard** - Step-by-step setup guides you through connecting your first vendor.
*   **Built-in Auto-Updates** - Automatic update notifications with manual or automatic update modes, automatic backups, and rollback capability.

### Supported Vendors

| Vendor | Auth Type | Description |
|--------|-----------|-------------|
| Amrod | Vendor Login | Promotional merchandise and branded items |
| Barron | API Key | Custom apparel and corporate wear |
| Giftwrap | Bearer Token | Corporate gifting and promotional packs |
| Custom | Any | Build your own vendor template |

## Installation

1. Upload the `woosync` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **WooSync** in the admin menu
4. Complete the onboarding wizard to connect your first vendor

### Requirements

*   WordPress 5.6 or higher
*   PHP 7.4 or higher
*   WooCommerce 5.0 or higher
*   SSL certificate (required for API connections)

## Frequently Asked Questions

### How does WooSync sync products?

WooSync authenticates with your supplier's API, fetches product data, maps the fields to WooCommerce format, and creates or updates products in your store. Products are matched by SKU.

### Can I sync multiple suppliers?

Yes. WooSync supports multiple vendor connections. Each vendor has its own credentials, API endpoints, and field mappings.

### How often can I sync?

You can run manual syncs anytime. For automated syncing, set up a cron job (recommended: every 30 minutes).

### What happens to existing products?

WooSync updates existing products by matching SKU. New products are created automatically. You control which fields are updated.

### Does WooSync affect my server performance?

WooSync uses batch processing (configurable 50-500 products per batch) to minimize server load. Each batch is processed separately with progress tracking.

## Changelog

See CHANGELOG.md for complete version history.

## Upgrade Notice

### 3.0.0
This is a major rewrite. If upgrading from WooSync 2.x, your Amrod vendor will be automatically migrated. Review field mappings after upgrade.

## Screenshots

1.  WooSync Dashboard with quick stats and recent activity
2.  Vendor management interface
3.  Onboarding wizard for new vendors
4.  Field mapping configuration with auto-detect
5.  Sync progress and real-time updates
6.  Sync log with filtering
7.  Settings and configuration
8.  Promotional notifications system

## Tags

woocommerce, sync, products, supplier, amrod, barron, dropshipping, inventory, woocommerce-plugin, product-sync, multi-vendor, ecommerce
