<?php
/** @var array $students */
$pageTitle = __('common.archived_list') . ' — ' . __('students.title');
$activeNav = 'students';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('archive', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('common.archived_list')) ?>
    </h1>
  </div>
  <div class="n-page-head-actions">
    <a href="/students" class="btn btn-outline-secondary d-flex align-items-center gap-2">
      <?= icon('chevron-right', 'n-flip-rtl') ?>
      <span><?= e(__('students.title')) ?></span>
    </a>
  </div>
</div>

<?php if (empty($students)): ?>
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
      <span class="badge text-bg-light border ms-auto"><?= count($students) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 n-table-stack">
          <thead>
            <tr>
              <th><?= e(__('students.code')) ?></th>
              <th><?= e(__('students.full_name')) ?></th>
              <th class="text-end n-stack-hide"><?= e(__('common.actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $student): ?>
              <tr>
                <td data-label="<?= e(__('students.code')) ?>" class="mono"><?= e($student['student_code']) ?></td>
                <td data-label="<?= e(__('students.full_name')) ?>"><?= e($student['full_name']) ?></td>
                <td class="text-end" data-label="<?= e(__('common.actions')) ?>">
                  <form method="post" action="/students/<?= (int) $student['id'] ?>/restore" data-confirm="<?= e(__('common.confirm_restore')) ?>">
                    <?= CsrfMiddleware::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1">
                      <?= icon('unarchive', 'n-icon-sm') ?>
                      <span><?= e(__('common.restore')) ?></span>
                    </button>
                  </form>
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
