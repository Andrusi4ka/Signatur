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
    function bakeFieldInputs(scope) {
        Array.prototype.forEach.call(scope.querySelectorAll('input.signatur-field'), function (input) {
            var span = document.createElement('span');

            span.textContent = input.value.trim();
            span.style.fontFamily = input.style.fontFamily;
            span.style.fontSize = input.style.fontSize;
            span.style.color = input.style.color;
            span.style.fontWeight = input.style.fontWeight;
            span.style.fontStyle = input.style.fontStyle;
            span.style.textDecoration = input.style.textDecoration;

            input.parentNode.replaceChild(span, input);
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
