<?php
if ( !sapa_woocommerce_activated() ) return;

/**
 * ------------------------------------------------------------------------------------------------
 * QuickView button
 * ------------------------------------------------------------------------------------------------
 */
if (! function_exists('sapa_the_quick_view')) {
    function sapa_the_quick_view($product_id)
    {
        if( !sapa_tbay_get_config('enable_quickview', false) ) return;
        ?>
        <div class="tbay-quick-view">  
            <a href="#" class="qview-button" title ="<?php esc_attr_e('Quick View', 'sapa') ?>" data-effect="mfp-move-from-top" data-product_id="<?php echo esc_attr($product_id); ?>">
                <i class="tb-icon tb-icon-search"></i>
                <span><?php esc_html_e('Quick View', 'sapa') ?></span>
            </a>
        </div> 
        <?php
    }
}