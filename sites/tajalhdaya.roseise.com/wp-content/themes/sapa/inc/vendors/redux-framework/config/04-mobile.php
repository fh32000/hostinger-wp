<?php
/**
 * Redux Framework checkbox config.
 * For full documentation, please visit: http://devs.redux.io/
 *
 * @package Redux Framework
 */

defined( 'ABSPATH' ) || exit;

/** Mobile Settings **/
Redux::set_section(
	$opt_name,
	array(
        'icon' => 'zmdi zmdi-smartphone-iphone',
        'title' => esc_html__('Mobile', 'sapa'),
	)
);


// Mobile Header settings
Redux::set_section(
	$opt_name,
	array(
        'subsection' => true,
        'title' => esc_html__('Header', 'sapa'),
        'fields' => array(
            array(
                'id' => 'mobile_header',
                'type' => 'switch',
                'title' => esc_html__('Enable Mobile Header', 'sapa'),
                'subtitle'  => esc_html__('Only off when use Header Elementor Pro on mobile ', 'sapa'),
                'default' => true
            ),
            array(
                'id' => 'mobile-logo',
                'type' => 'media',
                'required' => array('mobile_header','=', true),
                'title' => esc_html__('Upload Logo', 'sapa'),
                'subtitle' => esc_html__('Image File (.png or .gif)', 'sapa'),
            ),
            array(
                'id'        => 'logo_img_width_mobile',
                'type'      => 'slider',
                'required' => array('mobile_header','=', true),
                'title'     => esc_html__('Logo maximum width (px)', 'sapa'),
                "default"   => 100,
                "min"       => 50,
                "step"      => 1,
                "max"       => 600,
            ),
            array(
                'id'             => 'logo_mobile_padding',
                'type'           => 'spacing',
                'mode'           => 'padding',
                'required' => array('mobile_header','=', true),
                'units'          => array('px'),
                'units_extended' => 'false',
                'title'          => esc_html__('Logo Padding', 'sapa'),
                'desc'           => esc_html__('Add more spacing around logo.', 'sapa'),
                'default'            => array(
                    'padding-top'     => '',
                    'padding-right'   => '',
                    'padding-bottom'  => '',
                    'padding-left'    => '',
                    'units'          => 'px',
                ),
            ),
            array(
                'id'        => 'always_display_logo',
                'type'      => 'switch',
                'required' => array('mobile_header','=', true),
                'title'     => esc_html__('Always Display Logo', 'sapa'),
                'subtitle'      => esc_html__('Logo displays on all pages (page title is disabled)', 'sapa'),
                'default'   => false
            ),
           
            array(
                'id'        => 'menu_mobile_search',
                'type'      => 'switch',
                'required'  => array('mobile_header','=', true),
                'title'     => esc_html__('Always Display Search', 'sapa'),
                'subtitle'  => esc_html__('Search displays on all pages', 'sapa'),
                'class' =>   'tbay-search-mb-all-page',
                'default'   => false
            ),
            
            array(
                'id'        => 'hidden_header_el_pro_mobile',
                'type'      => 'switch',
                'title'     => esc_html__('Hide Header Elementor Pro', 'sapa'),
                'subtitle'  => esc_html__('Hide Header Elementor Pro on mobile', 'sapa'),
                'default'   => true
            ),
        )
	)
);


