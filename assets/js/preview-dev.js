/**
 * Developer mode of the preview (includes/preview.php, its frame only),
 * as WP Banner's: over the signature, each cell's box with its size, its
 * padding (green) with each side's px, the whole signature's width, and the
 * "Copy signature" button's margin (orange) with its px. Drawn from what
 * the browser lays out, so it is what the page shows; redrawn when the
 * page changes size (a photo chosen, a field typed into).
 * preview.js turns it on and off: window.signaturDev(true | false).
 */
(function () {
    'use strict';

    var on = false;
    var layer = null;

    function px(value) {
        return Math.round(parseFloat(value) || 0);
    }

    function add(className, rect, label) {
        var node = document.createElement('div');

        node.className = 'signatur-dev__' + className;
        node.style.left = (rect.left + window.scrollX) + 'px';
        node.style.top = (rect.top + window.scrollY) + 'px';
        node.style.width = Math.max(0, rect.width) + 'px';
        node.style.height = Math.max(0, rect.height) + 'px';

        if (label) {
            var tag = document.createElement('span');

            tag.className = 'signatur-dev__label';
            tag.textContent = label;
            node.appendChild(tag);
        }

        layer.appendChild(node);
    }

    // The four bands of a box's padding or margin, each with its px.
    function bands(kind, outer, sizes) {
        var sides = {
            top: { left: outer.left, top: outer.top, width: outer.width, height: sizes.top },
            bottom: { left: outer.left, top: outer.bottom - sizes.bottom, width: outer.width, height: sizes.bottom },
            left: { left: outer.left, top: outer.top + sizes.top, width: sizes.left, height: outer.height - sizes.top - sizes.bottom },
            right: { left: outer.right - sizes.right, top: outer.top + sizes.top, width: sizes.right, height: outer.height - sizes.top - sizes.bottom }
        };

        Object.keys(sides).forEach(function (side) {
            if (sizes[side] > 0) {
                add(kind + ' signatur-dev__' + kind + '--' + side, sides[side], String(sizes[side]));
            }
        });
    }

    function draw() {
        if (layer) {
            layer.remove();
            layer = null;
        }

        if (!on) {
            return;
        }

        layer = document.createElement('div');
        layer.className = 'signatur-dev';
        document.body.appendChild(layer);

        var table = document.querySelector('.signatur-signature table');

        if (table) {
            var t = table.getBoundingClientRect();

            add('table', t, Math.round(t.width) + ' px');

            Array.prototype.forEach.call(table.querySelectorAll('td'), function (cell) {
                var r = cell.getBoundingClientRect();
                var s = window.getComputedStyle(cell);

                bands('padding', r, {
                    top: px(s.paddingTop),
                    right: px(s.paddingRight),
                    bottom: px(s.paddingBottom),
                    left: px(s.paddingLeft)
                });
                add('cell', r, Math.round(r.width) + ' × ' + Math.round(r.height));
            });
        }

        var button = document.querySelector('.signatur-copy-button');

        if (button) {
            var b = button.getBoundingClientRect();
            var m = window.getComputedStyle(button);
            var margin = {
                top: px(m.marginTop),
                right: px(m.marginRight),
                bottom: px(m.marginBottom),
                left: px(m.marginLeft)
            };

            bands('margin', {
                left: b.left - margin.left,
                top: b.top - margin.top,
                right: b.right + margin.right,
                bottom: b.bottom + margin.bottom,
                width: b.width + margin.left + margin.right,
                height: b.height + margin.top + margin.bottom
            }, margin);
            add('button', b, Math.round(b.width) + ' × ' + Math.round(b.height));
        }
    }

    window.signaturDev = function (value) {
        on = !!value;
        document.documentElement.classList.toggle('signatur-dev-on', on);
        draw();
    };

    if ('ResizeObserver' in window) {
        new ResizeObserver(function () {
            if (on) {
                draw();
            }
        }).observe(document.body);
    }

    window.addEventListener('resize', function () {
        if (on) {
            draw();
        }
    });
})();
