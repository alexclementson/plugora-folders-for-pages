<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugora_Folders_Installer {
	public static function activate() {
		self::install_schema();
		add_option( 'plugora_folders_version', PLUGORA_FOLDERS_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'plugora_folders_version' ) !== PLUGORA_FOLDERS_VERSION ) {
			self::install_schema();
			update_option( 'plugora_folders_version', PLUGORA_FOLDERS_VERSION );
		}
	}

	private static function install_schema() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . 'plugora_folders';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			parent_id BIGINT UNSIGNED DEFAULT NULL,
			color VARCHAR(32) NOT NULL DEFAULT 'slate',
			icon VARCHAR(32) NOT NULL DEFAULT 'folder',
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY parent_id (parent_id)
		) $charset;" );

		$meta = $wpdb->prefix . 'plugora_folder_pages';
		dbDelta( "CREATE TABLE $meta (
			page_id BIGINT UNSIGNED NOT NULL,
			folder_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (page_id),
			KEY folder_id (folder_id)
		) $charset;" );
	}

	public static function deactivate() {
		// Keep data on deactivate; uninstall.php handles destructive cleanup.
	}
}
