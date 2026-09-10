/*
 * Nizam -- §I.3 step 2: restore requires a typed confirmation phrase, not
 * just an OK button, since it overwrites the entire database. The submit
 * button stays disabled until the exact literal word "RESTORE" is typed --
 * deliberately not translated (an untranslated, fixed token is unambiguous
 * to type correctly regardless of the active locale/script, the same
 * pattern many tools use for "type DELETE to confirm"). The label above the
 * field explaining what to type IS translated; only the token itself isn't.
 * Enforced again server-side (BackupController::restore) -- this is a UX
 * safety rail against a misclick, not the actual security boundary.
 */
document.addEventListener('DOMContentLoaded', function () {
  var CONFIRM_PHRASE = 'RESTORE';
  var input = document.getElementById('restoreConfirmPhrase');
  var hidden = document.getElementById('restoreConfirmPhraseValue');
  var submitBtn = document.getElementById('restoreSubmit');
  var form = document.getElementById('restoreForm');

  if (!input || !hidden || !submitBtn || !form) {
    return;
  }

  function sync() {
    var matches = input.value === CONFIRM_PHRASE;
    hidden.value = input.value;
    submitBtn.disabled = !matches;
  }

  input.addEventListener('input', sync);
  sync();

  form.addEventListener('submit', function (event) {
    if (input.value !== CONFIRM_PHRASE) {
      event.preventDefault();
    }
  });
});
