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

    $show_title = isset($_POST['signatur_show_title']) ? 1 : 0;

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
                    'show_title' => $show_title,
                    'settings'   => $settings,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                signatur_get_table_name(),
                array(
                    'title'      => $title,
                    'show_title' => $show_title,
                    'settings'   => $settings,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%s', '%d', '%s', '%s', '%s')
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

/**
 * `upload_dir` filter that redirects uploads into wp-content/uploads/signatur/
 * (a flat folder, no year/month subfolders) so signature images live in one
 * predictable place and survive plugin updates.
 */
function signatur_upload_dir($dirs)
{
    $dirs['subdir'] = '/signatur';
    $dirs['path']   = $dirs['basedir'] . '/signatur';
    $dirs['url']    = $dirs['baseurl'] . '/signatur';

    return $dirs;
}

/**
 * AJAX handler: upload an image for an image element. Stores the file in
 * wp-content/uploads/signatur/ and returns its URL. Admin-only.
 */
function signatur_handle_upload_image()
{
    if (! current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You are not allowed to perform this action.', 'signatur')));
    }

    check_ajax_referer('signatur_upload_image', 'nonce');

    if (empty($_FILES['file'])) {
        wp_send_json_error(array('message' => __('Image upload failed.', 'signatur')));
    }

    $allowed = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
    );

    $file = $_FILES['file'];

    if (! empty($file['size']) && $file['size'] > 5 * 1024 * 1024) {
        wp_send_json_error(array('message' => __('Image upload failed.', 'signatur')));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    add_filter('upload_dir', 'signatur_upload_dir');
    $result = wp_handle_upload(
        $file,
        array(
            'test_form' => false,
            'mimes'     => $allowed,
        )
    );
    remove_filter('upload_dir', 'signatur_upload_dir');

    if (isset($result['error']) || empty($result['url'])) {
        wp_send_json_error(array('message' => __('Image upload failed.', 'signatur')));
    }

    wp_send_json_success(array('url' => $result['url']));
}
