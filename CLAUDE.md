# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What it is

A statistics page — post, comment, user, category, tag and link totals, "most
commented", "recent comments", a per-commenter drill-down — available as the
`[page_stats]` shortcode on the front end, as the `wp-stats/page-stats` block,
as a Dashboard-style admin screen, and as a sidebar widget. It stores no
statistics of its own; everything is counted on read.

**The block and the shortcode are siblings over `WP_Stats_Page::render()`**, and
neither calls the other — no `do_shortcode()` in the block path. That is not
tidiness: `render()` is where `wp_stats_sections` is fired, so going through it
is the only thing that makes the block show the same contributed sections the
shortcode shows. `tests/test-blocks.php` pins that the two render
byte-identical markup and that unregistering either leaves the other working.
The shortcode is not deprecated and is not going anywhere.

The block name is hyphenated where the shortcode is underscored because a block
name may not contain an underscore, and it keeps the `wp-` prefix because a
block name is written into `post_content` and outlives the post. Its sources are
in `src/`, committed and excluded from the deploy; `bin/build` compiles them to
`build/`, which is gitignored and *is* shipped, since that is what
`register_block_type_from_metadata()` loads. `bin/build` also writes the
silence-is-golden `index.php` guards into `build/`, walked rather than listed,
because webpack knows nothing about that rule.

**This plugin owns a contract other plugins depend on.** Read the section below
before changing anything in `WP_Stats_Page`.

## The `wp_stats_sections` contract

Companion plugins — WP-EMail, WP-Polls, WP-PostRatings, WP-PostViews,
WP-UserOnline and WP-DownloadManager — put blocks on the statistics page. Since
3.0.0 they do it by answering one filter:

```php
$sections = apply_filters( 'wp_stats_sections', array() );
```

Each returns one entry keyed by its own prefix (`wp_polls`, `wp_postviews`, …),
holding `title`, `priority` and `render`. WP-Stats sorts by priority, ties broken by `strcmp()` on the key,
and fires `wp_stats_section_{key}` for each — its **own** listener at priority 10
echoes the title and calls `render`, which is what lets a theme replace one
block by hooking earlier and removing that listener.

Rules that the tests in `tests/test-sections.php` pin, and that must not drift:

* **WP-Stats reads no other plugin's option row.** `test_wp_stats_reads_no_sibling_option_row`.
  Before 3.0.0 it had to know the names of every panel each companion owned,
  and read the shared `stats_display` row to find out whether it was allowed to
  draw them. That is why a section could be rendered for a plugin that was not
  installed.
* **A malformed entry is skipped, never fatal.** A sibling is third-party code as
  far as WP-Stats is concerned. Skip when the key is not a non-empty string, the
  entry is not an array, `title` is missing/empty/not a string, or `render` is
  not `is_callable()`. A non-array filter return yields no sections.
* **A missing or non-numeric `priority` becomes 10** rather than invalidating the
  entry.
* **`render` echoes; it does not return.** The shortcode assembles the page with
  `ob_start()`, so a returned string is silently dropped.
* **The "Plugins Stats" heading is absent when nothing contributed.**

Eleven hooks were removed to make this: nine `wp_stats_page_*` /
`wp_stats_paging_*` filters, the seven `wp_stats_page_admin_*` filters and
`stats_display_defaults()`. `stats_page` became `wp_stats_page`.

## Data

`wp_stats_options` (absorbing `stats_url`, `stats_mostlimit` and the shared
`stats_display`) and `wp_stats_version`, which holds the `plugin` and `db`
upgrade markers and nothing else.

`stats_display` was **this plugin's own row**, but the companion plugins wrote
their toggles into it, so the migration has to keep the toggles WP-Stats owns
and drop theirs — they are not its to hold, and each companion reads the same
row in its own migration. The migration deletes the shared rows once it has
folded them in; **`uninstall.php` must not**, because a companion that has not
upgraded is still reading them.

The README carries the "Update all seven WP-Stats plugins together" note in its
Upgrade Notice, matching its companions; family tests pin it, so removing the
line turns them red.

## Traps

* **One menu, one page, two tabs** (`Statistics` / `Settings`). Both screens
  moved: statistics from `Dashboard → WP-Stats`, settings from
  `Settings → Stats`. There are no submenu entries.
