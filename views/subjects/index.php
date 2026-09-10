<?php
/** @var array $subjects */
$pageTitle = __('subjects.title');
$activeNav = 'subjects';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="d-flex justify-content-end mb-3">
  <a href="/subjects/create" class="btn btn-primary"><?= e(__('subjects.add')) ?></a>
</div>

<?php if (empty($subjects)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('subjects.code')) ?></th>
          <th><?= e(__('subjects.name_en')) ?></th>
          <th><?= e(__('subjects.name_ar')) ?></th>
          <th><?= e(__('academic_years.status')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $subject): ?>
          <tr>
            <td class="mono"><?= e($subject['code']) ?></td>
            <td><?= e($subject['name_en']) ?></td>
            <td><?= e($subject['name_ar']) ?></td>
            <td>
              <?php if ((int) $subject['is_active'] === 1): ?>
                <span class="badge text-bg-success"><?= e(__('common.status_active')) ?></span>
              <?php else: ?>
                <span class="badge text-bg-secondary"><?= e(__('common.status_archived')) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <a href="/subjects/<?= (int) $subject['id'] ?>/edit" class="btn btn-sm btn-outline-secondary"><?= e(__('common.edit')) ?></a>
              <?php if ((int) $subject['is_active'] === 1): ?>
                <form method="post" action="/subjects/<?= (int) $subject['id'] ?>/archive" class="d-inline" data-confirm="<?= e(__('common.confirm_archive')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('common.archive')) ?></button>
                </form>
              <?php else: ?>
                <form method="post" action="/subjects/<?= (int) $subject['id'] ?>/restore" class="d-inline" data-confirm="<?= e(__('common.confirm_restore')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-success"><?= e(__('common.restore')) ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
