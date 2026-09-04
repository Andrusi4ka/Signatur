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
        'rows'    => 1,
    );
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

    $rows = isset($decoded['rows']) ? absint($decoded['rows']) : $defaults['rows'];

    if ($rows < 1) {
        $rows = 1;
    }

    return wp_json_encode(
        array(
            'width'   => $width,
            'padding' => $padding,
            'rows'    => $rows,
        )
    );
}
