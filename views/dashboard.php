<?php
/** @var array $stats @var array $insights */
$pageTitle = __('dashboard.title');
$activeNav = 'dashboard';
$suppressPageTitle = true;
$pageScripts = $stats['hasActiveYear'] ? ['/assets/vendor/chartjs/chart.umd.min.js', '/assets/js/dashboard.js'] : [];
require __DIR__ . '/layout/start.php';

$fullName = (string) ($_SESSION['full_name'] ?? '');
$firstName = trim(explode(' ', $fullName)[0] ?? $fullName);
$yearLabel = \App\Middleware\AcademicYearContext::label();
?>

<section class="n-dashboard-welcome n-reveal" aria-labelledby="dashboard-welcome-title">
  <div>
    <h1 id="dashboard-welcome-title"><?= e(__('dashboard.greeting', ['name' => $firstName])) ?></h1>
    <p><?= e(__('dashboard.overview_subtitle')) ?></p>
  </div>
  <div class="n-dashboard-context">
    <span class="n-context-chip"><?= icon('calendar') ?><span dir="ltr"><?= e($yearLabel ?? __('dashboard.no_academic_year')) ?></span></span>
    <span class="n-context-chip"><?= icon('clock') ?><time dir="ltr" datetime="<?= e(date('Y-m-d')) ?>"><?= e(date('Y-m-d')) ?></time></span>
  </div>
</section>

