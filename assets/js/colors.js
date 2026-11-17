/**
 * The editor's colours as WP Banner's (its admin-color.js): a swatch that
 * opens the Pickr picker with the palette (the theme's colours, then black,
 * white and greys: includes/palette.php), Default and, where the browser has
 * one, the eyedropper. Colours are plain #rrggbb: Outlook does not take
 * transparency, and the plugin saves only #rrggbb.
 *
 * Every <input type="color"> of the editor becomes such a swatch as it
 * shows (the modals are built from templates when opened): the input stays,
 * hidden, with its id and value, so builder.js reads and fills it as
 * before; a colour chosen is written into it with an "input" event, as the
 * native picker did.
 */
(function () {
    'use strict';

    var boot = window.signaturColors || {};
    var PALETTE = (Array.isArray(boot.palette) ? boot.palette : []).filter(function (entry) {
        return entry && /^#[0-9a-f]{6}$/.test(entry.color);
    });
    var T = boot.i18n || {};
    var picker = null;

    function t(key, fallback) {
        return T[key] || fallback;
    }

    // "#rgb" or "#rrggbb" as "#rrggbb"; '' for anything else.
    function hex(value) {
        value = String(value || '').trim().toLowerCase();

        if (/^#[0-9a-f]{3}$/.test(value)) {
            value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
        }

        return /^#[0-9a-f]{6}$/.test(value) ? value : '';
    }

    // The pipette (WP Banner's icon).
    var EYEDROPPER = '<svg viewBox="314 314 380 380" aria-hidden="true" focusable="false" fill="currentColor">'
        + '<rect x="480.78" y="406.64" width="177.31" height="63.87" rx="31.93" ry="31.93" transform="translate(476.9 -274.19) rotate(45)"/>'
        + '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-miterlimit="10" stroke-width="20" d="M621.5,373.23c7.35-7.35,19.25-7.35,26.6,0"/>'
        + '<path d="M653.93,354.08c13.82,13.82,13.82,36.32,0,50.15l-194.31,194.31c-8.98,8.98-22.19,12.47-34.48,9.11l-11.21-3.06-57.7,57.7c-2.72,2.72-7.13,2.72-9.84,0-3.08-3.08-3.08-8.1,0-11.17l57.04-57.04-3.06-11.21c-3.37-12.29.13-25.5,9.11-34.48l194.31-194.31c13.82-13.82,36.32-13.82,50.15,0M668.07,339.94h0c-21.66-21.66-56.77-21.66-78.43,0l-194.31,194.31c-14.6,14.6-19.35,35.31-14.26,53.9l-48.82,48.82c-10.9,10.9-10.9,28.56,0,39.46h0c10.53,10.53,27.6,10.53,38.13,0l49.48-49.48c18.59,5.08,39.3.34,53.9-14.26l194.31-194.31c21.66-21.66,21.66-56.77,0-78.43h0Z"/>'
        + '</svg>';

    function chip(box) {
        var input = box.querySelector('input');
        var color = hex(input.value) || hex(box.getAttribute('data-default')) || '#000000';

        box.querySelector('.signatur-color__chip').style.boxShadow = 'inset 0 0 0 60px ' + color;
    }

    function closePicker() {
        if (!picker) {
            return;
        }

        var root = picker.getRoot();

        picker.destroy();

        if (root && root.app) {
            root.app.remove();
        }

        if (picker.signaturButton) {
            picker.signaturButton.setAttribute('aria-expanded', 'false');
        }

        picker = null;
    }

    function openPicker(button) {
        if (!window.Pickr) {
            return;
        }

        if (picker && picker.signaturButton === button) {
            return;
        }

        closePicker();

        var box = button.closest('.signatur-color');
        var input = box.querySelector('input');
        var syncing = false;
        var opened = input.value;

        var now = function () {
            return hex(input.value) || hex(box.getAttribute('data-default')) || '#000000';
        };

        // What is chosen: into the hidden input (builder.js takes it as the native picker's).
        var store = function (value) {
            if (input.value === value) {
                return;
            }

            input.value = value;
            chip(box);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        // Into Pickr (no event of its own).
        var show = function () {
            syncing = true;
            picker.setColor(now(), true);
            syncing = false;
            picker.applyColor(true);
        };

        var fromPickr = function (color) {
            if (!color || syncing) {
                return;
            }

            var rgba = color.toRGBA();
            var value = '#' + [0, 1, 2].map(function (i) {
                return ('0' + Math.max(0, Math.min(255, Math.round(rgba[i]))).toString(16)).slice(-2);
            }).join('');

            if (value !== now()) {
                store(value);
            }
        };

        picker = window.Pickr.create({
            el: button,
            useAsButton: true,
            theme: 'classic',
            appClass: 'signatur-pcr',
            position: 'bottom-start',
            default: now(),
            defaultRepresentation: 'HEX',
            lockOpacity: true,
            swatches: PALETTE.map(function (entry) {
                return entry.color;
            }),
            i18n: {
                'ui:dialog': t('dialog', 'Color picker'),
                'btn:toggle': t('toggle', 'Choose a color'),
                'btn:swatch': t('swatch', 'Color'),
                'btn:cancel': t('cancel', 'Cancel'),
                'aria:btn:cancel': t('cancelHelp', 'Back to the color it had when opened'),
                'aria:input': t('value', 'Color value'),
                'aria:palette': t('palette', 'Color and brightness'),
                'aria:hue': t('hue', 'Hue'),
                'aria:opacity': t('opacity', 'Opacity')
            },
            components: {
                preview: false,
                opacity: false,
                hue: true,
                interaction: { hex: true, rgba: true, hsla: true, input: true, cancel: true }
            }
        });
        picker.signaturButton = button;

        // Near the bottom of the screen Pickr would open below it, cut off: then above the swatch.
        var place = picker._rePositioningPicker;

        if ('function' === typeof place) {
            picker._rePositioningPicker = function () {
                place.apply(this, arguments);

                var app = this._root && this._root.app;

                if (!app) {
                    return;
                }

                var r = app.getBoundingClientRect();
                var b = button.getBoundingClientRect();

                if (r.bottom > window.innerHeight - 8 && b.top - r.height - 8 >= 8) {
                    app.style.top = (b.top - r.height - 8) + 'px';
                }
            };
        }

        picker.on('init', function (instance) {
            var root = instance.getRoot().app;

            // The palette's names on hover.
            Array.prototype.forEach.call(root.querySelectorAll('.pcr-swatches > button'), function (swatch, i) {
                if (PALETTE[i]) {
                    swatch.title = PALETTE[i].name;
                    swatch.setAttribute('aria-label', PALETTE[i].name);
                }
            });

            // Default: the colour the field starts with.
            var reset = document.createElement('input');

            reset.type = 'button';
            reset.className = 'pcr-clear signatur-pcr__default';
            reset.value = t('default', 'Default');
            reset.setAttribute('aria-label', t('defaultHelp', 'Back to the default color'));
            reset.addEventListener('click', function () {
                store(hex(box.getAttribute('data-default')) || '#000000');
                show();
            });
            root.querySelector('.pcr-interaction').appendChild(reset);

            // The eyedropper, where the browser has one (Chrome, Edge, Opera).
            if (window.EyeDropper) {
                var drop = document.createElement('button');
                var line = document.createElement('span');
                var result = root.querySelector('.pcr-result');

                drop.type = 'button';
                drop.className = 'signatur-pcr__dropper';
                drop.title = t('dropper', 'Pick a color from the screen');
                drop.setAttribute('aria-label', drop.title);
                drop.innerHTML = EYEDROPPER;
                line.className = 'signatur-pcr__break';
                drop.addEventListener('click', function () {
                    new window.EyeDropper().open().then(function (picked) {
                        var v = String(picked && picked.sRGBHex || '');
                        var m = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(v);
                        var c = m ? '#' + [m[1], m[2], m[3]].map(function (x) {
                            return ('0' + Math.min(255, Number(x)).toString(16)).slice(-2);
                        }).join('') : hex(v);

                        if (c) {
                            store(c);
                            show();
                        }
                    }).catch(function () { /* Esc: nothing changes. */ });
                });
                result.parentNode.insertBefore(drop, result.nextSibling);
                drop.parentNode.insertBefore(line, drop.nextSibling);
            }

            instance.show();
        });
        picker.on('show', function () {
            opened = input.value;
            show();
            button.setAttribute('aria-expanded', 'true');
        });
        picker.on('hide', function () {
            button.setAttribute('aria-expanded', 'false');
        });
        picker.on('change', fromPickr);
        // Cancel: the colour it had when opened.
        picker.on('cancel', function (instance) {
            store(opened);
            instance.hide();
        });
    }

    var count = 0;

    // A native colour input as a swatch (its label now names the swatch).
    function upgrade(input) {
        if (input.getAttribute('data-signatur-color')) {
            return;
        }

        var box = document.createElement('span');
        var button = document.createElement('button');
        var label = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;

        box.className = 'signatur-color';
        box.setAttribute('data-default', hex(input.getAttribute('value')) || '#000000');
        input.parentNode.insertBefore(box, input);
        input.setAttribute('data-signatur-color', '1');
        input.type = 'hidden';
        box.appendChild(input);

        button.type = 'button';
        button.className = 'signatur-color__swatch';
        button.id = (input.id || 'signatur_color_' + (++count)) + '_swatch';
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', label ? label.textContent.trim() : (input.getAttribute('aria-label') || t('swatch', 'Color')));
        button.innerHTML = '<span class="signatur-color__chip" aria-hidden="true"></span>';
        box.appendChild(button);

        if (label) {
            label.setAttribute('for', button.id);
        }

        chip(box);
    }

    function upgradeWithin(root) {
        if (root.nodeType !== 1) {
            return;
        }

        if (root.matches('input[type="color"]')) {
            upgrade(root);
        }

        Array.prototype.forEach.call(root.querySelectorAll('input[type="color"]'), upgrade);
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest && e.target.closest('.signatur-color__swatch');

        if (button) {
            openPicker(button);
        }
    });

    // A value set by builder.js (Clear, a modal filled): the swatch follows.
    document.addEventListener('input', function (e) {
        var box = e.target.closest && e.target.closest('.signatur-color');

        if (box) {
            chip(box);
        }
    });

    if ('MutationObserver' in window) {
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                Array.prototype.forEach.call(record.addedNodes, upgradeWithin);
            });

            // A modal closed or rebuilt with the picker open: close it too.
            if (picker && picker.signaturButton && !document.body.contains(picker.signaturButton)) {
                closePicker();
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    upgradeWithin(document.body);
})();
