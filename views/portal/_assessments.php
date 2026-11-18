<?php
/**
 * Weekly assessment distribution, shared by the teacher, student and family
 * portals. It answers "which subjects are assessed on which day" -- which is
 * not the timetable, because an assessment can fall on a day the subject is
 * not taught.
 *
 * @var list<array<string,mixed>> $assessments
 * @var array<int,string> $dayLabels
 */
?>
<div class="card mb-4">
  <div class="card-header fw-semibold d-flex align-items-center gap-2">
    <?= icon('clipboard-list') ?>
    <?= e(__('portal.weekly_assessments')) ?>
  </div>
  <div class="card-body p-0">
    <?php if ($assessments === []): ?>
      <div class="n-empty py-4">
        <div class="n-empty-icon"><?= icon('clipboard-list') ?></div>
        <p class="text-muted small mb-0"><?= e(__('portal.no_weekly_assessments')) ?></p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('assessments.day')) ?></th>
              <th><?= e(__('classes.class')) ?></th>
              <th><?= e(__('assessments.subjects')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assessments as $assessment): ?>
              <tr>
                <td class="fw-semibold"><?= e($dayLabels[(int) $assessment['day_of_week']] ?? '—') ?></td>
                <td><?= e((string) $assessment['class_name']) ?></td>
                <td>
                  <?php if ((int) $assessment['no_assessment'] === 1): ?>
                    <span class="badge text-bg-secondary"><?= e(__('portal.no_assessment')) ?></span>
                  <?php else: ?>
                    <?= e((string) $assessment['subjects_raw']) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
