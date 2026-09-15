<?php
/** @var array $assignments */
$pageTitle = __('common.archived_list') . ' — ' . __('assignments.title');
$activeNav = 'assignments';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('archive', 'n-grades-icon') ?></div>
        <?= e(__('common.archived_list')) ?> — <?= e(__('assignments.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('assignments.archived_description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-stat-mini">
        <span class="n-stat-mini-value"><?= count($assignments) ?></span>
        <span class="n-stat-mini-label"><?= e(__('common.status_archived')) ?></span>
      </div>
      <a href="/assignments" class="btn btn-outline-light d-flex align-items-center gap-2">
        <?= icon('chevron-right', 'n-flip-rtl') ?>
        <span><?= e(__('assignments.title')) ?></span>
      </a>
    </div>
  </div>
</div>

<?php if (empty($assignments)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('archive', 'n-icon-xl') ?></div>
    <h3 class="n-empty-title"><?= e(__('assignments.no_archived')) ?></h3>
    <p class="n-empty-description"><?= e(__('assignments.no_archived_description')) ?></p>
    <a href="/assignments" class="btn btn-primary mt-2">
      <?= icon('arrow-forward', 'n-flip-rtl') ?> <?= e(__('assignments.title')) ?>
    </a>
  </div>
<?php else: ?>
  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex align-items-center gap-2">
      <?= icon('archive') ?>
      <span><?= e(__('common.archived_list')) ?></span>
      <span class="badge text-bg-light border ms-auto"><?= count($assignments) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 n-table-stack">
          <thead>
            <tr>
              <th><?= e(__('assignments.teacher')) ?></th>
              <th><?= e(__('assignments.subject')) ?></th>
              <th><?= e(__('assignments.class')) ?></th>
              <th class="text-center"><?= e(__('assignments.weekly_periods')) ?></th>
              <?php if (hasPermission('assignments.manage')): ?>
              <th class="text-end"><?= e(__('common.actions')) ?></th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assignments as $a): ?>
              <tr>
                <td data-label="<?= e(__('assignments.teacher')) ?>">
                  <div class="d-flex align-items-center gap-2">
                    <div class="n-avatar" style="width: 32px; height: 32px; font-size: 0.7rem;">
                      <?= e(mb_substr($a['teacher_name'], 0, 1)) ?>
                    </div>
                    <span><?= e($a['teacher_name']) ?></span>
                  </div>
                </td>
                <td data-label="<?= e(__('assignments.subject')) ?>">
                  <span class="badge text-bg-light border">
                    <?= e(currentLocale() === 'ar' ? $a['subject_name_ar'] : $a['subject_name_en']) ?>
                  </span>
                </td>
                <td data-label="<?= e(__('assignments.class')) ?>">
                  <div class="text-muted small"><?= e(currentLocale() === 'ar' ? $a['grade_name_ar'] : $a['grade_name_en']) ?></div>
                  <div><?= e($a['class_name']) ?></div>
                </td>
                <td class="text-center" data-label="<?= e(__('assignments.weekly_periods')) ?>">
                  <span class="badge text-bg-secondary"><?= (int) $a['weekly_periods'] ?></span>
                </td>
                <?php if (hasPermission('assignments.manage')): ?>
                <td class="text-end text-nowrap">
                  <form method="post" action="/assignments/<?= (int)$a['id'] ?>/restore"
                        data-confirm="<?= e(__('common.confirm_restore')) ?>">
                    <?= CsrfMiddleware::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-success">
                      <?= icon('unarchive', 'n-icon-sm') ?>
                      <span class="d-none d-md-inline"><?= e(__('common.restore')) ?></span>
                    </button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
