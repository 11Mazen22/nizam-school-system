<?php
/** @var array $guardians */
$pageTitle = __('guardians.title');
$suppressPageTitle = true;
$activeNav = 'guardians';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('users-group', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('guardians.title')) ?>
    </h1>
    <p class="text-muted small mb-0"><?= e(__('guardians.subtitle')) ?></p>
  </div>
  <?php if (hasPermission('guardians.manage')): ?>
    <div>
      <a href="/guardians/create" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <?= icon('plus') ?>
        <span><?= e(__('guardians.add')) ?></span>
      </a>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($guardians)): ?>
      <div class="n-empty py-5">
        <div class="n-empty-icon"><?= icon('users-group') ?></div>
        <p class="mb-2 fw-semibold"><?= e(__('guardians.empty_title')) ?></p>
        <p class="text-muted small mb-3"><?= e(__('guardians.empty_hint')) ?></p>
        <?php if (hasPermission('guardians.manage')): ?>
          <a href="/guardians/create" class="btn btn-sm btn-primary"><?= e(__('guardians.add')) ?></a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('guardians.name')) ?></th>
              <th><?= e(__('guardians.phone')) ?></th>
              <th><?= e(__('guardians.relationship')) ?></th>
              <th><?= e(__('guardians.national_id')) ?></th>
              <th><?= e(__('guardians.students_count')) ?></th>
              <th class="text-end"><?= e(__('common.action')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($guardians as $g): ?>
              <tr>
                <td>
                  <a href="/guardians/<?= (int) $g['id'] ?>" class="fw-semibold text-decoration-none">
                    <?= e($g['full_name']) ?>
                  </a>
                  <?php if (!empty($g['occupation'])): ?>
                    <div class="small text-muted"><?= e($g['occupation']) ?></div>
                  <?php endif; ?>
                </td>
                <td dir="ltr" class="font-monospace small"><?= e($g['phone']) ?></td>
                <td>
                  <span class="badge text-bg-light border">
                    <?= e(__('guardians.rel_' . ($g['relationship_type'] ?? 'father'))) ?>
                  </span>
                </td>
                <td dir="ltr" class="font-monospace small"><?= e($g['national_id'] ?? '—') ?></td>
                <td>
                  <span class="badge text-bg-info text-white">
                    <?= (int) $g['student_count'] ?> <?= e(__('guardians.linked_children')) ?>
                  </span>
                </td>
                <td class="text-end">
                  <a href="/guardians/<?= (int) $g['id'] ?>" class="btn btn-sm btn-outline-secondary">
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
