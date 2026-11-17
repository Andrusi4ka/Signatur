/**
 * The editor's help (includes/help.php): one floating tip, shown at once on
 * hover or keyboard focus of an "i" ([data-signatur-tip]) or of an icon
 * button named in signaturTips.buttons (CSS selector => text; their short
 * title is dropped then, so the browser's own slow tooltip does not show
 * too). The tip lives in the page body, above the modal, kept on screen.
 *
 * Also: "Getting started" stays closed once closed (this browser).
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

    // "Getting started": closed once, closed after (a per-browser convenience).
    var guide = document.getElementById('signatur_getting_started');
    var KEY = 'signaturGuideClosed';

    if (guide) {
        try {
            if ('1' === window.localStorage.getItem(KEY)) {
                guide.open = false;
            }
        } catch (err) { /* No storage: it stays open. */ }

        guide.addEventListener('toggle', function () {
            try {
                window.localStorage.setItem(KEY, guide.open ? '0' : '1');
            } catch (err) { /* Nothing kept. */ }
        });
    }
})();
