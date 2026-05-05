<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Plugora Folders — Settings → Lightning Folders page.
 * Tabs: General settings + License (license UI is delegated to Plugora_Folders_License).
 */
class Plugora_Folders_Settings {
	const OPT_KEY = 'plugora_folders_settings';
	const NONCE   = 'plugora_folders_settings';

	public static function defaults() {
		return [
			'post_types'            => [ 'page' ],
			'show_counts'           => 1,
			'show_unfiled'          => 1,
			'show_search'           => 1,
			'show_breadcrumbs'      => 1,
			'show_hierarchy_column' => 0,
			'include_child_items'   => 0,
			'no_reload'             => 1,
			'context_menus'         => 1,
			'colors'                => [ '#ef4444', '#6e3ff3', '#f59e0b', '#eab308', '#dc2626', '#7dd3fc', '#3b82f6', '#22c55e', '#1d4ed8', '#9ca3af' ],
		];
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPT_KEY, [] ), self::defaults() );
		if ( $key === null ) return $opts;
		return $opts[ $key ] ?? null;
	}

	public static function register_menu() {
		add_options_page(
			'Lightning Folders Settings',
			'Lightning Folders',
			'manage_options',
			'plugora-folders-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_settings() {
		register_setting( 'plugora_folders_settings_group', self::OPT_KEY, [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
			'default'           => self::defaults(),
		] );
	}

	public static function sanitize( $input ) {
		$d = self::defaults();
		if ( ! is_array( $input ) ) return $d;
		$post_types = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_values( array_filter( array_map( 'sanitize_key', $input['post_types'] ) ) )
			: [ 'page' ];
		$bools = [ 'show_counts','show_unfiled','show_search','show_breadcrumbs','show_hierarchy_column','include_child_items','no_reload','context_menus' ];
		$out = [ 'post_types' => $post_types ];
		foreach ( $bools as $k ) {
			$out[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
		}
		$colors = [];
		if ( isset( $input['colors'] ) && is_array( $input['colors'] ) ) {
			foreach ( $input['colors'] as $c ) {
				$c = sanitize_hex_color( trim( (string) $c ) );
				if ( $c ) $colors[] = $c;
			}
		}
		$out['colors'] = $colors ?: $d['colors'];
		return $out;
	}

	private static function available_post_types() {
		$pts = get_post_types( [ 'show_ui' => true ], 'objects' );
		// Skip a couple of WP internals
		unset( $pts['attachment'], $pts['wp_block'], $pts['wp_template'], $pts['wp_template_part'], $pts['wp_navigation'] );
		return $pts;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$base_url = admin_url( 'options-general.php?page=plugora-folders-settings' );
		?>
		<div class="wrap plugora-settings-wrap">
			<h1>Lightning Folders Settings</h1>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">Settings</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'license', $base_url ) ); ?>" class="nav-tab <?php echo $tab === 'license' ? 'nav-tab-active' : ''; ?>">
					License <?php echo plugora_folders_is_premium() ? '<span class="plugora-badge plugora-badge-pro" style="margin-left:6px">PRO</span>' : '<span class="plugora-badge plugora-badge-free" style="margin-left:6px">FREE</span>'; ?>
				</a>
			</h2>

			<?php if ( $tab === 'license' ) : ?>
				<?php Plugora_Folders_License::render_panel(); ?>
			<?php else : ?>
				<?php self::render_general_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_general_tab() {
		$opts        = self::get();
		$post_types  = self::available_post_types();
		$is_premium  = plugora_folders_is_premium();
		$pro_pts     = [ 'post', 'attachment', 'plugins', 'users' ]; // pro-only post types (display hint)
		?>
		<form method="post" action="options.php" class="plugora-settings-form">
			<?php settings_fields( 'plugora_folders_settings_group' ); ?>
			<h2 class="title">General</h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Enable folders for:</th>
					<td>
						<fieldset class="plugora-pt-list">
							<?php foreach ( $post_types as $pt ) :
								$slug    = $pt->name;
								$checked = in_array( $slug, (array) $opts['post_types'], true );
								$is_pro  = in_array( $slug, $pro_pts, true ) && $slug !== 'page' && ! $is_premium;
								?>
								<label class="<?php echo $is_pro ? 'is-pro' : ''; ?>">
									<input type="checkbox" name="<?php echo esc_attr( self::OPT_KEY ); ?>[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $checked ); ?> <?php disabled( $is_pro ); ?> />
									<?php echo esc_html( $pt->labels->name ); ?>
									<?php if ( $is_pro ) : ?><span class="plugora-pro-pill">PRO</span><?php endif; ?>
								</label>
							<?php endforeach; ?>
							<p class="description plugora-upsell-line">
								<a href="<?php echo esc_url( add_query_arg( 'tab', 'license', admin_url( 'options-general.php?page=plugora-folders-settings' ) ) ); ?>">Upgrade to Lightning Folders Pro</a>
								to manage media, users, plugins, and more using folders.
							</p>
						</fieldset>
					</td>
				</tr>
			</table>

			<table class="form-table plugora-toggle-table" role="presentation">
				<?php
				self::toggle_row( 'show_counts',           'Show number of items in each folder', 'Adds a count badge on every folder.' );
				self::toggle_row( 'show_unfiled',          'Show unassigned items folder',         'Show the "Unfiled" pseudo-folder so users can see items without a folder.' );
				self::toggle_row( 'show_search',           'Show folder search',                   'Shows the search box at the top of the folder sidebar.' );
				self::toggle_row( 'show_breadcrumbs',      'Show folder breadcrumbs',              'Shows the active folder path above the list table.' );
				self::toggle_row( 'show_hierarchy_column', 'Show folder hierarchy in folder column','In the Folder column, show "Parent / Child" instead of just the folder name.' );
				self::toggle_row( 'include_child_items',   'Include items from child folders',     'When viewing a parent folder, also list items belonging to its children.' );
				self::toggle_row( 'no_reload',             "Don't reload page when navigating folders", 'Filter via AJAX (where supported) instead of a full page reload.' );
				self::toggle_row( 'context_menus',         'Enable folder context menus',          'Enables right-click menus on folders for rename / delete / new.' );
				?>
			</table>

			<h2 class="title">Folder colors</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Colors:</th>
					<td>
						<div class="plugora-color-picker" data-input-name="<?php echo esc_attr( self::OPT_KEY ); ?>[colors][]">
							<?php foreach ( (array) $opts['colors'] as $hex ) : ?>
								<span class="plugora-color-swatch" style="background:<?php echo esc_attr( $hex ); ?>" data-color="<?php echo esc_attr( $hex ); ?>" title="<?php echo esc_attr( $hex ); ?>">
									<input type="hidden" name="<?php echo esc_attr( self::OPT_KEY ); ?>[colors][]" value="<?php echo esc_attr( $hex ); ?>" />
									<button type="button" class="plugora-color-remove" aria-label="Remove">&times;</button>
								</span>
							<?php endforeach; ?>
							<button type="button" class="button plugora-color-add">Add color</button>
							<input type="color" class="plugora-color-input" value="#6e3ff3" aria-label="Pick a color" />
						</div>
						<p class="description">These colors appear in the "Color label" picker when you create or rename a folder.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save changes' ); ?>
		</form>
		<?php
	}

	private static function toggle_row( $key, $label, $help = '' ) {
		$opts = self::get();
		$on   = ! empty( $opts[ $key ] );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label class="plugora-switch">
					<input type="hidden" name="<?php echo esc_attr( self::OPT_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="0" />
					<input type="checkbox" name="<?php echo esc_attr( self::OPT_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $on ); ?> />
					<span class="plugora-switch-slider"></span>
				</label>
				<?php if ( $help ) : ?><span class="plugora-help" title="<?php echo esc_attr( $help ); ?>">?</span><?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
