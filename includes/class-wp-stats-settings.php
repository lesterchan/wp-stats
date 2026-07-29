<?php
/**
 * The WP-Stats -> Settings screen.
 *
 * Built entirely on the Settings API: register_setting() for storage and for
 * the nonce and capability handling, add_settings_section() and
 * add_settings_field() for the body, do_settings_sections() to draw it. The
 * screen writes no table markup of its own.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the settings screen.
 *
 * @since 3.0.0
 */
class WP_Stats_Settings {

	/**
	 * Settings group name, used by settings_fields(). The same name as the
	 * option row it writes, so the group and the row cannot drift apart.
	 */
	const GROUP = 'wp_stats_options';

	/**
	 * Menu slug, and the page name the sections are registered against.
	 */
	const PAGE = 'wp-stats-settings';

	/**
	 * Section holding the scalar settings.
	 */
	const SECTION_GENERAL = 'wp_stats_general';

	/**
	 * Section holding the display toggles.
	 */
	const SECTION_DISPLAY = 'wp_stats_display';

	/**
	 * Hook the screen up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the single option, and the sections and fields that edit it.
	 *
	 * Idempotent: every call registers the same ids, so re-running only
	 * refreshes the labels that count in the stored limit.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			WP_Stats_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => WP_Stats_Options::defaults(),
			)
		);

		// An untitled section renders no heading of its own, so the two scalar
		// settings open the screen as a plain form table.
		add_settings_section( self::SECTION_GENERAL, '', '__return_false', self::PAGE );

		add_settings_field(
			'url',
			__( 'Stats URL', 'wp-stats' ),
			array( __CLASS__, 'field_url' ),
			self::PAGE,
			self::SECTION_GENERAL,
			array( 'label_for' => 'wp_stats_url' )
		);

		add_settings_field(
			'most_limit',
			__( 'Stats Most Limit', 'wp-stats' ),
			array( __CLASS__, 'field_most_limit' ),
			self::PAGE,
			self::SECTION_GENERAL,
			array( 'label_for' => 'wp_stats_most_limit' )
		);

		add_settings_section(
			self::SECTION_DISPLAY,
			__( 'Type Of Stats To Display', 'wp-stats' ),
			'__return_false',
			self::PAGE
		);

		// Registration order is document order, and it is the order the blocks
		// appear in on the statistics page itself.
		foreach ( self::display_rows() as $row => $config ) {
			add_settings_field(
				$row,
				$config['title'],
				array( __CLASS__, 'field_' . $row ),
				self::PAGE,
				self::SECTION_DISPLAY
			);
		}
	}

	/**
	 * The display toggles, grouped into the screen's rows.
	 *
	 * One row per heading on the statistics page, so the screen reads in the
	 * same order as the thing it configures. Rebuilt on each call because half
	 * the labels count in the stored limit.
	 *
	 * @return array<string,array{title:string,checkboxes:array<string,string>}>
	 */
	protected static function display_rows() {
		$limit = WP_Stats_Options::most_limit();
		$count = number_format_i18n( $limit );

		return array(
			'general'         => array(
				'title'      => __( 'General Stats', 'wp-stats' ),
				'checkboxes' => array(
					'total_stats' => __( 'Total', 'wp-stats' ),
				),
			),
			'recent'          => array(
				/* translators: %s: Number of stats. */
				'title'      => sprintf( _n( 'Top %s Recent Stat', 'Top %s Recent Stats', $limit, 'wp-stats' ), $count ),
				'checkboxes' => array(
					/* translators: %s: Number of posts. */
					'recent_posts'    => sprintf( _n( '%s Most Recent Post', '%s Most Recent Posts', $limit, 'wp-stats' ), $count ),
					/* translators: %s: Number of comments. */
					'recent_comments' => sprintf( _n( '%s Most Recent Comment', '%s Most Recent Comments', $limit, 'wp-stats' ), $count ),
				),
			),
			'most'            => array(
				/* translators: %s: Number of stats. */
				'title'      => sprintf( _n( 'Top %s Most/Highest Stat', 'Top %s Most/Highest Stats', $limit, 'wp-stats' ), $count ),
				'checkboxes' => array(
					/* translators: %s: Number of posts. */
					'commented_post' => sprintf( _n( '%s Most Commented Post', '%s Most Commented Posts', $limit, 'wp-stats' ), $count ),
					/* translators: %s: Number of pages. */
					'commented_page' => sprintf( _n( '%s Most Commented Page', '%s Most Commented Pages', $limit, 'wp-stats' ), $count ),
				),
			),
			'authors'         => array(
				'title'      => __( 'Authors Stats', 'wp-stats' ),
				'checkboxes' => array(
					'authors' => __( 'Authors', 'wp-stats' ),
				),
			),
			'comment_members' => array(
				'title'      => __( 'Comments\' Members Stats', 'wp-stats' ),
				'checkboxes' => array(
					'comment_members' => __( 'Comment Members', 'wp-stats' ),
				),
			),
			'misc'            => array(
				'title'      => __( 'Misc Stats', 'wp-stats' ),
				'checkboxes' => array(
					'post_cats' => __( 'Post Categories', 'wp-stats' ),
					'link_cats' => __( 'Link Categories', 'wp-stats' ),
					'tags_list' => __( 'Tags List', 'wp-stats' ),
				),
			),
		);
	}

