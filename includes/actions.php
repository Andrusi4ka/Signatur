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
        'svg'          => 'image/svg+xml',
    );

    $file = $_FILES['file'];

    if (! empty($file['size']) && $file['size'] > 5 * 1024 * 1024) {
        wp_send_json_error(array('message' => __('Image upload failed.', 'signatur')));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    // WordPress's real-MIME sniff (finfo) does not reliably recognize SVG -
    // an XML format, not a binary one - and would otherwise reject a
    // legitimate upload even though its extension is allowed above. Trust
    // the .svg extension instead; the upload is already admin-only and
    // nonce-checked.
    add_filter('wp_check_filetype_and_ext', 'signatur_allow_svg_filetype', 10, 4);
    add_filter('upload_dir', 'signatur_upload_dir');
    $result = wp_handle_upload(
        $file,
        array(
            'test_form' => false,
            'mimes'     => $allowed,
        )
    );
    remove_filter('upload_dir', 'signatur_upload_dir');
    remove_filter('wp_check_filetype_and_ext', 'signatur_allow_svg_filetype');

    if (isset($result['error']) || empty($result['url'])) {
        wp_send_json_error(array('message' => __('Image upload failed.', 'signatur')));
    }

    // An SVG is served straight back as an <img src="...">, but it is still
    // a same-origin file that could carry active content (<script>, event
    // handler attributes, javascript: URIs) if opened directly - strip that
    // out before it is kept.
    if (! empty($result['type']) && 'image/svg+xml' === $result['type'] && ! empty($result['file'])) {
        signatur_sanitize_svg_file($result['file']);
    }

    wp_send_json_success(array('url' => $result['url']));
}

/**
 * `wp_check_filetype_and_ext` filter: trust the .svg extension instead of
 * WordPress's finfo-based real-MIME sniff, which does not reliably detect
 * SVG. See signatur_handle_upload_image().
 */
function signatur_allow_svg_filetype($data, $file, $filename, $mimes)
{
    if (empty($data['ext']) || empty($data['type'])) {
        $filetype = wp_check_filetype($filename, $mimes);

        if ('svg' === $filetype['ext']) {
            $data['ext']             = 'svg';
            $data['type']            = 'image/svg+xml';
            $data['proper_filename'] = $filename;
        }
    }

    return $data;
}

/**
 * Strip <script> tags, on*="" event-handler attributes and javascript:
 * URIs from an uploaded SVG file, in place. A minimal defusal against the
 * common script-injection vectors, not a full XML sanitizer.
 */
function signatur_sanitize_svg_file($path)
{
    $contents = file_get_contents($path);

    if (false === $contents) {
        return;
    }

    $contents = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $contents);
    $contents = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $contents);
    $contents = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $contents);
    $contents = preg_replace('#((?:xlink:)?href)\s*=\s*("|\')\s*javascript:[^"\']*("|\')#i', '', $contents);

    file_put_contents($path, $contents);
}
