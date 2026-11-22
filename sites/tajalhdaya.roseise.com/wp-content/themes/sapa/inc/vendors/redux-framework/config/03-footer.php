<?php
/**
 * Redux Framework checkbox config.
 * For full documentation, please visit: http://devs.redux.io/
 *
 * @package Redux Framework
 */

defined( 'ABSPATH' ) || exit;

/** Footer Settings **/
Redux::set_section(
	$opt_name,
	array(
        'icon' => 'zmdi zmdi-border-bottom',
        'title' => esc_html__('Footer', 'sapa'),
        'fields' => array(
            array(
                'id' => 'footer_type',
                'type' => 'select',
                'title' => esc_html__('Select Footer Layout', 'sapa'),
                'options' => sapa_tbay_get_footer_layouts(),
                'default' => 'footer_default'
            ),
            array(
                'id' => 'copyright_text',
                'type' => 'editor',
                'title' => esc_html__('Copyright Text', 'sapa'),
                'default' => esc_html__('Copyright  &#64; 2023 Sapa Designed by ThemBay. All Rights Reserved.', 'sapa'),
                'required' => array('footer_type','=','footer_default')
            ),
            array(
                'id' => 'back_to_top',
                'type' => 'switch',
                'title' => esc_html__('Enable "Back to Top" Button', 'sapa'),
                'default' => true,
            ),
        )
	)
);