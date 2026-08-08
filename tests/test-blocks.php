<?php
/**
 * Tests for the block.
 *
 * @package WP-Stats
 */

/**
 * The block, and the promise that it is an addition rather than a replacement.
 *
 * Most of what is worth asserting here is not "the block renders" -- that is one
 * line -- but the four things a later change could quietly break:
 *
 * * the shortcode still works, because it sits in published pages everywhere;
 * * the block and the shortcode render the *same* markup, because they are meant
 *   to share one renderer and nothing else checks that they still do;
 * * neither entry point is implemented in terms of the other, which is what
 *   stops one breaking the day somebody unregisters the other;
 * * a section contributed through `wp_stats_sections` reaches the block. That
 *   one is this plugin rather than the pattern: WP-Stats owns no statistics of
 *   its own beyond the counts it makes on read, and the "Plugins Stats" half of
 *   the page belongs to whichever companion plugins answered the filter. A block
 *   that collected them any other way would show a different page to the
 *   shortcode on every site that has one installed.
 *
 * No companion plugin is installed here and none is needed: the filter is the
 * whole contract, so the closure in contribute() is a contributor as far as
 * WP-Stats is concerned -- the same fixture tests/test-sections.php uses.
 */
class WP_Stats_Blocks_Test extends WP_Stats_TestCase {

	/**
	 * The block this plugin registers.
	 */
	const BLOCK = 'wp-stats/page-stats';

	/**
	 * The shortcode table as it stood before a test edited it.
	 *
	 * @var array
	 */
	private $shortcodes;

	/**
	 * Snapshot the global state these tests deliberately break.
	 *
	 * Two tests below unregister the shortcode or the block on purpose, to prove
	 * neither entry point is implemented in terms of the other. Both registries
	 * are process-global and WP_UnitTestCase restores neither, so without this
	 * the first such test silently disarms every test that runs after it -- and
	 * they fail with `[page_stats]` rendering as literal text, which reads as a
	 * broken shortcode rather than a leaky fixture.
	 */
	public function set_up() {
		parent::set_up();

		$this->shortcodes = $GLOBALS['shortcode_tags'];

		$this->restore_block();

		// Content for the page to count. The display toggles are left at the
		// defaults the base fixture restores, because what the block has to
		// agree with is whatever the shortcode would have shown.
		$this->seed_blog();
	}

	/**
	 * Put both registries back.
	 */
	public function tear_down() {
		$GLOBALS['shortcode_tags'] = $this->shortcodes;

		$this->restore_block();

		parent::tear_down();
	}

