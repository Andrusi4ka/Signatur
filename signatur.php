<?php

/**
 * Plugin Name: Signatures
 * Description: Creates and manages email signature templates.
 * Version: 1.5.0
 * Author: Relevant AS (Andrii Boiko)
 * Plugin URI: https://relevant.no/
 * Author URI: https://relevant.no/
 * Text Domain: signatur
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SIGNATUR_VERSION', '1.5.53');
define('SIGNATUR_DB_VERSION', '1.5.1');
define('SIGNATUR_PLUGIN_FILE', __FILE__);
define('SIGNATUR_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once SIGNATUR_PLUGIN_DIR . 'includes/i18n.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/database.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/admin-pages.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/actions.php';
require_once SIGNATUR_PLUGIN_DIR . 'includes/shortcode.php';

register_activation_hook(__FILE__, 'signatur_activate');
add_action('plugins_loaded', 'signatur_load_textdomain');
add_action('plugins_loaded', 'signatur_maybe_upgrade_database');
add_action('admin_menu', 'signatur_register_admin_pages');
add_action('admin_enqueue_scripts', 'signatur_enqueue_builder_assets');
add_action('wp_enqueue_scripts', 'signatur_register_frontend_assets');
add_action('admin_post_signatur_save_signature', 'signatur_handle_save_signature');
add_action('admin_post_signatur_delete_signature', 'signatur_handle_delete_signature');
add_action('wp_ajax_signatur_upload_image', 'signatur_handle_upload_image');
add_action('init', 'signatur_register_shortcode');
