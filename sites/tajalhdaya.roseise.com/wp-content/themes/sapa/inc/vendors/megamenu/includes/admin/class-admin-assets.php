<?php

defined( 'ABSPATH' ) || exit();

/**
 * Sapa_Megamenu_Walker
 *
 * extends Walker_Nav_Menu
 */
class Sapa_Admin_Megamenu_Assets {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'add_scripts_editor' ) );
	}

	public static function add_scripts_editor() {
		if ( isset( $_REQUEST['sapa-menu-editable'] ) && $_REQUEST['sapa-menu-editable'] ) {
			wp_register_script( 'sapa-elementor-menu', get_template_directory_uri() . '/inc/vendors/megamenu/assets/js/editor.js', [], SAPA_THEME_VERSION );
			wp_enqueue_script( 'sapa-elementor-menu' );
		}
	}

	/**
	 * enqueue scripts
	 */
	public static function enqueue_scripts( $page ) {
		if ( $page === 'nav-menus.php' ) {
			wp_enqueue_script( 'backbone' );
			wp_enqueue_script( 'underscore' );

			$suffix = '.min';
			wp_register_script(
				'jquery-elementor-select2',
				ELEMENTOR_ASSETS_URL . 'lib/e-select2/js/e-select2.full' . $suffix . '.js',
				[
					'jquery',
				],
				'4.0.6-rc.1',
				true
			);
			wp_enqueue_script( 'jquery-elementor-select2' );
			wp_register_style(
				'elementor-select2',
				ELEMENTOR_ASSETS_URL . 'lib/e-select2/css/e-select2' . $suffix . '.css',
				[],
				'4.0.6-rc.1'
			);
			wp_enqueue_style( 'elementor-select2' );
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_register_script( 'sapa-megamenu', get_template_directory_uri() . '/inc/vendors/megamenu/assets/js/admin.js', array(
				'jquery',
				'backbone',
				'underscore'
			), SAPA_THEME_VERSION, true );
			wp_localize_script( 'sapa-megamenu', 'sapa_memgamnu_params', apply_filters( 'sapa_admin_megamenu_localize_scripts', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'close' => esc_html__( 'Close', 'sapa' ),
					'submit' => esc_html__( 'Save', 'sapa' )
				),
				'nonces'  => array(
					'load_menu_data' => wp_create_nonce( 'sapa-menu-data-nonce' )
				)
			) ) );
			wp_enqueue_script( 'sapa-megamenu' );

			wp_enqueue_style( 'sapa-megamenu', get_template_directory_uri() . '/inc/vendors/megamenu/assets/css/admin.css', array(), SAPA_THEME_VERSION );
			wp_enqueue_style('sapa-font-tbay-custom', SAPA_STYLES . '/font-tbay-custom.css', array(), SAPA_THEME_VERSION);
		}

	}

}

Sapa_Admin_Megamenu_Assets::init();
