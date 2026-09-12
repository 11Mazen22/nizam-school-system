<?php
/** @var array $source @var array $target @var array $rows */
$pageTitle = __('promotion.preview_title');
$activeNav = 'students';
$pageScripts = ['/assets/js/promotion.js'];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<!-- §C "RTL is re-ordered, not mirrored" / §O-32's same reading-order
     discipline applied here: "2025/2026 -> 2026/2027" is Western-numeral
     data, and the DOM order (source, arrow, target) is already correct --
     but left unisolated inside dir="rtl", the bidi algorithm renders the
     arrow ambiguously against the two LTR-formatted labels around it,
     visually reading as if reversed. An explicit dir="ltr" span keeps this
     one fragment in its own bidi run so it reads correctly regardless of
     the page's own direction, exactly like a phone number or URL embedded
     in RTL prose. -->
<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('trending-up', 'n-icon-lg text-body-secondary') ?>
      <?= e($pageTitle) ?>
    </h1>
    <p class="mb-0">
      <span dir="ltr"><?= e($source['label']) ?> &rarr; <?= e($target['label']) ?></span>
    </p>
  </div>
</div>

<?php if (empty($rows)): ?>
  <div class="card">
    <div class="card-body">
      <div class="n-empty py-4">
        <div class="n-empty-icon"><?= icon('inbox') ?></div>
        <p class="mb-0"><?= e(__('promotion.no_active_enrollments')) ?></p>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e(__('promotion.confirm_warning')) ?></span>
  </div>

  <form method="post" action="/students/promotion" data-confirm="<?= e(__('promotion.confirm_warning')) ?>">
    <?= CsrfMiddleware::field() ?>
    <input type="hidden" name="source_year_id" value="<?= (int) $source['id'] ?>">
    <input type="hidden" name="target_year_id" value="<?= (int) $target['id'] ?>">

    <div class="table-responsive">
      <table class="table align-middle bg-white" id="promotionTable">
        <thead>
          <tr>
            <th><?= e(__('students.code')) ?></th>
            <th><?= e(__('students.full_name')) ?></th>
            <th><?= e(__('students.grade')) ?> / <?= e(__('students.class')) ?></th>
            <th><?= e(__('promotion.action')) ?></th>
            <th><?= e(__('promotion.target_class')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td class="mono">
                <input type="hidden" name="enrollment_id[]" value="<?= (int) $row['enrollment_id'] ?>">
                <?= e($row['student_code']) ?>
              </td>
              <td><?= e($row['student_name']) ?></td>
              <td>
                <?= e(currentLocale() === 'ar' ? $row['current_grade_name_ar'] : $row['current_grade_name_en']) ?>
                <?= $row['current_class_name'] !== null ? ' - ' . e($row['current_class_name']) : '' ?>
              </td>
              <td>
                <select name="action[<?= (int) $row['enrollment_id'] ?>]" class="form-select form-select-sm promotion-action">
                  <?php if (!$row['forced_graduate']): ?>
                    <option value="promote" selected><?= e(__('promotion.action_promote')) ?>
                      (<?= e(currentLocale() === 'ar' ? $row['next_grade_name_ar'] : $row['next_grade_name_en']) ?>)</option>
                    <option value="repeat"><?= e(__('promotion.action_repeat')) ?></option>
                    <option value="graduate"><?= e(__('promotion.action_graduate')) ?></option>
                  <?php else: ?>
                    <option value="graduate" selected><?= e(__('promotion.action_graduate')) ?> <?= e(__('promotion.forced_graduate_note')) ?></option>
                    <option value="repeat"><?= e(__('promotion.action_repeat')) ?></option>
                  <?php endif; ?>
                  <option value="transferred"><?= e(__('promotion.action_transferred')) ?></option>
                  <option value="withdrawn"><?= e(__('promotion.action_withdrawn')) ?></option>
                </select>
              </td>
              <td>
                <select name="class_id[<?= (int) $row['enrollment_id'] ?>]" class="form-select form-select-sm promotion-class">
                  <option value=""><?= e(__('promotion.unassigned')) ?></option>
                  <?php foreach ($row['promote_classes'] as $class): ?>
                    <option value="<?= (int) $class['id'] ?>" data-action="promote"><?= e($class['name']) ?></option>
                  <?php endforeach; ?>
                  <?php foreach ($row['repeat_classes'] as $class): ?>
                    <option value="<?= (int) $class['id'] ?>" data-action="repeat"><?= e($class['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
      <?= icon('check') ?>
      <span><?= e(__('promotion.confirm')) ?></span>
    </button>
  </form>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
