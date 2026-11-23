/**
 * The editor's preview (includes/preview.php): on Update (and once as the
 * screen opens) the editor's settings, saved or not (builder.js
 * window.signaturSerializeSettings()), go to admin-ajax signatur_preview,
 * and the page it sends is shown in the frame. The frame is a desktop
 * width (1280 px; the screen buttons were removed at the user's request)
 * scaled down to the column, as tall as its page. Developer turns on the frame's Developer mode
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

    window.addEventListener('resize', fit);

    fit();
    // Once as the screen opens (builder.js has drawn the signature by then).
    window.addEventListener('load', load);
})();