// Mobile Footer settings
Redux::set_section(
	$opt_name,
	array(
        'subsection' => true,
        'title' => esc_html__('Footer', 'sapa'),
        'fields' => array(
            array(
                'id' => 'mobile_footer',
                'type' => 'switch',
                'title' => esc_html__('Enable Desktop Footer', 'sapa'),
                'default' => false
            ),
            array(
                'id' => 'mobile_back_to_top',
                'type' => 'switch',
                'title' => esc_html__('Enable "Back to Top" Button', 'sapa'),
                'default' => false
            ),
            array(
                'id' => 'mobile_footer_icon',
                'type' => 'switch',
                'title' => esc_html__('Enable Mobile Footer', 'sapa'),
                'default' => true
            ),
            array(
                'id'          => 'mobile_footer_slides',
                'type'        => 'slides',
                'title'       => esc_html__('Config List Menu Icon', 'sapa'),
                'subtitle' => esc_html__('Enter icon name of fonts: ', 'sapa') . '<a href="//fontawesome.com/icons?m=free/" target="_blank">Awesome</a> , <a href="//fonts.thembay.com/simple-line-icons//" target="_blank">Simple Line Icons</a>, <a href="//fonts.thembay.com/material-design-iconic/" target="_blank">Material Design Iconic</a></br></br><b>'. esc_html__('List default URLs:', 'sapa') . '</b></br></br><span class="des-label">'. esc_html__('Home page:', 'sapa') .'</span><b class="df-url">{{home}}</b></br><span class="des-label">'. esc_html__('Shop page:', 'sapa') .'</span><b class="df-url">{{shop}}</b></br><span class="des-label">'. esc_html__('My account page:', 'sapa') .'</span><b class="df-url">{{account}}</b></br><span class="des-label">'. esc_html__('Cart page:', 'sapa') .'</span><b class="df-url">{{cart}}</b></br><span class="des-label">'. esc_html__('Checkout page:', 'sapa') .'</span><b class="df-url">{{checkout}}</b></br><span class="des-label">'. esc_html__('Wishlist page:', 'sapa') .'</span><b class="df-url">{{wishlist}}</b></br></br>'. esc_html__('Watch video tutorial: ', 'sapa') . '<a href="//youtu.be/d7b6dIzV-YI/" target="_blank">here</a>',
                'class' =>   'tbay-redux-slides',
                'show' => array(
                    'title' => true,
                    'description' => true,
                    'url' => true,
                ),
                'content_title' => esc_html__('Menu', 'sapa'),
                'required' => array('mobile_footer_icon','=', true),
                'placeholder'   => array(
                    'title'      => esc_html__('Title', 'sapa'),
                    'description' => esc_html__('Enter icon name', 'sapa'),
                    'url'       => esc_html__('Link', 'sapa'),
                ),
            ),
        )
	)
);


// Mobile Search settings
Redux::set_section(
	$opt_name,
	array(
        'subsection' => true,
        'title' => esc_html__('Search', 'sapa'),
        'fields' => array(
            array(
                'id'=>'mobile_search_type',
                'type' => 'button_set',
                'title' => esc_html__('Search Result', 'sapa'),
                'options' => array(
                    'post' => esc_html__('Post', 'sapa'),
                    'product' => esc_html__('Product', 'sapa')
                ),
                'default' => 'product'
            ),
            array(
                'id' => 'mobile_autocomplete_search',
                'type' => 'switch',
                'title' => esc_html__('Auto-complete Search?', 'sapa'),
                'default' => 1
            ),
            array(
                'id'       => 'mobile_search_placeholder',
                'type'     => 'text',
                'title'    => esc_html__('Placeholder', 'sapa'),
                'default'  => esc_html__('Search in 20.000+ products...', 'sapa'),
            ),
            array(
                'id' => 'mobile_enable_search_category',
                'type' => 'switch',
                'title' => esc_html__('Enable Search in Categories', 'sapa'),
                'default' => true
            ),
            array(
                'id' => 'mobile_show_search_product_subtitle',
                'type' => 'switch',
                'title' => esc_html__('Show Subtitle of Search Result', 'sapa'),
                'required' => array(array('mobile_autocomplete_search', '=', '1'), array('mobile_search_type', '=', 'product')),
                'default' => false
            ), 
            array(
                'id' => 'mobile_show_search_product_image',
                'type' => 'switch',
                'title' => esc_html__('Show Image of Search Result', 'sapa'),
                'required' => array('mobile_autocomplete_search', '=', '1'),
                'default' => 1
            ),
            array(
                'id' => 'mobile_show_search_product_price',
                'type' => 'switch',
                'title' => esc_html__('Show Price of Search Result', 'sapa'),
                'required' => array(array('mobile_autocomplete_search', '=', '1'), array('mobile_search_type', '=', 'product')),
                'default' => true
            ),
            array(
                'id' => 'mobile_search_min_chars',
                'type'  => 'slider',
                'required' => array('mobile_autocomplete_search', '=', '1'),
                'title' => esc_html__('Search Minimum Characters', 'sapa'),
                'default' => 2,
                'min'   => 1,
                'step'  => 1,
                'max'   => 6,
            ),
            array(
                'id' => 'mobile_search_max_number_results',
                'type'  => 'slider',
                'required' => array('mobile_autocomplete_search', '=', '1'),
                'title' => esc_html__('Number of Search Results', 'sapa'),
                'desc'  => esc_html__('Max number of results show in Mobile', 'sapa'),
                'default' => 5,
                'min'   => 2,
                'step'  => 1,
                'max'   => 20,
            ),
        )
	)
);


