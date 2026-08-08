<?php
/**
 * Plugin bootstrap: everything WP-Stats hooks, in one place.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 *
 * @since 3.0.0
 */
class WP_Stats {

	/**
	 * Sole instance.
	 *
	 * @var WP_Stats|null
	 */
	protected static $instance = null;

	/**
	 * Get the instance, creating it on first call.
	 *
	 * @return WP_Stats
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire everything up.
	 *
	 * Registered at file-load time, which is where WordPress requires
	 * activation hooks to be added.
	 */
	protected function __construct() {
		register_activation_hook( WP_STATS_MAIN_FILE, array( __CLASS__, 'activate' ) );

		WP_Stats_Options::register();
		WP_Stats_Settings::init();
		WP_Stats_Admin::init();
		WP_Stats_Blocks::init();

		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_shortcode( 'page_stats', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Create the options on activation, on every site of a network activation.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// 'number' => 0 lifts WP_Site_Query's default cap of 100.
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				WP_Stats_Options::activate();
				restore_current_blog();
			}

			return;
		}

		WP_Stats_Options::activate();
	}

	/**
	 * Declare the two query arguments the statistics page understands.
	 *
	 * Registering them is what lets the page read them with get_query_var()
	 * instead of reaching into $_GET, and this list is the one place that says
	 * what the page accepts. It does not clean them: the values are copied out
	 * of $_GET as they are, slashes and all, so a reader still has to unslash
	 * and sanitize whatever it takes back out.
	 *
	 * @param string[] $vars Public query variables.
	 * @return string[]
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'stats_author';
		$vars[] = 'stats_page';

		return $vars;
	}

	/**
	 * Register the widget.
	 *
	 * @return void
	 */
	public static function register_widget() {
		register_widget( 'WP_Stats_Widget' );
	}

	/**
	 * Enqueue the paging stylesheet.
	 *
	 * Only the [page_stats] shortcode and the widget render anything this
	 * styles, so the front end does not carry it site-wide.
	 *
	 * Until 3.0.0 this stood aside entirely when WP-PageNavi was active, by
	 * testing for a function of its that has not existed for years. The rules
	 * are scoped under .wp-stats now, so both sheets can load and neither has
	 * anything to say about the other's markup.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		if ( ! self::needs_styles() ) {
			return;
		}

		wp_enqueue_style( 'wp-stats', plugins_url( 'css/wp-stats.css', WP_STATS_MAIN_FILE ), array(), WP_STATS_VERSION );
	}

	/**
	 * Whether the current request renders anything the stylesheet applies to.
	 *
	 * Both spellings of the same page are asked about. The block is what a post
	 * written in the editor carries, the shortcode is what every post written
	 * before it carries, and the styles are for the paging strip in the
	 * per-commenter view -- which is reached from a link on the page itself, so
	 * a page holding the block would otherwise send a visitor to an unstyled
	 * one.
	 *
	 * @return bool
	 */
	protected static function needs_styles() {
		if ( is_active_widget( false, false, 'stats', true ) ) {
			return true;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'page_stats' )
			|| has_block( 'wp-stats/page-stats', $post );
	}

	/**
	 * The [page_stats] shortcode.
	 *
	 * Takes no attributes, so none are declared; WordPress passes them anyway
	 * and PHP discards the extras.
	 *
	 * @return string
	 */
	public static function shortcode() {
		return WP_Stats_Page::render();
	}
}
