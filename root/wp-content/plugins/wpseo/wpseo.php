<?php
/*
Plugin Name: wpSEO
Plugin URI: http://www.wpseo.org
Description: More SEO for WordPress! The wpSEO plugin helps you to optimize your blog for SEO purposes by eliminating issues with duplicate content and specifying meta tags and page titles for the different pages of your blog. You can also specify your meta tags and page titles manually. Commercial projects and blogs with ads have to acquire a license.
Author: Sergej M&uuml;ller
Version: 2.7.5
Author URI: http://wpcoder.de
*/

if (!function_exists('is_admin')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}
class wpSEO {
    var $basename;
    var $noindex;
    var $misc;
    var $groups;
    function wpSEO()
    {
        if (!class_exists('WPlize')) {
            require_once('inc/wplize.class.php');
        }
        $this->WPlize = new WPlize('wpseo_options');
        if (defined('DOING_AJAX')) {
            add_action('wp_ajax_set_toggle_status', array($this, 'set_toggle_status'));
        } else {
            $this->set_global_arrays();
            $this->basename = plugin_basename(__FILE__);
            if (is_admin()) {
                switch ($this->get_current_page()) {
                    case 'index':if ($this->get_option('misc_monitor')) {
                            add_action('wp_dashboard_setup', array($this, 'exe_dashboard_widget'));
                        }
                        break;
                    case 'home':$this->exe_load_language();
                        $this->set_language_arrays();
                        add_action('admin_head', array($this, 'show_plugin_head'));
                        if (isset($_GET['action']) && $_GET['action'] == 'export') {
                            add_action('init', array($this, 'get_export_file'));
                        }
                        break;
                    case 'plugins':$this->exe_load_language();
                        if ($this->is_min_wp('2.8')) {
                            add_filter('plugin_row_meta', array($this, 'set_plugin_meta'), 10, 2);
                        } else {
                            add_filter('plugin_action_links', array($this, 'set_plugin_actions'), 10, 2);
                        }

                        add_action('admin_notices', array($this, 'show_admin_notices'));
                        add_action('after_plugin_row', array($this, 'show_plugin_row'));
                        add_action('activate_' . $this->basename, array($this, 'exe_activate_plugin'));
                        add_action('deactivate_' . $this->basename, array($this, 'exe_deactivate_plugin'));
                        break;
                    case 'post':case 'page':case 'post-new':case 'page-new':case 'post-edit':case 'page-edit':if ($this->get_option('key_manually') || $this->get_option('desc_manually') || $this->get_option('title_manually') || $this->get_option('noindex_manually')) {
                            $this->exe_load_language();
                            add_action('admin_menu', array($this, 'set_edit_page'));
                            add_action('save_post', array($this, 'set_edit_fields'));
                        }
                        break;
                    case 'media':case 'media-new':case 'media-upload':if ($this->get_option('misc_attachment')) {
                            if ($this->is_min_wp('2.5')) {
                                add_filter('attachment_fields_to_save', array($this, 'set_attachment_file'));
                            } else {
                                add_filter('wp_handle_upload', array($this, 'set_attachment_file'));
                            }
                        }
                        break;
                }
                add_action('admin_menu', array($this, 'set_admin_page'));
            } else {
                add_action('wpseo_daily_cronjob', array($this, 'exe_daily_cronjob'));
                add_action('template_redirect', array($this, 'exe_redirect_request'), 1);
                if ($this->get_option('misc_feed')) {
                    add_action('rss_head', array($this, 'show_feed_noindex'));
                    add_action('rss2_head', array($this, 'show_feed_noindex'));
                }
                if ($this->get_option('misc_section')) {
                    add_filter('the_content', array($this, 'get_selected_data'));
                }
                if (function_exists('add_shortcode')) {
                    add_shortcode('wpseo', array($this, 'get_short_code'));
                }
            }
        }
    }
    function exe_load_language()
    {
        if (get_locale() != 'de_DE') {
            return;
        }
        if (!defined('WP_PLUGIN_DIR')) {
            load_plugin_textdomain('wpseo', str_replace(ABSPATH, '', dirname(__FILE__)) . '/lang');
        } else {
            load_plugin_textdomain('wpseo', false, dirname($this->basename) . '/lang');
        }
    }
    function exe_dashboard_widget()
    {
        if (function_exists('wp_add_dashboard_widget')) {
            wp_add_dashboard_widget('wpseo_dashboard_widget', 'SEO Monitor', array($this, 'show_dashboard_widget'));
        }
    }
    function exe_redirect_request()
    {
        if (is_feed() || is_trackback() || $this->shame_on_me() === false) {
            return;
        }
        if (is_singular() && $url = $this->get_user_field('set-redirect')) {
            wp_redirect($url, 301);
        } else {
            ob_start(array($this, 'exe_generate_header'));
            ob_end_clean();
            ob_start(array($this, 'exe_modify_content'));
        }
    }
    function exe_generate_header()
    {
        $GLOBALS['wpSEO']->cache['header'] = str_replace(array('%wpseo_comment%', '%wpseo_robots%', '%wpseo_desc%', '%wpseo_keywords%', '%wpseo_title%'), array($this->get_meta_comment(), $this->get_meta_robots(), $this->get_meta_description(), $this->get_meta_keywords(), $this->get_meta_title()), "\n\n%wpseo_comment%" . $this->get_option('misc_order'));
    }
    function exe_modify_content($input)
    {
        $tmp = array();
        $clean = array('meta' => '', 'title' => '');
        $output = '';
        if (!$this->get_option('speed_nocheck')) {
            if ($this->get_option('noindex_enable') || $this->get_option('misc_noodp') || $this->get_option('misc_noarchive')) {
                $tmp[] = 'robots';
            }
            if ($this->get_option('desc_enable')) {
                $tmp[] = 'description';
            }
            if ($this->get_option('key_enable')) {
                $tmp[] = 'keywords';
            }
            if ($this->get_option('title_enable')) {
                if (strpos($input, '<title>') !== false) {
                    $clean['title'] = '<title>(?:(?:.|\s)*?)<\/title>';
                }
            }
            if ($tmp && strpos($input, '<meta') !== false) {
                $clean['meta'] = '<meta(.*?)[\'"](' . implode('|', $tmp) . ')[\'"](.*?)>';
            }
            if ($clean['meta'] || $clean['title']) {
                $input = preg_replace('/(' . implode('|', $clean) . '|\\n\\r)/', '', $input);
            }
        }
        $output = preg_replace('#<head(.*?)>(.*?)<meta(.*?)charset=(.*?)>#si', '<head$1>$2<meta$3charset=$4>' . $GLOBALS['wpSEO']->cache['header'], $input, 1);
        if (strcmp($output, $input) == 0) {
            $output = preg_replace('/<head(.*?)>/', '<head$1>' . $GLOBALS['wpSEO']->cache['header'], $input, 1);
        }
        return $output;
    }
    function exe_activate_plugin()
    {
        $this->set_default_options();
        $this->exe_options_migration();
        if (function_exists('wp_schedule_event')) {
            if (!wp_next_scheduled('wpseo_daily_cronjob')) {
                wp_schedule_event(time(), 'daily', 'wpseo_daily_cronjob');
            }
        }
    }
    function exe_deactivate_plugin()
    {
        if (function_exists('wp_schedule_event')) {
            if (wp_next_scheduled('wpseo_daily_cronjob')) {
                wp_clear_scheduled_hook('wpseo_daily_cronjob');
            }
        }
    }
    function exe_options_migration()
    {
        if (get_option('wp_seo_title_channel_home')) {
            $results = $GLOBALS['wpdb']->get_results("SELECT `option_name`, `option_value` FROM `" . $GLOBALS['wpdb']->options . "` WHERE `option_name` LIKE 'wp_seo_%'", ARRAY_A);
            if ($results) {
                $options = array();
                foreach($results as $result) {
                    $options[$result['option_name']] = $result['option_value'];
                }
                $this->WPlize->update_option($options);
                if (get_option('wp_seo_title_channel_home') == $this->get_option('title_channel_home')) {
                    $GLOBALS['wpdb']->query("DELETE FROM `" . $GLOBALS['wpdb']->options . "` WHERE option_name LIKE 'wp_seo_%'");
                }
            }
        }
    }

