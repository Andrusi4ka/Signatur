(function () {
    'use strict';

    /**
     * Copy the signature's rendered markup to the clipboard so it can be
     * pasted, with formatting intact, into an email client. Prefers the
     * async Clipboard API (needs a secure context); falls back to a DOM
     * range selection + execCommand('copy'), which also carries rich HTML
     * and works on plain http.
     */
    /**
     * Turn every fillable field (<input class="signatur-field">) inside a
     * cloned signature table into a plain <span> holding what the visitor
     * typed, carrying over just the text styling. The email client only
     * ever sees text, never a form control.
     */
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

            // The cell carries a pale-yellow fill to flag it as fillable -
            // that's a page-only hint, never part of the copied signature.
            if (input.parentNode && input.parentNode.style) {
                input.parentNode.style.backgroundColor = '';
            }

            input.parentNode.replaceChild(node, input);
        });
    }

    /**
     * True when every required fillable field has a value. Alerts and
     * returns false on the first empty one.
     */
    function requiredFieldsFilled(target) {
        var required = target.querySelectorAll('input.signatur-field[data-required="1"]');

        for (var i = 0; i < required.length; i++) {
            if ('' === required[i].value.trim()) {
                window.alert(
                    (window.signaturFrontend && signaturFrontend.i18n && signaturFrontend.i18n.requiredFields)
                        || 'Please fill in all required fields before copying.'
                );

                return false;
            }
        }

        return true;
    }

    function copySignature(target, onDone) {
        if (!requiredFieldsFilled(target)) {
            return;
        }

        // TEMP: while testing Outlook rendering, copy only the <table> so
        // the paste is a clean table with no wrapper / title block. Revert
        // `source` to `target` and use innerHTML / selectNodeContents to go
        // back to copying the whole signature block.
        var source = target.querySelector('table') || target;
        // Work on a detached clone so baking the field inputs into text
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
     * Mark empty fillable fields so frontend.css can show a blinking caret
     * hint on them (see .signatur-field-empty). Toggled on load and on
     * every keystroke.
     */
    function markFieldEmpty(input) {
        input.classList.toggle('signatur-field-empty', '' === input.value.trim());
    }

    Array.prototype.forEach.call(document.querySelectorAll('.signatur-field'), markFieldEmpty);

    /**
     * A per-field validity marker (icon-ok.svg / icon-feil.svg) shown just
     * outside the table, level with each fillable field. It lives on
     * .signatur-signature (never inside the <table>), so it is a page-only
     * affordance and is never copied.
     */
    var statusIcons = (window.signaturFrontend && signaturFrontend.statusIcons) || null;

    function fieldIsValid(input) {
        var value = input.value.trim();

        if ('' === value) {
            return false;
        }

        if ('tel' === input.getAttribute('type')) {
            return /\d/.test(value);
        }

        return input.checkValidity();
    }

    function updateFieldStatus(input) {
        var icon = input.signaturStatusIcon;

        if (!icon || !statusIcons) {
            return;
        }

        // An empty optional field gets no marker at all - the feil icon
        // only shows for something the visitor still has to fix: an empty
        // required field, or a filled-in but invalid one.
        if ('' === input.value.trim() && '1' !== input.getAttribute('data-required')) {
            icon.hidden = true;
            return;
        }

        icon.hidden = false;
        icon.src = fieldIsValid(input) ? statusIcons.ok : statusIcons.feil;
    }

    function positionFieldStatuses() {
        Array.prototype.forEach.call(document.querySelectorAll('.signatur-signature'), function (sig) {
            var table = sig.querySelector('table');

            if (!table) {
                return;
            }

            var sigRect = sig.getBoundingClientRect();
            var tableRight = table.getBoundingClientRect().right - sigRect.left + 8;

            Array.prototype.forEach.call(sig.querySelectorAll('input.signatur-field'), function (input) {
                if (!input.signaturStatusIcon) {
                    return;
                }

                var r = input.getBoundingClientRect();

                input.signaturStatusIcon.style.left = tableRight + 'px';
                input.signaturStatusIcon.style.top = (r.top - sigRect.top + (r.height - 18) / 2) + 'px';
            });
        });
    }

    if (statusIcons) {
        Array.prototype.forEach.call(document.querySelectorAll('.signatur-signature'), function (sig) {
            Array.prototype.forEach.call(sig.querySelectorAll('input.signatur-field'), function (input) {
                var icon = document.createElement('img');

                icon.className = 'signatur-field-status';
                icon.alt = '';
                input.signaturStatusIcon = icon;
                sig.appendChild(icon);
                updateFieldStatus(input);
            });
        });

        positionFieldStatuses();
        window.addEventListener('resize', positionFieldStatuses);
        window.addEventListener('load', positionFieldStatuses);
    }

    document.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('signatur-field')) {
            markFieldEmpty(e.target);
            updateFieldStatus(e.target);
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
            // Visual-only feedback (dimmed + outlined via .is-copied in
            // frontend.css) - the label text never changes, so the button
            // doesn't resize/jump when copying succeeds.
            button.classList.add('is-copied');

            window.setTimeout(function () {
                button.classList.remove('is-copied');
            }, 2000);
        });
    });
})();
