    </main>
  </div>
</div>

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
  if (!badge || !listWrap) return;

  function renderItems(items, locale) {
    if (!items.length) {
      listWrap.innerHTML = '<li class="px-3 py-3 text-center text-muted small"><?= e(__('notifications.empty')) ?></li>';
      return;
    }
    listWrap.innerHTML = items.map(function (n) {
      var linkHtml = n.link
        ? '<a href="' + n.link + '" class="dropdown-item py-2 px-3 ' + (n.is_read ? '' : 'fw-semibold') + '" style="white-space:normal;">' +
          '<div class="small fw-semibold">' + n.title + '</div>' +
          '<div class="text-muted" style="font-size:.75rem;">' + n.body.substring(0, 80) + '</div></a>'
        : '<span class="dropdown-item-text py-2 px-3 ' + (n.is_read ? '' : 'fw-semibold') + '" style="white-space:normal;">' +
          '<div class="small fw-semibold">' + n.title + '</div>' +
          '<div class="text-muted" style="font-size:.75rem;">' + n.body.substring(0, 80) + '</div></span>';
      return '<li>' + linkHtml + '</li>';
    }).join('');
  }

  function poll() {
    fetch('/notifications/api', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.unread > 0) {
          badge.textContent = data.unread > 99 ? '99+' : data.unread;
          badge.style.display = '';
        } else {
          badge.style.display = 'none';
        }
        renderItems(data.items || [], '<?= currentLocale() ?>');
      })
      .catch(function () {});
  }

  poll();
  setInterval(poll, 60000); // refresh every 60s
})();

function markAllRead(e) {
  e.preventDefault();
  fetch('/notifications/read-all', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: '_csrf_token=' + encodeURIComponent(document.querySelector('input[name="_csrf_token"]') ? document.querySelector('input[name="_csrf_token"]').value : '')
  }).then(function () {
    document.getElementById('notif-badge').style.display = 'none';
  });
}
</script>
<?php endif; ?>

</body>
</html>
