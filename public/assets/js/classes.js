document.addEventListener('DOMContentLoaded', function () {
  var wrapper = document.getElementById('edit-tab-wrapper');
  var editTab = document.getElementById('edit-tab');
  var gradeField = document.getElementById('grade_id_edit');
  var nameField = document.getElementById('name_edit');
  var capacityField = document.getElementById('capacity_edit');
  var form = document.getElementById('editClassForm');

  document.querySelectorAll('[data-class-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-class-id');
      wrapper.classList.remove('d-none');
      gradeField.value = btn.getAttribute('data-class-grade-label');
      nameField.value = btn.getAttribute('data-class-name');
      capacityField.value = btn.getAttribute('data-class-capacity');
      form.action = '/classes/' + id;
      editTab.click();
    });
  });
});
