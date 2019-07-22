<?php
/*
Plugin Name:  Better Speed
Description:  Improve the loading speed of your website by removing bloat and unused features
Version:      1.1
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
		remove_action('wp_head', 'print_emoji_speed_script', 7);
		remove_action('admin_print_scripts', 'print_emoji_speed_script');
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

	//Comments
	if(better_speed_check_setting('comments')) {
		add_action('widgets_init', function() {
			unregister_widget('WP_Widget_Recent_Comments');
	    add_filter('show_recent_comments_widget_style', '__return_false');
		});
		add_action('template_redirect', function() {
			if(is_comment_feed()) {
     	  wp_die(__('Comments are disabled.', 'better-speed-text'), '', array('response' => 403));
      }
		}, 9);
		add_action('template_redirect', function() {
			if(is_admin_bar_showing()) {
				remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
			}
		});
		add_action('admin_init', function() {
			if(is_admin_bar_showing()) {
				remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
			}
		});
		add_action('wp_loaded', function() {
			$post_types = get_post_types(array('public' => true), 'names');
			if(!empty($post_types)) {
				foreach($post_types as $post_type) {
					if(post_type_supports($post_type, 'comments')) {
						remove_post_type_support($post_type, 'comments');
						remove_post_type_support($post_type, 'trackbacks');
					}
				}
			}
			add_filter('comments_array', function() {
				return array();
			}, 20, 2);
			add_filter('comments_open', function() {
				return false;
			}, 20, 2);
			add_filter('pings_open', function() {
				return false;
			}, 20, 2);
			if(is_admin()) {
				add_action('admin_menu', function() {
					global $pagenow;
					remove_menu_page('edit-comments.php');
					remove_submenu_page('options-general.php', 'options-discussion.php');
					if($pagenow == 'comment.php' || $pagenow == 'edit-comments.php') {
						wp_die(__('Comments are disabled.', 'better-speed-text'), '', array('response' => 403));
					}
					if($pagenow == 'options-discussion.php') {
						wp_die(__('Comments are disabled.', 'better-speed-text'), '', array('response' => 403));
					}
				}, 9999);
				add_action('admin_print_styles-index.php', function() {
					echo "<style>#dashboard_right_now .comment-count,#dashboard_right_now .comment-mod-count,#latest-comments,#welcome-panel .welcome-comments{display:none !important}</style>";
        });
				add_action('admin_print_styles-profile.php', function() {
					echo "<style>.user-comment-shortcuts-wrap{display:none !important}</style>";
				});
				add_action('wp_dashboard_setup', function() {
					remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
				});
				add_filter('pre_option_default_pingback_flag', '__return_zero');
			}
			else {
				wp_deregister_script('comment-reply');
				add_filter('comments_template', function() {
					return dirname(__FILE__) . '/comments-template.php';
				}, 20);
				add_filter('feed_links_show_comments_feed', '__return_false');
			}
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
--------------------------- Instant Page ----------------------------
*/

function better_speed_enqueue_instant_page() {
  if(better_speed_check_other_setting('instant-page')) {
    wp_enqueue_script('better-speed-instant-page', plugins_url('instant.page.min.js', __FILE__), array(), false, true);
  }
}
add_action('wp_enqueue_scripts', 'better_speed_enqueue_instant_page');

function better_speed_defer_scripts($tag, $handle, $src) {
	$tag = str_replace(' type="text/javascript"','',str_replace(" type='text/javascript'",'',$tag));
  if($handle==='better-speed-instant-page') {
    return str_replace('<script', '<script defer', $tag);
  }
  return $tag;
}
add_filter('script_loader_tag', 'better_speed_defer_scripts', 10, 3);

/*
----------------------------- Settings ------------------------------
*/

//check checkbox setting
function better_speed_check_setting($suffix) {
	$settings = get_option('better-speed-settings');
  return (isset($settings['better-speed-features-' . $suffix]) && $settings['better-speed-features-' . $suffix]==="YES");
}

//check checkbox setting
function better_speed_check_other_setting($suffix) {
	$settings = get_option('better-speed-settings');
  return (isset($settings['better-speed-' . $suffix]) && $settings['better-speed-' . $suffix]==="YES");
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
  add_settings_field('better-speed-features-comments', __('Visitor Comments', 'better-speed-text'), 'better_speed_features_comments', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-xmlrpc', __('XML-RPC + Pingback', 'better-speed-text'), 'better_speed_features_xmlrpc', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-generator', __('Generator', 'better-speed-text'), 'better_speed_features_generator', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-manifest', __('WLW Manifest', 'better-speed-text'), 'better_speed_features_manifest', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-rsdlink', __('Really Simple Discovery', 'better-speed-text'), 'better_speed_features_rsdlink', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-shortlink', __('Short Link', 'better-speed-text'), 'better_speed_features_shortlink', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-rssfeeds', __('RSS Feeds', 'better-speed-text'), 'better_speed_features_rssfeeds', 'better-speed', 'better-speed-section-features');
  add_settings_field('better-speed-features-restapi', __('REST API', 'better-speed-text'), 'better_speed_features_restapi', 'better-speed', 'better-speed-section-features');

  add_settings_section('better-speed-section-instant', __('Instant Page', 'better-speed-text'), 'better_speed_section_instant', 'better-speed-instant');
  add_settings_field('better-speed-instant-page', __('Instant Page', 'better-speed-text'), 'better_speed_instant_page', 'better-speed-instant', 'better-speed-section-instant');
}

