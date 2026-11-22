<?php
/**
 * Quick Pay Endpoint & URL Rewrite Routing.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

class WC_Quick_Pay_Endpoint {

    const OPTION_PAGE_ID = 'wc_quick_pay_page_id';

    /**
     * Init hooks.
     */
    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register_rewrites' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
        add_action( 'template_redirect', [ __CLASS__, 'handle_template_redirect' ] );
    }

    /**
     * Get configured slug.
     *
     * @return string
     */
    public static function get_slug(): string {
        $settings = get_option( 'wc_quick_pay_settings', [] );
        return ! empty( $settings['page_slug'] ) ? sanitize_title( $settings['page_slug'] ) : 'pay';
    }

    /**
     * Register URL rewrite rules.
     */
    public static function register_rewrites(): void {
        $slug = self::get_slug();
        add_rewrite_rule( '^' . $slug . '/?$', 'index.php?wc_quick_pay=1', 'top' );
    }

    /**
     * Add query vars.
     *
     * @param array $vars
     * @return array
     */
    public static function add_query_vars( array $vars ): array {
        $vars[] = 'wc_quick_pay';
        return $vars;
    }

    /**
     * Setup WordPress Page on install/update.
     *
     * @return int
     */
    public static function setup_page(): int {
        $page_id = (int) get_option( self::OPTION_PAGE_ID, 0 );

        if ( $page_id > 0 && get_post_status( $page_id ) === 'publish' ) {
            return $page_id;
        }

        $slug = self::get_slug();

        $existing = get_page_by_path( $slug );
        if ( $existing && $existing->ID > 0 ) {
            update_option( self::OPTION_PAGE_ID, $existing->ID );
            return $existing->ID;
        }

        // Create page.
        $new_page_id = wp_insert_post( [
            'post_title'     => __( 'الدفع السريع ومحطة السداد', 'wc-quick-pay' ),
            'post_name'      => $slug,
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_content'   => '[wc_quick_pay]',
            'comment_status' => 'closed',
        ] );

        if ( ! is_wp_error( $new_page_id ) && $new_page_id > 0 ) {
            update_option( self::OPTION_PAGE_ID, $new_page_id );
            return $new_page_id;
        }

        return 0;
    }

    /**
     * Get Quick Pay URL.
     *
     * @param array $params Optional query params.
     * @return string
     */
    public static function get_pay_url( array $params = [] ): string {
        $slug = self::get_slug();
        $url  = home_url( '/' . $slug . '/' );

        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        return $url;
    }

    /**
     * Intercept and load standalone template.
     */
    public static function handle_template_redirect(): void {
        $is_query = (int) get_query_var( 'wc_quick_pay' ) === 1;
        $page_id  = (int) get_option( self::OPTION_PAGE_ID, 0 );
        $is_page  = $page_id > 0 && is_page( $page_id );

        if ( ! $is_query && ! $is_page ) {
            return;
        }

        $settings = get_option( 'wc_quick_pay_settings', [] );

        // If plugin is disabled.
        if ( isset( $settings['enabled'] ) && 'no' === $settings['enabled'] ) {
            return;
        }

        $standalone = ( $settings['standalone_mode'] ?? 'yes' ) === 'yes';

        // In standalone mode, serve clean distraction-free template directly.
        if ( $standalone ) {
            status_header( 200 );
            nocache_headers();

            // Extract prefill query parameters.
            $prefill = [
                'amount'     => isset( $_GET['amount'] ) ? (float) sanitize_text_field( wp_unslash( $_GET['amount'] ) ) : ( ! empty( $settings['default_amount'] ) ? (float) $settings['default_amount'] : '' ),
                'invoice'    => isset( $_GET['invoice'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice'] ) ) : ( isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( $_GET['order_id'] ) ) : '' ),
                'name'       => isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : '',
                'email'      => isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '',
                'phone'      => isset( $_GET['phone'] ) ? sanitize_text_field( wp_unslash( $_GET['phone'] ) ) : '',
                'notes'      => isset( $_GET['notes'] ) ? sanitize_textarea_field( wp_unslash( $_GET['notes'] ) ) : ( isset( $_GET['desc'] ) ? sanitize_textarea_field( wp_unslash( $_GET['desc'] ) ) : '' ),
                'gateway_id' => isset( $_GET['gateway'] ) ? sanitize_text_field( wp_unslash( $_GET['gateway'] ) ) : '',
            ];

            // If user is logged in, auto-fill details from WP user if not provided in URL.
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

            include WC_QUICK_PAY_PATH . 'templates/quick-pay-standalone.php';
            exit;
        }
    }
}

// Hook into WordPress.
add_action( 'init', [ 'WC_Quick_Pay_Endpoint', 'init' ] );
