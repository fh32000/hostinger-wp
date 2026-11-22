<?php
/**
 * Plugin Name:       WooCommerce Ahli Bank (MPGS) Payment Gateway
 * Plugin URI:        https://github.com/your-org/wc-ahli-mpgs
 * Description:       يربط بوابة دفع البنك الأهلي (Mastercard Payment Gateway Services - MPGS) مع WooCommerce عبر طريقة Hosted Checkout.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-ahli-mpgs
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to:   8.5
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'WC_AHLI_MPGS_VERSION',  '1.0.0' );
define( 'WC_AHLI_MPGS_FILE',     __FILE__ );
define( 'WC_AHLI_MPGS_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WC_AHLI_MPGS_URL',      plugin_dir_url( __FILE__ ) );
define( 'WC_AHLI_MPGS_BASENAME', plugin_basename( __FILE__ ) );

// ─── Activation / Deactivation ────────────────────────────────────────────────
register_activation_hook( __FILE__, 'wc_ahli_mpgs_activate' );
function wc_ahli_mpgs_activate() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( __( 'يتطلب هذا الملحق تثبيت وتفعيل WooCommerce أولاً.', 'wc-ahli-mpgs' ) );
    }
}

// ─── Load after WooCommerce ───────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wc_ahli_mpgs_init', 11 );
function wc_ahli_mpgs_init() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p>' .
                 __( 'بوابة الأهلي (MPGS): يجب تفعيل WooCommerce أولاً.', 'wc-ahli-mpgs' ) .
                 '</p></div>';
        } );
        return;
    }

    // Load class files
    require_once WC_AHLI_MPGS_DIR . 'includes/class-wc-ahli-mpgs-license.php';
    require_once WC_AHLI_MPGS_DIR . 'includes/class-wc-ahli-mpgs-api.php';
    require_once WC_AHLI_MPGS_DIR . 'includes/class-wc-ahli-mpgs-gateway.php';

    // Register gateway
    add_filter( 'woocommerce_payment_gateways', 'wc_ahli_mpgs_add_gateway' );

    // Load text domain
    load_plugin_textdomain( 'wc-ahli-mpgs', false, WC_AHLI_MPGS_DIR . 'languages' );
}

function wc_ahli_mpgs_add_gateway( $gateways ) {
    $gateways[] = 'WC_Ahli_MPGS_Gateway';
    return $gateways;
}

// ─── Plugin action links ───────────────────────────────────────────────────────
add_filter( 'plugin_action_links_' . WC_AHLI_MPGS_BASENAME, 'wc_ahli_mpgs_plugin_links' );
function wc_ahli_mpgs_plugin_links( $links ) {
    $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_ahli_mpgs_gateway' );
    array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">' . __( 'الإعدادات', 'wc-ahli-mpgs' ) . '</a>' );
    return $links;
}

// ─── HPOS (High-Performance Order Storage) compatibility ─────────────────────
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

