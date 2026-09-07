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
        show_title TINYINT(1) NOT NULL DEFAULT 1,
        settings LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // The per-signature title link was removed; drop its legacy column if
    // an earlier version created it. dbDelta never drops columns.
    $has_link = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'link'",
            $table_name
        )
    );

    if ($has_link) {
        $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN link");
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
 * `row_padding` is a parallel array to `rows` giving each row its own
 * {top,right,bottom,left}, rendered onto that row's <td> cells (padding on
 * <tr> is unreliable everywhere).
 */
function signatur_get_default_settings()
{
    return array(
        'width'       => 300,
        'columns'     => array(300),
        'rows'        => array(array(array(1, 1))),
        'row_padding' => array(signatur_sanitize_padding(array())),
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
    );
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
                    $element = signatur_sanitize_text_element($cell[2]);

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

    $raw_row_padding = isset($decoded['row_padding']) && is_array($decoded['row_padding'])
        ? array_values($decoded['row_padding'])
        : array();
    $row_padding     = array();

    foreach ($rows as $index => $row) {
        $row_padding[] = signatur_sanitize_padding(
            isset($raw_row_padding[$index]) ? $raw_row_padding[$index] : array()
        );
    }

    return wp_json_encode(
        array(
            'width'       => $width,
            'columns'     => $columns,
            'rows'        => $rows,
            'row_padding' => $row_padding,
        )
    );
}
