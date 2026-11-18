<?php
/**
 * @var array $student
 * @var array $history
 * @var ?array $currentEnrollment
 * @var array $availableClasses
 * @var ?array $attendanceSummary
 * @var array $disciplineRecords
 * @var array $healthRecords
 */
$pageTitle = $student['full_name'];
$suppressPageTitle = true;
$activeNav = 'students';
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

$attTotal = $attendanceSummary['total_records'] ?? 0;
$attPresent = $attendanceSummary['present_count'] ?? 0;
$attLate = $attendanceSummary['late_count'] ?? 0;
$attAbsent = $attendanceSummary['absent_count'] ?? 0;
$attExcused = $attendanceSummary['excused_count'] ?? 0;
$attRate = $attTotal > 0 ? (int) round((($attPresent + $attLate) / $attTotal) * 100) : null;
$rosterImported = ($student['import_source'] ?? '') === 'roster_csv';
$missingLabel = $rosterImported ? __('students.not_printed') : __('students.not_recorded');
$missingPersonal = [];
if (empty($student['gender'])) { $missingPersonal[] = __('students.gender'); }
if (empty($student['date_of_birth'])) { $missingPersonal[] = __('students.date_of_birth'); }
if (empty($student['religion'])) { $missingPersonal[] = __('students.religion'); }
$missingContact = empty($student['phone']) && empty($student['guardian_phone']) && empty($student['address']);
$classAttribute = trim((string) ($student['class_attribute_raw'] ?? ''));
$secondLanguage = trim((string) ($student['second_language'] ?? ''));
$religionEvidence = (string) ($student['religion_source'] ?? '');
$officialSource = static fn (?string $source): bool => is_string($source) && str_starts_with($source, 'official_roster');
$publicNotes = trim((string) ($student['notes'] ?? ''));
if (preg_match('/^(row|L|R)\s*[LR]?\s*\d+$/iu', $publicNotes) === 1) {
    $publicNotes = '';
}
?>

<section class="n-profile-hero" aria-labelledby="student-name">
  <div class="n-profile-identity">
    <?php if (!empty($student['photo_path'])): ?>
      <img src="/students/<?= (int) $student['id'] ?>/photo" alt="" class="n-profile-photo">
    <?php else: ?>
      <span class="n-profile-placeholder" aria-hidden="true"><?= e(mb_substr($student['full_name'], 0, 1)) ?></span>
    <?php endif; ?>
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h1 class="n-profile-title" id="student-name"><?= e($student['full_name']) ?></h1>
        <span class="badge <?= $student['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
          <?= e($student['status'] === 'active' ? __('common.status_active') : __('common.status_archived')) ?>
        </span>
      </div>
      <div class="n-profile-code mono" dir="ltr"><?= e($student['student_code']) ?></div>
      <?php if ($currentHistory !== null): ?>
        <div class="mt-2 d-flex flex-wrap gap-1">
          <span class="badge text-bg-primary">
            <?= e(currentLocale() === 'ar' ? $currentHistory['grade_name_ar'] : $currentHistory['grade_name_en']) ?> · <?= e($currentHistory['class_name'] ?? __('promotion.unassigned')) ?>
          </span>
          <?php if ($secondLanguage !== ''): ?>
            <span class="badge text-bg-light border"><?= e(__('students.second_language')) ?>: <?= e(__('students.second_language_' . $secondLanguage)) ?></span>
          <?php elseif ($classAttribute !== ''): ?>
            <span class="badge text-bg-light border"><?= e(__('students.class_attribute')) ?>: <?= e($classAttribute) ?></span>
          <?php endif; ?>
          <?php if ($rosterImported): ?>
            <span class="badge text-bg-light border"><?= e(__('students.data_source')) ?>: <?= e(__('students.roster_source')) ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="n-profile-actions">
    <a href="/students" class="btn btn-ghost"><?= icon('chevron-left', 'n-flip-rtl') ?><span><?= e(__('students.title')) ?></span></a>
    <?php if (hasPermission('attendance.view')): ?>
      <a href="/attendance" class="btn btn-outline-secondary"><?= icon('check-circle') ?><span><?= e(__('nav.attendance')) ?></span></a>
    <?php endif; ?>
    <?php if (hasPermission('exams.view')): ?>
      <a href="/exams/report-card/<?= (int) $student['id'] ?>" class="btn btn-outline-secondary"><?= icon('file-text') ?><span><?= e(__('exams.report_card')) ?></span></a>
    <?php endif; ?>
    <?php if (hasPermission('students.edit')): ?>
      <a href="/students/<?= (int) $student['id'] ?>/edit" class="btn btn-primary"><?= icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
    <?php endif; ?>
    <?php if ($student['status'] === 'active' && hasPermission('students.archive')): ?>
      <form method="post" action="/students/<?= (int) $student['id'] ?>/archive" data-confirm="<?= e(__('students.confirm_archive')) ?>">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-outline-danger"><?= icon('archive') ?><span><?= e(__('common.archive')) ?></span></button>
      </form>
    <?php endif; ?>
  </div>
