<?php
/**
 * Quick Pay Form Component.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

// Settings.
$settings = get_option( 'wc_quick_pay_settings', [] );

// Gateways.
$all_gateways     = WC()->payment_gateways()->get_available_payment_gateways();
$mode             = $settings['gateway_mode'] ?? 'all';
$selected_list    = $settings['selected_gateways'] ?? [];
$default_gw       = $prefill['gateway_id'] ?: ( $settings['default_gateway'] ?? '' );

$active_gateways = [];
foreach ( $all_gateways as $id => $gw ) {
    if ( 'selected' === $mode && ! in_array( $id, $selected_list, true ) ) {
        continue;
    }
    // Filter out internal pixel widget when hosted paymob-main is present
    if ( 'paymob-pixel' === $id && isset( $all_gateways['paymob-main'] ) ) {
        continue;
    }
    $active_gateways[ $id ] = $gw;
}

// Fallback to all if selected is empty.
if ( empty( $active_gateways ) ) {
    foreach ( $all_gateways as $id => $gw ) {
        if ( 'paymob-pixel' === $id && isset( $all_gateways['paymob-main'] ) ) {
            continue;
        }
        $active_gateways[ $id ] = $gw;
    }
}

// Ensure default gateway is selected
if ( empty( $default_gw ) || ! isset( $active_gateways[ $default_gw ] ) ) {
    if ( isset( $active_gateways['paymob-main'] ) ) {
        $default_gw = 'paymob-main';
    } else {
        $default_gw = array_key_first( $active_gateways ) ?: '';
    }
}

// Preset amounts.
$presets_raw = $settings['preset_amounts'] ?? '50, 100, 250, 500, 1000';
$presets     = array_filter( array_map( 'trim', explode( ',', $presets_raw ) ) );

$currency_symbol = get_woocommerce_currency_symbol();
$min_amount      = ! empty( $settings['min_amount'] ) ? (float) $settings['min_amount'] : 0;
$max_amount      = ! empty( $settings['max_amount'] ) ? (float) $settings['max_amount'] : 0;

$init_amount = ! empty( $prefill['amount'] ) ? $prefill['amount'] : ( ! empty( $settings['default_amount'] ) ? $settings['default_amount'] : '' );

?>
<div class="wc-qp-container">
    <!-- Notice Container -->
    <div id="wc-qp-notice" class="wc-qp-notice" style="display:none;" role="alert"></div>

    <form id="wc-quick-pay-form" class="wc-qp-form" novalidate>
        <?php wp_nonce_field( 'wc_quick_pay_nonce', 'wc_quick_pay_nonce' ); ?>

        <!-- Step 1: Amount Selection -->
        <div class="wc-qp-card wc-qp-amount-card">
            <label class="wc-qp-label" for="wc_qp_amount">
                <span class="wc-qp-label-icon">💰</span>
                <span class="wc-qp-label-text"><?php esc_html_e( 'المبلغ المراد دفعه', 'wc-quick-pay' ); ?></span>
                <span class="wc-qp-required">*</span>
            </label>

            <div class="wc-qp-amount-input-group">
                <input
                    type="number"
                    step="0.01"
                    min="<?php echo esc_attr( $min_amount ?: 0.01 ); ?>"
                    <?php if ( $max_amount > 0 ) : ?>max="<?php echo esc_attr( $max_amount ); ?>"<?php endif; ?>
                    id="wc_qp_amount"
                    name="amount"
                    value="<?php echo esc_attr( $init_amount ); ?>"
                    placeholder="0.00"
                    required
                    autocomplete="off"
                    class="wc-qp-amount-input"
                />
                <span class="wc-qp-currency-tag"><?php echo esc_html( $currency_symbol ); ?></span>
            </div>

            <?php if ( ! empty( $presets ) ) : ?>
                <div class="wc-qp-preset-buttons">
                    <?php foreach ( $presets as $p ) : ?>
                        <button type="button" class="wc-qp-preset-btn <?php echo ( (float) $init_amount === (float) $p ) ? 'is-active' : ''; ?>" data-amount="<?php echo esc_attr( $p ); ?>">
                            <?php echo esc_html( $p ); ?> <?php echo esc_html( $currency_symbol ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( $min_amount > 0 || $max_amount > 0 ) : ?>
                <div class="wc-qp-amount-limits">
                    <?php if ( $min_amount > 0 && $max_amount > 0 ) : ?>
                        <?php printf( esc_html__( 'المبلغ المسموح به: من %1$s إلى %2$s %3$s', 'wc-quick-pay' ), esc_html( $min_amount ), esc_html( $max_amount ), esc_html( $currency_symbol ) ); ?>
                    <?php elseif ( $min_amount > 0 ) : ?>
                        <?php printf( esc_html__( 'الحد الأدنى للمبلغ: %1$s %2$s', 'wc-quick-pay' ), esc_html( $min_amount ), esc_html( $currency_symbol ) ); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Step 2: Invoice & Reference Details -->
        <div class="wc-qp-card">
            <h3 class="wc-qp-card-title">
                <span class="wc-qp-card-icon">📄</span>
                <?php esc_html_e( 'بيانات الفاتورة والطلب', 'wc-quick-pay' ); ?>
            </h3>

            <div class="wc-qp-field-group">
                <label class="wc-qp-label" for="wc_qp_invoice">
                    <?php esc_html_e( 'رقم الفاتورة أو المعاملة المرجعية', 'wc-quick-pay' ); ?>
                    <?php if ( ( $settings['field_invoice_req'] ?? 'yes' ) === 'yes' ) : ?>
                        <span class="wc-qp-required">*</span>
                    <?php endif; ?>
                </label>
                <div class="wc-qp-input-wrapper">
                    <span class="wc-qp-input-icon">#</span>
                    <input
                        type="text"
                        id="wc_qp_invoice"
                        name="invoice"
                        value="<?php echo esc_attr( $prefill['invoice'] ); ?>"
                        placeholder="<?php esc_attr_e( 'أدخل رقم الفاتورة أو الطلب المرتبط (مثلاً: INV-1002)', 'wc-quick-pay' ); ?>"
                        class="wc-qp-input"
                        <?php if ( ( $settings['field_invoice_req'] ?? 'yes' ) === 'yes' ) : ?>required<?php endif; ?>
                    />
                </div>
            </div>

            <?php if ( ( $settings['field_notes_enable'] ?? 'yes' ) === 'yes' ) : ?>
                <div class="wc-qp-field-group" style="margin-top:14px;">
                    <label class="wc-qp-label" for="wc_qp_notes">
                        <?php esc_html_e( 'وصف أو ملاحظات السداد (اختياري)', 'wc-quick-pay' ); ?>
                    </label>
                    <textarea
                        id="wc_qp_notes"
                        name="notes"
                        rows="2"
                        placeholder="<?php esc_attr_e( 'مثال: سداد الدفعة الثانية لمشروع كذا أو تفاصيل الخدمة...', 'wc-quick-pay' ); ?>"
                        class="wc-qp-textarea"
                    ><?php echo esc_textarea( $prefill['notes'] ); ?></textarea>
                </div>
            <?php endif; ?>
        </div>

        <!-- Step 3: Customer Information -->
        <div class="wc-qp-card">
            <h3 class="wc-qp-card-title">
                <span class="wc-qp-card-icon">👤</span>
                <?php esc_html_e( 'بيانات الدافع (المشتري)', 'wc-quick-pay' ); ?>
            </h3>

            <div class="wc-qp-grid-fields">
                <!-- Full Name -->
                <div class="wc-qp-field-group">
                    <label class="wc-qp-label" for="wc_qp_name">
                        <?php esc_html_e( 'الاسم الكامل', 'wc-quick-pay' ); ?>
                        <?php if ( ( $settings['field_name_req'] ?? 'yes' ) === 'yes' ) : ?>
                            <span class="wc-qp-required">*</span>
                        <?php endif; ?>
                    </label>
                    <div class="wc-qp-input-wrapper">
                        <span class="wc-qp-input-icon">👤</span>
                        <input
                            type="text"
                            id="wc_qp_name"
                            name="name"
                            value="<?php echo esc_attr( $prefill['name'] ); ?>"
                            placeholder="<?php esc_attr_e( 'الاسم الأول واسم العائلة', 'wc-quick-pay' ); ?>"
                            class="wc-qp-input"
                            autocomplete="name"
                            <?php if ( ( $settings['field_name_req'] ?? 'yes' ) === 'yes' ) : ?>required<?php endif; ?>
                        />
                    </div>
                </div>

                <!-- Email -->
                <div class="wc-qp-field-group">
                    <label class="wc-qp-label" for="wc_qp_email">
                        <?php esc_html_e( 'البريد الإلكتروني', 'wc-quick-pay' ); ?>
                        <?php if ( ( $settings['field_email_req'] ?? 'yes' ) === 'yes' ) : ?>
                            <span class="wc-qp-required">*</span>
                        <?php endif; ?>
                    </label>
                    <div class="wc-qp-input-wrapper">
                        <span class="wc-qp-input-icon">✉️</span>
                        <input
                            type="email"
                            id="wc_qp_email"
                            name="email"
                            value="<?php echo esc_attr( $prefill['email'] ); ?>"
                            placeholder="name@example.com"
                            class="wc-qp-input"
                            dir="ltr"
                            autocomplete="email"
                            <?php if ( ( $settings['field_email_req'] ?? 'yes' ) === 'yes' ) : ?>required<?php endif; ?>
                        />
                    </div>
                </div>

                <!-- Phone -->
                <div class="wc-qp-field-group" style="grid-column: 1 / -1;">
                    <label class="wc-qp-label" for="wc_qp_phone">
                        <?php esc_html_e( 'رقم الجوال', 'wc-quick-pay' ); ?>
                        <?php if ( ( $settings['field_phone_req'] ?? 'yes' ) === 'yes' ) : ?>
                            <span class="wc-qp-required">*</span>
                        <?php endif; ?>
                    </label>
                    <div class="wc-qp-input-wrapper">
                        <span class="wc-qp-input-icon">📱</span>
                        <input
                            type="tel"
                            id="wc_qp_phone"
                            name="phone"
                            value="<?php echo esc_attr( $prefill['phone'] ); ?>"
                            placeholder="05xxxxxxxx"
                            class="wc-qp-input"
                            dir="ltr"
                            autocomplete="tel"
                            <?php if ( ( $settings['field_phone_req'] ?? 'yes' ) === 'yes' ) : ?>required<?php endif; ?>
                        />
                    </div>
                    <span class="wc-qp-field-hint"><?php esc_html_e( 'ستصلك رسالة وإشعار تأكيد الدفع فور اكتمال العملية.', 'wc-quick-pay' ); ?></span>
                </div>
            </div>
        </div>

        <!-- Step 4: Payment Gateway Selection -->
        <div class="wc-qp-card">
            <h3 class="wc-qp-card-title">
                <span class="wc-qp-card-icon">💳</span>
                <?php esc_html_e( 'طريقة الدفع', 'wc-quick-pay' ); ?>
            </h3>

            <?php if ( empty( $active_gateways ) ) : ?>
                <div class="wc-qp-alert wc-qp-alert-warning">
                    ⚠️ <?php esc_html_e( 'لا توجد بوابات دفع مفعلة حالياً في المتجر. يرجى تفعيل إحدى بوابات الدفع (مثل البنك الأهلي أو البطاقات البنكية).', 'wc-quick-pay' ); ?>
                </div>
            <?php else : ?>
                <div class="wc-qp-gateways-list">
                    <?php
                    $is_first = true;
                    foreach ( $active_gateways as $gw_id => $gateway ) :
                        $checked = false;
                        if ( ! empty( $default_gw ) && $gw_id === $default_gw ) {
                            $checked = true;
                        } elseif ( empty( $default_gw ) && $is_first ) {
                            $checked = true;
                        }
                        $is_first = false;

                        $gw_title = $gateway->get_title();
                        $gw_desc  = $gateway->get_description();
                        $icon     = $gateway->get_icon();

                        // Custom friendly Arabic display for Paymob
                        if ( str_starts_with( $gw_id, 'paymob' ) ) {
                            $gw_title = __( 'باي موب - الدفع الإلكتروني (مدى / فيزا / ماستركارد / Apple Pay)', 'wc-quick-pay' );
                            $gw_desc  = __( 'سداد فوري وآمن ببطاقات مدى، البطاقات الائتمانية، و Apple Pay عبر بوابة Paymob.', 'wc-quick-pay' );
                            $icon     = '<img id="paymob-logo" src="' . esc_url( home_url( '/wp-content/plugins/paymob-for-woocommerce/assets/img/paymob.png' ) ) . '" alt="Paymob" style="max-height:28px;width:auto;" />';
                        }
                    ?>
                        <label class="wc-qp-gateway-item <?php echo $checked ? 'is-selected' : ''; ?>" for="gw_<?php echo esc_attr( $gw_id ); ?>">
                            <div class="wc-qp-gateway-radio-wrap">
                                <input
                                    type="radio"
                                    id="gw_<?php echo esc_attr( $gw_id ); ?>"
                                    name="gateway_id"
                                    value="<?php echo esc_attr( $gw_id ); ?>"
                                    class="wc-qp-gateway-radio"
                                    <?php checked( $checked ); ?>
                                />
                                <span class="wc-qp-custom-radio"></span>
                            </div>

                            <div class="wc-qp-gateway-info">
                                <div class="wc-qp-gateway-title-row">
                                    <strong class="wc-qp-gateway-title"><?php echo esc_html( $gw_title ); ?></strong>
                                    <?php if ( 'wc_ahli_mpgs_gateway' === $gw_id || str_starts_with( $gw_id, 'paymob' ) ) : ?>
                                        <span class="wc-qp-badge-recommended"><?php esc_html_e( 'فوري وآمن', 'wc-quick-pay' ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ( ! empty( $gw_desc ) ) : ?>
                                    <p class="wc-qp-gateway-desc"><?php echo wp_kses_post( $gw_desc ); ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if ( ! empty( $icon ) ) : ?>
                                <div class="wc-qp-gateway-icon">
                                    <?php echo wp_kses_post( $icon ); ?>
                                </div>
                            <?php else : ?>
                                <div class="wc-qp-gateway-icon-fallback">
                                    💳
                                </div>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Submit Button Card -->
        <div class="wc-qp-submit-card">
            <button type="submit" id="wc-qp-submit-btn" class="wc-qp-submit-btn">
                <span class="wc-qp-btn-icon">⚡</span>
                <span class="wc-qp-btn-text">
                    <?php esc_html_e( 'إتمام الدفع الآن', 'wc-quick-pay' ); ?>
                    <span id="wc-qp-btn-dynamic-amount">
                        <?php if ( ! empty( $init_amount ) ) : ?>
                            (<?php echo esc_html( number_format( (float) $init_amount, 2 ) ); ?> <?php echo esc_html( $currency_symbol ); ?>)
                        <?php endif; ?>
                    </span>
                </span>
                <span class="wc-qp-spinner" style="display:none;"></span>
            </button>

            <div class="wc-qp-trust-footer">
                <div class="wc-qp-trust-item">
                    <span>🔒</span>
                    <span><?php esc_html_e( 'معاملة مشفرة وآمنة بنسبة 100%', 'wc-quick-pay' ); ?></span>
                </div>
                <div class="wc-qp-trust-item">
                    <span>⚡</span>
                    <span><?php esc_html_e( 'تحويل فوري لبوابة الدفع', 'wc-quick-pay' ); ?></span>
                </div>
            </div>
        </div>
    </form>
</div>
