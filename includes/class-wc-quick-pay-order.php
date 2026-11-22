<?php
/**
 * Quick Pay Order & Product Handler.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

class WC_Quick_Pay_Order {

    const OPTION_PRODUCT_ID = 'wc_quick_pay_product_id';

    /**
     * Init hooks for admin order listing and order details.
     */
    public static function init(): void {
        // HPOS and legacy order columns.
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ __CLASS__, 'add_order_column' ], 20 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ __CLASS__, 'render_order_column_hpos' ], 20, 2 );

        add_filter( 'manage_shop_order_posts_columns', [ __CLASS__, 'add_order_column' ], 20 );
        add_action( 'manage_shop_order_posts_custom_column', [ __CLASS__, 'render_order_column_legacy' ], 20, 2 );

        // Order metabox in admin.
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_order_metabox' ], 10, 2 );

        // Ensure customer data sync with WC session.
        add_filter( 'woocommerce_checkout_get_value', [ __CLASS__, 'prefill_checkout_field' ], 10, 2 );
    }

    /**
     * Ensure backing hidden product exists.
     *
     * @return int Product ID.
     */
    public static function ensure_backing_product(): int {
        $product_id = (int) get_option( self::OPTION_PRODUCT_ID, 0 );

        if ( $product_id > 0 && get_post_status( $product_id ) === 'publish' ) {
            return $product_id;
        }

        // Check by title or create new.
        $existing = get_page_by_title( 'دفع سريع - Quick Payment', OBJECT, 'product' );
        if ( $existing && $existing->ID > 0 ) {
            update_option( self::OPTION_PRODUCT_ID, $existing->ID );
            return $existing->ID;
        }

        // Create new hidden virtual product.
        $product = new WC_Product_Simple();
        $product->set_name( __( 'سداد سريع / دفع مخصص (Quick Payment)', 'wc-quick-pay' ) );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product->set_price( 0 );
        $product->set_regular_price( 0 );
        $product->set_description( __( 'منتج افتراضي مخصص لعمليات السداد والدفع السريع المباشر.', 'wc-quick-pay' ) );
        
        $new_id = $product->save();

        if ( $new_id ) {
            // Set visibility taxonomy to hidden.
            wp_set_object_terms( $new_id, [ 'exclude-from-catalog', 'exclude-from-search' ], 'product_visibility' );
            update_option( self::OPTION_PRODUCT_ID, $new_id );
            return $new_id;
        }

        return 0;
    }

    /**
     * Get backing product ID.
     *
     * @return int
     */
    public static function get_product_id(): int {
        return self::ensure_backing_product();
    }

    /**
     * Create WooCommerce Order for Quick Pay.
     *
     * @param array $args Form input data.
     * @return WC_Order|WP_Error
     */
    public static function create_quick_pay_order( array $args ) {
        $amount     = (float) ( $args['amount'] ?? 0 );
        $full_name  = sanitize_text_field( $args['name'] ?? '' );
        $email      = sanitize_email( $args['email'] ?? '' );
        $phone      = sanitize_text_field( $args['phone'] ?? '' );
        $invoice_no = sanitize_text_field( $args['invoice'] ?? '' );
        $notes      = sanitize_textarea_field( $args['notes'] ?? '' );
        $gateway_id = sanitize_text_field( $args['gateway_id'] ?? '' );

        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_amount', __( 'المبلغ المدخل غير صحيح.', 'wc-quick-pay' ) );
        }

        // Split name into first and last name.
        $parts      = explode( ' ', trim( $full_name ), 2 );
        $first_name = $parts[0] ?? $full_name;
        $last_name  = $parts[1] ?? '.';

        // Get backing product.
        $product_id = self::get_product_id();
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            return new WP_Error( 'missing_product', __( 'تعذر العثور على منتج الدفع السريع المخصص.', 'wc-quick-pay' ) );
        }

        // Create new WooCommerce Order.
        $order = wc_create_order( [
            'status'        => 'pending',
            'customer_id'   => get_current_user_id() ?: 0,
            'customer_note' => $notes,
        ] );

        if ( is_wp_error( $order ) || ! $order ) {
            return new WP_Error( 'order_creation_failed', __( 'فشل في إنشاء الطلب. يرجى المحاولة مرة أخرى.', 'wc-quick-pay' ) );
        }

        // Set Billing Details.
        $order->set_billing_first_name( $first_name );
        $order->set_billing_last_name( $last_name );
        $order->set_billing_email( $email );
        $order->set_billing_phone( $phone );
        $order->set_billing_country( 'SA' ); // Default to SA for region

        // Add line item with custom dynamic price.
        $item = new WC_Order_Item_Product();
        $item->set_product( $product );
        $item->set_quantity( 1 );
        $item->set_subtotal( $amount );
        $item->set_total( $amount );

        // Item customized title.
        $item_title = sprintf(
            /* translators: 1: invoice number */
            __( 'سداد سريع - فاتورة #%s', 'wc-quick-pay' ),
            $invoice_no ?: $order->get_id()
        );
        $item->set_name( $item_title );

        if ( ! empty( $invoice_no ) ) {
            $item->add_meta_data( __( 'رقم الفاتورة / المرجع', 'wc-quick-pay' ), $invoice_no, true );
        }
        if ( ! empty( $notes ) ) {
            $item->add_meta_data( __( 'ملاحظات العميل', 'wc-quick-pay' ), $notes, true );
        }

        $order->add_item( $item );

        // Set payment gateway.
        $gateways = WC()->payment_gateways()->payment_gateways();
        if ( isset( $gateways[ $gateway_id ] ) ) {
            $order->set_payment_method( $gateways[ $gateway_id ] );
        } else {
            $order->set_payment_method( $gateway_id );
        }

        // Save Quick Pay Order Metadata.
        $order->update_meta_data( '_is_quick_pay', 'yes' );
        $order->update_meta_data( '_quick_pay_invoice_number', $invoice_no );
        $order->update_meta_data( '_quick_pay_amount', $amount );
        $order->update_meta_data( '_quick_pay_customer_notes', $notes );
        $order->update_meta_data( '_quick_pay_created_at', current_time( 'mysql' ) );

        // Calculate totals.
        $order->set_total( $amount );
        $order->calculate_totals( false );

        // Add note.
        $note_text = sprintf(
            /* translators: 1: invoice 2: amount 3: currency 4: notes */
            __( '⚡ طلب دفع سريع: رقم الفاتورة [%1$s] | المبلغ [%2$s %3$s]. ملاحظات: %4$s', 'wc-quick-pay' ),
            $invoice_no ?: 'بدون رقم',
            number_format( $amount, 2 ),
            get_woocommerce_currency(),
            $notes ?: 'لا توجد'
        );
        $order->add_order_note( $note_text );

        // Save order.
        $order->save();

        // Sync customer session so fields are preserved if navigated to checkout or back.
        if ( WC()->session ) {
            WC()->session->set( 'quick_pay_last_order', $order->get_id() );
            WC()->session->set( 'quick_pay_customer_name', $full_name );
            WC()->session->set( 'quick_pay_customer_email', $email );
            WC()->session->set( 'quick_pay_customer_phone', $phone );
            WC()->session->set( 'quick_pay_invoice_no', $invoice_no );
        }

        if ( WC()->customer ) {
            WC()->customer->set_billing_first_name( $first_name );
            WC()->customer->set_billing_last_name( $last_name );
            WC()->customer->set_billing_email( $email );
            WC()->customer->set_billing_phone( $phone );
            WC()->customer->save();
        }

        return $order;
    }

    /**
     * Add Quick Pay column in Orders list.
     *
     * @param array $columns
     * @return array
     */
    public static function add_order_column( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $column ) {
            $new_columns[ $key ] = $column;
            if ( 'order_status' === $key || 'order_number' === $key ) {
                $new_columns['quick_pay_ref'] = __( 'نوع السداد / الفاتورة', 'wc-quick-pay' );
            }
        }
        if ( ! isset( $new_columns['quick_pay_ref'] ) ) {
            $new_columns['quick_pay_ref'] = __( 'نوع السداد / الفاتورة', 'wc-quick-pay' );
        }
        return $new_columns;
    }

    /**
     * Render Order column for HPOS.
     *
     * @param string $column
     * @param WC_Order $order
     */
    public static function render_order_column_hpos( string $column, $order ): void {
        if ( 'quick_pay_ref' === $column && $order instanceof WC_Order ) {
            self::render_order_badge( $order );
        }
    }

    /**
     * Render Order column for Legacy posts.
     *
     * @param string $column
     * @param int $post_id
     */
    public static function render_order_column_legacy( string $column, int $post_id ): void {
        if ( 'quick_pay_ref' === $column ) {
            $order = wc_get_order( $post_id );
            if ( $order ) {
                self::render_order_badge( $order );
            }
        }
    }

    /**
     * Output Quick Pay badge in admin table.
     *
     * @param WC_Order $order
     */
    private static function render_order_badge( WC_Order $order ): void {
        $is_quick_pay = $order->get_meta( '_is_quick_pay' );
        if ( 'yes' === $is_quick_pay ) {
            $invoice_no = $order->get_meta( '_quick_pay_invoice_number' );
            echo '<span style="display:inline-flex;align-items:center;gap:4px;background:#e0f2fe;color:#0369a1;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;border:1px solid #bae6fd;">' .
                 '⚡ ' . esc_html__( 'دفع سريع', 'wc-quick-pay' ) .
                 ( $invoice_no ? ': #' . esc_html( $invoice_no ) : '' ) .
                 '</span>';
        } else {
            echo '<span style="color:#94a3b8;font-size:12px;">—</span>';
        }
    }

    /**
     * Register metabox on order edit page.
     *
     * @param string $post_type
     * @param mixed $post_or_order
     */
    public static function register_order_metabox( $post_type, $post_or_order = null ): void {
        $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) &&
                  wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
                  ? wc_get_page_screen_id( 'shop_order' )
                  : 'shop_order';

        add_meta_box(
            'wc_quick_pay_order_metabox',
            __( 'بيانات الدفع السريع (Quick Pay)', 'wc-quick-pay' ),
            [ __CLASS__, 'render_order_metabox' ],
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Render order metabox.
     *
     * @param mixed $post_or_order
     */
    public static function render_order_metabox( $post_or_order ): void {
        $order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );

        if ( ! $order ) {
            return;
        }

        $is_quick_pay = $order->get_meta( '_is_quick_pay' );
        if ( 'yes' !== $is_quick_pay ) {
            echo '<p style="color:#64748b;font-size:13px;margin:0;">' . esc_html__( 'هذا الطلب لم يتم إنشاؤه عبر صفحة الدفع السريع.', 'wc-quick-pay' ) . '</p>';
            return;
        }

        $invoice_no = $order->get_meta( '_quick_pay_invoice_number' );
        $amount     = $order->get_meta( '_quick_pay_amount' );
        $notes      = $order->get_meta( '_quick_pay_customer_notes' );
        $created_at = $order->get_meta( '_quick_pay_created_at' );

        ?>
        <div style="font-size:13px;line-height:1.6;">
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:8px 10px;border-radius:6px;font-weight:bold;margin-bottom:10px;">
                ✅ <?php esc_html_e( 'تم الدفع عبر رابط السداد المباشر', 'wc-quick-pay' ); ?>
            </div>
            <p style="margin:4px 0;"><strong><?php esc_html_e( 'رقم الفاتورة / المرجع:', 'wc-quick-pay' ); ?></strong> <span style="font-family:monospace;font-size:14px;color:#0284c7;"><?php echo esc_html( $invoice_no ?: '—' ); ?></span></p>
            <p style="margin:4px 0;"><strong><?php esc_html_e( 'المبلغ المحدد:', 'wc-quick-pay' ); ?></strong> <?php echo wc_price( $amount ?: $order->get_total() ); ?></p>
            <?php if ( ! empty( $notes ) ) : ?>
                <p style="margin:4px 0;"><strong><?php esc_html_e( 'ملاحظات العميل:', 'wc-quick-pay' ); ?></strong></p>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:6px 10px;border-radius:4px;margin-top:2px;color:#334155;"><?php echo nl2br( esc_html( $notes ) ); ?></div>
            <?php endif; ?>
            <?php if ( ! empty( $created_at ) ) : ?>
                <p style="margin:8px 0 0;font-size:11px;color:#94a3b8;"><?php printf( esc_html__( 'تاريخ الإنشاء: %s', 'wc-quick-pay' ), esc_html( $created_at ) ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Prefill checkout fields if customer navigates to checkout later.
     *
     * @param mixed $value
     * @param string $input
     * @return mixed
     */
    public static function prefill_checkout_field( $value, string $input ) {
        if ( ! empty( $value ) || ! WC()->session ) {
            return $value;
        }

        switch ( $input ) {
            case 'billing_first_name':
                return WC()->session->get( 'quick_pay_customer_name', $value );
            case 'billing_email':
                return WC()->session->get( 'quick_pay_customer_email', $value );
            case 'billing_phone':
                return WC()->session->get( 'quick_pay_customer_phone', $value );
        }

        return $value;
    }
}

// Initialize order hooks.
add_action( 'init', [ 'WC_Quick_Pay_Order', 'init' ] );
