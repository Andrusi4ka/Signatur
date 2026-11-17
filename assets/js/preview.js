/**
 * The editor's preview (includes/preview.php): on Update (and once as the
 * screen opens) the editor's settings, saved or not (builder.js
 * window.signaturSerializeSettings()), go to admin-ajax signatur_preview,
 * and the page it sends is shown in the frame. The frame is as wide as the
 * screen chosen (Desktop / Tablet / Phone) and scaled down to the column,
 * as tall as its page. Developer turns on the frame's Developer mode
 * (preview-dev.js window.signaturDev()), kept across updates.
 */
(function () {
    'use strict';

    var boot = window.signaturPreview || {};
    var panel = document.getElementById('signatur_preview');
    var stage = document.getElementById('signatur_preview_stage');
    var frame = document.getElementById('signatur_preview_frame');
    var update = document.getElementById('signatur_preview_update');
    var devButton = document.getElementById('signatur_preview_dev');
    var note = document.getElementById('signatur_preview_note');

    if (!panel || !stage || !frame || !update) {
        return;
    }

    var width = 1280;
    var dev = false;
    var busy = false;
    var KEY = 'signaturPreviewDevice';
    var noteText = note ? note.textContent : '';

    // The frame at the screen's width, scaled to the column; as tall as its page.
    function fit() {
        var doc = frame.contentDocument;
        var height = doc && doc.documentElement ? Math.max(200, doc.documentElement.scrollHeight) : 300;
        var scale = Math.min(1, stage.clientWidth / width);

        frame.style.width = width + 'px';
        frame.style.height = height + 'px';
        frame.style.transform = 'scale(' + scale + ')';
        stage.style.height = Math.ceil(height * scale) + 'px';
    }

    function setDev() {
        var win = frame.contentWindow;

        devButton.setAttribute('aria-pressed', dev ? 'true' : 'false');

        if (win && 'function' === typeof win.signaturDev) {
            win.signaturDev(dev);
        }
    }

    function setDevice(button) {
        Array.prototype.forEach.call(panel.querySelectorAll('.signatur-preview__device'), function (b) {
            b.setAttribute('aria-pressed', b === button ? 'true' : 'false');
        });
        width = parseInt(button.getAttribute('data-width'), 10) || 1280;

        try {
            window.localStorage.setItem(KEY, button.getAttribute('data-device'));
        } catch (err) { /* Not kept. */ }

        fit();
        // Developer mode measures again at the new width.
        setDev();
    }

    function load() {
        if (busy || 'function' !== typeof window.signaturSerializeSettings) {
            return;
        }

        busy = true;
        update.disabled = true;
        panel.classList.add('is-loading');

        var data = new FormData();

        data.append('action', 'signatur_preview');
        data.append('nonce', boot.nonce || '');
        data.append('settings', JSON.stringify(window.signaturSerializeSettings()));

        fetch(boot.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.text();
            })
            .then(function (html) {
                frame.srcdoc = html;
                note.textContent = noteText;
                note.classList.remove('is-error');
            })
            .catch(function () {
                note.textContent = boot.failed || 'The preview could not be loaded.';
                note.classList.add('is-error');
            })
            .then(function () {
                busy = false;
                update.disabled = false;
                panel.classList.remove('is-loading');
            });
    }

    frame.addEventListener('load', function () {
        fit();
        setDev();

        var doc = frame.contentDocument;

        // A photo chosen or a field typed into changes its height.
        if (doc && 'ResizeObserver' in window) {
            new ResizeObserver(fit).observe(doc.body);
        }
    });

    update.addEventListener('click', load);

    devButton.addEventListener('click', function () {
        dev = !dev;
        setDev();
    });

    panel.addEventListener('click', function (e) {
        var button = e.target.closest('.signatur-preview__device');

        if (button) {
            setDevice(button);
        }
    });

    window.addEventListener('resize', fit);

    // The screen chosen last time (this browser).
    try {
        var kept = window.localStorage.getItem(KEY);
        var keptButton = kept && panel.querySelector('.signatur-preview__device[data-device="' + kept + '"]');

        if (keptButton) {
            setDevice(keptButton);
        }
    } catch (err) { /* The first screen. */ }

    fit();
    // Once as the screen opens (builder.js has drawn the signature by then).
    window.addEventListener('load', load);
})();
