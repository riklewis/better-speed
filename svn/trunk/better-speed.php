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
-------------------------- Remove Features --------------------------
*/

function better_speed_init() {
	global $wp;

	//Emojis
	if(better_speed_check_setting('emojis')) {
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('admin_print_scripts', 'print_emoji_detection_script');
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_action('admin_print_styles', 'print_emoji_styles');
		remove_filter('the_content_feed', 'wp_staticize_emoji');
		remove_filter('comment_text_rss', 'wp_staticize_emoji');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
		add_filter('tiny_mce_plugins', function($plugins) {
			return array_diff($plugins, array('wpemoji'));
		});
	}

	//Embed
	if(better_speed_check_setting('embed')) {
		$wp->public_query_vars = array_diff($wp->public_query_vars, array('embed'));
		remove_action('rest_api_init', 'wp_oembed_register_route');
		remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('wp_head', 'wp_oembed_add_host_js');
		remove_filter('pre_oembed_result', 'wp_filter_pre_oembed_result', 10);
		add_filter('embed_oembed_discover', '__return_false' );
		add_filter('tiny_mce_plugins', function($plugins) {
			return array_diff($plugins, array('wpembed'));
		});
		add_filter('rewrite_rules_array', function($rules) {
			foreach($rules as $rule => $rewrite) {
				if(strpos($rewrite, 'embed=true')!==false) {
					unset($rules[$rule]);
				}
			}
			return $rules;
		});
	}
}
add_action('init', 'better_speed_init');

function better_speed_wp_default_scripts($scripts) {
	//jQuery Migrate
	if(better_speed_check_setting('migrate')) {
		$script = $scripts->registered['jquery'];
		if($script->deps) {
			$script->deps = array_diff($script->deps, array('jquery-migrate'));
		}
	}
}
add_filter('wp_default_scripts', 'better_speed_wp_default_scripts');

//XML-RPC
if(better_speed_check_setting('xmlrpc')) {
	add_filter('xmlrpc_enabled', '__return_false');
	add_filter('pings_open', '__return_false', 9999);
  add_filter('wp_headers', function($headers) {
    unset($headers['X-Pingback'], $headers['x-pingback']);
    return $headers;
  });
}

/*
----------------------------- Settings ------------------------------
*/

//check checkbox setting
function better_speed_check_setting($suffix) {
	$settings = get_option('better-speed-settings');
  return (isset($settings['better-speed-features-' . $suffix]) && $settings['better-speed-features-' . $suffix]==="YES");
}

//add settings page
function better_speed_menus() {
	add_options_page(__('Better Speed','better-speed-text'), __('Better Speed','better-speed-text'), 'manage_options', 'better-speed-settings', 'better_speed_show_settings');
}

//add the settings
function better_speed_settings() {
	register_setting('better-speed','better-speed-settings');

  add_settings_section('better-speed-section-features', __('Disable Features', 'better-speed-text'), 'better_speed_section_features', 'better-speed');
  add_settings_field('better-speed-features-emojis', __('Emojis', 'better-speed-text'), 'better_speed_features_emojis', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-embed', __('Embed Objects', 'better-speed-text'), 'better_speed_features_embed', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-migrate', __('jQuery Migrate', 'better-speed-text'), 'better_speed_features_migrate', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-xmlrpc', __('XML-RPC + Pingback', 'better-speed-text'), 'better_speed_features_xmlrpc', 'better-speed', 'better-speed-section-features');
}

//allow the settings to be stored
add_filter('whitelist_options', function($whitelist_options) {
  $whitelist_options['better-speed'][] = 'better-speed-features-emojis';
  $whitelist_options['better-speed'][] = 'better-speed-features-embed';
  $whitelist_options['better-speed'][] = 'better-speed-features-migrate';
  $whitelist_options['better-speed'][] = 'better-speed-features-xmlrpc';
  return $whitelist_options;
});

//define output for settings page
function better_speed_show_settings() {
  echo '<div class="wrap">';
  echo '  <div style="padding:12px;background-color:white;margin:24px 0;">';
  echo '    <a href="https://bettersecurity.co" target="_blank" style="display:inline-block;width:100%;">';
  echo '      <img src="' . plugins_url('header.png', __FILE__) . '" style="height:64px;">';
  echo '    </a>';
  echo '  </div>';
	echo '  <div style="margin:0 0 24px 0;">';
  echo '    <a href="https://www.php.net/supported-versions.php" target="_blank"><img src="' . better_speed_badge_php() . '"></a>';
  echo '  </div>';
  echo '  <h1>' . __('Better Speed', 'better-speed-text') . '</h1>';
	echo '  <p>This plugin will allow you to easily remove bloat and turn off unused features, in order to streamline your website and reduce file requests.';
	echo '  <p>This plugin is NOT a caching plugin, but should play well with any caching plugin you decide to use.';
	echo '  <br><br>';
	echo '  <form action="options.php" method="post">';
	settings_fields('better-speed');
  do_settings_sections('better-speed');
	submit_button();
  echo '  </form>';

  //estimated savings
	$reqs = 0;
	$size = 0;
	if(better_speed_check_setting('emojis')) {
    $reqs += 1;
		$size += 16;
	}
	if(better_speed_check_setting('embed')) {
    $reqs += 1;
		$size += 6;
	}
	if(better_speed_check_setting('migrate')) {
		$reqs += 1;
		$size += 10;
	}
	echo '  <h2>Estimated Savings</h2>';
  echo '  <hr>';
  echo '  <table class="form-table">';
  echo '    <tbody>';
	echo '      <tr>';
	echo '        <th scope="row">File Requests</th>';
	echo '        <td>' . $reqs . '</td>';
	echo '      </tr>';
	echo '      <tr>';
	echo '        <th scope="row">File Size</th>';
	echo '        <td>' . ($size>=1024 ? (number_format($size/1024,1)) . 'Mb' : $size . 'kb') . '</td>';
	echo '      </tr>';
	echo '    </tbody>';
  echo '  </table>';
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
function better_speed_section_features() {
  echo '<hr>';
}

//defined output for settings
function better_speed_features_emojis() {
	$checked = "";
	if(better_speed_check_setting('emojis')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-emojis" name="better-speed-settings[better-speed-features-emojis]" type="checkbox" value="YES"' . $checked . '> Remove support for emojis in posts <em>(saves at least 1 file request and ~16kb)</em>';
}

function better_speed_features_embed() {
	$checked = "";
	if(better_speed_check_setting('embed')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-embed" name="better-speed-settings[better-speed-features-embed]" type="checkbox" value="YES"' . $checked . '> Remove support for embedding objects in posts <em>(saves at least 1 file request and ~6kb)</em>';
}

function better_speed_features_migrate() {
	$checked = "";
	if(better_speed_check_setting('migrate')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-migrate" name="better-speed-settings[better-speed-features-migrate]" type="checkbox" value="YES"' . $checked . '> Remove support for old jQuery features dropped in 2016 <em>(saves 1 file request and ~10kb)</em>';
}

function better_speed_features_xmlrpc() {
	$checked = "";
	if(better_speed_check_setting('xmlrpc')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-xmlrpc" name="better-speed-settings[better-speed-features-xmlrpc]" type="checkbox" value="YES"' . $checked . '> Remove support for third-party application access, such as mobile apps';
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
