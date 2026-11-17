<?php

/**
 * WPBakery Page Builder element that wraps the [signatur] shortcode (as WP
 * Swiper's): a Signature list with "Edit signature" / "Add signature" under
 * it, and whether the "Copy signature" button shows.
 */

if (! defined('ABSPATH')) {
    exit;
}

// The "Edit signature" and "Add signature" buttons under the list (a param type of its own).
define('SIGNATUR_VC_EDIT_PARAM', 'signatur_edit');

// The Signature list again (wpbakery.js, once back from adding one): its AJAX action.
define('SIGNATUR_VC_LIST_ACTION', 'signatur_vc_signatures');

function signatur_wpbakery_init()
{
    add_action('vc_before_init', 'signatur_wpbakery_map');
    add_action('wp_ajax_' . SIGNATUR_VC_LIST_ACTION, 'signatur_wpbakery_ajax_signatures');
}

function signatur_wpbakery_map()
{
    // Not an element at all for roles not given it (Signatures → Settings;
    // Administrators always). Pages still show the signatures: WPBakery
    // keeps the shortcode's own handler.
    if (! current_user_can(SIGNATUR_CAP_WPBAKERY)) {
        return;
    }

    if (function_exists('vc_add_shortcode_param')) {
        vc_add_shortcode_param(
            SIGNATUR_VC_EDIT_PARAM,
            'signatur_wpbakery_edit_param',
            plugins_url('assets/js/wpbakery.js', SIGNATUR_PLUGIN_FILE) . '?ver=' . rawurlencode(SIGNATUR_VERSION)
        );
    }

    if (function_exists('vc_lean_map')) {
        // Lean map: settings (and the signatures query) load only when the editor needs them.
        vc_lean_map('signatur', 'signatur_wpbakery_settings');
    }
}

/**
 * The "Add element" tab: with the Woodmart theme its own "Theme elements",
 * named as the theme names it; otherwise WPBakery's "Content".
 */
function signatur_wpbakery_category()
{
    if (function_exists('woodmart_get_tab_title_category_for_wpb')) {
        return woodmart_get_tab_title_category_for_wpb(esc_html__('Theme elements', 'woodmart')); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- the theme's own tab name.
    }

    return __('Content', 'signatur');
}

/**
 * The element's definition.
 */
function signatur_wpbakery_settings()
{
    return array(
        'name'        => __('Signatures', 'signatur'),
        'base'        => 'signatur',
        'icon'        => plugins_url('assets/icon.svg', SIGNATUR_PLUGIN_FILE),
        'category'    => signatur_wpbakery_category(),
        // Last in the "Add element" list, as WP Swiper's.
        'weight'      => -100,
        'description' => __('Email signature created in Signatures', 'signatur'),
        'params'      => array(
            array(
                'type'        => 'dropdown',
                'heading'     => __('Signature', 'signatur'),
                'param_name'  => 'id',
                'value'       => signatur_wpbakery_signature_options(),
                'admin_label' => true,
                'description' => sprintf(
                    /* translators: %s: link to the signatures screen */
                    __('Create and edit signatures under %s.', 'signatur'),
                    '<a href="' . esc_url(admin_url('admin.php?page=signatur-all-signatures')) . '" target="_blank">' . esc_html__('Signatures', 'signatur') . '</a>'
                ),
            ),
            // Opens the chosen signature's edit screen in a new tab
            // (wpbakery.js keeps it pointing at the signature chosen above).
            // Saves nothing in the shortcode.
            array(
                'type'       => SIGNATUR_VC_EDIT_PARAM,
                'heading'    => '',
                'param_name' => SIGNATUR_VC_EDIT_PARAM,
            ),
            // The shortcode's copy="yes|no". Yes first: WPBakery leaves the
            // first value out of the shortcode, and the shortcode's own
            // default is yes.
            array(
                'type'       => 'dropdown',
                'heading'    => __('"Copy signature" button', 'signatur'),
                'param_name' => 'copy',
                'value'      => array(
                    __('Show', 'signatur') => 'yes',
                    __('Hide', 'signatur') => 'no',
                ),
                'description' => __('Hidden, visitors only see the signature and cannot fill in its fields.', 'signatur'),
            ),
        ),
    );
}

/**
 * The buttons under the Signature list: "Edit signature", a link to the
 * edit screen of the signature chosen (wpbakery.js sets it and hides it
 * while none is), and "Add signature" for those who may add one; both open
 * in a new tab. Back from adding one, wpbakery.js asks for the list again
 * and chooses the new signature. Its field is empty, so WPBakery adds
 * nothing to the shortcode.
 */
function signatur_wpbakery_edit_param($settings, $value)
{
    unset($value);

    $name = isset($settings['param_name']) ? $settings['param_name'] : SIGNATUR_VC_EDIT_PARAM;
    $add  = '';

    if (current_user_can(SIGNATUR_CAP_CREATE)) {
        $add = sprintf(
            ' <a class="button signatur-vc-edit__add" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
            esc_url(admin_url('admin.php?page=signatur-add-signature')),
            esc_html__('Add signature', 'signatur')
        );
    }

    $list = add_query_arg(
        array(
            'action'   => SIGNATUR_VC_LIST_ACTION,
            '_wpnonce' => wp_create_nonce(SIGNATUR_VC_LIST_ACTION),
        ),
        admin_url('admin-ajax.php')
    );

    return sprintf(
        '<div class="signatur-vc-edit" data-url="%1$s" data-list="%6$s"><a class="button signatur-vc-edit__link" href="#" target="_blank" rel="noopener" hidden>%2$s</a>%7$s<p class="description signatur-vc-edit__note" style="margin:6px 0 0">%5$s</p><input type="hidden" name="%3$s" class="wpb_vc_param_value %3$s %4$s" value=""></div>',
        esc_url(admin_url('admin.php?page=signatur-add-signature&id=')),
        esc_html__('Edit signature', 'signatur'),
        esc_attr($name),
        esc_attr(SIGNATUR_VC_EDIT_PARAM),
        esc_html__('Opens in a new tab. Back here, a signature saved there is in the list, chosen.', 'signatur'),
        esc_url($list),
        $add // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
    );
}

/**
 * The signatures the list offers, by title.
 *
 * @return array [['id' => int, 'label' => string]]
 */
function signatur_wpbakery_signatures()
{
    global $wpdb;

    $rows = $wpdb->get_results(
        'SELECT id, title FROM ' . signatur_get_table_name() . ' ORDER BY title ASC, id ASC'
    );

    $list = array();

    foreach ((array) $rows as $row) {
        $title  = '' !== (string) $row->title ? $row->title : __('(no title)', 'signatur');
        $list[] = array(
            'id'    => (int) $row->id,
            'label' => sprintf('%s (#%d)', $title, $row->id),
        );
    }

    return $list;
}

/**
 * WPBakery dropdown values: label => value.
 */
function signatur_wpbakery_signature_options()
{
    $options = array(__('— Select signature —', 'signatur') => '');

    foreach (signatur_wpbakery_signatures() as $signature) {
        $options[$signature['label']] = (string) $signature['id'];
    }

    return $options;
}

/**
 * The Signature list again, for wpbakery.js, to the roles given the element
 * (with the nonce signatur_wpbakery_edit_param() gave).
 */
function signatur_wpbakery_ajax_signatures()
{
    check_ajax_referer(SIGNATUR_VC_LIST_ACTION);

    if (! current_user_can(SIGNATUR_CAP_WPBAKERY)) {
        wp_send_json_error(null, 403);
    }

    wp_send_json_success(signatur_wpbakery_signatures());
}
