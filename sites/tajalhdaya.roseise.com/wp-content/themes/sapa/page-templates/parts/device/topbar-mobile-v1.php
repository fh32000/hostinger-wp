<?php
    if( sapa_checkout_optimized() ) return;
    $class_top_bar 	=  '';

    $always_display_logo 			= sapa_tbay_get_config('always_display_logo', true);
    if (!$always_display_logo && !sapa_catalog_mode_active() && sapa_woocommerce_activated() && (is_product() || is_cart() || is_checkout())) {
        $class_top_bar .= ' active-home-icon';
    }
?>
<div class="topbar-device-mobile d-xl-none clearfix <?php echo esc_attr($class_top_bar); ?>">

	<?php
        /**
        * sapa_before_header_mobile hook
        */
        do_action('sapa_before_header_mobile');

        /**
        * Hook: sapa_header_mobile_content.
        *
        * @hooked sapa_the_button_mobile_menu - 5
        * @hooked sapa_the_logo_mobile - 10
        * @hooked sapa_the_title_page_mobile - 10
        */

        do_action('sapa_header_mobile_content');

        /**
        * sapa_after_header_mobile hook

        * @hooked sapa_the_search_header_mobile - 5
        */
        
        do_action('sapa_after_header_mobile');
    ?>
</div>