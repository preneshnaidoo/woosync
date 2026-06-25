# Changelog

All notable changes to WooSync are documented in this file.

## [3.1.0] - 2026-07-15

### Added
- **Product Optimizer Tab**: New top-level tab for optimizing products for Google Shopping, Facebook/Meta Shop, and general SEO
- **SEO Product Scanner**: Scan all WooCommerce products and score them 0-100 on title quality, description, images, alt text, price, brand, taxonomy, and schema
- **Quick Fix Actions**: Batch and per-product fixes including auto-generate descriptions, optimize titles, add alt text, set brands, and map Google taxonomy
- **Plugin Recommendations Panel**: Curated list of must-have plugins (Yoast SEO, Rank Math, WooCommerce Google Product Feed, Facebook for WooCommerce, Social Rocket, Instagram Shopping) with WordPress.org links and "Suggested by WooSync" badges
- **Tutorial Links Section**: Curated links to Google Merchant Center, WooCommerce Google Shopping feed, Facebook Shop, Instagram Shopping, and Yoast SEO tutorials
- **Schema Markup Generator**: Generate JSON-LD Product schema with brand, SKU, GTIN, MPN, price, availability, condition, and aggregate ratings
- **Product Quality Score Dashboard**: Per-product and overall store scores with color-coded indicators (red/yellow/green) and category breakdown
- **CSV Export**: Export product quality report as CSV with all SEO checks

### Changed
- Added Product Optimizer as the 5th tab in the WooSync navigation
- Enhanced CSS with optimizer-specific styles (score cards, plugin cards, tutorial cards, resource links)

## [3.2.0] - 2026-07-20

### Added
- **Promo Share Tab (Tab 6)**: Marketing hub for promotional products with social sharing capabilities
  - Grid display of promotional products (sale, clearance, featured, deal of the day)
  - Social share buttons: Facebook, Twitter/X, LinkedIn, WhatsApp, Pinterest, Copy Link
  - Discount badges with percentage off
  - Filter by promotion type (All, Clearance, Sale, Featured, Deal of the Day)
  - 1-hour caching of promotional products
  - One-click sharing with pre-formatted text
- **Tiered Pricing Tab (Tab 7)**: Complete pricing management system
  - Enable/disable toggle with default markup settings
  - Role-based tiers: inline editing, enable/disable per role
  - Individual customer pricing: search, add, edit, remove
  - Customer tier labels: Gold, Silver, Bronze, VIP
  - Bulk CSV import/export for customer pricing
  - Price preview: select product + customer → shows margin breakdown
  - Pricing rules: minimum margin floor, maximum discount %, clearance minimum
  - WooCommerce hooks to apply markup on price display
  - Product meta box for base price override
- **Supplier Tier Display (in Connect & Map tab)**: Vendor tier visualization
  - Tier card at top of Connect & Map showing Gold/Silver/Bronze/Platinum/Standard
  - Color-coded badges matching tier level
  - Manual tier override in settings
  - Tier pricing preview in product panel
  - Tier savings widget showing total savings across products
  - Tier pricing benefits section in Sync Log

### Changed
- Added Promo Share and Pricing tabs to WooSync navigation
- Enhanced admin.css with new styles for promo cards, tier badges, pricing widgets
- Added 3 new JavaScript files: promo-share.js, pricing.js, tier-settings.js
- Updated WooCommerce price filters to apply tiered pricing
- Added product meta box for WooSync base price override

### PHP Functions Added
- `woosync_get_user_markup($user_id)` - Get user markup (priority: individual > role > default)
- `woosync_get_user_role_markup($roles)` - Get role-based markup
- `woosync_get_product_base_price($product_id, $vendor_id)` - Get supplier base price
- `woosync_calculate_display_price($product_id, $user_id)` - Calculate display price with markup
- `woosync_format_markup_price($price, $markup)` - Apply markup to price
- `woosync_get_tier_markup($tier)` - Get markup percentage for tier level
- `woosync_calculate_tier_savings($vendor)` - Calculate tier savings across products

### AJAX Handlers Added
- Promo Share: `woosync_get_promos`, `woosync_refresh_promos`
- Pricing: `woosync_save_pricing_settings`, `woosync_save_role_markup`, `woosync_toggle_role_tier`, `woosync_search_customers`, `woosync_add_customer_pricing`, `woosync_remove_customer_pricing`, `woosync_update_customer_markup`, `woosync_save_customer_pricing`, `woosync_get_customer_pricing`, `woosync_get_price_preview`, `woosync_save_pricing_rules`, `woosync_apply_markup_to_all`
- Tier: `woosync_refresh_tier_status`, `woosync_save_manual_tier`, `woosync_get_tier_benefits`, `woosync_get_tier_savings`

