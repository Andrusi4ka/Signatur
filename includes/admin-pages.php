<?php

if (! defined('ABSPATH')) {
    exit;
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
    global $wpdb;

    $signatures = $wpdb->get_results(
        'SELECT id, title FROM ' . signatur_get_table_name() . ' ORDER BY id DESC'
    );
?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('All signatures', 'signatur'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=signatur-add-signature')); ?>" class="page-title-action">
            <?php esc_html_e('Add signature', 'signatur'); ?>
        </a>
        <hr class="wp-header-end">

        <?php if (! empty($_GET['signatur_saved'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Signature saved.', 'signatur'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (! empty($_GET['signatur_updated'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Signature updated.', 'signatur'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (! empty($_GET['signatur_deleted'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Signature deleted.', 'signatur'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($signatures) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'signatur'); ?></th>
                        <th><?php esc_html_e('Shortcode', 'signatur'); ?></th>
                        <th><?php esc_html_e('Actions', 'signatur'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($signatures as $signature) : ?>
                        <?php
                        $edit_url = esc_url(
                            admin_url('admin.php?page=signatur-add-signature&id=' . $signature->id)
                        );
                        $delete_url = esc_url(
                            wp_nonce_url(
                                admin_url('admin-post.php?action=signatur_delete_signature&id=' . $signature->id),
                                'signatur_delete_signature_' . $signature->id
                            )
                        );
                        $confirm = esc_js(__('Are you sure you want to delete this signature?', 'signatur'));
                        ?>
                        <tr>
                            <td><?php echo esc_html($signature->title); ?></td>
                            <td><code>[signatur id="<?php echo esc_html($signature->id); ?>"]</code></td>
                            <td>
                                <a href="<?php echo $edit_url; ?>"><?php esc_html_e('Edit', 'signatur'); ?></a>
                                |
                                <a href="<?php echo $delete_url; ?>" onclick="return confirm('<?php echo $confirm; ?>');" class="submitdelete"><?php esc_html_e('Delete', 'signatur'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('Your email signatures will appear here.', 'signatur'); ?></p>
        <?php endif; ?>
    </div>
<?php
}

/**
 * Render the signature creation page.
 */
function signatur_render_add_signature_page()
{
    $id    = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $title = '';

    if ($id) {
        global $wpdb;

        $signature = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT title FROM ' . signatur_get_table_name() . ' WHERE id = %d',
                $id
            )
        );

        if ($signature) {
            $title = $signature->title;
        } else {
            $id = 0;
        }
    }

    $page_title = $id ? __('Edit signature', 'signatur') : __('Add signature', 'signatur');
?>
    <div class="wrap">
        <h1><?php echo esc_html($page_title); ?></h1>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('signatur_save_signature'); ?>
            <input type="hidden" name="action" value="signatur_save_signature">
            <input type="hidden" name="signatur_id" value="<?php echo esc_attr($id); ?>">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="signatur_title"><?php esc_html_e('Title', 'signatur'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="signatur_title" name="signatur_title" class="regular-text" value="<?php echo esc_attr($title); ?>" required>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(__('Save', 'signatur')); ?>
        </form>
    </div>
<?php
}
