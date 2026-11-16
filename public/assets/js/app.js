/* Nizam shared product interactions. No external runtime dependencies. */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    initFlashMessages(reduceMotion);
    initRevealGroups(reduceMotion);
    initCountUp(reduceMotion);
    initCommonActions();
    initSidebar();
    initDensity();
    initCommandPalette(reduceMotion);
    initNotificationMenu();
    initTableFilters();
    initUnsavedGuards();
    initLoadingForms();
    initCopyButtons();
    initHashTabs();
    normalizeModals();
    initLogoutCachePurge();

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(function () {
        // The application remains fully usable without service-worker support.
      });
    }
  });

  /**
   * Sign-out purges the service worker's dynamic page cache. Those cached
   * pages contain the leaving user's personal data, and on a shared school
   * computer the next person must never be able to open them -- online or
   * offline. Static assets stay cached, so the app keeps working offline.
   */
  function initLogoutCachePurge() {
    var form = document.querySelector('form[action="/logout"]');
    if (!form) return;

    form.addEventListener('submit', function () {
      try {
        if ('caches' in window) {
          caches.keys().then(function (keys) {
            keys.forEach(function (key) {
              if (key.indexOf('dynamic') !== -1) {
                caches.delete(key);
              }
            });
          }).catch(function () {});
        }
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
          navigator.serviceWorker.controller.postMessage({ action: 'clearDynamic' });
        } else if ('serviceWorker' in navigator) {
          navigator.serviceWorker.ready.then(function (registration) {
            if (registration.active) registration.active.postMessage({ action: 'clearDynamic' });
          }).catch(function () { /* offline sign-out still works */ });
        }
      } catch (error) {
        // Cache purging is a privacy nicety; it must never block sign-out.
      }
    });
  }

  /** Copy small, explicitly marked values without exposing them in a URL. */
  function initCopyButtons() {
    document.querySelectorAll('[data-copy-text]').forEach(function (button) {
      button.addEventListener('click', function () {
        var value = button.dataset.copyText || '';
        if (!value) return;

        var copy = navigator.clipboard && window.isSecureContext
          ? navigator.clipboard.writeText(value)
          : new Promise(function (resolve, reject) {
              var input = document.createElement('textarea');
              input.value = value;
              input.setAttribute('readonly', '');
              input.style.position = 'fixed';
              input.style.opacity = '0';
              document.body.appendChild(input);
              input.select();
              try {
                document.execCommand('copy') ? resolve() : reject(new Error('copy_failed'));
              } catch (error) {
                reject(error);
              }
              input.remove();
            });

        copy.then(function () {
          var label = button.querySelector('span');
          if (!label) return;
          label.textContent = button.dataset.copiedLabel || label.textContent;
          button.classList.add('is-copied');
          window.setTimeout(function () {
            label.textContent = button.dataset.copyLabel || label.textContent;
            button.classList.remove('is-copied');
          }, 1600);
        }).catch(function () {
          // Copy is a convenience. The visible text remains selectable.
        });
      });
    });
  }

  function initFlashMessages(reduceMotion) {
    var flashes = document.querySelectorAll('main.nizam-content > .alert');
    if (flashes.length) {
      var host = document.createElement('div');
      host.className = 'n-toast-host';
      host.setAttribute('aria-live', 'polite');
      host.setAttribute('aria-atomic', 'true');
      document.body.appendChild(host);
      flashes.forEach(function (el, index) {
        host.appendChild(el);
        if (!reduceMotion) {
          el.style.animationDelay = String(index * 50) + 'ms';
          el.classList.add('n-toast-enter');
        }
      });
    }

    document.querySelectorAll('[data-flash-autodismiss]').forEach(function (el) {
      window.setTimeout(function () {
        if (window.bootstrap && window.bootstrap.Alert) {
          window.bootstrap.Alert.getOrCreateInstance(el).close();
        } else {
          el.remove();
        }
      }, 5000);
    });
  }

  function initRevealGroups(reduceMotion) {
    if (reduceMotion) return;
    document.querySelectorAll('.n-reveal-group').forEach(function (group) {
      Array.prototype.forEach.call(group.children, function (child, index) {
        child.style.setProperty('--n-stagger', String(index));
        child.classList.add('n-reveal-item');
      });
    });
  }

  function initCountUp(reduceMotion) {
    if (reduceMotion) return;
    document.querySelectorAll('[data-count-up]').forEach(function (el) {
      var raw = el.textContent.trim();
      var target = parseInt(raw, 10);
      if (isNaN(target) || target < 0) return;
      var suffix = raw.replace(String(target), '');
      var started = null;
      var duration = 480;
      var finished = false;
      el.textContent = '0' + suffix;

      function finish() {
        if (finished) return;
        finished = true;
        el.textContent = String(target) + suffix;
      }

      function frame(time) {
        if (finished) return;
        if (started === null) started = time;
        var progress = Math.min((time - started) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = String(Math.round(target * eased)) + suffix;
        if (progress < 1) window.requestAnimationFrame(frame);
        else finish();
      }

      window.requestAnimationFrame(frame);
      window.setTimeout(finish, duration + 250);
    });
  }

  function initCommonActions() {
    document.querySelectorAll('[data-print-report]').forEach(function (button) {
      button.addEventListener('click', function () { window.print(); });
    });
    document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
      control.addEventListener('change', function () {
        if (!control.form) return;
        if (typeof control.form.requestSubmit === 'function') control.form.requestSubmit();
        else control.form.submit();
      });
    });
    document.querySelectorAll('[data-history-back]').forEach(function (button) {
      button.addEventListener('click', function () { window.history.back(); });
    });
    document.querySelectorAll('[data-tab-switch]').forEach(function (button) {
      button.addEventListener('click', function () {
        var target = document.getElementById(button.getAttribute('data-tab-switch'));
        if (target) target.click();
      });
    });
  }

  function initSidebar() {
    var shell = document.getElementById('nizamShell');
    var toggle = document.getElementById('sidebarToggle');
    var collapseBtn = document.getElementById('sidebarCollapseBtn');
    if (!shell) return;

    var collapsed = localStorage.getItem('nizam_sidebar_collapsed') === '1';
    setCollapsed(collapsed);

    if (toggle) {
      toggle.addEventListener('click', function () {
        collapsed = !shell.classList.contains('n-sidebar-collapsed');
        setCollapsed(collapsed);
        localStorage.setItem('nizam_sidebar_collapsed', collapsed ? '1' : '0');
      });
    }

    if (collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        collapsed = !shell.classList.contains('n-sidebar-collapsed');
        setCollapsed(collapsed);
        localStorage.setItem('nizam_sidebar_collapsed', collapsed ? '1' : '0');
      });
    }

    function setCollapsed(value) {
      shell.classList.toggle('n-sidebar-collapsed', value);
      if (toggle) {
        toggle.setAttribute('aria-expanded', value ? 'false' : 'true');
        toggle.setAttribute('aria-label', value ? toggle.dataset.labelExpand : toggle.dataset.labelCollapse);
      }
      if (collapseBtn) {
        collapseBtn.setAttribute('aria-expanded', value ? 'false' : 'true');
      }
    }

    // Group Accordions with persistence
    var savedGroups = [];
    try {
      savedGroups = JSON.parse(localStorage.getItem('nizam_collapsed_groups') || '[]');
    } catch (e) {
      savedGroups = [];
    }

    var navGroups = document.querySelectorAll('.n-nav-group');
    navGroups.forEach(function (groupEl) {
      var groupKey = groupEl.getAttribute('data-group');
      var headerBtn = groupEl.querySelector('.n-nav-group-header');
      var hasActive = groupEl.classList.contains('n-group-has-active');

      if (!hasActive && groupKey && savedGroups.indexOf(groupKey) !== -1) {
        groupEl.classList.add('is-collapsed');
        if (headerBtn) {
          headerBtn.setAttribute('aria-expanded', 'false');
          if (headerBtn.dataset.labelExpand) {
            headerBtn.setAttribute('title', headerBtn.dataset.labelExpand);
          }
        }
      }

      if (headerBtn) {
        headerBtn.addEventListener('click', function () {
          var isCollapsed = groupEl.classList.toggle('is-collapsed');
          headerBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
          var label = isCollapsed ? headerBtn.dataset.labelExpand : headerBtn.dataset.labelCollapse;
          if (label) headerBtn.setAttribute('title', label);

          if (groupKey) {
            var idx = savedGroups.indexOf(groupKey);
            if (isCollapsed && idx === -1) {
              savedGroups.push(groupKey);
            } else if (!isCollapsed && idx !== -1) {
              savedGroups.splice(idx, 1);
            }
            try {
              localStorage.setItem('nizam_collapsed_groups', JSON.stringify(savedGroups));
            } catch (e) {}
          }
        });
      }
    });

    // Real-time navigation filter
    var filterInput = document.getElementById('sidebarNavFilter');
    var filterClear = document.getElementById('sidebarFilterClear');
    var filterEmpty = document.getElementById('sidebarFilterEmpty');
    var sidebarNav = document.getElementById('sidebarNavScroll');

    if (filterInput && sidebarNav) {
      filterInput.addEventListener('input', applyFilter);

      if (filterClear) {
        filterClear.addEventListener('click', function () {
          filterInput.value = '';
          applyFilter();
          filterInput.focus();
        });
      }

      filterInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          filterInput.value = '';
          applyFilter();
          filterInput.blur();
        }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
          var activeTag = (document.activeElement && document.activeElement.tagName) || '';
          if (activeTag !== 'INPUT' && activeTag !== 'TEXTAREA' && !document.activeElement.isContentEditable) {
            e.preventDefault();
            filterInput.focus();
            filterInput.select();
          }
        }
      });
    }

    function applyFilter() {
      var query = filterInput.value.trim().toLowerCase();
      if (filterClear) filterClear.hidden = query === '';

      var totalVisible = 0;
      navGroups.forEach(function (groupEl) {
        var groupKey = groupEl.getAttribute('data-group');
        var links = groupEl.querySelectorAll('.nav-link');
        var groupMatches = 0;

        links.forEach(function (link) {
          var searchTxt = link.getAttribute('data-nav-search') || link.textContent.trim().toLowerCase();
          var item = link.closest('.nav-item');
          if (query === '' || searchTxt.indexOf(query) !== -1) {
            if (item) item.style.display = '';
            groupMatches++;
          } else {
            if (item) item.style.display = 'none';
          }
        });

        if (query === '') {
          groupEl.style.display = '';
          var wasCollapsed = savedGroups.indexOf(groupKey) !== -1 && !groupEl.classList.contains('n-group-has-active');
          groupEl.classList.toggle('is-collapsed', wasCollapsed);
          var hBtn = groupEl.querySelector('.n-nav-group-header');
          if (hBtn) hBtn.setAttribute('aria-expanded', wasCollapsed ? 'false' : 'true');
        } else if (groupMatches > 0) {
          groupEl.style.display = '';
          groupEl.classList.remove('is-collapsed');
          totalVisible += groupMatches;
        } else {
          groupEl.style.display = 'none';
        }
      });

      if (filterEmpty) {
        filterEmpty.hidden = query === '' || totalVisible > 0;
      }
    }
  }

  function initDensity() {
    var toggle = document.getElementById('densityToggle');
    var compact = localStorage.getItem('nizam_density') === 'compact';
    setDensity(compact);
    if (!toggle) return;
    toggle.addEventListener('click', function () {
      compact = !document.body.classList.contains('n-density-compact');
      setDensity(compact);
      localStorage.setItem('nizam_density', compact ? 'compact' : 'comfortable');
    });

    function setDensity(value) {
      document.body.classList.toggle('n-density-compact', value);
      document.querySelectorAll('[data-density-label]').forEach(function (label) {
        label.textContent = value ? toggle && toggle.dataset.compact || 'Compact' : toggle && toggle.dataset.comfortable || 'Comfortable';
      });
    }
  }

  function initCommandPalette(reduceMotion) {
    var overlay = document.getElementById('nizamCommandPalette');
    var input = document.getElementById('nizamCommandInput');
    var empty = document.getElementById('nizamCommandEmpty');
    if (!overlay || !input) return;

    var lastFocused = null;
    var visibleItems = [];
    var selected = 0;

    document.querySelectorAll('[data-command-open]').forEach(function (button) {
      button.addEventListener('click', open);
    });

    document.addEventListener('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        overlay.hidden ? open() : close();
        return;
      }
      if (!overlay.hidden && event.key === 'Escape') {
        event.preventDefault();
        close();
      }
    });

    overlay.addEventListener('mousedown', function (event) {
      if (event.target === overlay) close();
    });

    input.addEventListener('input', filter);
    input.addEventListener('keydown', function (event) {
      if (!visibleItems.length) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        selected = (selected + 1) % visibleItems.length;
        updateSelection();
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        selected = (selected - 1 + visibleItems.length) % visibleItems.length;
        updateSelection();
      } else if (event.key === 'Enter') {
        event.preventDefault();
        visibleItems[selected].click();
      }
    });

    function open() {
      lastFocused = document.activeElement;
      overlay.hidden = false;
      document.body.classList.add('n-command-open');
      if (!reduceMotion) window.requestAnimationFrame(function () { overlay.classList.add('show'); });
      else overlay.classList.add('show');
      input.value = '';
      filter();
      window.setTimeout(function () { input.focus(); }, 0);
    }

    function close() {
      overlay.classList.remove('show');
      document.body.classList.remove('n-command-open');
      var finish = function () {
        overlay.hidden = true;
        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
      };
      if (reduceMotion) finish();
      else window.setTimeout(finish, 150);
    }

    function filter() {
      var query = input.value.trim().toLocaleLowerCase();
      visibleItems = [];
      overlay.querySelectorAll('[data-command-item]').forEach(function (item) {
        var matches = !query || (item.dataset.search || item.textContent).toLocaleLowerCase().indexOf(query) !== -1;
        item.hidden = !matches;
        item.classList.remove('selected');
        if (matches) visibleItems.push(item);
      });
      overlay.querySelectorAll('[data-command-group]').forEach(function (group) {
        group.hidden = !group.querySelector('[data-command-item]:not([hidden])');
      });
      selected = 0;
      if (empty) empty.hidden = visibleItems.length !== 0;
      updateSelection();
    }

    function updateSelection() {
      visibleItems.forEach(function (item, index) {
        item.classList.toggle('selected', index === selected);
      });
      if (visibleItems[selected]) visibleItems[selected].scrollIntoView({ block: 'nearest' });
    }
  }

  function initNotificationMenu() {
    var wrapper = document.getElementById('notif-bell-wrapper');
    var badge = document.getElementById('notif-badge');
    var list = document.getElementById('notif-list');
    var markAll = document.getElementById('notif-markall-link');
    var bell = document.getElementById('notifBellBtn');
    if (!wrapper || !badge || !list) return;

    function poll() {
      return fetch('/notifications/api', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (response) {
          if (!response.ok) throw new Error('Notification request failed');
          return response.json();
        })
        .then(render)
        .catch(function () {
          // Notifications are non-critical; keep the rest of the shell quiet.
        });
    }

    function render(data) {
      var unread = Number(data.unread || 0);
      badge.hidden = unread === 0;
      badge.textContent = unread > 99 ? '99+' : String(unread);
      if (markAll) markAll.hidden = unread === 0;

      list.textContent = '';
      var items = Array.isArray(data.items) ? data.items : [];
      if (!items.length) {
        var empty = document.createElement('li');
        empty.className = 'n-notification-menu-empty';
        empty.textContent = wrapper.dataset.emptyLabel || '';
        list.appendChild(empty);
        return;
      }

      items.forEach(function (notification) {
        var li = document.createElement('li');
        var element = notification.link ? document.createElement('a') : document.createElement('div');
        if (notification.link) element.href = notification.link;
        element.className = 'n-notification-menu-item' + (notification.is_read ? '' : ' unread');

        var title = document.createElement('div');
        title.className = 'n-notification-menu-title';
        var titleText = document.createElement('span');
        titleText.textContent = notification.title || '';
        title.appendChild(titleText);
        if (!notification.is_read) title.appendChild(makeBadge(wrapper.dataset.newLabel, 'primary'));
        if (notification.priority === 'high') title.appendChild(makeBadge(wrapper.dataset.priorityLabel, 'danger'));

        var body = document.createElement('p');
        body.textContent = notification.body || '';
        var time = document.createElement('time');
        time.textContent = relativeTime(notification.created_at);

        element.appendChild(title);
        element.appendChild(body);
        element.appendChild(time);
        li.appendChild(element);
        list.appendChild(li);
      });
    }

    function makeBadge(text, type) {
      var span = document.createElement('span');
      span.className = 'n-mini-badge ' + type;
      span.textContent = text || '';
      return span;
    }

    function relativeTime(value) {
      var date = new Date(value);
      if (isNaN(date.getTime())) return value || '';
      var minutes = Math.floor((Date.now() - date.getTime()) / 60000);
      if (minutes < 1) return wrapper.dataset.justNow || '';
      if (minutes < 60) return String(minutes) + ' ' + (wrapper.dataset.minutesAgo || '');
      var hours = Math.floor(minutes / 60);
      if (hours < 24) return String(hours) + ' ' + (wrapper.dataset.hoursAgo || '');
      var days = Math.floor(hours / 24);
      if (days < 7) return String(days) + ' ' + (wrapper.dataset.daysAgo || '');
      return new Intl.DateTimeFormat(wrapper.dataset.locale === 'ar' ? 'ar-EG' : 'en', { dateStyle: 'medium' }).format(date);
    }

    if (markAll) {
      markAll.addEventListener('click', function () {
        var token = document.querySelector('input[name="_csrf_token"]');
        if (!token) return;
        markAll.disabled = true;
        fetch('/notifications/read-all', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: '_csrf_token=' + encodeURIComponent(token.value)
        }).then(function (response) {
          if (!response.ok) throw new Error('Mark all failed');
          return poll();
        }).finally(function () { markAll.disabled = false; });
      });
    }

    if (bell) bell.addEventListener('show.bs.dropdown', poll);
    poll();
    window.setInterval(poll, 60000);
  }

  function initTableFilters() {
    document.querySelectorAll('[data-table-filter]').forEach(function (input) {
      var target = document.querySelector(input.dataset.tableFilter);
      if (!target) return;
      var rows = target.querySelectorAll('tbody tr');
      input.addEventListener('input', function () {
        var query = input.value.trim().toLocaleLowerCase();
        rows.forEach(function (row) {
          row.hidden = !!query && row.textContent.toLocaleLowerCase().indexOf(query) === -1;
        });
      });
    });
  }

  function initUnsavedGuards() {
    var dirty = false;
    document.querySelectorAll('form[data-unsaved-guard]').forEach(function (form) {
      form.addEventListener('input', function () { dirty = true; });
      form.addEventListener('change', function () { dirty = true; });
      form.addEventListener('submit', function () { dirty = false; });
    });
    window.addEventListener('beforeunload', function (event) {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
  }

  function initLoadingForms() {
    document.querySelectorAll('form[data-loading-submit]').forEach(function (form) {
      form.addEventListener('submit', function () {
        if (!form.checkValidity()) return;
        var button = form.querySelector('[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        var spinner = document.createElement('span');
        spinner.className = 'n-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        button.prepend(spinner);
      });
    });
  }

  function initHashTabs() {
    if (!window.location.hash || !window.bootstrap || !window.bootstrap.Tab) return;
    var pane = document.querySelector(window.location.hash);
    if (!pane) return;
    var trigger = document.querySelector('[data-bs-target="' + window.location.hash.replace(/"/g, '') + '"]');
    if (trigger) window.bootstrap.Tab.getOrCreateInstance(trigger).show();
  }

  function normalizeModals() {
    document.querySelectorAll('.modal').forEach(function (modal) {
      if (modal.parentElement !== document.body) document.body.appendChild(modal);
    });
    document.addEventListener('hidden.bs.modal', function () {
      if (!document.querySelector('.modal.show')) {
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
      }
    });
  }
})();
