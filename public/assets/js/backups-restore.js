/*
 * Nizam -- §I.3 restore safeguards:
 *  1. typed RESTORE confirmation (server enforces it too),
 *  2. read-only pre-flight validation before the irreversible POST,
 *  3. visible validation feedback and a native loading state.
 */
document.addEventListener('DOMContentLoaded', function () {
  var CONFIRM_PHRASE = 'RESTORE';
  var input = document.getElementById('restoreConfirmPhrase');
  var hidden = document.getElementById('restoreConfirmPhraseValue');
  var submitBtn = document.getElementById('restoreSubmit');
  var form = document.getElementById('restoreForm');
  var fileInput = document.getElementById('restoreFile');
  var preflight = document.getElementById('restorePreflight');
  var validated = false;

  if (!input || !hidden || !submitBtn || !form) {
    return;
  }

  function sync() {
    var matches = input.value === CONFIRM_PHRASE;
    hidden.value = input.value;
    submitBtn.disabled = !matches || !validated;
  }

  function status(message, ok) {
    if (!preflight) return;
    preflight.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary');
    preflight.classList.add(ok ? 'alert-success' : 'alert-danger');
    preflight.textContent = message;
  }

  if (fileInput && form.dataset.validateUrl) {
    fileInput.addEventListener('change', function () {
      validated = false;
      sync();
      if (!fileInput.files || !fileInput.files.length) {
        if (preflight) preflight.classList.add('d-none');
        return;
      }
      if (status) status(preflight.dataset.checking || 'Validating backup…', true);
      var payload = new FormData();
      payload.append('backup_file', fileInput.files[0]);
      payload.append('_csrf_token', form.dataset.csrf || '');
      fetch(form.dataset.validateUrl, {
        method: 'POST',
        body: payload,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (response) {
          return response.json().catch(function () { return {}; }).then(function (data) {
            if (!response.ok) throw new Error(data.error || 'invalid_backup');
            return data;
          });
        })
        .then(function (data) {
          validated = true;
          status(
            (preflight.dataset.ready || 'Backup validated') + ' · ' +
            (data.table_count || 0) + ' ' + (preflight.dataset.tables || 'tables') + ' · ' +
            (data.record_count || 0) + ' ' + (preflight.dataset.records || 'records') + ' · ' +
            (preflight.dataset.schema || 'schema') + ' ' + (data.schema_version || '-'),
            true
          );
          sync();
        })
        .catch(function (error) {
          validated = false;
          status(error.message || 'invalid_backup', false);
          sync();
        });
    });
  }

  input.addEventListener('input', sync);
  sync();

  form.addEventListener('submit', function (event) {
    if (input.value !== CONFIRM_PHRASE || !validated) {
      event.preventDefault();
    }
  });
});