* **`wp_stats_capability` is filtered per *tab*, not per screen**, with
  `statistics` and `settings` contexts, and the page itself registers with
  whichever capability the current user holds. Both halves matter: give the page
  the lower capability and forget the per-tab check, and filtering the report
  down to `list_users` silently opens the settings form to that role. That is
  privilege escalation dressed as a layout change.
* **The dashicon is `chart-area`, not `chart-bar`** (commit `59c79df`).
  WP-Polls draws bars and keeps `chart-bar`; the two were indistinguishable at
  20px while both used it.
* **`WP_Stats_Query` is all core query APIs now**, not hand-written SQL: results
  go through core's object caches and a site's content-hiding filters still
  apply. Three methods group or count-distinct on `comment_author`, which
  `WP_Comment_Query` has no argument for — those **add a clause to the query core
  builds** rather than writing SQL from scratch, so the joins, status handling and
  caching stay core's. Do not replace them with raw `$wpdb`.
* **`total_authors()` counts `publish_posts`, not `user_level > 1`.** The old
  query also excluded anyone with a `user_activation_key` set — i.e. anyone with
  a password reset in flight, which has nothing to do with authorship.
* **`WP_Stats_Display` passes post IDs instead of juggling the `$post` global.**
  Every block used to assign `$post` inside its loop and remember to restore it.
  `get_the_title()` and `get_permalink()` have always taken an ID.
* **The rendered markup, down to the trailing newlines, and the heading `id`
  attributes are reproduced exactly from before 3.0.0**, because themes style and
  link to them. A tidy-up here is a visible regression on somebody's site.
* **The stylesheet is scoped to WP-Stats' own markup now.** It used to restyle
  WP-PageNavi, which is why the plugin used to switch its own styles off when
  WP-PageNavi was active. Both halves of that are gone.
* `stats_author` and `stats_page` arrive as registered query vars, not from
  `$_GET`, so `WP_Stats::register_query_vars()` is the single list of what the
  page accepts. **Registering a query var does not unslash it** —
  `WP::parse_request()` copies it out of `$_GET` after `wp_magic_quotes()` has
  slashed it. `WP_Stats_Page::render()` has to `wp_unslash()` before
  `sanitize_text_field()`, which does not remove backslashes; the docblock here
  and in the source used to claim the opposite, and "Sinead O'Brien" got an
  empty drill-down for it.
* **The stylesheet gate asks about both spellings of the page.**
  `needs_styles()` used to ask only whether the post carried the shortcode,
  which was the whole answer until the block existed. It now asks
  `has_block()` too — the styles are for the paging strip in the per-commenter
  view, which is reached by following a link on the page itself, so a page
  carrying only the block would have sent a visitor to an unstyled one.
* **A needle without its bracket matches the wrong thing.**
  `test_no_jquery_is_enqueued()` searched for `wp_enqueue_script`, which is a
  substring of the action name `wp_enqueue_scripts` — legitimately hooked here
  to enqueue a *stylesheet*. Search for `wp_enqueue_script(` with the bracket.

## Migrations, and why they are tested through a browser

`maybe_upgrade()` hangs off `plugins_loaded`, so every request reaches it —
activation hooks do not fire on a plugin update, which is the usual reason a
migration never runs at all.

Two generations fold in at once: `stats_url`, `stats_mostlimit` and
`stats_display` are the 2.x rows, and `stats_options` is the consolidated row a
pre-release 3.0.0 build wrote before the name gained its prefix. A dedicated 2.x
row is the later word and wins over the copy inside that one.

Three things `tests/e2e/upgrade.spec.js` relies on:

* **A `wp eval` call is itself an upgrade request**, because WP-CLI reaches
  `plugins_loaded` like any other request. Seed the fixture and read it back
  inside *one* call; a second call finds the rows already migrated, and the
  browser request then has nothing left to do.
* **Read rows raw** — `WP_Stats_Options::get()` merges over the defaults, so it
  cannot tell a written row from an absent one.
* **A fresh install writes no settings row at all**, deliberately: there is
  nothing to carry over, so the row stays absent and every read merges the
  defaults. Only the markers are written, and only so the check does not run
  again. A test expecting a row there would be asserting a behaviour the plugin
  does not have.

## Tests

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

`tests/test-sections.php` is the contract's specification in executable form —
read it before changing the filter.
