<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register the Signatures menu and its two initial admin pages.
 */
function signatur_register_admin_pages()
{
    // 'none': the icon is drawn by CSS (signatur_menu_icon_style()), so it
    // takes the menu's own colours like the dashicons around it.
    add_menu_page(
        __('All signatures', 'signatur'),
        __('Signatures', 'signatur'),
        SIGNATUR_CAP_EDIT,
        'signatur-all-signatures',
        'signatur_render_all_signatures_page',
        'none',
        25
    );

    add_submenu_page(
        'signatur-all-signatures',
        __('All signatures', 'signatur'),
        __('All signatures', 'signatur'),
        SIGNATUR_CAP_EDIT,
        'signatur-all-signatures',
        'signatur_render_all_signatures_page'
    );

    // The same screen adds a signature and edits one (?id=), so it opens for
    // Edit signatures; without Add it is left out of the menu (no parent:
    // a hidden page) and refuses a new signature itself.
    $add_signature_hook = add_submenu_page(
        current_user_can(SIGNATUR_CAP_CREATE) ? 'signatur-all-signatures' : '',
        __('Add signature', 'signatur'),
        __('Add signature', 'signatur'),
        SIGNATUR_CAP_EDIT,
        'signatur-add-signature',
        'signatur_render_add_signature_page'
    );

    signatur_add_signature_hook_suffix($add_signature_hook);
}

/**
 * The menu icon: assets/menu-icon.svg as a mask over the menu's text
 * colour, so it is as white / grey / blue as the dashicons beside it in
 * every admin colour scheme, and as large (20px) and as placed. Inlined as
 * a data URI: a mask from another origin (a CDN for wp-content) would need
 * CORS and stay blank without it.
 */
function signatur_menu_icon_style()
{
    $svg  = (string) file_get_contents(SIGNATUR_PLUGIN_DIR . 'assets/menu-icon.svg'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
    $icon = 'data:image/svg+xml,' . rawurlencode(trim($svg));
?>
    <style id="signatur-menu-icon">
        #adminmenu .toplevel_page_signatur-all-signatures div.wp-menu-image::before {
            content: "";
            background-color: currentColor;
            -webkit-mask: url("<?php echo $icon; ?>") center / 20px auto no-repeat;
            mask: url("<?php echo $icon; ?>") center / 20px auto no-repeat;
            padding: 0;
            margin-top: 7px;
        }
    </style>
<?php
}

/**
 * The Padding (and Margin) box of a modal, as WP Banner's: a field on each
 * side of a frame, the link in the middle setting the four together
 * (assets/js/spacing.js). The fields keep the ids builder.js reads and
 * fills (`<prefix>_top`, `_right`, `_bottom`, `_left`).
 *
 * @param array  $prefixes kind (padding | margin) => the fields' id prefix.
 * @param string $tip      What the spaces do, in an "i" in the box's corner.
 */
