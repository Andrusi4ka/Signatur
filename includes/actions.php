<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle submission of the "Add signature" form.
 */
function signatur_handle_save_signature()
{
    if (! current_user_can(SIGNATUR_CAP_EDIT)) {
        wp_die(esc_html__('You are not allowed to perform this action.', 'signatur'));
    }

    check_admin_referer('signatur_save_signature');

    // A new signature needs Add (Signatures → Settings).
    if (empty($_POST['signatur_id']) && ! current_user_can(SIGNATUR_CAP_CREATE)) {
        wp_die(esc_html__('You are not allowed to perform this action.', 'signatur'));
    }

    global $wpdb;

    $title = isset($_POST['signatur_title'])
        ? sanitize_text_field(wp_unslash($_POST['signatur_title']))
        : '';

    $settings = signatur_sanitize_settings(
        isset($_POST['signatur_settings']) ? wp_unslash($_POST['signatur_settings']) : ''
    );

    $id        = isset($_POST['signatur_id']) ? absint($_POST['signatur_id']) : 0;
    $is_update = (bool) $id;

    if ('' !== $title) {
        if ($id) {
            $wpdb->update(
                signatur_get_table_name(),
                array(
                    'title'      => $title,
                    'settings'   => $settings,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                signatur_get_table_name(),
                array(
                    'title'      => $title,
                    'settings'   => $settings,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%s', '%s', '%s', '%s')
            );

            $id = (int) $wpdb->insert_id;
        }
    }

    // Stay on the "Add/Edit signature" screen after saving instead of
    // jumping back to the list - $id is known either way at this point
    // (posted for an update, or just inserted for a new signature).
    $redirect_url = admin_url('admin.php?page=signatur-add-signature');

    if ($id) {
        $redirect_url = add_query_arg('id', $id, $redirect_url);
    }

    wp_safe_redirect(
        add_query_arg($is_update ? 'signatur_updated' : 'signatur_saved', '1', $redirect_url)
    );
    exit;
}

/**
 * Handle deletion of a signature.
 */
function signatur_handle_delete_signature()
{
    if (! current_user_can(SIGNATUR_CAP_DELETE)) {
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
 * wp-content/uploads/signatur/ and returns its URL. For those who may edit
 * signatures (Signatures → Settings).
 */
function signatur_handle_upload_image()
{
    if (! current_user_can(SIGNATUR_CAP_EDIT)) {
        wp_send_json_error(array('message' => __('You are not allowed to perform this action.', 'signatur')));
    }

    check_ajax_referer('signatur_upload_image', 'nonce');

    if (empty($_FILES['file'])) {
        wp_send_json_error(array('message' => __('Image upload failed.', 'signatur')));
    }

    // No SVG (since 1.8.0): Gmail and Outlook do not show it in a signature.
    $allowed = signatur_upload_mimes();
    $file    = $_FILES['file'];

    if (signatur_is_svg_name(isset($file['name']) ? $file['name'] : '')) {
        wp_send_json_error(array('message' => signatur_svg_message()));
    }

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

/**
 * The images a signature may use: what Gmail and Outlook show. Not SVG.
 */
function signatur_upload_mimes()
{
    return array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
    );
}

/**
 * Whether a file name or URL is an SVG (by its extension, a query or
 * #fragment after it ignored).
 */
function signatur_is_svg_name($name)
{
    return (bool) preg_match('/\.svgz?(?:$|[?#])/i', (string) $name);
}

/**
 * Why an SVG is refused, and what to upload instead.
 */
function signatur_svg_message()
{
    return __('Email clients (Gmail, Outlook) do not show SVG images. Upload a PNG or JPG instead.', 'signatur');
}

