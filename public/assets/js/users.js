document.addEventListener('DOMContentLoaded', function () {
  var wrapper = document.getElementById('edit-tab-wrapper');
  var editTab = document.getElementById('edit-tab');
  var usernameField = document.getElementById('username_edit');
  var fullNameField = document.getElementById('full_name_edit');
  var roleField = document.getElementById('role_code_edit');
  var passwordField = document.getElementById('password_edit');
  var form = document.getElementById('editUserForm');

  document.querySelectorAll('[data-user-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-user-id');
      wrapper.classList.remove('d-none');
      usernameField.value = btn.getAttribute('data-user-username');
      fullNameField.value = btn.getAttribute('data-user-full-name');
      roleField.value = btn.getAttribute('data-user-role');
      passwordField.value = '';
      form.action = '/users/' + id;
      editTab.click();
    });
  });
});