	/**
	 * Validate the submitted settings.
	 *
	 * A function from what the form posted to what gets stored, and nothing
	 * else: it reads no stored value, so it can neither preserve nor corrupt
	 * one. The upgrade markers live in their own row for exactly that reason.
	 *
	 * @param mixed $input Raw value from the form.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$display = isset( $input['display'] ) && is_array( $input['display'] ) ? $input['display'] : array();

		$output = array(
			'url'        => isset( $input['url'] ) ? sanitize_url( trim( (string) $input['url'] ) ) : '',
			'most_limit' => isset( $input['most_limit'] ) ? max( 1, (int) $input['most_limit'] ) : 10,
			'display'    => array(),
		);

		// Only ticked boxes are posted, so the toggles WP-Stats knows about are
		// walked rather than the input: anything missing is off, and nothing the
		// form did not offer can find its way into the row.
		foreach ( array_keys( WP_Stats_Options::display_defaults() ) as $key ) {
			$output['display'][ $key ] = empty( $display[ $key ] ) ? 0 : 1;
		}

		WP_Stats_Options::flush();

		return $output;
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( WP_Stats_Admin::capability( 'settings' ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-stats' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP-Stats Settings', 'wp-stats' ); ?></h1>

			<?php
			// Core only calls this from wp-admin/options-head.php, which runs
			// for its own settings screens. A plugin page is dispatched by
			// admin.php instead, so without this the save redirect lands back
			// here with no confirmation at all.
			settings_errors();
			?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The Stats URL field.
	 *
	 * @return void
	 */
	public static function field_url() {
		?>
		<input type="url" id="wp_stats_url" name="<?php echo esc_attr( WP_Stats_Options::OPTION ); ?>[url]" value="<?php echo esc_url( WP_Stats_Options::url() ); ?>" dir="ltr" class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'URL of the page holding the [page_stats] shortcode.', 'wp-stats' ); ?><br />
			<?php esc_html_e( 'Example: https://www.yoursite.com/blog/stats/', 'wp-stats' ); ?><br />
			<?php esc_html_e( 'Example: https://www.yoursite.com/blog/?page_id=2', 'wp-stats' ); ?>
		</p>
		<?php
	}

	/**
	 * The Stats Most Limit field.
	 *
	 * @return void
	 */
	public static function field_most_limit() {
		?>
		<input type="number" min="1" id="wp_stats_most_limit" name="<?php echo esc_attr( WP_Stats_Options::OPTION ); ?>[most_limit]" value="<?php echo esc_attr( WP_Stats_Options::most_limit() ); ?>" class="small-text" />
		<p class="description"><?php esc_html_e( 'Top X Stats, where X is the most limit.', 'wp-stats' ); ?></p>
		<?php
	}

	/**
	 * The General Stats row of toggles.
	 *
	 * @return void
	 */
	public static function field_general() {
		self::render_display_row( 'general' );
	}

	/**
	 * The Top X Recent Stats row of toggles.
	 *
	 * @return void
	 */
	public static function field_recent() {
		self::render_display_row( 'recent' );
	}

	/**
	 * The Top X Most/Highest Stats row of toggles.
	 *
	 * @return void
	 */
	public static function field_most() {
		self::render_display_row( 'most' );
	}

	/**
	 * The Authors Stats row of toggles.
	 *
	 * @return void
	 */
	public static function field_authors() {
		self::render_display_row( 'authors' );
	}

	/**
	 * The Comments' Members Stats row of toggles.
	 *
	 * @return void
	 */
	public static function field_comment_members() {
		self::render_display_row( 'comment_members' );
	}

	/**
	 * The Misc Stats row of toggles.
	 *
	 * @return void
	 */
	public static function field_misc() {
		self::render_display_row( 'misc' );
	}

	/**
	 * One row of display toggles.
	 *
	 * @param string $row Row name, a key of display_rows().
	 * @return void
	 */
	protected static function render_display_row( $row ) {
		$rows = self::display_rows();

		if ( ! isset( $rows[ $row ] ) ) {
			return;
		}

		// A group of checkboxes gets a fieldset, and the row's <th> is not a
		// <label> for any one of them, so the legend carries the group name for
		// screen readers.
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo esc_html( $rows[ $row ]['title'] ); ?></span></legend>
			<?php foreach ( $rows[ $row ]['checkboxes'] as $key => $label ) : ?>
				<label for="<?php echo esc_attr( 'wp_stats_display_' . $key ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( 'wp_stats_display_' . $key ); ?>" name="<?php echo esc_attr( WP_Stats_Options::OPTION ); ?>[display][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( WP_Stats_Options::display( $key ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
				<br />
			<?php endforeach; ?>
		</fieldset>
		<?php
	}
}
