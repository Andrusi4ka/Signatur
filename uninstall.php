<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'signatur_signatures';

$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

delete_option('signatur_db_version');
