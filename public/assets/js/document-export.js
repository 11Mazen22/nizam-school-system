/*
 * NIZAM document export — browser side.
 *
 * Talks to the Document Engine over three plain endpoints: the document HTML
 * (preview), a per-format export, and the step-wise bulk job. Every export is
 * fetched rather than navigated so a real server error can be shown as a
 * message instead of replacing the page (or opening a broken tab).
 */
(function () {
  'use strict';

  var i18n = {};
  var i18nNode = document.getElementById('nDocI18n');
  if (i18nNode) {
    Object.keys(i18nNode.dataset).forEach(function (key) {
      i18n[key] = i18nNode.dataset[key];
    });
  }

  var previewState = { key: null, params: {}, options: { signatures: true, watermark: false, compact: false } };
  var bulkState = { cancelled: false, running: false, token: null };

  function t(key, fallback) {
    return i18n[key] || fallback || '';
  }

  function closestContext(element) {
    var host = element.closest('[data-document-export]');
    if (host) {
      return {
        key: host.getAttribute('data-doc-key'),
        params: safeParse(host.getAttribute('data-doc-params')),
        bulk: host.getAttribute('data-doc-bulk') === '1',
        formats: (host.getAttribute('data-doc-formats') || '').split(',').filter(Boolean)
      };
    }
    return { key: previewState.key, params: previewState.params, bulk: true, formats: [] };
  }

  function safeParse(value) {
    try {
      var parsed = JSON.parse(value || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function query(params, extra) {
    var search = new URLSearchParams();
    Object.keys(params || {}).forEach(function (name) {
      var value = params[name];
      if (value !== null && value !== undefined && String(value) !== '') {
        search.set(name, value);
      }
    });
    Object.keys(extra || {}).forEach(function (name) {
      search.set(name, extra[name]);
    });
    var text = search.toString();
    return text ? '?' + text : '';
  }

  function filenameFrom(response, fallback) {
    var header = response.headers.get('Content-Disposition') || '';
    var utf = /filename\*=UTF-8''([^;]+)/i.exec(header);
    if (utf && utf[1]) {
      try {
        return decodeURIComponent(utf[1]);
      } catch (error) {
        return utf[1];
      }
    }
    var plain = /filename="([^"]+)"/i.exec(header);
    return plain && plain[1] ? plain[1] : fallback;
  }

  function toast(message, variant) {
    var stack = document.getElementById('nDocToasts');
    if (!stack) {
      return;
    }
    var node = document.createElement('div');
    node.className = 'n-toast n-toast-' + (variant || 'info');
    node.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
    node.textContent = message;
    stack.appendChild(node);
    window.setTimeout(function () {
      node.classList.add('is-leaving');
      window.setTimeout(function () { node.remove(); }, 240);
    }, 5200);
  }

  function errorMessage(payload) {
    if (payload && payload.error) {
      return payload.error;
    }
    return t('bulkError', 'Something went wrong. Please try again.');
  }

  async function readError(response) {
    try {
      var payload = await response.json();
      return errorMessage(payload);
    } catch (error) {
      return t('bulkError', 'Something went wrong. Please try again.');
    }
  }

  async function fetchBlob(url) {
    var response = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    var type = response.headers.get('Content-Type') || '';
    if (type.indexOf('text/html') === 0) {
      throw new Error(t('bulkError', 'Your session may have expired. Reload the page and try again.'));
    }
    return { blob: await response.blob(), response: response };
  }

  function download(blob, filename) {
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = filename || 'document';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
  }

  function openInTab(blob) {
    var url = URL.createObjectURL(blob);
    var tab = window.open(url, '_blank');
    if (!tab) {
      toast(t('popupBlocked', 'Allow pop-ups for this site to open the print view.'), 'danger');
      URL.revokeObjectURL(url);
      return;
    }
    window.setTimeout(function () { URL.revokeObjectURL(url); }, 120000);
  }

  async function exportDocument(context, format, options) {
    var extra = Object.assign({}, options || {});
    var url = '/documents/' + encodeURIComponent(context.key) + '/export/' + format + query(context.params, extra);
    try {
      var result = await fetchBlob(url);
      var filename = filenameFrom(result.response, 'document.' + format);
      if (format === 'print') {
        openInTab(result.blob);
      } else {
        download(result.blob, filename);
      }
    } catch (error) {
      toast(error.message || t('bulkError', 'Export failed.'), 'danger');
    }
  }

  async function printDocument(context) {
    var url = '/documents/' + encodeURIComponent(context.key) + '/export/pdf' + query(context.params, {
      inline: '1',
      signatures: previewState.options.signatures ? '1' : '0',
      watermark: previewState.options.watermark ? '1' : '0',
      compact: previewState.options.compact ? '1' : '0'
    });
    try {
      var result = await fetchBlob(url);
      openInTab(result.blob);
    } catch (error) {
      toast(error.message || t('bulkError', 'Printing is unavailable.'), 'danger');
    }
  }

  /* ------------------------------------------------------------- preview */

  function previewFrame() {
    return document.getElementById('nDocPreviewFrame');
  }

  function pushPreviewOptions() {
    var frame = previewFrame();
    if (!frame || !frame.contentWindow) {
      return;
    }
    frame.contentWindow.postMessage({
      type: 'nizam:document-options',
      signatures: previewState.options.signatures,
      watermark: previewState.options.watermark,
      compact: previewState.options.compact
    }, window.location.origin);
  }

  function openPreview(context, trigger) {
    var modal = document.getElementById('nDocPreviewModal');
    if (!modal || !window.bootstrap) {
      if (trigger) {
        window.location.href = '/documents/' + encodeURIComponent(context.key) + query(context.params);
      }
      return;
    }

    previewState.key = context.key;
    previewState.params = context.params || {};

    var checkboxes = modal.querySelectorAll('[data-doc-option]');
    checkboxes.forEach(function (box) {
      previewState.options[box.getAttribute('data-doc-option')] = box.checked;
    });

    var loading = modal.querySelector('[data-doc-loading]');
    var error = modal.querySelector('[data-doc-error]');
    var frame = previewFrame();

    if (loading) { loading.hidden = false; }
    if (error) { error.hidden = true; error.textContent = ''; }

    frame.onload = function () {
      if (loading) { loading.hidden = true; }
      pushPreviewOptions();
    };
    frame.src = '/documents/' + encodeURIComponent(context.key) + query(context.params, {
      embed: '1',
      signatures: previewState.options.signatures ? '1' : '0',
      watermark: previewState.options.watermark ? '1' : '0',
      compact: previewState.options.compact ? '1' : '0'
    });

    window.bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  /* ---------------------------------------------------------------- bulk */

  function bulkModal() {
    return document.getElementById('nDocBulkModal');
  }

  function setProgress(done, total, label) {
    var modal = bulkModal();
    if (!modal) { return; }
    var percent = total > 0 ? Math.round((done / total) * 100) : 0;
    var bar = modal.querySelector('[data-doc-bulk-bar]');
    var wrap = modal.querySelector('[data-doc-bulk-bar-wrap]');
    var count = modal.querySelector('[data-doc-bulk-count]');
    var text = modal.querySelector('[data-doc-bulk-label]');
    if (bar) { bar.style.width = percent + '%'; }
    if (wrap) { wrap.setAttribute('aria-valuenow', String(percent)); }
    if (count) { count.textContent = done + ' / ' + total; }
    if (text) {
      text.textContent = t('progress', 'Preparing file :done of :total')
        .replace(':done', String(done))
        .replace(':total', String(total));
    }
    var item = modal.querySelector('[data-doc-bulk-item]');
    if (item) {
      item.textContent = label ? t('current', 'Now processing') + ': ' + label : '';
    }
  }

  function setBulkError(message) {
    var modal = bulkModal();
    if (!modal) { return; }
    var node = modal.querySelector('[data-doc-bulk-error]');
    if (!node) { return; }
    node.hidden = !message;
    node.textContent = message || '';
  }

  function resetBulkModal() {
    var modal = bulkModal();
    if (!modal) { return; }
    modal.querySelector('[data-doc-bulk-setup]').hidden = false;
    modal.querySelector('[data-doc-bulk-progress]').hidden = true;
    modal.querySelector('[data-doc-bulk-done]').hidden = true;
    modal.querySelector('[data-doc-bulk-cancel]').hidden = true;
    var start = modal.querySelector('[data-doc-bulk-start]');
    start.disabled = false;
    start.hidden = false;
    setBulkError('');
    setProgress(0, 0, '');
  }

  async function openBulk(context) {
    var modal = bulkModal();
    if (!modal || !window.bootstrap) {
      return;
    }
    resetBulkModal();
    bulkState.cancelled = false;
    bulkState.token = null;
    modal.dataset.docKey = context.key;
    modal.dataset.docParams = JSON.stringify(context.params || {});

    var select = modal.querySelector('#nDocBulkScope');
    select.innerHTML = '';
    var formats = context.formats || [];
    var formatSelect = modal.querySelector('#nDocBulkFormat');
    if (formatSelect) {
      Array.prototype.forEach.call(formatSelect.options, function (option) {
        option.disabled = formats.length > 0 && formats.indexOf(option.value) === -1 && option.value !== 'pdf';
      });
      if (formats.indexOf('xlsx') === -1) {
        var excelOption = formatSelect.querySelector('option[value="xlsx"]');
        if (excelOption) { excelOption.disabled = true; }
      }
    }

    window.bootstrap.Modal.getOrCreateInstance(modal).show();

    try {
      var response = await fetch('/documents/' + encodeURIComponent(context.key) + '/bulk-options' + query(context.params), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!response.ok) {
        throw new Error(await readError(response));
      }
      var scopes = await response.json();
      (scopes.groups || []).forEach(function (group) {
        var option = document.createElement('option');
        option.value = group.key;
        option.textContent = group.label + ' (' + group.count + ')';
        select.appendChild(option);
      });
    } catch (error) {
      setBulkError(error.message || t('bulkError', 'Bulk export is unavailable.'));
    }
  }

  async function runBulk() {
    var modal = bulkModal();
    if (!modal || bulkState.running) { return; }

    var key = modal.dataset.docKey;
    var params = safeParse(modal.dataset.docParams);
    var group = modal.querySelector('#nDocBulkScope').value;
    var format = modal.querySelector('#nDocBulkFormat').value;

    bulkState.running = true;
    bulkState.cancelled = false;
    modal.querySelector('[data-doc-bulk-setup]').hidden = true;
    modal.querySelector('[data-doc-bulk-progress]').hidden = false;
    modal.querySelector('[data-doc-bulk-cancel]').hidden = false;
    modal.querySelector('[data-doc-bulk-start]').hidden = true;
    setBulkError('');

    try {
      var startResponse = await fetch('/documents/bulk/start' + query(params, {
        key: key,
        group: group,
        bulk_format: format
      }), { credentials: 'same-origin', headers: { 'Requested-With': 'XMLHttpRequest' } });
      if (!startResponse.ok) {
        throw new Error(await readError(startResponse));
      }
      var job = await startResponse.json();
      bulkState.token = job.token;
      setProgress(0, job.total, '');

      var total = job.total;
      var failed = 0;

      for (var index = 0; index < total; index++) {
        if (bulkState.cancelled) {
          break;
        }
        var stepResponse = await fetch('/documents/bulk/' + encodeURIComponent(job.token) + '/step', {
          credentials: 'same-origin',
          headers: { 'Requested-With': 'XMLHttpRequest' }
        });
        if (!stepResponse.ok) {
          throw new Error(await readError(stepResponse));
        }
        var step = await stepResponse.json();
        failed = step.failed || failed;
        setProgress(step.done, step.total, step.label);
        if (step.error) {
          setBulkError(step.error);
        }
        if (step.complete) {
          break;
        }
      }

      if (bulkState.cancelled) {
        await fetch('/documents/bulk/' + encodeURIComponent(job.token) + '/cancel', { credentials: 'same-origin' });
        resetBulkModal();
        bulkState.running = false;
        return;
      }

      var file = await fetchBlob('/documents/bulk/' + encodeURIComponent(job.token) + '/download');
      var filename = filenameFrom(file.response, 'NIZAM-export.zip');
      download(file.blob, filename);

      modal.querySelector('[data-doc-bulk-progress]').hidden = true;
      modal.querySelector('[data-doc-bulk-done]').hidden = false;
      modal.querySelector('[data-doc-bulk-cancel]').hidden = true;
      var result = modal.querySelector('[data-doc-bulk-result]');
      if (result) {
        result.textContent = t('done', 'Export complete') + ' — ' + filename
          + (failed > 0 ? ' · ' + t('failed', ':count documents could not be generated.').replace(':count', String(failed)) : '');
      }
      var restart = modal.querySelector('[data-doc-bulk-start]');
      restart.hidden = false;
      restart.disabled = false;
      restart.textContent = t('start', 'Start export');
      modal.querySelector('[data-doc-bulk-setup]').hidden = false;
    } catch (error) {
      setBulkError(error.message || t('bulkError', 'Bulk export failed.'));
      setProgress(0, 0, '');
      modal.querySelector('[data-doc-bulk-cancel]').hidden = true;
      var startButton = modal.querySelector('[data-doc-bulk-start]');
      startButton.hidden = false;
      startButton.disabled = false;
      modal.querySelector('[data-doc-bulk-setup]').hidden = false;
    } finally {
      bulkState.running = false;
    }
  }

  /* --------------------------------------------------------------- wiring */

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-doc-action]');
    if (!trigger) {
      return;
    }
    var action = trigger.getAttribute('data-doc-action');
    var context = closestContext(trigger);
    if (!context.key) {
      return;
    }
    event.preventDefault();

    if (action === 'print') {
      printDocument(context);
      return;
    }
    if (action === 'preview') {
      openPreview(context, trigger);
      return;
    }
    if (action === 'bulk') {
      openBulk(context);
      return;
    }
    if (['pdf', 'xlsx', 'png'].indexOf(action) !== -1) {
      exportDocument(context, action, {
        signatures: previewState.options.signatures ? '1' : '0',
        watermark: previewState.options.watermark ? '1' : '0',
        compact: previewState.options.compact ? '1' : '0'
      });
    }
  });

  document.addEventListener('change', function (event) {
    var box = event.target.closest('[data-doc-option]');
    if (!box) {
      return;
    }
    previewState.options[box.getAttribute('data-doc-option')] = box.checked;
    pushPreviewOptions();
  });

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-doc-bulk-start]')) {
      runBulk();
    }
    if (event.target.closest('[data-doc-bulk-cancel]')) {
      bulkState.cancelled = true;
      var modal = bulkModal();
      if (modal) {
        modal.querySelector('[data-doc-bulk-cancel]').hidden = true;
      }
      if (bulkState.token) {
        fetch('/documents/bulk/' + encodeURIComponent(bulkState.token) + '/cancel', { credentials: 'same-origin' });
      }
    }
  });

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin || !event.data || event.data.type !== 'nizam:document-print') {
      return;
    }
    var frame = previewFrame();
    if (frame && frame.contentWindow) {
      frame.contentWindow.focus();
      frame.contentWindow.print();
    }
  });
})();
