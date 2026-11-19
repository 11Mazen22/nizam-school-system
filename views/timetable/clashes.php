<?php
/**
 * Timetable -> Resolve printed clashes.
 *
 * A clash is a real lesson the printed grid assigned to a teacher who is
 * already in another room at that exact period. The database refuses to
 * record a double-booking, so the lesson keeps the printed name as evidence
 * and no teacher_id until the school chooses who actually teaches it.
 *
 * @var list<array<string,mixed>> $clashes
 * @var list<array<string,mixed>> $resolutions
 * @var array<int,string> $dayLabels
 * @var int $openGaps
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('timetable.clashes_title');
$activeNav = 'timetable';
$canManage = hasPermission('timetable.manage');

ob_start(); ?>
<p class="n-hero-sub"><?= e(__('timetable.clashes_subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/timetable" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('timetable.title')) ?></a>
<a href="/timetable/gaps" class="btn btn-outline-secondary"><?= icon('alert-triangle') ?><?= e(__('timetable.gaps_title')) ?><?= $openGaps > 0 ? ' (' . (int) $openGaps . ')' : '' ?></a>
<a href="/timetable/staff" class="btn btn-outline-secondary"><?= icon('users-group') ?><?= e(__('timetable.staff_title')) ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <span class="badge text-bg-danger"><?= e(__('timetable.clashes_open_count')) ?>: <?= count($clashes) ?></span>
  <span class="badge text-bg-success"><?= e(__('timetable.clashes_resolved_count')) ?>: <?= count($resolutions) ?></span>
</div>

<?php if ($clashes === []): ?>
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon"><?= icon('check-circle', 'n-icon-massive') ?></div>
      <h2 class="n-empty-powerful-title"><?= e(__('timetable.clashes_none')) ?></h2>
    </div>
  </div>
<?php else: ?>
  <div class="row g-4 mb-4">
    <?php foreach ($clashes as $clash): ?>
      <?php
      $day = (int) $clash['day_of_week'];
      $period = (int) $clash['period_number'];
      $subject = currentLocale() === 'ar' ? (string) $clash['subject_ar'] : (string) $clash['subject_en'];
      $classLabel = (string) $clash['class_name'];
      $grade = currentLocale() === 'ar' ? (string) $clash['grade_name_ar'] : (string) $clash['grade_name_en'];
      $candidates = is_array($clash['candidates'] ?? null) ? $clash['candidates'] : [];
      $assigned = array_values(array_filter($candidates, static fn (array $c): bool => !empty($c['assigned'])));
      $free = array_values(array_filter($candidates, static fn (array $c): bool => empty($c['assigned'])));
      $held = $clash['held_by'] ?? null;
      $slotLabel = ($dayLabels[$day] ?? ('#' . $day)) . ' — ' . __('timetable.period') . ' ' . $period;
      ?>
      <div class="col-xl-6">
        <div class="n-settings-card h-100">
          <div class="n-settings-card-head d-flex justify-content-between align-items-start gap-2">
            <div>
              <h3 class="h6 mb-1"><?= e($classLabel) ?><?= $grade !== '' ? ' — ' . e($grade) : '' ?></h3>
              <p class="mb-0 small text-body-secondary"><?= e($slotLabel) ?> · <?= e($subject) ?></p>
            </div>
            <span class="badge text-bg-danger"><?= icon('alert-triangle') ?> <?= e(__('timetable.slot_conflict_short')) ?></span>
          </div>
          <div class="n-settings-card-body">
            <dl class="row small mb-3">
              <dt class="col-5 text-body-secondary"><?= e(__('timetable.clashes_printed_teacher')) ?></dt>
              <dd class="col-7 fw-semibold mb-1"><?= e((string) $clash['printed_teacher']) ?></dd>
              <?php if (is_array($held)): ?>
                <dt class="col-5 text-body-secondary"><?= e(__('timetable.clashes_held_by')) ?></dt>
                <dd class="col-7 mb-1">
                  <?= e((string) $held['class']) ?>
                  <span class="text-body-secondary">· <?= e(currentLocale() === 'ar' ? (string) $held['subject_ar'] : (string) $held['subject_en']) ?></span>
                </dd>
              <?php endif; ?>
              <?php if (!empty($clash['note'])): ?>
                <dt class="col-5 text-body-secondary"><?= e(__('timetable.gaps_subject')) ?></dt>
                <dd class="col-7 mb-1"><?= e((string) $clash['note']) ?></dd>
              <?php endif; ?>
            </dl>

            <div class="alert alert-light border small d-flex gap-2 mb-3">
              <?= icon('info', 'flex-shrink-0') ?>
              <span><?= e(__('timetable.clashes_evidence')) ?></span>
            </div>

            <?php if (!$canManage): ?>
              <p class="text-body-secondary small mb-0"><?= e(__('timetable.clashes_no_permission')) ?></p>
            <?php elseif ($candidates === []): ?>
              <div class="alert alert-warning small mb-0"><?= e(__('timetable.clashes_no_candidates')) ?></div>
            <?php else: ?>
              <form method="post" action="/timetable/clashes/<?= (int) $clash['id'] ?>/resolve" data-loading-submit>
                <?= CsrfMiddleware::field() ?>
                <label class="form-label" for="teacher_<?= (int) $clash['id'] ?>"><?= e(__('timetable.clashes_candidates')) ?></label>
                <select class="form-select mb-1" id="teacher_<?= (int) $clash['id'] ?>" name="teacher_id" required>
                  <option value=""><?= e(__('timetable.choose_teacher')) ?></option>
                  <?php if ($assigned !== []): ?>
                    <optgroup label="<?= e(__('timetable.clashes_assigned_group')) ?>">
                      <?php foreach ($assigned as $candidate): ?>
                        <option value="<?= (int) $candidate['id'] ?>"><?= e((string) $candidate['name']) ?><?= (string) $candidate['code'] !== '' ? ' (' . e((string) $candidate['code']) . ')' : '' ?></option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endif; ?>
                  <?php if ($free !== []): ?>
                    <optgroup label="<?= e(__('timetable.clashes_free_group')) ?>">
                      <?php foreach ($free as $candidate): ?>
                        <option value="<?= (int) $candidate['id'] ?>"><?= e((string) $candidate['name']) ?><?= (string) $candidate['code'] !== '' ? ' (' . e((string) $candidate['code']) . ')' : '' ?></option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endif; ?>
                </select>
                <div class="form-text mb-3"><?= e(__('timetable.clashes_candidates_help')) ?></div>

                <label class="form-label" for="note_<?= (int) $clash['id'] ?>"><?= e(__('timetable.clashes_note')) ?></label>
                <input type="text" class="form-control mb-3" id="note_<?= (int) $clash['id'] ?>" name="note" maxlength="255">

                <button class="btn btn-primary d-flex align-items-center gap-2">
                  <?= icon('check') ?><span><?= e(__('timetable.clashes_resolve')) ?></span>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('timetable.clashes_history')) ?></h2>
  </div>
  <div class="n-settings-card-body">
    <?php if ($resolutions === []): ?>
      <p class="text-body-secondary mb-0"><?= e(__('timetable.clashes_history_empty')) ?></p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('timetable.clashes_resolved_at')) ?></th>
              <th><?= e(__('timetable.gaps_class')) ?></th>
              <th><?= e(__('timetable.gaps_slot')) ?></th>
              <th><?= e(__('timetable.clashes_printed_teacher')) ?></th>
              <th><?= e(__('timetable.gaps_teacher')) ?></th>
              <th><?= e(__('timetable.clashes_resolved_by')) ?></th>
              <th><?= e(__('timetable.clashes_note')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($resolutions as $resolution): ?>
              <tr>
                <td class="mono small"><?= e((string) $resolution['resolved_at']) ?></td>
                <td class="fw-semibold"><?= e((string) $resolution['class_name']) ?></td>
                <td class="small"><?= e(($dayLabels[(int) $resolution['day_of_week']] ?? '#' . (int) $resolution['day_of_week']) . ' — ' . __('timetable.period') . ' ' . (int) $resolution['period_number']) ?></td>
                <td><?= e((string) ($resolution['printed_teacher'] ?? '')) ?></td>
                <td class="fw-semibold"><?= e((string) $resolution['resolved_teacher_name']) ?></td>
                <td class="small"><?= e((string) ($resolution['resolved_by_name'] ?? '—')) ?></td>
                <td class="small"><?= e((string) ($resolution['note'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
