<?php
/**
 * @var array       $year    the academic year row being edited
 * @var string|null $error   validation error message, or null
 */
$pageTitle = __('academic_years.edit') . ' — ' . e($year['label']);
$activeNav = 'academic-years';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('edit', 'n-grades-icon') ?></div>
        <?= e(__('academic_years.edit')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e($year['label']) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <a href="/academic-years" class="btn btn-outline-light d-flex align-items-center gap-2">
        <?= icon('chevron-right', 'n-flip-rtl') ?>
        <span><?= e(__('academic_years.title')) ?></span>
      </a>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="n-form-card-powerful">
      <div class="n-form-card-header">
        <div class="n-form-card-icon">
          <?= icon('calendar', 'n-icon-lg') ?>
        </div>
        <div>
          <h2 class="n-form-card-title"><?= e(__('academic_years.edit')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('academic_years.form_edit_subtitle')) ?></p>
        </div>
      </div>

      <?php if ($error !== null): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
          <?= icon('alert-triangle', 'flex-shrink-0') ?>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" action="/academic-years/<?= (int)$year['id'] ?>" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="label_edit">
            <span><?= e(__('academic_years.label')) ?></span>
            <span class="n-label-example"><?= e(__('common.example')) ?>: 2025/2026</span>
          </label>
          <input type="text" class="form-control n-input-powerful"
                 id="label_edit"
                 name="label"
                 value="<?= e($year['label']) ?>"
                 placeholder="2025/2026"
                 dir="ltr"
                 required>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="start_date_edit">
            <span><?= e(__('academic_years.start_date')) ?></span>
          </label>
          <input type="date" class="form-control n-input-powerful"
                 id="start_date_edit"
                 name="start_date"
                 value="<?= e($year['start_date']) ?>"
                 required>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="end_date_edit">
            <span><?= e(__('academic_years.end_date')) ?></span>
          </label>
          <input type="date" class="form-control n-input-powerful"
                 id="end_date_edit"
                 name="end_date"
                 value="<?= e($year['end_date']) ?>"
                 required>
        </div>

        <!-- Status information (read-only) -->
        <div class="alert alert-info d-flex align-items-center gap-2 mb-4" style="font-size:.875rem;">
          <?= icon('info') ?>
          <span><?= e(__('academic_years.edit_status_note')) ?></span>
        </div>

        <div class="n-form-actions">
          <a href="/academic-years" class="btn btn-secondary n-btn-powerful-secondary">
            <?= icon('close') ?>
            <span><?= e(__('common.cancel')) ?></span>
          </a>
          <button type="submit" class="btn btn-primary n-btn-powerful">
            <?= icon('check') ?>
            <span><?= e(__('common.update')) ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
