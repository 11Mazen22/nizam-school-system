<?php
/** @var array $grades @var ?int $academicYearId */
$pageTitle = __('admissions.new_application');
$suppressPageTitle = true;
$activeNav = 'admissions';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('sparkle', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('admissions.new_application')) ?>
    </h1>
  </div>
  <div>
    <a href="/admissions" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
  </div>
</div>

<div class="card col-lg-8 mx-auto">
  <div class="card-body p-4">
    <form method="post" action="/admissions">
      <?= CsrfMiddleware::field() ?>
      
      <h5 class="mb-3 text-primary"><?= e(__('admissions.applicant_details')) ?></h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="applicant_name"><?= e(__('admissions.applicant_name')) ?></label>
          <input type="text" class="form-control" id="applicant_name" name="applicant_name" required>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="gender"><?= e(__('students.gender')) ?></label>
          <select class="form-select" id="gender" name="gender">
            <option value="m"><?= e(__('students.gender_m')) ?></option>
            <option value="f"><?= e(__('students.gender_f')) ?></option>
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="date_of_birth"><?= e(__('students.dob')) ?></label>
          <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
        </div>
      </div>

      <div class="row mb-4">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="grade_id"><?= e(__('grades.grade')) ?></label>
          <select class="form-select" id="grade_id" name="grade_id" required>
            <?php foreach ($grades as $g): ?>
              <option value="<?= (int) $g['id'] ?>">
                <?= e(currentLocale() === 'ar' ? $g['name_ar'] : $g['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="status"><?= e(__('admissions.initial_stage')) ?></label>
          <select class="form-select" id="status" name="status">
            <option value="inquiry"><?= e(__('admissions.status_inquiry')) ?></option>
            <option value="application"><?= e(__('admissions.status_application')) ?></option>
            <option value="assessment"><?= e(__('admissions.status_assessment')) ?></option>
          </select>
        </div>
      </div>

      <h5 class="mb-3 text-primary"><?= e(__('admissions.guardian_details')) ?></h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="guardian_name"><?= e(__('guardians.full_name')) ?></label>
          <input type="text" class="form-control" id="guardian_name" name="guardian_name" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="guardian_phone"><?= e(__('guardians.phone')) ?></label>
          <input type="text" class="form-control" id="guardian_phone" name="guardian_phone" required dir="ltr">
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="guardian_email"><?= e(__('guardians.email')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="email" class="form-control" id="guardian_email" name="guardian_email" dir="ltr">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="guardian_relationship"><?= e(__('guardians.relationship')) ?></label>
          <select class="form-select" id="guardian_relationship" name="guardian_relationship">
            <option value="father"><?= e(__('guardians.rel_father')) ?></option>
            <option value="mother"><?= e(__('guardians.rel_mother')) ?></option>
            <option value="guardian"><?= e(__('guardians.rel_guardian')) ?></option>
            <option value="other"><?= e(__('guardians.rel_other')) ?></option>
          </select>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="/admissions" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