function signatur_render_spacing_box($prefixes, $tip = '')
{
    $names = array(
        'padding' => __('Padding', 'signatur'),
        'margin'  => __('Margin', 'signatur'),
    );
    $sides = array(
        'top'    => __('Top', 'signatur'),
        'right'  => __('Right', 'signatur'),
        'bottom' => __('Bottom', 'signatur'),
        'left'   => __('Left', 'signatur'),
    );
    $link_icon = (string) file_get_contents(SIGNATUR_PLUGIN_DIR . 'assets/icon-spacing-link.svg'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
    // Both: Margin's ring around Padding's (5 x 5); one of them: 3 x 3.
    $classes   = 'signatur-spacing ' . (count($prefixes) > 1 ? 'signatur-spacing--margin' : 'signatur-spacing--single');
?>
    <div class="<?php echo esc_attr($classes); ?>">
        <?php if ($tip) : ?>
            <span class="signatur-spacing__tip"><?php echo signatur_tip($tip); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in signatur_tip(). ?></span>
        <?php endif; ?>
        <?php foreach (array('margin', 'padding') as $kind) : ?>
            <?php if (isset($prefixes[$kind])) : ?>
                <span class="signatur-spacing__ring signatur-spacing__ring--<?php echo esc_attr($kind); ?>" aria-hidden="true"></span>
                <span class="signatur-spacing__name signatur-spacing__name--<?php echo esc_attr($kind); ?>" aria-hidden="true"><?php echo esc_html($names[$kind]); ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
        <span class="signatur-spacing__core" aria-hidden="true"></span>
        <?php foreach ($prefixes as $kind => $prefix) : ?>
            <?php foreach ($sides as $side => $side_name) : ?>
                <span class="signatur-spacing__side signatur-spacing__side--<?php echo esc_attr($kind . '-' . $side); ?> signatur-spacing__at--<?php echo esc_attr($side); ?>">
                    <input type="number" id="<?php echo esc_attr($prefix . '_' . $side); ?>" data-spacing-kind="<?php echo esc_attr($kind); ?>" data-spacing-side="<?php echo esc_attr($side); ?>" min="0" max="500" step="1" placeholder="0" aria-label="<?php echo esc_attr($names[$kind] . ': ' . $side_name . ' (px)'); ?>">
                    <span class="signatur-spacing__unit" aria-hidden="true">px</span>
                </span>
            <?php endforeach; ?>
            <button type="button" class="signatur-spacing__link signatur-spacing__link--<?php echo esc_attr($kind); ?>" data-spacing-link="<?php echo esc_attr($kind); ?>" aria-pressed="false" title="<?php echo esc_attr(sprintf(/* translators: %s: Padding or Margin */ __('%s: set the four sides together', 'signatur'), $names[$kind])); ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: Padding or Margin */ __('%s: set the four sides together', 'signatur'), $names[$kind])); ?>">
                <?php echo $link_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file. ?>
            </button>
        <?php endforeach; ?>
    </div>
<?php
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

    // Also load the front-end stylesheet, purely so the static copy-button
    // preview below the table (see signatur_render_add_signature_page())
    // looks exactly like the real thing on the site - it has no behaviour
    // here, frontend.js is never enqueued on this screen.
    wp_enqueue_style(
        'signatur-frontend',
        plugins_url('assets/css/frontend.css', SIGNATUR_PLUGIN_FILE),
        array(),
        SIGNATUR_VERSION
    );

    // The Padding / Margin box and the colour swatches (WP Banner's controls).
    wp_enqueue_style('signatur-pickr', plugins_url('assets/vendor/pickr/classic.min.css', SIGNATUR_PLUGIN_FILE), array(), '1.9.1');
    wp_enqueue_style('signatur-controls', plugins_url('assets/css/controls.css', SIGNATUR_PLUGIN_FILE), array('signatur-builder', 'signatur-pickr'), SIGNATUR_VERSION);
    wp_enqueue_script('signatur-pickr', plugins_url('assets/vendor/pickr/pickr.min.js', SIGNATUR_PLUGIN_FILE), array(), '1.9.1', true);
    wp_enqueue_script('signatur-spacing', plugins_url('assets/js/spacing.js', SIGNATUR_PLUGIN_FILE), array(), SIGNATUR_VERSION, true);
    wp_enqueue_script('signatur-colors', plugins_url('assets/js/colors.js', SIGNATUR_PLUGIN_FILE), array('signatur-pickr'), SIGNATUR_VERSION, true);
    wp_localize_script(
        'signatur-colors',
        'signaturColors',
        array(
            'palette' => signatur_palette_colors(),
            'i18n'    => array(
                'dialog'      => __('Color picker', 'signatur'),
                'toggle'      => __('Choose a color', 'signatur'),
                'swatch'      => __('Color', 'signatur'),
                'cancel'      => __('Cancel', 'signatur'),
                'cancelHelp'  => __('Back to the color it had when opened', 'signatur'),
                'value'       => __('Color value', 'signatur'),
                'palette'     => __('Color and brightness', 'signatur'),
                'hue'         => __('Hue', 'signatur'),
                'opacity'     => __('Opacity', 'signatur'),
                'default'     => __('Default', 'signatur'),
                'defaultHelp' => __('Back to the default color', 'signatur'),
                'dropper'     => __('Pick a color from the screen', 'signatur'),
            ),
        )
    );

    // The preview at the right (includes/preview.php): after builder.js, whose state it posts.
    wp_enqueue_script('signatur-preview', plugins_url('assets/js/preview.js', SIGNATUR_PLUGIN_FILE), array('signatur-builder'), SIGNATUR_VERSION, true);
    wp_localize_script(
        'signatur-preview',
        'signaturPreview',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('signatur_preview'),
            'failed'  => __('The preview could not be loaded. Try Update again.', 'signatur'),
        )
    );

    // The help: "i" tips, the icon buttons' tips and the instructions' modals.
    wp_enqueue_style('dashicons');
    wp_enqueue_script('signatur-tips', plugins_url('assets/js/tips.js', SIGNATUR_PLUGIN_FILE), array(), SIGNATUR_VERSION, true);
    wp_localize_script('signatur-tips', 'signaturTips', array('buttons' => signatur_button_tips()));

    wp_enqueue_script(
        'signatur-builder',
        plugins_url('assets/js/builder.js', SIGNATUR_PLUGIN_FILE),
        array('signatur-spacing', 'signatur-colors', 'signatur-tips'),
        SIGNATUR_VERSION,
        true
    );

    wp_localize_script(
        'signatur-builder',
        'signaturBuilder',
        array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'uploadNonce' => wp_create_nonce('signatur_upload_image'),
            'socialIcons' => array_combine(
                array_keys(signatur_get_social_networks()),
                array_map(
                    function ($network) {
                        return plugins_url('assets/' . $network['icon'], SIGNATUR_PLUGIN_FILE);
                    },
                    signatur_get_social_networks()
                )
            ),
            'fieldPhotoIcon' => plugins_url('assets/icon-profil.svg', SIGNATUR_PLUGIN_FILE),
            'i18n'        => array(
                'uploadFailed' => __('Image upload failed.', 'signatur'),
            ),
        )
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
        <?php if (current_user_can(SIGNATUR_CAP_CREATE)) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=signatur-add-signature')); ?>" class="page-title-action">
                <?php esc_html_e('Add signature', 'signatur'); ?>
            </a>
        <?php endif; ?>
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
                                <?php if (current_user_can(SIGNATUR_CAP_DELETE)) : ?>
                                    |
                                    <a href="<?php echo $delete_url; ?>" onclick="return confirm('<?php echo $confirm; ?>');" class="submitdelete"><?php esc_html_e('Delete', 'signatur'); ?></a>
                                <?php endif; ?>
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
    <button type="button" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Edit row', 'signatur'); ?>" data-modal-type="row" title="<?php esc_attr_e('Edit row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Edit row', 'signatur'); ?>">
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
    $id       = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $title    = '';
    $settings = signatur_get_default_settings();

    if ($id) {
        global $wpdb;

        $signature = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT title, settings FROM ' . signatur_get_table_name() . ' WHERE id = %d',
                $id
            )
        );

        if ($signature) {
            $title    = $signature->title;
            $settings = json_decode(signatur_sanitize_settings($signature->settings), true);
        } else {
            $id = 0;
        }
    }

    // A new signature (also a missing one) needs Add.
    if (! $id && ! current_user_can(SIGNATUR_CAP_CREATE)) {
        wp_die(esc_html__('You are not allowed to perform this action.', 'signatur'));
    }

    $group_width = $settings['width'] + 200;

    $page_title = $id ? __('Edit signature', 'signatur') : __('Add signature', 'signatur');
