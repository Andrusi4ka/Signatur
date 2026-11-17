/**
 * The Padding / Margin box (includes/admin-pages.php
 * signatur_render_spacing_box()), as WP Banner's: the link in the middle
 * sets the four sides together; each ring's sides are drawn as wide as the
 * spaces set. builder.js fills and reads the fields by their ids as before
 * (populatePaddingInputs() / readPaddingInputs()); this file only adds the
 * box's behaviour, so a box shown in a modal is set up once its values are
 * in (sync(), from a MutationObserver: builder.js fills a modal right after
 * putting it in the page, in the same task).
 */
(function () {
    'use strict';

    var SIDES = ['top', 'right', 'bottom', 'left'];

    function fields(box, kind) {
        return SIDES.map(function (side) {
            return box.querySelector('[data-spacing-kind="' + kind + '"][data-spacing-side="' + side + '"]');
        });
    }

    function linkOf(box, kind) {
        return box.querySelector('[data-spacing-link="' + kind + '"]');
    }

    function kinds(box) {
        return Array.prototype.map.call(box.querySelectorAll('[data-spacing-link]'), function (link) {
            return link.getAttribute('data-spacing-link');
        });
    }

    // The ring's sides: 2-6px as the space is, none for 0.
    function draw(box) {
        kinds(box).forEach(function (kind) {
            var ring = box.querySelector('.signatur-spacing__ring--' + kind);

            if (!ring) {
                return;
            }

            fields(box, kind).forEach(function (input, i) {
                var px = Math.max(0, parseInt(input.value, 10) || 0);
                var side = SIDES[i];

                ring.style['border' + side[0].toUpperCase() + side.slice(1) + 'Width'] = (px ? Math.min(6, Math.max(2, px / 8)) : 0) + 'px';
            });
        });
    }

    function setLinked(link, on) {
        link.setAttribute('aria-pressed', on ? 'true' : 'false');
    }

    // A box as filled: linked where its four sides are the same, rings drawn.
    function sync(box) {
        kinds(box).forEach(function (kind) {
            var values = fields(box, kind).map(function (input) {
                return String(parseInt(input.value, 10) || 0);
            });

            setLinked(linkOf(box, kind), values.every(function (v) {
                return v === values[0];
            }));
        });
        draw(box);
    }

    function syncWithin(root) {
        if (root.nodeType !== 1) {
            return;
        }

        if (root.matches('.signatur-spacing')) {
            sync(root);
        }

        Array.prototype.forEach.call(root.querySelectorAll('.signatur-spacing'), sync);
    }

    // Typed into a linked side: the other three take it.
    document.addEventListener('input', function (e) {
        var input = e.target;
        var kind = input.getAttribute && input.getAttribute('data-spacing-kind');
        var box = kind && input.closest('.signatur-spacing');

        if (!box) {
            return;
        }

        if ('true' === linkOf(box, kind).getAttribute('aria-pressed')) {
            fields(box, kind).forEach(function (other) {
                if (other !== input) {
                    other.value = input.value;
                }
            });
        }

        draw(box);
    });

    // The link: on, the four take the first side's value (or the first one set).
    document.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('[data-spacing-link]');

        if (!link) {
            return;
        }

        var box = link.closest('.signatur-spacing');
        var kind = link.getAttribute('data-spacing-link');
        var on = 'true' !== link.getAttribute('aria-pressed');

        setLinked(link, on);

        if (on) {
            var inputs = fields(box, kind);
            var value = '';

            inputs.some(function (input) {
                if ('' !== input.value) {
                    value = input.value;

                    return true;
                }

                return false;
            });

            inputs.forEach(function (input) {
                input.value = value;
            });
            draw(box);
        }
    });

    if ('MutationObserver' in window) {
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                Array.prototype.forEach.call(record.addedNodes, syncWithin);
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    syncWithin(document.body);

    window.signaturSpacing = { sync: sync };
})();