### WooCommerce Hooks
- `woocommerce_product_get_price` filter to apply tiered pricing
- `woocommerce_product_get_sale_price` filter to apply tiered pricing

### Options Added
- `woosync_tiered_pricing_enabled`
- `woosync_default_markup`
- `woosync_role_markups`
- `woosync_user_markups`
- `woosync_minimum_margin`
- `woosync_maximum_discount`
- `woosync_clearance_minimum`
- `woosync_show_pricing_to_logged_out`

## [3.1.0] - 2026-07-15

### Added
- **Product Optimizer Tab**: New top-level tab for optimizing products for Google Shopping, Facebook/Meta Shop, and general SEO
- **SEO Product Scanner**: Scan all WooCommerce products and score them 0-100 on title quality, description, images, alt text, price, brand, taxonomy, and schema
- **Quick Fix Actions**: Batch and per-product fixes including auto-generate descriptions, optimize titles, add alt text, set brands, and map Google taxonomy
- **Plugin Recommendations Panel**: Curated list of must-have plugins (Yoast SEO, Rank Math, WooCommerce Google Product Feed, Facebook for WooCommerce, Social Rocket, Instagram Shopping) with WordPress.org links and "Suggested by WooSync" badges
- **Tutorial Links Section**: Curated links to Google Merchant Center, WooCommerce Google Shopping feed, Facebook Shop, Instagram Shopping, and Yoast SEO tutorials
- **Schema Markup Generator**: Generate JSON-LD Product schema with brand, SKU, GTIN, MPN, price, availability, condition, and aggregate ratings
- **Product Quality Score Dashboard**: Per-product and overall store scores with color-coded indicators (red/yellow/green) and category breakdown
- **CSV Export**: Export product quality report as CSV with all SEO checks

### Changed
- Added Product Optimizer as the 5th tab in the WooSync navigation
- Enhanced CSS with optimizer-specific styles (score cards, plugin cards, tutorial cards, resource links)

## [3.0.0] - 2026-06-25

### Added
- **Multi-Vendor Architecture**: Full support for multiple vendor connections with independent credentials and settings
- **Smart Auto-Mapping System**: Intelligent field matching with fuzzy matching algorithms and confidence scores
- **Visual Progress Tracking**: Real-time progress bars with batch processing status
- **Onboarding Wizard**: Step-by-step setup process for new vendors
- **Dashboard Analytics**: Quick stats, sync history chart, and recent activity log
- **Promotional Notification System**: Admin can create and manage notifications and banners
- **Cross-Sell Services Section**: Mediaplatform service cards displayed in plugin dashboard
- **Barron Vendor Template**: Pre-configured template for Barron API integration
- **Giftwrap Vendor Template**: Pre-configured template for Giftwrap API integration

### Changed
- Plugin renamed from "Amrod Sync" to "WooSync"
- Text domain changed from `amrod-sync` to `woosync`
- All function prefixes changed from `amrod_` to `woosync_`
- All option names changed from `amrod_` to `woosync_`
- Complete UI redesign with Bootstrap 5 and dark theme
- Improved error handling and logging
- Updated plugin header for WordPress.org standards

### Security
- All user inputs properly sanitized and escaped
- Nonce verification on all AJAX requests
- User capability checks (`manage_options`) on all admin functions
- No hardcoded credentials or API keys

### Backwards Compatibility
- Existing Amrod vendor data automatically migrated on activation
- Legacy options preserved and accessible
- Backwards compatibility aliases defined for all major constants

## [2.0.0] - 2026-02-20

### Added
- Field mapping configuration panel
- Auto-detect fields from Amrod API
- Manual field mapping override
- Batch size configuration
- Sync mode selection (Full/Batch/Resume)
- Progress bar with percentage
- Sync history chart
- API endpoints manager
- Collapsible settings panels
- Token display (masked)
- Advanced options (batch size, schedule, log retention)
- WP-CLI commands: `run`, `status`, `clear-log`

### Changed
- Bootstrap 5 UI
- Improved error handling
- Better logging
- Responsive design

## [1.0.0.1] - 2026-02-13

### Changed
- Removed stored-password fallback; API password must be provided via `AMROD_API_PASSWORD` environment variable or `wp-config.php` constant
- Added transient token caching and masked token display in admin
- Implemented Action Scheduler background batching (with synchronous fallback)
- Added WP-CLI commands: `run`, `status`, `clear-log`
- Added sync log, Status admin submenu, uninstall cleanup, and README updates
- Various security and error-handling improvements

### Fixed
- Syntax and parse errors
- Improved input sanitisation and JSON/HTTP error handling
