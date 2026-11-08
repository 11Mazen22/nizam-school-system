<?php
/** @var ?string $error @var ?array $student @var array $grades @var array $classes @var ?array $currentEnrollment */
$isEdit = $student !== null;
$pageTitle = $isEdit ? __('students.edit') : __('students.add');
$activeNav = 'students';
$pageScripts = !$isEdit || $grades !== [] ? ['/assets/js/students-form.js'] : [];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div class="n-page-head-main"><span class="n-page-head-icon"><?= icon($isEdit ? 'edit' : 'plus') ?></span><div><h1><?= e($pageTitle) ?></h1><p><?= e(__('students.description')) ?></p></div></div>
  <div class="n-page-head-actions"><a href="<?= $isEdit ? '/students/' . (int) $student['id'] : '/students' ?>" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('common.cancel')) ?></a></div>
</div>

<?php if ($error !== null): ?><div class="alert alert-danger"><?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?><span><?= e($error) ?></span></div><?php endif; ?>

<div class="n-form-workspace">
  <form method="post" action="<?= $isEdit ? '/students/' . (int) $student['id'] : '/students' ?>" enctype="multipart/form-data" data-unsaved-guard data-loading-submit>
    <?= CsrfMiddleware::field() ?>

    <section class="n-form-section pt-0" aria-labelledby="student-photo-section">
      <h2 class="n-form-section-title" id="student-photo-section"><?= e(__('students.profile_overview')) ?></h2>
      <div class="n-upload-row">
        <?php if (!empty($student['photo_path'])): ?><img src="/students/<?= (int) $student['id'] ?>/photo" alt="" class="n-upload-preview"><?php else: ?><span class="n-upload-preview n-upload-placeholder"><?= icon('image', 'n-icon-lg') ?></span><?php endif; ?>
        <div class="flex-grow-1">
          <label class="form-label" for="student_photo"><?= e(__('students.photo')) ?></label>
          <input type="file" class="form-control" id="student_photo" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
          <div class="form-text"><?= e(__('uploads.photo_help')) ?></div>
          <?php if (!empty($student['photo_path'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo"><label class="form-check-label" for="remove_photo"><?= e(__('common.remove_photo')) ?></label></div><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="n-form-section" aria-labelledby="student-personal-section">
      <h2 class="n-form-section-title" id="student-personal-section"><?= e(__('students.personal_information')) ?></h2>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="full_name"><?= e(__('students.full_name')) ?></label><input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($student['full_name'] ?? '') ?>" required maxlength="150" autocomplete="name"></div>
        <div class="col-md-3"><label class="form-label" for="gender"><?= e(__('students.gender')) ?></label><select class="form-select" id="gender" name="gender" required><option value="m" <?= ($student['gender'] ?? '') === 'm' ? 'selected' : '' ?>><?= e(__('students.gender_m')) ?></option><option value="f" <?= ($student['gender'] ?? '') === 'f' ? 'selected' : '' ?>><?= e(__('students.gender_f')) ?></option></select></div>
        <div class="col-md-3"><label class="form-label" for="date_of_birth"><?= e(__('students.date_of_birth')) ?></label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($student['date_of_birth'] ?? '') ?>" max="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="col-md-4"><label class="form-label" for="religion"><?= e(__('students.religion')) ?></label><select class="form-select" id="religion" name="religion" required><?php foreach (['muslim', 'christian', 'other'] as $religion): ?><option value="<?= e($religion) ?>" <?= ($student['religion'] ?? '') === $religion ? 'selected' : '' ?>><?= e(__('students.religion_' . $religion)) ?></option><?php endforeach; ?></select></div>
      </div>
    </section>

    <section class="n-form-section" aria-labelledby="student-contact-section">
      <h2 class="n-form-section-title" id="student-contact-section"><?= e(__('students.contact_information')) ?></h2>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="phone"><?= e(__('students.phone')) ?> <span class="text-muted">(<?= e(__('common.optional')) ?>)</span></label><input type="tel" class="form-control" id="phone" name="phone" dir="ltr" value="<?= e($student['phone'] ?? '') ?>" autocomplete="tel"></div>
        <div class="col-md-6"><label class="form-label" for="guardian_phone"><?= e(__('students.guardian_phone')) ?> <span class="text-muted">(<?= e(__('common.optional')) ?>)</span></label><input type="tel" class="form-control" id="guardian_phone" name="guardian_phone" dir="ltr" value="<?= e($student['guardian_phone'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label" for="address"><?= e(__('students.address')) ?> <span class="text-muted">(<?= e(__('common.optional')) ?>)</span></label><input type="text" class="form-control" id="address" name="address" value="<?= e($student['address'] ?? '') ?>" autocomplete="street-address"></div>
        <div class="col-12"><label class="form-label" for="notes"><?= e(__('students.notes')) ?> <span class="text-muted">(<?= e(__('common.optional')) ?>)</span></label><textarea class="form-control" id="notes" name="notes" rows="3"><?= e($student['notes'] ?? '') ?></textarea></div>
      </div>
    </section>

    <?php if (!$isEdit || (isset($currentEnrollment) && $currentEnrollment === null && $grades !== [])): ?>
      <section class="n-form-section" aria-labelledby="student-enrollment-section">
        <h2 class="n-form-section-title" id="student-enrollment-section"><?= e(__('students.academic_information')) ?></h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="grade_id"><?= e(__('students.grade')) ?></label><select class="form-select" id="grade_id" name="grade_id" required><?php foreach ($grades as $grade): ?><option value="<?= (int) $grade['id'] ?>"><?= e(currentLocale() === 'ar' ? $grade['name_ar'] : $grade['name_en']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label" for="class_id"><?= e(__('students.class')) ?><?= !$isEdit ? ' (' . e(__('common.optional')) . ')' : '' ?></label><select class="form-select" id="class_id" name="class_id" <?= $isEdit ? 'required' : '' ?>><option value=""><?= e(__('promotion.unassigned')) ?></option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" data-grade-id="<?= (int) $class['grade_id'] ?>"><?= e($class['name']) ?></option><?php endforeach; ?></select><div class="form-text"><?= e(__('students.edit_class_help')) ?></div></div>
        </div>
      </section>
    <?php elseif ($isEdit && isset($currentEnrollment) && $currentEnrollment !== null && $currentEnrollment['status'] === 'active'): ?>
      <section class="n-form-section" aria-labelledby="student-enrollment-section">
        <h2 class="n-form-section-title" id="student-enrollment-section"><?= e(__('students.academic_information')) ?></h2>
        <label class="form-label" for="class_id"><?= e(__('students.class')) ?></label><select class="form-select" id="class_id" name="class_id" required><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= (int) $class['id'] === (int) ($currentEnrollment['class_id'] ?? 0) ? 'selected' : '' ?>><?= e($class['name']) ?></option><?php endforeach; ?></select><div class="form-text"><?= e(__('students.edit_class_help')) ?></div>
      </section>
    <?php endif; ?>

    <div class="n-form-actions"><a href="<?= $isEdit ? '/students/' . (int) $student['id'] : '/students' ?>" class="btn btn-secondary"><?= e(__('common.cancel')) ?></a><button type="submit" class="btn btn-primary"><?= icon('check') ?><span><?= e(__('common.save')) ?></span></button></div>
  </form>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
