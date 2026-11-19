<?php
/**
 * @var string $key @var ?array $report @var ?string $error @var array $filters
 * @var array $years @var array $grades @var array $subjects @var array $classesForGrade
 */
$pageTitle = __('reports.' . $key);
$activeNav = 'reports';
$pageScripts = ['/assets/js/reports-filter.js'];
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('reports.' . $key . '_desc')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/reports" class="btn btn-outline-secondary d-flex align-items-center gap-2">
      <?= icon('chevron-right', 'n-flip-rtl') ?>
      <span><?= e(__('reports.title')) ?></span>
    </a>
<?php $pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';

$needsGrade = in_array($key, ['religion', 'class-list', 'density'], true);
$needsClass = in_array($key, ['religion', 'class-list'], true);
$classRequired = $key === 'class-list';
$gradeRequired = $key === 'class-list';
$needsSubject = in_array($key, ['teachers-by-subject', 'workload'], true);
?>



<div class="n-filter-panel no-print">
    <form method="get" action="/reports/<?= e($key) ?>" class="row g-2 align-items-end" id="reportFilterForm">
      <div class="col-auto">
        <label class="form-label" for="f-year"><?= e(__('reports.year')) ?></label>
        <select class="form-select" id="f-year" name="year" required>
          <option value=""><?= e(__('reports.choose_year')) ?></option>
          <?php foreach ($years as $year): ?>
            <option value="<?= (int) $year['id'] ?>" <?= ($filters['year'] ?? '') === (string) $year['id'] ? 'selected' : '' ?>>
              <?= e($year['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($needsGrade): ?>
        <div class="col-auto">
          <label class="form-label" for="f-grade"><?= e(__('students.grade')) ?></label>
          <select class="form-select" id="f-grade" name="grade" <?= $gradeRequired ? 'required' : '' ?>>
            <?php if (!$gradeRequired): ?><option value=""><?= e(__('reports.all_grades')) ?></option><?php endif; ?>
            <?php foreach ($grades as $grade): ?>
              <option value="<?= (int) $grade['id'] ?>" data-grade-option
                <?= ($filters['grade'] ?? '') === (string) $grade['id'] ? 'selected' : '' ?>>
                <?= e(currentLocale() === 'ar' ? $grade['name_ar'] : $grade['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if ($needsClass): ?>
        <div class="col-auto">
          <label class="form-label" for="f-class"><?= e(__('students.class')) ?></label>
          <select class="form-select" id="f-class" name="class" <?= $classRequired ? 'required' : '' ?>>
            <?php if (!$classRequired): ?><option value=""><?= e(__('reports.all_classes')) ?></option><?php endif; ?>
            <?php foreach ($classesForGrade as $class): ?>
              <option value="<?= (int) $class['id'] ?>" <?= ($filters['class'] ?? '') === (string) $class['id'] ? 'selected' : '' ?>>
                <?= e($class['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if ($needsSubject): ?>
        <div class="col-auto">
          <label class="form-label" for="f-subject"><?= e(__('assignments.subject')) ?></label>
          <select class="form-select" id="f-subject" name="subject">
            <option value=""><?= e(__('reports.all_subjects')) ?></option>
            <?php foreach ($subjects as $subject): ?>
              <option value="<?= (int) $subject['id'] ?>" <?= ($filters['subject'] ?? '') === (string) $subject['id'] ? 'selected' : '' ?>>
                <?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if ($key === 'class-list'): ?>
        <div class="col-auto">
          <label class="form-label" for="f-sort"><?= e(__('reports.sort_by')) ?></label>
          <select class="form-select" id="f-sort" name="sort">
            <option value="name" <?= ($filters['sort'] ?? 'name') === 'name' ? 'selected' : '' ?>><?= e(__('reports.sort_name')) ?></option>
            <option value="code" <?= ($filters['sort'] ?? '') === 'code' ? 'selected' : '' ?>><?= e(__('reports.sort_code')) ?></option>
          </select>
        </div>
      <?php elseif ($key === 'density'): ?>
        <div class="col-auto">
          <label class="form-label" for="f-sort"><?= e(__('reports.sort_by')) ?></label>
          <select class="form-select" id="f-sort" name="sort">
            <option value="desc" <?= ($filters['sort'] ?? 'desc') === 'desc' ? 'selected' : '' ?>><?= e(__('reports.sort_desc')) ?></option>
            <option value="asc" <?= ($filters['sort'] ?? '') === 'asc' ? 'selected' : '' ?>><?= e(__('reports.sort_asc')) ?></option>
          </select>
        </div>
      <?php elseif ($key === 'workload'): ?>
        <div class="col-auto">
          <label class="form-label" for="f-sort"><?= e(__('reports.sort_by')) ?></label>
          <select class="form-select" id="f-sort" name="sort">
            <option value="highest" <?= ($filters['sort'] ?? 'highest') === 'highest' ? 'selected' : '' ?>><?= e(__('reports.sort_highest')) ?></option>
            <option value="lowest" <?= ($filters['sort'] ?? '') === 'lowest' ? 'selected' : '' ?>><?= e(__('reports.sort_lowest')) ?></option>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-12 col-md-auto">
        <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2 w-100">
          <?= icon('filter') ?>
          <span><?= e(__('reports.apply_filters')) ?></span>
        </button>
      </div>
    </form>
</div>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2 no-print">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php elseif ($report === null): ?>
  <div class="card no-print">
    <div class="card-body">
      <div class="n-empty py-4">
        <div class="n-empty-icon"><?= icon('filter') ?></div>
        <p class="mb-0"><?= e(__('reports.choose_filters_prompt')) ?></p>
      </div>
    </div>
  </div>
<?php else: ?>
  <?php $exportQuery = http_build_query($filters + ((($q ?? '') !== '') ? ['q' => $q] : [])); ?>
  <div class="n-report-toolbar no-print">
    <?php
    // The report document is produced by the shared document engine, so it
    // prints as a designed report rather than as the filtered screen.
    $docKey = 'report';
    $docParams = array_merge(['report' => $key], $filters, ((($q ?? '') !== '') ? ['q' => $q] : []));
    $docCompact = true;
    require dirname(__DIR__) . '/partials/export-menu.php';
    ?>
  </div>

  <?php if (!empty($reportHasRows)): ?>
  <form method="get" action="/reports/<?= e($key) ?>" class="n-filter-panel n-list-search no-print" data-live-filter data-live-target="#report-results">
    <div class="n-filter-row">
      <div class="n-filter-field n-search-field flex-grow-1">
        <?= icon('search') ?>
        <label class="visually-hidden" for="report-search"><?= e(__('common.search')) ?></label>
        <input id="report-search" type="search" class="form-control" name="q"
               value="<?= e($q ?? '') ?>"
               placeholder="<?= e(__('common.search')) ?>"
               autocomplete="off">
      </div>
      <?php foreach ($filters as $filterKey => $filterValue): ?>
        <input type="hidden" name="<?= e((string) $filterKey) ?>" value="<?= e((string) $filterValue) ?>">
      <?php endforeach; ?>
      <button type="submit" class="btn btn-primary"><?= icon('search') ?><?= e(__('common.search')) ?></button>
      <?php if (($q ?? '') !== ''): ?><a href="/reports/<?= e($key) ?>?<?= e(http_build_query($filters)) ?>" class="btn btn-outline-secondary"><?= icon('close') ?><?= e(__('common.clear_filters')) ?></a><?php endif; ?>
    </div>
  </form>
  <?php endif; ?>
  <div id="report-results" data-live-results>
  <div id="reportPrintArea" class="n-report-document">
    <div class="n-report-document-head">
      <?php if (!empty($report['school_logo_url'])): ?>
        <img src="<?= e($report['school_logo_url']) ?>" alt="" class="n-report-logo" style="height:48px;max-width:120px;object-fit:contain;margin-bottom:.5rem;">
      <?php endif; ?>
      <div class="fw-semibold small mb-1"><?= e($report['school_name'] ?? '') ?></div>
      <h2 class="h5 mb-1"><?= e($report['title']) ?></h2>
      <div class="text-muted small">
        <?= e(__('reports.year')) ?>: <?= e($report['year_label']) ?>
        <?php foreach ($report['filters_summary'] as $label => $value): ?>
          &nbsp;·&nbsp; <?= e($label) ?>: <?= e($value) ?>
        <?php endforeach; ?>
        &nbsp;·&nbsp; <?= e(__('reports.generated_on')) ?>: <?= e($report['generated_at']) ?>
      </div>
    </div>

    <?php if (empty($report['rows'])): ?>
      <div class="n-empty border-0 py-4">
        <div class="n-empty-icon"><?= icon('inbox') ?></div>
        <p class="mb-0"><?= e(__('reports.no_data')) ?></p>
      </div>
    <?php else: ?>
      <div class="table-responsive n-report-table-wrap">
        <table id="report-table" class="table table-sm table-bordered">
          <thead>
            <tr>
              <?php foreach ($report['headers'] as $header): ?>
                <th><?= e($header) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['rows'] as $row): ?>
              <tr>
                <?php foreach ($row as $cell): ?>
                  <?php $cellStr = (string) $cell; ?>
                  <td><?php if (currentDirection() === 'rtl' && isRtlSafeNumericCell($cellStr)): ?><span dir="ltr"><?= e($cellStr) ?></span><?php else: ?><?= e($cellStr) ?><?php endif; ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
