document.addEventListener('DOMContentLoaded', function () {
  var wrapper = document.getElementById('edit-tab-wrapper');
  var editTab = document.getElementById('edit-tab');
  var idField = document.getElementById('edit_subject_id');
  var codeField = document.getElementById('code_edit');
  var nameEnField = document.getElementById('name_en_edit');
  var nameArField = document.getElementById('name_ar_edit');
  var form = document.getElementById('editSubjectForm');

  document.querySelectorAll('[data-subject-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-subject-id');
      wrapper.classList.remove('d-none');
      idField.value = id;
      codeField.value = btn.getAttribute('data-subject-code');
      nameEnField.value = btn.getAttribute('data-subject-name-en');
      nameArField.value = btn.getAttribute('data-subject-name-ar');
      form.action = '/subjects/' + id;
      editTab.click();
    });
  });
});
