<?php
/**
 * @var array $teacher
 * @var array $qualifications
 * @var array $assignments
 * @var int $totalPeriods
 */
$pageTitle = $teacher['full_name'];
$activeNav = 'teachers';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<section class="n-profile-hero" aria-labelledby="teacher-name">
  <div class="n-profile-identity">
    <?php if (!empty($teacher['photo_path'])): ?>
      <img src="/teachers/<?= (int) $teacher['id'] ?>/photo" alt="" class="n-profile-photo">
    <?php else: ?>
      <span class="n-profile-placeholder" aria-hidden="true"><?= e(mb_substr($teacher['full_name'], 0, 1)) ?></span>
    <?php endif; ?>
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h1 class="n-profile-title" id="teacher-name"><?= e($teacher['full_name']) ?></h1>
        <span class="badge <?= $teacher['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
          <?= e($teacher['status'] === 'active' ? __('common.status_active') : __('common.status_archived')) ?>
        </span>
      </div>
      <div class="n-profile-code mono" dir="ltr"><?= e($teacher['teacher_code']) ?></div>
      <?php if ($totalPeriods > 0): ?>
        <div class="mt-2">
          <span class="badge text-bg-primary">
            <?= icon('clock') ?> <?= (int) $totalPeriods ?> <?= e(__('teachers.total_periods')) ?>
          </span>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="n-profile-actions">
    <a href="/teachers" class="btn btn-ghost"><?= icon('chevron-left', 'n-flip-rtl') ?><span><?= e(__('teachers.title')) ?></span></a>
    <?php if (hasPermission('assignments.view')): ?>
      <a href="/assignments" class="btn btn-outline-secondary"><?= icon('clipboard-list') ?><span><?= e(__('assignments.title')) ?></span></a>
    <?php endif; ?>
    <?php if (hasPermission('teachers.edit')): ?>
      <a href="/teachers/<?= (int) $teacher['id'] ?>/edit" class="btn btn-primary"><?= icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
    <?php endif; ?>
    <?php if ($teacher['status'] === 'active' && hasPermission('teachers.archive')): ?>
      <form method="post" action="/teachers/<?= (int) $teacher['id'] ?>/archive" data-confirm="<?= e(__('common.confirm_archive')) ?>">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-outline-danger"><?= icon('archive') ?><span><?= e(__('common.archive')) ?></span></button>
      </form>
    <?php endif; ?>
  </div>
</section>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <section class="card h-100" aria-labelledby="teacher-contact-title">
      <div class="card-header"><?= icon('phone') ?><span id="teacher-contact-title"><?= e(__('teachers.contact_information')) ?></span></div>
      <div class="card-body">
        <dl class="n-detail-list">
          <dt><?= e(__('teachers.full_name')) ?></dt><dd><?= e($teacher['full_name']) ?></dd>
          <dt><?= e(__('teachers.phone')) ?></dt><dd dir="ltr"><?= e($teacher['phone'] ?? '—') ?></dd>
          <dt><?= e(__('teachers.email')) ?></dt><dd><?= e($teacher['email'] ?? '—') ?></dd>
        </dl>
      </div>
    </section>
  </div>
  <div class="col-lg-6">
    <section class="card h-100" aria-labelledby="teacher-subjects-title">
      <div class="card-header">
        <?= icon('book') ?>
        <span id="teacher-subjects-title"><?= e(__('teachers.qualifications')) ?></span>
        <span class="badge text-bg-light ms-auto"><?= count($qualifications) ?></span>
      </div>
      <div class="card-body">
        <?php if (empty($qualifications)): ?>
          <div class="n-empty border-0 py-3">
            <div class="n-empty-icon"><?= icon('book') ?></div>
            <p><?= e(__('teachers.no_qualifications')) ?></p>
          </div>
        <?php else: ?>
          <div class="n-tag-list">
            <?php foreach ($qualifications as $subject): ?>
              <span class="badge text-bg-light border"><?= icon('book') ?> <?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<?php if (hasPermission('assignments.view')): ?>
  <section class="card mb-3" aria-labelledby="teacher-workload-title">
    <div class="card-header justify-content-between flex-wrap">
      <span class="d-flex align-items-center gap-2">
        <?= icon('chalkboard') ?>
        <span id="teacher-workload-title"><?= e(__('teachers.assignments_workload')) ?></span>
      </span>
      <?php if (!empty($assignments)): ?>
        <span class="badge text-bg-primary">
          <?= (int) $totalPeriods ?> <?= e(__('teachers.total_periods')) ?>
        </span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (empty($assignments)): ?>
        <div class="n-empty border-0 py-3">
          <div class="n-empty-icon"><?= icon('chalkboard') ?></div>
          <p class="mb-0 text-muted small"><?= e(__('teachers.no_assignments')) ?></p>
          <?php if (hasPermission('assignments.manage')): ?>
            <a href="/assignments/create" class="btn btn-sm btn-primary mt-3"><?= icon('plus') ?> <?= e(__('assignments.create')) ?></a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle n-table-stack mb-0">
            <thead>
              <tr>
                <th><?= e(__('subjects.title')) ?></th>
                <th><?= e(__('classes.class')) ?></th>
                <th class="text-end"><?= e(__('assignments.weekly_periods')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assignments as $assignment): ?>
                <tr>
                  <td>
                    <strong><?= e(currentLocale() === 'ar' ? $assignment['subject_name_ar'] : $assignment['subject_name_en']) ?></strong>
                  </td>
                  <td>
                    <span class="badge text-bg-light border"><?= e($assignment['class_name']) ?></span>
                  </td>
                  <td class="text-end" dir="ltr">
                    <span class="fw-bold"><?= (int) $assignment['weekly_periods'] ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="table-light fw-bold">
                <td colspan="2"><?= e(__('teachers.total_periods')) ?></td>
                <td class="text-end" dir="ltr"><?= (int) $totalPeriods ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
