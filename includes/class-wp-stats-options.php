<?php
/**
 * The plugin's two stored rows.
 *
 * The settings row, wp_stats_options, holds every setting a site owner can
 * change and nothing else. The markers row, wp_stats_version, holds the pair of
 * upgrade markers on its own: the settings form never posts them, so a marker
 * kept inside the settings array would have to be rescued from the stored value
 * on every single save, and the one save that forgot would make the upgrade run
 * again on every request.
 *
 * Before 3.0.0 the settings were three unprefixed rows - stats_url,
 * stats_mostlimit and stats_display - two of which six other plugins read
 * directly. That arrangement is what STANDARDS.md 13 replaces: WP-Stats now
 * owns only its own row, and a companion plugin contributes a section through
 * the wp_stats_sections filter instead of sharing storage.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, writes and upgrades the settings and the version markers.
 *
 * @since 3.0.0
 */
class WP_Stats_Options {

	/**
	 * Settings row. Autoloaded.
	 */
	const OPTION = 'wp_stats_options';

	/**
	 * Upgrade markers row, holding 'plugin' and 'db'. Autoloaded.
	 */
	const VERSION = 'wp_stats_version';

	/**
	 * Every row the plugin used before 3.0.0, in one list.
	 *
	 * The migration reads these and then deletes them, and naming them once
	 * means the two halves cannot fall out of step - a row read but not deleted
	 * would be migrated again on the next schema bump, and one deleted but not
	 * read would take its setting with it.
	 */
	const LEGACY_ROWS = array( 'stats_options', 'stats_db_version', 'stats_url', 'stats_mostlimit', 'stats_display' );

	/**
	 * Cached copy of the merged settings for this request.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Register the upgrade check.
	 *
	 * Hooked to plugins_loaded rather than left to the activation hook: an
	 * activation hook does not fire on plugin *update*, which is the usual
	 * reason a migration silently never runs. Once the markers agree the check
	 * costs one autoloaded read.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
	}

	/**
	 * Defaults for the whole settings row.
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
	 * These are WP-Stats' own blocks and only its own. Before 3.0.0 the list
	 * also carried a toggle per companion plugin - polls, ratings, views,
	 * e-mailed posts and the rest - which meant WP-Stats decided whether a
	 * plugin it knew nothing about got to draw a panel. Each of those plugins
	 * now keeps that decision in its own settings row and answers the
	 * wp_stats_sections filter accordingly.
	 *
	 * @return array<string,int>
	 */
	public static function display_defaults() {
		return array(
			'total_stats'     => 1,
			'recent_posts'    => 1,
			'recent_comments' => 1,
			'commented_post'  => 1,
			'commented_page'  => 0,
			'authors'         => 1,
			'comment_members' => 1,
			'post_cats'       => 1,
			'link_cats'       => 1,
			'tags_list'       => 0,
		);
	}

	/**
	 * The settings row, merged over the defaults.
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
		// the defaults wholesale and lose the default of any toggle added since
		// the last save.
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
	 * Persist the settings row.
	 *
	 * @param array $options Full settings array.
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
	 * The stored upgrade markers, normalised.
	 *
	 * @return array
	 */
	public static function markers() {
		$markers = get_option( self::VERSION, array() );

		return is_array( $markers ) ? $markers : array();
	}

