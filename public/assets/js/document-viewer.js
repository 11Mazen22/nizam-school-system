/*
 * Runs inside the embedded document frame only.
 *
 * Presentation options arrive from the parent preview modal as messages, so
 * toggling signatures, the watermark or a compact layout never re-queries the
 * school database -- the document is already rendered, only its appearance
 * changes.
 */
(function () {
  'use strict';

  var body = document.body;

  function apply(options) {
    if (typeof options.signatures === 'boolean') {
      body.classList.toggle('doc-hide-signatures', !options.signatures);
    }
    if (typeof options.watermark === 'boolean') {
      body.classList.toggle('doc-hide-watermark', !options.watermark);
    }
    if (typeof options.compact === 'boolean') {
      body.classList.toggle('doc-compact', options.compact);
    }
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin || !event.data) {
      return;
    }
    if (event.data.type === 'nizam:document-options') {
      apply(event.data);
    }
  });

  document.querySelectorAll('[data-doc-print]').forEach(function (button) {
    button.addEventListener('click', function () {
      window.print();
    });
  });
})();
