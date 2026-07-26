<?php
/**
 * WP-Stats class-stats-page.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public statistics page, rendered by the [page_stats] shortcode.
 *
 * Two views: the overview, and the per-commenter listing reached by
 * ?stats_author=. The markup and the filter positions are unchanged from before
 * 3.0.0 - companion plugins append whole sections through those filters and
 * themes style the headings by id.
 *
 * @since 3.0.0
 */
class Stats_Page {

	/**
	 * Comments per page in the author view.
	 */
	const PER_PAGE = 30;

	/**
	 * Page links shown either side of the current one.
	 */
	const PAGES_TO_SHOW = 10;

	/**
	 * Build a link into the author view.
	 *
	 * @param string $author Comment author, already URL-encoded.
	 * @param int    $page   Page number; 0 or 1 omits the page argument.
	 * @return string
	 */
	public static function author_link( $author, $page = 0 ) {
		$stats_url   = esc_url( Stats_Options::url() );
		$page_string = $page > 1 ? '&amp;stats_page=' . $page : '';
		$separator   = ( false !== strpos( $stats_url, '?' ) ) ? '&amp;' : '?';

		return $stats_url . $separator . 'stats_author=' . $author . $page_string;
	}

	/**
	 * Render whichever view the request asks for.
	 *
	 * @return string
	 */
	public static function render() {
		global $post;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public page choosing what to display; nothing is written.
		$comment_author = isset( $_GET['stats_author'] ) ? sanitize_text_field( wp_unslash( $_GET['stats_author'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		$page = isset( $_GET['stats_page'] ) ? max( 1, (int) $_GET['stats_page'] ) : 1;

		// Both views loop over $post to reuse the template tags, so stash it.
		$temp_post = $post;

		$output = '' === $comment_author
			? self::render_overview()
			: self::render_author( $comment_author, $page );

		$post = $temp_post;

		return apply_filters( 'stats_page', $output );
	}

	/**
	 * The default view: every enabled stat block.
	 *
	 * @return string
	 */
	protected static function render_overview() {
		$limit  = Stats_Options::most_limit();
		$output = '';

		// --- General ---------------------------------------------------------
		if ( Stats_Options::display( 'total_stats' ) ) {
			$output .= '<h2 id="GeneralStats">' . __( 'General Stats', 'wp-stats' ) . '</h2>' . "\n";
			$output .= '<p><strong>' . __( 'Total Stats', 'wp-stats' ) . '</strong></p>' . "\n";
			$output .= '<ul>' . "\n";
			$output .= self::total_lines();

			/** This filter is documented in includes/class-stats-page.php */
			$output  = apply_filters( 'wp_stats_page_general', $output );
			$output .= '</ul>' . "\n";
		}

		// --- Plugins ---------------------------------------------------------
		$output .= '<h2 id="PluginsStats">' . __( 'Plugins Stats', 'wp-stats' ) . '</h2>' . "\n";

		/**
		 * Filter the stats page after the "Plugins Stats" heading.
		 *
		 * Companion plugins append their own panels here.
		 *
		 * @param string $output Accumulated markup.
		 */
		$output = apply_filters( 'wp_stats_page_plugins', $output );

		// --- Recent ----------------------------------------------------------
		$output .= '<h2 id="TopRecentStats">' . sprintf(
			/* translators: %s: Number of stats. */
			_n( 'Top %s Recent Stat', 'Top %s Recent Stats', $limit, 'wp-stats' ),
			number_format_i18n( $limit )
		) . '</h2>' . "\n";

		if ( Stats_Options::display( 'recent_posts' ) ) {
			$output .= self::block(
				sprintf(
					/* translators: %s: Number of posts. */
					_n( '%s Recent Post', '%s Recent Posts', $limit, 'wp-stats' ),
					number_format_i18n( $limit )
				),
				Stats_Display::recent_posts( 'post', $limit )
			);
		}

		if ( Stats_Options::display( 'recent_comments' ) ) {
			$output .= self::block(
				sprintf(
					/* translators: %s: Number of comments. */
					_n( '%s Recent Comment', '%s Recent Comments', $limit, 'wp-stats' ),
					number_format_i18n( $limit )
				),
				Stats_Display::recent_comments( 'both', $limit )
			);
		}

		/** This filter is documented in includes/class-stats-page.php */
		$output = apply_filters( 'wp_stats_page_recent', $output );

		// --- Most / highest --------------------------------------------------
		$output .= '<h2 id="TopMostHighestStats">' . sprintf(
			/* translators: %s: Number of stats. */
			_n( '%s Most/Highest Stat', '%s Most/Highest Stats', $limit, 'wp-stats' ),
			number_format_i18n( $limit )
		) . '</h2>' . "\n";

		if ( Stats_Options::display( 'commented_post' ) ) {
			$output .= self::block(
				sprintf(
					/* translators: %s: Number of posts. */
					_n( '%s Most Commented Post', '%s Most Commented Posts', $limit, 'wp-stats' ),
					number_format_i18n( $limit )
				),
				Stats_Display::most_commented( 'post', $limit, 0 )
			);
		}

		if ( Stats_Options::display( 'commented_page' ) ) {
			$output .= self::block(
				sprintf(
					/* translators: %s: Number of pages. */
					_n( '%s Most Commented Page', '%s Most Commented Pages', $limit, 'wp-stats' ),
					number_format_i18n( $limit )
				),
				Stats_Display::most_commented( 'page', $limit, 0 )
			);
		}

		/** This filter is documented in includes/class-stats-page.php */
		$output = apply_filters( 'wp_stats_page_most', $output );

		// --- Authors ---------------------------------------------------------
		$output .= '<h2 id="AuthorsStats">' . __( 'Authors Stats', 'wp-stats' ) . '</h2>' . "\n";

		if ( Stats_Options::display( 'authors' ) ) {
			$output .= self::block( __( 'Authors', 'wp-stats' ), Stats_Display::authors( 'post' ), 'ol' );
		}

		/** This filter is documented in includes/class-stats-page.php */
		$output = apply_filters( 'wp_stats_page_authors', $output );

		// --- Comment members -------------------------------------------------
		$output .= '<h2 id="CommentsMembersStats">' . __( 'Comments\' Members Stats', 'wp-stats' ) . '</h2>' . "\n";

		if ( Stats_Options::display( 'comment_members' ) ) {
			$output .= self::block( __( 'Comment Members', 'wp-stats' ), Stats_Display::comment_members( 5, 0 ), 'ol' );
		}

		/** This filter is documented in includes/class-stats-page.php */
		$output = apply_filters( 'wp_stats_page_comments_members', $output );

		// --- Misc ------------------------------------------------------------
		$output .= '<h2 id="MiscStats">' . __( 'Misc Stats', 'wp-stats' ) . '</h2>' . "\n";

		if ( Stats_Options::display( 'post_cats' ) ) {
			$output .= self::block( __( 'Post Categories', 'wp-stats' ), Stats_Display::post_categories( false ) );
		}

		if ( Stats_Options::display( 'link_cats' ) ) {
			$output .= self::block( __( 'Link Categories', 'wp-stats' ), Stats_Display::link_categories() );
		}

		if ( Stats_Options::display( 'tags_list' ) ) {
			$output .= self::block( __( 'Tags List', 'wp-stats' ), Stats_Display::tags() );
		}

		/** This filter is documented in includes/class-stats-page.php */
		return apply_filters( 'wp_stats_page_misc', $output );
	}

	/**
	 * One titled block wrapping a list.
	 *
	 * @param string $heading Bold heading above the list.
	 * @param string $body    List items.
	 * @param string $tag     'ul' or 'ol'.
	 * @return string
	 */
	protected static function block( $heading, $body, $tag = 'ul' ) {
		return '<p><strong>' . $heading . '</strong></p>' . "\n"
			. '<' . $tag . '>' . "\n"
			. $body
			. '</' . $tag . '>' . "\n";
	}

	/**
	 * The bullet list inside the General Stats block.
	 *
	 * The translated strings carry their own <strong> markup, so they are
	 * deliberately not escaped - escaping would print the tags as text.
	 *
	 * @return string
	 */
	protected static function total_lines() {
		$counts = array(
			array(
				Stats_Query::total_authors(),
				/* translators: %s: Number of authors. */
				_n_noop( '<strong>%s</strong> author to this blog.', '<strong>%s</strong> authors to this blog.', 'wp-stats' ),
			),
			array(
				Stats_Query::total_posts(),
				/* translators: %s: Number of posts. */
				_n_noop( '<strong>%s</strong> post was posted.', '<strong>%s</strong> posts were posted.', 'wp-stats' ),
			),
			array(
				Stats_Query::total_pages(),
				/* translators: %s: Number of pages. */
				_n_noop( '<strong>%s</strong> page was created.', '<strong>%s</strong> pages were created.', 'wp-stats' ),
			),
			array(
				Stats_Query::total_terms( 'post_tag' ),
				/* translators: %s: Number of tags. */
				_n_noop( '<strong>%s</strong> tag was created.', '<strong>%s</strong> tags were created.', 'wp-stats' ),
			),
			array(
				Stats_Query::total_comments(),
				/* translators: %s: Number of comments. */
				_n_noop( '<strong>%s</strong> comment was posted.', '<strong>%s</strong> comments were posted.', 'wp-stats' ),
			),
			array(
				Stats_Query::total_comment_posters(),
				/* translators: %s: Number of nicknames. */
				_n_noop( '<strong>%s</strong> nickname was represented in the comments.', '<strong>%s</strong> different nicknames were represented in the comments.', 'wp-stats' ),
			),
			array(
				Stats_Query::total_links(),
				/* translators: %s: Number of links. */
				_n_noop( '<strong>%s</strong> link was added.', '<strong>%s</strong> links were added.', 'wp-stats' ),
			),
			array(
				Stats_Query::total_terms( 'category' ),
				/* translators: %s: Number of post categories. */
				_n_noop( '<strong>%s</strong> post category was needed.', '<strong>%s</strong> post categories were needed.', 'wp-stats' ),
			),
			array(
				Stats_Query::total_terms( 'link_category' ),
				/* translators: %s: Number of link categories. */
				_n_noop( '<strong>%s</strong> link category was needed.', '<strong>%s</strong> link categories were needed.', 'wp-stats' ),
			),
		);

		$spam = Stats_Query::spam_count();

		if ( null !== $spam ) {
			$counts[] = array(
				$spam,
				/* translators: %s: Number of spam comments. */
				_n_noop( '<strong>%s</strong> spam blocked.', '<strong>%s</strong> spam blocked.', 'wp-stats' ),
			);
		}

		$output = '';

		foreach ( $counts as $count ) {
			list( $number, $strings ) = $count;

			$output .= '<li>' . sprintf(
				translate_nooped_plural( $strings, $number, 'wp-stats' ),
				number_format_i18n( $number )
			) . '</li>' . "\n";
		}

		return $output;
	}

	/**
	 * The per-commenter view.
	 *
	 * @param string $comment_author Comment author name.
	 * @param int    $page           Page number, 1-based.
	 * @return string
	 */
	protected static function render_author( $comment_author, $page ) {
		global $post;

		$stats_url           = esc_url( Stats_Options::url() );
		$comment_author_link = urlencode( $comment_author );

		$total    = Stats_Query::count_comments_by_author( $comment_author );
		$max_page = (int) ceil( $total / self::PER_PAGE );
		$offset   = ( $page - 1 ) * self::PER_PAGE;

		$max_on_page     = ( $offset + self::PER_PAGE ) > $total ? $total : ( $offset + self::PER_PAGE );
		$display_on_page = ( $offset + 1 ) > $total ? $total : ( $offset + 1 );

		$comments = Stats_Query::comments_by_author( $comment_author, $offset, self::PER_PAGE );

		$output  = '<h2>' . __( 'Comments Posted By', 'wp-stats' ) . ' ' . esc_html( $comment_author ) . '</h2>';
		$output .= '<p>' . sprintf(
			/* translators: 1: First comment shown, 2: Last comment shown, 3: Total comments. */
			__( 'Displaying <strong>%1$s</strong> To <strong>%2$s</strong> Of <strong>%3$s</strong> Comments', 'wp-stats' ),
			number_format_i18n( $display_on_page ),
			number_format_i18n( $max_on_page ),
			number_format_i18n( $total )
		) . '</p>';

		if ( $comments ) {
			$output .= self::render_author_comments( $comments );
		} else {
			$output .= '<p>' . esc_html( $comment_author ) . ' ' . __( 'has not made any comments yet.', 'wp-stats' ) . '</p>';
		}

		if ( $max_page > 1 ) {
			/** This filter is documented in includes/class-stats-page.php */
			$output  = apply_filters( 'wp_stats_paging_start', $output );
			$output .= self::render_paging( $comment_author_link, $page, $max_page );
			/** This filter is documented in includes/class-stats-page.php */
			$output = apply_filters( 'wp_stats_paging_end', $output );
		}

		$output .= '<strong>&laquo;&laquo;</strong> <a href="' . $stats_url . '">' . __( 'Back To Stats Page', 'wp-stats' ) . ' </a>';

		return $output;
	}

	/**
	 * The comment blockquotes in the author view.
	 *
	 * Consecutive comments on the same post share one heading, which is why the
	 * previous title is tracked rather than grouped up front.
	 *
	 * Before 3.0.0 this also carried a "Comments Protected" branch for
	 * password-protected posts. It was unreachable: every listing query filters
	 * on `post_password = ''`, so a protected post never reaches this loop. The
	 * branch was also broken - it compared the wp-postpass cookie to the plain
	 * post_password, and WordPress has stored that cookie hashed since 3.4, so
	 * the comparison could not have matched even if a row had got this far.
	 *
	 * @param array $comments Comment rows joined to their posts.
	 * @return string
	 */
	protected static function render_author_comments( $comments ) {
		global $post;

		$output           = '';
		$cache_post_title = '';
		$format           = sprintf(
			/* translators: 1: Date format, 2: Time format. */
			__( '%1$s @ %2$s', 'wp-stats' ),
			get_option( 'date_format' ),
			get_option( 'time_format' )
		);

		foreach ( $comments as $post ) {
			$comment_id      = (int) $post->comment_ID;
			$comment_author  = stripslashes( $post->comment_author );
			$comment_date    = mysql2date( $format, $post->comment_date );
			$comment_content = apply_filters( 'comment_text', $post->comment_content );
			$post_date       = get_the_time( $format );
			$post_title      = get_the_title();
			$is_new_title    = $post_title !== $cache_post_title;

			$title_attr = esc_attr( __( 'Posted On', 'wp-stats' ) . ' ' . $post_date );

			if ( $is_new_title ) {
				$output .= '<p><strong><a href="' . esc_url( get_permalink() ) . '" title="' . $title_attr . '">'
					. esc_html( $post_title ) . '</a></strong></p>';
			}

			// $comment_content has already been through the comment_text
			// filter, which is where core does its own sanitising.
			$output .= '<blockquote>' . $comment_content . '<p><a href="'
				. esc_url( get_permalink() . '#comment-' . $comment_id ) . '" title="' . esc_attr(
					sprintf(
						/* translators: %s: Comment author name. */
						__( 'View the comment posted by %s', 'wp-stats' ),
						$comment_author
					)
				) . '">&raquo;</a> ' . __( 'Posted By', 'wp-stats' ) . ' <strong>' . esc_html( $comment_author )
				. '</strong> ' . __( 'On', 'wp-stats' ) . ' ' . esc_html( $comment_date ) . '</p></blockquote>';

			$cache_post_title = $post_title;
		}

		return $output;
	}

	/**
	 * The paging strip, in WP-PageNavi's markup so its stylesheet applies.
	 *
	 * @param string $author   Comment author, already URL-encoded.
	 * @param int    $page     Current page.
	 * @param int    $max_page Last page.
	 * @return string
	 */
	protected static function render_paging( $author, $page, $max_page ) {
		$span            = self::PAGES_TO_SHOW - 1;
		$half_page_start = (int) floor( $span / 2 );
		$half_page_end   = (int) ceil( $span / 2 );

		$start_page = max( 1, $page - $half_page_start );
		$end_page   = $page + $half_page_end;

		if ( ( $end_page - $start_page ) !== $span ) {
			$end_page = $start_page + $span;
		}

		if ( $end_page > $max_page ) {
			$start_page = max( 1, $max_page - $span );
			$end_page   = $max_page;
		}

		$output = '<div class="wp-pagenavi">' . "\n";

		$output .= '<span class="pages">&#8201;' . sprintf(
			/* translators: 1: Current page, 2: Total pages. */
			__( 'Page %1$s of %2$s', 'wp-stats' ),
			number_format_i18n( $page ),
			number_format_i18n( $max_page )
		) . '&#8201;</span>';

		if ( $start_page >= 2 && self::PAGES_TO_SHOW < $max_page ) {
			$output .= self::page_link( $author, 1, __( '&laquo; First', 'wp-stats' ) );
			$output .= '<span class="extend">...</span>';
		}

		if ( $page > 1 ) {
			$output .= self::page_link( $author, $page - 1, __( '&laquo;', 'wp-stats' ) );
		}

		for ( $i = $start_page; $i <= $end_page; $i++ ) {
			if ( $i === $page ) {
				$output .= '<span class="current">&#8201;' . number_format_i18n( $i ) . '&#8201;</span>';
			} else {
				$output .= self::page_link( $author, $i, number_format_i18n( $i ) );
			}
		}

		if ( ( $page + 1 ) <= $max_page ) {
			$output .= self::page_link( $author, $page + 1, __( '&raquo;', 'wp-stats' ) );
		}

		if ( $end_page < $max_page ) {
			$output .= '<span class="extend">...</span>';
			$output .= self::page_link( $author, $max_page, __( 'Last &raquo;', 'wp-stats' ) );
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * One anchor in the paging strip.
	 *
	 * @param string $author Comment author, already URL-encoded.
	 * @param int    $page   Page to link to.
	 * @param string $label  Link text, which may contain entities.
	 * @return string
	 */
	protected static function page_link( $author, $page, $label ) {
		return '<a href="' . self::author_link( $author, $page ) . '" title="' . $label . '">&#8201;' . $label . '&#8201;</a>';
	}
}
