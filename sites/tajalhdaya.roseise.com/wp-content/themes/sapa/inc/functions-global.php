<?php

/**
 * Set the content width based on the theme's design and stylesheet.
 *
 * @since Sapa 1.0
 */
define('SAPA_THEME_VERSION', '1.0');

/**
 * ------------------------------------------------------------------------------------------------
 * Define constants.
 * ------------------------------------------------------------------------------------------------
 */
define('SAPA_THEME_DIR', get_template_directory_uri());
define('SAPA_THEMEROOT', get_template_directory());
define('SAPA_IMAGES', SAPA_THEME_DIR . '/images');
define('SAPA_SCRIPTS', SAPA_THEME_DIR . '/js');

define('SAPA_STYLES', SAPA_THEME_DIR . '/css');

define('SAPA_INC', 'inc');
define('SAPA_MERLIN', SAPA_INC . '/merlin');
define('SAPA_CLASSES', SAPA_INC . '/classes');
define('SAPA_VENDORS', SAPA_INC . '/vendors');
define('SAPA_CONFIG', SAPA_VENDORS . '/redux-framework/config');
define('SAPA_WOOCOMMERCE', SAPA_VENDORS . '/woocommerce');
define('SAPA_ELEMENTOR', SAPA_THEMEROOT . '/inc/vendors/elementor');
define('SAPA_ELEMENTOR_TEMPLATES', SAPA_THEMEROOT . '/elementor_templates');
define('SAPA_PAGE_TEMPLATES', SAPA_THEMEROOT . '/page-templates');
define('SAPA_WIDGETS', SAPA_INC . '/widgets');

define('SAPA_ASSETS', SAPA_THEME_DIR . '/inc/assets');
define('SAPA_ASSETS_IMAGES', SAPA_ASSETS    . '/images');

define('SAPA_MIN_JS', '');

define('TBAY_DISCOUNT_CAMPAIGN', true);

if (! isset($content_width)) {
    $content_width = 660;
}

function sapa_tbay_get_config($name, $default = '')
{
    global $sapa_options;
    if (isset($sapa_options[$name])) {
        return $sapa_options[$name];
    }
    return $default;
}

function sapa_tbay_get_global_config($name, $default = '')
{
    $options = get_option('sapa_tbay_theme_options', array());
    if (isset($options[$name])) {
        return $options[$name];
    }
    return $default;
}
