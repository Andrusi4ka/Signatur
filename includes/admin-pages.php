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
 * Render the edit/delete-row buttons shown beside a table row.
 */
function signatur_render_row_actions_buttons()
{
?>
    <button type="button" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Edit row', 'signatur'); ?>" title="<?php esc_attr_e('Edit row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Edit row', 'signatur'); ?>">
        <img src="<?php echo esc_url(plugins_url('assets/icon-edit.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
    </button>
    <button type="button" class="signatur-icon-button" title="<?php esc_attr_e('Delete row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Delete row', 'signatur'); ?>">
        <img src="<?php echo esc_url(plugins_url('assets/icon-delete.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
    </button>
<?php
}

/**
 * Render the signature creation page.
 */
function signatur_render_add_signature_page()
{
    $id       = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $title    = '';
    $link     = '';
    $settings = signatur_get_default_settings();

    if ($id) {
        global $wpdb;

        $signature = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT title, link, settings FROM ' . signatur_get_table_name() . ' WHERE id = %d',
                $id
            )
        );

        if ($signature) {
            $title    = $signature->title;
            $link     = $signature->link;
            $settings = json_decode(signatur_sanitize_settings($signature->settings), true);
        } else {
            $id = 0;
        }
    }

    $group_width = $settings['width'] + 160;

    $page_title = $id ? __('Edit signature', 'signatur') : __('Add signature', 'signatur');
