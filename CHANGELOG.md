# Changelog

## [1.0.0.1] - 2026-02-13
### Changed
- Removed stored-password fallback; API password must be provided via `AMROD_API_PASSWORD` environment variable or `wp-config.php` constant.
- Added transient token caching and masked token display in admin.  
- Implemented Action Scheduler background batching (with synchronous fallback).  
- Added WP‑CLI commands: `run`, `status`, `clear-log`.  
- Added sync log, Status admin submenu, uninstall cleanup, and README updates.  
- Various security and error‑handling improvements; removed unsafe public cron endpoint.

### Fixed
- Syntax and parse errors; improved input sanitisation and JSON/HTTP error handling.

### Notes
- Breaking change: stored password option is no longer used. Set `AMROD_API_PASSWORD` in your environment or `wp-config.php` before upgrading.