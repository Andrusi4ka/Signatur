(function () {
    'use strict';

    /**
     * Copy the signature's rendered markup to the clipboard so it can be
     * pasted, with formatting intact, into an email client. Prefers the
     * async Clipboard API (needs a secure context); falls back to a DOM
     * range selection + execCommand('copy'), which also carries rich HTML
     * and works on plain http.
     */
    function copySignature(target, onDone) {
        // TEMP: while testing Outlook rendering, copy only the <table> so
        // the paste is a clean table with no wrapper / title block. Revert
        // `scope` to `target` and use innerHTML / selectNodeContents to go
        // back to copying the whole signature block.
        var scope = target.querySelector('table') || target;
        var html = scope.outerHTML;
        var text = scope.innerText || scope.textContent || '';

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
