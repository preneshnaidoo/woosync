<?php
use PHPUnit\Framework\TestCase;

class Amrod_Sync_Test extends TestCase {
    public function setUp(): void {
        if (! defined('WPINC')) {
            $this->markTestSkipped('WordPress environment not available for plugin integration tests.');
        }
    }

    public function test_functions_exist() {
        $this->assertTrue(function_exists('amrod_sync_products'));
        $this->assertTrue(function_exists('amrod_sync_process_batch'));
        $this->assertTrue(function_exists('amrod_sync_log'));
    }

    public function test_log_writes_and_clears() {
        if (! function_exists('update_option')) {
            $this->markTestSkipped('WP option functions unavailable.');
        }

        amrod_sync_log('phpunit-test-entry');
        $log = (array) get_option('amrod_sync_log', []);
        $this->assertNotEmpty($log);
        $this->assertStringContainsString('phpunit-test-entry', end($log));

        update_option('amrod_sync_log', []);
        $this->assertEmpty(get_option('amrod_sync_log', []));
    }
}
