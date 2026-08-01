# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

WP-Stats follows `_standards/STANDARDS.md` in the parent folder, which is the
contract for all nineteen plugins in the collection. Where this file and that
one disagree, that one wins.

## What it is

A statistics page — post, comment, user, category, tag and link totals, "most
commented", "recent comments", a per-commenter drill-down — available as the
`[page_stats]` shortcode on the front end, as a Dashboard-style admin screen,
and as a sidebar widget. It stores no statistics of its own; everything is
counted on read.

**WP-Stats owns the one cross-plugin contract in the collection.** Read §13 of
STANDARDS.md before changing anything in `WP_Stats_Page`.

## The `wp_stats_sections` contract

Six siblings (wp-email, wp-polls, wp-postratings, wp-postviews, wp-useronline,
wp-downloadmanager) put blocks on the statistics page. Since 3.0.0 they do it by
answering one filter:

```php
$sections = apply_filters( 'wp_stats_sections', array() );
```

Each returns one entry keyed by its own `{{UNDER}}`, holding `title`, `priority`
and `render`. WP-Stats sorts by priority, ties broken by `strcmp()` on the key,
and fires `wp_stats_section_{key}` for each — its **own** listener at priority 10
echoes the title and calls `render`, which is what lets a theme replace one
block by hooking earlier and removing that listener.

Rules that the tests in `tests/test-sections.php` pin, and that must not drift:

* **WP-Stats reads no sibling's option row.** `test_wp_stats_reads_no_sibling_option_row`.
  Before 3.0.0 it had to know the names of every panel each of six plugins owned,
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
`stats_display`) and `wp_stats_version`. Per §13.2 the migration deletes the
shared rows — **and `uninstall.php` must not.** Up to six siblings that have not
upgraded are still reading them.

`_standards/RESUME.md` records that wp-stats (with wp-polls) is missing the
"Update all seven WP-Stats plugins together" line the other five carry, and that
two family tests are failing on it. Left failing deliberately.

## Traps

* **One menu, one page, two tabs** (`Statistics` / `Settings`), per §4.2.1. Both
  screens moved: statistics from `Dashboard → WP-Stats`, settings from
  `Settings → Stats`. There are no submenu entries.
* **`wp_stats_capability` is filtered per *tab*, not per screen**, with
  `statistics` and `settings` contexts, and the page itself registers with
  whichever capability the current user holds. §4.2.1 spells out why this matters:
  give the page the lower capability and forget the per-tab check, and filtering
  the report down to `list_users` silently opens the settings form to that role.
  That is privilege escalation dressed as a layout change.
* **The dashicon is `chart-area`, not `chart-bar`.** wp-polls had the same icon
  until somebody noticed they were indistinguishable at 20px; wp-polls draws
  bars, so it kept `chart-bar` (§4.1, commit `59c79df`).
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
  `$_GET`, so WordPress has already unslashed them and
  `WP_Stats::register_query_vars()` is the single list of what the page accepts.
* §7.2.1: this is the plugin whose `test_no_jquery_is_enqueued()` failed because
  the needle `wp_enqueue_script` (no bracket) is a substring of the action name
  `wp_enqueue_scripts`, which it legitimately hooks to enqueue a *stylesheet*.
  Four siblings carried the same bug, passing only for want of a sheet.

## Tests

`tests/test-sections.php` is the contract's specification in executable form —
read it before changing the filter. `tests/e2e/` is ten specs and 84 tests, among
the twelve suites `_standards/RESUME.md` lists as never run to green; the third
agent got it to 46/94 before the environment was torn down.
