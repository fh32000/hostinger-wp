<?php
/**
 * Plugin Name: Muskise Checkout Safari & RTL Fix
 * Plugin URI: https://muskise.com
 * Description: Fixes WooCommerce Checkout Block layout collapse and squashed input fields on iOS Safari and RTL views.
 * Version: 1.0.0
 * Author: Antigravity
 * Text Domain: muskise-safari-fix
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'muskise_enqueue_safari_fix', 99999);
function muskise_enqueue_safari_fix() {
    $custom_css = '
    /* ========================================================
       Muskise - iOS Safari / RTL WooCommerce Checkout Block Fix
       ======================================================== */

    /* 1. Reset theme content wrapper to full width in RTL */
    .the_content_wrapper,
    .woocommerce-page .the_content_wrapper,
    .has_content .the_content_wrapper {
        width: 100% !important;
        max-width: 100% !important;
        float: none !important;
        clear: both !important;
        box-sizing: border-box !important;
        direction: rtl !important;
    }

    /* 2. Prevent Safari WebKit 1fr Grid collapse in RTL */
    .wp-block-woocommerce-checkout,
    .wc-block-checkout {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        direction: rtl !important;
        margin: 0 auto !important;
    }

    /* Desktop layout (>= 960px) */
    @media (min-width: 960px) {
        .wp-block-woocommerce-checkout,
        .wc-block-checkout {
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-start !important;
            gap: 32px !important;
        }

        .wc-block-checkout__main,
        .wp-block-woocommerce-checkout-fields-block {
            flex: 1 1 62% !important;
            max-width: 62% !important;
            min-width: 0 !important;
            width: 62% !important;
            box-sizing: border-box !important;
        }

        .wc-block-checkout__sidebar,
        .wp-block-woocommerce-checkout-totals-block {
            flex: 1 1 38% !important;
            max-width: 38% !important;
            min-width: 320px !important;
            width: 38% !important;
            box-sizing: border-box !important;
        }
    }

    /* Mobile / Tablet layout (< 960px) - iPhone & iPad */
    @media (max-width: 959px) {
        .wp-block-woocommerce-checkout,
        .wc-block-checkout,
        .wc-block-checkout__form,
        .wc-block-checkout__main,
        .wc-block-checkout__sidebar,
        .wp-block-woocommerce-checkout-fields-block,
        .wp-block-woocommerce-checkout-totals-block {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            flex: 1 1 100% !important;
            box-sizing: border-box !important;
            float: none !important;
        }

        /* Prevent form items from squashing into a narrow right strip */
        .wc-block-checkout__billing-fields .wc-block-components-address-form,
        .wc-block-checkout__shipping-fields .wc-block-components-address-form {
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            gap: 12px !important;
            box-sizing: border-box !important;
        }

        .wc-block-checkout__billing-fields .wc-block-components-address-form .wc-block-components-text-input,
        .wc-block-checkout__billing-fields .wc-block-components-address-form .wc-block-components-select-input,
        .wc-block-checkout__billing-fields .wc-block-components-address-form .wc-block-components-state-input,
        .wc-block-checkout__billing-fields .wc-block-components-address-form .wc-block-components-country-input,
        .wc-block-checkout__shipping-fields .wc-block-components-address-form .wc-block-components-text-input,
        .wc-block-checkout__shipping-fields .wc-block-components-address-form .wc-block-components-select-input,
        .wc-block-checkout__shipping-fields .wc-block-components-address-form .wc-block-components-state-input,
        .wc-block-checkout__shipping-fields .wc-block-components-address-form .wc-block-components-country-input,
        .wc-block-components-address-form__address_1,
        .wc-block-components-address-form__address_2,
        .wc-block-components-address-form__company,
        .wc-block-components-address-form__city,
        .wc-block-components-address-form__state,
        .wc-block-components-address-form__postcode {
            flex: 1 1 100% !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            margin-right: 0 !important;
            margin-left: 0 !important;
        }
    }

    /* 3. Input controls normalization for iOS Safari */
    .wc-block-components-text-input,
    .wc-block-components-combobox-control,
    .wc-blocks-components-select {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .wc-block-components-text-input input,
    .wc-block-components-combobox-control input,
    .wc-blocks-components-select select {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        -webkit-appearance: none !important;
        border-radius: 4px !important;
        font-size: 16px !important; /* Prevents auto zoom in iOS Safari */
    }
    ';

    wp_register_style('muskise-safari-fix-inline', false);
    wp_enqueue_style('muskise-safari-fix-inline');
    wp_add_inline_style('muskise-safari-fix-inline', $custom_css);
}
