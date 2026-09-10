/*
 * Nizam -- Promotion Preview: each row has its own action select
 * (promote/repeat/graduate/transferred/withdrawn) and its own target-class
 * select. Every candidate class option is server-rendered up front, tagged
 * data-action="promote" or data-action="repeat"; this only shows the set
 * matching that row's current action and disables/clears the class select
 * for the three actions that don't use one. PromotionService re-validates
 * every submitted class id against the student's actual resolved grade
 * server-side (§Q "never trust client validation for security") -- this is
 * purely the UX filter.
 */
document.addEventListener('DOMContentLoaded', function () {
  var table = document.getElementById('promotionTable');
  if (!table) {
    return;
  }

  function applyRow(actionSelect) {
    var row = actionSelect.closest('tr');
    var classSelect = row.querySelector('.promotion-class');
    if (!classSelect) {
      return;
    }
    var action = actionSelect.value;
    var usesClass = action === 'promote' || action === 'repeat';

    classSelect.disabled = !usesClass;
    var hasVisibleSelection = false;
    classSelect.querySelectorAll('option[data-action]').forEach(function (option) {
      var matches = option.getAttribute('data-action') === action;
      option.hidden = !matches;
      if (matches && option.selected) {
        hasVisibleSelection = true;
      }
    });
    if (!usesClass || !hasVisibleSelection) {
      classSelect.value = '';
    }
  }

  table.querySelectorAll('.promotion-action').forEach(function (select) {
    select.addEventListener('change', function () {
      applyRow(select);
    });
    applyRow(select);
  });
});
