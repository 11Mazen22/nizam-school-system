<?php
/**
 * Timetable cover board -- one date, every lesson, only genuinely free staff.
 *
 * @var string $date
 * @var int|null $day
 * @var list<array<string,mixed>> $lessons
 * @var list<array<string,mixed>> $teachers
 * @var array<int, array<int, string>> $bookings
 * @var array<int,true> $on_leave
 * @var array<int,string> $dayLabels
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('timetable.covers_title');
$activeNav = 'timetable';
$canManage = hasPermission('timetable.manage');

$leaveCount = 0;
$coveredCount = 0;
foreach ($lessons as $lesson) {
    $leaveCount += (int) $lesson['on_leave'] === 1 ? 1 : 0;
    $coveredCount += !empty($lesson['cover_teacher_id']) ? 1 : 0;
}

ob_start(); ?>
<p class="n-hero-sub"><?= e(__('timetable.covers_subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/timetable" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('timetable.title')) ?></a>
<a href="/timetable/gaps" class="btn btn-outline-secondary"><?= icon('alert-triangle') ?><?= e(__('timetable.gaps_title')) ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php if ($day !== null): ?>
    <span class="badge text-bg-primary"><?= e($dayLabels[$day] ?? '') ?> · <span dir="ltr"><?= e($date) ?></span></span>
  <?php endif; ?>
  <span class="badge text-bg-light"><?= e(__('timetable.covers_lessons')) ?>: <?= count($lessons) ?></span>
  <span class="badge text-bg-warning"><?= e(__('timetable.covers_on_leave')) ?>: <?= $leaveCount ?></span>
  <span class="badge text-bg-success"><?= e(__('timetable.covers_covered')) ?>: <?= $coveredCount ?></span>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start" role="status">
  <?= icon('info-circle', 'n-icon-lg flex-shrink-0') ?>
  <div class="small"><?= e(__('timetable.covers_hint')) ?></div>
</div>

<form method="get" action="/timetable/covers" class="n-filter-panel no-print">
  <div class="n-filter-row">
    <div class="n-filter-field">
      <label class="visually-hidden" for="coverDate"><?= e(__('timetable.covers_date')) ?></label>
      <input id="coverDate" type="date" name="date" class="form-control" value="<?= e($date) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('calendar') ?><?= e(__('common.filter')) ?></button>
  </div>
</form>

<?php $lessons = filterRowsBySearch($lessons, (string) ($_GET['q'] ?? ''), ['class_name', 'subject_ar', 'subject_en', 'teacher_name', 'cover_teacher_name']); ?>
<?php if ($day === null): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('calendar', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(__('timetable.covers_no_school')) ?></h2>
    <p class="text-muted mb-0"><?= e(__('timetable.covers_no_school_hint')) ?></p>
  </div>
<?php elseif ($lessons === []): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('calendar', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(__('timetable.covers_none')) ?></h2>
  </div>
<?php else: ?>
  <?php $listSearchTarget = '#covers-table'; $listSearchAction = '/timetable/covers'; $listSearchHidden = ['date' => $date]; require dirname(__DIR__) . '/partials/list-search.php'; ?>
  <div class="n-data-panel">
    <div class="table-responsive">
      <table id="covers-table" class="table table-hover align-middle n-table-stack">
        <thead>
          <tr>
            <th><?= e(__('timetable.period')) ?></th>
            <th><?= e(__('timetable.gaps_class')) ?></th>
            <th><?= e(__('timetable.gaps_subject')) ?></th>
            <th><?= e(__('timetable.covers_regular')) ?></th>
            <th><?= e(__('timetable.covers_cover')) ?></th>
            <th class="text-end"><?= e(__('timetable.gaps_action')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lessons as $lesson): ?>
            <?php
            $period = (int) $lesson['period_number'];
            $originalId = (int) $lesson['teacher_id'];
            $currentCover = (int) ($lesson['cover_teacher_id'] ?? 0);
            $onLeave = (int) $lesson['on_leave'] === 1;
            $free = [];
            $busy = [];
            foreach ($teachers as $teacher) {
                $teacherId = (int) $teacher['id'];
                if ($teacherId === $originalId) {
                    continue;
                }
                if (isset($on_leave[$teacherId])) {
                    $busy[] = ['teacher' => $teacher, 'why' => __('timetable.covers_leave_short')];
                    continue;
                }
                $held = $bookings[$period][$teacherId] ?? null;
                if ($held !== null) {
                    $busy[] = ['teacher' => $teacher, 'why' => $held];
                    continue;
                }
                $free[] = $teacher;
            }
            ?>
            <tr>
              <td data-label="<?= e(__('timetable.period')) ?>"><?= $period ?></td>
              <td data-label="<?= e(__('timetable.gaps_class')) ?>">
                <a href="/timetable?class_id=<?= (int) $lesson['class_id'] ?>" class="fw-semibold"><?= e((string) $lesson['class_name']) ?></a>
              </td>
              <td data-label="<?= e(__('timetable.gaps_subject')) ?>">
                <?= e(currentLocale() === 'ar' ? (string) $lesson['subject_ar'] : (string) $lesson['subject_en']) ?>
              </td>
              <td data-label="<?= e(__('timetable.covers_regular')) ?>">
                <?= e((string) $lesson['teacher_name']) ?>
                <?php if ($onLeave): ?>
                  <span class="badge text-bg-warning ms-1"><?= e(__('timetable.covers_on_leave_short')) ?></span>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(__('timetable.covers_cover')) ?>">
                <?php if ($currentCover > 0): ?>
                  <span class="badge text-bg-success"><?= e((string) $lesson['cover_teacher_name']) ?></span>
                  <?php if (!empty($lesson['cover_note'])): ?>
                    <div class="small text-muted mt-1"><?= e((string) $lesson['cover_note']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(__('timetable.gaps_action')) ?>">
                <?php if ($currentCover > 0 && $canManage): ?>
                  <form method="post" action="/timetable/covers/remove" class="text-end">
                    <?= CsrfMiddleware::field() ?>
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <input type="hidden" name="timetable_id" value="<?= (int) $lesson['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('timetable.covers_remove')) ?></button>
                  </form>
                <?php elseif ($canManage): ?>
                  <form method="post" action="/timetable/covers/assign" class="d-flex flex-wrap gap-1 justify-content-end">
                    <?= CsrfMiddleware::field() ?>
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <input type="hidden" name="timetable_id" value="<?= (int) $lesson['id'] ?>">
                    <select name="cover_teacher_id" class="form-select form-select-sm" style="max-width:190px;" required aria-label="<?= e(__('timetable.covers_cover')) ?>">
                      <option value="" disabled selected><?= e(__('timetable.covers_pick')) ?></option>
                      <?php if ($free !== []): ?>
                        <optgroup label="<?= e(__('timetable.gaps_free_teachers')) ?>">
                          <?php foreach ($free as $teacher): ?>
                            <option value="<?= (int) $teacher['id'] ?>"><?= e((string) $teacher['full_name']) ?></option>
                          <?php endforeach; ?>
                        </optgroup>
                      <?php endif; ?>
                      <?php if ($busy !== []): ?>
                        <optgroup label="<?= e(__('timetable.gaps_busy_teachers')) ?>">
                          <?php foreach ($busy as $entry): ?>
                            <option value="<?= (int) $entry['teacher']['id'] ?>" disabled>
                              <?= e((string) $entry['teacher']['full_name']) ?> — <?= e((string) $entry['why']) ?>
                            </option>
                          <?php endforeach; ?>
                        </optgroup>
                      <?php endif; ?>
                    </select>
                    <input type="text" name="note" class="form-control form-control-sm" style="max-width:130px;" maxlength="255" placeholder="<?= e(__('timetable.covers_note')) ?>" aria-label="<?= e(__('timetable.covers_note')) ?>">
                    <button type="submit" class="btn btn-sm btn-primary" <?= $free === [] ? 'disabled' : '' ?>><?= e(__('timetable.covers_assign')) ?></button>
                  </form>
                  <?php if ($free === []): ?>
                    <div class="small text-muted mt-1 text-end"><?= e(__('timetable.covers_no_free')) ?></div>
                  <?php endif; ?>
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
