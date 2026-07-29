<?php
/**
 * WP-Stats class-wp-stats.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * styles, so the front end does not carry it site-wide. WP-PageNavi styles
	 * the same markup, so stand aside when it is active.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		if ( function_exists( 'pagenavi_stylesheets' ) ) {
			return;
		}

		if ( ! self::needs_styles() ) {
			return;
		}

		wp_enqueue_style( 'wp-stats', plugins_url( 'css/wp-stats.css', WP_STATS_MAIN_FILE ), array(), WP_STATS_VERSION );
	}

	/**
	 * Whether the current request renders anything the stylesheet applies to.
	 *
	 * @return bool
	 */
	protected static function needs_styles() {
		if ( is_active_widget( false, false, 'stats', true ) ) {
			return true;
		}

		$post = get_post();

		return $post instanceof WP_Post && has_shortcode( $post->post_content, 'page_stats' );
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
