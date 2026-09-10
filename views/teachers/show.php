<?php
/** @var array $teacher @var array $qualifications */
$pageTitle = $teacher['full_name'];
$activeNav = 'teachers';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div class="text-muted"><?= e($teacher['teacher_code']) ?></div>
  <div class="d-flex gap-2">
    <a href="/teachers/<?= (int) $teacher['id'] ?>/edit" class="btn btn-outline-secondary"><?= e(__('common.edit')) ?></a>
    <?php if ($teacher['status'] === 'active'): ?>
      <form method="post" action="/teachers/<?= (int) $teacher['id'] ?>/archive" data-confirm="<?= e(__('common.confirm_archive')) ?>">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-outline-danger"><?= e(__('common.archive')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <?php if (!empty($teacher['photo_path'])): ?>
      <img src="/teachers/<?= (int) $teacher['id'] ?>/photo" alt="" style="width:96px; height:96px; object-fit:cover;" class="rounded-circle border mb-3">
    <?php endif; ?>
    <dl class="row mb-0">
      <dt class="col-sm-3"><?= e(__('teachers.full_name')) ?></dt><dd class="col-sm-9"><?= e($teacher['full_name']) ?></dd>
      <dt class="col-sm-3"><?= e(__('teachers.phone')) ?></dt><dd class="col-sm-9"><?= e($teacher['phone'] ?? '—') ?></dd>
      <dt class="col-sm-3"><?= e(__('teachers.email')) ?></dt><dd class="col-sm-9"><?= e($teacher['email'] ?? '—') ?></dd>
    </dl>
  </div>
</div>

<div class="card">
  <div class="card-header"><?= e(__('teachers.qualifications')) ?></div>
  <div class="card-body">
    <?php if (empty($qualifications)): ?>
      <p class="text-muted mb-0"><?= e(__('teachers.no_qualifications')) ?></p>
    <?php else: ?>
      <?php foreach ($qualifications as $subject): ?>
        <span class="badge text-bg-light border me-1 mb-1"><?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?></span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
