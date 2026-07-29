<?php
/**
 * WP-Stats template-tags.php
 *
 * The plugin's public API: the functions themes call directly, plus the three
 * helpers companion plugins use to contribute panels to the stats page.
 *
 * Everything here is a thin wrapper over the classes in this directory. The
 * names are unchanged from before 3.0.0 and are not deprecated - themes in the
 * wild call them and they are documented in the readme.
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Echo or return a value, matching the old $display argument convention.
 *
 * @param mixed $value   Value to emit.
 * @param bool  $display Echo when true, return when false.
 * @return mixed|void
 */
function wp_stats_maybe_echo( $value, $display ) {
	if ( ! $display ) {
		return $value;
	}

	// These builders assemble their own escaped markup.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $value;
}

/*
 * ---------------------------------------------------------------------------
 * Companion plugin API
 *
 * WP-Stats owns the storage; other plugins should go through these rather than
 * reading its options. Before 3.0.0 there was no such API and six plugins each
 * hand-rolled a get_option( 'stats_display' ) read and their own checkbox
 * markup, which is why consolidating the option rows was a breaking change.
 * ---------------------------------------------------------------------------
 */

/**
 * Whether a stats page block is switched on.
 *
 * @since 3.0.0
 *
 * @param string $key Display toggle name.
 * @return bool
 */
function wp_stats_display_enabled( $key ) {
	return WP_Stats_Options::display( $key );
}

/**
 * How many entries the "top X" blocks show.
 *
 * @since 3.0.0
 *
 * @return int
 */
function wp_stats_most_limit() {
	return WP_Stats_Options::most_limit();
}

/**
 * Build one checkbox for the WP-Stats options screen.
 *
 * Companion plugins should use this rather than writing the markup themselves,
 * so the field name stays an implementation detail of WP-Stats.
 *
 * @since 3.0.0
 *
 * @param string $value Toggle name, also the checkbox value and id suffix.
 * @param string $label Visible label.
 * @return string
 */
function wp_stats_checkbox( $value, $label ) {
	// The hidden field is what makes unticking work. Only ticked boxes are
	// posted, so the save has to know which toggles were on the screen in order
	// to store the rest as 0 - and it cannot work that out from the registered
	// defaults, because whether a companion plugin has registered its keys by
	// then depends on hook timing it does not control.
	return sprintf(
		'<input type="hidden" name="%1$s[known][]" value="%2$s" />' .
		'<input type="checkbox" name="%1$s[display][]" id="wpstats_%2$s" value="%2$s"%3$s />&nbsp;&nbsp;<label for="wpstats_%2$s">%4$s</label><br />' . "\n",
		esc_attr( WP_Stats_Options::OPTION ),
		esc_attr( $value ),
		checked( wp_stats_display_enabled( $value ), true, false ),
		esc_html( $label )
	);
}

/*
 * ---------------------------------------------------------------------------
 * Theme template tags
 * ---------------------------------------------------------------------------
 */

/**
 * Number of users who can author content.
 *
 * @param bool $display Echo when true, return when false.
 * @return int|void
 */
function get_totalauthors( $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Query::total_authors(), $display );
}

/**
 * Number of published posts.
 *
 * @param bool $display Echo when true, return when false.
 * @return int|void
 */
function get_totalposts( $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Query::total_posts(), $display );
}

/**
 * Number of published pages.
 *
 * @param bool $display Echo when true, return when false.
 * @return int|void
 */
function get_totalpages( $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Query::total_pages(), $display );
}

/**
 * Number of approved comments.
 *
 * @param bool $display Echo when true, return when false.
 * @return int|void
 */
function get_totalcomments( $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Query::total_comments(), $display );
}

/**
 * Number of distinct approved comment authors.
 *
 * @param bool $display Echo when true, return when false.
 * @return int|void
 */
function get_totalcommentposters( $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Query::total_comment_posters(), $display );
}

/**
 * Number of links.
 *
 * @param bool $display Echo when true, return when false.
 * @return int|void
 */
function get_totallinks( $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Query::total_links(), $display );
}

/**
 * Recently published posts, as list items.
 *
 * @param string $mode    Post type, 'both', or ''.
 * @param int    $limit   Maximum rows.
 * @param bool   $display Echo when true, return when false.
 * @return string|void
 */
function get_recentposts( $mode = '', $limit = 10, $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Display::recent_posts( $mode, $limit ), $display );
}

/**
 * Most recent approved comments, as list items.
 *
 * @param string $mode    Post type, 'both', or ''.
 * @param int    $limit   Maximum rows.
 * @param bool   $display Echo when true, return when false.
 * @return string|void
 */
function get_recentcomments( $mode = '', $limit = 10, $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Display::recent_comments( $mode, $limit ), $display );
}

/**
 * Posts ordered by comment count, as list items.
 *
 * @param string $mode    Post type, 'both', or ''.
 * @param int    $limit   Maximum rows.
 * @param int    $chars   Truncate titles to this length, 0 to disable.
 * @param bool   $display Echo when true, return when false.
 * @return string|void
 */
function get_mostcommented( $mode = '', $limit = 10, $chars = 0, $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Display::most_commented( $mode, $limit, $chars ), $display );
}

/**
 * Published post counts per author, as list items.
 *
 * @param string $mode    Post type, 'both', or ''.
 * @param bool   $display Echo when true, return when false.
 * @return string|void
 */
function get_authorsstats( $mode = '', $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Display::authors( $mode ), $display );
}

/**
 * Commenters ranked by comment count, as list items.
 *
 * @param int  $threshhold Hide anyone below this many comments; -1 disables.
 * @param int  $limit      Maximum rows, 0 for all.
 * @param bool $display    Echo when true, return when false.
 * @return string|void
 */
function get_commentmembersstats( $threshhold = -1, $limit = 0, $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Display::comment_members( $threshhold, $limit ), $display );
}

/**
 * Post categories, as core's own list.
 *
 * @param bool $display Echo when true, return when false.
 * @return string|void
 */
function get_postcats( $display = true ) {
	return WP_Stats_Display::post_categories( $display );
}

/**
 * Link categories with counts, as list items.
 *
 * @param bool $display Echo when true, return when false.
 * @return string|void
 */
function get_linkcats( $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Display::link_categories(), $display );
}

/**
 * Post tags with counts, as list items.
 *
 * @param bool $display Echo when true, return when false.
 * @return string|void
 */
function get_tags_list( $display = true ) {
	return wp_stats_maybe_echo( WP_Stats_Display::tags(), $display );
}

/**
 * The whole statistics page.
 *
 * @return string
 */
function stats_page() {
	return WP_Stats_Page::render();
}

/**
 * Build a link into the per-commenter view.
 *
 * @param string $author Comment author, already URL-encoded.
 * @param int    $page   Page number; 0 or 1 omits the page argument.
 * @return string
 */
function stats_page_link( $author, $page = 0 ) {
	return WP_Stats_Page::author_link( $author, $page );
}

/**
 * The display toggles and their defaults.
 *
 * @return array<string,int>
 */
function stats_display_defaults() {
	return WP_Stats_Options::display_defaults();
}

// A very generic name, so it has always been guarded. WP-PostViews used to
// define it too, and now keeps its own copy on a class instead.
if ( ! function_exists( 'snippet_text' ) ) {
	/**
	 * Truncate text to a length, adding an ellipsis.
	 *
	 * @param string $text   Text to shorten.
	 * @param int    $length Maximum length, 0 for no limit.
	 * @return string
	 */
	function snippet_text( $text, $length = 0 ) {
		return WP_Stats_Display::snippet_text( $text, $length );
	}
}
