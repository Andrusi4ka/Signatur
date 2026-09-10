<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Create the signatures database table on activation.
 */
function signatur_activate()
{
    signatur_create_table();
    update_option('signatur_db_version', SIGNATUR_DB_VERSION);
}

/**
 * Create the table on every load if it is missing or outdated, so the
 * plugin keeps working even if it was updated without a re-activation.
 */
function signatur_maybe_upgrade_database()
{
    if (get_option('signatur_db_version') === SIGNATUR_DB_VERSION) {
        return;
    }

    signatur_create_table();
    update_option('signatur_db_version', SIGNATUR_DB_VERSION);
}

/**
 * Create (or update) the signatures database table.
 */
function signatur_create_table()
{
    global $wpdb;

    $table_name      = signatur_get_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        settings LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Legacy columns from earlier versions of this table - dbDelta() never
    // drops columns, so any still present on an existing install are
    // dropped here explicitly. `link` was the per-signature title link;
    // `show_title` was whether the title showed on the front end (the
    // title is now admin-only and never rendered there at all).
    foreach (array('link', 'show_title') as $legacy_column) {
        $has_column = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                $table_name,
                $legacy_column
            )
        );

        if ($has_column) {
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN {$legacy_column}");
        }
    }
}

/**
 * Get the fully-prefixed name of the signatures table.
 */
function signatur_get_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'signatur_signatures';
}

/**
 * Default values for the visual signature builder settings.
 *
 * `columns` holds the pixel width of every grid column, shared by all rows.
 * `rows` holds one array per row, listing [colspan, rowspan] for each of its
 * cells left to right - optionally [colspan, rowspan, element] when the cell
 * holds content (see signatur_sanitize_text_element()). A cell's rowspan
 * makes it also occupy that same column range in the rows below it, which
 * therefore carry no cell of their own there (standard HTML table
 * semantics) - see signatur_validate_grid_rows() for the full occupancy
 * check.
 *
 * `row_padding` and `row_border` are parallel arrays to `rows`: each gives
 * one row its own padding {top,right,bottom,left} / border
 * {style,width,color,sides}, rendered onto that row's <td> cells (styling
 * <tr> directly is unreliable everywhere).
 */
function signatur_get_default_settings()
{
    return array(
        'width'          => 300,
        'border'         => false,
        'columns'        => array(300),
        'rows'           => array(array(array(1, 1))),
        'row_padding'    => array(signatur_sanitize_padding(array())),
        'row_border'     => array(signatur_sanitize_row_border(array())),
        'row_background' => array(signatur_sanitize_row_background('')),
        'copy_button'    => signatur_get_default_copy_button(),
    );
}

/**
 * Default appearance for the front-end "Copy signature" button.
 */
function signatur_get_default_copy_button()
{
    return array(
        'text'           => __('Copy signature', 'signatur'),
        'align'          => 'left',
        'bg_color'       => '#f6f7f7',
        'text_color'     => '#1d2327',
        'font_size'      => 14,
        'padding'        => array('top' => 8, 'right' => 16, 'bottom' => 8, 'left' => 16),
        'border_width'   => 1,
        'border_style'   => 'solid',
        'border_color'   => '#8c8f94',
        'border_radius'  => 4,
        'text_transform' => 'none',
    );
}

/**
 * Validate a raw "Copy signature" button appearance object.
 */
