    </main>
  </div>
</div>

<?php if (!empty($pageModals)): ?>
  <?= $pageModals ?>
<?php endif; ?>

<!-- Bootstrap & Core Scripts -->
<script src="/assets/vendor/jquery/jquery.min.js"></script>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/confirm.js"></script>

<!-- Page-specific scripts -->
<?php if (!empty($pageScripts)): ?>
  <?php foreach ($pageScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Notification bell polling -->
<?php if (!empty($_SESSION['user_id'])): ?>
<script>
(function () {
  const badge    = document.getElementById('notif-badge');
  const listWrap = document.getElementById('notif-list');
  const markAllLink = document.getElementById('notif-markall-link');
  
  if (!badge || !listWrap) return;

  function renderItems(items, locale) {
    if (!items.length) {
      listWrap.innerHTML = '<li class="px-3 py-3 text-center text-muted small"><?= e(__('notifications.empty')) ?></li>';
      if (markAllLink) markAllLink.style.display = 'none';
      return;
    }
    
    const hasUnread = items.some(n => !n.is_read);
    if (markAllLink) {
      markAllLink.style.display = hasUnread ? '' : 'none';
    }
    
    listWrap.innerHTML = items.map(function (n) {
      const truncatedBody = n.body.length > 80 ? n.body.substring(0, 80) + '...' : n.body;
      const unreadClass = n.is_read ? '' : 'fw-semibold';
      const unreadBadge = n.is_read ? '' : '<span class="badge bg-primary ms-1" style="font-size:0.65rem;"><?= e(__('notifications.new')) ?></span>';
      
      if (n.link) {
        return '<li><a href="' + escapeHtml(n.link) + '" class="dropdown-item py-2 px-3 ' + unreadClass + '" style="white-space:normal;">' +
          '<div class="small fw-semibold">' + escapeHtml(n.title) + unreadBadge + '</div>' +
          '<div class="text-muted" style="font-size:.75rem;margin-top:0.25rem;">' + escapeHtml(truncatedBody) + '</div>' +
          '<div class="text-muted" style="font-size:.7rem;margin-top:0.35rem;opacity:0.7;"><svg class="n-icon n-icon-sm" style="width:12px;height:12px;display:inline-block;vertical-align:-2px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 7v5.3l3.5 2"/></svg> ' + formatTime(n.created_at) + '</div>' +
          '</a></li>';
      } else {
        return '<li><span class="dropdown-item-text py-2 px-3 ' + unreadClass + '" style="white-space:normal;">' +
          '<div class="small fw-semibold">' + escapeHtml(n.title) + unreadBadge + '</div>' +
          '<div class="text-muted" style="font-size:.75rem;margin-top:0.25rem;">' + escapeHtml(truncatedBody) + '</div>' +
          '<div class="text-muted" style="font-size:.7rem;margin-top:0.35rem;opacity:0.7;"><svg class="n-icon n-icon-sm" style="width:12px;height:12px;display:inline-block;vertical-align:-2px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 7v5.3l3.5 2"/></svg> ' + formatTime(n.created_at) + '</div>' +
          '</span></li>';
      }
    }).join('');
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatTime(dateStr) {
    try {
      const date = new Date(dateStr);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      
      if (diffMins < 1) return '<?= e(__('common.just_now')) ?>';
      if (diffMins < 60) return diffMins + ' <?= e(__('common.minutes_ago')) ?>';
      
      const diffHours = Math.floor(diffMins / 60);
      if (diffHours < 24) return diffHours + ' <?= e(__('common.hours_ago')) ?>';
      
      const diffDays = Math.floor(diffHours / 24);
      if (diffDays < 7) return diffDays + ' <?= e(__('common.days_ago')) ?>';
      
      return date.toLocaleDateString('<?= currentLocale() === 'ar' ? 'ar-EG' : 'en-US' ?>', { 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (e) {
      return dateStr;
    }
  }

  function poll() {
    fetch('/notifications/api', { credentials: 'same-origin' })
      .then(function (r) { 
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json(); 
      })
      .then(function (data) {
        if (data.unread > 0) {
          badge.textContent = data.unread > 99 ? '99+' : data.unread;
          badge.style.display = '';
        } else {
          badge.style.display = 'none';
        }
        renderItems(data.items || [], '<?= currentLocale() ?>');
      })
      .catch(function (err) {
        console.error('Notification poll failed:', err);
      });
  }

  // Initial poll
  poll();
  
  // Poll every 60 seconds
  setInterval(poll, 60000);
  
  // Refresh when dropdown is opened
  const dropdown = document.getElementById('notifBellBtn');
  if (dropdown) {
    dropdown.addEventListener('show.bs.dropdown', poll);
  }
})();

function markAllRead(e) {
  e.preventDefault();
  const csrfToken = document.querySelector('input[name="_csrf_token"]');
  if (!csrfToken) {
    console.error('CSRF token not found');
    return;
  }
  
  fetch('/notifications/read-all', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: '_csrf_token=' + encodeURIComponent(csrfToken.value)
  })
  .then(function (r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r;
  })
  .then(function () {
    const badge = document.getElementById('notif-badge');
    if (badge) badge.style.display = 'none';
    
    const markAllLink = document.getElementById('notif-markall-link');
    if (markAllLink) markAllLink.style.display = 'none';
    
    // Refresh the list after a short delay
    setTimeout(function() {
      fetch('/notifications/api', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          const listWrap = document.getElementById('notif-list');
          if (listWrap && data.items) {
            listWrap.innerHTML = data.items.map(function (n) {
              const truncatedBody = n.body.length > 80 ? n.body.substring(0, 80) + '...' : n.body;
              const title = document.createElement('div');
              title.textContent = n.title;
              const body = document.createElement('div');
              body.textContent = truncatedBody;
              return '<li><span class="dropdown-item-text py-2 px-3" style="white-space:normal;">' +
                '<div class="small">' + title.innerHTML + '</div>' +
                '<div class="text-muted" style="font-size:.75rem;margin-top:0.25rem;">' + body.innerHTML + '</div>' +
                '</span></li>';
            }).join('');
          }
        })
        .catch(function(err) {
          console.error('Failed to refresh notifications:', err);
        });
    }, 300);
  })
  .catch(function (err) {
    console.error('Mark all as read failed:', err);
  });
}
</script>
<?php endif; ?>

</body>
</html>
