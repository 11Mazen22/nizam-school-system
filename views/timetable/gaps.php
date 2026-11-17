<?php
/**
 * Timetable gaps -- printed lessons that could not be loaded.
 *
 * @var list<array<string,mixed>> $gaps
 * @var array{open:int,resolved:int,ignored:int,total:int} $counts
 * @var list<array<string,mixed>> $classes
 * @var list<array<string,mixed>> $teachers
 * @var list<array<string,mixed>> $subjects
 * @var string $status
 * @var string $reason
 * @var int $classId
 * @var string $teacher
 * @var array<int,string> $dayLabels
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('timetable.gaps_title');
$activeNav = 'timetable';
$canManage = hasPermission('timetable.manage');

$returnFields = static function () use ($status, $reason, $classId, $teacher): void {
    foreach (['status' => $status, 'reason' => $reason, 'teacher' => $teacher] as $key => $value) {
        if ($value !== '' && $value !== 'all') {
            echo '<input type="hidden" name="return_' . e($key) . '" value="' . e((string) $value) . '">';
        }
    }
    if ($classId > 0) {
        echo '<input type="hidden" name="return_class_id" value="' . $classId . '">';
    }
};

ob_start(); ?>
<p class="n-hero-sub"><?= e(__('timetable.gaps_subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/timetable" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('timetable.title')) ?></a>
<a href="/timetable/staff" class="btn btn-outline-secondary"><?= icon('users-group') ?><?= e(__('timetable.staff_title')) ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="badge text-bg-danger text-decoration-none" href="/timetable/gaps?status=open"><?= e(__('timetable.gaps_open')) ?>: <?= (int) $counts['open'] ?></a>
  <a class="badge text-bg-success text-decoration-none" href="/timetable/gaps?status=resolved"><?= e(__('timetable.gaps_resolved')) ?>: <?= (int) $counts['resolved'] ?></a>
  <a class="badge text-bg-secondary text-decoration-none" href="/timetable/gaps?status=ignored"><?= e(__('timetable.gaps_ignored')) ?>: <?= (int) $counts['ignored'] ?></a>
  <a class="badge text-bg-light text-decoration-none" href="/timetable/gaps?status=all"><?= e(__('timetable.gaps_filter_all')) ?>: <?= (int) $counts['total'] ?></a>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start" role="status">
  <?= icon('info-circle', 'n-icon-lg flex-shrink-0') ?>
  <div class="small"><?= e(__('timetable.gaps_hint')) ?></div>
</div>

<form method="get" action="/timetable/gaps" class="n-filter-panel no-print">
  <div class="n-filter-row">
    <div class="n-filter-field">
      <label class="visually-hidden" for="gapStatus"><?= e(__('verification.status')) ?></label>
      <select id="gapStatus" name="status" class="form-select" data-auto-submit>
        <?php foreach (['open' => __('timetable.gaps_open'), 'resolved' => __('timetable.gaps_resolved'), 'ignored' => __('timetable.gaps_ignored'), 'all' => __('timetable.gaps_filter_all')] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="n-filter-field">
      <label class="visually-hidden" for="gapReason"><?= e(__('timetable.gaps_reason')) ?></label>
      <select id="gapReason" name="reason" class="form-select" data-auto-submit>
        <option value=""><?= e(__('timetable.gaps_all_reasons')) ?></option>
        <?php foreach (['no_teacher' => __('timetable.gaps_no_teacher'), 'teacher_conflict' => __('timetable.gaps_conflict'), 'unmapped_teacher' => __('timetable.gaps_unmapped'), 'absence' => __('timetable.gaps_absence'), 'other' => __('timetable.gaps_other')] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $reason === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="n-filter-field">
      <label class="visually-hidden" for="gapClass"><?= e(__('timetable.gaps_class')) ?></label>
      <select id="gapClass" name="class_id" class="form-select" data-auto-submit>
        <option value="0"><?= e(__('timetable.gaps_all_classes')) ?></option>
        <?php foreach ($classes as $class): ?>
          <option value="<?= (int) $class['id'] ?>" <?= (int) $class['id'] === $classId ? 'selected' : '' ?>><?= e($class['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="n-filter-field n-search-field">
      <?= icon('search') ?>
      <label class="visually-hidden" for="gapTeacher"><?= e(__('timetable.gaps_teacher')) ?></label>
      <input id="gapTeacher" type="search" class="form-control" name="teacher" value="<?= e($teacher) ?>" placeholder="<?= e(__('timetable.gaps_teacher')) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('filter') ?><?= e(__('common.filter')) ?></button>
    <a href="/timetable/gaps" class="btn btn-outline-secondary"><?= icon('close') ?><?= e(__('common.clear_filters')) ?></a>
  </div>
</form>

<?php if ($gaps === []): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('check-circle', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(__('timetable.gaps_none')) ?></h2>
  </div>
<?php else: ?>
  <div class="n-data-panel">
    <div class="table-responsive">
      <table class="table table-hover align-middle n-table-stack">
        <thead>
          <tr>
            <th><?= e(__('timetable.gaps_class')) ?></th>
            <th><?= e(__('timetable.gaps_slot')) ?></th>
            <th><?= e(__('timetable.gaps_subject')) ?></th>
            <th><?= e(__('timetable.gaps_teacher')) ?></th>
            <th><?= e(__('timetable.gaps_reason')) ?></th>
            <th class="text-end"><?= e(__('timetable.gaps_action')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($gaps as $gap): ?>
            <?php
            $gapId = (int) $gap['id'];
            $reasonLabel = match ((string) $gap['reason']) {
                'no_teacher' => __('timetable.gaps_no_teacher'),
                'teacher_conflict' => __('timetable.gaps_conflict'),
                'unmapped_teacher' => __('timetable.gaps_unmapped'),
                'absence' => __('timetable.gaps_absence'),
                default => __('timetable.gaps_other'),
            };
            $reasonBadge = match ((string) $gap['reason']) {
                'no_teacher' => 'warning',
                'teacher_conflict' => 'danger',
                'unmapped_teacher' => 'secondary',
                'absence' => 'secondary',
                default => 'light',
            };
            $day = (int) $gap['day_of_week'];
            ?>
            <tr>
              <td data-label="<?= e(__('timetable.gaps_class')) ?>"><a href="/timetable?class_id=<?= (int) $gap['class_id'] ?>" class="fw-semibold"><?= e((string) $gap['class_name']) ?></a></td>
              <td data-label="<?= e(__('timetable.gaps_slot')) ?>"><?= e($dayLabels[$day] ?? (string) $day) ?> — <?= e(__('timetable.period')) ?> <?= (int) $gap['period_number'] ?></td>
              <td data-label="<?= e(__('timetable.gaps_subject')) ?>"><?= e((string) $gap['subject_raw']) ?></td>
              <td data-label="<?= e(__('timetable.gaps_teacher')) ?>"><?= e((string) ($gap['printed_teacher'] ?? '—')) ?></td>
              <td data-label="<?= e(__('timetable.gaps_reason')) ?>">
                <span class="badge text-bg-<?= $reasonBadge ?>"><?= e($reasonLabel) ?></span>
                <?php if ($gap['status'] === 'resolved' && !empty($gap['resolved_teacher_name'])): ?>
                  <div class="small text-muted mt-1"><?= e((string) $gap['resolved_teacher_name']) ?></div>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(__('timetable.gaps_action')) ?>">
                <?php if ($gap['status'] === 'open' && $canManage): ?>
                  <form method="post" action="/timetable/gaps/<?= $gapId ?>/resolve" class="d-flex flex-wrap gap-1 justify-content-end">
                    <?= CsrfMiddleware::field() ?>
                    <?php $returnFields(); ?>
                    <select name="subject_id" class="form-select form-select-sm" style="max-width:190px;" required aria-label="<?= e(__('timetable.gaps_subject')) ?>">
                      <option value="" disabled <?= empty($gap['subject_id']) ? 'selected' : '' ?>><?= e(__('timetable.select_subject')) ?></option>
                      <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>" <?= (int) ($gap['subject_id'] ?? 0) === (int) $subject['id'] ? 'selected' : '' ?>>
                          <?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <select name="teacher_id" class="form-select form-select-sm" style="max-width:170px;" required aria-label="<?= e(__('timetable.teacher')) ?>">
                      <option value="" disabled selected><?= e(__('timetable.select_teacher')) ?></option>
                      <?php foreach ($teachers as $teacherRow): ?>
                        <option value="<?= (int) $teacherRow['id'] ?>"><?= e($teacherRow['full_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary"><?= e(__('timetable.gaps_resolve')) ?></button>
                  </form>
                  <form method="post" action="/timetable/gaps/<?= $gapId ?>/ignore" class="mt-1 text-end">
                    <?= CsrfMiddleware::field() ?>
                    <?php $returnFields(); ?>
                    <button type="submit" class="btn btn-sm btn-ghost text-secondary"><?= e(__('timetable.gaps_ignore')) ?></button>
                  </form>
                <?php elseif ($gap['status'] !== 'open' && $canManage): ?>
                  <form method="post" action="/timetable/gaps/<?= $gapId ?>/reopen" class="text-end">
                    <?= CsrfMiddleware::field() ?>
                    <?php $returnFields(); ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('timetable.gaps_reopen')) ?></button>
                  </form>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
