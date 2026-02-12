<?php
// Minimal test bootstrap. For full WordPress integration tests, set up the WP testing suite
// and define WP_PLUGIN_DIR and WP_TESTS_DIR accordingly. This bootstrap simply lets
// PHPUnit run the test file and skip WP‑dependent tests when WordPress is not available.

// Attempt to load the plugin file if running inside WP tests
if (file_exists(__DIR__ . '/../amrod-sync.php')) {
    include_once __DIR__ . '/../amrod-sync.php';
}
