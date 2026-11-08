<?php
/** @var array $student @var array $history @var ?array $currentEnrollment @var array $availableClasses */
$pageTitle = $student['full_name'];
$activeNav = 'students';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$canReassign = hasPermission('students.edit') && $currentEnrollment !== null && $currentEnrollment['status'] === 'active' && !empty($availableClasses);
$canUndo = hasPermission('students.promote') && $currentEnrollment !== null && $currentEnrollment['status'] === 'active' && $currentEnrollment['previous_enrollment_id'] !== null;
$currentHistory = null;
if ($currentEnrollment !== null) {
    foreach ($history as $entry) {
        if ((int) $entry['id'] === (int) $currentEnrollment['id']) { $currentHistory = $entry; break; }
    }
}
?>

<section class="n-profile-hero" aria-labelledby="student-name">
  <div class="n-profile-identity">
    <?php if (!empty($student['photo_path'])): ?><img src="/students/<?= (int) $student['id'] ?>/photo" alt="" class="n-profile-photo"><?php else: ?><span class="n-profile-placeholder" aria-hidden="true"><?= e(mb_substr($student['full_name'], 0, 1)) ?></span><?php endif; ?>
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h1 class="n-profile-title" id="student-name"><?= e($student['full_name']) ?></h1>
        <span class="badge <?= $student['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($student['status'] === 'active' ? __('common.status_active') : __('common.status_archived')) ?></span>
      </div>
      <div class="n-profile-code mono" dir="ltr"><?= e($student['student_code']) ?></div>
      <?php if ($currentHistory !== null): ?><div class="mt-2"><span class="badge text-bg-primary"><?= e(currentLocale() === 'ar' ? $currentHistory['grade_name_ar'] : $currentHistory['grade_name_en']) ?> · <?= e($currentHistory['class_name'] ?? __('promotion.unassigned')) ?></span></div><?php endif; ?>
    </div>
  </div>
  <div class="n-profile-actions">
    <a href="/students" class="btn btn-ghost"><?= icon('chevron-left', 'n-flip-rtl') ?><span><?= e(__('students.title')) ?></span></a>
    <?php if (hasPermission('attendance.view')): ?><a href="/attendance" class="btn btn-outline-secondary"><?= icon('check-circle') ?><span><?= e(__('nav.attendance')) ?></span></a><?php endif; ?>
    <?php if (hasPermission('exams.view')): ?><a href="/exams/report-card/<?= (int) $student['id'] ?>" class="btn btn-outline-secondary"><?= icon('file-text') ?><span><?= e(__('exams.report_card')) ?></span></a><?php endif; ?>
    <?php if (hasPermission('students.edit')): ?><a href="/students/<?= (int) $student['id'] ?>/edit" class="btn btn-primary"><?= icon('edit') ?><span><?= e(__('common.edit')) ?></span></a><?php endif; ?>
    <?php if ($student['status'] === 'active' && hasPermission('students.archive')): ?>
      <form method="post" action="/students/<?= (int) $student['id'] ?>/archive" data-confirm="<?= e(__('students.confirm_archive')) ?>">
        <?= CsrfMiddleware::field() ?><button type="submit" class="btn btn-outline-danger"><?= icon('archive') ?><span><?= e(__('common.archive')) ?></span></button>
      </form>
    <?php endif; ?>
  </div>
</section>

