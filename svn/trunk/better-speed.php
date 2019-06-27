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
	//Self Pingbacks
	add_action('pre_ping', function(&$links) {
		$home = get_option('home');
		foreach($links as $l => $link) {
			if(strpos($link, $home) === 0) {
				unset($links[$l]);
			}
		}
	});

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
		global $wp;
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

	//XML-RPC
	if(better_speed_check_setting('xmlrpc')) {
		add_filter('xmlrpc_enabled', '__return_false');
		add_filter('pings_open', '__return_false', 9999);
	  add_filter('wp_headers', function($headers) {
	    unset($headers['X-Pingback'], $headers['x-pingback']);
	    return $headers;
	  });
	}

	//Generator
	if(better_speed_check_setting('generator')) {
		remove_action('wp_head', 'wp_generator');
		add_filter('the_generator', function() {
			return '';
		});
	}

	//WLW Manifest
	if(better_speed_check_setting('manifest')) {
		remove_action('wp_head', 'wlwmanifest_link');
	}

	//RSD Link
	if(better_speed_check_setting('rsdlink')) {
		remove_action('wp_head', 'rsd_link');
	}

	//Shortlink
	if(better_speed_check_setting('shortlink')) {
		remove_action('wp_head', 'wp_shortlink_wp_head');
		remove_action('template_redirect', 'wp_shortlink_header', 11, 0);
	}

	//RSS Feeds
	if(better_speed_check_setting('rssfeeds')) {
		remove_action('wp_head', 'feed_links', 2);
	  remove_action('wp_head', 'feed_links_extra', 3);
    add_action('template_redirect', function() {
			if(!is_feed() || is_404()) {
				return;
			}
			if(isset($_GET['feed'])) {
				wp_redirect(esc_url_raw(remove_query_arg('feed')), 301);
				exit;
			}
			if(get_query_var('feed') !== 'old') {
				set_query_var('feed', '');
			}
			redirect_canonical();
			wp_die(sprintf(__("RSS Feeds disabled, please visit the <a href='%s'>homepage</a>!"), esc_url(home_url('/'))));
		}, 1);
	}

	//REST API
	if(better_speed_check_setting('restapi') && !is_admin()) {
		remove_action('xmlrpc_rsd_apis', 'rest_output_rsd');
		remove_action('wp_head', 'rest_output_link_wp_head');
		remove_action('template_redirect', 'rest_output_link_header', 11, 0);
    add_filter('rest_authentication_errors', function($result) {
			if(empty($result) && !is_admin()) {
				return new WP_Error('rest_authentication_error', __('Forbidden', 'better-speed-text'), array('status' => 403));
			}
			return $result;
		}, 20);
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

function better_speed_wp_enqueue_scripts() {
	//Dashicons
	if(better_speed_check_setting('dashicons') && !is_user_logged_in()) {
		wp_dequeue_style('dashicons');
		wp_deregister_style('dashicons');
	}

	//Heartbeat
	if(better_speed_check_setting('heartbeat')) {
		global $pagenow;
		if($pagenow!=='post.php' && $pagenow!=='post-new.php') {
			wp_deregister_script('heartbeat');
		}
	}
}
add_action('wp_enqueue_scripts', 'better_speed_wp_enqueue_scripts');

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
  add_settings_field('better-speed-features-dashicons', __('Dashicons', 'better-speed-text'), 'better_speed_features_dashicons', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-heartbeat', __('Heartbeat', 'better-speed-text'), 'better_speed_features_heartbeat', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-xmlrpc', __('XML-RPC + Pingback', 'better-speed-text'), 'better_speed_features_xmlrpc', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-generator', __('Generator', 'better-speed-text'), 'better_speed_features_generator', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-manifest', __('WLW Manifest', 'better-speed-text'), 'better_speed_features_manifest', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-rsdlink', __('Really Simple Discovery', 'better-speed-text'), 'better_speed_features_rsdlink', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-shortlink', __('Short Link', 'better-speed-text'), 'better_speed_features_shortlink', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-rssfeeds', __('RSS Feeds', 'better-speed-text'), 'better_speed_features_rssfeeds', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-restapi', __('REST API', 'better-speed-text'), 'better_speed_features_restapi', 'better-speed', 'better-speed-section-features');
}

