/*
 * Nizam -- generic "are you sure" confirmation for any state-changing form,
 * reused across every Phase 6 archive/activate/close/promote action (§L:
 * "every reusable UI element is built once as a component and reused").
 * CSP-safe by construction: no inline onsubmit/onclick handler anywhere --
 * script-src stays 'self' with no relaxation (see app/Response.php). A form
 * opts in with <form data-confirm="message text">; this listens for its
 * submit event, shows the one shared modal (markup lives once in
 * views/layout/start.php), and only actually submits after the user clicks
 * the modal's confirm button -- via form.submit(), which does not itself
 * re-fire the submit event, so there is no re-entrant loop.
 */
document.addEventListener('DOMContentLoaded', function () {
  var modalEl = document.getElementById('nizamConfirmModal');
  if (!modalEl || typeof bootstrap === 'undefined') {
    return;
  }
  var modal = new bootstrap.Modal(modalEl);
  var messageEl = document.getElementById('nizamConfirmMessage');
  var confirmBtn = document.getElementById('nizamConfirmButton');
  var pendingForm = null;

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.tagName === 'FORM' && form.hasAttribute('data-confirm') && !form.hasAttribute('data-confirmed')) {
      event.preventDefault();
      pendingForm = form;
      messageEl.textContent = form.getAttribute('data-confirm');
      modal.show();
    }
  });

  confirmBtn.addEventListener('click', function () {
    modal.hide();
    if (pendingForm) {
      pendingForm.setAttribute('data-confirmed', '1');
      pendingForm.submit();
      pendingForm = null;
    }
  });
});
