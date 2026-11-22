<?php
/**
 * Redux Framework checkbox config.
 * For full documentation, please visit: http://devs.redux.io/
 *
 * @package Redux Framework
 */

defined( 'ABSPATH' ) || exit;

/** General Settings **/
Redux::set_section(
	$opt_name,
	array(
        'icon' => 'zmdi zmdi-settings',
        'title' => esc_html__('General', 'sapa'),
        'fields' => array(
            array(
                'id'        => 'active_theme',
                'type'      => 'image_select', 
                'compiler'  => true,
                'class'     => 'image-large active_skins',
                'title'     => esc_html__('Activated Skin', 'sapa'),
                'options'   => sapa_tbay_get_themes(),
                'default'   => 'smart-watch' 
            ), 
            array(
                'id'            => 'config_media',
                'type'          => 'switch',
                'title'         => esc_html__('Enable Config Image Size', 'sapa'),
                'subtitle'      => esc_html__('Config image size in WooCommerce and Media Setting', 'sapa'),
                'default'       => false
            ),
            array(
                'id' => 'ajax_dropdown_megamenu',
                'type' => 'switch',
                'title' => esc_html__('Enable "Ajax Dropdown" Mega Menu', 'sapa'),
                'default' => false,
            ),
            array(
                'id' => 'dark_mode',
                'type' => 'switch',
                'title' => esc_html__('Enable Dark Mode', 'sapa'),
                'default' => false,
            ),
        )
	)
);