<?php

/**
 * Plugin Name: Signatures
 * Description: Creates and manages email signature templates.
 * Version: 1.6.0
 * Author: Relevant AS (Andrii Boiko)
 * Plugin URI: https://relevant.no/
 * Author URI: https://relevant.no/
 * Text Domain: signatur
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/Andrusi4ka/Signatur
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SIGNATUR_VERSION', '1.6.0');
define('SIGNATUR_DB_VERSION', '1.5.1');
define('SIGNATUR_PLUGIN_FILE', __FILE__);
define('SIGNATUR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIGNATUR_REPOSITORY', 'https://github.com/Andrusi4ka/Signatur/');

require_once SIGNATUR_PLUGIN_DIR . 'includes/i18n.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/roles.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/wpbakery.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/database.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/admin-pages.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/actions.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/shortcode.php';

register_activation_hook(__FILE__, 'signatur_activate');
add_action('plugins_loaded', 'signatur_load_textdomain');
add_action('plugins_loaded', 'signatur_maybe_upgrade_database');
add_action('admin_menu', 'signatur_register_admin_pages');
add_action('admin_menu', 'signatur_register_settings_page', 20);
add_action('admin_head', 'signatur_menu_icon_style');
add_action('admin_init', 'signatur_register_roles_setting');
add_action('admin_enqueue_scripts', 'signatur_enqueue_settings_assets');
add_filter('user_has_cap', 'signatur_grant_caps', 10, 4);
foreach (array('add_option_', 'update_option_', 'delete_option_') as $signatur_hook) {
    add_action($signatur_hook . SIGNATUR_ROLES_OPTION, 'signatur_forget_roles');
}
unset($signatur_hook);
signatur_wpbakery_init();
add_action('admin_enqueue_scripts', 'signatur_enqueue_builder_assets');
add_action('wp_enqueue_scripts', 'signatur_register_frontend_assets');
add_action('admin_post_signatur_save_signature', 'signatur_handle_save_signature');
add_action('admin_post_signatur_delete_signature', 'signatur_handle_delete_signature');
add_action('wp_ajax_signatur_upload_image', 'signatur_handle_upload_image');
add_action('init', 'signatur_register_shortcode');
add_action('plugins_loaded', 'signatur_updates');

/**
 * Updates from GitHub: a release (tag "vX.Y.Z" with the signatur.zip built
 * by .github/workflows/release.yml) shows up under Plugins like any other
 * update. The same library adds "View details" (from readme.txt) and
 * "Check for updates" under the plugin there.
 */
function signatur_updates()
{
    $library = SIGNATUR_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

    if ((! is_admin() && ! wp_doing_cron()) || ! file_exists($library)) {
        return;
    }

    require_once $library;

    $checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        SIGNATUR_REPOSITORY,
        SIGNATUR_PLUGIN_FILE,
        'signatur'
    );

    // The release's zip, which holds just the plugin (not the repository's dev files).
    $checker->getVcsApi()->enableReleaseAssets('/signatur\.zip($|[?&#])/i');

    // Without a release yet, the details come from main.
    $checker->setBranch('main');
}
