<?php

if (! defined('ABSPATH') || function_exists('Sapa_Elementor_Currency')) {
    exit; // Exit if accessed directly.
}


use Elementor\Controls_Manager;

class Sapa_Elementor_Currency extends Sapa_Elementor_Widget_Base
{
    public function get_name()
    {
        return 'tbay-currency';
    }

    public function get_title()
    {
        return esc_html__('Sapa Currency', 'sapa');
    }

    public function get_icon()
    {
        return 'eicon-database';
    }

    protected function get_html_wrapper_class()
    {
        return 'w-auto elementor-widget-' . $this->get_name();
    }

    public function get_script_depends()
    {
        return ['jquery-sumoselect'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_layout',
            [
                'label' => esc_html__('Currency Settings', 'sapa'),
            ]
        );

        $this->add_control(
            'txt_type',
            [
                'label'              => esc_html__('Choose Type Text', 'sapa'),
                'type'               => Controls_Manager::SELECT,
                'options' => [
                    'desc' => esc_html__('Desc', 'sapa'),
                    'code' => esc_html__('Code', 'sapa')
                ],
                'default' => 'desc'
            ]
        );
        $this->add_control(
            'show_flags',
            [
                'label'              => esc_html__('Show Flags', 'sapa'),
                'type'               => Controls_Manager::SWITCHER,
                'default' => 'no'
            ]
        );
        $this->add_control(
            'position_flags',
            [
                'label'              => esc_html__('Position Flags', 'sapa'),
                'type'               => Controls_Manager::SELECT,
                'options' => [
                    'left'  => esc_html__('Left', 'sapa'),
                    'right'  => esc_html__('Right', 'sapa')
                ],
                'default' => 'left',
                'condition' => [
                    'show_flags' => 'yes'
                ]
            ]
        );
    
        $this->add_control(
            'position_sub_menu',
            [
                'label'     => esc_html__('Position Sub Menu', 'sapa'),
                'type'      => Controls_Manager::SELECT,
                'options' => [
                    'top' => esc_html__('Top', 'sapa'),
                    'bottom' => esc_html__('Bottom', 'sapa'),
                ],
                'default' => 'top',
                'prefix_class' => 'sub-menu-',
                
            ]
        );
    
        $this->end_controls_section();
        $this->style_currency_section();
    }

    protected function style_currency_section() {
        $this->start_controls_section(
            'section_style_currency',
            [
                'label' => esc_html__('Style Currency', 'sapa'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'currency_typography',
                'selector' => '{{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect>.CaptionCont>span,
                {{WRAPPER}} .woocommerce-currency-switcher-form select',  
            ]
        );

        $this->start_controls_tabs('currency_tabs');

        $this->start_controls_tab(
            'currency_tab_normal',
            [
                'label' => esc_html__('Normal', 'sapa'),
            ]
        );

        $this->add_control(
            'currency_color',
            [
                'label' => esc_html__('Color', 'sapa'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect > .CaptionCont,
                    {{WRAPPER}} .woocommerce-currency-switcher'=> 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'currency_bg',
            [
                'label' => esc_html__('Background', 'sapa'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect,
                    {{WRAPPER}} .woocommerce-currency-switcher' => 'background: {{VALUE}};',
                ],
            ]
        );


        $this->end_controls_tab();

        $this->start_controls_tab(
            'currency_tab_hover',
            [
                'label' => esc_html__('Hover', 'sapa'),
            ]
        );

        $this->add_control(
            'currency_color_hover',
            [
                'label' => esc_html__('Hover Color', 'sapa'),
                'type' => Controls_Manager::COLOR,
                'default' => '', 
                'selectors' => [
                    '{{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect > .CaptionCont:hover,
                    {{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect:hover > .CaptionCont,
                    {{WRAPPER}} .woocommerce-currency-switcher:hover,
                    {{WRAPPER}} .SumoSelect > .optWrapper > .options li.opt.selected,
                    {{WRAPPER}} .SumoSelect > .optWrapper > .options li.opt:hover,
                    {{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect > .CaptionCont:hover label i:after,
                    {{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect:hover label i:after,
                    {{WRAPPER}} .SumoSelect > .optWrapper > .options li.opt:focus'    => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'currency_bg_hover',
            [
                'label' => esc_html__('Background', 'sapa'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect:hover,
                    {{WRAPPER}} .woocommerce-currency-switcher:hover' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'currency_padding',
            [
                'label'     => esc_html__('Padding', 'sapa'),
                'type'      => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect,
                    {{WRAPPER}} .woocommerce-currency-switcher'        => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'currency_margin',
            [
                'label'     => esc_html__('Margin', 'sapa'),
                'type'      => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .woocommerce-currency-switcher-form .SumoSelect,
                    {{WRAPPER}} .woocommerce-currency-switcher'        => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }
    
    protected function sapa_currency()
    {
        $settings = $this->get_settings_for_display();
        extract($settings);

        if ($show_flags === 'yes') {
            $check_flags = 1;
        } else {
            $check_flags = 0;
        }
        $this->add_render_attribute(
            'woocs',
            [
                'show_flags'    => $check_flags,
                'txt_type'      => $txt_type ,
                'flag_position' => $position_flags
            ]
        );

        $woocs = $this->get_render_attribute_string('woocs');

        if (sapa_woocommerce_activated() && class_exists('WOOCS')) {
            wp_enqueue_style('sumoselect'); ?>
            <div class="tbay-currency">
            <?php
                echo do_shortcode("[woocs $woocs ]"); ?>
            </div>
            <?php
        }
    }
}
$widgets_manager->register(new Sapa_Elementor_Currency());
