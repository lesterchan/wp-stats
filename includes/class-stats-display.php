<?php
/**
 * WP-Stats class-stats-display.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the list markup for each stat block.
 *
 * The markup is reproduced exactly as it was before 3.0.0, down to the trailing
 * newlines, because themes style these lists and some of them are assembled by
 * companion plugins into the same <ul>.
 *
 * @since 3.0.0
 */
class Stats_Display {

	/**
	 * The "nothing here" list item every block falls back to.
	 *
	 * @return string
	 */
	protected static function none() {
		return '<li>' . __( 'N/A', 'wp-stats' ) . '</li>';
	}

	/**
	 * The date format the blocks use, "<date> @ <time>".
	 *
	 * @return string
	 */
	protected static function datetime_format() {
		return sprintf(
			/* translators: 1: Date format, 2: Time format. */
			__( '%1$s @ %2$s', 'wp-stats' ),
			get_option( 'date_format' ),
			get_option( 'time_format' )
		);
	}

	/**
	 * Truncate text to a length, adding an ellipsis.
	 *
	 * @param string $text   Text to shorten.
	 * @param int    $length Maximum length, 0 for no limit.
	 * @return string
	 */
	public static function snippet_text( $text, $length = 0 ) {
		$charset = get_option( 'blog_charset' );
		$text    = html_entity_decode( $text, ENT_QUOTES, $charset );

		if ( mb_strlen( $text ) > $length ) {
			return htmlentities( mb_substr( $text, 0, $length ), ENT_COMPAT, $charset ) . '...';
		}

		return htmlentities( $text, ENT_COMPAT, $charset );
	}

	/**
	 * Recently published posts.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @return string
	 */
	public static function recent_posts( $mode = '', $limit = 10 ) {
		global $post;

		$rows = Stats_Query::recent_posts( $mode, $limit );

		if ( ! $rows ) {
			return self::none();
		}

		$temp   = '';
		$format = self::datetime_format();

		foreach ( $rows as $post ) {
			$post_title   = get_the_title();
			$post_date    = get_the_time( $format );
			$display_name = stripslashes( $post->display_name );

			$temp .= '<li>' . esc_html( $post_date ) . ' - <a href="' . esc_url( get_permalink() ) . '" title="' . esc_attr(
				sprintf(
					/* translators: %s: Post title. */
					__( 'View post %s', 'wp-stats' ),
					$post_title
				)
			) . '">' . esc_html( $post_title ) . '</a> (' . esc_html( $display_name ) . ")</li>\n";
		}

		return $temp;
	}

	/**
	 * Most recent approved comments.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @return string
	 */
	public static function recent_comments( $mode = '', $limit = 10 ) {
		global $post;

		$rows = Stats_Query::recent_comments( $mode, $limit );

		if ( ! $rows ) {
			return self::none();
		}

		$temp   = '';
		$format = self::datetime_format();

		foreach ( $rows as $post ) {
			$post_title     = get_the_title();
			$comment_author = stripslashes( $post->comment_author );
			$comment_date   = mysql2date( $format, $post->comment_date );

			$temp .= '<li>' . esc_html( $comment_date ) . ' - ' . esc_html( $comment_author ) . ' (<a href="' . esc_url( get_permalink() . '#comment-' . $post->comment_ID ) . '" title="' . esc_attr(
				sprintf(
					/* translators: %s: Post title. */
					__( 'View comments in post %s', 'wp-stats' ),
					$post_title
				)
			) . '">' . esc_html( $post_title ) . "</a>)</li>\n";
		}

		return $temp;
	}

	/**
	 * Posts ordered by comment count.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @param int    $chars Truncate titles to this length, 0 to disable.
	 * @return string
	 */
	public static function most_commented( $mode = '', $limit = 10, $chars = 0 ) {
		global $post;

		$rows = Stats_Query::most_commented( $mode, $limit );

		if ( ! $rows ) {
			return self::none();
		}

		$temp  = '';
		$chars = (int) $chars;

		foreach ( $rows as $post ) {
			$post_title    = get_the_title();
			$comment_total = (int) $post->comment_total;
			$link_text     = $chars > 0 ? self::snippet_text( $post_title, $chars ) : $post_title;

			$temp .= '<li><a href="' . esc_url( get_permalink() ) . '" title="' . esc_attr(
				sprintf(
					/* translators: %s: Post title. */
					__( 'View comments in post %s', 'wp-stats' ),
					$post_title
				)
			) . '">' . esc_html( $link_text ) . '</a> - ' . esc_html(
				sprintf(
					/* translators: %s: Number of comments. */
					_n( '%s comment', '%s comments', $comment_total, 'wp-stats' ),
					number_format_i18n( $comment_total )
				)
			) . '</li>';
		}

		return $temp;
	}

