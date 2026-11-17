/**
 * The editor's help (includes/help.php): one floating tip, shown at once on
 * hover or keyboard focus of an "i" ([data-signatur-tip]) or of an icon
 * button named in signaturTips.buttons (CSS selector => text; their short
 * title is dropped then, so the browser's own slow tooltip does not show
 * too). The tip lives in the page body, above the modal, kept on screen.
 *
 * Also: the instructions' modals, with Copy (signatur_render_help()).
 */
(function () {
    'use strict';

    var boot = window.signaturTips || {};
    var buttons = boot.buttons || {};
    var selectors = Object.keys(buttons);
    var bubble = null;
    var shownFor = null;

    // The text for an element under the pointer / in focus, and the element it belongs to.
    function tipFor(node) {
        if (!node || !node.closest) {
            return null;
        }

        var own = node.closest('[data-signatur-tip]');

        if (own) {
            return { el: own, text: own.getAttribute('data-signatur-tip') };
        }

        for (var i = 0; i < selectors.length; i++) {
            var el = node.closest(selectors[i]);

            if (el) {
                return { el: el, text: buttons[selectors[i]] };
            }
        }

        return null;
    }

    function show(tip) {
        if (!bubble) {
            bubble = document.createElement('div');
            bubble.className = 'signatur-tip-bubble';
            bubble.setAttribute('role', 'tooltip');
            bubble.id = 'signatur_tip_bubble';
            document.body.appendChild(bubble);
        }

        // Its own short title would show as well, later.
        if (tip.el.hasAttribute('title')) {
            tip.el.removeAttribute('title');
        }

        shownFor = tip.el;
        bubble.textContent = tip.text;
        bubble.style.display = 'block';
        tip.el.setAttribute('aria-describedby', bubble.id);

        // Under the element, its left edge; above it where there is no room; always on screen.
        var r = tip.el.getBoundingClientRect();
        var b = bubble.getBoundingClientRect();
        var top = r.bottom + 6;
        var left = Math.min(Math.max(8, r.left - 4), window.innerWidth - b.width - 8);

        if (top + b.height > window.innerHeight - 8) {
            top = Math.max(8, r.top - b.height - 6);
        }

        bubble.style.top = (top + window.scrollY) + 'px';
        bubble.style.left = (left + window.scrollX) + 'px';
    }

    function hide() {
        if (bubble) {
            bubble.style.display = 'none';
        }

        if (shownFor) {
            shownFor.removeAttribute('aria-describedby');
        }

        shownFor = null;
    }

    document.addEventListener('mouseover', function (e) {
        var tip = tipFor(e.target);

        if (!tip) {
            if (shownFor && !shownFor.contains(e.target)) {
                hide();
            }

            return;
        }

        if (tip.el !== shownFor) {
            show(tip);
        }
    });

    document.addEventListener('focusin', function (e) {
        var tip = tipFor(e.target);

        if (tip) {
            show(tip);
        } else {
            hide();
        }
    });

    // An "i" inside a checkbox's label: a click on it does not tick the box.
    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('.signatur-tip')) {
            e.preventDefault();
        }
    });

    document.addEventListener('focusout', hide);
    document.addEventListener('mousedown', hide);
    window.addEventListener('scroll', hide, true);
    document.addEventListener('keydown', function (e) {
        if ('Escape' === e.key) {
            hide();
        }
    });

    /*
     * The instructions (signatur_render_help()): a button opens its <dialog>
     * (Esc, Close or a click beside it closes it); Copy puts its text on the
     * clipboard, as HTML (the lists kept) and as plain text.
     */
    document.addEventListener('click', function (e) {
        var open = e.target.closest && e.target.closest('[data-signatur-help]');

        if (open) {
            var dialog = document.getElementById(open.getAttribute('data-signatur-help'));

            if (dialog && dialog.showModal && !dialog.open) {
                hide();
                dialog.showModal();
            }

            return;
        }

        var close = e.target.closest && e.target.closest('[data-signatur-help-close]');

        if (close) {
            close.closest('dialog').close();

            return;
        }

        // A click on the backdrop lands on the <dialog> itself.
        if (e.target.matches && e.target.matches('dialog.signatur-help-dialog')) {
            var r = e.target.getBoundingClientRect();

            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                e.target.close();
            }

            return;
        }

        var copy = e.target.closest && e.target.closest('[data-signatur-help-copy]');

        if (copy) {
            copyInstructions(copy.closest('dialog'), copy);
        }
    });

    function copied(dialog, button) {
        var status = dialog.querySelector('.signatur-help-dialog__copied');

        if (status) {
            status.textContent = button.getAttribute('data-copied') || 'Copied';
            window.setTimeout(function () {
                status.textContent = '';
            }, 2000);
        }
    }

    function copyInstructions(dialog, button) {
        var content = dialog.querySelector('[data-signatur-help-content]');

        if (!content) {
            return;
        }

        var html = content.innerHTML.trim();
        var text = content.innerText.trim();

        if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
            navigator.clipboard.write([new window.ClipboardItem({
                'text/html': new Blob([html], { type: 'text/html' }),
                'text/plain': new Blob([text], { type: 'text/plain' })
            })]).then(function () {
                copied(dialog, button);
            }, function () {
                fallbackCopy(content, dialog, button);
            });

            return;
        }

        fallbackCopy(content, dialog, button);
    }

    // Without the Clipboard API (plain http): the box selected and copied.
    function fallbackCopy(content, dialog, button) {
        var selection = window.getSelection();
        var range = document.createRange();

        range.selectNodeContents(content);
        selection.removeAllRanges();
        selection.addRange(range);

        try {
            if (document.execCommand('copy')) {
                copied(dialog, button);
            }
        } catch (err) { /* Nothing copied: the text stays selected to copy by hand. */ }

        selection.removeAllRanges();
    }
})();
