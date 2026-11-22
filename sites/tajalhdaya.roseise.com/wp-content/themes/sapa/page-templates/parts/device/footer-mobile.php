<?php
    if( sapa_checkout_optimized() ) return;
    /**
     * sapa_before_topbar_mobile hook
     */
    do_action('sapa_before_footer_mobile');
    $mobile_footer_slides = sapa_tbay_get_config('mobile_footer_slides');
?>



<?php
    if ($mobile_footer_slides && !empty($mobile_footer_slides)) {
        ?>
            <div class="footer-device-mobile d-xl-none clearfix">
            <?php
                /**
                * sapa_before_footer_mobile hook
                */
                do_action('sapa_before_footer_mobile');

        /**
        * Hook: sapa_footer_mobile_content.
        *
        * @hooked sapa_the_custom_list_menu_icon - 10
        */

        do_action('sapa_footer_mobile_content');

        /**
        * sapa_after_footer_mobile hook
        */
        do_action('sapa_after_footer_mobile'); ?>
            </div>
        <?php
    }
?>