//allow the settings to be stored
add_filter('whitelist_options', function($whitelist_options) {
  $whitelist_options['better-speed'][] = 'better-speed-features-emojis';
  $whitelist_options['better-speed'][] = 'better-speed-features-embed';
  $whitelist_options['better-speed'][] = 'better-speed-features-migrate';
  $whitelist_options['better-speed'][] = 'better-speed-features-dashicons';
  $whitelist_options['better-speed'][] = 'better-speed-features-heartbeat';
  $whitelist_options['better-speed'][] = 'better-speed-features-generator';
  $whitelist_options['better-speed'][] = 'better-speed-features-xmlrpc';
  $whitelist_options['better-speed'][] = 'better-speed-features-manifest';
  $whitelist_options['better-speed'][] = 'better-speed-features-rsdlink';
  $whitelist_options['better-speed'][] = 'better-speed-features-shortlink';
  $whitelist_options['better-speed'][] = 'better-speed-features-rssfeeds';
  $whitelist_options['better-speed'][] = 'better-speed-features-restapi';
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
	$tags = 0;
	if(better_speed_check_setting('emojis')) {
    $reqs += 1;
		$size += 16;
		$tags += 1;
	}
	if(better_speed_check_setting('embed')) {
    $reqs += 1;
		$size += 6;
		$tags += 1;
	}
	if(better_speed_check_setting('migrate')) {
		$reqs += 1;
		$size += 10;
		$tags += 1;
	}
	if(better_speed_check_setting('dashicons')) {
		$reqs += 1;
		$size += 46;
		$tags += 1;
	}
	if(better_speed_check_setting('heartbeat')) {
		$reqs += 1;
		$size += 6;
		$tags += 1;
	}
	if(better_speed_check_setting('generator')) {
		$tags += 1;
	}
	if(better_speed_check_setting('xmlrpc')) {
		$tags += 1;
	}
	if(better_speed_check_setting('manifest')) {
		$tags += 1;
	}
	if(better_speed_check_setting('rsdlink')) {
		$tags += 1;
	}
	if(better_speed_check_setting('shortlink')) {
		$tags += 1;
	}
	if(better_speed_check_setting('rssfeeds')) {
		$tags += 2;
	}
	if(better_speed_check_setting('restapi')) {
		$tags += 1;
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
	echo '      <tr>';
	echo '        <th scope="row">HTML Tags</th>';
	echo '        <td>' . $tags . '</td>';
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

function better_speed_features_dashicons() {
	$checked = "";
	if(better_speed_check_setting('dashicons')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-dashicons" name="better-speed-settings[better-speed-features-dashicons]" type="checkbox" value="YES"' . $checked . '> Remove support for Dashicons <u>when not logged in</u> <em>(saves 1 file request and ~46kb)</em>';
}

function better_speed_features_heartbeat() {
	$checked = "";
	if(better_speed_check_setting('heartbeat')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-heartbeat" name="better-speed-settings[better-speed-features-heartbeat]" type="checkbox" value="YES"' . $checked . '> Remove support for auto-save <u>when not editing a page/post</u> <em>(saves 1 file request and ~6kb)</em>';
}

function better_speed_features_xmlrpc() {
	$checked = "";
	if(better_speed_check_setting('xmlrpc')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-xmlrpc" name="better-speed-settings[better-speed-features-xmlrpc]" type="checkbox" value="YES"' . $checked . '> Remove support for third-party application access <em>(such as mobile apps)</em>';
}

function better_speed_features_generator() {
	$checked = "";
	if(better_speed_check_setting('generator')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-generator" name="better-speed-settings[better-speed-features-generator]" type="checkbox" value="YES"' . $checked . '> Remove the generator tag <em>(includes Wordpress version number)</em>';
}

function better_speed_features_manifest() {
	$checked = "";
	if(better_speed_check_setting('manifest')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-manifest" name="better-speed-settings[better-speed-features-manifest]" type="checkbox" value="YES"' . $checked . '> Remove the Windows Live Writer manifest tag <em>(WLW was discontinued in Jan 2017)</em>';
}

function better_speed_features_rsdlink() {
	$checked = "";
	if(better_speed_check_setting('rsdlink')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-rsdlink" name="better-speed-settings[better-speed-features-rsdlink]" type="checkbox" value="YES"' . $checked . '> Remove the Really Simple Discovery (RSD) tag <em>(this protocol never became popular)</em>';
}

function better_speed_features_shortlink() {
	$checked = "";
	if(better_speed_check_setting('shortlink')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-shortlink" name="better-speed-settings[better-speed-features-shortlink]" type="checkbox" value="YES"' . $checked . '> Remove the Short Link tag <em>(search engines ignore this tag completely)</em>';
}

function better_speed_features_rssfeeds() {
	$checked = "";
	if(better_speed_check_setting('rssfeeds')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-rssfeeds" name="better-speed-settings[better-speed-features-rssfeeds]" type="checkbox" value="YES"' . $checked . '> Remove the RSS feed links and disable the feeds <em>(will redirect to the page instead)</em>';
}

function better_speed_features_restapi() {
	$checked = "";
	if(better_speed_check_setting('restapi')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-restapi" name="better-speed-settings[better-speed-features-restapi]" type="checkbox" value="YES"' . $checked . '> Remove the REST API links and disable the endpoints <u>when not on admin pages</u>';
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