function signatur_sanitize_copy_button($raw)
{
    $raw      = is_array($raw) ? $raw : array();
    $defaults = signatur_get_default_copy_button();

    $text = isset($raw['text']) ? sanitize_text_field((string) $raw['text']) : '';

    if ('' === $text) {
        $text = $defaults['text'];
    }

    $align = isset($raw['align']) && in_array($raw['align'], array('left', 'center', 'right'), true)
        ? $raw['align']
        : $defaults['align'];

    $bg_color = isset($raw['bg_color']) && preg_match('/^#[0-9a-f]{6}$/i', $raw['bg_color'])
        ? strtolower($raw['bg_color'])
        : $defaults['bg_color'];

    $text_color = isset($raw['text_color']) && preg_match('/^#[0-9a-f]{6}$/i', $raw['text_color'])
        ? strtolower($raw['text_color'])
        : $defaults['text_color'];

    $font_size = isset($raw['font_size']) ? absint($raw['font_size']) : $defaults['font_size'];
    $font_size = max(8, min(72, $font_size));

    $padding = signatur_sanitize_padding(isset($raw['padding']) ? $raw['padding'] : array());

    $border_width = isset($raw['border_width']) ? absint($raw['border_width']) : $defaults['border_width'];
    $border_width = min(20, $border_width);

    $border_style = isset($raw['border_style']) && in_array($raw['border_style'], array('solid', 'dashed', 'dotted'), true)
        ? $raw['border_style']
        : $defaults['border_style'];

    $border_color = isset($raw['border_color']) && preg_match('/^#[0-9a-f]{6}$/i', $raw['border_color'])
        ? strtolower($raw['border_color'])
        : $defaults['border_color'];

    $border_radius = isset($raw['border_radius']) ? absint($raw['border_radius']) : $defaults['border_radius'];
    $border_radius = min(100, $border_radius);

    $text_transform = isset($raw['text_transform']) && in_array($raw['text_transform'], array('none', 'uppercase', 'lowercase', 'capitalize'), true)
        ? $raw['text_transform']
        : $defaults['text_transform'];

    return array(
        'text'           => $text,
        'align'          => $align,
        'bg_color'       => $bg_color,
        'text_color'     => $text_color,
        'font_size'      => $font_size,
        'padding'        => $padding,
        'border_width'   => $border_width,
        'border_style'   => $border_style,
        'border_color'   => $border_color,
        'border_radius'  => $border_radius,
        'text_transform' => $text_transform,
    );
}

/**
 * Normalize a {top,right,bottom,left} padding array - every side an
 * absolute integer, missing sides zeroed.
 */
function signatur_sanitize_padding($raw)
{
    $raw     = is_array($raw) ? $raw : array();
    $padding = array();

    foreach (array('top', 'right', 'bottom', 'left') as $side) {
        $padding[$side] = isset($raw[$side]) ? absint($raw[$side]) : 0;
    }

    return $padding;
}

/**
 * Side-by-side sum of two {top,right,bottom,left} padding arrays. Used to
 * add an element's own padding on top of its row's.
 */
function signatur_sum_padding($a, $b)
{
    $a = signatur_sanitize_padding($a);
    $b = signatur_sanitize_padding($b);

    return array(
        'top'    => $a['top'] + $b['top'],
        'right'  => $a['right'] + $b['right'],
        'bottom' => $a['bottom'] + $b['bottom'],
        'left'   => $a['left'] + $b['left'],
    );
}

/**
 * Normalize a row border {style,width,color,sides}. `sides` is which edges
 * to draw it on: none | top | bottom | both.
 */
function signatur_sanitize_row_border($raw)
{
    $raw = is_array($raw) ? $raw : array();

    $style = isset($raw['style']) && in_array($raw['style'], array('solid', 'dashed', 'dotted'), true)
        ? $raw['style']
        : 'solid';

    $width = isset($raw['width']) ? absint($raw['width']) : 1;
    $width = min(20, $width);

    $color = isset($raw['color']) && preg_match('/^#[0-9a-f]{6}$/i', $raw['color'])
        ? strtolower($raw['color'])
        : '#000000';

    $sides = isset($raw['sides']) && in_array($raw['sides'], array('none', 'top', 'bottom', 'both'), true)
        ? $raw['sides']
        : 'none';

    return array(
        'style' => $style,
        'width' => $width,
        'color' => $color,
        'sides' => $sides,
    );
}

/**
 * Normalize a row's background colour - a hex string, or '' for no
 * background (the row stays transparent / inherits whatever sits behind
 * it).
 */
