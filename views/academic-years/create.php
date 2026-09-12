<?php
/** @var ?string $error @var array $years */
$pageTitle = __('academic_years.add');
$activeNav = 'academic-years';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('plus', 'n-icon-lg text-body-secondary') ?>
      <?= e($pageTitle) ?>
    </h1>
  </div>
</div>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 560px;">
  <div class="card-body">
    <form method="post" action="/academic-years">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="label"><?= e(__('academic_years.label')) ?></label>
        <input type="text" class="form-control" id="label" name="label" placeholder="2026/2027" required>
      </div>
      <div class="row">
        <div class="col-6 mb-3">
          <label class="form-label" for="start_date"><?= e(__('setup.year.start_date')) ?></label>
          <input type="date" class="form-control" id="start_date" name="start_date" required>
        </div>
        <div class="col-6 mb-3">
          <label class="form-label" for="end_date"><?= e(__('setup.year.end_date')) ?></label>
          <input type="date" class="form-control" id="end_date" name="end_date" required>
        </div>
      </div>
      <?php if (!empty($years)): ?>
        <div class="mb-3">
          <label class="form-label" for="rollover_from"><?= e(__('academic_years.rollover_option')) ?></label>
          <select class="form-select" id="rollover_from" name="rollover_from">
            <option value=""><?= e(__('academic_years.rollover_none')) ?></option>
            <?php foreach ($years as $year): ?>
              <option value="<?= (int) $year['id'] ?>"><?= e($year['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
        <?= icon('check') ?>
        <span><?= e(__('common.save')) ?></span>
      </button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
