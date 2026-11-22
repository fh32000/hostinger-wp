<?php if (! defined('SAPA_THEME_DIR')) {
    exit('No direct script access allowed');
}
/**
 * Sapa woocommerce Template Hooks
 *
 * Action/filter hooks used for Sapa woocommerce functions/templates.
 *
 */


/**
 * Sapa Header Mobile Content.
 *
 * @see sapa_the_button_mobile_menu()
 * @see sapa_the_logo_mobile()
 */
add_action('sapa_header_mobile_content', 'sapa_the_button_mobile_menu', 5);
add_action('sapa_header_mobile_content', 'sapa_the_icon_home_page_mobile', 10);
add_action('sapa_header_mobile_content', 'sapa_the_logo_mobile', 15);
add_action('sapa_header_mobile_content', 'sapa_the_icon_mini_cart_header_mobile', 20);


/**
 * Sapa Header Mobile before content
 *
 * @see sapa_the_hook_header_mobile_all_page
 */
add_action('sapa_before_header_mobile', 'sapa_the_hook_header_mobile_all_page', 5);

/**Page Cart**/
remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
