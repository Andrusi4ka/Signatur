<?php

/**
 * The editor's preview (1.10.0, the user's request, as WP Swiper's without
 * the devices' frames): the signature as on a page, drawn from the editor's
 * unsaved settings (admin-ajax signatur_preview, only on Update) into an
 * iframe at a screen's width (assets/js/preview.js). Its own small page:
 * frontend.css / frontend.js, so the fields and the copy button work as on
 * the site, and preview-dev.css / preview-dev.js for Developer mode.
 */

if (! defined('ABSPATH')) {
    exit;
}

// As large as the settings may be posted (a signature is far smaller).
define('SIGNATUR_PREVIEW_MAX', 1024 * 1024);

/**
 * The preview's screens, without frames: key => [name, width in px].
 */
function signatur_preview_devices()
{
    return array(
        'desktop' => array(__('Desktop', 'signatur'), 1280),
        'tablet'  => array(__('Tablet', 'signatur'), 820),
        'mobile'  => array(__('Phone', 'signatur'), 390),
    );
}

/**
 * admin-ajax signatur_preview: the preview's page for the posted settings.
 */
function signatur_handle_preview()
{
    check_ajax_referer('signatur_preview', 'nonce');

    if (! current_user_can(SIGNATUR_CAP_EDIT)) {
        wp_die(esc_html__('You are not allowed to perform this action.', 'signatur'), '', array('response' => 403));
    }

    $json = isset($_POST['settings']) && is_string($_POST['settings']) ? wp_unslash($_POST['settings']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, sanitized below.

    if ('' === $json || strlen($json) > SIGNATUR_PREVIEW_MAX) {
        wp_die('Invalid settings', '', array('response' => 400));
    }

    $settings = json_decode(signatur_sanitize_settings($json), true);

    nocache_headers();
    header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    echo signatur_preview_page($settings); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
    exit;
}

/**
 * The preview's page: the signature on a plain white page, as the site
 * shows it (frontend.css / frontend.js), and Developer mode's files.
 */
function signatur_preview_page($settings)
{
    $url = function ($path) {
        return esc_url(plugins_url($path, SIGNATUR_PLUGIN_FILE) . '?ver=' . rawurlencode(SIGNATUR_VERSION));
    };

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<link rel="stylesheet" href="' . $url('assets/css/frontend.css') . '">'
        . '<link rel="stylesheet" href="' . $url('assets/css/preview-dev.css') . '">'
        . '<style>html{scrollbar-width:thin}body{margin:0;padding:24px;background:#fff;color:#1d2327;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}</style>'
        . '</head><body>'
        . signatur_render_signature($settings, 'signatur-signature-preview', true)
        . '<script>var signaturFrontend = ' . wp_json_encode(signatur_frontend_data()) . ';</script>'
        . '<script src="' . $url('assets/js/frontend.js') . '"></script>'
        . '<script src="' . $url('assets/js/preview-dev.js') . '"></script>'
        . '</body></html>';
}

/**
 * The preview's column, right of the editor: the screens, Developer, Update
 * and the frame (assets/js/preview.js fills it).
 */
function signatur_render_preview_panel()
{
?>
    <aside class="signatur-preview signatur-editor-only" id="signatur_preview" aria-label="<?php esc_attr_e('Preview', 'signatur'); ?>">
        <div class="signatur-preview__bar">
            <div class="signatur-preview__devices" role="group" aria-label="<?php esc_attr_e('Screen size', 'signatur'); ?>">
                <?php foreach (signatur_preview_devices() as $key => $device) : ?>
                    <button type="button" class="button signatur-preview__device" data-device="<?php echo esc_attr($key); ?>" data-width="<?php echo (int) $device[1]; ?>" aria-pressed="<?php echo 'desktop' === $key ? 'true' : 'false'; ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr('mobile' === $key ? 'smartphone' : $key); ?>" aria-hidden="true"></span>
                        <span class="signatur-preview__device-name"><?php echo esc_html($device[0]); ?></span>
                        <span class="signatur-preview__device-width"><?php echo (int) $device[1]; ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button signatur-preview__dev" id="signatur_preview_dev" aria-pressed="false" data-signatur-tip="<?php esc_attr_e('Developer mode: each cell\'s size, its padding (green) and the button\'s margin (orange), in px.', 'signatur'); ?>">
                <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                <?php esc_html_e('Developer', 'signatur'); ?>
            </button>
            <button type="button" class="button button-primary signatur-preview__update" id="signatur_preview_update">
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <?php esc_html_e('Update', 'signatur'); ?>
            </button>
        </div>
        <p class="signatur-preview__note" id="signatur_preview_note"><?php esc_html_e('The signature as on the site, unsaved changes included. Update shows the latest changes.', 'signatur'); ?></p>
        <div class="signatur-preview__stage" id="signatur_preview_stage">
            <iframe class="signatur-preview__frame" id="signatur_preview_frame" title="<?php esc_attr_e('Preview', 'signatur'); ?>"></iframe>
        </div>
    </aside>
<?php
}
