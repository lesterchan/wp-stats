<?php
/**
 * WP-Stats class-wp-stats-query.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every database read the plugin makes.
 *
 * Kept apart from the markup so the counts can be tested without rendering
 * anything, and so there is one place to look when a query needs changing.
 *
 * @since 3.0.0
 */
class WP_Stats_Query {

	/**
	 * Number of users who can author content.
	 *
	 * Counts users whose `{$prefix}user_level` meta is above 1, i.e. author and
	 * above. WordPress still maintains that meta for backwards compatibility.
	 *
	 * @return int
	 */
	public static function total_authors() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM $wpdb->users LEFT JOIN $wpdb->usermeta ON $wpdb->usermeta.user_id = $wpdb->users.ID WHERE $wpdb->users.user_activation_key = '' AND $wpdb->usermeta.meta_key = %s AND (meta_value+0.00) > 1",
				$wpdb->prefix . 'user_level'
			)
		);
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
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_approved = '1'" );
	}

	/**
	 * Number of distinct names that have left an approved comment.
	 *
	 * @return int
	 */
	public static function total_comment_posters() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT comment_author) FROM $wpdb->comments WHERE comment_approved = '1' AND comment_type = 'comment'" );
	}

	/**
	 * Number of rows in the links table.
	 *
	 * @return int
	 */
	public static function total_links() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(link_id) FROM $wpdb->links" );
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
	 * Turn a post-type mode into a WHERE fragment and its prepare arguments.
	 *
	 * 'both' and '' mean "do not filter by type".
	 *
	 * @param string $mode Post type, 'both', or ''.
	 * @return array{0:string,1:array} Fragment and arguments.
	 */
	protected static function mode_where( $mode ) {
		if ( ! empty( $mode ) && 'both' !== $mode ) {
			return array( 'post_type = %s', array( $mode ) );
		}

		return array( '1=1', array() );
	}

	/**
	 * Most recently published posts.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @return array
	 */
	public static function recent_posts( $mode = '', $limit = 10 ) {
		global $wpdb;

		$limit                = max( 0, (int) $limit );
		list( $where, $args ) = self::mode_where( $mode );

		$args = array_merge( array( current_time( 'mysql' ) ), $args, array( $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is a literal fragment built above, holding only placeholders.
				"SELECT $wpdb->users.*, $wpdb->posts.* FROM $wpdb->posts LEFT JOIN $wpdb->users ON $wpdb->users.ID = $wpdb->posts.post_author WHERE user_activation_key = '' AND post_date < %s AND $where AND post_status = 'publish' AND post_password = '' ORDER BY post_date DESC LIMIT %d",
				$args
			)
		);
	}

	/**
	 * Most recent approved comments.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @return array
	 */
	public static function recent_comments( $mode = '', $limit = 10 ) {
		global $wpdb;

		$limit                = max( 0, (int) $limit );
		list( $where, $args ) = self::mode_where( $mode );

		$args = array_merge( array( current_time( 'mysql' ) ), $args, array( $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is a literal fragment built above, holding only placeholders.
				"SELECT * FROM $wpdb->posts INNER JOIN $wpdb->comments ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID WHERE comment_approved = '1' AND comment_type = 'comment' AND post_date < %s AND $where AND post_status = 'publish' AND post_password = '' ORDER BY comment_date DESC LIMIT %d",
				$args
			)
		);
	}

	/**
	 * Posts ordered by approved comment count.
	 *
	 * @param string $mode  Post type, 'both', or ''.
	 * @param int    $limit Maximum rows.
	 * @return array
	 */
	public static function most_commented( $mode = '', $limit = 10 ) {
		global $wpdb;

		$limit                = max( 0, (int) $limit );
		list( $where, $args ) = self::mode_where( $mode );

		$args = array_merge( array( current_time( 'mysql' ) ), $args, array( $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is a literal fragment built above, holding only placeholders.
				"SELECT $wpdb->posts.*, COUNT($wpdb->comments.comment_post_ID) AS 'comment_total' FROM $wpdb->posts LEFT JOIN $wpdb->comments ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID WHERE comment_approved = '1' AND post_date < %s AND $where AND post_status = 'publish' AND post_password = '' GROUP BY $wpdb->comments.comment_post_ID ORDER BY comment_total DESC LIMIT %d",
				$args
			)
		);
	}

	/**
	 * Published post counts per author.
	 *
	 * @param string $mode Post type, 'both', or ''.
	 * @return array
	 */
	public static function author_post_counts( $mode = '' ) {
		global $wpdb;

		list( $where, $args ) = self::mode_where( $mode );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is a literal fragment built above, holding only placeholders.
		$sql = "SELECT COUNT($wpdb->posts.ID) AS 'posts_total', $wpdb->users.display_name, $wpdb->users.user_nicename FROM $wpdb->posts LEFT JOIN $wpdb->users ON $wpdb->users.ID = $wpdb->posts.post_author WHERE user_activation_key = '' AND $where AND post_status = 'publish' GROUP BY $wpdb->posts.post_author";

		// prepare() warns when handed no arguments, so only call it when the
		// mode actually contributed a placeholder.
		return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql );
	}

	/**
	 * Approved comment counts per comment author name.
	 *
	 * @param int $limit Maximum rows, 0 for all.
	 * @return array
	 */
	public static function comment_author_counts( $limit = 0 ) {
		global $wpdb;

		$limit     = max( 0, (int) $limit );
		$args      = array( current_time( 'mysql' ) );
		$limit_sql = '';

		if ( $limit > 0 ) {
			$limit_sql = 'LIMIT %d';
			$args[]    = $limit;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $limit_sql is a literal fragment built above, holding only a placeholder.
				"SELECT comment_author, COUNT(comment_ID) AS 'comment_total' FROM $wpdb->comments INNER JOIN $wpdb->posts ON $wpdb->comments.comment_post_ID = $wpdb->posts.ID WHERE comment_approved = '1' AND comment_type = 'comment' AND post_date < %s AND post_status = 'publish' AND post_password = '' GROUP BY comment_author ORDER BY comment_total DESC $limit_sql",
				$args
			)
		);
	}

	/**
	 * How many approved comments a named author has left.
	 *
	 * @param string $author Comment author name.
	 * @return int
	 */
	public static function count_comments_by_author( $author ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(comment_ID) FROM $wpdb->comments INNER JOIN $wpdb->posts ON $wpdb->comments.comment_post_ID = $wpdb->posts.ID WHERE comment_author = %s AND comment_approved = '1' AND comment_type = 'comment' AND post_date < %s AND post_status = 'publish' AND post_password = ''",
				$author,
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * One page of a named author's approved comments.
	 *
	 * @param string $author  Comment author name.
	 * @param int    $offset  Row offset.
	 * @param int    $perpage Rows per page.
	 * @return array
	 */
	public static function comments_by_author( $author, $offset, $perpage ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT $wpdb->posts.*, $wpdb->comments.* FROM $wpdb->comments INNER JOIN $wpdb->posts ON $wpdb->comments.comment_post_ID = $wpdb->posts.ID WHERE comment_author = %s AND comment_approved = '1' AND comment_type = 'comment' AND post_date < %s AND post_status = 'publish' AND post_password = '' ORDER BY comment_post_ID DESC, comment_date DESC LIMIT %d, %d",
				$author,
				current_time( 'mysql' ),
				max( 0, (int) $offset ),
				max( 1, (int) $perpage )
			)
		);
	}

	/**
	 * Link categories that have at least one link.
	 *
	 * @return array
	 */
	public static function link_categories() {
		$terms = get_terms( array( 'taxonomy' => 'link_category' ) );

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Post tags, most used first.
	 *
	 * @return array
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
	 * @return int|null Null when Akismet is not available.
	 */
	public static function spam_count() {
		if ( ! class_exists( 'Akismet_Admin' ) ) {
			return null;
		}

		return (int) Akismet_Admin::get_spam_count();
	}
}
