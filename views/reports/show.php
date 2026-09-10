<?php
/**
 * @var string $key @var ?array $report @var ?string $error @var array $filters
 * @var array $years @var array $grades @var array $subjects @var array $classesForGrade
 */
$pageTitle = __('reports.' . $key);
$activeNav = 'reports';
$pageScripts = ['/assets/js/reports-filter.js'];
require dirname(__DIR__) . '/layout/start.php';

$needsGrade = in_array($key, ['religion', 'class-list', 'density'], true);
$needsClass = in_array($key, ['religion', 'class-list'], true);
$classRequired = $key === 'class-list';
$gradeRequired = $key === 'class-list';
$needsSubject = in_array($key, ['teachers-by-subject', 'workload'], true);
?>

<div class="card mb-3 no-print">
  <div class="card-body">
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

      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><?= e(__('reports.apply_filters')) ?></button>
      </div>
    </form>
  </div>
</div>

<?php if ($error !== null): ?>
  <div class="alert alert-danger no-print"><?= e($error) ?></div>
<?php elseif ($report === null): ?>
  <div class="alert alert-secondary no-print"><?= e(__('reports.choose_filters_prompt')) ?></div>
<?php else: ?>
  <div class="d-flex justify-content-end gap-2 mb-2 no-print">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-print-report><?= e(__('reports.print')) ?></button>
    <?php if (in_array('pdf', $report['formats'], true)): ?>
      <a class="btn btn-sm btn-outline-secondary" href="/reports/<?= e($key) ?>/export/pdf?<?= e(http_build_query($filters)) ?>">
        <?= e(__('reports.export_pdf')) ?>
      </a>
    <?php endif; ?>
    <?php if (in_array('excel', $report['formats'], true)): ?>
      <a class="btn btn-sm btn-outline-secondary" href="/reports/<?= e($key) ?>/export/excel?<?= e(http_build_query($filters)) ?>">
        <?= e(__('reports.export_excel')) ?>
      </a>
    <?php endif; ?>
  </div>

  <div id="reportPrintArea">
    <div class="mb-3">
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
      <div class="alert alert-secondary"><?= e(__('reports.no_data')) ?></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered bg-white">
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
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
