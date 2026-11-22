<?php
/**
 * Plugin Name: Taj Migration Sync
 * Description: One-time migration sync tool for Taj Alhdaya.
 * Version: 1.0.0
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function() {
    if (isset($_GET['taj_ping'])) {
        wp_send_json(['status' => 'pong', 'time' => time()]);
    }
});
