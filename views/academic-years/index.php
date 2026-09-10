<?php
/** @var array $years */
$pageTitle = __('academic_years.title');
$activeNav = 'academic-years';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="d-flex justify-content-end mb-3">
  <a href="/academic-years/create" class="btn btn-primary"><?= e(__('academic_years.add')) ?></a>
</div>

<?php if (empty($years)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('academic_years.label')) ?></th>
          <th><?= e(__('academic_years.dates')) ?></th>
          <th><?= e(__('academic_years.status')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($years as $year): ?>
          <tr>
            <td class="fw-semibold"><?= e($year['label']) ?></td>
            <!-- dir="ltr" isolation for the same bidi reason as promotion-preview.php's year range. -->
            <td><span class="text-nowrap" dir="ltr"><?= e($year['start_date']) ?> &rarr; <?= e($year['end_date']) ?></span></td>
            <td>
              <?php if ((int) $year['is_active'] === 1): ?>
                <span class="badge text-bg-success"><?= e(__('academic_years.active_badge')) ?></span>
              <?php endif; ?>
              <?php if ((int) $year['is_closed'] === 1): ?>
                <span class="badge text-bg-secondary"><?= e(__('academic_years.closed_badge')) ?></span>
              <?php else: ?>
                <span class="badge text-bg-light border"><?= e(__('academic_years.open_badge')) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <?php if ((int) $year['is_active'] !== 1): ?>
                <form method="post" action="/academic-years/<?= (int) $year['id'] ?>/activate" class="d-inline"
                      data-confirm="<?= e(__('academic_years.confirm_activate')) ?>">
                  <?= \App\Middleware\CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('academic_years.activate')) ?></button>
                </form>
              <?php endif; ?>
              <?php if ((int) $year['is_closed'] !== 1): ?>
                <form method="post" action="/academic-years/<?= (int) $year['id'] ?>/close" class="d-inline"
                      data-confirm="<?= e(__('academic_years.confirm_close')) ?>">
                  <?= \App\Middleware\CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('academic_years.close')) ?></button>
                </form>
              <?php else: ?>
                <form method="post" action="/academic-years/<?= (int) $year['id'] ?>/reopen" class="d-inline"
                      data-confirm="<?= e(__('academic_years.confirm_reopen')) ?>">
                  <?= \App\Middleware\CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-warning"><?= e(__('academic_years.reopen')) ?></button>
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
