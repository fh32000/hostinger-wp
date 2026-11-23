<?php
/**
 * Quick Pay Shortcode Handler.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

class WC_Quick_Pay_Shortcode {

    /**
     * Init shortcode.
     */
    public static function init(): void {
        add_shortcode( 'wc_quick_pay', [ __CLASS__, 'render_shortcode' ] );
    }

    /**
     * Render shortcode.
     *
     * @param array $atts
     * @return string
     */
    public static function render_shortcode( $atts = [] ): string {
        $settings = get_option( 'wc_quick_pay_settings', [] );

        wp_enqueue_style( 'wc-quick-pay-style' );
        wp_enqueue_script( 'wc-quick-pay-script' );

        $prefill = [
            'amount'        => isset( $_GET['amount'] ) ? (float) sanitize_text_field( wp_unslash( $_GET['amount'] ) ) : ( ! empty( $settings['default_amount'] ) ? (float) $settings['default_amount'] : '' ),
            'invoice'       => isset( $_GET['invoice'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice'] ) ) : ( isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( $_GET['order_id'] ) ) : '' ),
            'name'          => isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : '',
            'email'         => isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '',
            'phone'         => isset( $_GET['phone'] ) ? sanitize_text_field( wp_unslash( $_GET['phone'] ) ) : '',
            'notes'         => isset( $_GET['notes'] ) ? sanitize_textarea_field( wp_unslash( $_GET['notes'] ) ) : ( isset( $_GET['desc'] ) ? sanitize_textarea_field( wp_unslash( $_GET['desc'] ) ) : '' ),
            'gateway_id'    => isset( $_GET['gateway'] ) ? sanitize_text_field( wp_unslash( $_GET['gateway'] ) ) : '',
            'lock'          => isset( $_GET['lock'] ) && in_array( $_GET['lock'], [ '1', 'yes', 'true' ], true ),
            'hide_customer' => isset( $_GET['hide_customer'] ) && in_array( $_GET['hide_customer'], [ '1', 'yes', 'true' ], true ),
            'hide_invoice'  => isset( $_GET['hide_invoice'] ) && in_array( $_GET['hide_invoice'], [ '1', 'yes', 'true' ], true ),
            'hide_support'  => isset( $_GET['hide_support'] ) && in_array( $_GET['hide_support'], [ '1', 'yes', 'true' ], true ),
        ];

        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( empty( $prefill['email'] ) ) {
                $prefill['email'] = $user->user_email;
            }
            if ( empty( $prefill['name'] ) ) {
                $prefill['name'] = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
            }
            if ( empty( $prefill['phone'] ) && class_exists( 'WC_Customer' ) ) {
                $customer = new WC_Customer( $user->ID );
                $prefill['phone'] = $customer->get_billing_phone();
            }
        }

        ob_start();
        ?>
        <div class="wc-quick-pay-shortcode-wrapper">
            <?php include WC_QUICK_PAY_PATH . 'templates/quick-pay-form.php'; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Hook into init.
add_action( 'init', [ 'WC_Quick_Pay_Shortcode', 'init' ] );
