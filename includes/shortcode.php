<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register the [signatur] shortcode.
 */
function signatur_register_shortcode()
{
    add_shortcode('signatur', 'signatur_render_shortcode');
}

/**
 * Register (but do not enqueue) the front-end copy-to-clipboard assets.
 * signatur_render_shortcode() enqueues them only when a signature with a
 * copy button is actually output on the page.
 */
function signatur_register_frontend_assets()
{
    wp_register_style(
        'signatur-frontend',
        plugins_url('assets/css/frontend.css', SIGNATUR_PLUGIN_FILE),
        array(),
        SIGNATUR_VERSION
    );

    wp_register_script(
        'signatur-frontend',
        plugins_url('assets/js/frontend.js', SIGNATUR_PLUGIN_FILE),
        array(),
        SIGNATUR_VERSION,
        true
    );
}

/**
 * Render a saved signature by ID, e.g. [signatur id="12"].
 */
function signatur_render_shortcode($atts)
{
    $atts = shortcode_atts(
        array(
            'id'   => 0,
            'copy' => 'yes',
        ),
        $atts,
        'signatur'
    );

    $id = absint($atts['id']);

    if (! $id) {
        return '';
    }

    global $wpdb;

    $signature = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT settings FROM ' . signatur_get_table_name() . ' WHERE id = %d',
            $id
        )
    );

    if (! $signature) {
        return esc_html__('Signature not found.', 'signatur');
    }

    // The title is an admin-only label to tell signatures apart in the list
    // - it is never rendered on the front end.
    $settings  = json_decode(signatur_sanitize_settings($signature->settings), true);
    $show_copy = ! in_array(strtolower((string) $atts['copy']), array('no', 'false', '0', ''), true);

    // Fillable "field" elements only render as live <input>s when the copy
    // button is there to turn them into text - otherwise they show as their
    // placeholder text (see signatur_render_field_element()).
    $output = signatur_render_signature_table($settings, $show_copy);

    $dom_id         = 'signatur-signature-' . $id;
    $signature_html = '<div class="signatur-signature" id="' . esc_attr($dom_id) . '">' . $output . '</div>';

    if (! $show_copy) {
        return $signature_html;
    }

    wp_enqueue_style('signatur-frontend');
    wp_enqueue_script('signatur-frontend');
    wp_localize_script(
        'signatur-frontend',
        'signaturFrontend',
        array(
            'statusIcons' => array(
                'ok'   => plugins_url('assets/icon-ok.svg', SIGNATUR_PLUGIN_FILE),
                'feil' => plugins_url('assets/icon-feil.svg', SIGNATUR_PLUGIN_FILE),
            ),
            'i18n' => array(
                'requiredFields' => __('Please fill in all required fields before copying.', 'signatur'),
            ),
        )
    );

    $copy_button = $settings['copy_button'];

    // Width-matched to the table so "left/center/right" aligns the button
    // against the table's own edges, not the full width of whatever the
    // signature is embedded in.
    $button = '<p class="signatur-copy" style="width:' . (int) $settings['width'] . 'px; text-align:' . esc_attr($copy_button['align']) . ';">'
        . '<button type="button" class="signatur-copy-button"'
        . ' style="' . esc_attr(signatur_copy_button_style($copy_button)) . '"'
        . ' data-target="' . esc_attr($dom_id) . '">'
        . esc_html($copy_button['text'])
        . '</button></p>';

    return '<div class="signatur-signature-wrap">' . $signature_html . $button . '</div>';
}

/**
 * The font-family / font-size / colour triplet for a text element. Repeated
 * on the <td>, <span> and <a> because Outlook (Word engine) does not
 * inherit fonts reliably and falls back to Times New Roman without it.
 */
function signatur_text_base_style($element)
{
    return 'font-family:' . signatur_get_default_font()
        . '; font-size:' . (int) $element['size'] . 'pt'
        . '; color:' . $element['color'];
}

/**
 * Render a single text element as an inline-styled <span>. Kept as the one
 * place that turns a text element into markup, so the admin editor preview
 * and the front-end output stay identical. Styles are limited to what
 * Outlook renders reliably (inline, font-size in pt).
 */
