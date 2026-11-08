<?php
/** @var array $application @var array $classes */
$pageTitle = __('admissions.app_title', ['number' => $application['application_number']]);
$activeNav = 'admissions';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$statusBadges = [
    'inquiry'     => 'text-bg-secondary',
    'application' => 'text-bg-primary',
    'assessment'  => 'text-bg-info text-white',
    'review'      => 'text-bg-warning',
    'offer'       => 'text-bg-success',
    'enrolled'    => 'text-bg-success',
    'waitlisted'  => 'text-bg-dark',
    'rejected'    => 'text-bg-danger',
];
?>

<div class="n-page-head">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <h1 class="d-flex align-items-center gap-2 mb-0">
        <?= icon('sparkle', 'n-icon-lg text-body-secondary') ?>
        <?= e($application['applicant_name']) ?>
      </h1>
      <span class="badge <?= $statusBadges[$application['status']] ?? 'text-bg-secondary' ?> fs-6">
        <?= e(__('admissions.status_' . $application['status'])) ?>
      </span>
    </div>
    <div class="text-muted small">
      <?= e(__('admissions.app_number')) ?>: <span dir="ltr" class="font-monospace fw-bold"><?= e($application['application_number']) ?></span> |
      <?= e(__('grades.grade')) ?>: <strong><?= e(currentLocale() === 'ar' ? $application['grade_name_ar'] : $application['grade_name_en']) ?></strong>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="/admissions" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
    <?php if ($application['status'] !== 'enrolled' && hasPermission('admissions.convert')): ?>
      <button type="button" class="btn btn-success d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#enrollModal">
        <?= icon('user-check') ?>
        <span><?= e(__('admissions.convert_to_student')) ?></span>
      </button>
    <?php elseif ($application['enrolled_student_id'] !== null): ?>
      <a href="/students/<?= (int) $application['enrolled_student_id'] ?>" class="btn btn-outline-success d-inline-flex align-items-center gap-2">
        <?= icon('eye') ?>
        <span><?= e(__('admissions.view_student_profile')) ?></span>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header fw-semibold"><?= e(__('admissions.applicant_details')) ?></div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="mb-2">
            <span class="text-muted small d-block"><?= e(__('students.gender')) ?></span>
            <span><?= $application['gender'] === 'm' ? e(__('students.gender_m')) : e(__('students.gender_f')) ?></span>
          </li>
          <li class="mb-2">
            <span class="text-muted small d-block"><?= e(__('students.dob')) ?></span>
            <span><?= e($application['date_of_birth']) ?></span>
          </li>
          <li class="mb-2">
            <span class="text-muted small d-block"><?= e(__('guardians.full_name')) ?></span>
            <strong><?= e($application['guardian_name']) ?></strong> (<?= e(__('guardians.rel_' . ($application['guardian_relationship'] ?? 'father'))) ?>)
          </li>
          <li class="mb-2">
            <span class="text-muted small d-block"><?= e(__('guardians.phone')) ?></span>
            <span dir="ltr" class="font-monospace"><?= e($application['guardian_phone']) ?></span>
          </li>
          <li class="mb-0">
            <span class="text-muted small d-block"><?= e(__('guardians.email')) ?></span>
            <span dir="ltr"><?= e($application['guardian_email'] ?? '—') ?></span>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header fw-semibold"><?= e(__('admissions.evaluation_status')) ?></div>
      <div class="card-body">
        <div class="mb-3">
          <span class="text-muted small d-block"><?= e(__('admissions.assessment_score')) ?></span>
          <div class="fs-4 fw-bold text-primary">
            <?= $application['assessment_score'] !== null ? e((string) $application['assessment_score']) . '%' : '—' ?>
          </div>
          <?php if (!empty($application['assessment_notes'])): ?>
            <p class="small text-muted mb-0"><?= e($application['assessment_notes']) ?></p>
          <?php endif; ?>
        </div>

        <?php if (!empty($application['decision_date'])): ?>
          <div class="mb-3">
            <span class="text-muted small d-block"><?= e(__('admissions.decision_notes')) ?></span>
            <div><?= e($application['decision_notes'] ?? '') ?></div>
            <div class="small text-muted"><?= e($application['decision_date']) ?></div>
          </div>
        <?php endif; ?>

        <?php if (hasPermission('admissions.manage') && $application['status'] !== 'enrolled'): ?>
          <hr>
          <div class="accordion" id="admissionActions">
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAssess">
                  <?= e(__('admissions.record_assessment')) ?>
                </button>
              </h2>
              <div id="collapseAssess" class="accordion-collapse collapse p-3">
                <form method="post" action="/admissions/<?= (int) $application['id'] ?>/assessment">
                  <?= CsrfMiddleware::field() ?>
                  <div class="mb-2">
                    <label class="form-label small"><?= e(__('admissions.score_percentage')) ?></label>
                    <input type="number" step="0.5" min="0" max="100" class="form-control form-control-sm" name="assessment_score" value="<?= e((string) ($application['assessment_score'] ?? '')) ?>" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small"><?= e(__('admissions.notes')) ?></label>
                    <textarea class="form-control form-control-sm" name="assessment_notes" rows="2"></textarea>
                  </div>
                  <button type="submit" class="btn btn-sm btn-primary"><?= e(__('common.save')) ?></button>
                </form>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDecide">
                  <?= e(__('admissions.record_decision')) ?>
                </button>
              </h2>
              <div id="collapseDecide" class="accordion-collapse collapse p-3">
                <form method="post" action="/admissions/<?= (int) $application['id'] ?>/decision">
                  <?= CsrfMiddleware::field() ?>
                  <div class="mb-2">
                    <label class="form-label small"><?= e(__('admissions.decision')) ?></label>
                    <select class="form-select form-select-sm" name="decision">
                      <option value="offer"><?= e(__('admissions.decision_offer')) ?></option>
                      <option value="waitlist"><?= e(__('admissions.decision_waitlist')) ?></option>
                      <option value="reject"><?= e(__('admissions.decision_reject')) ?></option>
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small"><?= e(__('admissions.decision_notes')) ?></label>
                    <textarea class="form-control form-control-sm" name="decision_notes" rows="2"></textarea>
                  </div>
                  <button type="submit" class="btn btn-sm btn-primary"><?= e(__('common.save')) ?></button>
                </form>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($application['status'] !== 'enrolled'): ?>
  <!-- Convert to Student Modal -->
  <div class="modal fade" id="enrollModal" tabindex="-1" aria-labelledby="enrollModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post" action="/admissions/<?= (int) $application['id'] ?>/convert">
          <?= CsrfMiddleware::field() ?>
          <div class="modal-header">
            <h5 class="modal-title" id="enrollModalLabel"><?= e(__('admissions.convert_to_student')) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
          </div>
          <div class="modal-body">
            <p><?= e(__('admissions.convert_confirm_msg', ['name' => $application['applicant_name']])) ?></p>
            <div class="mb-3">
              <label class="form-label" for="class_id"><?= e(__('classes.class')) ?> (<?= e(__('common.optional')) ?>)</label>
              <select class="form-select" id="class_id" name="class_id">
                <option value=""><?= e(__('admissions.unassigned_class')) ?></option>
                <?php foreach ($classes as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
            <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-2">
              <?= icon('check') ?>
              <span><?= e(__('admissions.confirm_enrollment')) ?></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
