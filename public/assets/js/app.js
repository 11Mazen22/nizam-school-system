/*
 * Nizam -- small shared app behaviors. Bootstrap's own components
 * (offcanvas sidebar, dropdowns) already work via their data-bs-* attributes
 * with no custom JS needed. This file only covers what they don't.
 */
document.addEventListener('DOMContentLoaded', function () {
  // Auto-dismiss success flash messages after a few seconds; error/warning
  // messages stay until the user closes them (a mistake shouldn't vanish
  // before it's read -- same rule the blueprint's report catalog already
  // applies to toasts).
  document.querySelectorAll('[data-flash-autodismiss]').forEach(function (el) {
    setTimeout(function () {
      var alert = window.bootstrap && window.bootstrap.Alert
        ? window.bootstrap.Alert.getOrCreateInstance(el)
        : null;
      if (alert) {
        alert.close();
      } else {
        el.remove();
      }
    }, 5000);
  });
});
