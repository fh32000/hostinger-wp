<?php

if (! defined('ABSPATH') || function_exists('Sapa_Elementor_Gallery')) {
    exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Elementor tabs widget.
 *
 * Elementor widget that displays vertical or horizontal tabs with different
 * pieces of content.
 *
 * @since 1.0.0
 */
class Sapa_Elementor_Gallery extends Sapa_Elementor_Carousel_Base
{
    /**
     * Get widget name.
     *
     * Retrieve tabs widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name()
    {
        return 'tbay-custom-gallery';
    }

    /**
     * Get widget title.
     *
     * Retrieve tabs widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title()
    {
        return esc_html__('Sapa Gallery', 'sapa');
    }

    public function get_script_depends()
    {
        return [ 'slick', 'sapa-custom-slick', 'photoswipe', 'photoswipe-ui-default' ];
    } 

    public function get_style_depends() {
        return ['photoswipe', 'photoswipe-default-skin'];
    }
 
    /**
     * Get widget icon.
     *
     * Retrieve tabs widget icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon()
    {
        return 'eicon-gallery-justified';
    }

    /**
     * Register tabs widget controls.
     *
     * Adds different input fields to allow the user to change and customize the widget settings.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function register_controls()
    {
        $this->register_controls_heading();

        $this->start_controls_section(
            'section_general',
            [
                'label' => esc_html__('General', 'sapa'),
            ]
        );

        $this->add_control(
            'layout_type',
            [
                'label'     => esc_html__('Layout Type', 'sapa'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'grid',
                'options'   => [
                    'grid'      => esc_html__('Grid', 'sapa'),
                    'carousel'  => esc_html__('Carousel', 'sapa'),
                ],
            ]
        );

        $repeater = $this->register_list_gallery_repeater();

        $this->add_control(
            'list_gallery',
            [
                'label' => esc_html__('Gallery', 'sapa'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'gallery_field' => '{{{ gallery }}}',
                
            ]
        );

        $this->end_controls_section();

        $this->style_gallery();
        $this->add_control_responsive();
        $this->add_control_carousel(['layout_type' => 'carousel']);
    }

    
    protected function style_gallery()
    {
        $this->start_controls_section(
            'section_style_gallery',
            [
                'label' => esc_html__('Style', 'sapa'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'gallery_effects',
            [
                'label' => esc_html__('TB Effects', 'sapa'),
                'type' => Controls_Manager::SELECT,
                'label_block' => true,
                'prefix_class' => 'tb-effect effect-',
                'default' => 'no',
                'options' => sapa_list_controls_effects(),
            ]
        );
                
        $this->add_control(
            'heading_wrapper_style',
            [
                'label' => esc_html__('Wrapper Gallery', 'sapa'),
                'type' => Controls_Manager::HEADING,
                'separator'   => 'before',
            ]
        );

        $this->add_responsive_control(
            'wrapper_margin_style',
            [
                'label' => esc_html__('Margin', 'sapa'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .tbay-element-custom-gallery .custom-gallery' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'wrapper_padding_style',
            [
                'label' => esc_html__('Padding', 'sapa'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .tbay-element-custom-gallery .custom-gallery' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'heading_item_style',
            [
                'label' => esc_html__('Item', 'sapa'),
                'type' => Controls_Manager::HEADING,
                'separator'   => 'before',
            ]
        );

        $this->add_responsive_control(
            'item_margin_style',
            [
                'label' => esc_html__('Margin', 'sapa'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .tbay-element-custom-gallery .gallery-item' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'item_padding_style',
            [
                'label' => esc_html__('Padding', 'sapa'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .tbay-element-custom-gallery .gallery-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'heading_image_style',
            [
                'label' => esc_html__('Image', 'sapa'),
                'type' => Controls_Manager::HEADING,
                'separator'   => 'before',
            ]
        );

        $this->add_responsive_control(
            'image_margin_style',
            [
                'label' => esc_html__('Margin', 'sapa'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .tbay-element-custom-gallery img' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_padding_style',
            [
                'label' => esc_html__('Padding', 'sapa'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .tbay-element-custom-gallery img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'heading_text_style',
            [
                'label' => esc_html__('Text on photo', 'sapa'),
                'type' => Controls_Manager::HEADING,
                'separator'   => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'post_title_typography',
                'selector' => '{{WRAPPER}} .tbay-element-custom-gallery .gallery-link::after',
            ]
        );

        
        $this->add_control(
            'text_photo_color',
            [
                'label'     => esc_html__('Color', 'sapa'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tbay-element-custom-gallery .gallery-link::after' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'text_photo_align',
            [
                'label' => esc_html__('Align', 'sapa'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'sapa'),
                        'icon' => 'eicon-text-align-left'
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'sapa'),
                        'icon' => 'eicon-text-align-center'
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'sapa'),
                        'icon' => 'eicon-text-align-right'
                    ],
                ],
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .tbay-element-custom-gallery .gallery-link::after'  => 'text-align: {{VALUE}}',
                ]
            ]
        );

        $this->end_controls_section();
    }

    private function register_list_gallery_repeater()
    {
        $repeater = new \Elementor\Repeater();
        $repeater->add_control(
            'gallery',
            [
                'label' => esc_html__('Choose Image', 'sapa'),
                'type' => Controls_Manager::MEDIA,
            ]
        );
        $repeater->add_control(
            'text_gallery',
            [
                'label' => esc_html__('Text on photo', 'sapa'),
                'type' => Controls_Manager::TEXT,
            ]
        );

        return $repeater;
    }

}
$widgets_manager->register(new Sapa_Elementor_Gallery());