<?php
/**
 * The Stats sidebar widget.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Stats sidebar widget.
 *
 * Renamed from WP_Widget_Stats in 3.0.0. The id_base is unchanged, so the
 * stored `widget_stats` option and every placed instance carry over.
 *
 * @since 3.0.0
 */
class WP_Stats_Widget extends WP_Widget {

	/**
	 * Register the widget.
	 */
	public function __construct() {
		parent::__construct(
			'stats',
			__( 'Stats', 'wp-stats' ),
			array(
				'description'                 => __( 'WP-Stats statistics', 'wp-stats' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Field defaults, also the shape of a saved instance.
	 *
	 * @return array
	 */
	protected function defaults() {
		return array(
			'title'                     => __( 'Stats', 'wp-stats' ),
			'limit'                     => 10,
			'chars'                     => 200,
			'show_link'                 => 1,
			'stats_total_authors'       => 1,
			'stats_total_posts'         => 1,
			'stats_total_pages'         => 1,
			'stats_total_tags'          => 1,
			'stats_total_comments'      => 1,
			'stats_total_commenters'    => 1,
			'stats_total_links'         => 1,
			'stats_total_post_cat'      => 1,
			'stats_total_link_cat'      => 1,
			'stats_total_spam'          => 1,
			'stats_most_commented_post' => 1,
		);
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Saved settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );

		// Escaped before the filter, as this widget has always done, so a theme
		// filtering widget_title can still wrap the title in markup.
		$title = apply_filters( 'widget_title', esc_html( $instance['title'] ), $instance, $this->id_base );
		$limit = (int) $instance['limit'];
		$chars = (int) $instance['chars'];

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar markup from the theme; $title escaped above.
		echo $args['before_widget'] . $args['before_title'] . $title . $args['after_title'];

		$lines = array(
			'stats_total_authors'    => array(
				WP_Stats_Query::total_authors(),
				/* translators: %s: Number of authors. */
				_n_noop( '<strong>%s</strong> Author', '<strong>%s</strong> Authors', 'wp-stats' ),
			),
			'stats_total_posts'      => array(
				WP_Stats_Query::total_posts(),
				/* translators: %s: Number of posts. */
				_n_noop( '<strong>%s</strong> Post', '<strong>%s</strong> Posts', 'wp-stats' ),
			),
			'stats_total_pages'      => array(
				WP_Stats_Query::total_pages(),
				/* translators: %s: Number of pages. */
				_n_noop( '<strong>%s</strong> Page', '<strong>%s</strong> Pages', 'wp-stats' ),
			),
			'stats_total_tags'       => array(
				WP_Stats_Query::total_terms( 'post_tag' ),
				/* translators: %s: Number of tags. */
				_n_noop( '<strong>%s</strong> Tag', '<strong>%s</strong> Tags', 'wp-stats' ),
			),
			'stats_total_comments'   => array(
				WP_Stats_Query::total_comments(),
				/* translators: %s: Number of comments. */
				_n_noop( '<strong>%s</strong> Comment', '<strong>%s</strong> Comments', 'wp-stats' ),
			),
			'stats_total_commenters' => array(
				WP_Stats_Query::total_comment_posters(),
				/* translators: %s: Number of comment posters. */
				_n_noop( '<strong>%s</strong> Comment Poster', '<strong>%s</strong> Comment Posters', 'wp-stats' ),
			),
			'stats_total_links'      => array(
				WP_Stats_Query::total_links(),
				/* translators: %s: Number of links. */
				_n_noop( '<strong>%s</strong> Link', '<strong>%s</strong> Links', 'wp-stats' ),
			),
			'stats_total_post_cat'   => array(
				WP_Stats_Query::total_terms( 'category' ),
				/* translators: %s: Number of post categories. */
				_n_noop( '<strong>%s</strong> Post Category', '<strong>%s</strong> Post Categories', 'wp-stats' ),
			),
			'stats_total_link_cat'   => array(
				WP_Stats_Query::total_terms( 'link_category' ),
				/* translators: %s: Number of link categories. */
				_n_noop( '<strong>%s</strong> Link Category', '<strong>%s</strong> Link Categories', 'wp-stats' ),
			),
		);

		echo '<ul>' . "\n";
		echo '<li><strong>' . esc_html__( 'Total Stats', 'wp-stats' ) . '</strong>' . "\n";
		echo '<ul>' . "\n";

		foreach ( $lines as $key => $line ) {
			if ( empty( $instance[ $key ] ) ) {
				continue;
			}

			list( $number, $strings ) = $line;

			// The translated strings carry their own <strong> markup.
			echo '<li>' . wp_kses_post(
				sprintf(
					translate_nooped_plural( $strings, $number, 'wp-stats' ),
					number_format_i18n( $number )
				)
			) . '</li>' . "\n";
		}

		$spam = WP_Stats_Query::spam_count();

		if ( ! empty( $instance['stats_total_spam'] ) && null !== $spam ) {
			echo '<li>' . wp_kses_post(
				sprintf(
					/* translators: %s: Number of blocked spam comments. */
					_n( '<strong>%s</strong> Spam Blocked', '<strong>%s</strong> Spam Blockeds', $spam, 'wp-stats' ),
					number_format_i18n( $spam )
				)
			) . '</li>' . "\n";
		}

		echo '</ul>' . "\n";
		echo '</li>' . "\n";
		echo '</ul>' . "\n";

		if ( ! empty( $instance['stats_most_commented_post'] ) ) {
			echo '<ul>' . "\n";
			echo '<li><strong>' . esc_html( number_format_i18n( $limit ) ) . ' ' . esc_html__( 'Most Commented Posts', 'wp-stats' ) . '</strong>' . "\n";
			echo '<ul>' . "\n";
			echo wp_kses_post( WP_Stats_Display::most_commented( 'post', $limit, $chars ) );
			echo '</ul>' . "\n";
			echo '</li>' . "\n";
			echo '</ul>' . "\n";
		}

		if ( ! empty( $instance['show_link'] ) ) {
			echo '<ul>' . "\n";
			echo '<li><a href="' . esc_url( WP_Stats_Options::url() ) . '">' . esc_html__( 'My Blog Statistics', 'wp-stats' ) . '</a></li>' . "\n";
			echo '</ul>' . "\n";
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar markup from the theme.
		echo $args['after_widget'];
	}

	/**
	 * Save the widget form.
	 *
	 * @param array $new_instance Submitted values.
	 * @param array $old_instance Previously saved values.
	 * @return array|false
	 */
	public function update( $new_instance, $old_instance ) {
		// The form posts a hidden `submit` field. Without it this is not our
		// form, and returning false leaves the stored instance alone.
		if ( ! isset( $new_instance['submit'] ) ) {
			return false;
		}

		$instance = (array) $old_instance;

		$instance['title'] = isset( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';

		foreach ( array_keys( $this->defaults() ) as $key ) {
			if ( 'title' === $key ) {
				continue;
			}

			$instance[ $key ] = isset( $new_instance[ $key ] ) ? (int) $new_instance[ $key ] : 0;
		}

		return $instance;
	}

	/**
	 * The widget's settings form.
	 *
	 * @param array $instance Saved settings.
	 * @return void
	 */
	public function form( $instance ) {
		$instance   = wp_parse_args( (array) $instance, $this->defaults() );
		$most_limit = WP_Stats_Options::most_limit();

		$toggles = array(
			'stats_total_authors'    => __( 'Total Authors', 'wp-stats' ),
			'stats_total_posts'      => __( 'Total Posts', 'wp-stats' ),
			'stats_total_pages'      => __( 'Total Pages', 'wp-stats' ),
			'stats_total_tags'       => __( 'Total Tags', 'wp-stats' ),
			'stats_total_comments'   => __( 'Total Comments', 'wp-stats' ),
			'stats_total_commenters' => __( 'Total Comment Posters', 'wp-stats' ),
			'stats_total_links'      => __( 'Total Links', 'wp-stats' ),
			'stats_total_post_cat'   => __( 'Total Post Categories', 'wp-stats' ),
			'stats_total_link_cat'   => __( 'Total Link Categories', 'wp-stats' ),
		);

		if ( class_exists( 'Akismet_Admin' ) ) {
			$toggles['stats_total_spam'] = __( 'Total Spam Blocked', 'wp-stats' );
		}
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'wp-stats' ); ?>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			</label>
		</p>
		<p>
			<?php esc_html_e( 'Statistics To Display:', 'wp-stats' ); ?><br />
			<?php foreach ( $toggles as $key => $label ) : ?>
				<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" value="1" <?php checked( 1, (int) $instance[ $key ] ); ?> />&nbsp;&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $label ); ?></label>
				<br />
			<?php endforeach; ?>
			<br />
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'stats_most_commented_post' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'stats_most_commented_post' ) ); ?>" value="1" <?php checked( 1, (int) $instance['stats_most_commented_post'] ); ?> />&nbsp;&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( 'stats_most_commented_post' ) ); ?>">
			<?php
			printf(
				/* translators: %s: Number of posts. */
				esc_html( _n( '%s Most Commented Post', '%s Most Commented Posts', $most_limit, 'wp-stats' ) ),
				esc_html( number_format_i18n( $most_limit ) )
			);
			?>
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'No. Of Records To Show:', 'wp-stats' ); ?> *
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['limit'] ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'chars' ) ); ?>"><?php esc_html_e( 'Maximum Post Title Length (Characters):', 'wp-stats' ); ?> *
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'chars' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'chars' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['chars'] ); ?>" />
			</label><br />
			<span class="description"><?php echo wp_kses_post( __( '<strong>0</strong> to disable.', 'wp-stats' ) ); ?></span>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_link' ) ); ?>"><?php esc_html_e( 'Show Link To Statistics Page?', 'wp-stats' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'show_link' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_link' ) ); ?>" class="widefat">
					<option value="0"<?php selected( 0, (int) $instance['show_link'] ); ?>><?php esc_html_e( 'No', 'wp-stats' ); ?></option>
					<option value="1"<?php selected( 1, (int) $instance['show_link'] ); ?>><?php esc_html_e( 'Yes', 'wp-stats' ); ?></option>
				</select>
			</label>
		</p>
		<p class="description"><?php esc_html_e( '* Used only in most commented post.', 'wp-stats' ); ?></p>
		<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'submit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'submit' ) ); ?>" value="1" />
		<?php
	}
}