// Mobile Menu settings
Redux::set_section(
	$opt_name,
	array(
        'subsection' => true,
        'title' => esc_html__('Menu Mobile', 'sapa'),
        'fields' => array(
            array(
                'id'       => 'menu_mobile_select',
                'type'     => 'select',
                'data'     => 'menus',
                'title'    => esc_html__('Main Menu Mobile', 'sapa'),
                'desc'     => esc_html__('Select the menu you want to display.', 'sapa'),
                'default' => 69
            ),
            array(
                'id' => 'enable_mmenu_langue',
                'type' => 'switch',
                'title' => esc_html__('Enable Custom Language', 'sapa'),
                'desc'  => esc_html__('If you use WPML will appear here', 'sapa'),
                'default' => true
            ),
            array(
                'id' => 'enable_mmenu_currency',
                'type' => 'switch',
                'title' => esc_html__('Enable Currency', 'sapa'),
                'default' => true
            ),
        )
	)
);


// Mobile WooCommerce settings
Redux::set_section(
	$opt_name,
	array(
        'subsection' => true,
        'title' => esc_html__('Mobile WooCommerce', 'sapa'),
        'fields' => array(
            array(
                'id' => 'mobile_product_number',
                'type' => 'image_select',
                'title' => esc_html__('Product Column in Shop page', 'sapa'),
                'options' => array(
                    'one' => array(
                        'title' => esc_html__('One Column', 'sapa'),
                        'img'   => SAPA_ASSETS_IMAGES . '/mobile/one_column.jpg'
                    ),
                    'two' => array(
                        'title' => esc_html__('Two Columns', 'sapa'),
                        'img'   => SAPA_ASSETS_IMAGES . '/mobile/two_columns.jpg'
                    ),
                ),
                'default' => 'two'
            ),
            array(
                'id' => 'enable_add_cart_mobile',
                'type' => 'switch',
                'title' => esc_html__('Show "Add to Cart" Button', 'sapa'),
                'subtitle' => esc_html__('On Home and page Shop', 'sapa'),
                'default' => false
            ),
            array(
                'id' => 'enable_wishlist_mobile',
                'type' => 'switch',
                'title' => esc_html__('Show "Wishlist" Button', 'sapa'),
                'subtitle' => esc_html__('Enable or disable in Home and Shop page', 'sapa'),
                'default' => false
            ),
            array(
                'id' => 'enable_one_name_mobile',
                'type' => 'switch',
                'title' => esc_html__('Show Full Product Name', 'sapa'),
                'subtitle' => esc_html__('Enable or disable in Home and Shop page', 'sapa'),
                'default' => false
            ),
            array(
                'id' => 'mobile_form_cart_style',
                'type' => 'select',
                'title' => esc_html__('Add To Cart Form Type', 'sapa'),
                'subtitle' => esc_html__('On Page Single Product', 'sapa'),
                'options' => array(
                    'default' => esc_html__('Default', 'sapa'),
                    'popup' => esc_html__('Popup', 'sapa')
                ),
                'default' => 'popup'
            ),
            array(
                'id' => 'enable_quantity_mobile',
                'type' => 'switch',
                'title' => esc_html__('Show Quantity', 'sapa'),
                'subtitle' => esc_html__('On Page Single Product', 'sapa'),
                'required' => array('mobile_form_cart_style','=', 'default'),
                'default' => false
            ),
            array(
                'id' => 'enable_tabs_mobile',
                'type' => 'switch',
                'title' => esc_html__('Show Sidebar Tabs', 'sapa'),
                'subtitle' => esc_html__('On Page Single Product', 'sapa'),
                'default' => true
            ),
        )
	)
);