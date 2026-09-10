/*
 * Nizam -- Student create form: filters the class <select> to the options
 * matching the currently chosen grade. Every option is already server-
 * rendered with its own data-grade-id (no fetch, no new endpoint); this
 * only toggles visibility and re-selects a valid option when the grade
 * changes out from under the previous selection. Server-side validation
 * (StudentService::create) is the actual guarantee, not this filter.
 */
document.addEventListener('DOMContentLoaded', function () {
  var gradeSelect = document.getElementById('grade_id');
  var classSelect = document.getElementById('class_id');
  if (!gradeSelect || !classSelect) {
    return;
  }

  function applyFilter() {
    var gradeId = gradeSelect.value;
    var options = classSelect.querySelectorAll('option[data-grade-id]');
    var hasVisibleSelection = false;

    options.forEach(function (option) {
      var matches = option.getAttribute('data-grade-id') === gradeId;
      option.hidden = !matches;
      if (matches && option.selected) {
        hasVisibleSelection = true;
      }
    });

    if (!hasVisibleSelection) {
      classSelect.value = '';
    }
  }

  gradeSelect.addEventListener('change', applyFilter);
  applyFilter();
});
