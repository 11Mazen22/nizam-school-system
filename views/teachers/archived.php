<?php
/** @var array $teachers */
$pageTitle = __('common.archived_list') . ' — ' . __('teachers.title');
$activeNav = 'teachers';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="d-flex justify-content-end mb-3">
  <a href="/teachers" class="btn btn-outline-secondary"><?= e(__('teachers.title')) ?></a>
</div>

<?php if (empty($teachers)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('teachers.code')) ?></th>
          <th><?= e(__('teachers.full_name')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teachers as $teacher): ?>
          <tr>
            <td class="mono"><?= e($teacher['teacher_code']) ?></td>
            <td><?= e($teacher['full_name']) ?></td>
            <td>
              <form method="post" action="/teachers/<?= (int) $teacher['id'] ?>/restore" data-confirm="<?= e(__('common.confirm_restore')) ?>">
                <?= CsrfMiddleware::field() ?>
                <button type="submit" class="btn btn-sm btn-outline-success"><?= e(__('common.restore')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