	/**
	 * Published post counts per author.
	 *
	 * @param string $mode Post type, 'both', or ''.
	 * @return string
	 */
	public static function authors( $mode = '' ) {
		global $wp_rewrite;

		$rows = Stats_Query::author_post_counts( $mode );

		if ( ! $rows ) {
			return self::none();
		}

		$temp            = '';
		$using_permalink = get_option( 'permalink_structure' );
		$permalink       = $wp_rewrite->get_author_permastruct();

		foreach ( $rows as $row ) {
			// strip_tags(), not wp_strip_all_tags(): the latter also removes the
			// *contents* of script and style elements, so a name written as
			// "<script>Bob</script>" would render as an empty, unclickable link
			// instead of "Bob". Everything here is escaped at the sink anyway.
			$post_author  = strip_tags( stripslashes( $row->user_nicename ) );
			$display_name = strip_tags( stripslashes( $row->display_name ) );
			$posts_total  = number_format_i18n( $row->posts_total );

			if ( $using_permalink ) {
				$url = get_option( 'home' ) . str_replace( '%author%', $post_author, $permalink );
			} else {
				$url = get_option( 'siteurl' ) . '/?author_name=' . $post_author;
			}

			$temp .= '<li><a href="' . esc_url( $url ) . '" title="' . esc_attr(
				sprintf(
					/* translators: %s: Author name. */
					__( 'View posts posted by %s', 'wp-stats' ),
					$display_name
				)
			) . '">' . esc_html( $display_name ) . '</a> (' . esc_html( $posts_total ) . ")</li>\n";
		}

		return $temp;
	}

	/**
	 * Commenters ranked by number of approved comments.
	 *
	 * @param int $threshold Hide anyone below this many comments. -1 disables.
	 * @param int $limit     Maximum rows, 0 for all.
	 * @return string
	 */
	public static function comment_members( $threshold = -1, $limit = 0 ) {
		$rows = Stats_Query::comment_author_counts( $limit );

		if ( ! $rows ) {
			return self::none();
		}

		$temp = '';

		foreach ( $rows as $row ) {
			$comment_total = (int) $row->comment_total;

			if ( $comment_total < $threshold ) {
				continue;
			}

			// See the note in authors() on why this is not wp_strip_all_tags().
			$comment_author = strip_tags( stripslashes( $row->comment_author ) );

			// urlencode(), not rawurlencode(): this is a query-string value, and
			// switching would change every existing link's spaces from + to %20.
			$temp .= '<li><a href="' . esc_url( Stats_Page::author_link( urlencode( $comment_author ) ) ) . '" title="' . esc_attr(
				sprintf(
					/* translators: %s: Comment author name. */
					__( 'View all comments posted by %s', 'wp-stats' ),
					$comment_author
				)
			) . '">' . esc_html( $comment_author ) . '</a> (' . esc_html( number_format_i18n( $comment_total ) ) . ")</li>\n";
		}

		return $temp;
	}

	/**
	 * Post categories, as core's own list.
	 *
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	public static function post_categories( $display = true ) {
		return wp_list_categories(
			array(
				'style'               => 'list',
				'show_count'          => 1,
				'title_li'            => '',
				'hide_title_if_empty' => true,
				'echo'                => $display,
			)
		);
	}

	/**
	 * Link categories with their counts.
	 *
	 * @return string
	 */
	public static function link_categories() {
		$temp = '';

		foreach ( Stats_Query::link_categories() as $cat ) {
			$temp .= '<li>' . esc_html( $cat->name ) . ' (' . esc_html( number_format_i18n( $cat->count ) ) . ")</li>\n";
		}

		return $temp;
	}

	/**
	 * Post tags with their counts, most used first.
	 *
	 * @return string
	 */
	public static function tags() {
		$temp = '';

		foreach ( Stats_Query::tags() as $tag ) {
			$temp .= '<li><a href="' . esc_url( get_tag_link( $tag->term_id ) ) . '" title="' . esc_attr(
				sprintf(
					/* translators: %s: Number of topics. */
					_n( '%s topic', '%s topics', $tag->count, 'wp-stats' ),
					number_format_i18n( $tag->count )
				)
			) . '">' . esc_html( $tag->name ) . '</a> (' . esc_html( number_format_i18n( $tag->count ) ) . ")</li>\n";
		}

		return $temp;
	}
}