function signatur_sanitize_row_background($raw)
{
    if (is_array($raw) && isset($raw['color'])) {
        $raw = $raw['color'];
    }

    if (is_string($raw) && preg_match('/^#[0-9a-f]{6}$/i', $raw)) {
        return strtolower($raw);
    }

    return '';
}

/**
 * The single font stack used for every text element. Restricted to
 * families that render reliably in Outlook (no web fonts). Must match
 * DEFAULT_FONT in assets/js/builder.js.
 */
function signatur_get_default_font()
{
    return 'Arial, Helvetica, sans-serif';
}

/**
 * Default values for a text element placed inside a grid cell.
 */
function signatur_get_default_text_element()
{
    return array(
        'type'      => 'text',
        'text'      => '',
        'size'      => 11,
        'color'     => '#000000',
        'bold'      => false,
        'italic'    => false,
        'underline' => false,
        'align'     => 'left',
        'valign'    => 'top',
        'link'      => '',
        'padding'   => signatur_sanitize_padding(array()),
    );
}

/**
 * Validate a raw text element from the submitted settings. Returns a
 * normalized element array, or null when it is not a usable text element
 * (wrong shape, or empty text after sanitizing).
 */
function signatur_sanitize_text_element($raw)
{
    if (! is_array($raw) || ! isset($raw['type']) || 'text' !== $raw['type']) {
        return null;
    }

    $defaults = signatur_get_default_text_element();

    $text = isset($raw['text']) ? sanitize_textarea_field((string) $raw['text']) : '';

    if ('' === $text) {
        return null;
    }

    $size = isset($raw['size']) ? absint($raw['size']) : $defaults['size'];
    $size = max(8, min(72, $size));

    $color = isset($raw['color']) && preg_match('/^#[0-9a-f]{6}$/i', $raw['color'])
        ? strtolower($raw['color'])
        : $defaults['color'];

    $align = isset($raw['align']) && in_array($raw['align'], array('left', 'center', 'right'), true)
        ? $raw['align']
        : $defaults['align'];

    $valign = isset($raw['valign']) && in_array($raw['valign'], array('top', 'middle', 'bottom'), true)
        ? $raw['valign']
        : $defaults['valign'];

    $link = isset($raw['link']) ? esc_url_raw((string) $raw['link']) : '';

    return array(
        'type'      => 'text',
        'text'      => $text,
        'size'      => $size,
        'color'     => $color,
        'bold'      => ! empty($raw['bold']),
        'italic'    => ! empty($raw['italic']),
        'underline' => ! empty($raw['underline']),
        'align'     => $align,
        'valign'    => $valign,
        'link'      => $link,
        'padding'   => signatur_sanitize_padding(isset($raw['padding']) ? $raw['padding'] : array()),
    );
}

/**
 * Default values for an image element placed inside a grid cell.
 */
function signatur_get_default_image_element()
{
    return array(
        'type'    => 'image',
        'url'     => '',
        'width'   => 200,
        'align'   => 'left',
        'valign'  => 'top',
        'link'    => '',
        'padding' => signatur_sanitize_padding(array()),
    );
}

/**
 * Validate a raw image element. Returns a normalized array, or null when
 * it is not usable (wrong shape, or no URL).
 */
function signatur_sanitize_image_element($raw)
{
    if (! is_array($raw) || ! isset($raw['type']) || 'image' !== $raw['type']) {
        return null;
    }

    $defaults = signatur_get_default_image_element();

    $url = isset($raw['url']) ? esc_url_raw((string) $raw['url']) : '';

    if ('' === $url) {
        return null;
    }

    $width = isset($raw['width']) ? absint($raw['width']) : $defaults['width'];
    $width = max(1, min(1000, $width));

    $align = isset($raw['align']) && in_array($raw['align'], array('left', 'center', 'right'), true)
        ? $raw['align']
        : $defaults['align'];

    $valign = isset($raw['valign']) && in_array($raw['valign'], array('top', 'middle', 'bottom'), true)
        ? $raw['valign']
        : $defaults['valign'];

    $link = isset($raw['link']) ? esc_url_raw((string) $raw['link']) : '';

    $padding = signatur_sanitize_padding(isset($raw['padding']) ? $raw['padding'] : array());

    return array(
        'type'    => 'image',
        'url'     => $url,
        'width'   => $width,
        'align'   => $align,
        'valign'  => $valign,
        'link'    => $link,
        'padding' => $padding,
    );
}

