<?php
/** @var array $teachers @var string $q */
$pageTitle = __('teachers.title');
$activeNav = 'teachers';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';

// Calculate stats
$totalTeachers = count($teachers);
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('graduation-cap', 'n-grades-icon') ?>
        </div>
        <?= e(__('teachers.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('teachers.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value" data-count-up><?= $totalTeachers ?></span>
          <span class="n-stat-mini-label"><?= e(__('teachers.total_teachers')) ?></span>
        </div>
      </div>
      <button type="button" class="btn btn-outline-light d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importModal">
        <?= icon("upload") ?>
        <span><?= e(__("app.import")) ?></span>
      </button>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between mb-3 gap-2 flex-wrap">
  <form method="get" action="/teachers" class="d-flex gap-2">
    <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('common.search')) ?>">
    <button type="submit" class="btn btn-outline-secondary"><?= icon('search') ?> <?= e(__('common.search')) ?></button>
  </form>
  <div class="d-flex gap-2">
    <a href="/teachers/archived" class="btn btn-outline-secondary"><?= icon('archive') ?> <?= e(__('common.archived_list')) ?></a>
    <a href="/teachers/create" class="btn btn-primary"><?= icon('plus') ?> <?= e(__('teachers.add')) ?></a>
  </div>
</div>

<?php if (empty($teachers)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon" style="background: linear-gradient(135deg, var(--n-primary) 0%, var(--n-primary-strong) 100%);">
      <?= icon('users', 'n-icon-xl') ?>
    </div>
    <h3 class="n-empty-title"><?= e(__('teachers.empty_title')) ?></h3>
    <p class="n-empty-description"><?= e(__('teachers.empty_description')) ?></p>
    <a href="/teachers/create" class="btn btn-primary btn-lg mt-2">
      <?= icon('plus') ?> <?= e(__('teachers.add')) ?>
    </a>
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('teachers.code')) ?></th>
          <th><?= e(__('teachers.full_name')) ?></th>
          <th><?= e(__('teachers.phone')) ?></th>
          <th><?= e(__('teachers.email')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teachers as $teacher): ?>
          <tr>
            <td class="mono"><?= e($teacher['teacher_code']) ?></td>
            <td>
              <a href="/teachers/<?= (int) $teacher['id'] ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                <?php if (!empty($teacher['photo_path'])): ?>
                  <img src="/teachers/<?= (int) $teacher['id'] ?>/photo" alt="" style="width:28px; height:28px; object-fit:cover;" class="rounded-circle">
                <?php endif; ?>
                <?= e($teacher['full_name']) ?>
              </a>
            </td>
            <td><?= e($teacher['phone'] ?? '—') ?></td>
            <td><?= e($teacher['email'] ?? '—') ?></td>
            <td class="text-nowrap">
              <a href="/teachers/<?= (int) $teacher['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= e(__('common.view')) ?></a>
              <a href="/teachers/<?= (int) $teacher['id'] ?>/edit" class="btn btn-sm btn-outline-secondary"><?= e(__('common.edit')) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php
ob_start();
$title = __("app.import");
$action = "/import/teachers";
$templateUrl = "/import/teachers/template";
require dirname(__DIR__) . "/partials/import_modal.php";
$pageModals = ob_get_clean();
require dirname(__DIR__) . '/layout/end.php';
?>