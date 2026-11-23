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
                'i18n'         => [
                    'selected_counter' => __( 'تم تحديد %s من إجمالي %s بوابة', 'wc-quick-pay' ),
                ],
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

        $current_tab = sanitize_text_field( wp_unslash( $_POST['current_tab'] ?? 'general' ) );

        switch ( $current_tab ) {
            case 'general':
                $new_settings['enabled']         = isset( $_POST['enabled'] ) ? 'yes' : 'no';
                $new_settings['standalone_mode'] = isset( $_POST['standalone_mode'] ) ? 'yes' : 'no';
                $new_settings['page_slug']       = ! empty( $_POST['page_slug'] ) ? sanitize_title( wp_unslash( $_POST['page_slug'] ) ) : 'pay';
                $new_settings['page_title']      = sanitize_text_field( wp_unslash( $_POST['page_title'] ?? '' ) );
                break;

            case 'amounts':
                $new_settings['amount_mode']          = sanitize_text_field( wp_unslash( $_POST['amount_mode'] ?? 'custom' ) );
                $new_settings['lock_amount_from_url'] = isset( $_POST['lock_amount_from_url'] ) ? 'yes' : 'no';
                $new_settings['amount_label']         = sanitize_text_field( wp_unslash( $_POST['amount_label'] ?? 'المبلغ المراد دفعه' ) );
                $new_settings['amount_label_en']      = sanitize_text_field( wp_unslash( $_POST['amount_label_en'] ?? 'Payment Amount' ) );
                $new_settings['amount_help_text']     = sanitize_text_field( wp_unslash( $_POST['amount_help_text'] ?? '' ) );
                $new_settings['amount_help_text_en']  = sanitize_text_field( wp_unslash( $_POST['amount_help_text_en'] ?? '' ) );
                $new_settings['min_amount']           = sanitize_text_field( wp_unslash( $_POST['min_amount'] ?? '1' ) );
                $new_settings['max_amount']           = sanitize_text_field( wp_unslash( $_POST['max_amount'] ?? '' ) );
                $new_settings['default_amount']       = sanitize_text_field( wp_unslash( $_POST['default_amount'] ?? '' ) );
                $new_settings['show_preset_buttons']  = isset( $_POST['show_preset_buttons'] ) ? 'yes' : 'no';
                $new_settings['preset_amounts']       = sanitize_text_field( wp_unslash( $_POST['preset_amounts'] ?? '1000, 2000, 3000, 5000, 7000, 10000, 15000, 20000, 30000, 40000, 50000, 60000, 70000, 80000, 90000, 100000' ) );
                break;

            case 'header':
                $new_settings['logo_url']        = esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) );
                $new_settings['header_title']    = sanitize_text_field( wp_unslash( $_POST['header_title'] ?? '' ) );
                $new_settings['header_subtitle'] = sanitize_text_field( wp_unslash( $_POST['header_subtitle'] ?? '' ) );
                $new_settings['header_badge']    = sanitize_text_field( wp_unslash( $_POST['header_badge'] ?? '' ) );
                break;

            case 'fields':
                $new_settings['show_section_invoice'] = isset( $_POST['show_section_invoice'] ) ? 'yes' : 'no';
                $new_settings['field_invoice_enable'] = isset( $_POST['field_invoice_enable'] ) ? 'yes' : 'no';
                $new_settings['field_invoice_req']    = isset( $_POST['field_invoice_req'] ) ? 'yes' : 'no';
                $new_settings['field_notes_enable']   = isset( $_POST['field_notes_enable'] ) ? 'yes' : 'no';

                $new_settings['show_section_customer']= isset( $_POST['show_section_customer'] ) ? 'yes' : 'no';
                $new_settings['field_name_enable']    = isset( $_POST['field_name_enable'] ) ? 'yes' : 'no';
                $new_settings['field_name_req']       = isset( $_POST['field_name_req'] ) ? 'yes' : 'no';
                $new_settings['field_email_enable']   = isset( $_POST['field_email_enable'] ) ? 'yes' : 'no';
                $new_settings['field_email_req']      = isset( $_POST['field_email_req'] ) ? 'yes' : 'no';
                $new_settings['field_phone_enable']   = isset( $_POST['field_phone_enable'] ) ? 'yes' : 'no';
                $new_settings['field_phone_req']      = isset( $_POST['field_phone_req'] ) ? 'yes' : 'no';

                $new_settings['fallback_guest_name']  = sanitize_text_field( wp_unslash( $_POST['fallback_guest_name'] ?? 'عميل سداد سريع' ) );
                $new_settings['fallback_guest_email'] = sanitize_email( wp_unslash( $_POST['fallback_guest_email'] ?? '' ) );
                $new_settings['fallback_guest_phone'] = sanitize_text_field( wp_unslash( $_POST['fallback_guest_phone'] ?? '' ) );

                $new_settings['show_support_box']     = isset( $_POST['show_support_box'] ) ? 'yes' : 'no';
                break;

            case 'gateways':
                $new_settings['gateway_mode']               = sanitize_text_field( wp_unslash( $_POST['gateway_mode'] ?? 'all' ) );
                $new_settings['default_gateway']            = sanitize_text_field( wp_unslash( $_POST['default_gateway'] ?? '' ) );
                $new_settings['single_gateway_auto_select'] = isset( $_POST['single_gateway_auto_select'] ) ? 'yes' : 'no';
                $new_settings['selected_gateways']          = isset( $_POST['selected_gateways'] ) && is_array( $_POST['selected_gateways'] )
                    ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_gateways'] ) )
                    : [];
                break;

            case 'footer':
                $new_settings['show_support_box']   = isset( $_POST['show_support_box'] ) ? 'yes' : 'no';
                $new_settings['footer_text']        = sanitize_text_field( wp_unslash( $_POST['footer_text'] ?? '' ) );
                $new_settings['footer_support_text']= sanitize_text_field( wp_unslash( $_POST['footer_support_text'] ?? '' ) );
                $new_settings['support_whatsapp']   = sanitize_text_field( wp_unslash( $_POST['support_whatsapp'] ?? '' ) );
                $new_settings['support_phone']      = sanitize_text_field( wp_unslash( $_POST['support_phone'] ?? '' ) );
                $new_settings['support_email']      = sanitize_email( wp_unslash( $_POST['support_email'] ?? '' ) );
                $new_settings['terms_url']          = esc_url_raw( wp_unslash( $_POST['terms_url'] ?? '' ) );
                $new_settings['refund_url']         = esc_url_raw( wp_unslash( $_POST['refund_url'] ?? '' ) );
                $new_settings['show_developer_credit'] = 'no';
                break;

            case 'design':
                $new_settings['primary_color']      = sanitize_hex_color( wp_unslash( $_POST['primary_color'] ?? '#004733' ) );
                $new_settings['accent_color']       = sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '#D4AF37' ) );
                $new_settings['bg_color']           = sanitize_hex_color( wp_unslash( $_POST['bg_color'] ?? '#f8fafc' ) );
                break;
        }

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
                        <p class="wc-qp-subtitle"><?php esc_html_e( 'خصص صفحة السداد المباشر، تحكّم في الحقول والمبالغ، وحدد بوابات الدفع بكل مرونة.', 'wc-quick-pay' ); ?></p>
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
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'amounts' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'amounts' ? 'nav-tab-active' : ''; ?>">💰 <?php esc_html_e( 'خيارات المبالغ', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'fields' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'fields' ? 'nav-tab-active' : ''; ?>">📝 <?php esc_html_e( 'حقول البيانات والرؤية', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'gateways' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'gateways' ? 'nav-tab-active' : ''; ?>">💳 <?php esc_html_e( 'بوابات الدفع', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'header' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'header' ? 'nav-tab-active' : ''; ?>">🎨 <?php esc_html_e( 'الترويسة والشعار', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'design' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'design' ? 'nav-tab-active' : ''; ?>">✨ <?php esc_html_e( 'الألوان والمظهر', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'footer' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'footer' ? 'nav-tab-active' : ''; ?>">🛡️ <?php esc_html_e( 'التذييل والدعم', 'wc-quick-pay' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'generator' ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $current_tab === 'generator' ? 'nav-tab-active' : ''; ?>">🚀 <?php esc_html_e( 'مُولّد الروابط السريعة', 'wc-quick-pay' ); ?></a>
            </nav>

            <form method="post" action="" class="wc-qp-form">
                <?php wp_nonce_field( 'wc_quick_pay_save_action', 'wc_quick_pay_save_nonce' ); ?>
                <input type="hidden" name="current_tab" value="<?php echo esc_attr( $current_tab ); ?>" />

                <div class="wc-qp-tab-content">
                    <?php
                    switch ( $current_tab ) {
                        case 'amounts':
                            self::render_tab_amounts( $settings );
                            break;
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
                            self::render_tab_generator( $settings, $pay_url, $gateways );
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
                    <p class="description"><?php esc_html_e( 'عند التفعيل، تُعرض صفحة سريعة وأنيقة ومستقلة تشبه بوابات سترايب أو محطات الدفع البنكية الحديثة.', 'wc-quick-pay' ); ?></p>
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
     * Render Amounts Tab (New Enhanced Tab for Amounts).
     */
    private static function render_tab_amounts( array $s ): void {
        $amount_mode = $s['amount_mode'] ?? 'custom';
        ?>
        <div style="background:#f0f9ff;border:1px solid #bae6fd;padding:14px 18px;border-radius:8px;margin-bottom:20px;color:#0369a1;">
            <p style="margin:0;font-size:14px;line-height:1.6;">
                💡 <strong><?php esc_html_e( 'إعدادات المبالغ والتسعير:', 'wc-quick-pay' ); ?></strong>
                <?php esc_html_e( 'تحكّم في كيفية تحديد العميل للمبلغ، سواء كان مبلغاً حراً، مبالغ سريعة محددة، أو مبلغاً ثابتاً ومقفلاً غير قابل للتعديل.', 'wc-quick-pay' ); ?>
            </p>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'طريقة / نمط تحديد المبلغ', 'wc-quick-pay' ); ?></th>
                <td>
                    <fieldset>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="radio" name="amount_mode" value="custom" <?php checked( $amount_mode, 'custom' ); ?> />
                            <strong><?php esc_html_e( 'مبلغ حر ومخصص مع أزرار سريعة (الوضع الافتراضي والمرن)', 'wc-quick-pay' ); ?></strong>
                            <p class="description" style="margin-right:24px;"><?php esc_html_e( 'يستطيع العميل إدخال أي مبلغ يريده يدوياً، أو النقر على أحد أزرار المبالغ الجاهزة.', 'wc-quick-pay' ); ?></p>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="radio" name="amount_mode" value="fixed" <?php checked( $amount_mode, 'fixed' ); ?> />
                            <strong><?php esc_html_e( 'مبلغ ثابت ومقفل (غير قابل للتعديل من العميل)', 'wc-quick-pay' ); ?></strong>
                            <p class="description" style="margin-right:24px;"><?php esc_html_e( 'يتم تثبيت المبلغ المحدد في خانة (المبلغ الافتراضي) أو الممرر عبر الرابط، ويمنع العميل من تعديله.', 'wc-quick-pay' ); ?></p>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="radio" name="amount_mode" value="presets_only" <?php checked( $amount_mode, 'presets_only' ); ?> />
                            <strong><?php esc_html_e( 'خيارات مبالغ جاهزة فقط (بدون إدخال يدوي)', 'wc-quick-pay' ); ?></strong>
                            <p class="description" style="margin-right:24px;"><?php esc_html_e( 'يختار العميل حصراً من أزرار المبالغ المحددة مسبقاً، دون إمكانية كتابة مبلغ يدوي حر.', 'wc-quick-pay' ); ?></p>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'قفل المبلغ عند التمرير في الرابط', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="lock_amount_from_url" value="yes" <?php checked( $s['lock_amount_from_url'] ?? 'yes', 'yes' ); ?> />
                        <strong><?php esc_html_e( 'نعم، اقفل حقل المبلغ تلقائياً إذا فُتحت الصفحة برابط دفع يحتوي على مبلغ محدد', 'wc-quick-pay' ); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e( 'ميزة فائقة الأهمية: عند إرسال رابط فاتورة للعميل يحتوي على مبلغ (مثل ?amount=250)، يصبح الحقل للقراءة فقط (Read-only) ولا يمكن للعميل تقليل أو التلاعب بالمبلغ.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'عنوان حقل المبلغ (بالعربية)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="amount_label" value="<?php echo esc_attr( $s['amount_label'] ?? 'المبلغ المراد دفعه' ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'مثال: المبلغ المراد دفعه، قيمة الفاتورة، مبلغ المساهمة، رسوم الخدمة...', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'عنوان حقل المبلغ (بالإنجليزية - English)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="amount_label_en" value="<?php echo esc_attr( $s['amount_label_en'] ?? 'Payment Amount' ); ?>" class="regular-text" style="direction:ltr;text-align:left;" />
                    <p class="description"><?php esc_html_e( 'العنوان الذي يظهر للعميل عند تصفح الصفحة باللغة الإنجليزية (افتراضياً: Payment Amount).', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'نص توضيحي لحقل المبلغ (بالعربية - اختياري)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="amount_help_text" value="<?php echo esc_attr( $s['amount_help_text'] ?? '' ); ?>" class="large-text" placeholder="أدخل المبلغ المطلوب سداده" />
                    <p class="description"><?php esc_html_e( 'نص إرشادي اختياري يظهر أسفل حقل المبلغ لتوجيه الدافع بالعربية. (اتركه فارغاً إذا أردت إخفاءه).', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'نص توضيحي لحقل المبلغ (بالإنجليزية - English)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="text" name="amount_help_text_en" value="<?php echo esc_attr( $s['amount_help_text_en'] ?? '' ); ?>" class="large-text" placeholder="Enter the amount in SAR" style="direction:ltr;text-align:left;" />
                    <p class="description"><?php esc_html_e( 'النص الإرشادي بالإنجليزية (مثل: Enter the amount in SAR). يمكنك تغييره هنا أو تركه فارغاً لإخفائه بالكامل.', 'wc-quick-pay' ); ?></p>
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
                <th scope="row"><?php esc_html_e( 'المبلغ الافتراضي (Default)', 'wc-quick-pay' ); ?></th>
                <td>
                    <input type="number" step="0.01" min="0" name="default_amount" value="<?php echo esc_attr( $s['default_amount'] ?? '' ); ?>" class="regular-text" style="width:150px;" />
                    <span class="description"><?php esc_html_e( 'مبلغ يظهر معبأ مسبقاً إذا لم يحدد الرابط مبلغاً معيناً (مفيد جداً في وضع المبلغ الثابت).', 'wc-quick-pay' ); ?></span>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'أزرار المبالغ السريعة', 'wc-quick-pay' ); ?></th>
                <td>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="checkbox" name="show_preset_buttons" value="yes" <?php checked( $s['show_preset_buttons'] ?? 'yes', 'yes' ); ?> />
                        <strong><?php esc_html_e( 'إظهار شريط أزرار المبالغ السريعة أسفل حقل المبلغ', 'wc-quick-pay' ); ?></strong>
                    </label>
                    <input type="text" name="preset_amounts" value="<?php echo esc_attr( $s['preset_amounts'] ?? '1000, 2000, 3000, 5000, 7000, 10000, 15000, 20000, 30000, 40000, 50000, 60000, 70000, 80000, 90000, 100000' ); ?>" class="large-text" />
                    <p class="description"><?php esc_html_e( 'أدخل المبالغ مفصولة بفواصل (مثال: 1000, 2000, 3000, 5000, 7000, 10000, 15000, 20000, 30000, 40000, 50000, 60000, 70000, 80000, 90000, 100000).', 'wc-quick-pay' ); ?></p>
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
     * Render Fields Tab (Enhanced: Section Toggles, Per-Field Controls, and Gateway Fallbacks).
     */
    private static function render_tab_fields( array $s ): void {
        $show_invoice  = ( $s['show_section_invoice'] ?? 'yes' ) === 'yes';
        $show_customer = ( $s['show_section_customer'] ?? 'yes' ) === 'yes';
        ?>
        <!-- Informational banner -->
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;padding:16px 20px;border-radius:8px;margin-bottom:24px;color:#065f46;">
            <h4 style="margin:0 0 6px;font-size:15px;color:#047857;">⚡ <?php esc_html_e( 'الدفع فائق السرعة وبدون حقول (Zero-Field Checkout)', 'wc-quick-pay' ); ?></h4>
            <p style="margin:0;font-size:13px;line-height:1.6;">
                <?php esc_html_e( 'تستطيع الآن إخفاء قسم بيانات الفاتورة أو قسم بيانات المشتري بالكامل، مما يتيح تفعيل صفحة كويك باي بدون حقول نهائياً (المبلغ وبوابة الدفع فقط بنقرة واحدة).', 'wc-quick-pay' ); ?>
            </p>
        </div>

        <!-- Section 1: Invoice & Order Data -->
        <div class="wc-qp-admin-section-box" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;padding-bottom:14px;margin-bottom:16px;">
                <h3 style="margin:0;font-size:16px;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>📄</span> <?php esc_html_e( 'قسم بيانات الفاتورة والطلب المرجعي', 'wc-quick-pay' ); ?>
                </h3>
                <label style="background:#f8fafc;padding:6px 14px;border-radius:20px;border:1px solid #cbd5e1;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-weight:700;">
                    <input type="checkbox" name="show_section_invoice" id="wc_qp_show_section_invoice" value="yes" <?php checked( $show_invoice ); ?> />
                    <span style="font-size:13px;"><?php esc_html_e( 'إظهار هذا القسم في صفحة الدفع', 'wc-quick-pay' ); ?></span>
                </label>
            </div>

            <p class="description" style="margin-bottom:16px;">
                <?php esc_html_e( 'عند إلغاء تحديد الخيار أعلاه، سيتم إخفاء بطاقة "بيانات الفاتورة والطلب" بالكامل من صفحة الدفع ولن يُطلب من العميل إدخال أي رقم فاتورة أو ملاحظات.', 'wc-quick-pay' ); ?>
            </p>

            <div id="wc_qp_invoice_subfields" style="<?php echo ! $show_invoice ? 'opacity:0.5;pointer-events:none;' : ''; ?>">
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th scope="row" style="width:220px;"><?php esc_html_e( 'حقل رقم الفاتورة أو المرجع', 'wc-quick-pay' ); ?></th>
                        <td>
                            <label style="margin-left:20px;">
                                <input type="checkbox" name="field_invoice_enable" value="yes" <?php checked( $s['field_invoice_enable'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'إظهار الحقل', 'wc-quick-pay' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="field_invoice_req" value="yes" <?php checked( $s['field_invoice_req'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'حقل إلزامي', 'wc-quick-pay' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="width:220px;"><?php esc_html_e( 'حقل ملاحظات ووصف السداد', 'wc-quick-pay' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="field_notes_enable" value="yes" <?php checked( $s['field_notes_enable'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'إظهار حقل الملاحظات الاختياري للمستخدم لكتابة أي تفاصيل إضافية', 'wc-quick-pay' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Section 2: Customer / Buyer Information -->
        <div class="wc-qp-admin-section-box" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;padding-bottom:14px;margin-bottom:16px;">
                <h3 style="margin:0;font-size:16px;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>👤</span> <?php esc_html_e( 'قسم بيانات المشتري (الدافع)', 'wc-quick-pay' ); ?>
                </h3>
                <label style="background:#f8fafc;padding:6px 14px;border-radius:20px;border:1px solid #cbd5e1;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-weight:700;">
                    <input type="checkbox" name="show_section_customer" id="wc_qp_show_section_customer" value="yes" <?php checked( $show_customer ); ?> />
                    <span style="font-size:13px;"><?php esc_html_e( 'إظهار هذا القسم في صفحة الدفع', 'wc-quick-pay' ); ?></span>
                </label>
            </div>

            <p class="description" style="margin-bottom:16px;">
                <?php esc_html_e( 'عند إلغاء تحديد الخيار أعلاه، سيتم إخفاء بطاقة "بيانات الدافع (المشتري)" بالكامل من صفحة الدفع وتفعيل السداد المباشر بدون حقول مشتري.', 'wc-quick-pay' ); ?>
            </p>

            <div id="wc_qp_customer_subfields" style="<?php echo ! $show_customer ? 'opacity:0.5;pointer-events:none;' : ''; ?>">
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th scope="row" style="width:220px;"><?php esc_html_e( 'حقل الاسم الكامل', 'wc-quick-pay' ); ?></th>
                        <td>
                            <label style="margin-left:20px;">
                                <input type="checkbox" name="field_name_enable" value="yes" <?php checked( $s['field_name_enable'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'إظهار الحقل', 'wc-quick-pay' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="field_name_req" value="yes" <?php checked( $s['field_name_req'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'حقل إلزامي', 'wc-quick-pay' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="width:220px;"><?php esc_html_e( 'حقل البريد الإلكتروني', 'wc-quick-pay' ); ?></th>
                        <td>
                            <label style="margin-left:20px;">
                                <input type="checkbox" name="field_email_enable" value="yes" <?php checked( $s['field_email_enable'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'إظهار الحقل', 'wc-quick-pay' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="field_email_req" value="yes" <?php checked( $s['field_email_req'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'حقل إلزامي (مطلوب لإرسال إيصال الدفع)', 'wc-quick-pay' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="width:220px;"><?php esc_html_e( 'حقل رقم الجوال', 'wc-quick-pay' ); ?></th>
                        <td>
                            <label style="margin-left:20px;">
                                <input type="checkbox" name="field_phone_enable" value="yes" <?php checked( $s['field_phone_enable'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'إظهار الحقل', 'wc-quick-pay' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="field_phone_req" value="yes" <?php checked( $s['field_phone_req'] ?? 'yes', 'yes' ); ?> />
                                <?php esc_html_e( 'حقل إلزامي', 'wc-quick-pay' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Section 2.5: Support Box Visibility -->
        <div class="wc-qp-admin-section-box" style="background:#fff;border:1px solid #cbd5e1;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:16px;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>💬</span> <?php esc_html_e( 'قسم المساعدة وخدمة العملاء بالتذييل ("هل تحتاج لمساعدة؟")', 'wc-quick-pay' ); ?>
                </h3>
                <label style="background:#f8fafc;padding:6px 14px;border-radius:20px;border:1px solid #cbd5e1;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-weight:700;">
                    <input type="checkbox" name="show_support_box" value="yes" <?php checked( $s['show_support_box'] ?? 'no', 'yes' ); ?> />
                    <span style="font-size:13px;"><?php esc_html_e( 'إظهار هذا القسم في صفحة الدفع', 'wc-quick-pay' ); ?></span>
                </label>
            </div>
            <p style="color:#64748b;margin:8px 0 0;font-size:13px;">
                <?php esc_html_e( 'التحكم السريع بإظهار أو إخفاء أزرار التواصل (واتساب، اتصال، بريد) وصندوق المساعدة في أسفل الصفحة.', 'wc-quick-pay' ); ?>
            </p>
        </div>

        <!-- Section 3: Gateway Fallback Data -->
        <div class="wc-qp-admin-section-box" style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:20px;">
            <h3 style="margin:0 0 8px;font-size:15px;color:#334155;display:flex;align-items:center;gap:8px;">
                <span>⚙️</span> <?php esc_html_e( 'البيانات الاحتياطية لتوافق بوابات الدفع (عند إخفاء حقول المشتري)', 'wc-quick-pay' ); ?>
            </h3>
            <p class="description" style="margin-bottom:14px;">
                <?php esc_html_e( 'تتطلب بعض بوابات الدفع الإلكترونية (مثل باي موب أو البطاقات البنكية) وجود اسم وبريد إلكتروني في الفاتورة لإتمام المعاملة ومنع رفضها. في حال إخفاء حقول المشتري أعلاه، سيتم استخدام هذه البيانات تلقائياً:', 'wc-quick-pay' ); ?>
            </p>

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th scope="row" style="width:220px;"><?php esc_html_e( 'الاسم الافتراضي للطلب', 'wc-quick-pay' ); ?></th>
                    <td>
                        <input type="text" name="fallback_guest_name" value="<?php echo esc_attr( $s['fallback_guest_name'] ?? 'عميل سداد سريع' ); ?>" class="regular-text" />
                        <span class="description"><?php esc_html_e( 'يُسجل في الطلب إذا كان حقل الاسم مخفياً.', 'wc-quick-pay' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="width:220px;"><?php esc_html_e( 'البريد الإلكتروني الاحتياطي', 'wc-quick-pay' ); ?></th>
                    <td>
                        <input type="email" name="fallback_guest_email" value="<?php echo esc_attr( $s['fallback_guest_email'] ?? '' ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" style="direction:ltr;text-align:left;" />
                        <span class="description"><?php esc_html_e( 'إذا تُرك فارغاً سيتم استخدام بريد مدير المتجر تلقائياً.', 'wc-quick-pay' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="width:220px;"><?php esc_html_e( 'رقم الجوال الاحتياطي', 'wc-quick-pay' ); ?></th>
                    <td>
                        <input type="text" name="fallback_guest_phone" value="<?php echo esc_attr( $s['fallback_guest_phone'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $s['support_phone'] ?? '0500000000' ); ?>" class="regular-text" style="direction:ltr;text-align:left;" />
                        <span class="description"><?php esc_html_e( 'إذا تُرك فارغاً سيتم استخدام هاتف الدعم أو رقم افتراضي.', 'wc-quick-pay' ); ?></span>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Gateways Tab (Enhanced with Select All / Deselect All, Counters, and Single Gateway Mode).
     */
    private static function render_tab_gateways( array $s, array $gateways ): void {
        $selected_gws = $s['selected_gateways'] ?? [];
        $default_gw   = $s['default_gateway'] ?? '';
        $gateway_mode = $s['gateway_mode'] ?? 'all';
        $single_auto  = ( $s['single_gateway_auto_select'] ?? 'yes' ) === 'yes';

        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'طريقة عرض البوابات', 'wc-quick-pay' ); ?></th>
                <td>
                    <fieldset>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="radio" name="gateway_mode" value="all" <?php checked( $gateway_mode, 'all' ); ?> />
                            <strong><?php esc_html_e( 'عرض جميع بوابات الدفع المفعلة حالياً في ووكومرس', 'wc-quick-pay' ); ?></strong>
                        </label>
                        <label style="display:block;">
                            <input type="radio" name="gateway_mode" value="selected" <?php checked( $gateway_mode, 'selected' ); ?> />
                            <strong><?php esc_html_e( 'تحديد بوابات دفع معينة فقط لصفحة السداد السريع (بوابة واحدة، بوابتين، أو حسب الحاجة)', 'wc-quick-pay' ); ?></strong>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'البوابات المتاحة لصفحة الدفع', 'wc-quick-pay' ); ?></th>
                <td>
                    <div style="background:#fff;border:1px solid #ccd0d4;padding:16px 20px;border-radius:8px;max-width:620px;box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                        <!-- Quick action toolbar -->
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;gap:8px;">
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="wc_qp_btn_select_all_gws" class="button button-small">
                                    🔘 <?php esc_html_e( 'تحديد الكل', 'wc-quick-pay' ); ?>
                                </button>
                                <button type="button" id="wc_qp_btn_deselect_all_gws" class="button button-small">
                                    ⚪ <?php esc_html_e( 'إلغاء تحديد الكل', 'wc-quick-pay' ); ?>
                                </button>
                            </div>
                            <span id="wc_qp_gateway_counter" style="font-size:12px;color:#0284c7;font-weight:700;background:#e0f2fe;padding:3px 10px;border-radius:12px;">
                                <?php printf( esc_html__( 'تم تحديد %1$d من %2$d بوابة', 'wc-quick-pay' ), count( $selected_gws ), count( $gateways ) ); ?>
                            </span>
                        </div>

                        <div class="wc-qp-gateways-checklist">
                            <?php foreach ( $gateways as $id => $gateway ) : ?>
                                <label style="display:flex;align-items:center;justify-content:space-between;padding:10px 4px;border-bottom:1px solid #f1f5f9;cursor:pointer;">
                                    <span style="display:inline-flex;align-items:center;gap:10px;">
                                        <input type="checkbox" name="selected_gateways[]" class="wc-qp-gw-checkbox" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $selected_gws, true ) ); ?> />
                                        <strong><?php echo esc_html( $gateway->get_title() ); ?></strong>
                                        <span style="font-size:12px;color:#64748b;font-family:monospace;">(<?php echo esc_html( $id ); ?>)</span>
                                    </span>
                                    <?php if ( 'yes' === $gateway->enabled ) : ?>
                                        <span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;"><?php esc_html_e( 'مفعلة بالمتجر', 'wc-quick-pay' ); ?></span>
                                    <?php else : ?>
                                        <span style="background:#f1f5f9;color:#64748b;padding:2px 8px;border-radius:10px;font-size:11px;"><?php esc_html_e( 'معطلة بالمتجر', 'wc-quick-pay' ); ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="description"><?php esc_html_e( 'حدد البوابات التي ترغب بإتاحتها في صفحة الدفع السريع. إذا تم تحديد بوابة واحدة فقط، سيتم توجيه العميل إليها مباشرة.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'وضع البوابة الواحدة التلقائي', 'wc-quick-pay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="single_gateway_auto_select" value="yes" <?php checked( $single_auto ); ?> />
                        <strong><?php esc_html_e( 'عند توفر بوابة دفع واحدة فقط، حددها تلقائياً واجعل مظهرها بسيطاً دون حاجة لنقر العميل على راديو الاختيار', 'wc-quick-pay' ); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e( 'ميزة رائعة لتسريع الدفع وتفادي تشتيت العميل عند الرغبة بالسداد عبر بوابة وحيدة محددة.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'البوابة الافتراضية المحددة مسبقاً', 'wc-quick-pay' ); ?></th>
                <td>
                    <select name="default_gateway" style="min-width:260px;">
                        <option value=""><?php esc_html_e( '— أول بوابة مفعلة تلقائياً —', 'wc-quick-pay' ); ?></option>
                        <?php foreach ( $gateways as $id => $gateway ) : ?>
                            <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $default_gw, $id ); ?>>
                                <?php echo esc_html( $gateway->get_title() ); ?> (<?php echo esc_html( $id ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'البوابة التي تكون محددة بشكل مسبق ومفعل في شاشة الدفع فور فتح الصفحة.', 'wc-quick-pay' ); ?></p>
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
                <th scope="row"><?php esc_html_e( 'إظهار قسم المساعدة والدعم', 'wc-quick-pay' ); ?></th>
                <td>
                    <label style="font-weight:700;display:inline-flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                        <input type="checkbox" name="show_support_box" value="yes" <?php checked( $s['show_support_box'] ?? 'no', 'yes' ); ?> />
                        <span><?php esc_html_e( 'تفعيل وإظهار قسم "هل تحتاج لمساعدة؟ تواصل مع خدمة العملاء" وقنوات التواصل', 'wc-quick-pay' ); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e( 'عند إلغاء هذا الخيار، سيتم إخفاء صندوق الدعم بالكامل (عنوان المساعدة وأزرار الواتساب، الهاتف، والإيميل) من صفحة الدفع.', 'wc-quick-pay' ); ?></p>
                </td>
            </tr>
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
     * Render Generator Tab (Enhanced with Gateway, Lock Amount, and Hide Options).
     */
    private static function render_tab_generator( array $s, string $pay_url, array $gateways = [] ): void {
        ?>
        <div class="wc-qp-generator-box" style="background:#fff;border:1px solid #cbd5e1;padding:24px;border-radius:8px;max-width:760px;margin-top:15px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0;font-size:18px;color:#0f172a;">🚀 <?php esc_html_e( 'إنشاء رابط سداد سريع مخصص للمشاركة', 'wc-quick-pay' ); ?></h3>
            <p style="color:#64748b;margin-bottom:20px;font-size:14px;"><?php esc_html_e( 'أنشئ رابط دفع جاهز ومُعبأ مسبقاً، وحدد خيارات القفل والإخفاء لمشاركتها مباشرة مع العميل.', 'wc-quick-pay' ); ?></p>

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

            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e( 'وصف أو ملاحظة الفاتورة (اختياري):', 'wc-quick-pay' ); ?></label>
                <input type="text" id="gen_notes" placeholder="مثلاً: دفعة أولى مقابل عقد رقم 887" class="large-text" style="width:100%;" />
            </div>

            <!-- Advanced Link Options -->
            <div style="background:#f1f5f9;border:1px solid #e2e8f0;padding:14px 18px;border-radius:6px;margin-bottom:20px;">
                <h4 style="margin:0 0 10px;font-size:14px;color:#334155;">⚙️ <?php esc_html_e( 'خيارات التخصيص المتقدمة لهذا الرابط:', 'wc-quick-pay' ); ?></h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:center;">
                    <div>
                        <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;"><?php esc_html_e( 'بوابة الدفع المحددة لهذا الرابط:', 'wc-quick-pay' ); ?></label>
                        <select id="gen_gateway" style="width:100%;">
                            <option value=""><?php esc_html_e( '— حسب إعدادات الصفحة الافتراضية —', 'wc-quick-pay' ); ?></option>
                            <?php foreach ( $gateways as $id => $gateway ) : ?>
                                <option value="<?php echo esc_attr( $id ); ?>">
                                    <?php echo esc_html( $gateway->get_title() ); ?> (<?php echo esc_html( $id ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px;padding-top:14px;">
                        <label style="cursor:pointer;font-size:13px;">
                            <input type="checkbox" id="gen_lock_amount" value="1" checked />
                            <strong>🔒 <?php esc_html_e( 'قفل المبلغ (منع العميل من تعديله)', 'wc-quick-pay' ); ?></strong>
                        </label>
                        <label style="cursor:pointer;font-size:13px;">
                            <input type="checkbox" id="gen_hide_customer" value="1" />
                            <strong>👤 <?php esc_html_e( 'إخفاء بيانات المشتري (تفعيل بدون حقول)', 'wc-quick-pay' ); ?></strong>
                        </label>
                        <label style="cursor:pointer;font-size:13px;">
                            <input type="checkbox" id="gen_hide_invoice" value="1" />
                            <strong>📄 <?php esc_html_e( 'إخفاء بيانات الفاتورة والطلب', 'wc-quick-pay' ); ?></strong>
                        </label>
                        <label style="cursor:pointer;font-size:13px;">
                            <input type="checkbox" id="gen_hide_support" value="1" />
                            <strong>💬 <?php esc_html_e( 'إخفاء قسم المساعدة والدعم بالتذييل', 'wc-quick-pay' ); ?></strong>
                        </label>
                    </div>
                </div>
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
