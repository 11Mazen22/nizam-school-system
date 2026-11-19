/* Timetable staff mapping: live conflict check + autocomplete suggestions. */
(function () {
  'use strict';

  var root = document.getElementById('mappingChecker');
  if (!root || typeof fetch !== 'function') { return; }

  var printed = document.getElementById('newPrintedName');
  var full = document.getElementById('newFullName');
  var status = document.getElementById('mappingStatus');
  var suggestions = document.getElementById('mappingSuggestions');
  var timer = null;
  var controller = null;

  var labels = {
    clear: root.getAttribute('data-clear') || 'No conflict',
    nameTaken: root.getAttribute('data-name-taken') || 'This printed name already belongs to another teacher',
    slot: root.getAttribute('data-slot') || 'This teacher is already booked in a colliding lesson',
    checking: root.getAttribute('data-checking') || 'Checking…'
  };

  function paint(level, text) {
    if (!status) { return; }
    status.className = 'small mt-1 badge text-bg-' + (level === 'ok' ? 'success' : 'danger');
    status.textContent = text;
  }

  function render(items) {
    if (!suggestions) { return; }
    suggestions.innerHTML = '';
    (items || []).forEach(function (item) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm btn-outline-secondary';
      btn.textContent = item.name;
      btn.addEventListener('click', function () {
        if (full) { full.value = item.name; }
        suggestions.innerHTML = '';
      });
      suggestions.appendChild(btn);
    });
  }

  function check() {
    if (!printed || !printed.value.trim()) {
      paint('ok', labels.clear);
      render([]);
      return;
    }
    if (controller) { controller.abort(); }
    controller = new AbortController();
    paint('ok', labels.checking);
    fetch('/timetable/staff/check?printed_name=' + encodeURIComponent(printed.value.trim()), {
      credentials: 'same-origin',
      signal: controller.signal,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (r) { return r.ok ? r.json() : {}; }).then(function (data) {
      if (data && data.conflict) {
        var text = data.alias_owner ? labels.nameTaken + ': ' + data.alias_owner.name : labels.slot;
        paint('danger', text);
      } else {
        paint('ok', labels.clear);
      }
      render(data && data.suggestions ? data.suggestions : []);
    }).catch(function (e) {
      if (e && e.name === 'AbortError') { return; }
      render([]);
    });
  }

  printed.addEventListener('input', function () {
    window.clearTimeout(timer);
    timer = window.setTimeout(check, 250);
  });

  check();
})();
