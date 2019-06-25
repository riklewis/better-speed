<?php
/*
Plugin Name:  Better Speed
Description:  Improve the loading speed of your website by removing bloat and unused features
Version:      1.0
Author:       Better Security
Author URI:   https://bettersecurity.co
License:      GPL3
License URI:  https://www.gnu.org/licenses/gpl-3.0.en.html
Text Domain:  better-speed-text
Domain Path:  /languages
*/

//prevent direct access
defined('ABSPATH') or die('Forbidden');

//debug logging if required
function better_speed_log($message) {
  if (WP_DEBUG === true) {
    if (is_array($message) || is_object($message)) {
      error_log(print_r($message, true));
    }
		else {
      error_log($message);
    }
  }
}

/*
----------------------------- Settings ------------------------------
*/

function better_speed_admin_scripts() {
	if($_GET["page"]==="better-speed-settings") {
	  wp_enqueue_script('jquery-ui-core');
	  wp_enqueue_script('jquery-ui-tabs');
		wp_enqueue_script('better-speed-main-js', plugins_url('main.js', __FILE__),array('jquery','jquery-ui-tabs'),false,true);
		wp_enqueue_style('jquery-ui-tabs-min-css', plugins_url('jquery-ui-tabs.min.css', __FILE__));
	}
}
add_action('admin_enqueue_scripts', 'better_speed_admin_scripts');

//add settings page
function better_speed_menus() {
	add_options_page(__('Better Speed','better-speed-text'), __('Better Detection','better-speed-text'), 'manage_options', 'better-speed-settings', 'better_speed_show_settings');
}

//add the settings
function better_speed_settings() {
	register_setting('better-speed','better-speed-settings');

  add_settings_section('better-speed-section-notify', __('Notifications', 'better-speed-text'), 'better_speed_section_notify', 'better-speed');
  add_settings_field('better-speed-notify-email', __('Email Address', 'better-speed-text'), 'better_speed_notify_email', 'better-speed', 'better-speed-section-notify');
  add_settings_field('better-speed-notify-slack', __('Slack WebHook URL', 'better-speed-text'), 'better_speed_notify_slack', 'better-speed', 'better-speed-section-notify');
}

//allow the settings to be stored
add_filter('whitelist_options', function($whitelist_options) {
  $whitelist_options['better-speed'][] = 'better-speed-notify-email';
  $whitelist_options['better-speed'][] = 'better-speed-notify-slack';
  return $whitelist_options;
});

