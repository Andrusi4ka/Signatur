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

    $add_signature_hook = add_submenu_page(
        'signatur-all-signatures',
        __('Add signature', 'signatur'),
        __('Add signature', 'signatur'),
        'manage_options',
        'signatur-add-signature',
        'signatur_render_add_signature_page'
    );

    signatur_add_signature_hook_suffix($add_signature_hook);
}

/**
 * Store and retrieve the page hook suffix of the "Add signature" screen,
 * so the asset enqueue can target exactly that page. Called with a value
 * from signatur_register_admin_pages() (on admin_menu), read with no
 * argument from signatur_enqueue_builder_assets() (on admin_enqueue_scripts).
 */
function signatur_add_signature_hook_suffix($hook_suffix = null)
{
    static $stored = '';

    if (null !== $hook_suffix) {
        $stored = $hook_suffix;
    }

    return $stored;
}

/**
 * Load the signature builder's stylesheet and script, but only on the
 * "Add signature" / "Edit signature" screen.
 */
function signatur_enqueue_builder_assets($hook_suffix)
{
    if ($hook_suffix !== signatur_add_signature_hook_suffix()) {
        return;
    }

    wp_enqueue_style(
        'signatur-builder',
        plugins_url('assets/css/builder.css', SIGNATUR_PLUGIN_FILE),
        array(),
        SIGNATUR_VERSION
    );

    wp_enqueue_script(
        'signatur-builder',
        plugins_url('assets/js/builder.js', SIGNATUR_PLUGIN_FILE),
        array(),
        SIGNATUR_VERSION,
        true
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
 * Convert a zero-based column index into an Excel-style letter label:
 * 0 => A, 1 => B, ... 25 => Z, 26 => AA, 27 => AB, ...
 */
function signatur_column_letter($index)
{
    $letter = '';
    $index++;

    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $letter    = chr(65 + $remainder) . $letter;
        $index     = (int) (($index - 1) / 26);
    }

    return $letter;
}

/**
 * Render the edit/delete-row buttons shown beside a table row.
 */
function signatur_render_row_actions_buttons()
{
?>
    <button type="button" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Edit row', 'signatur'); ?>" title="<?php esc_attr_e('Edit row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Edit row', 'signatur'); ?>">
        <img src="<?php echo esc_url(plugins_url('assets/icon-edit.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
    </button>
    <button type="button" class="signatur-icon-button signatur-row-delete-button" title="<?php esc_attr_e('Delete row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Delete row', 'signatur'); ?>">
        <img src="<?php echo esc_url(plugins_url('assets/icon-delete.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
    </button>
<?php
}

/**
 * Render the signature creation page.
 */
function signatur_render_add_signature_page()
{
    $id         = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $title      = '';
    $show_title = true;
    $settings   = signatur_get_default_settings();

    if ($id) {
        global $wpdb;

        $signature = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT title, show_title, settings FROM ' . signatur_get_table_name() . ' WHERE id = %d',
                $id
            )
        );

        if ($signature) {
            $title      = $signature->title;
            $show_title = (bool) $signature->show_title;
            $settings   = json_decode(signatur_sanitize_settings($signature->settings), true);
        } else {
            $id = 0;
        }
    }

    $group_width = $settings['width'] + 200;

    $page_title = $id ? __('Edit signature', 'signatur') : __('Add signature', 'signatur');
