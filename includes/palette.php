<?php

/**
 * The colours every colour picker offers (assets/js/colors.js), as WP
 * Banner's and WP Swiper's: the theme's colours, then black, white and
 * greys. Any other colour can still be picked there.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A CSS colour as "#rrggbb" (lowercase): from "#rgb", "#rrggbb" or "rgb()" /
 * "rgba()" (its alpha dropped); '' for anything else.
 */
function signatur_palette_hex($value)
{
    $value = strtolower(trim((string) $value));

    if (preg_match('/^#([0-9a-f]{3})$/', $value, $m)) {
        $value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
    }

    if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
        return $value;
    }

    if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*[\d.]+\s*)?\)$/', $value, $m)) {
        return sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
    }

    return '';
}

/**
 * The theme's colours: Woodmart's own (Theme Settings), and any theme's
 * palette (theme.json or its editor colour palette).
 */
function signatur_palette_theme_colors()
{
    $colors = array();

    if ('woodmart' === get_template()) {
        $options = get_option('xts-woodmart-options');
        $names   = array(
            'primary-color'   => __('Primary color', 'signatur'),
            'secondary-color' => __('Secondary color', 'signatur'),
            'link-color'      => __('Link color', 'signatur'),
        );

        foreach ($names as $key => $name) {
            $value = is_array($options) && isset($options[$key]) ? $options[$key] : '';

            if (is_array($value)) {
                $value = isset($value['idle']) ? $value['idle'] : '';
            }

            $colors[] = array('color' => signatur_palette_hex(is_scalar($value) ? $value : ''), 'name' => $name);
        }
    }

    if (function_exists('wp_get_global_settings')) {
        $palette = wp_get_global_settings(array('color', 'palette', 'theme'));

        foreach (is_array($palette) ? $palette : array() as $entry) {
            if (is_array($entry) && isset($entry['color']) && is_scalar($entry['color'])) {
                $colors[] = array(
                    'color' => signatur_palette_hex($entry['color']),
                    'name'  => isset($entry['name']) && is_scalar($entry['name']) ? sanitize_text_field((string) $entry['name']) : '',
                );
            }
        }
    }

    return array_values(array_filter($colors, function ($entry) {
        return '' !== $entry['color'];
    }));
}

/**
 * Black, white and greys, always there.
 */
function signatur_palette_basic_colors()
{
    return array(
        array('color' => '#000000', 'name' => __('Black', 'signatur')),
        array('color' => '#444444', 'name' => __('Dark gray', 'signatur')),
        array('color' => '#888888', 'name' => __('Gray', 'signatur')),
        array('color' => '#cccccc', 'name' => __('Light gray', 'signatur')),
        array('color' => '#ffffff', 'name' => __('White', 'signatur')),
    );
}

/**
 * The palette: the theme's colours, then the basic ones; each colour once
 * (its first name), a nameless one named by itself.
 */
function signatur_palette_colors()
{
    $all  = array();
    $seen = array();

    foreach (array_merge(signatur_palette_theme_colors(), signatur_palette_basic_colors()) as $entry) {
        if (isset($seen[$entry['color']])) {
            continue;
        }

        $seen[$entry['color']] = true;
        $all[]                 = array(
            'color' => $entry['color'],
            'name'  => '' !== $entry['name'] ? $entry['name'] : $entry['color'],
        );
    }

    return $all;
}
