<?php
/**
 * Quick Pay Admin Settings Page.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

class WC_Quick_Pay_Settings {

    const MENU_SLUG = 'wc-quick-pay-settings';

    /**
     * Init hooks.
     */
    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ], 50 );
        add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    /**
     * Add admin menu under WooCommerce.
     */
    public static function add_admin_menu(): void {
        add_submenu_page(
            'woocommerce',
            __( 'إعدادات الدفع السريع (Quick Pay)', 'wc-quick-pay' ),
            __( 'الدفع السريع ⚡', 'wc-quick-pay' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /**
     * Enqueue admin scripts & styles.
     *
     * @param string $hook
     */
    public static function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, self::MENU_SLUG ) === false ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        wp_enqueue_style(
            'wc-quick-pay-admin-style',
            WC_QUICK_PAY_URL . 'assets/css/quick-pay-admin.css',
            [],
            WC_QUICK_PAY_VERSION
        );

        wp_enqueue_script(
            'wc-quick-pay-admin-script',
            WC_QUICK_PAY_URL . 'assets/js/quick-pay-admin.js',
            [ 'jquery', 'wp-color-picker' ],
            WC_QUICK_PAY_VERSION,
            true
        );

        wp_localize_script(
            'wc-quick-pay-admin-script',
            'wcQuickPayAdmin',
            [
                'base_pay_url' => WC_Quick_Pay_Endpoint::get_pay_url(),
                'copied_text'  => __( 'تم نسخ الرابط بنجاح! 📋', 'wc-quick-pay' ),
            ]
        );
    }

    /**
     * Handle saving settings.
     */
    public static function handle_save(): void {
        if ( ! isset( $_POST['wc_quick_pay_save_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_quick_pay_save_nonce'] ) ), 'wc_quick_pay_save_action' ) ) {
            wp_die( esc_html__( 'فشل التحقق من الأمان.', 'wc-quick-pay' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'غير مصرح لك بتعديل هذه الإعدادات.', 'wc-quick-pay' ) );
        }

        $old_settings = get_option( 'wc_quick_pay_settings', [] );
        $new_settings = $old_settings;

        // General.
        $new_settings['enabled']            = isset( $_POST['enabled'] ) ? 'yes' : 'no';
        $new_settings['standalone_mode']    = isset( $_POST['standalone_mode'] ) ? 'yes' : 'no';
        $new_settings['page_slug']          = ! empty( $_POST['page_slug'] ) ? sanitize_title( wp_unslash( $_POST['page_slug'] ) ) : 'pay';
        $new_settings['page_title']         = sanitize_text_field( wp_unslash( $_POST['page_title'] ?? '' ) );
        $new_settings['min_amount']         = sanitize_text_field( wp_unslash( $_POST['min_amount'] ?? '1' ) );
        $new_settings['max_amount']         = sanitize_text_field( wp_unslash( $_POST['max_amount'] ?? '' ) );
        $new_settings['default_amount']     = sanitize_text_field( wp_unslash( $_POST['default_amount'] ?? '' ) );
        $new_settings['preset_amounts']     = sanitize_text_field( wp_unslash( $_POST['preset_amounts'] ?? '' ) );

        // Header.
        $new_settings['logo_url']           = esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) );
        $new_settings['header_title']       = sanitize_text_field( wp_unslash( $_POST['header_title'] ?? '' ) );
        $new_settings['header_subtitle']    = sanitize_text_field( wp_unslash( $_POST['header_subtitle'] ?? '' ) );
        $new_settings['header_badge']       = sanitize_text_field( wp_unslash( $_POST['header_badge'] ?? '' ) );

        // Fields.
        $new_settings['field_name_req']     = isset( $_POST['field_name_req'] ) ? 'yes' : 'no';
        $new_settings['field_email_req']    = isset( $_POST['field_email_req'] ) ? 'yes' : 'no';
        $new_settings['field_phone_req']    = isset( $_POST['field_phone_req'] ) ? 'yes' : 'no';
        $new_settings['field_invoice_req']  = isset( $_POST['field_invoice_req'] ) ? 'yes' : 'no';
        $new_settings['field_notes_enable'] = isset( $_POST['field_notes_enable'] ) ? 'yes' : 'no';

        // Gateways.
        $new_settings['gateway_mode']       = sanitize_text_field( wp_unslash( $_POST['gateway_mode'] ?? 'all' ) );
        $new_settings['default_gateway']    = sanitize_text_field( wp_unslash( $_POST['default_gateway'] ?? '' ) );
        $new_settings['selected_gateways']  = isset( $_POST['selected_gateways'] ) && is_array( $_POST['selected_gateways'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_gateways'] ) )
            : [];

        // Footer.
        $new_settings['footer_text']        = sanitize_text_field( wp_unslash( $_POST['footer_text'] ?? '' ) );
        $new_settings['footer_support_text']= sanitize_text_field( wp_unslash( $_POST['footer_support_text'] ?? '' ) );
        $new_settings['support_whatsapp']   = sanitize_text_field( wp_unslash( $_POST['support_whatsapp'] ?? '' ) );
        $new_settings['support_phone']      = sanitize_text_field( wp_unslash( $_POST['support_phone'] ?? '' ) );
        $new_settings['support_email']      = sanitize_email( wp_unslash( $_POST['support_email'] ?? '' ) );
        $new_settings['terms_url']          = esc_url_raw( wp_unslash( $_POST['terms_url'] ?? '' ) );
        $new_settings['refund_url']         = esc_url_raw( wp_unslash( $_POST['refund_url'] ?? '' ) );
        $new_settings['show_developer_credit'] = isset( $_POST['show_developer_credit'] ) ? 'yes' : 'no';

        // Design.
        $new_settings['primary_color']      = sanitize_hex_color( wp_unslash( $_POST['primary_color'] ?? '#004733' ) );
        $new_settings['accent_color']       = sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '#D4AF37' ) );
        $new_settings['bg_color']           = sanitize_hex_color( wp_unslash( $_POST['bg_color'] ?? '#f8fafc' ) );

        update_option( 'wc_quick_pay_settings', $new_settings );

        // Update rewrite rules if slug changed.
        if ( ( $old_settings['page_slug'] ?? '' ) !== $new_settings['page_slug'] ) {
            WC_Quick_Pay_Endpoint::register_rewrites();
            flush_rewrite_rules();
        }

        // Redirect with message.
        $current_tab = sanitize_text_field( wp_unslash( $_POST['current_tab'] ?? 'general' ) );
        wp_safe_redirect( add_query_arg( [
            'page'    => self::MENU_SLUG,
            'tab'     => $current_tab,
            'updated' => 'true',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Render settings page.
     */
    public static function render_settings_page(): void {
        $settings     = get_option( 'wc_quick_pay_settings', [] );
        $current_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        $pay_url      = WC_Quick_Pay_Endpoint::get_pay_url();
        $gateways     = WC()->payment_gateways()->payment_gateways();

        ?>
        <div class="wrap wc-quick-pay-admin-wrap" dir="rtl">
            <div class="wc-qp-header">
                <div class="wc-qp-header-title">
                    <span class="wc-qp-icon">⚡</span>
                    <div>
                        <h1><?php esc_html_e( 'الدفع السريع ومحطة السداد المباشر (Quick Pay)', 'wc-quick-pay' ); ?></h1>
                        <p class="wc-qp-subtitle"><?php esc_html_e( 'خصص صفحة البيع والسداد المباشر، أنشئ روابط فواتير فورية، ومرر بيانات العملاء للبوابات بسلاسة.', 'wc-quick-pay' ); ?></p>
                    </div>
                </div>
                <div class="wc-qp-header-actions">
                    <a href="<?php echo esc_url( $pay_url ); ?>" target="_blank" class="button button-primary wc-qp-btn-preview">
                        🔗 <?php esc_html_e( 'معاينة صفحة الدفع المباشر', 'wc-quick-pay' ); ?>
                    </a>
                </div>
            </div>

            <?php if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) : ?>
                <div class="notice notice-success is-dismissible" style="margin-top:15px;">
                    <p><strong><?php esc_html_e( 'تم حفظ كافة الإعدادات بنجاح.', 'wc-quick-pay' ); ?></strong></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper wc-qp-nav-tabs">
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'general' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">⚙️ <?php esc_html_e( 'عام والصفحة', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'header' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'header' ? 'nav-tab-active' : ''; ?>">🎨 <?php esc_html_e( 'الترويسة والشعار', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'fields' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'fields' ? 'nav-tab-active' : ''; ?>">📝 <?php esc_html_e( 'حقول البيانات', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'gateways' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'gateways' ? 'nav-tab-active' : ''; ?>">💳 <?php esc_html_e( 'بوابات الدفع', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'footer' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'footer' ? 'nav-tab-active' : ''; ?>">🛡️ <?php esc_html_e( 'التذييل والأمان والدعم', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'design' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'design' ? 'nav-tab-active' : ''; ?>">✨ <?php esc_html_e( 'الألوان والمظهر', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'generator' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'generator' ? 'nav-tab-active' : ''; ?>">🚀 <?php esc_html_e( 'مُولّد الروابط السريعة', 'wc-quick-pay' ); ?></a>
            </nav>

            <form method="post" action="" class="wc-qp-form">
                <?php wp_nonce_field( 'wc_quick_pay_save_action', 'wc_quick_pay_save_nonce' ); ?>
                <input type="hidden" name="current_tab" value="<?php echo esc_attr( $current_tab ); ?>" />

                <div class="wc-qp-tab-content">
                    <?php
                    switch ( $current_tab ) {
                        case 'header':
                            self::render_tab_header( $settings );
                            break;
                        case 'fields':
                            self::render_tab_fields( $settings );
                            break;
                        case 'gateways':
                            self::render_tab_gateways( $settings, $gateways );
                            break;
                        case 'footer':
                            self::render_tab_footer( $settings );
                            break;
                        case 'design':
                            self::render_tab_design( $settings );
                            break;
                        case 'generator':
                            self::render_tab_generator( $settings, $pay_url );
                            break;
                        case 'general':
                        default:
                            self::render_tab_general( $settings, $pay_url );
                            break;
                    }
                    ?>
                </div>

                <?php if ( 'generator' !== $current_tab ) : ?>
                    <p class="submit" style="margin-top:25px;">
                        <button type="submit" class="button button-primary button-large" style="padding:6px 30px;font-size:15px;height:auto;">
                            💾 <?php esc_html_e( 'حفظ كافة التغييرات', 'wc-quick-pay' ); ?>
                        </button>
                    </p>
                <?php endif; ?>
            </form>

            <div style="margin-top:25px;padding:12px 18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;color:#64748b;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <span>⚡ <strong>WooCommerce Quick Pay & Pay Links</strong> — <?php esc_html_e( 'لطلب الخدمات البرمجية أو شراء وتخصيص الإضافة تواصل مع المطور:', 'wc-quick-pay' ); ?></span>
                <span style="direction:ltr;display:inline-flex;gap:14px;align-items:center;">
                    <a href="mailto:info@woostore.dev" style="color:#0284c7;text-decoration:none;font-weight:600;">✉️ info@woostore.dev</a>
                    <a href="https://t.me/fh32000" target="_blank" rel="noopener" style="color:#0284c7;text-decoration:none;font-weight:600;">✈️ Telegram: @fh32000</a>
                </span>
            </div>
        </div>
        <?php
    }

    /**
     * Render General Tab.
     */
    private static function render_tab_general( array $s, string $pay_url ): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'تفعيل صفحة الدفع السريع', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="yes" <?php checked( $s['enabled'] ?? 'yes', 'yes' ); ?> />
                        <?php esc_html_e( 'تمكين النظام وإتاحة صفحة وروابط الدفع السريع للعملاء', 'wc-quick-pay' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'النمط المستقل الخالي من التشتت (Standalone)', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="standalone_mode" value="yes" <?php checked( $s['standalone_mode'] ?? 'yes', 'yes' ); ?> />
                        <strong><?php esc_html_e( 'نعم، اجعل الصفحة مستقلة تماماً لا تشبه ووردبريس (بدون القوائم والترويسة والتذييل المزدحم للقالب)', 'wc-quick-pay' ); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e( 'عند التفعيل، تُعرض صفحة سريعة وأنيقة ومستقلة تشبه بوابات سترايب أو محطات الدفع البنكية.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'رابط الصفحة (Slug)', 'wc-quick-pay' ); ?></th>
                <td>
                    <div style="display:flex;align-items:center;direction:ltr;width:fit-content;background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:4px 8px;">
                        <span><?php echo esc_url( home_url( '/' ) ); ?></span>
                        <input type="text" name="page_slug" value="<?php echo esc_attr( $s['page_slug'] ?? 'pay' ); ?>" style="border:none;outline:none;box-shadow:none;padding:2px 4px;font-weight:bold;color:#0369a1;" />
                        <span>/</span>
                    </div>
                    <p class="description"><?php esc_html_e( 'الرابط المختصر الذي سيصل إليه العملاء لإتمام السداد (مثل: pay أو quick-pay).', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'عنوان نافذة المتصفح', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="page_title" value="<?php echo esc_attr( $s['page_title'] ?? 'سداد سريع وآمن' ); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'الحد الأدنى والأقصى للمبلغ', 'wc-quick-pay' ); ?></th>
                <td>
                    <div style="display:flex;gap:15px;align-items:center;">
                        <label>
                            <?php esc_html_e( 'الحد الأدنى:', 'wc-quick-pay' ); ?>
                            <input type="number" step="0.01" min="0" name="min_amount" value="<?php echo esc_attr( $s['min_amount'] ?? '1' ); ?>" style="width:100px;" />
                            <?php echo esc_html( get_woocommerce_currency() ); ?>
                        </label>
                        <label>
                            <?php esc_html_e( 'الحد الأقصى (اتركه فارغاً بلا حد):', 'wc-quick-pay' ); ?>
                            <input type="number" step="0.01" min="0" name="max_amount" value="<?php echo esc_attr( $s['max_amount'] ?? '' ); ?>" style="width:120px;" />
                            <?php echo esc_html( get_woocommerce_currency() ); ?>
                        </label>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'المبلغ الافتراضي (اختياري)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="number" step="0.01" min="0" name="default_amount" value="<?php echo esc_attr( $s['default_amount'] ?? '' ); ?>" class="regular-text" style="width:150px;" />
                    <span class="description"><?php esc_html_e( 'مبلغ يظهر معبأ مسبقاً إذا لم يحدد الرابط مبلغاً معيناً.', 'wc-quick-pay' ); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'أزرار مبالغ سريعة محددة مسبقاً', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="preset_amounts" value="<?php echo esc_attr( $s['preset_amounts'] ?? '50, 100, 250, 500, 1000' ); ?>" class="large-text" />
                    <p class="description"><?php esc_html_e( 'أدخل المبالغ مفصولة بفواصل ليتمكن العميل من النقر على أي منها بسرعة، أو إدخال أي مبلغ يريده يدوياً.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'الرمز القصير (Shortcode)', 'wc-quick-pay' ); ?></th>
                <td>
                    <code>[wc_quick_pay]</code>
                    <p class="description"><?php esc_html_e( 'يمكنك استخدام هذا الرمز القصير في أي صفحة أو مقال أو بواسطة Elementor إذا أردت تضمين النموذج داخل تصميم مخصص.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Header Tab.
     */
    private static function render_tab_header( array $s ): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'شعار المتجر أو المؤسسة (Logo)', 'wc-quick-pay' ); ?></th>
                <td>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="text" id="wc_qp_logo_url" name="logo_url" value="<?php echo esc_attr( $s['logo_url'] ?? '' ); ?>" class="large-text" style="max-width:400px;" />
                        <button type="button" class="button wc-qp-upload-btn" data-target="#wc_qp_logo_url">📁 <?php esc_html_e( 'اختيار أو رفع شعار', 'wc-quick-pay' ); ?></button>
                    </div>
                    <div id="wc_qp_logo_preview" style="margin-top:10px;">
                        <?php if ( ! empty( $s['logo_url'] ) ) : ?>
                            <img src="<?php echo esc_url( $s['logo_url'] ); ?>" style="max-height:60px;background:#f1f5f9;padding:6px;border-radius:6px;border:1px solid #cbd5e1;" />
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'العنوان الرئيسي للترويسة', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="header_title" value="<?php echo esc_attr( $s['header_title'] ?? 'صفحة الدفع السريع وسداد الفواتير' ); ?>" class="large-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'الوصف التوضيحي للترويسة', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="header_subtitle" value="<?php echo esc_attr( $s['header_subtitle'] ?? 'أدخل بياناتك ورقم الفاتورة لإتمام الدفع الآمن والمباشر' ); ?>" class="large-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'شارة الأمان في أعلى الصفحة', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="header_badge" value="<?php echo esc_attr( $s['header_badge'] ?? '🔒 سداد آمن ومشفّر 256-bit' ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'تظهر كبادج صغير فوق العنوان لزيادة ثقة وطمأنينة العميل.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Fields Tab.
     */
    private static function render_tab_fields( array $s ): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'حقل الاسم الكامل', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="field_name_req" value="yes" <?php checked( $s['field_name_req'] ?? 'yes', 'yes' ); ?> />
                        <?php esc_html_e( 'حقل إلزامي', 'wc-quick-pay' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'حقل البريد الإلكتروني', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="field_email_req" value="yes" <?php checked( $s['field_email_req'] ?? 'yes', 'yes' ); ?> />
                        <?php esc_html_e( 'حقل إلزامي (مطلوب لإرسال إيصال الدفع للعميل)', 'wc-quick-pay' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'حقل رقم الجوال', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="field_phone_req" value="yes" <?php checked( $s['field_phone_req'] ?? 'yes', 'yes' ); ?> />
                        <?php esc_html_e( 'حقل إلزامي', 'wc-quick-pay' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'حقل رقم الفاتورة أو الطلب المرجعي', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="field_invoice_req" value="yes" <?php checked( $s['field_invoice_req'] ?? 'yes', 'yes' ); ?> />
                        <?php esc_html_e( 'حقل إلزامي (لربط السداد برقم الفاتورة أو المعاملة في متجرك)', 'wc-quick-pay' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'حقل الملاحظات والوصف', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="field_notes_enable" value="yes" <?php checked( $s['field_notes_enable'] ?? 'yes', 'yes' ); ?> />
                        <?php esc_html_e( 'إظهار حقل الملاحظات الاختياري للمستخدم لكتابة أي تفاصيل إضافية', 'wc-quick-pay' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Gateways Tab.
     */
    private static function render_tab_gateways( array $s, array $gateways ): void {
        $selected_gws = $s['selected_gateways'] ?? [];
        $default_gw   = $s['default_gateway'] ?? '';
        $gateway_mode = $s['gateway_mode'] ?? 'all';

        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'طريقة عرض البوابات', 'wc-quick-pay' ); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="gateway_mode" value="all" <?php checked( $gateway_mode, 'all' ); ?> />
                            <?php esc_html_e( 'عرض جميع بوابات الدفع المفعلة حالياً في ووكومرس', 'wc-quick-pay' ); ?>
                        </label><br />
                        <label>
                            <input type="radio" name="gateway_mode" value="selected" <?php checked( $gateway_mode, 'selected' ); ?> />
                            <?php esc_html_e( 'تحديد بوابات دفع معينة فقط لصفحة السداد السريع', 'wc-quick-pay' ); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'البوابات المتاحة للاختيار', 'wc-quick-pay' ); ?></th>
                <td>
                    <div style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;border-radius:6px;max-width:550px;">
                        <?php foreach ( $gateways as $id => $gateway ) : ?>
                            <label style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                                <span>
                                    <input type="checkbox" name="selected_gateways[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $selected_gws, true ) ); ?> />
                                    <strong><?php echo esc_html( $gateway->get_title() ); ?></strong>
                                    <span style="font-size:12px;color:#64748b;">(<?php echo esc_html( $id ); ?>)</span>
                                </span>
                                <?php if ( 'yes' === $gateway->enabled ) : ?>
                                    <span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;"><?php esc_html_e( 'مفعلة في المتجر', 'wc-quick-pay' ); ?></span>
                                <?php else : ?>
                                    <span style="background:#f1f5f9;color:#64748b;padding:2px 8px;border-radius:10px;font-size:11px;"><?php esc_html_e( 'معطلة بالمتجر', 'wc-quick-pay' ); ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'البوابة الافتراضية المحددة مسبقاً', 'wc-quick-pay' ); ?></th>
                <td>
                    <select name="default_gateway" style="min-width:240px;">
                        <option value=""><?php esc_html_e( '— أول بوابة مفعلة تلقائياً —', 'wc-quick-pay' ); ?></option>
                        <?php foreach ( $gateways as $id => $gateway ) : ?>
                            <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $default_gw, $id ); ?>>
                                <?php echo esc_html( $gateway->get_title() ); ?> (<?php echo esc_html( $id ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Footer Tab.
     */
    private static function render_tab_footer( array $s ): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'نص حقوق النشر بالتذييل', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="footer_text" value="<?php echo esc_attr( $s['footer_text'] ?? '' ); ?>" class="large-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'نص التوجيه للمساعدة', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="footer_support_text" value="<?php echo esc_attr( $s['footer_support_text'] ?? 'هل تحتاج لمساعدة؟ تواصل مع خدمة العملاء' ); ?>" class="large-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'رقم الواتساب للدعم', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="support_whatsapp" value="<?php echo esc_attr( $s['support_whatsapp'] ?? '' ); ?>" placeholder="9665xxxxxxxx" class="regular-text" style="direction:ltr;text-align:left;" />
                    <p class="description"><?php esc_html_e( 'سيظهر زر مباشر لمحادثة الواتساب إذا تم إدخال الرقم بصيغة دولية (مثل: 966500000000).', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'رقم الهاتف للاتصال', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="support_phone" value="<?php echo esc_attr( $s['support_phone'] ?? '' ); ?>" placeholder="+966..." class="regular-text" style="direction:ltr;text-align:left;" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'البريد الإلكتروني للدعم', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="email" name="support_email" value="<?php echo esc_attr( $s['support_email'] ?? '' ); ?>" class="regular-text" style="direction:ltr;text-align:left;" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'رابط الشروط والأحكام', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="url" name="terms_url" value="<?php echo esc_attr( $s['terms_url'] ?? '' ); ?>" class="large-text" style="direction:ltr;text-align:left;" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'رابط سياسة الاسترجاع', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="url" name="refund_url" value="<?php echo esc_attr( $s['refund_url'] ?? '' ); ?>" class="large-text" style="direction:ltr;text-align:left;" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'شارة طلب الخدمة والإضافة (تسويقية)', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_developer_credit" value="yes" <?php checked( $s['show_developer_credit'] ?? 'yes', 'yes' ); ?> />
                        <strong><?php esc_html_e( 'إظهار شارة تسويقية صغيرة في حافة أسفل الصفحة لمن يرغب بالحصول على هذه الخدمة أو الإضافة', 'wc-quick-pay' ); ?></strong>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'البيانات المعروضة:', 'wc-quick-pay' ); ?> <code>info@woostore.dev</code> • <code>تليجرام: @fh32000</code>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Design Tab.
     */
    private static function render_tab_design( array $s ): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'اللون الأساسي (Primary Color)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="primary_color" value="<?php echo esc_attr( $s['primary_color'] ?? '#004733' ); ?>" class="wc-qp-color-field" />
                    <p class="description"><?php esc_html_e( 'يُستخدم للترويسة، العناوين، وزر السداد الرئيسي.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'لون التمييز (Accent Color)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="accent_color" value="<?php echo esc_attr( $s['accent_color'] ?? '#D4AF37' ); ?>" class="wc-qp-color-field" />
                    <p class="description"><?php esc_html_e( 'يُستخدم للبادجات، الإطارات المحددة، والعناصر المميزة.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'لون خلفية الصفحة (Background)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="bg_color" value="<?php echo esc_attr( $s['bg_color'] ?? '#f8fafc' ); ?>" class="wc-qp-color-field" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Generator Tab.
     */
    private static function render_tab_generator( array $s, string $pay_url ): void {
        ?>
        <div class="wc-qp-generator-box" style="background:#fff;border:1px solid #cbd5e1;padding:24px;border-radius:8px;max-width:700px;margin-top:15px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0;font-size:18px;color:#0f172a;">🚀 <?php esc_html_e( 'إنشاء رابط سداد سريع مخصص للمشاركة', 'wc-quick-pay' ); ?></h3>
            <p style="color:#64748b;margin-bottom:20px;font-size:14px;"><?php esc_html_e( 'أدخل تفاصيل الفاتورة أو العميل لإنشاء رابط دفع جاهز ومُعبأ مسبقاً، يمكنك إرساله للعميل عبر الواتساب أو الرسائل القصيرة ليسدد فوراً بنقرة واحدة.', 'wc-quick-pay' ); ?></p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e( 'المبلغ المراد دفعه:', 'wc-quick-pay' ); ?></label>
                    <input type="number" step="0.01" id="gen_amount" placeholder="مثلاً: 250" class="regular-text" style="width:100%;" />
                </div>
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e( 'رقم الفاتورة أو المرجع:', 'wc-quick-pay' ); ?></label>
                    <input type="text" id="gen_invoice" placeholder="مثلاً: INV-2026-01" class="regular-text" style="width:100%;" />
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e( 'اسم العميل (اختياري):', 'wc-quick-pay' ); ?></label>
                    <input type="text" id="gen_name" placeholder="اسم العميل" class="regular-text" style="width:100%;" />
                </div>
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e( 'رقم جوال العميل (اختياري):', 'wc-quick-pay' ); ?></label>
                    <input type="text" id="gen_phone" placeholder="05xxxxxxxx" class="regular-text" style="width:100%;" />
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e( 'وصف أو ملاحظة الفاتورة (اختياري):', 'wc-quick-pay' ); ?></label>
                <input type="text" id="gen_notes" placeholder="مثلاً: دفعة أولى مقابل عقد رقم 887" class="large-text" style="width:100%;" />
            </div>

            <div style="background:#f8fafc;border:1px dashed #94a3b8;padding:16px;border-radius:6px;">
                <label style="display:block;margin-bottom:8px;font-weight:bold;color:#0369a1;">🔗 <?php esc_html_e( 'الرابط الناتج (مُجهز للمشاركة المباشرة):', 'wc-quick-pay' ); ?></label>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="text" id="gen_result_url" readonly value="<?php echo esc_url( $pay_url ); ?>" style="width:100%;direction:ltr;background:#fff;font-weight:600;color:#0f172a;" />
                    <button type="button" id="wc_qp_copy_link_btn" class="button button-primary" style="white-space:nowrap;padding:4px 16px;">📋 <?php esc_html_e( 'نسخ الرابط', 'wc-quick-pay' ); ?></button>
                    <a href="#" id="wc_qp_whatsapp_share_btn" target="_blank" class="button" style="background:#25D366;color:#fff;border-color:#25D366;white-space:nowrap;display:none;">💬 <?php esc_html_e( 'إرسال عبر واتساب', 'wc-quick-pay' ); ?></a>
                </div>
            </div>
        </div>
        <?php
    }
}

// Hook into admin.
add_action( 'init', [ 'WC_Quick_Pay_Settings', 'init' ] );
