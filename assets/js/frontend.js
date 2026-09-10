(function () {
    'use strict';

    /**
     * A "widget" is one fillable thing in a signature: a text/email/tel
     * <input class="signatur-field">, or a profile-photo picker
     * <label class="signatur-field-photo">.
     */
    function isPhotoWidget(w) {
        return !!(w.classList && w.classList.contains('signatur-field-photo'));
    }

    function photoImg(label) {
        return label.querySelector('.signatur-field-photo-img');
    }

    function photoDataUrl(label) {
        var img = photoImg(label);
        var src = img ? (img.getAttribute('src') || '') : '';

        return /^data:/i.test(src) ? src : '';
    }

    function widgetHasValue(w) {
        return isPhotoWidget(w) ? '' !== photoDataUrl(w) : '' !== w.value.trim();
    }

    function widgetIsValid(w) {
        if (!widgetHasValue(w)) {
            return false;
        }

        if (isPhotoWidget(w)) {
            return true;
        }

        if ('tel' === w.getAttribute('type')) {
            return /\d/.test(w.value.trim());
        }

        return w.checkValidity();
    }

    /**
     * The href for a linked field value, keyed off the input's type. Only
     * ever produces a mailto:, tel: or https:// URL.
     */
    function fieldLinkHref(type, value) {
        if ('email' === type) {
            return 'mailto:' + value;
        }

        if ('tel' === type) {
            return 'tel:' + value.replace(/[^\d+]/g, '');
        }

        if (/^https?:\/\//i.test(value)) {
            return value;
        }

        return 'https://' + value.replace(/^\/+/, '');
    }

    /**
     * Turn every fillable widget inside a cloned signature table into plain
     * markup the email client can keep: a text field becomes a <span> (or
     * <a> when linked); a photo field becomes a plain <img> with the chosen
     * photo, or is dropped when no photo was added. The clone's parent
     * cells also lose the page-only pale-yellow fill.
     */
    function bakeFieldInputs(scope) {
        Array.prototype.forEach.call(scope.querySelectorAll('input.signatur-field'), function (input) {
            var value = input.value.trim();
            var linked = '1' === input.getAttribute('data-link') && '' !== value;
            var node = document.createElement(linked ? 'a' : 'span');

            node.textContent = value;
            node.style.fontFamily = input.style.fontFamily;
            node.style.fontSize = input.style.fontSize;
            node.style.color = input.style.color;
            node.style.fontWeight = input.style.fontWeight;
            node.style.fontStyle = input.style.fontStyle;

            if (linked) {
                node.setAttribute('href', fieldLinkHref(input.getAttribute('type'), value));
                node.setAttribute('target', '_blank');
                node.setAttribute('rel', 'noopener noreferrer');
                node.style.textDecoration = 'underline';
            } else {
                node.style.textDecoration = input.style.textDecoration;
            }

            if (input.parentNode && input.parentNode.style) {
                input.parentNode.style.backgroundColor = '';
            }

            input.parentNode.replaceChild(node, input);
        });

        Array.prototype.forEach.call(scope.querySelectorAll('.signatur-field-photo'), function (label) {
            if (label.parentNode && label.parentNode.style) {
                label.parentNode.style.backgroundColor = '';
            }

            var img = photoImg(label);
            var src = img ? (img.getAttribute('src') || '') : '';

            if (/^data:/i.test(src)) {
                var out = img.cloneNode(true);
                out.removeAttribute('class');
                label.parentNode.replaceChild(out, label);
            } else {
                label.parentNode.removeChild(label);
            }
        });
    }

    /**
     * True when every required fillable widget has a value. Alerts and
     * returns false on the first empty one.
     */
    function requiredFieldsFilled(target) {
        var widgets = target.querySelectorAll(
            'input.signatur-field[data-required="1"], .signatur-field-photo[data-required="1"]'
        );
        var missing = false;

        for (var i = 0; i < widgets.length; i++) {
            if (!widgetHasValue(widgets[i])) {
                missing = true;
                break;
            }
        }

        if (missing) {
            // A blocked copy is itself an interaction - light up the feil
            // marker on every required widget that still needs filling.
            Array.prototype.forEach.call(widgets, function (w) {
                w.signaturTouched = true;
                updateFieldStatus(w);
            });
            positionFieldStatuses();

            window.alert(
                (window.signaturFrontend && signaturFrontend.i18n && signaturFrontend.i18n.requiredFields)
                    || 'Please fill in all required fields before copying.'
            );

            return false;
        }

        return true;
    }

    /**
     * Copy the signature's rendered markup to the clipboard so it can be
     * pasted, with formatting intact, into an email client. Prefers the
     * async Clipboard API (needs a secure context); falls back to a DOM
     * range selection + execCommand('copy'), which also carries rich HTML
     * and works on plain http.
     */
    function copySignature(target, onDone) {
        if (!requiredFieldsFilled(target)) {
            return;
        }

        // TEMP: while testing Outlook rendering, copy only the <table> so
        // the paste is a clean table with no wrapper / title block. Revert
        // `source` to `target` and use innerHTML / selectNodeContents to go
        // back to copying the whole signature block.
        var source = target.querySelector('table') || target;
        // Work on a detached clone so baking the widgets into plain markup
        // never touches what the visitor sees on the page.
        var scope = source.cloneNode(true);

        bakeFieldInputs(scope);

        var html = scope.outerHTML;
        var text = scope.textContent || '';

        if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
            var item = new window.ClipboardItem({
                'text/html': new Blob([html], { type: 'text/html' }),
                'text/plain': new Blob([text], { type: 'text/plain' })
            });

            navigator.clipboard.write([item]).then(onDone, function () {
                fallbackCopy(scope, onDone);
            });

            return;
        }

        fallbackCopy(scope, onDone);
    }

    function fallbackCopy(scope, onDone) {
        // execCommand('copy') needs the node in the document and selectable,
        // and `scope` here is a detached clone - park it off-screen.
        var holder = document.createElement('div');

        holder.style.position = 'fixed';
        holder.style.left = '-9999px';
        holder.style.top = '0';
        holder.appendChild(scope);
        document.body.appendChild(holder);

        var selection = window.getSelection();
        var range = document.createRange();

        range.selectNode(scope);
        selection.removeAllRanges();
        selection.addRange(range);

        var ok = false;

        try {
            ok = document.execCommand('copy');
        } catch (err) {
            ok = false;
        }

        selection.removeAllRanges();
        document.body.removeChild(holder);

        if (ok) {
            onDone();
        }
    }

    /**
     * Mark empty text fields so frontend.css can show a blinking caret hint
     * on them (see .signatur-field-empty). Toggled on load and on every
     * keystroke.
     */
    function markFieldEmpty(input) {
        input.classList.toggle('signatur-field-empty', '' === input.value.trim());
    }

    Array.prototype.forEach.call(document.querySelectorAll('input.signatur-field'), markFieldEmpty);

    /**
     * Read a picked photo file into a label's preview <img>, downscaling
     * anything wider/taller than 512px so the copied signature stays a
     * sane size.
     */
    function loadPhotoIntoLabel(label, file) {
        var preview = photoImg(label);

        if (!preview) {
            return;
        }

        var reader = new FileReader();

        reader.onload = function () {
            var probe = new Image();

            var apply = function (src) {
                preview.src = src;
                label.classList.add('signatur-field-photo--has-photo');
                label.signaturTouched = true;
                refreshWidgetStatus(label);
            };

            probe.onload = function () {
                apply(downscaleDataUrl(probe, reader.result, 'image/png' === file.type ? 'image/png' : 'image/jpeg'));
            };

            probe.onerror = function () {
                apply(reader.result);
            };

            probe.src = reader.result;
        };

        reader.readAsDataURL(file);
    }

    function downscaleDataUrl(img, original, mime) {
        var max = 512;
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;

        if (!w || !h || (w <= max && h <= max)) {
            return original;
        }

        var scale = max / Math.max(w, h);
        var canvas = document.createElement('canvas');

        canvas.width = Math.round(w * scale);
        canvas.height = Math.round(h * scale);

        try {
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

            return canvas.toDataURL(mime, 0.85);
        } catch (err) {
            return original;
        }
    }

    document.addEventListener('change', function (e) {
        var input = e.target;

        if (!input || 'file' !== input.type || !input.files || !input.files[0]) {
            return;
        }

        var label = input.closest && input.closest('.signatur-field-photo');

        if (label) {
            loadPhotoIntoLabel(label, input.files[0]);
        }
    });

    /**
     * A per-widget validity marker (icon-ok.svg / icon-feil.svg) shown just
     * outside the table, level with each fillable widget. It lives on
     * .signatur-signature (never inside the <table>), so it is a page-only
     * affordance and is never copied.
     */
    var statusIcons = (window.signaturFrontend && signaturFrontend.statusIcons) || null;

    function updateFieldStatus(w) {
        var icon = w.signaturStatusIcon;

        if (!icon || !statusIcons) {
            return;
        }

        var required = '1' === w.getAttribute('data-required');

        // No marker until there is something to say: a value (ok / feil by
        // validity), or an empty required widget the visitor has already
        // interacted with (see markWidgetTouched()). An untouched empty
        // field - required or not - stays unmarked on load.
        if (!widgetHasValue(w) && !(required && w.signaturTouched)) {
            icon.hidden = true;
            return;
        }

        icon.hidden = false;
        icon.src = widgetIsValid(w) ? statusIcons.ok : statusIcons.feil;
    }

    function markWidgetTouched(w) {
        w.signaturTouched = true;
        updateFieldStatus(w);
    }

    function refreshWidgetStatus(w) {
        updateFieldStatus(w);
        positionFieldStatuses();
    }

    function forEachWidget(root, fn) {
        Array.prototype.forEach.call(
            root.querySelectorAll('input.signatur-field, .signatur-field-photo'),
            fn
        );
    }

    function positionFieldStatuses() {
        Array.prototype.forEach.call(document.querySelectorAll('.signatur-signature'), function (sig) {
            var table = sig.querySelector('table');

            if (!table) {
                return;
            }

            var sigRect = sig.getBoundingClientRect();
            var tableRight = table.getBoundingClientRect().right - sigRect.left + 8;

            forEachWidget(sig, function (w) {
                if (!w.signaturStatusIcon) {
                    return;
                }

                var r = w.getBoundingClientRect();

                w.signaturStatusIcon.style.left = tableRight + 'px';
                w.signaturStatusIcon.style.top = (r.top - sigRect.top + (r.height - 18) / 2) + 'px';
            });
        });
    }

    if (statusIcons) {
        Array.prototype.forEach.call(document.querySelectorAll('.signatur-signature'), function (sig) {
            forEachWidget(sig, function (w) {
                var icon = document.createElement('img');

                icon.className = 'signatur-field-status';
                icon.alt = '';
                w.signaturStatusIcon = icon;
                sig.appendChild(icon);
                updateFieldStatus(w);
            });
        });

        positionFieldStatuses();
        window.addEventListener('resize', positionFieldStatuses);
        window.addEventListener('load', positionFieldStatuses);
    }

    document.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('signatur-field')) {
            e.target.signaturTouched = true;
            markFieldEmpty(e.target);
            updateFieldStatus(e.target);
        }
    });

    // Leaving a field (even empty) counts as an interaction, so its feil
    // marker may then appear.
    document.addEventListener('focusout', function (e) {
        if (e.target.classList && e.target.classList.contains('signatur-field')) {
            markWidgetTouched(e.target);
        }
    });

    // Opening the file picker for a photo field is an interaction too.
    document.addEventListener('click', function (e) {
        var label = e.target.closest && e.target.closest('.signatur-field-photo');

        if (label) {
            markWidgetTouched(label);
        }
    });

    document.addEventListener('click', function (e) {
        var button = e.target.closest('.signatur-copy-button');

        if (!button) {
            return;
        }

        var target = document.getElementById(button.getAttribute('data-target'));

        if (!target) {
            return;
        }

        copySignature(target, function () {
            // Visual-only feedback (dimmed via .is-copied in frontend.css) -
            // the label text never changes, so the button doesn't
            // resize/jump when copying succeeds.
            button.classList.add('is-copied');

            window.setTimeout(function () {
                button.classList.remove('is-copied');
            }, 2000);
        });
    });
})();
