<?php
/** @var ?string $error @var ?array $student @var array $grades @var array $classes @var ?array $currentEnrollment */
$pageTitle = $student === null ? __('students.add') : __('students.edit');
$activeNav = 'students';
$pageScripts = $student === null || $grades !== [] ? ['/assets/js/students-form.js'] : [];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon($student === null ? 'plus' : 'edit', 'n-icon-lg text-body-secondary') ?>
      <?= e($pageTitle) ?>
    </h1>
  </div>
</div>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 720px;">
  <div class="card-body">
    <form method="post" action="<?= $student === null ? '/students' : '/students/' . (int) $student['id'] ?>" enctype="multipart/form-data">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label d-block"><?= e(__('students.photo')) ?></label>
        <?php if (!empty($student['photo_path'])): ?>
          <div class="d-flex align-items-center gap-3 mb-2">
            <img src="/students/<?= (int) $student['id'] ?>/photo" alt="" style="width:64px; height:64px; object-fit:cover;" class="rounded-circle border">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
              <label class="form-check-label" for="remove_photo"><?= e(__('common.remove_photo')) ?></label>
            </div>
          </div>
        <?php endif; ?>
        <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="full_name"><?= e(__('students.full_name')) ?></label>
          <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($student['full_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="gender"><?= e(__('students.gender')) ?></label>
          <select class="form-select" id="gender" name="gender" required>
            <option value="m" <?= ($student['gender'] ?? '') === 'm' ? 'selected' : '' ?>><?= e(__('students.gender_m')) ?></option>
            <option value="f" <?= ($student['gender'] ?? '') === 'f' ? 'selected' : '' ?>><?= e(__('students.gender_f')) ?></option>
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="date_of_birth"><?= e(__('students.date_of_birth')) ?></label>
          <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($student['date_of_birth'] ?? '') ?>" required>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="religion"><?= e(__('students.religion')) ?></label>
          <select class="form-select" id="religion" name="religion" required>
            <?php foreach (['muslim', 'christian', 'other'] as $r): ?>
              <option value="<?= $r ?>" <?= ($student['religion'] ?? '') === $r ? 'selected' : '' ?>><?= e(__('students.religion_' . $r)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="phone"><?= e(__('students.phone')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="phone" name="phone" value="<?= e($student['phone'] ?? '') ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="guardian_phone"><?= e(__('students.guardian_phone')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="guardian_phone" name="guardian_phone" value="<?= e($student['guardian_phone'] ?? '') ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="address"><?= e(__('students.address')) ?> (<?= e(__('common.optional')) ?>)</label>
        <input type="text" class="form-control" id="address" name="address" value="<?= e($student['address'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label" for="notes"><?= e(__('students.notes')) ?> (<?= e(__('common.optional')) ?>)</label>
        <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($student['notes'] ?? '') ?></textarea>
      </div>

      <?php if ($student === null): ?>
        <hr>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="grade_id"><?= e(__('students.grade')) ?></label>
            <select class="form-select" id="grade_id" name="grade_id" required>
              <?php foreach ($grades as $grade): ?>
                <option value="<?= (int) $grade['id'] ?>"><?= e(currentLocale() === 'ar' ? $grade['name_ar'] : $grade['name_en']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label" for="class_id"><?= e(__('students.class')) ?> (<?= e(__('common.optional')) ?>)</label>
            <!-- Every active class in the year is rendered up front, tagged with
                 its own grade -- students-form.js shows only the ones matching
                 the current grade_id selection. The service re-validates the
                 submitted pair server-side regardless (§Q "never trust client
                 validation for security"), so this is a UX filter, not the
                 actual guarantee. -->
            <select class="form-select" id="class_id" name="class_id">
              <option value=""><?= e(__('promotion.unassigned')) ?></option>
              <?php foreach ($classes as $class): ?>
                <option value="<?= (int) $class['id'] ?>" data-grade-id="<?= (int) $class['grade_id'] ?>"><?= e($class['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($student !== null && isset($currentEnrollment) && $currentEnrollment !== null && $currentEnrollment['status'] === 'active'): ?>
        <hr>
        <div class="mb-3">
          <label class="form-label" for="class_id"><?= e(__('students.class')) ?></label>
          <select class="form-select" id="class_id" name="class_id" required>
            <?php foreach ($classes as $class): ?>
              <option value="<?= (int) $class['id'] ?>" <?= (int) $class['id'] === (int) ($currentEnrollment['class_id'] ?? 0) ? 'selected' : '' ?>><?= e($class['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text"><?= e(__('students.edit_class_help')) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($student !== null && isset($currentEnrollment) && $currentEnrollment === null && $grades !== []): ?>
        <hr>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="grade_id"><?= e(__('students.grade')) ?></label>
            <select class="form-select" id="grade_id" name="grade_id" required>
              <?php foreach ($grades as $grade): ?>
                <option value="<?= (int) $grade['id'] ?>"><?= e(currentLocale() === 'ar' ? $grade['name_ar'] : $grade['name_en']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label" for="class_id"><?= e(__('students.class')) ?></label>
            <select class="form-select" id="class_id" name="class_id" required>
              <?php foreach ($classes as $class): ?>
                <option value="<?= (int) $class['id'] ?>" data-grade-id="<?= (int) $class['grade_id'] ?>"><?= e($class['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><?= e(__('students.edit_class_help')) ?></div>
          </div>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
        <?= icon('check') ?>
        <span><?= e(__('common.save')) ?></span>
      </button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
