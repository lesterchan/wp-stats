<?php
/**
 * WP-Stats class-wp-stats-options.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's settings, in one option row.
 *
 * Before 3.0.0 these lived in three rows - stats_url, stats_mostlimit and
 * stats_display - which every read had to fetch separately. They are now one
 * nested array in `stats_options`, migrated on upgrade.
 *
 * @since 3.0.0
 */
class WP_Stats_Options {

	/**
	 * The one option row this plugin owns.
	 */
	const OPTION = 'stats_options';

	/**
	 * Schema version. Kept in its own row because it is read to decide whether
	 * the main option needs migrating, so it cannot live inside the thing
	 * being migrated.
	 */
	const DB_VERSION_OPTION = 'stats_db_version';

	/**
	 * Current schema version.
	 */
	const DB_VERSION = 1;

	/**
	 * Cached copy of the merged options for this request.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * True while the migration is reading the legacy rows.
	 *
	 * The compatibility filters below answer the old option names out of the
	 * consolidated row. During the migration that is exactly backwards: the
	 * migration needs the real rows, and without this guard it would read its
	 * own defaults and write them over the settings it was meant to preserve.
	 *
	 * @var bool
	 */
	protected static $migrating = false;

	/**
	 * Wire up the migration and the backwards-compatibility shims.
	 *
	 * @return void
	 */
	public static function init() {
		// Activation does not fire on plugin *update*, which is the usual reason
		// a migration never runs, so drive it from a request hook as well.
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 1 );

		// Plugins released before WP-Stats 3.0.0 read the three legacy rows
		// directly. Serve them from the consolidated option so a stale companion
		// keeps working instead of silently losing every toggle.
		foreach ( array( 'stats_url', 'stats_mostlimit', 'stats_display' ) as $legacy ) {
			add_filter( "option_{$legacy}", array( __CLASS__, 'filter_legacy_option' ), 10, 2 );
			add_filter( "default_option_{$legacy}", array( __CLASS__, 'filter_legacy_option' ), 10, 2 );
		}
	}

	/**
	 * Defaults for the whole option.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'url'        => '',
			'most_limit' => 10,
			'display'    => self::display_defaults(),
		);
	}

	/**
	 * Which stat blocks are shown, and their default state.
	 *
	 * The single source of truth for the display keys. A companion plugin that
	 * contributes its own checkbox should register the key here via the filter,
	 * so its default is known before anyone has visited the options screen.
	 *
	 * @return array<string,int>
	 */
	public static function display_defaults() {
		return apply_filters(
			'wp_stats_display_defaults',
			array(
				'total_stats'        => 1,
				'email'              => 1,
				'polls'              => 1,
				'ratings'            => 1,
				'views'              => 1,
				'useronline'         => 1,
				'recent_posts'       => 1,
				'recent_comments'    => 1,
				'commented_post'     => 1,
				'commented_page'     => 0,
				'emailed_most_post'  => 1,
				'emailed_most_page'  => 0,
				'rated_highest_post' => 1,
				'rated_highest_page' => 0,
				'rated_most_post'    => 1,
				'rated_most_page'    => 0,
				'viewed_most_post'   => 1,
				'viewed_most_page'   => 0,
				'authors'            => 1,
				'comment_members'    => 1,
				'post_cats'          => 1,
				'link_cats'          => 1,
				'tags_list'          => 0,
			)
		);
	}

	/**
	 * The full option, merged over the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$options = wp_parse_args( $stored, self::defaults() );

		// wp_parse_args() is shallow, so the nested display array would replace
		// the defaults wholesale and lose any key a companion has registered
		// since the last save.
		$options['display'] = array_map(
			'intval',
			wp_parse_args(
				isset( $stored['display'] ) && is_array( $stored['display'] ) ? $stored['display'] : array(),
				self::display_defaults()
			)
		);

		$options['most_limit'] = max( 1, (int) $options['most_limit'] );
		$options['url']        = (string) $options['url'];

		self::$cache = $options;

		return $options;
	}

	/**
	 * Persist the option.
	 *
	 * @param array $options Full option array.
	 * @return bool
	 */
	public static function update( $options ) {
		self::$cache = null;

		return update_option( self::OPTION, $options );
	}

	/**
	 * Forget the per-request cache.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * URL of the page holding the [page_stats] shortcode.
	 *
	 * @return string
	 */
	public static function url() {
		$options = self::get();

		return $options['url'];
	}

	/**
	 * How many entries the "top X" blocks show.
	 *
	 * @return int
	 */
	public static function most_limit() {
		$options = self::get();

		return (int) $options['most_limit'];
	}

	/**
	 * Whether a display toggle is on.
	 *
	 * @param string $key Toggle name.
	 * @return bool
	 */
	public static function display( $key ) {
		$options = self::get();

		return 1 === (int) ( $options['display'][ $key ] ?? 0 );
	}

	/**
	 * Every display key currently known, defaults plus whatever is stored.
	 *
	 * @return string[]
	 */
	public static function display_keys() {
		$options = self::get();

		return array_keys( $options['display'] );
	}

	/**
	 * Create the option on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::maybe_migrate();

		$defaults        = self::defaults();
		$defaults['url'] = home_url( '/stats/' );

		add_option( self::OPTION, $defaults );
		add_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Fold the three pre-3.0.0 option rows into one.
	 *
	 * Gated on the schema version rather than on "do the old keys exist": an
	 * install that has already migrated has no old keys, and re-running would
	 * write defaults straight over the settings.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION ) {
			return;
		}

		self::$migrating = true;

		$legacy_url     = get_option( 'stats_url', null );
		$legacy_limit   = get_option( 'stats_mostlimit', null );
		$legacy_display = get_option( 'stats_display', null );

		self::$migrating = false;

		// Nothing to carry over: a fresh install, not an upgrade.
		if ( null === $legacy_url && null === $legacy_limit && null === $legacy_display ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
			return;
		}

		$options = self::defaults();

		if ( null !== $legacy_url ) {
			$options['url'] = (string) $legacy_url;
		}

		if ( null !== $legacy_limit ) {
			$options['most_limit'] = max( 1, (int) $legacy_limit );
		}

		if ( is_array( $legacy_display ) ) {
			$options['display'] = array_map(
				'intval',
				array_merge( $options['display'], $legacy_display )
			);
		}

		update_option( self::OPTION, $options );

		foreach ( array( 'stats_url', 'stats_mostlimit', 'stats_display' ) as $legacy ) {
			delete_option( $legacy );
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		self::$cache = null;
	}

	/**
	 * Serve a deleted legacy option row from the consolidated one.
	 *
	 * Registered on both `option_*` and `default_option_*` so it answers whether
	 * or not the row still exists.
	 *
	 * @param mixed  $value  Stored value, or the default when the row is gone.
	 * @param string $option Option name.
	 * @return mixed
	 */
	public static function filter_legacy_option( $value, $option ) {
		if ( self::$migrating ) {
			return $value;
		}

		switch ( $option ) {
			case 'stats_url':
				return self::url();
			case 'stats_mostlimit':
				return self::most_limit();
			case 'stats_display':
				$options = self::get();
				return $options['display'];
		}

		return $value;
	}
}
