<?php
/**
 * WP-Stats class-stats-settings.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Settings -> Stats screen.
 *
 * Uses the Settings API for storage and the nonce/capability handling, but
 * keeps hand-written markup for the body: companion plugins inject raw checkbox
 * HTML through the wp_stats_page_admin_* filters, which a registered settings
 * section could not reproduce.
 *
 * @since 3.0.0
 */
class Stats_Settings {

	/**
	 * Settings group name, used by settings_fields().
	 */
	const GROUP = 'wp-stats';

	/**
	 * Menu slug.
	 */
	const SLUG = 'wp-stats-options';

	/**
	 * Upper bound on stored display toggles.
	 *
	 * Guards against an unbounded option; no realistic set of companion plugins
	 * comes close.
	 */
	const MAX_DISPLAY_KEYS = 200;

	/**
	 * Hook the screen up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Add the Settings submenu entry.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'Stats', 'wp-stats' ),
			__( 'Stats', 'wp-stats' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the single option.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			Stats_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => Stats_Options::defaults(),
			)
		);
	}

	/**
	 * Validate the submitted settings.
	 *
	 * @param mixed $input Raw value from the form.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = Stats_Options::get();

		$output = array(
			'url'        => isset( $input['url'] ) ? esc_url_raw( trim( $input['url'] ) ) : '',
			'most_limit' => isset( $input['most_limit'] ) ? max( 1, (int) $input['most_limit'] ) : 10,
		);

		// Only ticked boxes are posted, so start from every key we know about at
		// 0 and switch on the ones that came back.
		$known = array_fill_keys(
			array_keys( array_merge( Stats_Options::display_defaults(), $current['display'] ) ),
			0
		);

		$posted = isset( $input['display'] ) && is_array( $input['display'] ) ? $input['display'] : array();

		foreach ( $posted as $key ) {
			$key = sanitize_key( $key );

			if ( '' === $key ) {
				continue;
			}

			// Deliberately not restricted to keys we already know. A companion
			// plugin's checkbox is rendered by that plugin, and rejecting an
			// unrecognised key here would report "Settings saved" and silently
			// drop the toggle. The screen is already nonce- and
			// capability-guarded, so the only exposure is option size.
			if ( ! isset( $known[ $key ] ) && count( $known ) >= self::MAX_DISPLAY_KEYS ) {
				continue;
			}

			$known[ $key ] = 1;
		}

		$output['display'] = $known;

		Stats_Options::flush();

		return $output;
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-stats' ) );
		}

		$limit = Stats_Options::most_limit();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stats Options', 'wp-stats' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="stats_url"><?php esc_html_e( 'Stats URL', 'wp-stats' ); ?></label></th>
						<td>
							<input type="text" id="stats_url" name="<?php echo esc_attr( Stats_Options::OPTION ); ?>[url]" value="<?php echo esc_url( Stats_Options::url() ); ?>" size="50" dir="ltr" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'URL to the page holding the [page_stats] shortcode.', 'wp-stats' ); ?><br />
								<?php esc_html_e( 'Example: https://www.yoursite.com/blog/stats/', 'wp-stats' ); ?><br />
								<?php esc_html_e( 'Example: https://www.yoursite.com/blog/?page_id=2', 'wp-stats' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stats_mostlimit"><?php esc_html_e( 'Stats Most Limit', 'wp-stats' ); ?></label></th>
						<td>
							<input type="number" min="1" id="stats_mostlimit" name="<?php echo esc_attr( Stats_Options::OPTION ); ?>[most_limit]" value="<?php echo esc_attr( $limit ); ?>" size="2" class="small-text" />
							<p class="description"><?php esc_html_e( 'Top X Stats, where X is the most limit.', 'wp-stats' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Type Of Stats To Display', 'wp-stats' ); ?></th>
						<td>
							<p><strong><?php esc_html_e( 'General Stats', 'wp-stats' ); ?></strong></p>
							<?php
							echo wp_kses( wp_stats_checkbox( 'total_stats', __( 'Total', 'wp-stats' ) ), self::allowed_html() );

							/**
							 * Filter the General Stats checkboxes on the options screen.
							 *
							 * @param string $content Accumulated markup.
							 */
							echo wp_kses( apply_filters( 'wp_stats_page_admin_general', '' ), self::allowed_html() );
							?>

							<p><strong><?php esc_html_e( 'Plugin Stats', 'wp-stats' ); ?></strong></p>
							<?php
							/** This filter is documented in includes/class-stats-settings.php */
							echo wp_kses( apply_filters( 'wp_stats_page_admin_plugins', '' ), self::allowed_html() );
							?>

