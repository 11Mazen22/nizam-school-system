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
        el.classList.remove('show');
        setTimeout(function() { alert.close(); }, 300);
      } else {
        el.remove();
      }
    }, 5000);
  });

  if (!reduceMotion) {
    document.querySelectorAll('.n-reveal-group').forEach(function (group) {
      Array.prototype.forEach.call(group.children, function (child, i) {
        child.style.setProperty('--n-i', i);
        child.classList.add('n-reveal-stagger');
      });
    });
  }

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

  document.querySelectorAll('[data-print-report]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  document.querySelectorAll('[data-history-back]').forEach(function (btn) {
    btn.addEventListener('click', function () { history.back(); });
  });

  document.querySelectorAll('[data-tab-switch]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.getElementById(btn.getAttribute('data-tab-switch'));
      if (target) { target.click(); }
    });
  });

  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
      var backdrop = e.target.querySelector('.modal-dialog');
      if (backdrop && !backdrop.contains(e.target)) {
        var modalInstance = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(e.target) : null;
        if (modalInstance) { modalInstance.hide(); }
      }
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      var openModal = document.querySelector('.modal.show');
      if (openModal) {
        var modalInstance = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(openModal) : null;
        if (modalInstance) { modalInstance.hide(); }
      }
    }
  });

  document.addEventListener('hidden.bs.modal', function (e) {
    setTimeout(function () {
      var orphanedBackdrops = document.querySelectorAll('.modal-backdrop');
      if (orphanedBackdrops.length > 0 && !document.querySelector('.modal.show')) {
        orphanedBackdrops.forEach(function (backdrop) { backdrop.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
      }
    }, 100);
  });

  document.querySelectorAll('.modal').forEach(function (modalEl) {
    if (modalEl.parentElement !== document.body) {
      document.body.appendChild(modalEl);
    }
    if (!modalEl.hasAttribute('data-bs-backdrop')) {
      modalEl.setAttribute('data-bs-backdrop', 'true');
    }
    if (!modalEl.hasAttribute('data-bs-keyboard')) {
      modalEl.setAttribute('data-bs-keyboard', 'true');
    }
  });

  // ---- Service worker (offline PWA) and Offline Queue ------------------
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  }

  window.addEventListener('submit', function(e) {
    if (!navigator.onLine && e.target.method && e.target.method.toUpperCase() === 'POST') {
      e.preventDefault();
      
      var form = e.target;
      var formData = new FormData(form);
      var dataObj = {};
      formData.forEach(function(value, key) { dataObj[key] = value; });
      
      var payload = {
        id: Date.now() + Math.random().toString(36).substr(2, 9),
        url: form.action || window.location.href,
        method: 'POST',
        data: dataObj,
        timestamp: Date.now()
      };
      
      var queue = JSON.parse(localStorage.getItem('nizam_offline_queue') || '[]');
      queue.push(payload);
      localStorage.setItem('nizam_offline_queue', JSON.stringify(queue));
      
      alert('أنت غير متصل بالشبكة. تم حفظ البيانات محلياً وستتم مزامنتها تلقائياً عند عودة الاتصال.');
      
      var modalEl = form.closest('.modal');
      if (modalEl) {
        var modalInstance = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(modalEl) : null;
        if (modalInstance) modalInstance.hide();
      }
    }
  });

  function refreshCsrfToken() {
    return fetch(window.location.href, { method: 'GET', headers: { 'Accept': 'text/html' } })
      .then(function(res) { return res.text(); })
      .then(function(html) {
        var match = html.match(/name="_csrf_token" value="([^"]+)"/);
        return match ? match[1] : null;
      });
  }

  // Flush queue when back online or on page load if online
  function flushOfflineQueue() {
    if (!navigator.onLine) return;
    
    var queue = JSON.parse(localStorage.getItem('nizam_offline_queue') || '[]');
    if (queue.length === 0) return;

    var host = document.querySelector('.n-toast-host');
    if (host) {
      var alertDiv = document.createElement('div');
      alertDiv.className = 'alert alert-info alert-dismissible fade show';
      alertDiv.innerHTML = 'عاد الاتصال. يتم الآن مزامنة البيانات المحفوظة...';
      host.appendChild(alertDiv);
    }
    
    var syncNext = function() {
      if (queue.length === 0) {
        localStorage.removeItem('nizam_offline_queue');
        alert('تمت مزامنة جميع البيانات المحفوظة بنجاح.');
        setTimeout(function() { window.location.reload(); }, 500);
        return;
      }
      
      var item = queue.shift();
      var formParams = new URLSearchParams();
      for (var key in item.data) {
        formParams.append(key, item.data[key]);
      }
      
      fetch(item.url, {
        method: item.method,
        body: formParams,
        headers: { 
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).then(function(res) {
        if (res.ok) {
          localStorage.setItem('nizam_offline_queue', JSON.stringify(queue));
          syncNext();
          return;
        }
        
        if (res.status === 419 || res.status === 403) {
          refreshCsrfToken().then(function(newToken) {
            if (newToken) {
              item.data['_csrf_token'] = newToken;
              queue.unshift(item); 
              localStorage.setItem('nizam_offline_queue', JSON.stringify(queue));
              syncNext();
            } else {
              alert('انتهت الجلسة. يرجى تسجيل الدخول مرة أخرى لمزامنة البيانات.');
              queue.unshift(item);
              localStorage.setItem('nizam_offline_queue', JSON.stringify(queue));
              window.location.href = '/login';
            }
          });
          return;
        }
        
        if (res.status >= 400 && res.status < 500) {
          // Client error (e.g. 400 Validation, 404 Not Found, 409 Conflict)
          // It will permanently fail, do not block the queue.
          alert('فشلت مزامنة إحدى العمليات بسبب خطأ في البيانات. سيتم تخطيها.');
          localStorage.setItem('nizam_offline_queue', JSON.stringify(queue));
          syncNext();
          return;
        }
        
        throw new Error('Server error: ' + res.status);
      }).catch(function(err) {
        queue.unshift(item);
        localStorage.setItem('nizam_offline_queue', JSON.stringify(queue));
      });
    };
    
    syncNext();
  }

  window.addEventListener('online', flushOfflineQueue);
  flushOfflineQueue(); // also try on initial load
});
