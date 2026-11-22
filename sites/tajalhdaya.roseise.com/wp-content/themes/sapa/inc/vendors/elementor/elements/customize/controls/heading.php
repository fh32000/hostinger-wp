<?php
if (!function_exists('sapa_heading_section_title_style')) {
    function sapa_heading_section_title_style($widget)
    {
        $widget->add_control(
            'enable_title_background_text',
            [
                'label'   => esc_html__('Enable Background Text', 'sapa'),
                'type'    => \Elementor\Controls_Manager::SWITCHER,
                'prefix_class' => 'show-title-bg-text-',
				'description' => esc_html__( 'When you enable this option, the text color will not receive it, it will change to the background text below', 'sapa' ),
                'default' => '',
            ]
        );

		$widget->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'text_box_title_background',
                'condition' => [
                    'enable_title_background_text' => 'yes'
                ],
                'selector' => '{{WRAPPER}} .elementor-heading-title',
            ]
        );
    } 

    add_action('elementor/element/heading/section_title_style/before_section_end', 'sapa_heading_section_title_style', 10, 2);
}