<?php
/**
 * Timetable staff mapping -- printed first names to real staff records.
 *
 * @var list<array<string,mixed>> $rows
 * @var list<array<string,mixed>> $teachers
 * @var int $openGaps
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('timetable.staff_title');
$activeNav = 'timetable';
$canManage = hasPermission('timetable.manage');

ob_start(); ?>
<p class="n-hero-sub"><?= e(__('timetable.staff_subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/timetable" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('timetable.title')) ?></a>
<a href="/timetable/gaps" class="btn btn-outline-secondary"><?= icon('alert-triangle') ?><?= e(__('timetable.gaps_title')) ?><?= $openGaps > 0 ? ' (' . $openGaps . ')' : '' ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="alert alert-info d-flex gap-2 align-items-start" role="status">
  <?= icon('info-circle', 'n-icon-lg flex-shrink-0') ?>
  <div class="small"><?= e(__('timetable.staff_hint')) ?></div>
</div>

<?php if ($rows === []): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('users-group', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(__('timetable.staff_none')) ?></h2>
  </div>
<?php else: ?>
  <div class="n-data-panel">
    <div class="table-responsive">
      <table class="table table-hover align-middle n-table-stack">
        <thead>
          <tr>
            <th><?= e(__('timetable.staff_printed')) ?></th>
            <th><?= e(__('timetable.staff_full_name')) ?></th>
            <th><?= e(__('teachers.code')) ?></th>
            <th class="text-center"><?= e(__('timetable.staff_lessons')) ?></th>
            <th class="text-center"><?= e(__('timetable.staff_gaps')) ?></th>
            <th class="text-end"><?= e(__('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
            $isKnown = $row['teacher_id'] !== null;
            $formId = 'staff-form-' . md5((string) $row['printed_name'] . '-' . (string) ($row['teacher_id'] ?? 'new'));
            ?>
            <tr>
              <td data-label="<?= e(__('timetable.staff_printed')) ?>">
                <strong><?= e((string) $row['printed_name']) ?></strong>
                <?php if (!$isKnown): ?>
                  <div><span class="badge text-bg-warning"><?= e(__('timetable.staff_unregistered')) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($row['aliases'])): ?>
                  <div class="small text-muted mt-1">
                    <?php foreach ($row['aliases'] as $alias): ?><span class="badge text-bg-light"><?= e((string) $alias) ?></span> <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(__('timetable.staff_full_name')) ?>">
                <?php if ($canManage): ?>
                  <input type="text" class="form-control form-control-sm" form="<?= e($formId) ?>" name="full_name"
                         value="<?= e((string) ($row['teacher_name'] ?? '')) ?>" maxlength="150" required
                         placeholder="<?= e(__('timetable.staff_full_name')) ?>">
                <?php else: ?>
                  <?= e((string) ($row['teacher_name'] ?? '—')) ?>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(__('teachers.code')) ?>" class="mono" dir="ltr"><?= e((string) ($row['teacher_code'] ?? '—')) ?></td>
              <td data-label="<?= e(__('timetable.staff_lessons')) ?>" class="text-center"><?= (int) $row['lessons'] ?></td>
              <td data-label="<?= e(__('timetable.staff_gaps')) ?>" class="text-center">
                <?php if ((int) $row['gaps'] > 0): ?>
                  <a href="/timetable/gaps?status=open&teacher=<?= rawurlencode((string) $row['printed_name']) ?>" class="badge text-bg-danger text-decoration-none"><?= (int) $row['gaps'] ?></a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(__('common.actions')) ?>">
                <?php if ($canManage): ?>
                  <form id="<?= e($formId) ?>" method="post"
                        action="<?= $isKnown ? '/timetable/staff/rename' : '/timetable/staff/create' ?>"
                        class="d-flex gap-1 justify-content-end">
                    <?= CsrfMiddleware::field() ?>
                    <input type="hidden" name="printed_name" value="<?= e((string) $row['printed_name']) ?>">
                    <?php if ($isKnown): ?><input type="hidden" name="teacher_id" value="<?= (int) $row['teacher_id'] ?>"><?php endif; ?>
                    <button type="submit" class="btn btn-sm btn-primary">
                      <?= icon($isKnown ? 'check' : 'plus') ?><?= e($isKnown ? __('timetable.staff_save') : __('timetable.staff_create')) ?>
                    </button>
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

<?php if ($canManage): ?>
  <div class="card mt-4">
    <div class="card-body">
      <h2 class="h6 d-flex align-items-center gap-2 mb-2"><?= icon('plus-circle') ?><?= e(__('timetable.staff_create')) ?></h2>
      <p class="text-muted small"><?= e(__('timetable.staff_conflict_hint')) ?></p>
      <form method="post" action="/timetable/staff/create" class="row g-2 align-items-end">
        <?= CsrfMiddleware::field() ?>
        <div class="col-md-4">
          <label class="form-label small" for="newPrintedName"><?= e(__('timetable.staff_printed')) ?></label>
          <input id="newPrintedName" type="text" name="printed_name" class="form-control" maxlength="150" required
                 placeholder="مروة">
        </div>
        <div class="col-md-5">
          <label class="form-label small" for="newFullName"><?= e(__('timetable.staff_full_name')) ?></label>
          <input id="newFullName" type="text" name="full_name" class="form-control" maxlength="150" required>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary w-100"><?= icon('plus') ?><?= e(__('timetable.staff_create')) ?></button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
