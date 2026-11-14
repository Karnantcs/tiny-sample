/**
 * Fills each [ninja_quote] block with a random quote from the API in its data-api-url attribute.
 *
 * The request runs in the browser when the page loads. The text is written with
 * textContent, so nothing from the API is read as HTML.
 */
(function () {
    'use strict';

    var TIMEOUT_MS = 10000;

    function showError(text, author) {
        text.textContent = 'Could not load a quote right now.';
        author.textContent = '';
    }

    function load(box) {
        var url = box.getAttribute('data-api-url');
        var text = box.querySelector('.ninja-quote-text');
        var author = box.querySelector('.ninja-quote-author');
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, TIMEOUT_MS) : null;

        fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller ? controller.signal : undefined
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (!data || typeof data.content !== 'string' || data.content === '') {
                    throw new Error('The response has no quote');
                }
                text.textContent = '\u201C' + data.content + '\u201D';
                author.textContent = typeof data.author === 'string' && data.author !== ''
                    ? '\u2014 ' + data.author
                    : '';
            })
            .catch(function () {
                showError(text, author);
            })
            .finally(function () {
                if (timer) {
                    clearTimeout(timer);
                }
            });
    }

    function start() {
        var boxes = document.querySelectorAll('.ninja-quote[data-api-url]');
        for (var i = 0; i < boxes.length; i++) {
            load(boxes[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
