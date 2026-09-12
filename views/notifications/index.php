<?php
/** @var array $notifications */
$pageTitle = __('notifications.title');
$activeNav = 'notifications';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
$locale = currentLocale();
?>

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
      <form method="post" action="/notifications/read-all">
        <?= CsrfMiddleware::field() ?>
        <button class="btn btn-outline-light btn-sm"><?= icon('check') ?> <?= e(__('notifications.mark_all_read')) ?></button>
      </form>
    </div>
  </div>
</div>

<?php if (empty($notifications)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('inbox', 'n-icon-xl') ?></div>
    <h3 class="n-empty-title"><?= e(__('notifications.empty')) ?></h3>
  </div>
<?php else: ?>
  <div class="card border-0 shadow-sm">
    <ul class="list-group list-group-flush">
      <?php foreach ($notifications as $n): ?>
        <?php $isRead = (bool)$n['is_read']; ?>
        <li class="list-group-item py-3 px-4 <?= !$isRead ? 'bg-primary-soft' : '' ?>" style="<?= !$isRead ? 'background:var(--n-primary-softer)' : '' ?>">
          <div class="d-flex align-items-start gap-3">
            <div class="flex-shrink-0 mt-1">
              <span class="rounded-circle d-inline-flex align-items-center justify-content-center"
                    style="width:36px;height:36px;background:<?= $isRead ? 'var(--n-surface-sunken)' : 'var(--n-primary-soft)' ?>;">
                <?= icon('inbox', $isRead ? 'text-muted' : 'text-primary') ?>
              </span>
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold <?= $isRead ? 'text-muted' : '' ?>">
                <?= e($locale === 'ar' ? $n['title_ar'] : $n['title_en']) ?>
                <?php if (!$isRead): ?><span class="badge bg-primary ms-1" style="font-size:.65rem;"><?= e(__('notifications.new')) ?></span><?php endif; ?>
              </div>
              <div class="text-muted small mt-1"><?= e($locale === 'ar' ? $n['body_ar'] : $n['body_en']) ?></div>
              <div class="text-muted" style="font-size:.75rem;margin-top:4px;"><?= e($n['created_at']) ?></div>
            </div>
            <div class="flex-shrink-0 d-flex gap-2">
              <?php if (!$isRead): ?>
                <form method="post" action="/notifications/<?= (int)$n['id'] ?>/read">
                  <?= CsrfMiddleware::field() ?>
                  <button class="btn btn-sm btn-outline-secondary"><?= icon('check') ?></button>
                </form>
              <?php endif; ?>
              <?php if (!empty($n['link'])): ?>
                <a href="<?= e($n['link']) ?>" class="btn btn-sm btn-outline-primary"><?= icon('arrow-forward') ?></a>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
