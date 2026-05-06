<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugora_Folders_REST {
	const NS = 'plugora-folders/v1';

	public static function register_routes() {
		register_rest_route( self::NS, '/folders', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'list_folders' ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'create_folder' ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
			],
		] );
		register_rest_route( self::NS, '/folders/(?P<id>\d+)', [
			[
				'methods'             => 'PATCH',
				'callback'            => [ __CLASS__, 'update_folder' ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ __CLASS__, 'delete_folder' ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
			],
		] );
		register_rest_route( self::NS, '/assign', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'assign_page' ],
			'permission_callback' => [ __CLASS__, 'can_manage' ],
		] );
	}

	public static function can_manage() {
		return current_user_can( 'edit_pages' );
	}

	public static function list_folders( WP_REST_Request $r ) {
		global $wpdb;
		$ft = $wpdb->prefix . 'plugora_folders';
		$mt = $wpdb->prefix . 'plugora_folder_pages';
		$rows = $wpdb->get_results( "SELECT * FROM {$ft} ORDER BY sort_order, name" );
		$rows = $rows ?: [];

		if ( ! $r->get_param( 'with_counts' ) ) {
			return rest_ensure_response( $rows );
		}

		// Counts of published+draft pages per folder, plus totals for "All" and "Unfiled".
		$counts = $wpdb->get_results(
			"SELECT m.folder_id, COUNT(*) AS c
			   FROM {$mt} m
			   INNER JOIN {$wpdb->posts} p ON p.ID = m.page_id
			  WHERE p.post_type = 'page' AND p.post_status NOT IN ('trash','auto-draft')
			  GROUP BY m.folder_id",
			OBJECT_K
		);
		foreach ( $rows as $f ) {
			$f->count = isset( $counts[ $f->id ] ) ? (int) $counts[ $f->id ]->c : 0;
		}
		$total_all = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status NOT IN ('trash','auto-draft')"
		);
		$total_unfiled = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$mt} m ON m.page_id = p.ID
			 WHERE p.post_type='page' AND p.post_status NOT IN ('trash','auto-draft') AND m.page_id IS NULL"
		);
		return rest_ensure_response( [
			'folders' => $rows,
			'totals'  => [ 'all' => $total_all, 'unfiled' => $total_unfiled ],
		] );
	}

	public static function create_folder( WP_REST_Request $r ) {
		global $wpdb;
		$name = sanitize_text_field( $r->get_param( 'name' ) );
		if ( $name === '' ) return new WP_Error( 'invalid', 'Name required', [ 'status' => 400 ] );

		$wpdb->insert( $wpdb->prefix . 'plugora_folders', [
			'name'      => $name,
			'parent_id' => $r->get_param( 'parent_id' ) ? (int) $r->get_param( 'parent_id' ) : null,
			'color'     => sanitize_key( $r->get_param( 'color' ) ?: 'slate' ),
			'icon'      => sanitize_key( $r->get_param( 'icon' ) ?: 'folder' ),
		] );
		return rest_ensure_response( [ 'id' => (int) $wpdb->insert_id ] );
	}

	public static function update_folder( WP_REST_Request $r ) {
		global $wpdb;
		$id = (int) $r['id'];
		$patch = array_filter( [
			'name'  => $r->get_param( 'name' ) !== null ? sanitize_text_field( $r->get_param( 'name' ) ) : null,
			'color' => $r->get_param( 'color' ) !== null ? sanitize_key( $r->get_param( 'color' ) ) : null,
			'icon'  => $r->get_param( 'icon' ) !== null ? sanitize_key( $r->get_param( 'icon' ) ) : null,
		], fn( $v ) => $v !== null );
		if ( $patch ) $wpdb->update( $wpdb->prefix . 'plugora_folders', $patch, [ 'id' => $id ] );
		return rest_ensure_response( [ 'ok' => true ] );
	}

	public static function delete_folder( WP_REST_Request $r ) {
		global $wpdb;
		$id = (int) $r['id'];
		$wpdb->delete( $wpdb->prefix . 'plugora_folders', [ 'id' => $id ] );
		$wpdb->delete( $wpdb->prefix . 'plugora_folder_pages', [ 'folder_id' => $id ] );
		return rest_ensure_response( [ 'ok' => true ] );
	}

	public static function assign_page( WP_REST_Request $r ) {
		global $wpdb;
		$page_id   = (int) $r->get_param( 'page_id' );
		$folder_id = $r->get_param( 'folder_id' );
		$table     = $wpdb->prefix . 'plugora_folder_pages';

		if ( ! $page_id || get_post_type( $page_id ) !== 'page' ) {
			return new WP_Error( 'invalid', 'Invalid page', [ 'status' => 400 ] );
		}

		if ( $folder_id === null || $folder_id === '' || (int) $folder_id === 0 ) {
			$wpdb->delete( $table, [ 'page_id' => $page_id ] );
		} else {
			$wpdb->replace( $table, [ 'page_id' => $page_id, 'folder_id' => (int) $folder_id ] );
		}
		return rest_ensure_response( [ 'ok' => true ] );
	}
}
