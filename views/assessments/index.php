<?php
/**
 * Weekly assessment distribution, A1-A9.
 *
 * @var array<string, list<array<string, mixed>>> $byClass
 * @var bool                                      $hasActiveYear
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('assessments.title');
$activeNav = 'exams';
$pageSubtitle = __('assessments.subtitle');

$days = [
    2 => 'timetable.day_sun',
    3 => 'timetable.day_mon',
    4 => 'timetable.day_tue',
    5 => 'timetable.day_wed',
    6 => 'timetable.day_thu',
];

ob_start(); ?>
<a href="/classes" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
  <?= icon('chalkboard') ?>
  <span><?= e(__('classes.title')) ?></span>
</a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<?php if (!$hasActiveYear): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('calendar') ?></div>
    <h2 class="n-empty-title"><?= e(__('classes.no_active_year_title')) ?></h2>
    <p class="n-empty-description"><?= e(__('classes.no_active_year')) ?></p>
  </div>
<?php elseif ($byClass === []): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('clipboard-check') ?></div>
    <h2 class="n-empty-title"><?= e(__('assessments.empty_title')) ?></h2>
    <p class="n-empty-description"><?= e(__('assessments.empty_text')) ?></p>
  </div>
<?php else: ?>
  <div class="row row-cols-1 row-cols-lg-2 g-3">
    <?php foreach ($byClass as $className => $rows): ?>
      <?php $first = $rows[0]; ?>
      <div class="col">
        <div class="card h-100 n-assessment-card">
          <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div class="d-flex align-items-center gap-2">
              <span class="badge text-bg-dark" dir="ltr"><?= e($className) ?></span>
              <span class="small text-muted"><?= e((string) $first['grade_name_ar']) ?></span>
            </div>
            <?php if (!empty($first['pathway_ar'])): ?>
              <span class="badge n-pathway-badge"><?= e((string) $first['pathway_ar']) ?></span>
            <?php endif; ?>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 n-assessment-table">
              <thead>
                <tr>
                  <th><?= e(__('assessments.day')) ?></th>
                  <th><?= e(__('assessments.subjects')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $row): ?>
                  <tr<?= (int) $row['no_assessment'] === 1 ? ' class="n-assessment-none"' : '' ?>>
                    <td class="text-nowrap"><?= e(__($days[(int) $row['day_of_week']] ?? 'assessments.day')) ?></td>
                    <td>
                      <?php if ((int) $row['no_assessment'] === 1): ?>
                        <span class="badge text-bg-secondary"><?= e((string) $row['subjects_raw']) ?></span>
                      <?php else: ?>
                        <?= e((string) $row['subjects_raw']) ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="text-muted small mt-3 mb-0">
    <?= icon('info', 'n-icon-sm') ?>
    <?= e(__('assessments.source_note')) ?>
  </p>
<?php endif; ?>
