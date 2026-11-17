<?php

/**
 * Help for whoever builds a signature in the admin (1.9.0, the user's
 * request): an "i" beside a field with what it does (signatur_tip()), a
 * fuller tip on the editor's icon buttons (signatur_button_tips(), shown by
 * assets/js/tips.js instead of their short title), and two buttons under
 * the editor's title opening the admin's instructions and the instructions
 * for the site (how visitors put it into Gmail / Outlook) in modals with a
 * Copy button (signatur_render_help()).
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * An "i" with a tip on hover or focus (assets/js/tips.js), as WP Banner's.
 */
function signatur_tip($text)
{
    return '<span class="signatur-tip" tabindex="0" role="img" aria-label="' . esc_attr($text) . '" data-signatur-tip="' . esc_attr($text) . '">'
        . '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span></span>';
}

/**
 * The editor's icon buttons and what each does, by a CSS selector (the
 * floating ones are moved around the grid, the modal ones are built when a
 * modal opens: tips.js matches them as the pointer reaches them).
 */
function signatur_button_tips()
{
    return array(
        '#signatur_edit_signature_button'  => __('Signature width and border: how wide the signature is in the email.', 'signatur'),
        '#signatur_undo'                   => __('Undo the last change.', 'signatur'),
        '#signatur_redo'                   => __('Redo the change you undid.', 'signatur'),
        '#signatur_column_add_end'         => __('Add a column at the right end of the table.', 'signatur'),
        '#signatur_row_add'                => __('Add a row at the bottom of the table.', 'signatur'),
        '#signatur_cell_add_element'       => __('Put something in this cell: text, an image, a social icon or a field for visitors to fill in.', 'signatur'),
        '#signatur_cell_edit_element'      => __('Change what is in this cell.', 'signatur'),
        '#signatur_cell_delete_element'    => __('Empty this cell (the cell itself stays).', 'signatur'),
        '#signatur_cell_copy_element'      => __('Copy what is in this cell, to paste it into another cell.', 'signatur'),
        '#signatur_cell_paste_element'     => __('Paste the copied content into this cell.', 'signatur'),
        '#signatur_column_split'           => __('Split this merged cell back into separate cells.', 'signatur'),
        '#signatur_column_add'             => __('Add a column next to this one.', 'signatur'),
        '#signatur_column_remove'          => __('Delete this column and everything in it.', 'signatur'),
        '#signatur_column_combine'         => __('Merge the selected cells into one, e.g. for a logo or a photo beside several lines.', 'signatur'),
        '#signatur_row_actions [data-modal-type="row"]' => __('Row settings: the space inside its cells, a line above or below it, its background.', 'signatur'),
        '.signatur-row-delete-button'      => __('Delete this row and everything in it.', 'signatur'),
        '#signatur_copy_button_edit'       => __('The look of the "Copy signature" button that visitors click on the page.', 'signatur'),
        '#signatur_add_text_element'       => __('Text: a name, a job title, a company, any line.', 'signatur'),
        '#signatur_add_image_element'      => __('Image: a logo or a photo, the same for everyone.', 'signatur'),
        '#signatur_add_social_element'     => __('Social: a clickable icon that opens a profile page.', 'signatur'),
        '#signatur_add_field_element'      => __('Field: something each visitor fills in on the page before copying (their name, phone, photo).', 'signatur'),
        '.signatur-col-resize-handle'      => __('Drag to change the width of the columns on either side.', 'signatur'),
    );
}

/**
 * The admin's instructions: from an empty signature to one on a page.
 */
function signatur_help_admin_steps()
{
    return array(
        __('Width: click the pencil above the table and set how wide the signature is (450–600 px suits most emails).', 'signatur'),
        __('Grid: add rows with the + under the table and columns with the + above it. Drag a column\'s edge to change its width. Drag across cells to select them and merge them, e.g. for a logo beside several lines.', 'signatur'),
        __('Content: click a cell, then + to put text, an image or a social icon in it. Use a Field for what each person fills in themselves (name, phone, photo).', 'signatur'),
        __('Look: the pencil beside a row sets its spacing, lines and background; the pencil beside the button below sets the "Copy signature" button.', 'signatur'),
        __('Give the signature a title and click Save.', 'signatur'),
        __('Put it on a page: the shortcode from All signatures, or the "Signatures" element in WPBakery (WoodMart\'s Theme elements). Then open the page, fill it in, copy it and paste it into your email to check it.', 'signatur'),
    );
}

