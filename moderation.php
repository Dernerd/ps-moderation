<?php
/*
Plugin Name: Moderation
Plugin URI: http://premium.wpmudev.org/project/moderation
Description: Moderiere Beiträge, Kommentare und Blogs in Deiner WordPress Mu-Installation
Author: WMS N@W
Version: 1.1.1
Author URI: https://n3rds.work
Network: true
*/

/*
Copyright 2007-2022 WMS N@W (https://n3rds.work)
Author - DerN3rd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
require 'psource/psource-plugin-update/psource-plugin-updater.php';
$MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://n3rds.work//wp-update-server/?action=get_metadata&slug=ps-moderation', 
	__FILE__, 
	'ps-moderation' 
);

$moderation_current_version = '1.1.1';
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

if ( !isset( $moderation_save_to_archive ))
    $moderation_save_to_archive = 'all'; //Either 'all' or 'removed'

if ( !isset( $moderation_use_filters ))
    $moderation_use_filters = array(
        'post'      => true,
        'comment'   => true,
        'blog'      => true,
    );

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
if (!isset($_GET['key']) || $_GET['key'] == '' || $_GET['key'] === ''){
	add_action('admin_head', 'moderation_make_current');
}
add_action('init', 'moderation_init');
add_action('admin_menu', 'moderation_plug_pages');
add_action('network_admin_menu', 'moderation_plug_pages');
add_action('wpmu_options', 'moderation_site_admin_options');
add_action('update_wpmu_options', 'moderation_site_admin_options_process');
add_action('wp_print_scripts', 'moderation_print_scripts');
add_action('wp_head','moderation_head');
if ( $moderation_use_filters['post'] === true )
    add_filter('the_content', 'moderation_post_filter', 20, 1);
if ( $moderation_save_to_archive == 'all' ) {
	add_action('save_post', 'moderation_post_archive_insert');
	add_action('comment_post', 'moderation_comment_archive_insert');
}
add_action('admin_footer', 'moderation_warnings_check');
if ( $moderation_use_filters['blog'] === true )
    add_filter('wp_footer', 'moderation_blog_filter');
add_action('delete_post', 'moderation_post_delete');
add_action('delete_blog', 'moderation_blog_delete', 10, 1);
add_action('delete_comment', 'moderation_comment_delete');
if ( $moderation_use_filters['comment'] === true )
    add_filter('get_comment_text', 'moderation_comment_filter', 20, 1);
add_filter('wpmu_users_columns', 'moderation_site_admin_users_column_header');
add_action('manage_users_custom_column','moderation_site_admin_users_column_content', 1, 2);
add_filter('admin_menu', 'moderation_hook_admin_menu');
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
function moderation_make_current() {
	global $wpdb, $moderation_current_version;
	if (get_site_option( "moderation_version" ) == '') {
		add_site_option( 'moderation_version', '0.0.0' );
	}

	if (get_site_option( "moderation_version" ) == $moderation_current_version) {
		// do nothing
	} else {
		//up to current version
		update_site_option( "moderation_installed", "no" );
		update_site_option( "moderation_version", $moderation_current_version );
	}
	moderation_global_install();
	//--------------------------------------------------//
	if (get_option( "moderation_version" ) == '') {
		add_option( 'moderation_version', '0.0.0' );
	}

	if (get_option( "moderation_version" ) == $moderation_current_version) {
		// do nothing
	} else {
		//up to current version
		update_option( "moderation_version", $moderation_current_version );
		moderation_blog_install();
	}
}

function moderation_blog_install() {
	global $wpdb, $moderation_current_version;
}

function moderation_global_install() {
	global $wpdb, $moderation_current_version;
	if (get_site_option( "moderation_installed" ) == '') {
		add_site_option( 'moderation_installed', 'no' );
	}

	if (get_site_option( "moderation_installed" ) == "yes") {
		// do nothing
	} else {

		$moderation_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "moderation_reports` (
  `report_ID` bigint(20) unsigned NOT NULL auto_increment,
  `report_blog_ID` int(11) NOT NULL default '0',
  `report_object_type` varchar(255),
  `report_object_ID` int(11) NOT NULL default '0',
  `report_reason` varchar(255),
  `report_note` TEXT,
  `report_user_ID` int(11) NOT NULL default '0',
  `report_user_email` varchar(255),
  `report_user_IP` varchar(100),
  `report_stamp` varchar(255),
  `report_date` datetime NOT NULL default '0000-00-00 00:00:00',
  `report_date_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
  `report_status` varchar(20) NOT NULL default 'new',
  PRIMARY KEY  (`report_ID`)
) ENGINE=MyISAM;";
		$moderation_table2 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "post_archive` (
  `post_archive_id` bigint(20) unsigned NOT NULL auto_increment,
  `blog_id` bigint(20),
  `post_id` bigint(20),
  `post_author` bigint(20),
  `post_title` TEXT,
  `post_content` TEXT,
  `post_type` varchar(255),
  `post_stamp` varchar(255),
  `post_date` datetime NOT NULL default '0000-00-00 00:00:00',
  `post_date_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`post_archive_id`)
) ENGINE=MyISAM;";
		$moderation_table3 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "moderation_warnings` (
  `warning_ID` bigint(20) unsigned NOT NULL auto_increment,
  `warning_user_ID` int(11) NOT NULL default '0',
  `warning_read` tinyint(1) NOT NULL default '0',
  `warning_note` TEXT,
  PRIMARY KEY  (`warning_ID`)
) ENGINE=MyISAM;";
		$moderation_table4 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "comment_archive` (
  `comment_archive_id` bigint(20) unsigned NOT NULL auto_increment,
  `blog_id` bigint(20),
  `comment_id` bigint(20),
  `post_id` bigint(20),
  `comment_author_user_id` bigint(20),
  `comment_author_email` varchar(255),
  `comment_author_ip` varchar(255),
  `comment_author_url` varchar(255),
  `comment_content` TEXT,
  `comment_stamp` varchar(255),
  `comment_date` datetime NOT NULL default '0000-00-00 00:00:00',
  `comment_date_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`comment_archive_id`)
) ENGINE=MyISAM;";

		$wpdb->query( $moderation_table1 );
		$wpdb->query( $moderation_table2 );
		$wpdb->query( $moderation_table3 );
		$wpdb->query( $moderation_table4 );
		update_site_option( "moderation_installed", "yes" );
	}
}


function is_moderator( $user_login = false ) {
	global $current_user;

	if ( !$current_user && !$user_login ) {
		return false;
	}
	if ( is_super_admin( $user_login ) ) {
		return true;
	}
	if ( $user_login ) {
		$user_login = sanitize_user( $user_login );
	} else {
		$user_login = $current_user->user_login;
	}

	$site_moderators = get_site_option( 'site_moderators' );
	if ( is_array( $site_moderators ) && in_array( $user_login, $site_moderators ) ) {
		return true;
	}
	return false;
}

function moderation_plug_pages() {
	global $wpdb, $user_id;
	if ( is_moderator() ) {
		$post_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'post' AND report_status = 'new'");
		$blog_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'blog' AND report_status = 'new'");
		$comment_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'comment' AND report_status = 'new'");
		$total_count = $post_count + $blog_count + $comment_count;
		if ( $post_count > 0 ) {
			$post_count = ' <span class="moderation-posts-menu"><span class="update-plugins"><span class="moderation-post-count count-' . $post_count . '">' . $post_count . '</span></span></span>';
		} else {
			$post_count = '';
		}
		if ( $blog_count > 0 ) {
			$blog_count = ' <span class="moderation-blogs-menu"><span class="update-plugins"><span class="moderation-blog-count count-' . $blog_count . '">' . $blog_count . '</span></span></span>';
		} else {
			$blog_count = '';
		}
		if ( $comment_count > 0 ) {
			$comment_count = ' <span class="moderation-comments-menu"><span class="update-plugins"><span class="moderation-comment-count count-' . $comment_count . '">' . $comment_count . '</span></span></span>';
		} else {
			$comment_count = '';
		}
		if ( $total_count > 0 ) {
			$total_count = ' <span class="moderation-total-menu"><span class="update-plugins"><span class="moderation-total-count count-' . $total_count . '">' . $total_count . '</span></span></span>';
		} else {
			$total_count = '';
		}
		add_menu_page(__('Moderation', 'moderation'), __('Moderation', 'moderation') . $total_count, 'read', 'moderation', 'moderation_overview');
		add_submenu_page('moderation', __('Blog Moderation', 'moderation'), __('Blogs', 'moderation') . $blog_count, 'read', 'moderation-blogs', 'moderation_blogs' );
		add_submenu_page('moderation', __('Beitrag Moderation', 'moderation'), __('Beiträge', 'moderation') . $post_count, 'read', 'moderation-posts', 'moderation_posts' );
		add_submenu_page('moderation', __('Kommentar Moderation', 'moderation'), __('Kommentare', 'moderation') . $comment_count, 'read', 'moderation-comments', 'moderation_comments' );
		add_submenu_page('moderation', __('Meldearchiv', 'moderation'), __('Meldearchiv', 'moderation'), 'read', 'moderation-report-archive', 'moderation_report_archive' );
		add_submenu_page('moderation', __('Beitrag-Archiv', 'moderation'), __('Beitrag-Archiv', 'moderation'), 'read', 'moderation-post-archive', 'moderation_post_archive' );
		add_submenu_page('moderation', __('Kommentar-Archiv', 'moderation'), __('Kommentar-Archiv', 'moderation'), 'read', 'moderation-comment-archive', 'moderation_comment_archive' );
	}
	
	$current_user = wp_get_current_user();
	
	if ($wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_warnings WHERE warning_user_ID = '" . $current_user->ID . "' AND warning_read = '0'") > 0) {
		add_menu_page(__('Moderationswarnung', 'moderation'), __('Moderationswarnung', 'moderation'), 'read', 'moderation-warning', 'moderation_warnings');
	}
}

function moderation_hook_admin_menu() {
	global $menu, $submenu, $_wp_submenu_nopriv;

	//unset($menu[100]);
	foreach ($menu as $key=>$val) {
		if ($val[2] == 'moderation-warning') {
			unset($menu[$key]);
		}
	}
}

function moderation_site_admin_options_process() {
	$site_moderators = explode( ' ', str_replace( ",", " ", $_POST['site_moderators'] ) );
	update_site_option( 'site_moderators' , $site_moderators );
	update_site_option( 'moderators_can_remove_users' , $_POST['moderators_can_remove_users'] );
	update_site_option( 'moderators_can_remove_blogs' , $_POST['moderators_can_remove_blogs'] );
	if( $_POST['moderation_report_post_reasons'] != '' ) {
		$moderation_report_post_reasons = explode( "\n", stripslashes( $_POST['moderation_report_post_reasons'] ) );
		foreach( (array) $moderation_report_post_reasons as $moderation_report_post_reason ) {
			$report_post_reasons[] = trim( $moderation_report_post_reason );
		}
		update_site_option( "moderation_report_post_reasons", $report_post_reasons );
	} else {
		update_site_option( "moderation_report_post_reasons", '' );
	}
	if( $_POST['moderation_report_comment_reasons'] != '' ) {
		$moderation_report_comment_reasons = explode( "\n", stripslashes( $_POST['moderation_report_comment_reasons'] ) );
		foreach( (array) $moderation_report_comment_reasons as $moderation_report_comment_reason ) {
			$report_comment_reasons[] = trim( $moderation_report_comment_reason );
		}
		update_site_option( "moderation_report_comment_reasons", $report_comment_reasons );
	} else {
		update_site_option( "moderation_report_comment_reasons", '' );
	}
	if( $_POST['moderation_report_blog_reasons'] != '' ) {
		$moderation_report_blog_reasons = explode( "\n", stripslashes( $_POST['moderation_report_blog_reasons'] ) );
		foreach( (array) $moderation_report_blog_reasons as $moderation_report_blog_reason ) {
			$report_blog_reasons[] = trim( $moderation_report_blog_reason );
		}
		update_site_option( "moderation_report_blog_reasons", $report_blog_reasons );
	} else {
		update_site_option( "moderation_report_blog_reasons", '' );
	}
	if( $_POST['moderation_remove_notes'] != '' ) {
		$moderation_remove_notes = explode( "\n", stripslashes( $_POST['moderation_remove_notes'] ) );
		foreach( (array) $moderation_remove_notes as $moderation_remove_note ) {
			$remove_notes[] = trim( $moderation_remove_note );
		}
		update_site_option( "moderation_remove_notes", $remove_notes );
	} else {
		update_site_option( "moderation_remove_notes", '' );
	}
}

function moderation_init() {
	global $wpdb, $moderation_current_version;

	if ( !is_multisite() )
		exit( 'Das Moderations-Plugin ist nur mit WordPress Multisite kompatibel.' );

	wp_register_script('moderation', plugins_url('ps-moderation/js/moderation.js'), array('thickbox'), $moderation_current_version);

	load_plugin_textdomain('moderation', false, dirname(plugin_basename(__FILE__)).'/languages');

	if (isset($_REQUEST['moderation_action'])) {

		switch ($_REQUEST['moderation_action']) {

			case "report_form" :
				$ot = $_REQUEST['object_type'];
				$oi = $_REQUEST['object_id'];
				moderation_report_form($ot, $oi);
				exit();

			break;

			case "submit_report" :

				moderation_process_submission();
				echo '<p>&nbsp;</p><p>&nbsp;</p><p>'.__('Danke für den Bericht. Wir werden uns darum kümmern', 'moderation').'</p>'; //// CHMAC TODO Prettify and nocache header this
				echo '<script type="text/javascript">setTimeout("tb_remove()",5000);</script>';
				exit();

			break;

		}

	}

}

function moderation_print_scripts() {
	wp_enqueue_script('moderation');
}

function moderation_process_submission() {
	global $wpdb, $user_id;

	$user_id = get_current_user_id();

	$user_email = '';
	if (isset($_POST['report_author_email'])) {
		$user_email = $_POST['report_author_email'];
	}

	if ( empty( $user_email ) && !empty( $user_id ) ) {
		$user_email = $wpdb->get_var($wpdb->prepare("SELECT user_email FROM " . $wpdb->users . " WHERE ID = %d", $user_id));
	}

	$post_type = $_POST['object_type'];
	$post_id = $_POST['object_id'];
	$report_reason = $_POST['report_reason'];
	$report_note = $_POST['report_note'];

	$wpdb->query($wpdb->prepare("INSERT IGNORE INTO " . $wpdb->base_prefix . "moderation_reports
	(report_blog_ID, report_object_type, report_object_ID, report_reason, report_note, report_user_ID, report_user_email, report_user_IP, report_stamp, report_date, report_date_gmt)
	VALUES
	('" . $wpdb->blogid . "', %s, %d, %s, %s, %d, %s, %s, '" . time() . "', '" . current_time('mysql') . "', '" . get_gmt_from_date( current_time('mysql') ) . "')", $post_type, $post_id, $report_reason, $report_note, $user_id, $user_email, $_SERVER['REMOTE_ADDR']));
}

function moderation_report_link($object_type, $object_id, $link_text = '', $link_atts = array()) {
    // Set default attribute values, which also support user defined atts
    // relly on wordpress to do the url parameter filtering (shortcode_atts)
    $default_atts = array_merge(
        array_fill_keys( array_keys( (array) $link_atts ), '' ),
        array( 'tag'   => 'p', 'class' => 'wp-report-this' ));
    $link_atts = shortcode_atts( $default_atts, (array) $link_atts );

    // Evaluate $object_type and determin the value of the link's title
    switch ($object_type) {
        case 'blog':
            $link_text = !empty($link_text)
                ? __($link_text , 'moderation')
                : __('&#9888; Blog melden' , 'moderation');
            break;

        case 'post':
            $link_text = !empty($link_text)
                ? __($link_text , 'moderation')
                : __('&#9888; Inhalt melden', 'moderation');
            break;

        case 'comment':
            $link_text = !empty($link_text)
                ? __($link_text , 'moderation')
                : __('&#9888; Kommentar melden', 'moderation');
            break;

        default:
            // Bail out if object_type was not recognised
            return '';
    }

    // Construct moderation URL and the link itself (consider local arg_separator.output)
    $link_url = get_option( 'siteurl') . '?' . http_build_query( array(
        'moderation_action' => 'report_form',
        'object_type'       => $object_type,
        'object_id'         => $object_id,
        'width'             => 250,
        'height'            => 300,
    ));
    $link = '<a href="' . $link_url . '" class="thickbox" style="color: orangered" title="' . $link_text . '">' . $link_text . '</a>';

    // Construct HTML container tag
    $tag = $link_atts['tag'];
    unset($link_atts['tag']);

    // Construct HTML container atts
    $tag_atts = '';
    foreach ($link_atts as $att_name => $att_value) {
        $tag_atts .= " {$att_name}=\"{$att_value}\"";
    }

    return "<{$tag}{$tag_atts}>{$link}</{$tag}>";
}

function moderation_post_archive_insert($post_ID) {
	global $wpdb, $current_site;

	$post = get_post($post_ID);
	if ( !empty( $post->post_content ) && !empty($post->post_title) ) {
		$wpdb->query($wpdb->prepare("INSERT IGNORE INTO " . $wpdb->base_prefix . "post_archive
		(blog_id, post_id, post_author, post_title, post_content, post_type, post_stamp, post_date, post_date_gmt)
		VALUES
		('" . $wpdb->blogid . "', %d, %d, %s, %s, %s, '" . time() . "', '" . $post->post_date . "', '" . $post->post_date_gmt . "')", $post_ID, $post->post_author, $post->post_title, $post->post_content, $post->post_type));
	}
}

function moderation_comment_archive_insert($comment_ID){
	global $wpdb, $current_site;

	$comment = get_comment($comment_ID);
	if ( !empty( $comment->comment_content ) ) {
		$wpdb->query("INSERT IGNORE INTO " . $wpdb->base_prefix . "comment_archive
		(blog_id, comment_id, post_id, comment_author_user_id, comment_author_email, comment_author_ip, comment_author_url, comment_content, comment_stamp, comment_date, comment_date_gmt)
		VALUES
		('" . $wpdb->blogid . "', '" . $comment->comment_ID . "', '" . $comment->comment_post_ID . "', '" . $comment->user_id . "', '" . $comment->comment_author_email . "', '" . $comment->comment_author_IP . "', '" . $comment->comment_author_url . "', '" . $comment->comment_content . "', '" . time() . "', '" . $comment->comment_date . "', '" . $comment->comment_date_gmt . "')");
	}
}

function moderation_warnings_check() {
	global $wpdb;

	
	$current_user = wp_get_current_user();
	
	if ( !strpos($_SERVER['REQUEST_URI'], 'warning') ){
		$user_warning_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_warnings WHERE warning_user_ID = '" . $current_user->ID . "' AND warning_read = '0'");
		if ( $user_warning_count > 0 ) {
			echo "
			<SCRIPT LANGUAGE='javascript'>
			window.location='admin.php?page=moderation-warning';
			</script>
			";
		}
	}
}

function moderation_post_delete($post_ID) {
	global $wpdb;
	$wpdb->query($wpdb->prepare("DELETE FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_status = 'new' AND report_blog_ID = '" . $wpdb->blogid . "' AND report_object_type = 'post' AND report_object_ID = %s", $post_ID));
}

function moderation_blog_delete($blog_ID) {
	global $wpdb;
	$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_status = 'new' AND report_blog_ID = '" . $wpdb->blogid . "'" );
}

function moderation_comment_delete($comment_ID) {
	global $wpdb;
	$wpdb->query($wpdb->prepare( "DELETE FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_status = 'new' AND report_blog_ID = '" . $wpdb->blogid . "' AND report_object_type = 'comment' AND report_object_ID = %d", $comment_ID ));
}

function moderation_site_admin_users_column_header($posts_columns) {
	$new_column = array('warnings' => __('Warnungen', 'moderation'));
	$posts_columns = array_merge($posts_columns, $new_column);
	return $posts_columns;
}

function moderation_site_admin_users_column_content($column,$uid) {
	global $wpdb;
	if ( $column == 'warnings' ) {
		$warning_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_warnings WHERE warning_user_ID = %d", $uid));
		echo $warning_count;
	}
}
//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function moderation_report_post($link_text = '', $link_atts = array()){
	global $post, $moderation_use_filters;
    $moderation_use_filters['post'] = false;
    if (!is_array($link_atts)) $link_atts = array('tag' => (string) $link_atts);
    return moderation_report_link('post', $post->ID, $link_text, $link_atts);
}

function moderation_report_comment($link_text = '', $link_atts = array()){
	global $comment, $moderation_use_filters;

    $moderation_use_filters['comment'] = false;
    if (!is_array($link_atts)) {
		$link_atts = array('tag' => (string) $link_atts);
	}
    return moderation_report_link('comment', $comment->comment_ID, $link_text, $link_atts);
}

function moderation_report_blog($link_text = '', $link_atts = array()){
	global $wpdb, $moderation_use_filters;

    $moderation_use_filters['blog'] = false;
    if (!is_array($link_atts)) {
		$link_atts = array('tag' => (string) $link_atts);
	}
    return moderation_report_link('blog', $wpdb->blogid, $link_text, $link_atts);
}

function moderation_post_filter($content){
	global $post, $wpdb, $moderation_use_filters;

	$link = '';
	if ( !is_admin() && $wpdb->blogid != BLOG_ID_CURRENT_SITE && !is_search() && $moderation_use_filters['post'] === true) {
		$link = moderation_report_link('post', $post->ID);
	}
	return $content . $link;
}

function moderation_comment_filter($content){
	global $comment, $wpdb, $moderation_use_filters;

	$link = '';
	if ( !is_admin() && $wpdb->blogid != BLOG_ID_CURRENT_SITE && $moderation_use_filters['comment'] === true) {
		$link = moderation_report_link('comment', $comment->comment_ID);
	}
	return $content . $link;
}

function moderation_blog_filter(){
    global $wpdb, $moderation_use_filters;
	if ( !is_admin() && $wpdb->blogid != BLOG_ID_CURRENT_SITE && $moderation_use_filters['blog'] === true) {
		echo moderation_report_link('blog', $wpdb->blogid);
	}
}

function moderation_head() {

	wp_enqueue_style('thickbox_css', get_bloginfo('wpurl')."/wp-includes/js/thickbox/thickbox.css", false, false, 'screen');
	wp_print_styles(array('thickbox_css'));
	?>
	<script type="text/javascript">
	var tb_pathToImage = "<?php echo get_option('siteurl') . '/' . WPINC; ?>/js/thickbox/loadingAnimation.gif";
	var tb_closeImage = "<?php echo get_option('siteurl') . '/' . WPINC; ?>/js/thickbox/tb-close.png";
	var moderation_ajaxurl = "<?php echo get_option('siteurl'); ?>/";
	function moderation_submit() {
		jQuery('#moderation-report').load( moderation_ajaxurl, jQuery('form#moderation-report-form').serializeArray(), function() {
			jQuery('#moderation-report').append('<p><?php  _e('Drücke ESC oder klicke auf eine beliebige Stelle außerhalb dieses Felds, um es zu schließen', 'moderation')?>.</p>');
		} );
		return false;
	}
	</script>
	<?php

}

function moderation_site_admin_options() {
	$site_moderators = get_site_option( 'site_moderators' );
	if ( !empty( $site_moderators ) ) {
		$site_moderators = implode(' ', $site_moderators );
	}
	$moderators_can_remove_users = get_site_option('moderators_can_remove_users', 'no');
	$moderators_can_remove_blogs = get_site_option('moderators_can_remove_blogs', 'no');

	$moderation_report_post_reasons = get_site_option('moderation_report_post_reasons', array('Spam','Language'));
	$moderation_report_comment_reasons = get_site_option('moderation_report_comment_reasons', array('Spam','Language'));
	$moderation_report_blog_reasons = get_site_option('moderation_report_blog_reasons', array('Spam','Language'));
	$moderation_remove_notes = get_site_option('moderation_remove_notes', array('Warnung: AGB verletzt - Weitere Verstöße können dazu führen, dass Dein Konto entfernt wird','Warnung: AUP verletzt - Weitere Verstöße können dazu führen, dass Dein Konto entfernt wird'));
	?>
		<h3><?php _e('Moderation', 'moderation'); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Websiten-Moderatoren', 'moderation') ?></th>
				<td>
					<input name="site_moderators" id="site_moderators" style="width: 95%;" value="<?php echo $site_moderators; ?>" size="45" type="text">
					<br />
					<?php _e('Diese Benutzer können auf die Registerkarte Moderation zugreifen. Durch Leerzeichen getrennte Liste von Benutzernamen.', 'moderation') ?>
					<br />
					<?php _e('Beachte, dass alle Webseiten-Administratoren standardmäßig auf die Registerkarte "Moderation" zugreifen können und hier nicht aufgelistet werden müssen.', 'moderation') ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Moderatoren können Benutzer entfernen', 'moderation') ?></th>
				<td>
					<input name="moderators_can_remove_users" id="moderators_can_remove_users" value="yes" <?php if ( $moderators_can_remove_users == 'yes' ) { echo 'checked="checked"'; } ?> type="radio"> <?php _e('Ja', 'moderation'); ?><br />
					<input name="moderators_can_remove_users" id="moderators_can_remove_users" value="no" <?php if ( $moderators_can_remove_users == 'no' ) { echo 'checked="checked"'; } ?> type="radio"> <?php _e('Nein', 'moderation'); ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Moderatoren können Blogs entfernen', 'moderation') ?></th>
				<td>
					<input name="moderators_can_remove_blogs" id="moderators_can_remove_blogs" value="yes" <?php if ( $moderators_can_remove_blogs == 'yes' ) { echo 'checked="checked"'; } ?> type="radio"> <?php _e('Ja', 'moderation'); ?><br />
					<input name="moderators_can_remove_blogs" id="moderators_can_remove_blogs" value="no" <?php if ( $moderators_can_remove_blogs == 'no' ) { echo 'checked="checked"'; } ?> type="radio"> <?php _e('Nein', 'moderation'); ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Gründe für Beitrag melden', 'moderation') ?></th>
				<td>
					<textarea name="moderation_report_post_reasons" id="moderation_report_post_reasons" cols='40' rows='5' style="width: 95%;"><?php echo $moderation_report_post_reasons == '' ? '' : @implode( "\n", $moderation_report_post_reasons ); ?></textarea>
					<br />
					<?php _e('Gründe für die Meldung eines Beitrags. Eine pro Zeile.', 'moderation') ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Gründe für Kommentar melden', 'moderation') ?></th>
				<td>
					<textarea name="moderation_report_comment_reasons" id="moderation_report_comment_reasons" cols='40' rows='5' style="width: 95%;"><?php echo $moderation_report_comment_reasons == '' ? '' : @implode( "\n", $moderation_report_comment_reasons ); ?></textarea>
					<br />
					<?php _e('Gründe für die Meldung eines Kommentars. Eine pro Zeile.', 'moderation') ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Blog Meldegründe', 'moderation') ?></th>
				<td>
					<textarea name="moderation_report_blog_reasons" id="moderation_report_blog_reasons" cols='40' rows='5' style="width: 95%;"><?php echo $moderation_report_blog_reasons == '' ? '' : @implode( "\n", $moderation_report_blog_reasons ); ?></textarea>
					<br />
					<?php _e('Gründe für die Meldung eines Blogs. Eine pro Zeile.', 'moderation') ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Moderations-Hinweis', 'moderation') ?></th>
				<td>
					<textarea name="moderation_remove_notes" id="moderation_remove_notes" cols='40' rows='5' style="width: 95%;"><?php echo $moderation_remove_notes == '' ? '' : @implode( "\n", $moderation_remove_notes ); ?></textarea>
					<br />
					<?php _e('Hinweis für den Benutzer, wenn ein Beitrag oder Kommentar entfernt wird. Eine pro Zeile.', 'moderation') ?>
				</td>
			</tr>
		</table>
	<?php
}

function moderation_report_form($ot, $oi) {

	if ( $ot == 'post' ) {
		$reasons = get_site_option('moderation_report_post_reasons', array('Spam','Language'));
	}
	if ( $ot == 'comment' ) {
		$reasons = get_site_option('moderation_report_comment_reasons', array('Spam','Language'));
	}
	if ( $ot == 'blog' ) {
		$reasons = get_site_option('moderation_report_blog_reasons', array('Spam','Language'));
	}

	$output = '';

	$output .= '<div id="moderation-report">';
	$output .= '<form id="moderation-report-form" action="' . get_option('siteurl') . '" onsubmit="return moderation_submit();" method="post">';
	$output .= '<input type="hidden" name="object_type" value="' . $ot . '">';
	$output .= '<input type="hidden" name="object_id" value="' . $oi . '">';
	$output .= '<input type="hidden" name="moderation_action" value="submit_report">';
	$output .= '<p>' . __('Grund für die Meldung:', 'moderation') . ' <select name="report_reason">';
	foreach ( $reasons as $reason ) {
		$output .= '<option value="' . $reason . '">' . $reason . '</option>';
	}
	$output .= '</select></p>';
	$output .= '<p>' . __('Bemerkungen zur Meldung', 'moderation') . ' (' . __('optional', 'moderation') . '):<br/><textarea name="report_note" style="width:95%;"></textarea></p>';
	if ( !is_user_logged_in() ) {
		$output .= '<p>' . __('Deine Email', 'moderation') . ' (' . __('optional', 'moderation') . '):<br /><input type="text" name="report_author_email">';
	}
	$output .= '<p><input class="button button-primary" type="submit" name="action" value="' . __('Meldung einreichen', 'moderation') . '">';
	$output .= '</form>';
	$output .= '</div>';

	echo $output;

}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function moderation_overview() {
	global $wpdb, $wp_roles, $current_user, $user_id, $current_site;

	if ( !is_moderator() ) {
		die();
	}

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'moderation') ?></p></div><?php
	}
	echo '<div class="wrap">';
	if (!isset($_GET[ 'action' ])) {
		$_GET[ 'action' ] = '';
	}
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			$moderators_can_remove_users = get_site_option('moderators_can_remove_users', 'no');
			$moderators_can_remove_blogs = get_site_option('moderators_can_remove_blogs', 'no');
			$post_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'post' AND report_status = 'new'");
			$blog_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'blog' AND report_status = 'new'");
			$comment_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'comment' AND report_status = 'new'");
			?>
            <h2><?php _e('Moderation', 'moderation') ?></h2>
            <h3><?php _e('Meldungen', 'moderation') ?></h3>
            <p>
            <strong><?php _e('Beiträge', 'moderation'); ?></strong>: <?php echo $blog_count; ?><br />
            <strong><?php _e('Kommentare', 'moderation'); ?></strong>: <?php echo $comment_count; ?><br />
            <strong><?php _e('Blogs', 'moderation'); ?></strong>: <?php echo $blog_count; ?>
            </p>
            <h3><?php _e('Benutzer Information', 'moderation') ?></h3>
            <form name="user_information" method="POST" action="admin.php?page=moderation&action=user_information">
                <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Benutzername', 'moderation') ?></th>
                <td><input type="text" name="user_login" id="user_login" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzer ID', 'moderation') ?></th>
                <td><input type="text" name="uid" id="uid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzer Email', 'moderation') ?></th>
                <td><input type="text" name="user_email" id="user_email" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                </table>
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Fortfahren', 'moderation') ?>" />
            </p>
            </form>
            <?php
			if ( $moderators_can_remove_users == 'yes' ) {
			?>
            <h3><?php _e('Benutzer entfernen', 'moderation') ?></h3>
            <form name="remove_user" method="POST" action="admin.php?page=moderation&action=remove_user">
                <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Benutzername', 'moderation') ?></th>
                <td><input type="text" name="user_login" id="user_login" style="width: 95%" value="" />
                <br /></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzer ID', 'moderation') ?></th>
                <td><input type="text" name="uid" id="uid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzer Email', 'moderation') ?></th>
                <td><input type="text" name="user_email" id="user_email" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                </table>
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Fortfahren', 'moderation') ?>" />
            </p>
            </form>
            <?php
			}
			if ( $moderators_can_remove_blogs == 'yes' ) {
			?>
            <h3><?php _e('Blog entfernen', 'moderation') ?></h3>
            <form name="remove_blog" method="POST" action="admin.php?page=moderation&action=remove_blog">
                <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Blog ID', 'moderation') ?></th>
                <td><input type="text" name="bid" id="bid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Blogname', 'moderation') ?></th>
                <td><input type="text" name="blog_name" id="blog_name" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                </table>
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Fortfahren', 'moderation') ?>" />
            </p>
            </form>
            <?php
			}
		break;
		//---------------------------------------------------//
		case "user_information":
			$uid = $_POST['uid'];
			if ( empty( $uid ) ) {
				$uid = $_GET['uid'];
			}
			$user_login = $_POST['user_login'];
			if ( empty( $user_login ) ) {
				$user_login = $_GET['user_login'];
			}
			$user_email = $_POST['user_email'];
			if ( empty( $user_email ) ) {
				$user_email = $_GET['user_email'];
			}
			if ( !empty( $user_login ) ) {
				$uid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM " . $wpdb->users . " WHERE user_login = %s", $user_login));
			}
			if ( !empty( $user_email ) ) {
				$uid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM " . $wpdb->users . " WHERE user_email = %s", $user_email));
			}
			$user_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $uid . "'");
			if ( $user_count > 0 ) {
				$user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $uid . "'");
				$warning_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_warnings WHERE warning_user_ID = '" . $uid . "'");
				$user_registered = $wpdb->get_var("SELECT user_registered FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $uid . "'");
				$user_email = $wpdb->get_var("SELECT user_email FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $uid . "'");
				$user_blogs = get_blogs_of_user( $uid, true );
				?>
				<h2><?php _e('User Information', 'moderation') ?>: <?php echo $user_login; ?></h2>
				<form name="user_information" method="POST" action="admin.php?page=moderation">
				<p>
				<strong><?php _e('Email', 'moderation'); ?></strong>: <?php echo $user_email; ?>
				<br />
				<strong><?php _e('Registriert', 'moderation'); ?></strong>: <?php echo mysql2date(get_option('date_format'), $user_registered); ?>
				<br />
				<strong><?php _e('Warnungen', 'moderation'); ?></strong>: <?php echo $warning_count; ?>
				<br />
				<strong><?php _e('Beitragsarchiv', 'moderation'); ?></strong>: <a href="admin.php?page=moderation-post-archive&object_type=post&uid=<?php echo $uid;?>" style="text-decoration:none;" ><?php _e('Ansehen', 'moderation'); ?></a>
				<br />
				<strong><?php _e('Blogs', 'moderation'); ?></strong>:
                <?php
				if( is_array( $user_blogs ) ) {
					echo '<br />';
					foreach ( (array) $user_blogs as $key => $val ) {
						$path	= ($val->path == '/') ? '' : $val->path;
						echo '<a href="http://' . $val->domain . $path . '">' . str_replace( '.' . $current_site->domain, '', $val->domain . $path ) . '</a>';
						echo '<br />';
					}
				} else {
					echo __('Keiner', 'moderation');
				}
				?>
				</p>
				<p class="submit">
				<input class="button button-primary" type="submit" name="Submit" value="<?php _e('Zurück', 'moderation') ?>" />
				</p>
				</form>
				<?php
			} else {
				?>
				<h2><?php _e('Fehler', 'moderation') ?></h2>
                <?php
				echo '<p>' . __('Benutzer wurde nicht gefunden.', 'moderation') . '</p>';
			}
		break;
		//---------------------------------------------------//
		case "remove_user":
			$uid = $_POST['uid'];
			if ( empty( $uid ) ) {
				$uid = $_GET['uid'];
			}
			$user_login = $_POST['user_login'];
			if ( empty( $user_login ) ) {
				$user_login = $_GET['user_login'];
			}
			$user_email = $_POST['user_email'];
			if ( empty( $user_email ) ) {
				$user_email = $_GET['user_email'];
			}
			if ( !empty( $user_login ) ) {
				$uid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM " . $wpdb->users . " WHERE user_login = %s", $user_login));
			}
			if ( !empty( $user_email ) ) {
				$uid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM " . $wpdb->users . " WHERE user_email = %s", $user_email));
			}
			$user_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $uid . "'");
			if ( $user_count > 0 ) {
				$user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $uid . "'");
				if ( !is_moderator( $user_login ) ) {
					?>
					<h2><?php _e('Remove User', 'moderation') ?>: <?php echo $user_login; ?></h2>
					<form name="remove_user" method="POST" action="admin.php?page=moderation&action=remove_user_process&uid=<?php echo $uid; ?>">
						<table class="form-table">
						<tr valign="top">
						<th scope="row"><?php _e('Bist du sicher?', 'moderation') ?></th>
						<td>
						<select name="remove_user" id="remove_user">
								<option value="yes"><?php _e('Ja', 'moderation'); ?></option>
								<option value="no" selected="selected" ><?php _e('Nein', 'moderation'); ?></option>
						</select>
						<br /><?php //_e('') ?></td>
						</tr>
						</table>
					<p class="submit">
					<input class="button button-primary" type="submit" name="Submit" value="<?php _e('Fortfahren', 'moderation') ?>" />
					</p>
					</form>
					<?php
				} else {
					?>
					<h2><?php _e('Fehler', 'moderation') ?></h2>
					<?php
					echo '<p>' . __('Du kannst keinen Moderator oder Webseiten-Administrator entfernen.', 'moderation') . '</p>';
				}
			} else {
				?>
				<h2><?php _e('Fehler', 'moderation') ?></h2>
                <?php
				echo '<p>' . __('Benutzer wurde nicht gefunden.', 'moderation') . '</p>';
			}
		break;
		//---------------------------------------------------//
		case "remove_user_process":
			if ( $_POST['remove_user'] == 'no' ) {
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='admin.php?page=moderation';
				</script>
				";
			} else {
				wpmu_delete_user( $_GET['uid'] );
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='admin.php?page=moderation&updated=true&updatedmsg=" . urlencode('Benutzer entfernt') . "';
				</script>
				";
			}
		break;
		//---------------------------------------------------//
		case "remove_blog":
			$bid = $_POST['bid'];
			if ( empty( $bid ) ) {
				$bid = $_GET['bid'];
			}
			$blog_name = $_POST['blog_name'];
			if ( empty( $blog_name ) ) {
				$blog_name = $_GET['blog_name'];
			}
			if ( !empty( $blog_name ) ) {
				if (VHOST == 'yes') {
					$bid = $wpdb->get_var("SELECT blog_id FROM " . $wpdb->blogs . " WHERE domain = '" . $blog_name . "." . $current_site->domains . "'");
				} else {
					$bid = $wpdb->get_var($wpdb->prepare("SELECT blog_id FROM " . $wpdb->blogs . " WHERE path = %s", $current_site->path.$blog_name."/"));
				}
			}
			$blog_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" . $bid . "'");
			if ( $blog_count > 0 ) {
				$blog_details = get_blog_details( $bid );
				if ( $bid != '1' ) {
					?>
					<h2><?php _e('Blog entfernen', 'moderation') ?>: <a href="<?php echo $blog_details->siteurl; ?>" style="text-decoration:none;"><?php echo $blog_details->blogname; ?></a></h2>
					<form name="remove_blog" method="POST" action="admin.php?page=moderation&action=remove_blog_process&bid=<?php echo $bid; ?>">
						<table class="form-table">
						<tr valign="top">
						<th scope="row"><?php _e('Bist du sicher?', 'moderation') ?></th>
						<td>
						<select name="remove_blog" id="remove_blog">
								<option value="yes"><?php _e('Ja', 'moderation'); ?></option>
								<option value="no" selected="selected" ><?php _e('Nein', 'moderation'); ?></option>
						</select>
						<br /><?php //_e('') ?></td>
						</tr>
						</table>
					<p class="submit">
					<input class="button button-primary" type="submit" name="Submit" value="<?php _e('Fortfahren', 'moderation') ?>" />
					</p>
					</form>
					<?php
				} else {
					?>
					<h2><?php _e('Fehler', 'moderation') ?></h2>
					<?php
					echo '<p>' . __('Du kannst den Hauptblog nicht entfernen.', 'moderation') . '</p>';
				}
			} else {
				?>
				<h2><?php _e('Fehler', 'moderation') ?></h2>
                <?php
				echo '<p>' . __('Blog nicht gefunden.', 'moderation') . '</p>';
			}
		break;
		//---------------------------------------------------//
		case "remove_blog_process":
			if ( $_POST['remove_blog'] == 'no' ) {
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='admin.php?page=moderation';
				</script>
				";
			} else {
				wpmu_delete_blog( $_GET['bid'] );
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='admin.php?page=moderation&updated=true&updatedmsg=" . urlencode('Blog entfernt') . "';
				</script>
				";
			}
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function moderation_posts() {
	global $wpdb, $wp_roles, $current_user, $user_id, $current_site, $moderation_save_to_archive;

	if ( !is_moderator() ) {
		die();
	}

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'moderation') ?></p></div><?php
	}
	echo '<div class="wrap">';
	if (!isset($_GET[ 'action' ])) {
		$_GET[ 'action' ] = '';
	}
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
            <h2><?php _e('Beiträge', 'moderation') ?></h2>
			<?php
			if( isset( $_GET[ 'start' ] ) == false ) {
				$start = 0;
			} else {
				$start = intval( $_GET[ 'start' ] );
			}
			if( isset( $_GET[ 'num' ] ) == false ) {
				$num = 30;
			} else {
				$num = intval( $_GET[ 'num' ] );
			}

			$query = "SELECT * FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'post' AND report_status = 'new' GROUP BY report_blog_ID, report_object_ID ORDER BY report_stamp DESC";
			$query .= " LIMIT " . intval( $start ) . ", " . intval( $num );
			$reports = $wpdb->get_results( $query, ARRAY_A );
			if( count( $reports ) < $num ) {
				$next = false;
			} else {
				$next = true;
			}
			if ( count( $reports ) > 0 ) {
				$report_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'post' AND report_status = 'new' GROUP BY report_blog_id, report_object_type, report_object_id ORDER BY report_stamp DESC");
				if ($report_count > 30){
					?>
                    <br />
                    <table><td>
					<fieldset>
					<?php

					//$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];

					if( $start == 0 ) {
						echo __('Vorherige Seite', 'moderation');
					} elseif( $start <= 30 ) {
						echo '<a href="admin.php?page=moderation-posts&start=0&' . $order_sort . ' " style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
					} else {
						echo '<a href="admin.php?page=moderation-posts&start=' . ( $start - $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
					}
					if ( $next ) {
						echo '&nbsp;||&nbsp;<a href="admin.php?page=moderation-posts&start=' . ( $start + $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Nächste Seite', 'moderation') . '</a>';
					} else {
						echo '&nbsp;||&nbsp;' . __('Nächste Seite', 'moderation');
					}
					?>
					</fieldset>
					</td></table>
					<?php
				}
				echo "<form name='process_reports' method='POST' action='admin.php?page=moderation-posts&action=process_reports' >";
				echo "
				<br />
				<table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
				<thead><tr>
				<th scope='col'>" . __('Beitrag', 'moderation') . "</th>
				<th width='25%' scope='col'>" . __('Information', 'moderation') . "</th>
				</tr></thead>
				<tbody id='the-list'>
				";
				//=========================================================//
					$class = (isset($class) && 'alternate' == $class) ? '' : 'alternate';
					$date_format = get_option('date_format');
					$time_format = get_option('time_format');
					foreach ($reports as $report){
					//=========================================================//
					echo "<tr class='" . $class . "'>";

					unset( $blog_details );
					$blog_details = get_blog_details( $report['report_blog_ID'] );

					unset( $post_details );
					unset( $post_permalink );
					switch_to_blog( $report['report_blog_ID'] );
					$post_details = get_post( $report['report_object_ID'] );
					$post_permalink = get_permalink( $report['report_object_ID'] );
					restore_current_blog();

					unset( $author_user_login );
					$author_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $post_details->post_author . "'");

					unset( $reasons );
					$query = "SELECT report_reason, report_note FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'post' AND report_status = 'new' AND report_blog_ID = '" . $report['report_blog_ID'] . "' AND report_object_ID = '" . $report['report_object_ID'] . "' ORDER BY report_stamp DESC";
					$reasons = $wpdb->get_results( $query, ARRAY_A );
					echo "<td valign='top'>" . stripslashes( $post_details->post_content ) . "</td>";
					echo "<td valign='top'>";
					echo "<strong>" . __('Beitrag-Titel', 'moderation') . "</strong>:<br />";
					echo "<a href='" . $post_permalink . "' rel='permalink' class='edit'>" . stripslashes( $post_details->post_title ) . "</a>";
					echo "<br /><br />";
					echo "<strong>" . __('Beitrag-Autor', 'moderation') . "</strong>:<br />";
					echo $author_user_login . " (<a href='admin.php?page=moderation-post-archive&object_type=post&uid=" . $post_details->post_author . "' rel='permalink' class='edit'>" . __('Archiv', 'moderation') . "</a>)";
					echo "<br /><br />";
					echo "<strong>" . __('Blog', 'moderation') . "</strong>:<br />";
					echo "<a href='" . $blog_details->siteurl . "' rel='permalink' class='edit'>" . $blog_details->blogname . "</a> (<a href='admin.php?page=moderation-post-archive&object_type=post&bid=" . $report['report_blog_ID'] . "' rel='permalink' class='edit'>" . __('Archiv', 'moderation') . "</a>)";
					echo "<br /><br />";
					echo "<strong>" . __('Meldung Datum/TZeit', 'moderation') . "</strong>:<br />";
					echo date_i18n( $date_format . ' ' . $time_format, $report['report_stamp'] );
					echo "<br /><br />";
					echo "<strong>" . __('Begründung', 'moderation') . "</strong>:<br />";
					foreach ( $reasons as $reason ) {
						echo $reason['report_reason'];
						if ( !empty( $reason['report_note'] ) ) {
							echo " - " . $reason['report_note'];
						}
						echo "<br />";
					}
					echo "<br />";
					echo "<strong>" . __('Aktion', 'moderation') . "</strong>:<br />";
					echo "<select name='reports[" . $report['report_ID'] . "-" . $report['report_blog_ID'] . "-" . $report['report_object_ID'] . "]'>";
							echo "<option value='reject_report'>" . __('Meldung ablehnen', 'moderation') . "</option>";
							echo "<option value='remove_post'>" . __('Beitrag entfernen', 'moderation') . "</option>";
					echo "</select>";
					echo "<br /><br />";
					echo "<strong>" . __('Moderations-Hinweis', 'moderation') . "</strong>:<br />";
					echo "<select name='remove_notes[" . $report['report_ID'] . "-" . $report['report_blog_ID'] . "-" . $report['report_object_ID'] . "]' style='width:200px;'>";
						$remove_notes = get_site_option('moderation_remove_notes', array('Warnung: AGB verletzt - Weitere Verstöße können dazu führen, dass Dein Konto entfernt wird','Warnung: AUP verletzt - Weitere Verstöße können dazu führen, dass Dein Konto entfernt wird'));
						foreach ( $remove_notes as $remove_note ) {
							echo "<option value='" . $remove_note . "'>" . $remove_note . "</option>";
						}
					echo "</select>";
					echo "</td>";
					$class = ('alternate' == $class) ? '' : 'alternate';
					//=========================================================//
					}
				//=========================================================//
				?>
				</tbody></table>
				<p class="submit">
				<input class="button button-primary" type="submit" name="Submit" value="<?php _e('Meldungen verarbeiten', 'moderation') ?>" />
				</p>
				</form>
				<?php
			} else {
				?>
	            <p><?php _e('Derzeit befinden sich keine Beiträge in der Moderationswarteschlange.', 'moderation') ?></p>
                <?php
			}
		break;
		//---------------------------------------------------//
		case "process_reports":
			$reports = $_POST['reports'];
			$remove_notes = $_POST['remove_notes'];
			if ( count( $reports ) > 0 ) {
				foreach ( $reports as $report_information => $action ) {
					unset( $report_ID );
					unset( $blog_ID );
					unset( $post_ID );
					unset( $remove_note );

					foreach ( $remove_notes as $remove_note_information => $note ) {
						if ( $remove_note_information = $report_information ) {
							$remove_note = $note;
						}
					}

					list($report_ID, $blog_ID, $post_ID) = explode("-", $report_information);
					if ( $action == 'reject_report' ) {
						$wpdb->query($wpdb->prepare( "UPDATE " . $wpdb->base_prefix . "moderation_reports SET report_status = 'rejected' WHERE report_object_type = 'post' AND report_status = 'new' AND report_blog_ID = %d AND report_object_ID = %d", $blog_ID, $post_ID));
					}
					if ( $action == 'remove_post' ) {
						switch_to_blog( $blog_ID );
						$post_details = get_post( $post_ID );
						if ( $moderation_save_to_archive == 'removed' ) {
							moderation_post_archive_insert($post_ID);
						}
						$wpdb->query($wpdb->prepare( "DELETE FROM " . $wpdb->posts . " WHERE ID = %d", $post_ID ));
						restore_current_blog();

						$wpdb->query($wpdb->prepare( "UPDATE " . $wpdb->base_prefix . "moderation_reports SET report_status = 'removed' WHERE report_object_type = 'post' AND report_status = 'new' AND report_blog_ID = %d AND report_object_ID = %d", $blog_ID, $post_ID ));
						$wpdb->query($wpdb->prepare("INSERT IGNORE INTO " . $wpdb->base_prefix . "moderation_warnings
						(warning_user_ID, warning_note)
						VALUES
						('" . $post_details->post_author . "', %s)", $remove_note));
					}
				}
			}
		echo "
		<SCRIPT LANGUAGE='JavaScript'>
		window.location='admin.php?page=moderation-posts&updated=true&updatedmsg=" . urlencode(__('Meldungen verarbeitet.', 'moderation')) . "';
		</script>
		";
		break;
		//---------------------------------------------------//
		case "process":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function moderation_blogs() {
	global $wpdb, $wp_roles, $current_user, $user_id, $current_site;

	if ( !is_moderator() ) {
		die();
	}

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'moderation') ?></p></div><?php
	}
	echo '<div class="wrap">';
	if (!isset($_GET[ 'action' ])) {
		$_GET[ 'action' ] = '';
	}
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
            <h2><?php _e('Blogs', 'moderation') ?></h2>
			<?php
			if( isset( $_GET[ 'start' ] ) == false ) {
				$start = 0;
			} else {
				$start = intval( $_GET[ 'start' ] );
			}
			if( isset( $_GET[ 'num' ] ) == false ) {
				$num = 30;
			} else {
				$num = intval( $_GET[ 'num' ] );
			}

			$query = "SELECT * FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'blog' AND report_status = 'new' GROUP BY report_blog_ID, report_object_ID ORDER BY report_stamp DESC";
			$query .= " LIMIT " . intval( $start ) . ", " . intval( $num );
			$reports = $wpdb->get_results( $query, ARRAY_A );
			if( count( $reports ) < $num ) {
				$next = false;
			} else {
				$next = true;
			}
			if ( count( $reports ) > 0 ) {
				$report_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'blog' AND report_status = 'new' GROUP BY GROUP BY report_blog_id, report_object_type, report_object_id ORDER BY report_stamp DESC");
				if ($report_count > 30){
					?>
                    <br />
                    <table><td>
					<fieldset>
					<?php

					//$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];

					if( $start == 0 ) {
						echo __('Vorherige Seite', 'moderation');
					} elseif( $start <= 30 ) {
						echo '<a href="admin.php?page=moderation-blogs&start=0&' . $order_sort . ' " style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
					} else {
						echo '<a href="admin.php?page=moderation-blogs&start=' . ( $start - $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
					}
					if ( $next ) {
						echo '&nbsp;||&nbsp;<a href="admin.php?page=moderation-blogss&start=' . ( $start + $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Nächste Seite', 'moderation') . '</a>';
					} else {
						echo '&nbsp;||&nbsp;' . __('Nächste Seite', 'moderation');
					}
					?>
					</fieldset>
					</td></table>
					<?php
				}
				echo "<form name='process_reports' method='POST' action='admin.php?page=moderation-blogs&action=process_reports' >";
				echo "
				<br />
				<table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
				<thead><tr>
				<th scope='col'>" . __('Blog', 'moderation') . "</th>
				<th scope='col'>" . __('Meldung Datum/Zeit', 'moderation') . "</th>
				<th scope='col'>" . __('Begründung', 'moderation') . "</th>
				<th scope='col'>" . __('Aktion', 'moderation') . "</th>
				</tr></thead>
				<tbody id='the-list'>
				";
				//=========================================================//
					$class = (isset($class) && 'alternate' == $class) ? '' : 'alternate';
					$date_format = get_option('date_format');
					$time_format = get_option('time_format');
					foreach ($reports as $report){
					//=========================================================//
					echo "<tr class='" . $class . "'>";

					unset( $blog_details );
					$blog_details = get_blog_details( $report['report_blog_ID'] );


					unset( $reasons );
					$query = $wpdb->prepare("SELECT report_reason, report_note FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'blog' AND report_status = 'new' AND report_blog_ID = %s AND report_object_ID = %s ORDER BY report_stamp DESC", $report['report_blog_ID'], $report['report_object_ID']);
					$reasons = $wpdb->get_results( $query, ARRAY_A );

					echo "<td valign='top'><a href='" . $blog_details->siteurl . "' rel='permalink' class='edit'>" . stripslashes($blog_details->blogname) . "</a> (" . $blog_details->siteurl . ")</td>";
					echo "<td valign='top'>" . date_i18n( $date_format . ' ' . $time_format, $report['report_stamp'] ) . "</td>";
					echo "<td valign='top'>";
					foreach ( $reasons as $reason ) {
						echo stripslashes($reason['report_reason']);
						if ( !empty( $reason['report_note'] ) ) {
							echo " - " . stripslashes($reason['report_note']);
						}
						echo "<br />";
					}
					echo "</td>";
					echo "<td valign='top'>";
					echo "<select name='reports[" . $report['report_ID'] . "-" . $report['report_blog_ID'] . "]'>";
							echo "<option value='reject_report'>" . __('Meldung ablehnen', 'moderation') . "</option>";
							echo "<option value='suspend_blog'>" . __('Blog suspendieren', 'moderation') . "</option>";
					echo "</select>";
					echo "</td>";
					echo "</tr>";
					$class = ('alternate' == $class) ? '' : 'alternate';
					//=========================================================//
					}
				//=========================================================//
				?>
				</tbody></table>
				<p class="submit">
				<input class="button button-primary" type="submit" name="Submit" value="<?php _e('Meldungen verarbeiten', 'moderation') ?>" />
				</p>
				</form>
				<?php
			} else {
				?>
	            <p><?php _e('Derzeit befinden sich keine Blogs in der Moderationswarteschlange.', 'moderation') ?></p>
                <?php
			}
		break;
		//---------------------------------------------------//
		case "process_reports":
			$reports = $_POST['reports'];
			if ( count( $reports ) > 0 ) {
				foreach ( $reports as $report_information => $action ) {
					unset( $report_ID );
					unset( $blog_ID );

					list($report_ID, $blog_ID) = explode("-", $report_information);
					if ( $action == 'reject_report' ) {
						$wpdb->query($wpdb->prepare( "UPDATE " . $wpdb->base_prefix . "moderation_reports SET report_status = 'rejected' WHERE report_object_type = 'blog' AND report_status = 'new' AND report_blog_ID = %d AND report_object_ID = %d", $blog_ID, $blog_ID ));
					}
					if ( $action == 'suspend_blog' ) {
						$wpdb->query($wpdb->prepare( "UPDATE " . $wpdb->base_prefix . "moderation_reports SET report_status = 'suspended' WHERE report_object_type = 'blog' AND report_status = 'new' AND report_blog_ID = %d AND report_object_ID = %d", $blog_ID, $blog_ID ));

						update_archived( $blog_ID, '1' );
					}
				}
			}
		echo "
		<SCRIPT LANGUAGE='JavaScript'>
		window.location='admin.php?page=moderation-blogs&updated=true&updatedmsg=" . urlencode(__('Meldungen verarbeitet.', 'moderation')) . "';
		</script>
		";
		break;
		//---------------------------------------------------//
		case "process":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function moderation_comments() {
	global $wpdb, $wp_roles, $current_user, $user_id, $current_site, $moderation_save_to_archive;

	if ( !is_moderator() ) {
		die();
	}

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'moderation') ?></p></div><?php
	}
	echo '<div class="wrap">';
	if (!isset($_GET[ 'action' ])) {
		$_GET[ 'action' ] = '';
	}
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
            <h2><?php _e('Kommentare', 'moderation') ?></h2>
			<?php
			if( isset( $_GET[ 'start' ] ) == false ) {
				$start = 0;
			} else {
				$start = intval( $_GET[ 'start' ] );
			}
			if( isset( $_GET[ 'num' ] ) == false ) {
				$num = 30;
			} else {
				$num = intval( $_GET[ 'num' ] );
			}

			$query = "SELECT * FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'comment' AND report_status = 'new' GROUP BY report_blog_ID, report_object_ID ORDER BY report_stamp DESC";
			$query .= " LIMIT " . intval( $start ) . ", " . intval( $num );
			$reports = $wpdb->get_results( $query, ARRAY_A );
			if( count( $reports ) < $num ) {
				$next = false;
			} else {
				$next = true;
			}
			if ( count( $reports ) > 0 ) {
				$report_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'comment' AND report_status = 'new' GROUP BY report_blog_id, report_object_type, report_object_id ORDER BY report_stamp DESC");
				if ($report_count > 30){
					?>
                    <br />
                    <table><td>
					<fieldset>
					<?php

					//$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];

					if( $start == 0 ) {
						echo __('Vorherige Seite', 'moderation');
					} elseif( $start <= 30 ) {
						echo '<a href="admin.php?page=moderation-comments&start=0&' . $order_sort . ' " style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
					} else {
						echo '<a href="admin.php?page=moderation-comments&start=' . ( $start - $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
					}
					if ( $next ) {
						echo '&nbsp;||&nbsp;<a href="admin.php?page=moderation-comments&start=' . ( $start + $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Nächste Seite', 'moderation') . '</a>';
					} else {
						echo '&nbsp;||&nbsp;' . __('Nächste Seite', 'moderation');
					}
					?>
					</fieldset>
					</td></table>
					<?php
				}
				echo "<form name='process_reports' method='POST' action='admin.php?page=moderation-comments&action=process_reports' >";
				echo "
				<br />
				<table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
				<thead><tr>
				<th scope='col'>" . __('Kommentar', 'moderation') . "</th>
				<th width='25%' scope='col'>" . __('Information', 'moderation') . "</th>
				</tr></thead>
				<tbody id='the-list'>
				";
				//=========================================================//
					$class = (isset($class) && 'alternate' == $class) ? '' : 'alternate';
					$date_format = get_option('date_format');
					$time_format = get_option('time_format');
					foreach ($reports as $report){
					//=========================================================//
					echo "<tr class='" . $class . "'>";

					unset( $blog_details );
					$blog_details = get_blog_details( $report['report_blog_ID'] );

					unset( $comment_details );
					unset( $post_permalink );
					switch_to_blog( $report['report_blog_ID'] );
					$comment_details = get_comment( $report['report_object_ID'] );
					restore_current_blog();

					unset( $author_user_login );
					if ( $comment_details->user_id != '0' ) {
						$author_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $comment_details->user_id . "'");
					}

					unset( $author_email );
					$author_email = $comment_details->comment_author_email;

					if ( !empty( $author_user_login ) ) {
						$author = $author_user_login . " (" . $author_email . ")";
					} else {
						$author = $author_email;
					}

					unset( $reasons );
					$query = $wpdb->prepare("SELECT report_reason, report_note FROM " . $wpdb->base_prefix . "moderation_reports WHERE report_object_type = 'comment' AND report_status = 'new' AND report_blog_ID = %d AND report_object_ID = %d ORDER BY report_stamp DESC", $report['report_blog_ID'], $report['report_object_ID']);
					$reasons = $wpdb->get_results( $query, ARRAY_A );
					echo "<td valign='top'>" . stripslashes( $comment_details->comment_content ) . "</td>";
					echo "<td valign='top'>";
					echo "<strong>" . __('Kommentar Autor', 'moderation') . "</strong>:<br />";
					echo $author . " (<a href='admin.php?page=moderation-comment-archive&email=" . $author_email . "' rel='permalink' class='edit'>" . __('Archiv', 'moderation') . "</a>)";
					echo "<br /><br />";
					echo "<strong>" . __('Blog', 'moderation') . "</strong>:<br />";
					echo "<a href='" . $blog_details->siteurl . "' rel='permalink' class='edit'>" . $blog_details->blogname . "</a> (<a href='admin.php?page=moderation-comment-archive&object_type=post&bid=" . $report['report_blog_ID'] . "' rel='permalink' class='edit'>" . __('Archiv', 'moderation') . "</a>)";
					echo "<br /><br />";
					echo "<strong>" . __('Meldung Datum/Zeit', 'moderation') . "</strong>:<br />";
					echo date_i18n( $date_format . ' ' . $time_format, $report['report_stamp'] );
					echo "<br /><br />";
					echo "<strong>" . __('Begründung', 'moderation') . "</strong>:<br />";
					foreach ( $reasons as $reason ) {
						echo $reason['report_reason'];
						if ( !empty( $reason['report_note'] ) ) {
							echo " - " . $reason['report_note'];
						}
						echo "<br />";
					}
					echo "<br />";
					echo "<strong>" . __('Aktion', 'moderation') . "</strong>:<br />";
					echo "<select name='reports[" . $report['report_ID'] . "-" . $report['report_blog_ID'] . "-" . $report['report_object_ID'] . "]'>";
							echo "<option value='reject_report'>" . __('Meldung ablehnen', 'moderation') . "</option>";
							echo "<option value='remove_comment'>" . __('Kommentar entfernen', 'moderation') . "</option>";
					echo "</select>";
					echo "<br /><br />";
					echo "<strong>" . __('Moderations-Hinweis', 'moderation') . "</strong>:<br />";
					echo "<select name='remove_notes[" . $report['report_ID'] . "-" . $report['report_blog_ID'] . "-" . $report['report_object_ID'] . "]' style='width:200px;'>";
						$remove_notes = get_site_option('moderation_remove_notes', array('Warnung: AGB verletzt - Weitere Verstöße können dazu führen, dass Dein Konto entfernt wird','Warnung: AUP verletzt - Weitere Verstöße können dazu führen, dass Dein Konto entfernt wird'));
						foreach ( $remove_notes as $remove_note ) {
							echo "<option value='" . $remove_note . "'>" . $remove_note . "</option>";
						}
					echo "</select>";
					echo "</td>";
					$class = ('alternate' == $class) ? '' : 'alternate';
					//=========================================================//
					}
				//=========================================================//
				?>
				</tbody></table>
				<p class="submit">
				<input class="button button-primary" type="submit" name="Submit" value="<?php _e('Meldungen verarbeitet', 'moderation') ?>" />
				</p>
				</form>
				<?php
			} else {
				?>
	            <p><?php _e('Derzeit befinden sich keine Kommentare in der Moderationswarteschlange.', 'moderation') ?></p>
                <?php
			}
		break;
		//---------------------------------------------------//
		case "process_reports":
			$reports = $_POST['reports'];
			$remove_notes = $_POST['remove_notes'];
			if ( count( $reports ) > 0 ) {
				foreach ( $reports as $report_information => $action ) {
					unset( $report_ID );
					unset( $blog_ID );
					unset( $comment_ID );
					unset( $remove_note );
					unset( $blog_admin_admin_email );
					unset( $blog_admin_user_ID );
					unset( $warning_user_ID );

					foreach ( $remove_notes as $remove_note_information => $note ) {
						if ( $remove_note_information = $report_information ) {
							$remove_note = $note;
						}
					}

					list($report_ID, $blog_ID, $comment_ID) = explode("-", $report_information);
					if ( $action == 'reject_report' ) {
						$wpdb->query($wpdb->prepare( "UPDATE " . $wpdb->base_prefix . "moderation_reports SET report_status = 'rejected' WHERE report_object_type = 'comment' AND report_status = 'new' AND report_blog_ID = %d AND report_object_ID = %d", $blog_ID, $comment_ID ));
					}
					if ( $action == 'remove_comment' ) {
						switch_to_blog( $blog_ID );
						$comment_details = get_comment( $comment_ID );
						if ( $moderation_save_to_archive == 'removed' ) {
							moderation_comment_archive_insert($comment_ID);
						}
						$wpdb->query( "DELETE FROM " . $wpdb->comments . " WHERE comment_ID = '" . $comment_ID . "'" );
						restore_current_blog();

						$wpdb->query($wpdb->prepare( "UPDATE " . $wpdb->base_prefix . "moderation_reports SET report_status = 'removed' WHERE report_object_type = 'comment' AND report_status = 'new' AND report_blog_ID = %d AND report_object_ID = %d", $blog_ID, $comment_ID ));
						if ( $comment_details->user_id != '0' ) {
							$warning_user_ID = $comment_details->user_id;
						} else {
							$blog_admin_admin_email = get_blog_option($blog_ID, 'admin_email');
							$blog_admin_user_ID = $wpdb->get_var("SELECT ID FROM " . $wpdb->users . " WHERE user_email = '" . $blog_admin_admin_email . "'");
							$warning_user_ID = $blog_admin_user_ID;
						}
						if ( !empty( $warning_user_ID ) ) {
							$wpdb->query($wpdb->prepare("INSERT IGNORE INTO " . $wpdb->base_prefix . "moderation_warnings
							(warning_user_ID, warning_note)
							VALUES
							(%d, %s)", $warning_user_ID, $remove_note ));
						}
					}
				}
			}
		echo "
		<SCRIPT LANGUAGE='JavaScript'>
		window.location='admin.php?page=moderation-comments&updated=true&updatedmsg=" . urlencode(__('Meldungen verarbeitet.', 'moderation')) . "';
		</script>
		";
		break;
		//---------------------------------------------------//
		case "process":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function moderation_post_archive() {
	global $wpdb, $wp_roles, $current_user, $user_id, $current_site;

	if ( !is_moderator() ) {
		die('');
	}

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'moderation') ?></p></div><?php
	}
	echo '<div class="wrap">';
	if (!isset($_GET[ 'action' ])) {
		$_GET[ 'action' ] = '';
	}
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			if (isset($_POST['pid'])) {
				$pid = $_POST['pid'];
			}
			if ( empty( $pid ) && isset($_GET['pid']) ) {
				$pid = $_GET['pid'];
			}
			if (isset($_POST['bid'])) {
				$bid = $_POST['bid'];
			}
			if ( empty( $bid ) && isset($_GET['bid']) ) {
				$bid = $_GET['bid'];
			}
			if (isset($_POST['blog_name'])) {
				$blog_name = $_POST['blog_name'];
			}
			if ( empty( $blog_name ) && isset($_GET['blog_name']) ) {
				$blog_name = $_GET['blog_name'];
			}
			if (isset($_POST['uid'])) {
				$uid = $_POST['uid'];
			}
			if ( empty( $uid ) && isset($_GET['uid']) ) {
				$uid = $_GET['uid'];
			}
			if (isset($_POST['user_login'])) {
				$user_login = $_POST['user_login'];
			}
			if ( empty( $user_login ) && isset($_GET['user_login']) ) {
				$user_login = $_GET['user_login'];
			}
			if (isset($_POST['user_email'])) {
				$user_email = $_POST['user_email'];
			}
			if ( empty( $user_email ) && isset($_GET['user_email']) ) {
				$user_email = $_GET['user_email'];
			}
			if (isset($_POST['object_type'])) {
				$post_type = $_POST['object_type'];
			}
			if ( empty( $post_type ) && isset($_GET['object_type']) ) {
				$post_type = $_GET['object_type'];
			}
			if ( !empty( $blog_name ) ) {
				if (VHOST == 'yes') {
					$bid = $wpdb->get_var($wpdb->prepare("SELECT blog_id FROM " . $wpdb->blogs . " WHERE domain = %s", $blog_name.".".$current_site->domains ));
				} else {
					$bid = $wpdb->get_var($wpdb->prepare("SELECT blog_id FROM " . $wpdb->blogs . " WHERE path = %s", $current_site->path.$blog_name . "/" ));
				}
			}
			if ( !empty( $user_login ) ) {
				$uid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM " . $wpdb->users . " WHERE user_login = %s", $user_login));
			}
			if ( !empty( $user_email ) ) {
				$uid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM " . $wpdb->users . " WHERE user_email = %s", $user_email));
			}


			?>
            <h2><?php _e('Beitragsarchiv', 'moderation') ?></h2>
            <?php
			if ( empty( $post_type ) ) {
			?>
            <form name="post_archive" method="POST" action="admin.php?page=moderation-post-archive">
                <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Beitrag-Typ', 'moderation') ?></th>
                <td><select name="object_type">
                    <option value="post"><?php _e('Beitrag', 'moderation'); ?></option>
                    <option value="page" ><?php _e('Seite', 'moderation'); ?></option>
                    <option value="revision" ><?php _e('Revision', 'moderation'); ?></option>
                    <option value="all" selected="selected" ><?php _e('Alle', 'moderation'); ?></option>
                </select>
                </td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Beitrag ID', 'moderation') ?></th>
                <td><input type="text" name="pid" id="pid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Blog ID', 'moderation') ?></th>
                <td><input type="text" name="bid" id="bid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Blogname', 'moderation') ?></th>
                <td><input type="text" name="blog_name" id="blog_name" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzername', 'moderation') ?></th>
                <td><input type="text" name="user_login" id="user_login" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzer ID', 'moderation') ?></th>
                <td><input type="text" name="uid" id="uid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzer Email', 'moderation') ?></th>
                <td><input type="text" name="user_email" id="user_email" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                </table>
            <p><?php _e('Beachte dass nur Beiträge verfügbar sind, die nach dem Hinzufügen des Moderations-Plugins veröffentlicht wurden.', 'moderation'); ?></p>
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Suche', 'moderation') ?>" />
            </p>
            </form>
			<?php
			}
			if ( !empty( $post_type ) ) {
                if( isset( $_GET[ 'start' ] ) == false ) {
                    $start = 0;
                } else {
                    $start = intval( $_GET[ 'start' ] );
                }
                if( isset( $_GET[ 'num' ] ) == false ) {
                    $num = 30;
                } else {
                    $num = intval( $_GET[ 'num' ] );
                }

                $count = 0;
				$where = '';
                if ( !empty( $uid ) || !empty( $bid ) || !empty( $pid ) || ( !empty( $post_type ) && $post_type != 'all' ) ) {
                    $where =  "WHERE ";
                }
                if ( $post_type != 'all' ) {
                    $where = $where . "post_type = '".$post_type."'";
                    $count = $count + 1;
                }
                if ( !empty( $uid ) ) {
                    if ( $count > 0 ) {
                    $where = $where . "AND ";
                    }
                    $where = $where . "post_author = '".$uid."'";
                    $count = $count + 1;
                }
                if ( !empty( $bid ) ) {
                    if ( $count > 0 ) {
                    $where = $where . "AND ";
                    }
                    $where = $where . $wpdb->prepare("blog_id = %d", $bid);
                    $count = $count + 1;
                }
                if ( !empty( $pid ) ) {
                    if ( $count > 0 ) {
                    $where = $where . "AND ";
                    }
                    $where = $where . $wpdb->prepare("post_id = %d", $pid);
                    $count = $count + 1;
                }

                $query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "post_archive " . $where . " ORDER BY post_stamp DESC LIMIT %d, %d", $start, $num );
                $posts = $wpdb->get_results( $query, ARRAY_A );
                if( count( $posts ) < $num ) {
                    $next = false;
                } else {
                    $next = true;
                }
                if ( count( $posts ) > 0 ) {
                    $post_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "post_archive " . $where);
                    if ($post_count > 30){
                        ?>
                        <br />
                        <table><td>
                        <fieldset>
                        <?php

                        //$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];

                        if( $start == 0 ) {
                            echo __('Vorherige Seite', 'moderation');
                        } elseif( $start <= 30 ) {
                            echo '<a href="admin.php?page=moderation-post-archive&object_type=' . $post_type . '&uid=' . $uid . '&bid=' . $bid . '&start=0&' . $order_sort . ' " style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
                        } else {
                            echo '<a href="admin.php?page=moderation-post-archive&object_type=' . $post_type . '&uid=' . $uid . '&bid=' . $bid . '&start=' . ( $start - $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
                        }
                        if ( $next ) {
                            echo '&nbsp;||&nbsp;<a href="admin.php?page=moderation-post-archive&object_type=' . $post_type . '&uid=' . $uid . '&bid=' . $bid . '&start=' . ( $start + $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Nächste Seite', 'moderation') . '</a>';
                        } else {
                            echo '&nbsp;||&nbsp;' . __('Nächste Seite', 'moderation');
                        }
                        ?>
                        </fieldset>
                        </td></table>
                        <?php
                    }
                    echo "
                    <br />
                    <table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
                    <thead><tr>
                    <th scope='col'>" . __('Blog', 'moderation') . "</th>
                    <th scope='col'>" . __('Autor', 'moderation') . "</th>
                    <th scope='col'>" . __('Titel', 'moderation') . "</th>
                    <th scope='col'>" . __('Datum/Zeit', 'moderation') . "</th>
                    <th scope='col'>" . __('Typ', 'moderation') . "</th>
                    <th scope='col'>" . __('Aktionen', 'moderation') . "</th>
                    </tr></thead>
                    <tbody id='the-list'>
                    ";
                    //=========================================================//
                        $class = (isset($class) && 'alternate' == $class) ? '' : 'alternate';
                        $date_format = get_option('date_format');
                        $time_format = get_option('time_format');
                        foreach ($posts as $post){
                        //=========================================================//
                        echo "<tr class='" . $class . "'>";

                        unset( $blog_details );
                        $blog_details = get_blog_details( $post['blog_id'] );

                        unset( $author_user_login );
                        $author_user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->users . " WHERE ID = %d", $post['post_author']));

						if (!isset($_GET['start'])) {
							$_GET['start'] = '';
						}
						if (!isset($_GET['num'])) {
							$_GET['num'] = '';
						}
						if (!isset($post_type)) {
							$post_type = '';
						}
						if (!isset($bid)) {
							$bid = '';
						}
						if (!isset($uid)) {
							$uid = '';
						}
						if (!isset($pid)) {
							$pid = '';
						}
                        echo "<td valign='top'><a href='" . $blog_details->siteurl . "' rel='permalink' class='edit'>" . $blog_details->blogname . "</a> (<a href='admin.php?page=moderation-post-archive&object_type=" . $post_type . "&bid=" . $post['blog_id'] . "' rel='permalink' class='edit'>" . __('Archiv', 'moderation') . "</a>)</td>";
                        echo "<td valign='top'>" . $author_user_login . " (<a href='admin.php?page=moderation-post-archive&object_type=" . $post_type . "&uid=" . $post['post_author'] . "' rel='permalink' class='edit'>" . __('Archiv', 'moderation') . "</a>)</td>";
                        echo "<td valign='top'>" . stripslashes( $post['post_title'] ) . "</td>";
                        echo "<td valign='top'>" . date_i18n( $date_format . ' ' . $time_format, $post['post_stamp'] ) . "</td>";
                        echo "<td valign='top'>" . ucfirst( $post['object_type'] ) . "</td>";
                        echo "<td valign='top'><a href='admin.php?page=moderation-post-archive&action=view&post_archive_id=" . $post['post_archive_id'] . "&start=" . $_GET['start'] . "&num=" . $_GET['num'] . "&object_type=" . $post_type . "&bid=" . $bid . "&uid=" . $uid . "&pid=" . $pid . "' rel='permalink' class='edit'>" . __('Ansehen', 'moderation') . "</a></td>";

                        echo "</tr>";
                        $class = ('alternate' == $class) ? '' : 'alternate';
                        //=========================================================//
                        }
                    //=========================================================//
                    ?>
                    </tbody></table>
                    <?php
                } else {
                    ?>
                    <p><?php _e('Keine Beiträge gefunden.', 'moderation') ?></p>
                    <?php
                }
			}
		break;
		//---------------------------------------------------//
		case "view":
			$post_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "post_archive WHERE post_archive_id = %d", $_GET['post_archive_id']));
			$blog_details = get_blog_details( $post_details->blog_id );
			$author_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $post_details->post_author . "'");
			?>
        	<h2><?php echo stripslashes($post_details->post_title); ?></h2>
            <ul>
            	<li><strong><?php _e('Blog', 'moderation'); ?>: </strong><a href="<?php echo $blog_details->siteurl; ?>" style="text-decoration:none;"><?php echo $blog_details->blogname; ?></a> (<a href="admin.php?page=moderation-post-archive&object_type=<?php echo $_GET['object_type']; ?>&bid=<?php echo $post_details->blog_id; ?>" style="text-decoration:none;"><?php _e('Archiv', 'moderation'); ?></a>)</li>
            	<li><strong><?php _e('Autor', 'moderation'); ?>: </strong><?php echo $author_user_login; ?>  (<a href="admin.php?page=moderation-post-archive&object_type=<?php echo $_GET['object_type']; ?>&uid=<?php echo $post_details->post_author; ?>" style="text-decoration:none;"><?php _e('Archiv', 'moderation'); ?></a>)</li>
            	<li><strong><?php _e('Datum/Zeit', 'moderation'); ?>: </strong><?php echo date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $post_details->post_stamp ); ?></li>
            </ul>
        	<p><?php echo stripslashes($post_details->post_content); ?></p>
            <?php
			if ( !empty($_GET['start']) || !empty($_GET['num']) ) {
				?>
				<form name="post_archive" method="POST" action="admin.php?page=moderation-post-archive&start=<?php echo $_GET['start']; ?>&num=<?php echo $_GET['num']; ?>&object_type=<?php echo $_GET['object_type']; ?>&bid=<?php echo $_GET['bid']; ?>&uid=<?php echo $_GET['uid']; ?>&pid=<?php echo $_GET['pid']; ?>">
                <?php
			} else {
				?>
            	<form name="post_archive" method="POST" action="admin.php?page=moderation-post-archive&object_type=<?php echo $_GET['object_type']; ?>&bid=<?php echo $_GET['bid']; ?>&uid=<?php echo $_GET['uid']; ?>&pid=<?php echo $_GET['pid']; ?>">
                <?php
			}
			?>
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Zurück', 'moderation') ?>" />
            </p>
            </form>
    	    <?php
		break;
		//---------------------------------------------------//
		case "process":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function moderation_comment_archive() {
	global $wpdb, $wp_roles, $current_user, $user_id, $current_site;

	if ( !is_moderator() ) {
		die();
	}

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'moderation') ?></p></div><?php
	}
	echo '<div class="wrap">';
	if (!isset($_GET[ 'action' ])) {
		$_GET[ 'action' ] = '';
	}
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			if (isset($_POST['cid'])) {
				$cid = $_POST['cid'];
			}
			if ( empty( $cid ) && isset($_GET['cid']) ) {
				$cid = $_GET['cid'];
			}
			if (isset($_POST['bid'])) {
				$bid = $_POST['bid'];
			}
			if ( empty( $bid ) && isset($_GET['bid']) ) {
				$bid = $_GET['bid'];
			}
			if (isset($_POST['blog_name'])) {
				$blog_name = $_POST['blog_name'];
			}
			if ( empty( $blog_name ) && isset($_GET['blog_name']) ) {
				$blog_name = $_GET['blog_name'];
			}
			if (isset($_POST['uid'])) {
				$uid = $_POST['uid'];
			}
			if ( empty( $uid ) && isset($_GET['uid']) ) {
				$uid = $_GET['uid'];
			}
			if (isset($_POST['ip'])) {
				$ip = $_POST['ip'];
			}
			if ( empty( $ip ) && isset($_GET['ip']) ) {
				$ip = $_GET['ip'];
			}
			if (isset($_POST['user_login'])) {
				$user_login = $_POST['user_login'];
			}
			if ( empty( $user_login ) && isset($_GET['user_login']) ) {
				$user_login = $_GET['user_login'];
			}
			if (isset($_POST['email'])) {
				$email = $_POST['email'];
			}
			if ( empty( $email ) && isset($_GET['email']) ) {
				$email = $_GET['email'];
			}
			if ( !empty( $blog_name ) ) {
				if (VHOST == 'yes') {
					$bid = $wpdb->get_var($wpdb->prepare("SELECT blog_id FROM " . $wpdb->blogs . " WHERE domain = %s", $blog_name . "." . $current_site->domains));
				} else {
					$bid = $wpdb->get_var($wpdb->prepare("SELECT blog_id FROM " . $wpdb->blogs . " WHERE path = %s", $current_site->path . $blog_name . "/"));
				}
			}
			if ( !empty( $user_login ) ) {
				$uid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM " . $wpdb->users . " WHERE user_login = %s", $user_login));
			}

			?>
            <h2><?php _e('Kommentararchiv', 'moderation') ?></h2>
            <?php
			if ( empty( $cid ) && empty( $bid ) && empty( $email ) && empty( $ip ) && !isset( $_POST['search'] ) ) {
			?>
            <form name="post_archive" method="POST" action="admin.php?page=moderation-comment-archive">
            	<input type="hidden" name="search" value="search" />
                <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Kommentar ID', 'moderation') ?></th>
                <td><input type="text" name="cid" id="cid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Blog ID', 'moderation') ?></th>
                <td><input type="text" name="bid" id="bid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Blogname', 'moderation') ?></th>
                <td><input type="text" name="blog_name" id="blog_name" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzername', 'moderation') ?></th>
                <td><input type="text" name="user_login" id="user_login" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Benutzer ID', 'moderation') ?></th>
                <td><input type="text" name="uid" id="uid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Email', 'moderation') ?></th>
                <td><input type="text" name="user_email" id="user_email" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('IP', 'moderation') ?></th>
                <td><input type="text" name="ip" id="ip" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                </table>
            <p><?php _e('Beachte dass nur Kommentare verfügbar sind, die nach dem Hinzufügen des Moderations-Plugins veröffentlicht wurden.', 'moderation'); ?></p>
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Suche', 'moderation') ?>" />
            </p>
            </form>
			<?php
			}
			if ( !empty( $cid ) || !empty( $bid ) || !empty( $email ) || !empty( $ip ) || isset( $_POST['search'] ) ) {
                if( isset( $_GET[ 'start' ] ) == false ) {
                    $start = 0;
                } else {
                    $start = intval( $_GET[ 'start' ] );
                }
                if( isset( $_GET[ 'num' ] ) == false ) {
                    $num = 30;
                } else {
                    $num = intval( $_GET[ 'num' ] );
                }

                $count = 0;
				$where = '';
                if ( !empty( $uid ) || !empty( $bid ) || !empty( $cid ) || !empty( $ip ) || !empty( $email ) ) {
                    $where =  "WHERE ";
                }
                if ( !empty( $uid ) ) {
                    if ( $count > 0 ) {
                    $where = $where . "AND ";
                    }
                    $where = $where . $wpdb->prepare("comment_author_user_id = %d", $uid);
                    $count = $count + 1;
                }
                if ( !empty( $bid ) ) {
                    if ( $count > 0 ) {
                    $where = $where . "AND ";
                    }
                    $where = $where . $wpdb->prepare("blog_id = %d", $bid);
                    $count = $count + 1;
                }
                if ( !empty( $cid ) ) {
                    if ( $count > 0 ) {
                    $where = $where . "AND ";
                    }
                    $where = $where . $wpdb->prepare("comment_id = %d", $cid);
                    $count = $count + 1;
                }
                if ( !empty( $email ) ) {
                    if ( $count > 0 ) {
                    $where = $where . "AND ";
                    }
                    $where = $where . $wpdb->prepare("comment_author_email = %s", $email);
                    $count = $count + 1;
                }
                if ( !empty( $ip ) ) {
                    if ( $count > 0 ) {
                    $where = $where . "AND ";
                    }
                    $where = $where . $wpdb->prepare("comment_author_ip = %s", $ip);
                    $count = $count + 1;
                }

                $query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "comment_archive " . $where . " ORDER BY comment_stamp DESC LIMIT %d, %d", $start, $num);
                $comments = $wpdb->get_results( $query, ARRAY_A );
                if( count( $comments ) < $num ) {
                    $next = false;
                } else {
                    $next = true;
                }
                if ( count( $comments ) > 0 ) {
                    $comment_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "comment_archive " . $where);
                    if ($comment_count > 30){
                        ?>
                        <br />
                        <table><td>
                        <fieldset>
                        <?php

                        //$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];

                        if( $start == 0 ) {
                            echo __('Vorherige Seite', 'moderation');
                        } elseif( $start <= 30 ) {
                            echo '<a href="admin.php?page=moderation-comment-archive&uid=' . $uid . '&bid=' . $bid . '&ip=' . $ip . '&email=' . $email . '&start=0&' . $order_sort . ' " style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
                        } else {
                            echo '<a href="admin.php?page=moderation-comment-archive&uid=' . $uid . '&bid=' . $bid . '&ip=' . $ip . '&email=' . $email . '&start=' . ( $start - $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
                        }
                        if ( $next ) {
                            echo '&nbsp;||&nbsp;<a href="admin.php?page=moderation-comment-archive&uid=' . $uid . '&bid=' . $bid . '&ip=' . $ip . '&email=' . $email . '&start=' . ( $start + $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Nächste Seite', 'moderation') . '</a>';
                        } else {
                            echo '&nbsp;||&nbsp;' . __('Nächste Seite', 'moderation');
                        }
                        ?>
                        </fieldset>
                        </td></table>
                        <?php
                    }
                    echo "
                    <br />
                    <table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
                    <thead><tr>
                    <th scope='col'>" . __('Blog', 'moderation') . "</th>
                    <th scope='col'>" . __('Autor', 'moderation') . "</th>
                    <th scope='col'>" . __('Datum/Zeit', 'moderation') . "</th>
                    <th scope='col'>" . __('Aktionen', 'moderation') . "</th>
                    </tr></thead>
                    <tbody id='the-list'>
                    ";
                    //=========================================================//
                        $class = (isset($class) && 'alternate' == $class) ? '' : 'alternate';
                        $date_format = get_option('date_format');
                        $time_format = get_option('time_format');
                        foreach ($comments as $comment){
                        //=========================================================//
                        echo "<tr class='" . $class . "'>";

                        unset( $blog_details );
                        $blog_details = get_blog_details( $comment['blog_id'] );

						unset( $author_user_login );
						if ( $comment['comment_author_user_id'] != '0' ) {
							$author_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $comment['comment_author_user_id'] . "'");
						}

						unset( $author_email );
						$author_email = $comment['comment_author_email'];

						if ( !empty( $author_user_login ) ) {
							$author = $author_user_login . " (" . $author_email . ")";
						} else {
							$author = $author_email;
						}

						if (!isset($_GET['start'])) {
							$_GET['start'] = '';
						}
						if (!isset($_GET['num'])) {
							$_GET['num'] = '';
						}
						if (!isset($email)) {
							$email = '';
						}
						if (!isset($post_type)) {
							$post_type = '';
						}
						if (!isset($bid)) {
							$bid = '';
						}
						if (!isset($uid)) {
							$uid = '';
						}
						if (!isset($cid)) {
							$cid = '';
						}
						if (!isset($ip)) {
							$ip = '';
						}
                        echo "<td valign='top'><a href='" . $blog_details->siteurl . "' rel='permalink' class='edit'>" . $blog_details->blogname . "</a> (<a href='admin.php?page=moderation-comment-archive&bid=" . $comment['blog_id'] . "' rel='permalink' class='edit'>" . __('Archiv', 'moderation') . "</a>)</td>";
                        echo "<td valign='top'>" . $author . " (<a href='admin.php?page=moderation-comment-archive&email=" . $author_email . "' rel='permalink' class='edit'>" . __('Archiv', 'moderation') . "</a>)</td>";
                        echo "<td valign='top'>" . date_i18n( $date_format . ' ' . $time_format, $comment['comment_stamp'] ) . "</td>";
                        echo "<td valign='top'><a href='admin.php?page=moderation-comment-archive&action=view&comment_archive_id=" . $comment['comment_archive_id'] . "&start=" . $_GET['start'] . "&num=" . $_GET['num'] . "&bid=" . $bid . "&uid=" . $uid . "&ip=" . $ip . "&email=" . $email . "&cid=" . $cid . "' rel='permalink' class='edit'>" . __('Ansehen', 'moderation') . "</a></td>";

                        echo "</tr>";
                        $class = ('alternate' == $class) ? '' : 'alternate';
                        //=========================================================//
                        }
                    //=========================================================//
                    ?>
                    </tbody></table>
                    <?php
                } else {
                    ?>
                    <p><?php _e('Keine Kommentare gefunden.', 'moderation') ?></p>
                    <?php
                }
			}
		break;
		//---------------------------------------------------//
		case "view":
			$comment_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "comment_archive WHERE comment_archive_id = %d", $_GET['comment_archive_id']));
			$blog_details = get_blog_details( $comment_details->blog_id );
			if ( $comment_details->comment_author_user_id != '0' ) {
				$author_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $comment_details->comment_author_user_id . "'");
			}

			$author_email = $comment_details->comment_author_email;

			if ( !empty( $author_user_login ) ) {
				$author = $author_user_login . " (" . $author_email . ")";
			} else {
				$author = $author_email;
			}
			?>
        	<h2><?php __('Kommentar anzeigen', 'moderation'); ?></h2>
            <ul>
            	<li><strong><?php _e('Blog', 'moderation'); ?>: </strong><a href="<?php echo $blog_details->siteurl; ?>" style="text-decoration:none;"><?php echo $blog_details->blogname; ?></a> (<a href="admin.php?page=moderation-comment-archive&bid=<?php echo $comment_details->blog_id; ?>" style="text-decoration:none;"><?php _e('Archiv', 'moderation'); ?></a>)</li>
            	<li><strong><?php _e('Autor', 'moderation'); ?>: </strong><?php echo $author; ?>  (<a href="admin.php?page=moderation-comment-archive&email=<?php echo $author_email; ?>" style="text-decoration:none;"><?php _e('Archiv', 'moderation'); ?></a>)</li>
            	<li><strong><?php _e('Datum/Zeit', 'moderation'); ?>: </strong><?php echo date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $comment_details->comment_stamp ); ?></li>
            </ul>
        	<p><?php echo stripslashes($comment_details->comment_content); ?></p>
            <?php
			if ( !empty($_GET['start']) || !empty($_GET['num']) ) {
				?>
				<form name="comment_archive" method="POST" action="admin.php?page=moderation-comment-archive&start=<?php echo $_GET['start']; ?>&num=<?php echo $_GET['num']; ?>&&bid=<?php echo $_GET['bid']; ?>&uid=<?php echo $_GET['uid']; ?>&ip=<?php echo $_GET['ip']; ?>&email=<?php echo $_GET['email']; ?>&cid=<?php echo $_GET['cid']; ?>">
                <?php
			} else {
				?>
            	<form name="comment_archive" method="POST" action="admin.php?page=moderation-comment-archive&bid=<?php echo $_GET['bid']; ?>&uid=<?php echo $_GET['uid']; ?>&ip=<?php echo $_GET['ip']; ?>&email=<?php echo $_GET['email']; ?>&cid=<?php echo $_GET['cid']; ?>">
                <?php
			}
			?>
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Zurück', 'moderation') ?>" />
            </p>
            </form>
    	    <?php
		break;
		//---------------------------------------------------//
		case "process":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function moderation_report_archive() {
	global $wpdb, $wp_roles, $current_user, $user_id, $current_site;

	if ( !is_moderator() ) {
		die();
	}

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'moderation') ?></p></div><?php
	}
	echo '<div class="wrap">';
	if (!isset($_GET[ 'action' ])) {
		$_GET[ 'action' ] = '';
	}
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			if (isset($_POST['report_type'])) {
				$report_type = $_POST['report_type'];
			}
			if ( empty( $report_type ) && isset($_GET['report_type']) ) {
				$report_type = $_GET['report_type'];
			}
			if (isset($_POST['bid'])) {
				$bid = $_POST['bid'];
			}
			if ( empty( $bid ) && isset($_GET['bid']) ) {
				$bid = $_GET['bid'];
			}
			if (isset($_POST['pid'])) {
				$pid = $_POST['pid'];
			}
			if ( empty( $pid ) && isset($_GET['pid']) ) {
				$pid = $_GET['pid'];
			}
			if ( !empty( $pid ) ) {
				$report_type = 'post';
			}
			if (isset($_POST['cid'])) {
				$cid = $_POST['cid'];
			}
			if ( empty( $cid ) && isset($_GET['cid']) ) {
				$cid = $_GET['cid'];
			}
			if ( !empty( $cid ) ) {
				$report_type = 'comment';
			}
			?>
            <h2><?php _e('Meldungs-Archiv', 'moderation') ?></h2>
            <?php
			if ( empty( $report_type ) ) {
			?>
            <form name="report_archive" method="POST" action="admin.php?page=moderation-report-archive">
                <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Meldungs-Typ', 'moderation') ?></th>
                <td><select name="report_type">
                    <option value="post"><?php _e('Beitrag', 'moderation'); ?></option>
                    <option value="comment" ><?php _e('Kommentar', 'moderation'); ?></option>
                    <option value="blog" ><?php _e('Blog', 'moderation'); ?></option>
                    <option value="all" selected="selected" ><?php _e('Alle', 'moderation'); ?></option>
                </select>
                </td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Blog ID', 'moderation') ?></th>
                <td><input type="text" name="bid" id="bid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Beitrags-ID', 'moderation') ?></th>
                <td><input type="text" name="pid" id="pid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Kommentar-ID', 'moderation') ?></th>
                <td><input type="text" name="cid" id="cid" style="width: 95%" value="" />
                <br />
                <?php //_e('') ?></td>
                </tr>
                </table>
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Suche', 'moderation') ?>" />
            </p>
            </form>
			<?php
			}
			if ( !empty( $report_type ) ) {
                if( isset( $_GET[ 'start' ] ) == false ) {
                    $start = 0;
                } else {
                    $start = intval( $_GET[ 'start' ] );
                }
                if( isset( $_GET[ 'num' ] ) == false ) {
                    $num = 30;
                } else {
                    $num = intval( $_GET[ 'num' ] );
                }

                $where =  "WHERE report_status != 'new' ";
                if ( $report_type != 'all' ) {
                    $where = $where . $wpdb->prepare("AND report_object_type = %s", $report_type);
                }
                if ( !empty($bid ) ) {
                    $where = $where . $wpdb->prepare("AND report_blog_ID = %d", $bid);
                }
                if ( !empty($pid ) ) {
                    $where = $where . $wpdb->prepare("AND report_object_ID = %d", $pid);
                }
                if ( !empty($cid ) ) {
                    $where = $where . $wpdb->prepare("AND report_object_ID = %d", $cid);
                }

                $query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "moderation_reports " . $where . " ORDER BY report_stamp DESC LIMIT %d, %d", $start, $num);
                $reports = $wpdb->get_results( $query, ARRAY_A );
                if( count( $reports ) < $num ) {
                    $next = false;
                } else {
                    $next = true;
                }
                if ( count( $reports ) > 0 ) {
                    $report_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "moderation_reports " . $where);
                    if ($report_count > 30){
                        ?>
                        <br />
                        <table><td>
                        <fieldset>
                        <?php

                        //$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];

                        if( $start == 0 ) {
                            echo __('Vorherige Seite', 'moderation');
                        } elseif( $start <= 30 ) {
                            echo '<a href="admin.php?page=moderation-report-archive&cid=' . $cid . '&bid=' . $bid . '&pid=' . $pid . '&report_type=' . $report_type . '&start=0&' . $order_sort . ' " style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
                        } else {
                            echo '<a href="admin.php?page=moderation-report-archive&cid=' . $cid . '&bid=' . $bid . '&pid=' . $pid . '&report_type=' . $report_type . '&start=' . ( $start - $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Vorherige Seite', 'moderation') . '</a>';
                        }
                        if ( $next ) {
                            echo '&nbsp;||&nbsp;<a href="admin.php?page=moderation-report-archive&cid=' . $cid . '&bid=' . $bid . '&pid=' . $pid . '&report_type=' . $report_type . '&start=' . ( $start + $num ) . '&' . $order_sort . '" style="text-decoration:none;" >' . __('Nächste Seite', 'moderation') . '</a>';
                        } else {
                            echo '&nbsp;||&nbsp;' . __('Nächste Seite', 'moderation');
                        }
                        ?>
                        </fieldset>
                        </td></table>
                        <?php
                    }
                    echo "
                    <br />
                    <table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
                    <thead><tr>
                    <th scope='col'>" . __('Meldung-Typ', 'moderation') . "</th>
					<th scope='col'>" . __('Meldung-Grund', 'moderation') . "</th>
					<th scope='col'>" . __('Meldung-Status', 'moderation') . "</th>
                    <th scope='col'>" . __('Blog', 'moderation') . "</th>
                    <th scope='col'>" . __('Datum/Zeit', 'moderation') . "</th>
                    <th scope='col'>" . __('Aktionen', 'moderation') . "</th>
                    </tr></thead>
                    <tbody id='the-list'>
                    ";
                    //=========================================================//
                        $class = (isset($class) && 'alternate' == $class) ? '' : 'alternate';
                        $date_format = get_option('date_format');
                        $time_format = get_option('time_format');
                        foreach ($reports as $report){
                        //=========================================================//
                        echo "<tr class='" . $class . "'>";

                        unset( $blog_details );
                        $blog_details = get_blog_details( $report['report_blog_ID'] );

						unset( $report_reason );
						$report_reason = $report['report_reason'];
						if ( !empty( $report['report_note'] ) ) {
							$report_reason = $report_reason . ' - ' . $report['report_note'];
						}

						echo "<td valign='top'>" . ucfirst( $report['report_object_type'] ) . "</td>";
						echo "<td valign='top'>" . $report_reason . "</td>";
						echo "<td valign='top'>" . ucfirst( $report['report_status'] ) . "</td>";
						echo "<td valign='top'><a href='" . $blog_details->siteurl . "' rel='permalink' class='edit'>" . $blog_details->blogname . "</a> (" . $blog_details->siteurl . ")</td>";

                        echo "<td valign='top'>" . date_i18n( $date_format . ' ' . $time_format, $report['report_stamp'] ) . "</td>";
                        echo "<td valign='top'>";
						if ( $report['report_object_type'] == 'post' ) {
							echo "<a href='admin.php?page=moderation-post-archive&object_type=all&bid=" . $report['report_blog_ID'] . "&pid=" . $report['report_object_ID'] . "' rel='permalink' class='edit'>" . __('Ansehen', 'moderation') . "</a>";
						}
						if ( $report['report_object_type'] == 'comment' ) {
							echo "<a href='admin.php?page=moderation-comment-archive&bid=" . $report['report_blog_ID'] . "&cid=" . $report['report_object_ID'] . "' rel='permalink' class='edit'>" . __('Ansehen', 'moderation') . "</a>";
						}
						echo"</td>";

                        echo "</tr>";
                        $class = ('alternate' == $class) ? '' : 'alternate';
                        //=========================================================//
                        }
                    //=========================================================//
                    ?>
                    </tbody></table>
                    <?php
                } else {
                    ?>
                    <p><?php _e('Keine Meldungen gefunden.', 'moderation') ?></p>
                    <?php
                }
			}
		break;
		//---------------------------------------------------//
		case "process":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function moderation_warnings() {
	global $wpdb, $wp_roles, $user_id, $current_site, $current_user;

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'moderation') ?></p></div><?php
	}
	echo '<div class="wrap">';
	if (!isset($_GET[ 'action' ])) {
		$_GET[ 'action' ] = '';
	}
	
	$current_user = wp_get_current_user();
	
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
            <h2><?php _e('Warnungen', 'moderation') ?></h2>
            <ul>
            <?php
			$query = "SELECT warning_note FROM " . $wpdb->base_prefix . "moderation_warnings WHERE warning_user_ID = '" . $current_user->ID . "' AND warning_read = '0'";
			$warnings = $wpdb->get_results( $query, ARRAY_A );
			foreach ( $warnings as $warning ) {
				echo '<li>' . $warning['warning_note'] . '</li>';
			}
			?>
            </ul>
            <form name="accept" method="POST" action="admin.php?page=moderation-warning&action=accept">
            <p class="submit">
            <input class="button button-primary" type="submit" name="Submit" value="<?php _e('Akzeptieren', 'moderation') ?>" />
            </p>
            </form>
            <?php
		break;
		//---------------------------------------------------//
		case "accept":
			$wpdb->query( "UPDATE " . $wpdb->base_prefix . "moderation_warnings SET warning_read = '1' WHERE warning_user_ID = '" . $current_user->ID . "'" );

			echo "
			<SCRIPT LANGUAGE='javascript'>
			window.location='index.php';
			</script>
			";
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

function moderation_roundup($value, $dp){
    return ceil($value*pow(10, $dp))/pow(10, $dp);
}


