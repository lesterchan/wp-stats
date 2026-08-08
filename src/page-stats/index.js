/**
 * The `wp-stats/page-stats` block.
 *
 * The statistics page the `[page_stats]` shortcode renders. It takes no
 * attributes, because the shortcode takes none either -- what the page shows is
 * settings, not something typed into a post.
 *
 * The block name is hyphenated where the shortcode is underscored: a block name
 * must match [a-z0-9-] and an underscore is not allowed in one. That is the
 * only reason the two spellings differ.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

/**
 * The editor view.
 *
 * Capitalised and named rather than an `edit()` shorthand because useBlockProps
 * is a React hook, and the hook rules identify a component by that capital.
 *
 * @return {Element} The editor view.
 */
function Edit() {
	return (
		<div { ...useBlockProps() }>
			{ /* Every commenter's name on the page is a link into the
			     per-commenter view, and following one from inside the editor
			     canvas navigates away from the post being written. */ }
			<div inert="">
				<ServerSideRender block={ metadata.name } />
			</div>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