<div class="row g-3">
  <div class="col-xl-7">
    <section class="card h-100" aria-labelledby="personal-info-title">
      <div class="card-header"><?= icon('user') ?><span id="personal-info-title"><?= e(__('students.personal_information')) ?></span></div>
      <div class="card-body">
        <dl class="n-detail-list">
          <dt><?= e(__('students.full_name')) ?></dt><dd><?= e($student['full_name']) ?></dd>
          <dt><?= e(__('students.gender')) ?></dt><dd><?= e(__('students.gender_' . $student['gender'])) ?></dd>
          <dt><?= e(__('students.date_of_birth')) ?></dt><dd dir="ltr"><?= e($student['date_of_birth']) ?></dd>
          <dt><?= e(__('students.religion')) ?></dt><dd><?= e(__('students.religion_' . $student['religion'])) ?></dd>
        </dl>
      </div>
    </section>
  </div>
  <div class="col-xl-5">
    <section class="card h-100" aria-labelledby="contact-info-title">
      <div class="card-header"><?= icon('phone') ?><span id="contact-info-title"><?= e(__('students.contact_information')) ?></span></div>
      <div class="card-body">
        <dl class="n-detail-list">
          <dt><?= e(__('students.phone')) ?></dt><dd dir="ltr"><?= e($student['phone'] ?? '—') ?></dd>
          <dt><?= e(__('students.guardian_phone')) ?></dt><dd dir="ltr"><?= e($student['guardian_phone'] ?? '—') ?></dd>
          <dt><?= e(__('students.address')) ?></dt><dd><?= e($student['address'] ?? '—') ?></dd>
          <?php if (!empty($student['notes'])): ?><dt><?= e(__('students.notes')) ?></dt><dd><?= nl2br(e($student['notes'])) ?></dd><?php endif; ?>
        </dl>
      </div>
    </section>
  </div>
</div>

<section class="card mt-3" aria-labelledby="academic-info-title">
  <div class="card-header justify-content-between flex-wrap">
    <span class="d-flex align-items-center gap-2"><?= icon('graduation-cap') ?><span id="academic-info-title"><?= e(__('students.academic_information')) ?></span></span>
    <span class="d-flex gap-2 flex-wrap">
      <?php if ($canReassign): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reassignModal"><?= icon('refresh') ?><span><?= e(__('students.reassign_class')) ?></span></button><?php endif; ?>
      <?php if ($canUndo): ?><form method="post" action="/students/<?= (int) $currentEnrollment['id'] ?>/undo-promotion" data-confirm="<?= e(__('students.confirm_undo_promotion')) ?>"><?= CsrfMiddleware::field() ?><button type="submit" class="btn btn-sm btn-outline-warning"><?= icon('refresh') ?><span><?= e(__('students.undo_promotion')) ?></span></button></form><?php endif; ?>
    </span>
  </div>
  <?php if (empty($history)): ?>
    <div class="card-body"><div class="n-empty border-0 py-3"><div class="n-empty-icon"><?= icon('inbox') ?></div><p><?= e(__('common.no_results')) ?></p></div></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle n-table-stack">
        <thead><tr><th><?= e(__('academic_years.title')) ?></th><th><?= e(__('students.grade')) ?></th><th><?= e(__('students.class')) ?></th><th><?= e(__('academic_years.status')) ?></th></tr></thead>
        <tbody><?php foreach ($history as $row): ?><tr><td data-label="<?= e(__('academic_years.title')) ?>" dir="ltr"><?= e($row['year_label']) ?></td><td data-label="<?= e(__('students.grade')) ?>"><?= e(currentLocale() === 'ar' ? $row['grade_name_ar'] : $row['grade_name_en']) ?></td><td data-label="<?= e(__('students.class')) ?>"><?= e($row['class_name'] ?? '—') ?></td><td data-label="<?= e(__('academic_years.status')) ?>"><span class="badge <?= $row['status'] === 'active' ? 'text-bg-success' : 'text-bg-light' ?>"><?= e(__('students.enrollment_status_' . $row['status'])) ?></span></td></tr><?php endforeach; ?></tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($canReassign): ?>
  <div class="modal fade" id="reassignModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="/students/<?= (int) $student['id'] ?>/reassign-class" data-loading-submit>
    <?= CsrfMiddleware::field() ?>
    <div class="modal-header"><h2 class="modal-title"><?= e(__('students.reassign_class')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
    <div class="modal-body"><label class="form-label" for="reassign_class_id"><?= e(__('students.reassign_to')) ?></label><select class="form-select" id="reassign_class_id" name="class_id" required><?php foreach ($availableClasses as $class): ?><?php if ((int) $class['id'] !== (int) ($currentEnrollment['class_id'] ?? 0)): ?><option value="<?= (int) $class['id'] ?>"><?= e($class['name']) ?></option><?php endif; ?><?php endforeach; ?></select><div class="form-text"><?= e(__('students.edit_class_help')) ?></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= icon('check') ?><?= e(__('common.save')) ?></button></div>
  </form></div></div></div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
