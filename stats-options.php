<?php
### Variables Variables Variables
$base_name = plugin_basename('wp-stats/stats-options.php');
$base_page = 'admin.php?page='.$base_name;

### Form Processing
if( ! empty( $_POST['Submit'] ) ) {
    check_admin_referer( 'wp-stats_options' );
    $stats_url = ! empty( $_POST['stats_url'] ) ? esc_url_raw( $_POST['stats_url'] ) : '';
    $stats_mostlimit = ! empty( $_POST['stats_mostlimit'] ) ? intval( trim( $_POST['stats_mostlimit'] ) ) : 10;
    $stats_display =  empty( $_POST['stats_display'] ) ? $_POST['stats_display'] : array();
    $stats_display_array = array();
    if( ! empty( $stats_display ) ) {
        foreach($stats_display as $stat_display) {
            $stat_display = addslashes( $stat_display );
            $stats_display_array[$stat_display] = 1;
        }
    }
    $stats_display = $stats_display_array;
    $update_stats_queries = array();
    $update_stats_text = array();
    $update_stats_queries[] = update_option('stats_url', $stats_url);
    $update_stats_queries[] = update_option('stats_mostlimit', $stats_mostlimit);
    $update_stats_queries[] = update_option('stats_display', $stats_display);
    $update_stats_text[] = __('Stats URL', 'wp-stats');
    $update_stats_text[] = __('Stats Most Limit', 'wp-stats');
    $update_stats_text[] = __('Stats Display Options', 'wp-stats');
    $i=0;
    $text = '';
    foreach($update_stats_queries as $update_stats_query) {
        if($update_stats_query) {
            $text .= '<p style="color: green">'.$update_stats_text[$i].' '.__('Updated', 'wp-stats').'</p>';
        }
        $i++;
    }
    if(empty($text)) {
        $text = '<p style="color: red">'.__('No Stats Option Updated', 'wp-stats').'</p>';
    }
}

