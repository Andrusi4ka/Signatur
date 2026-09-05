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
            $title = $signature->title;
            $link  = $signature->link;

            $decoded = json_decode((string) $signature->settings, true);

            if (is_array($decoded)) {
                $settings = wp_parse_args($decoded, $settings);
                $settings['padding'] = wp_parse_args(
                    is_array($decoded['padding'] ?? null) ? $decoded['padding'] : array(),
                    $settings['padding']
                );
            }
        } else {
            $id = 0;
        }
    }

    $group_width = $settings['width'] + 80;

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

        #signatur_column_add,
        #signatur_column_combine,
        #signatur_column_remove {
            display: none;
            position: fixed;
            z-index: 100001;
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
                    <table id="signatur_field_table" class="widefat striped" style="width:<?php echo esc_attr($settings['width']); ?>px; padding-top:<?php echo esc_attr($settings['padding']['top']); ?>px; padding-right:<?php echo esc_attr($settings['padding']['right']); ?>px; padding-bottom:<?php echo esc_attr($settings['padding']['bottom']); ?>px; padding-left:<?php echo esc_attr($settings['padding']['left']); ?>px;">
                        <tbody id="signatur_field_tbody">
                            <?php for ($i = 0; $i < $settings['rows']; $i++) : ?>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                    <div id="signatur_rows_actions" style="display:flex; flex-direction:column;">
                        <?php for ($i = 0; $i < $settings['rows']; $i++) : ?>
                            <div class="signatur-row-actions" style="height:38px; display:flex; align-items:center; gap:8px;">
                                <?php signatur_render_row_actions_buttons(); ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div id="signatur_row_bottom_wrap" style="width:<?php echo esc_attr($settings['width']); ?>px; display:flex; justify-content:center; margin-top:8px;">
                    <button type="button" id="signatur_row_add" class="signatur-icon-button" title="<?php esc_attr_e('Add row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add row', 'signatur'); ?>">
                        <img src="<?php echo esc_url(plugins_url('assets/icon-plus.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                    </button>
                </div>
            </div>

            <?php submit_button(__('Save', 'signatur')); ?>
        </form>

        <button type="button" id="signatur_column_add" class="signatur-icon-button" title="<?php esc_attr_e('Add column', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add column', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-plus.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_combine" class="signatur-icon-button" title="<?php esc_attr_e('Combine columns', 'signatur'); ?>" aria-label="<?php esc_attr_e('Combine columns', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-combine.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_remove" class="signatur-icon-button" title="<?php esc_attr_e('Remove column', 'signatur'); ?>" aria-label="<?php esc_attr_e('Remove column', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-minus.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

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

        <template id="signatur_row_template">
            <tr>
                <td>&nbsp;</td>
            </tr>
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
            var rowBottomWrap = document.getElementById('signatur_row_bottom_wrap');
            var fieldTable = document.getElementById('signatur_field_table');
            var fieldTbody = document.getElementById('signatur_field_tbody');
            var rowsActions = document.getElementById('signatur_rows_actions');
            var rowTemplate = document.getElementById('signatur_row_template');
            var rowActionsTemplate = document.getElementById('signatur_row_actions_template');
            var minWidth = 200;
            var paddingSides = ['top', 'right', 'bottom', 'left'];
            var activeModalType = null;

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

            function syncColumnWidths() {
                var rows = Array.prototype.slice.call(fieldTbody.children);
                var maxColumns = rows.reduce(function (max, row) {
                    return Math.max(max, row.children.length);
                }, 1);

                rows.forEach(function (row) {
                    var columns = row.children.length;

                    Array.prototype.forEach.call(row.children, function (cell, i) {
                        if (i === columns - 1 && columns < maxColumns) {
                            cell.setAttribute('colspan', maxColumns - columns + 1);
                        } else {
                            cell.removeAttribute('colspan');
                        }
                    });
                });
            }

            rowAddButton.addEventListener('click', function () {
                fieldTbody.appendChild(rowTemplate.content.cloneNode(true));
                rowsActions.appendChild(rowActionsTemplate.content.cloneNode(true));
                syncColumnWidths();
            });

            syncColumnWidths();

            var columnAddButton = document.getElementById('signatur_column_add');
            var columnCombineButton = document.getElementById('signatur_column_combine');
            var columnRemoveButton = document.getElementById('signatur_column_remove');
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

            function selectRange(row, fromCell, toCell) {
                var cells = Array.prototype.slice.call(row.children);
                var fromIndex = cells.indexOf(fromCell);
                var toIndex = cells.indexOf(toCell);

                if (-1 === fromIndex || -1 === toIndex) {
                    return [];
                }

                var start = Math.min(fromIndex, toIndex);
                var end = Math.max(fromIndex, toIndex);

                return cells.slice(start, end + 1);
            }

            function positionColumnButtons(cell) {
                var buttonSize = 32;
                var gap = 4;
                var showRemove = cell.parentNode.children.length > 1;
                var groupWidth = showRemove ? (buttonSize * 2 + gap) : buttonSize;
                var rect = cell.getBoundingClientRect();
                var top = rect.top + (rect.height - buttonSize) / 2;
                var groupLeft = rect.left + (rect.width - groupWidth) / 2;

                columnAddButton.style.top = top + 'px';
                columnAddButton.style.left = groupLeft + 'px';
                columnAddButton.style.display = 'inline-flex';

                if (showRemove) {
                    columnRemoveButton.style.top = top + 'px';
                    columnRemoveButton.style.left = (groupLeft + buttonSize + gap) + 'px';
                    columnRemoveButton.style.display = 'inline-flex';
                } else {
                    columnRemoveButton.style.display = 'none';
                }
            }

            function positionColumnCombineButton(fromCell, toCell) {
                var buttonSize = 32;
                var fromRect = fromCell.getBoundingClientRect();
                var toRect = toCell.getBoundingClientRect();
                var top = Math.min(fromRect.top, toRect.top) + (fromRect.height - buttonSize) / 2;
                var left = (fromRect.left + toRect.right) / 2 - buttonSize / 2;

                columnCombineButton.style.top = top + 'px';
                columnCombineButton.style.left = left + 'px';
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

            function hideColumnButtons() {
                activeColumnCell = null;
                hideColumnAddButton();
                hideColumnRemoveButton();
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

                if (! cell || cell.parentNode !== selectionAnchor.parentNode) {
                    return;
                }

                highlightSelection(selectRange(cell.parentNode, selectionAnchor, cell));
            });

            columnRemoveButton.addEventListener('click', function () {
                if (! activeColumnCell) {
                    return;
                }

                var row = activeColumnCell.parentNode;

                if (row.children.length > 1) {
                    row.removeChild(activeColumnCell);
                }

                hideColumnButtons();
                syncColumnWidths();
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
                    positionColumnCombineButton(selectedCells[0], selectedCells[selectedCells.length - 1]);
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

                var cell = document.createElement('td');
                cell.innerHTML = '&nbsp;';
                activeColumnCell.parentNode.insertBefore(cell, activeColumnCell.nextElementSibling);

                hideColumnButtons();
                syncColumnWidths();
            });

            columnCombineButton.addEventListener('click', function () {
                if (selectedCells.length < 2) {
                    return;
                }

                var totalSpan = selectedCells.reduce(function (sum, cell) {
                    return sum + (parseInt(cell.getAttribute('colspan'), 10) || 1);
                }, 0);

                var firstCell = selectedCells[0];
                firstCell.setAttribute('colspan', totalSpan);
                firstCell.classList.remove('signatur-cell-selected');

                selectedCells.slice(1).forEach(function (cell) {
                    cell.parentNode.removeChild(cell);
                });

                selectedCells = [];
                clearSelection();
                syncColumnWidths();
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
                    rowBottomWrap.style.width = width + 'px';
                    rowGroup.style.width = (width + 80) + 'px';
                    fieldTable.style.width = width + 'px';

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

                settingsInput.value = JSON.stringify({
                    width: parseInt(fieldTable.style.width, 10) || minWidth,
                    padding: padding,
                    rows: fieldTbody.querySelectorAll('tr').length
                });
            });
        })();
    </script>
<?php
}