/**
 * The plugin's built-in social-network choices for the Social element -
 * each maps to its own default icon shipped in assets/. `label` is
 * translated so it can be used as a picker button's title/aria-label.
 */
function signatur_get_social_networks()
{
    return array(
        'facebook'  => array(
            'label' => __('Facebook', 'signatur'),
            'icon'  => 'icon-facebook.svg',
        ),
        'instagram' => array(
            'label' => __('Instagram', 'signatur'),
            'icon'  => 'icon-instagram.svg',
        ),
        'linkedin'  => array(
            'label' => __('LinkedIn', 'signatur'),
            'icon'  => 'icon-linkedin.svg',
        ),
        'telegram'  => array(
            'label' => __('Telegram', 'signatur'),
            'icon'  => 'icon-telegram.svg',
        ),
    );
}

/**
 * Default values for a Social element placed inside a grid cell. `network`
 * picks one of signatur_get_social_networks()'s built-in icons; `icon` is an
 * optional custom-uploaded image URL overriding it (see
 * signatur_render_social_element()). Unlike the plain image element, `link`
 * is required - a Social element with no link is not a usable element.
 */
function signatur_get_default_social_element()
{
    return array(
        'type'    => 'social',
        'network' => 'facebook',
        'icon'    => '',
        'link'    => '',
        'height'  => 32,
        'align'   => 'left',
        'valign'  => 'top',
        'padding' => signatur_sanitize_padding(array()),
    );
}

/**
 * Validate a raw Social element. Returns a normalized array, or null when
 * it is not usable (wrong shape, or no link - the element is always
 * rendered as a clickable icon so a link is mandatory). Also accepts the
 * legacy `type: 'facebook'` shape from before this element covered more
 * than one network, defaulting `network` to 'facebook'.
 */
function signatur_sanitize_social_element($raw)
{
    if (! is_array($raw) || ! isset($raw['type']) || ! in_array($raw['type'], array('social', 'facebook'), true)) {
        return null;
    }

    $defaults = signatur_get_default_social_element();

    $link = isset($raw['link']) ? esc_url_raw((string) $raw['link']) : '';

    if ('' === $link) {
        return null;
    }

    $networks = array_keys(signatur_get_social_networks());
    $network  = isset($raw['network']) && in_array($raw['network'], $networks, true)
        ? $raw['network']
        : $defaults['network'];

    $icon = isset($raw['icon']) ? esc_url_raw((string) $raw['icon']) : '';

    $height = isset($raw['height']) ? absint($raw['height']) : $defaults['height'];
    $height = max(8, min(200, $height));

    $align = isset($raw['align']) && in_array($raw['align'], array('left', 'center', 'right'), true)
        ? $raw['align']
        : $defaults['align'];

    $valign = isset($raw['valign']) && in_array($raw['valign'], array('top', 'middle', 'bottom'), true)
        ? $raw['valign']
        : $defaults['valign'];

    $padding = signatur_sanitize_padding(isset($raw['padding']) ? $raw['padding'] : array());

    return array(
        'type'    => 'social',
        'network' => $network,
        'icon'    => $icon,
        'link'    => $link,
        'height'  => $height,
        'align'   => $align,
        'valign'  => $valign,
        'padding' => $padding,
    );
}

/**
 * The input types a fillable "field" element may use.
 */
function signatur_get_field_element_types()
{
    return array('text', 'email', 'tel', 'photo');
}

