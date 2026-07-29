<?php
/**
 * WP-Stats class-wp-stats-settings.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Settings -> Stats screen.
 *
 * Built entirely on the Settings API: register_setting() for storage and the
 * nonce/capability handling, add_settings_section()/add_settings_field() for
 * the body, do_settings_sections() to render it. The screen itself writes no
 * table markup.
 *
 * Companion plugins still contribute raw checkbox HTML through the
 * wp_stats_page_admin_* filters; each of those now fires inside the field
 * callback for the section it belongs to.
 *
 * @since 3.0.0
 */
class WP_Stats_Settings {

	/**
	 * Settings group name, used by settings_fields(). The same name as the
	 * option row it writes, so there is one thing to remember rather than two.
	 */
	const GROUP = 'wp_stats_options';

	/**
	 * Menu slug, and the page name the sections are registered against.
	 */
	const PAGE = 'wp-stats-settings';

	/**
	 * Section holding the scalar settings.
	 */
	const SECTION_GENERAL = 'wp_stats_general';

	/**
	 * Section holding the display toggles.
	 */
	const SECTION_DISPLAY = 'wp_stats_display';

	/**
	 * Upper bound on stored display toggles.
	 *
	 * Guards against an unbounded option; no realistic set of companion plugins
	 * comes close.
	 */
	const MAX_DISPLAY_KEYS = 200;

	/**
	 * Hook the screen up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Add the Settings submenu entry.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'Stats', 'wp-stats' ),
			__( 'Stats', 'wp-stats' ),
			WP_Stats_Admin::capability( 'settings' ),
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the single option, and the sections and fields that edit it.
	 *
	 * Idempotent: every call registers the same ids, so re-running only
	 * refreshes the labels that depend on the stored limit.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			WP_Stats_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => WP_Stats_Options::defaults(),
			)
		);

		// An untitled section renders no heading of its own, so the two scalar
		// settings open the screen as a plain form table.
		add_settings_section(
			self::SECTION_GENERAL,
			'',
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			'stats_url',
			__( 'Stats URL', 'wp-stats' ),
			array( __CLASS__, 'render_url_field' ),
			self::PAGE,
			self::SECTION_GENERAL,
			array( 'label_for' => 'stats_url' )
		);

		add_settings_field(
			'stats_mostlimit',
			__( 'Stats Most Limit', 'wp-stats' ),
			array( __CLASS__, 'render_most_limit_field' ),
			self::PAGE,
			self::SECTION_GENERAL,
			array( 'label_for' => 'stats_mostlimit' )
		);

		add_settings_section(
			self::SECTION_DISPLAY,
			__( 'Type Of Stats To Display', 'wp-stats' ),
			'__return_false',
			self::PAGE
		);

		// Registration order is document order, which is the order the
		// wp_stats_page_admin_* filters have always fired in.
		foreach ( self::display_groups() as $group => $config ) {
			add_settings_field(
				'wp_stats_display_' . $group,
				$config['title'],
				array( __CLASS__, 'render_display_field' ),
				self::PAGE,
				self::SECTION_DISPLAY,
				array( 'group' => $group )
			);
		}
	}

	/**
	 * The display toggles, grouped into the screen's rows.
	 *
	 * The array key is both the row's identity and the suffix of the filter a
	 * companion plugin hooks to add its own checkbox to that row, so the group
	 * list is the whole contract in one place.
	 *
	 * Rebuilt on each call because half the labels count in the stored limit.
	 *
	 * @return array<string,array{title:string,checkboxes:array<string,string>}>
	 */
	protected static function display_groups() {
		$limit = WP_Stats_Options::most_limit();
		$count = number_format_i18n( $limit );

		return array(
			'general'          => array(
				'title'      => __( 'General Stats', 'wp-stats' ),
				'checkboxes' => array(
					'total_stats' => __( 'Total', 'wp-stats' ),
				),
			),
			'plugins'          => array(
				'title'      => __( 'Plugin Stats', 'wp-stats' ),
				'checkboxes' => array(),
			),
			'recent'           => array(
				/* translators: %s: Number of stats. */
				'title'      => sprintf( _n( 'Top %s Recent Stat', 'Top %s Recent Stats', $limit, 'wp-stats' ), $count ),
				'checkboxes' => array(
					/* translators: %s: Number of posts. */
					'recent_posts'    => sprintf( _n( '%s Most Recent Post', '%s Most Recent Posts', $limit, 'wp-stats' ), $count ),
					/* translators: %s: Number of comments. */
					'recent_comments' => sprintf( _n( '%s Most Recent Comment', '%s Most Recent Comments', $limit, 'wp-stats' ), $count ),
				),
			),
			'most'             => array(
				/* translators: %s: Number of stats. */
				'title'      => sprintf( _n( 'Top %s Most/Highest Stat', 'Top %s Most/Highest Stats', $limit, 'wp-stats' ), $count ),
				'checkboxes' => array(
					/* translators: %s: Number of posts. */
					'commented_post' => sprintf( _n( '%s Most Commented Post', '%s Most Commented Posts', $limit, 'wp-stats' ), $count ),
					/* translators: %s: Number of pages. */
					'commented_page' => sprintf( _n( '%s Most Commented Page', '%s Most Commented Pages', $limit, 'wp-stats' ), $count ),
				),
			),
			'authors'          => array(
				'title'      => __( 'Authors Stats', 'wp-stats' ),
				'checkboxes' => array(
					'authors' => __( 'Authors', 'wp-stats' ),
				),
			),
			'comments_members' => array(
				'title'      => __( 'Comments\' Members Stats', 'wp-stats' ),
				'checkboxes' => array(
					'comment_members' => __( 'Comment Members', 'wp-stats' ),
				),
			),
			'misc'             => array(
				'title'      => __( 'Misc Stats', 'wp-stats' ),
				'checkboxes' => array(
					'post_cats' => __( 'Post Categories', 'wp-stats' ),
					'link_cats' => __( 'Link Categories', 'wp-stats' ),
					'tags_list' => __( 'Tags List', 'wp-stats' ),
				),
			),
		);
	}