/**
 * The instructions for the site: how a visitor puts the copied signature
 * into their email program, by program.
 */
function signatur_help_site_clients()
{
    return array(
        'Gmail' => array(
            __('On the page, fill in the fields and click "Copy signature".', 'signatur'),
            __('In Gmail: the gear → See all settings → General → Signature → Create new.', 'signatur'),
            __('Paste into the signature box (Ctrl+V, on a Mac Cmd+V).', 'signatur'),
            __('Scroll down and click Save Changes.', 'signatur'),
        ),
        __('Outlook (new, and on the web)', 'signatur') => array(
            __('Copy the signature on the page as above.', 'signatur'),
            __('In Outlook: Settings → Account → Signatures → New signature.', 'signatur'),
            __('Paste into the box and click Save.', 'signatur'),
        ),
        __('Outlook (classic, on Windows)', 'signatur') => array(
            __('Copy the signature on the page as above.', 'signatur'),
            __('In Outlook: File → Options → Mail → Signatures… → New.', 'signatur'),
            __('Paste into the box and click OK.', 'signatur'),
        ),
    );
}

/**
 * Two buttons at the left under the editor's title, each opening its
 * instructions in a modal with Copy (assets/js/tips.js): the admin's (how
 * to build a signature) and the site's (how visitors put it into their
 * email program: text to put on the page beside the signature).
 */
function signatur_render_help()
{
    $dialogs = array(
        'signatur_help_admin' => array(
            __('Instructions for admins', 'signatur'),
            'dashicons-admin-tools',
            __('How to build a signature', 'signatur'),
            '',
        ),
        'signatur_help_site' => array(
            __('Instructions for the site', 'signatur'),
            'dashicons-email-alt',
            __('How to put the signature into Gmail / Outlook', 'signatur'),
            __('Text for your page: copy it and put it beside the signature, so visitors know how to add it to their email.', 'signatur'),
        ),
    );
?>
    <div class="signatur-help-buttons">
        <?php foreach ($dialogs as $id => $dialog) : ?>
            <button type="button" class="button" data-signatur-help="<?php echo esc_attr($id); ?>" aria-haspopup="dialog">
                <span class="dashicons <?php echo esc_attr($dialog[1]); ?>" aria-hidden="true"></span>
                <?php echo esc_html($dialog[0]); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($dialogs as $id => $dialog) : ?>
        <dialog class="signatur-help-dialog" id="<?php echo esc_attr($id); ?>" aria-labelledby="<?php echo esc_attr($id . '_title'); ?>">
            <h2 id="<?php echo esc_attr($id . '_title'); ?>"><?php echo esc_html($dialog[2]); ?></h2>
            <?php if ($dialog[3]) : ?>
                <p class="description"><?php echo esc_html($dialog[3]); ?></p>
            <?php endif; ?>

            <?php // What Copy copies: this box only (its heading, lists and note). ?>
            <div class="signatur-help-dialog__content" data-signatur-help-content>
                <?php if ('signatur_help_admin' === $id) : ?>
                    <ol>
                        <?php foreach (signatur_help_admin_steps() as $step) : ?>
                            <li><?php echo esc_html($step); ?></li>
                        <?php endforeach; ?>
                    </ol>
                    <p><?php esc_html_e('Hover over an "i" or a button for what it does.', 'signatur'); ?></p>
                <?php else : ?>
                    <?php foreach (signatur_help_site_clients() as $client => $steps) : ?>
                        <h3><?php echo esc_html($client); ?></h3>
                        <ol>
                            <?php foreach ($steps as $step) : ?>
                                <li><?php echo esc_html($step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endforeach; ?>
                    <p><?php esc_html_e('Paste it as it is, not as plain text: the layout, colours and links come with it.', 'signatur'); ?></p>
                <?php endif; ?>
            </div>

            <div class="signatur-help-dialog__footer">
                <span class="signatur-help-dialog__copied" role="status" aria-live="polite"></span>
                <button type="button" class="button" data-signatur-help-close><?php esc_html_e('Close', 'signatur'); ?></button>
                <button type="button" class="button button-primary" data-signatur-help-copy data-copied="<?php esc_attr_e('Copied', 'signatur'); ?>"><?php esc_html_e('Copy', 'signatur'); ?></button>
            </div>
        </dialog>
    <?php endforeach; ?>
<?php
}