function signatur_render_text_span($element)
{
    $styles = array(signatur_text_base_style($element));

    if (! empty($element['bold'])) {
        $styles[] = 'font-weight:bold';
    }

    if (! empty($element['italic'])) {
        $styles[] = 'font-style:italic';
    }

    if (! empty($element['underline'])) {
        $styles[] = 'text-decoration:underline';
    }

    $span = '<span style="' . esc_attr(implode('; ', $styles)) . '">'
        . nl2br(esc_html($element['text']))
        . '</span>';

    if (! empty($element['link'])) {
        // Linked text keeps the element's colour and is always underlined.
        // font-family so Word does not drop to Times New Roman inside the
        // <a>; color / text-decoration !important so the site theme cannot
        // override them.
        $link_style = 'font-family:' . signatur_get_default_font()
            . '; color:' . $element['color'] . ' !important'
            . '; text-decoration:underline !important;';

        return '<a href="' . esc_url($element['link']) . '" target="_blank" rel="noopener noreferrer" style="' . esc_attr($link_style) . '">'
            . $span
            . '</a>';
    }

    return $span;
}

/**
 * Render a single image element as an inline-styled <img>. Kept as the one
 * place that turns an image element into markup so the admin editor preview
 * and the front-end output stay identical. Width is fixed in px, height is
 * left automatic; `border:0` so a theme cannot add one.
 *
 * $max_width, when > 0 and smaller than the element's own width, caps the
 * rendered width - the image never spills past the cell it sits in, once
 * its own left/right padding is accounted for too. It goes into both the
 * width="" attribute and the inline width (Outlook ignores max-width, so
 * the attribute is what actually constrains it there).
 *
 * When the element has a `link`, the <img> is wrapped in an <a> that opens
 * in a new tab (mirrors the text element's link handling).
 */
function signatur_render_image_element($element, $max_width = 0)
{
    $padding = isset($element['padding']) ? $element['padding'] : signatur_sanitize_padding(array());
    $width   = (int) $element['width'];

    if ($max_width > 0) {
        $available = $max_width - (int) $padding['left'] - (int) $padding['right'];

        if ($width > $available) {
            $width = (int) $available;
        }
    }

    $width = max(1, $width);

    $img = '<img src="' . esc_url($element['url']) . '"'
        . ' width="' . $width . '"'
        . ' style="display:inline-block; width:' . $width . 'px; max-width:100%; height:auto; border:0; outline:0; text-decoration:none; ' . signatur_padding_css($padding) . ';"'
        . ' alt="">';

    if (! empty($element['link'])) {
        return '<a href="' . esc_url($element['link']) . '" target="_blank" rel="noopener noreferrer"'
            . ' style="display:inline-block; border:0; text-decoration:none;">' . $img . '</a>';
    }

    return $img;
}

/**
 * Render a single Social element as a clickable icon <img> wrapped in an
 * <a> - always wrapped, since a Social element's link is required. The icon
 * falls back to its network's built-in icon (signatur_get_social_networks())
 * when no custom icon was uploaded. Sized by `height` (not width, unlike the
 * plain image element) so it lines up with surrounding text regardless of
 * the source image's own aspect ratio.
 */
function signatur_render_social_element($element)
{
    $height = max(1, (int) $element['height']);

    $networks = signatur_get_social_networks();
    $network  = isset($networks[$element['network']]) ? $element['network'] : 'facebook';

    $icon_url = ! empty($element['icon'])
        ? $element['icon']
        : plugins_url('assets/' . $networks[$network]['icon'], SIGNATUR_PLUGIN_FILE);

    $img = '<img src="' . esc_url($icon_url) . '"'
        . ' height="' . $height . '"'
        . ' style="display:inline-block; height:' . $height . 'px; width:auto; max-width:100%; border:0; outline:0; text-decoration:none;"'
        . ' alt="">';

    $padding = isset($element['padding']) ? $element['padding'] : signatur_sanitize_padding(array());

    return '<a href="' . esc_url($element['link']) . '" target="_blank" rel="noopener noreferrer"'
        . ' style="display:inline-block; border:0; text-decoration:none; ' . signatur_padding_css($padding) . ';">' . $img . '</a>';
}

/**
 * The font / weight / style / decoration part of a text-styled element
 * (text and field elements share these keys). Base triplet plus any of
 * bold / italic / underline that are set.
 */
function signatur_text_style_string($element)
{
    $styles = array(signatur_text_base_style($element));

    if (! empty($element['bold'])) {
        $styles[] = 'font-weight:bold';
    }

    if (! empty($element['italic'])) {
        $styles[] = 'font-style:italic';
    }

    if (! empty($element['underline'])) {
        $styles[] = 'text-decoration:underline';
    }

    return implode('; ', $styles);
}

