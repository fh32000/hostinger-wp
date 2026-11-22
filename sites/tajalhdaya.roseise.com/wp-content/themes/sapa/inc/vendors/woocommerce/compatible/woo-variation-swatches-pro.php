<?php

if ( !sapa_is_woo_variation_swatches_pro() ) return;

if ( ! function_exists( 'sapa_quantity_swatches_pro_field_archive' ) ) {
    function sapa_quantity_swatches_pro_field_archive( ) {

        global $product;
        if ( sapa_is_quantity_field_archive() ) {
            woocommerce_quantity_input( array( 'min_value' => 1, 'max_value' => $product->backorders_allowed() ? '' : $product->get_stock_quantity() ) );
        }

    }
}

if ( ! function_exists( 'sapa_variation_swatches_pro_group_button' ) ) {
    add_action('sapa_woo_before_shop_loop_item_caption', 'sapa_variation_swatches_pro_group_button', 5);
    function sapa_variation_swatches_pro_group_button() {
        if( !sapa_is_woo_variation_swatches_pro() ) return;

        $class_active = '';

        if( sapa_woocommerce_quantity_mode_active() ) {
            $class_active .= 'quantity-group-btn';

            if( sapa_is_quantity_field_archive() ) {
                $class_active .= ' active';
            }
        } else { 
            $class_active .= 'woo-swatches-pro-btn';
        }

        echo '<div class="'. esc_attr($class_active) .'">';

            if( sapa_woocommerce_quantity_mode_active() ) {
                sapa_quantity_swatches_pro_field_archive();
            }

            woocommerce_template_loop_add_to_cart();
        echo '</div>';
    }
}