//define output for settings page
function better_speed_show_settings() {
	global $wpdb;
	$errors = $wpdb->prefix . "better_speed_errors";
	$frmt = get_option('time_format') . ', ' . get_option('date_format');

  echo '<div class="wrap">';
  echo '  <div style="padding:12px;background-color:white;margin:24px 0;">';
  echo '    <a href="https://bettersecurity.co" target="_blank" style="display:inline-block;width:100%;">';
  echo '      <img src="' . plugins_url('header.png', __FILE__) . '" style="height:64px;">';
  echo '    </a>';
  echo '  </div>';
	echo '  <div style="margin:0 0 24px 0;">';
  echo '    <a href="https://www.php.net/supported-versions.php" target="_blank"><img src="' . better_speed_badge_php() . '"></a>';
  echo '  </div>';
  echo '  <h1>' . __('Better Detection', 'better-speed-text') . '</h1>';
	echo '  <p>This plugin will create and store hashes of content (eg. posts, pages, etc.) and monitor these moving forwards in order to detect when changes occur.  When changes are made outside of the normal working process, such as a direct database update, this will then be detected as the hash will get out of sync with the content.';
  echo '  <div id="better-speed-tabs">';
  echo '    <ul>';
  echo '      <li><a href="#better-speed-tabs-errors">Errors<span id="better-speed-error-count"></span></a></li>';
  echo '      <li><a href="#better-speed-tabs-settings">Options</a></li>';
  //echo '      <li><a href="#better-speed-tabs-extras">Extras</a></li>';
  echo '    </ul>';
  echo '    <div id="better-speed-tabs-errors">';

	//check if unfixed errors
	$count = $wpdb->get_var("SELECT COUNT(*) FROM $errors WHERE fixed_date IS NULL");
	if($count>0) {
		echo '    	<table class="wp-list-table widefat striped">';
		echo '      	<thead>';
		echo '        	<tr>';
		echo '    		    <th scope="col" id="better-speed-type" class="manage-column column-name column-primary">Type</th>';
		echo '            <th scope="col" id="better-speed-desc" class="manage-column column-description">Title</th>';
		echo '    		    <th scope="col" id="better-speed-indx" class="manage-column column-index">ID</th>';
		echo '    		    <th scope="col" id="better-speed-stat" class="manage-column column-status">Status</th>';
		echo '    		    <th scope="col" id="better-speed-cred" class="manage-column column-datetime">Created</th>';
		echo '    		    <th scope="col" id="better-speed-modd" class="manage-column column-datetime">Modified</th>';
		echo '    		    <th scope="col" id="better-speed-detd" class="manage-column column-datetime">Change Detected</th>';
		echo '    		    <th scope="col" id="better-speed-actn" class="manage-column column-actions">Action</th>';
		echo '         </tr>';
		echo '      	</thead>';
	  echo '        <tbody id="better-speed-list">';
		$sql = "SELECT * FROM $errors WHERE fixed_date IS NULL ORDER BY error_date ASC";
		$rows = $wpdb->get_results($sql);
		foreach($rows as $row) {
			if($row->post_id) {
				$item = get_post($row->post_id);
				$type = ucwords($item->post_type);
				$desc = '<a href="' . get_permalink($item->ID) . '" target="blank">' . $item->post_title . ' <span class="dashicons dashicons-external"></span></a>';
				$indx = $item->ID;
				$stat = ucwords($item->post_status);
				$cred = date($frmt, strtotime($item->post_date));
				$modd = date($frmt, strtotime($item->post_modified));
			}
			else {
				$type = "File";
				$desc = $row->filename;
				$indx = "";
				$stat = ""; //added/updated/deleted?
				$cred = ""; //file created date
				$modd = ""; //file modified date
			}
			echo '    		  <tr class="inactive">';
			echo '            <td class="column-primary">' . $type . '</td>';
			echo '            <td class="column-description desc">' . $desc . '</td>';
			echo '            <td class="column-index">' . $indx . '</td>';
			echo '            <td class="column-status">' . $stat . '</td>';
			echo '            <td class="column-datetime">' . $cred . '</td>';
			echo '            <td class="column-datetime">' . $modd . '</td>';
			echo '            <td class="column-datetime">' . date($frmt, strtotime($row->error_date)) . '</td>';
			echo '            <td class="column-actions">';
			echo '              <input type="button" id="action-fix-' . $row->error_id . '" class="button button-primary action-fixed" value="Fixed">';
			echo '              <input type="button" id="action-ign-' . $row->error_id . '" class="button button-secondary action-ignore" value="Ignore">';
			echo '            </td>';
			echo '          </tr>';
		}
		echo '        </tbody>';
		echo '      	<tfoot>';
		echo '        	<tr>';
		echo '    		    <th scope="col" class="manage-column column-name column-primary">Type</th>';
		echo '            <th scope="col" class="manage-column column-description">Title</th>';
		echo '    		    <th scope="col" class="manage-column column-index">ID</th>';
		echo '    		    <th scope="col" class="manage-column column-status">Status</th>';
		echo '    		    <th scope="col" class="manage-column column-datetime">Created</th>';
		echo '    		    <th scope="col" class="manage-column column-datetime">Modified</th>';
		echo '    		    <th scope="col" class="manage-column column-datetime">Change Detected</th>';
		echo '    		    <th scope="col" class="manage-column column-actions">Action</th>';
		echo '         </tr>';
		echo '        </tfoot>';
		echo '      </table>';
	}
	else {
		echo '      <p>No new errors have been detected - yay!</p>';
	}
	echo '    </div>';
	echo '    <div id="better-speed-tabs-settings">';
	echo '      <form action="options.php" method="post">';
	settings_fields('better-speed');
  do_settings_sections('better-speed');
	submit_button();
  echo '      </form>';
	echo '    </div>';
	//echo '    <div id="better-speed-tabs-extras">';
	//echo '    </div>';
	echo '  </div>';
  echo '</div>';
}

function better_speed_badge_php() {
  $ver = phpversion();
  $col = "critical";
  if(version_compare($ver,'7.1','>=')) {
    $col = "important";
  }
  if(version_compare($ver,'7.2','>=')) {
    $col = "success";
  }
  return 'https://img.shields.io/badge/PHP-' . $ver . '-' . $col . '.svg?logo=php&style=for-the-badge';
}

//define output for settings section
function better_speed_section_notify() {
  echo '<hr>';
}

//defined output for settings
function better_speed_notify_email() {
	$settings = get_option('better-speed-settings');
	$value = "";
	if(isset($settings['better-speed-notify-email']) && $settings['better-speed-notify-email']!=="") {
		$value = $settings['better-speed-notify-email'];
	}
  echo '<input id="better-speed" name="better-speed-settings[better-speed-notify-email]" type="email" size="50" value="' . str_replace('"', '&quot;', $value) . '">';
}

function better_speed_notify_slack() {
	$settings = get_option('better-speed-settings');
	$value = "";
	if(isset($settings['better-speed-notify-slack']) && $settings['better-speed-notify-slack']!=="") {
		$value = $settings['better-speed-notify-slack'];
	}
  echo '<input id="better-speed" name="better-speed-settings[better-speed-notify-slack]" type="url" size="50" value="' . str_replace('"', '&quot;', $value) . '">';
	echo '<br><small><em>See Slack\'s <a href="https://slack.com/services/new/incoming-webhook">Channel Settings &gt; Add an App &gt; Incoming WebHooks</a> menu.</em></small>';
}

//add actions
if(is_admin()) {
  add_action('admin_menu','better_speed_menus');
  add_action('admin_init','better_speed_settings');
}

/*
--------------------- Add links to plugins page ---------------------
*/

//show settings link
function better_speed_links($links) {
	$links[] = sprintf('<a href="%s">%s</a>',admin_url('options-general.php?page=better-speed-settings'),'Settings');
	return $links;
}

//add actions
if(is_admin()) {
  add_filter('plugin_action_links_'.plugin_basename(__FILE__),'better_speed_links');
}

/*
----------------------------- The End ------------------------------
*/
