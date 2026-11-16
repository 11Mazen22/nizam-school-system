(function () {
  'use strict';

  document.querySelectorAll('[data-attendance-mark-all]').forEach(function (button) {
    button.addEventListener('click', function () {
      var status = button.dataset.attendanceMarkAll || '';
      document.querySelectorAll('.attendance-toggle input[value="' + status + '"]').forEach(function (radio) {
        if (!radio.checked) radio.click();
      });
    });
  });
})();
