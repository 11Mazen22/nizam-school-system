/*
 * Nizam -- small shared app behaviors. Bootstrap's own components
 * (offcanvas sidebar, dropdowns, modals) already work via their data-bs-*
 * attributes with no custom JS needed. This file only covers what they
 * don't: flash messages presented as a toast stack, and a light entrance
 * animation for repeated content (stat cards, table rows) -- both pure
 * progressive enhancement, the page is fully correct and usable if this
 * file fails to load for any reason.
 */
document.addEventListener('DOMContentLoaded', function () {
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---- Flash messages as a toast stack -------------------------------
  // The server renders each flash as a normal Bootstrap .alert inside
  // <main> (so it's already correct with zero JS); this only relocates
  // them into a fixed, top-corner stack so they read as toasts instead of
  // pushing page content down. Auto-dismiss timing/logic is unchanged.
  var flashes = document.querySelectorAll('main.nizam-content > .alert');
  if (flashes.length) {
    var host = document.createElement('div');
    host.className = 'n-toast-host';
    host.setAttribute('aria-live', 'polite');
    document.body.appendChild(host);
    flashes.forEach(function (el, i) {
      host.appendChild(el);
      if (!reduceMotion) {
        el.style.animation = 'n-toast-in ' + (220) + 'ms cubic-bezier(0,0,.2,1) both';
        el.style.animationDelay = (i * 60) + 'ms';
      }
    });
  }

  document.querySelectorAll('[data-flash-autodismiss]').forEach(function (el) {
    setTimeout(function () {
      var alert = window.bootstrap && window.bootstrap.Alert
        ? window.bootstrap.Alert.getOrCreateInstance(el)
        : null;
      if (alert) {
        // Trigger fade out animation before closing
        el.classList.remove('show');
        setTimeout(function() {
          alert.close();
        }, 300); // Wait for fade animation to complete
      } else {
        el.remove();
      }
    }, 5000);
  });

  // ---- Staggered entrance for repeated content ------------------------
  // Sets --n-i on siblings sharing a `.n-reveal-group` container so the
  // CSS animation (.n-reveal-stagger, app.css) delays each one slightly --
  // a "cards settling into place" feel instead of everything popping at
  // once. No-op under reduced motion (CSS itself also collapses the
  // animation duration to ~0, this just skips the extra work).
  if (!reduceMotion) {
    document.querySelectorAll('.n-reveal-group').forEach(function (group) {
      Array.prototype.forEach.call(group.children, function (child, i) {
        child.style.setProperty('--n-i', i);
        child.classList.add('n-reveal-stagger');
      });
    });
  }

  // ---- Animated stat counters ------------------------------------------
  // Purely cosmetic count-up for .stat-value numbers on first paint --
  // reads the already-rendered final value from the markup, so the number
  // is 100% correct even if JS never runs (this only re-animates toward
  // the same value, never computes it).
  if (!reduceMotion) {
    document.querySelectorAll('[data-count-up]').forEach(function (el) {
      var target = parseInt(el.textContent, 10);
      if (isNaN(target)) { return; }
      var start = null;
      var duration = 560;
      el.textContent = '0';
      function step(ts) {
        if (start === null) { start = ts; }
        var progress = Math.min((ts - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.round(eased * target).toString();
        if (progress < 1) {
          window.requestAnimationFrame(step);
        }
      }
      window.requestAnimationFrame(step);
    });
  }

  // ---- Print-report buttons --------------------------------------------
  document.querySelectorAll('[data-print-report]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      window.print();
    });
  });

  // ---- "Go back" buttons (e.g. the 419 expired-session page) ------------
  document.querySelectorAll('[data-history-back]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      history.back();
    });
  });

  // ---- Generic "switch to this Bootstrap tab" trigger -------------------
  // Lets a button outside the nav-tabs bar (a cancel button, an empty-state
  // CTA) activate a tab by id, e.g. data-tab-switch="list-tab".
  document.querySelectorAll('[data-tab-switch]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.getElementById(btn.getAttribute('data-tab-switch'));
      if (target) { target.click(); }
    });
  });
});
