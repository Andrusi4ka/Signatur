(function () {
    var modalOverlay = document.getElementById('signatur_modal_overlay');
    var modalTitle = document.getElementById('signatur_modal_title');
    var modalBody = document.getElementById('signatur_modal_body');
    var modalCancel = document.getElementById('signatur_modal_cancel');
    var modalSave = document.getElementById('signatur_modal_save');
    var widthTemplate = document.getElementById('signatur_modal_template_width');
    var rowTemplate = document.getElementById('signatur_modal_template_row');
    var elementTemplate = document.getElementById('signatur_modal_template_element');
    var textTemplate = document.getElementById('signatur_modal_template_text');
    var imageTemplate = document.getElementById('signatur_modal_template_image');
    var socialTemplate = document.getElementById('signatur_modal_template_social');
    var fieldTemplate = document.getElementById('signatur_modal_template_field');
    var copyButtonTemplate = document.getElementById('signatur_modal_template_copy_button');
    var copyButtonWrap = document.getElementById('signatur_copy_button_wrap');
    var copyButtonPreview = document.getElementById('signatur_copy_button_preview');
    var copyButtonSection = document.getElementById('signatur_copy_button_section');
    var modalTargetCell = null;
    var modalTargetRow = null;
    var rowGroup = document.getElementById('signatur_row_group');
    var rowTopWrap = document.getElementById('signatur_row_top_wrap');
    var rowAddWrap = document.getElementById('signatur_row_add_wrap');
    var fieldTable = document.getElementById('signatur_field_table');
    var signatureBorder = '1' === fieldTable.getAttribute('data-border');
    var fieldTbody = document.getElementById('signatur_field_tbody');
    var rowsNumbers = document.getElementById('signatur_rows_numbers');
    var rowActionsGroup = document.getElementById('signatur_row_actions');
    var rowDeleteButton = rowActionsGroup.querySelector('.signatur-row-delete-button');
    var activeRow = null;
    var colgroup = document.getElementById('signatur_field_colgroup');
    var headerRow = document.getElementById('signatur_field_header_row');
    var resizeHandles = document.getElementById('signatur_col_resize_handles');
    var minWidth = 200;
    var minColumnWidth = 20;
    // Single font stack for every text element - must match
    // signatur_get_default_font() in includes/database.php.
    var DEFAULT_FONT = 'Arial, Helvetica, sans-serif';
    var paddingSides = ['top', 'right', 'bottom', 'left'];
    var activeModalType = null;
    var columnWidths = Array.prototype.map.call(colgroup.children, function (col) {
        return parseInt(col.style.width, 10) || minColumnWidth;
    });

    function clampWidth(value) {
        var width = parseInt(value, 10);

        if (isNaN(width) || width < minWidth) {
            return minWidth;
        }

        return width;
    }

    function clampPadding(value) {
        var padding = parseInt(value, 10);

        if (isNaN(padding) || padding < 0) {
            return 0;
        }

        return padding;
    }

    /**
     * Populate a group of 4 padding number inputs (ids `<prefix>_top`,
     * `<prefix>_right`, `<prefix>_bottom`, `<prefix>_left`) from a stored
     * {top,right,bottom,left} object, and wire each to clamp its own value
     * on blur. Shared by every modal with its own padding group (row,
     * image, social, copy button).
     */
    function populatePaddingInputs(prefix, padding) {
        var source = padding || ZERO_PADDING;

        paddingSides.forEach(function (side) {
            var input = document.getElementById(prefix + '_' + side);
            input.value = source[side] || '';

            input.addEventListener('blur', function () {
                if ('' !== input.value) {
                    input.value = clampPadding(input.value);
                }
            });
        });
    }

    /**
     * The inverse of populatePaddingInputs() - read a group of 4 padding
     * number inputs back into a {top,right,bottom,left} object, clamping
     * each value.
     */
    function readPaddingInputs(prefix) {
        var padding = {};

        paddingSides.forEach(function (side) {
            padding[side] = clampPadding(document.getElementById(prefix + '_' + side).value);
        });

        return padding;
    }

    /**
     * The inline style string for a text element - mirror of the PHP
     * signatur_render_text_span() so the editor preview and the saved
     * front-end output match exactly. Limited to Outlook-safe properties.
     */
    function textElementStyle(el) {
        var styles = [
            'font-family:' + DEFAULT_FONT,
            'font-size:' + el.size + 'pt',
            'color:' + el.color
        ];

        if (el.bold) {
            styles.push('font-weight:bold');
        }

        if (el.italic) {
            styles.push('font-style:italic');
        }

        if (el.underline) {
            styles.push('text-decoration:underline');
        }

        return styles.join('; ');
    }

    function renderTextSpan(el) {
        var span = document.createElement('span');
        var parts = String(el.text).split('\n');

        span.setAttribute('style', textElementStyle(el));

        parts.forEach(function (part, i) {
            span.appendChild(document.createTextNode(part));

            if (i < parts.length - 1) {
                span.appendChild(document.createElement('br'));
            }
        });

        return span;
    }

    /**
     * Render an image element as an inline-styled <img> - mirror of the PHP
     * signatur_render_image_element() so the editor preview matches the
     * saved front-end output. Width is fixed in px, height stays automatic.
     */
    function renderImageEl(el) {
        var img = document.createElement('img');
        var width = parseInt(el.width, 10) || 1;
        var padding = el.padding || ZERO_PADDING;
        var paddingCss = 'padding:' + (parseInt(padding.top, 10) || 0) + 'px '
            + (parseInt(padding.right, 10) || 0) + 'px '
            + (parseInt(padding.bottom, 10) || 0) + 'px '
            + (parseInt(padding.left, 10) || 0) + 'px;';

        img.setAttribute('src', el.url);
        img.setAttribute('width', width);
        img.setAttribute('alt', '');
        // max-width:100% keeps the preview inside its cell when the set
        // width is wider than the column; the front-end additionally caps
        // the width="" attribute for Outlook (see signatur_render_image_element()).
        img.setAttribute('style', 'display:inline-block; width:' + width + 'px; max-width:100%; height:auto; border:0; outline:0; text-decoration:none; ' + paddingCss);

        if (el.link) {
            var anchor = document.createElement('a');

            anchor.setAttribute('href', el.link);
            anchor.setAttribute('target', '_blank');
            anchor.setAttribute('rel', 'noopener noreferrer');
            anchor.setAttribute('style', 'display:inline-block; border:0; text-decoration:none;');
            anchor.appendChild(img);

            return anchor;
        }

        return img;
    }

    /**
     * Render a Social element as a clickable icon <img> wrapped in an <a> -
     * mirror of the PHP signatur_render_social_element(). Sized by
     * `height`; falls back to its network's built-in icon
     * (signaturBuilder.socialIcons) when no custom icon was uploaded.
     */
    function renderSocialEl(el) {
        var img = document.createElement('img');
        var height = parseInt(el.height, 10) || 1;
        var networkIcon = (signaturBuilder.socialIcons && signaturBuilder.socialIcons[el.network])
            || signaturBuilder.socialIcons.facebook;

        img.setAttribute('src', el.icon || networkIcon);
        img.setAttribute('height', height);
        img.setAttribute('alt', '');
        img.setAttribute('style', 'display:inline-block; height:' + height + 'px; width:auto; max-width:100%; border:0; outline:0; text-decoration:none;');

        var anchor = document.createElement('a');
        var padding = el.padding || ZERO_PADDING;
        var paddingCss = 'padding:' + (parseInt(padding.top, 10) || 0) + 'px '
            + (parseInt(padding.right, 10) || 0) + 'px '
            + (parseInt(padding.bottom, 10) || 0) + 'px '
            + (parseInt(padding.left, 10) || 0) + 'px;';

        anchor.setAttribute('href', el.link);
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
        anchor.setAttribute('style', 'display:inline-block; border:0; text-decoration:none; ' + paddingCss);
        anchor.appendChild(img);

        return anchor;
    }

    /**
     * Render a fillable "field" element for the editor - a disabled, tinted
     * <input> so the admin sees where the field sits. Mirror of the PHP
     * signatur_render_field_element($element, 'editor'); the front-end
     * version (live input / baked text) is built in PHP + frontend.js.
     */
    function renderFieldEl(el) {
        var input = document.createElement('input');
        var types = ['text', 'email', 'tel'];
        var style = textElementStyle(el)
            + '; display:inline-block; width:100%; box-sizing:border-box; background:transparent;'
            + ' border:0; padding:0; line-height:1.4; height:auto;'
            + ' text-align:' + el.align + ';';

        input.setAttribute('type', -1 !== types.indexOf(el.field_type) ? el.field_type : 'text');
        input.className = 'signatur-field signatur-field--editor';
        input.setAttribute('placeholder', el.placeholder || '');
        input.setAttribute('data-required', el.required ? '1' : '0');

        if (el.link) {
            input.setAttribute('data-link', '1');
        }

        input.setAttribute('style', style);
        input.disabled = true;

        return input;
    }

    /**
     * Put an element into a cell (or clear it when el is null / empty),
     * keeping the cell's data-element attribute, alignment and visible
     * content in sync. This is the single place cell content is written.
     */
    function applyElementToCell(cell, el) {
        cell.innerHTML = '';

        cell.classList.remove('signatur-field-cell');

        if (el && 'text' === el.type && '' !== String(el.text).trim()) {
            cell.dataset.element = JSON.stringify(el);
            cell.style.textAlign = el.align;
            cell.style.verticalAlign = el.valign || 'top';
            // Repeat font/size/colour on the cell too - Word does not
            // inherit fonts reliably. Mirrors PHP signatur_text_base_style().
            cell.style.fontFamily = DEFAULT_FONT;
            cell.style.fontSize = el.size + 'pt';
            cell.style.color = el.color;

            var span = renderTextSpan(el);

            if (el.link) {
                var anchor = document.createElement('a');

                anchor.setAttribute('href', el.link);
                anchor.setAttribute('target', '_blank');
                anchor.setAttribute('rel', 'noopener noreferrer');
                // Linked text keeps the element's font and colour and is
                // always underlined. !important on colour / decoration so
                // the theme cannot override them. Mirrors PHP
                // signatur_render_text_span().
                anchor.style.fontFamily = DEFAULT_FONT;
                anchor.style.setProperty('color', el.color, 'important');
                anchor.style.setProperty('text-decoration', 'underline', 'important');
                anchor.appendChild(span);
                cell.appendChild(anchor);
            } else {
                cell.appendChild(span);
            }

            return;
        }

        if (el && 'image' === el.type && '' !== String(el.url || '').trim()) {
            cell.dataset.element = JSON.stringify(el);
            cell.style.textAlign = el.align;
            cell.style.verticalAlign = el.valign || 'top';
            cell.style.fontFamily = '';
            cell.style.fontSize = '';
            cell.style.color = '';
            cell.appendChild(renderImageEl(el));

            return;
        }

        if (el && 'social' === el.type && '' !== String(el.link || '').trim()) {
            cell.dataset.element = JSON.stringify(el);
            cell.style.textAlign = el.align;
            cell.style.verticalAlign = el.valign || 'top';
            cell.style.fontFamily = '';
            cell.style.fontSize = '';
            cell.style.color = '';
            cell.appendChild(renderSocialEl(el));

            return;
        }

        if (el && 'field' === el.type) {
            cell.dataset.element = JSON.stringify(el);
            cell.classList.add('signatur-field-cell');
            cell.style.textAlign = el.align;
            cell.style.verticalAlign = el.valign || 'top';
            cell.style.fontFamily = DEFAULT_FONT;
            cell.style.fontSize = el.size + 'pt';
            cell.style.color = el.color;
            cell.appendChild(renderFieldEl(el));

            return;
        }

        delete cell.dataset.element;
        cell.style.textAlign = '';
        cell.style.verticalAlign = '';
        cell.style.fontFamily = '';
        cell.style.fontSize = '';
        cell.style.color = '';
        cell.innerHTML = '&nbsp;';
    }

    var ZERO_PADDING = { top: 0, right: 0, bottom: 0, left: 0 };
    var DEFAULT_ROW_BORDER = { style: 'solid', width: 1, color: '#000000', sides: 'none' };
    var DEFAULT_ROW_BACKGROUND = '';

    function readRowPadding(tr) {
        try {
            var p = JSON.parse(tr.dataset.padding || '');

            return {
                top: parseInt(p.top, 10) || 0,
                right: parseInt(p.right, 10) || 0,
                bottom: parseInt(p.bottom, 10) || 0,
                left: parseInt(p.left, 10) || 0
            };
        } catch (err) {
            return { top: 0, right: 0, bottom: 0, left: 0 };
        }
    }

    function readRowBorder(tr) {
        try {
            var b = JSON.parse(tr.dataset.border || '');

            return {
                style: b.style || 'solid',
                width: parseInt(b.width, 10) || 0,
                color: b.color || '#000000',
                sides: b.sides || 'none'
            };
        } catch (err) {
            return { style: 'solid', width: 0, color: '#000000', sides: 'none' };
        }
    }

    function readRowBackground(tr) {
        var color = tr.dataset.background || '';

        return /^#[0-9a-f]{6}$/i.test(color) ? color : '';
    }

    /**
     * Mirror a <tr>'s stored padding + border + background onto every <td>
     * it owns - styling <tr> directly is unreliable, so the row's settings
     * live on its cells (matches signatur_render_signature_table()).
     */
    function applyRowStyles(tr) {
        var p = readRowPadding(tr);
        var b = readRowBorder(tr);
        var background = readRowBackground(tr);
        var padding = p.top + 'px ' + p.right + 'px ' + p.bottom + 'px ' + p.left + 'px';
        var hasBorder = 'none' !== b.sides && b.width > 0;
        var rule = hasBorder ? (b.width + 'px ' + b.style + ' ' + b.color) : '';

        Array.prototype.forEach.call(tr.children, function (td) {
            td.style.padding = padding;
            td.style.borderTop = (hasBorder && ('top' === b.sides || 'both' === b.sides)) ? rule : '';
            td.style.borderBottom = (hasBorder && ('bottom' === b.sides || 'both' === b.sides)) ? rule : '';
            td.style.backgroundColor = background;
        });
    }

    function applyAllRowStyles() {
        Array.prototype.forEach.call(fieldTbody.children, applyRowStyles);
    }

    // Mirrors signatur_get_default_copy_button() in includes/database.php.
    var DEFAULT_COPY_BUTTON = {
        text: 'Copy signature',
        align: 'left',
        bg_color: '#f6f7f7',
        text_color: '#1d2327',
        font_size: 14,
        padding: { top: 8, right: 16, bottom: 8, left: 16 },
        border_width: 1,
        border_style: 'solid',
        border_color: '#8c8f94',
        border_radius: 4,
        text_transform: 'none'
    };

    function readCopyButtonSettings() {
        try {
            var stored = JSON.parse(copyButtonWrap.dataset.copyButton || '');

            return {
                text: stored.text || DEFAULT_COPY_BUTTON.text,
                align: stored.align || DEFAULT_COPY_BUTTON.align,
                bg_color: stored.bg_color || DEFAULT_COPY_BUTTON.bg_color,
                text_color: stored.text_color || DEFAULT_COPY_BUTTON.text_color,
                font_size: parseInt(stored.font_size, 10) || DEFAULT_COPY_BUTTON.font_size,
                padding: stored.padding || DEFAULT_COPY_BUTTON.padding,
                border_width: isNaN(parseInt(stored.border_width, 10)) ? DEFAULT_COPY_BUTTON.border_width : parseInt(stored.border_width, 10),
                border_style: stored.border_style || DEFAULT_COPY_BUTTON.border_style,
                border_color: stored.border_color || DEFAULT_COPY_BUTTON.border_color,
                border_radius: isNaN(parseInt(stored.border_radius, 10)) ? DEFAULT_COPY_BUTTON.border_radius : parseInt(stored.border_radius, 10),
                text_transform: stored.text_transform || DEFAULT_COPY_BUTTON.text_transform
            };
        } catch (err) {
            return JSON.parse(JSON.stringify(DEFAULT_COPY_BUTTON));
        }
    }

    /**
     * Apply the copy button's stored appearance to its admin-preview element
     * - mirrors PHP signatur_copy_button_style() so the preview matches the
     * real front-end button exactly. `align` is deliberately not applied
     * here - the preview always sits centered in the editor, only the real
     * front-end button (signatur_render_shortcode()) honours it.
     */
    function applyCopyButtonPreview(settings) {
        var p = settings.padding || ZERO_PADDING;

        copyButtonPreview.textContent = settings.text;
        copyButtonPreview.style.padding = (p.top || 0) + 'px ' + (p.right || 0) + 'px ' + (p.bottom || 0) + 'px ' + (p.left || 0) + 'px';
        copyButtonPreview.style.fontSize = settings.font_size + 'px';
        copyButtonPreview.style.color = settings.text_color;
        copyButtonPreview.style.backgroundColor = settings.bg_color;
        copyButtonPreview.style.borderRadius = settings.border_radius + 'px';
        copyButtonPreview.style.border = settings.border_width + 'px ' + settings.border_style + ' ' + settings.border_color;
        copyButtonPreview.style.textTransform = settings.text_transform;
    }

    var copyButtonSettings = readCopyButtonSettings();

    function openModal(title, type) {
        // Dismiss any floating buttons so they don't sit above the overlay.
        hideRowActions();
        hideColumnButtons();
        hideColumnCombineButton();

        modalTitle.textContent = title;
        modalBody.innerHTML = '';
        activeModalType = type || null;

        if ('element' === type) {
            modalBody.appendChild(elementTemplate.content.cloneNode(true));
        }

        if ('row' === type) {
            modalBody.appendChild(rowTemplate.content.cloneNode(true));

            populatePaddingInputs('signatur_row_padding', modalTargetRow ? readRowPadding(modalTargetRow) : ZERO_PADDING);

            var rowBorder = modalTargetRow ? readRowBorder(modalTargetRow) : DEFAULT_ROW_BORDER;

            document.getElementById('signatur_row_border_style').value = rowBorder.style;
            document.getElementById('signatur_row_border_width').value = rowBorder.width || '';
            document.getElementById('signatur_row_border_color').value = rowBorder.color;
            document.getElementById('signatur_row_border_sides').value = rowBorder.sides;

            var rowBackground = modalTargetRow ? readRowBackground(modalTargetRow) : DEFAULT_ROW_BACKGROUND;
            var backgroundInput = document.getElementById('signatur_row_background_color');

            backgroundInput.value = rowBackground || '#ffffff';
            backgroundInput.dataset.active = rowBackground ? '1' : '0';

            document.getElementById('signatur_row_background_clear').addEventListener('click', function () {
                backgroundInput.dataset.active = '0';
            });

            backgroundInput.addEventListener('input', function () {
                backgroundInput.dataset.active = '1';
            });
        }

        if ('text' === type) {
            modalBody.appendChild(textTemplate.content.cloneNode(true));

            var stored = null;

            if (modalTargetCell && modalTargetCell.dataset.element) {
                try {
                    stored = JSON.parse(modalTargetCell.dataset.element);
                } catch (err) {
                    stored = null;
                }
            }

            var textInput = document.getElementById('signatur_text_input');
            var sizeInput = document.getElementById('signatur_text_size');
            var colorInput = document.getElementById('signatur_text_color');
            var alignSelect = document.getElementById('signatur_text_align');
            var valignSelect = document.getElementById('signatur_text_valign');
            var linkInput = document.getElementById('signatur_text_link');
            var toggles = ['bold', 'italic', 'underline'].map(function (name) {
                return document.getElementById('signatur_text_' + name);
            });

            textInput.value = stored ? stored.text : '';
            sizeInput.value = stored ? stored.size : 11;
            colorInput.value = stored ? stored.color : '#000000';
            alignSelect.value = stored ? stored.align : 'left';
            valignSelect.value = stored && stored.valign ? stored.valign : 'top';
            linkInput.value = stored && stored.link ? stored.link : '';

            toggles[0].classList.toggle('is-active', !!(stored && stored.bold));
            toggles[1].classList.toggle('is-active', !!(stored && stored.italic));
            toggles[2].classList.toggle('is-active', !!(stored && stored.underline));

            /**
             * Reflect the current controls straight onto the textarea, so
             * what you type shows the way it will actually render.
             */
            function updateTextareaStyle() {
                // Linked text is always underlined, so the preview shows an
                // underline whenever the U toggle is on OR a link is set.
                var underlined = toggles[2].classList.contains('is-active') || '' !== linkInput.value.trim();

                textInput.style.fontFamily = DEFAULT_FONT;
                textInput.style.fontSize = (parseInt(sizeInput.value, 10) || 11) + 'pt';
                textInput.style.color = colorInput.value;
                textInput.style.fontWeight = toggles[0].classList.contains('is-active') ? 'bold' : 'normal';
                textInput.style.fontStyle = toggles[1].classList.contains('is-active') ? 'italic' : 'normal';
                textInput.style.textDecoration = underlined ? 'underline' : 'none';
                textInput.style.textAlign = alignSelect.value;
            }

            updateTextareaStyle();

            [textInput, sizeInput, colorInput, alignSelect, linkInput].forEach(function (control) {
                control.addEventListener('input', updateTextareaStyle);
                control.addEventListener('change', updateTextareaStyle);
            });

            toggles.forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    toggle.classList.toggle('is-active');
                    updateTextareaStyle();
                });
            });

            sizeInput.addEventListener('blur', function () {
                var value = parseInt(sizeInput.value, 10);

                if (isNaN(value)) {
                    value = 11;
                }

                sizeInput.value = Math.max(8, Math.min(72, value));
                updateTextareaStyle();
            });
        }

        if ('image' === type) {
            modalBody.appendChild(imageTemplate.content.cloneNode(true));

            var imageStored = null;

            if (modalTargetCell && modalTargetCell.dataset.element) {
                try {
                    var parsedImage = JSON.parse(modalTargetCell.dataset.element);

                    if (parsedImage && 'image' === parsedImage.type) {
                        imageStored = parsedImage;
                    }
                } catch (err) {
                    imageStored = null;
                }
            }

            var imageUrlInput = document.getElementById('signatur_image_url');
            var imageLinkInput = document.getElementById('signatur_image_link');
            var imageWidthInput = document.getElementById('signatur_image_width');
            var imageAlignSelect = document.getElementById('signatur_image_align');
            var imageValignSelect = document.getElementById('signatur_image_valign');
            var imagePreview = document.getElementById('signatur_image_preview');
            var imageUploadButton = document.getElementById('signatur_image_upload');
            var imageFileInput = document.getElementById('signatur_image_file');

            imageUrlInput.value = imageStored ? imageStored.url : '';
            imageLinkInput.value = imageStored && imageStored.link ? imageStored.link : '';
            imageWidthInput.value = imageStored ? imageStored.width : 200;
            imageAlignSelect.value = imageStored ? imageStored.align : 'left';
            imageValignSelect.value = imageStored && imageStored.valign ? imageStored.valign : 'top';

            populatePaddingInputs('signatur_image_padding', imageStored && imageStored.padding ? imageStored.padding : ZERO_PADDING);

            function updateImagePreview() {
                var url = imageUrlInput.value.trim();

                if ('' === url) {
                    imagePreview.style.display = 'none';
                    imagePreview.removeAttribute('src');

                    return;
                }

                imagePreview.src = url;
                imagePreview.style.display = 'block';
            }

            updateImagePreview();

            imageWidthInput.addEventListener('blur', function () {
                var value = parseInt(imageWidthInput.value, 10);

                if (isNaN(value)) {
                    value = 200;
                }

                imageWidthInput.value = Math.max(1, Math.min(1000, value));
            });

            imageUploadButton.addEventListener('click', function () {
                imageFileInput.click();
            });

            imageFileInput.addEventListener('change', function () {
                if (! imageFileInput.files || ! imageFileInput.files[0]) {
                    return;
                }

                var data = new FormData();
                data.append('action', 'signatur_upload_image');
                data.append('nonce', signaturBuilder.uploadNonce);
                data.append('file', imageFileInput.files[0]);

                imageUploadButton.disabled = true;

                fetch(signaturBuilder.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                }).then(function (response) {
                    return response.json();
                }).then(function (json) {
                    if (json && json.success && json.data && json.data.url) {
                        imageUrlInput.value = json.data.url;
                        updateImagePreview();
                    } else {
                        window.alert(signaturBuilder.i18n.uploadFailed);
                    }
                }).catch(function () {
                    window.alert(signaturBuilder.i18n.uploadFailed);
                }).then(function () {
                    imageUploadButton.disabled = false;
                    imageFileInput.value = '';
                });
            });
        }

        if ('social' === type) {
            modalBody.appendChild(socialTemplate.content.cloneNode(true));

            var socialStored = null;

            if (modalTargetCell && modalTargetCell.dataset.element) {
                try {
                    var parsedSocial = JSON.parse(modalTargetCell.dataset.element);

                    if (parsedSocial && 'social' === parsedSocial.type) {
                        socialStored = parsedSocial;
                    }
                } catch (err) {
                    socialStored = null;
                }
            }

            var socialNetworkInput = document.getElementById('signatur_social_network');
            var socialNetworkButtons = Array.prototype.slice.call(
                modalBody.querySelectorAll('.signatur-social-network-button')
            );
            var socialIconUrlInput = document.getElementById('signatur_social_icon_url');
            var socialLinkInput = document.getElementById('signatur_social_link');
            var socialHeightInput = document.getElementById('signatur_social_height');
            var socialAlignSelect = document.getElementById('signatur_social_align');
            var socialValignSelect = document.getElementById('signatur_social_valign');
            var socialPreview = document.getElementById('signatur_social_preview');
            var socialUploadButton = document.getElementById('signatur_social_upload');
            var socialFileInput = document.getElementById('signatur_social_file');

            var initialNetwork = socialStored && socialStored.network ? socialStored.network : 'facebook';

            /**
             * Highlight the currently chosen network's button among the
             * picker row - mirrors the Bold/Italic/Underline is-active
             * pattern used for the text element.
             */
            function setActiveNetworkButton(network) {
                socialNetworkButtons.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-network') === network);
                });
            }

            socialNetworkInput.value = initialNetwork;
            setActiveNetworkButton(initialNetwork);

            socialNetworkButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    socialNetworkInput.value = btn.getAttribute('data-network');
                    setActiveNetworkButton(btn.getAttribute('data-network'));
                    updateSocialPreview();
                });
            });

            socialIconUrlInput.value = socialStored && socialStored.icon ? socialStored.icon : '';
            socialLinkInput.value = socialStored ? socialStored.link : '';
            socialHeightInput.value = socialStored ? socialStored.height : 32;
            socialAlignSelect.value = socialStored ? socialStored.align : 'left';
            socialValignSelect.value = socialStored && socialStored.valign ? socialStored.valign : 'top';

            populatePaddingInputs('signatur_social_padding', socialStored && socialStored.padding ? socialStored.padding : ZERO_PADDING);

            /**
             * The icon that will actually be used - the uploaded custom
             * icon when one was set, otherwise the selected network's own
             * built-in icon. Kept live as the network picker buttons and
             * the upload field change.
             */
            function updateSocialPreview() {
                var url = socialIconUrlInput.value.trim();
                var networkIcon = (signaturBuilder.socialIcons && signaturBuilder.socialIcons[socialNetworkInput.value])
                    || signaturBuilder.socialIcons.facebook;

                socialPreview.src = url || networkIcon;
                socialPreview.style.display = 'block';
            }

            updateSocialPreview();

            socialHeightInput.addEventListener('blur', function () {
                var value = parseInt(socialHeightInput.value, 10);

                if (isNaN(value)) {
                    value = 32;
                }

                socialHeightInput.value = Math.max(8, Math.min(200, value));
            });

            socialUploadButton.addEventListener('click', function () {
                socialFileInput.click();
            });

            socialFileInput.addEventListener('change', function () {
                if (! socialFileInput.files || ! socialFileInput.files[0]) {
                    return;
                }

                var data = new FormData();
                data.append('action', 'signatur_upload_image');
                data.append('nonce', signaturBuilder.uploadNonce);
                data.append('file', socialFileInput.files[0]);

                socialUploadButton.disabled = true;

                fetch(signaturBuilder.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                }).then(function (response) {
                    return response.json();
                }).then(function (json) {
                    if (json && json.success && json.data && json.data.url) {
                        socialIconUrlInput.value = json.data.url;
                        updateSocialPreview();
                    } else {
                        window.alert(signaturBuilder.i18n.uploadFailed);
                    }
                }).catch(function () {
                    window.alert(signaturBuilder.i18n.uploadFailed);
                }).then(function () {
                    socialUploadButton.disabled = false;
                    socialFileInput.value = '';
                });
            });
        }

        if ('field' === type) {
            modalBody.appendChild(fieldTemplate.content.cloneNode(true));

            var fieldStored = null;

            if (modalTargetCell && modalTargetCell.dataset.element) {
                try {
                    var parsedField = JSON.parse(modalTargetCell.dataset.element);

                    if (parsedField && 'field' === parsedField.type) {
                        fieldStored = parsedField;
                    }
                } catch (err) {
                    fieldStored = null;
                }
            }

            var fieldTypeSelect = document.getElementById('signatur_field_type');
            var fieldTypeButtons = Array.prototype.slice.call(
                modalBody.querySelectorAll('.signatur-field-type-button')
            );
            var fieldPlaceholderInput = document.getElementById('signatur_field_placeholder');
            var fieldRequiredInput = document.getElementById('signatur_field_required');
            var fieldLinkInput = document.getElementById('signatur_field_link');
            var fieldSizeInput = document.getElementById('signatur_field_size');
            var fieldColorInput = document.getElementById('signatur_field_color');
            var fieldAlignSelect = document.getElementById('signatur_field_align');
            var fieldValignSelect = document.getElementById('signatur_field_valign');
            var fieldToggles = ['bold', 'italic', 'underline'].map(function (name) {
                return document.getElementById('signatur_field_' + name);
            });

            fieldTypeSelect.value = fieldStored ? fieldStored.field_type : 'text';
            fieldPlaceholderInput.value = fieldStored ? fieldStored.placeholder : '';
            fieldRequiredInput.checked = !!(fieldStored && fieldStored.required);
            fieldLinkInput.checked = !!(fieldStored && fieldStored.link);
            fieldSizeInput.value = fieldStored ? fieldStored.size : 11;
            fieldColorInput.value = fieldStored ? fieldStored.color : '#000000';
            fieldAlignSelect.value = fieldStored ? fieldStored.align : 'left';
            fieldValignSelect.value = fieldStored && fieldStored.valign ? fieldStored.valign : 'top';

            fieldToggles[0].classList.toggle('is-active', !!(fieldStored && fieldStored.bold));
            fieldToggles[1].classList.toggle('is-active', !!(fieldStored && fieldStored.italic));
            fieldToggles[2].classList.toggle('is-active', !!(fieldStored && fieldStored.underline));

            // Highlight the type button that matches the (hidden-ish) select.
            function syncFieldTypeButtons() {
                fieldTypeButtons.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-type') === fieldTypeSelect.value);
                });
            }

            syncFieldTypeButtons();

            fieldTypeButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    fieldTypeSelect.value = btn.getAttribute('data-type');
                    syncFieldTypeButtons();
                });
            });

            fieldTypeSelect.addEventListener('change', syncFieldTypeButtons);

            fieldToggles.forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    toggle.classList.toggle('is-active');
                });
            });

            fieldSizeInput.addEventListener('blur', function () {
                var value = parseInt(fieldSizeInput.value, 10);

                if (isNaN(value)) {
                    value = 11;
                }

                fieldSizeInput.value = Math.max(8, Math.min(72, value));
            });
        }

        if ('width' === type) {
            modalBody.appendChild(widthTemplate.content.cloneNode(true));
            var widthInput = document.getElementById('signatur_width_input');
            widthInput.value = parseInt(fieldTable.style.width, 10) || 300;

            widthInput.addEventListener('blur', function () {
                widthInput.value = clampWidth(widthInput.value);
            });

            document.getElementById('signatur_border_toggle').checked = signatureBorder;
        }

        if ('copy_button' === type) {
            modalBody.appendChild(copyButtonTemplate.content.cloneNode(true));

            var cb = readCopyButtonSettings();

            document.getElementById('signatur_copy_button_text').value = cb.text;
            document.getElementById('signatur_copy_button_bg_color').value = cb.bg_color;
            document.getElementById('signatur_copy_button_text_color').value = cb.text_color;
            document.getElementById('signatur_copy_button_align').value = cb.align;
            document.getElementById('signatur_copy_button_font_size').value = cb.font_size;
            document.getElementById('signatur_copy_button_border_radius').value = cb.border_radius;
            document.getElementById('signatur_copy_button_border_style').value = cb.border_style;
            document.getElementById('signatur_copy_button_border_width').value = cb.border_width;
            document.getElementById('signatur_copy_button_border_color').value = cb.border_color;
            document.getElementById('signatur_copy_button_text_transform').value = cb.text_transform;

            populatePaddingInputs('signatur_copy_button_padding', cb.padding);
        }

        modalOverlay.style.display = 'flex';
    }

    function closeModal() {
        modalOverlay.style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-modal-title]');

        if (button) {
            if ('signatur_cell_add_element' === button.id) {
                modalTargetCell = activeColumnCell;
            }

            if ('row' === button.getAttribute('data-modal-type')) {
                modalTargetRow = activeRow;
            }

            openModal(button.getAttribute('data-modal-title'), button.getAttribute('data-modal-type'));
        }
    });

    var rowAddButton = document.getElementById('signatur_row_add');
    var rowAddIndicator = document.getElementById('signatur_row_add_indicator');

    rowAddButton.addEventListener('mouseenter', function () {
        var rect = fieldTable.getBoundingClientRect();

        rowAddIndicator.style.top = (rect.bottom - 1) + 'px';
        rowAddIndicator.style.left = rect.left + 'px';
        rowAddIndicator.style.width = rect.width + 'px';
        rowAddIndicator.style.display = 'block';
    });

    rowAddButton.addEventListener('mouseleave', function () {
        rowAddIndicator.style.display = 'none';
    });

    var columnAddEndButton = document.getElementById('signatur_column_add_end');
    var columnAddIndicator = document.getElementById('signatur_column_add_indicator');
    var addColumnIndex = null;

    columnAddEndButton.addEventListener('mouseenter', function () {
        var rect = fieldTable.getBoundingClientRect();

        columnAddIndicator.style.top = rect.top + 'px';
        columnAddIndicator.style.left = (rect.right - 1) + 'px';
        columnAddIndicator.style.height = rect.height + 'px';
        columnAddIndicator.style.display = 'block';
    });

    columnAddEndButton.addEventListener('mouseleave', function () {
        columnAddIndicator.style.display = 'none';
    });

    columnAddEndButton.addEventListener('click', function () {
        pushUndoSnapshot();

        /*
         * The table must stay exactly as wide as configured - so the
         * new column's width comes out of the existing columns,
         * shrinking them all proportionally, rather than growing the
         * table itself.
         */
        var totalWidth = parseInt(fieldTable.style.width, 10) || minWidth;
        var newWidth = Math.max(minColumnWidth, Math.floor(totalWidth / (columnWidths.length + 1)));
        var targetWidth = totalWidth - newWidth;
        var oldWidth = columnWidths.reduce(function (sum, w) {
            return sum + w;
        }, 0);
        var ratio = targetWidth / oldWidth;
        var used = 0;

        columnWidths = columnWidths.map(function (w, i) {
            if (i === columnWidths.length - 1) {
                return Math.max(minColumnWidth, targetWidth - used);
            }

            var scaled = Math.max(minColumnWidth, Math.round(w * ratio));
            used += scaled;

            return scaled;
        });

        insertColumnAt(columnWidths.length, newWidth);
    });

    var rowHoverIndicator = document.getElementById('signatur_row_hover_indicator');
    var editSignatureButton = document.getElementById('signatur_edit_signature_button');

    editSignatureButton.addEventListener('mouseenter', function () {
        var tableRect = fieldTable.getBoundingClientRect();

        rowHoverIndicator.style.top = tableRect.top + 'px';
        rowHoverIndicator.style.left = tableRect.left + 'px';
        rowHoverIndicator.style.width = tableRect.width + 'px';
        rowHoverIndicator.style.height = tableRect.height + 'px';
        rowHoverIndicator.style.display = 'block';
    });

    editSignatureButton.addEventListener('mouseleave', function () {
        rowHoverIndicator.style.display = 'none';
    });

    function getCellColSpan(cell) {
        return parseInt(cell.getAttribute('colspan'), 10) || 1;
    }

    function setCellColSpan(cell, span) {
        if (span > 1) {
            cell.setAttribute('colspan', span);
        } else {
            cell.removeAttribute('colspan');
        }
    }

    function getCellRowSpan(cell) {
        return parseInt(cell.getAttribute('rowspan'), 10) || 1;
    }

    function setCellRowSpan(cell, span) {
        if (span > 1) {
            cell.setAttribute('rowspan', span);
        } else {
            cell.removeAttribute('rowspan');
        }
    }

    /**
     * Build the full occupancy grid of the table: grid[row][col] tells
     * which <td> owns that slot, and whether this row is where that
     * cell actually lives in the DOM (`owner`) or merely a slot it
     * covers via rowspan from an earlier row (pass-through).
     */
    function buildGrid() {
        var rows = Array.prototype.slice.call(fieldTbody.children);
        var pending = {};
        var grid = [];

        rows.forEach(function (row) {
            var cells = Array.prototype.slice.call(row.children);
            var col = 0;
            var cellPos = 0;
            var rowSlots = [];

            for (;;) {
                while (pending[col] && pending[col].remaining > 0) {
                    var p = pending[col];

                    rowSlots[col] = { cell: p.cell, start: p.start, end: p.end, owner: false };
                    p.remaining--;

                    if (0 === p.remaining) {
                        delete pending[col];
                    }

                    col++;
                }

                if (cellPos >= cells.length) {
                    break;
                }

                var cell = cells[cellPos];
                var colspan = getCellColSpan(cell);
                var rowspan = getCellRowSpan(cell);
                var start = col;
                var end = col + colspan;

                for (var c = start; c < end; c++) {
                    rowSlots[c] = { cell: cell, start: start, end: end, owner: c === start };
                }

                if (rowspan > 1) {
                    for (var c2 = start; c2 < end; c2++) {
                        pending[c2] = { cell: cell, start: start, end: end, remaining: rowspan - 1 };
                    }
                }

                col = end;
                cellPos++;
            }

            grid.push(rowSlots);
        });

        return grid;
    }

    function findCellPosition(grid, targetCell) {
        for (var r = 0; r < grid.length; r++) {
            for (var c = 0; c < grid[r].length; c++) {
                if (grid[r][c] && grid[r][c].cell === targetCell) {
                    return { row: r, col: c };
                }
            }
        }

        return null;
    }

    function renderColgroup() {
        colgroup.innerHTML = '';

        columnWidths.forEach(function (width) {
            var col = document.createElement('col');
            col.style.width = width + 'px';
            colgroup.appendChild(col);
        });

        renderColumnHeaders();
    }

    /**
     * Excel-style column label: 0 => A, 1 => B, ... 25 => Z,
     * 26 => AA, 27 => AB, ...
     */
    function columnLetter(index) {
        var letter = '';

        index += 1;

        while (index > 0) {
            var remainder = (index - 1) % 26;

            letter = String.fromCharCode(65 + remainder) + letter;
            index = Math.floor((index - 1) / 26);
        }

        return letter;
    }

    function renderColumnHeaders() {
        headerRow.innerHTML = '';

        columnWidths.forEach(function (width, index) {
            var header = document.createElement('th');

            header.className = 'signatur-column-header';
            header.setAttribute('data-index', index);
            header.textContent = columnLetter(index);
            headerRow.appendChild(header);
        });
    }

    function renderResizeHandles() {
        resizeHandles.innerHTML = '';

        if (columnWidths.length < 2) {
            return;
        }

        var headerRect = headerRow.getBoundingClientRect();
        var offset = headerRect.left;

        for (var i = 0; i < columnWidths.length - 1; i++) {
            offset += columnWidths[i];

            var handle = document.createElement('div');

            handle.className = 'signatur-col-resize-handle';
            handle.setAttribute('data-index', i);
            handle.style.top = headerRect.top + 'px';
            handle.style.height = headerRect.height + 'px';
            handle.style.left = offset + 'px';
            resizeHandles.appendChild(handle);
        }
    }

    /**
     * Insert a new grid column at `index` (0..columnWidths.length) across
     * every row. A cell (real or a rowspan covering that boundary from
     * an earlier row) that already reaches across the insertion point
     * simply grows by one column; otherwise a plain new <td> is added
     * at that row's matching position.
     */
    function insertColumnAt(index, width) {
        var grid = buildGrid();

        columnWidths.splice(index, 0, width);

        var grown = [];

        Array.prototype.forEach.call(fieldTbody.children, function (row, r) {
            var rowSlots = grid[r] || [];
            var slot = rowSlots[index];

            if (slot && index > slot.start && index < slot.end) {
                if (grown.indexOf(slot.cell) === -1) {
                    setCellColSpan(slot.cell, getCellColSpan(slot.cell) + 1);
                    grown.push(slot.cell);
                }

                return;
            }

            var referenceCell = null;

            for (var col = index; col < rowSlots.length; col++) {
                if (rowSlots[col] && rowSlots[col].owner) {
                    referenceCell = rowSlots[col].cell;
                    break;
                }
            }

            var newCell = document.createElement('td');
            newCell.innerHTML = '&nbsp;';

            if (referenceCell) {
                row.insertBefore(newCell, referenceCell);
            } else {
                row.appendChild(newCell);
            }
        });

        applyAllRowStyles();
        renderColgroup();
        renderResizeHandles();
    }

    /**
     * Remove grid column `index` from every row. A cell that only
     * covers that one column is removed outright; a cell (real or
     * covering that column via rowspan) that spans more than one
     * column simply shrinks by one.
     */
    function removeColumnAt(index) {
        if (columnWidths.length <= 1) {
            return;
        }

        var grid = buildGrid();
        var removedWidth = columnWidths.splice(index, 1)[0];
        var neighborIndex = index < columnWidths.length ? index : index - 1;
        columnWidths[neighborIndex] += removedWidth;

        var shrunk = [];

        grid.forEach(function (rowSlots) {
            var slot = rowSlots[index];

            if (! slot || shrunk.indexOf(slot.cell) !== -1) {
                return;
            }

            shrunk.push(slot.cell);

            var span = getCellColSpan(slot.cell);

            if (span > 1) {
                setCellColSpan(slot.cell, span - 1);
            } else if (slot.cell.parentNode) {
                slot.cell.parentNode.removeChild(slot.cell);
            }
        });

        renderColgroup();
        renderResizeHandles();
    }

    /**
     * Undo a merge on `cell`: shrink it back to a single 1x1 cell and
     * recreate a plain <td> for every grid position it used to cover
     * (its own row for the extra columns, and every row below it was
     * spanning down into, which currently have no <td> there at all).
     */
    function splitCell(cell) {
        var colspan = getCellColSpan(cell);
        var rowspan = getCellRowSpan(cell);

        if (colspan <= 1 && rowspan <= 1) {
            return;
        }

        var grid = buildGrid();
        var pos = findCellPosition(grid, cell);

        if (! pos) {
            return;
        }

        var startRow = pos.row;
        var startCol = pos.col;
        var rows = Array.prototype.slice.call(fieldTbody.children);

        setCellColSpan(cell, 1);
        setCellRowSpan(cell, 1);

        for (var r = startRow; r < startRow + rowspan; r++) {
            var row = rows[r];
            var rowSlots = grid[r] || [];
            var referenceCell = null;

            for (var col = startCol + colspan; col < rowSlots.length; col++) {
                if (rowSlots[col] && rowSlots[col].owner) {
                    referenceCell = rowSlots[col].cell;
                    break;
                }
            }

            var firstCol = (r === startRow) ? startCol + 1 : startCol;

            for (var c = firstCol; c < startCol + colspan; c++) {
                var newCell = document.createElement('td');
                newCell.innerHTML = '&nbsp;';

                if (referenceCell) {
                    row.insertBefore(newCell, referenceCell);
                } else {
                    row.appendChild(newCell);
                }
            }
        }

        applyAllRowStyles();
        rebuildRowNumbers();
    }

    function nonEmptyRows() {
        return Array.prototype.filter.call(fieldTbody.children, function (row) {
            return row.children.length > 0;
        });
    }

    /**
     * The <tr> a left-side row-number box belongs to (nth non-empty row).
     */
    function rowForNumberBox(box) {
        var boxes = Array.prototype.slice.call(rowsNumbers.children, 1);
        var index = boxes.indexOf(box);

        return -1 === index ? null : (nonEmptyRows()[index] || null);
    }

    /**
     * The reverse of rowForNumberBox() - the left-side row-number box that
     * belongs to a given <tr>.
     */
    function numberBoxForRow(row) {
        var index = nonEmptyRows().indexOf(row);
        var boxes = Array.prototype.slice.call(rowsNumbers.children, 1);

        return -1 === index ? null : (boxes[index] || null);
    }

    /**
     * Show the edit/delete row buttons to the left of the row numbers, so
     * they don't cover them. Toggled by clicking the row number.
     */
    function showRowActionsForRow(row) {
        if (activeRow) {
            activeRow.classList.remove('signatur-row-active');

            var previousBox = numberBoxForRow(activeRow);

            if (previousBox) {
                previousBox.classList.remove('is-active');
            }
        }

        activeRow = row;
        activeRow.classList.add('signatur-row-active');

        var activeBox = numberBoxForRow(activeRow);

        if (activeBox) {
            activeBox.classList.add('is-active');
        }

        updateRowDeleteButtons();

        var rect = row.getBoundingClientRect();

        rowActionsGroup.style.display = 'inline-flex';

        var anchorLeft = rowsNumbers.getBoundingClientRect().left;

        rowActionsGroup.style.top = (rect.top + (rect.height - 32) / 2) + 'px';
        rowActionsGroup.style.left = Math.max(2, anchorLeft - rowActionsGroup.offsetWidth - 6) + 'px';
    }

    function hideRowActions() {
        if (activeRow) {
            activeRow.classList.remove('signatur-row-active');

            var box = numberBoxForRow(activeRow);

            if (box) {
                box.classList.remove('is-active');
            }
        }

        activeRow = null;
        rowActionsGroup.style.display = 'none';
    }

    /**
     * One numbered box per non-empty row, matching the row-actions sidebar.
     * Heights are then synced to the real <tr> heights so the numbers line
     * up with their rows regardless of content / padding / border.
     */
    function rebuildRowNumbers() {
        var count = nonEmptyRows().length;
        var spacer = rowsNumbers.firstElementChild;

        rowsNumbers.innerHTML = '';
        rowsNumbers.appendChild(spacer);

        for (var i = 1; i <= count; i++) {
            var box = document.createElement('div');
            box.className = 'signatur-row-number';
            box.textContent = i;
            rowsNumbers.appendChild(box);
        }

        updateRowDeleteButtons();
        syncRowNumberHeights();
        syncGutterOffset();
    }

    /**
     * The row-number gutter to the left of the table (#signatur_rows_numbers)
     * has no equivalent in the toolbar row above the table, the "add row"
     * button below it, or the copy-button preview further down - all three
     * are width-matched to the table itself but, without this, start flush
     * at the left edge of the whole editor instead of lining up with the
     * table (which sits shifted right by the gutter's width). Its width can
     * change (e.g. 1 vs 3-digit row numbers), so it is measured rather than
     * assumed.
     */
    function syncGutterOffset() {
        var offset = rowsNumbers.offsetWidth
            + (parseFloat(window.getComputedStyle(rowsNumbers).marginRight) || 0);

        rowTopWrap.style.marginLeft = offset + 'px';
        rowAddWrap.style.marginLeft = offset + 'px';

        if (copyButtonSection) {
            copyButtonSection.style.marginLeft = offset + 'px';
        }
    }

    function syncRowNumberHeights() {
        var rows = nonEmptyRows();
        var boxes = Array.prototype.slice.call(rowsNumbers.children, 1);

        rowsNumbers.firstElementChild.style.height = headerRow.getBoundingClientRect().height + 'px';

        rows.forEach(function (row, i) {
            if (boxes[i]) {
                boxes[i].style.height = row.getBoundingClientRect().height + 'px';
            }
        });
    }

    /**
     * A single remaining row can't be deleted (removeRow() refuses),
     * so every "Delete row" button is greyed out while that's true.
     */
    function updateRowDeleteButtons() {
        rowDeleteButton.disabled = fieldTbody.children.length <= 1;
    }

    /**
     * Delete `row` entirely. A cell it owns that only covers this one
     * row disappears with it; a cell it owns with a taller rowspan is
     * instead moved down into the next row with its rowspan reduced
     * by one, so the merge it was part of just gets one row shorter
     * instead of losing its reach entirely. A cell owned by an
     * earlier row that merely passes through this row also has its
     * rowspan reduced by one, for the same reason.
     */
    function removeRow(row) {
        if (fieldTbody.children.length <= 1) {
            return;
        }

        var rows = Array.prototype.slice.call(fieldTbody.children);
        var rowIndex = rows.indexOf(row);

        if (-1 === rowIndex) {
            return;
        }

        var grid = buildGrid();
        var rowSlots = grid[rowIndex] || [];
        var nextRow = rows[rowIndex + 1];
        var handled = [];

        for (var c = 0; c < rowSlots.length; c++) {
            var slot = rowSlots[c];

            if (! slot || handled.indexOf(slot.cell) !== -1) {
                continue;
            }

            handled.push(slot.cell);

            var rowspan = getCellRowSpan(slot.cell);

            if (rowspan <= 1) {
                continue;
            }

            if (slot.owner) {
                var nextRowSlots = grid[rowIndex + 1] || [];
                var referenceCell = null;

                for (var col = slot.end; col < nextRowSlots.length; col++) {
                    if (nextRowSlots[col] && nextRowSlots[col].owner) {
                        referenceCell = nextRowSlots[col].cell;
                        break;
                    }
                }

                setCellRowSpan(slot.cell, rowspan - 1);

                if (referenceCell) {
                    nextRow.insertBefore(slot.cell, referenceCell);
                } else {
                    nextRow.appendChild(slot.cell);
                }
            } else {
                setCellRowSpan(slot.cell, rowspan - 1);
            }
        }

        row.parentNode.removeChild(row);

        applyAllRowStyles();
        rebuildRowNumbers();
        hideRowActions();
        clearSelection();
        hideColumnButtons();
        hideColumnCombineButton();
        renderResizeHandles();
    }

    rowsNumbers.addEventListener('click', function (e) {
        var box = e.target.closest('.signatur-row-number');

        if (! box || box.classList.contains('signatur-row-number-spacer')) {
            return;
        }

        var row = rowForNumberBox(box);

        if (! row) {
            return;
        }

        var alreadyShown = 'none' !== rowActionsGroup.style.display && row === activeRow;

        clearSelection();

        if (! alreadyShown) {
            showRowActionsForRow(row);
        }
    });

    rowActionsGroup.addEventListener('click', function (e) {
        if (! e.target.closest('.signatur-row-delete-button') || ! activeRow || fieldTbody.children.length <= 1) {
            return;
        }

        pushUndoSnapshot();
        removeRow(activeRow);
    });

    rowAddButton.addEventListener('click', function () {
        pushUndoSnapshot();

        var row = document.createElement('tr');

        row.dataset.padding = JSON.stringify(ZERO_PADDING);
        row.dataset.border = JSON.stringify(DEFAULT_ROW_BORDER);
        row.dataset.background = DEFAULT_ROW_BACKGROUND;

        columnWidths.forEach(function () {
            var cell = document.createElement('td');
            cell.innerHTML = '&nbsp;';
            row.appendChild(cell);
        });

        fieldTbody.appendChild(row);
        applyRowStyles(row);
        rebuildRowNumbers();
        renderResizeHandles();
    });

    renderColumnHeaders();
    renderResizeHandles();
    rebuildRowNumbers();

    if (window.ResizeObserver) {
        new ResizeObserver(syncRowNumberHeights).observe(fieldTbody);
    }

    var resizing = null;

    resizeHandles.addEventListener('mousedown', function (e) {
        var handle = e.target.closest('.signatur-col-resize-handle');

        if (! handle) {
            return;
        }

        e.preventDefault();
        pushUndoSnapshot();

        var index = parseInt(handle.getAttribute('data-index'), 10);

        resizing = {
            index: index,
            startX: e.clientX,
            widthA: columnWidths[index],
            widthB: columnWidths[index + 1]
        };
    });

    document.addEventListener('mousemove', function (e) {
        if (! resizing) {
            return;
        }

        var delta = e.clientX - resizing.startX;
        var newA = resizing.widthA + delta;
        var newB = resizing.widthB - delta;

        if (newA < minColumnWidth) {
            newA = minColumnWidth;
            newB = resizing.widthA + resizing.widthB - newA;
        }

        if (newB < minColumnWidth) {
            newB = minColumnWidth;
            newA = resizing.widthA + resizing.widthB - newB;
        }

        columnWidths[resizing.index] = newA;
        columnWidths[resizing.index + 1] = newB;
        renderColgroup();
        renderResizeHandles();
    });

    function stopResizing() {
        resizing = null;
    }

    document.addEventListener('mouseup', stopResizing);
    document.documentElement.addEventListener('mouseleave', stopResizing);

    window.addEventListener('scroll', renderResizeHandles, true);
    window.addEventListener('resize', renderResizeHandles);

    var cellDeleteElementButton = document.getElementById('signatur_cell_delete_element');
    var cellCopyElementButton = document.getElementById('signatur_cell_copy_element');
    var cellPasteElementButton = document.getElementById('signatur_cell_paste_element');
    var cellEditElementButton = document.getElementById('signatur_cell_edit_element');
    var cellAddElementButton = document.getElementById('signatur_cell_add_element');
    var elementClipboard = null;
    var columnAddButton = document.getElementById('signatur_column_add');
    var columnCombineButton = document.getElementById('signatur_column_combine');
    var columnRemoveButton = document.getElementById('signatur_column_remove');
    var columnSplitButton = document.getElementById('signatur_column_split');
    var activeColumnCell = null;
    var selectedCells = [];
    var isSelecting = false;
    var selectionAnchor = null;

    function highlightSelection(cells) {
        selectedCells.forEach(function (cell) {
            cell.classList.remove('signatur-cell-selected');
        });

        selectedCells = cells;

        selectedCells.forEach(function (cell) {
            cell.classList.add('signatur-cell-selected');
        });
    }

    /**
     * A selection may only grow along one axis from the anchor cell -
     * straight across a row, or straight down a column, never both at
     * once. When the drag moves diagonally, whichever axis moved
     * further wins and the other is locked back to the anchor.
     *
     * Growing down a column also stops at the first row whose cell in
     * that column covers a different set of columns than the
     * anchor's (same colspan alone isn't enough - two cells can both
     * be 2 columns wide yet start at different columns, which is just
     * as invalid a pairing). Such a row, and anything past it, is
     * simply never part of the selection.
     */
    function selectRectangle(fromCell, toCell) {
        var grid = buildGrid();
        var fromPos = findCellPosition(grid, fromCell);
        var toPos = findCellPosition(grid, toCell);

        if (! fromPos || ! toPos) {
            return [];
        }

        var rowDelta = Math.abs(toPos.row - fromPos.row);
        var colDelta = Math.abs(toPos.col - fromPos.col);
        var minRow, maxRow, minCol, maxCol;

        if (rowDelta >= colDelta) {
            minCol = maxCol = fromPos.col;

            var anchorSlot = grid[fromPos.row] && grid[fromPos.row][minCol];
            var step = toPos.row >= fromPos.row ? 1 : -1;
            var boundaryRow = fromPos.row;

            for (var r = fromPos.row + step; r !== toPos.row + step; r += step) {
                var slot = grid[r] && grid[r][minCol];

                if (! anchorSlot || ! slot || slot.start !== anchorSlot.start || slot.end !== anchorSlot.end) {
                    break;
                }

                boundaryRow = r;
            }

            minRow = Math.min(fromPos.row, boundaryRow);
            maxRow = Math.max(fromPos.row, boundaryRow);
        } else {
            minCol = Math.min(fromPos.col, toPos.col);
            maxCol = Math.max(fromPos.col, toPos.col);
            minRow = maxRow = fromPos.row;
        }

        var cells = [];

        for (var r = minRow; r <= maxRow; r++) {
            for (var c = minCol; c <= maxCol; c++) {
                var slot = grid[r] && grid[r][c];

                if (slot && cells.indexOf(slot.cell) === -1) {
                    cells.push(slot.cell);
                }
            }
        }

        return cells;
    }

    /**
     * Cells spread across more than one row are never mergeable
     * unless every row in range has a cell covering that exact same
     * set of columns - matching colspan alone isn't enough, since two
     * cells can both be 2 columns wide yet start at different
     * columns (a "staircase" pairing that isn't a real rectangle).
     * This mirrors the rule selectRectangle() already enforces while
     * dragging, kept here too as a guard against the merge being
     * triggered any other way. A same-row (horizontal) merge has no
     * such constraint.
     */
    function hasVerticalRangeMismatch(cells) {
        var grid = buildGrid();
        var positions = cells.map(function (cell) {
            return findCellPosition(grid, cell);
        }).filter(function (pos) {
            return null !== pos;
        });

        if (positions.length < 2) {
            return false;
        }

        var minRow = Math.min.apply(null, positions.map(function (pos) { return pos.row; }));
        var maxRow = Math.max.apply(null, positions.map(function (pos) { return pos.row; }));

        if (minRow === maxRow) {
            return false;
        }

        var anchorSlot = grid[positions[0].row] && grid[positions[0].row][positions[0].col];

        for (var i = 0; i < positions.length; i++) {
            var slot = grid[positions[i].row] && grid[positions[i].row][positions[i].col];

            if (! anchorSlot || ! slot || slot.start !== anchorSlot.start || slot.end !== anchorSlot.end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mirrors hasVerticalRangeMismatch() for the other direction:
     * cells spread across more than one column are never mergeable
     * unless every one of them spans exactly the same set of rows -
     * matching rowspan alone isn't enough, since two cells can both
     * be 2 rows tall yet start at different rows. A single-row
     * (horizontal-only) merge has no such constraint.
     */
    function hasHorizontalRangeMismatch(cells) {
        var grid = buildGrid();
        var positions = cells.map(function (cell) {
            return findCellPosition(grid, cell);
        }).filter(function (pos) {
            return null !== pos;
        });

        if (positions.length < 2) {
            return false;
        }

        var minCol = Math.min.apply(null, positions.map(function (pos) { return pos.col; }));
        var maxCol = Math.max.apply(null, positions.map(function (pos) { return pos.col; }));

        if (minCol === maxCol) {
            return false;
        }

        var anchorRowStart = positions[0].row;
        var anchorRowEnd = anchorRowStart + getCellRowSpan(cells[0]);

        for (var i = 0; i < positions.length; i++) {
            var rowStart = positions[i].row;
            var rowEnd = rowStart + getCellRowSpan(cells[i]);

            if (rowStart !== anchorRowStart || rowEnd !== anchorRowEnd) {
                return true;
            }
        }

        return false;
    }

    function positionColumnButtons(cell) {
        var buttonSize = 32;
        var gap = 4;
        var hasElement = !! cell.dataset.element;
        // A cell holds at most one element: either the "add" button, or the
        // edit / delete / copy trio. "Paste" shows only on an empty cell
        // once something has been copied.
        var buttons = [
            { el: cellEditElementButton, show: hasElement },
            { el: cellDeleteElementButton, show: hasElement },
            { el: cellCopyElementButton, show: hasElement },
            { el: cellPasteElementButton, show: ! hasElement && null !== elementClipboard },
            { el: cellAddElementButton, show: ! hasElement },
            { el: columnSplitButton, show: getCellColSpan(cell) > 1 || getCellRowSpan(cell) > 1 }
        ];

        var visibleCount = buttons.filter(function (b) { return b.show; }).length;
        var groupWidth = buttonSize * visibleCount + gap * (visibleCount - 1);
        var rect = cell.getBoundingClientRect();
        var top = rect.top + (rect.height - buttonSize) / 2;
        var left = rect.left + (rect.width - groupWidth) / 2;

        buttons.forEach(function (b) {
            if (b.show) {
                b.el.style.top = top + 'px';
                b.el.style.left = left + 'px';
                b.el.style.display = 'inline-flex';
                left += buttonSize + gap;
            } else {
                b.el.style.display = 'none';
            }
        });
    }

    function positionColumnCombineButton(cells) {
        var buttonSize = 32;
        var top = Infinity;
        var left = Infinity;
        var bottom = -Infinity;
        var right = -Infinity;

        cells.forEach(function (cell) {
            var rect = cell.getBoundingClientRect();

            top = Math.min(top, rect.top);
            left = Math.min(left, rect.left);
            bottom = Math.max(bottom, rect.bottom);
            right = Math.max(right, rect.right);
        });

        columnCombineButton.style.top = (top + (bottom - top - buttonSize) / 2) + 'px';
        columnCombineButton.style.left = (left + (right - left - buttonSize) / 2) + 'px';
        columnCombineButton.style.display = 'inline-flex';
    }

    function hideCellElementButtons() {
        cellDeleteElementButton.style.display = 'none';
        cellCopyElementButton.style.display = 'none';
        cellPasteElementButton.style.display = 'none';
        cellEditElementButton.style.display = 'none';
        cellAddElementButton.style.display = 'none';
    }

    function hideColumnAddButton() {
        columnAddButton.style.display = 'none';
        columnRemoveButton.style.display = 'none';
        columnAddIndicator.style.display = 'none';
        addColumnIndex = null;
    }

    /**
     * Show the add/remove column buttons under a clicked column header, with
     * an indicator line at that column's right edge. "+" inserts a new
     * column right after this one; "-" removes this column (hidden when only
     * one column is left).
     */
    function showColumnAddForHeader(th) {
        addColumnIndex = parseInt(th.getAttribute('data-index'), 10);

        var buttonSize = 32;
        var gap = 4;
        var showRemove = columnWidths.length > 1;
        var count = 1 + (showRemove ? 1 : 0);
        var groupWidth = buttonSize * count + gap * (count - 1);
        var thRect = th.getBoundingClientRect();
        var tableRect = fieldTable.getBoundingClientRect();
        var groupLeft = thRect.left + thRect.width / 2 - groupWidth / 2;
        var top = thRect.bottom;

        columnAddButton.style.top = top + 'px';
        columnAddButton.style.left = groupLeft + 'px';
        columnAddButton.style.display = 'inline-flex';

        if (showRemove) {
            columnRemoveButton.style.top = top + 'px';
            columnRemoveButton.style.left = (groupLeft + buttonSize + gap) + 'px';
            columnRemoveButton.style.display = 'inline-flex';
        } else {
            columnRemoveButton.style.display = 'none';
        }

        columnAddIndicator.style.top = tableRect.top + 'px';
        columnAddIndicator.style.left = (thRect.right - 1) + 'px';
        columnAddIndicator.style.height = tableRect.height + 'px';
        columnAddIndicator.style.display = 'block';
    }

    function hideColumnCombineButton() {
        columnCombineButton.style.display = 'none';
    }

    function hideColumnRemoveButton() {
        columnRemoveButton.style.display = 'none';
    }

    function hideColumnSplitButton() {
        columnSplitButton.style.display = 'none';
    }

    function hideColumnButtons() {
        activeColumnCell = null;
        hideCellElementButtons();
        hideColumnAddButton();
        hideColumnRemoveButton();
        hideColumnSplitButton();
    }

    function clearSelection() {
        highlightSelection([]);
        hideColumnCombineButton();
        hideColumnButtons();
        hideRowActions();
    }

    fieldTbody.addEventListener('mousedown', function (e) {
        var cell = e.target.closest('td');

        if (! cell) {
            return;
        }

        e.preventDefault();
        isSelecting = true;
        selectionAnchor = cell;
        hideColumnButtons();
        hideColumnCombineButton();
        hideRowActions();
        highlightSelection([cell]);
    });

    // A text element's link is live markup in the grid - don't follow it
    // while building the signature.
    fieldTbody.addEventListener('click', function (e) {
        if (e.target.closest('a')) {
            e.preventDefault();
        }
    });

    fieldTbody.addEventListener('mouseover', function (e) {
        if (! isSelecting) {
            return;
        }

        var cell = e.target.closest('td');

        if (! cell) {
            return;
        }

        var cells = selectRectangle(selectionAnchor, cell);

        if (hasVerticalRangeMismatch(cells) || hasHorizontalRangeMismatch(cells)) {
            return;
        }

        highlightSelection(cells);
    });

    columnRemoveButton.addEventListener('click', function () {
        var index = addColumnIndex;

        if (index === null || isNaN(index) || index < 0 || index >= columnWidths.length || columnWidths.length <= 1) {
            hideColumnAddButton();
            return;
        }

        pushUndoSnapshot();
        removeColumnAt(index);
        hideColumnAddButton();
    });

    columnSplitButton.addEventListener('click', function () {
        if (! activeColumnCell) {
            return;
        }

        pushUndoSnapshot();
        splitCell(activeColumnCell);
        clearSelection();
    });

    cellDeleteElementButton.addEventListener('click', function () {
        if (! activeColumnCell || ! activeColumnCell.dataset.element) {
            hideColumnButtons();
            return;
        }

        pushUndoSnapshot();
        applyElementToCell(activeColumnCell, null);
        syncRowNumberHeights();
        positionColumnButtons(activeColumnCell);
    });

    cellCopyElementButton.addEventListener('click', function () {
        if (! activeColumnCell || ! activeColumnCell.dataset.element) {
            return;
        }

        try {
            elementClipboard = JSON.parse(activeColumnCell.dataset.element);
        } catch (err) {
            elementClipboard = null;
        }

        positionColumnButtons(activeColumnCell);
    });

    cellPasteElementButton.addEventListener('click', function () {
        if (! activeColumnCell || null === elementClipboard) {
            return;
        }

        pushUndoSnapshot();
        applyElementToCell(activeColumnCell, elementClipboard);
        syncRowNumberHeights();
        positionColumnButtons(activeColumnCell);
    });

    cellEditElementButton.addEventListener('click', function () {
        if (! activeColumnCell || ! activeColumnCell.dataset.element) {
            return;
        }

        var stored;

        try {
            stored = JSON.parse(activeColumnCell.dataset.element);
        } catch (err) {
            return;
        }

        var type = stored && ('image' === stored.type || 'social' === stored.type || 'field' === stored.type) ? stored.type : 'text';
        var titleAttr = 'text' === type ? 'data-title-text' : 'data-title-' + type;

        modalTargetCell = activeColumnCell;
        openModal(cellEditElementButton.getAttribute(titleAttr), type);
    });

    function finishSelection() {
        if (! isSelecting) {
            return;
        }

        isSelecting = false;

        if (1 === selectedCells.length) {
            activeColumnCell = selectedCells[0];
            positionColumnButtons(activeColumnCell);
        } else if (selectedCells.length >= 2) {
            if (hasVerticalRangeMismatch(selectedCells) || hasHorizontalRangeMismatch(selectedCells)) {
                clearSelection();
            } else {
                positionColumnCombineButton(selectedCells);
            }
        } else {
            clearSelection();
        }
    }

    document.addEventListener('mouseup', finishSelection);

    document.documentElement.addEventListener('mouseleave', finishSelection);

    headerRow.addEventListener('click', function (e) {
        var th = e.target.closest('.signatur-column-header');

        if (! th) {
            return;
        }

        var index = parseInt(th.getAttribute('data-index'), 10);
        var alreadyShown = 'none' !== columnAddButton.style.display && index === addColumnIndex;

        clearSelection();

        // Toggle: a second click on the same header just hides it.
        if (! alreadyShown) {
            showColumnAddForHeader(th);
        }
    });

    columnAddButton.addEventListener('click', function () {
        var index = addColumnIndex;

        if (index === null || isNaN(index) || index < 0 || index >= columnWidths.length) {
            hideColumnAddButton();
            return;
        }

        pushUndoSnapshot();

        var neighborWidth = columnWidths[index];
        var newWidth = Math.max(minColumnWidth, Math.floor(neighborWidth / 2));

        columnWidths[index] = Math.max(minColumnWidth, neighborWidth - newWidth);
        insertColumnAt(index + 1, newWidth);

        hideColumnAddButton();
    });

    columnCombineButton.addEventListener('click', function () {
        if (selectedCells.length < 2 || hasVerticalRangeMismatch(selectedCells) || hasHorizontalRangeMismatch(selectedCells)) {
            return;
        }

        pushUndoSnapshot();

        var grid = buildGrid();
        var cellPositions = selectedCells.map(function (cell) {
            return { cell: cell, pos: findCellPosition(grid, cell) };
        }).filter(function (item) {
            return null !== item.pos;
        });

        if (cellPositions.length < 2) {
            return;
        }

        /*
         * The merged rectangle's true bounds come from each cell's
         * full extent (its grid slot's start/end for columns, its
         * own rowspan for rows) - not just its top-left grid
         * position. Using only the start position here previously
         * collapsed any cell already wider/taller than 1 back down
         * to 1 when it got re-set below.
         */
        var minRow = Infinity;
        var maxRow = -Infinity;
        var minCol = Infinity;
        var maxCol = -Infinity;

        cellPositions.forEach(function (item) {
            var slot = grid[item.pos.row] && grid[item.pos.row][item.pos.col];

            if (! slot) {
                return;
            }

            minRow = Math.min(minRow, item.pos.row);
            maxRow = Math.max(maxRow, item.pos.row + getCellRowSpan(item.cell) - 1);
            minCol = Math.min(minCol, slot.start);
            maxCol = Math.max(maxCol, slot.end - 1);
        });

        var rectCells = [];

        for (var r = minRow; r <= maxRow; r++) {
            for (var c = minCol; c <= maxCol; c++) {
                var rectSlot = grid[r] && grid[r][c];

                if (rectSlot && rectCells.indexOf(rectSlot.cell) === -1) {
                    rectCells.push(rectSlot.cell);
                }
            }
        }

        var topLeftSlot = grid[minRow][minCol];
        var topLeftCell = topLeftSlot.cell;

        setCellColSpan(topLeftCell, maxCol - minCol + 1);
        setCellRowSpan(topLeftCell, maxRow - minRow + 1);
        topLeftCell.classList.remove('signatur-cell-selected');

        // The merged cell keeps the first element found among the cells
        // being merged (reading order) - so a cell's content is not lost
        // just because it was not the top-left one.
        if (! topLeftCell.dataset.element) {
            for (var e = 0; e < rectCells.length; e++) {
                if (rectCells[e] !== topLeftCell && rectCells[e].dataset.element) {
                    try {
                        applyElementToCell(topLeftCell, JSON.parse(rectCells[e].dataset.element));
                    } catch (err) {
                        // Corrupt data-element - leave the merged cell empty.
                    }

                    break;
                }
            }
        }

        rectCells.forEach(function (cell) {
            if (cell !== topLeftCell && cell.parentNode) {
                cell.parentNode.removeChild(cell);
            }
        });

        rebuildRowNumbers();

        selectedCells = [];
        clearSelection();
    });

    document.addEventListener('click', function (e) {
        if (! e.target.closest('#signatur_field_table')
            && ! e.target.closest('#signatur_rows_numbers')
            && ! e.target.closest('#signatur_row_actions')) {
            clearSelection();
        }
    });

    window.addEventListener('scroll', function () {
        hideColumnButtons();
        hideColumnCombineButton();
        hideRowActions();
        rowAddIndicator.style.display = 'none';
        columnAddIndicator.style.display = 'none';
    }, true);

    modalCancel.addEventListener('click', closeModal);

    modalSave.addEventListener('click', function () {
        if ('text' === activeModalType && modalTargetCell) {
            var textInput = document.getElementById('signatur_text_input');
            var sizeInput = document.getElementById('signatur_text_size');
            var colorInput = document.getElementById('signatur_text_color');
            var alignSelect = document.getElementById('signatur_text_align');
            var valignSelect = document.getElementById('signatur_text_valign');
            var size = parseInt(sizeInput.value, 10);

            if (isNaN(size)) {
                size = 11;
            }

            var element = {
                type: 'text',
                text: textInput.value.replace(/\r\n/g, '\n'),
                size: Math.max(8, Math.min(72, size)),
                color: colorInput.value,
                bold: document.getElementById('signatur_text_bold').classList.contains('is-active'),
                italic: document.getElementById('signatur_text_italic').classList.contains('is-active'),
                underline: document.getElementById('signatur_text_underline').classList.contains('is-active'),
                align: alignSelect.value,
                valign: valignSelect.value,
                link: document.getElementById('signatur_text_link').value.trim()
            };

            pushUndoSnapshot();
            applyElementToCell(modalTargetCell, element);
            syncRowNumberHeights();
        }

        if ('image' === activeModalType && modalTargetCell) {
            var imageWidth = parseInt(document.getElementById('signatur_image_width').value, 10);

            if (isNaN(imageWidth)) {
                imageWidth = 200;
            }

            var imageElement = {
                type: 'image',
                url: document.getElementById('signatur_image_url').value.trim(),
                width: Math.max(1, Math.min(1000, imageWidth)),
                align: document.getElementById('signatur_image_align').value,
                valign: document.getElementById('signatur_image_valign').value,
                link: document.getElementById('signatur_image_link').value.trim(),
                padding: readPaddingInputs('signatur_image_padding')
            };

            // An empty URL clears the cell (handled inside applyElementToCell),
            // mirroring how clearing a text element's text removes it.
            pushUndoSnapshot();
            applyElementToCell(modalTargetCell, imageElement);
            syncRowNumberHeights();
        }

        if ('social' === activeModalType && modalTargetCell) {
            var socialHeight = parseInt(document.getElementById('signatur_social_height').value, 10);

            if (isNaN(socialHeight)) {
                socialHeight = 32;
            }

            var socialElement = {
                type: 'social',
                network: document.getElementById('signatur_social_network').value,
                icon: document.getElementById('signatur_social_icon_url').value.trim(),
                link: document.getElementById('signatur_social_link').value.trim(),
                height: Math.max(8, Math.min(200, socialHeight)),
                align: document.getElementById('signatur_social_align').value,
                valign: document.getElementById('signatur_social_valign').value,
                padding: readPaddingInputs('signatur_social_padding')
            };

            // An empty link clears the cell (handled inside applyElementToCell) -
            // the link is required, mirroring how an empty URL clears an image.
            pushUndoSnapshot();
            applyElementToCell(modalTargetCell, socialElement);
            syncRowNumberHeights();
        }

        if ('field' === activeModalType && modalTargetCell) {
            var fieldSize = parseInt(document.getElementById('signatur_field_size').value, 10);

            if (isNaN(fieldSize)) {
                fieldSize = 11;
            }

            var fieldElement = {
                type: 'field',
                field_type: document.getElementById('signatur_field_type').value,
                placeholder: document.getElementById('signatur_field_placeholder').value.trim(),
                required: document.getElementById('signatur_field_required').checked,
                link: document.getElementById('signatur_field_link').checked,
                size: Math.max(8, Math.min(72, fieldSize)),
                color: document.getElementById('signatur_field_color').value,
                bold: document.getElementById('signatur_field_bold').classList.contains('is-active'),
                italic: document.getElementById('signatur_field_italic').classList.contains('is-active'),
                underline: document.getElementById('signatur_field_underline').classList.contains('is-active'),
                align: document.getElementById('signatur_field_align').value,
                valign: document.getElementById('signatur_field_valign').value
            };

            pushUndoSnapshot();
            applyElementToCell(modalTargetCell, fieldElement);
            syncRowNumberHeights();
        }

        if ('row' === activeModalType && modalTargetRow) {
            var rowPadding = readPaddingInputs('signatur_row_padding');

            var borderWidth = parseInt(document.getElementById('signatur_row_border_width').value, 10);
            var rowBorder = {
                style: document.getElementById('signatur_row_border_style').value,
                width: Math.max(0, Math.min(20, isNaN(borderWidth) ? 0 : borderWidth)),
                color: document.getElementById('signatur_row_border_color').value,
                sides: document.getElementById('signatur_row_border_sides').value
            };

            var backgroundInput = document.getElementById('signatur_row_background_color');
            var rowBackground = '1' === backgroundInput.dataset.active ? backgroundInput.value : '';

            pushUndoSnapshot();
            modalTargetRow.dataset.padding = JSON.stringify(rowPadding);
            modalTargetRow.dataset.border = JSON.stringify(rowBorder);
            modalTargetRow.dataset.background = rowBackground;
            applyRowStyles(modalTargetRow);
            syncRowNumberHeights();
        }

        if ('width' === activeModalType) {
            pushUndoSnapshot();

            signatureBorder = document.getElementById('signatur_border_toggle').checked;

            var widthInput = document.getElementById('signatur_width_input');
            var width = clampWidth(widthInput.value);

            rowTopWrap.style.width = width + 'px';
            rowAddWrap.style.width = width + 'px';
            rowGroup.style.width = (width + 200) + 'px';
            fieldTable.style.width = width + 'px';

            var oldWidth = columnWidths.reduce(function (sum, w) {
                return sum + w;
            }, 0);
            var ratio = width / oldWidth;
            var used = 0;

            columnWidths = columnWidths.map(function (w, i) {
                if (i === columnWidths.length - 1) {
                    return Math.max(minColumnWidth, width - used);
                }

                var scaled = Math.max(minColumnWidth, Math.round(w * ratio));
                used += scaled;

                return scaled;
            });

            renderColgroup();
            renderResizeHandles();
        }

        if ('copy_button' === activeModalType) {
            pushUndoSnapshot();

            var cbFontSize = parseInt(document.getElementById('signatur_copy_button_font_size').value, 10);
            var cbBorderWidth = parseInt(document.getElementById('signatur_copy_button_border_width').value, 10);
            var cbBorderRadius = parseInt(document.getElementById('signatur_copy_button_border_radius').value, 10);
            var cbPadding = readPaddingInputs('signatur_copy_button_padding');

            var cbText = document.getElementById('signatur_copy_button_text').value.trim();

            copyButtonSettings = {
                text: '' === cbText ? DEFAULT_COPY_BUTTON.text : cbText,
                align: document.getElementById('signatur_copy_button_align').value,
                bg_color: document.getElementById('signatur_copy_button_bg_color').value,
                text_color: document.getElementById('signatur_copy_button_text_color').value,
                font_size: Math.max(8, Math.min(72, isNaN(cbFontSize) ? DEFAULT_COPY_BUTTON.font_size : cbFontSize)),
                padding: cbPadding,
                border_width: Math.max(0, Math.min(20, isNaN(cbBorderWidth) ? 0 : cbBorderWidth)),
                border_style: document.getElementById('signatur_copy_button_border_style').value,
                border_color: document.getElementById('signatur_copy_button_border_color').value,
                border_radius: Math.max(0, Math.min(100, isNaN(cbBorderRadius) ? 0 : cbBorderRadius)),
                text_transform: document.getElementById('signatur_copy_button_text_transform').value
            };

            copyButtonWrap.dataset.copyButton = JSON.stringify(copyButtonSettings);
            applyCopyButtonPreview(copyButtonSettings);
        }

        closeModal();
    });

    modalOverlay.addEventListener('click', function (e) {
        if (e.target === modalOverlay) {
            closeModal();
        }
    });

    var form = document.getElementById('signatur_form');
    var settingsInput = document.getElementById('signatur_settings_input');

    /**
     * A full, independent snapshot of the builder's state - used both
     * for the form submission and for each undo/redo step.
     */
    function serializeSettings() {
        var rows = Array.prototype.map.call(fieldTbody.children, function (row) {
            return Array.prototype.map.call(row.children, function (cell) {
                var span = [getCellColSpan(cell), getCellRowSpan(cell)];

                if (cell.dataset.element) {
                    try {
                        span.push(JSON.parse(cell.dataset.element));
                    } catch (err) {
                        // Ignore a corrupt data-element and treat the cell as empty.
                    }
                }

                return span;
            });
        });

        var rowPadding = Array.prototype.map.call(fieldTbody.children, readRowPadding);
        var rowBorder = Array.prototype.map.call(fieldTbody.children, readRowBorder);
        var rowBackground = Array.prototype.map.call(fieldTbody.children, readRowBackground);

        return {
            width: parseInt(fieldTable.style.width, 10) || minWidth,
            border: signatureBorder,
            columns: columnWidths.slice(),
            rows: rows,
            row_padding: rowPadding,
            row_border: rowBorder,
            row_background: rowBackground,
            copy_button: copyButtonSettings
        };
    }

    /**
     * Rebuild the whole table (colgroup, header, rows) plus the
     * row-actions sidebar from a snapshot produced by
     * serializeSettings() - this is how undo/redo restores a step.
     */
    function applySettings(settings) {
        var width = settings.width;

        signatureBorder = !! settings.border;
        fieldTable.style.width = width + 'px';
        rowTopWrap.style.width = width + 'px';
        rowAddWrap.style.width = width + 'px';
        rowGroup.style.width = (width + 200) + 'px';

        columnWidths = settings.columns.slice();
        renderColgroup();

        fieldTbody.innerHTML = '';

        settings.rows.forEach(function (rowCells, rowIndex) {
            var row = document.createElement('tr');
            var rowPad = (settings.row_padding && settings.row_padding[rowIndex]) || ZERO_PADDING;
            var rowBord = (settings.row_border && settings.row_border[rowIndex]) || DEFAULT_ROW_BORDER;
            var rowBg = (settings.row_background && settings.row_background[rowIndex]) || DEFAULT_ROW_BACKGROUND;

            row.dataset.padding = JSON.stringify(rowPad);
            row.dataset.border = JSON.stringify(rowBord);
            row.dataset.background = rowBg;

            rowCells.forEach(function (cellSpan) {
                var cell = document.createElement('td');

                setCellColSpan(cell, cellSpan[0]);
                setCellRowSpan(cell, cellSpan[1]);
                applyElementToCell(cell, cellSpan[2] || null);
                row.appendChild(cell);
            });

            fieldTbody.appendChild(row);
        });

        applyAllRowStyles();
        rebuildRowNumbers();

        copyButtonSettings = settings.copy_button || JSON.parse(JSON.stringify(DEFAULT_COPY_BUTTON));
        copyButtonWrap.dataset.copyButton = JSON.stringify(copyButtonSettings);
        applyCopyButtonPreview(copyButtonSettings);

        clearSelection();
        hideColumnButtons();
        hideColumnCombineButton();
        renderResizeHandles();
    }

    var undoStack = [];
    var redoStack = [];
    var undoButton = document.getElementById('signatur_undo');
    var redoButton = document.getElementById('signatur_redo');

    function updateUndoRedoButtons() {
        undoButton.disabled = 0 === undoStack.length;
        redoButton.disabled = 0 === redoStack.length;
    }

    function pushUndoSnapshot() {
        undoStack.push(serializeSettings());
        redoStack = [];
        updateUndoRedoButtons();
    }

    undoButton.addEventListener('click', function () {
        if (0 === undoStack.length) {
            return;
        }

        redoStack.push(serializeSettings());
        applySettings(undoStack.pop());
        updateUndoRedoButtons();
    });

    redoButton.addEventListener('click', function () {
        if (0 === redoStack.length) {
            return;
        }

        undoStack.push(serializeSettings());
        applySettings(redoStack.pop());
        updateUndoRedoButtons();
    });

    updateUndoRedoButtons();

    form.addEventListener('submit', function () {
        settingsInput.value = JSON.stringify(serializeSettings());
    });
})();