<?php if (!$stats['hasActiveYear']): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
    <span class="flex-grow-1"><?= e(__('dashboard.no_academic_year')) ?></span>
    <?php if (hasPermission('academic_years.manage')): ?>
      <a class="btn btn-sm btn-outline-warning" href="/academic-years"><?= e(__('academic_years.title')) ?></a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <section aria-labelledby="school-snapshot-title" class="mb-4">
    <div class="n-section-head border-0 px-0 pt-0 pb-2">
      <div><h2 id="school-snapshot-title"><?= e(__('dashboard.school_snapshot')) ?></h2></div>
    </div>
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 n-reveal-group">
      <?php
      $cards = [
          ['dashboard.total_students', $stats['students'], 'users', 'stat-blue'],
          ['dashboard.total_teachers', $stats['teachers'], 'graduation-cap', 'stat-teal'],
          ['dashboard.total_classes', $stats['classes'], 'chalkboard', 'stat-indigo'],
          ['dashboard.total_subjects', $stats['subjects'], 'book', 'stat-violet'],
          ['dashboard.total_grades', $stats['grades'], 'layers', 'stat-amber'],
      ];
      ?>
      <?php foreach ($cards as [$labelKey, $value, $cardIcon, $cardAccent]): ?>
        <div class="col">
          <div class="card stat-card h-100<?= $cardAccent !== '' ? ' ' . e($cardAccent) : '' ?>">
            <div class="card-body">
              <div><div class="stat-value" data-count-up><?= (int) $value ?></div><div class="stat-label"><?= e(__($labelKey)) ?></div></div>
              <div class="stat-icon"><?= icon($cardIcon) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="row g-3 mb-3 n-reveal-group">
    <div class="col-xl-8">
      <section class="card h-100" aria-labelledby="distribution-title">
        <div class="card-header"><span><?= icon('pie-chart') ?></span><span id="distribution-title"><?= e(__('dashboard.student_distribution')) ?></span></div>
        <div class="card-body">
          <?php if ($stats['students'] === 0): ?>
            <div class="n-empty border-0 py-4">
              <div class="n-empty-icon"><?= icon('users') ?></div>
              <p><?= e(__('dashboard.no_students_yet')) ?></p>
            </div>
          <?php else: ?>
            <div class="n-chart-wrap">
              <canvas id="religion-chart" height="145"
                data-chart='<?= e(json_encode([
                    'labels' => [__('dashboard.muslim'), __('dashboard.christian'), __('dashboard.other')],
                    'counts' => [$stats['religion']['muslim'], $stats['religion']['christian'], $stats['religion']['other']],
                ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
    <div class="col-xl-4">
      <section class="card h-100" aria-labelledby="quick-actions-title">
        <div class="card-header"><?= icon('sparkle') ?><span id="quick-actions-title"><?= e(__('dashboard.quick_actions')) ?></span></div>
        <div class="card-body">
          <div class="n-quick-actions-grid">
            <?php if (hasPermission('students.create')): ?><a class="n-quick-action" href="/students/create"><?= icon('users') ?><span><?= e(__('students.add')) ?></span></a><?php endif; ?>
            <?php if (hasPermission('teachers.create')): ?><a class="n-quick-action" href="/teachers/create"><?= icon('graduation-cap') ?><span><?= e(__('teachers.add')) ?></span></a><?php endif; ?>
            <?php if (hasPermission('attendance.manage')): ?><a class="n-quick-action" href="/attendance"><?= icon('check-circle') ?><span><?= e(__('attendance.mark_title')) ?></span></a><?php endif; ?>
            <?php if (hasPermission('exams.manage')): ?><a class="n-quick-action" href="/exams#add-panel"><?= icon('star') ?><span><?= e(__('exams.add')) ?></span></a><?php endif; ?>
            <?php if (hasPermission('reports.view')): ?><a class="n-quick-action" href="/reports"><?= icon('chart') ?><span><?= e(__('reports.title')) ?></span></a><?php endif; ?>
            <?php if (hasPermission('students.promote')): ?><a class="n-quick-action" href="/students/promotion"><?= icon('trending-up') ?><span><?= e(__('promotion.title')) ?></span></a><?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  </div>

  <div class="row g-3 n-reveal-group">
    <div class="col-xl-7">
      <section class="card h-100" aria-labelledby="activity-title">
        <div class="card-header"><span><?= icon('activity') ?></span><span id="activity-title"><?= e(__('dashboard.recent_activity')) ?></span></div>
        <?php if (empty($stats['activity'])): ?>
          <div class="card-body"><div class="n-empty border-0 py-3"><div class="n-empty-icon"><?= icon('inbox') ?></div><p><?= e(__('dashboard.no_activity')) ?></p></div></div>
        <?php else: ?>
          <ul class="n-activity-list">
            <?php foreach ($stats['activity'] as $entry): ?>
              <li>
                <span class="n-activity-marker"><?= icon('activity') ?></span>
                <span class="n-activity-copy"><strong><?= e(activityLabel($entry['action'])) ?></strong><?php if (!empty($entry['description'])): ?><small><?= e($entry['description']) ?></small><?php endif; ?><small><?= e($entry['full_name'] ?? '') ?></small></span>
                <time dir="ltr"><?= e($entry['created_at']) ?></time>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if (hasPermission('activity_log.view')): ?><div class="card-footer text-end"><a href="/activity-log" class="small text-decoration-none"><?= e(__('dashboard.view_all_activity')) ?> <?= icon('arrow-forward', 'n-flip-rtl') ?></a></div><?php endif; ?>
      </section>
    </div>
    <div class="col-xl-5">
      <section class="card h-100" aria-labelledby="review-title">
        <div class="card-header"><?= icon('shield') ?><span id="review-title"><?= e(__('dashboard.at_risk_title')) ?></span><?php if (!empty($insights)): ?><span class="badge text-bg-warning ms-auto"><?= count($insights) ?></span><?php endif; ?></div>
        <?php if (empty($insights)): ?>
          <div class="card-body"><div class="d-flex align-items-start gap-2 text-muted small"><?= icon('check-circle', 'text-success flex-shrink-0') ?><span><?= e(__('dashboard.at_risk_empty')) ?></span></div></div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><th><?= e(__('students.full_name')) ?></th><th><?= e(__('classes.class')) ?></th><th><?= e(__('dashboard.at_risk_absences')) ?></th><th><?= e(__('dashboard.at_risk_incidents')) ?></th></tr></thead>
              <tbody><?php foreach ($insights as $student): ?><tr><td><?php if (hasPermission('students.view')): ?><a href="/students/<?= (int) $student['id'] ?>" class="fw-semibold text-decoration-none"><?= e($student['full_name']) ?></a><?php else: ?><?= e($student['full_name']) ?><?php endif; ?><small class="d-block text-muted mono"><?= e($student['student_code']) ?></small></td><td><?= e($student['class_name']) ?></td><td><span class="badge text-bg-danger"><?= (int) $student['total_absences'] ?></span></td><td><span class="badge text-bg-warning"><?= (int) $student['total_incidents'] ?></span></td></tr><?php endforeach; ?></tbody>
            </table>
          </div>
        <?php endif; ?>
        <div class="card-footer"><div class="n-insight-note"><?= icon('info') ?><span><?= e(__('dashboard.at_risk_disclaimer')) ?></span></div></div>
      </section>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/layout/end.php'; ?>
