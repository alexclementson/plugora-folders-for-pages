<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}plugora_folders" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}plugora_folder_pages" );
delete_option( 'plugora_folders_version' );
