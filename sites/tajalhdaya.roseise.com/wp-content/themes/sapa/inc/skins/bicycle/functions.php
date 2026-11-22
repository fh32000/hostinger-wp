<?php 

if (!function_exists('sapa_tbay_private_size_image_setup')) {
    function sapa_tbay_private_size_image_setup()
    {
        if (sapa_tbay_get_global_config('config_media', false)) {
            return;
        }

        // Post Thumbnails Size 
        set_post_thumbnail_size(500, 300, true); // Unlimited height, soft crop
        update_option('thumbnail_size_w', 500);
        update_option('thumbnail_size_h', 300);

        update_option('medium_size_w', 555);    
        update_option('medium_size_h', 333);

        update_option('large_size_w', 770);
        update_option('large_size_h', 466); 
    }
    add_action('after_setup_theme', 'sapa_tbay_private_size_image_setup');
}
  
/**
 *  Include Load Google Front
 */

if ( !function_exists('sapa_fonts_url') ) {
	function sapa_fonts_url() {
        /**
         * Load Google Front
         */

        $fonts_url = '';

        /* Translators: If there are csapacters in your language that are not
        * supported by Montserrat, translate this to 'off'. Do not translate
        * into your own language.
        */
        $FjallaOne       = _x('on', 'Outfit font: on or off', 'sapa');

     
        if ('off' !== $FjallaOne) {
            $font_families = array();
     
            if ('off' !== $FjallaOne) {
                $font_families[] = 'Fjalla One:400,500,600,700';
            }

            $query_args = array(
                'family' => rawurlencode(implode('|', $font_families)),
                'subset' => urlencode('latin,latin-ext'),
                'display' => urlencode('swap'),
            );
            
            $protocol = is_ssl() ? 'https:' : 'http:';
            $fonts_url = add_query_arg($query_args, $protocol .'//fonts.googleapis.com/css');
        }
     
        return esc_url_raw($fonts_url);
		
	}
}

if ( !function_exists('sapa_tbay_fonts_url') ) {
	function sapa_tbay_fonts_url() {  
        $show_typography  = sapa_tbay_get_config('show_typography', false);
        $font_source      = sapa_tbay_get_config('font_source', "1");
        $font_google_code = sapa_tbay_get_config('font_google_code');
        if ( $show_typography && $font_source == "2" && !empty($font_google_code)) {
            wp_enqueue_style('sapa-theme-fonts', $font_google_code, array(), null);
        } else {
            wp_enqueue_style('circularstd', SAPA_STYLES . '/circularstd.css', array(), '1.0'); 
            wp_enqueue_style('sapa-theme-fonts', sapa_fonts_url(), array(), null);
        }
	}
	add_action('wp_enqueue_scripts', 'sapa_tbay_fonts_url');
}

if (!function_exists('sapa_customize_slick_prev')) {
    function sapa_customize_slick_prev() {
        return '<i class="tb-icon tb-icon-chevron-left"></i>';
    }
    add_filter('sapa_slick_prev', 'sapa_customize_slick_prev', 10, 1);
}

if (!function_exists('sapa_customize_slick_next')) {
    function sapa_customize_slick_next() {
        return '<i class="tb-icon tb-icon-chevron-right"></i>';
    }
    add_filter('sapa_slick_next', 'sapa_customize_slick_next', 10, 1);
}


