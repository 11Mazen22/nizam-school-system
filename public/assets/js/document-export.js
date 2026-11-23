/*
 * NIZAM document platform — browser side.
 *
 * One controller for the whole application:
 *   - Print opens the document itself in a hidden frame and prints it there,
 *     so the print dialog is instant and no pop-up is involved.
 *   - Preview shows that same document with zoom, page navigation and live
 *     appearance options.
 *   - Every format is fetched (never navigated to), so a server error becomes
 *     a readable message instead of a broken tab.
 *   - Bulk export reports the real number of files produced, lists failures
 *     and never shows an invented percentage.
 *
 * The format list is read from the engine's capability endpoint; nothing about
 * supported formats is hard-coded here.
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

  var state = {
    preview: {
      key: null,
      params: {},
      title: '',
      options: { signatures: true, watermark: false, compact: false },
      pages: 1,
      page: 1,
      scale: 1,
      width: 794,
      timer: null
    },
    bulk: {
      running: false,
      cancelled: false,
      token: null,
      capabilities: null,
      scope: '',
      format: 'pdf'
    },
    capabilities: Object.create(null),
    printTimer: null
  };

  /* ------------------------------------------------------------- helpers */

  function t(key, fallback) {
    return i18n[key] || fallback || '';
  }

  function one(selector, root) {
    return (root || document).querySelector(selector);
  }

  function all(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
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
      var value = extra[name];
      if (value === null || value === undefined) {
        return;
      }
      search.set(name, String(value));
    });
    var text = search.toString();
    return text ? '?' + text : '';
  }

  function documentUrl(key, params, extra) {
    return '/documents/' + encodeURIComponent(key) + query(params, extra);
  }

  function optionQuery() {
    return {
      signatures: state.preview.options.signatures ? '1' : '0',
      watermark: state.preview.options.watermark ? '1' : '0',
      compact: state.preview.options.compact ? '1' : '0'
    };
  }

  function contextOf(element) {
    var host = element.closest('[data-document-export]');
    if (host) {
      return {
        key: host.getAttribute('data-doc-key') || '',
        params: safeParse(host.getAttribute('data-doc-params')),
        title: host.getAttribute('data-doc-title') || '',
        bulk: host.getAttribute('data-doc-bulk') === '1',
        formats: (host.getAttribute('data-doc-formats') || '').split(',').filter(Boolean)
      };
    }
    return {
      key: state.preview.key,
      params: state.preview.params,
      title: state.preview.title,
      bulk: true,
      formats: []
    };
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

  function toast(message, variant, action) {
    var stack = document.getElementById('nDocToasts');
    if (!stack) {
      return;
    }
    var node = document.createElement('div');
    node.className = 'n-toast n-toast-' + (variant || 'info');
    node.setAttribute('role', variant === 'danger' ? 'alert' : 'status');

    var text = document.createElement('span');
    text.className = 'n-toast-text';
    text.textContent = message;
    node.appendChild(text);

    if (action && action.label && typeof action.onClick === 'function') {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'n-toast-action';
      button.textContent = action.label;
      button.addEventListener('click', function () {
        node.remove();
        action.onClick();
      });
      node.appendChild(button);
    }

    stack.appendChild(node);
    window.setTimeout(function () {
      node.classList.add('is-leaving');
      window.setTimeout(function () { node.remove(); }, 260);
    }, action ? 9000 : 5200);
  }

  function busy(button, on) {
    if (!button) {
      return;
    }
    button.classList.toggle('is-busy', !!on);
    button.disabled = !!on;
    if (on) {
      button.setAttribute('aria-busy', 'true');
    } else {
      button.removeAttribute('aria-busy');
    }
  }

  function download(blob, filename) {
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = filename || 'document';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () { URL.revokeObjectURL(url); }, 5000);
  }

  async function readError(response) {
    var session = response.status === 401 || response.status === 403 || response.status === 419;
    try {
      var payload = await response.json();
      return {
        message: payload.error || t('bulkError', 'Something went wrong. Please try again.'),
        code: payload.code || '',
        retryable: payload.retryable !== false
      };
    } catch (error) {
      return {
        message: session
          ? t('sessionExpired', 'Your session expired. Reload the page and try again.')
          : t('bulkError', 'Something went wrong. Please try again.'),
        code: session ? 'session' : 'network',
        retryable: true
      };
    }
  }

  function closeMenu(element) {
    var toggle = element.closest('.n-export') && one('[data-bs-toggle="dropdown"]', element.closest('.n-export'));
    if (!toggle || !window.bootstrap || !window.bootstrap.Dropdown) {
      return;
    }
    var instance = window.bootstrap.Dropdown.getInstance(toggle);
    if (instance) {
      instance.hide();
    }
  }

  function reportError(error, context) {
    var message = error && error.message ? error.message : t('bulkError', 'Something went wrong.');
    var code = error && error.code ? error.code : '';
    if (code === 'renderer_unavailable' || code === 'renderer_failed' || code === 'renderer_timeout') {
      toast(message, 'danger', {
        label: t('print', 'Print'),
        onClick: function () { printDocument(context); }
      });
      return;
    }
    toast(message, 'danger');
  }

  /* --------------------------------------------------------- capabilities */

  async function capabilities(key, params) {
    var cacheKey = key + '|' + JSON.stringify(params || {});
    if (state.capabilities[cacheKey]) {
      return state.capabilities[cacheKey];
    }

    var response = await fetch(
      '/documents/' + encodeURIComponent(key) + '/capabilities' + query(params),
      {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }
    );
    if (!response.ok) {
      throw await readError(response);
    }
    var data = await response.json();
    state.capabilities[cacheKey] = data;
    return data;
  }

  /* --------------------------------------------------------------- export */

  async function requestFormat(context, format, trigger) {
    busy(trigger, true);
    try {
      var url = '/documents/' + encodeURIComponent(context.key) + '/export/' + encodeURIComponent(format)
        + query(context.params, optionQuery());
      var response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!response.ok) {
        throw await readError(response);
      }

      var type = response.headers.get('Content-Type') || '';
      if (format !== 'html' && type.indexOf('text/html') === 0) {
        throw {
          message: t('sessionExpired', 'Your session expired. Reload the page and try again.'),
          code: 'session'
        };
      }

      var blob = await response.blob();
      var filename = filenameFrom(response, 'document.' + format);
      download(blob, filename);

      var pages = Number(response.headers.get('X-Nizam-Pages') || 0);
      var entries = Number(response.headers.get('X-Nizam-Entries') || 0);
      var detail = filename;
      if (entries > 1) {
        detail += ' · ' + entries + ' ' + t('files', 'files');
      } else if (pages > 1) {
        detail += ' · ' + pages + ' ' + t('pages', 'pages');
      }
      toast(t('saved', 'Downloaded') + ' — ' + detail, 'success');
    } catch (error) {
      reportError(error, context);
    } finally {
      busy(trigger, false);
    }
  }

  /* ---------------------------------------------------------------- print */

  var printFrame = null;

  function ensurePrintFrame() {
    if (!printFrame) {
      printFrame = document.createElement('iframe');
      printFrame.className = 'n-print-frame';
      printFrame.setAttribute('aria-hidden', 'true');
      printFrame.setAttribute('tabindex', '-1');
      printFrame.title = '';
      document.body.appendChild(printFrame);
    }
    return printFrame;
  }

  function printDocument(context) {
    if (!context || !context.key) {
      return;
    }
    var frame = ensurePrintFrame();
    var url = documentUrl(context.key, context.params, Object.assign({
      print: '1'
    }, optionQuery()));

    window.clearTimeout(state.printTimer);
    frame.onerror = function () {
      toast(t('printSlow', 'Printing did not start. Try reloading the page.'), 'danger');
    };
    frame.onload = function () {
      window.clearTimeout(state.printTimer);
    };
    frame.src = url;

    state.printTimer = window.setTimeout(function () {
      toast(t('printSlow', 'Printing did not start. Try reloading the page.'), 'danger');
    }, 25000);
  }

  /* -------------------------------------------------------------- preview */

  function previewModal() {
    return document.getElementById('nDocPreviewModal');
  }

  function previewFrame() {
    return document.getElementById('nDocPreviewFrame');
  }

  function postToFrame(message) {
    var frame = previewFrame();
    if (!frame || !frame.contentWindow) {
      return;
    }
    frame.contentWindow.postMessage(message, window.location.origin);
  }

  function setLoading(visible) {
    var loading = one('[data-doc-loading]');
    if (loading) {
      loading.hidden = !visible;
    }
  }

  function showPreviewError(message) {
    var node = one('[data-doc-error]');
    var text = one('[data-doc-error-message]');
    if (text) {
      text.textContent = message;
    }
    if (node) {
      node.hidden = false;
    }
    setLoading(false);
  }

  function hidePreviewError() {
    var node = one('[data-doc-error]');
    if (node) {
      node.hidden = true;
    }
  }

  function setPageLabel() {
    var label = one('[data-doc-page-label]');
    if (label) {
      label.textContent = state.preview.page + ' / ' + state.preview.pages;
    }
  }

  function setZoomLabel() {
    var output = one('[data-doc-zoom-level]');
    if (output) {
      output.textContent = Math.round(state.preview.scale * 100) + '%';
    }
  }

  function fitWidth() {
    var frame = previewFrame();
    if (!frame) {
      return;
    }
    var available = Math.max(280, frame.clientWidth - 28);
    var pageWidth = state.preview.width > 200 ? state.preview.width : 794;
    var scale = Math.min(1.25, Math.max(0.35, available / pageWidth));
    state.preview.scale = scale;
    postToFrame({ type: 'nizam:document-zoom', scale: scale });
    setZoomLabel();
  }

  function zoomBy(direction) {
    var next = state.preview.scale + (direction > 0 ? 0.1 : -0.1);
    state.preview.scale = Math.min(1.6, Math.max(0.35, Math.round(next * 100) / 100));
    postToFrame({ type: 'nizam:document-zoom', scale: state.preview.scale });
    setZoomLabel();
  }

  function goToPage(page) {
    state.preview.page = Math.min(state.preview.pages, Math.max(1, page));
    setPageLabel();
    postToFrame({ type: 'nizam:document-goto', page: state.preview.page });
  }

  function openPreview(context) {
    var modal = previewModal();
    if (!modal || !window.bootstrap) {
      window.open(documentUrl(context.key, context.params), '_blank', 'noopener');
      return;
    }

    state.preview.key = context.key;
    state.preview.params = context.params || {};
    state.preview.title = context.title || '';
    state.preview.pages = 1;
    state.preview.page = 1;
    state.preview.scale = 1;

    var titleNode = one('[data-doc-preview-title]');
    if (titleNode) {
      titleNode.textContent = context.title || t('previewTitle', 'Preview');
    }
    var meta = one('[data-doc-preview-meta]');
    if (meta) {
      meta.textContent = t('loading', 'Preparing the document…');
    }
    setZoomLabel();
    setPageLabel();
    hidePreviewError();
    setLoading(true);

    var frame = previewFrame();
    frame.onload = function () {
      setLoading(false);
    };
    frame.src = documentUrl(context.key, context.params, Object.assign({ embed: '1' }, optionQuery()));

    window.clearTimeout(state.preview.timer);
    state.preview.timer = window.setTimeout(function () {
      showPreviewError(t('bulkError', 'The document took too long to load. Try again.'));
    }, 20000);

    window.bootstrap.Modal.getOrCreateInstance(modal).show();

    var shown = function () {
      modal.removeEventListener('shown.bs.modal', shown);
      window.requestAnimationFrame(fitWidth);
    };
    modal.addEventListener('shown.bs.modal', shown);
  }

  function previewContext() {
    return {
      key: state.preview.key,
      params: state.preview.params,
      title: state.preview.title
    };
  }

  function reloadPreview() {
    var frame = previewFrame();
    if (!frame || !state.preview.key) {
      return;
    }
    hidePreviewError();
    setLoading(true);
    frame.src = documentUrl(state.preview.key, state.preview.params, Object.assign({ embed: '1' }, optionQuery()));
  }

  /* ----------------------------------------------------------------- bulk */

  function bulkModal() {
    return document.getElementById('nDocBulkModal');
  }

  function setBulkError(message) {
    var modal = bulkModal();
    if (!modal) {
      return;
    }
    var node = one('[data-doc-bulk-error]', modal);
    if (!node) {
      return;
    }
    node.hidden = !message;
    node.textContent = message || '';
  }

  function resetBulkModal() {
    var modal = bulkModal();
    if (!modal) {
      return;
    }
    one('[data-doc-bulk-setup]', modal).hidden = false;
    one('[data-doc-bulk-progress]', modal).hidden = true;
    one('[data-doc-bulk-done]', modal).hidden = true;
    one('[data-doc-bulk-cancel]', modal).hidden = true;
    one('[data-doc-bulk-retry]', modal).hidden = true;

    var start = one('[data-doc-bulk-start]', modal);
    start.disabled = false;
    start.hidden = false;

    var cancel = one('[data-doc-bulk-cancel]', modal);
    if (cancel) {
      cancel.disabled = false;
    }

    var log = one('[data-doc-bulk-log]', modal);
    if (log) {
      log.innerHTML = '';
    }
    var failures = one('[data-doc-bulk-failures]', modal);
    if (failures) {
      failures.innerHTML = '';
    }

    setBulkError('');
    setProgress(0, 0, '');
  }

  function setProgress(done, total, label, file) {
    var modal = bulkModal();
    if (!modal) {
      return;
    }
    var percent = total > 0 ? Math.round((done / total) * 100) : 0;
    var bar = one('[data-doc-bulk-bar]', modal);
    var wrap = one('[data-doc-bulk-bar-wrap]', modal);
    var count = one('[data-doc-bulk-count]', modal);
    var percentNode = one('[data-doc-bulk-percent]', modal);
    var caption = one('[data-doc-bulk-label]', modal);
    var current = one('[data-doc-bulk-current]', modal);

    if (bar) { bar.style.width = percent + '%'; }
    if (wrap) { wrap.setAttribute('aria-valuenow', String(percent)); }
    if (count) { count.textContent = done + ' / ' + total; }
    if (percentNode) { percentNode.textContent = percent + '%'; }
    if (caption) {
      caption.textContent = total > 0
        ? t('progress', 'Preparing file :done of :total')
            .replace(':done', String(done))
            .replace(':total', String(total))
        : t('current', 'Now processing');
    }
    if (current) {
      current.textContent = label || '';
    }
    if (file) {
      pushBulkLog(file);
    }
  }

  function pushBulkLog(file) {
    var log = one('[data-doc-bulk-log]');
    if (!log) {
      return;
    }
    var item = document.createElement('li');
    item.textContent = file;
    log.appendChild(item);
    while (log.children.length > 6) {
      log.removeChild(log.firstElementChild);
    }
  }

  function pushBulkFailure(label, code) {
    var list = one('[data-doc-bulk-failures]');
    if (!list) {
      return;
    }
    var item = document.createElement('li');
    item.textContent = label + (code ? ' — ' + code : '');
    list.appendChild(item);
  }

  function renderBulkFormats(formats) {
    var host = one('[data-doc-bulk-formats]');
    if (!host) {
      return;
    }
    host.innerHTML = '';

    var list = formats && formats.length ? formats : [
      { key: 'pdf', label: 'PDF', extension: 'pdf' },
      { key: 'png', label: 'PNG', extension: 'png' },
      { key: 'xlsx', label: 'Excel', extension: 'xlsx' },
      { key: 'csv', label: 'CSV', extension: 'csv' }
    ];

    list.forEach(function (format) {
      var label = document.createElement('label');
      label.className = 'n-doc-format';
      label.innerHTML =
        '<input type="radio" name="nDocBulkFormat" value="' + format.key + '">'
        + '<span class="n-doc-format-body">'
        + '<strong>' + escapeHtml(format.label) + '</strong>'
        + '<small>' + escapeHtml(format.description || format.extension.toUpperCase()) + '</small>'
        + '</span>'
        + '<b class="n-doc-format-ext">' + escapeHtml(format.extension.toUpperCase()) + '</b>';
      host.appendChild(label);
    });

    var preferred = list.some(function (format) { return format.key === 'pdf'; }) ? 'pdf' : list[0].key;
    setBulkFormat(preferred);
  }

  function setBulkFormat(format) {
    state.bulk.format = format;
    all('.n-doc-format').forEach(function (node) {
      var input = one('input', node);
      node.classList.toggle('is-selected', !!input && input.value === format);
    });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function updateBulkSummary() {
    var modal = bulkModal();
    var select = modal && one('#nDocBulkScope', modal);
    var summary = modal && one('[data-doc-bulk-summary]', modal);
    if (!select || !summary) {
      return;
    }
    var option = select.options[select.selectedIndex];
    var count = option ? Number(option.dataset.count || 0) : 0;
    var label = option ? (option.dataset.label || option.textContent) : '';
    var countLabel = count === 1
      ? t('summaryOne', '1 document')
      : t('summary', ':count documents').replace(':count', String(count));
    summary.textContent = count > 0
      ? label + ' · ' + countLabel
      : t('noData', 'There is nothing to export in this scope.');
  }

  async function openBulk(context) {
    var modal = bulkModal();
    if (!modal || !window.bootstrap) {
      return;
    }
    if (context.bulk === false) {
      return;
    }

    resetBulkModal();
    state.bulk.running = false;
    state.bulk.cancelled = false;
    state.bulk.token = null;
    state.bulk.scope = '';
    state.bulk.capabilities = null;

    modal.dataset.docKey = context.key;
    modal.dataset.docParams = JSON.stringify(context.params || {});
    modal.dataset.docTitle = context.title || '';

    window.bootstrap.Modal.getOrCreateInstance(modal).show();

    var select = one('#nDocBulkScope', modal);
    if (select) {
      select.innerHTML = '<option value="">' + escapeHtml(t('loading', 'Loading…')) + '</option>';
    }

    try {
      var caps = await capabilities(context.key, context.params);
      state.bulk.capabilities = caps;

      var groups = (caps.bulk && caps.bulk.groups) || [];
      if (select) {
        select.innerHTML = '';
        groups.forEach(function (group) {
          var option = document.createElement('option');
          option.value = group.key;
          option.dataset.count = String(group.count);
          option.dataset.label = group.label;
          option.textContent = group.label + ' (' + group.count + ')';
          select.appendChild(option);
        });
      }
      state.bulk.scope = groups.length ? groups[0].key : '';

      renderBulkFormats((caps.bulk && caps.bulk.formats) || []);
      updateBulkSummary();
    } catch (error) {
      setBulkError(error.message || t('bulkError', 'Bulk export is unavailable.'));
    }
  }

  async function runBulk() {
    var modal = bulkModal();
    if (!modal || state.bulk.running) {
      return;
    }

    var key = modal.dataset.docKey;
    var params = safeParse(modal.dataset.docParams);
    var select = one('#nDocBulkScope', modal);
    var group = select ? select.value : '';
    var format = state.bulk.format || 'pdf';

    if (!key || !group) {
      setBulkError(t('noData', 'There is nothing to export in this scope.'));
      return;
    }

    state.bulk.running = true;
    state.bulk.cancelled = false;
    state.bulk.token = null;

    one('[data-doc-bulk-setup]', modal).hidden = true;
    one('[data-doc-bulk-progress]', modal).hidden = false;
    one('[data-doc-bulk-done]', modal).hidden = true;
    one('[data-doc-bulk-cancel]', modal).hidden = false;
    one('[data-doc-bulk-retry]', modal).hidden = true;

    var startButton = one('[data-doc-bulk-start]', modal);
    startButton.hidden = true;

    setBulkError('');
    setProgress(0, 0, '');

    var log = one('[data-doc-bulk-log]', modal);
    if (log) {
      log.innerHTML = '';
    }

    try {
      var startResponse = await fetch('/documents/bulk/start' + query(params, {
        key: key,
        group: group,
        format: format
      }), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });

      if (!startResponse.ok) {
        throw await readError(startResponse);
      }

      var job = await startResponse.json();
      state.bulk.token = job.token;
      state.bulk.scope = job.group;
      var total = Number(job.total || 0);
      setProgress(0, total, '');

      for (var index = 0; index < total; index++) {
        if (state.bulk.cancelled) {
          break;
        }
        var stepResponse = await fetch('/documents/bulk/' + encodeURIComponent(job.token) + '/step', {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!stepResponse.ok) {
          throw await readError(stepResponse);
        }
        var step = await stepResponse.json();
        setProgress(step.done, step.total, step.label, step.file);
        if (step.error) {
          pushBulkFailure(step.label, step.errorCode || step.error);
        }
        if (step.complete) {
          break;
        }
      }

      if (state.bulk.cancelled) {
        await fetch('/documents/bulk/' + encodeURIComponent(job.token) + '/cancel', { credentials: 'same-origin' });
        resetBulkModal();
        state.bulk.running = false;
        return;
      }

      var fileResponse = await fetch('/documents/bulk/' + encodeURIComponent(job.token) + '/download', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!fileResponse.ok) {
        throw await readError(fileResponse);
      }

      var blob = await fileResponse.blob();
      var filename = filenameFrom(fileResponse, 'NIZAM-export.zip');
      download(blob, filename);

      var failed = Number(fileResponse.headers.get('X-Nizam-Failed') || 0);
      var files = Number(fileResponse.headers.get('X-Nizam-Files') || 0);

      one('[data-doc-bulk-progress]', modal).hidden = true;
      one('[data-doc-bulk-cancel]', modal).hidden = true;
      one('[data-doc-bulk-done]', modal).hidden = false;
      one('[data-doc-bulk-setup]', modal).hidden = false;
      setProgress(total, total, '');

      var result = one('[data-doc-bulk-result]', modal);
      if (result) {
        result.textContent = filename;
      }
      var stats = one('[data-doc-bulk-stats]', modal);
      if (stats) {
        stats.textContent = files + ' ' + (files === 1 ? t('filesOne', 'file') : t('files', 'files'))
          + (failed > 0 ? ' · ' + t('failed', ':count documents could not be generated.').replace(':count', String(failed)) : '');
      }

      startButton.hidden = false;
      startButton.disabled = false;

      toast(t('downloaded', 'Package download started') + ' — ' + filename, failed > 0 ? 'info' : 'success');
    } catch (error) {
      setBulkError(error.message || t('bulkError', 'Bulk export failed.'));
      one('[data-doc-bulk-setup]', modal).hidden = false;
      one('[data-doc-bulk-progress]', modal).hidden = true;
      one('[data-doc-bulk-cancel]', modal).hidden = true;
      one('[data-doc-bulk-retry]', modal).hidden = false;
      startButton.hidden = false;
      startButton.disabled = false;
    } finally {
      state.bulk.running = false;
    }
  }

  function cancelBulk() {
    state.bulk.cancelled = true;
    var modal = bulkModal();
    if (modal) {
      var cancelButton = one('[data-doc-bulk-cancel]', modal);
      if (cancelButton) {
        cancelButton.disabled = true;
      }
    }
    if (state.bulk.token) {
      fetch('/documents/bulk/' + encodeURIComponent(state.bulk.token) + '/cancel', { credentials: 'same-origin' });
    }
  }

  /* --------------------------------------------------------------- wiring */

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-doc-action]');
    if (trigger) {
      var context = contextOf(trigger);
      if (!context.key) {
        return;
      }
      event.preventDefault();
      var action = trigger.getAttribute('data-doc-action');
      closeMenu(trigger);

      if (action === 'print') {
        printDocument(context);
        return;
      }
      if (action === 'preview') {
        openPreview(context);
        return;
      }
      if (action === 'bulk') {
        openBulk(context);
        return;
      }
      requestFormat(context, action, trigger);
      return;
    }

    var formatButton = event.target.closest('[data-doc-format]');
    if (formatButton) {
      event.preventDefault();
      requestFormat(previewContext(), formatButton.getAttribute('data-doc-format'), formatButton);
      return;
    }

    if (event.target.closest('[data-doc-print]')) {
      event.preventDefault();
      printDocument(previewContext());
      return;
    }

    var zoom = event.target.closest('[data-doc-zoom]');
    if (zoom) {
      var direction = zoom.getAttribute('data-doc-zoom');
      if (direction === 'fit') {
        fitWidth();
      } else {
        zoomBy(direction === 'in' ? 1 : -1);
      }
      return;
    }

    var page = event.target.closest('[data-doc-page]');
    if (page) {
      goToPage(state.preview.page + (page.getAttribute('data-doc-page') === 'next' ? 1 : -1));
      return;
    }

    if (event.target.closest('[data-doc-retry]')) {
      event.preventDefault();
      reloadPreview();
      return;
    }

    if (event.target.closest('[data-doc-bulk-start]')) {
      event.preventDefault();
      runBulk();
      return;
    }

    if (event.target.closest('[data-doc-bulk-cancel]')) {
      event.preventDefault();
      cancelBulk();
      return;
    }

    if (event.target.closest('[data-doc-bulk-retry]')) {
      event.preventDefault();
      resetBulkModal();
      return;
    }
  });

  document.addEventListener('change', function (event) {
    var box = event.target.closest('[data-doc-option]');
    if (!box) {
      return;
    }
    var name = box.getAttribute('data-doc-option');
    state.preview.options[name] = box.checked;
    postToFrame({
      type: 'nizam:document-options',
      signatures: state.preview.options.signatures,
      watermark: state.preview.options.watermark,
      compact: state.preview.options.compact
    });
  });

  document.addEventListener('change', function (event) {
    if (!event.target) {
      return;
    }
    if (event.target.id === 'nDocBulkScope') {
      updateBulkSummary();
      return;
    }
    if (event.target.name === 'nDocBulkFormat') {
      setBulkFormat(event.target.value);
    }
  });

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin || !event.data) {
      return;
    }
    var data = event.data;

    if (data.type === 'nizam:document-ready') {
      window.clearTimeout(state.preview.timer);
      hidePreviewError();
      setLoading(false);
      state.preview.pages = Math.max(1, Number(data.pages) || 1);
      state.preview.width = Number(data.width) || 794;
      state.preview.page = 1;
      setPageLabel();

      var meta = one('[data-doc-preview-meta]');
      if (meta) {
        var parts = [state.preview.pages + ' ' + t('pages', 'pages')];
        if (data.orientation === 'landscape') {
          parts.push('A4 · Landscape');
        } else {
          parts.push('A4');
        }
        if (data.year) {
          parts.push(String(data.year));
        }
        meta.textContent = parts.join(' · ');
      }
      window.requestAnimationFrame(fitWidth);
      return;
    }

    if (data.type === 'nizam:document-page') {
      state.preview.page = Math.min(state.preview.pages, Math.max(1, Number(data.page) || 1));
      setPageLabel();
      return;
    }

    if (data.type === 'nizam:document-printed') {
      window.clearTimeout(state.printTimer);
    }
  });

  window.addEventListener('resize', function () {
    var modal = previewModal();
    if (modal && modal.classList.contains('show')) {
      fitWidth();
    }
  });

  document.addEventListener('keydown', function (event) {
    var modal = previewModal();
    if (!modal || !modal.classList.contains('show')) {
      return;
    }
    var tag = (event.target && event.target.tagName) || '';
    if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') {
      return;
    }
    if (event.key === 'ArrowRight') {
      goToPage(state.preview.page + 1);
    } else if (event.key === 'ArrowLeft') {
      goToPage(state.preview.page - 1);
    } else if (event.key === '+' || event.key === '=') {
      zoomBy(1);
    } else if (event.key === '-') {
      zoomBy(-1);
    }
  });

  function initModals() {
    var modal = previewModal();
    if (modal) {
      modal.addEventListener('hidden.bs.modal', function () {
        window.clearTimeout(state.preview.timer);
        var frame = previewFrame();
        if (frame) {
          frame.src = 'about:blank';
        }
      });
    }

    var bulk = bulkModal();
    if (bulk) {
      bulk.addEventListener('hidden.bs.modal', function () {
        if (state.bulk.running) {
          cancelBulk();
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModals);
  } else {
    initModals();
  }
})();
