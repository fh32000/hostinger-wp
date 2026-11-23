<?php
/**
 * Standalone Quick Pay Page Template.
 * Isolated from WordPress theme headers/footers for a distraction-free payment experience.
 * Fully bilingual (Arabic / English) with automatic device/browser language detection.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

$settings   = get_option( 'wc_quick_pay_settings', [] );
$site_title = get_bloginfo( 'name' );

// Internationalization & Device Language Detection.
$is_en          = class_exists( 'WC_Quick_Pay_I18n' ) && WC_Quick_Pay_I18n::is_en();
$ui             = class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_strings() : [];
$dir            = class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_direction() : 'rtl';
$lang           = class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_locale() : 'ar';
$clean_currency = class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_clean_currency() : 'SAR';

$page_title      = ! empty( $settings['page_title'] ) ? ( $is_en ? 'Quick Pay & Secure Settlement' : $settings['page_title'] ) : ( $is_en ? 'Quick Pay & Secure Settlement' : 'سداد سريع وآمن' );
$logo_url        = $settings['logo_url'] ?? '';
if ( empty( $logo_url ) ) {
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id ) {
        $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
    }
    if ( empty( $logo_url ) && file_exists( WP_CONTENT_DIR . '/uploads/2026/09/Gemini_Generated_Image_dg1l7pdg1l7pdg1l.png' ) ) {
        $logo_url = content_url( '/uploads/2026/09/Gemini_Generated_Image_dg1l7pdg1l7pdg1l.png' );
    }
}

$header_title    = ! empty( $settings['header_title'] ) ? ( $is_en ? ( $ui['header_title'] ?? 'Quick Pay & Invoice Settlement' ) : $settings['header_title'] ) : ( $ui['header_title'] ?? 'صفحة الدفع السريع وسداد الفواتير' );
$header_subtitle = ! empty( $settings['header_subtitle'] ) ? ( $is_en ? ( $ui['header_subtitle'] ?? 'Enter your details and pay securely & directly' ) : $settings['header_subtitle'] ) : ( $ui['header_subtitle'] ?? 'أدخل بياناتك ورقم الفاتورة لإتمام الدفع الآمن والمباشر' );
$header_badge    = ! empty( $settings['header_badge'] ) ? ( $is_en ? ( $ui['header_badge'] ?? '🔒 256-bit Secure & Encrypted Payment' ) : $settings['header_badge'] ) : ( $ui['header_badge'] ?? '🔒 سداد آمن ومشفّر 256-bit' );

// Colors.
$primary_color = $settings['primary_color'] ?? '#004733';
$accent_color  = $settings['accent_color'] ?? '#D4AF37';
$bg_color      = $settings['bg_color'] ?? '#f8fafc';

// Support.
$show_support = ( $settings['show_support_box'] ?? 'no' ) === 'yes';
if ( ! empty( $prefill['hide_support'] ) || ( isset( $_GET['hide_support'] ) && in_array( $_GET['hide_support'], [ '1', 'yes', 'true' ], true ) ) ) {
    $show_support = false;
}
$whatsapp      = ! empty( $settings['support_whatsapp'] ) ? $settings['support_whatsapp'] : '966579355216';
$phone         = ! empty( $settings['support_phone'] ) ? $settings['support_phone'] : '+966579355216';
$email         = $settings['support_email'] ?? '';
$terms         = $settings['terms_url'] ?? '';
$refund        = $settings['refund_url'] ?? '';
$support_title = ! empty( $settings['footer_support_text'] ) ? ( $is_en ? ( $ui['support_title'] ?? 'Need help? Contact Customer Support' ) : $settings['footer_support_text'] ) : ( $ui['support_title'] ?? 'هل تحتاج لمساعدة؟ تواصل مع خدمة العملاء' );
$footer_text   = ! empty( $settings['footer_text'] ) ? ( $is_en ? ( 'All Rights Reserved © ' . date( 'Y' ) . ' ' . $site_title ) : $settings['footer_text'] ) : ( ( $ui['copyright'] ?? 'جميع الحقوق محفوظة © ' ) . date( 'Y' ) . ' ' . $site_title );

?><!DOCTYPE html>
<html lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo esc_html( $page_title . ' — ' . $site_title ); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo esc_url( WC_QUICK_PAY_URL . 'assets/css/quick-pay.css?v=' . WC_QUICK_PAY_VERSION ); ?>">

    <style>
        :root {
            --wc-qp-primary: <?php echo esc_attr( $primary_color ); ?>;
            --wc-qp-accent: <?php echo esc_attr( $accent_color ); ?>;
            --wc-qp-bg: <?php echo esc_attr( $bg_color ); ?>;
        }
    </style>

    <?php wp_print_scripts( 'jquery' ); ?>
</head>
<body class="wc-quick-pay-body">

    <div class="wc-qp-page-wrapper">
        <!-- Main Container -->
        <main class="wc-qp-main">

            <!-- Language Switcher Bar -->
            <div class="wc-qp-lang-bar">
                <a href="<?php echo esc_url( add_query_arg( 'lang', $is_en ? 'ar' : 'en' ) ); ?>" class="wc-qp-lang-btn" id="wc-qp-lang-btn">
                    <span>🌐</span> <?php echo esc_html( $ui['lang_switch_label'] ?? ( $is_en ? 'العربية' : 'English' ) ); ?>
                </a>
            </div>

            <!-- Top Header Section -->
            <header class="wc-qp-header-section">
                <?php if ( ! empty( $header_badge ) ) : ?>
                    <div class="wc-qp-badge">
                        <span><?php echo esc_html( $header_badge ); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $logo_url ) ) : ?>
                    <div class="wc-qp-logo-wrap">
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_title ); ?>" class="wc-qp-logo" />
                    </div>
                <?php else : ?>
                    <div class="wc-qp-brand-title">
                        <?php echo esc_html( $site_title ); ?>
                    </div>
                <?php endif; ?>

                <h1 class="wc-qp-heading"><?php echo esc_html( $header_title ); ?></h1>
                <?php if ( ! empty( $header_subtitle ) ) : ?>
                    <p class="wc-qp-subheading"><?php echo esc_html( $header_subtitle ); ?></p>
                <?php endif; ?>
            </header>

            <!-- Payment Form Component -->
            <?php include WC_QUICK_PAY_PATH . 'templates/quick-pay-form.php'; ?>

            <!-- Footer Section -->
            <footer class="wc-qp-footer-section">
                <!-- Payment Badges / Logos -->
                <div class="wc-qp-payment-icons-row">
                    <span class="wc-qp-pay-icon" title="مدى Mada">مدى Mada</span>
                    <span class="wc-qp-pay-icon" title="Visa">Visa</span>
                    <span class="wc-qp-pay-icon" title="Mastercard">MasterCard</span>
                    <span class="wc-qp-pay-icon" title="Apple Pay">Apple Pay</span>
                    <span class="wc-qp-pay-icon" title="SSL Encrypted">SSL 256-bit</span>
                </div>

                <!-- Support Channels -->
                <?php if ( $show_support && ( ! empty( $whatsapp ) || ! empty( $phone ) || ! empty( $email ) ) ) : ?>
                    <div class="wc-qp-support-box">
                        <p class="wc-qp-support-title"><?php echo esc_html( $support_title ); ?></p>
                        <div class="wc-qp-support-links">
                            <?php if ( ! empty( $whatsapp ) ) : ?>
                                <a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $whatsapp ) ); ?>" target="_blank" rel="noopener" class="wc-qp-support-btn wc-qp-wa">
                                    <span>💬</span> <?php echo esc_html( $ui['support_wa'] ?? __( 'واتساب الدعم', 'wc-quick-pay' ) ); ?>
                                </a>
                            <?php endif; ?>

                            <?php if ( ! empty( $phone ) ) : ?>
                                <a href="tel:<?php echo esc_attr( $phone ); ?>" class="wc-qp-support-btn wc-qp-phone">
                                    <span>📞</span> <?php echo esc_html( $phone ); ?>
                                </a>
                            <?php endif; ?>

                            <?php if ( ! empty( $email ) ) : ?>
                                <a href="mailto:<?php echo esc_attr( $email ); ?>" class="wc-qp-support-btn wc-qp-email">
                                    <span>✉️</span> <?php echo esc_html( $email ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Policy Links -->
                <?php if ( ! empty( $terms ) || ! empty( $refund ) ) : ?>
                    <div class="wc-qp-legal-links">
                        <?php if ( ! empty( $terms ) ) : ?>
                            <a href="<?php echo esc_url( $terms ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ui['terms'] ?? __( 'الشروط والأحكام', 'wc-quick-pay' ) ); ?></a>
                        <?php endif; ?>
                        <?php if ( ! empty( $terms ) && ! empty( $refund ) ) : ?>
                            <span>•</span>
                        <?php endif; ?>
                        <?php if ( ! empty( $refund ) ) : ?>
                            <a href="<?php echo esc_url( $refund ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ui['refund'] ?? __( 'سياسة الاسترجاع والإلغاء', 'wc-quick-pay' ) ); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Copyright -->
                <div class="wc-qp-copyright">
                    <p><?php echo esc_html( $footer_text ); ?></p>
                </div>
            </footer>

        </main>
    </div>

    <!-- Scripts -->
    <script>
        var wcQuickPayParams = <?php echo wp_json_encode( [
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'wc_quick_pay_nonce' ),
            'currency'     => $clean_currency,
            'currency_code'=> get_woocommerce_currency(),
            'lang'         => $lang,
            'i18n'         => [
                'processing'     => $ui['i18n_processing'] ?? __( 'جاري تجهيز بوابة الدفع والتحويل...', 'wc-quick-pay' ),
                'invalid_amount' => $ui['i18n_amount_err'] ?? __( 'يرجى إدخال مبلغ صحيح أكبر من الصفر.', 'wc-quick-pay' ),
                'invalid_invoice'=> $ui['i18n_invoice_err'] ?? __( 'يرجى إدخال رقم الفاتورة أو المعاملة المرجعية.', 'wc-quick-pay' ),
                'invalid_email'  => $ui['i18n_email_err'] ?? __( 'يرجى إدخال بريد إلكتروني صحيح.', 'wc-quick-pay' ),
                'invalid_name'   => $ui['i18n_name_err'] ?? __( 'يرجى إدخال الاسم الكامل.', 'wc-quick-pay' ),
                'invalid_phone'  => $ui['i18n_phone_err'] ?? __( 'يرجى إدخال رقم هاتف صحيح.', 'wc-quick-pay' ),
                'select_gateway' => $ui['i18n_gateway_err'] ?? __( 'يرجى اختيار بوابة الدفع لإتمام العملية.', 'wc-quick-pay' ),
                'general_error'  => $ui['i18n_general_err'] ?? __( 'حدث خطأ أثناء معالجة الطلب. يرجى المحاولة لاحقاً.', 'wc-quick-pay' ),
            ],
        ] ); ?>;
    </script>
    <script src="<?php echo esc_url( WC_QUICK_PAY_URL . 'assets/js/quick-pay.js?v=' . WC_QUICK_PAY_VERSION ); ?>"></script>

</body>
</html>