$stats_mostlimit = intval(get_option('stats_mostlimit'));
$stats_display = get_option('stats_display');
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<form method="post" action="<?php echo admin_url( 'admin.php?page='.plugin_basename( __FILE__ ) ); ?>">
    <?php wp_nonce_field( 'wp-stats_options' ); ?>
    <div class="wrap">
        <h2><?php _e('Stats Options', 'wp-stats'); ?></h2>
        <table class="form-table">
             <tr>
                <th scope="row" valign="top"><?php _e('Stats URL', 'wp-stats'); ?></th>
                <td>
                    <input type="text" name="stats_url" value="<?php echo esc_url( get_option( 'stats_url' ) ); ?>" size="50" dir="ltr" /><br /><?php _e('URL To Stats Page.<br />Example: http://www.yoursite.com/blogs/stats/<br />Example: http://www.yoursite.com/blogs/?page_id=2', 'wp-stats'); ?>
                </td>
            </tr>
             <tr>
                <th scope="row" valign="top"><?php _e('Stats Most Limit', 'wp-stats'); ?></th>
                <td>
                    <input type="text" name="stats_mostlimit" value="<?php echo $stats_mostlimit ?>" size="2" /><br /><?php _e('Top X Stats, where X is the most limit.', 'wp-stats'); ?>
                </td>
            </tr>
             <tr>
                <th scope="row" valign="top"><?php _e('Type Of Stats To Display', 'wp-stats'); ?></th>
                <td>
                    <p><strong><?php _e('General Stats', 'wp-stats'); ?></strong></p>
                    <input type="checkbox" name="stats_display[]" id="wpstats_total_stats" value="total_stats"<?php checked(1, $stats_display['total_stats']); ?> />&nbsp;&nbsp;<label for="wpstats_total_stats"><?php _e('Total', 'wp-stats'); ?></label><br />

                    <!-- Admin General Stats Filter -->
                    <?php echo apply_filters('wp_stats_page_admin_general', $page_admin_general_stats); ?>

                    <p><strong><?php _e('Plugin Stats', 'wp-stats'); ?></strong></p>

                    <!-- Admin Plugins Stats Filter -->
                    <?php echo apply_filters('wp_stats_page_admin_plugins', $page_admin_plugins_stats); ?>

                    <p><strong><?php printf(_n('Top %s Recent Stat', 'Top %s Recent Stats', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)); ?></strong></p>
                    <input type="checkbox" name="stats_display[]" id="wpstats_recent_posts" value="recent_posts"<?php checked(1, $stats_display['recent_posts']); ?> />&nbsp;&nbsp;<label for="wpstats_recent_posts"><?php printf(_n('%s Most Recent Post', '%s Most Recent Posts', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)); ?></label><br />
                    <input type="checkbox" name="stats_display[]" id="wpstats_recent_comments" value="recent_comments"<?php checked(1, $stats_display['recent_comments']); ?> />&nbsp;&nbsp;<label for="wpstats_recent_comments"><?php printf(_n( '%s Most Recent Comment', '%s Most Recent Comments', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)); ?></label><br />

                    <!-- Admin Recent Stats Filter -->
                    <?php echo apply_filters('wp_stats_page_admin_recent', $page_admin_recent_stats); ?>

                    <p><strong><?php printf(_n('Top %s Most/Highest Stat', 'Top %s Most/Highest Stats', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)); ?></strong></p>
                    <input type="checkbox" name="stats_display[]" id="wpstats_commented_post" value="commented_post"<?php checked(1, $stats_display['commented_post']); ?> />&nbsp;&nbsp;<label for="wpstats_commented_post"><?php printf(_n( '%s Most Commented Post', '%s Most Commented Posts', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)); ?></label><br />
                    <input type="checkbox" name="stats_display[]" id="wpstats_commented_page" value="commented_page"<?php checked(1, $stats_display['commented_page']); ?> />&nbsp;&nbsp;<label for="wpstats_commented_page"><?php printf(_n( '%s Most Commented Page', '%s Most Commented Pages', $stats_mostlimit, 'wp-stats'), number_format_i18n($stats_mostlimit)); ?></label><br />


                    <!-- Admin Most Stats Filter -->
                    <?php echo apply_filters('wp_stats_page_admin_most', $page_page_admin_most_stats); ?>

                    <p><strong><?php _e('Authors Stats', 'wp-stats'); ?></strong></p>
                    <input type="checkbox" name="stats_display[]" id="wpstats_authors" value="authors"<?php checked(1, $stats_display['authors']); ?> />&nbsp;&nbsp;<label for="wpstats_authors"><?php _e('Authors', 'wp-stats'); ?></label><br />

                    <!-- Admin Authors Stats Filter -->
                    <?php echo apply_filters('wp_stats_page_admin_authors', $page_admin_authors_stats); ?>

                    <p><strong><?php _e('Comments\' Members Stats', 'wp-stats'); ?></strong></p>
                    <input type="checkbox" name="stats_display[]" id="wpstats_comment_members" value="comment_members"<?php checked(1, $stats_display['comment_members']); ?> />&nbsp;&nbsp;<label for="wpstats_comment_members"><?php _e('Comment Members', 'wp-stats'); ?></label><br />

                    <!-- Admin Comments' Members Stats Filter -->
                    <?php echo apply_filters('wp_stats_page_admin_comments_members', $admin_comments_members_stats); ?>

                    <p><strong><?php _e('Misc Stats', 'wp-stats'); ?></strong></p>
                    <input type="checkbox" name="stats_display[]" id="wpstats_post_cats" value="post_cats"<?php checked(1, $stats_display['post_cats']); ?> />&nbsp;&nbsp;<label for="wpstats_post_cats"><?php _e('Post Categories', 'wp-stats'); ?></label><br />
                    <input type="checkbox" name="stats_display[]" id="wpstats_link_cats" value="link_cats"<?php checked(1, $stats_display['link_cats']); ?> />&nbsp;&nbsp;<label for="wpstats_link_cats"><?php _e('Link Categories', 'wp-stats'); ?></label><br />
                    <input type="checkbox" name="stats_display[]" id="wpstats_tags_list" value="tags_list"<?php checked(1, $stats_display['tags_list']); ?> />&nbsp;&nbsp;<label for="wpstats_tags_list"><?php _e('Tags List', 'wp-stats'); ?></label><br />

                    <!-- Admin Misc Stats Filter -->
                    <?php echo apply_filters('wp_stats_page_admin_misc', $page_admin_misc_stats); ?>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="Submit" class="button" value="<?php _e('Save Changes', 'wp-stats'); ?>" />
        </p>
    </div>
</form>