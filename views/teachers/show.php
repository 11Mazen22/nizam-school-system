<?php
/** @var array $teacher @var array $qualifications */
$pageTitle = $teacher['full_name'];
$activeNav = 'teachers';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?php if (!empty($teacher['photo_path'])): ?>
        <img src="/teachers/<?= (int) $teacher['id'] ?>/photo" alt="" style="width:40px; height:40px; object-fit:cover;" class="rounded-circle border">
      <?php else: ?>
        <span class="n-avatar" style="width:40px; height:40px;"><?= e(mb_substr($teacher['full_name'], 0, 1)) ?></span>
      <?php endif; ?>
      <?= e($teacher['full_name']) ?>
    </h1>
    <p class="mono mb-0"><?= e($teacher['teacher_code']) ?></p>
  </div>
  <div class="n-page-head-actions">
    <a href="/teachers/<?= (int) $teacher['id'] ?>/edit" class="btn btn-outline-secondary d-flex align-items-center gap-2">
      <?= icon('edit') ?>
      <span><?= e(__('common.edit')) ?></span>
    </a>
    <?php if ($teacher['status'] === 'active'): ?>
      <form method="post" action="/teachers/<?= (int) $teacher['id'] ?>/archive" data-confirm="<?= e(__('common.confirm_archive')) ?>">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-outline-danger d-flex align-items-center gap-2">
          <?= icon('archive') ?>
          <span><?= e(__('common.archive')) ?></span>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex align-items-center gap-2">
    <?= icon('graduation-cap') ?>
    <span><?= e(__('teachers.full_name')) ?></span>
  </div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3"><?= e(__('teachers.full_name')) ?></dt><dd class="col-sm-9"><?= e($teacher['full_name']) ?></dd>
      <dt class="col-sm-3"><?= e(__('teachers.phone')) ?></dt><dd class="col-sm-9"><?= e($teacher['phone'] ?? '—') ?></dd>
      <dt class="col-sm-3"><?= e(__('teachers.email')) ?></dt><dd class="col-sm-9"><?= e($teacher['email'] ?? '—') ?></dd>
    </dl>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center gap-2">
    <?= icon('book') ?>
    <span><?= e(__('teachers.qualifications')) ?></span>
  </div>
  <div class="card-body">
    <?php if (empty($qualifications)): ?>
      <div class="n-empty py-3">
        <div class="n-empty-icon"><?= icon('book') ?></div>
        <p class="mb-0"><?= e(__('teachers.no_qualifications')) ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($qualifications as $subject): ?>
        <span class="badge text-bg-light border me-1 mb-1"><?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?></span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