	/**
	 * Return the block registry to exactly the one registered block.
	 *
	 * Unregisters before registering rather than registering conditionally: the
	 * plugin has already registered it on `init` by the time any test runs, and
	 * registering a second time is a doing_it_wrong notice the suite fails on.
	 *
	 * @return void
	 */
	private function restore_block() {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK ) ) {
			unregister_block_type( self::BLOCK );
		}

		WP_Stats_Blocks::register();
	}

	/**
	 * Contribute one well-formed section, as a companion plugin would.
	 *
	 * @param string $key   Contributor key.
	 * @param string $title Heading.
	 * @return void
	 */
	private function contribute( $key, $title ) {
		add_filter(
			'wp_stats_sections',
			static function ( $sections ) use ( $key, $title ) {
				$sections[ $key ] = array(
					'title'  => $title,
					'render' => static function () use ( $key ) {
						echo '<ul><li>body of ' . esc_html( $key ) . '</li></ul>';
					},
				);

				return $sections;
			}
		);
	}

	// --- registration ----------------------------------------------------

	/**
	 * The block registers, under the prefixed name.
	 *
	 * The `wp-` prefix is deliberate and is the one place the naming rule for
	 * commands and namespaces does not carry: those drop it, because a collision
	 * there is survivable and visible. A block name is written into post_content
	 * and stays there for the life of the post, so a collision would render
	 * another plugin's block inside somebody's published pages.
	 *
	 * @return void
	 */
	public function test_the_block_registers_under_the_prefixed_name() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( self::BLOCK ), 'The statistics block registers.' );
		$this->assertFalse( $registry->is_registered( 'stats/page-stats' ), 'The unprefixed name is not also claimed.' );
	}

	/**
	 * The block is dynamic, so it carries a render callback.
	 *
	 * Without one a block saves its markup into post_content, and the whole
	 * reason a shortcode and a block can share a renderer is that neither does.
	 * It matters more here than in most plugins: every number on this page is
	 * counted on read, so markup frozen at save time would be a snapshot of the
	 * site as it stood the day somebody wrote the post.
	 *
	 * @return void
	 */
	public function test_the_block_is_dynamic() {
		$this->assertIsCallable(
			WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK )->render_callback,
			'The block renders server-side.'
		);
	}

	/**
	 * It takes no attributes, because the shortcode takes none.
	 *
	 * Which of the page's two views is drawn comes from the request rather than
	 * from the post, so an attribute here would be a setting the shortcode does
	 * not have and the two entry points could disagree.
	 *
	 * @return void
	 */
	public function test_the_block_declares_no_attributes_of_its_own() {
		$attributes = (array) WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK )->attributes;

		// Compared against core's own list rather than a literal, because core
		// adds those to every block and the list is core's to grow.
		$this->assertSame(
			array(),
			array_diff( array_keys( $attributes ), array_keys( WP_Block_Type::GLOBAL_ATTRIBUTES ) ),
			'The block declares nothing beyond the attributes core adds to every block.'
		);
	}

	// --- the shortcode survives -------------------------------------------

	/**
	 * Adding the block did not unregister the shortcode.
	 *
	 * If this ever fails, the block has stopped being an addition and become a
	 * replacement, and every published page holding `[page_stats]` renders
	 * literal text.
	 *
	 * @return void
	 */
	public function test_the_shortcode_is_still_registered() {
		$this->assertTrue( shortcode_exists( 'page_stats' ), 'The shortcode survives the block.' );
	}

	// --- the block and the shortcode agree --------------------------------

	/**
	 * The block and the shortcode render the same page identically.
	 *
	 * This is the assertion the whole design rests on. Two entry points that
	 * merely both work can drift; two that produce byte-identical markup are
	 * demonstrably going through one renderer.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_render_the_same_markup() {
		$block     = $this->render( array( 'WP_Stats_Blocks', 'render_page_stats' ) );
		$shortcode = do_shortcode( '[page_stats]' );

		$this->assertStringContainsString( 'id="GeneralStats"', $block, 'The block rendered the statistics page.' );
		$this->assertSame( $shortcode, $block, 'And it is what the shortcode renders.' );
		$this->assertMarkupIsClean( $block );
	}

	/**
	 * They agree on the per-commenter view too.
	 *
	 * The view is chosen by a query argument rather than by anything the entry
	 * point passes, so a block that read the request differently -- or not at
	 * all -- would show the overview where the shortcode shows the drill-down.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_agree_on_the_author_view() {
		set_query_var( 'stats_author', 'Normal Commenter' );

		$block     = $this->render( array( 'WP_Stats_Blocks', 'render_page_stats' ) );
		$shortcode = do_shortcode( '[page_stats]' );

		$this->assertStringContainsString( 'Normal Commenter', $block, 'The block rendered the drill-down.' );
		$this->assertStringNotContainsString( 'id="GeneralStats"', $block, 'And not the overview.' );
		$this->assertSame( $shortcode, $block, 'The two entry points agree.' );
	}

	// --- the contributed sections -----------------------------------------

	/**
	 * A section contributed through the filter appears in the block.
	 *
	 * WP-Stats renders whatever `wp_stats_sections` collects, and the block gets
	 * there by calling the same method the shortcode calls rather than by
	 * collecting anything itself. So this is really an assertion that it did not
	 * reimplement the collection: a block that built its own list would show
	 * this contributor nothing, or show it in a different order.
	 *
	 * @return void
	 */
	public function test_a_contributed_section_reaches_the_block() {
		$this->contribute( 'wp_polls', 'Polls' );

		$block = $this->render( array( 'WP_Stats_Blocks', 'render_page_stats' ) );

		$this->assertStringContainsString( 'id="PluginsStats"', $block, 'The Plugins Stats heading appears in the block.' );
		$this->assertStringContainsString( '<strong>Polls</strong>', $block, 'WP-Stats echoes the contributed title.' );
		$this->assertStringContainsString( 'body of wp_polls', $block, "The entry's render callback drew the body." );
		$this->assertSame( do_shortcode( '[page_stats]' ), $block, 'The shortcode shows the same contributor.' );
		$this->assertMarkupIsClean( $block );
	}

	/**
	 * Several contributors reach the block in the documented order.
	 *
	 * Priority ordering is the contract's, not the block's, and it holds here
	 * for the same reason the section appears at all: there is one collector.
	 *
	 * @return void
	 */
	public function test_contributed_sections_keep_their_order_in_the_block() {
		$this->contribute( 'wp_useronline', 'UserOnline' );
		$this->contribute( 'wp_downloadmanager', 'Downloads' );

		$block = $this->render( array( 'WP_Stats_Blocks', 'render_page_stats' ) );

		$this->assertStringContainsString( '<strong>Downloads</strong>', $block, 'Both contributors reached the block.' );
		$this->assertStringContainsString( '<strong>UserOnline</strong>', $block, 'Both contributors reached the block.' );

		$this->assertLessThan(
			strpos( $block, '<strong>UserOnline</strong>' ),
			strpos( $block, '<strong>Downloads</strong>' ),
			'Equal priorities break on the key, wp_downloadmanager before wp_useronline.'
		);
	}

	/**
	 * A site with no companion plugin gets no empty heading in the block either.
	 *
	 * @return void
	 */
	public function test_the_plugins_heading_is_absent_from_the_block_when_nothing_contributes() {
		$this->assertStringNotContainsString(
			'id="PluginsStats"',
			$this->render( array( 'WP_Stats_Blocks', 'render_page_stats' ) ),
			'With nothing contributing, the heading is absent rather than standing over nothing.'
		);
	}

	// --- neither is implemented in terms of the other ---------------------

	/**
	 * The block does not render by running the shortcode.
	 *
	 * Routing a block through do_shortcode() would break it outright the day
	 * anybody unregistered the shortcode. So: unregister it, and assert the
	 * block carries on rendering.
	 *
	 * @return void
	 */
	public function test_the_block_renders_with_the_shortcode_unregistered() {
		remove_shortcode( 'page_stats' );

		$this->assertStringContainsString(
			'id="GeneralStats"',
			$this->render( array( 'WP_Stats_Blocks', 'render_page_stats' ) ),
			'The block does not need the shortcode.'
		);
	}

	/**
	 * The shortcode does not render by running the block.
	 *
	 * The other direction of the same rule, and the one a later "tidy-up" is
	 * likelier to break, because making the shortcode a thin wrapper over the
	 * block reads as removing duplication.
	 *
	 * @return void
	 */
	public function test_the_shortcode_renders_with_the_block_unregistered() {
		unregister_block_type( self::BLOCK );

		$this->assertStringContainsString( 'id="GeneralStats"', do_shortcode( '[page_stats]' ), 'The shortcode does not need the block.' );
	}

	// --- rendering through the block parser -------------------------------

	/**
	 * A post holding the block comment renders the statistics page.
	 *
	 * The tests above call the callback directly, which does not prove the
	 * registration wired it to the name that gets saved into post_content. This
	 * goes through do_blocks(), the way a published page does.
	 *
	 * @return void
	 */
	public function test_a_saved_block_renders_through_the_block_parser() {
		$this->contribute( 'wp_postratings', 'Ratings' );

		$rendered = $this->render(
			static function () {
				return do_blocks( '<!-- wp:' . self::BLOCK . ' /-->' );
			}
		);

		$this->assertStringContainsString( 'id="GeneralStats"', $rendered, 'The saved block renders the page.' );
		$this->assertStringContainsString( '<strong>Ratings</strong>', $rendered, 'Contributors reach it through the parser too.' );
	}

	// --- the stylesheet ---------------------------------------------------

	/**
	 * A page holding the block gets the stylesheet, as one holding the shortcode
	 * does.
	 *
	 * The styles are for the paging strip in the per-commenter view, which is
	 * reached by following a link on the page itself -- so a page carrying the
	 * block and not the shortcode would send a visitor to an unstyled one. The
	 * enqueue used to ask about the shortcode alone, which was correct until
	 * there was a second way to put this page in a post.
	 *
	 * @return void
	 */
	public function test_a_page_holding_the_block_enqueues_the_stylesheet() {
		wp_dequeue_style( 'wp-stats' );

		$GLOBALS['post'] = get_post(
			self::factory()->post->create( array( 'post_content' => '<!-- wp:' . self::BLOCK . ' /-->' ) )
		);

		WP_Stats::enqueue_styles();

		$this->assertTrue( wp_style_is( 'wp-stats', 'enqueued' ), 'The block is a reason to load the stylesheet.' );
	}
}
