<?php
/**
 * The public statistics page, rendered by the [page_stats] shortcode.
 *
 * Two views: the overview, and the per-commenter listing reached by
 * ?stats_author=. The heading ids are unchanged from before 3.0.0, because
 * themes style and link to them.
 *
 * What did change is how another plugin gets a block onto this page. Until
 * 3.0.0 it hooked one of seven wp_stats_page_* filters and appended a string,
 * having first read WP-Stats' own stats_display row to find out whether it was
 * allowed to. Both halves of that are gone: a contributor now answers the
 * wp_stats_sections filter with an entry describing its block, and decides for
 * itself, out of its own settings, whether to answer at all.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the statistics page.
 *
 * @since 3.0.0
 */
class WP_Stats_Page {

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
		$stats_url   = esc_url( WP_Stats_Options::url() );
		$page_string = $page > 1 ? '&amp;stats_page=' . $page : '';
		$separator   = ( false !== strpos( $stats_url, '?' ) ) ? '&amp;' : '?';

		return $stats_url . $separator . 'stats_author=' . $author . $page_string;
	}

	/**
	 * Render whichever view the request asks for.
	 *
	 * The two arguments arrive as registered query variables rather than out of
	 * $_GET, which is what makes WP_Stats::register_query_vars() the one place
	 * that says which arguments this page understands. Registering one does not
	 * clean it, though: WP::parse_request() copies the value straight out of
	 * $_GET, and wp_magic_quotes() has already slashed everything in there, so
	 * it still arrives with backslashes in front of its quotes. Unslash it
	 * before it is compared against anything - sanitize_text_field() does not,
	 * and without this a commenter called Sinead O'Brien is looked up as
	 * Sinead O\'Brien, matches no row and gets an empty listing under a heading
	 * with a stray backslash in it.
	 *
	 * @return string
	 */
	public static function render() {
		$comment_author = sanitize_text_field( wp_unslash( (string) get_query_var( 'stats_author' ) ) );
		$page           = max( 1, (int) get_query_var( 'stats_page' ) );

		$output = '' === $comment_author
			? self::render_overview()
			: self::render_author( $comment_author, $page );

		$output = '<div class="wp-stats">' . "\n" . $output . '</div>' . "\n";

		/**
		 * Filter the whole statistics page.
		 *
		 * Named stats_page before 3.0.0.
		 *
		 * @since 3.0.0
		 *
		 * @param string $output Assembled markup, wrapper included.
		 */
		return apply_filters( 'wp_stats_page', $output );
	}

	/**
	 * Every section another plugin has contributed, valid and in order.
	 *
	 * A sibling plugin is third-party code as far as WP-Stats is concerned, so
	 * an entry that is malformed - not an array, no title, a render callback
	 * that is not callable - is skipped rather than allowed to fatal the page.
	 * A missing priority is the documented default rather than a reason to
	 * throw the entry away.
	 *
	 * @return array<string,array> Entries keyed by contributor, lowest priority first.
	 */
	public static function sections() {
		/**
		 * Collect the blocks other plugins contribute to the statistics page.
		 *
		 * Each contributor adds one entry keyed by its own slug with
		 * underscores - wp_polls, wp_postratings - holding:
		 *
		 *     title    (string)   Translated heading. Required.
		 *     priority (int)      Sort order. Optional, defaults to 10.
		 *     render   (callable) Echoes the block body. Required, no arguments.
		 *
		 * A contributor reads only its own settings to decide whether to
		 * answer, and one that is switched off returns $sections untouched
		 * rather than adding an entry with an empty body. WP-Stats reads
		 * nobody's row.
		 *
		 * @since 3.0.0
		 *
		 * @param array $sections Sections keyed by plugin slug with underscores.
		 */
		$sections = apply_filters( 'wp_stats_sections', array() );

		if ( ! is_array( $sections ) ) {
			return array();
		}

		$valid = array();

		foreach ( $sections as $key => $section ) {
			if ( ! is_string( $key ) || '' === $key || ! is_array( $section ) ) {
				continue;
			}

			if ( empty( $section['title'] ) || ! is_string( $section['title'] ) ) {
				continue;
			}

			if ( ! isset( $section['render'] ) || ! is_callable( $section['render'] ) ) {
				continue;
			}

			$section['priority'] = isset( $section['priority'] ) && is_numeric( $section['priority'] )
				? (int) $section['priority']
				: 10;

			$valid[ $key ] = $section;
		}

		// Sorted by key rather than by value, because the documented tie-break
		// is the key itself and uasort() never sees it.
		uksort(
			$valid,
			static function ( $left, $right ) use ( $valid ) {
				if ( $valid[ $left ]['priority'] === $valid[ $right ]['priority'] ) {
					return strcmp( $left, $right );
				}

				return $valid[ $left ]['priority'] < $valid[ $right ]['priority'] ? -1 : 1;
			}
		);

		return $valid;
	}

	/**
	 * WP-Stats' own rendering of one contributed section.
	 *
	 * Hung on wp_stats_section_<key> rather than called inline, so a theme can
	 * take one plugin's block over without touching the others:
	 *
	 *     add_action( 'wp_stats_section_wp_polls', function ( $section ) {
	 *         remove_action( 'wp_stats_section_wp_polls', array( 'WP_Stats_Page', 'render_section' ) );
	 *         echo '<p>my own poll numbers</p>';
	 *     }, 5 );
	 *
	 * @param array $section Section entry.
	 * @return void
	 */
	public static function render_section( $section ) {
		echo '<p><strong>' . esc_html( $section['title'] ) . '</strong></p>' . "\n";

		// The contributor echoes its own markup and is responsible for escaping
		// it, exactly as it would be inside a shortcode of its own.
		call_user_func( $section['render'] );
	}

	/**
	 * The "Plugins Stats" block: every contributed section, in order.
	 *
	 * The heading only appears when something contributed, so a site running
	 * WP-Stats on its own no longer shows an empty section.
	 *
	 * @return string
	 */
	protected static function render_sections() {
		$sections = self::sections();

		if ( ! $sections ) {
			return '';
		}

		$output = '<h2 id="PluginsStats">' . esc_html__( 'Plugins Stats', 'wp-stats' ) . '</h2>' . "\n";

		foreach ( $sections as $key => $section ) {
			$hook = 'wp_stats_section_' . $key;

			if ( ! has_action( $hook, array( __CLASS__, 'render_section' ) ) ) {
				add_action( $hook, array( __CLASS__, 'render_section' ) );
			}

			ob_start();

			/**
			 * Render one plugin's block on the statistics page.
			 *
			 * The dynamic portion of the hook name, `$key`, is the contributing
			 * plugin's slug with underscores: wp_stats_section_wp_polls.
			 * WP_Stats_Page::render_section() is hooked here by default, and is
			 * what echoes the heading and calls the entry's render callback.
			 *
			 * @since 3.0.0
			 *
			 * @param array $section The entry the contributor returned.
			 */
			do_action( $hook, $section );

			$output .= ob_get_clean();
		}

		return $output;
	}

	/**
	 * The default view: every enabled stat block.
	 *
	 * @return string
	 */
	protected static function render_overview() {
		$limit  = WP_Stats_Options::most_limit();
		$output = '';

		// --- General ---------------------------------------------------------
		if ( WP_Stats_Options::display( 'total_stats' ) ) {
			$output .= '<h2 id="GeneralStats">' . esc_html__( 'General Stats', 'wp-stats' ) . '</h2>' . "\n";
			$output .= '<p><strong>' . esc_html__( 'Total Stats', 'wp-stats' ) . '</strong></p>' . "\n";
			$output .= '<ul>' . "\n" . self::total_lines() . '</ul>' . "\n";
		}

		// --- Plugins ---------------------------------------------------------
		$output .= self::render_sections();

		// --- Recent ----------------------------------------------------------
		$output .= '<h2 id="TopRecentStats">' . esc_html(
			sprintf(
				/* translators: %s: Number of stats. */
				_n( 'Top %s Recent Stat', 'Top %s Recent Stats', $limit, 'wp-stats' ),
				number_format_i18n( $limit )
			)
		) . '</h2>' . "\n";

		if ( WP_Stats_Options::display( 'recent_posts' ) ) {
			$output .= self::block(
				sprintf(
					/* translators: %s: Number of posts. */
					_n( '%s Recent Post', '%s Recent Posts', $limit, 'wp-stats' ),
					number_format_i18n( $limit )
				),
				WP_Stats_Display::recent_posts( 'post', $limit )
			);
		}

		if ( WP_Stats_Options::display( 'recent_comments' ) ) {
			$output .= self::block(
				sprintf(
					/* translators: %s: Number of comments. */
					_n( '%s Recent Comment', '%s Recent Comments', $limit, 'wp-stats' ),
					number_format_i18n( $limit )
				),
				WP_Stats_Display::recent_comments( 'both', $limit )
			);
		}

		// --- Most / highest --------------------------------------------------
		$output .= '<h2 id="TopMostHighestStats">' . esc_html(
			sprintf(
				/* translators: %s: Number of stats. */
				_n( '%s Most/Highest Stat', '%s Most/Highest Stats', $limit, 'wp-stats' ),
				number_format_i18n( $limit )
			)
		) . '</h2>' . "\n";

		if ( WP_Stats_Options::display( 'commented_post' ) ) {
			$output .= self::block(
				sprintf(
					/* translators: %s: Number of posts. */
					_n( '%s Most Commented Post', '%s Most Commented Posts', $limit, 'wp-stats' ),
					number_format_i18n( $limit )
				),
				WP_Stats_Display::most_commented( 'post', $limit, 0 )
			);
		}

		if ( WP_Stats_Options::display( 'commented_page' ) ) {
			$output .= self::block(
				sprintf(
					/* translators: %s: Number of pages. */
					_n( '%s Most Commented Page', '%s Most Commented Pages', $limit, 'wp-stats' ),
					number_format_i18n( $limit )
				),
				WP_Stats_Display::most_commented( 'page', $limit, 0 )
			);
		}

		// --- Authors ---------------------------------------------------------
		$output .= '<h2 id="AuthorsStats">' . esc_html__( 'Authors Stats', 'wp-stats' ) . '</h2>' . "\n";

		if ( WP_Stats_Options::display( 'authors' ) ) {
			$output .= self::block( __( 'Authors', 'wp-stats' ), WP_Stats_Display::authors( 'post' ), 'ol' );
		}

		// --- Comment members -------------------------------------------------
		$output .= '<h2 id="CommentsMembersStats">' . esc_html__( 'Comments\' Members Stats', 'wp-stats' ) . '</h2>' . "\n";

		if ( WP_Stats_Options::display( 'comment_members' ) ) {
			$output .= self::block( __( 'Comment Members', 'wp-stats' ), WP_Stats_Display::comment_members( 5, 0 ), 'ol' );
		}

		// --- Misc ------------------------------------------------------------
		$output .= '<h2 id="MiscStats">' . esc_html__( 'Misc Stats', 'wp-stats' ) . '</h2>' . "\n";

		if ( WP_Stats_Options::display( 'post_cats' ) ) {
			$output .= self::block( __( 'Post Categories', 'wp-stats' ), WP_Stats_Display::post_categories( false ) );
		}

		if ( WP_Stats_Options::display( 'link_cats' ) ) {
			$output .= self::block( __( 'Link Categories', 'wp-stats' ), WP_Stats_Display::link_categories() );
		}

		if ( WP_Stats_Options::display( 'tags_list' ) ) {
			$output .= self::block( __( 'Tags List', 'wp-stats' ), WP_Stats_Display::tags() );
		}

		return $output;
	}

	/**
	 * One titled block wrapping a list.
	 *
	 * @param string $heading Bold heading above the list, plain text.
	 * @param string $body    List items, already escaped by WP_Stats_Display.
	 * @param string $tag     'ul' or 'ol'.
	 * @return string
	 */
	protected static function block( $heading, $body, $tag = 'ul' ) {
		$tag = 'ol' === $tag ? 'ol' : 'ul';

		return '<p><strong>' . esc_html( $heading ) . '</strong></p>' . "\n"
			. '<' . $tag . '>' . "\n"
			. $body
			. '</' . $tag . '>' . "\n";
	}

	/**
	 * The bullet list inside the General Stats block.
	 *
	 * The translated strings carry their own <strong> markup, so they go
	 * through wp_kses_post() rather than esc_html(), which would print the tags
	 * as text.
	 *
	 * @return string
	 */
	protected static function total_lines() {
		$counts = array(
			array(
				WP_Stats_Query::total_authors(),
				/* translators: %s: Number of authors. */
				_n_noop( '<strong>%s</strong> author to this blog.', '<strong>%s</strong> authors to this blog.', 'wp-stats' ),
			),
			array(
				WP_Stats_Query::total_posts(),
				/* translators: %s: Number of posts. */
				_n_noop( '<strong>%s</strong> post was posted.', '<strong>%s</strong> posts were posted.', 'wp-stats' ),
			),
			array(
				WP_Stats_Query::total_pages(),
				/* translators: %s: Number of pages. */
				_n_noop( '<strong>%s</strong> page was created.', '<strong>%s</strong> pages were created.', 'wp-stats' ),
			),
			array(
				WP_Stats_Query::total_terms( 'post_tag' ),
				/* translators: %s: Number of tags. */
				_n_noop( '<strong>%s</strong> tag was created.', '<strong>%s</strong> tags were created.', 'wp-stats' ),
			),
			array(
				WP_Stats_Query::total_comments(),
				/* translators: %s: Number of comments. */
				_n_noop( '<strong>%s</strong> comment was posted.', '<strong>%s</strong> comments were posted.', 'wp-stats' ),
			),
			array(
				WP_Stats_Query::total_comment_posters(),
				/* translators: %s: Number of nicknames. */
				_n_noop( '<strong>%s</strong> nickname was represented in the comments.', '<strong>%s</strong> different nicknames were represented in the comments.', 'wp-stats' ),
			),
			array(
				WP_Stats_Query::total_links(),
				/* translators: %s: Number of links. */
				_n_noop( '<strong>%s</strong> link was added.', '<strong>%s</strong> links were added.', 'wp-stats' ),
			),
			array(
				WP_Stats_Query::total_terms( 'category' ),
				/* translators: %s: Number of post categories. */
				_n_noop( '<strong>%s</strong> post category was needed.', '<strong>%s</strong> post categories were needed.', 'wp-stats' ),
			),
			array(
				WP_Stats_Query::total_terms( 'link_category' ),
				/* translators: %s: Number of link categories. */
				_n_noop( '<strong>%s</strong> link category was needed.', '<strong>%s</strong> link categories were needed.', 'wp-stats' ),
			),
		);

		$spam = WP_Stats_Query::spam_count();

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

			$output .= '<li>' . wp_kses_post(
				sprintf(
					translate_nooped_plural( $strings, $number, 'wp-stats' ),
					number_format_i18n( $number )
				)
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
		$stats_url           = esc_url( WP_Stats_Options::url() );
		$comment_author_link = rawurlencode( $comment_author );

		$total    = WP_Stats_Query::count_comments_by_author( $comment_author );
		$max_page = (int) ceil( $total / self::PER_PAGE );
		$offset   = ( $page - 1 ) * self::PER_PAGE;

		$max_on_page     = ( $offset + self::PER_PAGE ) > $total ? $total : ( $offset + self::PER_PAGE );
		$display_on_page = ( $offset + 1 ) > $total ? $total : ( $offset + 1 );

		$comments = WP_Stats_Query::comments_by_author( $comment_author, $offset, self::PER_PAGE );

		$output  = '<h2>' . esc_html__( 'Comments Posted By', 'wp-stats' ) . ' ' . esc_html( $comment_author ) . '</h2>';
		$output .= '<p>' . wp_kses_post(
			sprintf(
				/* translators: 1: First comment shown, 2: Last comment shown, 3: Total comments. */
				__( 'Displaying <strong>%1$s</strong> To <strong>%2$s</strong> Of <strong>%3$s</strong> Comments', 'wp-stats' ),
				number_format_i18n( $display_on_page ),
				number_format_i18n( $max_on_page ),
				number_format_i18n( $total )
			)
		) . '</p>';

		if ( $comments ) {
			$output .= self::render_author_comments( $comments );
		} else {
			$output .= '<p>' . esc_html( $comment_author ) . ' ' . esc_html__( 'has not made any comments yet.', 'wp-stats' ) . '</p>';
		}

		if ( $max_page > 1 ) {
			$output .= self::render_paging( $comment_author_link, $page, $max_page );
		}

		$output .= '<strong>&laquo;&laquo;</strong> <a href="' . $stats_url . '">' . esc_html__( 'Back To Stats Page', 'wp-stats' ) . ' </a>';

		return $output;
	}

	/**
	 * The comment blockquotes in the author view.
	 *
	 * Consecutive comments on the same post share one heading, which is why the
	 * previous title is tracked rather than the list grouped up front.
	 *
	 * Before 3.0.0 this also carried a "Comments Protected" branch for
	 * password-protected posts. It was unreachable: every listing query filters
	 * those out, so a protected post never reached this loop. The branch was
	 * also broken - it compared the wp-postpass cookie to the plain
	 * post_password, and WordPress has stored that cookie hashed since 3.4, so
	 * the comparison could not have matched even if a row had got this far.
	 *
	 * @param WP_Comment[] $comments Comments, grouped by post, newest first.
	 * @return string
	 */
	protected static function render_author_comments( $comments ) {
		$output           = '';
		$cache_post_title = '';
		$format           = WP_Stats_Display::datetime_format();

		foreach ( $comments as $comment ) {
			$post_id    = (int) $comment->comment_post_ID;
			$permalink  = (string) get_permalink( $post_id );
			$post_title = get_the_title( $post_id );
			$post_date  = get_the_time( $format, $post_id );

			if ( $post_title !== $cache_post_title ) {
				$output .= '<p><strong><a href="' . esc_url( $permalink ) . '" title="'
					. esc_attr(
						sprintf(
							/* translators: %s: the date the post was published. */
							__( 'Posted On %s', 'wp-stats' ),
							$post_date
						)
					) . '">'
					. esc_html( $post_title ) . '</a></strong></p>';
			}

			// The content has been through the comment_text filter, which is
			// where core does its own sanitising.
			$output .= '<blockquote>' . apply_filters( 'comment_text', $comment->comment_content ) . '<p><a href="'
				. esc_url( $permalink . '#comment-' . (int) $comment->comment_ID ) . '" title="' . esc_attr(
					sprintf(
						/* translators: %s: Comment author name. */
						__( 'View the comment posted by %s', 'wp-stats' ),
						$comment->comment_author
					)
				) . '">&raquo;</a> '
				. sprintf(
					/* translators: 1: the comment author name, wrapped in <strong>. 2: the date the comment was posted. */
					esc_html__( 'Posted By %1$s On %2$s', 'wp-stats' ),
					'<strong>' . esc_html( $comment->comment_author ) . '</strong>',
					esc_html( mysql2date( $format, $comment->comment_date ) )
				) . '</p></blockquote>';

			$cache_post_title = $post_title;
		}

		return $output;
	}

	/**
	 * The paging strip, in WP-PageNavi's markup so its stylesheet applies too.
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

		$output .= '<span class="pages">&#8201;' . esc_html(
			sprintf(
				/* translators: 1: Current page, 2: Total pages. */
				__( 'Page %1$s of %2$s', 'wp-stats' ),
				number_format_i18n( $page ),
				number_format_i18n( $max_page )
			)
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
				$output .= '<span class="current">&#8201;' . esc_html( number_format_i18n( $i ) ) . '&#8201;</span>';
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
	 * The labels are entities - &laquo;, &raquo; - or formatted numbers, so
	 * they are passed through wp_kses() with no tags allowed rather than
	 * esc_html(), which would print the entity itself.
	 *
	 * @param string $author Comment author, already URL-encoded.
	 * @param int    $page   Page to link to.
	 * @param string $label  Link text, which may contain entities.
	 * @return string
	 */
	protected static function page_link( $author, $page, $label ) {
		// esc_url() here as well as inside author_link(). That function returns
		// URL-ish text rather than a self-escaping value, its docblock asks the
		// caller to have URL-encoded the author already, and this was the one
		// call site of the three that took it at its word. It happens to be safe
		// -- render_author() rawurlencode()s before render_paging() ever sees the
		// name -- but "safe because of what a caller two frames up did" is a
		// property nothing enforces, and the one sink with no escaping is where
		// it stops being true.
		return '<a href="' . esc_url( self::author_link( $author, $page ) ) . '" title="' . esc_attr( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) ) . '">&#8201;'
			. wp_kses( $label, array() ) . '&#8201;</a>';
	}
}
