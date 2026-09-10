<?php
/** @var ?string $error @var ?array $teacher @var array $subjects @var int[] $selected */
$pageTitle = $teacher === null ? __('teachers.add') : __('teachers.edit');
$activeNav = 'teachers';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width: 640px;">
  <div class="card-body">
    <form method="post" action="<?= $teacher === null ? '/teachers' : '/teachers/' . (int) $teacher['id'] ?>" enctype="multipart/form-data">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label d-block"><?= e(__('teachers.photo')) ?></label>
        <?php if (!empty($teacher['photo_path'])): ?>
          <div class="d-flex align-items-center gap-3 mb-2">
            <img src="/teachers/<?= (int) $teacher['id'] ?>/photo" alt="" style="width:64px; height:64px; object-fit:cover;" class="rounded-circle border">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
              <label class="form-check-label" for="remove_photo"><?= e(__('common.remove_photo')) ?></label>
            </div>
          </div>
        <?php endif; ?>
        <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
      </div>
      <div class="mb-3">
        <label class="form-label" for="full_name"><?= e(__('teachers.full_name')) ?></label>
        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($teacher['full_name'] ?? '') ?>" required>
      </div>
      <div class="row">
        <div class="col-6 mb-3">
          <label class="form-label" for="phone"><?= e(__('teachers.phone')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="phone" name="phone" value="<?= e($teacher['phone'] ?? '') ?>">
        </div>
        <div class="col-6 mb-3">
          <label class="form-label" for="email"><?= e(__('teachers.email')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="email" class="form-control" id="email" name="email" value="<?= e($teacher['email'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(__('teachers.qualifications')) ?></label>
        <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
          <?php foreach ($subjects as $subject): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="subject_ids[]"
                     value="<?= (int) $subject['id'] ?>" id="subj-<?= (int) $subject['id'] ?>"
                     <?= in_array((int) $subject['id'], $selected, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="subj-<?= (int) $subject['id'] ?>">
                <?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?= e(__('common.save')) ?></button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
