<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Load translations bundled with the plugin.
 */
function signatur_load_textdomain()
{
    load_plugin_textdomain(
        'signatur',
        false,
        dirname(plugin_basename(SIGNATUR_PLUGIN_FILE)) . '/languages'
    );
}
