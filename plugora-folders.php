<?php
/**
 * Plugin Name: Plugora Folders for Pages
 * Description: A featherweight folder system for the WordPress Pages screen. Free + Premium in one plugin — paste a license key to unlock premium features instantly.
 * Version:     2.4.1
 * Author:      Plugora
 * License:     GPL-2.0-or-later
 * Text Domain: plugora-folders-for-pages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PLUGORA_FOLDERS_VERSION', '2.4.1' );
define( 'PLUGORA_FOLDERS_FILE', __FILE__ );
define( 'PLUGORA_FOLDERS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLUGORA_FOLDERS_URL', plugin_dir_url( __FILE__ ) );
define( 'PLUGORA_FOLDERS_API', 'https://kmsqtusutpknswtdzclw.supabase.co/functions/v1/folders-license-validate' );
define( 'PLUGORA_FOLDERS_BUY_URL', 'https://plugora.dev/buy/folders-pages' );

require_once PLUGORA_FOLDERS_DIR . 'includes/class-data.php';
require_once PLUGORA_FOLDERS_DIR . 'includes/class-installer.php';
require_once PLUGORA_FOLDERS_DIR . 'includes/class-license.php';
require_once PLUGORA_FOLDERS_DIR . 'includes/class-settings.php';
require_once PLUGORA_FOLDERS_DIR . 'includes/class-rest.php';
require_once PLUGORA_FOLDERS_DIR . 'includes/class-admin.php';
require_once PLUGORA_FOLDERS_DIR . 'includes/class-pages-screen.php';

if ( ! function_exists( 'plugora_folders_is_premium' ) ) {
	function plugora_folders_is_premium() {
		return class_exists( 'Plugora_Folders_License' ) && Plugora_Folders_License::is_active();
	}
}

register_activation_hook( __FILE__, [ 'Plugora_Folders_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Plugora_Folders_Installer', 'deactivate' ] );

add_action( 'plugins_loaded',         [ 'Plugora_Folders_Installer', 'maybe_upgrade' ] );
add_action( 'rest_api_init',          [ 'Plugora_Folders_REST', 'register_routes' ] );
add_action( 'admin_menu',             [ 'Plugora_Folders_Admin', 'register_menu' ] );
add_action( 'admin_menu',             [ 'Plugora_Folders_Settings', 'register_menu' ] );
add_action( 'admin_init',             [ 'Plugora_Folders_Settings', 'register_settings' ] );
add_action( 'admin_enqueue_scripts',  [ 'Plugora_Folders_Admin', 'enqueue' ] );
add_action( 'admin_enqueue_scripts',  [ 'Plugora_Folders_Pages_Screen', 'enqueue' ] );
add_action( 'restrict_manage_posts',  [ 'Plugora_Folders_Pages_Screen', 'render_filter' ] );
add_filter( 'parse_query',            [ 'Plugora_Folders_Pages_Screen', 'filter_query' ] );

// Pages list column + inline assign (free for everyone)
add_filter( 'manage_pages_columns',         [ 'Plugora_Folders_Pages_Screen', 'add_column' ] );
add_action( 'manage_pages_custom_column',   [ 'Plugora_Folders_Pages_Screen', 'render_column' ], 10, 2 );

// Preload all folder assignments for the current Pages list in one query (avoids N+1).
add_filter( 'the_posts', [ 'Plugora_Folders_Pages_Screen', 'preload_for_listing' ], 10, 1 );

// Folder meta box on the page editor sidebar (free)
add_action( 'add_meta_boxes_page',          [ 'Plugora_Folders_Pages_Screen', 'register_meta_box' ] );
add_action( 'save_post_page',               [ 'Plugora_Folders_Pages_Screen', 'save_meta_box' ], 10, 2 );

// Premium hooks — only registered when a valid license is active.
add_action( 'init', function() {
	if ( ! plugora_folders_is_premium() ) return;
	add_action( 'bulk_edit_custom_box', [ 'Plugora_Folders_Pages_Screen', 'bulk_edit_box' ], 10, 2 );
	add_action( 'save_post_page',       [ 'Plugora_Folders_Pages_Screen', 'save_bulk_edit' ], 10, 2 );
	add_action( 'admin_init',           [ 'Plugora_Folders_Pages_Screen', 'remember_user_view' ] );
	add_filter( 'plugora_folders_visible', [ 'Plugora_Folders_Pages_Screen', 'filter_by_capability' ], 10, 1 );
}, 5 );