/**
 * Render a fillable "field" element. $context picks the shape:
 *  - 'front'        live <input class="signatur-field"> the visitor types
 *                   into; frontend.js bakes its value into text on copy.
 *  - 'front-static' just the placeholder text in a <span> - used when the
 *                   signature is embedded with copy="no" (nothing would
 *                   turn a dangling input into text).
 *  - 'editor'       a disabled, tinted <input> so the admin sees where the
 *                   field sits and that the cell is for front-end filling.
 */
function signatur_render_field_element($element, $context = 'front')
{
    $text_style = signatur_text_style_string($element);

    if ('front-static' === $context) {
        return '<span class="signatur-field-static" style="' . esc_attr($text_style) . ';">'
            . esc_html($element['placeholder'])
            . '</span>';
    }

    $field_type = in_array($element['field_type'], signatur_get_field_element_types(), true)
        ? $element['field_type']
        : 'text';

    $style = $text_style
        . '; display:inline-block; width:100%; box-sizing:border-box; background:transparent;'
        . ' border:0; padding:0; line-height:1.4; height:auto;'
        . ' text-align:' . $element['align'] . ';';

    $class = 'signatur-field';
    $extra = '';

    if ('editor' === $context) {
        $class .= ' signatur-field--editor';
        $extra  = ' disabled';
    }

    if (! empty($element['link'])) {
        // frontend.js turns the typed value into an <a> on copy, with the
        // scheme picked from the input's type (mailto: / tel: / https://).
        $extra .= ' data-link="1"';
    }

    return '<input type="' . esc_attr($field_type) . '"'
        . ' class="' . esc_attr($class) . '"'
        . ' placeholder="' . esc_attr($element['placeholder']) . '"'
        . ' data-required="' . (! empty($element['required']) ? '1' : '0') . '"'
        . ' style="' . esc_attr($style) . '"' . $extra . '>';
}

/**
 * Shorthand `padding:` declaration from a {top,right,bottom,left} array.
 */
function signatur_padding_css($padding)
{
    return 'padding:' . (int) $padding['top'] . 'px ' . (int) $padding['right'] . 'px '
        . (int) $padding['bottom'] . 'px ' . (int) $padding['left'] . 'px';
}

/**
 * The inline style string for the "Copy signature" button, built from its
 * stored appearance settings (signatur_get_default_copy_button() /
 * signatur_sanitize_copy_button()). Shared between the real front-end
 * button (signatur_render_shortcode()) and its static admin-editor preview,
 * so they always look identical.
 */
function signatur_copy_button_style($copy_button)
{
    return signatur_padding_css($copy_button['padding'])
        . '; font-size:' . (int) $copy_button['font_size'] . 'px'
        . '; color:' . $copy_button['text_color']
        . '; background-color:' . $copy_button['bg_color']
        . '; border-radius:' . (int) $copy_button['border_radius'] . 'px'
        . '; border:' . (int) $copy_button['border_width'] . 'px ' . $copy_button['border_style'] . ' ' . $copy_button['border_color']
        . '; text-transform:' . $copy_button['text_transform'] . ';';
}

/**
 * `border-top` / `border-bottom` declarations for a row border
 * {style,width,color,sides}, or '' when it is off.
 */
function signatur_row_border_css($border)
{
    if ('none' === $border['sides'] || (int) $border['width'] < 1) {
        return '';
    }

    $rule = (int) $border['width'] . 'px ' . $border['style'] . ' ' . $border['color'];
    $css  = '';

    if ('top' === $border['sides'] || 'both' === $border['sides']) {
        $css .= ' border-top:' . $rule . ';';
    }

    if ('bottom' === $border['sides'] || 'both' === $border['sides']) {
        $css .= ' border-bottom:' . $rule . ';';
    }

    return $css;
}

/**
 * Build the signature's table markup straight from its stored settings -
 * this is the actual signature content, so unlike the admin editor's table
 * it has no header row, row numbers, or any other editor-only chrome.
 */
