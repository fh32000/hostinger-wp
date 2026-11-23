<?php
/**
 * Plugin Name:       WooCommerce Quick Pay & Pay Links
 * Description:       إضافة صفحة الدفع السريع المخصصة وروابط الفواتير المباشرة لووكومرس - دفع حر وسريع بدون تعقيدات قوالب ووردبريس مع تمرير تلقائي للبيانات إلى بوابات الدفع.
 * Version:           1.3.2
 * Text Domain:       wc-quick-pay
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   9.5
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_QUICK_PAY_VERSION', '1.3.1' );
define( 'WC_QUICK_PAY_FILE', __FILE__ );
define( 'WC_QUICK_PAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_QUICK_PAY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main WC_Quick_Pay Class.
 */
final class WC_Quick_Pay {

    /**
     * Single instance.
     *
     * @var WC_Quick_Pay|null
     */
    private static ?WC_Quick_Pay $instance = null;

    /**
     * Get instance.
     *
     * @return WC_Quick_Pay
     */
    public static function instance(): WC_Quick_Pay {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes(): void {
        require_once WC_QUICK_PAY_PATH . 'includes/class-wc-quick-pay-i18n.php';
        require_once WC_QUICK_PAY_PATH . 'includes/class-wc-quick-pay-order.php';
        require_once WC_QUICK_PAY_PATH . 'includes/class-wc-quick-pay-endpoint.php';
        require_once WC_QUICK_PAY_PATH . 'includes/class-wc-quick-pay-ajax.php';
        require_once WC_QUICK_PAY_PATH . 'includes/class-wc-quick-pay-shortcode.php';

        if ( is_admin() ) {
            require_once WC_QUICK_PAY_PATH . 'includes/class-wc-quick-pay-settings.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks(): void {
        // Declare HPOS compatibility.
        add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );

        // Load text domain.
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Check WooCommerce dependency.
        add_action( 'admin_notices', [ $this, 'check_woocommerce_active' ] );

        // Enqueue frontend scripts and styles.
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
    }

    /**
     * Declare High-Performance Order Storage (HPOS) compatibility.
     */
    public function declare_hpos_compatibility(): void {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                WC_QUICK_PAY_FILE,
                true
            );
        }
    }

    /**
     * Load plugin translations.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'wc-quick-pay',
            false,
            dirname( plugin_basename( WC_QUICK_PAY_FILE ) ) . '/languages'
        );
    }

    /**
     * Check if WooCommerce is installed and active.
     */
    public function check_woocommerce_active(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-error"><p>' .
                esc_html__( 'إضافة الدفع السريع (WooCommerce Quick Pay) تتطلب تفعيل إضافة WooCommerce لتعمل بالشكل المطلوب.', 'wc-quick-pay' ) .
                '</p></div>';
        }
    }

    /**
     * Register scripts & styles.
     */
    public function register_assets(): void {
        wp_register_style(
            'wc-quick-pay-fonts',
            'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap',
            [],
            WC_QUICK_PAY_VERSION
        );

        wp_register_style(
            'wc-quick-pay-style',
            WC_QUICK_PAY_URL . 'assets/css/quick-pay.css',
            [ 'wc-quick-pay-fonts' ],
            WC_QUICK_PAY_VERSION
        );

        wp_register_script(
            'wc-quick-pay-script',
            WC_QUICK_PAY_URL . 'assets/js/quick-pay.js',
            [ 'jquery' ],
            WC_QUICK_PAY_VERSION,
            true
        );

        $ui_strings = class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_strings() : [];
        $clean_curr = class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_clean_currency() : 'SAR';

        wp_localize_script(
            'wc-quick-pay-script',
            'wcQuickPayParams',
            [
                'ajax_url'     => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'wc_quick_pay_nonce' ),
                'currency'     => $clean_curr,
                'currency_code'=> get_woocommerce_currency(),
                'lang'         => class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_locale() : 'ar',
                'i18n'         => [
                    'processing'     => $ui_strings['i18n_processing'] ?? __( 'جاري تجهيز بوابة الدفع...', 'wc-quick-pay' ),
                    'invalid_amount' => $ui_strings['i18n_amount_err'] ?? __( 'يرجى إدخال مبلغ صحيح أكبر من الصفر.', 'wc-quick-pay' ),
                    'invalid_invoice'=> $ui_strings['i18n_invoice_err'] ?? __( 'يرجى إدخال رقم الفاتورة أو المعاملة المرجعية.', 'wc-quick-pay' ),
                    'invalid_email'  => $ui_strings['i18n_email_err'] ?? __( 'يرجى إدخال بريد إلكتروني صحيح.', 'wc-quick-pay' ),
                    'invalid_name'   => $ui_strings['i18n_name_err'] ?? __( 'يرجى إدخال الاسم الكامل.', 'wc-quick-pay' ),
                    'invalid_phone'  => $ui_strings['i18n_phone_err'] ?? __( 'يرجى إدخال رقم هاتف صحيح.', 'wc-quick-pay' ),
                    'select_gateway' => $ui_strings['i18n_gateway_err'] ?? __( 'يرجى اختيار بوابة الدفع لإتمام العملية.', 'wc-quick-pay' ),
                    'general_error'  => $ui_strings['i18n_general_err'] ?? __( 'حدث خطأ أثناء معالجة الطلب. يرجى المحاولة لاحقاً.', 'wc-quick-pay' ),
                ],
            ]
        );
    }

    /**
     * Plugin activation.
     */
    public static function activate(): void {
        // Ensure backing product exists.
        require_once WC_QUICK_PAY_PATH . 'includes/class-wc-quick-pay-order.php';
        WC_Quick_Pay_Order::ensure_backing_product();

        // Create quick pay page if it doesn't exist.
        require_once WC_QUICK_PAY_PATH . 'includes/class-wc-quick-pay-endpoint.php';
        WC_Quick_Pay_Endpoint::setup_page();

        // Set default options.
        if ( ! get_option( 'wc_quick_pay_settings' ) ) {
            $defaults = [
                'enabled'            => 'yes',
                'page_slug'          => 'pay',
                'standalone_mode'    => 'yes',
                'page_title'         => 'سداد سريع وآمن',
                'header_title'       => 'صفحة الدفع السريع وسداد الفواتير',
                'header_subtitle'    => 'أدخل بياناتك ورقم الفاتورة لإتمام الدفع الآمن والمباشر',
                'header_badge'       => '🔒 سداد آمن ومشفّر 256-bit',
                'logo_url'           => '',
                'amount_mode'        => 'custom',
                'lock_amount_from_url'=> 'yes',
                'show_preset_buttons'=> 'yes',
                'amount_label'       => 'المبلغ المراد دفعه',
                'amount_help_text'   => '',
                'min_amount'         => '1',
                'max_amount'         => '',
                'default_amount'     => '',
                'preset_amounts'     => '50, 100, 250, 500, 1000',
                'show_section_invoice' => 'yes',
                'field_invoice_enable' => 'yes',
                'field_invoice_req'  => 'yes',
                'field_notes_enable' => 'yes',
                'show_section_customer'=> 'yes',
                'field_name_enable'  => 'yes',
                'field_name_req'     => 'yes',
                'field_email_enable' => 'yes',
                'field_email_req'    => 'yes',
                'field_phone_enable' => 'yes',
                'field_phone_req'    => 'yes',
                'fallback_guest_name'=> 'عميل سداد سريع',
                'fallback_guest_email'=> '',
                'fallback_guest_phone'=> '',
                'gateway_mode'       => 'all', // 'all' or 'selected'
                'selected_gateways'  => [ 'wc_ahli_mpgs_gateway' ],
                'default_gateway'    => 'wc_ahli_mpgs_gateway',
                'single_gateway_auto_select' => 'yes',
                'footer_text'        => 'جميع الحقوق محفوظة © ' . date( 'Y' ) . ' ' . get_bloginfo( 'name' ),
                'show_support_box'   => 'no',
                'footer_support_text'=> 'هل تحتاج لمساعدة؟ تواصل مع خدمة العملاء',
                'support_whatsapp'   => '',
                'support_phone'      => '',
                'support_email'      => get_option( 'admin_email' ),
                'terms_url'          => '',
                'refund_url'         => '',
                'show_developer_credit' => 'no',
                'primary_color'      => '#004733', // Luxury emerald green (Saudi/AlAhli vibe)
                'accent_color'       => '#D4AF37', // Gold accent
                'bg_color'           => '#f8fafc',
            ];
            update_option( 'wc_quick_pay_settings', $defaults );
        }

        // Flush rewrite rules.
        WC_Quick_Pay_Endpoint::register_rewrites();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}

// Register activation / deactivation hooks.
register_activation_hook( WC_QUICK_PAY_FILE, [ 'WC_Quick_Pay', 'activate' ] );
register_deactivation_hook( WC_QUICK_PAY_FILE, [ 'WC_Quick_Pay', 'deactivate' ] );

// Initialize plugin.
add_action( 'plugins_loaded', [ 'WC_Quick_Pay', 'instance' ], 11 );
