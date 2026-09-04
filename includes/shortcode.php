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
            'SELECT title FROM ' . signatur_get_table_name() . ' WHERE id = %d',
            $id
        )
    );

    if (! $signature) {
        return esc_html__('Signature not found.', 'signatur');
    }

    return '<div class="signatur-signature">' . esc_html($signature->title) . '</div>';
}