/**
 * Default values for a fillable "field" element placed inside a grid cell.
 * On the front end this renders as an <input> the visitor fills in; the
 * typed value is baked into the signature as plain text when it is copied
 * (see signatur_render_field_element() / frontend.js). The text styling
 * keys mirror the text element so the typed value blends into the
 * signature.
 */
function signatur_get_default_field_element()
{
    return array(
        'type'       => 'field',
        'field_type' => 'text',
        'placeholder' => '',
        'required'   => false,
        'link'       => false,
        'size'       => 11,
        'color'      => '#000000',
        'bold'       => false,
        'italic'     => false,
        'underline'  => false,
        'align'      => 'left',
        'valign'     => 'top',
        'width'      => 80,
        'padding'    => signatur_sanitize_padding(array()),
    );
}

/**
 * Validate a raw fillable "field" element. Returns a normalized array, or
 * null when the shape is wrong. Unlike the text element, an empty
 * placeholder is fine - the element is still usable.
 */
function signatur_sanitize_field_element($raw)
{
    if (! is_array($raw) || ! isset($raw['type']) || 'field' !== $raw['type']) {
        return null;
    }

    $defaults = signatur_get_default_field_element();

    $field_type = isset($raw['field_type']) && in_array($raw['field_type'], signatur_get_field_element_types(), true)
        ? $raw['field_type']
        : $defaults['field_type'];

    $size = isset($raw['size']) ? absint($raw['size']) : $defaults['size'];
    $size = max(8, min(72, $size));

    $color = isset($raw['color']) && preg_match('/^#[0-9a-f]{6}$/i', $raw['color'])
        ? strtolower($raw['color'])
        : $defaults['color'];

    $align = isset($raw['align']) && in_array($raw['align'], array('left', 'center', 'right'), true)
        ? $raw['align']
        : $defaults['align'];

    $valign = isset($raw['valign']) && in_array($raw['valign'], array('top', 'middle', 'bottom'), true)
        ? $raw['valign']
        : $defaults['valign'];

    return array(
        'type'        => 'field',
        'field_type'  => $field_type,
        'placeholder' => isset($raw['placeholder']) ? sanitize_text_field((string) $raw['placeholder']) : '',
        'required'    => ! empty($raw['required']),
        'link'        => ! empty($raw['link']),
        'size'        => $size,
        'color'       => $color,
        'bold'        => ! empty($raw['bold']),
        'italic'      => ! empty($raw['italic']),
        'underline'   => ! empty($raw['underline']),
        'align'       => $align,
        'valign'      => $valign,
        'width'       => max(1, min(1000, isset($raw['width']) ? absint($raw['width']) : $defaults['width'])),
        'padding'     => signatur_sanitize_padding(isset($raw['padding']) ? $raw['padding'] : array()),
    );
}

/**
 * Validate any cell element - dispatches on its `type`. Returns a
 * normalized element array, or null when it is not usable.
 */
function signatur_sanitize_element($raw)
{
    if (! is_array($raw) || ! isset($raw['type'])) {
        return null;
    }

    if ('image' === $raw['type']) {
        return signatur_sanitize_image_element($raw);
    }

    if (in_array($raw['type'], array('social', 'facebook'), true)) {
        return signatur_sanitize_social_element($raw);
    }

    if ('field' === $raw['type']) {
        return signatur_sanitize_field_element($raw);
    }

    return signatur_sanitize_text_element($raw);
}

/**
 * Check that `rows` forms a valid rectangular grid: every row, once cells
 * covered by a rowspan from an earlier row are accounted for, covers
 * exactly `column_count` columns, and no rowspan reaches past the last row.
 */
