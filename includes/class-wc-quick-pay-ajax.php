<?php
/**
 * AJAX Handler for Quick Pay Form Submission.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

class WC_Quick_Pay_Ajax {

    /**
     * Hook up AJAX handlers.
     */
    public static function init(): void {
        add_action( 'wp_ajax_wc_quick_pay_submit',        [ __CLASS__, 'handle_submission' ] );
        add_action( 'wp_ajax_nopriv_wc_quick_pay_submit', [ __CLASS__, 'handle_submission' ] );
    }

    /**
     * Handle payment submission.
     */
    public static function handle_submission(): void {
        check_ajax_referer( 'wc_quick_pay_nonce', 'nonce' );

        $settings = get_option( 'wc_quick_pay_settings', [] );

        // Read and sanitize inputs.
        $amount     = isset( $_POST['amount'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : 0;
        $name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $invoice    = isset( $_POST['invoice'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice'] ) ) : '';
        $notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
        $gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway_id'] ) ) : '';

        // Validation: Amount.
        if ( $amount <= 0 ) {
            wp_send_json_error( [
                'message' => __( 'يرجى إدخال مبلغ صحيح أكبر من الصفر.', 'wc-quick-pay' ),
            ] );
        }

        $min_amount = ! empty( $settings['min_amount'] ) ? (float) $settings['min_amount'] : 0;
        $max_amount = ! empty( $settings['max_amount'] ) ? (float) $settings['max_amount'] : 0;

        if ( $min_amount > 0 && $amount < $min_amount ) {
            wp_send_json_error( [
                'message' => sprintf( __( 'الحد الأدنى للمبلغ المسموح به هو %s %s.', 'wc-quick-pay' ), $min_amount, get_woocommerce_currency() ),
            ] );
        }

        if ( $max_amount > 0 && $amount > $max_amount ) {
            wp_send_json_error( [
                'message' => sprintf( __( 'الحد الأقصى للمبلغ المسموح به هو %s %s.', 'wc-quick-pay' ), $max_amount, get_woocommerce_currency() ),
            ] );
        }

        // Validation: Name.
        $req_name = ( $settings['field_name_req'] ?? 'yes' ) === 'yes';
        if ( $req_name && empty( $name ) ) {
            wp_send_json_error( [
                'message' => __( 'يرجى إدخال الاسم الكامل.', 'wc-quick-pay' ),
            ] );
        }

        // Validation: Email.
        $req_email = ( $settings['field_email_req'] ?? 'yes' ) === 'yes';
        if ( $req_email && ( empty( $email ) || ! is_email( $email ) ) ) {
            wp_send_json_error( [
                'message' => __( 'يرجى إدخال بريد إلكتروني صحيح.', 'wc-quick-pay' ),
            ] );
        }

        // Validation: Phone.
        $req_phone = ( $settings['field_phone_req'] ?? 'yes' ) === 'yes';
        if ( $req_phone && empty( $phone ) ) {
            wp_send_json_error( [
                'message' => __( 'يرجى إدخال رقم الجوال.', 'wc-quick-pay' ),
            ] );
        }

        // Validation: Invoice.
        $req_invoice = ( $settings['field_invoice_req'] ?? 'yes' ) === 'yes';
        if ( $req_invoice && empty( $invoice ) ) {
            wp_send_json_error( [
                'message' => __( 'يرجى إدخال رقم الفاتورة أو الطلب.', 'wc-quick-pay' ),
            ] );
        }

        // Validation: Gateway.
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

        // If gateway_id is empty, use default gateway or first available.
        if ( empty( $gateway_id ) ) {
            $default_gw = $settings['default_gateway'] ?? '';
            if ( ! empty( $default_gw ) && isset( $available_gateways[ $default_gw ] ) ) {
                $gateway_id = $default_gw;
            } elseif ( ! empty( $available_gateways ) ) {
                $gateway_id = array_key_first( $available_gateways );
            }
        }

        if ( empty( $gateway_id ) || ! isset( $available_gateways[ $gateway_id ] ) ) {
            wp_send_json_error( [
                'message' => __( 'يرجى اختيار بوابة دفع صالحة أو التأكد من تفعيل بوابات الدفع في المتجر.', 'wc-quick-pay' ),
            ] );
        }

        $gateway = $available_gateways[ $gateway_id ];

        // Create the Order.
        $order = WC_Quick_Pay_Order::create_quick_pay_order( [
            'amount'     => $amount,
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'invoice'    => $invoice,
            'notes'      => $notes,
            'gateway_id' => $gateway_id,
        ] );

        if ( is_wp_error( $order ) ) {
            wp_send_json_error( [
                'message' => $order->get_error_message(),
            ] );
        }

        // Clear any previous notices.
        wc_clear_notices();

        // Process payment with gateway.
        try {
            $result = $gateway->process_payment( $order->get_id() );

            if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
                $redirect = $result['redirect'] ?? $order->get_checkout_payment_url( true );

                wp_send_json_success( [
                    'redirect' => $redirect,
                    'order_id' => $order->get_id(),
                    'message'  => __( 'تم تجهيز بوابة الدفع بنجاح، جاري تحويلك...', 'wc-quick-pay' ),
                ] );
            } else {
                $notices = wc_get_notices( 'error' );
                $error_msgs = [];
                if ( ! empty( $notices ) ) {
                    foreach ( $notices as $notice ) {
                        $error_msgs[] = wp_strip_all_tags( $notice['notice'] ?? '' );
                    }
                    wc_clear_notices();
                }

                $message = ! empty( $error_msgs )
                    ? implode( ' - ', $error_msgs )
                    : __( 'فشلت معالجة الدفع عبر البوابة المختارة. يرجى مراجعة بيانات البطاقة أو المحاولة لاحقاً.', 'wc-quick-pay' );

                wp_send_json_error( [
                    'message'  => $message,
                    'order_id' => $order->get_id(),
                ] );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( [
                'message'  => sprintf( __( 'خطأ غير متوقع: %s', 'wc-quick-pay' ), $e->getMessage() ),
                'order_id' => $order->get_id(),
            ] );
        }
    }
}

// Hook into AJAX.
add_action( 'init', [ 'WC_Quick_Pay_Ajax', 'init' ] );
