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
 * Render a saved signature by ID, e.g. [signatur id="12"].
 */
function signatur_render_shortcode($atts)
{
    $atts = shortcode_atts(
        array(
            'id' => 0,
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
            'SELECT title, link, show_title, settings FROM ' . signatur_get_table_name() . ' WHERE id = %d',
            $id
        )
    );

    if (! $signature) {
        return esc_html__('Signature not found.', 'signatur');
    }

    $output = '';

    if ($signature->show_title) {
        $title = esc_html($signature->title);

        if (! empty($signature->link)) {
            $title = '<a href="' . esc_url($signature->link) . '" target="_blank" rel="noopener noreferrer">' . $title . '</a>';
        }

        $output .= '<div class="signatur-signature-title">' . $title . '</div>';
    }

    $settings = json_decode(signatur_sanitize_settings($signature->settings), true);
    $output  .= signatur_render_signature_table($settings);

    return '<div class="signatur-signature">' . $output . '</div>';
}

/**
 * Build the signature's table markup straight from its stored settings -
 * this is the actual signature content, so unlike the admin editor's table
 * it has no header row, row numbers, or any other editor-only chrome.
 */
function signatur_render_signature_table($settings)
{
    $html  = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; width:' . (int) $settings['width'] . 'px; padding-top:' . (int) $settings['padding']['top'] . 'px; padding-right:' . (int) $settings['padding']['right'] . 'px; padding-bottom:' . (int) $settings['padding']['bottom'] . 'px; padding-left:' . (int) $settings['padding']['left'] . 'px;">';
    $html .= '<colgroup>';

    foreach ($settings['columns'] as $column_width) {
        $html .= '<col style="width:' . (int) $column_width . 'px;">';
    }

    $html .= '</colgroup><tbody>';

    foreach ($settings['rows'] as $row_cells) {
        $html .= '<tr>';

        foreach ($row_cells as $cell_span) {
            $colspan = $cell_span[0];
            $rowspan = $cell_span[1];
            $attrs   = '';

            if ($colspan > 1) {
                $attrs .= ' colspan="' . (int) $colspan . '"';
            }

            if ($rowspan > 1) {
                $attrs .= ' rowspan="' . (int) $rowspan . '"';
            }

            $html .= '<td' . $attrs . '>&nbsp;</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
}
