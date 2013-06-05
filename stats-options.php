<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.8 Plugin: WP-Stats 2.50										|
|	Copyright (c) 2009 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- WordPress Statistics															|
|	- wp-content/plugins/wp-stats/stats-options.php						|
|																							|
+----------------------------------------------------------------+
*/


### Variables Variables Variables
$base_name = plugin_basename('wp-stats/stats-options.php');
$base_page = 'admin.php?page='.$base_name;
$mode = trim($_GET['mode']);
$stats_settings = array('stats_mostlimit', 'stats_display', 'stats_url', 'widget_stats');


### Form Processing
// Update Options
if(!empty($_POST['Submit'])) {
	$stats_url = addslashes(trim($_POST['stats_url']));
	$stats_mostlimit = intval(trim($_POST['stats_mostlimit']));
	$stats_display = $_POST['stats_display'];
	if($stats_display) {
		foreach($stats_display as $stat_display) {
			$stat_display = addslashes($stat_display);
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
			$text .= '<font color="green">'.$update_stats_text[$i].' '.__('Updated', 'wp-stats').'</font><br />';
		}
		$i++;
	}
	if(empty($text)) {
		$text = '<font color="red">'.__('No Stats Option Updated', 'wp-stats').'</font>';
	}
}
// Uninstall WP-Stats
if(!empty($_POST['do'])) {
	switch($_POST['do']) {		
		case __('UNINSTALL WP-Stats', 'wp-stats') :
			if(trim($_POST['uninstall_stats_yes']) == 'yes') {
				echo '<div id="message" class="updated fade">';
				echo '<p>';
				foreach($stats_settings as $setting) {
					$delete_setting = delete_option($setting);
					if($delete_setting) {
						echo '<font color="green">';
						printf(__('Setting Key \'%s\' has been deleted.', 'wp-stats'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					} else {
						echo '<font color="red">';
						printf(__('Error deleting Setting Key \'%s\'.', 'wp-stats'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					}
				}
				echo '</p>';
				echo '</div>'; 
				$mode = 'end-UNINSTALL';
			}
			break;
	}
}


### Determines Which Mode It Is
switch($mode) {
		//  Deactivating WP-Stats
		case 'end-UNINSTALL':
			$deactivate_url = 'plugins.php?action=deactivate&amp;plugin=wp-stats/wp-stats.php';
			if(function_exists('wp_nonce_url')) { 
				$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_wp-stats/wp-stats.php');
			}
			echo '<div class="wrap">';
			echo '<h2>'.__('Uninstall WP-Stats', 'wp-stats').'</h2>';
			echo '<p><strong>'.sprintf(__('<a href="%s">Click Here</a> To Finish The Uninstallation And WP-Stats Will Be Deactivated Automatically.', 'wp-stats'), $deactivate_url).'</strong></p>';
			echo '</div>';
			break;
	// Main Page
	default:
		$stats_mostlimit = intval(get_option('stats_mostlimit'));
		$stats_display = get_option('stats_display');
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__); ?>">
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('Stats Options', 'wp-stats'); ?></h2> 
	<table class="form-table">
		 <tr>
			<th scope="row" valign="top"><?php _e('Stats URL', 'wp-stats'); ?></th>
			<td>
				<input type="text" name="stats_url" value="<?php echo get_option('stats_url'); ?>" size="50" dir="ltr" /><br /><?php _e('URL To Stats Page.<br />Example: http://www.yoursite.com/blogs/stats/<br />Example: http://www.yoursite.com/blogs/?page_id=2', 'wp-stats'); ?>
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
<p>&nbsp;</p>

<!-- Uninstall WP-Stats -->
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__); ?>">
<div class="wrap"> 
	<h3><?php _e('Uninstall WP-Stats', 'wp-stats'); ?></h3>
	<p>
		<?php _e('Deactivating WP-Stats plugin does not remove any data that may have been created, such as the stats options. To completely remove this plugin, you can uninstall it here.', 'wp-stats'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('WARNING:', 'wp-stats'); ?></strong><br />
		<?php _e('Once uninstalled, this cannot be undone. You should use a Database Backup plugin of WordPress to back up all the data first.', 'wp-stats'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('The following WordPress Options will be DELETED:', 'wp-stats'); ?></strong><br />
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e('WordPress Options', 'wp-stats'); ?></th>
			</tr>
		</thead>
		<tr>
			<td valign="top">
				<ol>
				<?php
					foreach($stats_settings as $settings) {
						echo '<li>'.$settings.'</li>'."\n";
					}
				?>
				</ol>
			</td>
		</tr>
	</table>
	<p>&nbsp;</p>
	<p style="text-align: center;">
		<input type="checkbox" name="uninstall_stats_yes" value="yes" />&nbsp;<?php _e('Yes', 'wp-stats'); ?><br /><br />
		<input type="submit" name="do" value="<?php _e('UNINSTALL WP-Stats', 'wp-stats'); ?>" class="button" onclick="return confirm('<?php _e('You Are About To Uninstall WP-Stats From WordPress.\nThis Action Is Not Reversible.\n\n Choose [Cancel] To Stop, [OK] To Uninstall.', 'wp-stats'); ?>')" />
	</p>
</div> 
</form>
<?php
} // End switch($mode)
?>