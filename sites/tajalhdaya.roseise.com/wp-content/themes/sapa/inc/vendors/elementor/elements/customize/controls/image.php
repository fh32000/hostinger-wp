<?php
if (!function_exists('sapa_customize_image_section')) {
    function sapa_customize_image_section($widget)
    {
		$widget->add_control(
            'gallery_effects',
            [
                'label' => esc_html__('TB Effects', 'sapa'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'label_block' => true,
                'default' => 'no',
				'prefix_class' => 'tb-effect effect-',
                'options' => sapa_list_controls_effects(),
            ]
        );
    } 
    add_action('elementor/element/image/section_style_image/after_section_start', 'sapa_customize_image_section', 10, 2);
}