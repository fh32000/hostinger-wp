<?php

if (! defined('ABSPATH') || function_exists('Sapa_Elementor_Product_Recently_Viewed_Main')) {
    exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;

class Sapa_Elementor_Product_Recently_Viewed_Main extends Sapa_Elementor_Carousel_Base
{
    public function get_name()
    {
        return 'tbay-product-recently-viewed-main';
    }

    public function get_title()
    {
        return esc_html__('Sapa Product Recently Viewed Main', 'sapa');
    }

    public function get_categories()
    {
        return [ 'sapa-elements', 'woocommerce-elements'];
    }

    public function get_icon()
    {
        return 'eicon-clock';
    }

    /**
     * Retrieve the list of scripts the image carousel widget depended on.
     *
     * Used to set scripts dependencies required to run the widget.
     *
     * @since 1.3.0
     * @access public
     *
     * @return array Widget scripts dependencies.
     */
    public function get_script_depends()
    {
        return ['slick', 'sapa-custom-slick'];
    }

    public function get_keywords()
    {
        return [ 'woocommerce-elements', 'product', 'products', 'Recently Viewed', 'Recently' ];
    }

    protected function register_controls()
    {
        $this->register_controls_heading();

        $this->start_controls_section(
            'general',
            [
                'label' => esc_html__('General', 'sapa'),
            ]
        );

        $this->add_control(
            'advanced',
            [
                'label' => esc_html__('Advanced', 'sapa'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'empty',
            [
                'label' => esc_html__('Empty Result - Custom Paragraph', 'sapa'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => esc_html__('You have no recently viewed item.', 'sapa'),
                'dynamic' => [
                    'active' => true,
                ]
            ]
        );
        
        $this->add_control(
            'align_empty',
            [
                'label' => esc_html__('Align Empty Result', 'sapa'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'left' => esc_html__('Left', 'sapa'),
                    'center' => esc_html__('Center', 'sapa'),
                    'right' => esc_html__('Right', 'sapa')
                ],
                'default' => 'center',
                'dynamic' => [
                    'active' => true,
                ],
                'selectors' => [
                    '{{WRAPPER}} .content-empty' => 'text-align: {{VALUE}};',
                ]
            ]
        );


        $this->register_control_main();

        $this->add_control(
            'enable_readmore',
            [
                'label' => esc_html__('Enable Button "Read More" ', 'sapa'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();
        $this->register_style_product_item();
        $this->add_control_responsive();

        $this->add_control_carousel(['layout_type' => 'carousel']);
        $this->register_control_viewall();
    }

    private function register_control_main()
    {
        $this->add_control(
            'limit',
            [
                'label' => esc_html__('Number of products', 'sapa'),
                'type' => Controls_Manager::NUMBER,
                'description' => esc_html__('Number of products to show ( -1 = all )', 'sapa'),
                'default' => 8,
                'min'  => -1,
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

        $this->add_control(
            'product_style',
            [
                'label' => esc_html__('Product Style', 'sapa'),
                'type' => Controls_Manager::SELECT,
                'default' => 'inner',
                'options' => $this->get_template_product(),
                'prefix_class' => 'elementor-product-',
            ]
        );
    }
 
    private function register_control_viewall()
    {
        $this->start_controls_section(
            'section_readmore',
            [
                'label' => esc_html__('Read More Options', 'sapa'),
                'type'  => Controls_Manager::SECTION,
                'condition' => [
                    'enable_readmore' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'readmore_text',
            [
                'label' => esc_html__('Button "Read More" Custom Text', 'sapa'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Read More', 'sapa'),
                'label_block' => true,
            ]
        );

        $pages = $this->get_available_pages();

        if (!empty($pages)) {
            $this->add_control(
                'readmore_page',
                [
                    'label'        => esc_html__('Page', 'sapa'),
                    'type'         => Controls_Manager::SELECT2,
                    'options'      => $pages,
                    'default'      => array_keys($pages)[0],
                    'save_default' => true,
                    'label_block' => true,
                    'separator'    => 'after',
                ]
            );
        } else {
            $this->add_control(
                'readmore_page',
                [
                    'type'            => Controls_Manager::RAW_HTML,
                    'raw'             => sprintf(__('<strong>There are no pages in your site.</strong><br>Go to the <a href="%s" target="_blank">pages screen</a> to create one.', 'sapa'), admin_url('edit.php?post_type=page')),
                    'separator'       => 'after',
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
                ]
            );
        }
        $this->end_controls_section();
    }

    private function render_empty()
    {
        $settings = $this->get_settings_for_display();
        echo '<div class="content-empty">'. trim($settings['empty']) .'</div>';
    }

    private function render_btn_readmore($count)
    {
        $settings = $this->get_settings_for_display();
        extract($settings);
        $products_list              =  sapa_tbay_wc_track_user_get_cookie();
        $all                        =  count($products_list);

        if (!empty($readmore_page)) {
            $link = get_permalink($readmore_page);
        }

        if ($enable_readmore && ($all > $count) && !empty($link)) : ?>
            <div class="btn-readmore-wrapper"><a class="btn-readmore" href="<?php echo esc_url($link); ?>" title="<?php esc_attr($readmore_text); ?>"><span><?php echo trim($readmore_text); ?></span></a></div>
        <?php endif;
    }

    public function render_content_main()
    {
        $settings = $this->get_settings_for_display();
        extract($settings);

        $args   = sapa_tbay_get_products_recently_viewed($limit);

        $args   =  apply_filters('sapa_list_recently_viewed_products_args', $args);
        $loop   = new WP_Query($args);

        if (!$loop->have_posts()) {
            $this->render_empty();
        }
        
        $this->add_render_attribute('row', 'class', ['products']);

        $attr_row = $this->get_render_attribute_string('row');

        wc_get_template('layout-products/layout-products.php', array( 'loop' => $loop, 'product_style' => $product_style, 'attr_row' => $attr_row));

        $this->render_btn_readmore($limit);
    }
}
$widgets_manager->register(new Sapa_Elementor_Product_Recently_Viewed_Main());
