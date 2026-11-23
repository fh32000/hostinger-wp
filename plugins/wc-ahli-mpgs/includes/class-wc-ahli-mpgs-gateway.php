<?php
/**
 * WooCommerce Gateway Class for Ahli Bank (MPGS)
 *
 * @package WC_Ahli_MPGS
 */

defined( 'ABSPATH' ) || exit;

class WC_Ahli_MPGS_Gateway extends WC_Payment_Gateway {

    /** Meta key where we store the MPGS success indicator per order. */
    const META_SUCCESS_INDICATOR = '_ahli_mpgs_success_indicator';
    const META_SESSION_ID        = '_ahli_mpgs_session_id';
    const META_MPGS_ORDER_ID     = '_ahli_mpgs_order_id';
    const META_TRANSACTION_ID    = '_ahli_mpgs_transaction_id';

    // ─── Constructor ──────────────────────────────────────────────────────────

    public function __construct() {
        $this->id                 = 'wc_ahli_mpgs_gateway';
        $this->icon               = WC_AHLI_MPGS_URL . 'assets/images/ahli-logo.png'; // optional
        $this->has_fields         = false;
        $this->method_title       = __( 'بوابة الأهلي (MPGS)', 'wc-ahli-mpgs' );
        $this->method_description = __( 'قبول المدفوعات عبر بوابة البنك الأهلي المدعومة بـ Mastercard Payment Gateway Services.', 'wc-ahli-mpgs' );
        $this->supports           = [ 'products', 'refunds' ];
        $this->order_button_text  = __( 'ادفع الآن عبر الأهلي', 'wc-ahli-mpgs' );

        // Load settings.
        $this->init_form_fields();
        $this->init_settings();

        // Assign settings to properties.
        $this->title             = $this->get_option( 'title' );
        $this->description       = $this->get_option( 'description' );
        $this->merchant_id       = $this->get_option( 'merchant_id' );
        $this->api_password      = $this->get_option( 'api_password' );
        $this->refund_password   = $this->get_option( 'refund_password' );
        $this->test_mode         = 'yes' === $this->get_option( 'test_mode' );
        $this->api_version       = (int) $this->get_option( 'api_version', 61 );
        $this->debug             = 'yes' === $this->get_option( 'debug' );
        $this->debug_on_checkout = 'yes' === $this->get_option( 'debug_on_checkout', 'yes' );
        $this->license_api_url   = $this->get_option( 'license_api_url' );
        $this->whatsapp_number   = $this->get_option( 'whatsapp_number', '966500000000' );
        $this->enabled           = $this->get_option( 'enabled' );

        // Hooks.
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_api_' . $this->id, [ $this, 'handle_return' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ] );

        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
            add_action( 'admin_notices', [ $this, 'admin_notices' ] );
        }
    }

    public function frontend_scripts(): void {
        if ( is_checkout() || is_checkout_pay_page() || ( function_exists( 'is_cart' ) && is_cart() ) ) {
            wp_enqueue_style( 'wc-ahli-mpgs-checkout', WC_AHLI_MPGS_URL . 'assets/css/checkout.css', [], WC_AHLI_MPGS_VERSION );
        }
    }

    /**
     * Return the gateway icon HTML with responsive styling.
     *
     * @return string
     */
    public function get_icon(): string {
        $icon = '';
        if ( ! empty( $this->icon ) ) {
            $icon = sprintf(
                '<img src="%s" alt="%s" class="ahli-mpgs-logo" style="max-height: 28px; width: auto; vertical-align: middle; margin-right: 8px; margin-left: 8px; display: inline-block;" />',
                esc_url( WC_HTTPS::force_https_url( $this->icon ) ),
                esc_attr( $this->get_title() )
            );
        }

        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
    }

    public function admin_scripts(): void {
        if ( isset( $_GET['section'] ) && $_GET['section'] === $this->id ) {
            wp_enqueue_style( 'wc-ahli-mpgs-admin', WC_AHLI_MPGS_URL . 'assets/css/checkout.css', [], WC_AHLI_MPGS_VERSION );
        }
    }

    // ─── Admin Settings ───────────────────────────────────────────────────────

    public function init_form_fields(): void {
        $this->form_fields = [

            'enabled' => [
                'title'   => __( 'تفعيل / تعطيل', 'wc-ahli-mpgs' ),
                'type'    => 'checkbox',
                'label'   => __( 'تفعيل بوابة الأهلي (MPGS)', 'wc-ahli-mpgs' ),
                'default' => 'no',
            ],

            'title' => [
                'title'       => __( 'العنوان', 'wc-ahli-mpgs' ),
                'type'        => 'text',
                'description' => __( 'الاسم الذي يراه العميل عند الدفع.', 'wc-ahli-mpgs' ),
                'default'     => __( 'بطاقة بنكية (الأهلي)', 'wc-ahli-mpgs' ),
                'desc_tip'    => true,
            ],

            'description' => [
                'title'       => __( 'الوصف', 'wc-ahli-mpgs' ),
                'type'        => 'textarea',
                'description' => __( 'وصف يظهر أسفل طريقة الدفع في صفحة الدفع.', 'wc-ahli-mpgs' ),
                'default'     => __( 'ادفع بأمان عبر بوابة البنك الأهلي — ادعم فيزا وماستركارد ومدى.', 'wc-ahli-mpgs' ),
            ],

            'credentials_section' => [
                'title'       => __( 'بيانات الاعتماد', 'wc-ahli-mpgs' ),
                'type'        => 'title',
                'description' => __( 'أدخل بيانات حسابك التجاري من بوابة MPGS.', 'wc-ahli-mpgs' ),
            ],

            'merchant_id' => [
                'title'       => __( 'Merchant ID', 'wc-ahli-mpgs' ),
                'type'        => 'text',
                'description' => __( 'معرّف التاجر الخاص بك كما هو موضح في لوحة تحكم البوابة.', 'wc-ahli-mpgs' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => 'TEST123456 أو MERCHANT_ID',
            ],

            'api_password' => [
                'title'       => __( 'API Password (Password 1)', 'wc-ahli-mpgs' ),
                'type'        => 'password',
                'description' => __( 'كلمة مرور الـ API الرئيسية (Password 1) من لوحة تحكم MPGS.', 'wc-ahli-mpgs' ),
                'default'     => '',
                'desc_tip'    => true,
            ],

            'refund_password' => [
                'title'       => __( 'Refund API Password (Excessive Refunds)', 'wc-ahli-mpgs' ),
                'type'        => 'password',
                'description' => __( 'كلمة مرور استرداد المدفوعات — اتركها فارغة إذا لم تكن مفعّلة.', 'wc-ahli-mpgs' ),
                'default'     => '',
                'desc_tip'    => true,
            ],

            'api_version' => [
                'title'       => __( 'MPGS API Version', 'wc-ahli-mpgs' ),
                'type'        => 'number',
                'description' => __( 'رقم إصدار الـ API. تم التحقق أن الإصدار 61 يعمل مع بوابة الأهلي.', 'wc-ahli-mpgs' ),
                'default'     => '61',
                'desc_tip'    => true,
                'custom_attributes' => [ 'min' => '50', 'max' => '100' ],
            ],

            'environment_section' => [
                'title' => __( 'البيئة والاختبار', 'wc-ahli-mpgs' ),
                'type'  => 'title',
            ],

            'test_mode' => [
                'title'       => __( 'وضع الاختبار (Test Mode)', 'wc-ahli-mpgs' ),
                'type'        => 'checkbox',
                'label'       => __( 'تفعيل وضع الاختبار (استخدم Merchant ID مسبوقاً بـ TEST)', 'wc-ahli-mpgs' ),
                'description' => __( 'في وضع الاختبار لن تُسحب أموال حقيقية. يتطلب Merchant ID خاصاً بالاختبار.', 'wc-ahli-mpgs' ),
                'default'     => 'yes',
                'desc_tip'    => false,
            ],

            'debug' => [
                'title'       => __( 'سجل التنقيح (Debug Log)', 'wc-ahli-mpgs' ),
                'type'        => 'checkbox',
                'label'       => __( 'تسجيل أحداث MPGS في WooCommerce → Status → Logs', 'wc-ahli-mpgs' ),
                'default'     => 'yes',
                'description' => sprintf(
                    __( 'عرض السجل في <a href="%s">WooCommerce → Status → Logs</a>.', 'wc-ahli-mpgs' ),
                    esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) )
                ),
            ],

            'debug_on_checkout' => [
                'title'       => __( '🛠️ عرض تفاصيل الخطأ في صفحة الدفع', 'wc-ahli-mpgs' ),
                'type'        => 'checkbox',
                'label'       => __( 'عرض الـ API response كاملاً في رسالة الخطأ (للتطوير فقط — أوقفه في Production)', 'wc-ahli-mpgs' ),
                'default'     => 'yes',
                'description' => '<span style="color:#c0392b;font-weight:bold;">' .
                                 __( '⚠️ لا تُبقِه مفعّلاً على الموقع الحقيقي — يظهر معلومات حساسة.', 'wc-ahli-mpgs' ) .
                                 '</span>',
            ],

            'license_section' => [
                'title'       => __( 'إعدادات خادم الترخيص (License Server)', 'wc-ahli-mpgs' ),
                'type'        => 'title',
                'description' => __( 'إعدادات الربط مع Google Apps Script Web App لإدارة التراخيص والعملاء.', 'wc-ahli-mpgs' ),
            ],

            'license_api_url' => [
                'title'       => __( 'رابط خادم الترخيص (Web App URL)', 'wc-ahli-mpgs' ),
                'type'        => 'url',
                'description' => __( 'الصق هنا رابط Google Apps Script Web App الناتج من نشر الإسكربت.', 'wc-ahli-mpgs' ),
                'default'     => '',
                'placeholder' => 'https://script.google.com/macros/s/.../exec',
            ],

            'whatsapp_number' => [
                'title'       => __( 'رقم الواتساب للمبيعات', 'wc-ahli-mpgs' ),
                'type'        => 'text',
                'description' => __( 'رقمك مع رمز الدولة (مثال: 966501234567) لتلقي طلبات شراء الترخيص.', 'wc-ahli-mpgs' ),
                'default'     => '966500000000',
                'placeholder' => '966501234567',
            ],
        ];
    }

    /**
     * Check if gateway is available for checkout.
     */
    public function is_available(): bool {
        return ( 'yes' === $this->enabled );
    }

    // ─── Admin Options & License UI ──────────────────────────────────────────

    public function admin_options(): void {
        $this->handle_license_actions();
        $this->render_license_banner();
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    private function handle_license_actions(): void {
        if ( ! isset( $_POST['ahli_license_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ahli_license_nonce'] ) ), 'ahli_license_action' ) ) {
            return;
        }

        if ( isset( $_POST['ahli_activate_license'] ) ) {
            $key        = sanitize_text_field( wp_unslash( $_POST['ahli_license_key'] ?? '' ) );
            $email      = sanitize_email( wp_unslash( $_POST['ahli_client_email'] ?? '' ) );
            $phone      = sanitize_text_field( wp_unslash( $_POST['ahli_client_phone'] ?? '' ) );
            $server_url = esc_url_raw( wp_unslash( $_POST['ahli_server_url'] ?? $this->license_api_url ) );

            $result = WC_Ahli_MPGS_License::activate( $key, $email, $phone, $server_url );

            if ( $result['success'] ) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html( $result['message'] ) . '</strong></p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html( $result['message'] ) . '</strong></p></div>';
            }
        } elseif ( isset( $_POST['ahli_deactivate_license'] ) ) {
            WC_Ahli_MPGS_License::deactivate();
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'تم إلغاء تفعيل الترخيص على هذا الموقع.', 'wc-ahli-mpgs' ) . '</p></div>';
        }
    }

    private function render_license_banner(): void {
        $data           = WC_Ahli_MPGS_License::get_license_data();
        $is_active      = WC_Ahli_MPGS_License::is_active();
        $current_domain = WC_Ahli_MPGS_License::get_site_domain();
        $whatsapp       = preg_replace( '/[^0-9]/', '', $this->whatsapp_number );
        $wa_msg         = rawurlencode( "مرحباً، أرغب في شراء وتفعيل ترخيص إضافة بوابة البنك الأهلي (MPGS) لموقعي: " . home_url() );
        $wa_link        = "https://wa.me/{$whatsapp}?text={$wa_msg}";
        $server_url     = ! empty( $data['server_url'] ) ? $data['server_url'] : $this->license_api_url;

        ?>
        <div class="ahli-license-card <?php echo $is_active ? 'status-active' : 'status-inactive'; ?>">
            <div class="ahli-license-header">
                <h3 class="ahli-license-title">
                    <span>🔐 حالة ترخيص إضافة بوابة الأهلي (MPGS)</span>
                </h3>
                <div>
                    <?php if ( $is_active ) : ?>
                        <span class="ahli-badge ahli-badge-success">✅ مفعل ومقترن بهذا النطاق</span>
                    <?php else : ?>
                        <span class="ahli-badge ahli-badge-danger">❌ غير مفعل (البوابة معطلة)</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! empty( $data['message'] ) ) : ?>
                <div class="ahli-license-info-row" style="color: <?php echo $is_active ? '#03543f' : '#9b1c1c'; ?>; font-weight:600;">
                    <?php echo esc_html( $data['message'] ); ?>
                </div>
            <?php endif; ?>

            <div class="ahli-license-info-row">
                <strong>نطاق هذا الموقع:</strong> <code><?php echo esc_html( $current_domain ); ?></code>
                <?php if ( ! empty( $data['bound_domain'] ) ) : ?>
                    &nbsp;|&nbsp; <strong>النطاق المرتبط بالترخيص:</strong> <code><?php echo esc_html( $data['bound_domain'] ); ?></code>
                <?php endif; ?>
            </div>

            <form method="post" action="" style="margin-top: 15px;">
                <?php wp_nonce_field( 'ahli_license_action', 'ahli_license_nonce' ); ?>
                <input type="hidden" name="ahli_server_url" value="<?php echo esc_attr( $server_url ); ?>">

                <div class="ahli-license-grid">
                    <div class="ahli-license-field">
                        <label for="ahli_client_email">البريد الإلكتروني <span style="color:red;">*</span></label>
                        <input type="email" id="ahli_client_email" name="ahli_client_email" required value="<?php echo esc_attr( $data['client_email'] ); ?>" placeholder="email@example.com" <?php echo $is_active ? 'readonly' : ''; ?>>
                    </div>
                    <div class="ahli-license-field">
                        <label for="ahli_client_phone">رقم الجوال <span style="color:red;">*</span></label>
                        <input type="tel" id="ahli_client_phone" name="ahli_client_phone" required value="<?php echo esc_attr( $data['client_phone'] ); ?>" placeholder="05xxxxxxxx" <?php echo $is_active ? 'readonly' : ''; ?>>
                    </div>
                    <div class="ahli-license-field" style="grid-column: 1 / -1;">
                        <label for="ahli_license_key">مفتاح الترخيص (License Key) <span style="color:red;">*</span></label>
                        <input type="text" id="ahli_license_key" name="ahli_license_key" required value="<?php echo esc_attr( $data['license_key'] ); ?>" placeholder="AHLI-PRO-XXXX-XXXX" <?php echo $is_active ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="ahli-license-actions">
                    <?php if ( ! $is_active ) : ?>
                        <button type="submit" name="ahli_activate_license" class="button button-primary button-large">
                            🚀 تفعيل الترخيص وربطه بهذا الموقع
                        </button>
                        <?php if ( ! empty( $whatsapp ) ) : ?>
                            <a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" class="ahli-btn-whatsapp">
                                💬 طلب شراء مفتاح الترخيص عبر واتساب
                            </a>
                        <?php endif; ?>
                    <?php else : ?>
                        <button type="submit" name="ahli_activate_license" class="button button-secondary">
                            🔄 إعادة التحقق من الترخيص
                        </button>
                        <button type="submit" name="ahli_deactivate_license" class="button button-link-delete" onclick="return confirm('هل أنت متأكد من إلغاء تفعيل الترخيص على هذا الموقع؟');">
                            إلغاء التفعيل
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
    }

    // ─── Admin Notices ────────────────────────────────────────────────────────

    public function admin_notices(): void {
        if ( 'yes' !== $this->enabled ) {
            return;
        }

        if ( empty( $this->merchant_id ) || empty( $this->api_password ) ) {
            echo '<div class="notice notice-warning"><p>' .
                 sprintf(
                     /* translators: %s settings URL */
                     __( '<strong>بوابة الأهلي (MPGS):</strong> يرجى إدخال Merchant ID وAPI Password في <a href="%s">إعدادات البوابة</a>.', 'wc-ahli-mpgs' ),
                     esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $this->id ) )
                 ) .
                 '</p></div>';
        }
    }

    // ─── Payment Processing ───────────────────────────────────────────────────

    /**
     * Process the payment and redirect the customer to the MPGS Hosted Checkout page.
     *
     * @param int $order_id  WooCommerce order ID.
     * @return array{result: string, redirect: string}
     */
    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'الطلب غير موجود. يرجى المحاولة مرة أخرى.', 'wc-ahli-mpgs' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        // Validate credentials.
        if ( empty( $this->merchant_id ) || empty( $this->api_password ) ) {
            $this->log( 'Missing merchant_id or api_password.', 'error' );
            wc_add_notice( __( 'خطأ في إعداد بوابة الدفع. يرجى التواصل مع الدعم.', 'wc-ahli-mpgs' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        // Build a unique MPGS order ID (must be unique per transaction attempt).
        $mpgs_order_id = 'WC-' . $order_id . '-' . time();

        // Return URL — WooCommerce API endpoint on this site.
        $return_url = add_query_arg(
            [
                'wc-api'    => $this->id,
                'order_id'  => $order_id,
                'mpgs_oid'  => $mpgs_order_id,
            ],
            home_url( '/' )
        );

        // Build MPGS session payload.
        // NOTE: 'interaction.operation' is NOT supported in CREATE_CHECKOUT_SESSION on this gateway version.
        $payload = [
            'apiOperation' => 'CREATE_CHECKOUT_SESSION',
            'order'        => [
                'id'          => $mpgs_order_id,
                'amount'      => number_format( (float) $order->get_total(), 2, '.', '' ),
                'currency'    => get_woocommerce_currency(),
                'description' => sprintf(
                    /* translators: order ID */
                    __( 'طلب رقم #%d من %s', 'wc-ahli-mpgs' ),
                    $order_id,
                    get_bloginfo( 'name' )
                ),
            ],
            'interaction'  => [
                'operation' => 'PURCHASE',
                'returnUrl' => $return_url,
                'cancelUrl' => wc_get_checkout_url(),
                'merchant'  => [
                    'name' => get_bloginfo( 'name' ),
                    'url'  => home_url( '/' ),
                ],
            ],
            'customer' => [
                'email'     => $order->get_billing_email(),
                'firstName' => $order->get_billing_first_name(),
                'lastName'  => $order->get_billing_last_name(),
                'phone'     => $order->get_billing_phone(),
            ],
        ];

        // Call MPGS API.
        $api      = $this->get_api();
        $response = $api->create_checkout_session( $payload );

        if ( ! $response['success'] ) {
            $this->log( 'create_checkout_session failed: ' . wp_json_encode( $response ), 'error' );

            $error_message = __( 'تعذر إنشاء جلسة الدفع. يرجى المحاولة مرة أخرى أو التواصل مع الدعم.', 'wc-ahli-mpgs' );

            if ( $this->debug_on_checkout ) {
                $raw_response = wp_json_encode( $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                $error_message .= '<div style="margin-top:15px;padding:12px;background:#1e293b;color:#f8fafc;border-radius:6px;font-family:monospace;font-size:12px;text-align:left;direction:ltr;border:1px solid #475569;">';
                $error_message .= '<div style="font-weight:bold;color:#f59e0b;margin-bottom:8px;">🛠️ [Debug Mode - MPGS API Response]:</div>';
                $error_message .= '<pre style="margin:0;color:#38bdf8;white-space:pre-wrap;word-break:break-all;">' . esc_html( $raw_response ) . '</pre>';
                $error_message .= '</div>';
            }

            wc_add_notice( $error_message, 'error' );
            return [ 'result' => 'failure' ];
        }

        $data             = $response['data'];
        $session_id       = $data['session']['id']       ?? '';
        $success_indicator = $data['successIndicator']  ?? '';
        $checkout_version  = $data['session']['version'] ?? '';

        if ( empty( $session_id ) || empty( $success_indicator ) ) {
            $this->log( 'Missing session.id or successIndicator in response: ' . wp_json_encode( $data ), 'error' );

            $error_message = __( 'استجابة غير صحيحة من بوابة الدفع.', 'wc-ahli-mpgs' );

            if ( $this->debug_on_checkout ) {
                $raw_response = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                $error_message .= '<div style="margin-top:15px;padding:12px;background:#1e293b;color:#f8fafc;border-radius:6px;font-family:monospace;font-size:12px;text-align:left;direction:ltr;border:1px solid #475569;">';
                $error_message .= '<div style="font-weight:bold;color:#f59e0b;margin-bottom:8px;">🛠️ [Debug Mode - Incomplete API Data]:</div>';
                $error_message .= '<pre style="margin:0;color:#38bdf8;white-space:pre-wrap;word-break:break-all;">' . esc_html( $raw_response ) . '</pre>';
                $error_message .= '</div>';
            }

            wc_add_notice( $error_message, 'error' );
            return [ 'result' => 'failure' ];
        }

        // Persist MPGS data on the order for verification on return.
        $order->update_meta_data( self::META_SUCCESS_INDICATOR, $success_indicator );
        $order->update_meta_data( self::META_SESSION_ID,        $session_id );
        $order->update_meta_data( self::META_MPGS_ORDER_ID,     $mpgs_order_id );
        $order->update_meta_data( '_ahli_mpgs_checkout_version', $checkout_version );
        $order->save();

        // Mark as pending.
        $order->update_status( 'pending', __( 'في انتظار الدفع عبر بوابة الأهلي (MPGS).', 'wc-ahli-mpgs' ) );

        // Empty the cart.
        WC()->cart->empty_cart();

        // Redirect URL to MPGS Hosted Checkout page.
        $checkout_url = $this->get_hosted_checkout_url( $session_id );

        $this->log( "Order #{$order_id}: redirecting to MPGS. session={$session_id}" );

        return [
            'result'   => 'success',
            'redirect' => $checkout_url,
        ];
    }

    // ─── Return Handler ───────────────────────────────────────────────────────

    /**
     * Handle customer return from MPGS hosted checkout.
     * Called via: /?wc-api=wc_ahli_mpgs_gateway
     */
    public function handle_return(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $order_id        = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $result_indicator = isset( $_GET['resultIndicator'] ) ? sanitize_text_field( wp_unslash( $_GET['resultIndicator'] ) ) : '';
        $mpgs_order_id   = isset( $_GET['mpgs_oid'] ) ? sanitize_text_field( wp_unslash( $_GET['mpgs_oid'] ) ) : '';
        // phpcs:enable

        if ( ! $order_id ) {
            $this->log( 'handle_return: missing order_id', 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->log( "handle_return: order #{$order_id} not found", 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        $stored_indicator = $order->get_meta( self::META_SUCCESS_INDICATOR );

        $this->log( "handle_return order #{$order_id}: resultIndicator={$result_indicator}, stored={$stored_indicator}" );

        // Compare result indicator with what we stored before redirect.
        if ( ! empty( $result_indicator ) && hash_equals( (string) $stored_indicator, (string) $result_indicator ) ) {
            // Payment successful.
            $this->payment_complete( $order, $result_indicator );
            wp_safe_redirect( $this->get_return_url( $order ) );
        } else {
            // Payment failed or cancelled.
            $this->payment_failed( $order, $result_indicator );
            $fail_message = empty( $result_indicator )
                ? __( 'تم إلغاء عملية الدفع.', 'wc-ahli-mpgs' )
                : __( 'فشلت عملية الدفع. يرجى المحاولة مرة أخرى.', 'wc-ahli-mpgs' );

            wc_add_notice( $fail_message, 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
        }

        exit;
    }

    // ─── Refunds ──────────────────────────────────────────────────────────────

    /**
     * Process refund via MPGS API.
     *
     * @param int        $order_id  WooCommerce order ID.
     * @param float|null $amount    Amount to refund (null = full refund).
     * @param string     $reason    Reason for refund.
     * @return bool|WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        if ( empty( $this->refund_password ) ) {
            return new WP_Error(
                'mpgs_refund_no_password',
                __( 'لم يتم تعيين كلمة مرور الاسترداد (Refund Password) في إعدادات البوابة.', 'wc-ahli-mpgs' )
            );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'mpgs_refund_no_order', __( 'الطلب غير موجود.', 'wc-ahli-mpgs' ) );
        }

        $mpgs_order_id    = $order->get_meta( self::META_MPGS_ORDER_ID );
        $transaction_id   = $order->get_meta( self::META_TRANSACTION_ID );
        $refund_amount    = $amount ?? $order->get_total();
        $currency         = $order->get_currency();

        if ( empty( $mpgs_order_id ) ) {
            return new WP_Error( 'mpgs_refund_no_mpgs_order', __( 'لا يوجد معرّف طلب MPGS مرتبط بهذا الطلب.', 'wc-ahli-mpgs' ) );
        }

        $api      = $this->get_api();
        $response = $api->refund( $mpgs_order_id, $transaction_id ?: 'T1', $refund_amount, $currency, $this->refund_password );

        if ( $response['success'] ) {
            $order->add_order_note(
                sprintf(
                    __( 'تم استرداد المبلغ %.2f %s بنجاح عبر بوابة الأهلي (MPGS).', 'wc-ahli-mpgs' ),
                    $refund_amount,
                    $currency
                )
            );
            $this->log( "Refund success for order #{$order_id}: " . wp_json_encode( $response['data'] ) );
            return true;
        }

        $error_msg = $response['message'] ?: __( 'فشل الاسترداد — راجع سجل التنقيح لمزيد من التفاصيل.', 'wc-ahli-mpgs' );
        $this->log( "Refund failed for order #{$order_id}: " . wp_json_encode( $response ), 'error' );
        return new WP_Error( 'mpgs_refund_failed', $error_msg );
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Get initialised API instance.
     */
    private function get_api(): WC_Ahli_MPGS_API {
        return new WC_Ahli_MPGS_API(
            $this->merchant_id,
            $this->api_password,
            $this->test_mode,
            $this->api_version
        );
    }

    /**
     * Build the MPGS Hosted Checkout redirect URL for a given session.
     */
    private function get_hosted_checkout_url( string $session_id ): string {
        return sprintf(
            'https://alahligatway.gateway.mastercard.com/checkout/pay/%s',
            urlencode( $session_id )
        );
    }

    /**
     * Mark order as paid.
     */
    private function payment_complete( WC_Order $order, string $result_indicator ): void {
        $order->payment_complete();
        $order->add_order_note(
            sprintf(
                __( 'تمت عملية الدفع بنجاح عبر بوابة الأهلي (MPGS). Result Indicator: %s', 'wc-ahli-mpgs' ),
                $result_indicator
            )
        );
        $this->log( "Payment complete for order #{$order->get_id()}" );
    }

    /**
     * Mark order as failed.
     */
    private function payment_failed( WC_Order $order, string $result_indicator ): void {
        $order->update_status(
            'failed',
            sprintf(
                __( 'فشلت عملية الدفع عبر بوابة الأهلي (MPGS). Result Indicator: %s', 'wc-ahli-mpgs' ),
                $result_indicator ?: 'N/A'
            )
        );
        $this->log( "Payment failed for order #{$order->get_id()}: indicator={$result_indicator}", 'warning' );
    }

    /**
     * WooCommerce logger wrapper.
     */
    private function log( string $message, string $level = 'info' ): void {
        if ( $this->debug && function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'wc-ahli-mpgs' ] );
        }
    }
}