	/**
	 * Validate the submitted settings.
	 *
	 * Reached two ways: options.php calls it with what the screen posted, and
	 * register_setting() hangs it on sanitize_option_stats_options, so it also
	 * sees whatever any code writes to the option once admin_init has run.
	 *
	 * @param mixed $input Raw value from the form, or the stored option.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = WP_Stats_Options::get();

		$output = array(
			'url'        => isset( $input['url'] ) ? esc_url_raw( trim( $input['url'] ) ) : '',
			'most_limit' => isset( $input['most_limit'] ) ? max( 1, (int) $input['most_limit'] ) : 10,
		);

		// Only ticked boxes are posted, so start from every key we know about at
		// 0 and switch on the ones that came back.
		//
		// The universe of keys is the union of three sources. The form's own
		// hidden `known` list is the authoritative one: it is exactly what was
		// rendered, so a toggle can be switched off even if the plugin that owns
		// it had not registered its default by the time this callback ran. The
		// other two keep already-stored keys alive when an older companion
		// plugin renders a checkbox without the hidden field.
		$universe = array_merge( WP_Stats_Options::display_defaults(), $current['display'] );
		$known    = array_fill_keys( array_keys( $universe ), 0 );

		if ( isset( $input['known'] ) && is_array( $input['known'] ) ) {
			foreach ( $input['known'] as $key ) {
				$key = sanitize_key( $key );

				if ( '' !== $key && count( $known ) < self::MAX_DISPLAY_KEYS ) {
					$known[ $key ] = 0;
				}
			}
		}

		$display = isset( $input['display'] ) && is_array( $input['display'] ) ? $input['display'] : array();
		$posted  = array();

		// The screen posts a list of the ticked names. register_setting() also
		// wires this callback in as a sanitize_option_ filter, though, so an
		// option write on an admin request lands here as well - in the stored
		// shape, a name => 0|1 map. Read both, or such a write would store one
		// entry per literal 0 and 1 and zero every real toggle.
		foreach ( $display as $key => $value ) {
			if ( ! is_string( $key ) ) {
				$posted[] = $value;
				continue;
			}

			$name = sanitize_key( $key );

			if ( '' === $name || ( ! isset( $known[ $name ] ) && count( $known ) >= self::MAX_DISPLAY_KEYS ) ) {
				continue;
			}

			// A name the map switches off is still a name we now know about, so
			// it is stored as 0 rather than dropped and left to re-inherit its
			// default on the next read.
			$known[ $name ] = 0;

			if ( (int) $value ) {
				$posted[] = $name;
			}
		}

		$posted = array_merge( $posted, self::legacy_posted_keys() );

		foreach ( $posted as $key ) {
			$key = sanitize_key( $key );

			if ( '' === $key ) {
				continue;
			}

			// Deliberately not restricted to keys we already know. A companion
			// plugin's checkbox is rendered by that plugin, and rejecting an
			// unrecognised key here would report "Settings saved" and silently
			// drop the toggle. The screen is already nonce- and
			// capability-guarded, so the only exposure is option size.
			if ( ! isset( $known[ $key ] ) && count( $known ) >= self::MAX_DISPLAY_KEYS ) {
				continue;
			}

			$known[ $key ] = 1;
		}

		$output['display'] = $known;

		WP_Stats_Options::flush();

		return $output;
	}

	/**
	 * Toggles ticked on a checkbox built against WP-Stats 2.x.
	 *
	 * Before 3.0.0 every companion plugin rendered its own checkbox as
	 * `name="stats_display[]"`. options.php saves only the option names
	 * registered to the settings group - here that is `stats_options` and
	 * nothing else - so that field reaches this callback through no other route.
	 *
	 * Without this the consequence is worse than the checkbox being ignored.
	 * The sanitizer starts every key it knows about at 0, and a stale
	 * companion's key is already in the stored row, so the first save would
	 * force its panel off and leave no way to switch it back on.
	 *
	 * @return string[] Ticked toggle names.
	 */
	protected static function legacy_posted_keys() {
		// options.php verifies the nonce and the capability for this settings
		// group before it calls the sanitize callback, so there is nothing left
		// to check here; the sniff cannot see across that boundary.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['stats_display'] ) || ! is_array( $_POST['stats_display'] ) ) {
			return array();
		}

