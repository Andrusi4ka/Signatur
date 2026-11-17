<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'signatur_signatures';

$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

delete_option('signatur_db_version');
delete_option('signatur_roles');

// Remove the plugin's uploaded signature images (wp-content/uploads/signatur/).
$uploads = wp_upload_dir();

if (! empty($uploads['basedir'])) {
    $signatur_dir = $uploads['basedir'] . '/signatur';

    if (is_dir($signatur_dir)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($signatur_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($signatur_dir);
    }
}
