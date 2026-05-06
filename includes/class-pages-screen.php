<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugora_Folders_Pages_Screen {

	public static function enqueue( $hook ) {
		// Styles are loaded via Plugora_Folders_Admin::enqueue. Kept for hook compatibility.
		if ( $hook !== 'edit.php' ) return;
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'page' ) return;
	}

	/* -------------------------------------------------------------------- */
	/*  Filter dropdown above the Pages list                                */
	/* -------------------------------------------------------------------- */

	public static function render_filter() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'page' ) return;

		$folders = Plugora_Folders_Data::all_folders();
		$current = isset( $_GET['plugora_folder'] )
			? (int) sanitize_text_field( wp_unslash( $_GET['plugora_folder'] ) )
			: 0;

		echo '<label class="screen-reader-text" for="plugora_folder">' . esc_html__( 'Filter by folder', 'plugora-folders-for-pages' ) . '</label>';
		echo '<select name="plugora_folder" id="plugora_folder">';
		echo '<option value="">' . esc_html__( 'All folders', 'plugora-folders-for-pages' ) . '</option>';
		echo '<option value="-1"' . selected( $current, -1, false ) . '>' . esc_html__( 'Unfiled', 'plugora-folders-for-pages' ) . '</option>';
		foreach ( $folders as $f ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $f->id,
				selected( $current, (int) $f->id, false ),
				esc_html( $f->name )
			);
		}
		echo '</select>';
	}

	public static function filter_query( $query ) {
		global $pagenow, $wpdb;
		if ( ! is_admin() || $pagenow !== 'edit.php' ) return $query;
		if ( ! isset( $_GET['plugora_folder'] ) || $_GET['plugora_folder'] === '' ) return $query;
		if ( ( $query->query_vars['post_type'] ?? '' ) !== 'page' ) return $query;

		$folder_id = (int) sanitize_text_field( wp_unslash( $_GET['plugora_folder'] ) );
		$table     = Plugora_Folders_Data::assignments_table();

		if ( $folder_id === -1 ) {
			// Unfiled: pages without an entry in the meta table.
			$ids = $wpdb->get_col(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$table} m ON m.page_id = p.ID
				 WHERE p.post_type = 'page' AND m.page_id IS NULL"
			);
		} else {
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT page_id FROM {$table} WHERE folder_id = %d",
				$folder_id
			) );
		}
		$query->query_vars['post__in'] = $ids ?: [ 0 ];
		return $query;
	}

	/* -------------------------------------------------------------------- */
	/*  Folder column on the Pages list                                     */
	/* -------------------------------------------------------------------- */

	public static function add_column( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new['plugora_folder'] = esc_html__( 'Folder', 'plugora-folders-for-pages' );
			}
		}
		return $new;
	}

	/**
	 * Preload folder assignments for every visible page on the list table
	 * — eliminates the N+1 query that would otherwise hit on each row.
	 */
	public static function preload_for_listing( $posts ) {
		if ( empty( $posts ) || ! is_admin() ) return $posts;
		$ids = [];
		foreach ( $posts as $p ) {
			if ( isset( $p->post_type ) && $p->post_type === 'page' ) {
				$ids[] = (int) $p->ID;
			}
		}
		if ( $ids ) {
			Plugora_Folders_Data::preload_assignments( $ids );
		}
		return $posts;
	}

	public static function render_column( $column, $post_id ) {
		if ( $column !== 'plugora_folder' ) return;
		$current = Plugora_Folders_Data::page_folder_id( (int) $post_id );
		$folders = Plugora_Folders_Data::all_folders();

		echo '<select class="plugora-assign" data-page="' . (int) $post_id . '">';
		echo '<option value="">— ' . esc_html__( 'Unfiled', 'plugora-folders-for-pages' ) . '</option>';
		foreach ( $folders as $f ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $f->id,
				selected( $current, (int) $f->id, false ),
				esc_html( $f->name )
			);
		}
		echo '</select> <span class="plugora-assign-status" aria-live="polite"></span>';
	}

	/* -------------------------------------------------------------------- */
	/*  Bulk Edit (premium)                                                  */
	/* -------------------------------------------------------------------- */

	public static function bulk_edit_box( $column, $post_type ) {
		if ( $column !== 'plugora_folder' || $post_type !== 'page' ) return;
		$folders = Plugora_Folders_Data::all_folders();
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Folder', 'plugora-folders-for-pages' ); ?></span>
					<select name="plugora_bulk_folder">
						<option value="">— <?php esc_html_e( 'No change', 'plugora-folders-for-pages' ); ?> —</option>
						<option value="0"><?php esc_html_e( 'Unfiled', 'plugora-folders-for-pages' ); ?></option>
						<?php foreach ( $folders as $f ) : ?>
							<option value="<?php echo (int) $f->id; ?>"><?php echo esc_html( $f->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php wp_nonce_field( 'plugora_bulk', 'plugora_bulk_nonce' ); ?>
			</div>
		</fieldset>
		<?php
	}

	public static function save_bulk_edit( $post_id, $post ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) return;
		if ( ! isset( $_REQUEST['plugora_bulk_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['plugora_bulk_nonce'] ) ), 'plugora_bulk' ) ) return;
		if ( ! isset( $_REQUEST['plugora_bulk_folder'] ) ) return;

		$val = sanitize_text_field( wp_unslash( $_REQUEST['plugora_bulk_folder'] ) );
		if ( $val === '' ) return; // "No change"

		global $wpdb;
		$table     = Plugora_Folders_Data::assignments_table();
		$folder_id = (int) $val;
		if ( $folder_id === 0 ) {
			$wpdb->delete( $table, [ 'page_id' => $post_id ] );
		} else {
			$wpdb->replace( $table, [ 'page_id' => $post_id, 'folder_id' => $folder_id ] );
		}
		Plugora_Folders_Data::flush_assignments_cache();
	}

	/* -------------------------------------------------------------------- */
	/*  Editor sidebar meta box                                              */
	/* -------------------------------------------------------------------- */

	public static function register_meta_box() {
		add_meta_box(
			'plugora_folder_box',
			esc_html__( 'Folder', 'plugora-folders-for-pages' ),
			[ __CLASS__, 'render_meta_box' ],
			'page',
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		$current = Plugora_Folders_Data::page_folder_id( (int) $post->ID );
		$folders = Plugora_Folders_Data::all_folders();
		wp_nonce_field( 'plugora_meta', 'plugora_meta_nonce' );
		echo '<p style="margin:0 0 6px"><label for="plugora_meta_folder"><strong>' . esc_html__( 'Assign to folder', 'plugora-folders-for-pages' ) . '</strong></label></p>';
		echo '<select name="plugora_meta_folder" id="plugora_meta_folder" style="width:100%">';
		echo '<option value="0"' . selected( $current, 0, false ) . '>— ' . esc_html__( 'Unfiled', 'plugora-folders-for-pages' ) . ' —</option>';
		foreach ( $folders as $f ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $f->id,
				selected( $current, (int) $f->id, false ),
				esc_html( $f->name )
			);
		}
		echo '</select>';
		echo '<p style="margin:8px 0 0;color:#646970;font-size:12px">' . esc_html__( 'Manage folders under Folders in the admin sidebar.', 'plugora-folders-for-pages' ) . '</p>';
	}

	public static function save_meta_box( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( ! current_user_can( 'edit_page', $post_id ) ) return;
		if ( ! isset( $_POST['plugora_meta_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plugora_meta_nonce'] ) ), 'plugora_meta' ) ) return;
		if ( ! isset( $_POST['plugora_meta_folder'] ) ) return;

		global $wpdb;
		$table     = Plugora_Folders_Data::assignments_table();
		$folder_id = (int) sanitize_text_field( wp_unslash( $_POST['plugora_meta_folder'] ) );
		if ( $folder_id === 0 ) {
			$wpdb->delete( $table, [ 'page_id' => $post_id ] );
		} else {
			$wpdb->replace( $table, [ 'page_id' => $post_id, 'folder_id' => $folder_id ] );
		}
		Plugora_Folders_Data::flush_assignments_cache();
	}

	/* -------------------------------------------------------------------- */
	/*  Premium: per-user folder views                                       */
	/* -------------------------------------------------------------------- */

	public static function remember_user_view() {
		if ( ! is_user_logged_in() ) return;
		if ( ( $_GET['post_type'] ?? '' ) !== 'page' ) return;
		$user_id = get_current_user_id();

		if ( isset( $_GET['plugora_folder'] ) ) {
			update_user_meta(
				$user_id,
				'plugora_folders_last_view',
				sanitize_text_field( wp_unslash( $_GET['plugora_folder'] ) )
			);
			return;
		}

		// No filter in URL — restore previous if we have one.
		$last = get_user_meta( $user_id, 'plugora_folders_last_view', true );
		$skip = ( $_GET['plugora_skip_view'] ?? '' ) === '1';
		if ( $last !== '' && $last !== false && ! $skip ) {
			$qs = array_map( function( $v ) {
				return is_string( $v ) ? sanitize_text_field( wp_unslash( $v ) ) : $v;
			}, (array) $_GET );
			$qs['plugora_folder'] = $last;
			wp_safe_redirect( add_query_arg( $qs, admin_url( 'edit.php' ) ) );
			exit;
		}
	}

	/* -------------------------------------------------------------------- */
	/*  Premium: folder-level capabilities                                   */
	/* -------------------------------------------------------------------- */

	public static function filter_by_capability( $folders ) {
		if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) return $folders;
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;
		$caps  = get_option( 'plugora_folder_role_caps', [] );
		if ( empty( $caps ) || ! is_array( $caps ) ) return $folders;
		return array_values( array_filter( (array) $folders, function( $f ) use ( $caps, $roles ) {
			$id      = is_array( $f ) ? (int) ( $f['id'] ?? 0 ) : (int) ( $f->id ?? 0 );
			$allowed = $caps[ $id ] ?? null;
			if ( ! is_array( $allowed ) || empty( $allowed ) ) return true; // no restriction
			return (bool) array_intersect( $allowed, $roles );
		} ) );
	}
}
