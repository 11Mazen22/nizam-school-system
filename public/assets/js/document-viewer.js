/*
 * Runs inside the generated document frame only (preview or direct print).
 *
 * The parent never manipulates the document's DOM: it sends small messages,
 * which keeps the frame's own markup independent of the application shell --
 * the same property that makes the printed file and the preview identical.
 *
 * In print mode this script waits for the embedded fonts, then calls the
 * browser's print dialog itself. That is what makes "Print" instant and
 * pop-up free compared with downloading a PDF first.
 */
(function () {
  'use strict';

  var body = document.body;
  var papers = Array.prototype.slice.call(document.querySelectorAll('.doc-page'));
  var isPrint = body.getAttribute('data-print') === '1';
  var lastPage = 0;

  function post(message) {
    if (window.parent && window.parent !== window) {
      try {
        window.parent.postMessage(message, window.location.origin);
      } catch (error) {
        // A detached frame (the modal was closed mid-render) simply stops
        // receiving updates; there is nothing to report to.
      }
    }
  }

  function ready() {
    if (!papers.length) {
      return;
    }
    var first = papers[0].getBoundingClientRect();
    post({
      type: 'nizam:document-ready',
      pages: papers.length,
      width: Math.round(first.width),
      height: Math.round(first.height),
      orientation: document.documentElement.classList.contains('doc-landscape') ? 'landscape' : 'portrait',
      year: body.getAttribute('data-year') || ''
    });
    reportPage();
  }

  function applyOptions(options) {
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

  function reportPage() {
    if (!papers.length) {
      return;
    }
    var best = 1;
    var distance = Infinity;
    for (var index = 0; index < papers.length; index++) {
      var top = Math.abs(papers[index].getBoundingClientRect().top - 8);
      if (top < distance) {
        distance = top;
        best = index + 1;
      }
    }
    if (best !== lastPage) {
      lastPage = best;
      post({ type: 'nizam:document-page', page: best });
    }
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin || !event.data) {
      return;
    }

    if (event.data.type === 'nizam:document-options') {
      applyOptions(event.data);
      return;
    }

    if (event.data.type === 'nizam:document-zoom') {
      var scale = Math.max(0.3, Math.min(2, Number(event.data.scale) || 1));
      document.documentElement.style.zoom = String(scale);
      return;
    }

    if (event.data.type === 'nizam:document-goto') {
      var page = Math.max(1, Math.min(papers.length, Number(event.data.page) || 1));
      if (papers[page - 1]) {
        papers[page - 1].scrollIntoView({ behavior: 'smooth', block: 'start' });
        lastPage = page;
        post({ type: 'nizam:document-page', page: page });
      }
    }
  });

  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) {
      return;
    }
    ticking = true;
    window.requestAnimationFrame(function () {
      ticking = false;
      reportPage();
    });
  }, { passive: true });

  document.querySelectorAll('[data-doc-print]').forEach(function (button) {
    button.addEventListener('click', function () {
      window.print();
    });
  });

  if (isPrint) {
    var start = function () {
      window.focus();
      window.print();
    };
    var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
    fontsReady.then(function () {
      window.setTimeout(start, 60);
    });
    window.addEventListener('afterprint', function () {
      post({ type: 'nizam:document-printed' });
    });
  }

  if (document.readyState === 'complete') {
    ready();
  } else {
    window.addEventListener('load', ready);
  }
})();
