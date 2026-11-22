<?php
/**
 * Templates Name: Elementor
 * Widget: Text Box
 */
extract($settings);

$this->add_render_attribute('row', 'class', 'elementor-text-box-wrapper');
$this->settings_layout();
?>

<div <?php echo $this->get_render_attribute_string('wrapper'); ?>>
    <div <?php echo $this->get_render_attribute_string('row'); ?>>			

        <?php 
            if (!empty($text_heading) || !empty($text_heading)) {
                echo '<div class="elementor-text-box-heading">'. esc_html($text_heading) .'</div>';
            }
        ?>
        
        <div class="elementor-text-box-content">

            <?php 
                if (!empty($text_title) || !empty($text_title)) {
                    echo '<div class="elementor-text-box-title">'. esc_html($text_title) .'</div>';
                }

                if (!empty($text_subtitle) || !empty($text_subtitle)) {
                    echo '<div class="elementor-text-box-subtitle">'. esc_html($text_subtitle) .'</div>';
                }
            ?>
        </div>
    </div>
</div>