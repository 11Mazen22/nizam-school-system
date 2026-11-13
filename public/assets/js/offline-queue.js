/*
 * Nizam offline write queue.
 *
 * A school can lose Wi-Fi mid-lesson. When a normal POST form is submitted
 * while the browser is offline this captures the fields in IndexedDB instead
 * of letting the submission die on the browser's error page, then replays it
 * when the connection returns.
 *
 * Deliberate boundaries:
 *  - Only same-origin POST forms. File uploads cannot be serialised safely
 *    and are left to the normal submit path.
 *  - Never queues sign-in, sign-out, setup, delete, or any form marked
 *    data-offline-queue="off" / carrying a data-confirm.
 *  - Replays carry X-Nizam-Queue-Id; the server answers 204 for a key it has
 *    already processed, so a lost response cannot duplicate a record.
 *  - A fresh CSRF token is fetched immediately before replay. If the session
 *    expired while offline the queue stops and asks the user to sign in
 *    rather than silently dropping their work.
 */
(function () {
  'use strict';

  var DB_NAME = 'nizam-offline-queue';
  var DB_VERSION = 1;
  var STORE = 'submissions';
  var MAX_ITEMS = 25;

  var banner = null;
  var queueCount = 0;
  var flushing = false;

  // Cross-tab coordination. Two tabs share one IndexedDB, so without this both
  // could replay the same item: harmless for data (the server answers 204 to
  // the second copy) but wasteful and confusing, with two banners and two
  // toasts. A flush now runs under one cross-tab lock, and tabs notify each
  // other when the queue changes so their banners stay truthful.
  var TAB_ID = makeId();
  var LOCK_NAME = 'nizam-offline-queue:flush';
  var LOCK_KEY = 'nizam-offline-queue:lock';
  var channel = null;

  try {
    channel = 'BroadcastChannel' in window ? new BroadcastChannel('nizam-offline-queue') : null;
  } catch (error) {
    channel = null; // not fatal; storage events still cover most browsers
  }

  function isEnglish() {
    return (document.documentElement.lang || 'ar').toLowerCase().indexOf('en') === 0;
  }

  function t(ar, en) {
    return isEnglish() ? en : ar;
  }

  function makeId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    var bytes = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(bytes);
    return Array.prototype.map.call(bytes, function (b) {
      return ('0' + b.toString(16)).slice(-2);
    }).join('');
  }

  function openDb() {
    return new Promise(function (resolve, reject) {
      if (!('indexedDB' in window)) {
        reject(new Error('IndexedDB unavailable'));
        return;
      }
      var request = indexedDB.open(DB_NAME, DB_VERSION);
      request.onupgradeneeded = function () {
        var db = request.result;
        if (!db.objectStoreNames.contains(STORE)) {
          db.createObjectStore(STORE, { keyPath: 'id' });
        }
      };
      request.onsuccess = function () { resolve(request.result); };
      request.onerror = function () { reject(request.error); };
    });
  }

  function withStore(mode, operation) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORE, mode);
        var store = tx.objectStore(STORE);
        var request = operation(store);
        tx.oncomplete = function () { resolve(request ? request.result : undefined); };
        tx.onerror = function () { reject(tx.error); };
        tx.onabort = function () { reject(tx.error); };
      });
    });
  }

  function all() {
    return withStore('readonly', function (store) { return store.getAll(); }).catch(function () { return []; });
  }

  function put(item) {
    return withStore('readwrite', function (store) { return store.put(item); });
  }

  function remove(id) {
    return withStore('readwrite', function (store) { return store.delete(id); });
  }

  function count() {
    return withStore('readonly', function (store) { return store.count(); }).catch(function () { return 0; });
  }

  function eligible(form) {
    if (!form || form.getAttribute('data-offline-queue') === 'off') {
      return false;
    }
    if (form.querySelector('input[type="password"], input[type="file"], [data-confirm]')) {
      return false;
    }
    var method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'post') {
      return false;
    }
    var action;
    try {
      action = new URL(form.getAttribute('action') || window.location.pathname, window.location.href);
    } catch (error) {
      return false;
    }
    if (action.origin !== window.location.origin) {
      return false;
    }
    // Destructive or session-changing actions are never replayed later.
    if (/^\/(login|logout|setup)(\/|$)/.test(action.pathname)) {
      return false;
    }
    if (/\/(delete)(\/|$)/.test(action.pathname)) {
      return false;
    }
    return true;
  }

  function arChanges(count) {
    if (count === 1) {
      return 'تغيير واحد محفوظ';
    }
    if (count === 2) {
      return 'تغييران محفوظان';
    }
    if (count >= 3 && count <= 10) {
      return count + ' تغييرات محفوظة';
    }
    return count + ' تغييرًا محفوظًا';
  }

  function enChanges(count) {
    return count + (count === 1 ? ' saved change' : ' saved changes');
  }

  function broadcast() {
    if (channel) {
      try { channel.postMessage({ type: 'changed' }); } catch (error) { /* tab closing */ }
    }
  }

  function refreshBanner() {
    return count().then(function (pending) {
      queueCount = pending;
      if (pending > 0) {
        showBanner(pending, false);
      } else {
        hideBanner();
      }
      renderPanel();
    });
  }

  // One replay at a time across every tab of this origin. The wait is bounded:
  // if another tab has been holding the lock (e.g. stuck on a slow request)
  // this tab backs off and its interval retries later instead of hanging.
  function withFlushLock(task) {
    if (navigator.locks && typeof navigator.locks.request === 'function') {
      var controller = 'AbortController' in window ? new AbortController() : null;
      var timer = controller ? window.setTimeout(function () { controller.abort(); }, 30000) : null;

      return navigator.locks.request(
        LOCK_NAME,
        controller ? { mode: 'exclusive', signal: controller.signal } : { mode: 'exclusive' },
        function () { return task(); }
      ).then(function (result) {
        if (timer) { window.clearTimeout(timer); }
        return result;
      }, function () {
        if (timer) { window.clearTimeout(timer); }
        return null; // another tab owns the lock, or the wait was aborted
      });
    }
    return withLeaseLock(task);
  }

  // Fallback for browsers without Web Locks: a localStorage lease that expires
  // on its own if the holding tab is closed or crashes mid-flush.
  function withLeaseLock(task) {
    var now = Date.now();
    try {
      var existing = JSON.parse(localStorage.getItem(LOCK_KEY) || 'null');
      if (existing && existing.expires > now && existing.id !== TAB_ID) {
        return Promise.resolve(null);
      }
      localStorage.setItem(LOCK_KEY, JSON.stringify({ id: TAB_ID, expires: now + 30000 }));
      var confirmed = JSON.parse(localStorage.getItem(LOCK_KEY) || 'null');
      if (!confirmed || confirmed.id !== TAB_ID) {
        return Promise.resolve(null);
      }
    } catch (error) {
      // Storage blocked (private mode): flush anyway; the server dedupes.
    }

    function release() {
      try {
        var current = JSON.parse(localStorage.getItem(LOCK_KEY) || 'null');
        if (!current || current.id === TAB_ID) {
          localStorage.removeItem(LOCK_KEY);
        }
      } catch (error) { /* nothing to release */ }
    }

    return Promise.resolve().then(task).then(function (result) {
      release();
      return result;
    }, function (error) {
      release();
      throw error;
    });
  }

  function ensureBanner() {
    if (banner) {
      return banner;
    }
    banner = document.createElement('div');
    banner.className = 'n-queue-banner';
    banner.setAttribute('role', 'status');
    banner.setAttribute('aria-live', 'polite');
    banner.hidden = true;
    banner.innerHTML =
      '<span class="n-queue-banner-icon" aria-hidden="true">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
          '<path d="M12 3v12m0 0l4-4m-4 4l-4-4"/><path d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>' +
        '</svg>' +
      '</span>' +
      '<span class="n-queue-banner-text"></span>' +
      '<button type="button" class="n-queue-banner-btn"></button>';
    document.body.appendChild(banner);

    banner.querySelector('.n-queue-banner-btn').addEventListener('click', function () {
      if (banner.dataset.state === 'auth') {
        window.location.href = '/login';
        return;
      }
      flush(true);
    });

    return banner;
  }

  function showBanner(pending, needsAuth) {
    var el = ensureBanner();
    el.hidden = false;
    el.dataset.state = needsAuth ? 'auth' : 'pending';
    el.querySelector('.n-queue-banner-text').textContent = needsAuth
      ? t('سجّل الدخول لتُرسل التغييرات المحفوظة على هذا الجهاز.',
          'Sign in to send the changes saved on this device.')
      : t('لديك ' + arChanges(pending) + ' محليًا. سيُرسل تلقائيًا عند عودة الاتصال.',
          'You have ' + enChanges(pending) + '. ' + (pending === 1 ? 'It will' : 'They will') +
          ' be sent automatically when the connection returns.');
    el.querySelector('.n-queue-banner-btn').textContent = needsAuth
      ? t('تسجيل الدخول', 'Sign in')
      : t('إرسال الآن', 'Send now');
  }

  function hideBanner() {
    if (banner) {
      banner.hidden = true;
    }
  }

  function notify(sent, failed) {
    var host = document.querySelector('.n-toast-host');
    if (!host) {
      host = document.createElement('div');
      host.className = 'n-toast-host';
      host.setAttribute('aria-live', 'polite');
      document.body.appendChild(host);
    }
    var toast = document.createElement('div');
    toast.className = 'alert alert-' + (failed > 0 ? 'warning' : 'success') + ' n-queue-toast';
    toast.textContent = failed > 0
      ? t('تم إرسال ' + arChanges(sent) + '، وتعذّر إرسال ' + arChanges(failed) + '.',
          enChanges(sent) + ' sent, ' + enChanges(failed) + ' could not be sent.')
      : t('تم إرسال ' + arChanges(sent) + ' بنجاح.', enChanges(sent) + ' sent successfully.');
    host.appendChild(toast);
    window.setTimeout(function () { toast.remove(); }, 6000);
  }

  // ------------------------------------------------- pending sends screen
  var panel = null;
  var panelStrings = null;

  function panelEl() {
    if (panel === null) {
      panel = document.getElementById('offline-queue-panel');
    }
    return panel;
  }

  function readPanelStrings() {
    if (panelStrings === null) {
      var host = panelEl();
      try {
        panelStrings = host ? JSON.parse(host.getAttribute('data-queue-i18n') || '{}') : {};
      } catch (error) {
        panelStrings = {};
      }
    }
    return panelStrings;
  }

  function formatWhen(ms) {
    try {
      return new Date(ms).toLocaleString(isEnglish() ? 'en-GB' : 'ar-EG', {
        dateStyle: 'medium',
        timeStyle: 'short'
      });
    } catch (error) {
      return new Date(ms).toLocaleString();
    }
  }

  function renderPanel() {
    var host = panelEl();
    if (!host) {
      return Promise.resolve();
    }
    var strings = readPanelStrings();

    return all().then(function (items) {
      var list = host.querySelector('[data-queue-list]');
      var empty = host.querySelector('[data-queue-empty]');
      var sendAll = host.querySelector('[data-queue-send-all]');
      if (!list) {
        return;
      }

      list.textContent = '';
      if (empty) { empty.hidden = items.length > 0; }
      if (sendAll) { sendAll.disabled = items.length === 0; }

      items.sort(function (a, b) { return (a.createdAt || 0) - (b.createdAt || 0); }).forEach(function (item) {
        var row = document.createElement('li');
        row.className = 'n-queue-row';
        row.setAttribute('data-id', item.id);

        var main = document.createElement('div');
        main.className = 'n-queue-row-main';

        var title = document.createElement('span');
        title.className = 'n-queue-row-title';
        title.textContent = item.action;

        var meta = document.createElement('span');
        meta.className = 'n-queue-row-meta';
        var fieldCount = item.fields ? item.fields.length : 0;
        meta.textContent = (strings.queuedAt || 'Saved') + ' ' + formatWhen(item.createdAt || Date.now()) +
          ' · ' + fieldCount + ' ' + (strings.fields || 'fields');

        main.appendChild(title);
        main.appendChild(meta);

        var actions = document.createElement('div');
        actions.className = 'n-queue-row-actions';

        var retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'btn btn-sm btn-outline-primary';
        retry.setAttribute('data-queue-action', 'retry');
        retry.textContent = strings.retry || 'Retry';

        var discard = document.createElement('button');
        discard.type = 'button';
        discard.className = 'btn btn-sm btn-outline-danger';
        discard.setAttribute('data-queue-action', 'discard');
        discard.textContent = strings.discard || 'Discard';

        actions.appendChild(retry);
        actions.appendChild(discard);

        row.appendChild(main);
        row.appendChild(actions);
        list.appendChild(row);
      });
    });
  }

  function discard(id) {
    return remove(id).then(function () {
      broadcast();
      renderPanel();
      refreshBanner();
    });
  }

  function sendItem(id) {
    if (!navigator.onLine) {
      refreshBanner();
      return Promise.resolve(false);
    }

    return withFlushLock(function () {
      return all().then(function (items) {
        var item = items.filter(function (candidate) { return candidate.id === id; })[0];
        if (!item) {
          return false;
        }
        return freshToken().then(function (token) {
          if (!token) {
            showBanner(items.length, true);
            return false;
          }
          return send(item, token).then(function (result) {
            if (result === 'sent') {
              return remove(id).then(function () { notify(1, 0); broadcast(); return true; });
            }
            if (result === 'auth') {
              showBanner(items.length, true);
              return false;
            }
            if (result === 'network') {
              return false;
            }
            return remove(id).then(function () { notify(0, 1); broadcast(); return true; });
          });
        });
      });
    }).then(function (result) {
      renderPanel();
      refreshBanner();
      return result;
    });
  }

  function freshToken() {
    return fetch('/csrf-token', {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    }).then(function (response) {
      if (!response.ok) {
        return null;
      }
      return response.json().then(function (body) {
        return body && typeof body.token === 'string' ? body.token : null;
      });
    }).catch(function () { return null; });
  }

  function send(item, token) {
    var body = new URLSearchParams();
    item.fields.forEach(function (pair) { body.append(pair[0], pair[1]); });
    body.set('_csrf_token', token);

    // A stalled request must not hold the cross-tab lock forever.
    var controller = 'AbortController' in window ? new AbortController() : null;
    var timer = controller ? window.setTimeout(function () { controller.abort(); }, 20000) : null;

    return fetch(item.action, {
      method: 'POST',
      credentials: 'same-origin',
      redirect: 'follow',
      signal: controller ? controller.signal : undefined,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Nizam-Queue-Id': item.id
      },
      body: body.toString()
    }).then(function (response) {
      if (response.status === 204 || response.ok) {
        return 'sent';
      }
      if (response.status === 419 || (response.url || '').indexOf('/login') !== -1) {
        return 'auth';
      }
      return 'failed';
    }, function () {
      return 'network';
    }).then(function (result) {
      if (timer) { window.clearTimeout(timer); }
      return result;
    });
  }

  function flush(manual) {
    if (flushing || !navigator.onLine) {
      return Promise.resolve();
    }
    flushing = true;

    return withFlushLock(function () {
      return runFlush(manual);
    }).then(function (result) {
      flushing = false;
      if (result !== null) {
        broadcast();
      }
      return result;
    }, function () {
      flushing = false;
    });
  }

  function runFlush(manual) {
    return all().then(function (items) {
      queueCount = items.length;
      if (!items.length) {
        hideBanner();
        return null;
      }

      return freshToken().then(function (token) {
        if (!token && !navigator.onLine) {
          showBanner(items.length, false);
          return null;
        }
        if (!token) {
          showBanner(items.length, true);
          return null;
        }

        var sent = 0;
        var failed = 0;
        var chain = Promise.resolve();

        items.forEach(function (item) {
          chain = chain.then(function (stop) {
            if (stop) {
              return stop;
            }
            return send(item, token).then(function (result) {
              if (result === 'sent') {
                return remove(item.id).then(function () { sent++; return false; });
              }
              if (result === 'auth') {
                showBanner(items.length - sent, true);
                return true;
              }
              if (result === 'network') {
                return true;
              }
              // Permanent client error: drop it and report rather than
              // retrying forever.
              return remove(item.id).then(function () { failed++; return false; });
            });
          });
        });

        return chain.then(function () {
          if (sent > 0 || failed > 0) {
            notify(sent, failed);
          }
          return count().then(function (remaining) {
            queueCount = remaining;
            if (remaining > 0) {
              showBanner(remaining, false);
            } else {
              hideBanner();
            }
          });
        });
      });
    }).catch(function () {
      // IndexedDB unavailable: the normal submit path stays untouched.
    }).then(function () {
      if (manual) {
        count().then(function (remaining) {
          if (remaining === 0) {
            hideBanner();
          }
        });
      }
    });
  }

  function enqueue(item) {
    return count().then(function (existing) {
      queueCount = existing;
      if (existing >= MAX_ITEMS) {
        showBanner(existing, false);
        return false;
      }
      return put(item).then(function () {
        queueCount = existing + 1;
        showBanner(queueCount, false);
        renderPanel();
        broadcast();
        return true;
      });
    }).catch(function () { return false; });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !eligible(form)) {
      return;
    }
    if (navigator.onLine) {
      return; // normal navigation is untouched while connected
    }

    event.preventDefault();
    event.stopPropagation();

    var data = new FormData(form);
    var fields = [];
    data.forEach(function (value, key) {
      if (typeof value === 'string' && key !== '_csrf_token') {
        fields.push([key, value]);
      }
    });

    var target = new URL(form.getAttribute('action') || window.location.pathname, window.location.href);
    enqueue({
      id: makeId(),
      action: target.pathname + (target.search || ''),
      fields: fields,
      createdAt: Date.now(),
      attempts: 0
    });
  }, true);

  // Buttons inside the pending-sends screen (rendered rows included).
  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) {
      return;
    }

    var sendAll = target.closest('[data-queue-send-all]');
    if (sendAll) {
      flush(true).then(renderPanel);
      return;
    }

    var button = target.closest('[data-queue-action]');
    if (!button) {
      return;
    }
    var row = button.closest('.n-queue-row');
    var id = row ? row.getAttribute('data-id') : null;
    if (!id) {
      return;
    }

    if (button.getAttribute('data-queue-action') === 'retry') {
      sendItem(id);
      return;
    }

    var strings = readPanelStrings();
    if (window.confirm(strings.discardConfirm || 'Discard this saved submission? It will never be sent.')) {
      discard(id);
    }
  });

  window.addEventListener('online', function () { flush(false); });
  window.addEventListener('offline', function () {
    count().then(function (remaining) { if (remaining > 0) { showBanner(remaining, false); } });
  });

  // Another tab queued, replayed or cleared something.
  if (channel) {
    channel.onmessage = function (event) {
      if (!event || !event.data || event.data.type !== 'changed') {
        return;
      }
      refreshBanner().then(function () {
        if (navigator.onLine && !panelEl()) {
          flush(false);
        }
      });
    };
  }

  // Fallback cross-tab signal where BroadcastChannel is missing.
  window.addEventListener('storage', function (event) {
    if (event && event.key === LOCK_KEY) {
      return;
    }
    refreshBanner();
  });

  document.addEventListener('DOMContentLoaded', function () {
    count().then(function (remaining) {
      queueCount = remaining;
      if (remaining > 0) {
        showBanner(remaining, false);
        // On the review screen the admin decides when to send, so nothing is
        // replayed out from under them.
        if (!panelEl()) {
          window.setTimeout(function () { flush(false); }, 800);
        }
      }
      renderPanel();
    });
    window.setInterval(function () {
      if (navigator.onLine && !panelEl()) {
        count().then(function (remaining) { if (remaining > 0) { flush(false); } });
      }
    }, 45000);
  });

  // Small inspection surface for tests and support calls.
  window.NizamOfflineQueue = {
    flush: function () { return flush(true); },
    count: count,
    items: all,
    sendItem: sendItem,
    discard: discard,
    renderPanel: renderPanel,
    tabId: TAB_ID
  };
})();
