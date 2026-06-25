# Changelog - WooSync (Amrod WooCommerce Sync)

## [3.4.1] - 2026-06-25

### Fix: Dynamic Credential Fields Per Vendor, SMD Auth Support, Contextual Help Panels

**PROBLEM:**
The wizard credential form (Step 2 — Connect) showed Amrod fields (Username, Password, Customer Code) for ALL vendors including SMD which uses completely different authentication. This was very confusing for users.

**SOLUTION:**
Implemented dynamic credential form that shows DIFFERENT fields based on which vendor is selected.

**Dynamic Credential Form (Step 2 — Connect):**
- When user selects a vendor in Step 1, the credential form fields change to match that vendor's auth type
- Field groups per vendor:

  **Amrod (Vendor Login):**
  - Auth URL (pre-filled: https://identity.amrod.co.za)
  - Username *
  - Password *
  - Customer Code *
  - Support panel: support@amrod.co.za | docs: newapidocs.amrod.co.za

  **SMD (Bearer Token + ClientAccessKey):**
  - API Base URL (pre-filled: https://api.smdtechnologies.com/v1/)
  - Bearer Token * (with placeholder: "Your API token from SMD")
  - Client Access Key * (with placeholder: "Your ClientAccessKey from SMD")
  - Support panel: (in progress — contact SMD directly)
  - Note: "SMD uses a Bearer Token + ClientAccessKey. You'll receive these via email from SMD."

  **Barron (Custom Auth):**
  - API Base URL *
  - API Key (optional)
  - Support panel: (in progress — contact Barron directly)

  **Custom (any auth type):**
  - API Base URL *
  - Auth URL (optional)
  - Username (optional)
  - Password (optional)
  - Bearer Token (optional)
  - API Key (optional)
  - Customer Code (optional)
  - Note: "Fill in only what your API requires"

**Vendor Credential Schemas (PHP):**
- New `amrod_get_vendor_credential_schemas()` function defines credential fields per vendor
- Each schema includes: auth_type, label, description, fields array, support info, test_type
- New `amrod_get_credential_schema($vendor_id)` helper function
- Schemas passed to JavaScript via `wp_localize_script`

**AJAX Handlers Updated:**
- `amrod_ajax_test_connection()` now handles all vendor types:
  - vendor_login (Amrod): POST to Auth URL with username/password/customer_code
  - bearer_key (SMD): GET /products with Bearer + ClientAccessKey headers
  - custom: try GET on API Base URL with provided auth
- `amrod_ajax_save_credentials_simple()` now:
  - Validates only required fields for selected vendor type
  - Stores credentials in `woosync_vendor_credentials` option (per-vendor)
  - Maintains backward compatibility for Amrod (updates legacy options)
  - Stores auth_type in vendor record
- New `amrod_ajax_get_credential_schema()` returns schema for selected vendor

**Wizard.js Updates:**
- `renderCredentialsForm()` renders fields dynamically from schema
- `renderSupportPanel()` shows vendor-specific support info with color-coded headers
- Vendor auth badges shown in Step 1 vendor selection
- All form fields sent to AJAX handlers dynamically
- Password toggle buttons for all password fields
- Vendor-specific support panel colors (Amrod=indigo, SMD=purple, Barron=amber, Custom=gray)

**CSS Updates:**
- Dynamic credential field styles
- Vendor support panel color variations
- Vendor auth badge styles
- Password field styles
- Vendor auth note styles
- Responsive wizard styles

**Technical Changes:**
- New `amrod_get_vendor_credential_schemas()` function (vendor credential field definitions)
- New `amrod_get_credential_schema($vendor_id)` helper function
- New `amrod_ajax_get_credential_schema` AJAX handler
- Updated `amrod_ajax_test_connection` to handle vendor_login, bearer_key, custom auth types
- Updated `amrod_ajax_save_credentials_simple` with vendor-specific validation and storage
- Updated `wp_localize_script` for amrod-wizard-js to include vendorCredentialSchemas
- Updated SMD vendor template with api_base_url and auth_type
- New `woosync_vendor_credentials` option for per-vendor credential storage
- Version bump to 3.4.1

**Files Changed:**
- `woosync.php` — added credential schemas, updated AJAX handlers, updated localize_script
- `assets/js/wizard.js` — dynamic field rendering, vendor-specific support panels
- `assets/css/admin.css` — credential field styles, support panel variations
- `CHANGELOG.md` — updated with v3.4.1 changelog

# Changelog - WooSync (Amrod WooCommerce Sync)

## [3.4.0] - 2026-06-25

### Feature: UX Overhaul — Clean Wizard, Vendor Support Panels, Professional Colours, Auto-Update Fix

**Onboarding Wizard — Remove Pre-filled Vendors:**
- New clean onboarding wizard replaces chaotic vendor setup
- Step 1: Welcome screen with logo, tagline, and "Get Started" button only
- Step 2: "No vendors connected" empty state with "Add Your First Vendor" grid
- Step 3: Clean credential form with contextual support panel
- Wizard triggered on first activation, skippable
- Stored in localStorage to not show again after completion
- Uses Bootstrap 5 modal with custom professional styling

**Vendor Support Panel:**
- When selecting a vendor type, contextual support panel appears:
  ```
  📋 Need help getting Amrod API credentials?
  📧 Email: support@amrod.co.za
  📖 Docs: newapidocs.amrod.co.za
  ```
- Support details stored per vendor in `amrod_get_vendor_templates()` array
- Amrod support: email, docs URL, auth URL, API base URL
- SMD support: placeholder until user provides
- After credentials saved → "Vendor Connected Successfully" animated state

**Help & Documentation Section (Settings Tab):**
- Always-visible "Help & Documentation" section in WooSync Settings
- Per-vendor documentation links styled as buttons:
  - Amrod API Docs: https://newapidocs.amrod.co.za
  - WooCommerce REST API Docs: https://woocommerce.com/document/rest-api/
  - WooSync User Guide: https://mediaplatform.co.za/woosync-docs
  - Get Support link
- Links open in new tab, styled with new primary/accent buttons

**Colour Scheme Overhaul:**
- New professional palette replacing old unprofessional colours:
  - Primary: #4338CA (indigo-blue — trustworthy, professional)
  - Primary Light: #6366F1 (lighter indigo for hover)
  - Accent: #0EA5E9 (sky blue — action buttons, links)
  - Success: #10B981 (emerald green)
  - Warning: #F59E0B (amber)
  - Danger: #EF4444 (red)
  - Background: #F8FAFC (very light gray)
  - Card BG: #FFFFFF (white)
  - Text Primary: #1E293B (dark slate)
  - Text Secondary: #64748B (muted gray)
  - Border: #E2E8F0 (light border)
- CSS variables for consistency across all components
- Gradient headers on cards (primary, success, warning, danger, info, dark)
- All buttons use new border-radius (8px), hover effects, and shadow
- Updated admin.css completely with new professional palette

**New WooSync Logo (SVG):**
- New indigo/sky-blue logo created at assets/images/woosync-logo.svg
- W + S letters with sync arrows icon
- Indigo gradient text, sky blue accent

**GitHub Auto-Update Fix:**
- Created `includes/class-woosync-updater.php` for GitHub-based auto-updates
- Points to correct repo: https://github.com/preneshnaidoo/woosync
- Checks GitHub releases API for latest version
- Reads release "assets" to get the .zip download URL
- Caches release info for 1 hour
- Plugin can self-update without manual download/upload
- `amrod_auto_update` option controls enable/disable

**Clean Admin Menu:**
- WooSync menu simplified to 4 tabs:
  1. Dashboard
  2. Connect & Map
  3. Sync Log
  4. Settings
- Removed "Updates" as separate menu item (moved into Settings)
- Removed "Promotions" as separate menu item (merged into Settings)
- Removed "Promo Share" as separate menu item (tab still works via URL)
- Removed "Pricing" as separate menu item (tab still works via URL)
- Promotions, Promo Share, and Pricing remain accessible via their sub-tab URLs within Settings
- Clean tab count badges (removed redundant count badges)

**Technical Changes:**
- New `includes/class-woosync-updater.php` with WooSync_Updater class
- New `assets/js/wizard.js` with clean 3-step wizard flow
- New `assets/images/woosync-logo.svg` with indigo color scheme
- Updated `assets/css/admin.css` with complete new professional palette
- New `amrod_get_vendor_templates()` function with vendor support data
- New AJAX handler: `amrod_save_credentials_simple` for wizard
- Wizard modal data localized via `wp_localize_script`
- Wizard triggered on plugin activation via `set_transient('amrod_activated')`
- Version bump to 3.4.0

## [3.3.0] - 2026-06-25

### Feature: Supplier Tier Display with Tier Pricing, Margin Calculator, and Savings Dashboard

**Vendor Tier Storage:**
- When vendor API connection is tested, tier info is stored in `woosync_vendors` option
- Stores: tier level (Gold/Silver/Bronze/Standard), tier pricing endpoint, tier active since, tier expiry, tier notes
- Refresh tier status from API with one click
- Manual tier override in Settings for cases where API doesn't return tier

**Dashboard Tier Display (Connect & Map tab):**
- Tier card displayed at top of Connect & Map page with color-coded styling:
  - Gold = gold/amber gradient
  - Silver = silver/gray gradient
  - Bronze = bronze gradient
  - Platinum = purple gradient
  - Standard = blue gradient
- Shows: tier level, tier active since date, tier expiry (if available)
- "Refresh Tier Status" button to fetch latest tier from API
- "Upgrade Tier" link if vendor has upgrade URL

**Tier Price Display in Product Preview:**
- Right-column product preview now shows tier pricing breakdown:
  ```
  Supplier Tier Price:  R89.00  ← what YOU pay (Amrod Gold price)
  WooSync Markup (30%): +R26.70
  ─────────────────────────────
  Customer Sees:       R115.70
  Your Margin:          R26.70
  ```
- Makes margins immediately visible — no guessing
- Uses new `amrod_get_product_preview_tier` AJAX handler

**Tier Pricing API Integration:**
- Fetches tier-specific pricing from vendor pricing endpoint (e.g., Amrod `/api/v1/Prices/`)
- Stores tier price in product preview before WooSync markup
- Falls back to standard product price if tier pricing endpoint not available
- Shows "Standard Pricing" badge if user is on Standard tier

**Tier Comparison View (Sync Log page):**
- "Tier Pricing Benefits" section in Sync Log for Gold/Silver/Bronze tiers
- Table showing: Product | Standard Price | Your Tier Price | Savings/Unit | Savings %
- Total savings dashboard widget: "Your Gold tier saves you R12,450 across 234 products"
- Refresh button to load latest tier pricing comparison
- Products sorted by highest savings first

**WooSync Settings → Vendor Tier Edit:**
- Manual tier override dropdown (Standard, Bronze, Silver, Gold, Platinum)
- Tier pricing endpoint configuration field
- WooSync markup percentage (default 30%)
- Tier notes field for internal use
- "Refresh from API" button to auto-detect tier

**Technical Changes:**
- New tier functions: `amrod_get_vendor_tier()`, `amrod_save_vendor_tier()`, `amrod_get_tier_level()`, `amrod_get_tier_color_class()`, `amrod_get_tier_icon()`, `amrod_generate_preview_with_tier()`
- New AJAX handlers: `amrod_refresh_tier_status`, `amrod_save_tier_settings`, `amrod_get_tier_savings`, `amrod_get_product_preview_tier`
- New `assets/js/tier-settings.js` for tier settings and savings display
- Updated `assets/css/admin.css` with tier card styles, tier badge styles, price breakdown box styles
- New `woosync_vendors` and `woosync_markup_percent` options
- Version bump to 3.3.0

## [3.2.0] - 2026-06-25

### Feature: Promo Share Tab with Social One-Click Sharing

**New Promo Share Tab (Tab 6):**
- Fetches promotional campaigns from Amrod API with caching (1 hour transient)
- Hero banner at top showing featured/current promo
- Filterable grid: All | Clearance | Sale | Featured | Deal of the Day
- Each promo card displays:
  - Product image (banner/marketing image)
  - Campaign tag (🔥 CLEARANCE, 💰 DEAL OF THE DAY, etc.)
  - Original + sale price with % off badge
  - NEW badge for products added in last 7 days
  - Campaign end date if available

**One-Click Social Share Buttons:**
- Facebook, Twitter/X, LinkedIn, WhatsApp, Pinterest, Instagram, Copy Link
- Uses platform web share URLs (no auth required)
- Share text format: "{Product Name} - R{Price} | Shop now: {url} #promo #brandedmerch"
- Copy Link copies formatted text to clipboard

**Promo Email Integration:**
- "Send Promo Email" button per card
- Select recipients: All customers / By role / Specific users
- Email includes product name, prices, CTA button
- Uses WooCommerce email system

**Promo Data Fetching:**
- `woosync_fetch_promos()` function queries Amrod API for:
  - Products with Clearance, OnSale, Special, DealOfTheDay, Featured flags
  - Products with BannerImage, HeroImage, MarketingImage fields
  - Products where SalePrice < Price (discounted items)
- Cached in `woosync_promos_{vendor_id}` transient for 1 hour
- Last fetch time stored in options

**Technical Changes:**
- New `assets/js/promo-share.js` for grid rendering, filters, share handlers
- Updated `assets/css/admin.css` with promo card styles, share button styles
- New AJAX handlers: `woosync_fetch_promos`, `woosync_send_promo_email`
- New menu registration: `amrod_register_promo_share_menu`
- Version bump to 3.2.0

## [3.1.0] - 2026-06-25

### Major Refactor: Simplified Navigation & UI Cleanup

**Navigation Restructure:**
- Reduced from 10+ tabs to 5 clean tabs:
  1. **Dashboard** - Quick stats, last sync, errors, start sync button
  2. **Connect & Map** - Merged Field Mapping + API Endpoints + Preview + Data Sync
  3. **Sync Log** - History, errors, filters (renamed from Status & Analytics)
  4. **Promotions** - Banners, cross-sells, notifications
  5. **Settings** - Merged Settings + Updates into one

**Connect & Map - Two-Column Layout:**
- Left column (40%): Vendor selector, API connection status, Test Connection button, field mapping tabs, Save Mapping button, Start Sync button
- Right column (60%): Live product preview with fuzzy search

**Key Features Added:**
- ✅ Live product preview (updates as you type in search)
- ✅ Fuzzy search matching (e.g., "cups" matches "Coffee Cups", "Tea Cups", "Plastic Cups")
- ✅ Status indicators (green/yellow/red dots) for一眼就懂
- ✅ Help icons on every field (tooltip)
- ✅ Breadcrumb navigation (WooSync → Dashboard → Connect & Map)
- ✅ Test Connection button with real-time feedback
- ✅ "Sync This Product" and "Sync All Products" buttons
- ✅ Quick endpoint toggles on Connect & Map page

**Technical Changes:**
- New `connect-map.js` for two-column layout functionality
- New AJAX handlers: `amrod_test_connection`, `amrod_search_products`, `amrod_get_product_preview`, `amrod_sync_single_product`, `amrod_save_endpoint`
- Updated CSS with status dots and compact layout styles
- Version bump to 3.1.0

## [3.0.0] - 2026-06-25

### Category & Brand Import Fix

- Fixed categories not being assigned during sync
- Added brand attribute support (pa_brand)
- Added colour attribute support (pa_colour)
- Added sale price with date range
- Added marketing field detection (clearance, deal of day, banner image, catalog PDF)
- Added image download and attachment
- Enhanced field mapping UI with tabs (Core Fields, Categories, Attributes, Marketing)
- Auto-mapping confidence rules for 20+ field types

## [2.0.0] - 2026-06-24

### Initial v2 Features

- Progress tracking with live updates
- Batch processing with configurable size
- Sync history chart
- Manual sync controls
- Cron setup helper
- Enhanced logging

## [1.0.0] - 2026-06-24

### Initial Release

- Basic product sync from Amrod API
- Simple field mapping
- API credentials configuration