function signatur_render_signature_table($settings, $interactive = true)
{
    // Optional plain grid border (toggled from the "Signature width" modal),
    // just to make the cell structure visible. When off we still emit
    // "border:0" so a theme's own "table td { border-bottom: ... }" rule
    // (e.g. Woodmart) doesn't leak a border into the signature.
    $grid_border = empty($settings['border']) ? ' border:0;' : ' border:1px solid #cccccc;';

    $html  = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; table-layout:fixed;' . $grid_border . ' width:' . (int) $settings['width'] . 'px;">';
    $html .= '<colgroup>';

    foreach ($settings['columns'] as $column_width) {
        $html .= '<col style="width:' . (int) $column_width . 'px;">';
    }

    $html .= '</colgroup><tbody>';

    // Border eats 1px off each side of a cell's content box when the grid
    // border is on (border-collapse:collapse, 1px rule).
    $border_allowance = empty($settings['border']) ? 0 : 2;

    // Column index -> number of further rows a rowspan above still covers,
    // so we can map each cell to the actual columns it starts on and total
    // their widths (needed to cap image widths to the real cell width).
    $occupied = array();

    foreach ($settings['rows'] as $row_index => $row_cells) {
        $row_padding    = isset($settings['row_padding'][$row_index])
            ? $settings['row_padding'][$row_index]
            : signatur_sanitize_padding(array());
        $row_border     = isset($settings['row_border'][$row_index])
            ? $settings['row_border'][$row_index]
            : signatur_sanitize_row_border(array());
        $row_background = isset($settings['row_background'][$row_index])
            ? $settings['row_background'][$row_index]
            : '';

        foreach ($occupied as $col => $rows_left) {
            $occupied[$col] = $rows_left - 1;

            if ($occupied[$col] <= 0) {
                unset($occupied[$col]);
            }
        }

        $col_cursor = 0;

        $html .= '<tr>';

        foreach ($row_cells as $cell_span) {
            $colspan = max(1, (int) $cell_span[0]);
            $rowspan = max(1, (int) $cell_span[1]);
            $element = isset($cell_span[2]) ? $cell_span[2] : null;
            $attrs   = '';

            while (isset($occupied[$col_cursor])) {
                $col_cursor++;
            }

            $start_col   = $col_cursor;
            $span_width  = 0;

            for ($k = 0; $k < $colspan; $k++) {
                if (isset($settings['columns'][$start_col + $k])) {
                    $span_width += (int) $settings['columns'][$start_col + $k];
                }
            }

            if ($rowspan > 1) {
                for ($k = 0; $k < $colspan; $k++) {
                    $occupied[$start_col + $k] = $rowspan;
                }
            }

            $col_cursor += $colspan;

            if ($colspan > 1) {
                $attrs .= ' colspan="' . $colspan . '"';
            }

            if ($rowspan > 1) {
                $attrs .= ' rowspan="' . $rowspan . '"';
            }

            $cell_style  = signatur_padding_css($row_padding) . '; line-height:1.4; overflow-wrap:break-word;' . $grid_border;
            $cell_style .= signatur_row_border_css($row_border);

            if ('' !== $row_background) {
                $cell_style .= ' background-color:' . $row_background . ';';
            }

            if ($element && 'text' === $element['type']) {
                $cell_style .= ' ' . signatur_text_base_style($element) . ';';
                $cell_style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';
                $html       .= '<td' . $attrs . ' style="' . esc_attr($cell_style) . '">' . signatur_render_text_span($element) . '</td>';
            } elseif ($element && 'image' === $element['type']) {
                $available   = $span_width - (int) $row_padding['left'] - (int) $row_padding['right'] - $border_allowance;
                $cell_style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';
                $html       .= '<td' . $attrs . ' style="' . esc_attr($cell_style) . '">' . signatur_render_image_element($element, $available) . '</td>';
            } elseif ($element && 'social' === $element['type']) {
                $cell_style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';
                $html       .= '<td' . $attrs . ' style="' . esc_attr($cell_style) . '">' . signatur_render_social_element($element) . '</td>';
            } elseif ($element && 'field' === $element['type']) {
                $cell_style .= ' ' . signatur_text_base_style($element) . ';';
                $cell_style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';

                // Pale-yellow fill on the whole cell marks it as fillable -
                // last, so it wins over any row background. frontend.js
                // strips it from the copied signature (bakeFieldInputs()).
                if ($interactive) {
                    $cell_style .= ' background-color:#fdf6d8;';
                }

                $html .= '<td' . $attrs . ' style="' . esc_attr($cell_style) . '">' . signatur_render_field_element($element, $interactive ? 'front' : 'front-static') . '</td>';
            } else {
                $html .= '<td' . $attrs . ' style="' . esc_attr($cell_style) . '">&nbsp;</td>';
            }
        }

        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
}
