<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle submission of the "Add signature" form.
 */
function signatur_handle_save_signature()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to perform this action.', 'signatur'));
    }

    check_admin_referer('signatur_save_signature');

    $title = isset($_POST['signatur_title'])
        ? sanitize_text_field(wp_unslash($_POST['signatur_title']))
        : '';

    $link = isset($_POST['signatur_link'])
        ? esc_url_raw(wp_unslash($_POST['signatur_link']))
        : '';

    $settings = signatur_sanitize_settings(
        isset($_POST['signatur_settings']) ? wp_unslash($_POST['signatur_settings']) : ''
    );

    $id = isset($_POST['signatur_id']) ? absint($_POST['signatur_id']) : 0;

    if ('' !== $title) {
        global $wpdb;

        if ($id) {
            $wpdb->update(
                signatur_get_table_name(),
                array(
                    'title'      => $title,
                    'link'       => $link,
                    'settings'   => $settings,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                signatur_get_table_name(),
                array(
                    'title'      => $title,
                    'link'       => $link,
                    'settings'   => $settings,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    wp_safe_redirect(
        add_query_arg(
            $id ? 'signatur_updated' : 'signatur_saved',
            '1',
            admin_url('admin.php?page=signatur-all-signatures')
        )
    );
    exit;
}

/**
 * Handle deletion of a signature.
 */
function signatur_handle_delete_signature()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to perform this action.', 'signatur'));
    }

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    check_admin_referer('signatur_delete_signature_' . $id);

    if ($id) {
        global $wpdb;

        $wpdb->delete(
            signatur_get_table_name(),
            array('id' => $id),
            array('%d')
        );
    }

    wp_safe_redirect(
        add_query_arg(
            'signatur_deleted',
            '1',
            admin_url('admin.php?page=signatur-all-signatures')
        )
    );
    exit;
}
