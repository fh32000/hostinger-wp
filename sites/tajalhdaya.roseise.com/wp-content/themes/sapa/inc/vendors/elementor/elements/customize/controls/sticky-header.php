<?php
if (!function_exists('sapa_section_sticky_header')) {
    function sapa_section_sticky_header($widget)
    {
        if (get_post_type() !== 'tbay_custom_post') {
            return;
        }

        global $post;

        $block_type = get_post_meta($post->ID, 'tbay_block_type', true);

        if( $block_type !== 'type_header' ) {
            return;
        }


        $widget->start_controls_section(
            'sticky_header',
            array(
                'label' => esc_html__('Sticky Header', 'sapa'),
                'tab' => \Elementor\Controls_Manager::TAB_LAYOUT,
            )
        );

        $widget->add_control(
            'enable_sticky_headers',
            array(
                'label'                 =>  esc_html__('Enable Sticky Headers', 'sapa'),
                'type'                  => \Elementor\Controls_Manager::SWITCHER,
                'default'               => '',
                'return_value'          => 'yes',
            )
        );

        $widget->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array( 
                'name'        => 'sticky_headers_border', 
                'selector' => '{{WRAPPER}}.sticky',
                'condition' => [
                    'enable_sticky_headers' => 'yes'
                ],
                'separator'   => 'before',
            )
        );

        $widget->add_control(
            'sticky_headers_bg',
            [
                'label'     => esc_html__('Background Color', 'sapa'),
                'description' => esc_html__( 'Background color when the sticky section', 'sapa' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'enable_sticky_headers' => 'yes'
                ],
                'separator'   => 'before',
                'selectors' => [
                    '{{WRAPPER}}.sticky' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $widget->end_controls_section();
    }

    add_action('elementor/element/section/section_layout/before_section_start', 'sapa_section_sticky_header', 10, 2);
}
