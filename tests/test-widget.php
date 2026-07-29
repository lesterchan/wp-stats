<?php
/**
 * The Stats widget.
 *
 * @package WP-Stats
 */

/**
 * WP_Stats_Widget.
 */
class WP_Stats_Widget_Test extends WP_Stats_TestCase {

	/**
	 * Widget under test.
	 *
	 * @var WP_Stats_Widget
	 */
	private $widget;

	/**
	 * Sidebar arguments.
	 *
	 * @var array
	 */
	private $args = array(
		'before_widget' => '<aside>',
		'after_widget'  => '</aside>',
		'before_title'  => '<h3>',
		'after_title'   => '</h3>',
	);

	/**
	 * Seed a blog and build the widget.
	 */
	public function set_up() {
		parent::set_up();
		$this->seed_blog();
		$this->widget = new WP_Stats_Widget();
	}

	/**
	 * The id_base is what ties saved instances to the widget, so it must not
	 * change even though the class was renamed in 3.0.0.
	 */
	public function test_the_id_base_survived_the_rename() {
		$this->assertSame( 'stats', $this->widget->id_base );
		$this->assertSame( 'widget_stats', $this->widget->option_name );
	}

	/**
	 * A fully switched-on widget renders every line.
	 */
	public function test_it_renders_every_enabled_line() {
		$html = $this->render_widget( array( 'title' => 'My Stats' ) );

		$this->assertStringContainsString( '<aside>', $html );
		$this->assertStringContainsString( '<h3>My Stats</h3>', $html );
		$this->assertStringContainsString( 'Total Stats', $html );
		$this->assertStringContainsString( 'Most Commented Posts', $html );
		$this->assertStringContainsString( 'My Blog Statistics', $html );
		$this->assertStringContainsString( '</aside>', $html );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * Each toggle gates its own line.
	 *
	 * @dataProvider data_toggles
	 *
	 * @param string $key    Instance key to switch off.
	 * @param string $needle Text that must then be absent.
	 */
	public function test_a_toggle_gates_its_line( $key, $needle ) {
		$on = $this->render_widget( array( $key => 1 ) );
		$this->assertStringContainsString( $needle, $on );

		$off = $this->render_widget( array( $key => 0 ) );
		$this->assertStringNotContainsString( $needle, $off );
	}

	/**
	 * Widget toggles and the text each controls.
	 *
	 * @return array
	 */
	public function data_toggles() {
		// The needles have to be unambiguous: a bare "Link" also matches the
		// "Link Categories" line, so the off-case assertion would fail against
		// a line the toggle does not control.
		return array(
			'authors'        => array( 'stats_total_authors', 'Author' ),
			'links'          => array( 'stats_total_links', 'Link</li>' ),
			'link cats'      => array( 'stats_total_link_cat', 'Link Categor' ),
			'most commented' => array( 'stats_most_commented_post', 'Most Commented' ),
			'stats link'     => array( 'show_link', 'My Blog Statistics' ),
		);
	}

	/**
	 * Saving strips markup from the title and casts the numbers.
	 */
	public function test_update_sanitises() {
		$saved = $this->widget->update(
			array(
				'submit'              => 1,
				'title'               => '<b>Bold</b> Title',
				'limit'               => '7',
				'chars'               => '42',
				'show_link'           => '0',
				'stats_total_authors' => '1',
			),
			array()
		);

		$this->assertSame( 'Bold Title', $saved['title'] );
		$this->assertSame( 7, $saved['limit'] );
		$this->assertSame( 42, $saved['chars'] );
		$this->assertSame( 0, $saved['show_link'] );
		$this->assertSame( 1, $saved['stats_total_authors'] );
	}

	/**
	 * A post that is not this widget's form leaves the stored instance alone.
	 */
	public function test_update_ignores_a_foreign_post() {
		$this->assertFalse( $this->widget->update( array( 'title' => 'x' ), array( 'title' => 'kept' ) ) );
	}

	/**
	 * An unticked checkbox is not posted at all, so it must save as 0.
	 */
	public function test_update_turns_missing_checkboxes_off() {
		$saved = $this->widget->update( array( 'submit' => 1 ), array() );

		$this->assertSame( 0, $saved['stats_total_authors'] );
		$this->assertSame( 0, $saved['show_link'] );
	}

	/**
	 * The settings form renders cleanly.
	 */
	public function test_the_form_renders() {
		$html = $this->render(
			function () {
				$this->widget->form( array() );
			}
		);

		$this->assertStringContainsString( 'No. Of Records To Show', $html );
		$this->assertStringContainsString( 'widget-stats', $html );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * The form's markup is balanced; the last <p> used to be left unclosed.
	 */
	public function test_the_form_markup_is_balanced() {
		$html = $this->render(
			function () {
				$this->widget->form( array() );
			}
		);

		$this->assertSame(
			substr_count( $html, '<p>' ) + substr_count( $html, '<p ' ),
			substr_count( $html, '</p>' ),
			'Every <p> in the widget form should be closed.'
		);
	}

	/**
	 * Render the widget with an instance merged over an all-on default.
	 *
	 * @param array $instance Overrides.
	 * @return string
	 */
	private function render_widget( array $instance ) {
		$defaults = array(
			'title'                     => 'Stats',
			'limit'                     => 5,
			'chars'                     => 0,
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
			'stats_total_spam'          => 0,
			'stats_most_commented_post' => 1,
		);

		return $this->render(
			function () use ( $instance, $defaults ) {
				$this->widget->widget( $this->args, array_merge( $defaults, $instance ) );
			}
		);
	}
}
