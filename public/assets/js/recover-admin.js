(function () {
  'use strict';

  var form = document.querySelector('[data-recovery-form]');
  if (!form) return;

  var token = form.querySelector('#token');
  var password = form.querySelector('#password');
  var confirm = form.querySelector('#password_confirm');
  var meter = form.querySelector('[data-password-meter]');
  var strength = form.querySelector('[data-password-strength]');
  var lengthHint = form.querySelector('[data-password-length]');
  var matchHint = form.querySelector('[data-password-match]');

  function formatToken(value) {
    var raw = value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 20);
    return raw.match(/.{1,4}/g)?.join('-') || '';
  }

  if (token) {
    token.value = formatToken(token.value);
    token.addEventListener('input', function () {
      var formatted = formatToken(token.value);
      if (token.value !== formatted) token.value = formatted;
    });
  }

  document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    var field = document.getElementById(button.dataset.passwordToggle || '');
    if (!field) return;

    button.addEventListener('click', function () {
      var reveal = field.type === 'password';
      field.type = reveal ? 'text' : 'password';
      var label = reveal ? button.dataset.hideLabel : button.dataset.showLabel;
      if (label) button.setAttribute('aria-label', label);
      button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      field.focus({ preventScroll: true });
    });
  });

  function scorePassword(value) {
    if (!value) return 0;
    var score = 0;
    if (value.length >= 8) score++;
    if (value.length >= 12) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    return Math.min(score, 4);
  }

  function updatePasswordGuidance() {
    if (!password || !confirm) return;
    var value = password.value;
    var score = scorePassword(value);
    var levels = ['empty', 'weak', 'fair', 'good', 'strong'];
    var level = levels[score];

    if (meter) {
      meter.className = '';
      if (value) meter.classList.add('is-' + level);
    }
    if (strength) {
      strength.textContent = strength.dataset[level] || '';
      strength.className = value ? 'is-' + level : '';
    }
    if (lengthHint) {
      lengthHint.textContent = value.length >= 12
        ? (lengthHint.dataset.recommended || '')
        : (lengthHint.dataset.minimum || '');
      lengthHint.className = value.length >= 8 ? 'is-valid' : '';
    }

    if (!confirm.value) {
      confirm.setCustomValidity('');
      if (matchHint) {
        matchHint.textContent = matchHint.dataset.waiting || '';
        matchHint.className = 'n-field-help';
      }
      return;
    }

    var matches = value === confirm.value;
    confirm.setCustomValidity(matches ? '' : (matchHint?.dataset.mismatch || 'Passwords do not match.'));
    if (matchHint) {
      matchHint.textContent = matches ? (matchHint.dataset.match || '') : (matchHint.dataset.mismatch || '');
      matchHint.className = 'n-field-help ' + (matches ? 'is-valid' : 'is-invalid');
    }
  }

  password?.addEventListener('input', updatePasswordGuidance);
  confirm?.addEventListener('input', updatePasswordGuidance);
  updatePasswordGuidance();

  var status = document.querySelector('[data-recovery-expiry]');
  var countdown = status?.querySelector('[data-recovery-countdown]');
  if (status && countdown) {
    var expiresAt = Number(status.dataset.recoveryExpiry) * 1000;
    var timer = null;
    var renderCountdown = function () {
      var remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
      var minutes = Math.floor(remaining / 60);
      var seconds = String(remaining % 60).padStart(2, '0');
      countdown.textContent = String(minutes).padStart(2, '0') + ':' + seconds;

      if (remaining === 0) {
        if (timer !== null) window.clearInterval(timer);
        status.classList.remove('is-active');
        status.classList.add('is-expired');
        var title = status.querySelector('.n-recovery-status-copy strong');
        var text = status.querySelector('.n-recovery-status-copy > span');
        if (title && status.dataset.expiredTitle) title.textContent = status.dataset.expiredTitle;
        if (text && status.dataset.expiredText) text.textContent = status.dataset.expiredText;
      }
    };
    renderCountdown();
    timer = window.setInterval(renderCountdown, 1000);
  }
})();
