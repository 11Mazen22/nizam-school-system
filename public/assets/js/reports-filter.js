/*
 * Nizam -- report filter form. The class dropdown's options are already
 * scoped server-side to the currently-submitted grade+year (ReportController
 * re-fetches classesForGrade on every request) -- there's no client-side
 * data to filter with before the next submit, so changing grade just clears
 * a now-possibly-stale class selection rather than silently submitting a
 * class id that belonged to the previous grade. The actual scope guarantee
 * either way is server-side (ReportService rejects a mismatched pair) --
 * this is only a courtesy, not the guarantee.
 *
 * The Print button is wired up here rather than an inline onclick, staying
 * CSP-safe the same way confirm.js's shared modal does.
 */
document.addEventListener('DOMContentLoaded', function () {
  var gradeSelect = document.getElementById('f-grade');
  var classSelect = document.getElementById('f-class');
  if (gradeSelect && classSelect) {
    gradeSelect.addEventListener('change', function () {
      classSelect.value = '';
    });
  }

  document.querySelectorAll('[data-print-report]').forEach(function (button) {
    button.addEventListener('click', function () {
      window.print();
    });
  });
});
