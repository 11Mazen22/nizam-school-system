<?php
/** @var array $applications @var array $counts @var ?string $activeStatus */
$pageTitle = __('admissions.title');
$activeNav = 'admissions';
require dirname(__DIR__) . '/layout/start.php';

$stages = [
    'inquiry'     => ['label' => 'admissions.status_inquiry', 'icon' => 'info', 'badge' => 'text-bg-secondary'],
    'application' => ['label' => 'admissions.status_application', 'icon' => 'file-text', 'badge' => 'text-bg-primary'],
    'assessment'  => ['label' => 'admissions.status_assessment', 'icon' => 'clipboard-check', 'badge' => 'text-bg-info text-white'],
    'review'      => ['label' => 'admissions.status_review', 'icon' => 'eye', 'badge' => 'text-bg-warning'],
    'offer'       => ['label' => 'admissions.status_offer', 'icon' => 'sparkle', 'badge' => 'text-bg-success'],
    'enrolled'    => ['label' => 'admissions.status_enrolled', 'icon' => 'check-circle', 'badge' => 'text-bg-success'],
    'waitlisted'  => ['label' => 'admissions.status_waitlisted', 'icon' => 'clock', 'badge' => 'text-bg-dark'],
    'rejected'    => ['label' => 'admissions.status_rejected', 'icon' => 'x-circle', 'badge' => 'text-bg-danger'],
];
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('sparkle', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('admissions.title')) ?>
    </h1>
    <p class="text-muted small mb-0"><?= e(__('admissions.subtitle')) ?></p>
  </div>
  <?php if (hasPermission('admissions.manage')): ?>
    <div>
      <a href="/admissions/create" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <?= icon('plus') ?>
        <span><?= e(__('admissions.new_application')) ?></span>
      </a>
    </div>
  <?php endif; ?>
</div>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?php foreach ($stages as $key => $st): ?>
    <div class="col">
      <a href="/admissions?status=<?= e($key) ?>" class="text-decoration-none">
        <div class="card h-100 <?= $activeStatus === $key ? 'border-primary shadow-sm' : '' ?>">
          <div class="card-body d-flex align-items-center justify-content-between p-3">
            <div>
              <div class="small text-muted"><?= e(__($st['label'])) ?></div>
              <div class="fs-4 fw-bold text-dark"><?= (int) ($counts[$key] ?? 0) ?></div>
            </div>
            <div class="text-secondary"><?= icon($st['icon'], 'n-icon-lg') ?></div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold">
      <?= $activeStatus !== null ? e(__('admissions.status_' . $activeStatus)) : e(__('admissions.all_applications')) ?>
    </span>
    <?php if ($activeStatus !== null): ?>
      <a href="/admissions" class="btn btn-sm btn-link text-decoration-none"><?= e(__('common.clear_filter')) ?></a>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
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
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
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
                <td>
                  <?= e(currentLocale() === 'ar' ? $app['grade_name_ar'] : $app['grade_name_en']) ?>
                </td>
                <td>
                  <div><?= e($app['guardian_name']) ?></div>
                  <div dir="ltr" class="font-monospace small text-muted"><?= e($app['guardian_phone']) ?></div>
                </td>
                <td>
                  <span class="badge <?= $stages[$app['status']]['badge'] ?? 'text-bg-secondary' ?>">
                    <?= e(__('admissions.status_' . $app['status'])) ?>
                  </span>
                </td>
                <td>
                  <?= $app['assessment_score'] !== null ? e((string) $app['assessment_score']) . '%' : '—' ?>
                </td>
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
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