function signatur_validate_grid_rows($rows, $column_count)
{
    $row_count = count($rows);
    $pending   = array();

    foreach ($rows as $row_index => $row) {
        $col = 0;

        foreach ($row as $cell) {
            while (isset($pending[$col]) && $pending[$col] > 0) {
                $pending[$col]--;

                if (0 === $pending[$col]) {
                    unset($pending[$col]);
                }

                $col++;
            }

            $colspan = $cell[0];
            $rowspan = $cell[1];

            if ($col + $colspan > $column_count) {
                return false;
            }

            if ($row_index + $rowspan > $row_count) {
                return false;
            }

            if ($rowspan > 1) {
                for ($c = $col; $c < $col + $colspan; $c++) {
                    $pending[$c] = $rowspan - 1;
                }
            }

            $col += $colspan;
        }

        while (isset($pending[$col]) && $pending[$col] > 0) {
            $pending[$col]--;

            if (0 === $pending[$col]) {
                unset($pending[$col]);
            }

            $col++;
        }

        if ($col !== $column_count) {
            return false;
        }
    }

    return true;
}

/**
 * Validate and normalize the builder settings submitted with the form,
 * returning a JSON string ready to store in the database.
 */
function signatur_sanitize_settings($raw_json)
{
    $defaults = signatur_get_default_settings();
    $decoded  = json_decode((string) $raw_json, true);

    if (! is_array($decoded)) {
        $decoded = array();
    }

    $width = isset($decoded['width']) ? absint($decoded['width']) : $defaults['width'];

    if ($width < 200) {
        $width = 200;
    }

    $border = ! empty($decoded['border']);

    $columns = array();

    if (isset($decoded['columns']) && is_array($decoded['columns'])) {
        foreach ($decoded['columns'] as $column_width) {
            $columns[] = max(20, absint($column_width));

            if (count($columns) >= 50) {
                break;
            }
        }
    }

    if (empty($columns)) {
        $columns = array($width);
    }

    $column_count = count($columns);
    $rows         = array();

    if (isset($decoded['rows']) && is_array($decoded['rows'])) {
        foreach ($decoded['rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = array();

            foreach ($row as $cell) {
                if (! is_array($cell) || count($cell) < 2) {
                    continue;
                }

                $normalized = array(
                    max(1, absint($cell[0])),
                    max(1, absint($cell[1])),
                );

                if (isset($cell[2])) {
                    $element = signatur_sanitize_element($cell[2]);

                    if (null !== $element) {
                        $normalized[] = $element;
                    }
                }

                $cells[] = $normalized;

                if (count($cells) >= 50) {
                    break;
                }
            }

            $rows[] = $cells;

            if (count($rows) >= 100) {
                break;
            }
        }
    }

    if (empty($rows) || ! signatur_validate_grid_rows($rows, $column_count)) {
        $row_count = empty($rows) ? 1 : count($rows);
        $rows      = array_fill(0, $row_count, array(array($column_count, 1)));
    }

    $raw_row_padding    = isset($decoded['row_padding']) && is_array($decoded['row_padding'])
        ? array_values($decoded['row_padding'])
        : array();
    $raw_row_border     = isset($decoded['row_border']) && is_array($decoded['row_border'])
        ? array_values($decoded['row_border'])
        : array();
    $raw_row_background = isset($decoded['row_background']) && is_array($decoded['row_background'])
        ? array_values($decoded['row_background'])
        : array();
    $row_padding        = array();
    $row_border         = array();
    $row_background     = array();

    foreach ($rows as $index => $row) {
        $row_padding[] = signatur_sanitize_padding(
            isset($raw_row_padding[$index]) ? $raw_row_padding[$index] : array()
        );
        $row_border[] = signatur_sanitize_row_border(
            isset($raw_row_border[$index]) ? $raw_row_border[$index] : array()
        );
        $row_background[] = signatur_sanitize_row_background(
            isset($raw_row_background[$index]) ? $raw_row_background[$index] : ''
        );
    }

    $copy_button = signatur_sanitize_copy_button(isset($decoded['copy_button']) ? $decoded['copy_button'] : array());

    return wp_json_encode(
        array(
            'width'          => $width,
            'border'         => $border,
            'columns'        => $columns,
            'rows'           => $rows,
            'row_padding'    => $row_padding,
            'row_border'     => $row_border,
            'row_background' => $row_background,
            'copy_button'    => $copy_button,
        )
    );
}
