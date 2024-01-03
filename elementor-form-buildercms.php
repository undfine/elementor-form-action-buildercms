<?php
/**
 * Plugin Name: Elementor Form BuilderCMS Action
 * Plugin URI:
 * Description: An integration to add BuilderCMS action to Elementor Pro Forms
 * Author: Compass Marketing
 * Version: 2.0.1
 * Text Domain: compassad
 * Author URI: https://compassad.com
*/



function admin_notice_missing_main_plugin() {

	if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

	$message = sprintf(
		/* translators: 1: Plugin Name 2: Elementor */
		esc_html__( '"%1$s" requires "%2$s" to be installed and activated.', 'text-domain' ),
		'<strong>' . esc_html__( 'Elementor Form BuilderCMS Action', 'compassad' ) . '</strong>',
		'<strong>' . esc_html__( 'Elementor Pro', 'compassad' ) . '</strong>'
	);

	printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
}


// Check if Elementor Pro is installed and init, otherwise,
function check_for_elementor_pro(){

  if ( in_array( 'elementor-pro/elementor-pro.php', get_option( 'active_plugins' ) ) ) {
      require_once('builder-cms-action.php');
  } else {
    add_action( 'admin_notices', 'admin_notice_missing_main_plugin' );
  }
}

add_action( 'elementor/init', 'check_for_elementor_pro' );
