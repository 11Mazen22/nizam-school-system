<?php
/** @var array $assignments */
$pageTitle = __('common.archived_list') . ' — ' . __('assignments.title');
$activeNav = 'assignments';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1><?= e(__('common.archived_list')) ?></h1>
    <p><?= e(__('assignments.description')) ?></p>
  </div>
  <div class="n-page-head-actions">
    <a href="/assignments" class="btn btn-outline-secondary d-flex align-items-center gap-2">
      <?= icon('chevron-right', 'n-flip-rtl') ?>
      <span><?= e(__('assignments.title')) ?></span>
    </a>
  </div>
</div>

<?php if (empty($assignments)): ?>
  <div class="card">
    <div class="card-body">
      <div class="n-empty py-4">
        <div class="n-empty-icon"><?= icon('archive') ?></div>
        <p class="mb-0"><?= e(__('common.no_results')) ?></p>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="card">
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
              <th><?= e(__('assignments.weekly_periods')) ?></th>
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
                <td data-label="<?= e(__('assignments.weekly_periods')) ?>">
                  <?= (int) $a['weekly_periods'] ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
