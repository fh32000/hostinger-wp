<?php
/**
 * Quick Pay Internationalization & Bilingual (AR/EN) Engine.
 *
 * Automatically detects device/browser language (English vs Arabic),
 * supports URL parameter (?lang=en / ?lang=ar) and cookie persistence,
 * and handles clean decoded currency symbols.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

final class WC_Quick_Pay_I18n {

    /**
     * Cached detected locale.
     *
     * @var string|null
     */
    private static ?string $locale = null;

    /**
     * Detect and return the active language code ('ar' or 'en').
     *
     * @return string
     */
    public static function get_locale(): string {
        if ( null !== self::$locale ) {
            return self::$locale;
        }

        // 1. Explicit URL parameter.
        if ( isset( $_GET['lang'] ) ) {
            $lang = strtolower( sanitize_key( wp_unslash( $_GET['lang'] ) ) );
            if ( in_array( $lang, [ 'en', 'ar' ], true ) ) {
                if ( ! headers_sent() && ! isset( $_COOKIE['wc_qp_lang'] ) || ( $_COOKIE['wc_qp_lang'] ?? '' ) !== $lang ) {
                    @setcookie( 'wc_qp_lang', $lang, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
                }
                self::$locale = $lang;
                return self::$locale;
            }
        }

        // 2. Cookie preference if user previously switched.
        if ( ! empty( $_COOKIE['wc_qp_lang'] ) ) {
            $c_lang = strtolower( sanitize_key( wp_unslash( $_COOKIE['wc_qp_lang'] ) ) );
            if ( in_array( $c_lang, [ 'en', 'ar' ], true ) ) {
                self::$locale = $c_lang;
                return self::$locale;
            }
        }

        // 3. Auto-detect from browser / device HTTP_ACCEPT_LANGUAGE.
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ( ! empty( $accept ) ) {
            $ar_pos = stripos( $accept, 'ar' );
            $en_pos = stripos( $accept, 'en' );

            // If English is present and appears before Arabic, or Arabic is absent.
            if ( false !== $en_pos && ( false === $ar_pos || $en_pos < $ar_pos ) ) {
                self::$locale = 'en';
                return self::$locale;
            }
        }

        // Default language: Arabic.
        self::$locale = 'ar';
        return self::$locale;
    }

    /**
     * Check if current locale is English.
     *
     * @return bool
     */
    public static function is_en(): bool {
        return 'en' === self::get_locale();
    }

    /**
     * Get HTML direction ('rtl' for Arabic, 'ltr' for English).
     *
     * @return string
     */
    public static function get_direction(): string {
        return self::is_en() ? 'ltr' : 'rtl';
    }

    /**
     * Get clean, decoded currency symbol with no HTML entities.
     *
     * @param string $currency_code Optional currency code (defaults to store currency).
     * @return string
     */
    public static function get_clean_currency( string $currency_code = '' ): string {
        if ( empty( $currency_code ) && function_exists( 'get_woocommerce_currency' ) ) {
            $currency_code = get_woocommerce_currency();
        }

        if ( 'SAR' === $currency_code ) {
            return self::is_en() ? 'SAR' : 'ر.س';
        }

        if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
            $raw = get_woocommerce_currency_symbol( $currency_code );
            $decoded = trim( html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' ) );
            if ( ! empty( $decoded ) && strpos( $decoded, '&' ) === false ) {
                return $decoded;
            }
        }

        return $currency_code ?: 'SAR';
    }

    /**
     * Translate or return bilingual text.
     *
     * @param string $key String identifier.
     * @param string $ar Arabic string.
     * @param string $en English string.
     * @return string
     */
    public static function t( string $key, string $ar, string $en ): string {
        return self::is_en() ? $en : $ar;
    }

    /**
     * Get array of all frontend UI strings in current language.
     *
     * @return array
     */
    public static function get_strings(): array {
        $en = self::is_en();
        return [
            'header_badge'      => $en ? '🔒 256-bit Secure & Encrypted Payment' : '🔒 سداد آمن ومشفّر 256-bit',
            'header_title'      => $en ? 'Quick Pay & Invoice Settlement' : 'صفحة الدفع السريع وسداد الفواتير',
            'header_subtitle'   => $en ? 'Enter your details and pay securely & directly' : 'أدخل بياناتك ورقم الفاتورة لإتمام الدفع الآمن والمباشر',
            'amount_label'      => $en ? 'Payment Amount' : 'المبلغ المراد دفعه',
            'amount_locked'     => $en ? '🔒 Fixed Invoice Amount' : '🔒 مبلغ محدد للفاتورة',
            'amount_help'       => $en ? 'Enter the amount in SAR' : 'أدخل المبلغ المطلوب سداده',
            'customer_title'    => $en ? '👤 Payer Information' : '👤 بيانات المشتري (الدافع)',
            'customer_subtitle' => $en ? 'Enter contact details to receive your payment confirmation' : 'أدخل بياناتك لإرسال إيصال السداد وتأكيد العملية',
            'field_name'        => $en ? 'Full Name' : 'الاسم الكامل',
            'field_name_ph'     => $en ? 'e.g. John Doe' : 'مثال: أحمد محمد عبد الله',
            'field_email'       => $en ? 'Email Address' : 'البريد الإلكتروني',
            'field_email_ph'    => $en ? 'name@example.com' : 'name@example.com',
            'field_email_help'  => $en ? 'Your payment receipt will be sent to this email' : 'سيتم إرسال إيصال السداد وتأكيد الفاتورة إلى هذا البريد',
            'field_phone'       => $en ? 'Mobile Phone' : 'رقم الجوال',
            'field_phone_ph'    => $en ? '05xxxxxxxx' : '05xxxxxxxx',
            'invoice_title'     => $en ? '📄 Invoice & Reference Details' : '📄 تفاصيل الفاتورة والطلب',
            'invoice_subtitle'  => $en ? 'Enter reference to link this payment to your order' : 'أدخل رقم الفاتورة أو المعاملة لربط عملية السداد بسجل طلبك',
            'field_invoice'     => $en ? 'Invoice / Reference Number' : 'رقم الفاتورة أو المعاملة المرجعية',
            'field_invoice_ph'  => $en ? 'e.g. INV-2026-001' : 'مثال: INV-2026-001',
            'invoice_locked'    => $en ? '🔒 Auto-set from invoice link' : '🔒 مثبت تلقائياً من رابط الفاتورة',
            'field_notes'       => $en ? 'Invoice Description or Notes (Optional)' : 'وصف الفاتورة أو ملاحظات إضافية (اختياري)',
            'field_notes_ph'    => $en ? 'e.g. Service deposit, shipment fees, contract #...' : 'مثال: دفعة أولى من العقد، رسوم شحن، ملاحظات...',
            'gateway_title'     => $en ? '💳 Payment Method' : '💳 طريقة الدفع',
            'gateway_subtitle'  => $en ? 'Choose your preferred payment method to proceed securely' : 'اختر بوابة الدفع المناسبة لك لإتمام العملية فورياً وبأمان',
            'single_gw_badge'   => $en ? '⚡ Accepted Payment Method' : '⚡ طريقة الدفع المعتمدة',
            'submit_btn'        => $en ? 'Complete Payment Now' : 'إتمام الدفع الآن',
            'trust_secure'      => $en ? '100% Secure & Encrypted Transaction' : 'معاملة مشفرة وآمنة بنسبة 100%',
            'trust_instant'     => $en ? 'Instant Gateway Redirection' : 'تحويل فوري لبوابة الدفع',
            'support_title'     => $en ? 'Need help? Contact Customer Support' : 'هل تحتاج لمساعدة؟ تواصل مع خدمة العملاء',
            'support_wa'        => $en ? 'Support WhatsApp' : 'واتساب الدعم',
            'terms'             => $en ? 'Terms & Conditions' : 'الشروط والأحكام',
            'refund'            => $en ? 'Refund Policy' : 'سياسة الاسترجاع',
            'copyright'         => $en ? 'All Rights Reserved © ' : 'جميع الحقوق محفوظة © ',
            'lang_switch_label' => $en ? '🇸🇦 العربية' : '🇺🇸 English',
            'i18n_processing'   => $en ? 'Preparing payment gateway, please wait...' : 'جاري تجهيز بوابة الدفع والتحويل...',
            'i18n_amount_err'   => $en ? 'Please enter a valid payment amount greater than zero.' : 'يرجى إدخال مبلغ صحيح أكبر من الصفر.',
            'i18n_invoice_err'  => $en ? 'Please enter the invoice or reference number.' : 'يرجى إدخال رقم الفاتورة أو المعاملة المرجعية.',
            'i18n_email_err'    => $en ? 'Please enter a valid email address.' : 'يرجى إدخال بريد إلكتروني صحيح.',
            'i18n_name_err'     => $en ? 'Please enter your full name.' : 'يرجى إدخال الاسم الكامل.',
            'i18n_phone_err'    => $en ? 'Please enter your mobile phone number.' : 'يرجى إدخال رقم الجوال.',
            'i18n_gateway_err'  => $en ? 'Please select a payment gateway first.' : 'يرجى اختيار بوابة الدفع أولاً.',
            'i18n_general_err'  => $en ? 'An error occurred during payment processing. Please try again.' : 'حدث خطأ أثناء معالجة الطلب، يرجى المحاولة مرة أخرى.',
        ];
    }
}
