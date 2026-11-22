<?php 

if(!class_exists('WooCommerce')) return;

add_action('sapa_woocommerce_group_buttons', 'sapa_the_quick_view', 20, 1);
add_action('sapa_woocommerce_group_buttons', 'sapa_the_yith_wishlist', 30, 1);
add_action('sapa_woocommerce_group_buttons', 'sapa_the_yith_compare', 40, 1);

if ( ! function_exists( 'sapa_woocommerce_setup_size_image' ) ) {
    function sapa_woocommerce_setup_size_image() {
        $thumbnail_width = 480;
        $main_image_width = 800; 
        $cropping_custom_width = 1;
        $cropping_custom_height = 1.33;

        // Image sizes
        update_option('woocommerce_thumbnail_image_width', $thumbnail_width);
        update_option('woocommerce_single_image_width', $main_image_width);

        update_option('woocommerce_thumbnail_cropping', 'custom');
        update_option('woocommerce_thumbnail_cropping_custom_width', $cropping_custom_width);
        update_option('woocommerce_thumbnail_cropping_custom_height', $cropping_custom_height);
    }
    add_action( 'after_setup_theme', 'sapa_woocommerce_setup_size_image' );
}

if(sapa_tbay_get_global_config('config_media',false)) {
    remove_action( 'after_setup_theme', 'sapa_woocommerce_setup_size_image' );
}

if ( ! function_exists( 'sapa_woocommerce_quantity_mode_remove_grid_block_add_to_cart' ) ) {
    function sapa_woocommerce_quantity_mode_remove_grid_block_add_to_cart() {
        if( sapa_is_woo_variation_swatches_pro() || sapa_woocommerce_quantity_mode_active() ) {
            remove_action( 'sapa_woocommerce_group_add_to_cart', 'woocommerce_template_loop_add_to_cart', 10 );
        }
    }
    add_action( 'sapa_tbay_after_shop_loop_item_title', 'sapa_woocommerce_quantity_mode_remove_grid_block_add_to_cart', 10 ); 
}