?>
    <div class="wrap">
        <h1><?php echo esc_html($page_title); ?></h1>

        <form id="signatur_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
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
                            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                                <input type="text" id="signatur_title" name="signatur_title" class="regular-text" value="<?php echo esc_attr($title); ?>" required>
                                <label for="signatur_show_title" style="display:flex; align-items:center; gap:6px; font-weight:400;">
                                    <input type="checkbox" id="signatur_show_title" name="signatur_show_title" value="1" <?php checked($show_title); ?>>
                                    <?php esc_html_e('Show on site', 'signatur'); ?>
                                </label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <input type="hidden" name="signatur_settings" id="signatur_settings_input" value="<?php echo esc_attr(wp_json_encode($settings)); ?>">

            <div id="signatur_row_group" style="width:<?php echo esc_attr($group_width); ?>px; margin:16px auto 0;">
                <div id="signatur_row_top_wrap" class="signatur-editor-only" style="width:<?php echo esc_attr($settings['width']); ?>px; display:flex; justify-content:center; gap:8px;">
                    <button type="button" id="signatur_edit_signature_button" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Edit signature', 'signatur'); ?>" data-modal-type="width" title="<?php esc_attr_e('Edit signature', 'signatur'); ?>" aria-label="<?php esc_attr_e('Edit signature', 'signatur'); ?>">
                        <img src="<?php echo esc_url(plugins_url('assets/icon-edit.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                    </button>
                    <button type="button" id="signatur_undo" class="signatur-icon-button" title="<?php esc_attr_e('Undo', 'signatur'); ?>" aria-label="<?php esc_attr_e('Undo', 'signatur'); ?>" disabled>
                        <img src="<?php echo esc_url(plugins_url('assets/icon-back.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                    </button>
                    <button type="button" id="signatur_redo" class="signatur-icon-button" title="<?php esc_attr_e('Redo', 'signatur'); ?>" aria-label="<?php esc_attr_e('Redo', 'signatur'); ?>" disabled>
                        <img src="<?php echo esc_url(plugins_url('assets/icon-next.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                    </button>
                    <button type="button" id="signatur_column_add_end" class="signatur-icon-button" title="<?php esc_attr_e('Add column', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add column', 'signatur'); ?>">
                        <img src="<?php echo esc_url(plugins_url('assets/icon-plus-column.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                    </button>
                </div>

                <div style="display:flex; align-items:flex-start; margin-top:8px;">
                    <table id="signatur_field_table" class="widefat striped" style="width:<?php echo esc_attr($settings['width']); ?>px; padding-top:<?php echo esc_attr($settings['padding']['top']); ?>px; padding-right:<?php echo esc_attr($settings['padding']['right']); ?>px; padding-bottom:<?php echo esc_attr($settings['padding']['bottom']); ?>px; padding-left:<?php echo esc_attr($settings['padding']['left']); ?>px; margin-right:8px;">
                        <colgroup id="signatur_field_colgroup">
                            <?php foreach ($settings['columns'] as $column_width) : ?>
                                <col style="width:<?php echo esc_attr($column_width); ?>px;">
                            <?php endforeach; ?>
                        </colgroup>
                        <?php
                        /*
                         * Editor-only chrome: the A/B/C... column labels exist purely to
                         * help build the grid and must never end up in the actual
                         * signature. This <thead> is not part of the signature content -
                         * whatever eventually copies/exports this table for use in an
                         * email client (e.g. pasting into Outlook) must take only
                         * #signatur_field_tbody's rows, never this thead.
                         */
                        ?>
                        <thead class="signatur-editor-only">
                            <tr id="signatur_field_header_row">
                                <?php foreach (array_keys($settings['columns']) as $column_index) : ?>
                                    <th class="signatur-column-header"><?php echo esc_html(signatur_column_letter($column_index)); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="signatur_field_tbody">
                            <?php foreach ($settings['rows'] as $row_cells) : ?>
                                <tr>
                                    <?php foreach ($row_cells as $cell_span) : ?>
                                        <?php
                                        $colspan = $cell_span[0];
                                        $rowspan = $cell_span[1];
                                        $element = isset($cell_span[2]) ? $cell_span[2] : null;
                                        $attrs   = '';

                                        if ($colspan > 1) {
                                            $attrs .= ' colspan="' . (int) $colspan . '"';
                                        }

                                        if ($rowspan > 1) {
                                            $attrs .= ' rowspan="' . (int) $rowspan . '"';
                                        }

                                        if ($element && 'text' === $element['type']) {
                                            $attrs .= ' style="text-align:' . esc_attr($element['align']) . '; vertical-align:' . esc_attr($element['valign']) . ';"';
                                            $attrs .= ' data-element="' . esc_attr(wp_json_encode($element)) . '"';
                                        }
                                        ?>
                                        <td<?php echo $attrs; ?>><?php
                                                                    echo ($element && 'text' === $element['type'])
                                                                        ? signatur_render_text_span($element)
                                                                        : '&nbsp;';
                                                                    ?></td>
                                        <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="signatur_rows_actions" class="signatur-editor-only" style="display:flex; flex-direction:column;">
                        <div class="signatur-row-actions signatur-row-actions-spacer" aria-hidden="true"></div>
                        <?php for ($i = 0, $signatur_row_total = count($settings['rows']); $i < $signatur_row_total; $i++) : ?>
                            <div class="signatur-row-actions">
                                <?php signatur_render_row_actions_buttons(); ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div id="signatur_row_add_wrap" class="signatur-editor-only" style="width:<?php echo esc_attr($settings['width']); ?>px; margin:8px 0 0; display:flex; justify-content:center;">
                    <button type="button" id="signatur_row_add" class="signatur-icon-button" title="<?php esc_attr_e('Add row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add row', 'signatur'); ?>">
                        <img src="<?php echo esc_url(plugins_url('assets/icon-plus-row.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                    </button>
                </div>
            </div>

            <?php submit_button(__('Save', 'signatur')); ?>
        </form>

        <button type="button" id="signatur_cell_edit_element" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Edit text', 'signatur'); ?>" data-modal-type="text" title="<?php esc_attr_e('Edit element', 'signatur'); ?>" aria-label="<?php esc_attr_e('Edit element', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-edit-element.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_cell_add_element" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Add element', 'signatur'); ?>" data-modal-type="element" title="<?php esc_attr_e('Add element', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add element', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-plus.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_split" class="signatur-icon-button" title="<?php esc_attr_e('Split cell', 'signatur'); ?>" aria-label="<?php esc_attr_e('Split cell', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-shift.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_add" class="signatur-icon-button" title="<?php esc_attr_e('Add column', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add column', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-plus-column.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_combine" class="signatur-icon-button" title="<?php esc_attr_e('Combine columns', 'signatur'); ?>" aria-label="<?php esc_attr_e('Combine columns', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-combine.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_remove" class="signatur-icon-button" title="<?php esc_attr_e('Remove column', 'signatur'); ?>" aria-label="<?php esc_attr_e('Remove column', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-minus-column.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <div id="signatur_col_resize_handles"></div>

        <div id="signatur_row_add_indicator"></div>

        <div id="signatur_column_add_indicator"></div>

        <div id="signatur_row_hover_indicator"></div>

        <div id="signatur_modal_overlay">
            <div id="signatur_modal">
                <h2 id="signatur_modal_title"></h2>
                <div id="signatur_modal_body"></div>
                <div id="signatur_modal_footer">
                    <button type="button" class="button" id="signatur_modal_cancel"><?php esc_html_e('Cancel', 'signatur'); ?></button>
                    <button type="button" class="button button-primary" id="signatur_modal_save"><?php esc_html_e('Save', 'signatur'); ?></button>
                </div>
            </div>
        </div>

        <template id="signatur_modal_template_width">
            <p style="display:flex; align-items:center; gap:8px; margin:0;">
                <label for="signatur_width_input" style="font-weight:600;">
                    <?php esc_html_e('Signature width', 'signatur'); ?>
                </label>
                <input type="number" id="signatur_width_input" class="small-text" min="200" step="1" style="width:70px;">
                <span><?php esc_html_e('px', 'signatur'); ?></span>
                <span style="font-size:11px; color:#646970;"><?php esc_html_e('min. 200px', 'signatur'); ?></span>
            </p>

            <p style="display:flex; align-items:center; gap:6px; margin:12px 0 0;">
                <span style="font-weight:600;">Padding</span>
                <input type="number" id="signatur_padding_top" class="small-text" min="0" step="1" placeholder="<?php esc_attr_e('Top', 'signatur'); ?>" style="width:50px;">
                <input type="number" id="signatur_padding_left" class="small-text" min="0" step="1" placeholder="<?php esc_attr_e('Left', 'signatur'); ?>" style="width:50px;">
                <input type="number" id="signatur_padding_bottom" class="small-text" min="0" step="1" placeholder="<?php esc_attr_e('Bottom', 'signatur'); ?>" style="width:50px;">
                <input type="number" id="signatur_padding_right" class="small-text" min="0" step="1" placeholder="<?php esc_attr_e('Right', 'signatur'); ?>" style="width:50px;">
            </p>
        </template>

        <template id="signatur_modal_template_element">
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" id="signatur_add_text_element" class="signatur-icon-button signatur-element-button" data-modal-title="<?php echo esc_attr__('Add text', 'signatur'); ?>" data-modal-type="text" title="<?php esc_attr_e('Text', 'signatur'); ?>" aria-label="<?php esc_attr_e('Text', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-add-text.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
            </div>
        </template>

        <template id="signatur_modal_template_text">
            <p style="margin:0;">
                <textarea id="signatur_text_input" rows="3" class="large-text" style="width:100%;"></textarea>
            </p>

            <div class="signatur-text-controls">
                <input type="number" id="signatur_text_size" class="small-text" min="8" max="72" step="1" style="width:56px;">
                <span><?php esc_html_e('pt', 'signatur'); ?></span>

                <input type="color" id="signatur_text_color" value="#000000">

                <button type="button" id="signatur_text_bold" class="button signatur-text-toggle" title="<?php esc_attr_e('Bold', 'signatur'); ?>" aria-label="<?php esc_attr_e('Bold', 'signatur'); ?>" style="font-weight:700;">B</button>
                <button type="button" id="signatur_text_italic" class="button signatur-text-toggle" title="<?php esc_attr_e('Italic', 'signatur'); ?>" aria-label="<?php esc_attr_e('Italic', 'signatur'); ?>" style="font-style:italic;">I</button>
                <button type="button" id="signatur_text_underline" class="button signatur-text-toggle" title="<?php esc_attr_e('Underline', 'signatur'); ?>" aria-label="<?php esc_attr_e('Underline', 'signatur'); ?>" style="text-decoration:underline;">U</button>

                <select id="signatur_text_align" aria-label="<?php esc_attr_e('Horizontal alignment', 'signatur'); ?>">
                    <option value="left"><?php esc_html_e('Left', 'signatur'); ?></option>
                    <option value="center"><?php esc_html_e('Center', 'signatur'); ?></option>
                    <option value="right"><?php esc_html_e('Right', 'signatur'); ?></option>
                </select>

                <select id="signatur_text_valign" aria-label="<?php esc_attr_e('Vertical alignment', 'signatur'); ?>">
                    <option value="top"><?php esc_html_e('Top', 'signatur'); ?></option>
                    <option value="middle"><?php esc_html_e('Middle', 'signatur'); ?></option>
                    <option value="bottom"><?php esc_html_e('Bottom', 'signatur'); ?></option>
                </select>
            </div>

            <p style="margin:12px 0 0;">
                <input type="url" id="signatur_text_link" class="regular-text" style="width:100%;" placeholder="<?php echo esc_attr__('https://example.com', 'signatur'); ?>" aria-label="<?php echo esc_attr__('https://example.com', 'signatur'); ?>">
            </p>
        </template>

        <template id="signatur_row_actions_template">
            <div class="signatur-row-actions">
                <?php signatur_render_row_actions_buttons(); ?>
            </div>
        </template>
    </div>

<?php
}
