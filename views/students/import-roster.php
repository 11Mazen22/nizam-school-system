<?php
/**
 * Student roster CSV import -- upload step.
 *
 * @var list<string> $headers
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('roster.title');
$activeNav = 'students';
$pageSubtitle = __('roster.subtitle');

ob_start(); ?>
<a href="/students" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
  <?= icon('chevron-left', 'n-flip-rtl') ?>
  <span><?= e(__('students.title')) ?></span>
</a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card n-roster-upload">
      <div class="card-body">
        <h2 class="h6 mb-1"><?= e(__('roster.upload_title')) ?></h2>
        <p class="text-muted small"><?= e(__('roster.upload_text')) ?></p>

        <ol class="n-import-steps">
          <li><strong><?= e(__('roster.step1')) ?></strong><?= e(__('roster.step1_text')) ?></li>
          <li><strong><?= e(__('roster.step2')) ?></strong><?= e(__('roster.step2_text')) ?></li>
          <li><strong><?= e(__('roster.step3')) ?></strong><?= e(__('roster.step3_text')) ?></li>
        </ol>

        <a href="/students/import/roster/template" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 mb-4">
          <?= icon('download') ?>
          <span><?= e(__('roster.download_template')) ?></span>
        </a>

        <form method="post" action="/students/import/roster/preview" enctype="multipart/form-data">
          <?= CsrfMiddleware::field() ?>
          <label class="form-label" for="roster_file"><?= e(__('roster.file_label')) ?></label>
          <input class="form-control" type="file" id="roster_file" name="roster_file" accept=".csv,text/csv" required>
          <p class="form-text"><?= e(__('roster.file_help')) ?></p>
          <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <?= icon('clipboard-check') ?>
            <span><?= e(__('roster.preview_button')) ?></span>
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= e(__('roster.columns_title')) ?></h2>
        <ul class="n-roster-columns">
          <?php foreach ($headers as $header): ?>
            <li dir="ltr"><code><?= e($header) ?></code></li>
          <?php endforeach; ?>
        </ul>
        <div class="alert alert-info small mb-0 mt-3">
          <?= icon('info', 'n-icon-sm') ?>
          <?= e(__('roster.attribute_note')) ?>
        </div>
      </div>
    </div>
  </div>
</div>
