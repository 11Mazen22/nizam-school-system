(function () {
  'use strict';

  var modal = document.getElementById('addSlotModal');
  if (!modal) return;

  var dayLabels = [];
  try {
    dayLabels = JSON.parse(modal.dataset.dayLabels || '[]');
  } catch (_) {
    dayLabels = [];
  }

  modal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    if (!button) return;
    var day = button.dataset.day || '';
    var period = button.dataset.period || '';

    document.getElementById('slotDay').value = day;
    document.getElementById('slotPeriod').value = period;

    var dayName = dayLabels[Number(day) - 1] || day;
    document.getElementById('slotInfo').textContent =
      (modal.dataset.dayLabel || '') + ': ' + dayName + '  |  ' +
      (modal.dataset.periodLabel || '') + ': ' + period;

    document.getElementById('slotSubject').value = '';
    document.getElementById('slotTeacher').value = '';
  });

  var form = modal.querySelector('form');
  if (!form) return;
  form.addEventListener('submit', function (event) {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add('was-validated');
  });
})();
