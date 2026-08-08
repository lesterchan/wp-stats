<?php
/**
 * Block registration.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks and renders them.
 *
 * **The block is added beside the shortcode, never in place of it.**
 * `[page_stats]` stays registered, documented and supported, and nothing here
 * deprecates it. This plugin has shipped for fifteen years and that shortcode
 * sits in an unknowable number of published pages; a block that replaced it
 * would render literal text on every one of them on update.
 *
 * The block is dynamic. Its `save` returns null in JavaScript, so what lands in
 * post_content is the block comment and no markup at all, and every view
 * re-renders through the callback below. That is what lets a block and a
 * shortcode share one renderer: the markup is decided once, at render time, for
 * both of them.
 *
 * **Neither entry point calls the other.** The block does not run
 * `do_shortcode()` and the shortcode does not ask this class for anything. They
 * are siblings over `WP_Stats_Page`, which is where the rendering lives -- and
 * where the `wp_stats_sections` filter is fired, which is the part that matters
 * here. This plugin owns no statistics of its own beyond the counts it makes on
 * read; the "Plugins Stats" half of the page is whatever WP-EMail, WP-Polls,
 * WP-PostRatings, WP-PostViews, WP-UserOnline and WP-DownloadManager answer that
 * filter with. Rendering the block through the same method is what guarantees a
 * site sees the same contributors in the block as in the shortcode, without this
 * class knowing that any of them exist.
 *
 * @since 3.0.0
 */
class WP_Stats_Blocks {

	/**
	 * Blocks this plugin registers, as build directory => render callback.
	 *
	 * The keys are directory names under `build/`, which is what
	 * `register_block_type_from_metadata()` reads: the name, title and script
	 * handle all come out of the `block.json` copied there by the build, so this
	 * class never restates any of them.
	 *
	 * One entry today, and an array rather than a single call because the shape
	 * is the collection's: one class registers all of a plugin's blocks.
	 *
	 * @return array<string,callable>
	 */
	private static function blocks() {
		return array(
			'page-stats' => array( __CLASS__, 'render_page_stats' ),
		);
	}

	/**
	 * Hook block registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register every block against its built metadata.
	 *
	 * A missing `build/` directory means the plugin was installed without its
	 * build step having run -- a git checkout rather than a release zip, since
	 * `bin/build` runs before the deploy copies anything. Registering a block
	 * whose script cannot be enqueued gives an editor that silently fails to
	 * load it, so this returns instead and leaves the shortcode working.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::blocks() as $directory => $callback ) {
			$metadata = WP_STATS_DIR . 'build/' . $directory;

			if ( ! file_exists( $metadata . '/block.json' ) ) {
				continue;
			}

			register_block_type_from_metadata(
				$metadata,
				array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * Render the `wp-stats/page-stats` block.
	 *
	 * Takes no attributes, because `[page_stats]` takes none either. The two
	 * views the page has -- the overview and the per-commenter drill-down -- are
	 * chosen by the `stats_author` query argument on the request, not by
	 * anything saved in the post, so a block that offered a control for either
	 * would be inventing a setting the shortcode does not have.
	 *
	 * @return string
	 */
	public static function render_page_stats() {
		return WP_Stats_Page::render();
	}
}