	/**
	 * Create the settings row on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::maybe_upgrade();

		$defaults        = self::defaults();
		$defaults['url'] = home_url( '/stats/' );

		add_option( self::OPTION, $defaults );
	}

	/**
	 * Bring the stored rows up to the running version.
	 *
	 * Gated on the markers rather than on "do the old rows exist". An install
	 * that has already migrated has no old rows, so a second pass would find
	 * nothing and write the defaults straight over the settings; and a row that
	 * reappears afterwards - a restored backup, or a plugin built against 2.x
	 * calling update_option() - must not send the migration round again.
	 *
	 * Both markers are written in one call at the end, so an upgrade that dies
	 * half way never records itself as finished.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$markers = self::markers();

		$plugin = isset( $markers['plugin'] ) ? (string) $markers['plugin'] : '';
		$db     = isset( $markers['db'] ) ? (string) $markers['db'] : '';

		if ( WP_STATS_VERSION === $plugin && WP_STATS_DB_VERSION === $db ) {
			return;
		}

		if ( '' === $db ) {
			self::migrate_legacy_rows();
		}

		update_option(
			self::VERSION,
			array(
				'plugin' => WP_STATS_VERSION,
				'db'     => WP_STATS_DB_VERSION,
			),
			true
		);
	}

	/**
	 * Write the settings row from inside an upgrade.
	 *
	 * `update_option()` declines to write a value equal to the one
	 * `get_option()` would return, and `register_setting()` is passed a
	 * `default`, which installs a `default_option_wp_stats_options` filter answering with
	 * the shipped defaults for a row that does not exist. So on an admin request
	 * -- the path every real update takes, because activation hooks do not fire
	 * on an update -- a migration whose result happens to equal the defaults
	 * writes nothing at all, while the legacy rows it read are deleted anyway.
	 *
	 * Passing an explicit default to `get_option()` defeats the registered one,
	 * because `filter_default_option()` returns early when a default was passed.
	 * That is what lets an absent row be told from a defaulted one and added
	 * outright. `add_option()` runs the sanitize callback exactly as
	 * `update_option()` does, so nothing else about the write changes.
	 *
	 * Latent here rather than live: `get()` merges over the defaults, so a
	 * missing row and a defaults row read identically and nothing is lost today.
	 * It stops being latent the moment this plugin gains a setting whose
	 * *absence* means something other than its default -- and then the failure is
	 * silent, browser-only, and the legacy rows are already gone. §7.6.1 has the
	 * three plugins this shape has already bitten.
	 *
	 * @param array $options The settings to store.
	 * @return void
	 */
	private static function write( array $options ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $options );

			return;
		}

		update_option( self::OPTION, $options );
	}

	/**
	 * Carry the pre-3.0.0 rows over, then delete them.
	 *
	 * Two generations are folded in at once: stats_url, stats_mostlimit and
	 * stats_display are the 2.x rows, and stats_options is the consolidated row
	 * an unreleased 3.0.0 build wrote before the name gained its prefix. An
	 * install that had neither is a fresh one and keeps its defaults.
	 *
	 * The toggles a companion plugin owned are deliberately not carried over.
	 * They were never WP-Stats' to hold, and each of those plugins reads the
	 * same stats_display row in its own migration.
	 *
	 * @return void
	 */
	protected static function migrate_legacy_rows() {
		$legacy = array();

		foreach ( self::LEGACY_ROWS as $row ) {
			$legacy[ $row ] = get_option( $row, null );
		}

		$options = self::defaults();
		$found   = false;

		if ( is_array( $legacy['stats_options'] ) ) {
			$options = wp_parse_args( $legacy['stats_options'], $options );
			$found   = true;
		}

		if ( null !== $legacy['stats_url'] ) {
			$options['url'] = (string) $legacy['stats_url'];
			$found          = true;
		}

		if ( null !== $legacy['stats_mostlimit'] ) {
			$options['most_limit'] = max( 1, (int) $legacy['stats_mostlimit'] );
			$found                 = true;
		}

		$display = isset( $options['display'] ) && is_array( $options['display'] ) ? $options['display'] : array();

		if ( is_array( $legacy['stats_display'] ) ) {
			$display = array_merge( $display, $legacy['stats_display'] );
			$found   = true;
		}

		// Only the keys WP-Stats still owns survive the fold, so a companion
		// plugin's old toggle cannot linger in this row for ever.
		$options['display'] = array_map(
			'intval',
			array_intersect_key(
				wp_parse_args( $display, self::display_defaults() ),
				self::display_defaults()
			)
		);

		$options['most_limit'] = max( 1, (int) $options['most_limit'] );
		$options['url']        = (string) $options['url'];

		if ( $found ) {
			self::write( $options );
		}

		foreach ( self::LEGACY_ROWS as $row ) {
			delete_option( $row );
		}

		self::$cache = null;
	}
}
