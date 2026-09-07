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
            'SELECT title, show_title, settings FROM ' . signatur_get_table_name() . ' WHERE id = %d',
            $id
        )
    );

    if (! $signature) {
        return esc_html__('Signature not found.', 'signatur');
    }

    $output = '';

    if ($signature->show_title) {
        $output .= '<div class="signatur-signature-title">' . esc_html($signature->title) . '</div>';
    }

    $settings = json_decode(signatur_sanitize_settings($signature->settings), true);
    $output  .= signatur_render_signature_table($settings);

    $dom_id     = 'signatur-signature-' . $id;
    $show_copy  = ! in_array(strtolower((string) $atts['copy']), array('no', 'false', '0', ''), true);
    $signature_html = '<div class="signatur-signature" id="' . esc_attr($dom_id) . '">' . $output . '</div>';

    if (! $show_copy) {
        return $signature_html;
    }

    wp_enqueue_style('signatur-frontend');
    wp_enqueue_script('signatur-frontend');

    $button = '<p class="signatur-copy">'
        . '<button type="button" class="signatur-copy-button"'
        . ' data-target="' . esc_attr($dom_id) . '"'
        . ' data-label-copied="' . esc_attr__('Copied', 'signatur') . '">'
        . esc_html__('Copy signature', 'signatur')
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
 * Shorthand `padding:` declaration from a {top,right,bottom,left} array.
 */
function signatur_padding_css($padding)
{
    return 'padding:' . (int) $padding['top'] . 'px ' . (int) $padding['right'] . 'px '
        . (int) $padding['bottom'] . 'px ' . (int) $padding['left'] . 'px';
}

/**
 * Build the signature's table markup straight from its stored settings -
 * this is the actual signature content, so unlike the admin editor's table
 * it has no header row, row numbers, or any other editor-only chrome.
 */
function signatur_render_signature_table($settings)
{
    // TEMP: visible grid border while testing Outlook rendering. Remove the
    // "border:1px solid #cccccc;" here and the per-cell border below to go
    // back to a borderless signature.
    $html  = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; table-layout:fixed; border:1px solid #cccccc; width:' . (int) $settings['width'] . 'px;">';
    $html .= '<colgroup>';

    foreach ($settings['columns'] as $column_width) {
        $html .= '<col style="width:' . (int) $column_width . 'px;">';
    }

    $html .= '</colgroup><tbody>';

    foreach ($settings['rows'] as $row_index => $row_cells) {
        $row_padding = isset($settings['row_padding'][$row_index])
            ? $settings['row_padding'][$row_index]
            : signatur_sanitize_padding(array());

        $html .= '<tr>';

        foreach ($row_cells as $cell_span) {
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

            // TEMP: per-cell border, see the note at the top of this function.
            $cell_style = signatur_padding_css($row_padding) . '; line-height:1.4; overflow-wrap:break-word; border:1px solid #cccccc;';

            if ($element && 'text' === $element['type']) {
                $cell_style .= ' ' . signatur_text_base_style($element) . ';';
                $cell_style .= ' text-align:' . $element['align'] . '; vertical-align:' . $element['valign'] . ';';
                $html       .= '<td' . $attrs . ' style="' . esc_attr($cell_style) . '">' . signatur_render_text_span($element) . '</td>';
            } else {
                $html .= '<td' . $attrs . ' style="' . esc_attr($cell_style) . '">&nbsp;</td>';
            }
        }

        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
}