</section>

<div class="row g-3 mb-3">
  <div class="col-xl-7">
    <div class="d-flex flex-column gap-3">
      <section class="card" aria-labelledby="personal-info-title">
        <div class="card-header"><?= icon('user') ?><span id="personal-info-title"><?= e(__('students.personal_information')) ?></span></div>
        <div class="card-body">
          <?php if ($missingPersonal !== []): ?>
            <div class="alert alert-warning d-flex flex-column gap-1 mb-3" role="status">
              <div class="fw-semibold d-flex align-items-center gap-2">
                <?= icon('alert-triangle') ?>
                <?= e(__('students.missing_fields_title')) ?>
              </div>
              <div class="small">
                <?= e(__('students.missing_fields_hint')) ?>
                <strong><?= e(implode(currentLocale() === 'ar' ? '، ' : ', ', $missingPersonal)) ?></strong>
              </div>
              <?php if (hasPermission('students.edit')): ?>
                <a class="btn btn-sm btn-warning align-self-start" href="/students/<?= (int) $student['id'] ?>/edit">
                  <?= icon('edit') ?><span><?= e(__('students.complete_data')) ?></span>
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <dl class="n-detail-list">
            <dt><?= e(__('students.full_name')) ?></dt><dd><?= e($student['full_name']) ?></dd>
            <dt><?= e(__('students.gender')) ?></dt>
            <dd>
              <?= $student['gender'] ? e(__('students.gender_' . $student['gender'])) : '<span class="text-muted">—</span> <span class="badge text-bg-light border">' . e($missingLabel) . '</span>' ?>
              <?php if (!empty($student['gender']) && $officialSource($student['gender_source'] ?? null)): ?><span class="badge text-bg-light border"><?= e(__('students.source_official_roster')) ?></span><?php endif; ?>
            </dd>
            <dt><?= e(__('students.date_of_birth')) ?></dt><dd dir="ltr"><?= $student['date_of_birth'] ? e($student['date_of_birth']) : '<span class="text-muted">—</span> <span class="badge text-bg-light border">' . e($missingLabel) . '</span>' ?></dd>
            <dt><?= e(__('students.religion')) ?></dt>
            <dd>
              <?php if (!empty($student['religion'])): ?>
                <?= e(__('students.religion_' . $student['religion'])) ?>
                <?php if ($officialSource($religionEvidence)): ?><span class="badge text-bg-light border"><?= e(__('students.source_official_roster')) ?></span><?php endif; ?>
              <?php else: ?>
                <span class="text-muted">—</span> <span class="badge text-bg-light border"><?= e($missingLabel) ?></span>
              <?php endif; ?>
            </dd>
            <dt><?= e(__('students.second_language')) ?></dt>
            <dd>
              <?php if ($secondLanguage !== ''): ?>
                <?= e(__('students.second_language_' . $secondLanguage)) ?>
                <?php if (in_array((string) ($student['second_language_source'] ?? ''), ['printed_cell', 'class_heading'], true)): ?><span class="badge text-bg-light border"><?= e(__('students.source_official_roster')) ?></span><?php endif; ?>
              <?php else: ?>
                <span class="text-muted">—</span> <span class="badge text-bg-light border"><?= e($missingLabel) ?></span>
              <?php endif; ?>
            </dd>
          </dl>
        </div>
      </section>

      <section class="card" aria-labelledby="contact-info-title">
        <div class="card-header"><?= icon('phone') ?><span id="contact-info-title"><?= e(__('students.contact_information')) ?></span></div>
        <div class="card-body">
          <?php if ($missingContact): ?>
            <p class="small text-muted mb-3"><?= e(__('students.contact_missing_hint')) ?></p>
          <?php endif; ?>
          <dl class="n-detail-list">
            <dt><?= e(__('students.phone')) ?></dt><dd dir="ltr"><?= e($student['phone'] ?? '—') ?></dd>
            <dt><?= e(__('students.guardian_phone')) ?></dt><dd dir="ltr"><?= e($student['guardian_phone'] ?? '—') ?></dd>
            <dt><?= e(__('students.address')) ?></dt><dd><?= e($student['address'] ?? '—') ?></dd>
            <?php if ($publicNotes !== ''): ?><dt><?= e(__('students.notes')) ?></dt><dd><?= nl2br(e($publicNotes)) ?></dd><?php endif; ?>
          </dl>
        </div>
      </section>

      <section class="card" id="guardians" aria-labelledby="family-guardians-title">
        <div class="card-header justify-content-between flex-wrap">
          <span class="d-flex align-items-center gap-2">
            <?= icon('users-group') ?>
            <span id="family-guardians-title"><?= e(__('guardians.title')) ?></span>
          </span>
          <?php if (hasPermission('guardians.manage') && !empty($allGuardians)): ?>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#linkGuardianModal">
              <?= icon('plus') ?>
              <span><?= e(__('guardians.link_guardian')) ?></span>
            </button>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <?php if (empty($studentGuardians)): ?>
            <div class="n-empty border-0 py-3">
              <div class="n-empty-icon"><?= icon('users-group') ?></div>
              <p class="text-muted small mb-2"><?= e(__('students.guardian_missing_hint')) ?></p>
              <?php if (hasPermission('guardians.manage')): ?>
                <a href="/guardians/create" class="btn btn-sm btn-outline-primary"><?= icon('plus') ?><span><?= e(__('students.add_guardian')) ?></span></a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($studentGuardians as $g): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <a href="/guardians/<?= (int) $g['id'] ?>" class="fw-semibold text-decoration-none">
                      <?= e($g['full_name']) ?>
                    </a>
                    <span class="badge text-bg-light border ms-1"><?= e(__('guardians.rel_' . ($g['relationship'] ?? 'father'))) ?></span>
                    <?php if (dbBool($g['is_primary'])): ?><span class="badge text-bg-success small ms-1"><?= e(__('guardians.primary')) ?></span><?php endif; ?>
                    <?php if (dbBool($g['is_emergency'])): ?><span class="badge text-bg-danger small ms-1"><?= e(__('guardians.emergency')) ?></span><?php endif; ?>
                    <div dir="ltr" class="font-monospace small text-muted"><?= e($g['phone']) ?></div>
                  </div>
                  <?php if (hasPermission('guardians.manage')): ?>
                    <form method="post" action="/students/<?= (int) $student['id'] ?>/guardians/<?= (int) $g['id'] ?>/unlink" data-confirm="<?= e(__('guardians.confirm_unlink')) ?>">
                      <?= CsrfMiddleware::field() ?>
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e(__('guardians.unlink')) ?>">
                        <?= icon('trash') ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </section>

      <section class="card" aria-labelledby="academic-info-title">
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
            <table class="table align-middle n-table-stack mb-0">
              <thead><tr><th><?= e(__('academic_years.title')) ?></th><th><?= e(__('students.grade')) ?></th><th><?= e(__('students.class')) ?></th><th><?= e(__('academic_years.status')) ?></th></tr></thead>
              <tbody><?php foreach ($history as $row): ?><tr><td data-label="<?= e(__('academic_years.title')) ?>" dir="ltr"><?= e($row['year_label']) ?></td><td data-label="<?= e(__('students.grade')) ?>"><?= e(currentLocale() === 'ar' ? $row['grade_name_ar'] : $row['grade_name_en']) ?></td><td data-label="<?= e(__('students.class')) ?>"><?= e($row['class_name'] ?? '—') ?></td><td data-label="<?= e(__('academic_years.status')) ?>"><span class="badge <?= $row['status'] === 'active' ? 'text-bg-success' : 'text-bg-light' ?>"><?= e(__('students.enrollment_status_' . $row['status'])) ?></span></td></tr><?php endforeach; ?></tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="d-flex flex-column gap-3">
      <?php if (hasPermission('attendance.view')): ?>
        <section class="card" aria-labelledby="student-attendance-title">
          <div class="card-header">
            <?= icon('check-circle') ?>
            <span id="student-attendance-title"><?= e(__('students.attendance_summary')) ?></span>
            <?php if ($attRate !== null): ?>
              <span class="badge text-bg-primary ms-auto" dir="ltr"><?= (int) $attRate ?>%</span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if ($attTotal === 0): ?>
              <div class="n-empty border-0 py-3">
                <div class="n-empty-icon"><?= icon('check-circle') ?></div>
                <p class="text-muted small mb-0"><?= e(__('students.no_attendance')) ?></p>
              </div>
            <?php else: ?>
              <div class="n-att-rate">
                <span class="n-att-rate-value" dir="ltr"><?= (int) $attRate ?>%</span>
                <span class="n-att-rate-label"><?= e(__('dashboard.attendance_rate')) ?><small><?= (int) $attTotal ?> <?= e(__('common.days_total')) ?></small></span>
              </div>
              <div class="n-att-bar" role="img" aria-label="<?= e(__('students.attendance_summary')) ?>">
                <?php if ($attPresent > 0): ?><span class="n-att-seg n-att-present" style="flex-grow: <?= (int) $attPresent ?>"></span><?php endif; ?>
                <?php if ($attLate > 0): ?><span class="n-att-seg n-att-late" style="flex-grow: <?= (int) $attLate ?>"></span><?php endif; ?>
                <?php if ($attExcused > 0): ?><span class="n-att-seg n-att-excused" style="flex-grow: <?= (int) $attExcused ?>"></span><?php endif; ?>
                <?php if ($attAbsent > 0): ?><span class="n-att-seg n-att-absent" style="flex-grow: <?= (int) $attAbsent ?>"></span><?php endif; ?>
              </div>
              <ul class="n-att-legend">
                <li><i class="n-att-dot n-att-present" aria-hidden="true"></i><span><?= e(__('attendance.status_present')) ?></span><strong dir="ltr"><?= (int) $attPresent ?></strong></li>
                <li><i class="n-att-dot n-att-late" aria-hidden="true"></i><span><?= e(__('attendance.status_late')) ?></span><strong dir="ltr"><?= (int) $attLate ?></strong></li>
                <li><i class="n-att-dot n-att-excused" aria-hidden="true"></i><span><?= e(__('attendance.status_excused')) ?></span><strong dir="ltr"><?= (int) $attExcused ?></strong></li>
                <li><i class="n-att-dot n-att-absent" aria-hidden="true"></i><span><?= e(__('attendance.status_absent')) ?></span><strong dir="ltr"><?= (int) $attAbsent ?></strong></li>
              </ul>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (hasPermission('welfare.view')): ?>
        <section class="card" aria-labelledby="student-welfare-title">
          <div class="card-header">
            <?= icon('shield') ?>
            <span id="student-welfare-title"><?= e(__('students.welfare_records')) ?></span>
            <?php $totalWelfare = count($disciplineRecords) + count($healthRecords); if ($totalWelfare > 0): ?>
              <span class="badge text-bg-light ms-auto"><?= $totalWelfare ?></span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if (empty($disciplineRecords) && empty($healthRecords)): ?>
              <div class="d-flex align-items-center gap-2 text-muted small py-2">
                <?= icon('check-circle', 'text-success flex-shrink-0') ?>
                <span><?= e(__('students.no_welfare')) ?></span>
              </div>
            <?php else: ?>
              <?php if (!empty($disciplineRecords)): ?>
                <h6 class="text-uppercase text-muted small fw-bold mb-2"><?= e(__('welfare.discipline_title')) ?></h6>
                <ul class="list-unstyled mb-3">
                  <?php foreach ($disciplineRecords as $record): ?>
                    <li class="py-2 border-bottom">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="badge <?= $record['severity'] === 'high' ? 'text-bg-danger' : ($record['severity'] === 'medium' ? 'text-bg-warning' : 'text-bg-secondary') ?>">
                          <?= e(__('welfare.severity_' . $record['severity'])) ?>
                        </span>
                        <time class="small text-muted" dir="ltr"><?= e($record['incident_date']) ?></time>
                      </div>
                      <p class="small mb-1 mt-1"><?= e($record['description']) ?></p>
                      <?php if (!empty($record['action_taken'])): ?>
                        <small class="text-muted d-block"><?= e(__('welfare.action_taken')) ?>: <?= e($record['action_taken']) ?></small>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <?php if (!empty($healthRecords)): ?>
                <h6 class="text-uppercase text-muted small fw-bold mb-2"><?= e(__('welfare.health_title')) ?></h6>
                <ul class="list-unstyled mb-0">
                  <?php foreach ($healthRecords as $record): ?>
                    <li class="py-2 border-bottom">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <strong><?= e($record['title']) ?></strong>
                        <time class="small text-muted" dir="ltr"><?= e($record['date_logged']) ?></time>
                      </div>
                      <p class="small text-muted mb-0"><?= e($record['details']) ?></p>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="card-footer">
            <div class="n-insight-note">
              <?= icon('lock') ?>
              <span><?= e(__('students.confidential_note')) ?></span>
            </div>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($canReassign): ?>
  <div class="modal fade" id="reassignModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="/students/<?= (int) $student['id'] ?>/reassign-class" data-loading-submit>
    <?= CsrfMiddleware::field() ?>
    <div class="modal-header"><h2 class="modal-title"><?= e(__('students.reassign_class')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
    <div class="modal-body"><label class="form-label" for="reassign_class_id"><?= e(__('students.reassign_to')) ?></label><select class="form-select" id="reassign_class_id" name="class_id" required><?php foreach ($availableClasses as $class): ?><?php if ((int) $class['id'] !== (int) ($currentEnrollment['class_id'] ?? 0)): ?><option value="<?= (int) $class['id'] ?>"><?= e($class['name']) ?></option><?php endif; ?><?php endforeach; ?></select><div class="form-text"><?= e(__('students.edit_class_help')) ?></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= icon('check') ?><?= e(__('common.save')) ?></button></div>
  </form></div></div></div>
<?php endif; ?>

<?php if (hasPermission('guardians.manage') && !empty($allGuardians)): ?>
  <div class="modal fade" id="linkGuardianModal" tabindex="-1" aria-labelledby="linkGuardianModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post" action="/students/<?= (int) $student['id'] ?>/guardians/link">
          <?= CsrfMiddleware::field() ?>
          <div class="modal-header">
            <h5 class="modal-title" id="linkGuardianModalLabel"><?= e(__('guardians.link_guardian')) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="guardian_id"><?= e(__('guardians.select_guardian')) ?></label>
              <select class="form-select" id="guardian_id" name="guardian_id" required>
                <?php foreach ($allGuardians as $ag): ?>
                  <option value="<?= (int) $ag['id'] ?>">
                    <?= e($ag['full_name']) ?> (<?= e($ag['phone']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="rel"><?= e(__('guardians.relationship')) ?></label>
              <select class="form-select" id="rel" name="relationship">
                <option value="father"><?= e(__('guardians.rel_father')) ?></option>
                <option value="mother"><?= e(__('guardians.rel_mother')) ?></option>
                <option value="guardian"><?= e(__('guardians.rel_guardian')) ?></option>
                <option value="sponsor"><?= e(__('guardians.rel_sponsor')) ?></option>
                <option value="other"><?= e(__('guardians.rel_other')) ?></option>
              </select>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1" checked>
              <label class="form-check-label" for="is_primary"><?= e(__('guardians.primary_contact')) ?></label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="is_emergency" name="is_emergency" value="1" checked>
              <label class="form-check-label" for="is_emergency"><?= e(__('guardians.emergency_contact')) ?></label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
              <?= icon('check') ?>
              <span><?= e(__('guardians.link_submit')) ?></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