//allow the settings to be stored
add_filter('whitelist_options', function($whitelist_options) {
  $whitelist_options['better-speed'][] = 'better-speed-features-emojis';
  $whitelist_options['better-speed'][] = 'better-speed-features-embed';
  $whitelist_options['better-speed'][] = 'better-speed-features-migrate';
  $whitelist_options['better-speed'][] = 'better-speed-features-dashicons';
  $whitelist_options['better-speed'][] = 'better-speed-features-heartbeat';
  $whitelist_options['better-speed'][] = 'better-speed-features-comments';
  $whitelist_options['better-speed'][] = 'better-speed-features-generator';
  $whitelist_options['better-speed'][] = 'better-speed-features-xmlrpc';
  $whitelist_options['better-speed'][] = 'better-speed-features-manifest';
  $whitelist_options['better-speed'][] = 'better-speed-features-rsdlink';
  $whitelist_options['better-speed'][] = 'better-speed-features-shortlink';
  $whitelist_options['better-speed'][] = 'better-speed-features-rssfeeds';
  $whitelist_options['better-speed'][] = 'better-speed-features-restapi';
  $whitelist_options['better-speed'][] = 'better-speed-instant-page';
  return $whitelist_options;
});

//define output for settings page
function better_speed_show_settings() {
  echo '<style>#better-speed-tabs h2{display:none}</style>';
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
	echo '  <p>' . __('This plugin will allow you to easily remove bloat and turn off unused features, in order to streamline your website and reduce file requests.', 'better-speed-text');
	echo '  <p>' . __('This plugin is NOT a caching plugin, but should play well with any caching plugin you decide to use', 'better-speed-text');
	echo '  <br><br>';
	echo '  <form action="options.php" method="post">';
	settings_fields('better-speed');

  echo '  <div id="better-speed-tabs">';
  echo '    <ul>';
  echo '      <li><a href="#better-speed-tabs-disable">' . __('Disable Features', 'better-detect-text') . '</a></li>';
  echo '      <li><a href="#better-speed-tabs-instant">' . __('Instant Page', 'better-detect-text') . '</a></li>';
  echo '    </ul>';
  echo '    <div id="better-speed-tabs-disable">';
  do_settings_sections('better-speed');
  echo '    </div>';
  echo '    <div id="better-speed-tabs-instant">';
  do_settings_sections('better-speed-instant');
  echo '    </div>';
  echo '  </div>';

	submit_button();
  echo '  </form>';

  //estimated savings
	$reqs = 0;
	$size = 0;
	$tags = 0;
	if(better_speed_check_setting('emojis')) {
    $reqs += 2;
		$size += 16;
		$tags += 2;
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
	if(better_speed_check_setting('comments')) {
		$reqs += 1;
		$size += 2;
		$tags += 2;
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
	echo '  <h2>' . __('Estimated Savings', 'better-speed-text') . '</h2>';
  echo '  <hr>';
  echo '  <table class="form-table">';
  echo '    <tbody>';
	echo '      <tr>';
	echo '        <th scope="row">' . __('File Requests', 'better-speed-text') . '</th>';
	echo '        <td>' . $reqs . '</td>';
	echo '      </tr>';
	echo '      <tr>';
	echo '        <th scope="row">' . __('File Size', 'better-speed-text') . '</th>';
	echo '        <td>' . ($size>=1024 ? (number_format($size/1024,1)) . 'Mb' : $size . 'kb') . '</td>';
	echo '      </tr>';
	echo '      <tr>';
	echo '        <th scope="row">' . __('HTML Tags', 'better-speed-text') . '</th>';
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
  //nothing to output
}

//defined output for settings
function better_speed_features_emojis() {
	$checked = "";
	if(better_speed_check_setting('emojis')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-emojis" name="better-speed-settings[better-speed-features-emojis]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove support for emojis in posts <em>(saves at least 1 file request and ~16kb)</em>', 'better-speed-text');
}

function better_speed_features_embed() {
	$checked = "";
	if(better_speed_check_setting('embed')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-embed" name="better-speed-settings[better-speed-features-embed]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove support for embedding objects in posts <em>(saves at least 1 file request and ~6kb)</em>', 'better-speed-text');
}

function better_speed_features_migrate() {
	$checked = "";
	if(better_speed_check_setting('migrate')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-migrate" name="better-speed-settings[better-speed-features-migrate]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove support for old jQuery features dropped in 2016 <em>(saves 1 file request and ~10kb)</em>', 'better-speed-text');
}

function better_speed_features_dashicons() {
	$checked = "";
	if(better_speed_check_setting('dashicons')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-dashicons" name="better-speed-settings[better-speed-features-dashicons]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove support for Dashicons <u>when not logged in</u> <em>(saves 1 file request and ~46kb)</em>', 'better-speed-text');
}

function better_speed_features_heartbeat() {
	$checked = "";
	if(better_speed_check_setting('heartbeat')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-heartbeat" name="better-speed-settings[better-speed-features-heartbeat]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove support for auto-save <u>when not editing a page/post</u> <em>(saves 1 file request and ~6kb)</em>', 'better-speed-text');
}

function better_speed_features_comments() {
	$checked = "";
	if(better_speed_check_setting('comments')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-comments" name="better-speed-settings[better-speed-features-comments]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove support for leaving comments on posts</u> <em>(saves at least 1 file request and ~2kb)</em>', 'better-speed-text');
}

function better_speed_features_xmlrpc() {
	$checked = "";
	if(better_speed_check_setting('xmlrpc')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-xmlrpc" name="better-speed-settings[better-speed-features-xmlrpc]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove support for third-party application access <em>(such as mobile apps)</em>', 'better-speed-text');
}

function better_speed_features_generator() {
	$checked = "";
	if(better_speed_check_setting('generator')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-generator" name="better-speed-settings[better-speed-features-generator]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove the generator tag <em>(includes Wordpress version number)</em>', 'better-speed-text');
}

function better_speed_features_manifest() {
	$checked = "";
	if(better_speed_check_setting('manifest')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-manifest" name="better-speed-settings[better-speed-features-manifest]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove the Windows Live Writer manifest tag <em>(WLW was discontinued in Jan 2017)</em>', 'better-speed-text');
}

function better_speed_features_rsdlink() {
	$checked = "";
	if(better_speed_check_setting('rsdlink')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-rsdlink" name="better-speed-settings[better-speed-features-rsdlink]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove the Really Simple Discovery (RSD) tag <em>(this protocol never became popular)</em>', 'better-speed-text');
}

function better_speed_features_shortlink() {
	$checked = "";
	if(better_speed_check_setting('shortlink')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-shortlink" name="better-speed-settings[better-speed-features-shortlink]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove the Short Link tag <em>(search engines ignore this tag completely)</em>', 'better-speed-text');
}

function better_speed_features_rssfeeds() {
	$checked = "";
	if(better_speed_check_setting('rssfeeds')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-rssfeeds" name="better-speed-settings[better-speed-features-rssfeeds]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove the RSS feed links and disable the feeds <em>(will redirect to the page instead)</em>', 'better-speed-text');
}

function better_speed_features_restapi() {
	$checked = "";
	if(better_speed_check_setting('restapi')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-features-restapi" name="better-speed-settings[better-speed-features-restapi]" type="checkbox" value="YES"' . $checked . '> ' . __('Remove the REST API links and disable the endpoints <u>when not on admin pages</u>', 'better-speed-text');
}
//define output for settings section
function better_speed_section_instant() {
  echo '<p><a href="https://instant.page"><img src="' . plugins_url('instant.page.png', __FILE__) . '"></a></p>';
  echo '<p><a href="https://instant.page"><strong>instant.page</strong></a> ' . __('is a free and open source library that uses just-in-time preloading, meaning it preloads a page right before a user clicks on it. Pages are preloaded only when there\'s a good chance that a user will visit them, and only the HTML is preloaded, being respectful of your users\' and servers\' bandwidth and CPU. It uses passive event listeners so that your pages stay smooth and doesn\'t preload when the user has data saver enabled. It\'s less than 1kb and loads after everything else.', 'better-speed-text') . '</p>';
}

//defined output for settings
function better_speed_instant_page() {
	$checked = "";
	if(better_speed_check_other_setting('instant-page')) {
		$checked = " checked";
	}
  echo '<label><input id="better-speed-instant-page" name="better-speed-settings[better-speed-instant-page]" type="checkbox" value="YES"' . $checked . '> ' . __('Enable <strong>instant.page</strong> functionality', 'better-speed-text');
}


//add actions
if(is_admin()) {
  add_action('admin_menu','better_speed_menus');
  add_action('admin_init','better_speed_settings');
}

function better_speed_admin_scripts() {
	if($_GET["page"]==="better-speed-settings") {
	  wp_enqueue_script('jquery-ui-core');
	  wp_enqueue_script('jquery-ui-tabs');

		wp_enqueue_script('better-speed-main-js', plugins_url('main.js', __FILE__),array('jquery','jquery-ui-tabs'),false,true);

		wp_enqueue_style('jquery-ui-tabs-min-css', plugins_url('jquery-ui-tabs.min.css', __FILE__));
	}
}
add_action('admin_enqueue_scripts', 'better_speed_admin_scripts');

/*
--------------------- Add links to plugins page ---------------------
*/

//show settings link
function better_speed_links($links) {
	$links[] = sprintf('<a href="%s">%s</a>',admin_url('options-general.php?page=better-speed-settings'), __('Settings', 'better-speed-text'));
	return $links;
}

//add actions
if(is_admin()) {
  add_filter('plugin_action_links_'.plugin_basename(__FILE__),'better_speed_links');
}

/*
----------------------------- The End ------------------------------
*/
