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
        link VARCHAR(255) NOT NULL DEFAULT '',
        settings LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
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
 * cells left to right. A cell's rowspan makes it also occupy that same
 * column range in the rows below it, which therefore carry no cell of their
 * own there (standard HTML table semantics) - see
 * signatur_validate_grid_rows() for the full occupancy check.
 */
function signatur_get_default_settings()
{
    return array(
        'width'   => 300,
        'padding' => array(
            'top'    => 0,
            'right'  => 0,
            'bottom' => 0,
            'left'   => 0,
        ),
        'columns' => array(300),
        'rows'    => array(array(array(1, 1))),
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

    $raw_padding = isset($decoded['padding']) && is_array($decoded['padding']) ? $decoded['padding'] : array();
    $padding     = array();

    foreach (array('top', 'right', 'bottom', 'left') as $side) {
        $padding[$side] = isset($raw_padding[$side]) ? absint($raw_padding[$side]) : 0;
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

                $cells[] = array(
                    max(1, absint($cell[0])),
                    max(1, absint($cell[1])),
                );

                if (count($cells) >= 50) {
                    break;
                }
            }

            if (empty($cells)) {
                continue;
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

    return wp_json_encode(
        array(
            'width'   => $width,
            'padding' => $padding,
            'columns' => $columns,
            'rows'    => $rows,
        )
    );
}
