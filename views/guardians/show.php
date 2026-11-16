<?php
/** @var array $guardian @var array $students */
$pageTitle = $guardian['full_name'];
$activeNav = 'guardians';
ob_start(); ?>
<a href="/guardians" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
    <?php if (hasPermission('guardians.manage')): ?>
      <a href="/guardians/<?= (int) $guardian['id'] ?>/edit" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <?= icon('edit') ?>
        <span><?= e(__('common.edit')) ?></span>
      </a>
    <?php endif; ?>
<?php $pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>



<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header fw-semibold"><?= e(__('guardians.contact_info')) ?></div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="mb-2">
            <span class="text-muted small d-block"><?= e(__('guardians.phone')) ?></span>
            <span dir="ltr" class="font-monospace fw-semibold"><?= e($guardian['phone']) ?></span>
          </li>
          <li class="mb-2">
            <span class="text-muted small d-block"><?= e(__('guardians.email')) ?></span>
            <span dir="ltr"><?= e($guardian['email'] ?? '—') ?></span>
          </li>
          <li class="mb-2">
            <span class="text-muted small d-block"><?= e(__('guardians.national_id')) ?></span>
            <span dir="ltr" class="font-monospace"><?= e($guardian['national_id'] ?? '—') ?></span>
          </li>
          <li class="mb-2">
            <span class="text-muted small d-block"><?= e(__('guardians.occupation')) ?></span>
            <span><?= e($guardian['occupation'] ?? '—') ?></span>
          </li>
          <li class="mb-0">
            <span class="text-muted small d-block"><?= e(__('guardians.address')) ?></span>
            <span><?= e($guardian['address'] ?? '—') ?></span>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e(__('guardians.linked_children')) ?></span>
        <span class="badge text-bg-primary"><?= count($students) ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($students)): ?>
          <div class="n-empty py-4">
            <div class="n-empty-icon"><?= icon('users') ?></div>
            <p class="text-muted small mb-0"><?= e(__('guardians.no_linked_children')) ?></p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th><?= e(__('students.code')) ?></th>
                  <th><?= e(__('students.name')) ?></th>
                  <th><?= e(__('classes.class')) ?></th>
                  <th><?= e(__('guardians.relationship')) ?></th>
                  <th class="text-end"><?= e(__('common.action')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $s): ?>
                  <tr>
                    <td dir="ltr" class="font-monospace small"><?= e($s['student_code']) ?></td>
                    <td class="fw-semibold">
                      <a href="/students/<?= (int) $s['id'] ?>" class="text-decoration-none">
                        <?= e($s['full_name']) ?>
                      </a>
                    </td>
                    <td>
                      <?= e((currentLocale() === 'ar' ? $s['grade_name_ar'] : $s['grade_name_en']) ?? '') ?>
                      <?= !empty($s['class_name']) ? ' - ' . e($s['class_name']) : '' ?>
                    </td>
                    <td>
                      <span class="badge text-bg-light border">
                        <?= e(__('guardians.rel_' . ($s['relationship'] ?? 'father'))) ?>
                      </span>
                      <?php if (dbBool($s['is_primary'])): ?>
                        <span class="badge text-bg-success small"><?= e(__('guardians.primary')) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <a href="/students/<?= (int) $s['id'] ?>" class="btn btn-sm btn-outline-secondary">
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
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
