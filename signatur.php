<?php

/**
 * Plugin Name: Signatures
 * Description: Creates and manages email signature templates.
 * Version: 1.0.0
 * Author: Relevant AS (Andrii Boiko)
 * Plugin URI: https://relevant.no/
 * Author URI: https://relevant.no/
 * Text Domain: signatur
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'signatur_load_textdomain');
add_action('admin_menu', 'signatur_register_admin_pages');

/**
 * Load translations bundled with the plugin.
 */
function signatur_load_textdomain()
{
    load_plugin_textdomain(
        'signatur',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

/**
 * Register the Signatures menu and its two initial admin pages.
 */
function signatur_register_admin_pages()
{
    add_menu_page(
        __('All signatures', 'signatur'),
        __('Signatures', 'signatur'),
        'manage_options',
        'signatur-all-signatures',
        'signatur_render_all_signatures_page',
        'dashicons-email-alt',
        25
    );

    add_submenu_page(
        'signatur-all-signatures',
        __('All signatures', 'signatur'),
        __('All signatures', 'signatur'),
        'manage_options',
        'signatur-all-signatures',
        'signatur_render_all_signatures_page'
    );

    add_submenu_page(
        'signatur-all-signatures',
        __('Add signature', 'signatur'),
        __('Add signature', 'signatur'),
        'manage_options',
        'signatur-add-signature',
        'signatur_render_add_signature_page'
    );
}

/**
 * Render the signatures list page.
 */
function signatur_render_all_signatures_page()
{
?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('All signatures', 'signatur'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=signatur-add-signature')); ?>" class="page-title-action">
            <?php esc_html_e('Add signature', 'signatur'); ?>
        </a>
        <hr class="wp-header-end">

        <p><?php esc_html_e('Your email signatures will appear here.', 'signatur'); ?></p>
    </div>
<?php
}

/**
 * Render the signature creation page.
 */
function signatur_render_add_signature_page()
{
?>
    <div class="wrap">
        <h1><?php esc_html_e('Add signature', 'signatur'); ?></h1>
    </div>
<?php
}
