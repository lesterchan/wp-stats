<?php
/**
 * Every database read the plugin makes.
 *
 * Kept apart from the markup so the counts can be tested without rendering
 * anything, and so there is one place to look when a query needs changing.
 *
 * Before 3.0.0 every method here was hand-written SQL against $wpdb. It is all
 * core query APIs now - WP_User_Query, get_posts(), get_comments(),
 * get_bookmarks() - which means the results go through the same object caches
 * as the rest of WordPress, the filters a site already uses to hide content
 * still apply, and the plugin stops maintaining its own dialect of "a published
 * post".
 *
 * Three of these group or count-distinct on comment_author, which
 * WP_Comment_Query has no argument for. Those add a clause to the query it
 * builds rather than writing one from scratch, so the joins, the status
 * handling and the caching are still core's.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's read queries.
 *
 * @since 3.0.0
 */
class WP_Stats_Query {

	/**
	 * Number of users who can publish content.
	 *
	 * Before 3.0.0 this counted users whose legacy `user_level` meta was above
	 * 1 and whose user_activation_key was empty. The first is a capability
	 * question with a capability answer, and the second excluded anyone with a
	 * password reset in flight, which has nothing to do with authorship.
	 *
	 * @return int
	 */
	public static function total_authors() {
		$query = new WP_User_Query(
			array(
				'capability'  => 'publish_posts',
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * Number of published posts.
	 *
	 * @return int
	 */
	public static function total_posts() {
		return (int) wp_count_posts( 'post' )->publish;
	}

	/**
	 * Number of published pages.
	 *
	 * @return int
	 */
	public static function total_pages() {
		return (int) wp_count_posts( 'page' )->publish;
	}

	/**
	 * Number of approved comments, of every comment type.
	 *
	 * @return int
	 */
	public static function total_comments() {
		return (int) wp_count_comments()->approved;
	}

	/**
	 * Number of distinct names that have left an approved comment.
	 *
	 * @return int
	 */
	public static function total_comment_posters() {
		return (int) self::comment_query(
			array(
				'status' => 'approve',
				'type'   => 'comment',
				'count'  => true,
			),
			array( 'fields' => 'COUNT( DISTINCT comment_author )' )
		);
	}

	/**
	 * Number of links.
	 *
	 * @return int
	 */
	public static function total_links() {
		return count(
			(array) get_bookmarks(
				array(
					'hide_invisible' => 0,
					'limit'          => -1,
				)
			)
		);
	}

	/**
	 * Number of terms in a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return int
	 */
	public static function total_terms( $taxonomy ) {
		$count = wp_count_terms( array( 'taxonomy' => $taxonomy ) );

		return is_wp_error( $count ) ? 0 : (int) $count;
	}

	/**
	 * Most recently published posts.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @return WP_Post[]
	 */
	public static function recent_posts( $mode = '', $limit = 10 ) {
		return get_posts(
			array(
				'post_type'    => self::post_types( $mode ),
				'post_status'  => 'publish',
				'has_password' => false,
				'numberposts'  => max( 0, (int) $limit ),
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);
	}

	/**
	 * Most recent approved comments.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @return WP_Comment[]
	 */
	public static function recent_comments( $mode = '', $limit = 10 ) {
		return (array) self::comment_query(
			array(
				'status'      => 'approve',
				'type'        => 'comment',
				'post_type'   => self::post_types( $mode ),
				'post_status' => 'publish',
				'number'      => self::number( $limit ),
				'orderby'     => 'comment_date',
				'order'       => 'DESC',
			),
			array( 'where' => self::unprotected_posts_clause() )
		);
	}

	/**
	 * Posts ordered by approved comment count.
	 *
	 * The count comes from the posts table's own comment_count column, which
	 * WordPress maintains and which counts approved comments - so this no
	 * longer joins and groups the comments table to work out what core already
	 * knows.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @return WP_Post[]
	 */
	public static function most_commented( $mode = '', $limit = 10 ) {
		return get_posts(
			array(
				'post_type'    => self::post_types( $mode ),
				'post_status'  => 'publish',
				'has_password' => false,
				'numberposts'  => max( 0, (int) $limit ),
				'orderby'      => 'comment_count',
				'order'        => 'DESC',
			)
		);
	}

	/**
	 * Published post counts per author.
	 *
	 * Walks the users who can publish rather than grouping the posts table by
	 * post_author, so "Authors" means the people who write for the site rather
	 * than every account that ever had a row attributed to it. Anyone with
	 * nothing published is dropped, exactly as the GROUP BY used to drop them.
	 *
	 * @param string $mode Post type, 'both', or ''.
	 * @return object[] Each with user_nicename, display_name and posts_total.
	 */
	public static function author_post_counts( $mode = '' ) {
		$users = get_users(
			array(
				'capability' => 'publish_posts',
				'fields'     => array( 'ID', 'display_name', 'user_nicename' ),
				'orderby'    => 'display_name',
			)
		);

		$post_types = self::post_types( $mode );
		$post_types = 'any' === $post_types ? array( 'post', 'page' ) : $post_types;

		$rows = array();

		foreach ( $users as $user ) {
			$total = (int) count_user_posts( (int) $user->ID, $post_types, true );

			if ( $total < 1 ) {
				continue;
			}

			$user->posts_total = $total;
			$rows[]            = $user;
		}

		return $rows;
	}

	/**
	 * Approved comment counts per comment author name.
	 *
	 * WP_Comment_Query has no "group by author" argument and hands back comment
	 * ids, so the names and the counts come from two runs of the same grouped
	 * query. The sort is fully determined - count descending, then the name -
	 * so the two lists line up row for row.
	 *
	 * @param int $limit Maximum rows, 0 for all.
	 * @return object[] Each with comment_author and comment_total.
	 */
	public static function comment_author_counts( $limit = 0 ) {
		$args = array(
			'status'      => 'approve',
			'type'        => 'comment',
			'post_status' => 'publish',
			'number'      => self::number( $limit ),
		);

		$shared = array(
			'where'   => self::unprotected_posts_clause(),
			'groupby' => 'comment_author',
			'orderby' => 'COUNT( comment_ID ) DESC, comment_author ASC',
		);

		// One representative comment per author, so the names can be read off
		// hydrated WP_Comment objects rather than parsed out of a raw column.
		$comments = array_values( (array) self::comment_query( $args, $shared ) );

		// The same grouping again, selecting the count instead. 'ids' is what
		// stops WP_Comment_Query treating those counts as comment ids and
		// trying to load posts with them.
		$totals = array_values(
			(array) self::comment_query(
				array_merge( $args, array( 'fields' => 'ids' ) ),
				array_merge( $shared, array( 'fields' => 'COUNT( comment_ID )' ) )
			)
		);

		$rows = array();

		foreach ( $comments as $index => $comment ) {
			if ( ! isset( $totals[ $index ] ) ) {
				break;
			}

			$rows[] = (object) array(
				'comment_author' => $comment->comment_author,
				'comment_total'  => (int) $totals[ $index ],
			);
		}

		return $rows;
	}

	/**
	 * How many approved comments a named author has left.
	 *
	 * @param string $author Comment author name.
	 * @return int
	 */
	public static function count_comments_by_author( $author ) {
		return (int) self::comment_query(
			array(
				'status'      => 'approve',
				'type'        => 'comment',
				'post_status' => 'publish',
				'count'       => true,
			),
			array( 'where' => self::author_clause( $author ) )
		);
	}

	/**
	 * One page of a named author's approved comments.
	 *
	 * Ordered by post first, so the view can group consecutive comments on the
	 * same post under one heading.
	 *
	 * @param string $author  Comment author name.
	 * @param int    $offset  Row offset.
	 * @param int    $perpage Rows per page.
	 * @return WP_Comment[]
	 */
	public static function comments_by_author( $author, $offset, $perpage ) {
		return (array) self::comment_query(
			array(
				'status'      => 'approve',
				'type'        => 'comment',
				'post_status' => 'publish',
				'number'      => max( 1, (int) $perpage ),
				'offset'      => max( 0, (int) $offset ),
				'orderby'     => array(
					'comment_post_ID' => 'DESC',
					'comment_date'    => 'DESC',
				),
			),
			array( 'where' => self::author_clause( $author ) )
		);
	}

	/**
	 * Link categories that have at least one link.
	 *
	 * @return WP_Term[]
	 */
	public static function link_categories() {
		$terms = get_terms( array( 'taxonomy' => 'link_category' ) );

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Post tags, most used first.
	 *
	 * @return WP_Term[]
	 */
	public static function tags() {
		$tags = get_tags(
			array(
				'orderby' => 'count',
				'order'   => 'DESC',
			)
		);

		return is_wp_error( $tags ) ? array() : $tags;
	}

	/**
	 * Akismet's spam count, when Akismet is active.
	 *
	 * Akismet publishes no filter for this and is not one of the plugins the
	 * wp_stats_sections contract covers, so asking whether its class is loaded
	 * is the only way to offer the line at all.
	 *
	 * @return int|null Null when Akismet is not available.
	 */
	public static function spam_count() {
		if ( ! class_exists( 'Akismet_Admin' ) ) {
			return null;
		}

		return (int) Akismet_Admin::get_spam_count();
	}

	/**
	 * Turn a row limit into what WP_Comment_Query means by "no limit".
	 *
	 * Zero is an empty string there, not a number: 'number' => 0 becomes
	 * LIMIT 0 and the query returns nothing at all.
	 *
	 * @param int $limit Maximum rows, 0 for all.
	 * @return int|string
	 */
	protected static function number( $limit ) {
		$limit = max( 0, (int) $limit );

		return $limit > 0 ? $limit : '';
	}

	/**
	 * Turn a post-type mode into something the query APIs understand.
	 *
	 * 'both' and '' mean "do not filter by type".
	 *
	 * @param string $mode Post type, 'both', or ''.
	 * @return string
	 */
	protected static function post_types( $mode ) {
		if ( ! empty( $mode ) && 'both' !== $mode ) {
			return $mode;
		}

		return 'any';
	}

	/**
	 * Run a comment query with extra SQL clauses merged in.
	 *
	 * WP_Comment_Query builds the joins, the status handling, the caching and
	 * the escaping; this only adds the fragment it has no argument for, and
	 * removes the filter again immediately so no other comment query on the
	 * request sees it.
	 *
	 * @param array $args    Arguments for get_comments().
	 * @param array $clauses Clause fragments to merge over the built ones.
	 * @return mixed Whatever get_comments() returns for those arguments.
	 */
	protected static function comment_query( array $args, array $clauses = array() ) {
		if ( ! $clauses ) {
			return get_comments( $args );
		}

		/*
		 * WP_Comment_Query keys its result cache on its own arguments and knows
		 * nothing about a clause added from outside, so two calls differing
		 * only in the clause - one comment author's count and the next one's -
		 * would collide and the second would be handed the first one's rows.
		 * cache_domain is part of that key and part of nothing else, which is
		 * exactly what is needed to tell them apart.
		 */
		$args['cache_domain'] = 'wp-stats-' . md5( (string) wp_json_encode( $clauses ) );

		$filter = static function ( $pieces ) use ( $clauses ) {
			foreach ( $clauses as $piece => $fragment ) {
				if ( 'where' === $piece ) {
					$pieces['where'] .= $fragment;
					continue;
				}

				$pieces[ $piece ] = $fragment;
			}

			return $pieces;
		};

		add_filter( 'comments_clauses', $filter );

		try {
			return get_comments( $args );
		} finally {
			remove_filter( 'comments_clauses', $filter );
		}
	}

	/**
	 * WHERE fragment restricting a comment query to one author name.
	 *
	 * @param string $author Comment author name.
	 * @return string
	 */
	protected static function author_clause( $author ) {
		global $wpdb;

		return $wpdb->prepare( ' AND comment_author = %s', (string) $author ) . self::unprotected_posts_clause();
	}

	/**
	 * WHERE fragment excluding comments on password-protected posts.
	 *
	 * Every listing on the statistics page excludes them, which is why the old
	 * "Comments Protected" branch in the author view could never run.
	 * WP_Comment_Query joins the posts table whenever a post_* argument is
	 * given and every caller of this passes post_status, so the column is in
	 * scope. There is no placeholder in it, so it needs no prepare().
	 *
	 * @return string
	 */
	protected static function unprotected_posts_clause() {
		global $wpdb;

		return " AND {$wpdb->posts}.post_password = ''";
	}
}