?>
    <div class="wrap">
        <h1><?php echo esc_html($page_title); ?></h1>

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

        <?php signatur_render_help(); ?>

        <form id="signatur_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('signatur_save_signature'); ?>
            <input type="hidden" name="action" value="signatur_save_signature">
            <input type="hidden" name="signatur_id" value="<?php echo esc_attr($id); ?>">

            <div id="titlediv">
                <div id="titlewrap">
                    <label class="screen-reader-text" id="title-prompt-text" for="title"><?php esc_html_e('Add title', 'signatur'); ?></label>
                    <input type="text" name="signatur_title" size="30" value="<?php echo esc_attr($title); ?>" id="title" spellcheck="true" autocomplete="off" placeholder="<?php esc_attr_e('Add title', 'signatur'); ?>" required>
                </div>
            </div>

            <input type="hidden" name="signatur_settings" id="signatur_settings_input" value="<?php echo esc_attr(wp_json_encode($settings)); ?>">

            <?php // The editor at the left, the preview at the right (includes/preview.php). ?>
            <div class="signatur-workspace">
            <div class="signatur-workspace__editor">
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
                    <div id="signatur_rows_numbers" class="signatur-editor-only" style="display:flex; flex-direction:column; margin-right:8px;">
                        <div class="signatur-row-number signatur-row-number-spacer" aria-hidden="true"></div>
                        <?php for ($i = 1, $signatur_rows_total = count($settings['rows']); $i <= $signatur_rows_total; $i++) : ?>
                            <div class="signatur-row-number"><?php echo (int) $i; ?></div>
                        <?php endfor; ?>
                    </div>
                    <table id="signatur_field_table" class="widefat striped" data-border="<?php echo empty($settings['border']) ? '0' : '1'; ?>" style="width:<?php echo esc_attr($settings['width']); ?>px; margin-right:8px;">
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
                                    <th class="signatur-column-header" data-index="<?php echo esc_attr($column_index); ?>"><?php echo esc_html(signatur_column_letter($column_index)); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="signatur_field_tbody">
                            <?php foreach ($settings['rows'] as $row_index => $row_cells) : ?>
                                <?php
                                $row_padding     = isset($settings['row_padding'][$row_index])
                                    ? $settings['row_padding'][$row_index]
                                    : signatur_sanitize_padding(array());
                                $row_border      = isset($settings['row_border'][$row_index])
                                    ? $settings['row_border'][$row_index]
                                    : signatur_sanitize_row_border(array());
                                $row_background  = isset($settings['row_background'][$row_index])
                                    ? $settings['row_background'][$row_index]
                                    : '';
                                $cell_style_base = signatur_padding_css($row_padding) . ';' . signatur_row_border_css($row_border);

                                if ('' !== $row_background) {
                                    $cell_style_base .= ' background-color:' . $row_background . ';';
                                }
                                ?>
                                <tr data-padding="<?php echo esc_attr(wp_json_encode($row_padding)); ?>" data-border="<?php echo esc_attr(wp_json_encode($row_border)); ?>" data-background="<?php echo esc_attr($row_background); ?>">
                                    <?php foreach ($row_cells as $cell_span) : ?>
                                        <?php
                                        $colspan = $cell_span[0];
                                        $rowspan = $cell_span[1];
                                        $element = isset($cell_span[2]) ? $cell_span[2] : null;
                                        $style   = $cell_style_base;
                                        $attrs   = '';

                                        if ($colspan > 1) {
                                            $attrs .= ' colspan="' . (int) $colspan . '"';
                                        }

                                        if ($rowspan > 1) {
                                            $attrs .= ' rowspan="' . (int) $rowspan . '"';
                                        }

                                        if ($element && 'text' === $element['type']) {
                                            $style .= ' ' . signatur_padding_css(signatur_sum_padding($row_padding, isset($element['padding']) ? $element['padding'] : array())) . ';';
                                            $style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';
                                            $attrs .= ' data-element="' . esc_attr(wp_json_encode($element)) . '"';
                                        } elseif ($element && 'image' === $element['type']) {
                                            $style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';
                                            $attrs .= ' data-element="' . esc_attr(wp_json_encode($element)) . '"';
                                        } elseif ($element && 'social' === $element['type']) {
                                            $style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';
                                            $attrs .= ' data-element="' . esc_attr(wp_json_encode($element)) . '"';
                                        } elseif ($element && 'field' === $element['type']) {
                                            $style .= ' ' . signatur_padding_css(signatur_sum_padding($row_padding, isset($element['padding']) ? $element['padding'] : array())) . ';';
                                            $style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';
                                            $attrs .= ' data-element="' . esc_attr(wp_json_encode($element)) . '"';
                                            $attrs .= ' class="signatur-field-cell"';
                                        }
                                        ?>
                                        <td<?php echo $attrs; ?> style="<?php echo esc_attr($style); ?>"><?php
                                            if ($element && 'text' === $element['type']) {
                                                echo signatur_render_text_span($element);
                                            } elseif ($element && 'image' === $element['type']) {
                                                echo signatur_render_image_element($element);
                                            } elseif ($element && 'social' === $element['type']) {
                                                echo signatur_render_social_element($element);
                                            } elseif ($element && 'field' === $element['type']) {
                                                echo signatur_render_field_element($element, 'editor');
                                            } else {
                                                echo '&nbsp;';
                                            }
                                        ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div id="signatur_row_add_wrap" class="signatur-editor-only" style="width:<?php echo esc_attr($settings['width']); ?>px; margin:8px 0 0; display:flex; justify-content:center;">
                    <button type="button" id="signatur_row_add" class="signatur-icon-button" title="<?php esc_attr_e('Add row', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add row', 'signatur'); ?>">
                        <img src="<?php echo esc_url(plugins_url('assets/icon-plus-row.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                    </button>
                </div>

                <?php
                /*
                 * A static preview of the "Copy signature" button shown on
                 * the front end - purely illustrative, so it carries no
                 * data-target / data-label-copied and does nothing when
                 * clicked (frontend.js, which is what makes the real button
                 * work, is never loaded on this admin screen). Its own
                 * appearance settings (signatur_get_default_copy_button())
                 * are edited via the button next to it.
                 *
                 * The preview always sits centered here regardless of the
                 * stored `align` - that setting only affects the real
                 * front-end placement (signatur_render_shortcode()); the
                 * admin editor just needs one predictable spot for it and
                 * its edit button.
                 */
                ?>
                <div id="signatur_copy_button_section" class="signatur-editor-only" style="width:<?php echo esc_attr($settings['width']); ?>px; margin:32px 0 0; text-align:center;">
                    <p style="margin:0 0 6px; font-size:11px; color:#646970;"><?php esc_html_e('Preview: how the copy button looks on the front end', 'signatur'); ?></p>
                    <div id="signatur_copy_button_wrap" style="display:inline-flex; align-items:center; gap:8px;" data-copy-button="<?php echo esc_attr(wp_json_encode($settings['copy_button'])); ?>">
                        <p class="signatur-copy" style="margin:0;">
                            <button type="button" id="signatur_copy_button_preview" class="signatur-copy-button" style="<?php echo esc_attr(signatur_copy_button_style($settings['copy_button'], false)); ?>"><?php echo esc_html($settings['copy_button']['text']); ?></button>
                        </p>
                        <button type="button" id="signatur_copy_button_edit" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Edit copy button', 'signatur'); ?>" data-modal-type="copy_button" title="<?php esc_attr_e('Edit copy button', 'signatur'); ?>" aria-label="<?php esc_attr_e('Edit copy button', 'signatur'); ?>">
                            <img src="<?php echo esc_url(plugins_url('assets/icon-edit.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
                        </button>
                    </div>
                </div>
            </div>
            </div>
            <?php signatur_render_preview_panel(); ?>
            </div>

            <?php submit_button(__('Save', 'signatur')); ?>
        </form>

        <button type="button" id="signatur_cell_edit_element" class="signatur-icon-button" data-title-text="<?php echo esc_attr__('Edit text', 'signatur'); ?>" data-title-image="<?php echo esc_attr__('Edit image', 'signatur'); ?>" data-title-social="<?php echo esc_attr__('Edit social', 'signatur'); ?>" data-title-field="<?php echo esc_attr__('Edit field', 'signatur'); ?>" title="<?php esc_attr_e('Edit element', 'signatur'); ?>" aria-label="<?php esc_attr_e('Edit element', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-edit-element.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_cell_delete_element" class="signatur-icon-button" title="<?php esc_attr_e('Delete element', 'signatur'); ?>" aria-label="<?php esc_attr_e('Delete element', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-delete.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_cell_copy_element" class="signatur-icon-button" title="<?php esc_attr_e('Copy element', 'signatur'); ?>" aria-label="<?php esc_attr_e('Copy element', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-copy-element.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_cell_paste_element" class="signatur-icon-button" title="<?php esc_attr_e('Paste element', 'signatur'); ?>" aria-label="<?php esc_attr_e('Paste element', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-paste-element.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_cell_add_element" class="signatur-icon-button" data-modal-title="<?php echo esc_attr__('Add element', 'signatur'); ?>" data-modal-type="element" title="<?php esc_attr_e('Add element', 'signatur'); ?>" aria-label="<?php esc_attr_e('Add element', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-plus.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <button type="button" id="signatur_column_split" class="signatur-icon-button" title="<?php esc_attr_e('Split cell', 'signatur'); ?>" aria-label="<?php esc_attr_e('Split cell', 'signatur'); ?>">
            <img src="<?php echo esc_url(plugins_url('assets/icon-shift.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="16" height="16">
        </button>

        <div id="signatur_row_actions" class="signatur-editor-only">
            <?php signatur_render_row_actions_buttons(); ?>
        </div>

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
                <?php echo signatur_tip(__('How wide the whole signature is in the email. 450–600 px suits most emails; the columns share this width.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p style="display:flex; align-items:center; gap:6px; margin:12px 0 0;">
                <label for="signatur_border_toggle" style="display:flex; align-items:center; gap:6px;">
                    <input type="checkbox" id="signatur_border_toggle">
                    <?php esc_html_e('Border', 'signatur'); ?>
                </label>
                <?php echo signatur_tip(__('A thin grey line around every cell, handy to see the grid while building. It is copied with the signature too: turn it off if you do not want it in emails.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>
        </template>

        <template id="signatur_modal_template_row">
            <div class="signatur-spacing-field">
                <?php signatur_render_spacing_box(array('padding' => 'signatur_row_padding'), __('Space inside every cell of this row, around its content, in px. The link sets the four sides together.', 'signatur')); ?>
            </div>

            <p style="display:flex; align-items:center; gap:6px; margin:12px 0 0;">
                <span style="font-weight:600;"><?php esc_html_e('Border', 'signatur'); ?></span>
                <select id="signatur_row_border_style">
                    <option value="solid">solid</option>
                    <option value="dashed">dashed</option>
                    <option value="dotted">dotted</option>
                </select>
                <input type="number" id="signatur_row_border_width" class="small-text" min="0" max="20" step="1" placeholder="px" style="width:50px;">
                <input type="color" id="signatur_row_border_color" value="#000000">
                <select id="signatur_row_border_sides">
                    <option value="none"><?php esc_html_e('None', 'signatur'); ?></option>
                    <option value="top"><?php esc_html_e('Top', 'signatur'); ?></option>
                    <option value="bottom"><?php esc_html_e('Bottom', 'signatur'); ?></option>
                    <option value="both"><?php esc_html_e('Both', 'signatur'); ?></option>
                </select>
                <?php echo signatur_tip(__('A line above, below or on both sides of this row: its style, width in px, colour and where. E.g. a divider between the name and the contacts.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p style="display:flex; align-items:center; gap:6px; margin:12px 0 0;">
                <span style="font-weight:600;"><?php esc_html_e('Background', 'signatur'); ?></span>
                <input type="color" id="signatur_row_background_color" value="#ffffff">
                <button type="button" id="signatur_row_background_clear" class="button"><?php esc_html_e('Clear', 'signatur'); ?></button>
                <?php echo signatur_tip(__('The row\'s background colour. Clear: none, the email\'s own background shows.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>
        </template>

        <template id="signatur_modal_template_copy_button">
            <p style="display:flex; align-items:center; gap:6px; margin:0;">
                <input type="text" id="signatur_copy_button_text" class="regular-text" style="flex:1 1 auto; width:auto;" aria-label="<?php esc_attr_e('Button text', 'signatur'); ?>">
                <?php echo signatur_tip(__('The button\'s label on the page. Visitors click it to copy the signature with what they filled in.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <?php
            // As WP Banner's Button: sample buttons for Background and
            // Corners (builder.js keeps the choice in the hidden inputs).
            $signatur_choices = array(
                'style' => array(
                    __('Background', 'signatur'),
                    array(
                        'filled'    => __('Filled', 'signatur'),
                        'bordered'  => __('Border', 'signatur'),
                        'underline' => __('Underline', 'signatur'),
                    ),
                    __('Filled: a button in its colour. Border: only its outline. Underline: a line under the text.', 'signatur'),
                ),
                'shape' => array(
                    __('Corners', 'signatur'),
                    array(
                        'square'  => __('Square', 'signatur'),
                        'rounded' => __('Rounded', 'signatur'),
                        'round'   => __('Round', 'signatur'),
                    ),
                    __('How round the button\'s corners are. Not for Underline.', 'signatur'),
                ),
            );
            ?>
            <?php foreach ($signatur_choices as $signatur_key => $signatur_choice) : ?>
                <div class="signatur-choice-field" id="signatur_copy_button_<?php echo esc_attr($signatur_key); ?>_field">
                    <span class="signatur-choice-field__label signatur-label-tip"><?php echo esc_html($signatur_choice[0]); ?> <?php echo signatur_tip($signatur_choice[2]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <input type="hidden" id="signatur_copy_button_<?php echo esc_attr($signatur_key); ?>">
                    <div class="signatur-button-choices" role="group" aria-label="<?php echo esc_attr($signatur_choice[0]); ?>">
                        <?php foreach ($signatur_choice[1] as $signatur_value => $signatur_name) : ?>
                            <button type="button" class="signatur-button-choice" data-choice="<?php echo esc_attr($signatur_key); ?>" data-value="<?php echo esc_attr($signatur_value); ?>" aria-pressed="false">
                                <span class="signatur-button-choice__sample signatur-button-choice__sample--<?php echo esc_attr($signatur_value); ?>"><?php echo esc_html($signatur_name); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="signatur-text-controls" style="margin-top:12px;">
                <label for="signatur_copy_button_size"><?php esc_html_e('Size', 'signatur'); ?></label>
                <select id="signatur_copy_button_size">
                    <option value="extra-small"><?php esc_html_e('Extra Small', 'signatur'); ?></option>
                    <option value="small"><?php esc_html_e('Small', 'signatur'); ?></option>
                    <option value="default"><?php esc_html_e('Default', 'signatur'); ?></option>
                    <option value="large"><?php esc_html_e('Large', 'signatur'); ?></option>
                    <option value="extra-large"><?php esc_html_e('Extra Large', 'signatur'); ?></option>
                </select>
                <?php echo signatur_tip(__('Preset sizes: padding, height and font size together.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <select id="signatur_copy_button_align" aria-label="<?php esc_attr_e('Horizontal alignment', 'signatur'); ?>">
                    <option value="left"><?php esc_html_e('Left', 'signatur'); ?></option>
                    <option value="center"><?php esc_html_e('Center', 'signatur'); ?></option>
                    <option value="right"><?php esc_html_e('Right', 'signatur'); ?></option>
                </select>
                <?php echo signatur_tip(__('Where the button sits under the signature on the page: left, centre or right.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <div class="signatur-text-controls" style="margin-top:12px;">
                <label for="signatur_copy_button_color"><?php esc_html_e('Fill / border color', 'signatur'); ?></label>
                <input type="color" id="signatur_copy_button_color" value="#2271b1">
                <?php echo signatur_tip(__('Filled: the button\'s color; Border and Underline: their line\'s.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <label for="signatur_copy_button_text_color"><?php esc_html_e('Text color', 'signatur'); ?></label>
                <input type="color" id="signatur_copy_button_text_color" value="#ffffff">
            </div>

            <div class="signatur-spacing-field">
                <?php signatur_render_spacing_box(array('margin' => 'signatur_copy_button_margin'), __('Space around the button on the page, in px, e.g. its distance from the signature above it.', 'signatur')); ?>
            </div>
        </template>

        <template id="signatur_modal_template_element">
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" id="signatur_add_text_element" class="signatur-icon-button signatur-element-button" data-modal-title="<?php echo esc_attr__('Add text', 'signatur'); ?>" data-modal-type="text" title="<?php esc_attr_e('Text', 'signatur'); ?>" aria-label="<?php esc_attr_e('Text', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-add-text.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
                <button type="button" id="signatur_add_image_element" class="signatur-icon-button signatur-element-button" data-modal-title="<?php echo esc_attr__('Add image', 'signatur'); ?>" data-modal-type="image" title="<?php esc_attr_e('Image', 'signatur'); ?>" aria-label="<?php esc_attr_e('Image', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-add-image.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
                <button type="button" id="signatur_add_social_element" class="signatur-icon-button signatur-element-button" data-modal-title="<?php echo esc_attr__('Add social', 'signatur'); ?>" data-modal-type="social" title="<?php esc_attr_e('Social', 'signatur'); ?>" aria-label="<?php esc_attr_e('Social', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-add-social.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
                <button type="button" id="signatur_add_field_element" class="signatur-icon-button signatur-element-button signatur-element-button--field" data-modal-title="<?php echo esc_attr__('Add field', 'signatur'); ?>" data-modal-type="field" title="<?php esc_attr_e('Field', 'signatur'); ?>" aria-label="<?php esc_attr_e('Field', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-add-input.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
            </div>
        </template>

        <template id="signatur_modal_template_text">
            <p style="display:flex; align-items:flex-start; gap:6px; margin:0;">
                <textarea id="signatur_text_input" rows="3" class="large-text" style="flex:1 1 auto; width:auto;"></textarea>
                <?php echo signatur_tip(__('The text as it shows in the email, e.g. a name or a job title. Enter starts a new line. Everyone who copies the signature gets the same text: for what each person writes, use a Field.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <div class="signatur-text-controls">
                <input type="number" id="signatur_text_size" class="small-text" min="8" max="72" step="1" style="width:56px;">
                <span><?php esc_html_e('pt', 'signatur'); ?></span>
                <?php echo signatur_tip(__('Font size in points, as email programs use; 9–11 pt is usual for signatures. The font is Arial, the one that looks the same in every email program.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

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

            <p style="display:flex; align-items:center; gap:6px; margin:12px 0 0;">
                <input type="url" id="signatur_text_link" class="regular-text" style="flex:1 1 auto; width:auto;" placeholder="<?php echo esc_attr__('https://example.com', 'signatur'); ?>" aria-label="<?php echo esc_attr__('https://example.com', 'signatur'); ?>">
                <?php echo signatur_tip(__('Optional: makes the text a link. A web address (https://…), mailto:name@example.com for an email or tel:+4712345678 for a phone.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <div class="signatur-spacing-field">
                <?php signatur_render_spacing_box(array('padding' => 'signatur_text_padding'), __('Extra space around this text, in px, added to its row\'s.', 'signatur')); ?>
            </div>
        </template>

        <template id="signatur_modal_template_image">
            <p style="display:flex; align-items:center; gap:6px; margin:0;">
                <button type="button" id="signatur_image_upload" class="button"><?php esc_html_e('Upload image', 'signatur'); ?></button>
                <input type="file" id="signatur_image_file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                <input type="hidden" id="signatur_image_url">
                <?php echo signatur_tip(__('PNG or JPG, the same for everyone (a logo, a banner). For a sharp image, upload one about twice as wide as its Width below.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p style="margin:12px 0 0;">
                <label for="signatur_image_link" class="signatur-label-tip" style="margin-bottom:4px;"><?php esc_html_e('Link', 'signatur'); ?> <?php echo signatur_tip(__('Optional: the page that opens when the image is clicked in the email, e.g. your website.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                <input type="url" id="signatur_image_link" class="regular-text" style="width:100%;" placeholder="<?php echo esc_attr__('https://example.com', 'signatur'); ?>">
            </p>

            <div class="signatur-text-controls" style="margin-top:12px;">
                <label for="signatur_image_width"><?php esc_html_e('Width', 'signatur'); ?></label>
                <input type="number" id="signatur_image_width" class="small-text" min="1" max="1000" step="1" style="width:70px;">
                <span><?php esc_html_e('px', 'signatur'); ?></span>
                <?php echo signatur_tip(__('How wide the image shows in the email, in px; its height follows. It never gets wider than its cell.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <select id="signatur_image_align" aria-label="<?php esc_attr_e('Horizontal alignment', 'signatur'); ?>">
                    <option value="left"><?php esc_html_e('Left', 'signatur'); ?></option>
                    <option value="center"><?php esc_html_e('Center', 'signatur'); ?></option>
                    <option value="right"><?php esc_html_e('Right', 'signatur'); ?></option>
                </select>

                <select id="signatur_image_valign" aria-label="<?php esc_attr_e('Vertical alignment', 'signatur'); ?>">
                    <option value="top"><?php esc_html_e('Top', 'signatur'); ?></option>
                    <option value="middle"><?php esc_html_e('Middle', 'signatur'); ?></option>
                    <option value="bottom"><?php esc_html_e('Bottom', 'signatur'); ?></option>
                </select>
            </div>

            <div class="signatur-spacing-field">
                <?php signatur_render_spacing_box(array('padding' => 'signatur_image_padding'), __('Space around the image, in px.', 'signatur')); ?>
            </div>

            <p id="signatur_image_svg_warning" class="signatur-svg-warning" hidden><?php echo esc_html(signatur_svg_message()); ?></p>

            <p style="margin:12px 0 0;">
                <img id="signatur_image_preview" src="" alt="" style="max-width:100%; display:none;">
            </p>
        </template>

        <template id="signatur_modal_template_social">
            <p style="margin:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <?php foreach (signatur_get_social_networks() as $network_key => $network) : ?>
                    <button type="button" class="signatur-icon-button signatur-social-network-button" data-network="<?php echo esc_attr($network_key); ?>" title="<?php echo esc_attr($network['label']); ?>" aria-label="<?php echo esc_attr($network['label']); ?>">
                        <img src="<?php echo esc_url(plugins_url('assets/' . $network['icon'], SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="20" height="20">
                    </button>
                <?php endforeach; ?>
                <?php echo signatur_tip(__('Choose the network: its icon is used. For another network, upload its icon below.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p style="margin:12px 0 0; display:flex; align-items:center; gap:10px;">
                <span id="signatur_social_preview_wrap" style="display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:4px; overflow:hidden; background:#f6f7f7; flex-shrink:0;">
                    <img id="signatur_social_preview" src="" alt="" style="max-width:100%; max-height:100%; display:none;">
                </span>
                <span>
                    <button type="button" id="signatur_social_upload" class="button"><?php esc_html_e('Upload icon', 'signatur'); ?></button>
                    <input type="file" id="signatur_social_file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                    <input type="hidden" id="signatur_social_icon_url">
                    <input type="hidden" id="signatur_social_network" value="facebook">
                </span>
                <?php echo signatur_tip(__('Optional: your own icon instead of the network\'s, a square PNG or JPG.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p id="signatur_social_svg_warning" class="signatur-svg-warning" hidden><?php echo esc_html(signatur_svg_message()); ?></p>

            <p style="margin:12px 0 0;">
                <label for="signatur_social_link" class="signatur-label-tip" style="margin-bottom:4px;"><?php esc_html_e('Link', 'signatur'); ?> <?php echo signatur_tip(__('Required: the profile page the icon opens, e.g. https://www.linkedin.com/company/…', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                <input type="url" id="signatur_social_link" class="regular-text" style="width:100%;" placeholder="<?php echo esc_attr__('https://example.com', 'signatur'); ?>" required>
            </p>

            <div class="signatur-text-controls" style="margin-top:12px;">
                <label for="signatur_social_height"><?php esc_html_e('Height', 'signatur'); ?></label>
                <input type="number" id="signatur_social_height" class="small-text" min="8" max="200" step="1" style="width:70px;">
                <span><?php esc_html_e('px', 'signatur'); ?></span>
                <?php echo signatur_tip(__('The icon\'s height in px; 20–32 px is usual.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <select id="signatur_social_align" aria-label="<?php esc_attr_e('Horizontal alignment', 'signatur'); ?>">
                    <option value="left"><?php esc_html_e('Left', 'signatur'); ?></option>
                    <option value="center"><?php esc_html_e('Center', 'signatur'); ?></option>
                    <option value="right"><?php esc_html_e('Right', 'signatur'); ?></option>
                </select>

                <select id="signatur_social_valign" aria-label="<?php esc_attr_e('Vertical alignment', 'signatur'); ?>">
                    <option value="top"><?php esc_html_e('Top', 'signatur'); ?></option>
                    <option value="middle"><?php esc_html_e('Middle', 'signatur'); ?></option>
                    <option value="bottom"><?php esc_html_e('Bottom', 'signatur'); ?></option>
                </select>
            </div>

            <div class="signatur-spacing-field">
                <?php signatur_render_spacing_box(array('padding' => 'signatur_social_padding'), __('Space around the icon, in px, e.g. between icons in cells side by side.', 'signatur')); ?>
            </div>
        </template>

        <template id="signatur_modal_template_field">
            <p style="margin:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <button type="button" class="signatur-icon-button signatur-field-type-button" data-type="text" title="<?php esc_attr_e('Text', 'signatur'); ?>" aria-label="<?php esc_attr_e('Text', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-add-text.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
                <button type="button" class="signatur-icon-button signatur-field-type-button" data-type="email" title="<?php esc_attr_e('Email', 'signatur'); ?>" aria-label="<?php esc_attr_e('Email', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-email.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
                <button type="button" class="signatur-icon-button signatur-field-type-button" data-type="tel" title="<?php esc_attr_e('Phone', 'signatur'); ?>" aria-label="<?php esc_attr_e('Phone', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-phone.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
                <button type="button" class="signatur-icon-button signatur-field-type-button" data-type="photo" title="<?php esc_attr_e('Profile photo', 'signatur'); ?>" aria-label="<?php esc_attr_e('Profile photo', 'signatur'); ?>">
                    <img src="<?php echo esc_url(plugins_url('assets/icon-add-profil.svg', SIGNATUR_PLUGIN_FILE)); ?>" alt="" width="24" height="24">
                </button>
                <?php echo signatur_tip(__('What the visitor fills in on the page before copying: text (a name, a job title), an email, a phone number or their photo. It shows as a yellow cell on the page.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p style="margin:12px 0 0; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <select id="signatur_field_type" aria-label="<?php esc_attr_e('Type', 'signatur'); ?>" style="min-width:150px;">
                    <option value="text"><?php esc_html_e('Text', 'signatur'); ?></option>
                    <option value="email"><?php esc_html_e('Email', 'signatur'); ?></option>
                    <option value="tel"><?php esc_html_e('Phone', 'signatur'); ?></option>
                    <option value="photo"><?php esc_html_e('Profile photo', 'signatur'); ?></option>
                </select>
                <label style="display:inline-flex; align-items:center; gap:6px;">
                    <input type="checkbox" id="signatur_field_required">
                    <?php esc_html_e('Required', 'signatur'); ?>
                </label>
                <?php echo signatur_tip(__('Required: the visitor cannot copy the signature until this is filled in.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <label id="signatur_field_link_wrap" style="display:inline-flex; align-items:center; gap:6px;">
                    <input type="checkbox" id="signatur_field_link">
                    <?php esc_html_e('Link', 'signatur'); ?>
                    <?php echo signatur_tip(__('Link: in the copied signature an email becomes a mailto: link, a phone number a tel: link (a tap calls it), a text a web link.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </label>
            </p>

            <p id="signatur_field_placeholder_wrap" style="display:flex; align-items:center; gap:6px; margin:12px 0 0;">
                <input type="text" id="signatur_field_placeholder" class="regular-text" style="flex:1 1 auto; width:auto;" placeholder="<?php esc_attr_e('Placeholder', 'signatur'); ?>" aria-label="<?php esc_attr_e('Placeholder', 'signatur'); ?>">
                <?php echo signatur_tip(__('The hint shown in the empty field on the page, e.g. "Your name". It is not copied.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <div id="signatur_field_font_row" class="signatur-text-controls" style="margin-top:12px;">
                <input type="number" id="signatur_field_size" class="small-text" min="8" max="72" step="1" style="width:56px;">
                <span><?php esc_html_e('pt', 'signatur'); ?></span>
                <?php echo signatur_tip(__('The look of what the visitor types, in the signature: size in points, colour, bold, italic, underline.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <input type="color" id="signatur_field_color" value="#000000">

                <button type="button" id="signatur_field_bold" class="button signatur-text-toggle" title="<?php esc_attr_e('Bold', 'signatur'); ?>" aria-label="<?php esc_attr_e('Bold', 'signatur'); ?>" style="font-weight:700;">B</button>
                <button type="button" id="signatur_field_italic" class="button signatur-text-toggle" title="<?php esc_attr_e('Italic', 'signatur'); ?>" aria-label="<?php esc_attr_e('Italic', 'signatur'); ?>" style="font-style:italic;">I</button>
                <button type="button" id="signatur_field_underline" class="button signatur-text-toggle" title="<?php esc_attr_e('Underline', 'signatur'); ?>" aria-label="<?php esc_attr_e('Underline', 'signatur'); ?>" style="text-decoration:underline;">U</button>
            </div>

            <p id="signatur_field_width_row" class="signatur-text-controls" hidden>
                <label for="signatur_field_width"><?php esc_html_e('Width', 'signatur'); ?></label>
                <input type="number" id="signatur_field_width" class="small-text" min="1" max="1000" step="1" style="width:70px;">
                <span><?php esc_html_e('px', 'signatur'); ?></span>
                <?php echo signatur_tip(__('How wide the visitor\'s photo shows in the signature, in px.', 'signatur')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <div id="signatur_field_align_row" class="signatur-text-controls">
                <select id="signatur_field_align" aria-label="<?php esc_attr_e('Horizontal alignment', 'signatur'); ?>">
                    <option value="left"><?php esc_html_e('Left', 'signatur'); ?></option>
                    <option value="center"><?php esc_html_e('Center', 'signatur'); ?></option>
                    <option value="right"><?php esc_html_e('Right', 'signatur'); ?></option>
                </select>

                <select id="signatur_field_valign" aria-label="<?php esc_attr_e('Vertical alignment', 'signatur'); ?>">
                    <option value="top"><?php esc_html_e('Top', 'signatur'); ?></option>
                    <option value="middle"><?php esc_html_e('Middle', 'signatur'); ?></option>
                    <option value="bottom"><?php esc_html_e('Bottom', 'signatur'); ?></option>
                </select>
            </div>

            <div class="signatur-spacing-field" id="signatur_field_padding_row" hidden>
                <?php signatur_render_spacing_box(array('padding' => 'signatur_field_padding'), __('Extra space around what the visitor fills in, in px, added to its row\'s.', 'signatur')); ?>
            </div>
        </template>
    </div>

<?php
}
