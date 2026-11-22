<?php

if (!class_exists('MVX')) {
    return;
}

update_option('is_enable_store_sidebar_position', false);

if (!function_exists('sapa_mvx_vendor_name')) {
    function sapa_mvx_vendor_name()
    {
        $active = sapa_tbay_get_config('show_vendor_name', true);

        if (!$active) {
            return;
        }

        global $product;
        $product_id = $product->get_id();

        $vendor = get_mvx_product_vendors($product_id);

        if (empty($vendor)) {
            return;
        }

        $sold_by_text = apply_filters('mvx_sold_by_text', esc_html__('Sold by:', 'sapa')); 
        
        if( get_mvx_vendor_settings('display_product_seller', 'settings_general') && apply_filters( 'mvx_enable_sold_by_on_wc_blocks_product_grid', true, $product ) ) :
        ?> 
            <div class="sold-by-meta sold-mvx">
                <span class="sold-by-label"><?php echo trim($sold_by_text); ?> </span>
                <a href="<?php echo esc_url($vendor->permalink); ?>"><?php echo esc_html($vendor->user_data->display_name); ?></a>
            </div>

            <?php
        endif;
    }

    add_filter('mvx_sold_by_text_after_products_shop_page', '__return_false');
    add_action('sapa_woo_after_shop_loop_item_caption', 'sapa_mvx_vendor_name', 15);
    add_action('sapa_woo_after_single_rating', 'sapa_mvx_vendor_name', 15);
    add_action('sapa_woo_list_after_short_description', 'sapa_mvx_vendor_name', 15);
}

/*Get title My Account in top bar mobile*/
if (! function_exists('sapa_tbay_mvx_get_title_mobile')) {
    function sapa_tbay_mvx_get_title_mobile($title = '')
    {
        if (sapa_woo_is_vendor_page()) {
            $vendor_id  = get_queried_object()->term_id;
            $vendor     = get_mvx_vendor_by_term($vendor_id);

            $title          = $vendor->page_title;
        }

        return $title;
    }
    add_filter('sapa_get_filter_title_mobile', 'sapa_tbay_mvx_get_title_mobile');
}

if (! function_exists('sapa_tbay_mvx_description')) {
    function sapa_tbay_mvx_description($description)
    {
        global $MVX;

        if (is_tax($MVX->taxonomy->taxonomy_name)) {
            $vendor_id = get_queried_object()->term_id;
            // Get vendor info
            $vendor = get_mvx_vendor_by_term($vendor_id);

            if ($vendor) {
                $description = $vendor->description;
            }
        }

        return $description;
    }
    add_filter('the_content', 'sapa_tbay_mvx_description', 10, 1);
}

/*Fix WCMP 3.7*/
if ( !function_exists('sapa_mvx_load_default_vendor_store') ) {
    function sapa_mvx_load_default_vendor_store() {
        return true;
    }
    add_filter( 'mvx_load_default_vendor_store', 'sapa_mvx_load_default_vendor_store', 10, 1 );
}

if ( !function_exists('sapa_mvx_store_sidebar_args') ) {
    function sapa_mvx_store_sidebar_args() {
        $sidebars = array(
            'name'          => esc_html__( 'MultiVendor Marketplace Store Sidebar ', 'sapa' ),
            'id'            => 'mvx-marketplace-store',
            'description'   => esc_html__( 'Add widgets here to appear in your site.', 'sapa' ),
            'before_widget' => '<aside id="%1$s" class="widget %2$s">',
            'after_widget'  => '</aside>',
            'before_title'  => '<h2 class="widget-title">',
            'after_title'   => '</h2>',
        ); 

        return $sidebars;
    }
    add_filter( 'mvx_store_sidebar_args', 'sapa_mvx_store_sidebar_args', 10, 1 );
}
/*End fix WCMP 3.7*/