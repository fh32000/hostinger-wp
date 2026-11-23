<?php
/**
 * Quick Pay Form Component.
 * Fully bilingual (Arabic / English) and compatible with Zero-Field Checkout.
 *
 * @package WooCommerce_Quick_Pay
 */

defined( 'ABSPATH' ) || exit;

// Settings.
$settings = get_option( 'wc_quick_pay_settings', [] );

// Internationalization.
$is_en           = class_exists( 'WC_Quick_Pay_I18n' ) && WC_Quick_Pay_I18n::is_en();
$ui              = class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_strings() : [];
$currency_symbol = class_exists( 'WC_Quick_Pay_I18n' ) ? WC_Quick_Pay_I18n::get_clean_currency() : 'SAR';

// Gateways.
$all_gateways     = WC()->payment_gateways()->get_available_payment_gateways();
$mode             = $settings['gateway_mode'] ?? 'all';
$selected_list    = $settings['selected_gateways'] ?? [];
$default_gw       = ( ! empty( $prefill['gateway_id'] ) ) ? $prefill['gateway_id'] : ( $settings['default_gateway'] ?? '' );
$single_auto      = ( $settings['single_gateway_auto_select'] ?? 'yes' ) === 'yes';

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

$is_single_gw = ( count( $active_gateways ) === 1 );

// Amount Configuration.
$amount_mode  = $settings['amount_mode'] ?? 'custom';
$presets_raw  = $settings['preset_amounts'] ?? '1000, 2000, 3000, 5000, 7000, 10000, 15000, 20000, 30000, 40000, 50000, 60000, 70000, 80000, 90000, 100000';
$presets      = array_filter( array_map( 'trim', explode( ',', $presets_raw ) ) );

$min_amount   = ! empty( $settings['min_amount'] ) ? (float) $settings['min_amount'] : 0;
$max_amount   = ! empty( $settings['max_amount'] ) ? (float) $settings['max_amount'] : 0;

if ( $is_en ) {
    $amount_label = ! empty( $settings['amount_label_en'] ) ? $settings['amount_label_en'] : ( $ui['amount_label'] ?? 'Payment Amount' );
    $amount_help  = array_key_exists( 'amount_help_text_en', $settings ) ? $settings['amount_help_text_en'] : ( $ui['amount_help'] ?? '' );
} else {
    $amount_label = ! empty( $settings['amount_label'] ) ? $settings['amount_label'] : ( $ui['amount_label'] ?? 'المبلغ المراد دفعه' );
    $amount_help  = array_key_exists( 'amount_help_text', $settings ) ? $settings['amount_help_text'] : ( $ui['amount_help'] ?? '' );
}

$init_amount  = ! empty( $prefill['amount'] ) ? $prefill['amount'] : ( ! empty( $settings['default_amount'] ) ? $settings['default_amount'] : '' );

// Locked Amount Logic (Fixed mode, URL ?lock=1, or ?amount=XX with lock_amount_from_url enabled)
$lock_url_opt = ( $settings['lock_amount_from_url'] ?? 'yes' ) === 'yes';
$is_locked    = ( ! empty( $prefill['lock'] ) ) || ( $lock_url_opt && ! empty( $prefill['amount'] ) ) || ( 'fixed' === $amount_mode && ! empty( $init_amount ) );
$show_presets = ( $settings['show_preset_buttons'] ?? 'yes' ) === 'yes' && ! empty( $presets ) && ( 'fixed' !== $amount_mode );

// Section Visibility: Invoice & Order (Dashboard option controls default, URL param can force hide).
$show_section_invoice = ( $settings['show_section_invoice'] ?? 'yes' ) === 'yes';
if ( ! empty( $prefill['hide_invoice'] ) ) {
    $show_section_invoice = false;
}
$field_invoice_enable = ( $settings['field_invoice_enable'] ?? 'yes' ) === 'yes';
$field_invoice_req    = ( $settings['field_invoice_req'] ?? 'yes' ) === 'yes';
$field_notes_enable   = ( $settings['field_notes_enable'] ?? 'yes' ) === 'yes';

$render_invoice_card  = $show_section_invoice && ( $field_invoice_enable || $field_notes_enable );

