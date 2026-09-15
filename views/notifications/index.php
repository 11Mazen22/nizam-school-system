<?php
/**
 * @var array $notifications
 */
$pageTitle = __('notifications.title');
$activeNav = 'notifications';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
$locale = currentLocale();
?>

<!-- Powerful Notifications Header -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('inbox', 'n-grades-icon') ?></div>
        <?= e(__('notifications.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('notifications.subtitle')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-stat-mini">
        <span class="n-stat-mini-value"><?= count(array_filter($notifications, fn($n) => !$n['is_read'])) ?></span>
        <span class="n-stat-mini-label"><?= e(__('notifications.unread')) ?></span>
      </div>
      <?php if (!empty($notifications) && count(array_filter($notifications, fn($n) => !$n['is_read'])) > 0): ?>
        <form method="post" action="/notifications/read-all" class="d-inline">
          <?= CsrfMiddleware::field() ?>
          <button type="submit" class="btn btn-outline-light">
            <?= icon('check') ?> <?= e(__('notifications.mark_all_read')) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Category Navigation -->
<div class="n-filters-bar mb-4">
  <ul class="nav nav-pills n-nav-pills">
    <li class="nav-item">
      <a class="nav-link <?= empty($currentCategory) ? 'active' : '' ?>" href="/notifications">
        <?= e(__('notifications.cat_all')) ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $currentCategory === 'system' ? 'active' : '' ?>" href="/notifications?category=system">
        <?= e(__('notifications.cat_system')) ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $currentCategory === 'academic' ? 'active' : '' ?>" href="/notifications?category=academic">
        <?= e(__('notifications.cat_academic')) ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $currentCategory === 'welfare' ? 'active' : '' ?>" href="/notifications?category=welfare">
        <?= e(__('notifications.cat_welfare')) ?>
      </a>
    </li>
  </ul>
</div>

<?php if (empty($notifications)): ?>
  <!-- Professional Empty State -->
  <div class="n-empty-state">
    <div class="n-empty-powerful">
      <div class="n-empty-powerful-bg">
        <div class="n-empty-blob n-empty-blob-1"></div>
        <div class="n-empty-blob n-empty-blob-2"></div>
        <div class="n-empty-blob n-empty-blob-3"></div>
      </div>
      <div class="n-empty-powerful-content">
        <div class="n-empty-powerful-icon">
          <?= icon('inbox', 'n-icon-massive') ?>
        </div>
        <h3 class="n-empty-powerful-title"><?= e(__('notifications.empty')) ?></h3>
        <p class="n-empty-powerful-text"><?= e(__('notifications.empty_description')) ?></p>
      </div>
    </div>
  </div>
<?php else: ?>
  <!-- Notifications List -->
  <div class="notifications-container">
    <?php foreach ($notifications as $n): ?>
      <?php 
        $isRead = (bool)$n['is_read'];
        $title = $locale === 'ar' ? $n['title_ar'] : $n['title_en'];
        $body = $locale === 'ar' ? $n['body_ar'] : $n['body_en'];
        $hasLink = !empty($n['link']);
      ?>
      <div class="notification-card <?= !$isRead ? 'notification-unread' : '' ?>" data-notification-id="<?= (int)$n['id'] ?>">
        <div class="notification-icon">
          <div class="notification-icon-circle <?= !$isRead ? 'notification-icon-active' : '' ?>">
            <?= icon('inbox') ?>
          </div>
        </div>
        <div class="notification-content">
          <div class="notification-header">
            <h4 class="notification-title <?= !$isRead ? 'fw-bold' : '' ?>">
              <?= e($title) ?>
            </h4>
            <div class="d-flex gap-2 align-items-center">
              <?php if ($n['priority'] === 'high'): ?>
                <span class="badge text-bg-danger d-inline-flex align-items-center gap-1">
                  <?= icon('alert-triangle', 'n-icon-sm') ?>
                  <?= e(__('notifications.priority_high')) ?>
                </span>
              <?php endif; ?>
              <?php if (!$isRead): ?>
                <span class="badge bg-primary notification-new-badge"><?= e(__('notifications.new')) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <p class="notification-body"><?= e($body) ?></p>
          <div class="notification-footer">
            <span class="notification-time">
              <?= icon('clock', 'n-icon-sm') ?>
              <?= e(date('M d, Y H:i', strtotime($n['created_at']))) ?>
            </span>
            <div class="notification-actions">
              <?php if (!$isRead): ?>
                <form method="post" action="/notifications/<?= (int)$n['id'] ?>/read" class="d-inline">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-secondary notification-action-btn">
                    <?= icon('check', 'n-icon-sm') ?> <?= e(__('notifications.mark_read')) ?>
                  </button>
                </form>
              <?php endif; ?>
              <?php if ($hasLink): ?>
                <form method="post" action="/notifications/<?= (int)$n['id'] ?>/read" class="d-inline">
                  <?= CsrfMiddleware::field() ?>
                  <input type="hidden" name="follow" value="1">
                  <button type="submit" class="btn btn-sm btn-primary notification-action-btn">
                    <?= e(__('notifications.view_details')) ?> <?= icon('arrow-forward', 'n-icon-sm') ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
