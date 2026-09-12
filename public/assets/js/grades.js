document.addEventListener('DOMContentLoaded', function () {
  var wrapper = document.getElementById('edit-tab-wrapper');
  var editTab = document.getElementById('edit-tab');
  var idField = document.getElementById('edit_grade_id');
  var nameEnField = document.getElementById('name_en_edit');
  var nameArField = document.getElementById('name_ar_edit');
  var sortField = document.getElementById('sort_order_edit');
  var form = document.getElementById('editGradeForm');

  document.querySelectorAll('[data-grade-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-grade-id');
      wrapper.classList.remove('d-none');
      idField.value = id;
      nameEnField.value = btn.getAttribute('data-grade-name-en');
      nameArField.value = btn.getAttribute('data-grade-name-ar');
      sortField.value = btn.getAttribute('data-grade-sort-order');
      form.action = '/grades/' + id;
      editTab.click();
    });
  });
});