// Section Visibility: Customer / Buyer Information (Dashboard option controls default, URL param can force hide).
$show_section_customer = ( $settings['show_section_customer'] ?? 'yes' ) === 'yes';
if ( ! empty( $prefill['hide_customer'] ) ) {
    $show_section_customer = false;
}
$field_name_enable  = ( $settings['field_name_enable'] ?? 'yes' ) === 'yes';
$field_name_req     = ( $settings['field_name_req'] ?? 'yes' ) === 'yes';
$field_email_enable = ( $settings['field_email_enable'] ?? 'yes' ) === 'yes';
$field_email_req    = ( $settings['field_email_req'] ?? 'yes' ) === 'yes';
$field_phone_enable = ( $settings['field_phone_enable'] ?? 'yes' ) === 'yes';
$field_phone_req    = ( $settings['field_phone_req'] ?? 'yes' ) === 'yes';

$render_customer_card = $show_section_customer && ( $field_name_enable || $field_email_enable || $field_phone_enable );

?>
<div class="wc-qp-container <?php echo ( ! $render_customer_card && ! $render_invoice_card ) ? 'wc-qp-zero-fields-mode' : ''; ?>">
    <!-- Notice Container -->
    <div id="wc-qp-notice" class="wc-qp-notice" style="display:none;" role="alert"></div>

    <form id="wc-quick-pay-form" class="wc-qp-form" novalidate>
        <?php wp_nonce_field( 'wc_quick_pay_nonce', 'wc_quick_pay_nonce' ); ?>

        <!-- Step 1: Amount Selection -->
        <div class="wc-qp-card wc-qp-amount-card <?php echo $is_locked ? 'is-amount-locked' : ''; ?>">
            <label class="wc-qp-label" for="wc_qp_amount">
                <span class="wc-qp-label-icon">💰</span>
                <span class="wc-qp-label-text"><?php echo esc_html( $amount_label ); ?></span>
                <span class="wc-qp-required">*</span>
                <?php if ( $is_locked ) : ?>
                    <span class="wc-qp-amount-locked-badge"><?php echo esc_html( $ui['amount_locked'] ?? '🔒 مبلغ محدد للفاتورة' ); ?></span>
                <?php endif; ?>
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
                    <?php if ( $is_locked ) : ?>readonly tabindex="-1"<?php endif; ?>
                    autocomplete="off"
                    class="wc-qp-amount-input <?php echo ( 'presets_only' === $amount_mode && ! $is_locked ) ? 'is-presets-only' : ''; ?>"
                />
                <span class="wc-qp-currency-tag"><?php echo esc_html( $currency_symbol ); ?></span>
            </div>

            <?php if ( ! empty( $amount_help ) ) : ?>
                <p class="wc-qp-amount-help-text"><?php echo esc_html( $amount_help ); ?></p>
            <?php endif; ?>

            <?php if ( $show_presets && ! $is_locked ) : ?>
                <div class="wc-qp-presets-wrapper">
                    <div class="wc-qp-presets-label"><?php echo esc_html( $is_en ? 'QUICK AMOUNTS' : 'المبالغ السريعة' ); ?></div>
                    <div class="wc-qp-preset-buttons">
                        <?php foreach ( $presets as $p ) : 
                            $clean_p = trim( str_replace( [ ',', ' ' ], '', $p ) );
                            $float_p = (float) $clean_p;
                            $formatted_p = $float_p > 0 ? number_format( $float_p, 2 ) : $p;
                        ?>
                            <button type="button" class="wc-qp-preset-btn <?php echo ( (float) $init_amount === $float_p ) ? 'is-active' : ''; ?>" data-amount="<?php echo esc_attr( $clean_p ); ?>">
                                <?php echo esc_html( $formatted_p ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! $is_locked && ( $min_amount > 0 || $max_amount > 0 ) ) : ?>
                <div class="wc-qp-amount-limits">
                    <?php if ( $min_amount > 0 && $max_amount > 0 ) : ?>
                        <?php
                        if ( $is_en ) {
                            printf( 'Allowed amount: from %1$s to %2$s %3$s', esc_html( $min_amount ), esc_html( $max_amount ), esc_html( $currency_symbol ) );
                        } else {
                            printf( esc_html__( 'المبلغ المسموح به: من %1$s إلى %2$s %3$s', 'wc-quick-pay' ), esc_html( $min_amount ), esc_html( $max_amount ), esc_html( $currency_symbol ) );
                        }
                        ?>
                    <?php elseif ( $min_amount > 0 ) : ?>
                        <?php
                        if ( $is_en ) {
                            printf( 'Minimum amount: %1$s %2$s', esc_html( $min_amount ), esc_html( $currency_symbol ) );
                        } else {
                            printf( esc_html__( 'الحد الأدنى للمبلغ: %1$s %2$s', 'wc-quick-pay' ), esc_html( $min_amount ), esc_html( $currency_symbol ) );
                        }
                        ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Step 2: Invoice & Reference Details (Conditional Render) -->
        <?php if ( $render_invoice_card ) : ?>
            <div class="wc-qp-card wc-qp-invoice-card">
                <h3 class="wc-qp-card-title">
                    <span class="wc-qp-card-icon">📄</span>
                    <?php echo esc_html( $ui['invoice_title'] ?? 'بيانات الفاتورة والطلب' ); ?>
                </h3>

                <?php if ( $field_invoice_enable ) : ?>
                    <div class="wc-qp-field-group">
                        <label class="wc-qp-label" for="wc_qp_invoice">
                            <?php echo esc_html( $ui['field_invoice'] ?? 'رقم الفاتورة أو المعاملة المرجعية' ); ?>
                            <?php if ( $field_invoice_req ) : ?>
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
                                placeholder="<?php echo esc_attr( $ui['field_invoice_ph'] ?? 'INV-1002' ); ?>"
                                class="wc-qp-input"
                                <?php if ( $field_invoice_req ) : ?>required<?php endif; ?>
                            />
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( $field_notes_enable ) : ?>
                    <div class="wc-qp-field-group" style="<?php echo $field_invoice_enable ? 'margin-top:14px;' : ''; ?>">
                        <label class="wc-qp-label" for="wc_qp_notes">
                            <?php echo esc_html( $ui['field_notes'] ?? 'وصف أو ملاحظات السداد (اختياري)' ); ?>
                        </label>
                        <textarea
                            id="wc_qp_notes"
                            name="notes"
                            rows="2"
                            placeholder="<?php echo esc_attr( $ui['field_notes_ph'] ?? '' ); ?>"
                            class="wc-qp-textarea"
                        ><?php echo esc_textarea( $prefill['notes'] ); ?></textarea>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Step 3: Customer Information (Conditional Render - Can be completely hidden) -->
        <?php if ( $render_customer_card ) : ?>
            <div class="wc-qp-card wc-qp-customer-card">
                <h3 class="wc-qp-card-title">
                    <span class="wc-qp-card-icon">👤</span>
                    <?php echo esc_html( $ui['customer_title'] ?? 'بيانات الدافع (المشتري)' ); ?>
                </h3>

                <div class="wc-qp-grid-fields">
                    <!-- Full Name -->
                    <?php if ( $field_name_enable ) : ?>
                        <div class="wc-qp-field-group <?php echo ( ! $field_email_enable ) ? 'wc-qp-field-full' : ''; ?>">
                            <label class="wc-qp-label" for="wc_qp_name">
                                <?php echo esc_html( $ui['field_name'] ?? 'الاسم الكامل' ); ?>
                                <?php if ( $field_name_req ) : ?>
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
                                    placeholder="<?php echo esc_attr( $ui['field_name_ph'] ?? 'الاسم الأول واسم العائلة' ); ?>"
                                    class="wc-qp-input"
                                    autocomplete="name"
                                    <?php if ( $field_name_req ) : ?>required<?php endif; ?>
                                />
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Email -->
                    <?php if ( $field_email_enable ) : ?>
                        <div class="wc-qp-field-group <?php echo ( ! $field_name_enable ) ? 'wc-qp-field-full' : ''; ?>">
                            <label class="wc-qp-label" for="wc_qp_email">
                                <?php echo esc_html( $ui['field_email'] ?? 'البريد الإلكتروني' ); ?>
                                <?php if ( $field_email_req ) : ?>
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
                                    placeholder="<?php echo esc_attr( $ui['field_email_ph'] ?? 'name@example.com' ); ?>"
                                    class="wc-qp-input"
                                    dir="ltr"
                                    autocomplete="email"
                                    <?php if ( $field_email_req ) : ?>required<?php endif; ?>
                                />
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Phone -->
                    <?php if ( $field_phone_enable ) : ?>
                        <div class="wc-qp-field-group" style="grid-column: 1 / -1;">
                            <label class="wc-qp-label" for="wc_qp_phone">
                                <?php echo esc_html( $ui['field_phone'] ?? 'رقم الجوال' ); ?>
                                <?php if ( $field_phone_req ) : ?>
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
                                    placeholder="<?php echo esc_attr( $ui['field_phone_ph'] ?? '05xxxxxxxx' ); ?>"
                                    class="wc-qp-input"
                                    dir="ltr"
                                    autocomplete="tel"
                                    <?php if ( $field_phone_req ) : ?>required<?php endif; ?>
                                />
                            </div>
                            <span class="wc-qp-field-hint"><?php echo esc_html( $ui['field_email_help'] ?? 'ستصلك رسالة وإشعار تأكيد الدفع فور اكتمال العملية.' ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 4: Payment Gateway Selection (Flexible 1, 2, or All Gateways) -->
        <div class="wc-qp-card wc-qp-gateways-card">
            <h3 class="wc-qp-card-title">
                <span class="wc-qp-card-icon">💳</span>
                <?php echo esc_html( $ui['gateway_title'] ?? 'طريقة الدفع' ); ?>
            </h3>

            <?php if ( empty( $active_gateways ) ) : ?>
                <div class="wc-qp-alert wc-qp-alert-warning">
                    ⚠️ <?php echo esc_html( $is_en ? 'No active payment gateways available. Please contact store support.' : 'لا توجد بوابات دفع مفعلة حالياً في المتجر. يرجى مراجعة إدارة المتجر.' ); ?>
                </div>
            <?php else : ?>
                <div class="wc-qp-gateways-list <?php echo ( $is_single_gw && $single_auto ) ? 'is-single-gateway-wrap' : ''; ?>">
                    <?php
                    $is_first = true;
                    foreach ( $active_gateways as $gw_id => $gateway ) :
                        $checked = false;
                        if ( $is_single_gw ) {
                            $checked = true;
                        } elseif ( ! empty( $default_gw ) && $gw_id === $default_gw ) {
                            $checked = true;
                        } elseif ( empty( $default_gw ) && $is_first ) {
                            $checked = true;
                        }
                        $is_first = false;

                        $gw_title = $gateway->get_title();
                        $gw_desc  = $gateway->get_description();
                        $icon     = $gateway->get_icon();

                        // Friendly display for Paymob
                        if ( str_starts_with( $gw_id, 'paymob' ) ) {
                            $gw_title = $is_en ? 'Paymob - Cards & Apple Pay' : 'باي موب - الدفع الإلكتروني (مدى / فيزا / ماستركارد / Apple Pay)';
                            $gw_desc  = $is_en ? 'Secure instant payment via Mada, Credit Cards & Apple Pay.' : 'سداد فوري وآمن ببطاقات مدى، البطاقات الائتمانية، و Apple Pay عبر بوابة Paymob.';
                            $icon     = '<img id="paymob-logo" src="' . esc_url( home_url( '/wp-content/plugins/paymob-for-woocommerce/assets/img/paymob.png' ) ) . '" alt="Paymob" style="max-height:28px;width:auto;" />';
                        }
                    ?>
                        <label class="wc-qp-gateway-item <?php echo $checked ? 'is-selected' : ''; ?> <?php echo ( $is_single_gw && $single_auto ) ? 'is-single' : ''; ?>" for="gw_<?php echo esc_attr( $gw_id ); ?>">
                            <div class="wc-qp-gateway-radio-wrap" <?php if ( $is_single_gw && $single_auto ) : ?>style="display:none;"<?php endif; ?>>
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

                            <?php if ( $is_single_gw && $single_auto ) : ?>
                                <input type="hidden" name="gateway_id" value="<?php echo esc_attr( $gw_id ); ?>" />
                            <?php endif; ?>

                            <div class="wc-qp-gateway-info">
                                <div class="wc-qp-gateway-title-row">
                                    <strong class="wc-qp-gateway-title"><?php echo esc_html( $gw_title ); ?></strong>
                                    <?php if ( 'wc_ahli_mpgs_gateway' === $gw_id || str_starts_with( $gw_id, 'paymob' ) ) : ?>
                                        <span class="wc-qp-badge-recommended"><?php echo esc_html( $is_en ? 'Instant & Secure' : 'فوري وآمن' ); ?></span>
                                    <?php elseif ( $is_single_gw ) : ?>
                                        <span class="wc-qp-badge-recommended">✅ <?php echo esc_html( $ui['single_gw_badge'] ?? 'طريقة الدفع المعتمدة' ); ?></span>
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
                    <?php echo esc_html( $ui['submit_btn'] ?? 'إتمام الدفع الآن' ); ?>
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
                    <span><?php echo esc_html( $ui['trust_secure'] ?? 'معاملة مشفرة وآمنة بنسبة 100%' ); ?></span>
                </div>
                <div class="wc-qp-trust-item">
                    <span>⚡</span>
                    <span><?php echo esc_html( $ui['trust_instant'] ?? 'تحويل فوري لبوابة الدفع' ); ?></span>
                </div>
            </div>
        </div>
    </form>
</div>
