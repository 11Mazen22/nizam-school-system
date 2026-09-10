<?php
/** @var array $classes @var bool $hasActiveYear */
$pageTitle = __('classes.title');
$activeNav = 'classes';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<?php if (!$hasActiveYear): ?>
  <div class="alert alert-warning"><?= e(__('classes.no_active_year')) ?></div>
<?php else: ?>
  <div class="d-flex justify-content-end mb-3">
    <a href="/classes/create" class="btn btn-primary"><?= e(__('classes.add')) ?></a>
  </div>

  <?php if (empty($classes)): ?>
    <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle bg-white">
        <thead>
          <tr>
            <th><?= e(__('classes.grade')) ?></th>
            <th><?= e(__('classes.name')) ?></th>
            <th><?= e(__('classes.capacity')) ?></th>
            <th><?= e(__('classes.enrolled')) ?></th>
            <th><?= e(__('academic_years.status')) ?></th>
            <th><?= e(__('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($classes as $class): ?>
            <tr>
              <td><?= e(currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en']) ?></td>
              <td class="fw-semibold"><?= e($class['name']) ?></td>
              <td class="font-variant-numeric-tabular"><?= $class['capacity'] !== null ? (int) $class['capacity'] : '—' ?></td>
              <td class="font-variant-numeric-tabular"><?= (int) $class['enrolled'] ?></td>
              <td>
                <?php if ((int) $class['is_active'] === 1): ?>
                  <span class="badge text-bg-success"><?= e(__('common.status_active')) ?></span>
                <?php else: ?>
                  <span class="badge text-bg-secondary"><?= e(__('common.status_archived')) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <a href="/classes/<?= (int) $class['id'] ?>/edit" class="btn btn-sm btn-outline-secondary"><?= e(__('common.edit')) ?></a>
                <?php if ((int) $class['is_active'] === 1): ?>
                  <form method="post" action="/classes/<?= (int) $class['id'] ?>/archive" class="d-inline" data-confirm="<?= e(__('common.confirm_archive')) ?>">
                    <?= CsrfMiddleware::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('common.archive')) ?></button>
                  </form>
                <?php else: ?>
                  <form method="post" action="/classes/<?= (int) $class['id'] ?>/restore" class="d-inline" data-confirm="<?= e(__('common.confirm_restore')) ?>">
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
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