							<p><strong>
							<?php
							printf(
								/* translators: %s: Number of stats. */
								esc_html( _n( 'Top %s Recent Stat', 'Top %s Recent Stats', $limit, 'wp-stats' ) ),
								esc_html( number_format_i18n( $limit ) )
							);
							?>
							</strong></p>
							<?php
							echo wp_kses(
								wp_stats_checkbox(
									'recent_posts',
									sprintf(
										/* translators: %s: Number of posts. */
										_n( '%s Most Recent Post', '%s Most Recent Posts', $limit, 'wp-stats' ),
										number_format_i18n( $limit )
									)
								),
								self::allowed_html()
							);
							echo wp_kses(
								wp_stats_checkbox(
									'recent_comments',
									sprintf(
										/* translators: %s: Number of comments. */
										_n( '%s Most Recent Comment', '%s Most Recent Comments', $limit, 'wp-stats' ),
										number_format_i18n( $limit )
									)
								),
								self::allowed_html()
							);

							/** This filter is documented in includes/class-stats-settings.php */
							echo wp_kses( apply_filters( 'wp_stats_page_admin_recent', '' ), self::allowed_html() );
							?>

							<p><strong>
							<?php
							printf(
								/* translators: %s: Number of stats. */
								esc_html( _n( 'Top %s Most/Highest Stat', 'Top %s Most/Highest Stats', $limit, 'wp-stats' ) ),
								esc_html( number_format_i18n( $limit ) )
							);
							?>
							</strong></p>
							<?php
							echo wp_kses(
								wp_stats_checkbox(
									'commented_post',
									sprintf(
										/* translators: %s: Number of posts. */
										_n( '%s Most Commented Post', '%s Most Commented Posts', $limit, 'wp-stats' ),
										number_format_i18n( $limit )
									)
								),
								self::allowed_html()
							);
							echo wp_kses(
								wp_stats_checkbox(
									'commented_page',
									sprintf(
										/* translators: %s: Number of pages. */
										_n( '%s Most Commented Page', '%s Most Commented Pages', $limit, 'wp-stats' ),
										number_format_i18n( $limit )
									)
								),
								self::allowed_html()
							);

							/** This filter is documented in includes/class-stats-settings.php */
							echo wp_kses( apply_filters( 'wp_stats_page_admin_most', '' ), self::allowed_html() );
							?>

							<p><strong><?php esc_html_e( 'Authors Stats', 'wp-stats' ); ?></strong></p>
							<?php
							echo wp_kses( wp_stats_checkbox( 'authors', __( 'Authors', 'wp-stats' ) ), self::allowed_html() );

							/** This filter is documented in includes/class-stats-settings.php */
							echo wp_kses( apply_filters( 'wp_stats_page_admin_authors', '' ), self::allowed_html() );
							?>

							<p><strong><?php esc_html_e( 'Comments\' Members Stats', 'wp-stats' ); ?></strong></p>
							<?php
							echo wp_kses( wp_stats_checkbox( 'comment_members', __( 'Comment Members', 'wp-stats' ) ), self::allowed_html() );

							/** This filter is documented in includes/class-stats-settings.php */
							echo wp_kses( apply_filters( 'wp_stats_page_admin_comments_members', '' ), self::allowed_html() );
							?>

							<p><strong><?php esc_html_e( 'Misc Stats', 'wp-stats' ); ?></strong></p>
							<?php
							echo wp_kses( wp_stats_checkbox( 'post_cats', __( 'Post Categories', 'wp-stats' ) ), self::allowed_html() );
							echo wp_kses( wp_stats_checkbox( 'link_cats', __( 'Link Categories', 'wp-stats' ) ), self::allowed_html() );
							echo wp_kses( wp_stats_checkbox( 'tags_list', __( 'Tags List', 'wp-stats' ) ), self::allowed_html() );

							/** This filter is documented in includes/class-stats-settings.php */
							echo wp_kses( apply_filters( 'wp_stats_page_admin_misc', '' ), self::allowed_html() );
							?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Markup a companion plugin may contribute to the checkbox area.
	 *
	 * @return array
	 */
	protected static function allowed_html() {
		return array(
			'input'  => array(
				'type'    => array(),
				'name'    => array(),
				'id'      => array(),
				'value'   => array(),
				'checked' => array(),
				'class'   => array(),
			),
			'label'  => array(
				'for'   => array(),
				'class' => array(),
			),
			'br'     => array(),
			'p'      => array( 'class' => array() ),
			'strong' => array(),
			'em'     => array(),
			'span'   => array( 'class' => array() ),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'small'  => array(),
			'code'   => array(),
		);
	}
}
