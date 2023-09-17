<?php
/**
 * Plugin Name:     Thumbnail
 * Plugin URL:      https://rwsite.ru
 * Description:     WordPress thumbnail based by Kama Thumbnail. Support WebP format and other features. Creates post thumbnails on fly and cache it. The Image for the thumbnail is taken from: WP post thumbnail / first img in post content / first post attachment img. To creat thumb for any img in post content add class "mini" to img and resize it in visual editor.  In theme/plugin use functions: <code>kama_thumb_a_img()</code>, <code>kama_thumb_img()</code>, <code>kama_thumb_src()</code>.
 * Version:         3.4.1
 * Text Domain:     thumbnail
 * Domain Path:     /languages
 * Author:          Aleksey Tikhomirov <alex@rwsite.ru>
 * Author URI:      https://rwsite.ru
 *
 * Tags:            thumbnail
 * Requires at least: 4.6
 * Tested up to:    5.3.0
 * Requires PHP:    7.4+
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

define('KT_MAIN_FILE', __FILE__);
define('KT_PATH', wp_normalize_path(dirname(__FILE__) . '/'));

if (strpos(KT_PATH, wp_normalize_path(WP_PLUGIN_DIR)) ||
    strpos(KT_PATH, wp_normalize_path(WPMU_PLUGIN_DIR))) { // как плагин
    define('KT_URL', plugin_dir_url(__FILE__));
} else {
    define('KT_URL', strtr(KT_PATH, [wp_normalize_path(get_template_directory()) => get_template_directory_uri()]));
}

require_once 'Kama_Thumbnail_Admin_Part.php';
require_once 'Kama_Thumbnail_Clear_Cache.php';
require_once 'Kama_Thumbnail_Plugin.php';
require_once 'Kama_Make_Thumb.php';
require_once 'functions.php';

function kama_thumbnail_init()
{
    if (!defined('DOING_AJAX')) {
        load_plugin_textdomain('thumbnail', false, basename(KT_PATH) . '/languages');
    }
    $GLOBALS['Kama_Thumbnail'] = new Kama_Thumbnail_Plugin();
    if (is_admin() && !wp_doing_ajax()) {
        require_once __DIR__ . '/upgrade.php';
        kama_thumb_upgrade();
    }
}

add_action('init', 'kama_thumbnail_init'); // подключаем попозже, чтобы можно было например из темы использовать хуки