		return array_map( 'sanitize_key', wp_unslash( $_POST['stats_display'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( WP_Stats_Admin::capability( 'settings' ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-stats' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stats Options', 'wp-stats' ); ?></h1>

			<?php
			// Core only calls this from wp-admin/options-head.php, which runs for
			// its own settings screens. A plugin page is dispatched by admin.php
			// instead, so without this the save redirect lands back here with no
			// confirmation at all.
			settings_errors();
			?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The Stats URL field.
	 *
	 * @return void
	 */
	public static function render_url_field() {
		?>
		<input type="text" id="stats_url" name="<?php echo esc_attr( WP_Stats_Options::OPTION ); ?>[url]" value="<?php echo esc_url( WP_Stats_Options::url() ); ?>" size="50" dir="ltr" class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'URL to the page holding the [page_stats] shortcode.', 'wp-stats' ); ?><br />
			<?php esc_html_e( 'Example: https://www.yoursite.com/blog/stats/', 'wp-stats' ); ?><br />
			<?php esc_html_e( 'Example: https://www.yoursite.com/blog/?page_id=2', 'wp-stats' ); ?>
		</p>
		<?php
	}

	/**
	 * The Stats Most Limit field.
	 *
	 * @return void
	 */
	public static function render_most_limit_field() {
		?>
		<input type="number" min="1" id="stats_mostlimit" name="<?php echo esc_attr( WP_Stats_Options::OPTION ); ?>[most_limit]" value="<?php echo esc_attr( WP_Stats_Options::most_limit() ); ?>" size="2" class="small-text" />
		<p class="description"><?php esc_html_e( 'Top X Stats, where X is the most limit.', 'wp-stats' ); ?></p>
		<?php
	}

	/**
	 * One row of display toggles: this plugin's checkboxes, then anything a
	 * companion plugin hooks on.
	 *
	 * @param array $args Field arguments; 'group' names the row.
	 * @return void
	 */
	public static function render_display_field( $args ) {
		$groups = self::display_groups();
		$group  = isset( $args['group'] ) ? $args['group'] : '';

		if ( ! isset( $groups[ $group ] ) ) {
			return;
		}

		// A group of checkboxes gets a fieldset, and the row's <th> is not a
		// <label> for any one of them, so the legend carries the group name for
		// screen readers.
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo esc_html( $groups[ $group ]['title'] ); ?></span></legend>
			<?php
			foreach ( $groups[ $group ]['checkboxes'] as $key => $label ) {
				echo wp_kses( wp_stats_checkbox( $key, $label ), self::allowed_html() );
			}

			/**
			 * Filter the checkboxes in one row of the options screen.
			 *
			 * The dynamic portion of the hook name, `$group`, is the row:
			 * `general`, `plugins`, `recent`, `most`, `authors`,
			 * `comments_members` or `misc`.
			 *
			 * A companion plugin should build its markup with
			 * wp_stats_checkbox() rather than by hand, so the field names stay
			 * an implementation detail of WP-Stats.
			 *
			 * @param string $content Accumulated markup.
			 */
			echo wp_kses( apply_filters( "wp_stats_page_admin_{$group}", '' ), self::allowed_html() );
			?>
		</fieldset>
		<?php
	}

	/**
	 * Markup a companion plugin may contribute to the checkbox area.
	 *
	 * @return array
	 */
	protected static function allowed_html() {
		return array(
			'input'  => array(
				'type'    => array(),
				'name'    => array(),
				'id'      => array(),
				'value'   => array(),
				'checked' => array(),
				'class'   => array(),
			),
			'label'  => array(
				'for'   => array(),
				'class' => array(),
			),
			'br'     => array(),
			'p'      => array( 'class' => array() ),
			'strong' => array(),
			'em'     => array(),
			'span'   => array( 'class' => array() ),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'small'  => array(),
			'code'   => array(),
		);
	}
}
