Amrod WooCommerce Sync
======================

Purpose
-------
Sync Amrod product data into WooCommerce. The plugin provides a manual sync button, batched processing (Background via Action Scheduler when available, synchronous fallback), a log, and WP‑CLI commands for automation.

Installation
------------
1. Upload the plugin into `wp-content/plugins/amrod-sync` and activate it.
2. Go to **Settings → Amrod Sync** and enter your credentials.

Credentials
-----------
- Request a Vendor API `username` and `Customer Code` from Amrod support.
- For the API password the plugin prefers secure configuration (priority order):
  1. Set environment variable `AMROD_API_PASSWORD` on your server (recommended).
  2. Define `AMROD_API_PASSWORD` in `wp-config.php`.
- Password must be provided via environment variable `AMROD_API_PASSWORD` or `wp-config.php` constant; the plugin no longer accepts or uses a stored password option.

WP‑CLI
------
- `wp amrod-sync run [--batch=<n>] [--background]` — start a sync.
- `wp amrod-sync status` — show last run and recent log.
- `wp amrod-sync clear-log` — clear the plugin log.

Testing / CI
-----------
The repo includes a PHPUnit skeleton and a GitHub Actions workflow (`.github/workflows/phpunit.yml`). The tests are marked to skip when WordPress test environment is not available.

Security & Notes
----------------
- Manual sync requires `manage_options` capability.
- The plugin prefers environment/constant password over stored option for safer automated deployments.

Support
-------
Contact Amrod support for API credentials and endpoint details.
