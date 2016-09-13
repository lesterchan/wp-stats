<?php
/*
Plugin Name: WP-Stats
Plugin URI: http://lesterchan.net/portfolio/programming/php/
Description: Display your WordPress blog statistics. Ranging from general total statistics, some of my plugins statistics and top 10 statistics.
Version: 2.54
Author: Lester 'GaMerZ' Chan
Author URI: http://lesterchan.net
Text Domain: wp-stats
*/


/*
    Copyright 2016  Lester Chan  (email : lesterchan@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


### Create Text Domain For Translations
add_action( 'plugins_loaded', 'stats_textdomain' );
function stats_textdomain() {
    load_plugin_textdomain( 'wp-stats', false, dirname( plugin_basename( __FILE__ ) ) );
}


### Function: WP-Stats Menu
add_action('admin_menu', 'stats_menu');
function stats_menu() {
    add_submenu_page('index.php',  __('WP-Stats', 'wp-stats'),  __('WP-Stats', 'wp-stats'), 'manage_options', 'wp-stats/wp-stats.php', 'display_stats');
    add_options_page(__('Stats', 'wp-stats'), __('Stats', 'wp-stats'), 'manage_options', 'wp-stats/stats-options.php');
}


### Function: Enqueue Stats Stylesheets
add_action('wp_print_styles', 'stats_stylesheets');
function stats_stylesheets() {
    if(!function_exists('pagenavi_stylesheets')) {
        if(@file_exists(TEMPLATEPATH.'/stats-css.css')) {
            wp_enqueue_style('wp-stats', plugins_url(get_stylesheet_directory_uri().'/stats-css.css'), false, '2.50', 'all');
        } else {
            wp_enqueue_style('wp-stats', plugins_url('wp-stats/stats-css.css'), false, '2.50', 'all');
        }
    }
}


### Display WP-Stats Admin Page
function display_stats() {
    $stats_page = stats_page();
    echo '<div class="wrap">';
    echo $stats_page;
    echo '</div>';
}


### Function: Get Total Authors
function get_totalauthors($display = true) {
    global $wpdb;
    $totalauthors = intval($wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users LEFT JOIN $wpdb->usermeta ON $wpdb->usermeta.user_id = $wpdb->users.ID WHERE $wpdb->users.user_activation_key = '' AND $wpdb->usermeta.meta_key = '".$wpdb->prefix."user_level' AND (meta_value+0.00) > 1"));
    if($display) {
        echo $totalauthors;
    } else {
        return $totalauthors;
    }
}


### Function: Get Total Posts
function get_totalposts($display = true) {
    global $wpdb;
    $totalposts = intval($wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'"));
    if($display) {
        echo $totalposts;
    } else {
        return $totalposts;
    }
}


### Function: Get Total Pages
function get_totalpages($display = true) {
    global $wpdb;
    $totalpages = intval($wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'page' AND post_status = 'publish'"));
    if($display) {
        echo $totalpages;
    } else {
        return $totalpages;
    }
}


### Function: Get Total Comments
function get_totalcomments($display = true) {
    global $wpdb;
    $totalcomments = intval($wpdb->get_var("SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_approved = '1'"));
    if($display) {
        echo $totalcomments;
    } else {
        return $totalcomments;
    }
}


### Function: Get Total Comments Poster
function get_totalcommentposters($display = true) {
    global $wpdb;
    $totalcommentposters = intval($wpdb->get_var("SELECT COUNT(DISTINCT comment_author) FROM $wpdb->comments WHERE comment_approved = '1' AND comment_type = ''"));
    if($display) {
        echo $totalcommentposters;
    } else {
        return $totalcommentposters;
    }
}


### Function: Get Total Links
function get_totallinks($display = true) {
    global $wpdb;
    $totallinks = intval($wpdb->get_var("SELECT COUNT(link_id) FROM $wpdb->links"));
    if($display) {
        echo $totallinks;
    } else {
        return $totallinks;
    }
}


### Function: Get Recent Posts
function get_recentposts($mode = '', $limit = 10, $display = true) {
    global $wpdb, $post;
    $where = '';
    $temp = '';
    if(!empty($mode) && $mode != 'both') {
        $where = "post_type = '$mode'";
    } else {
        $where = '1=1';
    }
    $recentposts = $wpdb->get_results("SELECT $wpdb->users.*, $wpdb->posts.* FROM $wpdb->posts LEFT JOIN $wpdb->users ON $wpdb->users.ID = $wpdb->posts.post_author WHERE user_activation_key = '' AND post_date < '".current_time('mysql')."' AND $where AND post_status = 'publish' AND post_password = '' ORDER  BY post_date DESC LIMIT $limit");
    if($recentposts) {
        foreach ($recentposts as $post) {
            $post_title = get_the_title();
            $post_date = get_the_time(sprintf(__('%s @ %s', 'wp-stats'), get_option('date_format'), get_option('time_format')));
            $display_name = stripslashes($post->display_name);
            $temp .= "<li>$post_date - <a href=\"".get_permalink()."\" title=\"".sprintf(__('View post %s', 'wp-stats'), $post_title)."\">$post_title</a> ($display_name)</li>\n";
        }
    } else {
        $temp = '<li>'.__('N/A', 'wp-stats').'</li>';
    }
    if($display) {
        echo $temp;
    } else {
        return $temp;
    }
}


### Function: Get Recent Comments
function get_recentcomments($mode = '', $limit = 10, $display = true) {
    global $wpdb, $post;
    $where = '';
    $temp = '';
    if(!empty($mode) && $mode != 'both') {
        $where = "post_type = '$mode'";
    } else {
        $where = '1=1';
    }
    $recentcomments = $wpdb->get_results("SELECT * FROM $wpdb->posts INNER JOIN $wpdb->comments ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID WHERE comment_approved = '1' AND comment_type = '' AND post_date < '".current_time('mysql')."' AND $where AND post_status = 'publish' AND post_password = '' ORDER  BY comment_date DESC LIMIT $limit");
    if($recentcomments) {
        foreach ($recentcomments as $post) {
            $post_title = get_the_title();
            $comment_author = htmlspecialchars(stripslashes($post->comment_author));
            $comment_date = mysql2date(sprintf(__('%s @ %s', 'wp-stats'), get_option('date_format'), get_option('time_format')), $post->comment_date);
            $temp .= "<li>$comment_date - $comment_author (<a href=\"".get_permalink()."#comment-".$post->comment_ID."\" title=\"".sprintf(__('View comments in post %s', 'wp-stats'), $post_title)."\">$post_title</a>)</li>\n";
        }
    } else {
        $temp = '<li>'.__('N/A', 'wp-stats').'</li>';
    }
    if($display) {
        echo $temp;
    } else {
        return $temp;
    }
}


### Function: Get Top Commented Posts
function get_mostcommented($mode = '', $limit = 10, $chars = 0, $display = true) {
    global $wpdb, $post;
    $where = '';
    $temp = '';
    if(!empty($mode) && $mode != 'both') {
        $where = "post_type = '$mode'";
    } else {
        $where = '1=1';
    }
    $mostcommenteds = $wpdb->get_results("SELECT $wpdb->posts.*, COUNT($wpdb->comments.comment_post_ID) AS 'comment_total' FROM $wpdb->posts LEFT JOIN $wpdb->comments ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID WHERE comment_approved = '1' AND post_date < '".current_time('mysql')."' AND $where AND post_status = 'publish' AND post_password = '' GROUP BY $wpdb->comments.comment_post_ID ORDER  BY comment_total DESC LIMIT $limit");
    if($mostcommenteds) {
        if($chars > 0) {
            foreach ($mostcommenteds as $post) {
                $post_title = get_the_title();
                $comment_total = $post->comment_total;
                $temp .= "<li><a href=\"".get_permalink()."\" title=\"".sprintf(__('View comments in post %s', 'wp-stats'), $post_title)."\">".snippet_text($post_title, $chars)."</a> - ".sprintf(_n('%s comment', '%s comments', $comment_total, 'wp-stats'), number_format_i18n($comment_total))."</li>";
            }
        } else {
            foreach ($mostcommenteds as $post) {
                $post_title = get_the_title();
                $comment_total = $post->comment_total;
                $temp .= "<li><a href=\"".get_permalink()."\" title=\"".sprintf(__('View comments in post %s', 'wp-stats'), $post_title)."\">$post_title</a> - ".sprintf(_n('%s comment', '%s comments', $comment_total, 'wp-stats'), number_format_i18n($comment_total))."</li>";
            }
        }
    } else {
        $temp = '<li>'.__('N/A', 'wp-stats').'</li>';
    }
    if($display) {
        echo $temp;
    } else {
        return $temp;
    }
}


### Function: Get Author Stats
function get_authorsstats($mode = '', $display = true) {
    global $wpdb, $wp_rewrite;
    $where = '';
    $temp = '';
    if(!empty($mode) && $mode != 'both') {
        $where = "post_type = '$mode'";
    } else {
        $where = '1=1';
    }
    $posts = $wpdb->get_results("SELECT COUNT($wpdb->posts.ID) AS 'posts_total', $wpdb->users.display_name, $wpdb->users.user_nicename FROM $wpdb->posts LEFT JOIN $wpdb->users ON $wpdb->users.ID = $wpdb->posts.post_author WHERE user_activation_key = '' AND $where AND post_status = 'publish' GROUP BY $wpdb->posts.post_author");
    if($posts) {
        $using_permalink = get_option('permalink_structure');
        $permalink = $wp_rewrite->get_author_permastruct();
        foreach ($posts as $post) {
                $post_author = strip_tags(stripslashes($post->user_nicename));
                $author_link = str_replace('%author%', $post_author, $permalink);
                $display_name = strip_tags(stripslashes($post->display_name));
                $posts_total = number_format_i18n($post->posts_total);
                if($using_permalink) {
                    $temp .= "<li><a href=\"".get_option('home').$author_link."\" title=\"".sprintf(__('View posts posted by %s', 'wp-stats'), $display_name)."\">$display_name</a> ($posts_total)</li>\n";
                } else {
                    $temp .= "<li><a href=\"".get_option('siteurl')."/?author_name=$post_author\" title=\"".sprintf(__('View posts posted by %s', 'wp-stats'), $display_name)."\">$display_name</a> ($posts_total)</li>\n";
                }
        }
    } else {
        $temp = '<li>'.__('N/A', 'wp-stats').'</li>';
    }
    if($display) {
        echo $temp;
    } else {
        return $temp;
    }
}


### Function: Get Comments' Members Stats
// Treshhold = Number Of Posts User Must Have Before It Will Display His Name Out
// 5 = Default Treshhold; -1 = Disable Treshhold
function get_commentmembersstats($threshhold = -1, $limit = 0, $display = true) {
    global $wpdb;
    $temp = '';
    $limit_sql = '';
    if($limit > 0) {
        $limit_sql = "LIMIT $limit";
    }
    $comments = $wpdb->get_results("SELECT comment_author, COUNT(comment_ID) AS 'comment_total' FROM $wpdb->comments INNER  JOIN $wpdb->posts ON $wpdb->comments.comment_post_ID = $wpdb->posts.ID WHERE comment_approved = '1' AND comment_type = '' AND post_date < '".current_time('mysql')."' AND post_status = 'publish' AND post_password = '' GROUP BY comment_author ORDER BY comment_total DESC $limit_sql");
    if($comments) {
        foreach ($comments as $comment) {
                $comment_total = intval($comment->comment_total);
                // If Total Comments Is Below Threshold
                if($comment_total >= $threshhold) {
                    $comment_author = strip_tags(stripslashes($comment->comment_author));
                    $comment_author_link = urlencode($comment_author);
                    $temp .= "<li><a href=\"".stats_page_link($comment_author_link)."\" title=\"".sprintf(__('View all comments posted by %s', 'wp-stats'), $comment_author)."\">$comment_author</a> (".number_format_i18n($comment_total).")</li>\n";
                }
        }
    } else {
        $temp = '<li>'.__('N/A', 'wp-stats').'</li>';
    }
    if($display) {
        echo $temp;
    } else {
        return $temp;
    }
}


### Function: Get Post Categories Stats
function get_postcats($display = true) {
    global $wpdb;
    $temp = '';
    $defaults = array('type' => 'post', 'style' => 'list', 'show_count' => 1);
    $categories = get_categories($defaults);
    if (empty($categories)){
        $temp .= '<li>'.__('No categories', 'wp-stats').'</li>';
    } else {
        $temp .= walk_category_tree($categories, 0, $defaults);
    }
    if($display) {
        echo $temp;
    } else {
        return $temp;
    }
}


### Function: Get Links Categories Stats
function get_linkcats($display = true) {
    global $wpdb;
    $temp = '';
    $cats = get_categories('type=link');
    if ($cats) {
        foreach ($cats as $cat) {
            $temp .= '<li>'.$cat->cat_name.' ('.number_format_i18n($cat->count).")</li>\n";
        }
    }
    if($display) {
        echo $temp;
    } else {
        return $temp;
    }
}


### Function: Get Tags List
function get_tags_list($display = true) {
    global $wpdb;
    $temp = '';
    $tags = get_tags('orderby=count&order=DESC');
    if ($tags) {
        foreach ($tags as $tag) {
            $temp .= '<li><a href="'.clean_url(get_tag_link($tag->term_id)).'" title="'.sprintf(_n('%s topic', '%s topics', $tag->count, 'wp-stats'), number_format_i18n($tag->count)).'">'.$tag->name.'</a> ('.number_format_i18n($tag->count).")</li>\n";
        }
    }
    if($display) {
        echo $temp;
    } else {
        return $temp;
    }
}


### Function: Snippet Text
if(!function_exists('snippet_text')) {
    function snippet_text($text, $length = 0) {
        if (defined('MB_OVERLOAD_STRING')) {
          $text = @html_entity_decode($text, ENT_QUOTES, get_option('blog_charset'));
             if (mb_strlen($text) > $length) {
                return htmlentities(mb_substr($text,0,$length), ENT_COMPAT, get_option('blog_charset')).'...';
             } else {
                return htmlentities($text, ENT_COMPAT, get_option('blog_charset'));
             }
        } else {
            $text = @html_entity_decode($text, ENT_QUOTES, get_option('blog_charset'));
             if (strlen($text) > $length) {
                return htmlentities(substr($text,0,$length), ENT_COMPAT, get_option('blog_charset')).'...';
             } else {
                return htmlentities($text, ENT_COMPAT, get_option('blog_charset'));
             }
        }
    }
}


### Function: Short Code For Inserting Stats Into Page
add_shortcode('page_stats', 'stats_page_shortcode');
function stats_page_shortcode($atts) {
    return stats_page();
}


### Function: Stats Page
function stats_page_link($author, $page = 0) {
    $stats_url = esc_url( get_option( 'stats_url' ) );
    if($page > 1) {
        $page = "&amp;stats_page=$page";
    } else {
        $page = '';
    }
    if(strpos($stats_url, '?') !== false) {
        $stats_url = "$stats_url&amp;stats_author=$author$page";
    } else {
        $stats_url = "$stats_url?stats_author=$author$page";
    }
    return $stats_url;
}


### Function: Statistics Page
function stats_page() {
    global $wpdb, $post;
    // Variables Variables Variables
    $comment_author = urldecode(strip_tags(stripslashes(trim($_GET['stats_author']))));
    $page = intval($_GET['stats_page']);
    $temp_stats = '';
    $temp_post = $post;
    $stats_mostlimit = intval(get_option('stats_mostlimit'));
    $stats_display = get_option('stats_display');

    // Default wp-stats.php Page
    if(empty($comment_author)) {
        // General Stats
        if($stats_display['total_stats'] == 1) {
            $temp_stats .= '<h2 id="GeneralStats">'.__('General Stats', 'wp-stats').'</h2>'."\n";
            $temp_stats .= '<p><strong>'.__('Total Stats', 'wp-stats').'</strong></p>'."\n";
            $temp_stats .= '<ul>'."\n";
            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> author to this blog.', '<strong>%s</strong> authors to this blog.', get_totalauthors(false), 'wp-stats'), number_format_i18n(get_totalauthors(false))).'</li>'."\n";
            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> post was posted.', '<strong>%s</strong> posts were posted.', get_totalposts(false), 'wp-stats'), number_format_i18n(get_totalposts(false))).'</li>'."\n";
            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> page was created.', '<strong>%s</strong> pages were created.', get_totalpages(false), 'wp-stats'), number_format_i18n(get_totalpages(false))).'</li>'."\n";
            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> tag was created.', '<strong>%s</strong> tags were created.', wp_count_terms('post_tag'), 'wp-stats'), number_format_i18n(wp_count_terms('post_tag'))).'</li>'."\n";

            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> comment was posted.', '<strong>%s</strong> comments were posted.', get_totalcomments(false), 'wp-stats'), number_format_i18n(get_totalcomments(false))).'</li>'."\n";
            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> nickname was represented in the comments.', '<strong>%s</strong> different nicknames were represented in the comments.', get_totalcommentposters(false), 'wp-stats'), number_format_i18n(get_totalcommentposters(false))).'</li>'."\n";

            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> link was added.', '<strong>%s</strong> links were added.', get_totallinks(false), 'wp-stats'), number_format_i18n(get_totallinks(false))).'</li>'."\n";
            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> post category was needed.', '<strong>%s</strong> post categories were needed.', wp_count_terms('category'), 'wp-stats'), number_format_i18n(wp_count_terms('category'))).'</li>'."\n";
            $temp_stats .= '<li>'.sprintf(_n('<strong>%s</strong> link category was needed.', '<strong>%s</strong> link categories were needed.', wp_count_terms('link_category'), 'wp-stats'), number_format_i18n(wp_count_terms('link_category'))).'</li>'."\n";
            if( class_exists( 'Akismet_Admin' ) ) {
                $spam_count = Akismet_Admin::get_spam_count();
                $temp_stats .= '<li>' . sprintf( _n( '<strong>%s</strong> spam blocked.', '<strong>%s</strong> spam blocked.', $spam_count, 'wp-stats' ), number_format_i18n( $spam_count ) ) . '</li>' . "\n";
            }
            // WP-Stats: General Stats Filter
            $temp_stats = apply_filters('wp_stats_page_general', $temp_stats);
            $temp_stats .= '</ul>'."\n";
        }

        // Plugin Stats
        $temp_stats .= '<h2 id="PluginsStats">'.__('Plugins Stats', 'wp-stats').'</h2>'."\n";

        // WP-Stats: Plugins Stats Filter
        $temp_stats = apply_filters('wp_stats_page_plugins', $temp_stats);

        // Top Recent Stats
        $temp_stats .= '<h2 id="TopRecentStats">'.sprintf(_n('Top %s Recent Stat', 'Top %s Recent Stats', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)).'</h2>'."\n";

        // Recent Posts
        if($stats_display['recent_posts'] == 1) {
            $temp_stats .= '<p><strong>'.sprintf(_n('%s Recent Post', '%s Recent Posts', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
            $temp_stats .= '<ul>'."\n";
            $temp_stats .= get_recentposts('post', $stats_mostlimit, false);
            $temp_stats .= '</ul>'."\n";
        }

        // Recent Comments
        if($stats_display['recent_comments'] == 1) {
            $temp_stats .= '<p><strong>'.sprintf(_n('%s Recent Comment', '%s Recent Comments', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
            $temp_stats .= '<ul>'."\n";
            $temp_stats .= get_recentcomments('both', $stats_mostlimit, false);
            $temp_stats .= '</ul>'."\n";
        }

        // WP-Stats: Top Recent Stats Filter
        $temp_stats = apply_filters('wp_stats_page_recent', $temp_stats);

        // Top Most Stats
        $temp_stats .= '<h2 id="TopMostHighestStats">'.sprintf(_n('%s Most/Highest Stat', '%s Most/Highest Stats', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)).'</h2>'."\n";

        // Most Commented Posts
        if($stats_display['commented_post'] == 1) {
            $temp_stats .= '<p><strong>'.sprintf(_n('%s Most Commented Post', '%s Most Commented Posts', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
            $temp_stats .= '<ul>'."\n";
            $temp_stats .= get_mostcommented('post', $stats_mostlimit, 0, false);
            $temp_stats .= '</ul>'."\n";
        }

        // Most Commented Pages
        if($stats_display['commented_page'] == 1) {
            $temp_stats .= '<p><strong>'.sprintf(_n('%s Most Commented Page', '%s Most Commented Pages', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
            $temp_stats .= '<ul>'."\n";
            $temp_stats .= get_mostcommented('page', $stats_mostlimit, 0, false);
            $temp_stats .= '</ul>'."\n";
        }

        // WP-Stats: Top Most/Highest Stats Filter
        $temp_stats = apply_filters('wp_stats_page_most', $temp_stats);

        // Authors Stats
        $temp_stats .= '<h2 id="AuthorsStats">'.__('Authors Stats', 'wp-stats').'</h2>'."\n";

        // Authors
        if($stats_display['authors'] == 1) {
            $temp_stats .= '<p><strong>'.__('Authors', 'wp-stats').'</strong></p>'."\n";
            $temp_stats .= '<ol>'."\n";
            $temp_stats .= get_authorsstats('post', false);
            $temp_stats .= '</ol>'."\n";
        }

        // WP-Stats: Authors Stats Filter
        $temp_stats = apply_filters('wp_stats_page_authors', $temp_stats);

        // Comments' Members Stats
        $temp_stats .= '<h2 id="CommentsMembersStats">'.__('Comments\' Members Stats', 'wp-stats').'</h2>'."\n";

        // Comments' Member
        if($stats_display['comment_members'] == 1) {
            $temp_stats .= '<p><strong>'.__('Comment Members', 'wp-stats').'</strong></p>'."\n";
            $temp_stats .= '<ol>'."\n";
            $temp_stats .= get_commentmembersstats(5, 0, false);
            $temp_stats .= '</ol>'."\n";
        }

        // WP-Stats: Comments' Members Stats Filter
        $temp_stats = apply_filters('wp_stats_page_comments_members', $temp_stats);

        // Misc Stats
        $temp_stats .= '<h2 id="MiscStats">'.__('Misc Stats', 'wp-stats').'</h2>'."\n";

        // Post Categories
        if($stats_display['post_cats'] == 1) {
            $temp_stats .= '<p><strong>'.__('Post Categories', 'wp-stats').'</strong></p>'."\n";
            $temp_stats .= '<ul>'."\n";
            $temp_stats .= get_postcats(false);
            $temp_stats .= '</ul>'."\n";
        }

        // Link Categories
        if($stats_display['link_cats'] == 1) {
            $temp_stats .= '<p><strong>'.__('Link Categories', 'wp-stats').'</strong></p>'."\n";
            $temp_stats .= '<ul>'."\n";
            $temp_stats .= get_linkcats(false);
            $temp_stats .= '</ul>'."\n";
        }

        if($stats_display['tags_list'] == 1) {
            $temp_stats .= '<p><strong>'.__('Tags List', 'wp-stats').'</strong></p>'."\n";
            $temp_stats .= '<ul>'."\n";
            $temp_stats .= get_tags_list(false);
            $temp_stats .= '</ul>'."\n";
        }

        // WP-Stats: Plugin Misc Filter
        $temp_stats = apply_filters('wp_stats_page_misc', $temp_stats);

    // Displaying Comments Posted By User
    } else {
        // Stats URL
        $stats_url = esc_url( get_option( 'stats_url' ) );
        // Number Of Comments Per Page
        $perpage = 10;
        // Comment Author Link
        $comment_author_link = urlencode($comment_author);
        // Comment Author SQL
        $comment_author_sql = $wpdb->escape($comment_author);
        // Total Comments Posted By User
        $totalcomments = $wpdb->get_var("SELECT COUNT(comment_ID) FROM $wpdb->comments INNER  JOIN $wpdb->posts ON $wpdb->comments.comment_post_ID = $wpdb->posts.ID WHERE comment_author =  '$comment_author_sql' AND comment_approved = '1' AND comment_type = '' AND post_date < '".current_time('mysql')."' AND post_status = 'publish' AND post_password = ''");
        // Calculate Paging
        $numposts = $totalcomments;
        $perpage = 30;
        $max_page = ceil($numposts/$perpage);
        if(empty($page) || $page == 0) {
            $page = 1;
        }
        $offset = ($page-1) * $perpage;
        $pages_to_show = 10;
        $pages_to_show_minus_1 = $pages_to_show-1;
        $half_page_start = floor($pages_to_show_minus_1/2);
        $half_page_end = ceil($pages_to_show_minus_1/2);
        $start_page = $page - $half_page_start;
        if($start_page <= 0) {
            $start_page = 1;
        }
        $end_page = $page + $half_page_end;
        if(($end_page - $start_page) != $pages_to_show_minus_1) {
            $end_page = $start_page + $pages_to_show_minus_1;
        }
        if($end_page > $max_page) {
            $start_page = $max_page - $pages_to_show_minus_1;
            $end_page = $max_page;
        }
        if($start_page <= 0) {
            $start_page = 1;
        }
        if(($offset + $perpage) > $numposts) {
            $max_on_page = $numposts;
        } else {
            $max_on_page = ($offset + $perpage);
        }
        if (($offset + 1) > ($numposts)) {
            $display_on_page = $numposts;
        } else {
            $display_on_page = ($offset + 1);
        }

        // Getting The Comments
        $gmz_comments =  $wpdb->get_results("SELECT $wpdb->posts.*, $wpdb->comments.* FROM $wpdb->comments INNER  JOIN $wpdb->posts ON $wpdb->comments.comment_post_ID = $wpdb->posts.ID WHERE comment_author =  '$comment_author_sql' AND comment_approved = '1' AND post_date < '".current_time('mysql')."' AND post_status = 'publish' AND post_password = '' ORDER  BY comment_post_ID DESC, comment_date DESC  LIMIT $offset, $perpage");
        $temp_stats .= '<h2>'.__('Comments Posted By', 'wp-stats').' '.$comment_author.'</h2>';
        $temp_stats .= '<p>'.sprintf(__('Displaying <strong>%s</strong> To <strong>%s</strong> Of <strong>%s</strong> Comments', 'wp-stats'), number_format_i18n($display_on_page), number_format_i18n($max_on_page), number_format_i18n($numposts)).'</p>';


        // Get Comments
        if($gmz_comments) {
            foreach($gmz_comments as $post) {
                $comment_id = intval($post->comment_ID);
                $comment_author2 = htmlspecialchars(stripslashes($post->comment_author));
                $comment_date = mysql2date(sprintf(__('%s @ %s', 'wp-stats'), get_option('date_format'), get_option('time_format')), $post->comment_date);
                $comment_content = apply_filters('comment_text', $post->comment_content);
                $post_date = get_the_time(sprintf(__('%s @ %s', 'wp-stats'), get_option('date_format'), get_option('time_format')));
                $post_title = get_the_title();

                // Check For Password Protected Post
                if(!empty($post->post_password) && stripslashes($_COOKIE['wp-postpass_'.COOKIEHASH]) != $post->post_password) {
                    // If New Title, Print It Out
                    if($post_title != $cache_post_title) {
                        $temp_stats .= "<p><strong><a href=\"".get_permalink()."\" title=\"".__('Posted On', 'wp-stats')." $post_date\">".__('Protected', 'wp-stats').": $post_title</a></strong></p>";
                        $temp_stats .= '<blockquote>'.__('Comments Protected', 'wp-stats').'</blockquote>';
                    }
                } else {
                    // If New Title, Print It Out
                    if($post_title != $cache_post_title) {
                        $temp_stats .= "<p><strong><a href=\"".get_permalink()."\" title=\"".__('Posted On', 'wp-stats')." $post_date\">$post_title</a></strong></p>";
                    }
                    $temp_stats .= "<blockquote>$comment_content<p><a href=\"".get_permalink()."#comment-$comment_id\" title=\"".sprintf(__('View the comment posted by %s', 'wp-stats'), $comment_author2)."\">&raquo;</a> ".__('Posted By', 'wp-stats')." <strong>$comment_author2</strong> ".__('On', 'wp-stats')." $comment_date</p></blockquote>";
                }
                $cache_post_title = $post_title;
            }
        } else {
                $temp_stats .= "<p>$comment_author ".__('has not made any comments yet.', 'wp-stats')."</p>";
        }

        // Comments Paging
        if($max_page > 1) {
            $temp_stats = apply_filters('wp_stats_paging_start', $temp_stats);
            $temp_stats .= '<div class="wp-pagenavi">'."\n";
            $temp_stats .= '<span class="pages">&#8201;'.sprintf(__('Page %s of %s', 'wp-stats'), number_format_i18n($page), number_format_i18n($max_page)).'&#8201;</span>';
            if ($start_page >= 2 && $pages_to_show < $max_page) {
                $temp_stats .= '<a href="'.stats_page_link($comment_author_link, 1).'" title="'.__('&laquo; First', 'wp-stats').'">&#8201;'.__('&laquo; First', 'wp-stats').'&#8201;</a>';
                $temp_stats .= '<span class="extend">...</span>';
            }
            if($page > 1) {
                $temp_stats .= '<a href="'.stats_page_link($comment_author_link, ($page-1)).'" title="'.__('&laquo;', 'wp-stats').'">&#8201;'.__('&laquo;', 'wp-stats').'&#8201;</a>';
            }
            for($i = $start_page; $i  <= $end_page; $i++) {
                if($i == $page) {
                    $temp_stats .= '<span class="current">&#8201;'.number_format_i18n($i).'&#8201;</span>';
                } else {
                    $temp_stats .= '<a href="'.stats_page_link($comment_author_link, $i).'" title="'.number_format_i18n($i).'">&#8201;'.number_format_i18n($i).'&#8201;</a>';
                }
            }
            if(empty($page) || ($page+1) <= $max_page) {
                $temp_stats .= '<a href="'.stats_page_link($comment_author_link, ($page+1)).'" title="'.__('&raquo;', 'wp-stats').'">&#8201;'.__('&raquo;', 'wp-stats').'&#8201;</a>';
            }
            if ($end_page < $max_page) {
                $temp_stats .= '<span class="extend">...</span>';
                $temp_stats .= '<a href="'.stats_page_link($comment_author_link, $max_page).'" title="'.__('Last &raquo;', 'wp-stats').'">&#8201;'.__('Last &raquo;', 'wp-stats').'&#8201;</a>';
            }
            $temp_stats .= '</div>';
            $temp_stats = apply_filters('wp_stats_paging_end', $temp_stats);
        }
        $temp_stats .= '<strong>&laquo;&laquo;</strong> <a href="'.$stats_url.'">'.__('Back To Stats Page', 'wp-stats').' </a>';
    } // End If

    // Assign Back $post
    $post = $temp_post;

    // Output Stats Page
    return apply_filters('stats_page', $temp_stats);
}


### Class: WP-Stats Widget
 class WP_Widget_Stats extends WP_Widget {
    // Constructor
    function __construct() {
        $widget_ops = array('description' => __('WP-Stats statistics', 'wp-stats'));
        parent::__construct('stats', __('Stats', 'wp-stats'), $widget_ops);
    }

    // Display Widget
    function widget($args, $instance) {
        $title = apply_filters('widget_title', esc_attr($instance['title']));
        $limit = intval($instance['limit']);
        $chars = intval($instance['chars']);
        $show_link = intval($instance['show_link']);
        $stats_total_authors = intval($instance['stats_total_authors']);
        $stats_total_posts = intval($instance['stats_total_posts']);
        $stats_total_pages = intval($instance['stats_total_pages']);
        $stats_total_tags = intval($instance['stats_total_tags']);
        $stats_total_comments = intval($instance['stats_total_comments']);
        $stats_total_commenters = intval($instance['stats_total_commenters']);
        $stats_total_links = intval($instance['stats_total_links']);
        $stats_total_post_cat = intval($instance['stats_total_post_cat']);
        $stats_total_link_cat = intval($instance['stats_total_link_cat']);
        $stats_total_spam = intval($instance['stats_total_spam']);
        $stats_most_commented_post = intval($instance['stats_most_commented_post']);
        echo $args['before_widget'] . $args['before_title'] . $title . $args['after_title'];
        echo '<ul>'."\n";
        echo '<li><strong>'.__('Total Stats', 'wp-stats').'</strong>'."\n";
        echo '<ul>'."\n";
        // Total Authors
        if($stats_total_authors) {
            echo '<li>'.sprintf(_n('<strong>%s</strong> Author', '<strong>%s</strong> Authors', get_totalauthors(false), 'wp-stats'), number_format_i18n(get_totalauthors(false))).'</li>'."\n";
        }
        // Total Posts
        if($stats_total_posts) {
            echo '<li>'.sprintf(_n('<strong>%s</strong> Post', '<strong>%s</strong> Posts', get_totalposts(false), 'wp-stats'), number_format_i18n(get_totalposts(false))).'</li>'."\n";
        }
        // Total Pages
        if($stats_total_pages) {
            '<li>'.sprintf(_n('<strong>%s</strong> Page', '<strong>%s</strong> Pages', get_totalpages(false), 'wp-stats'), number_format_i18n(get_totalpages(false))).'</li>'."\n";
        }
        // Total Tags
        if($stats_total_tags) {
            echo '<li>'.sprintf(_n('<strong>%s</strong> Tag', '<strong>%s</strong> Tags', wp_count_terms('post_tag'), 'wp-stats'), number_format_i18n(wp_count_terms('post_tag'))).'</li>'."\n";
        }
        // Total Comments
        if($stats_total_comments) {
            echo '<li>'.sprintf(_n('<strong>%s</strong> Comment', '<strong>%s</strong> Comments', get_totalcomments(false), 'wp-stats'), number_format_i18n(get_totalcomments(false))).'</li>'."\n";
        }
        // Total Comment Posters
        if($stats_total_commenters) {
            echo '<li>'.sprintf(_n('<strong>%s</strong> Comment Poster', '<strong>%s</strong> Comment Posters', get_totalcommentposters(false), 'wp-stats'), number_format_i18n(get_totalcommentposters(false))).'</li>'."\n";
        }
        // Total Links
        if($stats_total_links) {
            echo '<li>'.sprintf(_n('<strong>%s</strong> Link', '<strong>%s</strong> Links', get_totallinks(false), 'wp-stats'), number_format_i18n(get_totallinks(false))).'</li>'."\n";
        }
        // Total Post Categories
        if($stats_total_post_cat) {
            echo '<li>'.sprintf(_n('<strong>%s</strong> Post Category', '<strong>%s</strong> Post Categories', wp_count_terms('category'), 'wp-stats'), number_format_i18n(wp_count_terms('category'))).'</li>'."\n";
        }
        // Total Link Categories
        if($stats_total_link_cat) {
            echo '<li>'.sprintf(_n('<strong>%s</strong> Link Category', '<strong>%s</strong> Link Categories', wp_count_terms('link_category'), 'wp-stats'), number_format_i18n(wp_count_terms('link_category'))).'</li>'."\n";
        }
        // Total Spam
        if($stats_total_spam && class_exists( 'Akismet_Admin' ) ) {
            $spam_count = Akismet_Admin::get_spam_count();
            echo '<li>' . sprintf( _n( '<strong>%s</strong> Spam Blocked', '<strong>%s</strong> Spam Blockeds', $spam_count, 'wp-stats' ), number_format_i18n( $spam_count ) ) . '</li>' . "\n";
        }
        echo '</ul>'."\n";
        echo '</li>'."\n";
        echo '</ul>'."\n";
        // Most Commented
        if($stats_most_commented_post) {
            echo '<ul>'."\n";
            echo '<li><strong>'.number_format_i18n($limit).' '.__('Most Commented Posts', 'wp-stats').'</strong>'."\n";
            echo '<ul>'."\n";
            get_mostcommented('post', $limit, $chars);
            echo '</ul>'."\n";
            echo '</li>'."\n";
            echo '</ul>'."\n";
        }
        if($show_link) {
            echo '<ul>'."\n";
            echo '<li><a href="'.esc_url( get_option( 'stats_url' ) ).'">'.__('My Blog Statistics', 'wp-stats').'</a></li>'."\n";
            echo '</ul>'."\n";
        }
        echo $args['after_widget'];
    }

    // When Widget Control Form Is Posted
    function update($new_instance, $old_instance) {
        if (!isset($new_instance['submit'])) {
            return false;
        }
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);;
        $instance['limit'] = intval($new_instance['limit']);
        $instance['chars'] = intval($new_instance['chars']);
        $instance['show_link'] = intval($new_instance['show_link']);
        $instance['stats_total_authors'] = intval($new_instance['stats_total_authors']);
        $instance['stats_total_posts'] = intval($new_instance['stats_total_posts']);
        $instance['stats_total_pages'] = intval($new_instance['stats_total_pages']);
        $instance['stats_total_tags'] = intval($new_instance['stats_total_tags']);
        $instance['stats_total_comments'] = intval($new_instance['stats_total_comments']);
        $instance['stats_total_commenters'] = intval($new_instance['stats_total_commenters']);
        $instance['stats_total_links'] = intval($new_instance['stats_total_links']);
        $instance['stats_total_post_cat'] = intval($new_instance['stats_total_post_cat']);
        $instance['stats_total_link_cat'] = intval($new_instance['stats_total_link_cat']);
        $instance['stats_total_spam'] = intval($new_instance['stats_total_spam']);
        $instance['stats_most_commented_post'] = intval($new_instance['stats_most_commented_post']);
        return $instance;
    }

    // DIsplay Widget Control Form
    function form($instance) {
        global $wpdb;
        $instance = wp_parse_args((array) $instance, array('title' => __('Stats', 'wp-stats'), 'limit' => 10, 'chars' => 200, 'show_link' => 1, 'stats_total_authors' => 1, 'stats_total_posts' => 1, 'stats_total_pages' => 1, 'stats_total_tags' => 1, 'stats_total_comments' => 1, 'stats_total_commenters' => 1, 'stats_total_links' => 1, 'stats_total_post_cat' => 1, 'stats_total_link_cat' => 1, 'stats_total_spam' => 1, 'stats_most_commented_post' => 1));
        $title = esc_attr($instance['title']);
        $limit = intval($instance['limit']);
        $chars = intval($instance['chars']);
        $show_link = intval($instance['show_link']);
        $stats_total_authors = intval($instance['stats_total_authors']);
        $stats_total_posts = intval($instance['stats_total_posts']);
        $stats_total_pages = intval($instance['stats_total_pages']);
        $stats_total_tags = intval($instance['stats_total_tags']);
        $stats_total_comments = intval($instance['stats_total_comments']);
        $stats_total_commenters = intval($instance['stats_total_commenters']);
        $stats_total_links = intval($instance['stats_total_links']);
        $stats_total_post_cat = intval($instance['stats_total_post_cat']);
        $stats_total_link_cat = intval($instance['stats_total_link_cat']);
        $stats_total_spam = intval($instance['stats_total_spam']);
        $stats_most_commented_post = intval($instance['stats_most_commented_post']);
?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp-stats'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
        </p>
        <p>
            <?php _e('Statistics To Display:', 'wp-postviews'); ?><br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_authors'); ?>" name="<?php echo $this->get_field_name('stats_total_authors'); ?>" value="1" <?php checked(1, $instance['stats_total_authors']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_authors'); ?>"><?php _e('Total Authors', 'wp-stats'); ?></label>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_posts'); ?>" name="<?php echo $this->get_field_name('stats_total_posts'); ?>" value="1" <?php checked(1, $instance['stats_total_posts']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_posts'); ?>"><?php _e('Total Posts', 'wp-stats'); ?></label>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_pages'); ?>" name="<?php echo $this->get_field_name('stats_total_pages'); ?>" value="1" <?php checked(1, $instance['stats_total_pages']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_pages'); ?>"><?php _e('Total Pages', 'wp-stats'); ?></label>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_tags'); ?>" name="<?php echo $this->get_field_name('stats_total_tags'); ?>" value="1" <?php checked(1, $instance['stats_total_tags']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_tags'); ?>"><?php _e('Total Tags', 'wp-stats'); ?></label>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_comments'); ?>" name="<?php echo $this->get_field_name('stats_total_comments'); ?>" value="1" <?php checked(1, $instance['stats_total_comments']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_comments'); ?>"><?php _e('Total Comments', 'wp-stats'); ?></label>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_commenters'); ?>" name="<?php echo $this->get_field_name('stats_total_commenters'); ?>" value="1" <?php checked(1, $instance['stats_total_commenters']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_commenters'); ?>"><?php _e('Total Comment Posters', 'wp-stats'); ?></label>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_links'); ?>" name="<?php echo $this->get_field_name('stats_total_links'); ?>" value="1" <?php checked(1, $instance['stats_total_links']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_links'); ?>"><?php _e('Total Links', 'wp-stats'); ?></label>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_post_cat'); ?>" name="<?php echo $this->get_field_name('stats_total_post_cat'); ?>" value="1" <?php checked(1, $instance['stats_total_post_cat']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_post_cat'); ?>"><?php _e('Total Post Categories', 'wp-stats'); ?></label>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_link_cat'); ?>" name="<?php echo $this->get_field_name('stats_total_link_cat'); ?>" value="1" <?php checked(1, $instance['stats_total_link_cat']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_link_cat'); ?>"><?php _e('Total Link Categories', 'wp-stats'); ?></label>
            <br />
            <?php if( class_exists( 'Akismet_Admin' ) ): ?>
                <input type="checkbox" id="<?php echo $this->get_field_id('stats_total_spam'); ?>" name="<?php echo $this->get_field_name('stats_total_spam'); ?>" value="1" <?php checked(1, $instance['stats_total_spam']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_total_spam'); ?>"><?php _e('Total Spam Blocked', 'wp-stats'); ?></label>
                <br />
            <?php endif; ?>
            <br />
            <input type="checkbox" id="<?php echo $this->get_field_id('stats_most_commented_post'); ?>" name="<?php echo $this->get_field_name('stats_most_commented_post'); ?>" value="1" <?php checked(1, $instance['stats_most_commented_post']); ?> />&nbsp;&nbsp;<label for="<?php echo $this->get_field_id('stats_most_commented_post'); ?>"><?php printf(_n('%s Most Commented Post', '%s Most Commented Posts', $options['most_limit'], 'wp-stats'), $options['most_limit']); ?></label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('No. Of Records To Show:', 'wp-stats'); ?> <span style="color: red;">*</span> <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" /></label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('chars'); ?>"><?php _e('Maximum Post Title Length (Characters):', 'wp-stats'); ?> <span style="color: red;">*</span> <input class="widefat" id="<?php echo $this->get_field_id('chars'); ?>" name="<?php echo $this->get_field_name('chars'); ?>" type="text" value="<?php echo $chars; ?>" /></label><br />
            <small><?php _e('<strong>0</strong> to disable.', 'wp-stats'); ?></small>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('show_link'); ?>"><?php _e('Show Link To Statistics Page?', 'wp-stats'); ?>
                <select name="<?php echo $this->get_field_name('show_link'); ?>" id="<?php echo $this->get_field_id('show_link'); ?>" class="widefat">
                    <option value="0"<?php selected('0', $show_link); ?>><?php _e('No', 'wp-stats'); ?></option>
                    <option value="1"<?php selected('1', $show_link); ?>><?php _e('Yes', 'wp-stats'); ?></option>
                </select>
            </label>
        </p>
        <p style="color: red;">
            <small><?php _e('* Used only in most commented post.', 'wp-stats'); ?></small>
        <p>
        <input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
<?php
    }
}


### Function: Init WP-Stats Widget
add_action('widgets_init', 'widget_stats_init');
function widget_stats_init() {
    stats_textdomain();
    register_widget('WP_Widget_Stats');
}


### Function: Activate Plugin
register_activation_hook( __FILE__, 'stats_activation' );
function stats_activation( $network_wide ) {
    if ( is_multisite() && $network_wide ) {
        $ms_sites = wp_get_sites();

        if( 0 < sizeof( $ms_sites ) ) {
            foreach ( $ms_sites as $ms_site ) {
                switch_to_blog( $ms_site['blog_id'] );
                stats_activate();
            }
        }

        restore_current_blog();
    } else {
        stats_activate();
    }
}
function stats_activate() {
    $stats_display = array( 'total_stats'  => 1, 'email'  => 1, 'polls' => 1, 'ratings' => 1, 'views' => 1, 'useronline' => 1, 'recent_posts' => 1, 'recent_comments' => 1, 'commented_post' => 1, 'commented_page' => 0, 'emailed_most_post' => 1, 'emailed_most_page' => 0, 'rated_highest_post' => 1, 'rated_highest_page' => 0, 'rated_most_post' => 1, 'rated_most_page' => 0, 'viewed_most_post' => 1, 'viewed_most_page' => 0, 'authors' => 1, 'comment_members' => 1, 'post_cats' => 1, 'link_cats' => 1 );
    add_option( 'stats_mostlimit', '10', 'Stats Most Limit' );
    add_option( 'stats_display', $stats_display, 'Stats To Display' );
    add_option( 'stats_url', esc_url( get_option( 'siteurl' ) ) .'/stats/', 'Stats URL' );
}