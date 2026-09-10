<?php
/** @var array $students */
$pageTitle = __('common.archived_list') . ' — ' . __('students.title');
$activeNav = 'students';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="d-flex justify-content-end mb-3">
  <a href="/students" class="btn btn-outline-secondary"><?= e(__('students.title')) ?></a>
</div>

<?php if (empty($students)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('students.code')) ?></th>
          <th><?= e(__('students.full_name')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $student): ?>
          <tr>
            <td class="mono"><?= e($student['student_code']) ?></td>
            <td><?= e($student['full_name']) ?></td>
            <td>
              <form method="post" action="/students/<?= (int) $student['id'] ?>/restore" data-confirm="<?= e(__('common.confirm_restore')) ?>">
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
