<?php
/**
 * Plugin Name: WP Playlist Manager
 * Description: Image playlist slideshows for Raspberry Pi display screens.
 * Version: 1.0.6
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'PPM_VERSION', '1.0.6' );
define( 'PPM_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPM_URL', plugin_dir_url( __FILE__ ) );

require_once PPM_DIR . 'includes/class-db.php';
require_once PPM_DIR . 'includes/class-cpt.php';
require_once PPM_DIR . 'includes/class-admin.php';
require_once PPM_DIR . 'includes/class-frontend.php';
require_once PPM_DIR . 'includes/class-rest.php';

register_activation_hook( __FILE__, function () {
    PPM_DB::create_table();
    PPM_CPT::register();
    flush_rewrite_rules();
} );

add_action( 'init', [ 'PPM_CPT', 'register' ] );
add_action( 'add_meta_boxes', [ 'PPM_Admin', 'add_meta_box' ] );
add_action( 'save_post_playlist', [ 'PPM_Admin', 'save' ], 10, 2 );
add_action( 'admin_enqueue_scripts', [ 'PPM_Admin', 'enqueue' ] );
add_action( 'template_include', [ 'PPM_Frontend', 'override_template' ] );
add_action( 'rest_api_init', [ 'PPM_Rest', 'register_routes' ] );
add_action( 'wp_ajax_ppm_force_reload', [ 'PPM_Admin', 'ajax_force_reload' ] );
