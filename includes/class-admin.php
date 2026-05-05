<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugora_Folders_Admin {
	public static function register_menu() {
		// Register only under Pages — no top-level menu (avoids duplicate "Folders" entries).
		add_submenu_page(
			'edit.php?post_type=page',
			'Folders',
			'Folders',
			'edit_pages',
			'plugora-folders',
			[ __CLASS__, 'render_page' ]
		);

		// License + general settings now live under Settings → Lightning Folders
		// (registered by Plugora_Folders_Settings::register_menu).
	}

	public static function enqueue( $hook ) {
		// Load on Folders admin page AND on the Pages list (so the inline assign control works there too).
		$is_folders_page = strpos( (string) $hook, 'plugora-folders' ) !== false;
		$is_pages_list   = $hook === 'edit.php' && ( $_GET['post_type'] ?? '' ) === 'page';
		$is_page_editor  = in_array( $hook, [ 'post.php', 'post-new.php' ], true )
			&& ( get_current_screen() && get_current_screen()->post_type === 'page' );
		if ( ! $is_folders_page && ! $is_pages_list && ! $is_page_editor ) return;

		wp_enqueue_style(
			'plugora-folders-admin',
			PLUGORA_FOLDERS_URL . 'assets/admin.css',
			[],
			PLUGORA_FOLDERS_VERSION
		);
		wp_enqueue_script(
			'plugora-folders-admin',
			PLUGORA_FOLDERS_URL . 'assets/admin.js',
			[ 'wp-api-fetch' ],
			PLUGORA_FOLDERS_VERSION,
			true
		);
		wp_localize_script( 'plugora-folders-admin', 'PlugoraFolders', [
			'restRoot' => esc_url_raw( rest_url() ),
			'ns'       => 'plugora-folders/v1',
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'context'  => $is_folders_page ? 'folders' : 'pages',
			'settings' => class_exists( 'Plugora_Folders_Settings' ) ? Plugora_Folders_Settings::get() : [],
		] );
	}

	public static function render_page() {
		echo '<div class="wrap"><h1>Folders</h1><div id="plugora-folders-app"></div></div>';
	}
}