?>
    <style>
        .signatur-icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f6f7f7;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            padding: 0;
        }

        .signatur-icon-button:hover,
        .signatur-icon-button:focus {
            background: #f0f0f1;
            border-color: #646970;
        }

        #signatur_modal_overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            align-items: center;
            justify-content: center;
        }

        #signatur_modal {
            background: #fff;
            border-radius: 4px;
            padding: 20px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
        }

        #signatur_modal_footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 20px;
        }

        .wrap input[type="date"],
        .wrap input[type="datetime-local"],
        .wrap input[type="datetime"],
        .wrap input[type="email"],
        .wrap input[type="month"],
        .wrap input[type="number"],
        .wrap input[type="password"],
        .wrap input[type="search"],
        .wrap input[type="tel"],
        .wrap input[type="text"],
        .wrap input[type="time"],
        .wrap input[type="url"],
        .wrap input[type="week"] {
            min-height: 30px;
            height: 30px;
            line-height: 28px;
        }

        #signatur_field_table {
            border-collapse: collapse;
            table-layout: fixed;
        }

        #signatur_field_table td {
            height: 38px;
            box-sizing: border-box;
            border: 1px solid #dcdcde;
            user-select: none;
        }

        #signatur_field_table td.signatur-cell-selected {
            background: #d5e5f8;
        }

        #signatur_field_table thead th.signatur-column-header {
            box-sizing: border-box;
            height: 22px;
            padding: 0;
            font-size: 11px;
            font-weight: normal;
            text-align: center;
            color: #646970;
            background: #f0f0f1;
            border: 1px solid #dcdcde;
            user-select: none;
        }

        #signatur_field_table thead th.signatur-column-header:hover {
            background: #d5e5f8;
            color: #1d2327;
        }

        #signatur_column_add,
        #signatur_column_combine,
        #signatur_column_remove,
        #signatur_column_split {
            display: none;
            position: fixed;
            z-index: 100001;
        }

        .signatur-col-resize-handle {
            position: fixed;
            width: 7px;
            margin-left: -3px;
            cursor: col-resize;
            z-index: 100000;
            background: transparent;
        }

        .signatur-col-resize-handle:hover {
            background: #2271b1;
            opacity: 0.5;
        }
    </style>
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
                                <button type="button" id="signatur_link_toggle" class="signatur-icon-button" title="<?php esc_attr_e('Add link', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add link', 'signatur'); ?>">
                                    <img src="<?php echo esc_url(plugins_url('assets/icon-link.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                                </button>
                                <span id="signatur_link_field" style="<?php echo $link ? '' : 'display:none;'; ?>">
                                    <input type="url" id="signatur_link" name="signatur_link" class="regular-text" value="<?php echo esc_attr($link); ?>" placeholder="https://example.com">
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <input type="hidden" name="signatur_settings" id="signatur_settings_input" value="<?php echo esc_attr(wp_json_encode($settings)); ?>">

            <div id="signatur_row_group" style="width:<?php echo esc_attr($group_width); ?>px; margin:16px auto 0;">
                <div id="signatur_row_top_wrap" style="width:<?php echo esc_attr($settings['width']); ?>px; display:flex; justify-content:center;">
                    <button type="button" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Edit signature', 'signatur'); ?>" data-modal-type="width" title="<?php esc_attr_e('Edit signature', 'signatur'); ?>" aria-label="<?php esc_attr_e('Edit signature', 'signatur'); ?>">
                        <img src="<?php echo esc_url(plugins_url('assets/icon-edit.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                    </button>
                </div>

                <div style="display:flex; align-items:flex-start; gap:8px; margin-top:8px;">
                    <div id="signatur_row_add_wrap" style="display:flex; flex-direction:column; justify-content:flex-end; align-self:stretch;">
                        <button type="button" id="signatur_row_add" class="signatur-icon-button" title="<?php esc_attr_e('Add row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add row', 'signatur'); ?>">
                            <img src="<?php echo esc_url(plugins_url('assets/icon-plus.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                        </button>
                    </div>
                    <table id="signatur_field_table" class="widefat striped" style="width:<?php echo esc_attr($settings['width']); ?>px; padding-top:<?php echo esc_attr($settings['padding']['top']); ?>px; padding-right:<?php echo esc_attr($settings['padding']['right']); ?>px; padding-bottom:<?php echo esc_attr($settings['padding']['bottom']); ?>px; padding-left:<?php echo esc_attr($settings['padding']['left']); ?>px;">
                        <colgroup id="signatur_field_colgroup">
                            <?php foreach ($settings['columns'] as $column_width) : ?>
                                <col style="width:<?php echo esc_attr($column_width); ?>px;">
                            <?php endforeach; ?>
                        </colgroup>
                        <thead>
                            <tr id="signatur_field_header_row">
                                <?php foreach ($settings['columns'] as $column_index => $column_width) : ?>
                                    <th class="signatur-column-header" data-index="<?php echo esc_attr($column_index); ?>"><?php echo esc_html($column_index + 1); ?></th>
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
                                        $attrs   = '';

                                        if ($colspan > 1) {
                                            $attrs .= ' colspan="' . (int) $colspan . '"';
                                        }

                                        if ($rowspan > 1) {
                                            $attrs .= ' rowspan="' . (int) $rowspan . '"';
                                        }
                                        ?>
                                        <td<?php echo $attrs; ?>>&nbsp;</td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="signatur_rows_actions" style="display:flex; flex-direction:column;">
                        <div class="signatur-row-actions" style="height:22px;" aria-hidden="true"></div>
                        <?php foreach ($settings['rows'] as $row_spans) : ?>
                            <div class="signatur-row-actions" style="height:38px; display:flex; align-items:center; gap:8px;">
                                <?php signatur_render_row_actions_buttons(); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php submit_button(__('Save', 'signatur')); ?>
        </form>

        <button type="button" id="signatur_column_split" class="signatur-icon-button" title="<?php esc_attr_e('Split cell', 'signatur'); ?>" aria-label="<?php esc_attr_e('Split cell', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-shift.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_add" class="signatur-icon-button" title="<?php esc_attr_e('Add column', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add column', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-plus.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_combine" class="signatur-icon-button" title="<?php esc_attr_e('Combine columns', 'signatur'); ?>" aria-label="<?php esc_attr_e('Combine columns', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-combine.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_remove" class="signatur-icon-button" title="<?php esc_attr_e('Remove column', 'signatur'); ?>" aria-label="<?php esc_attr_e('Remove column', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-minus.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <div id="signatur_col_resize_handles"></div>

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

        <template id="signatur_row_actions_template">
            <div class="signatur-row-actions" style="height:38px; display:flex; align-items:center; gap:8px;">
                <?php signatur_render_row_actions_buttons(); ?>
            </div>
        </template>
    </div>

    <script>
        (function () {
            var toggle = document.getElementById('signatur_link_toggle');
            var field = document.getElementById('signatur_link_field');
            var input = document.getElementById('signatur_link');

            toggle.addEventListener('click', function () {
                field.style.display = '';
                input.focus();
            });

            var modalOverlay = document.getElementById('signatur_modal_overlay');
            var modalTitle = document.getElementById('signatur_modal_title');
            var modalBody = document.getElementById('signatur_modal_body');
            var modalCancel = document.getElementById('signatur_modal_cancel');
            var modalSave = document.getElementById('signatur_modal_save');
            var widthTemplate = document.getElementById('signatur_modal_template_width');
            var rowGroup = document.getElementById('signatur_row_group');
            var rowTopWrap = document.getElementById('signatur_row_top_wrap');
            var fieldTable = document.getElementById('signatur_field_table');
            var fieldTbody = document.getElementById('signatur_field_tbody');
            var rowsActions = document.getElementById('signatur_rows_actions');
            var rowActionsTemplate = document.getElementById('signatur_row_actions_template');
            var colgroup = document.getElementById('signatur_field_colgroup');
            var headerRow = document.getElementById('signatur_field_header_row');
            var resizeHandles = document.getElementById('signatur_col_resize_handles');
            var minWidth = 200;
            var minColumnWidth = 20;
            var paddingSides = ['top', 'right', 'bottom', 'left'];
            var activeModalType = null;
            var columnWidths = Array.prototype.map.call(colgroup.children, function (col) {
                return parseInt(col.style.width, 10) || minColumnWidth;
            });

            function clampWidth(value) {
                var width = parseInt(value, 10);

                if (isNaN(width) || width < minWidth) {
                    return minWidth;
                }

                return width;
            }

            function clampPadding(value) {
                var padding = parseInt(value, 10);

                if (isNaN(padding) || padding < 0) {
                    return 0;
                }

                return padding;
            }

            function openModal(title, type) {
                modalTitle.textContent = title;
                modalBody.innerHTML = '';
                activeModalType = type || null;

                if ('width' === type) {
                    modalBody.appendChild(widthTemplate.content.cloneNode(true));
                    var widthInput = document.getElementById('signatur_width_input');
                    widthInput.value = parseInt(fieldTable.style.width, 10) || 300;

                    widthInput.addEventListener('blur', function () {
                        widthInput.value = clampWidth(widthInput.value);
                    });

                    paddingSides.forEach(function (side) {
                        var paddingInput = document.getElementById('signatur_padding_' + side);
                        var computed = fieldTable.style['padding' + side.charAt(0).toUpperCase() + side.slice(1)];
                        paddingInput.value = computed ? (parseInt(computed, 10) || '') : '';

                        paddingInput.addEventListener('blur', function () {
                            if ('' !== paddingInput.value) {
                                paddingInput.value = clampPadding(paddingInput.value);
                            }
                        });
                    });
                }

                modalOverlay.style.display = 'flex';
            }

            function closeModal() {
                modalOverlay.style.display = 'none';
            }

            document.addEventListener('click', function (e) {
                var button = e.target.closest('[data-modal-title]');

                if (button) {
                    openModal(button.getAttribute('data-modal-title'), button.getAttribute('data-modal-type'));
                }
            });

            var rowAddButton = document.getElementById('signatur_row_add');

            function getCellColSpan(cell) {
                return parseInt(cell.getAttribute('colspan'), 10) || 1;
            }

            function setCellColSpan(cell, span) {
                if (span > 1) {
                    cell.setAttribute('colspan', span);
                } else {
                    cell.removeAttribute('colspan');
                }
            }

            function getCellRowSpan(cell) {
                return parseInt(cell.getAttribute('rowspan'), 10) || 1;
            }

            function setCellRowSpan(cell, span) {
                if (span > 1) {
                    cell.setAttribute('rowspan', span);
                } else {
                    cell.removeAttribute('rowspan');
                }
            }

            /**
             * Build the full occupancy grid of the table: grid[row][col] tells
             * which <td> owns that slot, and whether this row is where that
             * cell actually lives in the DOM (`owner`) or merely a slot it
             * covers via rowspan from an earlier row (pass-through).
             */
            function buildGrid() {
                var rows = Array.prototype.slice.call(fieldTbody.children);
                var pending = {};
                var grid = [];

                rows.forEach(function (row) {
                    var cells = Array.prototype.slice.call(row.children);
                    var col = 0;
                    var cellPos = 0;
                    var rowSlots = [];

                    for (;;) {
                        while (pending[col] && pending[col].remaining > 0) {
                            var p = pending[col];

                            rowSlots[col] = { cell: p.cell, start: p.start, end: p.end, owner: false };
                            p.remaining--;

                            if (0 === p.remaining) {
                                delete pending[col];
                            }

                            col++;
                        }

                        if (cellPos >= cells.length) {
                            break;
                        }

                        var cell = cells[cellPos];
                        var colspan = getCellColSpan(cell);
                        var rowspan = getCellRowSpan(cell);
                        var start = col;
                        var end = col + colspan;

                        for (var c = start; c < end; c++) {
                            rowSlots[c] = { cell: cell, start: start, end: end, owner: c === start };
                        }

                        if (rowspan > 1) {
                            for (var c2 = start; c2 < end; c2++) {
                                pending[c2] = { cell: cell, start: start, end: end, remaining: rowspan - 1 };
                            }
                        }

                        col = end;
                        cellPos++;
                    }

                    grid.push(rowSlots);
                });

                return grid;
            }

            function findCellPosition(grid, targetCell) {
                for (var r = 0; r < grid.length; r++) {
                    for (var c = 0; c < grid[r].length; c++) {
                        if (grid[r][c] && grid[r][c].cell === targetCell) {
                            return { row: r, col: c };
                        }
                    }
                }

                return null;
            }

            function getCellStartIndex(row, targetCell) {
                var grid = buildGrid();
                var rowIndex = Array.prototype.indexOf.call(fieldTbody.children, row);
                var rowSlots = grid[rowIndex] || [];

                for (var col = 0; col < rowSlots.length; col++) {
                    if (rowSlots[col] && rowSlots[col].cell === targetCell && rowSlots[col].owner) {
                        return col;
                    }
                }

                return 0;
            }

            function renderColgroup() {
                colgroup.innerHTML = '';

                columnWidths.forEach(function (width) {
                    var col = document.createElement('col');
                    col.style.width = width + 'px';
                    colgroup.appendChild(col);
                });

                renderColumnHeaders();
            }

            function renderColumnHeaders() {
                headerRow.innerHTML = '';

                columnWidths.forEach(function (width, index) {
                    var header = document.createElement('th');

                    header.className = 'signatur-column-header';
                    header.setAttribute('data-index', index);
                    header.textContent = index + 1;
                    headerRow.appendChild(header);
                });
            }

            function renderResizeHandles() {
                resizeHandles.innerHTML = '';

                if (columnWidths.length < 2) {
                    return;
                }

                var headerRect = headerRow.getBoundingClientRect();
                var offset = headerRect.left;

                for (var i = 0; i < columnWidths.length - 1; i++) {
                    offset += columnWidths[i];

                    var handle = document.createElement('div');

                    handle.className = 'signatur-col-resize-handle';
                    handle.setAttribute('data-index', i);
                    handle.style.top = headerRect.top + 'px';
                    handle.style.height = headerRect.height + 'px';
                    handle.style.left = offset + 'px';
                    resizeHandles.appendChild(handle);
                }
            }

            /**
             * Insert a new grid column at `index` (0..columnWidths.length) across
             * every row. A cell (real or a rowspan covering that boundary from
             * an earlier row) that already reaches across the insertion point
             * simply grows by one column; otherwise a plain new <td> is added
             * at that row's matching position.
             */
            function insertColumnAt(index, width) {
                var grid = buildGrid();

                columnWidths.splice(index, 0, width);

                var grown = [];

                Array.prototype.forEach.call(fieldTbody.children, function (row, r) {
                    var rowSlots = grid[r] || [];
                    var slot = rowSlots[index];

                    if (slot && index > slot.start && index < slot.end) {
                        if (grown.indexOf(slot.cell) === -1) {
                            setCellColSpan(slot.cell, getCellColSpan(slot.cell) + 1);
                            grown.push(slot.cell);
                        }

                        return;
                    }

                    var referenceCell = null;

                    for (var col = index; col < rowSlots.length; col++) {
                        if (rowSlots[col] && rowSlots[col].owner) {
                            referenceCell = rowSlots[col].cell;
                            break;
                        }
                    }

                    var newCell = document.createElement('td');
                    newCell.innerHTML = '&nbsp;';

                    if (referenceCell) {
                        row.insertBefore(newCell, referenceCell);
                    } else {
                        row.appendChild(newCell);
                    }
                });

                renderColgroup();
                renderResizeHandles();
            }

            /**
             * Remove grid column `index` from every row. A cell that only
             * covers that one column is removed outright; a cell (real or
             * covering that column via rowspan) that spans more than one
             * column simply shrinks by one.
             */
            function removeColumnAt(index) {
                if (columnWidths.length <= 1) {
                    return;
                }

                var grid = buildGrid();
                var removedWidth = columnWidths.splice(index, 1)[0];
                var neighborIndex = index < columnWidths.length ? index : index - 1;
                columnWidths[neighborIndex] += removedWidth;

                var shrunk = [];

                grid.forEach(function (rowSlots) {
                    var slot = rowSlots[index];

                    if (! slot || shrunk.indexOf(slot.cell) !== -1) {
                        return;
                    }

                    shrunk.push(slot.cell);

                    var span = getCellColSpan(slot.cell);

                    if (span > 1) {
                        setCellColSpan(slot.cell, span - 1);
                    } else if (slot.cell.parentNode) {
                        slot.cell.parentNode.removeChild(slot.cell);
                    }
                });

                renderColgroup();
                renderResizeHandles();
            }

            /**
             * Undo a merge on `cell`: shrink it back to a single 1x1 cell and
             * recreate a plain <td> for every grid position it used to cover
             * (its own row for the extra columns, and every row below it was
             * spanning down into, which currently have no <td> there at all).
             */
            function splitCell(cell) {
                var colspan = getCellColSpan(cell);
                var rowspan = getCellRowSpan(cell);

                if (colspan <= 1 && rowspan <= 1) {
                    return;
                }

                var grid = buildGrid();
                var pos = findCellPosition(grid, cell);

                if (! pos) {
                    return;
                }

                var startRow = pos.row;
                var startCol = pos.col;
                var rows = Array.prototype.slice.call(fieldTbody.children);

                setCellColSpan(cell, 1);
                setCellRowSpan(cell, 1);

                for (var r = startRow; r < startRow + rowspan; r++) {
                    var row = rows[r];
                    var rowSlots = grid[r] || [];
                    var referenceCell = null;

                    for (var col = startCol + colspan; col < rowSlots.length; col++) {
                        if (rowSlots[col] && rowSlots[col].owner) {
                            referenceCell = rowSlots[col].cell;
                            break;
                        }
                    }

                    var firstCol = (r === startRow) ? startCol + 1 : startCol;

                    for (var c = firstCol; c < startCol + colspan; c++) {
                        var newCell = document.createElement('td');
                        newCell.innerHTML = '&nbsp;';

                        if (referenceCell) {
                            row.insertBefore(newCell, referenceCell);
                        } else {
                            row.appendChild(newCell);
                        }
                    }
                }

                syncRowActions();
            }

            /**
             * A row fully swallowed by a full-width vertical merge keeps its
             * (now empty) <tr> in the table - removing it would shift every
             * rowspan below it, since rowspan counts actual <tr> siblings.
             * The row-actions sidebar has no such constraint though, so it
             * is kept in sync separately: one action group per row that
             * still has real cells of its own, no more. Matched up by index
             * (not just trimmed off the end) so the group removed/restored
             * is always the one for the row that actually emptied/refilled -
             * the sidebar's first child is a fixed spacer that aligns it
             * with the column headers above the table and is never touched.
             */
            function syncRowActions() {
                var rows = Array.prototype.slice.call(fieldTbody.children);
                var groups = Array.prototype.slice.call(rowsActions.children).slice(1);

                rows.forEach(function (row, index) {
                    var group = groups[index];

                    if (0 === row.children.length) {
                        if (group && group.parentNode) {
                            group.parentNode.removeChild(group);
                        }
                    } else if (! group) {
                        rowsActions.appendChild(rowActionsTemplate.content.cloneNode(true));
                    }
                });
            }

            rowAddButton.addEventListener('click', function () {
                var row = document.createElement('tr');

                columnWidths.forEach(function () {
                    var cell = document.createElement('td');
                    cell.innerHTML = '&nbsp;';
                    row.appendChild(cell);
                });

                fieldTbody.appendChild(row);
                rowsActions.appendChild(rowActionsTemplate.content.cloneNode(true));
                renderResizeHandles();
            });

            renderColumnHeaders();
            renderResizeHandles();

            var resizing = null;

            resizeHandles.addEventListener('mousedown', function (e) {
                var handle = e.target.closest('.signatur-col-resize-handle');

                if (! handle) {
                    return;
                }

                e.preventDefault();

                var index = parseInt(handle.getAttribute('data-index'), 10);

                resizing = {
                    index: index,
                    startX: e.clientX,
                    widthA: columnWidths[index],
                    widthB: columnWidths[index + 1]
                };
            });

            document.addEventListener('mousemove', function (e) {
                if (! resizing) {
                    return;
                }

                var delta = e.clientX - resizing.startX;
                var newA = resizing.widthA + delta;
                var newB = resizing.widthB - delta;

                if (newA < minColumnWidth) {
                    newA = minColumnWidth;
                    newB = resizing.widthA + resizing.widthB - newA;
                }

                if (newB < minColumnWidth) {
                    newB = minColumnWidth;
                    newA = resizing.widthA + resizing.widthB - newB;
                }

                columnWidths[resizing.index] = newA;
                columnWidths[resizing.index + 1] = newB;
                renderColgroup();
                renderResizeHandles();
            });

            function stopResizing() {
                resizing = null;
            }

            document.addEventListener('mouseup', stopResizing);
            document.documentElement.addEventListener('mouseleave', stopResizing);

            window.addEventListener('scroll', renderResizeHandles, true);
            window.addEventListener('resize', renderResizeHandles);

            var columnAddButton = document.getElementById('signatur_column_add');
            var columnCombineButton = document.getElementById('signatur_column_combine');
            var columnRemoveButton = document.getElementById('signatur_column_remove');
            var columnSplitButton = document.getElementById('signatur_column_split');
            var activeColumnCell = null;
            var selectedCells = [];
            var isSelecting = false;
            var selectionAnchor = null;

            function highlightSelection(cells) {
                selectedCells.forEach(function (cell) {
                    cell.classList.remove('signatur-cell-selected');
                });

                selectedCells = cells;

                selectedCells.forEach(function (cell) {
                    cell.classList.add('signatur-cell-selected');
                });
            }

            /**
             * A selection may only grow along one axis from the anchor cell -
             * straight across a row, or straight down a column, never both at
             * once. When the drag moves diagonally, whichever axis moved
             * further wins and the other is locked back to the anchor.
             *
             * Growing down a column also stops at the first row whose cell in
             * that column covers a different set of columns than the
             * anchor's (same colspan alone isn't enough - two cells can both
             * be 2 columns wide yet start at different columns, which is just
             * as invalid a pairing). Such a row, and anything past it, is
             * simply never part of the selection.
             */
            function selectRectangle(fromCell, toCell) {
                var grid = buildGrid();
                var fromPos = findCellPosition(grid, fromCell);
                var toPos = findCellPosition(grid, toCell);

                if (! fromPos || ! toPos) {
                    return [];
                }

                var rowDelta = Math.abs(toPos.row - fromPos.row);
                var colDelta = Math.abs(toPos.col - fromPos.col);
                var minRow, maxRow, minCol, maxCol;

                if (rowDelta >= colDelta) {
                    minCol = maxCol = fromPos.col;

                    var anchorSlot = grid[fromPos.row] && grid[fromPos.row][minCol];
                    var step = toPos.row >= fromPos.row ? 1 : -1;
                    var boundaryRow = fromPos.row;

                    for (var r = fromPos.row + step; r !== toPos.row + step; r += step) {
                        var slot = grid[r] && grid[r][minCol];

                        if (! anchorSlot || ! slot || slot.start !== anchorSlot.start || slot.end !== anchorSlot.end) {
                            break;
                        }

                        boundaryRow = r;
                    }

                    minRow = Math.min(fromPos.row, boundaryRow);
                    maxRow = Math.max(fromPos.row, boundaryRow);
                } else {
                    minCol = Math.min(fromPos.col, toPos.col);
                    maxCol = Math.max(fromPos.col, toPos.col);
                    minRow = maxRow = fromPos.row;
                }

                var cells = [];

                for (var r = minRow; r <= maxRow; r++) {
                    for (var c = minCol; c <= maxCol; c++) {
                        var slot = grid[r] && grid[r][c];

                        if (slot && cells.indexOf(slot.cell) === -1) {
                            cells.push(slot.cell);
                        }
                    }
                }

                return cells;
            }

            /**
             * Cells spread across more than one row are never mergeable
             * unless every row in range has a cell covering that exact same
             * set of columns - matching colspan alone isn't enough, since two
             * cells can both be 2 columns wide yet start at different
             * columns (a "staircase" pairing that isn't a real rectangle).
             * This mirrors the rule selectRectangle() already enforces while
             * dragging, kept here too as a guard against the merge being
             * triggered any other way. A same-row (horizontal) merge has no
             * such constraint.
             */
            function hasVerticalRangeMismatch(cells) {
                var grid = buildGrid();
                var positions = cells.map(function (cell) {
                    return findCellPosition(grid, cell);
                }).filter(function (pos) {
                    return null !== pos;
                });

                if (positions.length < 2) {
                    return false;
                }

                var minRow = Math.min.apply(null, positions.map(function (pos) { return pos.row; }));
                var maxRow = Math.max.apply(null, positions.map(function (pos) { return pos.row; }));

                if (minRow === maxRow) {
                    return false;
                }

                var anchorSlot = grid[positions[0].row] && grid[positions[0].row][positions[0].col];

                for (var i = 0; i < positions.length; i++) {
                    var slot = grid[positions[i].row] && grid[positions[i].row][positions[i].col];

                    if (! anchorSlot || ! slot || slot.start !== anchorSlot.start || slot.end !== anchorSlot.end) {
                        return true;
                    }
                }

                return false;
            }

            /**
             * Mirrors hasVerticalRangeMismatch() for the other direction:
             * cells spread across more than one column are never mergeable
             * unless every one of them spans exactly the same set of rows -
             * matching rowspan alone isn't enough, since two cells can both
             * be 2 rows tall yet start at different rows. A single-row
             * (horizontal-only) merge has no such constraint.
             */
            function hasHorizontalRangeMismatch(cells) {
                var grid = buildGrid();
                var positions = cells.map(function (cell) {
                    return findCellPosition(grid, cell);
                }).filter(function (pos) {
                    return null !== pos;
                });

                if (positions.length < 2) {
                    return false;
                }

                var minCol = Math.min.apply(null, positions.map(function (pos) { return pos.col; }));
                var maxCol = Math.max.apply(null, positions.map(function (pos) { return pos.col; }));

                if (minCol === maxCol) {
                    return false;
                }

                var anchorRowStart = positions[0].row;
                var anchorRowEnd = anchorRowStart + getCellRowSpan(cells[0]);

                for (var i = 0; i < positions.length; i++) {
                    var rowStart = positions[i].row;
                    var rowEnd = rowStart + getCellRowSpan(cells[i]);

                    if (rowStart !== anchorRowStart || rowEnd !== anchorRowEnd) {
                        return true;
                    }
                }

                return false;
            }

            function positionColumnButtons(cell) {
                var buttonSize = 32;
                var gap = 4;
                var showSplit = getCellColSpan(cell) > 1 || getCellRowSpan(cell) > 1;
                var showRemove = columnWidths.length > 1 && 1 === getCellColSpan(cell);
                var buttonCount = 1 + (showSplit ? 1 : 0) + (showRemove ? 1 : 0);
                var groupWidth = buttonSize * buttonCount + gap * (buttonCount - 1);
                var rect = cell.getBoundingClientRect();
                var top = rect.top + (rect.height - buttonSize) / 2;
                var left = rect.left + (rect.width - groupWidth) / 2;

                if (showSplit) {
                    columnSplitButton.style.top = top + 'px';
                    columnSplitButton.style.left = left + 'px';
                    columnSplitButton.style.display = 'inline-flex';
                    left += buttonSize + gap;
                } else {
                    columnSplitButton.style.display = 'none';
                }

                columnAddButton.style.top = top + 'px';
                columnAddButton.style.left = left + 'px';
                columnAddButton.style.display = 'inline-flex';
                left += buttonSize + gap;

                if (showRemove) {
                    columnRemoveButton.style.top = top + 'px';
                    columnRemoveButton.style.left = left + 'px';
                    columnRemoveButton.style.display = 'inline-flex';
                } else {
                    columnRemoveButton.style.display = 'none';
                }
            }

            function positionColumnCombineButton(cells) {
                var buttonSize = 32;
                var top = Infinity;
                var left = Infinity;
                var bottom = -Infinity;
                var right = -Infinity;

                cells.forEach(function (cell) {
                    var rect = cell.getBoundingClientRect();

                    top = Math.min(top, rect.top);
                    left = Math.min(left, rect.left);
                    bottom = Math.max(bottom, rect.bottom);
                    right = Math.max(right, rect.right);
                });

                columnCombineButton.style.top = (top + (bottom - top - buttonSize) / 2) + 'px';
                columnCombineButton.style.left = (left + (right - left - buttonSize) / 2) + 'px';
                columnCombineButton.style.display = 'inline-flex';
            }

            function hideColumnAddButton() {
                columnAddButton.style.display = 'none';
            }

            function hideColumnCombineButton() {
                columnCombineButton.style.display = 'none';
            }

            function hideColumnRemoveButton() {
                columnRemoveButton.style.display = 'none';
            }

            function hideColumnSplitButton() {
                columnSplitButton.style.display = 'none';
            }

            function hideColumnButtons() {
                activeColumnCell = null;
                hideColumnAddButton();
                hideColumnRemoveButton();
                hideColumnSplitButton();
            }

            function clearSelection() {
                highlightSelection([]);
                hideColumnCombineButton();
                hideColumnButtons();
            }

            fieldTbody.addEventListener('mousedown', function (e) {
                var cell = e.target.closest('td');

                if (! cell) {
                    return;
                }

                e.preventDefault();
                isSelecting = true;
                selectionAnchor = cell;
                hideColumnButtons();
                hideColumnCombineButton();
                highlightSelection([cell]);
            });

            fieldTbody.addEventListener('mouseover', function (e) {
                if (! isSelecting) {
                    return;
                }

                var cell = e.target.closest('td');

                if (! cell) {
                    return;
                }

                var cells = selectRectangle(selectionAnchor, cell);

                if (hasVerticalRangeMismatch(cells) || hasHorizontalRangeMismatch(cells)) {
                    return;
                }

                highlightSelection(cells);
            });

            columnRemoveButton.addEventListener('click', function () {
                if (! activeColumnCell || columnWidths.length <= 1) {
                    hideColumnButtons();
                    return;
                }

                var row = activeColumnCell.parentNode;
                var start = getCellStartIndex(row, activeColumnCell);
                var span = getCellColSpan(activeColumnCell);

                removeColumnAt(start + span - 1);
                hideColumnButtons();
            });

            columnSplitButton.addEventListener('click', function () {
                if (! activeColumnCell) {
                    return;
                }

                splitCell(activeColumnCell);
                clearSelection();
            });

            function finishSelection() {
                if (! isSelecting) {
                    return;
                }

                isSelecting = false;

                if (1 === selectedCells.length) {
                    activeColumnCell = selectedCells[0];
                    positionColumnButtons(activeColumnCell);
                } else if (selectedCells.length >= 2) {
                    if (hasVerticalRangeMismatch(selectedCells) || hasHorizontalRangeMismatch(selectedCells)) {
                        clearSelection();
                    } else {
                        positionColumnCombineButton(selectedCells);
                    }
                } else {
                    clearSelection();
                }
            }

            document.addEventListener('mouseup', finishSelection);

            document.documentElement.addEventListener('mouseleave', finishSelection);

            columnAddButton.addEventListener('click', function () {
                if (! activeColumnCell) {
                    return;
                }

                var row = activeColumnCell.parentNode;
                var start = getCellStartIndex(row, activeColumnCell);
                var span = getCellColSpan(activeColumnCell);
                var insertIndex = start + span;
                var neighborWidth = columnWidths[start];
                var newWidth = Math.max(minColumnWidth, Math.floor(neighborWidth / 2));

                columnWidths[start] = Math.max(minColumnWidth, neighborWidth - newWidth);
                insertColumnAt(insertIndex, newWidth);

                hideColumnButtons();
            });

            columnCombineButton.addEventListener('click', function () {
                if (selectedCells.length < 2 || hasVerticalRangeMismatch(selectedCells) || hasHorizontalRangeMismatch(selectedCells)) {
                    return;
                }

                var grid = buildGrid();
                var cellPositions = selectedCells.map(function (cell) {
                    return { cell: cell, pos: findCellPosition(grid, cell) };
                }).filter(function (item) {
                    return null !== item.pos;
                });

                if (cellPositions.length < 2) {
                    return;
                }

                /*
                 * The merged rectangle's true bounds come from each cell's
                 * full extent (its grid slot's start/end for columns, its
                 * own rowspan for rows) - not just its top-left grid
                 * position. Using only the start position here previously
                 * collapsed any cell already wider/taller than 1 back down
                 * to 1 when it got re-set below.
                 */
                var minRow = Infinity;
                var maxRow = -Infinity;
                var minCol = Infinity;
                var maxCol = -Infinity;

                cellPositions.forEach(function (item) {
                    var slot = grid[item.pos.row] && grid[item.pos.row][item.pos.col];

                    if (! slot) {
                        return;
                    }

                    minRow = Math.min(minRow, item.pos.row);
                    maxRow = Math.max(maxRow, item.pos.row + getCellRowSpan(item.cell) - 1);
                    minCol = Math.min(minCol, slot.start);
                    maxCol = Math.max(maxCol, slot.end - 1);
                });

                var rectCells = [];

                for (var r = minRow; r <= maxRow; r++) {
                    for (var c = minCol; c <= maxCol; c++) {
                        var rectSlot = grid[r] && grid[r][c];

                        if (rectSlot && rectCells.indexOf(rectSlot.cell) === -1) {
                            rectCells.push(rectSlot.cell);
                        }
                    }
                }

                var topLeftSlot = grid[minRow][minCol];
                var topLeftCell = topLeftSlot.cell;

                setCellColSpan(topLeftCell, maxCol - minCol + 1);
                setCellRowSpan(topLeftCell, maxRow - minRow + 1);
                topLeftCell.classList.remove('signatur-cell-selected');

                rectCells.forEach(function (cell) {
                    if (cell !== topLeftCell && cell.parentNode) {
                        cell.parentNode.removeChild(cell);
                    }
                });

                syncRowActions();

                selectedCells = [];
                clearSelection();
            });

            document.addEventListener('click', function (e) {
                if (! e.target.closest('#signatur_field_table')) {
                    clearSelection();
                }
            });

            window.addEventListener('scroll', function () {
                hideColumnButtons();
                hideColumnCombineButton();
            }, true);

            modalCancel.addEventListener('click', closeModal);

            modalSave.addEventListener('click', function () {
                if ('width' === activeModalType) {
                    var widthInput = document.getElementById('signatur_width_input');
                    var width = clampWidth(widthInput.value);

                    rowTopWrap.style.width = width + 'px';
                    rowGroup.style.width = (width + 160) + 'px';
                    fieldTable.style.width = width + 'px';

                    var oldWidth = columnWidths.reduce(function (sum, w) {
                        return sum + w;
                    }, 0);
                    var ratio = width / oldWidth;
                    var used = 0;

                    columnWidths = columnWidths.map(function (w, i) {
                        if (i === columnWidths.length - 1) {
                            return Math.max(minColumnWidth, width - used);
                        }

                        var scaled = Math.max(minColumnWidth, Math.round(w * ratio));
                        used += scaled;

                        return scaled;
                    });

                    renderColgroup();
                    renderResizeHandles();

                    paddingSides.forEach(function (side) {
                        var paddingInput = document.getElementById('signatur_padding_' + side);
                        var padding = clampPadding(paddingInput.value);
                        var property = 'padding' + side.charAt(0).toUpperCase() + side.slice(1);

                        fieldTable.style[property] = padding + 'px';
                    });
                }

                closeModal();
            });

            modalOverlay.addEventListener('click', function (e) {
                if (e.target === modalOverlay) {
                    closeModal();
                }
            });

            var form = document.getElementById('signatur_form');
            var settingsInput = document.getElementById('signatur_settings_input');

            form.addEventListener('submit', function () {
                var padding = {};

                paddingSides.forEach(function (side) {
                    var property = 'padding' + side.charAt(0).toUpperCase() + side.slice(1);
                    padding[side] = parseInt(fieldTable.style[property], 10) || 0;
                });

                var rows = Array.prototype.map.call(fieldTbody.children, function (row) {
                    return Array.prototype.map.call(row.children, function (cell) {
                        return [getCellColSpan(cell), getCellRowSpan(cell)];
                    });
                });

                settingsInput.value = JSON.stringify({
                    width: parseInt(fieldTable.style.width, 10) || minWidth,
                    padding: padding,
                    columns: columnWidths,
                    rows: rows
                });
            });
        })();
    </script>
<?php
}