    function exe_daily_cronjob()
    {
        if ($this->is_min_wp('2.7') && $this->get_option('misc_monitor') && ($this->get_option('misc_monitor_timestamp') + (60 * 60) < time())) {
            $this->WPlize->update_option(array('wp_seo_google_pagerank' => $this->get_google_pagerank(true), 'wp_seo_alexa_rank' => $this->get_alexa_rank(true), 'wp_seo_yahoo_backlinks' => $this->get_yahoo_backlinks(true), 'wp_seo_feedburner_subscribers' => $this->get_feedburner_subscribers(true), 'wp_seo_misc_monitor_timestamp' => time()));
        }
        if (md5($this->WPlize->get_option(base64_decode('d3Bfc2VvX2ludGVybl9rZXk=')) . 'sergej+sweta=love' . $this->WPlize->get_option(base64_decode('d3Bfc2VvX2ludGVybl9ieQ=='))) != $this->WPlize->get_option(base64_decode('d3Bfc2VvX2ludGVybl9oYXNo'))) {
            $this->WPlize->update_option(array(base64_decode('d3Bfc2VvX2ludGVybl9rZXk=') => '', base64_decode('d3Bfc2VvX2ludGVybl9ieQ==') => '', base64_decode('d3Bfc2VvX2ludGVybl9oYXNo') => ''));
        }
    }
    function get_option($option)
    {
        return $this->WPlize->get_option('wp_seo_' . $option);
    }
    function get_google_pagerank($refresh = false)
    {
        $current = $this->get_option('google_pagerank');
        if (!$refresh) {
            return $current;
        }
        $response = $this->get_http_request(sprintf('http://toolbarqueries.google.com/search?client=navclient-auto&ch=%s&features=Rank&q=info:%s', $this->CheckHash($this->HashURL(get_bloginfo('url'))), get_bloginfo('url')));
        if ($response && preg_match('/Rank_\d{1,2}:\d{1,2}:(\d{1,2})/', $response, $matches) && $matches[1]) {
            return $matches[1];
        }
        return $current;
    }
    function get_alexa_rank($refresh = false)
    {
        $current = $this->get_option('alexa_rank');
        if (!$refresh) {
            return $current;
        }
        $response = $this->get_http_request(sprintf('http://data.alexa.com/data?cli=10&dat=snbamz&url=%s', urlencode(get_bloginfo('url'))));
        if ($response && preg_match('/" TEXT="((\d|\,)+?)"/', $response, $matches) && $matches[1]) {
            return $matches[1];
        }
        return $current;
    }
    function get_yahoo_backlinks($refresh = false)
    {
        $current = $this->get_option('yahoo_backlinks');
        if (!$refresh) {
            return $current;
        }
        $response = $this->get_http_request(sprintf('http://siteexplorer.search.yahoo.com/search?p=%s&bwm=i', urlencode(get_bloginfo('url'))));
        if ($response && preg_match('/Inlinks \(([0-9,]+)\)/', $response, $matches) && $matches[1]) {
            return str_replace(',', '', $matches[1]);
        }
        return $current;
    }
    function get_feedburner_subscribers($refresh = false)
    {
        $current = $this->get_option('feedburner_subscribers');
        if (!$refresh) {
            return $current;
        }
        if (!function_exists('curl_init') || !get_option('feedburner_settings')) {
            return $current;
        }
        $settings = get_option('feedburner_settings');
        if (!$settings['feedburner_url']) {
            return $current;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, 'https://feedburner.google.com/api/awareness/1.0/GetFeedData?uri=' . $settings['feedburner_url']);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response && preg_match('/circulation="([0-9]+)"/', $response, $matches) && $matches[1]) {
            return $matches[1];
        }
        return $current;
    }
    function get_plugin_tld()
    {
        return (get_locale() == 'de_DE' ? 'de' : 'org');
    }
    function get_plugin_domain()
    {
        return sprintf('http://www.wpseo.%s', $this->get_plugin_tld());
    }
    function get_short_code($attr, $content)
    {
        return $content;
    }
    function get_selected_data($data = '')
    {
        return "\n<!-- google_ad_section_start -->\n" . $data . "\n<!-- google_ad_section_end -->\n";
    }
    function get_category_data()
    {
        $categs = get_the_category();
        if (empty($categs)) {
            return '';
        }
        foreach ($categs as $v) {
            if (!isset($data)) {
                $data = $v->cat_name;
            } else {
                $data .= ', ' . $v->cat_name;
            }
        }
        return $data;
    }
    function get_post_titles()
    {
        if (!$posts = $GLOBALS['posts']) {
            return '';
        }
        foreach ($posts as $v) {
            if (!isset($data)) {
                $data = $v->post_title;
            } else {
                $data .= ', ' . $v->post_title;
            }
        }
        return $data;
    }
    function get_composed_data($data, $args)
    {
        if (empty($data)) {
            return;
        }
        $data = apply_filters('the_title', $data);
        $data = $this->get_htmlentities_decode($data);
        if ($args['labeling'] || $args['xhtml']) {
            preg_match_all(sprintf("#[<\[](?:%1\$s%2\$s%3\$s)[>\]](.*?)[<\[]/(?:%4\$s%2\$s%3\$s)[>\]]#i", ($args['labeling'] ? 'span class="wpseo_keyword"|wpseo' : ''), ($args['labeling'] && $args['xhtml'] ? '|' : ''), ($args['xhtml'] ? 'em|strong' : ''), ($args['labeling'] ? 'span|wpseo' : '')), preg_quote($data), $matches, PREG_PATTERN_ORDER);
            if (!empty($matches) && !empty($matches[1])) {
                $labels = $matches[1];
                $labels = $this->get_strip_tags($labels);
                $labels = array_map(array($this, 'get_character_filter'), $labels);
                if (count($labels) >= $args['counter']) {
                    return $this->get_converted_data($labels, $args);
                }
            }
        }
        $data = $this->get_strip_tags($data);
        if ($args['type'] != 'tags') {
            $array = preg_split('/[\s"]+/', $data, - 1, PREG_SPLIT_NO_EMPTY);
        } else {
            $array = explode(', ', $data);
        }
        if ($args['character']) {
            $array = array_map(array($this, 'get_character_filter'), $array);
        }
        if ($args['length']) {
            $GLOBALS['wpSEO']->cache['length'] = $args['length'];
            $array = array_filter($array, array($this, 'get_length_filter'));
        }
        if ($args['substantive'] && $args['type'] != 'tags') {
            $array = array_filter($array, array($this, 'get_substantive_filter'));
        }
        if ($args['relevance']) {
            $array = $this->get_counted_data($array);
        }
        if (isset($labels) && !empty($labels)) {
            $array = array_merge($labels, $array);
        }
        if ($args['unique']) {
            $array = array_unique($array);
        }
        if ($args['character'] && $blacklist = $this->get_htmlentities_decode(stripslashes($this->get_option('key_blacklist')))) {
            $array = array_merge(array_diff($array, preg_split('/\s/', $blacklist, - 1, PREG_SPLIT_NO_EMPTY)));
        }
        if ($args['complete'] && count($array) < $args['counter'] && $default = stripslashes($this->get_option('key_default'))) {
            $array = array_merge($array, preg_split('/[\s\.\+\?\(\)\[\]\/\\,;&"\':!%~#_]+/', $default, - 1, PREG_SPLIT_NO_EMPTY));
            if ($args['unique']) {
                $array = array_unique($array);
            }
        }
        return $this->get_converted_data($array, $args);
    }
    function get_converted_data($array, $args)
    {
        $end = $args['counter'];
        if ($args['tender']) {
            $max = count($array);
            $signs = array('.', '?', '!');
            for ($x = $end; $x < $max; $x ++) {
                if (in_array(substr($array[$x], - 1), $signs)) {
                    $end = $x + 1;
                    break;
                }
            }
        }
        $array = array_slice($array, 0, $end);
        $data = implode($args['separator'], $array);
        return $data;
    }
    function get_counted_data($array)
    {
        $ret_array = array();
        foreach($array as $value) {
            foreach($ret_array as $key2 => $value2) {
                if (strtolower($key2) == strtolower($value)) {
                    $ret_array[$key2] ++;
                    continue 2;
                }
            }
            $ret_array[$value] = 1;
        }
        arsort($ret_array);
        $ret_array = array_keys($ret_array);
        return $ret_array;
    }
    function get_substantive_filter($data)
    {
        if (!empty($data) && strtolower($data) != $data) {
            return $data;
        }
    }
    function get_length_filter($data)
    {
        if (!empty($data) && strlen($data) > $GLOBALS['wpSEO']->cache['length']) {
            return $data;
        }
    }
    function get_character_filter($data)
    {
        return utf8_encode(preg_replace('/[\.\+\?\(\)\[\]\/\\,:!%~#_"»«„“”]/iu', '', utf8_decode($data)));
    }
    function get_strip_tags($data)
    {
        if (is_array($data)) {
            return array_map(array($this, 'get_strip_tags'), $data);
        } else {
            if (isset($GLOBALS['g_execphp_manager'])) {
                $data = str_replace('<?php', '<\?php', $data);
            }
            if (strpos($data, '[') !== false && strpos($data, ']') !== false) {
                $data = strtr($data, '[]', '<>');
            }
            if (strpos($data, '{{') !== false && strpos($data, '}}') !== false) {
                $data = preg_replace('/{{.+?}}/', '', $data);
            }
            return strip_tags($data);
        }
    }
    function get_htmlentities_decode($data)
    {
        if (version_compare(phpversion(), '5.0.0', '>=')) {
            return html_entity_decode($data, ENT_QUOTES, get_bloginfo('charset'));
        } else {
            if ($this->is_utf8()) {
                return $this->get_entity_decode_php4($data);
            } else {
                return html_entity_decode($data, ENT_QUOTES, get_bloginfo('charset'));
            }
        }
    }
    function get_htmlentities_encode($data, $stripslashes = false, $encode = false)
    {
        if (!$data) {
            return '';
        }
        $entity_decoded = $this->get_htmlentities_decode($data);
        if ($this->is_utf8() && $encode === false) {
            $return = $entity_decoded;
        } else {
            $return = htmlentities($entity_decoded, ENT_QUOTES, get_bloginfo('charset'));
        }
        return ($stripslashes === false) ? $return : stripslashes($return);
    }
    function get_utf8_code_php4($num)
    {
        if ($num <= 0x7F) {
            return chr($num);
        } elseif ($num <= 0x7FF) {
            return chr(($num >> 0x06) + 0xC0) . chr(($num &0x3F) + 128);
        } elseif ($num <= 0xFFFF) {
            return chr(($num >> 0x0C) + 0xE0) . chr((($num >> 0x06) &0x3F) + 0x80) . chr(($num &0x3F) + 0x80);
        } elseif ($num <= 0x1FFFFF) {
            return chr(($num >> 0x12) + 0xF0) . chr((($num >> 0x0C) &0x3F) + 0x80) . chr((($num >> 0x06) &0x3F) + 0x80) . chr(($num &0x3F) + 0x80);
        }
        return ' ';
    }
    function get_entity_decode_php4($data)
    {
        $table = array ('&Aacute;' => chr(195) . chr(129), '&aacute;' => chr(195) . chr(161), '&Acirc;' => chr(195) . chr(130), '&acirc;' => chr(195) . chr(162), '&acute;' => chr(194) . chr(180), '&AElig;' => chr(195) . chr(134), '&aelig;' => chr(195) . chr(166), '&Agrave;' => chr(195) . chr(128), '&agrave;' => chr(195) . chr(160), '&alefsym;' => chr(226) . chr(132) . chr(181), '&Alpha;' => chr(206) . chr(145), '&alpha;' => chr(206) . chr(177), '&amp;' => chr(38), '&and;' => chr(226) . chr(136) . chr(167), '&ang;' => chr(226) . chr(136) . chr(160), '&Aring;' => chr(195) . chr(133), '&aring;' => chr(195) . chr(165), '&asymp;' => chr(226) . chr(137) . chr(136), '&Atilde;' => chr(195) . chr(131), '&atilde;' => chr(195) . chr(163), '&Auml;' => chr(195) . chr(132), '&auml;' => chr(195) . chr(164), '&bdquo;' => chr(226) . chr(128) . chr(158), '&Beta;' => chr(206) . chr(146), '&beta;' => chr(206) . chr(178), '&brvbar;' => chr(194) . chr(166), '&bull;' => chr(226) . chr(128) . chr(162), '&cap;' => chr(226) . chr(136) . chr(169), '&Ccedil;' => chr(195) . chr(135), '&ccedil;' => chr(195) . chr(167), '&cedil;' => chr(194) . chr(184), '&cent;' => chr(194) . chr(162), '&Chi;' => chr(206) . chr(167), '&chi;' => chr(207) . chr(135), '&circ;' => chr(203) . chr(134), '&clubs;' => chr(226) . chr(153) . chr(163), '&cong;' => chr(226) . chr(137) . chr(133), '&copy;' => chr(194) . chr(169), '&crarr;' => chr(226) . chr(134) . chr(181), '&cup;' => chr(226) . chr(136) . chr(170), '&curren;' => chr(194) . chr(164), '&dagger;' => chr(226) . chr(128) . chr(160), '&Dagger;' => chr(226) . chr(128) . chr(161), '&darr;' => chr(226) . chr(134) . chr(147), '&dArr;' => chr(226) . chr(135) . chr(147), '&deg;' => chr(194) . chr(176), '&Delta;' => chr(206) . chr(148), '&delta;' => chr(206) . chr(180), '&diams;' => chr(226) . chr(153) . chr(166), '&divide;' => chr(195) . chr(183), '&Eacute;' => chr(195) . chr(137), '&eacute;' => chr(195) . chr(169), '&Ecirc;' => chr(195) . chr(138), '&ecirc;' => chr(195) . chr(170), '&Egrave;' => chr(195) . chr(136), '&egrave;' => chr(195) . chr(168), '&empty;' => chr(226) . chr(136) . chr(133), '&emsp;' => chr(226) . chr(128) . chr(131), '&ensp;' => chr(226) . chr(128) . chr(130), '&Epsilon;' => chr(206) . chr(149), '&epsilon;' => chr(206) . chr(181), '&equiv;' => chr(226) . chr(137) . chr(161), '&Eta;' => chr(206) . chr(151), '&eta;' => chr(206) . chr(183), '&ETH;' => chr(195) . chr(144), '&eth;' => chr(195) . chr(176), '&Euml;' => chr(195) . chr(139), '&euml;' => chr(195) . chr(171), '&euro;' => chr(226) . chr(130) . chr(172), '&exist;' => chr(226) . chr(136) . chr(131), '&fnof;' => chr(198) . chr(146), '&forall;' => chr(226) . chr(136) . chr(128), '&frac12;' => chr(194) . chr(189), '&frac14;' => chr(194) . chr(188), '&frac34;' => chr(194) . chr(190), '&frasl;' => chr(226) . chr(129) . chr(132), '&Gamma;' => chr(206) . chr(147), '&gamma;' => chr(206) . chr(179), '&ge;' => chr(226) . chr(137) . chr(165), '&harr;' => chr(226) . chr(134) . chr(148), '&hArr;' => chr(226) . chr(135) . chr(148), '&hearts;' => chr(226) . chr(153) . chr(165), '&hellip;' => chr(226) . chr(128) . chr(166), '&Iacute;' => chr(195) . chr(141), '&iacute;' => chr(195) . chr(173), '&Icirc;' => chr(195) . chr(142), '&icirc;' => chr(195) . chr(174), '&iexcl;' => chr(194) . chr(161), '&Igrave;' => chr(195) . chr(140), '&igrave;' => chr(195) . chr(172), '&image;' => chr(226) . chr(132) . chr(145), '&infin;' => chr(226) . chr(136) . chr(158), '&int;' => chr(226) . chr(136) . chr(171), '&Iota;' => chr(206) . chr(153), '&iota;' => chr(206) . chr(185), '&iquest;' => chr(194) . chr(191), '&isin;' => chr(226) . chr(136) . chr(136), '&Iuml;' => chr(195) . chr(143), '&iuml;' => chr(195) . chr(175), '&Kappa;' => chr(206) . chr(154), '&kappa;' => chr(206) . chr(186), '&Lambda;' => chr(206) . chr(155), '&lambda;' => chr(206) . chr(187), '&lang;' => chr(226) . chr(140) . chr(169), '&laquo;' => chr(194) . chr(171), '&larr;' => chr(226) . chr(134) . chr(144), '&lArr;' => chr(226) . chr(135) . chr(144), '&lceil;' => chr(226) . chr(140) . chr(136), '&ldquo;' => chr(226) . chr(128) . chr(156), '&le;' => chr(226) . chr(137) . chr(164), '&lfloor;' => chr(226) . chr(140) . chr(138), '&lowast;' => chr(226) . chr(136) . chr(151), '&loz;' => chr(226) . chr(151) . chr(138), '&lrm;' => chr(226) . chr(128) . chr(142), '&lsaquo;' => chr(226) . chr(128) . chr(185), '&lsquo;' => chr(226) . chr(128) . chr(152), '&macr;' => chr(194) . chr(175), '&mdash;' => chr(226) . chr(128) . chr(148), '&micro;' => chr(194) . chr(181), '&middot;' => chr(194) . chr(183), '&minus;' => chr(226) . chr(136) . chr(146), '&Mu;' => chr(206) . chr(156), '&mu;' => chr(206) . chr(188), '&nabla;' => chr(226) . chr(136) . chr(135), '&nbsp;' => chr(194) . chr(160), '&ndash;' => chr(226) . chr(128) . chr(147), '&ne;' => chr(226) . chr(137) . chr(160), '&ni;' => chr(226) . chr(136) . chr(139), '&not;' => chr(194) . chr(172), '&notin;' => chr(226) . chr(136) . chr(137), '&nsub;' => chr(226) . chr(138) . chr(132), '&Ntilde;' => chr(195) . chr(145), '&ntilde;' => chr(195) . chr(177), '&Nu;' => chr(206) . chr(157), '&nu;' => chr(206) . chr(189), '&Oacute;' => chr(195) . chr(147), '&oacute;' => chr(195) . chr(179), '&Ocirc;' => chr(195) . chr(148), '&ocirc;' => chr(195) . chr(180), '&OElig;' => chr(197) . chr(146), '&oelig;' => chr(197) . chr(147), '&Ograve;' => chr(195) . chr(146), '&ograve;' => chr(195) . chr(178), '&oline;' => chr(226) . chr(128) . chr(190), '&Omega;' => chr(206) . chr(169), '&omega;' => chr(207) . chr(137), '&Omicron;' => chr(206) . chr(159), '&omicron;' => chr(206) . chr(191), '&oplus;' => chr(226) . chr(138) . chr(149), '&or;' => chr(226) . chr(136) . chr(168), '&ordf;' => chr(194) . chr(170), '&ordm;' => chr(194) . chr(186), '&Oslash;' => chr(195) . chr(152), '&oslash;' => chr(195) . chr(184), '&Otilde;' => chr(195) . chr(149), '&otilde;' => chr(195) . chr(181), '&otimes;' => chr(226) . chr(138) . chr(151), '&Ouml;' => chr(195) . chr(150), '&ouml;' => chr(195) . chr(182), '&para;' => chr(194) . chr(182), '&part;' => chr(226) . chr(136) . chr(130), '&permil;' => chr(226) . chr(128) . chr(176), '&perp;' => chr(226) . chr(138) . chr(165), '&Phi;' => chr(206) . chr(166), '&phi;' => chr(207) . chr(134), '&Pi;' => chr(206) . chr(160), '&pi;' => chr(207) . chr(128), '&piv;' => chr(207) . chr(150), '&plusmn;' => chr(194) . chr(177), '&pound;' => chr(194) . chr(163), '&prime;' => chr(226) . chr(128) . chr(178), '&Prime;' => chr(226) . chr(128) . chr(179), '&prod;' => chr(226) . chr(136) . chr(143), '&prop;' => chr(226) . chr(136) . chr(157), '&Psi;' => chr(206) . chr(168), '&psi;' => chr(207) . chr(136), '&radic;' => chr(226) . chr(136) . chr(154), '&rang;' => chr(226) . chr(140) . chr(170), '&raquo;' => chr(194) . chr(187), '&rarr;' => chr(226) . chr(134) . chr(146), '&rArr;' => chr(226) . chr(135) . chr(146), '&rceil;' => chr(226) . chr(140) . chr(137), '&rdquo;' => chr(226) . chr(128) . chr(157), '&real;' => chr(226) . chr(132) . chr(156), '&reg;' => chr(194) . chr(174), '&rfloor;' => chr(226) . chr(140) . chr(139), '&Rho;' => chr(206) . chr(161), '&rho;' => chr(207) . chr(129), '&rlm;' => chr(226) . chr(128) . chr(143), '&rsaquo;' => chr(226) . chr(128) . chr(186), '&rsquo;' => chr(226) . chr(128) . chr(153), '&sbquo;' => chr(226) . chr(128) . chr(154), '&Scaron;' => chr(197) . chr(160), '&scaron;' => chr(197) . chr(161), '&sdot;' => chr(226) . chr(139) . chr(133), '&sect;' => chr(194) . chr(167), '&shy;' => chr(194) . chr(173), '&Sigma;' => chr(206) . chr(163), '&sigma;' => chr(207) . chr(131), '&sigmaf;' => chr(207) . chr(130), '&sim;' => chr(226) . chr(136) . chr(188), '&spades;' => chr(226) . chr(153) . chr(160), '&sub;' => chr(226) . chr(138) . chr(130), '&sube;' => chr(226) . chr(138) . chr(134), '&sum;' => chr(226) . chr(136) . chr(145), '&sup1;' => chr(194) . chr(185), '&sup2;' => chr(194) . chr(178), '&sup3;' => chr(194) . chr(179), '&sup;' => chr(226) . chr(138) . chr(131), '&supe;' => chr(226) . chr(138) . chr(135), '&szlig;' => chr(195) . chr(159), '&Tau;' => chr(206) . chr(164), '&tau;' => chr(207) . chr(132), '&there4;' => chr(226) . chr(136) . chr(180), '&Theta;' => chr(206) . chr(152), '&theta;' => chr(206) . chr(184), '&thetasym;' => chr(207) . chr(145), '&thinsp;' => chr(226) . chr(128) . chr(137), '&THORN;' => chr(195) . chr(158), '&thorn;' => chr(195) . chr(190), '&tilde;' => chr(203) . chr(156), '&times;' => chr(195) . chr(151), '&trade;' => chr(226) . chr(132) . chr(162), '&Uacute;' => chr(195) . chr(154), '&uacute;' => chr(195) . chr(186), '&uarr;' => chr(226) . chr(134) . chr(145), '&uArr;' => chr(226) . chr(135) . chr(145), '&Ucirc;' => chr(195) . chr(155), '&ucirc;' => chr(195) . chr(187), '&Ugrave;' => chr(195) . chr(153), '&ugrave;' => chr(195) . chr(185), '&uml;' => chr(194) . chr(168), '&upsih;' => chr(207) . chr(146), '&Upsilon;' => chr(206) . chr(165), '&upsilon;' => chr(207) . chr(133), '&Uuml;' => chr(195) . chr(156), '&uuml;' => chr(195) . chr(188), '&weierp;' => chr(226) . chr(132) . chr(152), '&Xi;' => chr(206) . chr(158), '&xi;' => chr(206) . chr(190), '&Yacute;' => chr(195) . chr(157), '&yacute;' => chr(195) . chr(189), '&yen;' => chr(194) . chr(165), '&yuml;' => chr(195) . chr(191), '&Yuml;' => chr(197) . chr(184), '&Zeta;' => chr(206) . chr(150), '&zeta;' => chr(206) . chr(182), '&zwj;' => chr(226) . chr(128) . chr(141), '&zwnj;' => chr(226) . chr(128) . chr(140), '&gt;' => '>', '&lt;' => '<');
        $return = strtr($data, $table);
        $return = preg_replace('~&#x([0-9a-f]+);~ei', '\$this->get_utf8_code_php4(hexdec("\\1"))', $return);
        $return = preg_replace('~&#([0-9]+);~e', '\$this->get_utf8_code_php4(\\1)', $return);
        return $return;
    }
    function StrToNum($Str, $Check, $Magic)
    {
        $Int32Unit = 4294967296;
        $length = strlen($Str);
        for ($i = 0; $i < $length; $i++) {
            $Check *= $Magic;
            if ($Check >= $Int32Unit) {
                $Check = ($Check - $Int32Unit * (int) ($Check / $Int32Unit));
                $Check = ($Check < - 2147483648) ? ($Check + $Int32Unit) : $Check;
            }
            $Check += ord($Str {
                    $i}
                );
        }
        return $Check;
    }
    function HashURL($String)
    {
        $Check1 = $this->StrToNum($String, 0x1505, 0x21);
        $Check2 = $this->StrToNum($String, 0, 0x1003F);
        $Check1 >>= 2;
        $Check1 = (($Check1 >> 4) &0x3FFFFC0) | ($Check1 &0x3F);
        $Check1 = (($Check1 >> 4) &0x3FFC00) | ($Check1 &0x3FF);
        $Check1 = (($Check1 >> 4) &0x3C000) | ($Check1 &0x3FFF);
        $T1 = (((($Check1 &0x3C0) << 4) | ($Check1 &0x3C)) <<2) | ($Check2 &0xF0F);
        $T2 = (((($Check1 &0xFFFFC000) << 4) | ($Check1 &0x3C00)) << 0xA) | ($Check2 &0xF0F0000);
        return ($T1 | $T2);
    }
    function CheckHash($Hashnum)
    {
        $CheckByte = 0;
        $Flag = 0;
        $HashStr = sprintf('%u', $Hashnum) ;
        $length = strlen($HashStr);
        for ($i = $length - 1;$i >= 0;$i --) {
            $Re = $HashStr {
                $i} ;
            if (1 === ($Flag % 2)) {
                $Re += $Re;
                $Re = (int)($Re / 10) + ($Re % 10);
            }
            $CheckByte += $Re;
            $Flag ++;
        }
        $CheckByte %= 10;
        if (0 !== $CheckByte) {
            $CheckByte = 10 - $CheckByte;
            if (1 === ($Flag % 2)) {
                if (1 === ($CheckByte % 2)) {
                    $CheckByte += 9;
                }
                $CheckByte >>= 1;
            }
        }
        return '7' . $CheckByte . $HashStr;
    }
    function get_export_file()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "\n<options>\n";
        $options = get_option($this->WPlize->multi_option);
        if ($options) {
            foreach ($options as $key => $value) {
                $name = $key;
                $value = stripslashes($value);
                if (strpos($name, 'wp_seo_intern') !== false) {
                    continue;
                }
                if (preg_match('/^[0-9]*$/', $value)) {
                    $xml .= "<" . $name . ">" . $value . "</" . $name . ">\n";
                } else {
                    $xml .= "<" . $name . "><![CDATA[" . $value . "]]></" . $name . ">\n";
                }
            }
            $xml .= '</options>';
        }
        $this->set_download_header('wpSEO.' . date('Y-m-d') . '.xml', strlen($xml), 'text/xml');
        echo $xml;
        exit;
    }
    function get_security_scan()
    {
        if (function_exists('current_user_can') && (current_user_can('manage_options') === false || current_user_can('edit_plugins') === false)) {
            wp_die('You do not have sufficient permissions to edit the plugins for this blog.');
        }
    }
    function get_the_tags()
    {
        $output = array();
        if ($posts = $GLOBALS['posts']) {
            foreach ($posts as $post) {
                if ($tags = get_the_tags($post->ID)) {
                    foreach ($tags as $tag) {
                        $output[] = $tag->name;
                    }
                }
            }
        }
        if (empty($output)) {
            return;
        }
        return implode(', ', array_unique($output));
    }
    function get_http_request($url)
    {
        $output = false;
        if (file_exists(ABSPATH . 'wp-includes/class-snoopy.php')) {
            require_once(ABSPATH . 'wp-includes/class-snoopy.php');
            $s = new Snoopy();
            $s->fetch($url);
            if ($s->status == 200) {
                $output = $s->results;
            }
        }
        if (!$output && function_exists('wp_remote_fopen')) {
            $output = wp_remote_fopen($url);
        }
        if (!$output && function_exists('fsockopen')) {
            $parsed_url = parse_url($url);
            $http_request = 'GET ' . $parsed_url['path'] . ($parsed_url['query'] ? '?' . $parsed_url['query'] : '') . " HTTP/1.0\r\n";
            $http_request .= "Host: " . $parsed_url['host'] . "\r\n";
            $http_request .= 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option('blog_charset') . "\r\n";
            $http_request .= "Connection: Close\r\n\r\n";
            $response = '';
            if (false != ($fs = fsockopen($parsed_url['host'], 80, $errno, $errstr, 10))) {
                fwrite($fs, $http_request);
                while (!feof($fs))$response .= fgets($fs, 1160);
                fclose($fs);
                $response = explode("\r\n\r\n", $response, 2);
                $output = $response[1];
            }
        }
        return $output;
    }
    function get_meta_comment()
    {
        if ($this->get_crypt_data($this->WPlize->get_option(base64_decode('d3Bfc2VvX2ludGVybl9ieQ==')), 'integer') === - 1) {
            return '';
        }

    }
    function get_meta_canonical()
    {
        if (is_404() || is_search() || is_attachment() || is_tag()) {
            return;
        }
        $canonical = '';
        if ($this->is_home() || $this->is_front_page()) {
            $canonical = trailingslashit(get_option('home'));
        } else if (is_paged()) {
            $canonical = get_pagenum_link(get_query_var('paged'));
        } else if (is_singular()) {
            $canonical = get_permalink(get_query_var('p'));
        } else if (is_category()) {
            $canonical = get_category_link(get_query_var('cat'));
        } else if (is_day()) {
            $canonical = get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day'));
        } else if (is_month()) {
            $canonical = get_month_link(get_query_var('year'), get_query_var('monthnum'));
        } else if (is_year()) {
            $canonical = get_year_link(get_query_var('year'));
        } else if (is_author()) {
            if ($author = get_userdata(get_query_var('author'))) {
                $canonical = get_author_link(false, $author->ID, $author->user_nicename);
            }
        }
        if (empty($canonical)) {
            return;
        }
        return sprintf('<link rel="canonical" href="%s" />%s', $canonical, "\n");
    }
    function get_meta_robots()
    {
        if (!$this->get_option('noindex_enable') && !$this->get_option('misc_noodp') && !$this->get_option('misc_noarchive')) {
            return;
        }
        $return = '';
        $robots = '';
        $canonical = '';
        if (!$robots = $this->get_custom_value('robots')) {
            if ($this->get_option('noindex_enable')) {
                if ($this->is_home()) {
                    $area = 'home';
                } else if ($this->is_single()) {
                    $area = 'single';
                } else if ($this->is_page()) {
                    $area = 'page';
                } else if (is_category()) {
                    $area = 'category';
                } else if (is_search()) {
                    $area = 'search';
                } else if ($this->is_archive()) {
                    $area = 'archive';
                } else if (is_tag()) {
                    $area = 'tagging';
                } else if (is_404()) {
                    $area = '404';
                } else if (is_attachment()) {
                    $area = 'attachment';
                } else {
                    $area = 'home';
                }
                $option = $this->get_option('noindex_' . $area);
                if (is_singular() && get_option('page_comments') && get_query_var('cpage') >= 1 && $option <= 2) {
                    $robots = $this->noindex[$option + 3];
                } else if (!$this->get_option('noindex_hidden') || ($this->get_option('noindex_hidden') && $option >= 2)) {
                    $robots = $this->noindex[$option];
                }
                if ($this->get_option('noindex_canonical') && $option <= 2) {
                    $return = $this->get_meta_canonical();
                }
            }
            if ($this->get_option('misc_noodp')) {
                if ($robots) {
                    $robots .= ', ';
                }
                $robots .= 'noodp';
            }
            if ($this->get_option('misc_noarchive')) {
                if ($robots) {
                    $robots .= ', ';
                }
                $robots .= 'noarchive';
            }
        }
        if ($robots) {
            $return = sprintf('<meta name="robots" content="%s" />%s%s', $robots, "\n", $return);
        }
        return $return;
    }
    function get_meta_description()
    {
        if (!$this->get_option('desc_enable')) {
            return;
        }
        $desc = '';
        $post = '';
        $data = '';
        $attr = array('substantive' => false, 'separator' => ' ', 'counter' => $this->get_option('desc_counter'), 'length' => false, 'character' => false, 'relevance' => false, 'complete' => false, 'labeling' => false, 'xhtml' => false, 'unique' => false, 'tender' => $this->get_option('desc_tender'), 'type' => 'content');
        if ($this->is_singular()) {
            if ($this->get_user_field('hidden-description')) {
                return;
            } elseif ($this->get_option('desc_manually') && $desc = $this->get_custom_value('description')) {
            } else {
                $post = $GLOBALS['wp_query']->get_queried_object();
            }
        } else if ($GLOBALS['posts']) {
            $post = $GLOBALS['posts'][0];
        }
        if (!$desc && !$post) {
            return;
        }
        if (!$desc) {
            if ($this->is_home()) {
                switch ($this->get_option('desc_home')) {
                    case 1:$data = $post->post_content;
                        break;
                    case 2:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                }
            } else if (is_single()) {
                switch ($this->get_option('desc_single')) {
                    case 1:$data = $post->post_title;
                        $attr['type'] = 'title';
                        break;
                    case 2:$data = $post->post_content;
                        break;
                    case 3:$data = ($post->post_excerpt ? $post->post_excerpt : $post->post_content);
                        $attr['type'] = 'excerpt';
                        break;
                }
            } else if ($this->is_page()) {
                switch ($this->get_option('desc_page')) {
                    case 1:$data = $post->post_title;
                        $attr['type'] = 'title';
                        break;
                    case 2:$data = $post->post_content;
                        break;
                }
            } else if (is_category()) {
                switch ($this->get_option('desc_category')) {
                    case 1:$data = category_description();
                        $attr['type'] = 'catdesc';
                        break;
                    case 2:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                }
            } else if (is_search()) {
                switch ($this->get_option('desc_search')) {
                    case 1:$data = $post->post_content;
                        break;
                    case 2:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                }
            } else if ($this->is_archive()) {
                switch ($this->get_option('desc_archive')) {
                    case 1:$data = $post->post_content;
                        break;
                    case 2:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                }
            } else if (is_tag()) {
                switch ($this->get_option('desc_tagging')) {
                    case 1:$data = $post->post_content;
                        break;
                    case 2:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                    case 3:$data = tag_description();
                        $attr['type'] = 'tagdesc';
                        break;
                }
            }
            if ($data) {
                $desc = $this->get_composed_data($data, $attr);
            }
        }
        if (!$desc) {
            if ($this->get_option('desc_default')) {
                $desc = stripslashes($this->get_option('desc_default'));
            } else {
                return;
            }
        }
        $desc = apply_filters('the_title', $desc);
        $desc = $this->get_htmlentities_encode($desc);
        if ($this->is_utf8()) {
            $desc = @htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
        }
        $desc = trim($desc);
        return sprintf('<meta name="description" content="%s" />%s', $desc, "\n");
    }
    function get_meta_keywords()
    {
        if (!$this->get_option('key_enable')) {
            return;
        }
        $key = '';
        $post = '';
        $data = '';
        $attr = array('substantive' => $this->get_option('key_substantive'), 'separator' => ', ', 'counter' => $this->get_option('key_counter'), 'length' => $this->get_option('key_length'), 'character' => true, 'relevance' => $this->get_option('key_relevance'), 'complete' => $this->get_option('key_complete'), 'labeling' => (is_archive() || is_tag() ? false : $this->get_option('key_labeling')), 'xhtml' => (is_archive() || is_tag() ? false : $this->get_option('key_xhtml')), 'unique' => true, 'tender' => false, 'type' => 'content');
        if ($this->is_singular()) {
            if ($this->get_user_field('hidden-keywords')) {
                return;
            } elseif ($this->get_option('key_manually') && $key = $this->get_custom_value('keywords')) {
            } else {
                $post = $GLOBALS['wp_query']->get_queried_object();
            }
        } else if ($GLOBALS['posts']) {
            $post = $GLOBALS['posts'][0];
        }
        if (!$key && !$post) {
            return;
        }
        if (!$key) {
            if ($this->is_home()) {
                switch ($this->get_option('key_home')) {
                    case 1:$data = $post->post_content;
                        break;
                    case 2:$data = $this->get_the_tags();
                        $attr['type'] = 'tags';
                        break;
                }
            } else if (is_single()) {
                switch ($this->get_option('key_single')) {
                    case 1:$data = $post->post_content;
                        break;
                    case 2:$data = $this->get_the_tags();
                        $attr['type'] = 'tags';
                        break;
                }
            } else if ($this->is_page()) {
                switch ($this->get_option('key_page')) {
                    case 1:$data = $post->post_content;
                        break;
                    case 2:$data = $this->get_the_tags();
                        $attr['type'] = 'tags';
                        break;
                }
            } else if (is_category()) {
                switch ($this->get_option('key_category')) {
                    case 1:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                    case 2:$data = $this->get_the_tags();
                        $attr['type'] = 'tags';
                        break;
                }
            } else if (is_search()) {
                switch ($this->get_option('key_search')) {
                    case 1:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                    case 2:$data = $this->get_the_tags();
                        $attr['type'] = 'tags';
                        break;
                }
            } else if ($this->is_archive()) {
                switch ($this->get_option('key_archive')) {
                    case 1:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                    case 2:$data = $this->get_the_tags();
                        $attr['type'] = 'tags';
                        break;
                }
            } else if (is_tag()) {
                switch ($this->get_option('key_tagging')) {
                    case 1:$data = $this->get_post_titles();
                        $attr['type'] = 'titles';
                        break;
                    case 2:$data = $this->get_the_tags();
                        $attr['type'] = 'tags';
                        break;
                }
            }
            if ($data) {
                $key = $this->get_composed_data($data, $attr);
            }
        }
        if (!$key) {
            if ($this->get_option('key_default')) {
                $key = stripslashes($this->get_option('key_default'));
            } else {
                return;
            }
        }
        $key = apply_filters('the_title', $key);
        $key = $this->get_htmlentities_encode($key);
        if ($this->is_utf8()) {
            $key = @htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        }
        $GLOBALS['wpSEO']->cache['keywords'] = $key = trim($key);
        return sprintf('<meta name="keywords" content="%s" />%s', $key, "\n");
    }
    function get_meta_title()
    {
        if (!$this->get_option('title_enable')) {
            return;
        }
        $end_title = '';
        $mod_title = '';
        $replace_arr = array();
        $txt_clip = $this->get_option('title_separator');
        $txt_clip = $txt_clip ? stripslashes($txt_clip) : '&raquo;';
        $reg_clip = str_replace('/', '\/', preg_quote($txt_clip));
        if ($this->is_singular()) {
            if ($this->get_user_field('hidden-title')) {
                return;
            } elseif ($this->get_option('title_manually') && $custom_title = $this->get_custom_value('title')) {
                if ($this->get_custom_value('only')) {
                    $end_title = $custom_title;
                } else {
                    $mod_title = $custom_title;
                }
            } else {
                $post = $GLOBALS['wp_query']->get_queried_object();
                $mod_title = $post->post_title;
            }
        } else if (is_search()) {
            $mod_title = wp_specialchars($_REQUEST['s'], 1);
        }
        if ($this->is_home() || $this->is_front_page()) {
            $end_title = stripslashes($this->get_option('title_homepage'));
        }
        if (!$end_title && !$mod_title) {
            $mod_title = wp_title('', false);
            $mod_title = $mod_title ? preg_replace('{\s+}', ' ', $mod_title) : get_bloginfo('description');
        }
        if (!$end_title) {
            if (is_paged() && $this->get_option('title_number')) {
                $mod_title .= sprintf(' %s %s %s', $txt_clip, ($this->get_option('title_desc_page') ? stripslashes($this->get_option('title_desc_page')) : __('Page')), $GLOBALS['paged']);
            }
            if (is_singular() && $this->get_option('title_author')) {
                $user_info = get_userdata($post->post_author);
                $mod_title .= sprintf(' %s %s %s', $txt_clip, ucfirst(__('by')), $user_info->display_name);
            }
            if ($this->is_home()) {
                $end_title = str_replace(array('%area%', '%title%'), array(stripslashes($this->get_option('title_desc_home')), $mod_title), $this->get_option('title_channel_home'));
            } else if ($this->is_single()) {
                $end_title = str_replace(array('%area%', '%title%'), array(stripslashes($this->get_option('title_desc_single')), $mod_title), $this->get_option('title_channel_single'));
            } else if ($this->is_page()) {
                $end_title = str_replace(array('%area%', '%title%'), array(stripslashes($this->get_option('title_desc_page')), $mod_title), $this->get_option('title_channel_page'));
            } else if (is_category()) {
                $end_title = str_replace(array('%area%', '%title%', '%desc%'), array(stripslashes($this->get_option('title_desc_category')), $mod_title, $this->get_strip_tags(category_description())), $this->get_option('title_channel_category'));
            } else if (is_search()) {
                $end_title = str_replace(array('%area%', '%title%'), array(stripslashes($this->get_option('title_desc_search')), $mod_title), $this->get_option('title_channel_search'));
            } else if ($this->is_archive()) {
                $end_title = str_replace(array('%area%', '%title%'), array(stripslashes($this->get_option('title_desc_archive')), $mod_title), $this->get_option('title_channel_archive'));
            } else if (is_tag()) {
                $end_title = str_replace(array('%area%', '%tag%'), array(stripslashes($this->get_option('title_desc_tagging')), $mod_title), $this->get_option('title_channel_tagging'));
            } else if (is_404()) {
                $end_title = str_replace(array('%area%', '%title%'), array(stripslashes($this->get_option('title_desc_404')), $mod_title), $this->get_option('title_channel_404'));
            } else {
                $end_title = str_replace(array('%title%'), array($mod_title), $this->get_option('title_channel_home'));
            }
        }
        $end_title = apply_filters('the_title', $end_title);
        if ($this->get_option('title_cleanup')) {
            $end_title = $this->get_strip_tags($end_title);
        }
        if (preg_match('/%\w+%/', $end_title)) {
            if (strpos($end_title, '%blog%') !== false) {
                $replace_arr['%blog%'] = get_bloginfo('name');
            }
            if (strpos($end_title, '%clip%') !== false) {
                $replace_arr['%clip%'] = $txt_clip;
            }
            if (strpos($end_title, '%keywords%') !== false) {
                $replace_arr['%keywords%'] = $GLOBALS['wpSEO']->cache['keywords'];
            }
            if (strpos($end_title, '%category%') !== false) {
                $replace_arr['%category%'] = $this->get_category_data();
            }
            if (strpos($end_title, '%tag%') !== false) {
                $replace_arr['%tag%'] = $this->get_the_tags();
            }
            if ($replace_arr) {
                $end_title = str_replace(array_keys($replace_arr), array_values($replace_arr), $end_title);
            }
        }
        if (strpos($end_title, $txt_clip) !== false) {
            $end_title = preg_replace('/(^\s*' . $reg_clip . '\s*' . $reg_clip . '|\s*' . $reg_clip . '\s*' . $reg_clip . '\s*$|^\s*' . $reg_clip . '|' . $reg_clip . '$|%area% ' . $reg_clip . ')/', '', $end_title);
        }
        $end_title = $this->get_htmlentities_encode($end_title);
        if ($this->is_utf8()) {
            $end_title = @htmlspecialchars($end_title, ENT_QUOTES, 'UTF-8');
        }
        $end_title = trim($end_title);
        return sprintf('<title>%s</title>%s', $end_title, "\n");
    }
    function is_utf8()
    {
        return (strtolower(get_bloginfo('charset')) == 'utf-8') ? true : false;
    }
    function is_min_wp($version)
    {
        return version_compare($GLOBALS['wp_version'], $version . 'alpha', '>=');
    }
    function get_current_page()
    {
        return ((isset($_REQUEST['page']) && $_REQUEST['page'] == $this->basename) ? 'home' : str_replace('.php', '', $GLOBALS['pagenow']));
    }
    function is_home()
    {
        return (is_home() || ($this->is_front_page() && $this->get_option('misc_static'))) && !is_paged();
    }
    function is_single()
    {
        return is_single() && !is_attachment();
    }
    function is_page()
    {
        return is_page() || ($this->is_front_page() && !$this->get_option('misc_static'));
    }
    function is_singular()
    {
        return is_singular() || ($this->is_front_page() && !$this->get_option('misc_static'));
    }
    function is_archive()
    {
        return is_date() || is_author() || is_paged();
    }
    function is_front_page()
    {
        return (get_option('show_on_front') == 'page' && get_option('page_on_front') == $GLOBALS['wp_query']->get_queried_object_id()) ? true : false;
    }
    function is_plugin_updatable()
    {
        $this->get_security_scan();
        $data = get_plugin_data(__FILE__);

        if (!$response = $this->get_http_request(base64_decode('aHR0cDovL3d3dy53cHNlby5kZS9hcGkvaW5mby54bWw='))) {
            return false;
        }
        preg_match('/<version>(.*)<\/version>/', $response, $matches);
        $api_version = @$matches[1];
        if ($api_version && version_compare($data['Version'], $api_version) === - 1) {
            $this->WPlize->update_option('wp_seo_update_version', $api_version);
            return $api_version;
        }
        return false;
    }
    function set_global_arrays()
    {
        $GLOBALS['wpSEO']->cache = array('header' => '', 'length' => '', 'keywords' => '');
        $this->noindex = array(0 => 'index', 1 => 'index, follow', 2 => 'index, nofollow', 3 => 'noindex', 4 => 'noindex, follow', 5 => 'noindex, nofollow');
    }
    function set_language_arrays()
    {
        $this->groups = array('home' => array('name' => __('Home', 'wpseo'), 'desc' => array(0 => __('Default', 'wpseo'), 1 => __('Excerpt of the first post', 'wpseo'), 2 => __('Titles of all listed posts', 'wpseo')), 'key' => array(0 => __('Default', 'wpseo'), 1 => __('Excerpt of the first post', 'wpseo'), 2 => __('Tags of all listed posts', 'wpseo'))), 'single' => array('name' => __('Post', 'wpseo'), 'desc' => array(0 => __('Default', 'wpseo'), 1 => __('Title of the current post', 'wpseo'), 2 => __('Excerpt of the current post', 'wpseo'), 3 => __('Optional excerpt while writing the post', 'wpseo')), 'key' => array(0 => __('Default', 'wpseo'), 1 => __('Excerpt of the current post', 'wpseo'), 2 => __('Tags of the current post', 'wpseo'))), 'page' => array('name' => __('Page', 'wpseo'), 'desc' => array(0 => __('Default', 'wpseo'), 1 => __('Title of the current post', 'wpseo'), 2 => __('Excerpt of the current post', 'wpseo')), 'key' => array(0 => __('Default', 'wpseo'), 1 => __('Excerpt of the current post', 'wpseo'), 2 => __('Tags of the current page', 'wpseo'))), 'category' => array('name' => __('Category', 'wpseo'), 'desc' => array(0 => __('Default', 'wpseo'), 1 => __('Category description', 'wpseo'), 2 => __('Titles of all listed posts', 'wpseo')), 'key' => array(0 => __('Default', 'wpseo'), 1 => __('Titles of all listed posts', 'wpseo'), 2 => __('Tags of all listed posts', 'wpseo'))), 'search' => array('name' => __('Search', 'wpseo'), 'desc' => array(0 => __('Default', 'wpseo'), 1 => __('Excerpt of the first post', 'wpseo'), 2 => __('Titles of all listed posts', 'wpseo')), 'key' => array(0 => __('Default', 'wpseo'), 1 => __('Titles of all listed posts', 'wpseo'), 2 => __('Tags of all listed posts', 'wpseo'))), 'archive' => array('name' => __('Archives', 'wpseo'), 'desc' => array(0 => __('Default', 'wpseo'), 1 => __('Excerpt of the first post', 'wpseo'), 2 => __('Titles of all listed posts', 'wpseo')), 'key' => array(0 => __('Default', 'wpseo'), 1 => __('Titles of all listed posts', 'wpseo'), 2 => __('Tags of all listed posts', 'wpseo'))), 'tagging' => array('name' => __('Tag', 'wpseo'), 'desc' => array(0 => __('Default', 'wpseo'), 1 => __('Excerpt of the first post', 'wpseo'), 2 => __('Titles of all listed posts', 'wpseo'), 3 => __('Description of the tag', 'wpseo')), 'key' => array(0 => __('Default', 'wpseo'), 1 => __('Titles of all listed posts', 'wpseo'), 2 => __('Tags of all listed posts', 'wpseo'))), '404' => array('name' => __('Error', 'wpseo'), 'desc' => array(0 => __('Default', 'wpseo')), 'key' => array(0 => __('Default', 'wpseo'))));
        $this->misc = array('channel' => array('%blog%' => __('Blogname', 'wpseo'), '%blog% %clip% %desc%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Description', 'wpseo'), '%blog% %clip% %area%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Label', 'wpseo'), '%blog% %clip% %area% %title%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Label', 'wpseo') . ' ' . __('Title', 'wpseo'), '%blog% %clip% %title%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo'), '%blog% %clip% %tag%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Tag', 'wpseo'), '%blog% %clip% %desc% %clip% %title%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Description', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo'), '%blog% %clip% %area% %clip% %title%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Label', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo'), '%blog% %clip% %area% %tag%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Label', 'wpseo') . ' ' . __('Tag', 'wpseo'), '%blog% %clip% %area% %clip% %tag%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Label', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Tag', 'wpseo'), '%blog% %clip% %keywords% %clip% %title%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Keywords', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo'), '%blog% %clip% %category% %clip% %title%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Category', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo'), '%blog% %clip% %title% %clip% %keywords%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Keywords', 'wpseo'), '%blog% %clip% %title% %clip% %category%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Category', 'wpseo'), '%blog% %clip% %tag% %clip% %title%' => __('Blogname', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Tag', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo'), '%title%' => __('Title', 'wpseo'), '%title% %clip% %blog%' => __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%title% %clip% %category%' => __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Category', 'wpseo'), '%title% %clip% %desc% %clip% %blog%' => __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Description', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%title% %clip% %area% %clip% %blog%' => __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Label', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%title% %clip% %keywords% %clip% %blog%' => __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Keywords', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%title% %clip% %category% %clip% %blog%' => __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Category', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%title% %clip% %category% %clip% %keywords%' => __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Category', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Keywords', 'wpseo'), '%title% %clip% %tag% %clip% %blog%' => __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Tag', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%tag% %clip% %blog%' => __('Tag', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%tag% %clip% %area%' => __('Tag', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Label', 'wpseo'), '%tag% %clip% %area% %clip% %blog%' => __('Tag', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Label', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%category% %clip% %title% %clip% %blog%' => __('Category', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%category% %clip% %title%' => __('Category', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo'), '%desc%' => __('Description', 'wpseo'), '%desc% %clip% %blog%' => __('Description', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%area% %clip% %blog%' => __('Label', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%keywords% %clip% %title% %clip% %blog%' => __('Keywords', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Blogname', 'wpseo'), '%keywords% %clip% %category% %clip% %title%' => __('Keywords', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Category', 'wpseo') . ' ' . __('Separator', 'wpseo') . ' ' . __('Title', 'wpseo')), 'orderby' => array('%wpseo_title%%wpseo_desc%%wpseo_keywords%%wpseo_robots%' => __('Title', 'wpseo') . ' / ' . __('Description', 'wpseo') . ' / ' . __('Keywords', 'wpseo') . ' / ' . __('Robots', 'wpseo'), '%wpseo_title%%wpseo_keywords%%wpseo_desc%%wpseo_robots%' => __('Title', 'wpseo') . ' / ' . __('Keywords', 'wpseo') . ' / ' . __('Description', 'wpseo') . ' / ' . __('Robots', 'wpseo'), '%wpseo_desc%%wpseo_keywords%%wpseo_robots%%wpseo_title%' => __('Description', 'wpseo') . ' / ' . __('Keywords', 'wpseo') . ' / ' . __('Robots', 'wpseo') . ' / ' . __('Title', 'wpseo'), '%wpseo_keywords%%wpseo_desc%%wpseo_robots%%wpseo_title%' => __('Keywords', 'wpseo') . ' / ' . __('Description', 'wpseo') . ' / ' . __('Robots', 'wpseo') . ' / ' . __('Title', 'wpseo')));
    }
    function set_toggle_status()
    {
        check_ajax_referer('wp_seo_ajax');
        if ($_POST['set_toggle_id'] && $_POST['set_toggle_status']) {
            $this->WPlize->update_option($_POST['set_toggle_id'], ($_POST['set_toggle_status'] == 'true' ? 'closed' : ''));
        }
    }
    function set_plugin_actions($links, $file)
    {
        if ($this->basename == $file && $this->shame_on_me() !== false) {
            return array_merge(array(sprintf('| <a href="options-general.php?page=%s">%s</a>', $this->basename, __('Settings') . '<br />')), $links);
        }
        return $links;
    }
    function set_plugin_meta($links, $file)
    {
        if ($this->basename == $file && $this->shame_on_me() !== false) {
            return array_merge($links, array(sprintf('<a href="options-general.php?page=%s">%s</a>', $this->basename, __('Settings'))));
        }
        return $links;
    }
    function set_edit_page()
    {
        if ($this->shame_on_me() !== false) {
            if (function_exists('add_meta_box')) {
                add_meta_box('wpseo_edit', __('wpSEO Options', 'wpseo'), array($this, 'show_edit_fields_new'), 'post', 'advanced', 'high');
                add_meta_box('wpseo_edit', __('wpSEO Options', 'wpseo'), array($this, 'show_edit_fields_new'), 'page', 'advanced', 'high');
            } else {
                add_action('simple_edit_form', array($this, 'show_edit_fields'));
                add_action('edit_form_advanced', array($this, 'show_edit_fields'));
                add_action('edit_page_form', array($this, 'show_edit_fields'));
            }
        }
    }
    function set_admin_page()
    {
        if ($this->shame_on_me() !== false) {
            $hook = add_options_page('wpSEO Plugin', ($this->is_min_wp('2.7') ? '<img src="' . plugins_url('wpseo/img/icon.png') . '" width="11" height="9" border="0" alt="wpSEO Icon" />' : '') . 'wpSEO', 9, __FILE__, array($this, 'show_admin_page'));
            if (function_exists('add_contextual_help')) {
                add_contextual_help($hook, sprintf('%s<br /><br /><a href="%s/manual/" target="_blank">%s</a>', __('wpSEO contains more than 70 options. Most options are directly linked to the corresponding point in the online manual, where the specific option is described in detail. Below the link to the manual with descriptions of all the available functions of wpSEO.', 'wpseo'), $this->get_plugin_domain(), __('Documentation', 'wpseo')));
            }
        }
    }
    function set_default_options()
    {
        $this->WPlize->init_option(array('wp_seo_title_enable' => 1, 'wp_seo_title_separator' => '&raquo;', 'wp_seo_title_homepage' => '', 'wp_seo_title_number' => 1, 'wp_seo_title_author' => 0, 'wp_seo_title_cleanup' => 0, 'wp_seo_title_manually' => 0, 'wp_seo_title_desc_home' => '', 'wp_seo_title_desc_single' => '', 'wp_seo_title_desc_page' => '', 'wp_seo_title_desc_category' => '', 'wp_seo_title_desc_search' => '', 'wp_seo_title_desc_archive' => '', 'wp_seo_title_desc_tagging' => '', 'wp_seo_title_desc_404' => '', 'wp_seo_title_channel_home' => '%title% %clip% %blog%', 'wp_seo_title_channel_single' => '%title% %clip% %keywords% %clip% %blog%', 'wp_seo_title_channel_page' => '%title% %clip% %blog%', 'wp_seo_title_channel_category' => '%title% %clip% %blog%', 'wp_seo_title_channel_search' => '%title% %clip% %blog%', 'wp_seo_title_channel_archive' => '%title% %clip% %blog%', 'wp_seo_title_channel_tagging' => '%tag% %clip% %blog%', 'wp_seo_title_channel_404' => '%title% %clip% %blog%', 'wp_seo_desc_enable' => 1, 'wp_seo_desc_default' => get_bloginfo('description'), 'wp_seo_desc_counter' => 20, 'wp_seo_desc_manually' => 0, 'wp_seo_desc_tender' => 0, 'wp_seo_desc_home' => 2, 'wp_seo_desc_single' => 2, 'wp_seo_desc_page' => 2, 'wp_seo_desc_category' => 2, 'wp_seo_desc_search' => 2, 'wp_seo_desc_archive' => 2, 'wp_seo_desc_tagging' => 2, 'wp_seo_desc_404' => 0, 'wp_seo_key_enable' => 1, 'wp_seo_key_default' => '', 'wp_seo_key_counter' => 6, 'wp_seo_key_length' => 3, 'wp_seo_key_blacklist' => '', 'wp_seo_key_labeling' => 0, 'wp_seo_key_xhtml' => 0, 'wp_seo_key_manually' => 0, 'wp_seo_key_complete' => 0, 'wp_seo_key_substantive' => 1, 'wp_seo_key_relevance' => 1, 'wp_seo_key_home' => 1, 'wp_seo_key_single' => 1, 'wp_seo_key_page' => 1, 'wp_seo_key_category' => 1, 'wp_seo_key_search' => 1, 'wp_seo_key_archive' => 1, 'wp_seo_key_tagging' => 1, 'wp_seo_key_404' => 0, 'wp_seo_noindex_enable' => 1, 'wp_seo_noindex_hidden' => 0, 'wp_seo_noindex_canonical' => 1, 'wp_seo_noindex_manually' => 0, 'wp_seo_noindex_home' => 0, 'wp_seo_noindex_single' => 0, 'wp_seo_noindex_page' => 0, 'wp_seo_noindex_category' => 4, 'wp_seo_noindex_search' => 4, 'wp_seo_noindex_archive' => 4, 'wp_seo_noindex_tagging' => 4, 'wp_seo_noindex_404' => 4, 'wp_seo_noindex_attachment' => 0, 'wp_seo_speed_nocheck' => 0, 'wp_seo_misc_static' => 0, 'wp_seo_misc_monitor' => 1, 'wp_seo_misc_monitor_timestamp' => 0, 'wp_seo_misc_section' => 0, 'wp_seo_misc_noodp' => 0, 'wp_seo_misc_noarchive' => 0, 'wp_seo_misc_feed' => 1, 'wp_seo_misc_attachment' => 0, 'wp_seo_misc_order' => '%wpseo_title%%wpseo_desc%%wpseo_keywords%%wpseo_robots%', 'wp_seo_title_convert' => '', 'wp_seo_desc_convert' => '', 'wp_seo_key_convert' => '', 'wp_seo_noindex_convert' => '', 'wp_seo_speed_settings' => '', 'wp_seo_misc_settings' => '', 'wp_seo_export_settings' => 'closed', 'wp_seo_about_wpseo' => 'closed', 'wp_seo_google_pagerank' => '', 'wp_seo_alexa_rank' => '', 'wp_seo_yahoo_backlinks' => '', 'wp_seo_feedburner_subscribers' => '', 'wp_seo_update_version' => '', 'wp_seo_update_check' => time(), base64_decode('d3Bfc2VvX2ludGVybl9rZXk=') => '', base64_decode('d3Bfc2VvX2ludGVybl9ieQ==') => '', base64_decode('d3Bfc2VvX2ludGVybl9oYXNo') => ''));
        add_option(base64_decode('YWRkX2FkbWluX21hcmtlcl90aW1lc3RhbXA='), time(), '', 'no');
    }
    function set_user_settings()
    {
        $options = array();
        if (isset($_POST['wp_seo_title_enable']) && !empty($_POST['wp_seo_title_enable'])) {
            $options['wp_seo_title_enable'] = 1;
            $options['wp_seo_title_separator'] = (empty($_POST['wp_seo_title_separator']) ? '&raquo;' : $this->get_htmlentities_decode($_POST['wp_seo_title_separator']));
            $options['wp_seo_title_homepage'] = $this->get_htmlentities_decode($_POST['wp_seo_title_homepage']);
            $options['wp_seo_title_number'] = @$_POST['wp_seo_title_number'];
            $options['wp_seo_title_author'] = @$_POST['wp_seo_title_author'];
            $options['wp_seo_title_cleanup'] = @$_POST['wp_seo_title_cleanup'];
            $options['wp_seo_title_manually'] = @$_POST['wp_seo_title_manually'];
            foreach (array_keys($this->groups) as $k) {
                $options['wp_seo_title_channel_' . $k] = $_POST['wp_seo_title_channel_' . $k];
                $options['wp_seo_title_desc_' . $k] = $this->get_htmlentities_decode($_POST['wp_seo_title_desc_' . $k]);
            }
        } else {
            $options['wp_seo_title_enable'] = 0;
        }
        if (isset($_POST['wp_seo_desc_enable']) && !empty($_POST['wp_seo_desc_enable'])) {
            $options['wp_seo_desc_enable'] = 1;
            $options['wp_seo_desc_default'] = $this->get_htmlentities_decode($_POST['wp_seo_desc_default']);
            $options['wp_seo_desc_counter'] = $_POST['wp_seo_desc_counter'];
            $options['wp_seo_desc_tender'] = @$_POST['wp_seo_desc_tender'];
            $options['wp_seo_desc_manually'] = @$_POST['wp_seo_desc_manually'];
            foreach (array_keys($this->groups) as $k) {
                $options['wp_seo_desc_' . $k] = $_POST['wp_seo_desc_' . $k];
            }
        } else {
            $options['wp_seo_desc_enable'] = 0;
        }
        if (isset($_POST['wp_seo_key_enable']) && !empty($_POST['wp_seo_key_enable'])) {
            $options['wp_seo_key_enable'] = 1;
            $options['wp_seo_key_default'] = $this->get_htmlentities_decode($_POST['wp_seo_key_default']);
            $options['wp_seo_key_blacklist'] = $this->get_htmlentities_decode($_POST['wp_seo_key_blacklist']);
            $options['wp_seo_key_complete'] = @$_POST['wp_seo_key_complete'];
            $options['wp_seo_key_substantive'] = $_POST['wp_seo_key_substantive'];
            $options['wp_seo_key_relevance'] = $_POST['wp_seo_key_relevance'];
            $options['wp_seo_key_labeling'] = @$_POST['wp_seo_key_labeling'];
            $options['wp_seo_key_xhtml'] = $_POST['wp_seo_key_xhtml'];
            $options['wp_seo_key_manually'] = @$_POST['wp_seo_key_manually'];
            $options['wp_seo_key_counter'] = $_POST['wp_seo_key_counter'];
            $options['wp_seo_key_length'] = $_POST['wp_seo_key_length'];
            foreach (array_keys($this->groups) as $k) {
                $options['wp_seo_key_' . $k] = $_POST['wp_seo_key_' . $k];
            }
        } else {
            $options['wp_seo_key_enable'] = 0;
        }
        if (isset($_POST['wp_seo_noindex_enable']) && !empty($_POST['wp_seo_noindex_enable'])) {
            $options['wp_seo_noindex_enable'] = 1;
            foreach (array_keys($this->groups) as $k) {
                $options['wp_seo_noindex_' . $k] = $_POST['wp_seo_noindex_' . $k];
            }
            $options['wp_seo_noindex_attachment'] = $_POST['wp_seo_noindex_attachment'];
            $options['wp_seo_noindex_hidden'] = @$_POST['wp_seo_noindex_hidden'];
            $options['wp_seo_noindex_canonical'] = @$_POST['wp_seo_noindex_canonical'];
            $options['wp_seo_noindex_manually'] = @$_POST['wp_seo_noindex_manually'];
        } else {
            $options['wp_seo_noindex_enable'] = 0;
        }
        $options['wp_seo_speed_nocheck'] = @$_POST['wp_seo_speed_nocheck'];
        $options['wp_seo_misc_static'] = @$_POST['wp_seo_misc_static'];
        $options['wp_seo_misc_monitor'] = @$_POST['wp_seo_misc_monitor'];
        $options['wp_seo_misc_section'] = @$_POST['wp_seo_misc_section'];
        $options['wp_seo_misc_noodp'] = @$_POST['wp_seo_misc_noodp'];
        $options['wp_seo_misc_noarchive'] = @$_POST['wp_seo_misc_noarchive'];
        $options['wp_seo_misc_feed'] = @$_POST['wp_seo_misc_feed'];
        $options['wp_seo_misc_attachment'] = @$_POST['wp_seo_misc_attachment'];
        $options['wp_seo_misc_order'] = @$_POST['wp_seo_misc_order'];
        $this->WPlize->update_option($options);
        if (isset($_POST['wp_seo_misc_monitor']) && !empty($_POST['wp_seo_misc_monitor'])) {
            $this->exe_daily_cronjob();
        }
    }
    function set_download_header($filename, $filesize, $filetype)
    {
        @ob_end_clean();
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Length: ' . $filesize);
        header('Content-type: ' . $filetype . '; charset=' . get_option('blog_charset'), true);
    }
    function set_import_options($file)
    {
        $options = array();
        $data = str_replace(array('<![CDATA[', ']]>'), '', file_get_contents($file));
        preg_match_all('/<(.*?)>(.*?)<\/.*?>/', $data, $matches);
        for ($i = 0; $i < count($matches[1]); $i ++) {
            $name = $matches[1][$i];
            $value = $matches[2][$i];
            if (strpos($name, 'wp_seo') !== false && strpos($name, 'wp_seo_intern') === false) {
                $options[$name] = $value;
            }
        }
        $this->WPlize->update_option($options);
    }
    function set_edit_fields($post_id)
    {
        if ($_POST['post_type'] == 'page') {
            if (!current_user_can('edit_page', $post_id)) {
                return $post_id;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return $post_id;
            }
        }
        if (!isset($_REQUEST['_wpseo_edit_nonce']) || empty($_REQUEST['_wpseo_edit_nonce']) || !wp_verify_nonce($_REQUEST['_wpseo_edit_nonce'], '_wpseo_edit_nonce')) {
            return $post_id;
        }
        if ($_POST['post_ID'] != $post_id) {
            return $post_id;
        }
        $fields = array('title' => 'title', 'only' => 'only', 'description' => 'description', 'keywords' => 'keywords', 'robots' => 'custom-robots');
        foreach ($fields as $new => $old) {
            $adv_field = '_wpseo_edit_' . $new;
            $post_field = @$_POST[$adv_field];
            $prev_field = get_post_meta($post_id, $adv_field, true);
            if (!empty($post_field) && !empty($prev_field) && $post_field != $prev_field) {
                update_post_meta($post_id, $adv_field, $post_field);
            } else if (empty($post_field) && !empty($prev_field)) {
                delete_post_meta($post_id, $adv_field);
            } else if (!empty($post_field) && empty($prev_field)) {
                add_post_meta($post_id, $adv_field, $post_field, true);
            }
            if (get_post_meta($post_id, $old, true)) {
                delete_post_meta($post_id, $old);
            }
        }
    }
    function set_attachment_file($data)
    {
        if (empty($data)) {
            return $data;
        }
        $title = strtolower(trim($data['post_title']));
        if (empty($title) || strcasecmp($title, $data['post_name']) == 0) {
            return $data;
        }
        $title = strtr($title, array('œ' => 'oe', 'à' => 'a', 'ô' => 'o', 'ď' => 'd', 'ḟ' => 'f', 'ë' => 'e', 'š' => 's', 'ơ' => 'o', 'ß' => 'ss', 'ă' => 'a', 'ř' => 'r', 'ț' => 't', 'ň' => 'n', 'ā' => 'a', 'ķ' => 'k', 'ŝ' => 's', 'ỳ' => 'y', 'ņ' => 'n', 'ĺ' => 'l', 'ħ' => 'h', 'ṗ' => 'p', 'ó' => 'o', 'ú' => 'u', 'ě' => 'e', 'é' => 'e', 'ç' => 'c', 'ẁ' => 'w', 'ċ' => 'c', 'õ' => 'o', 'ṡ' => 's', 'ø' => 'o', 'ģ' => 'g', 'ŧ' => 't', 'ș' => 's', 'ė' => 'e', 'ĉ' => 'c', 'ś' => 's', 'î' => 'i', 'ű' => 'u', 'ć' => 'c', 'ę' => 'e', 'ŵ' => 'w', 'ṫ' => 't', 'ū' => 'u', 'č' => 'c', 'ö' => 'oe', 'è' => 'e', 'ŷ' => 'y', 'ą' => 'a', 'ł' => 'l', 'ų' => 'u', 'ů' => 'u', 'ş' => 's', 'ğ' => 'g', 'ļ' => 'l', 'ƒ' => 'f', 'ž' => 'z', 'ẃ' => 'w', 'ḃ' => 'b', 'å' => 'a', 'ì' => 'i', 'ï' => 'i', 'ḋ' => 'd', 'ť' => 't', 'ŗ' => 'r', 'ä' => 'ae', 'í' => 'i', 'ŕ' => 'r', 'ê' => 'e', 'ü' => 'ue', 'ò' => 'o', 'ē' => 'e', 'ñ' => 'n', 'ń' => 'n', 'ĥ' => 'h', 'ĝ' => 'g', 'đ' => 'd', 'ĵ' => 'j', 'ÿ' => 'y', 'ũ' => 'u', 'ŭ' => 'u', 'ư' => 'u', 'ţ' => 't', 'ý' => 'y', 'ő' => 'o', 'â' => 'a', 'ľ' => 'l', 'ẅ' => 'w', 'ż' => 'z', 'ī' => 'i', 'ã' => 'a', 'ġ' => 'g', 'ṁ' => 'm', 'ō' => 'o', 'ĩ' => 'i', 'ù' => 'u', 'į' => 'i', 'ź' => 'z', 'á' => 'a', 'û' => 'u', 'þ' => 'th', 'ð' => 'dh', 'æ' => 'ae', 'µ' => 'u', 'ĕ' => 'e', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'jo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'x', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'Ъ' => '', 'ы' => 'y', 'ь' => '', 'Ь' => '', 'э' => 'eh', 'ю' => 'ju', 'я' => 'ja'));
        $title = sanitize_title_with_dashes($title);
        $title = substr($title, 0, 50);
        if (empty($title)) {
            return $data;
        }
        if ($this->is_min_wp('2.5')) {
            $metadata = wp_get_attachment_metadata($data['ID'], true);
            $oldfile = get_attached_file($data['ID']);
            $url = $data['guid'];
        } else if (isset($data['file']) && !empty($data['file'])) {
            $oldfile = $data['file'];
            $type = $data['type'];
            $url = $data['url'];
        } else {
            return $data;
        }
        $infodata = pathinfo($oldfile);
        $dirname = $infodata['dirname'];
        $basename = $infodata['basename'];
        $extension = $infodata['extension'];
        $filename = preg_replace('/\.' . $extension . '$/', '', $basename);
        $newname = $title;
        if ($filename != $newname && file_exists($newfile)) {
            $newname = $title . '-1';
        }
        $newfilename = sprintf('%s.%s', $newname, $extension);
        $newfile = sprintf('%s/%s', $dirname, $newfilename);
        if (rename($oldfile, $newfile)) {
            if ($this->is_min_wp('2.5')) {
                $update = $metadata;
                $update['file'] = $newfilename;
                if ($thumbs = @$metadata['sizes']) {
                    foreach($thumbs as $key => $value) {
                        $thumbfilename = str_replace($filename, $newname, $value['file']);
                        rename(sprintf('%s/%s', $dirname, $value['file']), sprintf('%s/%s', $dirname, $thumbfilename));
                        $update['sizes'][$key] = array_merge($value, array('file' => $thumbfilename));
                    }
                }
                $data = array_merge($data, array('guid' => sprintf('%s/%s', dirname($url), $newfilename), 'post_name' => $newname));
                update_attached_file($data['ID'], $newfile);
                wp_update_attachment_metadata($data['ID'], $update);
            } else {
                $data = array('file' => $newfile, 'url' => dirname($url) . '/' . $newfile, 'type' => $type);
            }
        }
        return $data;
    }
    function get_crypt_data($encoded_data, $desired_type = '')
    {
        $decoded_data = '';
        $decoded_sign = '';
        if (empty($encoded_data)) {
            return false;
        }
        if (!is_string($encoded_data)) {
            settype($encoded_data, 'string');
        }
        $encoded_array = explode(':', wordwrap($encoded_data, 7, ':', 7));
        foreach ($encoded_array as $decoded_pair) {
            $ascii_array = explode('.', base64_decode($decoded_pair));
            $data_digit = intval($ascii_array[0]);
            $sign_digit = $ascii_array[1];
            $decoded_data .= !empty($data_digit) ? chr($data_digit) : '';
            $decoded_sign .= $sign_digit;
        }
        $decoded_data = strrev($decoded_data);
        $data_sign = sprintf('%u', crc32($decoded_data));
        $data_length = strlen($decoded_data);
        if (strcmp(str_pad($data_sign, $data_length, '0'), $decoded_sign) !== 0) {
            return false;
        }
        if (!empty($desired_type) && in_array($desired_type, array('integer', 'double', 'string', 'array', 'object'))) {
            settype($decoded_data, $desired_type);
        }
        return $decoded_data;
    }
    function get_user_field($field)
    {
        return get_post_meta((is_admin() ? $GLOBALS['post_ID'] : $GLOBALS['wp_query']->get_queried_object_id()), $field, true);
    }
    function get_custom_value($key, $encode = false)
    {
        $fields = array('title' => 'title', 'only' => 'only', 'description' => 'description', 'keywords' => 'keywords', 'robots' => 'custom-robots');
        $new = '_wpseo_edit_' . $key;
        $old = $fields[$key];
        $output = $this->get_user_field($new);
        $output = empty($output) ? $this->get_user_field($old) : $output;
        if ($encode) {
            return $this->get_htmlentities_encode($output, true, true);
        } else {
            return $output;
        }
    }
    function shame_on_me()
    {
     return true;
    }
    function show_admin_notices()
    {
        $marker = $this->shame_on_me();
        if ($marker !== true) { ?><?php }
    }
    function show_dashboard_widget()
    { ?><style type="text/css">#wpseo_dashboard_widget p.sub {font: italic 13px Georgia, "Times New Roman", "Bitstream Charter", Times, serif;color: #777;margin: -12px;padding: 5px 10px 15px;}#wpseo_dashboard_widget .table {margin: 0 -9px 10px;padding: 0 10px;background: #F9F9F9;border-top: 1px solid #ECECEC;border-bottom: 1px solid #ECECEC;}#wpseo_dashboard_widget table {width: 100%;}#wpseo_dashboard_widget td.b {font: normal 14px Georgia, "Times New Roman", "Bitstream Charter", Times, serif;text-align: right;padding-right: 6px;}#wpseo_dashboard_widget table td {padding: 3px 0;border-top: 1px solid #ECECEC;white-space: nowrap;}#wpseo_dashboard_widget table tr.first td {border-top: none;}#wpseo_dashboard_widget td.first,#wpseo_dashboard_widget td.last {width: 1px;}#wpseo_dashboard_widget .t {color: #777;font-size: 12px;padding-top: 6px;padding-right: 12px;}#wpseo_dashboard_widget td.b a {font-size: 18px;}#wpseo_dashboard_widget tr.first td {border: none;}</style><p class="sub"><?php _e('At a Glance') ?></p><div class="table"><table><tr class="first"><td class="first b"><a href="http://www.google.<?php echo ($this->get_plugin_tld() == 'de' ? 'de' : 'com') ?>/search?q=site%3A<?php echo urlencode(get_bloginfo('url')) ?>" target="_blank"><?php echo number_format_i18n($this->get_option('google_pagerank')) ?></a></td><td class="t"><a href="http://www.google.<?php echo ($this->get_plugin_tld() == 'de' ? 'de' : 'com') ?>/search?q=site%3A<?php echo urlencode(get_bloginfo('url')) ?>" target="_blank">Google PageRank</a></td><td class="b"><a href="http://www.alexa.com/data/details/traffic_details/<?php echo get_bloginfo('url') ?>" target="_blank"><?php echo number_format_i18n($this->get_option('alexa_rank')) ?></a></td><td class="last t"><a href="http://www.alexa.com/data/details/traffic_details/<?php echo get_bloginfo('url') ?>" target="_blank">Alexa Rank</a></td></tr><tr><td class="first b"><a href="http://siteexplorer.search.yahoo.com/search?p=<?php echo urlencode(get_bloginfo('url')) ?>&bwm=i" target="_blank"><?php echo number_format_i18n($this->get_option('yahoo_backlinks')) ?></a></td><td class="t"><a href="http://siteexplorer.search.yahoo.com/search?p=<?php echo urlencode(get_bloginfo('url')) ?>&bwm=i" target="_blank">Yahoo! Backlinks</a></td><?php if ($this->get_option('feedburner_subscribers')) { ?><td class="b"><a href="http://feedburner.google.com/fb/a/myfeeds" target="_blank"><?php echo number_format_i18n($this->get_option('feedburner_subscribers')) ?></a></td><td class="last t"><a href="http://feedburner.google.com/fb/a/myfeeds" target="_blank">FeedBurner</a></td><?php } else { ?><td class="b"></td><td class="last t"></td><?php } ?></tr></table></div><?php }
    function show_plugin_head()
    {
        wp_enqueue_script('jquery') ?><style type="text/css"><?php if ($this->is_min_wp('2.7')) { ?>div.icon32 {background: url(<?php echo plugins_url('wpseo/img/icon32.png') ?>) no-repeat;}div.inside {background: url(<?php echo plugins_url('wpseo/img/icon270.png') ?>) no-repeat right bottom;}div.less {background: none;}<?php } ?>table.form-table table td {padding: 0;border: 0;}table.form-table td span {margin: 0 3px;}.mini {width: 400px;}.medium,textarea {width: 500px;}textarea {height: 50px;}input[type="checkbox"] {margin-right: 5px;}</style><script type="text/javascript">jQuery(document).ready(function($){$('#accept_suggestion').click(function() {$('#wp_seo_desc_default').val($(this).parent().find('em').text());return false;});function wpseo_enable_form() {var status = !$(this).attr('checked');$('#wp_seo_form [id^=' + this.id.replace('_enable', '') + ']').not('[id$=_convert]').not('[id$=_enable]').not('[id$=_submit]').each(function() {$(this).attr('disabled', status);});}$('#wp_seo_form input[id$=_enable]').click(wpseo_enable_form);$('#wp_seo_form input[id$=_enable]').each(wpseo_enable_form);<?php if ($this->is_min_wp('2.6')) { ?>$('.wpseo .postbox h3').click(function() {var postbox = $($(this).parent().get(0));postbox.toggleClass('closed');$.post('<?php echo admin_url("admin-ajax.php") ?>',{'action':'set_toggle_status','_ajax_nonce':'<?php echo wp_create_nonce("wp_seo_ajax") ?>','set_toggle_id':postbox.attr('id'),'set_toggle_status': postbox.is('.closed')});});<?php } ?>$('#wpseo_export_button').click(function() {document.location.href = 'options-general.php?page=<?php echo $this->basename ?>&action=export';return false;});});</script><?php }
    function show_plugin_row($file)
    {
        if ($this->basename != $file) {
            return;
        }
         }
    function show_plugin_info()
    {
        $data = get_plugin_data(__FILE__);
        echo sprintf('%1$s - %2$s | ' . __('Version') . ' <a href="%3$s/history/#%4$s" target="_blank">%4$s</a> | ' . __('Author') . ': %5$s<br />', $data['Title'], __('The SEO plugin for WordPress', 'wpseo'), $this->get_plugin_domain(), $data['Version'], $data['Author']);
    }
    function show_feed_noindex()
    {
        echo '<xhtml:meta xmlns:xhtml="http://www.w3.org/1999/xhtml" name="robots" content="noindex" />' . "\n";
    }
    function show_help_link($anchor)
    {
        echo sprintf('[<a href="%s/manual/#%s" target="_blank">?</a>]', $this->get_plugin_domain(), $anchor);
    }
    function show_edit_fields()
    { ?><style type="text/css">#_wpseo_edit_title,#_wpseo_edit_description,#_wpseo_edit_keywords {width: 98%;}#_wpseo_edit_robots {width: 24%;}</style><div id="wp_seo_edit"><?php if ($this->get_option('title_manually')) { ?><fieldset><legend><?php _e('Add title manually', 'wpseo') ?></legend><div><input type="text" name="_wpseo_edit_title" id="_wpseo_edit_title" size="30" value="<?php echo $this->get_custom_value('title', true) ?>" /></div><div><input type="checkbox" name="_wpseo_edit_only" id="_wpseo_edit_only" value="1" <?php checked($this->get_custom_value('only'), 1) ?> />&nbsp;<?php _e('Deactivate a automatic formatting and display just this value as the post title', 'wpseo') ?></div></fieldset><?php }
        if ($this->get_option('desc_manually')) { ?><fieldset><legend><?php _e('Add description manually', 'wpseo') ?></legend><div><input type="text" name="_wpseo_edit_description" id="_wpseo_edit_description" size="30" value="<?php echo $this->get_custom_value('description', true) ?>" /></div></fieldset><?php }
        if ($this->get_option('key_manually')) { ?><fieldset><legend><?php _e('Add keywords manually', 'wpseo') ?></legend><div><input type="text" name="_wpseo_edit_keywords" id="_wpseo_edit_keywords" value="<?php echo $this->get_custom_value('keywords', true) ?>" /></div></fieldset><?php }
        if ($this->get_option('noindex_manually')) { ?><fieldset><legend><?php _e('Add Robots tag manually', 'wpseo') ?></legend><div><input type="text" name="_wpseo_edit_robots" id="_wpseo_edit_robots" value="<?php echo $this->get_custom_value('robots') ?>" /></div></fieldset><?php } ?></div><?php }
    function show_edit_fields_new()
    {
        wp_enqueue_script('jquery') ?><style type="text/css">#wpseo_edit {height: 1%;overflow: hidden;}#wpseo_edit p {padding-bottom: 5px;}#wpseo_edit p label {display: block;}#wpseo_edit p label span {float: right;}#wpseo_edit p.warning {margin-top: 12px;float: right;text-align: right;}#_wpseo_edit_title,#_wpseo_edit_description,#_wpseo_edit_keywords {width: 100%;*width: 99%;}#_wpseo_edit_only {float: left;height: 20px;margin: 0 4px 0 6px;}#_wpseo_edit_only + label {line-height: 20px;}#_wpseo_edit_robots {width: 25%;}</style><script type="text/javascript">jQuery(document).ready(function($) {function wpseo_edit_counter(item) {var field = '#_wpseo_edit_' + item;var obj = $(field);var value = obj.val();var i = 0;if (obj.length <= 0) {return;}$.each(value.split(/\b[\s,\.-:;]*/),function(index, item) {if (item.length > 0) {i ++;}});$(field + '_count').html("<?php _e('Characters', 'wpseo') ?>: " + value.length + " | <?php _e('Words', 'wpseo') ?>: " + i);}$.each(['title', 'description', 'keywords'],function(index, item) {$('#_wpseo_edit_' + item).keyup(function() {wpseo_edit_counter(item);});wpseo_edit_counter(item);});});</script><?php if ($this->get_option('title_manually')) { ?><p><label for="_wpseo_edit_title"><span id="_wpseo_edit_title_count"></span><?php _e('Add title manually', 'wpseo') ?></label><input type="text" name="_wpseo_edit_title" id="_wpseo_edit_title" value="<?php echo $this->get_custom_value('title', true) ?>" /><input type="checkbox" name="_wpseo_edit_only" id="_wpseo_edit_only" value="1" <?php checked($this->get_custom_value('only'), 1) ?> /><label for="_wpseo_edit_only"><?php _e('Deactivate a automatic formatting and display just this value as the post title', 'wpseo') ?></label></p><?php } ?><?php if ($this->get_option('desc_manually')) { ?><p><label for="_wpseo_edit_description"><span id="_wpseo_edit_description_count"></span><?php _e('Add description manually', 'wpseo') ?></label><input type="text" name="_wpseo_edit_description" id="_wpseo_edit_description" value="<?php echo $this->get_custom_value('description', true) ?>" /></p><?php } ?><?php if ($this->get_option('key_manually')) { ?><p><label for="_wpseo_edit_keywords"><span id="_wpseo_edit_keywords_count"></span><?php _e('Add keywords manually', 'wpseo') ?></label><input type="text" name="_wpseo_edit_keywords" id="_wpseo_edit_keywords" value="<?php echo $this->get_custom_value('keywords', true) ?>" /></p><?php } ?><?php if ($this->get_option('noindex_manually')) { ?><p class="warning"><?php _e('Please note', 'wpseo') ?>: <?php _e('Assigned values manually switch the automatic generation off', 'wpseo') ?><br /><?php _e('wpSEO convince with their dynamic,', 'wpseo') ?> <a href="<?php echo $this->get_plugin_domain() ?>/support/#6" target="_blank"><?php _e('many settings can also be manually allocated', 'wpseo') ?></a></p><p><label for="_wpseo_edit_robots"><?php _e('Add Robots tag manually', 'wpseo') ?></label><input type="text" name="_wpseo_edit_robots" id="_wpseo_edit_robots" value="<?php echo $this->get_custom_value('robots') ?>" /></p><?php } ?><input type="hidden" name="_wpseo_edit_nonce" value="<?php echo wp_create_nonce('_wpseo_edit_nonce') ?>" /><?php }
    function show_admin_page()
    {
        if (isset($_FILES) && !empty($_FILES)) {
            $this->get_security_scan();
            check_admin_referer('wp_seo_export');
            if ($_FILES['xml']['error'] || !$_FILES['xml']['size']) { ?><div id="message" class="error"><p><strong><?php _e('Error during upload of the XML file.', 'wpseo') ?></strong></p></div><?php } else {
                $this->set_import_options($_FILES['xml']['tmp_name']); ?><div id="message" class="updated fade"><p><strong><?php _e('Options successfully imported.', 'wpseo') ?></strong></p></div><?php }
        } else if (isset($_POST) && isset($_POST['action']) && $_POST['action'] == 'edit') {
            $this->get_security_scan();
            check_admin_referer('wp_seo_form');
            $this->set_user_settings(); ?><div id="message" class="updated fade"><p><strong><?php _e('Settings saved.') ?></strong></p></div><?php } ?><div class="wrap wpseo"><?php if ($this->is_min_wp('2.7')) { ?><div class="icon32"><br /></div><?php } ?><h2><?php _e('wpSEO Options', 'wpseo') ?></h2><br class="clear" /><form name="wp_seo_form" id="wp_seo_form" action="" method="post"><input type="hidden" name="action" value="edit" /><?php wp_nonce_field('wp_seo_form') ?><div id="poststuff" class="ui-sortable"><div id="wp_seo_title_convert" class="postbox <?php echo $this->get_option('title_convert') ?>"><h3><?php _e('Edit page titles', 'wpseo') ?></h3><div class="inside"><table class="form-table"><tr><th colspan="2" scope="row"><label for="wp_seo_title_enable"><input type="checkbox" name="wp_seo_title_enable" id="wp_seo_title_enable" value="1" <?php checked($this->get_option('title_enable'), 1) ?> /><?php _e('Activate formatting of the title', 'wpseo') ?></label></th></tr><tr><th scope="row"><?php _e('Format', 'wpseo') ?></th><td><table><?php foreach (array_keys($this->groups) as $k) { ?><tr><td width="96"><?php echo $this->groups[$k]['name'] ?></td><td><select name="wp_seo_title_channel_<?php echo $k ?>" id="wp_seo_title_channel_<?php echo $k ?>" class="mini"><?php foreach ($this->misc['channel'] as $key => $value) {
                if (($k != 'category' && strpos($key, '%desc%') !== false) || ($k != 'single' && strpos($key, '%category%') !== false) || ($k != 'single' && $k != 'tagging' && strpos($key, '%tag%') !== false) || ($k == 'tagging' && (strpos($key, '%tag%') === false || strpos($key, '%title%') !== false)) || ($k == '404' && strpos($key, '%keywords%') !== false)) {
                    continue;
                } ?><option value="<?php echo $key ?>" <?php selected($this->get_option('title_channel_' . $k), $key) ?>><?php echo $value ?></option><?php } ?></select></td></tr><?php } ?></table><p><?php _e('Wildcards will be replaced in the selected order', 'wpseo') ?> <?php $this->show_help_link('title_format') ?></p></td></tr><tr><th scope="row"><?php _e('Separator', 'wpseo') ?></th><td><input type="text" name="wp_seo_title_separator" id="wp_seo_title_separator" class="medium" value="<?php echo $this->get_htmlentities_encode($this->get_option('title_separator'), true, true) ?>" /><p><?php _e('Special signs can be entered as HTML. Example: &amp;laquo; becomes &laquo;', 'wpseo') ?> <?php $this->show_help_link('title_separator') ?></p></td></tr><tr><th scope="row"><?php _e('Label', 'wpseo') ?></th><td><table><?php foreach (array_keys($this->groups) as $k) { ?><tr><td width="96"><?php echo $this->groups[$k]['name'] ?></td><td><input type="text" name="wp_seo_title_desc_<?php echo $k ?>" id="wp_seo_title_desc_<?php echo $k ?>" class="mini" value="<?php echo $this->get_htmlentities_encode($this->get_option('title_desc_' . $k), true, true) ?>" /></td></tr><?php } ?></table><p><?php _e('Only used if &quot;Format&quot; contains &quot;Label&quot;', 'wpseo') ?> <?php $this->show_help_link('title_area') ?></p></td></tr><tr><th scope="row"><?php _e('Home title', 'wpseo') ?></th><td><input type="text" name="wp_seo_title_homepage" id="wp_seo_title_homepage" class="medium" value="<?php echo $this->get_htmlentities_encode($this->get_option('title_homepage'), true, true) ?>" /><p><?php _e('By the output of the title it overwrites all previous defined values', 'wpseo') ?> <?php $this->show_help_link('title_homepage') ?></p></td></tr><tr><th scope="row"><?php _e('Page number', 'wpseo') ?></th><td><label for="wp_seo_title_number"><input type="checkbox" name="wp_seo_title_number" id="wp_seo_title_number" value="1" <?php checked($this->get_option('title_number'), 1) ?> /><?php _e('Activate page numeration', 'wpseo') ?></label><p><?php _e('Only displayed if archives, categories or search generate more than one page', 'wpseo') ?> <?php $this->show_help_link('title_count') ?></p></td></tr><tr><th scope="row"><?php _e('Author of a post', 'wpseo') ?></th><td><label for="wp_seo_title_author"><input type="checkbox" name="wp_seo_title_author" id="wp_seo_title_author" value="1" <?php checked($this->get_option('title_author'), 1) ?> /><?php _e('Show author of current post', 'wpseo') ?></label><p><?php _e('Only displayed if posts or static pages are called and &quot;Format&quot; contains &quot;Title&quot;', 'wpseo') ?> <?php $this->show_help_link('title_author') ?></p></td></tr><tr><th scope="row"><?php _e('Clean up title', 'wpseo') ?></th><td><label for="wp_seo_title_cleanup"><input type="checkbox" name="wp_seo_title_cleanup" id="wp_seo_title_cleanup" value="1" <?php checked($this->get_option('title_cleanup'), 1) ?> /><?php _e('Remove HTML tags from title', 'wpseo') ?></label><p><?php _e('With HTML tags formatted post titles will be displayed validated', 'wpseo') ?> <?php $this->show_help_link('title_cleanup') ?></p></td></tr><tr><th scope="row"><?php _e('Add title manually', 'wpseo') ?></th><td><label for="wp_seo_title_manually"><input type="checkbox" name="wp_seo_title_manually" id="wp_seo_title_manually" value="1" <?php checked($this->get_option('title_manually'), 1) ?> /><?php _e('Enter the value directly while writing the post or page in the meta box &quot;wpSEO Options&quot;', 'wpseo') ?></label><p><?php _e('Assigned values manually switch the automatic generation off', 'wpseo') ?> <?php $this->show_help_link('title_manually') ?></p></td></tr></table><p class="submit"><input type="submit" class="button-primary" name="wp_seo_title_submit" value="<?php _e('Save Changes') ?>" /></p></div></div></div><div id="poststuff" class="ui-sortable"><div id="wp_seo_desc_convert" class="postbox <?php echo $this->get_option('desc_convert') ?>"><h3><?php _e('Edit meta description', 'wpseo') ?></h3><div class="inside"><table class="form-table"><tr><th colspan="2" scope="row"><label for="wp_seo_desc_enable"><input type="checkbox" name="wp_seo_desc_enable" id="wp_seo_desc_enable" value="1" <?php checked($this->get_option('desc_enable'), 1) ?> /><?php _e('Activate formatting of the meta description', 'wpseo') ?></label></th></tr><tr><th scope="row"><?php _e('Default', 'wpseo') ?></th><td><input type="text" name="wp_seo_desc_default" id="wp_seo_desc_default" class="medium" value="<?php echo $this->get_htmlentities_encode($this->get_option('desc_default'), true, true) ?>" /><p><?php if ($this->get_option('desc_default')) { ?><?php _e('Only used if &quot;Default&quot; selected in the &quot;Dynamic value&quot;', 'wpseo') ?> <?php $this->show_help_link('desc_default') ?><?php } else if (get_bloginfo('description')) { ?><?php _e('Suggestion', 'wpseo') ?>: <em><?php echo get_bloginfo('description') ?></em> (<a href="#" id="accept_suggestion"><?php _e('Accept', 'wpseo') ?>?</a>)<?php } ?></p></td></tr><tr><th scope="row"><?php _e('Dynamic value', 'wpseo') ?></th><td><table><?php foreach (array_keys($this->groups) as $key) { ?><tr><td width="96"><?php echo $this->groups[$key]['name'] ?></td><td><select name="wp_seo_desc_<?php echo $key ?>" id="wp_seo_desc_<?php echo $key ?>" class="mini"><?php foreach ($this->groups[$key]['desc'] as $k => $v) {
                if ($key == 'tagging' && $k == 3 && !$this->is_min_wp('2.8')) continue; ?><option value="<?php echo $k ?>" <?php selected($this->get_option('desc_' . $key), $k) ?>><?php echo $v ?></option><?php } ?></select></td></tr><?php } ?></table><p><?php _e('Description is dynamically created from the selected value', 'wpseo') ?> <?php $this->show_help_link('desc_dynamic') ?></p></td></tr><tr><th scope="row"><?php _e('Number of words', 'wpseo') ?></th><td><select name="wp_seo_desc_counter" id="wp_seo_desc_counter" class="medium"><?php for ($k = 5; $k <= 50; $k = $k + 5) { ?><option value="<?php echo $k ?>" <?php selected($this->get_option('desc_counter'), $k) ?>><?php echo $k ?></option><?php } ?></select><p><?php _e('Maximum word count before description is truncated', 'wpseo') ?> <?php $this->show_help_link('desc_counter') ?></p></td></tr><tr><th scope="row"><?php _e('Complete Sentences', 'wpseo') ?></th><td><label for="wp_seo_desc_tender"><input type="checkbox" name="wp_seo_desc_tender" id="wp_seo_desc_tender" value="1" <?php checked($this->get_option('desc_tender'), 1) ?> /><?php _e('Under consideration of the number of words, display until next ending of sentence', 'wpseo') ?></label><p><?php _e('Dot, question- and exclamation mark indicates the ending of a sentence', 'wpseo') ?> <?php $this->show_help_link('desc_tender') ?></p></td></tr><tr><th scope="row"><?php _e('Add description manually', 'wpseo') ?></th><td><label for="wp_seo_desc_manually"><input type="checkbox" name="wp_seo_desc_manually" id="wp_seo_desc_manually" value="1" <?php checked($this->get_option('desc_manually'), 1) ?> /><?php _e('Enter the value directly while writing the post or page in the meta box &quot;wpSEO Options&quot;', 'wpseo') ?></label><p><?php _e('Assigned values manually switch the automatic generation off', 'wpseo') ?> <?php $this->show_help_link('desc_manually') ?></p></td></tr></table><p class="submit"><input type="submit" class="button-primary" name="wp_seo_title_submit" value="<?php _e('Save Changes') ?>" /></p></div></div></div><div id="poststuff" class="ui-sortable"><div id="wp_seo_key_convert" class="postbox <?php echo $this->get_option('key_convert') ?>"><h3><?php _e('Edit meta keywords', 'wpseo') ?></h3><div class="inside"><table class="form-table"><tr><th colspan="2" scope="row"><label for="wp_seo_key_enable"><input type="checkbox" name="wp_seo_key_enable" id="wp_seo_key_enable" value="1" <?php checked($this->get_option('key_enable'), 1) ?> /><?php _e('Activate formatting of the meta keywords', 'wpseo') ?></label></th></tr><tr><th scope="row"><?php _e('Default', 'wpseo') ?></th><td><input type="text" name="wp_seo_key_default" id="wp_seo_key_default" class="medium" value="<?php echo $this->get_htmlentities_encode($this->get_option('key_default'), true, true) ?>" /><p><?php _e('Only used if &quot;Default&quot; selected in the &quot;Dynamic value&quot;', 'wpseo') ?></p></td></tr><tr><th scope="row"><?php _e('Dynamic value', 'wpseo') ?></th><td><table><?php foreach (array_keys($this->groups) as $key) { ?><tr><td width="96"><?php echo $this->groups[$key]['name'] ?></td><td><select name="wp_seo_key_<?php echo $key ?>" id="wp_seo_key_<?php echo $key ?>" class="mini"><?php foreach ($this->groups[$key]['key'] as $k => $v) { ?><option value="<?php echo $k ?>" <?php selected($this->get_option('key_' . $key), $k) ?>><?php echo $v ?></option><?php } ?></select></td></tr><?php } ?></table><p><?php _e('Keywords are dynamically created from the selected value', 'wpseo') ?></p></td></tr><tr><th scope="row"><?php _e('Number of words', 'wpseo') ?></th><td><select name="wp_seo_key_counter" id="wp_seo_key_counter" class="medium"><?php for ($k = 4; $k <= 30; $k = $k + 2) { ?><option value="<?php echo $k ?>" <?php selected($this->get_option('key_counter'), $k) ?>><?php echo $k ?></option><?php } ?></select><p><?php _e('Maximum word count before description is truncated', 'wpseo') ?> <?php $this->show_help_link('key_counter') ?></p></td></tr><tr><th scope="row"><?php _e('Short words', 'wpseo') ?></th><td><select name="wp_seo_key_length" id="wp_seo_key_length" class="medium"><?php for ($k = 1; $k <= 10; $k ++) { ?><option value="<?php echo $k ?>" <?php selected($this->get_option('key_length'), $k) ?>><?php echo $k ?></option><?php } ?></select><p><?php _e('Words with a few characters will be filtered from the dynamic value', 'wpseo') ?></p></td></tr><tr><th scope="row"><?php _e('Blacklist', 'wpseo') ?></th><td><textarea name="wp_seo_key_blacklist" id="wp_seo_key_blacklist"><?php echo $this->get_htmlentities_encode($this->get_option('key_blacklist'), true, true) ?></textarea><p><?php _e('These keywords will be ignored. Value is case-sensitive and separated by a space', 'wpseo') ?> <?php $this->show_help_link('key_blacklist') ?></p></td></tr><tr><th scope="row"><?php _e('Completion', 'wpseo') ?></th><td><label for="wp_seo_key_complete"><input type="checkbox" name="wp_seo_key_complete" id="wp_seo_key_complete" value="1" <?php checked($this->get_option('key_complete'), 1) ?> /><?php _e('Activate automatic completion', 'wpseo') ?></label><p><?php _e('If the keywords tag consists of less words than allowed, &quot;Default&quot; entries will be used', 'wpseo') ?></p></td></tr><tr><th scope="row"><?php _e('Nouns', 'wpseo') ?></th><td><label for="wp_seo_key_substantive"><input type="checkbox" name="wp_seo_key_substantive" id="wp_seo_key_substantive" value="1" <?php checked($this->get_option('key_substantive'), 1) ?> /><?php _e('Use only nouns as keywords', 'wpseo') ?></label><p><?php _e('Only single nouns will be used as keywords', 'wpseo') ?></p></td></tr><tr><th scope="row"><?php _e('Relevance', 'wpseo') ?></th><td><label for="wp_seo_key_relevance"><input type="checkbox" name="wp_seo_key_relevance" id="wp_seo_key_relevance" value="1" <?php checked($this->get_option('key_relevance'), 1) ?> /><?php _e('Activate relevance for keywords', 'wpseo') ?></label><p><?php _e('Keywords in post or title will be counted, the most frequent ones will be positioned first', 'wpseo') ?></p></td></tr><tr><th scope="row"><?php _e('Manual tagging', 'wpseo') ?></th><td><label for="wp_seo_key_labeling"><input type="checkbox" name="wp_seo_key_labeling" id="wp_seo_key_labeling" value="1" <?php checked($this->get_option('key_labeling'), 1) ?> /><?php _e('Detect manually tagged keywords in a post', 'wpseo') ?></label><p><?php _e('With [wpseo]...[/wpseo] tagged words will be interpreted as most relevant keywords', 'wpseo') ?> <?php $this->show_help_link('key_labelling') ?></p></td></tr><tr><th scope="row"><?php _e('HTML highlighting', 'wpseo') ?></th><td><label for="wp_seo_key_xhtml"><input type="checkbox" name="wp_seo_key_xhtml" id="wp_seo_key_xhtml" value="1" <?php checked($this->get_option('key_xhtml'), 1) ?> /><?php _e('Words between HTML tags will be interpreted as most relevant keywords', 'wpseo') ?></label><p><?php _e('Detects the following XHTML tags: &lt;em&gt;...&lt;/em&gt;, &lt;strong&gt;...&lt;/strong&gt;', 'wpseo') ?> <?php $this->show_help_link('key_labelling') ?></p></td></tr><tr><th scope="row"><?php _e('Add keywords manually', 'wpseo') ?></th><td><label for="wp_seo_key_manually"><input type="checkbox" name="wp_seo_key_manually" id="wp_seo_key_manually" value="1" <?php checked($this->get_option('key_manually'), 1) ?> /><?php _e('Enter the value directly while writing the post or page in the meta box &quot;wpSEO Options&quot;', 'wpseo') ?></label><p><?php _e('Assigned values manually switch the automatic generation off', 'wpseo') ?> <?php $this->show_help_link('key_manually') ?></p></td></tr></table><p class="submit"><input type="submit" class="button-primary" name="wp_seo_title_submit" value="<?php _e('Save Changes') ?>" /></p></div></div></div><div id="poststuff" class="ui-sortable"><div id="wp_seo_noindex_convert" class="postbox <?php echo $this->get_option('noindex_convert') ?>"><h3><?php _e('Avoid duplicate content', 'wpseo') ?></h3><div class="inside"><table class="form-table"><tr><th colspan="2" scope="row"><label for="wp_seo_noindex_enable"><input type="checkbox" name="wp_seo_noindex_enable" id="wp_seo_noindex_enable" value="1" <?php checked($this->get_option('noindex_enable'), 1) ?> /><?php _e('Activate integration of the Robots tag', 'wpseo') ?></label></th></tr><tr><th scope="row"><?php _e('Robots tag', 'wpseo') ?></th><td><table><?php foreach (array_keys($this->groups) as $key) { ?><tr><td width="96"><?php echo $this->groups[$key]['name'] ?></td><td><select name="wp_seo_noindex_<?php echo $key ?>" id="wp_seo_noindex_<?php echo $key ?>" class="mini"><?php foreach ($this->noindex as $k => $v) { ?><option value="<?php echo $k ?>" <?php selected($this->get_option('noindex_' . $key), $k) ?>><?php echo $v ?></option><?php } ?></select></td></tr><?php } ?><tr><td width="96"><?php _e('Attachment', 'wpseo') ?></td><td><select name="wp_seo_noindex_attachment" id="wp_seo_noindex_attachment" class="mini"><?php foreach ($this->noindex as $k => $v) { ?><option value="<?php echo $k ?>" <?php selected($this->get_option('noindex_attachment'), $k) ?>><?php echo $v ?></option><?php } ?></select></td></tr></table><p><?php _e('The value &quot;noindex&quot; excludes an area to be indexed by search engines', 'wpseo') ?> <?php $this->show_help_link('misc_robots') ?></p></td></tr><tr><th scope="row"><?php _e('Suppress Robots tag', 'wpseo') ?></th><td><label for="wp_seo_noindex_hidden"><input type="checkbox" name="wp_seo_noindex_hidden" id="wp_seo_noindex_hidden" value="1" <?php checked($this->get_option('noindex_hidden'), 1) ?> /><?php _e("Don't create Robots tag in source code, if it is the value &quot;index&quot; or &quot;index, follow&quot;", 'wpseo') ?></label><p><?php _e('Google expects standard value &quot;index, follow&quot;', 'wpseo') ?> <?php $this->show_help_link('noindex_suppress') ?></p></td></tr><tr><th scope="row"><?php _e('Creates Canonical tag', 'wpseo') ?></th><td><label for="wp_seo_noindex_canonical"><input type="checkbox" name="wp_seo_noindex_canonical" id="wp_seo_noindex_canonical" value="1" <?php checked($this->get_option('noindex_canonical'), 1) ?> /><?php _e('Generates automatically the preferred URL and outputs as Canonical tag value', 'wpseo') ?></label><p><?php _e('Only usage in areas with &quot;index&quot; as value in Robots tag', 'wpseo') ?> <?php $this->show_help_link('noindex_canonical') ?></p></td></tr><tr><th scope="row"><?php _e('Add Robots tag manually', 'wpseo') ?></th><td><label for="wp_seo_noindex_manually"><input type="checkbox" name="wp_seo_noindex_manually" id="wp_seo_noindex_manually" value="1" <?php checked($this->get_option('noindex_manually'), 1) ?> /><?php _e('Enter the value directly while writing the post or page in the meta box &quot;wpSEO Options&quot;', 'wpseo') ?></label><p><?php _e('Assigned values manually switch the automatic generation off', 'wpseo') ?> <?php $this->show_help_link('misc_robots_custom') ?></p></td></tr></table><p class="submit"><input type="submit" class="button-primary" name="wp_seo_title_submit" value="<?php _e('Save Changes') ?>" /></p></div></div></div><div id="poststuff" class="ui-sortable"><div id="wp_seo_speed_settings" class="postbox <?php echo $this->get_option('speed_settings') ?>"><h3><?php _e('Performance management', 'wpseo') ?></h3><div class="inside less"><table class="form-table"><tr><th scope="row"><?php _e('Check for duplicates', 'wpseo') ?></th><td><label for="wp_seo_speed_nocheck"><input type="checkbox" name="wp_seo_speed_nocheck" id="wp_seo_speed_nocheck" value="1" <?php checked($this->get_option('speed_nocheck'), 1) ?> /></label><?php _e('<strong>No Check</strong> for existing meta data and title in the source code, as these are removed from the theme template manually', 'wpseo') ?> <?php $this->show_help_link('speed_nocheck') ?></td></tr></table><p class="submit"><input type="submit" class="button-primary" name="wp_seo_title_submit" value="<?php _e('Save Changes') ?>" /></p></div></div></div><div id="poststuff" class="ui-sortable"><div id="wp_seo_misc_settings" class="postbox <?php echo $this->get_option('misc_settings') ?>"><h3><?php _e('Other Settings', 'wpseo') ?></h3><div class="inside"><table class="form-table"><tr><th scope="row"><?php _e('Static first page', 'wpseo') ?></th><td><label for="wp_seo_misc_static"><input type="checkbox" name="wp_seo_misc_static" id="wp_seo_misc_static" value="1" <?php checked($this->get_option('misc_static'), 1) ?> /><?php _e('The static first page not to treat a &quot;Page&quot; but as &quot;Home&quot;', 'wpseo') ?></label><span><?php $this->show_help_link('misc_static') ?></span></td></tr><?php if ($this->is_min_wp('2.7')) { ?><tr><th scope="row"><?php _e('SEO Monitor', 'wpseo') ?></th><td><label for="wp_seo_misc_monitor"><input type="checkbox" name="wp_seo_misc_monitor" id="wp_seo_misc_monitor" value="1" <?php checked($this->get_option('misc_monitor'), 1) ?> /><?php _e('Display SEO factors on the dashboard as a widget', 'wpseo') ?></label><span><?php $this->show_help_link('misc_monitor') ?></span></td></tr><?php } ?><tr><th scope="row"><?php _e('Highlighting for AdSense', 'wpseo') ?></th><td><label for="wp_seo_misc_section"><input type="checkbox" name="wp_seo_misc_section" id="wp_seo_misc_section" value="1" <?php checked($this->get_option('misc_section'), 1) ?> /><?php _e('Highlight posts for Google Adsense Bot with &lt;!-- google_ad_section ... --&gt;', 'wpseo') ?></label><span><?php $this->show_help_link('misc_section') ?></span></td></tr><tr><th scope="row"><?php _e('ODP snippet', 'wpseo') ?></th><td><label for="wp_seo_misc_noodp"><input type="checkbox" name="wp_seo_misc_noodp" id="wp_seo_misc_noodp" value="1" <?php checked($this->get_option('misc_noodp'), 1) ?> /><?php _e('Add the &lt;meta name=&quot;robots&quot; content=&quot;noodp&quot; /&gt; meta tag to the source code', 'wpseo') ?></label><span><?php $this->show_help_link('misc_noodp') ?></span></td></tr><tr><th scope="row"><?php _e('Noarchive snippet', 'wpseo') ?></th><td><label for="wp_seo_misc_noarchive"><input type="checkbox" name="wp_seo_misc_noarchive" id="wp_seo_misc_noarchive" value="1" <?php checked($this->get_option('misc_noarchive'), 1) ?> /><?php _e('Add the &lt;meta name=&quot;robots&quot; content=&quot;noarchive&quot; /&gt; meta tag to the source code', 'wpseo') ?></label><span><?php $this->show_help_link('misc_noarchive') ?></span></td></tr><tr><th scope="row"><?php _e('Protect RSS Feeds', 'wpseo') ?></th><td><label for="wp_seo_misc_feed"><input type="checkbox" name="wp_seo_misc_feed" id="wp_seo_misc_feed" value="1" <?php checked($this->get_option('misc_feed'), 1) ?> /><?php _e('Prevent indexing of RSS Feeds by search engines', 'wpseo') ?></label><span><?php $this->show_help_link('misc_feed') ?></span></td></tr><tr><th scope="row"><?php _e('File renaming', 'wpseo') ?></th><td><label for="wp_seo_misc_attachment"><input type="checkbox" name="wp_seo_misc_attachment" id="wp_seo_misc_attachment" value="1" <?php checked($this->get_option('misc_attachment'), 1) ?> /><?php _e('Rename files during upload and use title as file name', 'wpseo') ?></label><span><?php $this->show_help_link('misc_attachment') ?></span></td></tr><tr><th scope="row"><?php _e('Order of meta tags', 'wpseo') ?></th><td><select name="wp_seo_misc_order" class="medium"><?php foreach ($this->misc['orderby'] as $key => $value) { ?><option value="<?php echo $key ?>" <?php selected($this->get_option('misc_order'), $key) ?>><?php echo $value ?></option><?php } ?></select><p><?php _e('The order of meta tags in the source code of blog sites', 'wpseo') ?> <?php $this->show_help_link('misc_order') ?></p></td></tr></table><p class="submit"><input type="submit" class="button-primary" name="wp_seo_title_submit" value="<?php _e('Save Changes') ?>" /></p></div></div></div></form><div id="poststuff" class="ui-sortable"><div id="wp_seo_export_settings" class="postbox <?php echo $this->get_option('export_settings') ?>"><h3><?php _e('Import / Export', 'wpseo') ?></h3><div class="inside less"><table class="form-table"><tr><td><button id="wpseo_export_button" class="button-secondary"><?php _e('Download options as XML', 'wpseo') ?></button></td></tr></table></div><div class="inside less"><form name="wp_seo_export" action="" method="post" enctype="multipart/form-data"><?php wp_nonce_field('wp_seo_export') ?><table class="form-table"><tr><td><input type="file" name="xml" /></td></tr><tr><td><input type="submit" class="button-secondary" value="<?php _e('Read XML file and set values accordingly', 'wpseo') ?>" /></td></tr></table><p><?php _e('Important: Existing settings will be overwritten.', 'wpseo') ?></p></form></div></div></div></div><?php }
}
$GLOBALS['wpSEO'] = new wpSEO();?>