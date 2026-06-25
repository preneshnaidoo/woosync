# Changelog

All notable changes to WooSync are documented in this file.

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
