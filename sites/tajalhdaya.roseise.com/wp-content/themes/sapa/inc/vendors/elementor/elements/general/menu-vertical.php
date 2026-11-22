<?php

if (! defined('ABSPATH') || function_exists('Sapa_Elementor_Menu_Vertical')) {
    exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class Sapa_Elementor_Menu_Vertical extends Sapa_Elementor_Widget_Base
{
    public function get_name()
    {
        return 'tbay-menu-vertical';
    }

    public function get_title()
    {
        return esc_html__('Sapa Menu Vertical', 'sapa');
    }

    public function get_icon()
    {
        return 'eicon-nav-menu';
    }

    public function on_export($element)
    {
        unset($element['settings']['menu']);

        return $element;
    }

    protected function register_controls()
    {
        $this->register_controls_heading();
        $this->start_controls_section(
            'section_layout',
            [
                'label' => esc_html__('General', 'sapa'),
            ]
        );
      
        $menus = $this->get_available_menus();

        if (!empty($menus)) {
            $this->add_control(
                'menu',
                [
                    'label'        => esc_html__('Menu', 'sapa'),
                    'type'         => Controls_Manager::SELECT,
                    'options'      => $menus,
                    'default'      => array_keys($menus)[0],
                    'save_default' => true,
                    'separator'    => 'after',
                    'description'  => esc_html__('Note does not apply to Mega Menu.', 'sapa'),
                ]
            );
        } else {
            $this->add_control(
                'menu',
                [
                    'type'            => Controls_Manager::RAW_HTML,
                    'raw'             => sprintf(__('<strong>There are no menus in your site.</strong><br>Go to the <a href="%s" target="_blank">Menus screen</a> to create one.', 'sapa'), admin_url('nav-menus.php?action=edit&menu=0')),
                    'separator'       => 'after',
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
                ]
            );
        }
        $this->end_controls_section();
        $this->style_menu_vertical();
    }
    protected function style_menu_vertical()
    {
        $this->start_controls_section(
            'section_style_menu_vertical',
            [
                'label' => esc_html__('Style Menu Vertical', 'sapa'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'menu_vertical_align',
            [
                'label' => esc_html__('Align', 'sapa'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'sapa'),
                        'icon' => 'fa fa-align-left'
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'sapa'),
                        'icon' => 'fa fa-align-center'
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'sapa'),
                        'icon' => 'fa fa-align-right'
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .menu-vertical > li' => 'text-align: {{VALUE}} !important',
                ]
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'menu_vertical_typography',
                'selector' => '{{WRAPPER}} .menu-vertical > li > a',
            ]
        );

        $this->add_responsive_control(
            'padding_menu_vertical',
            [
                'label'     => esc_html__('Padding', 'sapa'),
                'type'      => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .menu-vertical > li'        => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'margin_menu_vertical',
            [
                'label'     => esc_html__('Margin', 'sapa'),
                'type'      => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .menu-vertical > li'        => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->start_controls_tabs('menu_vertical_tabs');

        $this->start_controls_tab(
            'menu_vertical_tab_normal',
            [
                'label' => esc_html__('Normal', 'sapa'),
            ]
        );

        $this->add_control(
            'menu_vertical_color',
            [
                'label' => esc_html__('Color', 'sapa'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .menu-vertical > li > a' => 'color: {{VALUE}};',
                ],
            ]
        );


        $this->end_controls_tab();

        $this->start_controls_tab(
            'menu_vertical_tab_hover',
            [
                'label' => esc_html__('Hover', 'sapa'),
            ]
        );

        $this->add_control(
            'menu_vertical_color_hover',
            [
                'label' => esc_html__('Hover Color', 'sapa'),
                'type' => Controls_Manager::COLOR,
                'default' => '', 
                'selectors' => [
                    '{{WRAPPER}} .menu-vertical > li.active > a' => 'color: {{VALUE}} !important;',
                    '{{WRAPPER}} .menu-vertical > li > a:hover' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }
}
$widgets_manager->register(new Sapa_Elementor_Menu_Vertical());
