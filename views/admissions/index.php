<?php
/**
 * Admissions pipeline.
 *
 * Rebuilt on the Ink & Saffron system: one hero statement, the nine real
 * pipeline stages as filter tiles, then the ledger table. The stage list is
 * derived from the enum in 019_universal_platform_foundation.sql -- an earlier
 * version invented review/offer/waitlisted labels while omitting "decision",
 * so three tiles showed raw translation keys and one real stage was invisible.
 *
 * @var array $applications
 * @var array $counts
 * @var ?string $activeStatus
 */
$pageTitle = __('admissions.title');
$activeNav = 'admissions';
$pageSubtitle = __('admissions.subtitle');

$stages = [
    'inquiry'     => ['icon' => 'info', 'tone' => 'muted'],
    'application' => ['icon' => 'file-text', 'tone' => ''],
    'review'      => ['icon' => 'eye', 'tone' => 'amber'],
    'assessment'  => ['icon' => 'clipboard-check', 'tone' => ''],
    'decision'    => ['icon' => 'filter', 'tone' => 'amber'],
    'offer'       => ['icon' => 'sparkle', 'tone' => 'success'],
    'enrolled'    => ['icon' => 'check-circle', 'tone' => 'success'],
    'waitlisted'  => ['icon' => 'clock', 'tone' => 'muted'],
    'rejected'    => ['icon' => 'x-circle', 'tone' => 'danger'],
];

$badges = [
    'inquiry'     => 'text-bg-secondary',
    'application' => 'text-bg-primary',
    'review'      => 'text-bg-warning',
    'assessment'  => 'text-bg-info text-white',
    'decision'    => 'text-bg-warning',
    'offer'       => 'text-bg-success',
    'enrolled'    => 'text-bg-success',
    'waitlisted'  => 'text-bg-dark',
    'rejected'    => 'text-bg-danger',
];

$total = 0;
foreach (array_keys($stages) as $stageKey) {
    $total += (int) ($counts[$stageKey] ?? 0);
}

// Shared hero: this screen contributes its total and primary action, and the
// layout renders the eyebrow, title and subtitle like every other page.
ob_start(); ?>
<div class="n-hero-stat">
  <b><?= $total ?></b>
  <span><?= e(__('admissions.all_applications')) ?></span>
</div>
<?php if (hasPermission('admissions.manage')): ?>
  <a href="/admissions/create" class="btn btn-primary d-inline-flex align-items-center gap-2">
    <?= icon('plus') ?>
    <span><?= e(__('admissions.new_application')) ?></span>
  </a>
<?php endif; ?>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-stage-grid mb-4">
  <?php foreach ($stages as $key => $stage): ?>
    <a class="n-stage-tile<?= $activeStatus === $key ? ' is-active' : '' ?>"
       href="/admissions?status=<?= e($key) ?>"
       <?= $stage['tone'] !== '' ? 'data-tone="' . e($stage['tone']) . '"' : '' ?>
       <?= $activeStatus === $key ? 'aria-current="page"' : '' ?>>
      <span class="n-stage-icon"><?= icon($stage['icon']) ?></span>
      <span class="n-stage-meta">
        <span class="n-stage-label"><?= e(__('admissions.status_' . $key)) ?></span>
        <span class="n-stage-count"><?= (int) ($counts[$key] ?? 0) ?></span>
      </span>
    </a>
  <?php endforeach; ?>
</div>

<?php $applications = filterRowsBySearch($applications, (string) ($_GET['q'] ?? ''), ['application_number', 'applicant_name', 'guardian_name', 'guardian_phone', 'status']); ?>
<?php if (!empty($applications)) { $listSearchTarget = '#applications-table'; $listSearchAction = '/admissions'; $listSearchHidden = ['status' => $activeStatus ?? '']; require dirname(__DIR__) . '/partials/list-search.php'; } ?>
<div class="n-data-panel">
  <div class="card-header d-flex justify-content-between align-items-center gap-3">
    <span class="fw-semibold">
      <?= $activeStatus !== null && isset($stages[$activeStatus])
          ? e(__('admissions.status_' . $activeStatus))
          : e(__('admissions.all_applications')) ?>
    </span>
    <div class="d-flex align-items-center gap-3">
      <span class="small text-body-secondary"><?= count($applications) ?></span>
      <?php if ($activeStatus !== null): ?>
        <a href="/admissions" class="btn btn-sm btn-link text-decoration-none p-0"><?= e(__('common.clear_filter')) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($applications)): ?>
    <div class="n-empty py-5">
      <div class="n-empty-icon"><?= icon('sparkle') ?></div>
      <p class="mb-2 fw-semibold"><?= e(__('admissions.empty_title')) ?></p>
      <p class="text-muted small mb-3"><?= e(__('admissions.empty_hint')) ?></p>
      <?php if (hasPermission('admissions.manage')): ?>
        <a href="/admissions/create" class="btn btn-sm btn-primary"><?= e(__('admissions.new_application')) ?></a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table id="applications-table" class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th><?= e(__('admissions.app_number')) ?></th>
            <th><?= e(__('admissions.applicant_name')) ?></th>
            <th><?= e(__('grades.grade')) ?></th>
            <th><?= e(__('admissions.guardian_contact')) ?></th>
            <th><?= e(__('admissions.status')) ?></th>
            <th><?= e(__('admissions.assessment_score')) ?></th>
            <th class="text-end"><?= e(__('common.action')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applications as $app): ?>
            <tr>
              <td dir="ltr" class="font-monospace small fw-bold text-primary">
                <a href="/admissions/<?= (int) $app['id'] ?>" class="text-decoration-none">
                  <?= e($app['application_number']) ?>
                </a>
              </td>
              <td class="fw-semibold">
                <a href="/admissions/<?= (int) $app['id'] ?>" class="text-decoration-none">
                  <?= e($app['applicant_name']) ?>
                </a>
                <div class="small text-muted"><?= e($app['date_of_birth']) ?></div>
              </td>
              <td><?= e(currentLocale() === 'ar' ? $app['grade_name_ar'] : $app['grade_name_en']) ?></td>
              <td>
                <div><?= e($app['guardian_name']) ?></div>
                <div dir="ltr" class="font-monospace small text-muted"><?= e($app['guardian_phone']) ?></div>
              </td>
              <td>
                <span class="badge <?= $badges[$app['status']] ?? 'text-bg-secondary' ?>">
                  <?= e(__('admissions.status_' . $app['status'])) ?>
                </span>
              </td>
              <td><?= $app['assessment_score'] !== null ? e((string) $app['assessment_score']) . '%' : '—' ?></td>
              <td class="text-end">
                <a href="/admissions/<?= (int) $app['id'] ?>" class="btn btn-sm btn-outline-secondary">
                  <?= icon('eye') ?>
                  <span><?= e(__('common.view')) ?></span>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
