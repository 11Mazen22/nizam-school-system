document.addEventListener('DOMContentLoaded', function () {
  var wrapper = document.getElementById('edit-tab-wrapper');
  var editTab = document.getElementById('edit-tab');
  if (!wrapper || !editTab) { return; }

  var idField = document.getElementById('edit_exam_id');
  var nameEnField = document.getElementById('name_en_edit');
  var nameArField = document.getElementById('name_ar_edit');
  var termField = document.getElementById('term_edit');
  var maxScoreField = document.getElementById('max_score_edit');
  var weightField = document.getElementById('weight_edit');
  var form = document.getElementById('editExamForm');

  document.querySelectorAll('[data-exam-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-exam-id');
      wrapper.classList.remove('d-none');
      idField.value = id;
      nameEnField.value = btn.getAttribute('data-exam-name-en');
      nameArField.value = btn.getAttribute('data-exam-name-ar');
      termField.value = btn.getAttribute('data-exam-term');
      maxScoreField.value = btn.getAttribute('data-exam-max-score');
      weightField.value = btn.getAttribute('data-exam-weight');
      form.action = '/exams/' + id + '/update';
      editTab.click();
    });
  });
});
