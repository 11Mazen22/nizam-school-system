<?php
/** @var array $stats @var array $insights */
$pageTitle = __('dashboard.title');
$suppressPageTitle = true;
$activeNav = 'dashboard';
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
    <span class="n-context-chip"><?= icon('building') ?><span><?= e($schoolName) ?></span></span>
    <span class="n-context-chip"><?= icon('calendar') ?><span dir="ltr"><?= e($yearLabel ?? __('dashboard.no_academic_year')) ?></span></span>
    <span class="n-context-chip"><?= icon('clock') ?><time dir="ltr" datetime="<?= e(date('Y-m-d')) ?>"><?= e(date('Y-m-d')) ?></time></span>
    <span class="n-context-chip"><span class="badge text-bg-primary text-white"><?= e(__('roles.' . ($_SESSION['role_code'] ?? 'admin'))) ?></span></span>
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

  <?php
  $att = $stats['attendance'];
  $pending = $stats['pendingAttendance'];
  $capacityRows = $stats['capacity'];
  $overCapacity = array_values(array_filter($capacityRows, static fn (array $r): bool => $r['capacity'] !== null && $r['enrolled'] > $r['capacity']));
  $className = static fn (array $r): string => (currentLocale() === 'ar' ? $r['grade_name_ar'] : $r['grade_name_en']) . ' — ' . $r['class_name'];
  $attRate = $att !== null && $att['total'] > 0 ? (int) round((($att['present'] + $att['late']) / $att['total']) * 100) : null;
  $showOps = $att !== null || $capacityRows !== [] || hasPermission('classes.view');
  ?>
  <?php if ($showOps): ?>
    <div class="row g-3 mb-3 n-reveal-group">
      <?php if ($att !== null): ?>
        <div class="col-xl-5">
          <section class="card h-100" aria-labelledby="attendance-today-title">
            <div class="card-header"><span><?= icon('check-circle') ?></span><span id="attendance-today-title"><?= e(__('dashboard.attendance_today')) ?></span></div>
            <div class="card-body">
              <?php if ($att['total'] === 0): ?>
                <div class="n-empty border-0 py-3">
                  <div class="n-empty-icon"><?= icon('check-circle') ?></div>
                  <p class="mb-1"><strong><?= e(__('dashboard.attendance_none')) ?></strong></p>
                  <p class="text-muted small"><?= e(__('dashboard.attendance_none_hint')) ?></p>
                  <?php if (hasPermission('attendance.manage')): ?><a class="btn btn-sm btn-primary" href="/attendance"><?= e(__('dashboard.attendance_open')) ?></a><?php endif; ?>
                </div>
              <?php else: ?>
                <div class="n-att-rate">
                  <span class="n-att-rate-value" dir="ltr"><?= (int) $attRate ?>%</span>
                  <span class="n-att-rate-label"><?= e(__('dashboard.attendance_rate')) ?><small><?= e(__('dashboard.attendance_rate_note')) ?></small></span>
                </div>
                <div class="n-att-bar" role="img" aria-label="<?= e(__('dashboard.attendance_today')) ?>">
                  <?php foreach (['present', 'late', 'excused', 'absent'] as $st): if ($att[$st] > 0): ?>
                    <span class="n-att-seg n-att-<?= $st ?>" style="flex-grow: <?= (int) $att[$st] ?>"></span>
                  <?php endif; endforeach; ?>
                </div>
                <ul class="n-att-legend">
                  <?php foreach (['present', 'late', 'excused', 'absent'] as $st): ?>
                    <li><i class="n-att-dot n-att-<?= $st ?>" aria-hidden="true"></i><span><?= e(__('attendance.status_' . $st)) ?></span><strong dir="ltr"><?= (int) $att[$st] ?></strong></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </section>
        </div>
        <div class="col-xl-7">
          <section class="card h-100" aria-labelledby="attention-title">
            <div class="card-header"><?= icon('alert-triangle') ?><span id="attention-title"><?= e(__('dashboard.attention_title')) ?></span><?php $attentionCount = count($pending) + count($overCapacity) + count($stats['actionItems'] ?? []); if ($attentionCount > 0): ?><span class="badge text-bg-warning ms-auto"><?= $attentionCount ?></span><?php endif; ?></div>
            <?php if ($attentionCount === 0): ?>
              <div class="card-body"><div class="d-flex align-items-start gap-2 text-muted small"><?= icon('check-circle', 'text-success flex-shrink-0') ?><span><?= e(__('dashboard.attention_empty')) ?></span></div></div>
            <?php else: ?>
              <ul class="n-attention-list">
                <?php foreach (($stats['actionItems'] ?? []) as $item): ?>
                  <li>
                    <span class="n-attention-tag <?= $item['severity'] === 'danger' ? 'n-attention-tag-danger' : '' ?>">
                      <?= icon('alert-circle') ?><?= e(__($item['title_key'])) ?>
                    </span>
                    <strong><?= e($item['description']) ?></strong>
                    <a class="ms-auto small" href="<?= e($item['url']) ?>"><?= e(__('dashboard.take_action')) ?></a>
                  </li>
                <?php endforeach; ?>
                <?php foreach ($pending as $row): ?>
                  <li>
                    <span class="n-attention-tag"><?= icon('clock') ?><?= e(__('dashboard.attention_pending')) ?></span>
                    <strong><?= e($className($row)) ?></strong>
                    <?php if (hasPermission('attendance.manage')): ?><a class="ms-auto small" href="/attendance?class_id=<?= (int) $row['class_id'] ?>"><?= e(__('dashboard.attendance_open')) ?></a><?php endif; ?>
                  </li>
                <?php endforeach; ?>
                <?php foreach ($overCapacity as $row): ?>
                  <li>
                    <span class="n-attention-tag n-attention-tag-danger"><?= icon('alert-triangle') ?><?= e(__('dashboard.attention_over')) ?></span>
                    <strong><?= e($className($row)) ?></strong>
                    <span class="ms-auto small text-muted" dir="ltr"><?= (int) $row['enrolled'] ?> / <?= (int) $row['capacity'] ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($stats['qualityIssues'])): ?>
    <section class="card mb-3 border-warning" aria-labelledby="quality-title">
      <div class="card-header bg-warning-subtle text-dark d-flex align-items-center justify-content-between">
        <span class="d-flex align-items-center gap-2 fw-semibold">
          <?= icon('alert-triangle') ?>
          <span id="quality-title"><?= e(__('quality.center_title')) ?></span>
        </span>
        <span class="badge text-bg-warning"><?= count($stats['qualityIssues']) ?></span>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($stats['qualityIssues'] as $qi): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold text-danger"><?= e(__($qi['title_key'])) ?></div>
                <div class="small text-muted"><?= e($qi['description']) ?></div>
              </div>
              <a href="<?= e($qi['action_url']) ?>" class="btn btn-sm btn-outline-warning">
                <?= e(__($qi['action_label_key'])) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($stats['financeSummary']) && ($stats['financeSummary']['invoice_count'] ?? 0) > 0): $fin = $stats['financeSummary']; ?>
    <section class="card mb-3" aria-labelledby="fin-title">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="d-flex align-items-center gap-2 fw-semibold">
          <?= icon('wallet') ?>
          <span id="fin-title"><?= e(__('finance.overview_title')) ?></span>
        </span>
        <a href="/finance" class="btn btn-sm btn-link text-decoration-none"><?= e(__('finance.manage')) ?></a>
      </div>
      <div class="card-body">
        <div class="row row-cols-1 row-cols-md-3 g-3">
          <div class="col">
            <div class="p-3 border rounded bg-light">
              <div class="small text-muted"><?= e(__('finance.total_billed')) ?></div>
              <div class="fs-4 fw-bold" dir="ltr"><?= number_format((float) $fin['total_billed'], 2) ?></div>
            </div>
          </div>
          <div class="col">
            <div class="p-3 border rounded bg-light">
              <div class="small text-muted"><?= e(__('finance.total_collected')) ?></div>
              <div class="fs-4 fw-bold text-success" dir="ltr"><?= number_format((float) $fin['total_collected'], 2) ?></div>
            </div>
          </div>
          <div class="col">
            <div class="p-3 border rounded bg-light">
              <div class="small text-muted"><?= e(__('finance.total_outstanding')) ?></div>
              <div class="fs-4 fw-bold text-danger" dir="ltr"><?= number_format((float) $fin['total_outstanding'], 2) ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (hasPermission('classes.view')): ?>
    <section class="card mb-3 n-reveal" aria-labelledby="capacity-title">
      <div class="card-header"><span><?= icon('chalkboard') ?></span><span id="capacity-title"><?= e(__('dashboard.capacity_title')) ?></span></div>
      <?php if ($capacityRows === []): ?>
        <div class="card-body"><div class="n-empty border-0 py-3"><div class="n-empty-icon"><?= icon('chalkboard') ?></div><p><?= e(__('dashboard.capacity_empty')) ?></p></div></div>
      <?php else: ?>
        <ul class="n-capacity-grid">
          <?php foreach ($capacityRows as $row):
            $pct = $row['capacity'] !== null && $row['capacity'] > 0 ? min(100, (int) round($row['enrolled'] / $row['capacity'] * 100)) : null;
            $state = $pct === null ? 'unset' : ($row['enrolled'] > $row['capacity'] ? 'over' : ($pct >= 90 ? 'near' : 'ok')); ?>
            <li class="n-capacity-item n-capacity-<?= $state ?>">
              <div class="n-capacity-name"><?= e($className($row)) ?></div>
              <div class="n-capacity-track" aria-hidden="true"><span style="width: <?= (int) ($pct ?? 0) ?>%"></span></div>
              <div class="n-capacity-meta" dir="ltr">
                <span><?= (int) $row['enrolled'] ?><?= $row['capacity'] !== null ? ' / ' . (int) $row['capacity'] : '' ?></span>
                <?php if ($state === 'unset'): ?><em><?= e(__('dashboard.capacity_unset')) ?></em><?php elseif ($state === 'over'): ?><em><?= e(__('dashboard.attention_over')) ?></em><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  <?php endif; ?>

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
