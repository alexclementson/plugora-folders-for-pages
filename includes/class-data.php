<?php
/**
 * Plugora Folders — data helpers.
 *
 * Single place for table names, request-scoped caches, and batched lookups.
 * Used by REST + admin code so we never run the same query twice in one render.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugora_Folders_Data {

	/** @var array<int,object>|null Per-request folder list cache. */
	private static $folders_cache = null;

	/** @var array<int,int>|null Per-request page->folder map preloaded for the current list table. */
	private static $assignments_cache = null;

	/** Folders table name. */
	public static function folders_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'plugora_folders';
	}

	/** Page->folder mapping table name. */
	public static function assignments_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'plugora_folder_pages';
	}

	/**
	 * Return every folder, ordered by sort_order then name.
	 * Cached for the current request — safe to call from the column callback.
	 *
	 * @return array<int,object>
	 */
	public static function all_folders(): array {
		if ( self::$folders_cache !== null ) {
			return self::$folders_cache;
		}
		global $wpdb;
		$table = self::folders_table();
		$rows  = $wpdb->get_results( "SELECT id, name, parent_id, color, icon, sort_order FROM {$table} ORDER BY sort_order, name" );
		self::$folders_cache = is_array( $rows ) ? $rows : [];
		return self::$folders_cache;
	}

	/** Lightweight name-only list (re-uses the full cache). */
	public static function all_folders_basic(): array {
		return self::all_folders();
	}

	/** Invalidate the folder cache after a write. */
	public static function flush_folders_cache(): void {
		self::$folders_cache = null;
	}

	/**
	 * Look up a single page's folder. Uses the preloaded map when available,
	 * falls back to a single SELECT otherwise.
	 */
	public static function page_folder_id( int $page_id ): int {
		if ( self::$assignments_cache !== null && array_key_exists( $page_id, self::$assignments_cache ) ) {
			return (int) self::$assignments_cache[ $page_id ];
		}
		global $wpdb;
		$table = self::assignments_table();
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT folder_id FROM {$table} WHERE page_id = %d",
			$page_id
		) );
	}

	/**
	 * Preload assignments for a batch of page IDs into the request cache.
	 * Eliminates the N+1 query when rendering the Folder column on a Pages list.
	 *
	 * @param int[] $page_ids
	 */
	public static function preload_assignments( array $page_ids ): void {
		$page_ids = array_values( array_filter( array_map( 'intval', $page_ids ) ) );
		if ( empty( $page_ids ) ) {
			self::$assignments_cache = self::$assignments_cache ?? [];
			return;
		}
		global $wpdb;
		$table       = self::assignments_table();
		$placeholders = implode( ',', array_fill( 0, count( $page_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are ints.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT page_id, folder_id FROM {$table} WHERE page_id IN ({$placeholders})",
			...$page_ids
		) );
		$map = self::$assignments_cache ?? [];
		// Initialise every requested id to 0 so missing rows resolve without a follow-up query.
		foreach ( $page_ids as $id ) {
			if ( ! array_key_exists( $id, $map ) ) {
				$map[ $id ] = 0;
			}
		}
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->page_id ] = (int) $row->folder_id;
		}
		self::$assignments_cache = $map;
	}

	/** Invalidate the assignment cache after a write. */
	public static function flush_assignments_cache(): void {
		self::$assignments_cache = null;
	}
}
